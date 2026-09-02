<?php

declare(strict_types=1);

namespace App\Admin;

use App\Content;
use App\Shape\ShapeService;
use App\Theme\Color;
use App\Theme\Palette;

/**
 * Écritures du back-office dans les fichiers de contenu.
 *
 * Chaque écriture est atomique et précédée d'une sauvegarde : le site ne peut
 * pas se retrouver avec un JSON à moitié écrit, et un mauvais réglage se
 * rattrape en restaurant le fichier précédent.
 */
final class ContentWriter
{
    /** Nombre de sauvegardes conservées par fichier. */
    private const KEEP_BACKUPS = 10;

    /**
     * Affecte une forme à une section.
     *
     * @param  array<string,mixed> $shape
     * @throws \InvalidArgumentException si la page, la section ou la forme est invalide
     */
    public static function saveSectionShape(string $pageSlug, string $sectionId, array $shape): void
    {
        if (!Content::isValidSlug($pageSlug)) {
            throw new \InvalidArgumentException("Identifiant de page invalide : « {$pageSlug} »");
        }

        $file = APP_CONTENT . '/pages/' . $pageSlug . '.json';
        $data = self::read($file);

        $shape = self::sanitizeShape($shape);

        // On refuse d'enregistrer une forme que le moteur ne sait pas construire :
        // mieux vaut un message d'erreur qu'une page cassée en production.
        ShapeService::build($shape + ['id' => $pageSlug . '/' . $sectionId]);

        $found = false;
        foreach ($data['sections'] ?? [] as $index => $section) {
            if (($section['id'] ?? null) === $sectionId) {
                $data['sections'][$index]['shape'] = $shape;
                $found = true;
                break;
            }
        }

        if (!$found) {
            throw new \InvalidArgumentException("Section inconnue : {$pageSlug}/{$sectionId}");
        }

        self::write($file, $data);
    }

    /**
     * Enregistre la couleur dominante et le mode d'harmonie.
     *
     * @param array<string,mixed> $theme
     */
    public static function saveTheme(array $theme): void
    {
        $file = APP_CONTENT . '/site.json';
        $data = self::read($file);

        $dominant = (string) ($theme['dominant'] ?? '');
        // Lève une exception si la couleur n'est pas exploitable.
        Color::fromHex($dominant);

        $harmony = (string) ($theme['harmony'] ?? 'duo');
        if (!isset(Palette::HARMONIES[$harmony])) {
            throw new \InvalidArgumentException("Harmonie inconnue : « {$harmony} »");
        }

        // Les surcharges manuelles déjà présentes sont conservées telles quelles.
        $existing = is_array($data['theme'] ?? null) ? $data['theme'] : [];
        $data['theme'] = ['dominant' => $dominant, 'harmony' => $harmony] + $existing;
        $data['theme']['dominant'] = $dominant;
        $data['theme']['harmony'] = $harmony;

        self::write($file, $data);
    }

    /**
     * Retire une surcharge de couleur posée à la main, pour revenir à la dérivation.
     */
    public static function resetThemeKey(string $key): void
    {
        $file = APP_CONTENT . '/site.json';
        $data = self::read($file);
        unset($data['theme'][$key]);
        self::write($file, $data);
    }

    /**
     * Ne garde que les clés connues d'une forme, avec le bon type et dans les bornes.
     *
     * @param  array<string,mixed> $shape
     * @return array<string,mixed>
     */
    public static function sanitizeShape(array $shape): array
    {
        $type = (string) ($shape['type'] ?? 'preset');
        if (!in_array($type, ['svg', 'image', 'preset', 'text'], true)) {
            throw new \InvalidArgumentException("Type de forme inconnu : « {$type} »");
        }

        $clean = [
            'type'  => $type,
            'count' => max(64, min((int) ($shape['count'] ?? 12000), 40000)),
            'depth' => round(max(0.0, min((float) ($shape['depth'] ?? 0.12), 1.0)), 3),
            'scale' => round(max(0.2, min((float) ($shape['scale'] ?? 1.0), 2.0)), 3),
        ];

        $spin = round(max(0.0, min((float) ($shape['spin'] ?? 0.0), 2.0)), 3);
        if ($spin > 0.0) {
            $clean['spin'] = $spin;
            $clean['spinAxis'] = ($shape['spinAxis'] ?? 'y') === 'z' ? 'z' : 'y';
        }

        foreach (['offsetX', 'offsetY'] as $axis) {
            $value = round(max(-1.0, min((float) ($shape[$axis] ?? 0.0), 1.0)), 3);
            if ($value !== 0.0) {
                $clean[$axis] = $value;
            }
        }

        $seed = (int) ($shape['seed'] ?? 1337);
        if ($seed !== 1337) {
            $clean['seed'] = max(1, min($seed, 999999));
        }

        switch ($type) {
            case 'svg':
            case 'image':
                $clean['src'] = self::sanitizeSource((string) ($shape['src'] ?? ''));
                if ($type === 'svg') {
                    $clean['mode'] = ($shape['mode'] ?? 'fill') === 'outline' ? 'outline' : 'fill';
                    if (($shape['fillRule'] ?? 'nonzero') === 'evenodd') {
                        $clean['fillRule'] = 'evenodd';
                    }
                } elseif (in_array($shape['criterion'] ?? 'auto', ['alpha', 'dark', 'light'], true)) {
                    $clean['criterion'] = (string) $shape['criterion'];
                }
                break;

            case 'preset':
                $clean['preset'] = (string) ($shape['preset'] ?? 'sphere');
                break;

            case 'text':
                $text = trim((string) ($shape['text'] ?? ''));
                if ($text === '') {
                    throw new \InvalidArgumentException('Le texte de la forme est vide.');
                }
                $clean['text'] = mb_substr($text, 0, 24);
                break;
        }

        $label = trim((string) ($shape['label'] ?? ''));
        if ($label !== '') {
            $clean['label'] = mb_substr($label, 0, 120);
        }

        return $clean;
    }

    /**
     * Un chemin de source doit désigner un fichier réel sous content/shapes/.
     */
    private static function sanitizeSource(string $src): string
    {
        $src = ltrim(str_replace('\\', '/', trim($src)), '/');
        if ($src === '' || str_contains($src, '..')) {
            throw new \InvalidArgumentException("Chemin de source invalide : « {$src} »");
        }

        $resolved = realpath(APP_CONTENT . '/' . $src);
        $root = realpath(APP_CONTENT . '/shapes');
        if ($resolved === false || $root === false || !str_starts_with($resolved, $root . DIRECTORY_SEPARATOR)) {
            throw new \InvalidArgumentException("Source introuvable dans content/shapes/ : « {$src} »");
        }

        return $src;
    }

    /**
     * @return array<string,mixed>
     */
    private static function read(string $file): array
    {
        if (!is_file($file)) {
            throw new \InvalidArgumentException('Fichier introuvable : ' . basename($file));
        }
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data)) {
            throw new \RuntimeException(basename($file) . ' est illisible : ' . json_last_error_msg());
        }

        return $data;
    }

    /**
     * @param array<string,mixed> $data
     */
    private static function write(string $file, array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Encodage JSON impossible : ' . json_last_error_msg());
        }

        self::backup($file);

        // Écriture atomique : le fichier servi n'est jamais un fichier à moitié écrit.
        $tmp = $file . '.' . getmypid() . '.tmp';
        if (file_put_contents($tmp, $json . "\n") === false) {
            throw new \RuntimeException('Écriture impossible dans content/ : vérifiez les droits.');
        }
        if (!rename($tmp, $file)) {
            @unlink($tmp);
            throw new \RuntimeException('Remplacement du fichier impossible : ' . basename($file));
        }

        Content::forget();
    }

    private static function backup(string $file): void
    {
        $dir = APP_ROOT . '/var/backups';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $name = basename($file, '.json');
        @copy($file, sprintf('%s/%s-%s.json', $dir, $name, date('Ymd-His')));

        // On ne conserve que les dernières : l'historique n'a pas vocation à gonfler.
        $existing = glob($dir . '/' . $name . '-*.json') ?: [];
        if (count($existing) > self::KEEP_BACKUPS) {
            sort($existing);
            foreach (array_slice($existing, 0, count($existing) - self::KEEP_BACKUPS) as $old) {
                @unlink($old);
            }
        }
    }
}
