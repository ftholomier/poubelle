<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Domain\JobRepository;
use App\Storage\Audit;
use App\Storage\Index;
use App\Storage\Json;

/**
 * Durée de vie d'une annonce.
 *
 * Une offre déposée reste en ligne un mois — quarante-cinq jours pour un poste
 * permanent — puis passe en « expirée » : elle disparaît des listes et des
 * compteurs, sa fiche reste consultable sous un bandeau et en noindex, et
 * l'archive finit par répondre 410. Sans cela, la liste affiche des annonces
 * vieilles de plusieurs années et la date de fin manque aux données
 * structurées JobPosting.
 */
final class JobLifecycle
{
    /**
     * Contrats considérés comme durables : on leur laisse plus de temps.
     *
     * « CDD d'usage » n'en fait pas partie : c'est le contrat au cachet du
     * spectacle, par nature court. Le préfixe seul l'aurait attrapé, d'où la
     * comparaison exacte après normalisation.
     */
    private const LONG_CONTRACTS = ['cdi', 'cdd', 'permanent', 'temps plein', 'temps partiel',
                                    'cdi intermittent'];

    /** Durée de vie, en jours, d'après le type de contrat. */
    public static function lifetimeDays(array $job): int
    {
        $short = (int) Config::get('jobs.lifetime_days', 30);
        $long  = (int) Config::get('jobs.lifetime_days_long', 45);

        foreach ((array) ($job['contract'] ?? []) as $contract) {
            $needle = mb_strtolower(trim((string) $contract));
            if ($needle !== '' && in_array($needle, self::LONG_CONTRACTS, true)) {
                return $long;
            }
        }
        return $short;
    }

    /**
     * Date de fin à inscrire sur une annonce qui n'en a pas.
     *
     * Une date de début annoncée dans le futur commande : un tournage prévu en
     * janvier reste d'actualité même s'il a été publié six mois plus tôt.
     * L'annonce tient alors jusqu'à une semaine après le démarrage.
     */
    public static function expiresAt(array $job, ?int $from = null): string
    {
        $from ??= (int) (strtotime((string) ($job['published_at'] ?: $job['created_at'] ?: 'now')) ?: time());
        $expires = $from + self::lifetimeDays($job) * 86400;

        $starts = strtotime((string) ($job['starts_at'] ?? ''));
        if ($starts !== false && $starts > $expires) {
            $expires = $starts + 7 * 86400;
        }

        return date('c', $expires);
    }

    /** Complète une annonce avant enregistrement : date de fin systématique. */
    public static function stamp(array $job): array
    {
        if (($job['status'] ?? '') === 'publish' && trim((string) ($job['expires_at'] ?? '')) === '') {
            $job['expires_at'] = self::expiresAt($job);
        }
        return $job;
    }

    /**
     * Instant où le site a commencé à appliquer les durées de vie.
     *
     * Il sert de point de départ au délai de grâce des annonces reprises de
     * WordPress : sans lui, elles auraient toutes été datées dans le passé et
     * archivées à la seconde de la mise en ligne.
     */
    public static function startedAt(): int
    {
        static $at = null;
        if ($at !== null) {
            return $at;
        }

        $dir = Config::path('data') . '/private';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir . '/lifecycle.json';

        $state = Json::read($file);
        $stored = (int) ($state['started_at'] ?? 0);
        if ($stored > 0) {
            return $at = $stored;
        }

        $at = time();
        Json::write($file, ['started_at' => $at, 'noted' => date('c', $at)]);
        Audit::log('job.lifecycle_started', ['grace_days' => (int) Config::get('jobs.legacy_grace_days', 30)]);
        return $at;
    }

    /**
     * Date de fin réellement appliquée à une annonce.
     *
     * Trois cas. Une date inscrite fait foi. Une annonce sans date mais assez
     * récente prend sa date de publication plus sa durée de vie. Une annonce
     * ancienne — tout l'historique repris de l'ancien site — bénéficie du délai
     * de grâce : elle reste en ligne le temps que l'exploitant fasse le tri.
     */
    public static function effectiveExpiry(array $item): string
    {
        $expires = trim((string) ($item['expires_at'] ?? ''));
        if ($expires !== '') {
            return $expires;
        }
        if (trim((string) ($item['published_at'] ?? $item['created_at'] ?? '')) === '') {
            return '';
        }

        $natural = strtotime(self::expiresAt($item));
        $grace = self::startedAt() + max(0, (int) Config::get('jobs.legacy_grace_days', 30)) * 86400;

        return date('c', max((int) $natural, $grace));
    }

    /** Une annonce est-elle périmée ? */
    public static function isExpired(array $item, ?int $now = null): bool
    {
        $expires = self::effectiveExpiry($item);
        if ($expires === '') {
            return false;
        }
        $timestamp = strtotime($expires);
        return $timestamp !== false && $timestamp < ($now ?? time());
    }

    /** Une annonce très ancienne n'a plus à répondre 200 : 410 et hors index. */
    public static function isArchived(array $item, ?int $now = null): bool
    {
        $days = (int) Config::get('jobs.archive_after_days', 180);
        if ($days <= 0) {
            return false;
        }
        // Même règle que pour l'expiration, délai de grâce compris.
        $raw = self::effectiveExpiry($item);
        if ($raw === '') {
            return false;
        }

        $expires = strtotime($raw);
        return $expires !== false && $expires < ($now ?? time()) - $days * 86400;
    }

    /**
     * Inscrit la date de fin appliquée sur les annonces qui n'en ont pas.
     * Rien ne disparaît : c'est la même date que celle déjà utilisée pour
     * l'affichage, rendue visible au back-office.
     *
     * @return int nombre d'annonces datées
     */
    public static function stampUndated(): int
    {
        $count = 0;
        foreach (JobRepository::all() as $job) {
            if (($job['status'] ?? '') !== 'publish' || trim((string) ($job['expires_at'] ?? '')) !== '') {
                continue;
            }
            $job['expires_at'] = self::effectiveExpiry($job);
            if ($job['expires_at'] !== '') {
                JobRepository::save($job);
                $count++;
            }
        }
        if ($count > 0) {
            Index::rebuild('jobs');
            Audit::log('job.dated_batch', ['count' => $count]);
        }
        return $count;
    }

    /**
     * Archive tout de suite l'historique sans date de fin, sans attendre la
     * fin du délai de grâce. Décision de l'exploitant, jamais automatique.
     *
     * @return int nombre d'annonces archivées
     */
    public static function archiveUndated(): int
    {
        $count = 0;
        foreach (JobRepository::all() as $job) {
            if (($job['status'] ?? '') !== 'publish' || trim((string) ($job['expires_at'] ?? '')) !== '') {
                continue;
            }
            $job['expires_at'] = self::expiresAt($job);   // date naturelle, déjà passée
            $job['status'] = 'expired';
            JobRepository::save($job);
            $count++;
        }
        if ($count > 0) {
            Index::rebuild('jobs');
            Index::rebuild('employers');
            Audit::log('job.archived_batch', ['count' => $count]);
        }
        return $count;
    }

    /** Annonces publiées auxquelles il manque encore une date de fin. */
    public static function undated(): int
    {
        $count = 0;
        foreach (Index::load('jobs') as $row) {
            if (($row['status'] ?? '') === 'publish' && trim((string) ($row['expires_at'] ?? '')) === '') {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Passe en « expirée » toute annonce publiée dont la date de fin est
     * dépassée. Idempotent : appelable aussi souvent qu'on veut.
     *
     * @return int nombre d'annonces modifiées
     */
    public static function expire(): int
    {
        $expired = 0;
        $dated = 0;

        foreach (JobRepository::all() as $job) {
            if (($job['status'] ?? '') !== 'publish') {
                continue;
            }

            $touched = false;
            if (trim((string) ($job['expires_at'] ?? '')) === '') {
                // Annonce antérieure à la mise en place des durées de vie :
                // on inscrit la date réellement appliquée, délai de grâce
                // compris, pour qu'elle devienne lisible au back-office.
                $job['expires_at'] = self::effectiveExpiry($job);
                $touched = $job['expires_at'] !== '';
                $dated += $touched ? 1 : 0;
            }

            if (self::isExpired($job)) {
                $job['status'] = 'expired';
                $touched = true;
                $expired++;
            }
            if ($touched) {
                JobRepository::save($job);
            }
        }

        // Dater une annonce change ce que montre la liste autant que
        // l'expirer : l'index doit suivre dans les deux cas.
        if ($expired > 0 || $dated > 0) {
            Index::rebuild('jobs');
            Index::rebuild('employers');
            Audit::log('job.expired_batch', ['expirees' => $expired, 'datees' => $dated]);
        }
        return $expired;
    }
}
