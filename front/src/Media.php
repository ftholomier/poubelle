<?php

declare(strict_types=1);

/**
 * Médiathèque : images importées de renovest.fr (public/media, versionnées) et
 * téléversements du back-office (public/uploads, jamais touchés par les mises à jour).
 */
final class Media
{
    private const TYPES = [
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'video/mp4' => 'mp4',
    ];
    private const MAX_IMAGE = 15 * 1024 * 1024;
    private const MAX_VIDEO = 200 * 1024 * 1024;
    private const MAX_SIDE = 2400;

    private static function root(): string
    {
        return APP_ROOT . '/public';
    }

    /** Fichiers d'un dossier : « uploads », « media » ou « tout ». */
    public static function list(string $folder = 'tout'): array
    {
        $dirs = $folder === 'media' ? ['media'] : ($folder === 'uploads' ? ['uploads'] : ['uploads', 'media']);
        $out = [];
        foreach ($dirs as $d) {
            $base = self::root() . '/' . $d;
            if (!is_dir($base)) {
                continue;
            }
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                $ext = strtolower($f->getExtension());
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'mp4'], true)) {
                    continue;
                }
                $rel = '/' . $d . substr(str_replace('\\', '/', $f->getPathname()), strlen($base));
                $out[] = ['path' => $rel, 'name' => $f->getFilename(), 'size' => $f->getSize(), 'time' => $f->getMTime(), 'folder' => $d, 'video' => $ext === 'mp4'];
            }
        }
        usort($out, fn($a, $b) => $b['time'] <=> $a['time'] ?: strcmp($a['path'], $b['path']));
        return $out;
    }

    /** $_FILES (simple ou multiple) en liste de fichiers. */
    public static function normalizeFiles(array $f): array
    {
        if (!$f || !isset($f['name'])) {
            return [];
        }
        if (!is_array($f['name'])) {
            return [$f];
        }
        $out = [];
        foreach ($f['name'] as $i => $n) {
            $out[] = ['name' => $n, 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
        }
        return $out;
    }

    /** Téléversement vérifié : type réel, taille, nom aléatoire, redimensionnement des grandes photos. */
    public static function upload(array $f): array
    {
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
            throw new RuntimeException('envoi incomplet (taille maximale du serveur : ' . ini_get('upload_max_filesize') . ')');
        }
        $mime = function_exists('finfo_open') ? (string) finfo_file(finfo_open(FILEINFO_MIME_TYPE), $f['tmp_name']) : (string) ($f['type'] ?? '');
        if (!isset(self::TYPES[$mime])) {
            throw new RuntimeException('format refusé (JPG, PNG, WebP, GIF ou MP4)');
        }
        $video = $mime === 'video/mp4';
        if ($f['size'] > ($video ? self::MAX_VIDEO : self::MAX_IMAGE)) {
            throw new RuntimeException('fichier trop volumineux');
        }
        if (!$video && @getimagesize($f['tmp_name']) === false) {
            throw new RuntimeException('image illisible');
        }
        $dir = '/uploads/' . date('Y/m');
        if (!is_dir(self::root() . $dir) && !@mkdir(self::root() . $dir, 0755, true)) {
            throw new RuntimeException('dossier public/uploads non inscriptible');
        }
        $base = slugify(pathinfo((string) $f['name'], PATHINFO_FILENAME));
        $name = substr($base, 0, 50) . '-' . bin2hex(random_bytes(3)) . '.' . self::TYPES[$mime];
        $dest = self::root() . $dir . '/' . $name;
        if (!move_uploaded_file($f['tmp_name'], $dest)) {
            throw new RuntimeException('enregistrement impossible');
        }
        @chmod($dest, 0644);
        if (!$video) {
            self::shrink($dest, $mime);
        }
        return ['path' => $dir . '/' . $name, 'name' => $name, 'size' => (int) filesize($dest), 'folder' => 'uploads', 'video' => $video];
    }

    /** Photos trop grandes ramenées à 2400 px (si GD est disponible). */
    private static function shrink(string $file, string $mime): void
    {
        if (!function_exists('imagecreatetruecolor') || $mime === 'image/gif') {
            return;
        }
        [$w, $h] = @getimagesize($file) ?: [0, 0];
        if ($w <= self::MAX_SIDE && $h <= self::MAX_SIDE) {
            return;
        }
        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($file),
            'image/png' => @imagecreatefrompng($file),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file) : false,
            default => false,
        };
        if (!$src) {
            return;
        }
        $k = self::MAX_SIDE / max($w, $h);
        $nw = (int) round($w * $k);
        $nh = (int) round($h * $k);
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        match ($mime) {
            'image/jpeg' => imagejpeg($dst, $file, 84),
            'image/png' => imagepng($dst, $file, 8),
            'image/webp' => imagewebp($dst, $file, 84),
        };
        imagedestroy($src);
        imagedestroy($dst);
    }

    /** Suppression : uniquement les téléversements (public/uploads). */
    public static function delete(string $path): void
    {
        if (!preg_match('#^/uploads/[\w/.-]+$#', $path) || str_contains($path, '..')) {
            Http::error('Seuls les fichiers téléversés peuvent être supprimés.', 422);
        }
        $f = self::root() . $path;
        if (is_file($f)) {
            @unlink($f);
        }
    }

    /** Télécharge une image distante dans public/uploads (import Facebook). */
    public static function fetchRemote(string $url, string $prefix = 'fb'): string
    {
        if (!preg_match('#^https://#', $url) || !function_exists('curl_init')) {
            return '';
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 4, CURLOPT_TIMEOUT => 25,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_USERAGENT => 'Renovest-Site']);
        $data = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (!is_string($data) || $code !== 200 || strlen($data) > self::MAX_IMAGE) {
            return '';
        }
        $info = @getimagesizefromstring($data);
        $mime = $info['mime'] ?? '';
        if (!isset(self::TYPES[$mime]) || $mime === 'video/mp4') {
            return '';
        }
        $dir = '/uploads/' . $prefix . '/' . date('Y/m');
        if (!is_dir(self::root() . $dir)) {
            @mkdir(self::root() . $dir, 0755, true);
        }
        $name = $prefix . '-' . substr(sha1($url), 0, 12) . '.' . self::TYPES[$mime];
        if (@file_put_contents(self::root() . $dir . '/' . $name, $data) === false) {
            return '';
        }
        self::shrink(self::root() . $dir . '/' . $name, $mime);
        return $dir . '/' . $name;
    }
}
