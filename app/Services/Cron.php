<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\JsonStore;
use App\Core\Settings;
use App\Data\Derived;
use App\Data\Fiches;
use App\Data\Index;
use App\Front\Donations;

/**
 * Tâches planifiées. Une seule ligne de cron chez l'hébergeur (o2switch) :
 *
 *   * /5 * * * *  php /home/<compte>/sochauxretro/bin/console.php cron
 *
 * Chaque tâche a sa fréquence ; l'état est conservé dans storage/cron.json
 * (consultable dans le back-office). Un verrou empêche deux passages simultanés.
 */
final class Cron
{
    private const STATE = STORAGE_PATH . '/cron.json';
    private const LOCK = STORAGE_PATH . '/cron.lock';

    /** Tâche => [intervalle en secondes, description] */
    public const TASKS = [
        'publication' => [0, 'Publication des fiches programmées'],
        'statistiques' => [0, 'Recalcul des statistiques si nécessaire'],
        'audience' => [0, 'Mesure d’audience anonyme (agrégation)'],
        'traductions' => [0, 'Traduction anglaise des fiches (Gemini)'],
        'correcteur' => [0, 'Correcteur d’orthographe : vérification des fiches nouvelles ou modifiées'],
        'newsletter' => [0, 'Newsletter « Ce jour-là »'],
        'geolocalisation' => [600, 'Géolocalisation des stades et lieux (OpenStreetMap)'],
        'medias' => [0, 'Médiathèque : dimensions, poids et empreintes des fichiers (doublons)'],
        'videos' => [3600, 'Vignettes des vidéos (YouTube, Dailymotion, Vimeo, Rutube)'],
        'assistant' => [3600, 'Index sémantique de l’assistant IA'],
        'dons' => [3600, 'Synchronisation des dons (Stripe, PayPal)'],
        'plan-du-site' => [86400, 'Plan du site (sitemap.xml)'],
        'sauvegarde' => [3600, 'Sauvegarde quotidienne'],
        'recus-annuels' => [86400, 'Reçus fiscaux annuels (janvier)'],
        'menage' => [86400, 'Purges RGPD et fichiers temporaires'],
    ];

    public static function run(?string $only = null): array
    {
        $fp = fopen(self::LOCK, 'c');
        if (!$fp || !flock($fp, LOCK_EX | LOCK_NB)) {
            echo "Une exécution est déjà en cours.\n";
            return [];
        }
        @set_time_limit(280);
        $state = JsonStore::read(self::STATE, []) ?: [];
        $report = [];
        foreach (self::TASKS as $task => [$every, $label]) {
            if ($only && $only !== $task) {
                continue;
            }
            $last = (int) ($state[$task]['at'] ?? 0);
            if (!$only && $every > 0 && time() - $last < $every) {
                continue;
            }
            $t0 = microtime(true);
            try {
                $res = self::task($task);
                $ok = true;
            } catch (\Throwable $e) {
                $res = $e->getMessage();
                $ok = false;
                error_log("[cron] $task : " . $e);
            }
            if ($res === null) {
                continue; // rien à faire : on ne journalise pas
            }
            $state[$task] = ['at' => time(), 'ok' => $ok, 'ms' => (int) ((microtime(true) - $t0) * 1000), 'result' => is_string($res) ? mb_substr($res, 0, 300) : $res];
            $report[$task] = $state[$task];
            echo date('H:i:s') . " $task : " . (is_string($res) ? $res : json_encode($res, JSON_UNESCAPED_UNICODE)) . "\n";
        }
        $state['_last'] = time();
        JsonStore::write(self::STATE, $state);
        flock($fp, LOCK_UN);
        fclose($fp);
        return $report;
    }

    public static function state(): array
    {
        return JsonStore::read(self::STATE, []) ?: [];
    }

    /** @return mixed résultat à journaliser, ou null si la tâche n'avait rien à faire */
    private static function task(string $task): mixed
    {
        switch ($task) {
            case 'publication':
                $n = 0;
                Fiches::batch(function () use (&$n) {
                    foreach (Index::all() as $id => $s) {
                        if ($s['status'] === 'planifie' && $s['publish_at'] && strtotime((string) $s['publish_at']) <= time()) {
                            $doc = Fiches::get((int) $id);
                            if ($doc) {
                                $doc['status'] = 'publie';
                                $doc['date'] = $doc['date'] ?: date('c');
                                Fiches::save($doc, ['name' => 'Publication programmée'], 'Publication programmée');
                                $n++;
                            }
                        }
                    }
                });
                return $n ? "$n fiche(s) publiée(s)" : null;

            case 'statistiques':
                if (!Derived::isDirty()) {
                    return null;
                }
                $d = Derived::rebuild();
                return sprintf('%d matchs, %d personnes reliées (%.1f s)', count($d['matches']), count($d['person_totals']), $d['duration']);

            case 'audience':
                $n = Stats::aggregate();
                return $n ? "$n pages vues agrégées" : null;

            case 'traductions':
                if (!Translator::enabled() || !Settings::get('translation.auto_translate', true)) {
                    return null;
                }
                $r = Translator::run(8);
                return $r['done'] ? $r : null;

            case 'correcteur':
                return Proofreader::run(40);

            case 'newsletter':
                return Newsletter::tick();

            case 'geolocalisation':
                if (!Settings::get('map.geocoding', true)) {
                    return null;
                }
                $r = Geo::run(40, true);
                return $r['stades'] + $r['lieux'] > 0 ? $r : null;

            case 'medias':
                return self::mediaFacts(400);

            case 'videos':
                $r = VideoThumbs::run(100);
                return $r['faites'] + $r['echecs'] > 0 ? $r : null;

            case 'assistant':
                if (!Rag::enabled() || !Gemini::embedModel()) {
                    return null;
                }
                return Rag::reindex(null, 300);

            case 'dons':
                return Settings::get('donations.enabled', false) ? Donations::sync() : null;

            case 'plan-du-site':
                \App\Front\Seo::build(true);
                return 'ok';

            case 'sauvegarde':
                // Une fois par jour, à partir de l'heure réglée.
                if (!Settings::get('backups.enabled', true) || (int) date('G') < (int) Settings::get('backups.hour', 3)) {
                    return null;
                }
                $last = Backup::list()[0]['at'] ?? null;
                if ($last && substr($last, 0, 10) === date('Y-m-d')) {
                    return null;
                }
                return Backup::run();

            case 'recus-annuels':
                if ((int) date('n') !== 1 || (int) date('j') < 15 || !Settings::get('donations.tax_receipts', false)) {
                    return null;
                }
                $year = (int) date('Y') - 1;
                $flag = STORAGE_PATH . "/dons/recus-annuels-$year.done";
                if (is_file($flag)) {
                    return null;
                }
                $done = Donations::annualReceipts($year);
                touch($flag);
                return count($done) . " reçu(s) annuel(s) $year";

            case 'menage':
                return self::housekeeping();
        }
        return null;
    }

    /** Complète les métadonnées techniques des médias (par lots, en une seule écriture). */
    /** Complète dimensions, poids et empreinte (sha1) des fichiers de la médiathèque, par lots. */
    public static function mediaFacts(int $max): ?string
    {
        $changes = [];
        foreach (\App\Data\Media::all() as $rel => $m) {
            if (count($changes) >= $max) {
                break;
            }
            if (!empty($m['sha1']) && !empty($m['size'])) {
                continue;
            }
            $file = \App\Data\Media::file((string) $rel);
            if (!$file) {
                continue;
            }
            $info = @getimagesize($file);
            $changes[(string) $rel] = array_filter([
                'size' => filesize($file) ?: null,
                'width' => $info[0] ?? ($m['width'] ?? null),
                'height' => $info[1] ?? ($m['height'] ?? null),
                'mime' => $m['mime'] ?? ((new \finfo(FILEINFO_MIME_TYPE))->file($file) ?: null),
                'sha1' => sha1_file($file) ?: null,
            ], fn ($v) => $v !== null);
        }
        if (!$changes) {
            return null;
        }
        $n = \App\Data\Media::putMany($changes);
        return "$n média(s) complété(s)";
    }

    /** Purges : journaux de l'assistant, consentements (13 mois), e-mails (12 mois), limites, sessions, caches. */
    private static function housekeeping(): array
    {
        $out = ['assistant' => Rag::purgeLogs()];
        $purgeMonthly = function (string $dir, int $months): int {
            $n = 0;
            $limit = date('Y-m', strtotime("-$months months"));
            foreach (glob($dir . '/*.jsonl') ?: [] as $f) {
                if (basename($f, '.jsonl') < $limit) {
                    @unlink($f);
                    $n++;
                }
            }
            return $n;
        };
        $out['consentements'] = $purgeMonthly(STORAGE_PATH . '/consent', 13);
        $out['emails'] = $purgeMonthly(STORAGE_PATH . '/mail', 12);
        $old = function (string $pattern, int $seconds): int {
            $n = 0;
            foreach (glob($pattern) ?: [] as $f) {
                if (is_file($f) && filemtime($f) < time() - $seconds) {
                    @unlink($f);
                    $n++;
                }
            }
            return $n;
        };
        $out['limites'] = $old(STORAGE_PATH . '/ratelimit/*', 2 * 86400);
        $out['sessions'] = $old(STORAGE_PATH . '/sessions/sess_*', 14 * 86400);
        $out['partage'] = $old(STORAGE_PATH . '/cache/share/*.png', 30 * 86400);
        $out['correcteur'] = Proofreader::purgeCache();
        // Dons abandonnés : les coordonnées des paiements jamais finalisés sont effacées après 30 jours.
        $out['dons_abandonnes'] = 0;
        foreach (Donations::all() as $d) {
            if (in_array($d['status'], ['abandoned', 'failed'], true) && empty($d['payments']) && strtotime($d['created']) < time() - 30 * 86400 && !empty($d['donor']['email'])) {
                Donations::update($d['id'], fn ($x) => ['donor' => ['first' => '', 'last' => '', 'email' => '', 'address' => '', 'zip' => '', 'city' => '', 'country' => ''], 'wall_name' => ''] + $x);
                $out['dons_abandonnes']++;
            }
        }
        return $out;
    }
}
