<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\JsonStore;

/**
 * Mesure d'audience interne, sans cookie et sans adresse IP conservée (exemptée de consentement) :
 * chaque page vue ajoute une ligne « heure|langue|adresse|visiteur|appareil|provenance » au journal
 * du jour. « Visiteur » : empreinte anonyme de l'adresse IP et du navigateur, salée par un secret
 * qui change chaque jour (impossible de suivre quelqu'un d'un jour à l'autre ni de retrouver l'IP).
 * La tâche planifiée agrège les journaux en totaux quotidiens : pages vues, visiteurs uniques,
 * visites (30 min d'inactivité ferment une visite), visites d'une seule page, heures, appareils,
 * provenances, pages et recherches. Le temps réel lit la fin du journal du jour.
 */
final class Stats
{
    private const DIR = STORAGE_PATH . '/stats';
    private const RAW = self::DIR . '/raw';
    private const DAYS = self::DIR . '/jours.json';
    private const OFFSETS = self::DIR . '/positions.json';
    private const STATE = self::DIR . '/visites.json';
    private const BOTS = '/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|embedly|quora|pinterest|whatsapp|telegram|discord|curl|wget|python|java\/|httpclient|headless|lighthouse|monitor|uptime|preview/i';
    /** Une visite se termine après 30 minutes sans page vue. */
    private const GAP = 1800;
    /** « En ligne » : une page vue dans les 5 dernières minutes. */
    public const LIVE = 300;

    public const DEVICES = ['mobile' => 'Mobile', 'tablette' => 'Tablette', 'ordinateur' => 'Ordinateur'];

    public static function hit(string $path, string $ua, string $lang): void
    {
        if ($ua === '' || preg_match(self::BOTS, $ua)) {
            return;
        }
        $s = $_SERVER;
        // La recherche garde ce qui a été cherché (utile : ce que les visiteurs veulent trouver).
        $q = '';
        if (str_starts_with($path, '/recherche') || str_starts_with($path, '/en/recherche')) {
            parse_str((string) (parse_url($path, PHP_URL_QUERY) ?: ($s['QUERY_STRING'] ?? '')), $qs);
            $q = mb_strtolower(trim(mb_substr((string) ($qs['q'] ?? ''), 0, 60)));
        }
        $path = mb_substr(strtok($path, '?') ?: '/', 0, 200);
        if ($q !== '') {
            $path .= '?q=' . $q;
        }
        $ip = (string) ($s['HTTP_CF_CONNECTING_IP'] ?? $s['REMOTE_ADDR'] ?? '');
        $visitor = substr(hash_hmac('sha256', $ip . '|' . $ua, self::salt()), 0, 12);
        $dev = preg_match('/ipad|tablet|kindle|silk|(android(?!.*mobile))/i', $ua) ? 'tablette' : (preg_match('/mobi|iphone|ipod|android|phone/i', $ua) ? 'mobile' : 'ordinateur');
        $ref = '';
        $r = (string) ($s['HTTP_REFERER'] ?? '');
        if ($r !== '') {
            $h = strtolower((string) parse_url($r, PHP_URL_HOST));
            $own = strtolower((string) ($s['HTTP_HOST'] ?? ''));
            if ($h !== '' && $h !== $own && !str_ends_with($h, '.' . preg_replace('/^(www|musee)\./', '', $own))) {
                $ref = preg_replace('/^(www\.|m\.|l\.|lm\.)/', '', $h);
            }
        }
        if (!is_dir(self::RAW)) {
            @mkdir(self::RAW, 0775, true);
        }
        $line = date('H:i:s') . '|' . $lang . '|' . str_replace(["\n", '|'], '', $path) . '|' . $visitor . '|' . $dev . '|' . str_replace(["\n", '|'], '', $ref) . "\n";
        @file_put_contents(self::RAW . '/' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }

    /** Secret du jour : l'empreinte d'un visiteur change chaque jour. */
    private static function salt(): string
    {
        static $s = null;
        if ($s === null) {
            $key = @file_get_contents(STORAGE_PATH . '/secret.key') ?: 'sochaux-retro';
            $s = hash_hmac('sha256', date('Y-m-d'), $key);
        }
        return $s;
    }

    /** Lit une ligne brute (ancien format « HH|langue|adresse » compris). */
    private static function parse(string $line): ?array
    {
        $p = explode('|', rtrim($line, "\n"));
        if (count($p) < 3) {
            return null;
        }
        $t = $p[0];
        $sec = preg_match('/^(\d\d):(\d\d):(\d\d)$/', $t, $m) ? $m[1] * 3600 + $m[2] * 60 + $m[3] : (int) $t * 3600;
        return ['h' => (int) substr($t, 0, 2), 'sec' => (int) $sec, 'lang' => $p[1], 'path' => $p[2], 'v' => $p[3] ?? '', 'dev' => $p[4] ?? '', 'ref' => $p[5] ?? ''];
    }

    /** Agrège les journaux bruts (tâche planifiée, et à l'ouverture du tableau de bord). */
    public static function aggregate(): int
    {
        $files = glob(self::RAW . '/*.log') ?: [];
        if (!$files) {
            return 0;
        }
        $n = 0;
        JsonStore::update(self::DAYS, function ($days) use ($files, &$n) {
            $days = is_array($days) ? $days : [];
            $offsets = JsonStore::read(self::OFFSETS, []) ?: [];
            $state = JsonStore::read(self::STATE, []) ?: [];
            foreach ($files as $f) {
                $day = basename($f, '.log');
                $from = (int) ($offsets[$day] ?? 0);
                $size = (int) filesize($f);
                if ($size <= $from) {
                    if ($day < date('Y-m-d', strtotime('-2 days'))) {
                        @unlink($f);
                        unset($offsets[$day], $state[$day]);
                    }
                    continue;
                }
                $fp = fopen($f, 'r');
                fseek($fp, $from);
                $d = ($days[$day] ?? []) + ['total' => 0, 'en' => 0, 'hours' => array_fill(0, 24, 0), 'pages' => [], 'dev' => [], 'ref' => []];
                $vs = $state[$day] ?? []; // visiteur => [dernière seconde, visites, pages de la visite en cours, visites d'une page]
                while (($line = fgets($fp)) !== false) {
                    $x = self::parse($line);
                    if (!$x) {
                        continue;
                    }
                    $d['total']++;
                    if ($x['lang'] === 'en') {
                        $d['en']++;
                    }
                    $d['hours'][$x['h']] = ($d['hours'][$x['h']] ?? 0) + 1;
                    $d['pages'][$x['path']] = ($d['pages'][$x['path']] ?? 0) + 1;
                    if ($x['v'] !== '') {
                        $v = $vs[$x['v']] ?? null;
                        if ($v === null || $x['sec'] - $v[0] > self::GAP) {
                            // Nouvelle visite : sa provenance et son appareil comptent une fois.
                            $bounce = $v !== null && $v[2] === 1 ? 1 : 0;
                            $v = [$x['sec'], ($v[1] ?? 0) + 1, 1, ($v[3] ?? 0) + $bounce];
                            $d['dev'][$x['dev']] = ($d['dev'][$x['dev']] ?? 0) + 1;
                            $ref = $x['ref'] !== '' ? $x['ref'] : '(accès direct)';
                            $d['ref'][$ref] = ($d['ref'][$ref] ?? 0) + 1;
                        } else {
                            $v = [$x['sec'], $v[1], $v[2] + 1, $v[3]];
                        }
                        $vs[$x['v']] = $v;
                    }
                    $n++;
                }
                $offsets[$day] = ftell($fp);
                fclose($fp);
                if ($vs) {
                    $d['visitors'] = count($vs);
                    $d['visits'] = array_sum(array_column($vs, 1));
                    $d['bounces'] = array_sum(array_column($vs, 3)) + count(array_filter($vs, fn ($v) => $v[2] === 1));
                }
                arsort($d['pages']);
                $d['pages'] = array_slice($d['pages'], 0, 600, true);
                arsort($d['ref']);
                $d['ref'] = array_slice($d['ref'], 0, 60, true);
                $days[$day] = $d;
                $state[$day] = $vs;
            }
            foreach (array_keys($state) as $day) {
                if ($day < date('Y-m-d', strtotime('-2 days'))) {
                    unset($state[$day]);
                }
            }
            ksort($days);
            JsonStore::write(self::OFFSETS, $offsets);
            JsonStore::write(self::STATE, $state);
            return array_slice($days, -800, null, true); // un peu plus de deux ans
        });
        return $n;
    }

    /** @return array<string,array> date => totaux du jour */
    public static function days(): array
    {
        return JsonStore::read(self::DAYS, []) ?: [];
    }

    /** Temps réel : visiteurs en ligne (5 min), pages qu'ils regardent, pages vues minute par minute (30 min). */
    public static function live(): array
    {
        $f = self::RAW . '/' . date('Y-m-d') . '.log';
        $now = (int) date('H') * 3600 + (int) date('i') * 60 + (int) date('s');
        $out = ['online' => 0, 'pages' => [], 'minutes' => array_fill(0, 30, 0), 'today' => 0, 'devices' => []];
        if (!is_file($f)) {
            return $out;
        }
        $size = (int) filesize($f);
        $fp = fopen($f, 'r');
        fseek($fp, max(0, $size - 400000));
        if ($size > 400000) {
            fgets($fp);
        }
        $who = [];
        while (($line = fgets($fp)) !== false) {
            $x = self::parse($line);
            if (!$x || !str_contains($line, ':')) {
                continue;
            }
            $age = $now - $x['sec'];
            if ($age < 0 || $age >= 1800) {
                continue;
            }
            $out['minutes'][29 - intdiv($age, 60)]++;
            if ($age < self::LIVE && $x['v'] !== '') {
                $who[$x['v']] = $x;
            }
        }
        fclose($fp);
        foreach ($who as $x) {
            $p = preg_replace('/\?.*$/', '', $x['path']);
            $out['pages'][$p] = ($out['pages'][$p] ?? 0) + 1;
            $out['devices'][$x['dev']] = ($out['devices'][$x['dev']] ?? 0) + 1;
        }
        arsort($out['pages']);
        $out['pages'] = array_slice($out['pages'], 0, 8, true);
        $out['online'] = count($who);
        $out['today'] = (int) ((self::days()[date('Y-m-d')]['total'] ?? 0));
        return $out;
    }

    /** Remise à zéro (avant l'ouverture) : les données sont mises de côté dans un dossier daté, pas effacées. */
    public static function reset(): string
    {
        $dest = STORAGE_PATH . '/stats-avant-remise-a-zero-' . date('Y-m-d-His');
        if (is_dir(self::DIR)) {
            @rename(self::DIR, $dest);
        }
        JsonStore::forget();
        @mkdir(self::RAW, 0775, true);
        JsonStore::write(self::DIR . '/remise-a-zero.json', ['at' => date('c')]);
        return basename($dest);
    }

    public static function resetAt(): ?string
    {
        return JsonStore::read(self::DIR . '/remise-a-zero.json', [])['at'] ?? null;
    }

    // ------------------------------------------------------------------ anciens appels

    /** @return array<string,int> date => pages vues, sur $n jours (aujourd'hui inclus) */
    public static function series(int $n = 30): array
    {
        self::aggregate();
        $days = self::days();
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
        $acc = [];
        $from = date('Y-m-d', strtotime('-' . ($n - 1) . ' day'));
        foreach (self::days() as $d => $v) {
            if ($d >= $from) {
                foreach ($v['pages'] ?? [] as $p => $c) {
                    $acc[$p] = ($acc[$p] ?? 0) + $c;
                }
            }
        }
        arsort($acc);
        return array_slice($acc, 0, $limit, true);
    }

    public static function englishShare(int $n = 30): float
    {
        $t = $en = 0;
        $from = date('Y-m-d', strtotime('-' . ($n - 1) . ' day'));
        foreach (self::days() as $d => $v) {
            if ($d >= $from) {
                $t += $v['total'] ?? 0;
                $en += $v['en'] ?? 0;
            }
        }
        return $t ? round($en / $t * 100, 1) : 0.0;
    }
}
