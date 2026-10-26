<?php
declare(strict_types=1);

namespace LD;

/**
 * Boîte de réception : formulaires, rendez-vous, rappels, vocaux et briefs de l'assistant.
 */
final class Leads
{
    private const FILE = 'data/leads.json';

    public const TYPES = [
        'contact' => 'Formulaire',
        'rdv'     => 'Rendez-vous',
        'rappel'  => 'Rappel demandé',
        'vocal'   => 'Vocal',
        'brief'   => 'Brief de l’assistant',
    ];

    public const STATUSES = ['new' => 'Nouveau', 'progress' => 'En cours', 'done' => 'Traité', 'archived' => 'Archivé'];

    public static function add(string $type, array $fields): array
    {
        $lead = [
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
        Store::update(self::FILE, static function (array &$all) use ($lead): void {
            $all[] = $lead;
        });
        return $lead;
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
        ];
        foreach ($lead['data'] ?? [] as $k => $v) {
            if ($v === '' || $v === null || $v === [] || in_array($k, ['audio', 'mime', 'booking_id', 'date', 'time'], true)) {
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
            } elseif (is_array($v)) {
                $v = implode(', ', array_map('strval', $v));
            }
            $rows[$labels[$k] ?? ucfirst($k)] = (string) $v;
        }
        return $rows;
    }

    /** Notification e-mail à Fred pour chaque nouvelle demande. */
    /** E-mail à Fred pour une nouvelle demande. Le résultat est noté sur la demande (visible dans le back-office). */
    public static function notify(array $lead, array $attachments = []): bool
    {
        if (!Settings::get('mail.notify', true)) {
            return false;
        }
        $to = Mailer::notifyAddress();
        $html = '<table style="width:100%;border-collapse:collapse">';
        foreach (self::rows($lead) as $label => $value) {
            $html .= '<tr><td style="padding:8px 10px 8px 0;vertical-align:top;color:#8a86a0;font-size:13px;white-space:nowrap">' . e($label) . '</td><td style="padding:8px 0;border-bottom:1px solid #f6ecea">' . nl2br(e($value)) . '</td></tr>';
        }
        $html .= '</table>';
        $title = (self::TYPES[$lead['type']] ?? 'Demande') . ($lead['name'] ? ' · ' . $lead['name'] : '');
        $ok = Mailer::send($to, '☕ ' . $title, Mailer::layout($title, $html, 'Ouvrir dans le back-office', abs_url('/admin/demandes/' . $lead['id'])), $lead['email'] ?: null, $attachments);
        if (!$ok && $attachments) {
            // Pièce jointe trop lourde pour le serveur d'envoi ? Nouvel essai sans (le vocal reste dans le back-office).
            $ok = Mailer::send($to, '☕ ' . $title, Mailer::layout($title, $html . '<p>(Pièce jointe non envoyée : écoute-la dans le back-office.)</p>', 'Ouvrir dans le back-office', abs_url('/admin/demandes/' . $lead['id'])), $lead['email'] ?: null);
        }
        self::update($lead['id'], ['notify' => ['ok' => $ok, 'at' => date('c'), 'to' => $to, 'error' => $ok ? null : Mailer::$lastError]]);
        return $ok;
    }
}
