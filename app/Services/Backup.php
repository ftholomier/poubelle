<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Settings;

/**
 * Sauvegardes ZIP (storage/backups, hors du dossier public) : toutes les données
 * éditoriales (data/) et tout storage/ (réglages chiffrés et leur clé, versions, boîtes de
 * réception, dons, boutique, carnets, albums, tickets, consentements, Rétro-Direct, défis,
 * validations, Trouvailles, notifications…), sauf ce qui se refait tout seul (caches, sessions,
 * limites, journaux, mises à jour, fichiers temporaires, verrous). Les photos, les voix IA des
 * fiches et les commentaires radio (volumineux) sont ajoutés le dimanche si l'option est cochée.
 * Les plus anciennes sont supprimées.
 */
final class Backup
{
    public const DIR = STORAGE_PATH . '/backups';

    /** Dossiers sauvegardés en entier (relatifs au dossier du club). */
    private const PATHS = ['data', 'storage'];

    /**
     * Exclus de storage/ (relatifs à storage/) : régénérables, temporaires ou trop volumineux.
     * Les photos (storage/media) suivent l'option hebdomadaire ; les copies brutes du site
     * WordPress (storage/import/html, rest, rest-full) et les maquettes ne servent plus au site.
     */
    private const EXCLUDE = [
        'cache', 'sessions', 'ratelimit', 'logs', 'backups', 'update', 'tmp', 'media',
        'import/html', 'import/rest', 'import/rest-full', 'maquette', 'maquette2',
        'audio/jobs', // fichiers d'échange temporaires avec Google
    ];

    /** Fichier ou dossier exclu de la sauvegarde (chemin relatif au dossier du club). */
    public static function excluded(string $local): bool
    {
        $name = basename($local);
        if (preg_match('/\.(lock|tmp|pid)$/', $name) || str_contains('/' . $local . '/', '/cache/')) {
            return true; // verrous, fichiers en cours d'écriture, caches propres à un module (compos/cache…)
        }
        if (str_starts_with($local, 'storage/')) {
            foreach (self::EXCLUDE as $x) {
                if ($local === 'storage/' . $x || str_starts_with($local, 'storage/' . $x . '/')) {
                    return true;
                }
            }
        }
        return false;
    }

    /** @return array{file:string,size:int,files:int,ms:int}|array{error:string} */
    public static function run(bool $force = false, ?bool $withMedia = null): array
    {
        if (!class_exists(\ZipArchive::class)) {
            return ['error' => 'Extension PHP zip indisponible.'];
        }
        if (!is_dir(self::DIR)) {
            mkdir(self::DIR, 0750, true);
        }
        $withMedia ??= Settings::get('backups.media_weekly', false) && (int) date('N') === 7;
        $t0 = microtime(true);
        $name = 'sauvegarde-' . date('Y-m-d-His') . ($withMedia ? '-photos' : '') . '.zip';
        $file = self::DIR . '/' . $name;
        $zip = new \ZipArchive();
        if ($zip->open($file, \ZipArchive::CREATE | \ZipArchive::EXCL) !== true) {
            return ['error' => 'Impossible de créer l’archive.'];
        }
        $root = dirname(DATA_PATH); // dossier du club : data/ et storage/ (racine pour le club historique)
        $count = 0;
        // Chemin dans l'archive => chemin sur le disque.
        $paths = [];
        foreach (self::PATHS as $rel) {
            $paths[$rel] = $root . '/' . $rel;
        }
        if ($withMedia) {
            $paths['storage/media'] = $root . '/storage/media';
            // Voix IA des fiches et commentaires radio (payés : on les garde), pour chaque musée
            // (public/media/… pour le club historique, public/m/<id>/… pour les autres).
            $pub = substr(MEDIA_PATH, strlen(APP_ROOT) + 1);
            $paths[$pub . '/audio'] = MEDIA_PATH . '/audio';
            $paths[$pub . '/radio'] = MEDIA_PATH . '/radio';
        }
        foreach ($paths as $rel => $abs) {
            if (is_file($abs)) {
                $zip->addFile($abs, $rel);
                $count++;
            } elseif (is_dir($abs)) {
                $media = $rel !== 'data' && $rel !== 'storage';
                // Dossiers exclus écartés dès le parcours (le cache seul pèse des Go).
                $dirs = new \RecursiveCallbackFilterIterator(
                    new \RecursiveDirectoryIterator($abs, \FilesystemIterator::SKIP_DOTS),
                    fn (\SplFileInfo $f) => $media || !self::excluded($rel . substr($f->getPathname(), strlen($abs)))
                );
                foreach (new \RecursiveIteratorIterator($dirs) as $f) {
                    if (!$f->isFile() || preg_match('/\.(lock|tmp)$/', $f->getFilename())) {
                        continue;
                    }
                    $local = $rel . substr($f->getPathname(), strlen($abs));
                    $zip->addFile($f->getPathname(), $local);
                    // Photos et sons sont déjà compressés : stockage sans recompression.
                    if ($media) {
                        $zip->setCompressionName($local, \ZipArchive::CM_STORE);
                    }
                    $count++;
                }
            }
        }
        $zip->setArchiveComment(club('{site} · ') . date('c') . ' · ' . $count . ' fichiers');
        if (!$zip->close()) {
            @unlink($file);
            return ['error' => 'Écriture de l’archive impossible (espace disque ?).'];
        }
        @chmod($file, 0640);
        self::prune();
        return ['file' => $name, 'size' => (int) filesize($file), 'files' => $count, 'ms' => (int) ((microtime(true) - $t0) * 1000)];
    }

    /** Sauvegardes existantes, plus récentes d'abord. */
    public static function list(): array
    {
        $out = [];
        foreach (glob(self::DIR . '/sauvegarde-*.zip') ?: [] as $f) {
            $out[] = ['file' => basename($f), 'size' => (int) @filesize($f), 'at' => date('c', (int) @filemtime($f)), 'photos' => str_contains($f, '-photos')];
        }
        usort($out, fn ($a, $b) => strcmp($b['at'], $a['at']));
        return $out;
    }

    public static function path(string $name): ?string
    {
        return preg_match('/^sauvegarde-[\d-]+(-photos)?\.zip$/', $name) && is_file(self::DIR . '/' . $name) ? self::DIR . '/' . $name : null;
    }

    private static function prune(): void
    {
        $keep = max(2, (int) Settings::get('backups.keep', 14));
        $all = self::list();
        $light = array_values(array_filter($all, fn ($b) => !$b['photos']));
        $heavy = array_values(array_filter($all, fn ($b) => $b['photos']));
        foreach (array_slice($light, $keep) as $b) {
            @unlink(self::DIR . '/' . $b['file']);
        }
        foreach (array_slice($heavy, 2) as $b) {
            @unlink(self::DIR . '/' . $b['file']);
        }
    }
}
