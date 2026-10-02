<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Cache;
use App\Core\Env;
use App\Core\Fs;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Str;
use App\Core\Url;
use App\Services\Backup;
use App\Services\Cron;
use App\Services\Geo;
use App\Services\Import\Importer;
use App\Services\Pros;
use App\Services\Seo;
use App\Services\Store;

/** Maintenance : état du serveur, caches, index, sauvegardes, import de l'ancienne base, journaux, archives. */
final class SystemController extends AdminController
{
    private const COLLECTIONS = ['pros', 'requests', 'messages', 'reviews', 'articles', 'pages', 'admins', 'campaigns', 'mail_queue', 'notifications', 'push_subs', 'chats', 'contacts', 'tokens'];

    public function index(): Response
    {
        $sizes = [];
        foreach (['data' => STORAGE_PATH . '/data', 'cache' => STORAGE_PATH . '/cache', 'logs' => STORAGE_PATH . '/logs', 'backups' => STORAGE_PATH . '/backups', 'media' => PUBLIC_PATH . '/media', 'import' => STORAGE_PATH . '/import'] as $k => $dir) {
            $sizes[$k] = is_dir($dir) ? Fs::dirSize($dir) : 0;
        }
        $counts = [];
        foreach (self::COLLECTIONS as $c) {
            $counts[$c] = Store::col($c)->count();
        }
        $exts = [];
        foreach (['gd' => 'Images (GD)', 'zip' => 'Sauvegardes (zip)', 'sodium' => 'Chiffrement (sodium)', 'openssl' => 'OpenSSL (push, SMTP)', 'curl' => 'cURL (API, IA)', 'mbstring' => 'mbstring', 'intl' => 'intl', 'fileinfo' => 'fileinfo', 'exif' => 'EXIF (rotation photos)', 'dom' => 'DOM (nettoyage HTML)', 'Zend OPcache' => 'OPcache (performances)'] as $ext => $label) {
            $exts[$label] = extension_loaded($ext);
        }
        $exts['WebP'] = function_exists('imagewebp');
        return $this->page('system', [
            'php' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'exts' => $exts,
            'sizes' => $sizes,
            'free' => @disk_free_space(STORAGE_PATH) ?: 0,
            'counts' => $counts,
            'cron' => Cron::status(),
            'backups' => Backup::available() ? Backup::all() : [],
            'zip' => Backup::available(),
            'import' => Importer::status(),
            'dump' => Importer::findDump(),
            'importDone' => Fs::readJson(STORAGE_PATH . '/data/import_done.json', null),
            'limits' => ['upload_max_filesize' => ini_get('upload_max_filesize'), 'post_max_size' => ini_get('post_max_size'), 'memory_limit' => ini_get('memory_limit'), 'max_execution_time' => ini_get('max_execution_time')],
            'photosTodo' => Store::pros()->count(static fn ($p) => $p['status'] === 'active' && $p['photos'] === 0),
            'version' => APP_VERSION,
            'canEnv' => \App\Core\Auth::adminCan('env'),
        ], 'Maintenance', 'system');
    }

    public function action(): Response
    {
        @set_time_limit(600);
        $action = (string) Request::input('action', '');
        switch ($action) {
            case 'cache':
                $n = Cache::flush() + Cache::flush('pages');
                Cache::bump();
                $this->audit('Caches vidés');
                return $this->done("Caches vidés ($n fichiers).", 'maintenance');
            case 'reindex':
                $out = [];
                foreach (self::COLLECTIONS as $c) {
                    $out[] = $c . ' : ' . Store::col($c)->rebuild();
                }
                Pros::changed();
                $this->audit('Index reconstruits');
                return $this->done('Index reconstruits — ' . implode(', ', $out), 'maintenance');
            case 'sitemaps':
                Seo::buildSitemaps(true);
                return $this->done('Sitemaps régénérés.', 'maintenance');
            case 'cron':
                $r = Cron::run('admin', array_keys(Cron::TASKS));
                return $this->done('Tâches exécutées : ' . implode(', ', array_keys($r)), 'maintenance');
            case 'backup':
                if (!Backup::available()) {
                    return $this->done('Extension zip absente sur le serveur.', 'maintenance', 'error');
                }
                $withEnv = Request::bool('env') && \App\Core\Auth::adminCan('env');
                $name = Backup::create(Request::bool('media'), $withEnv, 'manuelle');
                return $this->done('Sauvegarde créée : ' . $name, 'maintenance#sauvegardes');
            case 'backup-delete':
                Backup::delete((string) Request::input('name', ''));
                $this->audit('Sauvegarde supprimée', ['fichier' => (string) Request::input('name', '')]);
                return $this->done('Sauvegarde supprimée.', 'maintenance#sauvegardes');
            case 'restore':
                if (!\App\Core\Auth::adminCan('env')) {
                    throw new HttpException(403, 'Seul un super-administrateur peut restaurer une sauvegarde.');
                }
                if (mb_strtoupper(trim((string) Request::input('confirm', ''))) !== 'RESTAURER') {
                    return $this->done('Tapez RESTAURER pour confirmer.', 'maintenance#sauvegardes', 'error');
                }
                try {
                    $r = Backup::restore((string) Request::input('name', ''));
                } catch (\Throwable $e) {
                    return $this->done('Restauration impossible : ' . $e->getMessage(), 'maintenance#sauvegardes', 'error');
                }
                return $this->done('Données restaurées (' . $r['files'] . ' fichiers). Une sauvegarde de l\'état précédent a été créée : ' . $r['safety'], 'maintenance#sauvegardes', 'warning');
            case 'geo':
                $dep = Geo::depCode((string) Request::input('dep', ''));
                if (!Geo::dep($dep)) {
                    return $this->done('Département inconnu.', 'maintenance', 'error');
                }
                try {
                    $n = Geo::refreshDepFromApi($dep);
                } catch (\Throwable $e) {
                    return $this->done('Mise à jour impossible : ' . $e->getMessage(), 'maintenance', 'error');
                }
                return $this->done("Communes du département $dep mises à jour ($n).", 'maintenance');
            case 'logs-prune':
                $n = Logger::prune(30);
                return $this->done("$n journal(aux) de plus de 30 jours supprimé(s).", 'journal');
        }
        return $this->done('Action inconnue.', 'maintenance', 'error');
    }

    /** Import de l'ancienne base : envoi du dump puis étapes pas à pas (AJAX). */
    public function import(): Response
    {
        @set_time_limit(900);
        if (!\App\Core\Auth::adminCan('env')) {
            throw new HttpException(403, 'Seul un super-administrateur peut lancer la migration.');
        }
        $f = Request::file('dump');
        if ($f && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if ($f['error'] !== UPLOAD_ERR_OK || !preg_match('/\.sql$/i', (string) $f['name'])) {
                return $this->done('Envoyez un fichier .sql (dump de l\'ancienne base). Si le fichier est trop gros, déposez-le par FTP dans storage/import/.', 'maintenance#import', 'error');
            }
            Fs::ensureDir(Importer::DIR, 0700);
            $dest = Importer::DIR . '/' . preg_replace('/[^A-Za-z0-9_\-.]/', '_', (string) $f['name']);
            if (!move_uploaded_file($f['tmp_name'], $dest)) {
                return $this->done('Enregistrement du fichier impossible.', 'maintenance#import', 'error');
            }
            @chmod($dest, 0600);
            touch($dest);
            $this->audit('Dump SQL déposé pour la migration', ['fichier' => basename($dest), 'taille' => filesize($dest)]);
            return $this->done('Fichier reçu (' . Fs::humanSize((int) filesize($dest)) . '). Lancez maintenant la migration.', 'maintenance#import');
        }
        $step = (string) Request::input('step', '');
        $offset = max(0, Request::int('offset', 0));
        try {
            if ($step === 'photos') {
                $r = Importer::photos($offset, 12, !Request::bool('all'));
            } elseif (isset(Importer::STEPS[$step])) {
                $r = Importer::run($step, $offset);
            } else {
                return Response::json(['error' => 'Étape inconnue'], 422);
            }
        } catch (\Throwable $e) {
            Logger::log('import', 'Erreur de migration', ['step' => $step, 'error' => $e->getMessage()], 'error');
            return Response::json(['error' => $e->getMessage()], 500);
        }
        if ($step === 'finalize') {
            $this->audit('Migration de l\'ancienne base terminée');
        }
        return Response::json($r);
    }

    public function download(string $name): Response
    {
        $path = Backup::path($name);
        if ($path === null) {
            throw new HttpException(404);
        }
        $this->audit('Sauvegarde téléchargée', ['fichier' => $name]);
        return Response::download($path, $name, 'application/zip');
    }

    // ---------------------------------------------------------------- journaux

    public function logs(): Response
    {
        $channel = in_array(self::q('canal', 20), Logger::CHANNELS, true) ? self::q('canal', 20) : 'error';
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', self::q('date', 10)) ? self::q('date', 10) : null;
        $search = self::q('q', 100);
        $rows = Logger::tail($channel, 400, $date, $search !== '' ? $search : null);
        $files = [];
        foreach (glob(STORAGE_PATH . '/logs/' . $channel . '-*.log') ?: [] as $f) {
            $files[] = substr(basename($f, '.log'), strlen($channel) + 1);
        }
        rsort($files);
        return $this->page('logs', ['channel' => $channel, 'rows' => $rows, 'date' => $date, 'q' => $search, 'dates' => array_slice($files, 0, 60)], 'Journal', 'logs');
    }

    // ---------------------------------------------------------------- archives

    public function archives(): Response
    {
        $list = [];
        foreach (glob(STORAGE_PATH . '/data/archives/*.json') ?: [] as $f) {
            $list[basename($f, '.json')] = (int) filesize($f);
        }
        ksort($list);
        return $this->page('archives', ['list' => $list], 'Archives de l\'ancien site', 'archives');
    }

    public function archive(string $table): Response
    {
        $file = STORAGE_PATH . '/data/archives/' . $table . '.json';
        if (!preg_match('/^[a-z_]+$/', $table) || !is_file($file)) {
            throw new HttpException(404);
        }
        $rows = Fs::readJson($file, []);
        $q = Str::norm(self::q('q', 100));
        if ($q !== '') {
            $rows = array_values(array_filter($rows, static fn ($r) => str_contains(Str::norm(implode(' ', array_map(static fn ($v) => is_scalar($v) ? (string) $v : '', $r))), $q)));
        }
        if (Request::query('format') === 'csv') {
            $out = [array_keys($rows[0] ?? [])];
            foreach ($rows as $r) {
                $out[] = array_map(static fn ($v) => is_scalar($v) ? (string) $v : json_encode($v), $r);
            }
            $this->audit('Export d\'une archive', ['table' => $table]);
            return Response::csv($out, 'archive-' . $table . '.csv');
        }
        return $this->page('archive', self::paginate($rows, 100) + ['table' => $table, 'q' => self::q('q', 100), 'link' => self::pageLink(), 'columns' => array_keys($rows[0] ?? [])], 'Archive : ' . $table, 'archives');
    }
}
