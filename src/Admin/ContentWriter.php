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

    // ------------------------------------------------------------------ Pages

    /**
     * Crée une page, avec une première section pour qu'elle ne soit pas vide.
     *
     * @param  array<string,mixed> $data
     * @return string l'identifiant retenu
     */
    public static function createPage(array $data): string
    {
        $slug = self::slugify((string) ($data['slug'] ?? $data['title'] ?? ''));
        if ($slug === '') {
            throw new \InvalidArgumentException('Il faut au moins un titre pour créer une page.');
        }
        if (Content::page($slug) !== null) {
            throw new \InvalidArgumentException("Une page « {$slug} » existe déjà.");
        }

        $kind = (string) ($data['kind'] ?? 'hero');
        if (!SectionSchema::isKnownKind($kind)) {
            $kind = 'hero';
        }

        $title = self::text($data['title'] ?? $slug, 160) ?: $slug;
        $page = [
            'title'    => $title,
            'navLabel' => self::text($data['navLabel'] ?? '', 60) ?: $title,
            'order'    => self::order($data['order'] ?? null),
            'inNav'    => ($data['inNav'] ?? true) !== false,
            'meta'     => ['description' => self::text($data['description'] ?? '', 300)],
            'sections' => [[
                'id'    => 'intro',
                'kind'  => $kind,
                'title' => [$title],
                'shape' => ['type' => 'preset', 'preset' => 'sphere', 'count' => 12000],
            ]],
        ];

        self::write(self::pageFile($slug), $page);

        return $slug;
    }

    /**
     * Modifie les réglages d'une page : titre, entrée de menu, rang, description.
     *
     * @param array<string,mixed> $data
     */
    public static function updatePage(string $slug, array $data): void
    {
        $file = self::pageFile($slug, true);
        $page = self::read($file);

        if (array_key_exists('title', $data)) {
            $page['title'] = self::text($data['title'], 160) ?: ($page['title'] ?? $slug);
        }
        if (array_key_exists('navLabel', $data)) {
            $page['navLabel'] = self::text($data['navLabel'], 60) ?: ($page['title'] ?? $slug);
        }
        if (array_key_exists('order', $data)) {
            $page['order'] = self::order($data['order']);
        }
        if (array_key_exists('inNav', $data)) {
            $page['inNav'] = (bool) $data['inNav'];
        }
        if (array_key_exists('description', $data)) {
            $description = self::text($data['description'], 300);
            if ($description === '') {
                unset($page['meta']['description']);
            } else {
                $page['meta']['description'] = $description;
            }
        }

        self::write($file, $page);
    }

    /**
     * Supprime une page.
     *
     * L'accueil est protégé : c'est lui qui répond à la racine du site, et le
     * supprimer laisserait une adresse d'accueil sans contenu.
     */
    public static function deletePage(string $slug): void
    {
        $file = self::pageFile($slug, true);

        if ($slug === Content::HOME) {
            throw new \InvalidArgumentException(
                "L'accueil ne peut pas être supprimé : c'est lui qui répond à la racine du site."
            );
        }
        if (count(Content::pages()) <= 1) {
            throw new \InvalidArgumentException('Il doit rester au moins une page.');
        }

        // La sauvegarde permet de récupérer la page si la suppression était une erreur.
        self::backup($file);
        if (!@unlink($file)) {
            throw new \RuntimeException('Suppression impossible : vérifiez les droits sur content/pages/.');
        }

        Content::forget();
    }

    /**
     * Fixe le rang de chaque page dans le menu, dans l'ordre reçu.
     *
     * @param list<string> $slugs
     */
    public static function reorderPages(array $slugs): void
    {
        $rank = 1;
        foreach ($slugs as $slug) {
            if (!Content::isValidSlug((string) $slug) || Content::page((string) $slug) === null) {
                continue;
            }
            $file = self::pageFile((string) $slug, true);
            $page = self::read($file);
            $page['order'] = $rank++;
            self::write($file, $page);
        }
    }

    // --------------------------------------------------------------- Sections

    /**
     * Ajoute une section à la fin d'une page.
     *
     * @return string l'identifiant retenu
     */
    public static function addSection(string $slug, string $kind, string $id = ''): string
    {
        if (!SectionSchema::isKnownKind($kind)) {
            throw new \InvalidArgumentException("Type de section inconnu : « {$kind} »");
        }

        $file = self::pageFile($slug, true);
        $page = self::read($file);
        $existing = array_column($page['sections'] ?? [], 'id');

        $id = self::slugify($id !== '' ? $id : $kind);
        if ($id === '') {
            $id = $kind;
        }
        // Un identifiant sert d'ancre dans l'URL : il doit rester unique.
        $base = $id;
        $n = 2;
        while (in_array($id, $existing, true)) {
            $id = $base . '-' . $n++;
        }

        $page['sections'][] = [
            'id'    => $id,
            'kind'  => $kind,
            'shape' => ['type' => 'preset', 'preset' => 'sphere', 'count' => 12000],
        ];

        self::write($file, $page);

        return $id;
    }

    /**
     * Remplace le contenu éditorial d'une section, sans toucher à sa forme.
     *
     * @param array<string,mixed> $fields valeurs brutes venues du formulaire
     */
    public static function updateSection(string $slug, string $sectionId, array $fields): void
    {
        $file = self::pageFile($slug, true);
        $page = self::read($file);
        $index = self::findSection($page, $sectionId, $slug);

        $section = $page['sections'][$index];
        $kind = (string) ($section['kind'] ?? 'statement');

        // La forme et l'identité de la section sont pilotées ailleurs : on les
        // reporte telles quelles, et le reste vient du schéma.
        $rebuilt = ['id' => $section['id'], 'kind' => $kind]
            + SectionSchema::sanitize($kind, $fields);
        if (isset($section['shape'])) {
            $rebuilt['shape'] = $section['shape'];
        }

        $page['sections'][$index] = $rebuilt;
        self::write($file, $page);
    }

    public static function deleteSection(string $slug, string $sectionId): void
    {
        $file = self::pageFile($slug, true);
        $page = self::read($file);
        $index = self::findSection($page, $sectionId, $slug);

        if (count($page['sections']) <= 1) {
            throw new \InvalidArgumentException('Une page doit garder au moins une section.');
        }

        array_splice($page['sections'], $index, 1);
        self::write($file, $page);
    }

    /**
     * Déplace une section d'un cran.
     *
     * @param string $direction « up » ou « down »
     */
    public static function moveSection(string $slug, string $sectionId, string $direction): void
    {
        $file = self::pageFile($slug, true);
        $page = self::read($file);
        $index = self::findSection($page, $sectionId, $slug);

        $target = $direction === 'up' ? $index - 1 : $index + 1;
        if ($target < 0 || $target >= count($page['sections'])) {
            // Déjà en bout de liste : il n'y a rien à faire, ce n'est pas une erreur.
            return;
        }

        [$page['sections'][$index], $page['sections'][$target]] =
            [$page['sections'][$target], $page['sections'][$index]];

        self::write($file, $page);
    }

    // ----------------------------------------------------------------- Outils

    /**
     * @param array<string,mixed> $page
     */
    private static function findSection(array $page, string $sectionId, string $slug): int
    {
        foreach ($page['sections'] ?? [] as $index => $section) {
            if (($section['id'] ?? null) === $sectionId) {
                return $index;
            }
        }

        throw new \InvalidArgumentException("Section inconnue : {$slug}/{$sectionId}");
    }

    private static function pageFile(string $slug, bool $mustExist = false): string
    {
        if (!Content::isValidSlug($slug)) {
            throw new \InvalidArgumentException("Identifiant de page invalide : « {$slug} »");
        }
        $file = APP_CONTENT . '/pages/' . $slug . '.json';
        if ($mustExist && !is_file($file)) {
            throw new \InvalidArgumentException("Page inconnue : « {$slug} »");
        }

        return $file;
    }

    /**
     * Transforme un titre en identifiant utilisable dans une URL.
     */
    public static function slugify(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        // Les accents deviennent leur lettre de base ; tout le reste devient un tiret.
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT', $value);
        if ($ascii !== false) {
            $value = $ascii;
        }
        $value = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $value) ?? '');

        return trim(preg_replace('/-+/', '-', $value) ?? '', '-');
    }

    private static function text(mixed $value, int $max): string
    {
        if (!is_string($value)) {
            return '';
        }
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';

        return mb_substr(trim(preg_replace('/\s+/u', ' ', $value) ?? ''), 0, $max);
    }

    private static function order(mixed $value): int
    {
        return max(1, min((int) $value ?: 99, 999));
    }

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
        $json = self::reindent($json);

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

    /**
     * Ramène l'indentation de json_encode() de quatre espaces à deux.
     *
     * Les fichiers de contenu sont aussi écrits à la main : garder le même
     * style évite qu'une sauvegarde depuis le back-office ne réindente tout le
     * fichier et ne noie la modification réelle dans le diff. Une valeur ne
     * peut pas contenir de saut de ligne réel — json_encode l'échappe en « \n » —
     * donc seule l'indentation est touchée.
     */
    private static function reindent(string $json): string
    {
        return (string) preg_replace_callback(
            '/^(?: {4})+/m',
            static fn (array $m): string => str_repeat(' ', strlen($m[0]) / 2),
            $json
        );
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
