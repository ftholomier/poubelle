<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Core\Request;
use App\Core\Str;
use App\Core\Url;

/**
 * Demandes de devis (diffusées aux pros du secteur) et messages directs à un pro.
 * Modération hybride : envoi automatique si l'anti-spam est serein, sinon file de modération.
 */
final class Leads
{
    public const REQUEST_STATUSES = ['pending' => 'À modérer', 'diffused' => 'Diffusée', 'rejected' => 'Refusée', 'spam' => 'Spam', 'closed' => 'Clôturée'];
    public const MESSAGE_STATUSES = ['pending' => 'À modérer', 'delivered' => 'Transmis', 'rejected' => 'Refusé', 'spam' => 'Spam'];
    public const EVENT_TYPES = ['mariage' => 'Mariage', 'anniversaire' => 'Anniversaire', 'entreprise' => 'Soirée d\'entreprise / séminaire', 'soiree' => 'Soirée privée', 'bapteme' => 'Baptême / communion', 'association' => 'Fête associative / publique', 'autre' => 'Autre'];

    private static function mode(string $kind): string
    {
        return (string) Settings::get('moderation.' . $kind . '_mode', 'hybrid');
    }

    private static function statusFor(string $kind, array $spam): string
    {
        if (in_array($spam['decision'], ['spam', 'blocked'], true)) {
            return 'spam';
        }
        return match (self::mode($kind)) {
            'auto' => $spam['decision'] === 'clean' || $spam['decision'] === 'review' ? 'go' : 'pending',
            'manual' => 'pending',
            default => $spam['decision'] === 'clean' ? 'go' : 'pending',
        };
    }

    // ------------------------------------------------------- demandes de devis

    public static function createRequest(array $in, array $spam): array
    {
        $status = self::statusFor('requests', $spam);
        $commune = !empty($in['insee']) ? Geo::commune((string) $in['insee']) : null;
        $rec = Store::requests()->insert([
            'status' => $status === 'go' ? 'pending' : $status,
            'source' => $in['source'] ?? 'form',
            'client' => [
                'type' => $in['client_type'] ?? 'particulier',
                'first_name' => $in['first_name'] ?? '',
                'last_name' => $in['last_name'] ?? '',
                'company' => $in['company'] ?? '',
                'email' => $in['email'],
                'phone' => $in['phone'] ?? '',
                'city' => $in['client_city'] ?? '',
            ],
            'event' => [
                'type' => $in['event_type'] ?? 'autre',
                'date' => $in['date'] ?? '',
                'date_flexible' => !empty($in['date_flexible']),
                'place' => $in['place'] ?? '',
                'city' => $commune['n'] ?? ($in['city'] ?? ''),
                'insee' => $commune['insee'] ?? '',
                'dep' => $commune['d'] ?? Geo::depCode((string) ($in['dep'] ?? '')),
                'lat' => $commune ? (float) $commune['la'] : null,
                'lng' => $commune ? (float) $commune['lo'] : null,
                'guests' => $in['guests'] ?? '',
                'budget' => $in['budget'] ?? '',
                'categories' => $in['categories'] ?? [],
                'message' => $in['message'],
            ],
            'target_pros' => array_values(array_map('intval', (array) ($in['target_pros'] ?? []))),
            'recipients' => [],
            'spam' => ['score' => $spam['score'], 'decision' => $spam['decision'], 'reasons' => $spam['reasons'], 'ai' => $spam['ai'] ?? null],
            'ip_hash' => Request::ipHash(),
            'ua' => Str::limit(Request::userAgent(), 180, ''),
            'consent_at' => date('c'),
        ]);
        Stats::hit('devis');
        if ($status === 'go') {
            $rec = self::diffuse((int) $rec['id']) ?? $rec;
        } elseif ($status === 'pending') {
            Notify::admin('request_pending', 'Demande de devis à modérer', ($rec['client']['first_name'] ?? '') . ' — ' . ($rec['event']['city'] ?? '') . ' : ' . Str::limit((string) $rec['event']['message'], 140), Url::admin('demandes/' . $rec['id']), 'warning');
        }
        self::confirmToClient($rec);
        return $rec;
    }

    /**
     * Diffuse une demande : destinataires calculés (secteur + métiers) ou imposés.
     * @param int[]|null $recipients
     */
    public static function diffuse(int $id, ?array $recipients = null, ?string $by = null): ?array
    {
        $r = Store::requests()->get($id);
        if (!$r) {
            return null;
        }
        $ev = $r['event'];
        if ($recipients === null) {
            if (!empty($r['target_pros'])) {
                $recipients = array_values(array_filter($r['target_pros'], static fn ($pid) => isset(Pros::publicIndex()[$pid])));
            } else {
                $recipients = Pros::recipientsFor((string) ($ev['dep'] ?? ''), $ev['lat'] ?? null, $ev['lng'] ?? null, (array) ($ev['categories'] ?? []), (int) Settings::get('moderation.max_recipients', 40), (int) Settings::get('moderation.radius_km', 50));
                if (!$recipients && !empty($ev['categories'])) {
                    // aucun pro du métier dans le secteur : on élargit à tous les métiers
                    $recipients = Pros::recipientsFor((string) ($ev['dep'] ?? ''), $ev['lat'] ?? null, $ev['lng'] ?? null, [], (int) Settings::get('moderation.max_recipients', 40), (int) Settings::get('moderation.radius_km', 50));
                }
            }
        }
        $recipients = array_values(array_unique(array_map('intval', $recipients)));
        $already = array_map('intval', (array) ($r['recipients'] ?? []));
        $new = array_diff($recipients, $already);
        foreach ($new as $pid) {
            $pro = Store::pros()->get($pid);
            if (!$pro || empty($pro['email']) || empty($pro['settings']['notify_requests'] ?? true)) {
                continue;
            }
            $m = Mail::build('request_to_pro', [
                'prenom' => $pro['first_name'] ?: Pros::displayName($pro),
                'evenement' => self::EVENT_TYPES[$ev['type'] ?? 'autre'] ?? 'Événement',
                'lieu_court' => !empty($ev['city']) ? Geo::inCity((string) $ev['city']) : '',
            ], [
                'details' => Mail::details(self::requestDetails($r, true)),
                'bouton_url' => '/espace-pro/demandes/' . $id . '/',
                'bouton_label' => 'Voir la demande dans mon espace',
            ]);
            Mail::queue((string) $pro['email'], $m['subject'], $m['html'], ['priority' => 2, 'reply_to' => $r['client']['email'] ?? null, 'ref' => 'request:' . $id]);
            Notify::pro($pid, '🎉 Nouvelle demande de devis', (self::EVENT_TYPES[$ev['type'] ?? 'autre'] ?? 'Événement') . ' ' . (!empty($ev['city']) ? Geo::inCity((string) $ev['city']) : ''), '/espace-pro/demandes/' . $id . '/');
        }
        $rec = Store::requests()->update($id, [
            'status' => 'diffused',
            'recipients' => array_values(array_unique(array_merge($already, $recipients))),
            'diffused_at' => date('c'),
            'moderated_by' => $by,
        ]);
        Notify::admin('request_new', 'Demande diffusée à ' . count($recipients) . ' pro(s)', Str::limit((string) ($ev['message'] ?? ''), 140), Url::admin('demandes/' . $id), 'success');
        Logger::info('Demande diffusée', ['id' => $id, 'pros' => count($recipients)]);
        return $rec;
    }

    public static function requestDetails(array $r, bool $forPro): array
    {
        $ev = $r['event'];
        $c = $r['client'];
        if (!$forPro) {
            // copie envoyée à l'adresse saisie dans le formulaire : aucun texte libre (sinon relais de spam possible)
            return [
                'Événement' => self::EVENT_TYPES[$ev['type'] ?? 'autre'] ?? '',
                'Date' => !empty($ev['date']) ? date_fr((string) $ev['date'], 'long') : '',
                'Ville' => !empty($ev['insee']) ? (string) ($ev['city'] ?? '') . (!empty($ev['dep']) ? ' (' . $ev['dep'] . ')' : '') : '',
                'Prestations' => implode(', ', array_map([Categories::class, 'name'], (array) ($ev['categories'] ?? []))),
            ];
        }
        $rows = [
            'Événement' => self::EVENT_TYPES[$ev['type'] ?? 'autre'] ?? '',
            'Date' => !empty($ev['date']) ? date_fr((string) $ev['date'], 'long') . (!empty($ev['date_flexible']) ? ' (flexible)' : '') : ($ev['date_text'] ?? ''),
            'Lieu' => trim(($ev['place'] ?? '') . (!empty($ev['city']) && ($ev['place'] ?? '') !== $ev['city'] ? ' — ' . $ev['city'] : '') . (!empty($ev['dep']) ? ' (' . $ev['dep'] . ')' : '')),
            'Invités' => (string) ($ev['guests'] ?? ''),
            'Budget' => (string) ($ev['budget'] ?? ''),
            'Prestations' => implode(', ', array_map([Categories::class, 'name'], (array) ($ev['categories'] ?? []))),
            'Demande' => (string) ($ev['message'] ?? ''),
        ];
        if ($forPro) {
            $rows += [
                'Client' => trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '') . (!empty($c['company']) ? ' — ' . $c['company'] : '')),
                'Email' => (string) ($c['email'] ?? ''),
                'Téléphone' => (string) ($c['phone'] ?? ''),
            ];
        }
        return $rows;
    }

    /**
     * Prénom affiché dans un email envoyé à une adresse saisie par un visiteur : seulement s'il ressemble
     * à un prénom (lettres, espaces, tirets, apostrophes), pour qu'on ne puisse pas y glisser un lien.
     */
    public static function greetingName(string $name): string
    {
        $name = trim($name);
        return preg_match("/^[\p{L}\p{M}][\p{L}\p{M}' .\-]{0,39}$/u", $name) ? $name : 'à vous';
    }

    private static function confirmToClient(array $r): void
    {
        if (in_array($r['status'], ['spam'], true) || empty($r['client']['email'])) {
            return;
        }
        $n = count($r['recipients'] ?? []);
        $suite = $r['status'] === 'diffused'
            ? ($n > 0 ? "Elle a été transmise à $n professionnel" . ($n > 1 ? 's' : '') . " de votre secteur." : 'Elle va être transmise aux professionnels de votre secteur.')
            : 'Elle sera transmise aux professionnels de votre secteur après une rapide vérification par notre équipe.';
        Mail::send((string) $r['client']['email'], 'request_confirmation', ['prenom' => self::greetingName((string) ($r['client']['first_name'] ?? ''))], ['details' => Mail::details(self::requestDetails($r, false)), 'suite' => e($suite)]);
    }

    // ---------------------------------------------------------- messages directs

    public static function createMessage(array $pro, array $in, array $spam): array
    {
        $status = self::statusFor('messages', $spam);
        $msg = Store::messages()->insert([
            'pro_id' => (int) $pro['id'],
            'status' => $status === 'go' ? 'pending' : $status,
            'source' => $in['source'] ?? 'fiche',
            'name' => $in['name'],
            'email' => $in['email'],
            'phone' => $in['phone'] ?? '',
            'place' => $in['place'] ?? '',
            'event_date' => $in['date'] ?? '',
            'event_type' => $in['event_type'] ?? '',
            'guests' => $in['guests'] ?? '',
            'message' => $in['message'],
            'spam' => ['score' => $spam['score'], 'decision' => $spam['decision'], 'reasons' => $spam['reasons'], 'ai' => $spam['ai'] ?? null],
            'ip_hash' => Request::ipHash(),
            'read_at' => null,
        ]);
        Stats::hit('message', (int) $pro['id']);
        if ($status === 'go') {
            $msg = self::deliverMessage((int) $msg['id']) ?? $msg;
        } elseif ($status === 'pending') {
            Notify::admin('message_pending', 'Message à modérer pour ' . Pros::displayName($pro), $in['name'] . ' : ' . Str::limit((string) $in['message'], 140), Url::admin('messages/' . $msg['id']), 'warning');
        }
        if ($status !== 'spam') {
            $suite = $msg['status'] === 'delivered' ? '' : ' après une rapide vérification';
            Mail::send((string) $in['email'], 'message_confirmation', ['prenom' => self::greetingName((string) $in['name']), 'fiche' => Pros::displayName($pro)], ['suite' => e($suite), 'details' => '']);
        }
        return $msg;
    }

    public static function deliverMessage(int $id, ?string $by = null): ?array
    {
        $m = Store::messages()->get($id);
        if (!$m) {
            return null;
        }
        $pro = Store::pros()->get((int) $m['pro_id']);
        if ($pro && !empty($pro['email']) && ($pro['settings']['notify_messages'] ?? true)) {
            $mail = Mail::build('message_to_pro', ['prenom' => $pro['first_name'] ?: Pros::displayName($pro), 'client' => $m['name']], [
                'details' => Mail::details(array_filter([
                    'De' => $m['name'],
                    'Email' => $m['email'],
                    'Téléphone' => $m['phone'] ?? '',
                    'Événement' => self::EVENT_TYPES[$m['event_type'] ?? ''] ?? '',
                    'Date' => !empty($m['event_date']) ? date_fr((string) $m['event_date'], 'long') : '',
                    'Lieu' => $m['place'] ?? '',
                    'Invités' => $m['guests'] ?? '',
                    'Message' => $m['message'],
                ])),
                'bouton_url' => '/espace-pro/messages/' . $id . '/',
                'bouton_label' => 'Répondre depuis mon espace',
            ]);
            Mail::queue((string) $pro['email'], $mail['subject'], $mail['html'], ['priority' => 2, 'reply_to' => $m['email'], 'ref' => 'message:' . $id]);
            Notify::pro((int) $pro['id'], '✉️ Nouveau message', $m['name'] . ' : ' . Str::limit((string) $m['message'], 90), '/espace-pro/messages/' . $id . '/');
        }
        return Store::messages()->update($id, ['status' => 'delivered', 'delivered_at' => date('c'), 'moderated_by' => $by]);
    }
}
