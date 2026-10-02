<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;
use App\Core\Env;
use App\Core\ErrorHandler;
use App\Core\Fs;
use App\Core\Logger;
use App\Core\RateLimiter;
use App\Core\Url;

/**
 * Tâches planifiées. À lancer chaque minute :
 *   * * * * * php /chemin/bin/cron.php            (recommandé)
 * ou par appel web : https://site/cron/{CRON_TOKEN}
 * À défaut, un « cron du pauvre » s'exécute après les réponses HTTP (PHP-FPM).
 */
final class Cron
{
    private const STATE = STORAGE_PATH . '/data/cron.json';
    private const TICK = STORAGE_PATH . '/cache/cron.tick';
    private const LOCK = STORAGE_PATH . '/cache/cron.lock';

    /** clé => [libellé, intervalle en secondes] */
    public const TASKS = [
        'mail' => ['Envoi des emails en file', 60],
        'campaigns' => ['Campagnes programmées', 60],
        'stats' => ['Agrégation des statistiques', 300],
        'sitemaps' => ['Sitemaps XML', 900],
        'health' => ['Contrôles de santé', 3600],
        'digest' => ['Récapitulatif quotidien des administrateurs', 86400],
        'hot' => ['Badge « très demandé »', 86400],
        'cleanup' => ['Nettoyage (caches, sessions, jetons, journaux)', 86400],
        'backup' => ['Sauvegarde automatique', 86400],
        'weekly' => ['Bilan hebdomadaire des pros', 604800],
    ];

    public static function state(): array
    {
        return Fs::readJson(self::STATE, ['tasks' => [], 'last' => null]);
    }

    /**
     * Exécute les tâches arrivées à échéance.
     * @param string[]|null $only tâches à forcer (ignore les intervalles)
     */
    public static function run(string $source = 'cli', ?array $only = null, int $budget = 50): array
    {
        Fs::ensureDir(dirname(self::LOCK));
        $fh = fopen(self::LOCK, 'c');
        if (!$fh || !flock($fh, LOCK_EX | LOCK_NB)) {
            return ['skipped' => 'Une exécution est déjà en cours'];
        }
        @touch(self::TICK);
        $t0 = microtime(true);
        $state = self::state();
        $report = [];
        try {
            foreach (self::TASKS as $key => [$label, $every]) {
                if ($only !== null && !in_array($key, $only, true)) {
                    continue;
                }
                if ($only === null && !self::due($key, $every, $state)) {
                    continue;
                }
                if ($only === null && microtime(true) - $t0 > $budget && $key !== 'mail') {
                    break; // le reste attendra le passage suivant
                }
                $t = microtime(true);
                try {
                    $res = self::task($key);
                    $state['tasks'][$key] = ['at' => date('c'), 'ok' => true, 'ms' => (int) ((microtime(true) - $t) * 1000), 'result' => $res];
                    $report[$key] = $res;
                } catch (\Throwable $e) {
                    ErrorHandler::report($e);
                    $state['tasks'][$key] = ['at' => date('c'), 'ok' => false, 'ms' => (int) ((microtime(true) - $t) * 1000), 'result' => $e->getMessage()];
                    $report[$key] = 'Erreur : ' . $e->getMessage();
                    Logger::log('cron', 'Tâche en erreur : ' . $key, ['error' => $e->getMessage()], 'error');
                }
            }
            $state['last'] = ['at' => date('c'), 'source' => $source, 'ms' => (int) ((microtime(true) - $t0) * 1000)];
            if ($source !== 'web-auto') {
                $state['last_system'] = date('c');
            }
            Fs::writeJson(self::STATE, $state, true);
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
        return $report;
    }

    private static function due(string $key, int $every, array $state): bool
    {
        $last = isset($state['tasks'][$key]['at']) ? (int) strtotime((string) $state['tasks'][$key]['at']) : 0;
        $now = time();
        return match ($key) {
            // tâches quotidiennes à heure fixe
            'digest' => (int) date('G') >= (int) Settings::get('notifications.digest_hour', 8) && date('Y-m-d', $last) !== date('Y-m-d'),
            'backup', 'cleanup', 'hot' => (int) date('G') >= 3 && date('Y-m-d', $last) !== date('Y-m-d'),
            // le lundi matin
            'weekly' => date('N') === '1' && (int) date('G') >= 9 && date('o-W', $last) !== date('o-W'),
            default => $now - $last >= $every - 5,
        };
    }

    private static function task(string $key): mixed
    {
        return match ($key) {
            'mail' => Mail::processQueue(),
            'campaigns' => Mailing::launchScheduled(),
            'stats' => Stats::aggregate(),
            'sitemaps' => Seo::buildSitemaps() ? 'régénérés' : 'à jour',
            'health' => self::health(),
            'digest' => Notify::dailyDigest() ? 'envoyé' : 'aucun destinataire',
            'hot' => Stats::recomputeHot() . ' pros « très demandés »',
            'cleanup' => self::cleanup(),
            'backup' => self::backup(),
            'weekly' => self::weekly(),
        };
    }

    /** Cron « du pauvre » : après la réponse HTTP, au plus une fois par minute, si aucun cron système ne tourne. */
    public static function maybeRun(): void
    {
        $tick = @filemtime(self::TICK) ?: 0;
        if ($tick > time() - 60) {
            return;
        }
        $state = self::state();
        if (!empty($state['last_system']) && strtotime((string) $state['last_system']) > time() - 600) {
            @touch(self::TICK);
            return; // un vrai cron est en place
        }
        ignore_user_abort(true);
        @set_time_limit(120);
        self::run('web-auto', null, 20);
    }

    /** Le cron tourne-t-il ? (pour le tableau de bord) */
    public static function status(): array
    {
        $s = self::state();
        $last = isset($s['last']['at']) ? (int) strtotime((string) $s['last']['at']) : 0;
        return [
            'last' => $s['last']['at'] ?? null,
            'source' => $s['last']['source'] ?? null,
            'ok' => $last > time() - 900,
            'system' => !empty($s['last_system']) && strtotime((string) $s['last_system']) > time() - 900,
            'tasks' => $s['tasks'] ?? [],
        ];
    }

    // ------------------------------------------------------------------ tâches

    private static function health(): array
    {
        $issues = [];
        if (Push::available() && Push::publicKey() === '') {
            Push::ensureKeys(); // clés des notifications push générées au premier passage
        }
        if (Geo::keylessCarto((string) Env::get('MAP_TILE_URL', ''))) {
            Env::write(['MAP_TILE_URL' => '', 'MAP_TILE_ATTRIBUTION' => '']); // CARTO exige une clé : retour à OpenStreetMap
        }
        $free = @disk_free_space(STORAGE_PATH);
        if ($free !== false && $free < 300 * 1024 * 1024) {
            $issues[] = 'Espace disque faible : ' . Fs::humanSize($free) . ' disponibles.';
        }
        foreach ([STORAGE_PATH . '/data', STORAGE_PATH . '/cache', STORAGE_PATH . '/logs', PUBLIC_PATH . '/media'] as $dir) {
            if (is_dir($dir) && !is_writable($dir)) {
                $issues[] = 'Dossier non inscriptible : ' . str_replace(BASE_PATH . '/', '', $dir);
            }
        }
        $failed = Store::mailQueue()->count(static fn ($m) => $m['status'] === 'failed' && $m['created'] >= date('c', strtotime('-24 hours')));
        if ($failed >= 10) {
            $issues[] = "$failed emails en échec définitif ces dernières 24 h : vérifiez la configuration SMTP.";
        }
        $queued = count(Store::mailQueue()->ids('status', 'queued'));
        if ($queued > 2000) {
            $issues[] = "$queued emails en attente d'envoi.";
        }
        $errors = 0;
        foreach (\App\Core\Logger::tail('error', 200, date('Y-m-d')) as $row) {
            if (strtotime((string) ($row['t'] ?? '')) > time() - 3600) {
                $errors++;
            }
        }
        if ($errors >= 20) {
            $issues[] = "$errors erreurs techniques dans la dernière heure.";
        }
        if (Settings::get('ai.enabled') && Ai::configured()) {
            $u = Ai::usage();
            $limit = (int) Settings::get('ai.daily_limit', 1500);
            if ($limit > 0 && ($u['calls'] ?? 0) >= $limit * 0.9) {
                $issues[] = 'Quota quotidien de l\'IA presque atteint (' . ($u['calls'] ?? 0) . ' / ' . $limit . ').';
            }
        }
        if (Env::bool('APP_DEBUG') && Env::get('APP_ENV') === 'production') {
            $issues[] = 'APP_DEBUG est activé en production : désactivez-le dans les réglages.';
        }
        if ($issues && RateLimiter::attempt('health-alert:' . md5(implode('|', $issues)), 1, 86400)) {
            Notify::admin('system', 'Contrôle de santé : ' . count($issues) . ' point(s) à vérifier', implode("\n", $issues), Url::admin('maintenance'), 'warning');
        }
        return $issues ?: ['ok'];
    }

    private static function cleanup(): array
    {
        $r = [];
        $r['pages_cache'] = Cache::prunePages();
        $r['rate_limits'] = RateLimiter::prune();
        $r['logs'] = Logger::prune(180);
        // sessions expirées (le ramasse-miettes PHP est souvent désactivé sur les serveurs Debian)
        $n = 0;
        foreach (glob(STORAGE_PATH . '/sessions/sess_*') ?: [] as $f) {
            if (filemtime($f) < time() - 86400 * 2 && @unlink($f)) {
                $n++;
            }
        }
        $r['sessions'] = $n;
        // fichiers temporaires
        $n = 0;
        foreach (glob(STORAGE_PATH . '/tmp/*') ?: [] as $f) {
            if (filemtime($f) < time() - 86400) {
                is_dir($f) ? Fs::rmrf($f) : @unlink($f);
                $n++;
            }
        }
        $r['tmp'] = $n;
        // emails envoyés de plus de 30 jours, échecs de plus de 90 jours
        $old = [];
        $sentLimit = date('c', strtotime('-30 days'));
        $failLimit = date('c', strtotime('-90 days'));
        foreach (Store::mailQueue()->iterate(false) as $id => $m) {
            if (($m['status'] === 'sent' && $m['created'] < $sentLimit) || ($m['status'] === 'failed' && $m['created'] < $failLimit)) {
                $old[] = (int) $id;
            }
        }
        foreach ($old as $id) {
            Store::mailQueue()->delete($id);
        }
        $r['mail_queue'] = count($old);
        // notifications lues de plus de 90 jours
        $old = [];
        $limit = date('c', strtotime('-90 days'));
        foreach (Store::notifications()->iterate(false) as $id => $n2) {
            if ($n2['read'] && $n2['created'] < $limit) {
                $old[] = (int) $id;
            }
        }
        foreach ($old as $id) {
            Store::notifications()->delete($id);
        }
        $r['notifications'] = count($old);
        // conversations de l'assistant de plus de 6 mois (minimisation des données)
        $old = [];
        $limit = date('c', strtotime('-180 days'));
        foreach (Store::chats()->iterate(false) as $id => $c) {
            if (($c['updated'] ?: $c['created']) < $limit) {
                $old[] = (int) $id;
            }
        }
        foreach ($old as $id) {
            Store::chats()->delete($id);
        }
        $r['chats'] = count($old);
        // jetons expirés
        $old = [];
        foreach (Store::tokens()->iterate(false) as $id => $t) {
            if ($t['used'] || ($t['exp'] !== '' && $t['exp'] < date('c'))) {
                $old[] = (int) $id;
            }
        }
        foreach ($old as $id) {
            Store::tokens()->delete($id);
        }
        $r['tokens'] = count($old);
        // fin automatique du mode congés des pros
        $today = date('Y-m-d');
        $patches = [];
        foreach (Store::pros()->iterate(false) as $id => $p) {
            if (!$p['vacation']) {
                continue;
            }
            $full = Store::pros()->get((int) $id);
            $until = (string) ($full['settings']['vacation_until'] ?? '');
            if ($until !== '' && $until < $today) {
                $patches[(int) $id] = static function (array $x): array {
                    $x['settings']['vacation'] = false;
                    $x['settings']['vacation_until'] = '';
                    return $x;
                };
            }
        }
        if ($patches) {
            Store::pros()->updateMany($patches);
            Pros::changed();
        }
        $r['vacations_ended'] = count($patches);
        return $r;
    }

    private static function backup(): string
    {
        if (!Backup::available()) {
            return 'extension zip absente';
        }
        $name = Backup::create((bool) Settings::get('backup.include_media', false), false, 'auto');
        $deleted = Backup::prune((int) Settings::get('backup.keep', 7));
        return $name . ($deleted ? " ($deleted ancienne(s) supprimée(s))" : '');
    }

    /** Bilan hebdomadaire envoyé aux pros dont la fiche a eu de l'activité. */
    private static function weekly(): string
    {
        $n = 0;
        $unsub = Store::doc('unsubscribed')->get('pros', []);
        foreach (Pros::publicIndex() as $id => $light) {
            $pro = Store::pros()->get((int) $id);
            if (!$pro || empty($pro['email']) || ($pro['settings']['weekly_report'] ?? true) === false || isset($unsub[(string) $id])) {
                continue;
            }
            $series = Stats::proSeries((int) $id, 7);
            $views = array_sum(array_column($series, 'pro_view'));
            if ($views === 0) {
                continue;
            }
            $phone = array_sum(array_column($series, 'phone'));
            $site = array_sum(array_column($series, 'site'));
            $since = date('c', strtotime('-7 days'));
            $msgs = 0;
            foreach (Store::messages()->ids('pro_id', (int) $id) as $mid) {
                $l = Store::messages()->light($mid);
                if ($l && $l['created'] >= $since && $l['status'] === 'delivered') {
                    $msgs++;
                }
            }
            $reqs = 0;
            foreach (Store::requests()->ids('recipients', (int) $id) as $rid) {
                $l = Store::requests()->light($rid);
                if ($l && $l['created'] >= $since) {
                    $reqs++;
                }
            }
            $rows = [
                'Vues de votre fiche' => (string) $views,
                'Numéros affichés' => (string) $phone,
                'Clics vers votre site' => (string) $site,
                'Messages reçus' => (string) $msgs,
                'Demandes de devis reçues' => (string) $reqs,
                'Complétude de la fiche' => Pros::completeness($pro) . ' %',
            ];
            $tips = Pros::tips($pro);
            $details = Mail::details($rows) . ($tips ? '<p><strong>Notre conseil :</strong> ' . e($tips[0]) . '</p>' : '');
            $m = Mail::build('pro_weekly', ['prenom' => $pro['first_name'] ?: Pros::displayName($pro)], [
                'details' => $details,
                'bouton_url' => '/espace-pro/statistiques/',
                'bouton_label' => 'Voir mes statistiques',
                'footer' => '<p style="font-size:12px;color:#6b5f80">Vous pouvez désactiver ce bilan dans votre espace pro (Mon compte).</p>',
            ]);
            Mail::queue((string) $pro['email'], $m['subject'], $m['html'], ['priority' => 8, 'ref' => 'weekly:' . $id, 'unsubscribe' => Mail::unsubscribeUrl('pro', (int) $id)]);
            $n++;
        }
        return "$n bilans envoyés";
    }
}
