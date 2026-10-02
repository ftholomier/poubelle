<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;
use App\Core\Fs;
use App\Core\Request;
use App\Core\Str;

/**
 * Statistiques d'audience sans cookie ni service tiers :
 * les événements sont ajoutés à un journal du jour puis agrégés par le cron.
 */
final class Stats
{
    private const DIR = STORAGE_PATH . '/data/stats';
    public const METRICS = [
        'pv' => 'Pages vues', 'uniq' => 'Visiteurs uniques', 'pro_view' => 'Fiches consultées', 'phone' => 'Numéros affichés',
        'site' => 'Clics vers les sites', 'devis' => 'Demandes de devis', 'message' => 'Messages aux pros', 'search' => 'Recherches',
        'chat' => 'Conversations IA', 'register' => 'Inscriptions pros', 'review' => 'Avis déposés', 'fav' => 'Favoris ajoutés', 'bots' => 'Robots',
    ];

    public static function hit(string $type, int $proId = 0, string $extra = ''): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }
        $bot = Request::isBot() ? 1 : 0;
        $line = time() . "\t" . $type . "\t" . $proId . "\t" . Request::ipHash() . "\t" . $bot . "\t" . str_replace(["\t", "\n"], ' ', mb_substr($extra, 0, 120)) . "\n";
        $file = self::DIR . '/live/' . date('Y-m-d') . '.log';
        try {
            if (!is_dir(dirname($file))) {
                Fs::ensureDir(dirname($file));
            }
            @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
        } catch (\Throwable) {
        }
    }

    /** Agrège les nouvelles lignes des journaux (cron, toutes les 5 minutes). */
    public static function aggregate(): array
    {
        $statePath = self::DIR . '/agg_state.json';
        $state = Fs::readJson($statePath, []);
        $proDelta = [];
        $lines = 0;
        foreach (glob(self::DIR . '/live/*.log') ?: [] as $file) {
            $day = basename($file, '.log');
            $offset = (int) ($state[$day] ?? 0);
            $size = filesize($file);
            if ($size <= $offset) {
                if ($day < date('Y-m-d', strtotime('-7 days'))) {
                    @unlink($file);
                    unset($state[$day]);
                }
                continue;
            }
            $fh = fopen($file, 'r');
            fseek($fh, $offset);
            $daily = Fs::readJson(self::DIR . '/daily/' . $day . '.json', []);
            $uniqFile = self::DIR . '/daily/' . $day . '.uniq';
            $uniq = is_file($uniqFile) ? array_flip(explode("\n", trim((string) file_get_contents($uniqFile)))) : [];
            $searches = [];
            while (($l = fgets($fh)) !== false) {
                if (!str_ends_with($l, "\n")) {
                    break; // ligne en cours d'écriture : on la reprendra au prochain passage
                }
                $offset += strlen($l);
                $parts = explode("\t", rtrim($l, "\n"));
                if (count($parts) < 5) {
                    continue;
                }
                [, $type, $pro, $ip, $bot] = $parts;
                $extra = $parts[5] ?? '';
                $lines++;
                if ($bot === '1') {
                    $daily['bots'] = ($daily['bots'] ?? 0) + 1;
                    continue;
                }
                $daily[$type] = ($daily[$type] ?? 0) + 1;
                if ($type === 'pv' && $ip !== '') {
                    $uniq[$ip] = true;
                }
                if ((int) $pro > 0 && in_array($type, ['pro_view', 'phone', 'site', 'message', 'devis_direct'], true)) {
                    $proDelta[(int) $pro][$day][$type] = ($proDelta[(int) $pro][$day][$type] ?? 0) + 1;
                }
                if ($type === 'search' && $extra !== '') {
                    $k = mb_strtolower(trim($extra));
                    $searches[$k] = ($searches[$k] ?? 0) + 1;
                }
            }
            fclose($fh);
            unset($uniq['']);
            $daily['uniq'] = count($uniq);
            Fs::writeJson(self::DIR . '/daily/' . $day . '.json', $daily);
            Fs::writeAtomic($uniqFile, implode("\n", array_keys($uniq)));
            if ($searches) {
                $mf = self::DIR . '/searches/' . substr($day, 0, 7) . '.json';
                $m = Fs::readJson($mf, []);
                foreach ($searches as $q => $n) {
                    $m[$q] = ($m[$q] ?? 0) + $n;
                }
                arsort($m);
                Fs::writeJson($mf, array_slice($m, 0, 2000, true));
            }
            $state[$day] = $offset;
        }
        // Compteurs par pro (fiche + série quotidienne pour son tableau de bord)
        if ($proDelta) {
            $patches = [];
            foreach ($proDelta as $proId => $days) {
                $pf = self::DIR . '/pros/' . $proId . '.json';
                $series = Fs::readJson($pf, []);
                $tot = ['pro_view' => 0, 'phone' => 0, 'site' => 0];
                foreach ($days as $day => $vals) {
                    foreach ($vals as $k => $v) {
                        $series[$day][$k] = ($series[$day][$k] ?? 0) + $v;
                        if (isset($tot[$k])) {
                            $tot[$k] += $v;
                        }
                    }
                }
                ksort($series);
                Fs::writeJson($pf, array_slice($series, -400, null, true));
                $patches[$proId] = static function (array $p) use ($tot): array {
                    $p['stats']['views'] = (int) ($p['stats']['views'] ?? 0) + $tot['pro_view'];
                    $p['stats']['phone_reveals'] = (int) ($p['stats']['phone_reveals'] ?? 0) + $tot['phone'];
                    $p['stats']['website_clicks'] = (int) ($p['stats']['website_clicks'] ?? 0) + $tot['site'];
                    return $p;
                };
            }
            Store::pros()->updateMany($patches);
        }
        Fs::writeJson($statePath, $state);
        return ['lines' => $lines, 'pros' => count($proDelta)];
    }

    /** Série quotidienne d'une métrique sur N jours. @return array<string,int> */
    public static function series(string $metric, int $days = 30): array
    {
        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $data = Fs::readJson(self::DIR . '/daily/' . $d . '.json', []);
            $out[$d] = (int) ($data[$metric] ?? 0);
        }
        return $out;
    }

    public static function total(string $metric, int $days = 30): int
    {
        return array_sum(self::series($metric, $days));
    }

    /** @return array<string,array<string,int>> */
    public static function proSeries(int $proId, int $days = 30): array
    {
        $series = Fs::readJson(self::DIR . '/pros/' . $proId . '.json', []);
        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $out[$d] = ['pro_view' => (int) ($series[$d]['pro_view'] ?? 0), 'phone' => (int) ($series[$d]['phone'] ?? 0), 'site' => (int) ($series[$d]['site'] ?? 0), 'message' => (int) ($series[$d]['message'] ?? 0)];
        }
        return $out;
    }

    public static function topSearches(int $limit = 20, ?string $month = null): array
    {
        $m = Fs::readJson(self::DIR . '/searches/' . ($month ?? date('Y-m')) . '.json', []);
        return array_slice($m, 0, $limit, true);
    }

    /** Historique mensuel (ancien site + nouveau) pour les graphiques longs. */
    public static function monthly(): array
    {
        $legacy = Fs::readJson(self::DIR . '/legacy_monthly.json', ['requests' => [], 'messages' => []]);
        $req = $legacy['requests'] ?? [];
        $msg = $legacy['messages'] ?? [];
        foreach (Store::requests()->iterate() as $r) {
            if (($r['src'] ?? '') === 'legacy') {
                continue;
            }
            $k = substr((string) $r['created'], 0, 7);
            $req[$k] = ($req[$k] ?? 0) + 1;
        }
        // messages reçus depuis la migration (les précédents sont dans l'historique de l'ancien site)
        $cutoff = (string) (Fs::readJson(STORAGE_PATH . '/data/import_done.json', [])['at'] ?? '');
        foreach (Store::messages()->iterate() as $m) {
            if ($cutoff === '' || $m['created'] <= $cutoff) {
                break;
            }
            $k = substr((string) $m['created'], 0, 7);
            $msg[$k] = ($msg[$k] ?? 0) + 1;
        }
        ksort($req);
        ksort($msg);
        return ['requests' => $req, 'messages' => $msg];
    }

    /** Chiffres publics (page d'accueil, textes SEO). */
    public static function publicNumbers(): array
    {
        return Cache::remember('stats_public', 3600, static function (): array {
            $pros = Pros::publicIndex();
            $cities = [];
            $byCat = [];
            foreach ($pros as $p) {
                if ($p['insee']) {
                    $cities[$p['insee']] = true;
                }
                foreach ($p['cats'] as $c) {
                    $byCat[$c] = ($byCat[$c] ?? 0) + 1;
                }
            }
            $legacy = Fs::readJson(self::DIR . '/legacy_monthly.json', ['requests' => []]);
            $requests = max(Store::requests()->count(), array_sum($legacy['requests'] ?? []));
            return ['pros' => count($pros), 'cities' => count($cities), 'by_cat' => $byCat, 'requests' => $requests, 'members' => Store::pros()->count()];
        });
    }

    /** Badge « très demandé » : 10 % des pros les plus sollicités sur 90 jours (3 demandes minimum). */
    public static function recomputeHot(): int
    {
        $since = date('c', strtotime('-90 days'));
        $score = [];
        foreach (Store::messages()->iterate() as $m) {
            if ($m['created'] < $since) {
                break;
            }
            if (in_array($m['status'], ['delivered', 'read', 'replied'], true)) {
                $score[$m['pro']] = ($score[$m['pro']] ?? 0) + 1;
            }
        }
        foreach (Store::requests()->iterate() as $r) {
            if ($r['created'] < $since) {
                break;
            }
            if ($r['status'] === 'diffused') {
                foreach (Store::requests()->get((int) $r['id'])['recipients'] ?? [] as $pid) {
                    $score[(int) $pid] = ($score[(int) $pid] ?? 0) + 0.5;
                }
            }
        }
        foreach (Pros::publicIndex() as $id => $p) {
            $score[$id] = ($score[$id] ?? 0) + (int) (Store::pros()->get($id)['stats']['messages_12m'] ?? 0) / 8;
        }
        arsort($score);
        $published = Pros::publicIndex();
        $quota = max(1, (int) ceil(count($published) * 0.1));
        $hot = [];
        foreach ($score as $id => $s) {
            if (count($hot) >= $quota || $s < 3) {
                break;
            }
            if (isset($published[$id])) {
                $hot[(int) $id] = true;
            }
        }
        $patches = [];
        foreach ($published as $id => $p) {
            $want = isset($hot[$id]);
            if ($want !== (bool) $p['hot']) {
                $patches[$id] = static function (array $pro) use ($want): array {
                    $pro['stats']['hot'] = $want;
                    return $pro;
                };
            }
        }
        if ($patches) {
            Store::pros()->updateMany($patches);
            Pros::changed();
        }
        return count($hot);
    }

    public static function label(string $metric): string
    {
        return self::METRICS[$metric] ?? Str::ucfirst($metric);
    }
}
