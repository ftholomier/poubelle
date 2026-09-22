<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Domain\JobRepository;
use App\Storage\Audit;
use App\Storage\Index;

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
    /** Contrats considérés comme durables : on leur laisse plus de temps. */
    private const LONG_CONTRACTS = ['cdi', 'cdd', 'permanent', 'temps plein', 'temps partiel'];

    /** Durée de vie, en jours, d'après le type de contrat. */
    public static function lifetimeDays(array $job): int
    {
        $short = (int) Config::get('jobs.lifetime_days', 30);
        $long  = (int) Config::get('jobs.lifetime_days_long', 45);

        foreach ((array) ($job['contract'] ?? []) as $contract) {
            $needle = mb_strtolower(trim((string) $contract));
            foreach (self::LONG_CONTRACTS as $word) {
                if ($needle !== '' && str_starts_with($needle, $word)) {
                    return $long;
                }
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

    public static function isExpired(array $item, ?int $now = null): bool
    {
        $expires = trim((string) ($item['expires_at'] ?? ''));
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
        $expires = strtotime((string) ($item['expires_at'] ?? '')) ?: null;
        if ($expires === null) {
            return false;
        }
        return $expires < ($now ?? time()) - $days * 86400;
    }

    /**
     * Passe en « expirée » toute annonce publiée dont la date de fin est
     * dépassée. Idempotent : appelable aussi souvent qu'on veut.
     *
     * @return int nombre d'annonces modifiées
     */
    public static function expire(): int
    {
        $changed = 0;
        foreach (JobRepository::all() as $job) {
            if (($job['status'] ?? '') !== 'publish') {
                continue;
            }
            if (trim((string) ($job['expires_at'] ?? '')) === '') {
                // Annonce antérieure à la mise en place des durées de vie.
                $job['expires_at'] = self::expiresAt($job);
            }
            if (!self::isExpired($job)) {
                if (($job['expires_at'] ?? '') !== '') {
                    JobRepository::save($job);
                }
                continue;
            }
            $job['status'] = 'expired';
            JobRepository::save($job);
            $changed++;
        }

        if ($changed > 0) {
            Index::rebuild('jobs');
            Index::rebuild('employers');
            Audit::log('job.expired_batch', ['count' => $changed]);
        }
        return $changed;
    }
}
