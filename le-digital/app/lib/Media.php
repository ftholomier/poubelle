<?php
declare(strict_types=1);

namespace LD;

/**
 * Médiathèque : envoi d'images, optimisation automatique (WebP + déclinaisons responsive via GD).
 */
final class Media
{
    private const INDEX = 'data/media.json';
    private const WIDTHS = [480, 960, 1600];
    private const MAX_BYTES = 12 * 1024 * 1024;

    private static array $dimCache = [];

    public static function all(): array
    {
        $items = Store::read(self::INDEX, []);
        usort($items, static fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
        return $items;
    }

    public static function find(string $path): ?array
    {
        foreach (Store::read(self::INDEX, []) as $m) {
            if ($m['path'] === $path) {
                return $m;
            }
        }
        return null;
    }

    private static function localFile(string $src): ?string
    {
        if (!str_starts_with($src, '/uploads/') && !str_starts_with($src, '/assets/')) {
            return null;
        }
        $file = LD_PUBLIC . parse_url($src, PHP_URL_PATH);
        return is_file($file) ? $file : null;
    }

    public static function dimensions(string $src): ?array
    {
        if (array_key_exists($src, self::$dimCache)) {
            return self::$dimCache[$src];
        }
        $m = self::find($src);
        if ($m && !empty($m['w'])) {
            return self::$dimCache[$src] = [(int) $m['w'], (int) $m['h']];
        }
        $file = self::localFile($src);
        $size = $file ? @getimagesize($file) : false;
        return self::$dimCache[$src] = $size ? [$size[0], $size[1]] : null;
    }

    public static function best(string $src): string
    {
        return $src;
    }

    /** Déclinaisons « srcset » : médiathèque, ou fichiers livrés nommés « nom-LARGEUR.webp ». */
    public static function srcset(string $src): string
    {
        $m = self::find($src);
        if ($m && !empty($m['variants'])) {
            $parts = [];
            foreach ($m['variants'] as $w => $p) {
                $parts[] = $p . ' ' . $w . 'w';
            }
            $parts[] = $m['path'] . ' ' . $m['w'] . 'w';
            return implode(', ', array_unique($parts));
        }
        if (preg_match('~^(/assets/img/.+)-(\d+)\.webp$~', $src, $mm)) {
            $parts = [];
            foreach (glob(LD_PUBLIC . $mm[1] . '-*.webp') ?: [] as $f) {
                if (preg_match('~-(\d+)\.webp$~', $f, $w)) {
                    $parts[(int) $w[1]] = $mm[1] . '-' . $w[1] . '.webp ' . $w[1] . 'w';
                }
            }
            ksort($parts);
            return implode(', ', $parts);
        }
        return '';
    }

    /**
     * Enregistre une image envoyée ($_FILES entry). Retourne l'entrée de la médiathèque.
     */
    public static function upload(array $file, string $alt = ''): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            throw new \RuntimeException('Envoi du fichier impossible.');
        }
        if ($file['size'] > self::MAX_BYTES) {
            throw new \RuntimeException('Image trop lourde (12 Mo maximum).');
        }
        return self::store($file['tmp_name'], (string) $file['name'], $alt, true);
    }

    /** Enregistre une image fournie en binaire (ex. image générée par l'IA). */
    public static function fromBinary(string $data, string $name, string $alt = ''): array
    {
        if (strlen($data) > self::MAX_BYTES) {
            throw new \RuntimeException('Image trop lourde (12 Mo maximum).');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'ldimg');
        file_put_contents($tmp, $data);
        try {
            return self::store($tmp, $name, $alt, false);
        } finally {
            @unlink($tmp);
        }
    }

    private static function store(string $tmpPath, string $name, string $alt, bool $uploaded): array
    {
        $file = ['tmp_name' => $tmpPath, 'name' => $name];
        $info = @getimagesize($file['tmp_name']);
        $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
        if (!$info || !isset($types[$info[2]])) {
            throw new \RuntimeException('Format non pris en charge (JPG, PNG, WebP ou GIF).');
        }
        $dir = '/uploads/' . date('Y/m');
        if (!is_dir(LD_PUBLIC . $dir)) {
            mkdir(LD_PUBLIC . $dir, 0775, true);
        }
        $base = slugify(pathinfo((string) $file['name'], PATHINFO_FILENAME));
        $base = substr($base, 0, 50) . '-' . substr(bin2hex(random_bytes(3)), 0, 5);
        $entry = ['id' => bin2hex(random_bytes(6)), 'name' => (string) $file['name'], 'alt' => $alt, 'created_at' => date('c'), 'variants' => []];

        $canGd = function_exists('imagewebp') && $info[2] !== IMAGETYPE_GIF;
        if ($canGd) {
            $img = match ($info[2]) {
                IMAGETYPE_JPEG => @imagecreatefromjpeg($file['tmp_name']),
                IMAGETYPE_PNG  => @imagecreatefrompng($file['tmp_name']),
                IMAGETYPE_WEBP => @imagecreatefromwebp($file['tmp_name']),
                default        => false,
            };
            if ($img === false) {
                throw new \RuntimeException('Image illisible.');
            }
            if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
                $img = self::autoRotate($img, $file['tmp_name']);
            }
            imagepalettetotruecolor($img);
            imagealphablending($img, false);
            imagesavealpha($img, true);
            $w = imagesx($img);
            $h = imagesy($img);
            $main = self::resized($img, min($w, 2000));
            $path = $dir . '/' . $base . '.webp';
            imagewebp($main, LD_PUBLIC . $path, 82);
            $entry += ['path' => $path, 'w' => imagesx($main), 'h' => imagesy($main)];
            foreach (self::WIDTHS as $vw) {
                if ($vw < $entry['w'] - 100) {
                    $v = self::resized($img, $vw);
                    $vp = $dir . '/' . $base . '-' . $vw . '.webp';
                    imagewebp($v, LD_PUBLIC . $vp, 80);
                    $entry['variants'][$vw] = $vp;
                }
            }
        } else {
            $path = $dir . '/' . $base . '.' . $types[$info[2]];
            $uploaded ? move_uploaded_file($file['tmp_name'], LD_PUBLIC . $path) : copy($file['tmp_name'], LD_PUBLIC . $path);
            $entry += ['path' => $path, 'w' => $info[0], 'h' => $info[1]];
        }
        $entry['size'] = (int) @filesize(LD_PUBLIC . $entry['path']);
        Store::update(self::INDEX, static function (array &$items) use ($entry): void {
            $items[] = $entry;
        });
        return $entry;
    }

    private static function resized(\GdImage $src, int $w): \GdImage
    {
        $sw = imagesx($src);
        $sh = imagesy($src);
        if ($w >= $sw) {
            return $src;
        }
        $h = (int) round($sh * $w / $sw);
        $dst = imagecreatetruecolor($w, $h);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, $sw, $sh);
        return $dst;
    }

    private static function autoRotate(\GdImage $img, string $file): \GdImage
    {
        $exif = @exif_read_data($file);
        $o = (int) ($exif['Orientation'] ?? 1);
        $r = match ($o) { 3 => imagerotate($img, 180, 0), 6 => imagerotate($img, -90, 0), 8 => imagerotate($img, 90, 0), default => $img };
        return $r ?: $img;
    }

    public static function updateAlt(string $id, string $alt): void
    {
        Store::update(self::INDEX, static function (array &$items) use ($id, $alt): void {
            foreach ($items as &$m) {
                if ($m['id'] === $id) {
                    $m['alt'] = $alt;
                }
            }
        });
        PageCache::clear();
    }

    public static function delete(string $id): void
    {
        Store::update(self::INDEX, static function (array &$items) use ($id): void {
            foreach ($items as $i => $m) {
                if ($m['id'] !== $id) {
                    continue;
                }
                foreach (array_merge([$m['path']], array_values($m['variants'] ?? [])) as $p) {
                    $f = LD_PUBLIC . $p;
                    if (str_starts_with($p, '/uploads/') && is_file($f)) {
                        @unlink($f);
                    }
                }
                unset($items[$i]);
            }
            $items = array_values($items);
        });
        PageCache::clear();
    }
}
