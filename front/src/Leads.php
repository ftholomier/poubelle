<?php

declare(strict_types=1);

/** Demandes reçues par le site : devis, contact, rappel. Enregistrées puis notifiées par e-mail. */
final class Leads
{
    public const TYPES = ['devis' => 'Devis', 'contact' => 'Contact', 'rappel' => 'Rappel'];
    public const STATUS = ['nouveau' => 'Nouveau', 'encours' => 'En cours', 'traite' => 'Traité', 'archive' => 'Archivé'];

    public static function all(): array
    {
        $l = Store::get('leads', []);
        usort($l, fn($a, $b) => strcmp((string) $b['at'], (string) $a['at']));
        return $l;
    }

    public static function find(string $id): ?array
    {
        foreach (Store::get('leads', []) as $l) {
            if ($l['id'] === $id) {
                return $l;
            }
        }
        return null;
    }

    public static function add(array $lead): array
    {
        $lead = ['id' => date('ymd') . '-' . bin2hex(random_bytes(4)), 'at' => date('c'), 'status' => 'nouveau', 'notes' => []] + $lead;
        Store::update('leads', function (array $all) use ($lead) {
            array_unshift($all, $lead);
            return array_slice($all, 0, 5000);
        });
        $sent = self::notify($lead);
        if ($sent !== true) {
            error_log('[renovest] notification e-mail : ' . $sent);
            self::update($lead['id'], ['mail_error' => (string) $sent]);
        }
        return $lead;
    }

    public static function update(string $id, array $changes): void
    {
        Store::update('leads', function (array $all) use ($id, $changes) {
            foreach ($all as &$l) {
                if ($l['id'] === $id) {
                    $l = array_replace($l, $changes);
                }
            }
            return $all;
        });
    }

    public static function delete(string $id): void
    {
        Store::update('leads', fn(array $all) => array_values(array_filter($all, fn($l) => $l['id'] !== $id)));
    }

    public static function unread(): int
    {
        return count(array_filter(Store::get('leads', []), fn($l) => ($l['status'] ?? '') === 'nouveau'));
    }

    /** Texte lisible d'une demande (e-mail, export). */
    public static function summary(array $l): string
    {
        $rows = [
            'Type' => self::TYPES[$l['type']] ?? $l['type'],
            'Date' => date('d/m/Y H:i', strtotime((string) $l['at'])),
            'Nom' => $l['nom'] ?? '',
            'Téléphone' => $l['tel'] ?? '',
            'E-mail' => $l['email'] ?? '',
            'Objet' => $l['objet'] ?? '',
            'Produits' => implode(', ', (array) ($l['types'] ?? [])),
            "Nombre d'éléments" => $l['qty'] ?? '',
            'Échéance' => $l['delai'] ?? '',
            'Code postal' => $l['cp'] ?? '',
            'Message' => $l['msg'] ?? '',
            'Page' => $l['page'] ?? '',
        ];
        $out = '';
        foreach ($rows as $k => $v) {
            if (trim((string) $v) !== '') {
                $out .= $k . ' : ' . $v . "\n";
            }
        }
        return $out;
    }

    /** @return true|string */
    private static function notify(array $l)
    {
        $to = trim((string) Settings::get('notify_email', ''));
        $cc = trim((string) Settings::get('notify_cc', ''));
        if ($cc !== '') {
            $to .= ',' . $cc;
        }
        $subject = '[Site] Nouvelle demande de ' . mb_strtolower(self::TYPES[$l['type']] ?? $l['type']) . ' — ' . ($l['nom'] ?? '');
        $text = "Une nouvelle demande a été envoyée depuis le site.\n\n" . self::summary($l)
            . "\nVoir dans le back-office : " . Http::abs('/admin/demandes?id=' . $l['id']) . "\n";
        return Mailer::send($to, $subject, $text, (string) ($l['email'] ?? ''));
    }
}
