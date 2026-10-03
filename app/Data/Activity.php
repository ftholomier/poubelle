<?php
declare(strict_types=1);

namespace App\Data;

use App\Core\JsonStore;

/** Journal d'activité du back-office (qui a fait quoi, quand). Un fichier par mois. */
final class Activity
{
    public static function log(?array $user, string $action, ?array $doc, array $extra = []): void
    {
        if (PHP_SAPI === 'cli' && $user === null && empty($extra['force'])) {
            return; // imports et tâches en ligne de commande : pas de bruit dans le journal
        }
        JsonStore::append(STORAGE_PATH . '/activity/' . date('Y-m') . '.jsonl', [
            'at' => date('c'),
            'by' => $user['name'] ?? 'Système',
            'uid' => $user['id'] ?? null,
            'initials' => self::initials($user['name'] ?? 'Système'),
            'action' => $action,
            'title' => $doc['title'] ?? null,
            'id' => $doc['id'] ?? null,
            'type' => $doc['type'] ?? null,
        ] + $extra);
    }

    /** @return list<array> entrées les plus récentes en premier */
    public static function recent(int $limit = 50): array
    {
        $files = glob(STORAGE_PATH . '/activity/*.jsonl') ?: [];
        rsort($files);
        $out = [];
        foreach ($files as $f) {
            foreach (JsonStore::readLines($f) as $e) {
                $out[] = $e;
                if (count($out) >= $limit) {
                    return $out;
                }
            }
        }
        return $out;
    }

    public static function initials(string $name): string
    {
        $parts = preg_split('/[\s.\-]+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];
        $i = mb_strtoupper(mb_substr($parts[0], 0, 1) . (isset($parts[1]) ? mb_substr($parts[1], 0, 1) : ''));
        return $i ?: '?';
    }
}
