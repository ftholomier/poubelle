<?php
declare(strict_types=1);

namespace LD;

/**
 * Tâches planifiées : rappels de rendez-vous (e-mail + SMS) la veille, récapitulatifs des discussions IA, avis Google,
 * vérification des mises à jour, ménage des caches.
 * À lancer une fois par heure : `php app/cron.php` ou via l'URL secrète affichée dans le back-office.
 */
final class Cron
{
    public static function url(): string
    {
        return abs_url('/cron/' . substr(hash_hmac('sha256', 'cron', Settings::appSecret()), 0, 24));
    }

    public static function run(): string
    {
        $out = [];
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        // Les rappels partent à partir de 17 h la veille (ou au premier passage après).
        if ((int) date('G') >= 17) {
            foreach (Booking::upcoming() as $b) {
                if ($b['date'] !== $tomorrow || !empty($b['reminded'])) {
                    continue;
                }
                $sent = [];
                if (!empty($b['email'])) {
                    $html = '<p>Petit rappel : on se retrouve <b>demain à ' . e($b['time']) . '</b> (' . e($b['mode']) . ').</p>'
                        . (str_contains(mb_strtolower($b['mode']), 'visio') && Settings::get('booking.visio_link') ? '<p>Lien visio : <a href="' . e(Settings::get('booking.visio_link')) . '">' . e(Settings::get('booking.visio_link')) . '</a></p>' : '')
                        . (!str_contains(mb_strtolower($b['mode']), 'visio') && Settings::get('booking.address') ? '<p>Adresse : ' . e(Settings::get('booking.address')) . '</p>' : '')
                        . '<p>Un empêchement ? <a href="' . e(abs_url('/rdv/annuler/' . $b['id'] . '/' . $b['token'])) . '">Annule ou décale ton créneau</a>.</p><p>À demain ☕<br>Fred</p>';
                    if (Mailer::send($b['email'], 'Rappel : notre café de demain à ' . $b['time'], Mailer::layout('À demain ' . $b['name'] . ' !', $html))) {
                        $sent[] = 'email';
                    }
                }
                if (!empty($b['sms']) && !empty($b['phone'])) {
                    $text = strtr((string) Settings::get('sms.reminder_text', ''), ['{time}' => $b['time'], '{mode}' => mb_strtolower($b['mode']), '{name}' => $b['name']]);
                    if (Sms::send($b['phone'], $text)) {
                        $sent[] = 'sms';
                    }
                }
                Booking::markReminded($b['id']);
                $out[] = 'Rappel ' . $b['id'] . ' : ' . ($sent ? implode(' + ', $sent) : 'aucun canal disponible');
            }
        }
        // Discussions IA terminées : récapitulatif à Fred.
        if ($n = Leads::flushChats()) {
            $out[] = $n . ' récapitulatif(s) de discussion IA envoyé(s)';
        }
        // Avis Google : rafraîchis une fois par jour.
        if ($g = GoogleReviews::autoSync()) {
            $out[] = $g;
        }
        // Mises à jour du site : vérification auprès de GitHub (sans rien installer).
        if ($u = Updater::checkIfDue()) {
            $out[] = $u;
        }
        // Ménage : sessions et limites de débit expirées.
        foreach (glob(Store::path('cache/rl/*.json')) ?: [] as $f) {
            if (filemtime($f) < time() - 86400) {
                @unlink($f);
            }
        }
        Store::write('cache/cron.json', ['last_run' => date('c')]);
        $out[] = 'OK ' . date('c');
        return implode("\n", $out) . "\n";
    }
}
