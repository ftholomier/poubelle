<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Storage\Audit;

/**
 * Redimensionnement des images téléversées, avec GD — aucune dépendance.
 *
 * Les photos de profil et les logos étaient servis tels que les visiteurs les
 * avaient envoyés : une page d'annuaire pesait 2,7 Mo pour douze vignettes de
 * 96 pixels. On ramène chaque fichier à une taille raisonnable et on produit
 * une vignette, servie aux listes.
 */
final class Image
{
    /** Côté le plus long d'une image de fiche. */
    private const MAX_SIDE = 1000;

    /** Côté d'une vignette de liste, en pixels physiques (densité 2 comprise). */
    private const THUMB_SIDE = 256;

    private const QUALITY = 82;

    public static function available(): bool
    {
        return function_exists('imagecreatetruecolor') && function_exists('imagecreatefromstring');
    }

    /**
     * Réécrit l'image à une taille raisonnable et produit sa vignette.
     *
     * @return array{ok:bool, width:int, height:int, thumb:string}
     *         `thumb` est le chemin relatif de la vignette, '' si aucune
     */
    public static function process(string $absolutePath, string $relative): array
    {
        $fail = ['ok' => false, 'width' => 0, 'height' => 0, 'thumb' => ''];
        if (!self::available() || !is_file($absolutePath)) {
            return $fail;
        }

        $image = self::load($absolutePath);
        if ($image === null) {
            return $fail;
        }

        [$width, $height] = [imagesx($image), imagesy($image)];

        $resized = self::scale($image, self::MAX_SIDE);
        if ($resized !== $image) {
            imagedestroy($image);
            $image = $resized;
            [$width, $height] = [imagesx($image), imagesy($image)];
            self::save($image, $absolutePath);
        }

        $thumbRelative = self::thumbPath($relative);
        $thumb = self::scale($image, self::THUMB_SIDE);
        $thumbAbsolute = dirname($absolutePath) . '/' . basename($thumbRelative);
        $saved = self::save($thumb, $thumbAbsolute);
        if ($thumb !== $image) {
            imagedestroy($thumb);
        }
        imagedestroy($image);

        return [
            'ok'     => true,
            'width'  => $width,
            'height' => $height,
            'thumb'  => $saved ? $thumbRelative : '',
        ];
    }

    /** « photo/c1234.jpg » -> « photo/c1234-t.jpg ». */
    public static function thumbPath(string $relative): string
    {
        $extension = pathinfo($relative, PATHINFO_EXTENSION);
        $base = substr($relative, 0, strlen($relative) - strlen($extension) - 1);
        return $base . '-t.' . $extension;
    }

    private static function load(string $path): ?\GdImage
    {
        $data = @file_get_contents($path);
        if ($data === false || $data === '') {
            return null;
        }
        $image = @imagecreatefromstring($data);
        return $image instanceof \GdImage ? $image : null;
    }

    /** Réduit l'image si son plus grand côté dépasse la limite. */
    private static function scale(\GdImage $image, int $maxSide): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $longest = max($width, $height);
        if ($longest <= $maxSide) {
            return $image;
        }

        $ratio = $maxSide / $longest;
        $target = imagescale($image, (int) round($width * $ratio), (int) round($height * $ratio), IMG_BICUBIC);
        return $target instanceof \GdImage ? $target : $image;
    }

    /** Écrit l'image au format déduit de l'extension, sur fond blanc si besoin. */
    private static function save(\GdImage $image, string $path): bool
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        // JPEG ne connaît pas la transparence : un PNG à fond transparent
        // deviendrait noir. On aplatit sur blanc avant d'encoder.
        if ($extension === 'jpg' || $extension === 'jpeg') {
            $flat = imagecreatetruecolor(imagesx($image), imagesy($image));
            imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
            imagecopy($flat, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
            $ok = @imagejpeg($flat, $path, self::QUALITY);
            imagedestroy($flat);
            return (bool) $ok;
        }

        if ($extension === 'webp' && function_exists('imagewebp')) {
            imagepalettetotruecolor($image);
            imagealphablending($image, false);
            imagesavealpha($image, true);
            return (bool) @imagewebp($image, $path, self::QUALITY);
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);
        return (bool) @imagepng($image, $path, 8);
    }

    /**
     * Repasse toutes les images déjà stockées. Appelé par
     * bin/optimize-images.php ; sans effet sur une image déjà à la bonne taille.
     *
     * @return array{files:int, saved:int}
     */
    public static function optimizeAll(): array
    {
        $root = Config::path('data') . '/uploads';
        $files = 0;
        $saved = 0;

        foreach (['photo', 'logo'] as $kind) {
            foreach (glob($root . '/' . $kind . '/*') ?: [] as $path) {
                if (!is_file($path) || str_contains(basename($path), '-t.')) {
                    continue;
                }
                $before = (int) filesize($path);
                $relative = $kind . '/' . basename($path);
                $result = self::process($path, $relative);
                if ($result['ok']) {
                    clearstatcache(true, $path);
                    $saved += max(0, $before - (int) filesize($path));
                    $files++;
                }
            }
        }

        Audit::log('images.optimized', ['files' => $files, 'saved_kb' => (int) round($saved / 1024)]);
        return ['files' => $files, 'saved' => $saved];
    }
}
