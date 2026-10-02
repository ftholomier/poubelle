<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Response;
use App\Data\Media;

/**
 * Images redimensionnées à la volée, au format WebP, puis mises en cache dans
 * public/media/{largeur}/{chemin}.webp : les visites suivantes sont servies
 * directement par Apache, sans PHP.
 */
final class Images
{
    public const WIDTHS = [160, 320, 480, 640, 800, 1200, 1600];

    public static function serve(string $w, string $rel): Response
    {
        $rel = ltrim(str_replace(['..', "\0", '\\'], '', rawurldecode($rel)), '/');
        $isWebp = str_ends_with($rel, '.webp') && !is_file(Media::ORIGINALS . '/' . $rel);
        $srcRel = $isWebp ? substr($rel, 0, -5) : $rel;
        $src = Media::file($srcRel);

        if ($w === 'full') {
            if (!$src) {
                return self::placeholder();
            }
            return new Response((string) file_get_contents($src), 200, [
                'Content-Type' => mime_content_type($src) ?: 'application/octet-stream',
                'Cache-Control' => 'public, max-age=2592000',
            ]);
        }
        $width = (int) $w;
        if (!in_array($width, self::WIDTHS, true)) {
            return new Response('Largeur non autorisée', 400);
        }
        if (!$src) {
            return self::placeholder();
        }
        $dest = PUBLIC_PATH . "/media/$width/$srcRel.webp";
        if (!is_file($dest) || filemtime($dest) < filemtime($src)) {
            if (!self::generate($src, $dest, $width)) {
                return self::placeholder();
            }
        }
        return new Response((string) file_get_contents($dest), 200, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    public static function generate(string $src, string $dest, int $width): bool
    {
        $info = @getimagesize($src);
        if (!$info) {
            return self::copySvg($src, $dest);
        }
        [$w, $h, $type] = $info;
        if ($w * $h > 120_000_000) {
            return false;
        }
        ini_set('memory_limit', '1536M');
        set_time_limit(120);
        $im = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($src),
            IMAGETYPE_PNG => @imagecreatefrompng($src),
            IMAGETYPE_GIF => @imagecreatefromgif($src),
            IMAGETYPE_WEBP => @imagecreatefromwebp($src),
            IMAGETYPE_BMP => @imagecreatefrombmp($src),
            default => false,
        };
        if (!$im) {
            return false;
        }
        if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($src);
            $o = $exif['Orientation'] ?? 1;
            if (in_array($o, [3, 6, 8], true)) {
                $im = imagerotate($im, [3 => 180, 6 => -90, 8 => 90][$o], 0);
                [$w, $h] = [imagesx($im), imagesy($im)];
            }
        }
        $tw = min($width, $w);
        $th = (int) max(1, round($h * $tw / $w));
        $out = imagecreatetruecolor($tw, $th);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagecopyresampled($out, $im, 0, 0, 0, 0, $tw, $th, $w, $h);
        imagedestroy($im);
        $dir = dirname($dest);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }
        $tmp = $dest . '.' . bin2hex(random_bytes(3)) . '.tmp';
        $ok = imagewebp($out, $tmp, $tw <= 320 ? 76 : 80);
        imagedestroy($out);
        if ($ok) {
            rename($tmp, $dest);
        }
        return $ok;
    }

    private static function copySvg(string $src, string $dest): bool
    {
        // SVG et formats non matriciels : pas de redimensionnement.
        return false;
    }

    public static function placeholder(): Response
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300"><rect width="400" height="300" fill="#E8DFC9"/>'
            . '<g fill="none" stroke="#0E1F4D" stroke-opacity=".35" stroke-width="6"><rect x="150" y="105" width="100" height="80" rx="6"/>'
            . '<circle cx="178" cy="132" r="9"/><path d="M155 180l35-30 25 20 15-12 20 22"/></g></svg>';
        return new Response($svg, 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'public, max-age=600']);
    }

    /** Pré-génère les vignettes (ligne de commande). */
    public static function warmup(int $width, ?callable $log = null): int
    {
        $n = 0;
        foreach (Media::all() as $rel => $m) {
            if (!str_starts_with((string) ($m['mime'] ?? ''), 'image/') || str_contains((string) $m['mime'], 'svg')) {
                continue;
            }
            $src = Media::file($rel);
            $dest = PUBLIC_PATH . "/media/$width/$rel.webp";
            if ($src && !is_file($dest) && self::generate($src, $dest, $width)) {
                $n++;
                if ($log && $n % 200 === 0) {
                    $log("$n…");
                }
            }
        }
        return $n;
    }
}
