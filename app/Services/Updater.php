<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Storage\Audit;
use App\Storage\Json;
use App\Storage\Lock;

/**
 * Mise à jour du code depuis GitHub, en un clic au back-office.
 *
 * Seul le code est remplacé : app/, bin/, templates/, public/ (hors fichiers
 * déposés). Les données (data/), les réglages (config/config.php s'il existe
 * déjà) et les secrets ne sont jamais touchés. Avant chaque mise à jour, le
 * code en place est archivé : un clic le restaure.
 */
final class Updater
{
    /** Dossiers de code remplacés. */
    private const DIRS = ['app', 'bin', 'templates', 'public'];
    /** Pages éditoriales du site (FAQ, mentions…) : livrées avec le code. */
    private const PAGES = 'data/content/fr';
    /** Jamais écrasés, même s'ils figurent dans l'archive. */
    private const KEEP = ['config/config.php', 'config/secrets.php', 'public/.htaccess.local'];

    private static function repo(): string
    {
        return (string) Config::get('update.repo', 'ftholomier/poubelle');
    }

    public static function branch(): string
    {
        return (string) Config::get('update.branch', 'claude/loving-ramanujan-hg8m3v');
    }

    private static function stateFile(): string
    {
        return Config::path('data') . '/private/version.json';
    }

    private static function backupDir(): string
    {
        $dir = Config::path('data') . '/backups/code';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        return $dir;
    }

    /** @return array{sha:string, at:string, message:string} version installée */
    public static function installed(): array
    {
        $s = Json::read(self::stateFile());
        return ['sha' => (string) ($s['sha'] ?? ''), 'at' => (string) ($s['at'] ?? ''), 'message' => (string) ($s['message'] ?? '')];
    }

    /** @return array{sha:string, date:string, message:string, error:string} dernière version sur GitHub */
    public static function latest(): array
    {
        $r = Http::request('GET', 'https://api.github.com/repos/' . self::repo() . '/commits/' . rawurlencode(self::branch()),
            ['headers' => ['Accept: application/vnd.github+json'], 'timeout' => 15]);
        $d = json_decode($r['body'], true);
        if ($r['status'] !== 200 || !is_array($d) || !isset($d['sha'])) {
            return ['sha' => '', 'date' => '', 'message' => '', 'error' => 'GitHub n’a pas répondu (' . ($r['error'] ?: 'code ' . $r['status']) . ').'];
        }
        return ['sha' => (string) $d['sha'], 'date' => (string) ($d['commit']['committer']['date'] ?? ''),
                'message' => strtok((string) ($d['commit']['message'] ?? ''), "\n") ?: '', 'error' => ''];
    }

    /** @return array<int, string> archives de code, la plus récente d'abord */
    public static function backups(): array
    {
        $files = glob(self::backupDir() . '/code-*.zip') ?: [];
        rsort($files);
        return array_map('basename', $files);
    }

    /**
     * Télécharge la branche, archive le code en place, installe.
     *
     * @return array{ok:bool, message:string}
     */
    public static function update(int $userId): array
    {
        return Lock::transaction('updater', static function () use ($userId): array {
            if (!class_exists('ZipArchive')) {
                return ['ok' => false, 'message' => 'Extension ZIP absente sur le serveur.'];
            }
            $latest = self::latest();
            if ($latest['error'] !== '') {
                return ['ok' => false, 'message' => $latest['error']];
            }
            $r = Http::request('GET', 'https://codeload.github.com/' . self::repo() . '/zip/' . rawurlencode($latest['sha']), ['timeout' => 120]);
            if ($r['status'] !== 200 || strlen($r['body']) < 1000) {
                return ['ok' => false, 'message' => 'Téléchargement impossible (' . ($r['error'] ?: 'code ' . $r['status']) . ').'];
            }
            $tmp = Config::path('data') . '/private/update-' . bin2hex(random_bytes(4));
            @mkdir($tmp, 0700, true);
            $zipFile = $tmp . '/src.zip';
            file_put_contents($zipFile, $r['body']);
            $zip = new \ZipArchive();
            if ($zip->open($zipFile) !== true || !$zip->extractTo($tmp . '/src')) {
                self::rmtree($tmp);
                return ['ok' => false, 'message' => 'Archive GitHub illisible.'];
            }
            $zip->close();
            $root = (glob($tmp . '/src/*', GLOB_ONLYDIR) ?: [''])[0];
            // L'archive doit ressembler au site, sinon on n'écrase rien.
            if ($root === '' || !is_file($root . '/app/bootstrap.php') || !is_file($root . '/public/index.php')) {
                self::rmtree($tmp);
                return ['ok' => false, 'message' => 'L’archive ne contient pas le site attendu : rien n’a été modifié.'];
            }

            $backup = self::archiveCurrent();
            if ($backup === '') {
                self::rmtree($tmp);
                return ['ok' => false, 'message' => 'Sauvegarde du code en place impossible : mise à jour annulée.'];
            }
            $count = self::install($root);
            [$pages, $kept, $hashes] = self::installPages($root);
            $count += $pages;
            self::rmtree($tmp);
            Json::write(self::stateFile(), ['sha' => $latest['sha'], 'at' => date('c'), 'message' => $latest['message'],
                                            'pages' => $hashes]);
            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }
            Audit::log('update.installed', ['sha' => substr($latest['sha'], 0, 12), 'files' => $count, 'backup' => $backup], $userId);
            return ['ok' => true, 'message' => sprintf('Mise à jour installée : %d fichiers, version %s. Sauvegarde : %s.',
                $count, substr($latest['sha'], 0, 7), $backup)
                . ($kept !== [] ? ' Pages retouchées au back-office, laissées telles quelles : ' . implode(', ', $kept) . '.' : '')];
        });
    }

    /** Restaure une archive de code. */
    public static function rollback(string $name, int $userId): array
    {
        $file = self::backupDir() . '/' . basename($name);
        if (preg_match('/^code-[\w-]+\.zip$/', basename($name)) !== 1 || !is_file($file)) {
            return ['ok' => false, 'message' => 'Sauvegarde introuvable.'];
        }
        $zip = new \ZipArchive();
        if ($zip->open($file) !== true) {
            return ['ok' => false, 'message' => 'Sauvegarde illisible.'];
        }
        $zip->extractTo(Config::path('root'));
        $zip->close();
        Json::write(self::stateFile(), ['sha' => '', 'at' => date('c'), 'message' => 'Restauration de ' . basename($name)]);
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        Audit::log('update.rolled_back', ['backup' => basename($name)], $userId);
        return ['ok' => true, 'message' => 'Code restauré depuis ' . basename($name) . '.'];
    }

    private static function archiveCurrent(): string
    {
        $name = 'code-' . date('Ymd-His') . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open(self::backupDir() . '/' . $name, \ZipArchive::CREATE) !== true) {
            return '';
        }
        $root = Config::path('root');
        foreach (array_merge(self::DIRS, [self::PAGES]) as $dir) {
            foreach (self::files($root . '/' . $dir) as $abs) {
                $zip->addFile($abs, substr($abs, strlen($root) + 1));
            }
        }
        $zip->close();
        // Les cinq dernières suffisent.
        foreach (array_slice(self::backups(), 5) as $old) {
            @unlink(self::backupDir() . '/' . $old);
        }
        return $name;
    }

    private static string $src = '';

    private static function install(string $src): int
    {
        self::$src = $src;
        $root = Config::path('root');
        $count = 0;
        foreach (array_merge(self::DIRS, ['config']) as $dir) {
            foreach (self::files($src . '/' . $dir) as $abs) {
                $rel = substr($abs, strlen($src) + 1);
                // Fichiers déposés par les visiteurs et réglages locaux : jamais écrasés.
                if (in_array($rel, self::KEEP, true) && is_file($root . '/' . $rel)) {
                    continue;
                }
                if (str_starts_with($rel, 'public/uploads/')) {
                    continue;
                }
                $dest = $root . '/' . $rel;
                if (!is_dir(dirname($dest))) {
                    @mkdir(dirname($dest), 0755, true);
                }
                if (!is_file($dest) || md5_file($dest) !== md5_file($abs)) {
                    // Écriture atomique : un visiteur ne lit jamais un fichier à moitié copié.
                    if (@copy($abs, $dest . '.tmp-upd') && @rename($dest . '.tmp-upd', $dest)) {
                        $count++;
                    }
                }
            }
        }
        return $count;
    }

    /**
     * Pages en français. Une page modifiée sur le serveur depuis la dernière
     * livraison (retouchée dans « Pages & contenus ») n'est pas écrasée.
     *
     * @return array{0:int, 1:array<int,string>, 2:array<string,string>} remplacées, gardées, empreintes livrées
     */
    private static function installPages(string $root): array
    {
        $src = self::$src . '/' . self::PAGES;
        $known = (array) (Json::read(self::stateFile())['pages'] ?? []);
        $count = 0;
        $kept = [];
        $hashes = [];
        foreach (glob($src . '/*.json') ?: [] as $abs) {
            $name = basename($abs);
            $dest = $root . '/' . self::PAGES . '/' . $name;
            $incoming = (string) md5_file($abs);
            $hashes[$name] = $incoming;
            if (is_file($dest)) {
                $current = (string) md5_file($dest);
                if ($current === $incoming) {
                    continue;
                }
                // Déjà livrée et changée depuis sur le serveur : c'est une retouche, on la garde.
                if (isset($known[$name]) && $known[$name] !== $current) {
                    $kept[] = basename($name, '.json');
                    $hashes[$name] = $current === '' ? $incoming : $known[$name];
                    continue;
                }
            }
            if (!is_dir(dirname($dest))) {
                @mkdir(dirname($dest), 0755, true);
            }
            if (@copy($abs, $dest . '.tmp-upd') && @rename($dest . '.tmp-upd', $dest)) {
                $count++;
            }
        }
        return [$count, $kept, $hashes];
    }

    /** @return array<int, string> */
    private static function files(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }
        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile()) {
                $out[] = $f->getPathname();
            }
        }
        return $out;
    }

    private static function rmtree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }
}
