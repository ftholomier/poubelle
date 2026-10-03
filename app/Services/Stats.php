<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\JsonStore;

/**
 * Mesure d'audience interne, sans cookie ni adresse IP (exemptée de consentement) :
 * chaque page vue ajoute une ligne « heure|langue|adresse » à un journal du jour,
 * agrégé par la tâche planifiée en totaux quotidiens et pages les plus vues.
 */
final class Stats
{
    private const RAW = STORAGE_PATH . '/stats/raw';
    private const DAYS = STORAGE_PATH . '/stats/jours.json';
    private const OFFSETS = STORAGE_PATH . '/stats/positions.json';
    private const BOTS = '/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|embedly|quora|pinterest|whatsapp|telegram|discord|curl|wget|python|java\/|httpclient|headless|lighthouse|monitor|uptime|preview/i';

    public static function hit(string $path, string $ua, string $lang): void
    {
        if ($ua === '' || preg_match(self::BOTS, $ua)) {
            return;
        }
        $path = mb_substr(strtok($path, '?') ?: '/', 0, 200);
        if (!is_dir(self::RAW)) {
            @mkdir(self::RAW, 0775, true);
        }
        @file_put_contents(self::RAW . '/' . date('Y-m-d') . '.log', date('H') . '|' . $lang . '|' . str_replace(["\n", '|'], '', $path) . "\n", FILE_APPEND | LOCK_EX);
    }

    /** Agrège les journaux bruts (tâche planifiée). */
    public static function aggregate(): int
    {
        $files = glob(self::RAW . '/*.log') ?: [];
        if (!$files) {
            return 0;
        }
        $offsets = JsonStore::read(self::OFFSETS, []) ?: [];
        $days = JsonStore::read(self::DAYS, []) ?: [];
        $n = 0;
        foreach ($files as $f) {
            $day = basename($f, '.log');
            $from = (int) ($offsets[$day] ?? 0);
            $size = (int) filesize($f);
            if ($size <= $from) {
                if ($day < date('Y-m-d', strtotime('-2 days'))) {
                    @unlink($f);
                    unset($offsets[$day]);
                }
                continue;
            }
            $fp = fopen($f, 'r');
            fseek($fp, $from);
            $d = $days[$day] ?? ['total' => 0, 'en' => 0, 'hours' => array_fill(0, 24, 0), 'pages' => []];
            while (($line = fgets($fp)) !== false) {
                [$h, $lang, $path] = array_pad(explode('|', rtrim($line, "\n"), 3), 3, '');
                $d['total']++;
                if ($lang === 'en') {
                    $d['en']++;
                }
                $d['hours'][(int) $h] = ($d['hours'][(int) $h] ?? 0) + 1;
                $d['pages'][$path] = ($d['pages'][$path] ?? 0) + 1;
                $n++;
            }
            $offsets[$day] = ftell($fp);
            fclose($fp);
            arsort($d['pages']);
            $d['pages'] = array_slice($d['pages'], 0, 300, true);
            $days[$day] = $d;
        }
        ksort($days);
        $days = array_slice($days, -400, null, true); // un peu plus d'un an
        JsonStore::write(self::DAYS, $days);
        JsonStore::write(self::OFFSETS, $offsets);
        return $n;
    }

    /** @return array<string,int> date => pages vues, sur $n jours (aujourd'hui inclus) */
    public static function series(int $n = 30): array
    {
        self::aggregate();
        $days = JsonStore::read(self::DAYS, []) ?: [];
        $out = [];
        for ($i = $n - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i day"));
            $out[$d] = (int) ($days[$d]['total'] ?? 0);
        }
        return $out;
    }

    /** Pages les plus vues sur $n jours. */
    public static function top(int $n = 30, int $limit = 15): array
    {
        $days = JsonStore::read(self::DAYS, []) ?: [];
        $from = date('Y-m-d', strtotime('-' . ($n - 1) . ' day'));
        $acc = [];
        foreach ($days as $d => $v) {
            if ($d < $from) {
                continue;
            }
            foreach ($v['pages'] ?? [] as $p => $c) {
                $acc[$p] = ($acc[$p] ?? 0) + $c;
            }
        }
        arsort($acc);
        return array_slice($acc, 0, $limit, true);
    }

    public static function englishShare(int $n = 30): float
    {
        $days = JsonStore::read(self::DAYS, []) ?: [];
        $from = date('Y-m-d', strtotime('-' . ($n - 1) . ' day'));
        $t = 0;
        $en = 0;
        foreach ($days as $d => $v) {
            if ($d >= $from) {
                $t += $v['total'] ?? 0;
                $en += $v['en'] ?? 0;
            }
        }
        return $t ? round($en / $t * 100, 1) : 0.0;
    }
}
