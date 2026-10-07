<?php
declare(strict_types=1);

namespace App\Services;

use App\Data\Categories;
use App\Data\Fiches;
use App\Data\Index;
use App\Data\Paths;

/**
 * Grands récits (rubrique « Grands récits », mise en page App\Front\Recit) : textes livrés avec le site
 * (app/Resources/import/recits.php), créés en ligne d'un clic, une seule fois chacun. Une fiche déjà
 * créée n'est jamais réécrite : les historiens peuvent la retoucher librement.
 */
final class GrandsRecits
{
    public const ROOT = 'grands-recits';
    private const AUTHOR = ['name' => 'Sochaux Rétro'];

    /** @return list<array> */
    public static function all(): array
    {
        return require APP_ROOT . '/app/Resources/import/recits.php';
    }

    /** Fiche déjà créée pour ce récit (clé dans legacy.recit), ou null. */
    public static function existing(string $key): ?int
    {
        foreach (Index::all() as $e) {
            if (in_array($e['type'] ?? '', ['article', 'page'], true) && in_array(self::ROOT, (array) ($e['categories'] ?? []), true)) {
                $d = Fiches::get((int) $e['id']);
                if (($d['legacy']['recit'] ?? '') === $key) {
                    return (int) $e['id'];
                }
            }
        }
        return null;
    }

    /** État de chaque récit : ['key', 'title', 'fiche' => id|null]. */
    public static function status(): array
    {
        return array_map(fn ($r) => ['key' => $r['key'], 'title' => $r['title'], 'fiche' => self::existing($r['key'])], self::all());
    }

    /** Rubrique racine « Grands récits » (/grands-recits/), créée si elle manque. */
    public static function ensureRubric(): void
    {
        if (Categories::get(self::ROOT)) {
            return;
        }
        $cats = Categories::all();
        $cats[self::ROOT] = ['id' => max(array_map(fn ($c) => (int) ($c['id'] ?? 0), $cats)) + 1, 'slug' => self::ROOT, 'name' => 'Grands récits', 'label' => null, 'position' => null,
            'description' => '<p>Les grandes histoires du FC Sochaux-Montbéliard, racontées d’après les archives du club, de l’association et du musée Peugeot.</p>',
            'parent' => null, 'path' => '/' . self::ROOT . '/', 'season' => null, 'technical' => false, 'old_path' => null, 'order' => [], 'wp_count' => 0];
        Categories::save($cats, self::AUTHOR);
        Categories::forget();
    }

    /** Crée les récits manquants. @return int créés */
    public static function create(?array $user = null): int
    {
        self::ensureRubric();
        $n = 0;
        foreach (self::all() as $r) {
            if (self::existing($r['key'])) {
                continue;
            }
            $doc = Fiches::blank('article');
            $doc['title'] = $r['title'];
            $doc['intro'] = $r['intro'];
            // Chapô du récit : le texte avant le premier intertitre.
            $doc['sections'][] = ['title' => '', 'html' => '<p>' . e($r['intro']) . '</p>'];
            foreach ($r['sections'] as [$t, $html]) {
                $doc['sections'][] = ['title' => $t, 'html' => self::links($html)];
            }
            $doc['sections'][] = ['title' => 'Sources', 'html' => '<p>Récit de Sochaux Rétro d’après les feuilles de match et les bilans de l’association, les documents du club et les archives du musée Peugeot.</p>'];
            $doc['status'] = 'publie';
            $doc['categories'] = [self::ROOT];
            $doc['legacy'] = ['recit' => $r['key'], 'image_hint' => $r['image_hint'] ?? []];
            $doc['path'] = Paths::unique('/' . self::ROOT . '/' . $r['key'] . '/', -1);
            $doc['slug'] = $r['key'];
            Fiches::save($doc, $user ?? self::AUTHOR, 'Création du grand récit');
            $n++;
        }
        return $n;
    }

    /** {{match:AAAA-MM-JJ|texte}} → lien vers la fiche du match de Sochaux ce jour-là (sinon le texte seul). */
    public static function links(string $html): string
    {
        return preg_replace_callback('/\{\{match:(\d{4}-\d{2}-\d{2})\|([^}]*)\}\}/u', function ($m) {
            foreach (Index::all() as $e) {
                if (($e['type'] ?? '') === 'match' && ($e['m']['date'] ?? '') === $m[1] && preg_match('/sochaux/i', (string) ($e['m']['home'] ?? '') . (string) ($e['m']['away'] ?? '')) && Index::visible($e)) {
                    return '<a href="' . e($e['path']) . '">' . e($m[2]) . '</a>';
                }
            }
            return e($m[2]);
        }, $html) ?? $html;
    }

    /** Récits auxquels une image du catalogue pourrait aller (mots-clés communs). @return list<array{id:int,title:string}> */
    public static function forImage(array $it): array
    {
        $hay = mb_strtolower(implode(' ', array_merge([(string) ($it['title'] ?? ''), (string) ($it['caption'] ?? ''), (string) ($it['lot'] ?? '')], (array) ($it['topics'] ?? []))));
        $out = [];
        foreach (self::all() as $r) {
            foreach ((array) ($r['image_hint'] ?? []) as $w) {
                if (str_contains($hay, mb_strtolower($w)) && ($id = self::existing($r['key']))) {
                    $out[] = ['id' => $id, 'title' => $r['title']];
                    break;
                }
            }
        }
        return $out;
    }
}
