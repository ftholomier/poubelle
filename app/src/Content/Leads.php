<?php

declare(strict_types=1);

namespace App\Content;

use App\Core\JsonStore;
use App\Core\Logger;

/**
 * Demandes entrantes (formulaire de contact, pop-up de sortie, assistant IA).
 * Stockage mensuel pour éviter un fichier unique qui grossit sans fin.
 */
final class Leads
{
    public const SOURCE_CONTACT = 'contact';
    public const SOURCE_EXIT    = 'exit_popup';
    public const SOURCE_CHAT    = 'chatbot';

    private static function file(?string $month = null): string
    {
        $month ??= date('Y-m');
        $safe = preg_replace('/[^0-9\-]/', '', $month) ?: date('Y-m');
        return DATA_PATH . '/leads-' . $safe . '.json';
    }

    /** @param array<string,mixed> $lead */
    public static function add(array $lead): string
    {
        $id = date('Ymd-His') . '-' . bin2hex(random_bytes(3));
        $entry = [
            'id'        => $id,
            'source'    => (string) ($lead['source'] ?? self::SOURCE_CONTACT),
            'name'      => (string) ($lead['name'] ?? ''),
            'email'     => (string) ($lead['email'] ?? ''),
            'phone'     => (string) ($lead['phone'] ?? ''),
            'company'   => (string) ($lead['company'] ?? ''),
            'subject'   => (string) ($lead['subject'] ?? ''),
            'message'   => (string) ($lead['message'] ?? ''),
            'lang'      => (string) ($lead['lang'] ?? 'fr'),
            'page'      => (string) ($lead['page'] ?? ''),
            'ip_hash'   => hash('sha256', (string) ($lead['ip'] ?? '')),  // RGPD : pas d'IP en clair
            'status'    => 'new',
            'created_at'=> date('c'),
        ];

        JsonStore::mutate(self::file(), static function (array $data) use ($entry): array {
            $data['leads'][] = $entry;
            return $data;
        }, ['leads' => []]);

        Logger::audit('lead.new', ['source' => $entry['source'], 'id' => $id]);
        return $id;
    }

    /** @return array<int,array<string,mixed>> */
    public static function month(?string $month = null): array
    {
        $data = JsonStore::read(self::file($month), ['leads' => []], true);
        $leads = array_values(array_filter($data['leads'] ?? [], 'is_array'));
        usort($leads, static fn (array $a, array $b): int => strcmp((string) $b['created_at'], (string) $a['created_at']));
        return $leads;
    }

    /** @return array<int,string> mois disponibles, du plus récent au plus ancien */
    public static function months(): array
    {
        $months = [];
        foreach (glob(DATA_PATH . '/leads-*.json') ?: [] as $file) {
            if (preg_match('/leads-(\d{4}-\d{2})\.json$/', $file, $m)) {
                $months[] = $m[1];
            }
        }
        rsort($months);
        return $months ?: [date('Y-m')];
    }

    public static function setStatus(string $month, string $id, string $status): bool
    {
        $allowed = ['new', 'processing', 'done', 'archived'];
        if (!in_array($status, $allowed, true)) {
            return false;
        }
        $found = false;
        JsonStore::mutate(self::file($month), static function (array $data) use ($id, $status, &$found): array {
            foreach ($data['leads'] as $i => $lead) {
                if (is_array($lead) && (string) ($lead['id'] ?? '') === $id) {
                    $data['leads'][$i]['status'] = $status;
                    $found = true;
                }
            }
            return $data;
        }, ['leads' => []]);
        return $found;
    }

    public static function delete(string $month, string $id): bool
    {
        $removed = false;
        JsonStore::mutate(self::file($month), static function (array $data) use ($id, &$removed): array {
            $data['leads'] = array_values(array_filter($data['leads'], static function ($lead) use ($id, &$removed) {
                if (is_array($lead) && (string) ($lead['id'] ?? '') === $id) {
                    $removed = true;
                    return false;
                }
                return true;
            }));
            return $data;
        }, ['leads' => []]);
        return $removed;
    }

    public static function countNew(): int
    {
        $count = 0;
        foreach (self::month() as $lead) {
            if (($lead['status'] ?? '') === 'new') {
                $count++;
            }
        }
        return $count;
    }
}
