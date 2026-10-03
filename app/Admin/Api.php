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
use App\Core\RateLimiter;
use App\Data\Activity;
use App\Services\AiCosts;
use App\Services\EditLock;
use App\Services\Gemini;
use App\Services\Proofreader;
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
                        $items[] = ['id' => $s['id'], 'label' => $d['label'], 'meta' => $d['type'] . ($d['meta'] ? ' · ' . $d['meta'] : ''), 'value' => $s['title'], 'url' => $s['path'], 'date' => $s['m']['date'] ?? null];
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
        return self::json(['ok' => empty($m['error']), 'error' => $m['error'] ?? null, 'generate' => $m['generate'], 'embed' => $m['embed'], 'tts' => $m['tts'] ?? []]);
    }

    /** Traduction ponctuelle d'un texte (bouton « Traduire » des écrans de collections). */
    public static function translate(Request $req): Response
    {
        $texts = array_slice(array_filter(array_map('strval', (array) ($req->json()['texts'] ?? []))), 0, 60);
        if (!$texts || !\App\Services\Translator::enabled()) {
            return self::json(['ok' => false, 'error' => 'Traduction indisponible (clé Gemini non réglée).'], 422);
        }
        try {
            return self::json(['ok' => true, 'translations' => \App\Services\Translator::strings($texts), 'cost' => AiCosts::request()]);
        } catch (\Throwable $e) {
            return self::json(['ok' => false, 'error' => $e->getMessage()], 502);
        }
    }

    /**
     * Correcteur d'orthographe : corrections proposées pour les textes de l'écran.
     * Entrée : {scope: "fiche:123", fields: [{k, value, html, lang, kind}]}.
     */
    public static function proofread(Request $req): Response
    {
        $in = $req->json();
        $user = Auth::actor();
        if (!RateLimiter::hit('correcteur', (string) ($user['id'] ?? 'anonyme'), 120, 3600)) {
            return self::json(['ok' => false, 'error' => 'Beaucoup de vérifications en une heure : réessayez dans quelques minutes.'], 429);
        }
        $scope = (string) ($in['scope'] ?? '');
        $scope = Proofreader::validScope($scope) ? $scope : '';
        $fields = [];
        $total = 0;
        foreach (array_slice((array) ($in['fields'] ?? []), 0, 400) as $f) {
            $k = is_array($f) ? (string) ($f['k'] ?? '') : '';
            $v = is_array($f) ? (string) ($f['value'] ?? '') : '';
            if (!preg_match('/^[a-z0-9_-]{1,24}$/i', $k) || trim($v) === '') {
                continue;
            }
            $total += strlen($v);
            if ($total > 800000) {
                break;
            }
            $fields[] = ['k' => $k, 'value' => $v, 'html' => !empty($f['html']), 'lang' => ($f['lang'] ?? '') === 'en' ? 'en' : 'fr', 'kind' => (string) ($f['kind'] ?? 'text')];
        }
        $names = [];
        if (preg_match('/^fiche:(\d+)$/', $scope, $m) && ($doc = Store::get((int) $m[1]))) {
            $names = Proofreader::namesForDoc($doc);
        }
        // Gemini peut prendre quelques dizaines de secondes : la session est libérée pour
        // que l'éditeur reste utilisable pendant ce temps.
        session_write_close();
        @set_time_limit(180);
        $r = Proofreader::check($fields, ['scope' => $scope, 'names' => $names]);
        return self::json(['ok' => true, 'gemini' => Gemini::ready(), 'cost' => AiCosts::request()] + $r);
    }

    /** Correcteur : « Ignorer » (la correction n'est plus proposée pour cette fiche ou cet écran). */
    public static function proofIgnore(Request $req): Response
    {
        $in = $req->json();
        $scope = (string) ($in['scope'] ?? '');
        if (!Proofreader::validScope($scope)) {
            return self::json(['ok' => false, 'error' => 'Écran inconnu.'], 422);
        }
        Proofreader::ignore($scope, (string) ($in['sig'] ?? ''));
        return self::json(['ok' => true]);
    }

    /** Correcteur : « Ajouter au dictionnaire » (le mot n'est plus jamais corrigé). */
    /**
     * Verrou de modification (App\Services\EditLock) : l'éditeur ouvert signale sa présence
     * toutes les 30 secondes, observe une fiche tenue par quelqu'un d'autre, prend la main, ou la
     * libère en partant (navigator.sendBeacon : formulaire avec _csrf).
     * Entrée : {key, mode: hold|watch|take|release, tab, idle (secondes), modified (version chargée)}.
     */
    public static function lock(Request $req): Response
    {
        $in = $req->json() ?: $req->post;
        $key = (string) ($in['key'] ?? '');
        $mode = (string) ($in['mode'] ?? 'hold');
        $user = Auth::actor();
        if (!$user || !EditLock::validKey($key) || !in_array($mode, ['hold', 'watch', 'take', 'release'], true)) {
            return self::json(['ok' => false, 'error' => 'Demande invalide.'], 400);
        }
        $r = EditLock::ping($key, ['id' => (string) $user['id'], 'name' => (string) $user['name']], $mode, (string) ($in['tab'] ?? ''), (int) ($in['idle'] ?? 0));
        $doc = preg_match('/^fiche:(\d+)$/', $key, $m) ? Store::get((int) $m[1]) : null;
        if ($r['took'] !== null) {
            Activity::log($user, 'a pris la main (modification en cours par ' . $r['took'] . ')', $doc ?: ['title' => $key]);
        }
        $out = [
            'ok' => true, 'mine' => $r['mine'],
            'holder' => $r['holder'] ? self::lockInfo($r['holder']) : null,
            'taken' => $r['taken'] ? ['by' => $r['taken']['by'], 'at' => date('G \h i', (int) $r['taken']['at'])] : null,
        ];
        if ($doc) {
            // Une nouvelle version a-t-elle été enregistrée depuis l'ouverture de l'éditeur ?
            $out['modified'] = (string) ($doc['modified'] ?? '');
            $seen = (string) ($in['modified'] ?? '');
            if ($seen !== '' && $seen !== $out['modified']) {
                $last = Store::versions((int) $doc['id'])[0] ?? null;
                $out['saved'] = ['by' => (string) ($last['by'] ?? 'quelqu’un'), 'at' => date('G \h i', strtotime((string) $doc['modified']) ?: time())];
            }
        }
        return self::json($out);
    }

    public static function proofWord(Request $req): Response
    {
        $word = (string) ($req->json()['mot'] ?? '');
        if (!Proofreader::addWord($word, Auth::actor())) {
            return self::json(['ok' => false, 'error' => 'Mot invalide.'], 422);
        }
        return self::json(['ok' => true, 'message' => '« ' . trim($word) . ' » ajouté au dictionnaire du musée.']);
    }
}
