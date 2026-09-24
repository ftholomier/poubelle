<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Storage\Audit;

/**
 * Alertes e-mail sur l'activité du site.
 *
 * Une seule adresse (ou plusieurs, séparées par des virgules) reçoit tout ce
 * qui se passe : dépôts, modération, candidatures, messages aux candidats,
 * signalements, sauvegardes, incidents. Chaque type d'alerte se coupe
 * séparément depuis le back-office.
 *
 * Deux garde-fous : jamais de donnée inutile dans le corps du message — on
 * envoie de quoi décider, pas une copie de la fiche — et un plafond horaire,
 * pour qu'une vague de dépôts ne se transforme pas en centaines d'e-mails.
 */
final class Notifier
{
    /** Types d'alerte proposés à l'administrateur, dans l'ordre d'affichage. */
    public const EVENTS = [
        'job.new'        => 'Nouvelle offre déposée',
        'cv.new'         => 'Nouveau CV déposé',
        'moderation'     => 'Dépôt en attente de modération',
        'application'    => 'Candidature envoyée à un employeur',
        'contact'        => 'Message envoyé à un candidat',
        'report'         => 'Signalement d’une annonce',
        'regie'          => 'Conversation avec l’assistant Régie',
        'user.new'       => 'Nouveau compte créé',
        'backup'         => 'Sauvegarde ou restauration',
        'translate'      => 'Plafond de traduction atteint',
        'system'         => 'Incident technique',
    ];

    /** Plafond d'alertes par heure : au-delà, on journalise sans envoyer. */
    private const MAX_PER_HOUR = 60;

    /**
     * Découpe une liste d'adresses saisie à la main, sans rien valider.
     * Virgule, point-virgule ou simple espace font office de séparateur.
     *
     * @return string[]
     */
    public static function split(string $raw): array
    {
        $out = [];
        foreach (preg_split('/[,;\s]+/', $raw) ?: [] as $address) {
            $address = trim($address);
            if ($address !== '') {
                $out[] = $address;
            }
        }
        return array_values(array_unique($out));
    }

    /** @return string[] */
    public static function recipients(): array
    {
        $raw = (string) Config::secret('alert_email', '');
        if (trim($raw) === '') {
            $raw = (string) Config::get('site.email', '');
        }

        return array_values(array_filter(
            self::split($raw),
            static fn(string $a) => filter_var($a, FILTER_VALIDATE_EMAIL) !== false,
        ));
    }

    /** Une alerte est active tant qu'elle n'a pas été décochée. */
    public static function enabled(string $event): bool
    {
        $settings = Config::secret('alert_events', []);
        if (!is_array($settings) || $settings === []) {
            return true;
        }
        return ($settings[$event] ?? '') !== 'off';
    }

    /**
     * @param array<string, string|int|bool> $facts  « Intitulé => valeur »
     * @param string                         $link   chemin ou URL à ouvrir
     */
    public static function notify(string $event, string $title, array $facts = [], string $link = ''): bool
    {
        $body = '';
        foreach ($facts as $label => $value) {
            if (is_bool($value)) {
                $value = $value ? 'oui' : 'non';
            }
            $value = trim((string) $value);
            if ($value !== '') {
                $body .= $label . ' : ' . $value . "\n";
            }
        }
        if ($link !== '') {
            $body .= "\n" . (str_starts_with($link, 'http') ? $link : rtrim((string) Config::get('site.url'), '/') . $link) . "\n";
        }
        return self::message($event, $title, $body);
    }

    /**
     * Alerte dont l'appelant rédige le corps — une conversation avec
     * l'assistant, par exemple —, sous les mêmes garde-fous que notify() :
     * type coupé, aucune adresse, plafond horaire.
     *
     * @param bool $sensitive corps à ne jamais laisser sur le disque si l'envoi échoue
     */
    public static function message(string $event, string $title, string $body, bool $sensitive = false): bool
    {
        if (!self::enabled($event)) {
            return false;
        }

        $recipients = self::recipients();
        if ($recipients === []) {
            return false;
        }

        if (RateLimit::hit('alerts', 'global', self::MAX_PER_HOUR, 3600) > 0) {
            Audit::log('alert.throttled', ['event' => $event]);
            return false;
        }

        $site = (string) Config::get('site.name');
        $base = rtrim((string) Config::get('site.url'), '/');

        $text = $title . "\n" . str_repeat('-', max(8, mb_strlen($title))) . "\n\n"
              . (rtrim($body) !== '' ? rtrim($body) . "\n" : '')
              . "\n—\n" . $site . " · alerte automatique.\n"
              . "Pour changer l'adresse ou couper ce type d'alerte : "
              . $base . "/admin/alertes\n";

        $ok = Mailer::send($recipients, '[' . $site . '] ' . $title, $text, '', [], $sensitive);
        if (!$ok) {
            Audit::log('alert.failed', ['event' => $event, 'error' => Mailer::lastError()]);
        }
        return $ok;
    }

    /**
     * Message de test envoyé depuis le back-office : il prouve la chaîne
     * complète, transport compris.
     */
    public static function test(): array
    {
        $recipients = self::recipients();
        if ($recipients === []) {
            return ['ok' => false, 'message' => 'Aucune adresse d’alerte valide n’est enregistrée.'];
        }

        $site = (string) Config::get('site.name');
        $ok = Mailer::send(
            $recipients,
            '[' . $site . '] Test des alertes',
            "Ce message confirme que les alertes du site fonctionnent.\n\n"
            . 'Transport : ' . (Mailer::transport() === 'smtp' ? 'SMTP authentifié' : 'fonction mail() de PHP') . "\n"
            . 'Destinataires : ' . implode(', ', $recipients) . "\n",
        );

        return [
            'ok' => $ok,
            'message' => $ok
                ? 'Message de test envoyé à ' . implode(', ', $recipients) . ' via '
                  . (Mailer::transport() === 'smtp' ? 'SMTP' : 'mail()') . '.'
                : ('Échec de l’envoi : ' . (Mailer::lastError() ?: 'raison inconnue')),
        ];
    }
}
