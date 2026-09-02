<?php

declare(strict_types=1);

namespace App\Shape;

use App\Config;

/**
 * Point d'entrée unique du moteur de formes.
 *
 * Il aiguille vers le bon échantillonneur selon le type déclaré dans le JSON,
 * ramène le résultat dans le repère normalisé attendu par WebGL (cube [-1, 1],
 * axe Y vers le haut) et conserve le tout en cache disque.
 *
 * Types acceptés :
 *   svg     — un fichier vectoriel, rempli ou détouré
 *   image   — un PNG / JPEG / GIF / WEBP, échantillonné pixel par pixel
 *   preset  — une forme mathématique (sphère, galaxie, tore…)
 *   text    — du texte, tracé par le navigateur avec la police du site
 */
final class ShapeService
{
    /** Version du format : la modifier invalide tous les caches existants. */
    private const CACHE_VERSION = 3;

    /**
     * @param  array<string,mixed> $shape déclaration issue de content/sections.json
     * @return array<string,mixed>
     */
    public static function build(array $shape): array
    {
        $type = strtolower((string) ($shape['type'] ?? 'preset'));

        // Le texte a besoin des polices du navigateur : il est tracé côté client.
        if ($type === 'text') {
            return self::clientShape($shape);
        }

        $key = self::cacheKey($shape);
        $cached = self::readCache($key);
        if ($cached !== null) {
            return $cached;
        }

        $built = match ($type) {
            'svg'    => self::fromSvg($shape),
            'image'  => self::fromImage($shape),
            'preset' => self::fromPreset($shape),
            default  => throw new \InvalidArgumentException(
                "Type de forme inconnu : « {$type} » (attendu : svg, image, preset ou text)"
            ),
        };

        self::writeCache($key, $built);

        return $built;
    }

    /**
     * @param  array<string,mixed> $shape
     * @return array<string,mixed>
     */
    private static function fromSvg(array $shape): array
    {
        $file = self::resolvePath((string) ($shape['src'] ?? ''));
        $result = SvgSampler::sample($file, (int) $shape['count'], [
            'mode'     => $shape['mode'] ?? 'fill',
            'fillRule' => $shape['fillRule'] ?? 'nonzero',
            'seed'     => $shape['seed'] ?? 1337,
            'jitter'   => $shape['jitter'] ?? 0.0,
        ]);

        return self::finalize($shape, self::project($result['points'], $result['viewBox'], $shape), 'svg');
    }

    /**
     * @param  array<string,mixed> $shape
     * @return array<string,mixed>
     */
    private static function fromImage(array $shape): array
    {
        $file = self::resolvePath((string) ($shape['src'] ?? ''));
        $result = ImageSampler::sample($file, (int) $shape['count'], [
            'criterion' => $shape['criterion'] ?? 'auto',
            'threshold' => $shape['threshold'] ?? 0.5,
            'seed'      => $shape['seed'] ?? 1337,
            'colors'    => $shape['colors'] ?? false,
        ]);

        $built = self::finalize($shape, self::project($result['points'], $result['viewBox'], $shape), 'image');
        if ($result['colors'] !== []) {
            $flat = [];
            foreach ($result['colors'] as [$r, $g, $b]) {
                $flat[] = round($r, 3);
                $flat[] = round($g, 3);
                $flat[] = round($b, 3);
            }
            $built['colors'] = $flat;
        }

        return $built;
    }

    /**
     * @param  array<string,mixed> $shape
     * @return array<string,mixed>
     */
    private static function fromPreset(array $shape): array
    {
        $points = PresetSampler::sample(
            (string) ($shape['preset'] ?? 'sphere'),
            (int) $shape['count'],
            $shape
        );

        return self::finalize($shape, self::normalize3d($points, $shape), 'preset');
    }

    /**
     * Passage du repère écran d'une image ou d'un SVG (origine en haut à gauche,
     * Y vers le bas) au cube normalisé de WebGL, en conservant les proportions.
     *
     * @param  list<array{float,float}>       $points
     * @param  array{float,float,float,float} $viewBox
     * @param  array<string,mixed>            $shape
     * @return list<float>
     */
    private static function project(array $points, array $viewBox, array $shape): array
    {
        [$vx, $vy, $vw, $vh] = $viewBox;
        $longest = max($vw, $vh);
        if ($longest <= 0.0) {
            $longest = 1.0;
        }
        $scale = (2.0 / $longest) * (float) ($shape['scale'] ?? 1.0);
        $cx = $vx + $vw / 2.0;
        $cy = $vy + $vh / 2.0;

        $depth = (float) ($shape['depth'] ?? 0.12);
        $rng = new Rng(((int) ($shape['seed'] ?? 1337)) ^ 0x5EED);

        $flat = [];
        foreach ($points as [$x, $y]) {
            $flat[] = round(($x - $cx) * $scale, 4);
            $flat[] = round(-($y - $cy) * $scale, 4);
            $flat[] = round($rng->nextSigned() * $depth, 4);
        }

        return $flat;
    }

    /**
     * Recentre et met à l'échelle un nuage déjà tridimensionnel.
     *
     * @param  list<array{float,float,float}> $points
     * @param  array<string,mixed>            $shape
     * @return list<float>
     */
    private static function normalize3d(array $points, array $shape): array
    {
        $min = [INF, INF, INF];
        $max = [-INF, -INF, -INF];
        foreach ($points as $p) {
            for ($axis = 0; $axis < 3; $axis++) {
                $min[$axis] = min($min[$axis], $p[$axis]);
                $max[$axis] = max($max[$axis], $p[$axis]);
            }
        }
        $extent = max($max[0] - $min[0], $max[1] - $min[1], $max[2] - $min[2]);
        if (!is_finite($extent) || $extent <= 0.0) {
            $extent = 1.0;
        }
        $scale = (2.0 / $extent) * (float) ($shape['scale'] ?? 1.0);
        $center = [
            ($min[0] + $max[0]) / 2.0,
            ($min[1] + $max[1]) / 2.0,
            ($min[2] + $max[2]) / 2.0,
        ];

        $flat = [];
        foreach ($points as $p) {
            $flat[] = round(($p[0] - $center[0]) * $scale, 4);
            $flat[] = round(($p[1] - $center[1]) * $scale, 4);
            $flat[] = round(($p[2] - $center[2]) * $scale, 4);
        }

        return $flat;
    }

    /**
     * @param  array<string,mixed> $shape
     * @param  list<float>         $positions
     * @return array<string,mixed>
     */
    private static function finalize(array $shape, array $positions, string $type): array
    {
        return [
            'id'        => (string) ($shape['id'] ?? 'shape'),
            'type'      => $type,
            'source'    => 'server',
            'count'     => intdiv(count($positions), 3),
            'positions' => $positions,
            'spin'      => (float) ($shape['spin'] ?? 0.0),
            'spinAxis'  => ($shape['spinAxis'] ?? 'y') === 'z' ? 'z' : 'y',
            'label'     => $shape['label'] ?? null,
        ];
    }

    /**
     * Forme déléguée au navigateur : on ne renvoie que la recette.
     *
     * @param  array<string,mixed> $shape
     * @return array<string,mixed>
     */
    private static function clientShape(array $shape): array
    {
        return [
            'id'      => (string) ($shape['id'] ?? 'shape'),
            'type'    => 'text',
            'source'  => 'client',
            'count'   => (int) $shape['count'],
            'text'    => (string) ($shape['text'] ?? ''),
            'font'    => (string) ($shape['font'] ?? '700 200px Montserrat, sans-serif'),
            'spacing' => (float) ($shape['spacing'] ?? 0.0),
            'depth'   => (float) ($shape['depth'] ?? 0.08),
            'scale'   => (float) ($shape['scale'] ?? 1.0),
            'seed'    => (int) ($shape['seed'] ?? 1337),
            'spin'    => (float) ($shape['spin'] ?? 0.0),
            'spinAxis' => ($shape['spinAxis'] ?? 'y') === 'z' ? 'z' : 'y',
            'label'   => $shape['label'] ?? null,
        ];
    }

    /**
     * Les sources de formes vivent dans content/ ; toute échappée est refusée.
     */
    private static function resolvePath(string $src): string
    {
        if ($src === '') {
            throw new \InvalidArgumentException('Chemin de source manquant (clé « src »).');
        }
        $candidate = realpath(APP_CONTENT . '/' . ltrim($src, '/'));
        $root = realpath(APP_CONTENT);
        if ($candidate === false || $root === false || !str_starts_with($candidate, $root . DIRECTORY_SEPARATOR)) {
            throw new \InvalidArgumentException("Source introuvable dans content/ : « {$src} »");
        }

        return $candidate;
    }

    /**
     * @param array<string,mixed> $shape
     */
    private static function cacheKey(array $shape): string
    {
        $signature = $shape;
        // La date du fichier source fait partie de la clé : modifier le SVG régénère le nuage.
        if (isset($shape['src']) && is_string($shape['src'])) {
            $path = APP_CONTENT . '/' . ltrim($shape['src'], '/');
            $signature['__mtime'] = is_file($path) ? filemtime($path) : 0;
        }
        ksort($signature);

        return substr(hash('sha256', self::CACHE_VERSION . json_encode($signature)), 0, 32);
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function readCache(string $key): ?array
    {
        if (!Config::get('cache.enabled', true)) {
            return null;
        }
        $file = APP_CACHE . '/shape-' . $key . '.json';
        if (!is_file($file)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data)) {
            return null;
        }

        // json_encode écrit 0.0 sous la forme « 0 », que json_decode relit en entier.
        // Sans cette remise au type flottant, une réponse servie depuis le cache
        // diffère de la même réponse calculée à froid — et son ETag avec elle.
        if (isset($data['positions']) && is_array($data['positions'])) {
            $data['positions'] = array_map('floatval', $data['positions']);
        }
        if (isset($data['colors']) && is_array($data['colors'])) {
            $data['colors'] = array_map('floatval', $data['colors']);
        }

        return $data;
    }

    /**
     * @param array<string,mixed> $data
     */
    private static function writeCache(string $key, array $data): void
    {
        if (!Config::get('cache.enabled', true)) {
            return;
        }
        $file = APP_CACHE . '/shape-' . $key . '.json';
        // Écriture atomique : jamais de fichier de cache tronqué servi à un visiteur.
        $tmp = $file . '.' . getmypid() . '.tmp';
        if (file_put_contents($tmp, json_encode($data)) !== false) {
            @rename($tmp, $file);
        }
    }
}
