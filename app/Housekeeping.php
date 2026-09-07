<?php
declare(strict_types=1);

/**
 * Purge des données arrivées à échéance.
 *
 * La politique de confidentialité annonce des durées de conservation :
 * sans mécanisme d'effacement, cette annonce est fausse. Cette classe les
 * applique, une fois par jour, sans tâche planifiée à configurer — un
 * marqueur daté suffit à ne l'exécuter qu'une fois.
 */
final class Housekeeping
{
    /** Durées de conservation, en jours. */
    public const RETENTION = [
        'applications' => 730,   // candidatures envoyées : 24 mois
        'drafts' => 90,          // tunnels abandonnés : 3 mois
        'leads' => 365,          // messages de contact : 12 mois
        'bot_chats' => 365,      // conversations de l'assistant : 12 mois
        'maillog' => 365,        // journal des e-mails : 12 mois
        'events' => 395,         // audience : 13 mois (fichiers mensuels)
    ];

    private static function marker(): string
    {
        return DATA_DIR . '/.housekeeping';
    }

    /** Passage quotidien : ne fait rien si la purge a déjà eu lieu aujourd'hui. */
    public static function maybeRun(): void
    {
        $today = date('Y-m-d');
        $marker = self::marker();
        if (is_file($marker) && trim((string) @file_get_contents($marker)) === $today) {
            return;
        }
        // Le marqueur est posé avant la purge : deux requêtes simultanées ne
        // déclenchent pas deux passages.
        @file_put_contents($marker, $today, LOCK_EX);
        self::run();
    }

    /** @return array<string,int> nombre d'enregistrements supprimés par catégorie */
    public static function run(): array
    {
        $now = time();
        $removed = [];

        // --- Candidatures : envoyées (24 mois) et brouillons (90 jours) ---
        $cvToDelete = [];
        $removed['candidatures'] = 0;
        $removed['brouillons'] = 0;
        Store::mutate('applications', static function (array $rows) use ($now, &$removed, &$cvToDelete): array {
            $kept = [];
            foreach ($rows as $row) {
                $isDraft = ($row['status'] ?? '') === 'brouillon';
                $limit = $isDraft ? self::RETENTION['drafts'] : self::RETENTION['applications'];
                $ref = strtotime((string) ($row['updated_at'] ?? $row['submitted_at'] ?? $row['created_at'] ?? ''));
                if ($ref !== false && $ref < $now - $limit * 86400) {
                    if (!empty($row['cv'])) { $cvToDelete[] = basename((string) $row['cv']); }
                    $removed[$isDraft ? 'brouillons' : 'candidatures']++;
                    continue;
                }
                $kept[] = $row;
            }
            return $kept;
        });
        foreach ($cvToDelete as $file) {
            @unlink(UPLOAD_DIR . '/' . $file);
        }

        $removed['messages'] = self::purgeCollection('leads', self::RETENTION['leads'], 'created_at');
        $removed['conversations'] = self::purgeCollection('bot-chats', self::RETENTION['bot_chats'], 'created_at');
        $removed['emails'] = self::purgeCollection('maillog', self::RETENTION['maillog'], 'created_at');

        // --- Demandes de réinitialisation expirées ---
        $removed['reinitialisations'] = PasswordReset::purger();

        // --- Audience : les fichiers mensuels entiers ---
        $removed['mois_audience'] = 0;
        $cutoff = date('Y-m', $now - self::RETENTION['events'] * 86400);
        foreach (glob(Analytics::dir() . '/events-*.jsonl') ?: [] as $file) {
            if (preg_match('/events-(\d{4}-\d{2})\.jsonl$/', $file, $m) && $m[1] < $cutoff) {
                @unlink($file);
                $removed['mois_audience']++;
            }
        }

        // --- CV orphelins : plus aucune candidature ne les référence ---
        $referenced = [];
        foreach (Store::read('applications') as $row) {
            if (!empty($row['cv'])) { $referenced[basename((string) $row['cv'])] = true; }
        }
        $removed['cv_orphelins'] = 0;
        foreach (glob(UPLOAD_DIR . '/cv-*') ?: [] as $file) {
            if (!isset($referenced[basename($file)])) {
                @unlink($file);
                $removed['cv_orphelins']++;
            }
        }

        self::log($removed);
        return $removed;
    }

    /** Supprime d'une collection les lignes dont la date dépasse la durée. */
    private static function purgeCollection(string $name, int $days, string $field): int
    {
        $now = time();
        $count = 0;
        Store::mutate($name, static function (array $rows) use ($now, $days, $field, &$count): array {
            $kept = [];
            foreach ($rows as $row) {
                $ref = strtotime((string) ($row[$field] ?? ''));
                if ($ref !== false && $ref < $now - $days * 86400) {
                    $count++;
                    continue;
                }
                $kept[] = $row;
            }
            return $kept;
        });
        return $count;
    }

    private static function log(array $removed): void
    {
        $dir = DATA_DIR . '/logs';
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $total = array_sum($removed);
        $detail = $total > 0
            ? implode(', ', array_map(static fn ($k, $v) => $k . '=' . $v, array_keys($removed), $removed))
            : 'rien à supprimer';
        @file_put_contents(
            $dir . '/housekeeping.log',
            date('c') . ' — ' . $detail . "\n",
            FILE_APPEND | LOCK_EX
        );
    }
}
