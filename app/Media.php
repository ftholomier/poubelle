<?php
declare(strict_types=1);

namespace App;

/**
 * Photothèque : upload contrôlé, dérivés WebP 1600/800/400, dimensions connues
 * (width/height dans le JSON) pour éviter tout décalage de mise en page.
 */
final class Media
{
    /** Petit côté minimal d'une photo du diaporama de l'accueil (carré d'environ 560 px). */
    public const SLIDE_MIN = 600;

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

        $converted = self::convert($tmp, $name);
        if (!$converted['ok']) {
            return ['ok' => false, 'error' => $converted['error']];
        }

        $items = self::all();
        array_unshift($items, [
            'path' => $converted['path'],
            'name' => (string) ($file['name'] ?? $name),
            'alt' => $alt !== '' ? $alt : $base,
            'caption' => '',
            'site' => Config::SITES[0],
            'width' => $converted['width'],
            'height' => $converted['height'],
            'bytes' => $converted['bytes'],
            'derivatives' => $converted['derivatives'],
            'at' => (new \DateTimeImmutable())->format(\DATE_ATOM),
            'by' => $by,
            'i18n' => new \stdClass(),
        ]);
        Store::write(self::FILE, ['_schema' => Config::SCHEMA, 'media' => $items], $by);

        return ['ok' => true, 'path' => $converted['path']];
    }

    /**
     * Convertit une image (JPEG, PNG ou WebP) en WebP sous /public/media :
     * le fichier maître à sa définition d'origine, jamais agrandi, puis les
     * dérivés 1600/800/400 plus étroits que lui. La transparence des PNG est
     * conservée. Rien n'est écrit dans la photothèque : c'est à l'appelant
     * d'enregistrer la fiche, une seule fois pour tout un lot.
     *
     * @return array{ok:bool,error:string,path:string,width:int,height:int,bytes:int,derivatives:array<int,string>}
     */
    public static function convert(string $file, string $name): array
    {
        $fail = static fn (string $error): array => ['ok' => false, 'error' => $error, 'path' => '', 'width' => 0, 'height' => 0, 'bytes' => 0, 'derivatives' => []];

        $name = Text::slug($name);
        if ($name === '') {
            return $fail('Nom de fichier invalide.');
        }
        $info = @getimagesize($file);
        $mime = \is_array($info) ? (string) ($info['mime'] ?? '') : '';
        if (!isset(self::MIMES[$mime])) {
            return $fail('Format non accepté : JPEG, PNG ou WebP uniquement.');
        }
        $dir = Config::publicPath(self::DIR);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return $fail('Dossier /public/media inaccessible en écriture.');
        }
        $source = self::openImage($file, $mime);
        if ($source === null) {
            return $fail('Image illisible.');
        }
        $width = imagesx($source);
        $height = imagesy($source);

        if (!self::writeWebp($source, $dir . '/' . $name . '.webp', 86)) {
            imagedestroy($source);
            return $fail('Conversion WebP impossible sur ce serveur.');
        }

        $derivatives = [];
        foreach (self::SIZES as $size) {
            if ($width <= $size) {
                continue;
            }
            $resized = self::resize($source, $size);
            if ($resized === null) {
                continue;
            }
            if (self::writeWebp($resized, $dir . '/' . $name . '-' . $size . '.webp', 82)) {
                $derivatives[$size] = '/' . self::DIR . '/' . $name . '-' . $size . '.webp';
            }
            imagedestroy($resized);
        }
        imagedestroy($source);
        clearstatcache();

        return [
            'ok' => true,
            'error' => '',
            'path' => '/' . self::DIR . '/' . $name . '.webp',
            'width' => $width,
            'height' => $height,
            'bytes' => (int) (filesize($dir . '/' . $name . '.webp') ?: 0),
            'derivatives' => $derivatives,
        ];
    }

    /**
     * Ajoute ou remplace des fiches de la photothèque, en une seule écriture.
     * Une fiche dont le chemin existe déjà remplace l'ancienne.
     *
     * @param array<int,array<string,mixed>> $entries
     */
    public static function register(array $entries, string $by): bool
    {
        $byPath = [];
        foreach (self::all() as $item) {
            $byPath[(string) ($item['path'] ?? '')] = $item;
        }
        foreach ($entries as $entry) {
            $path = (string) ($entry['path'] ?? '');
            if ($path !== '') {
                $byPath[$path] = $entry;
            }
        }
        return Store::write(self::FILE, ['_schema' => Config::SCHEMA, 'media' => array_values($byPath)], $by);
    }

    /** Réduction de qualité (rééchantillonnage), transparence comprise. */
    private static function resize(\GdImage $source, int $width): ?\GdImage
    {
        $height = (int) max(1, round(imagesy($source) * $width / max(1, imagesx($source))));
        $target = imagecreatetruecolor($width, $height);
        if ($target === false) {
            return null;
        }
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));
        imagecopyresampled($target, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));
        return $target;
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
