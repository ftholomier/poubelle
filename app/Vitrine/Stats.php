<?php
declare(strict_types=1);

namespace App\Vitrine;

/**
 * Mesure d'audience du site de l'association, sans cookie ni donnée personnelle : une ligne
 * « heure|page » par page vue (storage/vitrine/stats/AAAA-MM-JJ.log), robots exclus. Résumé
 * calculé à la demande pour le tableau de bord du pavé ; journaux de plus de 400 jours effacés.
 */
final class Stats
{
    private const DIR = STORAGE_PATH . '/vitrine/stats';
    private const BOTS = '/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|embedly|quora|pinterest|whatsapp|telegram|discord|curl|wget|python|java\/|httpclient|headless|lighthouse|monitor|uptime|preview/i';

    public static function hit(string $path, string $ua): void
    {
        if ($ua === '' || preg_match(self::BOTS, $ua)) {
            return;
        }
        $path = mb_substr(strtok($path, '?') ?: '/', 0, 200);
        if (!is_dir(self::DIR)) {
            @mkdir(self::DIR, 0775, true);
        }
        @file_put_contents(self::DIR . '/' . date('Y-m-d') . '.log', date('H') . '|' . str_replace(["\n", '|'], '', $path) . "\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * Pages vues des $days derniers jours : total, par jour, pages les plus vues.
     * @return array{total:int,days:array<string,int>,top:array<string,int>}
     */
    public static function summary(int $days = 30): array
    {
        $out = ['total' => 0, 'days' => [], 'top' => []];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime("-$i day"));
            $out['days'][$day] = 0;
            $f = self::DIR . "/$day.log";
            if (!is_file($f)) {
                continue;
            }
            foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $path = explode('|', $line, 2)[1] ?? '/';
                $out['days'][$day]++;
                $out['total']++;
                $out['top'][$path] = ($out['top'][$path] ?? 0) + 1;
            }
        }
        arsort($out['top']);
        $out['top'] = array_slice($out['top'], 0, 10, true);
        return $out;
    }

    /** Efface les journaux anciens (tâche planifiée). */
    public static function prune(int $keepDays = 400): int
    {
        $n = 0;
        $limit = date('Y-m-d', strtotime("-$keepDays days"));
        foreach (glob(self::DIR . '/*.log') ?: [] as $f) {
            if (basename($f, '.log') < $limit && @unlink($f)) {
                $n++;
            }
        }
        return $n;
    }
}
