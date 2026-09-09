<?php
declare(strict_types=1);

namespace App;

/**
 * Photothèque : upload contrôlé, dérivés WebP 1600/800/400, dimensions connues
 * (width/height dans le JSON) pour éviter tout décalage de mise en page.
 */
final class Media
{
    public const FILE = 'media.json';
    public const DIR = 'media';
    public const MAX_BYTES = 12_582_912; // 12 Mo
    public const SIZES = [1600, 800, 400];
    private const MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public static function all(): array
    {
        $data = Store::read(self::FILE);
        $items = \is_array($data['media'] ?? null) ? $data['media'] : [];
        usort($items, static fn (array $a, array $b): int => strcmp((string) ($b['at'] ?? ''), (string) ($a['at'] ?? '')));
        return $items;
    }

    /** Toutes les photos rattachées à un lieu, dans l'ordre de la photothèque. */
    public static function bySite(string $site): array
    {
        return array_values(array_filter(
            self::all(),
            static fn (array $m): bool => (string) ($m['site'] ?? '') === $site
        ));
    }

    public static function find(string $path): ?array
    {
        foreach (self::all() as $item) {
            if ((string) ($item['path'] ?? '') === $path) {
                return $item;
            }
        }
        return null;
    }

    /** Texte alternatif d'une photo (avec repli sur le nom du fichier). */
    public static function alt(string $path, string $lang = Config::DEFAULT_LANG, string $default = ''): string
    {
        $item = self::find($path);
        if ($item === null) {
            return $default;
        }
        return Content::i18n($item, 'alt', $lang, $default !== '' ? $default : (string) ($item['name'] ?? ''));
    }

    public static function caption(string $path, string $lang = Config::DEFAULT_LANG): string
    {
        $item = self::find($path);
        return $item === null ? '' : Content::i18n($item, 'caption', $lang, '');
    }

    /** Attributs width/height pour un <img>, vides si inconnus. */
    public static function dimensions(string $path): array
    {
        $item = self::find($path);
        return [
            'width' => (int) ($item['width'] ?? 0),
            'height' => (int) ($item['height'] ?? 0),
        ];
    }

    /**
     * srcset : les dérivés ET le fichier maître, sans quoi un écran à haute
     * densité serait servi en 800 px alors que l'original est plus grand.
     */
    public static function srcset(string $path): string
    {
        $item = self::find($path);
        if ($item === null) {
            return '';
        }
        $candidates = [];
        foreach ((array) ($item['derivatives'] ?? []) as $width => $url) {
            $candidates[(int) $width] = (string) $url;
        }
        $master = (int) ($item['width'] ?? 0);
        if ($master > 0) {
            $candidates[$master] = $path;
        }
        if (\count($candidates) < 2) {
            return '';
        }
        krsort($candidates);
        $parts = [];
        foreach ($candidates as $width => $url) {
            $parts[] = Config::basePath() . $url . ' ' . $width . 'w';
        }
        return implode(', ', $parts);
    }

    /**
     * Enregistre un fichier reçu par formulaire.
     * @return array{ok:bool,path?:string,error?:string}
     */
    public static function store(array $file, string $by, string $alt = ''): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'error' => 'Aucun fichier reçu.'];
        }
        if ($error !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Envoi interrompu (code ' . $error . ').'];
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if (!is_uploaded_file($tmp) && !is_file($tmp)) {
            return ['ok' => false, 'error' => 'Fichier temporaire introuvable.'];
        }
        if ((int) ($file['size'] ?? 0) > self::MAX_BYTES) {
            return ['ok' => false, 'error' => 'Fichier trop lourd (12 Mo maximum).'];
        }

        $info = @getimagesize($tmp);
        $mime = \is_array($info) ? (string) ($info['mime'] ?? '') : '';
        if (!isset(self::MIMES[$mime])) {
            return ['ok' => false, 'error' => 'Format non accepté : JPEG, PNG ou WebP uniquement.'];
        }

        $base = Text::slug(pathinfo((string) ($file['name'] ?? 'photo'), PATHINFO_FILENAME));
        $base = $base === '' ? 'photo' : mb_substr($base, 0, 60);
        $name = $base . '-' . substr(bin2hex(random_bytes(4)), 0, 6);

        $dir = Config::publicPath(self::DIR);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return ['ok' => false, 'error' => 'Dossier /public/media inaccessible en écriture.'];
        }

        $source = self::openImage($tmp, $mime);
        if ($source === null) {
            return ['ok' => false, 'error' => 'Image illisible.'];
        }
        $width = imagesx($source);
        $height = imagesy($source);

        $master = self::DIR . '/' . $name . '.webp';
        $derivatives = [];
        $ok = self::writeWebp($source, $dir . '/' . $name . '.webp', 86);
        foreach (self::SIZES as $size) {
            if ($width <= $size) {
                continue;
            }
            $resized = imagescale($source, $size);
            if ($resized === false) {
                continue;
            }
            if (self::writeWebp($resized, $dir . '/' . $name . '-' . $size . '.webp', 82)) {
                $derivatives[$size] = '/' . self::DIR . '/' . $name . '-' . $size . '.webp';
            }
            imagedestroy($resized);
        }
        imagedestroy($source);

        if (!$ok) {
            return ['ok' => false, 'error' => 'Conversion WebP impossible sur ce serveur.'];
        }

        $items = self::all();
        array_unshift($items, [
            'path' => '/' . $master,
            'name' => (string) ($file['name'] ?? $name),
            'alt' => $alt !== '' ? $alt : $base,
            'caption' => '',
            'width' => $width,
            'height' => $height,
            'bytes' => filesize($dir . '/' . $name . '.webp') ?: 0,
            'derivatives' => $derivatives,
            'at' => (new \DateTimeImmutable())->format(\DATE_ATOM),
            'by' => $by,
            'i18n' => new \stdClass(),
        ]);
        Store::write(self::FILE, ['_schema' => Config::SCHEMA, 'media' => $items], $by);

        return ['ok' => true, 'path' => '/' . $master];
    }

    public static function update(string $path, array $fields, string $by): bool
    {
        $items = self::all();
        foreach ($items as $i => $item) {
            if ((string) ($item['path'] ?? '') === $path) {
                $items[$i] = array_replace($item, $fields);
                return Store::write(self::FILE, ['_schema' => Config::SCHEMA, 'media' => $items], $by);
            }
        }
        return false;
    }

    public static function delete(string $path, string $by): bool
    {
        $item = self::find($path);
        if ($item === null) {
            return false;
        }
        $files = [Config::publicPath(ltrim($path, '/'))];
        foreach ((array) ($item['derivatives'] ?? []) as $url) {
            $files[] = Config::publicPath(ltrim((string) $url, '/'));
        }
        foreach ($files as $file) {
            if (is_file($file) && str_starts_with(realpath($file) ?: '', Config::publicPath(self::DIR))) {
                @unlink($file);
            }
        }
        $items = array_values(array_filter(self::all(), static fn (array $m): bool => (string) ($m['path'] ?? '') !== $path));
        return Store::write(self::FILE, ['_schema' => Config::SCHEMA, 'media' => $items], $by);
    }

    /**
     * Recense les fichiers déjà présents dans /public/media qui ne sont pas
     * encore dans la photothèque (photos livrées avec le design).
     */
    public static function importExisting(string $by): int
    {
        $known = array_map(static fn (array $m): string => (string) ($m['path'] ?? ''), self::all());
        $added = 0;
        $items = self::all();
        foreach (glob(Config::publicPath(self::DIR) . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [] as $file) {
            $path = '/' . self::DIR . '/' . basename($file);
            if (\in_array($path, $known, true) || preg_match('/-(1600|800|400)\.webp$/', $file) === 1) {
                continue;
            }
            $size = @getimagesize($file) ?: [0, 0];
            $items[] = [
                'path' => $path,
                'name' => basename($file),
                'alt' => Text::slug(pathinfo($file, PATHINFO_FILENAME)),
                'caption' => '',
                'width' => (int) $size[0],
                'height' => (int) $size[1],
                'bytes' => filesize($file) ?: 0,
                'derivatives' => [],
                'at' => date(\DATE_ATOM, filemtime($file) ?: time()),
                'by' => $by,
                'i18n' => new \stdClass(),
            ];
            $added++;
        }
        if ($added > 0) {
            Store::write(self::FILE, ['_schema' => Config::SCHEMA, 'media' => $items], $by);
        }
        return $added;
    }

    private static function openImage(string $file, string $mime): ?\GdImage
    {
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($file),
            'image/png' => @imagecreatefrompng($file),
            'image/webp' => @imagecreatefromwebp($file),
            default => false,
        };
        if ($image === false) {
            return null;
        }
        if ($mime === 'image/png') {
            imagepalettetotruecolor($image);
            imagealphablending($image, true);
            imagesavealpha($image, true);
        }
        return $image;
    }

    private static function writeWebp(\GdImage $image, string $target, int $quality): bool
    {
        if (!\function_exists('imagewebp')) {
            return false;
        }
        return @imagewebp($image, $target, $quality);
    }
}
