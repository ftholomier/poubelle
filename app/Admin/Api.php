<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Data\Categories;
use App\Data\Collections as Coll;
use App\Data\Derived;
use App\Data\Fiches as Store;
use App\Data\Index;
use App\Data\Media;
use App\Services\Gemini;
use App\Services\Search;

/** API JSON internes du back-office (recherche globale, auto-complétion, médiathèque). */
final class Api extends Base
{
    /** Recherche globale (Ctrl+K) : fiches, médias, rubriques, écrans. */
    public static function search(Request $req): Response
    {
        $q = Search::norm($req->str('q'));
        $out = [];
        if ($q === '') {
            foreach (array_slice(\App\Data\Activity::recent(40), 0, 40) as $a) {
                if (!empty($a['id']) && ($s = Index::get((int) $a['id'])) && !isset($out[$s['id']])) {
                    $out[$s['id']] = ['kind' => 'Récent', 'title' => $s['title'], 'meta' => Store::TYPES[$s['type']] ?? '', 'url' => '/admin/fiche/' . $s['id'], 'status' => Store::STATUSES[$s['status']] ?? ''];
                }
                if (count($out) >= 8) {
                    break;
                }
            }
            return self::json(['items' => array_values($out)]);
        }
        // Écrans du back-office
        foreach (Base::NAV as $items) {
            foreach ($items as [$k, $label, $href, $adminOnly]) {
                if ((!$adminOnly || Auth::isAdmin()) && str_contains(Search::norm($label), $q)) {
                    $out[] = ['kind' => 'Écran', 'title' => $label, 'meta' => '', 'url' => $href, 'status' => ''];
                }
            }
        }
        // Fiches : titre, puis noms
        $hits = [];
        foreach (Index::all() as $s) {
            $hay = Search::norm($s['title'] . ' ' . ($s['p']['name'] ?? '') . ' ' . ($s['p']['nickname'] ?? ''));
            if ((string) $s['id'] === $q) {
                $hits[$s['id']] = 1000;
            } elseif (str_contains($hay, $q)) {
                $hits[$s['id']] = (str_starts_with($hay, $q) ? 50 : 10) + ($s['type'] === 'personne' ? 5 : 0) - min(9, (int) (strlen($hay) / 40));
            }
        }
        arsort($hits);
        foreach (array_slice(array_keys($hits), 0, 14) as $id) {
            $s = Index::get((int) $id);
            $d = Search::describe($s);
            $out[] = ['kind' => $d['type'], 'title' => $d['label'], 'meta' => $d['meta'], 'url' => '/admin/fiche/' . $s['id'], 'status' => Store::STATUSES[$s['status']] ?? ''];
        }
        // Rubriques
        foreach (Categories::all() as $slug => $c) {
            if (count($out) > 22) {
                break;
            }
            if (empty($c['technical']) && empty($c['season']) && str_contains(Search::norm(Categories::label($slug)), $q)) {
                $out[] = ['kind' => 'Rubrique', 'title' => Categories::label($slug), 'meta' => (string) ($c['path'] ?? ''), 'url' => '/admin/rubriques#' . $slug, 'status' => ''];
            }
        }
        // Médias
        $n = 0;
        foreach (Media::all() as $rel => $m) {
            if ($n >= 6) {
                break;
            }
            if (str_contains(Search::norm($rel . ' ' . ($m['caption'] ?? '') . ' ' . ($m['credit'] ?? '')), $q)) {
                $out[] = ['kind' => 'Média', 'title' => (string) ($m['caption'] ?? '') ?: basename((string) $rel), 'meta' => (string) $rel, 'url' => '/admin/medias?f=' . rawurlencode((string) $rel), 'status' => ''];
                $n++;
            }
        }
        return self::json(['items' => $out]);
    }

    /** Auto-complétion : personnes, clubs, stades, fiches, matchs. */
    public static function autocomplete(Request $req): Response
    {
        $type = $req->str('type');
        $q = Search::norm($req->str('q'));
        if (mb_strlen($q) < 2) {
            return self::json(['items' => []]);
        }
        $items = [];
        switch ($type) {
            case 'personnes':
                foreach (Index::all() as $s) {
                    if ($s['type'] !== 'personne') {
                        continue;
                    }
                    $hay = Search::norm($s['p']['name'] . ' ' . $s['p']['last'] . ' ' . $s['p']['nickname']);
                    if (str_contains($hay, $q)) {
                        $years = $s['p']['arrival'] ? $s['p']['arrival'] . ($s['p']['departure'] && $s['p']['departure'] !== $s['p']['arrival'] ? '–' . $s['p']['departure'] : '') : '';
                        // Valeur insérée dans la composition : NOM Prénom (format des feuilles de match)
                        $value = trim(mb_strtoupper((string) $s['p']['last']) . ' ' . $s['p']['first']);
                        $items[] = ['id' => $s['id'], 'label' => $s['p']['name'], 'meta' => trim(($s['p']['position'] ? $s['p']['position'] . ' · ' : '') . $years), 'value' => $value !== '' ? $value : $s['p']['name'], 'score' => str_starts_with(Search::norm((string) $s['p']['last']), $q) ? 2 : 1];
                    }
                }
                usort($items, fn ($a, $b) => $b['score'] <=> $a['score'] ?: strcmp($a['label'], $b['label']));
                break;
            case 'clubs':
                $seen = [];
                foreach (Derived::get()['clubs'] ?? [] as $key => $c) {
                    $first = Derived::get()['matches'][$c['matches'][0] ?? 0]['opp'] ?? $key;
                    if (str_contains(Search::norm((string) $first), $q)) {
                        $seen[$first] = true;
                        $items[] = ['label' => $first, 'meta' => $c['count'] . ' matchs', 'value' => $first];
                    }
                }
                foreach (Coll::get('clubs', []) as $c) {
                    if (!isset($seen[$c['name']]) && str_contains(Search::norm($c['name']), $q)) {
                        $items[] = ['label' => $c['name'], 'meta' => '', 'value' => $c['name']];
                    }
                }
                break;
            case 'stades':
                foreach (Coll::get('stades', []) as $st) {
                    if (str_contains(Search::norm(($st['name'] ?? '') . ' ' . ($st['city'] ?? '')), $q)) {
                        $items[] = ['label' => $st['name'], 'meta' => (string) ($st['city'] ?? ''), 'value' => $st['name']];
                    }
                }
                if (!$items) {
                    foreach (Derived::get()['stades'] ?? [] as $key => $st) {
                        $name = \App\Front\Explore::stadiumName((string) $key);
                        if (str_contains(Search::norm($name), $q)) {
                            $items[] = ['label' => $name, 'meta' => ($st['count'] ?? 0) . ' matchs', 'value' => $name];
                        }
                    }
                }
                break;
            case 'matchs':
            case 'fiches':
                foreach (Index::all() as $s) {
                    if ($type === 'matchs' && $s['type'] !== 'match') {
                        continue;
                    }
                    if (str_contains(Search::norm($s['title'] . ' ' . ($s['p']['name'] ?? '')), $q)) {
                        $d = Search::describe($s);
                        $items[] = ['id' => $s['id'], 'label' => $d['label'], 'meta' => $d['type'] . ($d['meta'] ? ' · ' . $d['meta'] : ''), 'value' => $s['title'], 'url' => $s['path']];
                    }
                    if (count($items) >= 40) {
                        break;
                    }
                }
                break;
        }
        return self::json(['items' => array_slice($items, 0, 15)]);
    }

    /** Médiathèque paginée pour le sélecteur d'images. */
    public static function media(Request $req): Response
    {
        $q = Search::norm($req->str('q'));
        $page = max(1, (int) $req->str('page', '1'));
        $per = 48;
        $all = Media::all();
        $list = [];
        foreach ($all as $rel => $m) {
            if ($q !== '' && !str_contains(Search::norm($rel . ' ' . ($m['caption'] ?? '') . ' ' . ($m['credit'] ?? '') . ' ' . ($m['alt'] ?? '')), $q)) {
                continue;
            }
            $list[(string) $rel] = $m;
        }
        // Les plus récents d'abord (chemins AAAA/MM/…)
        uksort($list, fn ($a, $b) => strcmp((string) ($list[$b]['added'] ?? $b), (string) ($list[$a]['added'] ?? $a)));
        $slice = array_slice($list, ($page - 1) * $per, $per, true);
        $items = [];
        foreach ($slice as $rel => $m) {
            $items[] = [
                'file' => $rel,
                'name' => basename($rel),
                'caption' => (string) ($m['caption'] ?? ''),
                'thumb' => preg_match('/\.pdf$/i', $rel) ? '/assets/admin/pdf.svg' : img($rel, 320),
                'flag' => trim((string) ($m['credit'] ?? '')) === '' ? 'Sans crédit' : '',
            ];
        }
        return self::json(['items' => $items, 'more' => count($list) > $page * $per, 'total' => count($list)]);
    }

    /** Réglages : recharge la liste des modèles Gemini disponibles pour la clé. */
    public static function models(Request $req): Response
    {
        if (!Auth::can('settings')) {
            return self::json(['error' => 'Réservé aux administrateurs.'], 403);
        }
        $m = Gemini::models(true);
        return self::json(['ok' => empty($m['error']), 'error' => $m['error'] ?? null, 'generate' => $m['generate'], 'embed' => $m['embed']]);
    }

    /** Traduction ponctuelle d'un texte (bouton « Traduire » des écrans de collections). */
    public static function translate(Request $req): Response
    {
        $texts = array_slice(array_filter(array_map('strval', (array) ($req->json()['texts'] ?? []))), 0, 60);
        if (!$texts || !\App\Services\Translator::enabled()) {
            return self::json(['ok' => false, 'error' => 'Traduction indisponible (clé Gemini non réglée).'], 422);
        }
        try {
            return self::json(['ok' => true, 'translations' => \App\Services\Translator::strings($texts)]);
        } catch (\Throwable $e) {
            return self::json(['ok' => false, 'error' => $e->getMessage()], 502);
        }
    }
}
