<?php

declare(strict_types=1);

namespace App\Shape;

/**
 * Transforme une image matricielle (PNG, JPEG, GIF, WEBP) en nuage de points.
 *
 * Chaque pixel reçoit un poids selon le critère choisi, puis les points sont
 * tirés proportionnellement à ce poids via une table cumulative : les zones
 * denses de l'image reçoivent naturellement plus de particules.
 */
final class ImageSampler
{
    /** Au-delà de cette largeur l'image est réduite : la précision utile est vite atteinte. */
    private const MAX_WIDTH = 420;

    /**
     * @param  array<string,mixed> $options
     * @return array{points: list<array{float,float}>, viewBox: array{float,float,float,float}, colors: list<array{float,float,float}>}
     */
    public static function sample(string $file, int $count, array $options = []): array
    {
        $image = self::open($file);
        [$image, $w, $h] = self::downscale($image);

        // « alpha » suit la transparence, « dark » les pixels sombres, « light » les clairs.
        $criterion = strtolower((string) ($options['criterion'] ?? 'auto'));
        $threshold = (float) ($options['threshold'] ?? 0.5);
        $keepColors = (bool) ($options['colors'] ?? false);

        if ($criterion === 'auto') {
            $criterion = self::hasTransparency($image, $w, $h) ? 'alpha' : 'dark';
        }

        $weights = [];
        $total = 0.0;
        $pixels = [];
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $rgba = imagecolorat($image, $x, $y);
                $alpha = 1.0 - ((($rgba >> 24) & 0x7F) / 127.0);
                $r = (($rgba >> 16) & 0xFF) / 255.0;
                $g = (($rgba >> 8) & 0xFF) / 255.0;
                $b = ($rgba & 0xFF) / 255.0;
                $luma = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;

                $weight = match ($criterion) {
                    'alpha' => $alpha,
                    'light' => $alpha * max(0.0, $luma - $threshold) / max(1e-6, 1.0 - $threshold),
                    default => $alpha * max(0.0, $threshold - $luma) / max(1e-6, $threshold),
                };

                if ($weight <= 0.01) {
                    continue;
                }
                $total += $weight;
                $weights[] = $total;
                $pixels[] = $keepColors ? [$x, $y, $r, $g, $b] : [$x, $y];
            }
        }
        imagedestroy($image);

        if ($pixels === [] || $total <= 0.0) {
            throw new \RuntimeException('Image trop uniforme pour en extraire une forme : ' . basename($file));
        }

        $rng = new Rng((int) ($options['seed'] ?? 1337));
        $points = [];
        $colors = [];
        $n = count($weights);
        $cursor = 0;

        for ($i = 0; $i < $count; $i++) {
            // Tirage stratifié sur la masse cumulée : couverture régulière, sans grappes.
            $target = (($i + $rng->next()) / $count) * $total;
            while ($cursor < $n - 1 && $weights[$cursor] < $target) {
                $cursor++;
            }
            $pixel = $pixels[$cursor];
            $points[] = [$pixel[0] + $rng->next(), $pixel[1] + $rng->next()];
            if ($keepColors) {
                $colors[] = [$pixel[2], $pixel[3], $pixel[4]];
            }
        }

        return [
            'points'  => $points,
            'viewBox' => [0.0, 0.0, (float) $w, (float) $h],
            'colors'  => $colors,
        ];
    }

    private static function open(string $file): \GdImage
    {
        if (!is_file($file)) {
            throw new \RuntimeException('Image introuvable : ' . $file);
        }
        $info = @getimagesize($file);
        if ($info === false) {
            throw new \RuntimeException('Format d\'image non reconnu : ' . basename($file));
        }

        $image = match ($info[2]) {
            IMAGETYPE_PNG  => @imagecreatefrompng($file),
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file),
            IMAGETYPE_GIF  => @imagecreatefromgif($file),
            IMAGETYPE_WEBP => @imagecreatefromwebp($file),
            default        => false,
        };
        if (!$image instanceof \GdImage) {
            throw new \RuntimeException('Lecture impossible : ' . basename($file));
        }

        return $image;
    }

    /**
     * @return array{\GdImage,int,int}
     */
    private static function downscale(\GdImage $image): array
    {
        $w = imagesx($image);
        $h = imagesy($image);
        if ($w <= self::MAX_WIDTH) {
            return [$image, $w, $h];
        }

        $nw = self::MAX_WIDTH;
        $nh = max(1, (int) round($h * $nw / $w));
        $resized = imagecreatetruecolor($nw, $nh);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagefill($resized, 0, 0, imagecolorallocatealpha($resized, 0, 0, 0, 127));
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($image);

        return [$resized, $nw, $nh];
    }

    private static function hasTransparency(\GdImage $image, int $w, int $h): bool
    {
        // Un échantillonnage grossier suffit à détecter un fond transparent.
        $step = max(1, (int) floor(min($w, $h) / 24));
        for ($y = 0; $y < $h; $y += $step) {
            for ($x = 0; $x < $w; $x += $step) {
                if (((imagecolorat($image, $x, $y) >> 24) & 0x7F) > 8) {
                    return true;
                }
            }
        }

        return false;
    }
}
