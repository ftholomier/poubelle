<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Traitement d'images (GD) : contrôle du type réel, protection contre les images géantes,
 * redressement EXIF, redimensionnement et ré-encodage en WebP (supprime toute charge cachée).
 */
final class Image
{
    public const SIZES = ['sm' => 480, 'md' => 960, 'lg' => 1600];
    private const MAX_PIXELS = 40_000_000;
    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

    public static function available(): bool
    {
        return function_exists('imagecreatetruecolor');
    }

    public static function webp(): bool
    {
        return function_exists('imagewebp');
    }

    /** Valide un fichier envoyé ($_FILES). Renvoie un message d'erreur ou null. */
    public static function validateUpload(?array $file, int $maxBytes = 10_000_000): ?string
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return 'Aucun fichier reçu.';
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 'Fichier trop lourd.' : 'Envoi du fichier interrompu.';
        }
        if (!is_uploaded_file($file['tmp_name']) && PHP_SAPI !== 'cli') {
            return 'Fichier invalide.';
        }
        if ($file['size'] > $maxBytes) {
            return 'Fichier trop lourd (' . Fs::humanSize($maxBytes) . ' maximum).';
        }
        return self::validatePath($file['tmp_name']);
    }

    public static function validatePath(string $path): ?string
    {
        $mime = self::mime($path);
        if (!isset(self::TYPES[$mime])) {
            return 'Format non accepté (JPG, PNG, WebP ou GIF uniquement).';
        }
        $info = @getimagesize($path);
        if (!$info || $info[0] < 50 || $info[1] < 50) {
            return 'Image illisible ou trop petite.';
        }
        if ($info[0] * $info[1] > self::MAX_PIXELS) {
            return 'Image trop grande (40 mégapixels maximum).';
        }
        return null;
    }

    public static function mime(string $path): string
    {
        if (function_exists('finfo_open')) {
            $f = finfo_open(FILEINFO_MIME_TYPE);
            $m = (string) finfo_file($f, $path);
            finfo_close($f);
            return $m;
        }
        $info = @getimagesize($path);
        return $info['mime'] ?? '';
    }

    /**
     * Enregistre une image en plusieurs tailles.
     * @return array{id:string,w:int,h:int,ext:string,sizes:array<string,string>}
     */
    public static function store(string $source, string $destDir, ?string $id = null, array $sizes = self::SIZES): array
    {
        if (!self::available()) {
            throw new \RuntimeException('Extension GD indisponible sur le serveur.');
        }
        $err = self::validatePath($source);
        if ($err !== null) {
            throw new \RuntimeException($err);
        }
        $img = self::load($source);
        $w = imagesx($img);
        $h = imagesy($img);
        $id ??= strtolower(Str::random(10));
        $ext = self::webp() ? 'webp' : 'jpg';
        Fs::ensureDir($destDir);
        $out = [];
        foreach ($sizes as $name => $maxW) {
            $tw = min($w, $maxW);
            $th = (int) round($h * ($tw / $w));
            $dst = imagecreatetruecolor($tw, $th);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagecopyresampled($dst, $img, 0, 0, 0, 0, $tw, $th, $w, $h);
            $file = $destDir . '/' . $id . '-' . $name . '.' . $ext;
            $ok = $ext === 'webp' ? imagewebp($dst, $file, 80) : imagejpeg($dst, $file, 82);
            imagedestroy($dst);
            if (!$ok) {
                throw new \RuntimeException("Impossible d'enregistrer l'image.");
            }
            @chmod($file, 0644);
            $out[$name] = basename($file);
        }
        imagedestroy($img);
        return ['id' => $id, 'w' => $w, 'h' => $h, 'ext' => $ext, 'sizes' => $out];
    }

    /** @return \GdImage */
    private static function load(string $path)
    {
        $mime = self::mime($path);
        $img = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            'image/gif' => @imagecreatefromgif($path),
            default => false,
        };
        if (!$img) {
            throw new \RuntimeException('Image corrompue ou format non pris en charge.');
        }
        if (!imageistruecolor($img)) {
            imagepalettetotruecolor($img);
        }
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($path);
            $o = (int) ($exif['Orientation'] ?? 1);
            $rot = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
            if ($rot !== 0) {
                $r = imagerotate($img, $rot, 0);
                if ($r) {
                    imagedestroy($img);
                    $img = $r;
                }
            }
        }
        return $img;
    }

    /** Télécharge une image distante dans un fichier temporaire (taille limitée). */
    public static function download(string $url, int $maxBytes = 15_000_000): ?string
    {
        $res = Http::get($url, ['Accept' => 'image/*'], 25);
        if ($res['status'] !== 200 || $res['body'] === '' || strlen($res['body']) > $maxBytes) {
            return null;
        }
        $tmp = STORAGE_PATH . '/tmp/dl-' . bin2hex(random_bytes(6));
        Fs::ensureDir(dirname($tmp));
        file_put_contents($tmp, $res['body']);
        if (self::validatePath($tmp) !== null) {
            @unlink($tmp);
            return null;
        }
        return $tmp;
    }

    public static function deleteSet(string $dir, string $id): void
    {
        foreach (glob($dir . '/' . preg_replace('/[^a-z0-9]/i', '', $id) . '-*') ?: [] as $f) {
            @unlink($f);
        }
    }
}
