<?php
declare(strict_types=1);

namespace App\Services;

use App\Data\Categories;
use App\Data\Fiches;
use App\Data\Index;
use App\Data\Names;
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
        static $all = null;
        if ($all !== null) {
            return $all;
        }
        // Premiers récits, puis un fichier par décennie (app/Resources/import/recits/*.php), dans l'ordre chronologique.
        $all = require APP_ROOT . '/app/Resources/import/recits.php';
        foreach (glob(APP_ROOT . '/app/Resources/import/recits/*.php') ?: [] as $f) {
            $all = array_merge($all, require $f);
        }
        usort($all, fn ($a, $b) => ($a['year'] ?? 9999) <=> ($b['year'] ?? 9999));
        return $all;
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
            $links = implode('', array_map(fn ($l) => '<li><a href="' . e($l[1]) . '" rel="noopener" target="_blank">' . e($l[0]) . '</a></li>', (array) ($r['sources'] ?? [])));
            $doc['sections'][] = ['title' => 'Sources', 'html' => '<p>Récit de Sochaux Rétro d’après les feuilles de match et les bilans de l’association, les documents du club et les archives du musée Peugeot' . ($links ? ', et la presse :' : '.') . '</p>' . ($links ? '<ul>' . $links . '</ul>' : '')];
            // Les nouveaux récits attendent une relecture avant d'être publiés.
            $doc['status'] = !empty($r['review']) ? 'relire' : 'publie';
            $doc['categories'] = [self::ROOT];
            $doc['legacy'] = ['recit' => $r['key'], 'image_hint' => $r['image_hint'] ?? []];
            $doc['path'] = Paths::unique('/' . self::ROOT . '/' . $r['key'] . '/', -1);
            $doc['slug'] = $r['key'];
            Fiches::save($doc, $user ?? self::AUTHOR, 'Création du grand récit');
            $n++;
        }
        if ($n) {
            self::illustrate($user);
        }
        return $n;
    }

    /** Photos par récit (au plus), et qualité minimale des images d'archives. */
    public const MAX_PHOTOS = 8;

    /**
     * Illustre les récits : complète la galerie de chaque récit (sans rien retirer) avec les photos
     * des fiches qu'il cite (matchs liés, joueurs nommés) et les images du catalogue des archives de
     * la même époque et du même sujet. Jamais d'image de presse. @return array{recits:int,photos:int}
     */
    public static function illustrate(?array $user = null): array
    {
        $index = Index::all();
        $byPath = [];
        $people = [];
        foreach ($index as $e) {
            $byPath[$e['path'] ?? ''] = $e;
            if (($e['type'] ?? '') === 'personne' && Index::visible($e) && !empty($e['image']) && !Index::isPlaceholderImage($e['image'])) {
                $name = trim((string) ($e['p']['name'] ?? ''));
                if (mb_strlen($name) >= 6 && str_contains($name, ' ')) {
                    $people[$name] = $e;
                }
            }
        }
        // Images d'archives déjà déposées, utilisables (pas de presse, pas floues).
        $archives = [];
        $state = Catalogue::state()['items'];
        foreach (Catalogue::items() as $md5 => $it) {
            $file = $state[$md5]['file'] ?? null;
            if (!$file || ($state[$md5]['status'] ?? '') === 'ecarte' || in_array($it['rights'] ?? '', ['presse', 'photographe'], true)
                || ($it['quality'] ?? '') === 'faible' || !empty($it['duplicate_of']) || !\App\Data\Media::get($file)) {
                continue;
            }
            $archives[] = $it + ['file' => $file];
        }
        $done = ['recits' => 0, 'photos' => 0];
        foreach (self::all() as $r) {
            $id = self::existing($r['key']);
            $doc = $id ? Fiches::get($id) : null;
            if (!$doc) {
                continue;
            }
            $have = array_column((array) ($doc['gallery'] ?? []), 'image');
            $room = self::MAX_PHOTOS - count($have);
            if ($room <= 0) {
                continue;
            }
            $html = implode(' ', array_column((array) $doc['sections'], 'html')) . ' ' . $doc['intro'];
            $text = html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
            $add = [];
            $push = function (?string $img, string $caption, string $credit) use (&$add, $have) {
                if (!$img || in_array($img, $have, true) || isset($add[$img]) || Index::isPlaceholderImage($img) || self::press($credit . ' ' . $caption)) {
                    return;
                }
                $add[$img] = ['image' => $img, 'caption' => $caption, 'credit' => $credit, 'caption_raw' => $caption . ($credit !== '' ? ' – ' . $credit : '')];
            };
            // 1. Matchs liés dans le texte : photo principale puis galerie.
            preg_match_all('#href="(/matchs/[^"]+)"#', $html, $mm);
            foreach (array_unique($mm[1]) as $path) {
                $e = $byPath[$path] ?? null;
                $m = $e ? Fiches::get((int) $e['id']) : null;
                if (!$m) {
                    continue;
                }
                $label = (string) $m['title'];
                $push($m['featured_image'] ?? null, \App\Data\Media::caption($m['featured_image'] ?? null, $label), (string) (\App\Data\Media::get($m['featured_image'] ?? null)['credit'] ?? ''));
                foreach (array_slice((array) ($m['gallery'] ?? []), 0, 2) as $g) {
                    $push($g['image'] ?? null, (string) (($g['caption'] ?? '') ?: $label), (string) ($g['credit'] ?? ''));
                }
            }
            // 2. Archives de la même époque et du même sujet.
            [$from, $to] = self::span($r);
            $hints = array_map('mb_strtolower', (array) ($r['image_hint'] ?? []));
            $scored = [];
            foreach ($archives as $it) {
                $y = (int) substr((string) ($it['date'] ?? ''), 0, 4) ?: (int) ($it['decade'] ?? 0);
                $hay = mb_strtolower(implode(' ', [(string) $it['title'], (string) $it['caption'], (string) ($it['summary'] ?? ''), implode(' ', (array) ($it['topics'] ?? [])), implode(' ', (array) ($it['persons'] ?? []))]));
                $hit = count(array_filter($hints, fn ($h) => $h !== '' && str_contains($hay, $h)));
                $inTime = $y && $y >= $from - 1 && $y <= $to + 1;
                if ($inTime && ($hit || ($it['type'] ?? '') === 'photo') || (!$y && $hit >= 2)) {
                    $scored[] = [($inTime ? 2 : 0) + $hit + (($it['type'] ?? '') === 'photo' ? 1 : 0), $it];
                }
            }
            usort($scored, fn ($a, $b) => $b[0] <=> $a[0]);
            foreach (array_slice($scored, 0, 4) as [, $it]) {
                $mm2 = \App\Data\Media::get($it['file']);
                $push($it['file'], (string) ($mm2['caption'] ?? $it['caption']), (string) ($mm2['credit'] ?? ''));
            }
            // 3. Portraits des joueurs nommés dans le récit.
            foreach ($people as $name => $e) {
                if (count($add) >= $room + 2) {
                    break;
                }
                if (mb_stripos($text, $name) !== false) {
                    $push($e['image'], $name, (string) (\App\Data\Media::get($e['image'])['credit'] ?? ''));
                }
            }
            $add = array_slice(array_values($add), 0, $room);
            if (!$add) {
                continue;
            }
            $doc['gallery'] = array_merge((array) ($doc['gallery'] ?? []), $add);
            if (empty($doc['featured_image'])) {
                $doc['featured_image'] = $add[0]['image'];
            }
            Fiches::save($doc, $user ?? self::AUTHOR, 'Photos ajoutées au récit (' . count($add) . ')');
            $done['recits']++;
            $done['photos'] += count($add);
        }
        return $done;
    }

    /** Crédit ou légende de presse (agences, journaux nationaux et régionaux) : image écartée des récits. */
    public static function press(string $text): bool
    {
        if (trim($text) === '') {
            return false;
        }
        if (PhotoWall::risky($text) !== null) {
            return true;
        }
        $t = mb_strtolower(Names::ascii($text));
        foreach (['est republicain', 'le pays', 'l equipe', 'lequipe', 'france football', 'miroir', 'onze', 'paris match', 'ouest france', 'getty', 'panoramic', 'icon sport', 'maxppp', 'dppi', 'presse'] as $w) {
            if (str_contains(' ' . preg_replace('/[^a-z0-9]+/', ' ', $t) . ' ', ' ' . $w . ' ')) {
                return true;
            }
        }
        return false;
    }

    /** Années couvertes par un récit, d'après son titre (« … 1952 – 1953 ») ou son année. */
    public static function span(array $r): array
    {
        preg_match_all('/\b(19\d{2}|20\d{2})\b/', (string) $r['title'], $m);
        $ys = array_map('intval', $m[1]) ?: [(int) ($r['year'] ?? 0)];
        return [min($ys), max($ys)];
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
