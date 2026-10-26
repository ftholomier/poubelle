<?php
declare(strict_types=1);

namespace LD;

/**
 * Boîte de réception : formulaires, rendez-vous, rappels, vocaux, discussions et briefs de l'assistant IA.
 */
final class Leads
{
    private const FILE = 'data/leads.json';

    public const TYPES = [
        'contact' => 'Formulaire',
        'rdv'     => 'Rendez-vous',
        'rappel'  => 'Rappel demandé',
        'vocal'   => 'Vocal',
        'ia'      => 'Discussion IA',
        'brief'   => 'Brief de l’assistant',
        'analyse' => 'Analyse IA de site',
        'demo'    => 'Démo devis IA',
    ];

    /** Récapitulatif d'une discussion IA envoyé après ce délai sans nouveau message (si le visiteur n'a pas fermé la fenêtre). */
    public const CHAT_IDLE = 600;

    public const STATUSES = ['new' => 'Nouveau', 'progress' => 'En cours', 'done' => 'Traité', 'archived' => 'Archivé'];

    public static function add(string $type, array $fields): array
    {
        $lead = self::make($type, $fields);
        Store::update(self::FILE, static function (array &$all) use ($lead): void {
            $all[] = $lead;
        });
        return $lead;
    }

    private static function make(string $type, array $fields): array
    {
        return [
            'id'         => date('ymd') . '-' . bin2hex(random_bytes(4)),
            'type'       => $type,
            'status'     => 'new',
            'created_at' => date('c'),
            'name'       => (string) ($fields['name'] ?? ''),
            'email'      => (string) ($fields['email'] ?? ''),
            'phone'      => (string) ($fields['phone'] ?? ''),
            'data'       => $fields['data'] ?? [],
            'meta'       => [
                'page' => clean_text((string) ($_SERVER['HTTP_REFERER'] ?? ''), 300),
                'ua'   => clean_text((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 200),
                'ip'   => substr(hash('sha256', Http::ip() . Settings::appSecret()), 0, 12),
            ],
            'notes'      => '',
        ];
    }

    /**
     * Discussion avec l'assistant IA : une demande par conversation, mise à jour à chaque échange
     * (et complétée par le brief s'il est demandé). Fred est alerté dès le premier échange.
     */
    public static function chat(string $cid, array $conversation, ?array $brief = null): array
    {
        $users = count(array_filter($conversation, static fn($m) => ($m['role'] ?? '') === 'user'));
        $contact = self::contactIn($conversation);
        $lead = null;
        Store::update(self::FILE, static function (array &$all) use ($cid, $conversation, $brief, $users, $contact, &$lead): void {
            foreach ($all as &$l) {
                if ($l['type'] === 'ia' && ($l['data']['cid'] ?? '') === $cid) {
                    $l['data'] = array_merge($l['data'], $brief ?? [], ['conversation' => $conversation, 'messages' => max($users, (int) ($l['data']['messages'] ?? 0)), 'last_at' => date('c')]);
                    foreach ($contact as $k => $v) {
                        $l[$k] = $l[$k] !== '' ? $l[$k] : $v;
                    }
                    $l['updated_at'] = date('c');
                    $lead = $l;
                    return;
                }
            }
            unset($l);
            $lead = self::make('ia', $contact + ['data' => ($brief ?? []) + ['conversation' => $conversation, 'cid' => $cid, 'messages' => $users, 'notified' => 0, 'last_at' => date('c')]]);
            $all[] = $lead;
        });
        if (empty($lead['data']['notified']) && !isset($lead['notify']) && $brief === null) {
            self::notifyChat($lead, true);
        }
        return $lead;
    }

    /**
     * Essai d'un outil du site (analyse IA du site, démo de devis) : une demande par visiteur et par outil sur 30 min.
     * Fred est alerté au premier essai ; les suivants s'ajoutent à la même demande (pas d'avalanche d'e-mails).
     */
    public static function interaction(string $type, array $data, string $summary): array
    {
        $ip = substr(hash('sha256', Http::ip() . Settings::appSecret()), 0, 12);
        $since = date('c', time() - 1800);
        $lead = null;
        $isNew = false;
        Store::update(self::FILE, static function (array &$all) use ($type, $data, $summary, $ip, $since, &$lead, &$isNew): void {
            foreach ($all as &$l) {
                if ($l['type'] === $type && ($l['meta']['ip'] ?? '') === $ip && $l['created_at'] >= $since) {
                    $l['data']['essais'] = array_slice([...(array) ($l['data']['essais'] ?? []), date('H:i') . ' · ' . $summary], -10);
                    $l['updated_at'] = date('c');
                    $lead = $l;
                    return;
                }
            }
            unset($l);
            $lead = self::make($type, ['data' => $data]);
            $all[] = $lead;
            $isNew = true;
        });
        if ($isNew) {
            self::notify($lead);
        }
        return $lead;
    }

    public static function chatLead(string $cid): ?array
    {
        foreach (Store::read(self::FILE, []) as $l) {
            if ($l['type'] === 'ia' && ($l['data']['cid'] ?? '') === $cid) {
                return $l;
            }
        }
        return null;
    }

    /**
     * E-mail à Fred pour une discussion IA : alerte au premier échange, puis récapitulatif complet à la fin
     * (fenêtre fermée, visiteur parti ou CHAT_IDLE sans message), seulement s'il y a du nouveau depuis le dernier e-mail.
     */
    public static function notifyChat(array $lead, bool $first = false, string $title = '', string $intro = ''): bool
    {
        $users = (int) ($lead['data']['messages'] ?? 0);
        if (!$first && $title === '' && $users <= (int) ($lead['data']['notified'] ?? 0)) {
            return false;
        }
        self::update($lead['id'], ['data' => ['notified' => $users]]);
        if ($title === '') {
            $title = $first ? 'Discussion IA en cours' : 'Discussion IA · récapitulatif';
            $intro = $first
                ? 'Un visiteur discute en ce moment avec ton assistant IA. Voici le début de la conversation : la suite se met à jour dans le back-office et tu recevras le récapitulatif complet à la fin.'
                : 'Conversation complète du visiteur avec ton assistant IA (' . $users . ' message' . ($users > 1 ? 's' : '') . ' du visiteur).';
        }
        return self::notify($lead, [], $title, $intro);
    }

    /** Récapitulatifs des discussions IA terminées (aucun message depuis $idle secondes). */
    public static function flushChats(int $idle = self::CHAT_IDLE): int
    {
        $n = 0;
        foreach (Store::read(self::FILE, []) as $l) {
            if ($l['type'] === 'ia' && (int) ($l['data']['messages'] ?? 0) > (int) ($l['data']['notified'] ?? 0)
                && (strtotime((string) ($l['data']['last_at'] ?? '')) ?: 0) <= time() - $idle) {
                $n += (int) self::notifyChat($l);
            }
        }
        return $n;
    }

    /** E-mail et téléphone donnés par le visiteur dans la discussion (pour pouvoir le recontacter). */
    private static function contactIn(array $conversation): array
    {
        $text = implode("\n", array_map(static fn($m) => ($m['role'] ?? '') === 'user' ? (string) ($m['content'] ?? '') : '', $conversation));
        $out = ['email' => '', 'phone' => ''];
        if (preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $text, $m)) {
            $out['email'] = mb_strtolower($m[0]);
        }
        if (preg_match('/(?:\+33\s?|0)[1-9](?:[\s.-]?\d{2}){4}/', $text, $m)) {
            $out['phone'] = $m[0];
        }
        return $out;
    }

    public static function all(?string $type = null, ?string $status = null, string $q = ''): array
    {
        $all = array_filter(Store::read(self::FILE, []), static function ($l) use ($type, $status, $q) {
            if ($type && $l['type'] !== $type) {
                return false;
            }
            if ($status === 'open' && in_array($l['status'], ['done', 'archived'], true)) {
                return false;
            }
            if ($status && $status !== 'open' && $l['status'] !== $status) {
                return false;
            }
            if ($q !== '' && !str_contains(mb_strtolower(json_encode($l, JSON_UNESCAPED_UNICODE)), mb_strtolower($q))) {
                return false;
            }
            return true;
        });
        usort($all, static fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
        return array_values($all);
    }

    public static function find(string $id): ?array
    {
        foreach (Store::read(self::FILE, []) as $l) {
            if ($l['id'] === $id) {
                return $l;
            }
        }
        return null;
    }

    public static function update(string $id, array $changes): void
    {
        Store::update(self::FILE, static function (array &$all) use ($id, $changes): void {
            foreach ($all as &$l) {
                if ($l['id'] === $id) {
                    foreach (['status', 'notes', 'notify'] as $k) {
                        if (array_key_exists($k, $changes)) {
                            $l[$k] = $changes[$k];
                        }
                    }
                    if (isset($changes['data']) && is_array($changes['data'])) {
                        $l['data'] = array_merge($l['data'], $changes['data']);
                    }
                    $l['updated_at'] = date('c');
                }
            }
        });
    }

    public static function delete(string $id): void
    {
        Store::update(self::FILE, static function (array &$all) use ($id): void {
            foreach ($all as $i => $l) {
                if ($l['id'] === $id) {
                    if (!empty($l['data']['audio'])) {
                        @unlink(Store::path('data/voices/' . basename($l['data']['audio'])));
                    }
                    unset($all[$i]);
                }
            }
            $all = array_values($all);
        });
    }

    public static function counts(): array
    {
        $c = ['new' => 0, 'total' => 0, 'types' => array_fill_keys(array_keys(self::TYPES), 0), 'types_new' => array_fill_keys(array_keys(self::TYPES), 0)];
        foreach (Store::read(self::FILE, []) as $l) {
            $c['total']++;
            $c['types'][$l['type']] = ($c['types'][$l['type']] ?? 0) + 1;
            if ($l['status'] === 'new') {
                $c['new']++;
                $c['types_new'][$l['type']] = ($c['types_new'][$l['type']] ?? 0) + 1;
            }
        }
        return $c;
    }

    /** Lignes « libellé => valeur » lisibles pour un e-mail ou le back-office. */
    public static function rows(array $lead): array
    {
        $rows = [];
        foreach (['name' => 'Prénom', 'email' => 'Email', 'phone' => 'Téléphone'] as $k => $label) {
            if (!empty($lead[$k])) {
                $rows[$label] = $lead[$k];
            }
        }
        $labels = [
            'interests' => 'Intéressé par', 'time_text' => 'Ce qui prend le plus de temps', 'why' => 'Le pourquoi du comment',
            'date_label' => 'Créneau', 'mode' => 'Format', 'sms' => 'Rappel SMS', 'moment' => 'Moment souhaité', 'origine' => 'Origine',
            'transcript' => 'Transcription', 'summary' => 'Résumé IA', 'duration' => 'Durée', 'besoin' => 'Besoin',
            'taches' => 'Tâches à automatiser', 'urgence' => 'Urgence', 'formule' => 'Formule conseillée',
            'sim' => 'Simulateur gain de temps', 'brief' => 'Brief de l’assistant', 'conversation' => 'Conversation',
            'site' => 'Site analysé', 'activite' => 'Activité', 'difficulte' => 'Ce qui lui prend du temps', 'constats' => 'Constats de l’IA',
            'idees' => 'Idées proposées', 'demande' => 'Devis demandé', 'devis' => 'Devis généré', 'essais' => 'Essais suivants',
        ];
        $data = $lead['data'] ?? [];
        if (isset($data['conversation'])) {
            // La conversation, souvent longue, passe en dernier (après le besoin et le brief).
            $conv = $data['conversation'];
            unset($data['conversation']);
            $data['conversation'] = $conv;
        }
        foreach ($data as $k => $v) {
            if ($v === '' || $v === null || $v === [] || in_array($k, ['audio', 'mime', 'booking_id', 'date', 'time', 'cid', 'messages', 'notified', 'last_at'], true)) {
                continue;
            }
            if ($k === 'sim' && is_array($v)) {
                $v = '≈ ' . str_replace('.', ',', (string) ($v['saved'] ?? '?')) . ' h/sem. récupérables · ' . implode(', ', array_map(static fn($t) => ($t[0] ?? '') . ' (' . ($t[1] ?? '') . ' h)', $v['top'] ?? []));
            } elseif ($k === 'brief' && is_array($v)) {
                $v = implode(' · ', array_filter([$v['besoin'] ?? '', $v['taches'] ?? '', $v['formule'] ?? '']));
            } elseif ($k === 'conversation' && is_array($v)) {
                $v = implode("\n", array_map(static fn($m) => (($m['role'] ?? '') === 'user' ? 'Visiteur : ' : 'Assistant : ') . ($m['content'] ?? ''), $v));
            } elseif (is_bool($v)) {
                $v = $v ? 'Oui' : 'Non';
            } elseif (is_array($v) && in_array($k, ['constats', 'idees', 'essais'], true)) {
                $v = '• ' . implode("\n• ", array_map('strval', $v));
            } elseif (is_array($v)) {
                $v = implode(', ', array_map('strval', $v));
            }
            $rows[$labels[$k] ?? ucfirst($k)] = (string) $v;
        }
        return $rows;
    }

    /** E-mail à Fred pour une nouvelle demande. Le résultat est noté sur la demande (visible dans le back-office). */
    public static function notify(array $lead, array $attachments = [], string $title = '', string $intro = ''): bool
    {
        if (!Settings::get('mail.notify', true)) {
            return false;
        }
        $to = Mailer::notifyAddress();
        $html = ($intro !== '' ? '<p>' . e($intro) . '</p>' : '') . '<table style="width:100%;border-collapse:collapse">';
        foreach (self::rows($lead) as $label => $value) {
            $html .= '<tr><td style="padding:8px 10px 8px 0;vertical-align:top;color:#8a86a0;font-size:13px;white-space:nowrap">' . e($label) . '</td><td style="padding:8px 0;border-bottom:1px solid #f6ecea">' . nl2br(e($value)) . '</td></tr>';
        }
        $html .= '</table>';
        $title = ($title !== '' ? $title : (self::TYPES[$lead['type']] ?? 'Demande')) . ($lead['name'] ? ' · ' . $lead['name'] : '');
        $ok = Mailer::send($to, '☕ ' . $title, Mailer::layout($title, $html, 'Ouvrir dans le back-office', abs_url('/admin/demandes/' . $lead['id'])), $lead['email'] ?: null, $attachments);
        if (!$ok && $attachments) {
            // Pièce jointe trop lourde pour le serveur d'envoi ? Nouvel essai sans (le vocal reste dans le back-office).
            $ok = Mailer::send($to, '☕ ' . $title, Mailer::layout($title, $html . '<p>(Pièce jointe non envoyée : écoute-la dans le back-office.)</p>', 'Ouvrir dans le back-office', abs_url('/admin/demandes/' . $lead['id'])), $lead['email'] ?: null);
        }
        self::update($lead['id'], ['notify' => ['ok' => $ok, 'at' => date('c'), 'to' => $to, 'error' => $ok ? null : Mailer::$lastError]]);
        return $ok;
    }
}
