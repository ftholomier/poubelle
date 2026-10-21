<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Domain\CvRepository;
use App\Domain\JobRepository;
use App\Storage\Audit;
use App\Storage\Index;

/**
 * Alertes e-mail aux candidats : les nouvelles offres qui correspondent à
 * leur CV.
 *
 * Consentement d'abord. Un CV ne reçoit d'alerte que si son auteur a dit oui :
 * case cochée au dépôt, ou réponse au message de demande envoyé une seule fois
 * aux CV déposés avant l'existence des alertes. Chaque e-mail porte un lien de
 * désinscription en un clic. Un récapitulatif ne part que s'il contient au
 * moins une offre : jamais d'e-mail vide.
 *
 * État, dans le CV : alerts = {status: yes|no|'', invited_at, last_sent_at}.
 */
final class JobAlerts
{
    /** Fenêtre maximale d'un récapitulatif : un abonné absent ne reçoit pas un mois d'archives. */
    private const LOOKBACK = 7 * 86400;
    /** Offres détaillées par message. */
    private const MAX_JOBS = 10;
    /** Demandes d'accord envoyées par passage : l'hébergeur et les filtres anti-spam n'aiment pas les rafales. */
    private const INVITES_PER_RUN = 40;

    public static function enabled(): bool
    {
        return (bool) Config::get('alerts.enabled', true);
    }

    /** Jeton des liens : signé, propre à chaque CV, sans rien stocker. */
    public static function token(string $cvId): string
    {
        return substr(hash_hmac('sha256', 'alerts|' . $cvId, Secrets::appKey()), 0, 32);
    }

    public static function link(string $cvId, string $action): string
    {
        return rtrim((string) Config::get('site.url'), '/') . I18n::url('/alertes-emploi', 'fr')
             . '?cv=' . rawurlencode($cvId) . '&t=' . self::token($cvId) . '&choix=' . $action;
    }

    /** Enregistre le choix d'un candidat depuis un lien. */
    public static function decide(string $cvId, string $token, bool $yes): bool
    {
        if (!hash_equals(self::token($cvId), $token)) {
            return false;
        }
        $cv = CvRepository::find($cvId);
        if ($cv === null) {
            return false;
        }
        $alerts = (array) ($cv['alerts'] ?? []);
        $alerts['status'] = $yes ? 'yes' : 'no';
        $alerts['decided_at'] = date('c');
        // Pas de rattrapage de tout l'historique au premier récapitulatif.
        $alerts['last_sent_at'] = $alerts['last_sent_at'] ?? date('c');
        $cv['alerts'] = $alerts;
        CvRepository::save($cv);
        Audit::log($yes ? 'alerts.opt_in' : 'alerts.opt_out', ['cv' => $cvId]);
        return true;
    }

    private static function email(array $cv): string
    {
        $email = trim((string) ($cv['contact']['email'] ?? ''));
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : '';
    }

    /** Une offre correspond à un CV : même fiche métier, sinon le métier du CV figure dans l'intitulé. */
    public static function matches(array $cv, array $job): bool
    {
        $cvTrades = Trades::owners((string) ($cv['title'] ?? ''));
        $jobTrades = Trades::owners((string) ($job['title'] ?? ''));
        if ($cvTrades !== [] && array_intersect($cvTrades, $jobTrades) !== []) {
            return true;
        }
        $title = Index::haystack([(string) ($cv['title'] ?? '')]);
        return $cvTrades === [] && mb_strlen($title) >= 4
            && str_contains(' ' . Index::haystack([(string) ($job['title'] ?? '')]) . ' ', ' ' . $title . ' ');
    }

    /**
     * Récapitulatif aux abonnés : les offres publiées depuis leur dernier
     * envoi. Rien à leur montrer : rien ne part.
     *
     * @return array{sent:int, skipped:int}
     */
    public static function digest(?int $now = null): array
    {
        $now ??= time();
        $stats = ['sent' => 0, 'skipped' => 0];
        if (!self::enabled()) {
            return $stats;
        }

        $jobs = array_values(array_filter(JobRepository::all(), static fn(array $j): bool
            => ($j['status'] ?? '') === 'publish'
            && (int) strtotime((string) ($j['published_at'] ?: $j['created_at'])) >= $now - self::LOOKBACK));
        if ($jobs === []) {
            return $stats;
        }

        foreach (CvRepository::all() as $cv) {
            if (($cv['alerts']['status'] ?? '') !== 'yes' || self::email($cv) === '') {
                continue;
            }
            $since = max($now - self::LOOKBACK, (int) strtotime((string) ($cv['alerts']['last_sent_at'] ?? '')));
            $fresh = array_values(array_filter($jobs, static fn(array $j): bool
                => (int) strtotime((string) ($j['published_at'] ?: $j['created_at'])) > $since
                && self::matches($cv, $j)));
            if ($fresh === []) {
                $stats['skipped']++;
                continue;
            }
            if (Mailer::send(self::email($cv), self::subject(count($fresh)), self::body($cv, $fresh))) {
                $cv['alerts']['last_sent_at'] = date('c', $now);
                CvRepository::save($cv);
                $stats['sent']++;
            }
        }
        if ($stats['sent'] > 0) {
            Audit::log('alerts.digest', $stats);
        }
        return $stats;
    }

    /**
     * Demande d'accord, une seule fois, aux CV déposés avant les alertes.
     * Aucune relance : sans réponse, le candidat ne reçoit plus rien.
     */
    public static function invite(?int $limit = null): int
    {
        if (!self::enabled()) {
            return 0;
        }
        $sent = 0;
        foreach (CvRepository::all() as $cv) {
            if ($sent >= ($limit ?? self::INVITES_PER_RUN)) {
                break;
            }
            $alerts = (array) ($cv['alerts'] ?? []);
            if (($alerts['status'] ?? '') !== '' || !empty($alerts['invited_at']) || self::email($cv) === ''
                || ($cv['status'] ?? '') !== 'publish') {
                continue;
            }
            $alerts['invited_at'] = date('c');   // noté avant l'envoi : jamais deux demandes
            $cv['alerts'] = $alerts;
            CvRepository::save($cv);
            $site = (string) Config::get('site.name');
            $name = trim((string) ($cv['name'] ?? ''));
            Mailer::send(self::email($cv), '[' . $site . '] Recevoir les offres qui correspondent à votre CV ?',
                "Bonjour" . ($name !== '' ? ' ' . $name : '') . ",\n\n"
                . "Vous avez déposé votre CV sur {$site}. Le site peut désormais vous prévenir par e-mail quand une "
                . "offre d'emploi correspondant à votre métier (« " . (string) ($cv['title'] ?? '') . " ») est publiée : "
                . "un récapitulatif au plus par jour, seulement s'il y a de nouvelles offres.\n\n"
                . "Oui, je veux recevoir ces offres :\n" . self::link((string) $cv['id'], 'oui') . "\n\n"
                . "Non merci :\n" . self::link((string) $cv['id'], 'non') . "\n\n"
                . "Sans réponse de votre part, vous ne recevrez aucune alerte. Ce message ne sera pas renvoyé.\n");
            $sent++;
        }
        if ($sent > 0) {
            Audit::log('alerts.invited', ['count' => $sent]);
        }
        return $sent;
    }

    private static function subject(int $n): string
    {
        return '[' . (string) Config::get('site.name') . '] '
             . ($n > 1 ? $n . ' nouvelles offres pour votre profil' : 'Une nouvelle offre pour votre profil');
    }

    private static function body(array $cv, array $jobs): string
    {
        $base = rtrim((string) Config::get('site.url'), '/');
        $out = "Bonjour,\n\nDe nouvelles offres correspondent à votre CV (« " . (string) ($cv['title'] ?? '') . " ») :\n";
        foreach (array_slice($jobs, 0, self::MAX_JOBS) as $job) {
            $where = trim((string) ($job['location']['city'] ?? '') ?: (string) ($job['location']['region'] ?? ''));
            $out .= "\n• " . (string) $job['title']
                  . ((string) ($job['company']['name'] ?? '') !== '' ? ' — ' . $job['company']['name'] : '')
                  . ($where !== '' ? ' (' . $where . ')' : '')
                  . "\n  " . $base . I18n::url('/offre/' . $job['slug'], 'fr') . "\n";
        }
        if (count($jobs) > self::MAX_JOBS) {
            $out .= "\n… et " . (count($jobs) - self::MAX_JOBS) . " autre(s) : " . $base . I18n::url('/offres', 'fr') . "\n";
        }
        return $out . "\nVous recevez ce message parce que vous avez demandé à être alerté des offres "
             . "correspondant à votre CV.\nNe plus recevoir ces alertes : " . self::link((string) $cv['id'], 'stop') . "\n";
    }

    /** Chiffres pour le back-office. */
    public static function stats(): array
    {
        $out = ['yes' => 0, 'no' => 0, 'pending' => 0, 'not_invited' => 0, 'unpublished' => 0];
        foreach (CvRepository::all() as $cv) {
            $status = (string) ($cv['alerts']['status'] ?? '');
            if ($status === 'yes' || $status === 'no') {
                $out[$status]++;
            } elseif (!empty($cv['alerts']['invited_at'])) {
                $out['pending']++;
            } elseif (self::email($cv) !== '') {
                // Seuls les CV publiés reçoivent la demande : les autres sont comptés à part.
                $out[($cv['status'] ?? '') === 'publish' ? 'not_invited' : 'unpublished']++;
            }
        }
        return $out;
    }
}
