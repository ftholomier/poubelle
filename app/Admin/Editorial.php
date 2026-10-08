<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\JsonStore;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Data\Activity;
use App\Data\Categories;
use App\Data\Collections;
use App\Data\Fiches;
use App\Data\Index;
use App\Data\Redirects;
use App\Front\Interactive;
use App\Front\Pages;
use App\Front\Site;
use App\Services\Moments;
use App\Services\Search;

/**
 * Éditorial : page d'accueil (slider « À la une », bandeau défilant, introduction,
 * compteurs, palmarès, grandes époques, réserves, vignettes), calendrier des
 * 100 moments, rubriques et méga-menus (libellés FR/EN, descriptions, ordre des
 * fiches dans les mosaïques), redirections 301 et adresses introuvables.
 */
final class Editorial extends Base
{
    // ------------------------------------------------------------------ accueil

    public static function home(Request $req): Response
    {
        $slider = Collections::get('slider', ['mode' => 'random', 'ids' => []]);
        $manual = [];
        foreach ($slider['ids'] ?? [] as $id) {
            if ($s = Index::get((int) $id)) {
                $m = $s['image'] ? \App\Data\Media::get($s['image']) : null;
                $manual[] = ['id' => (int) $id, 'title' => $s['title'], 'image' => $s['image'], 'status' => $s['status'],
                    // Photo trop petite pour le plein écran : bulle avec sa taille.
                    'small' => !$s['image'] || Pages::slideReady($s['image']) ? ''
                        : (empty($m['width']) ? 'Taille inconnue (photo absente de la médiathèque)' : 'Photo de ' . (int) $m['width'] . ' × ' . (int) ($m['height'] ?? 0) . ' pixels : floue en plein écran')];
            }
        }
        // Tirage au hasard : fiches « À la une » dont la photo est assez grande ; les autres sont comptées à part.
        $withPhoto = count(array_filter(Index::published(), fn ($s) => $s['image'] && !Index::isPlaceholderImage($s['image'])));
        $pool = Pages::slidePool();
        $poolSmall = max(0, $withPhoto - count($pool));
        $poolNoImage = count(array_filter(Index::published(), fn ($s) => (!$s['image'] || Index::isPlaceholderImage($s['image']))));
        $ticker = Collections::get('ticker', ['auto' => ['jour' => true, 'centenaire' => true, 'dernier' => true], 'messages' => Site::defaultTickerMessages()]);
        $home = [];
        foreach (Settings::schema()['home']['fields'] as $k => $f) {
            $home[$k] = Settings::get("home.$k");
        }
        return self::html('admin/editorial/home', [
            'slider' => $slider, 'manual' => $manual, 'pool' => count($pool), 'poolSmall' => $poolSmall, 'poolNoImage' => $poolNoImage, 'slideMin' => Pages::$slideMin,
            'ticker' => $ticker, 'home' => $home, 'schema' => Settings::schema()['home']['fields'],
            'palmares' => Collections::get('palmares', Pages::defaultPalmares()),
            'eras' => Collections::get('epoques', Pages::defaultEras()),
            'reserves' => Collections::get('reserves', Pages::defaultReserves()),
            'teasers' => Collections::get('teasers', Pages::defaultTeasers()),
            'legends' => count(array_filter(Index::published('personne'), fn ($s) => $s['p']['legend'])),
            'figure' => \App\Services\Chiffres::daily(),
        ], ['title' => 'Accueil & bandeau', 'crumb' => 'Éditorial', 'nav' => 'accueil']);
    }

    public static function homeSave(Request $req): Response
    {
        if ($locked = self::lockedJson('ecran:accueil', 'l’accueil')) {
            return $locked;
        }
        $in = $req->json();
        $user = self::actor();
        $line = fn ($v, int $max = 200) => Html::line($v, $max);
        $img = fn ($v) => ($r = Html::line($v, 300)) !== '' && \App\Data\Media::get($r) ? $r : null;
        $href = function ($v): ?string {
            $v = trim((string) $v);
            return $v === '' ? null : (preg_match('#^(/|https?://)#', $v) ? mb_substr($v, 0, 300) : '/' . ltrim(mb_substr($v, 0, 300), '/'));
        };
        $done = [];
        if (isset($in['slider'])) {
            $ids = array_values(array_unique(array_filter(array_map(fn ($x) => (int) (is_array($x) ? ($x['id'] ?? 0) : $x), (array) ($in['slider']['ids'] ?? [])), fn ($id) => $id > 0 && Index::get($id))));
            Collections::save('slider', ['mode' => ($in['slider']['mode'] ?? '') === 'manual' ? 'manual' : 'random', 'ids' => $ids], $user, 'Slider de l’accueil');
            $done[] = 'slider';
        }
        if (isset($in['ticker'])) {
            $t = $in['ticker'];
            $msgs = [];
            foreach ((array) ($t['messages'] ?? []) as $m) {
                $k = $line($m['k'] ?? '', 40);
                $v = $line($m['v'] ?? '', 160);
                if ($v === '') {
                    continue;
                }
                $msgs[] = ['k' => $k, 'v' => $v, 'k_en' => $line($m['k_en'] ?? '', 40), 'v_en' => $line($m['v_en'] ?? '', 160), 'href' => $href($m['href'] ?? '') ?? '/', 'on' => !empty($m['on'])];
            }
            Collections::save('ticker', ['auto' => ['jour' => !empty($t['auto']['jour']), 'centenaire' => !empty($t['auto']['centenaire']), 'dernier' => !empty($t['auto']['dernier']), 'retro' => !empty($t['auto']['retro'])], 'messages' => $msgs], $user, 'Bandeau défilant');
            $done[] = 'bandeau';
        }
        if (isset($in['home']) && is_array($in['home'])) {
            $schema = Settings::schema()['home']['fields'];
            $changes = [];
            foreach ($schema as $k => $f) {
                if (!array_key_exists($k, $in['home'])) {
                    continue;
                }
                $v = $in['home'][$k];
                $changes["home.$k"] = match ($f['type']) {
                    'wysiwyg' => Html::clean((string) $v),
                    'number' => is_numeric($v) ? (int) $v : ($f['default'] ?? 0),
                    'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) ? (string) $v : ($f['default'] ?? ''),
                    'bool' => (bool) $v,
                    default => $line($v, 300),
                };
            }
            Settings::save($changes);
            Activity::log($user, 'a modifié', ['title' => 'les textes de l’accueil']);
            $done[] = 'textes';
        }
        if (isset($in['palmares'])) {
            $rows = [];
            foreach ((array) $in['palmares'] as $p) {
                if (($t = $line($p['title'] ?? '', 80)) !== '') {
                    $rows[] = ['years' => $line($p['years'] ?? '', 20), 'title' => $t, 'title_en' => $line($p['title_en'] ?? '', 80)];
                }
            }
            Collections::save('palmares', $rows, $user, 'Palmarès de l’accueil');
            $done[] = 'palmarès';
        }
        if (isset($in['eras'])) {
            $rows = [];
            foreach ((array) $in['eras'] as $e) {
                if (($n = $line($e['name'] ?? '', 80)) === '') {
                    continue;
                }
                $facts = [];
                foreach ((array) ($e['facts'] ?? []) as $f) {
                    if (($ft = $line($f['t'] ?? '', 120)) !== '') {
                        $facts[] = ['y' => $line($f['y'] ?? '', 12), 't' => $ft, 't_en' => $line($f['t_en'] ?? '', 120)];
                    }
                }
                $rows[] = ['range' => $line($e['range'] ?? '', 20), 'name' => $n, 'name_en' => $line($e['name_en'] ?? '', 80),
                    'text' => Html::clean(mb_substr((string) ($e['text'] ?? ''), 0, 3000)), 'text_en' => Html::clean(mb_substr((string) ($e['text_en'] ?? ''), 0, 3000)), 'image' => $img($e['image'] ?? ''), 'href' => $href($e['href'] ?? ''), 'facts' => $facts];
            }
            Collections::save('epoques', $rows, $user, 'Grandes époques');
            $done[] = 'époques';
        }
        if (isset($in['reserves'])) {
            $rows = [];
            foreach ((array) $in['reserves'] as $r) {
                $slug = \App\Data\Paths::slug((string) ($r['slug'] ?? '') ?: (string) ($r['name'] ?? ''), 40);
                if ($slug === '' || ($n = $line($r['name'] ?? '', 40)) === '') {
                    continue;
                }
                $rows[] = ['slug' => $slug, 'name' => $n, 'name_en' => $line($r['name_en'] ?? '', 40), 'desc' => $line($r['desc'] ?? '', 80), 'desc_en' => $line($r['desc_en'] ?? '', 80), 'image' => $img($r['image'] ?? '')];
            }
            Collections::save('reserves', $rows, $user, 'Réserves du musée');
            $done[] = 'réserves';
        }
        if (isset($in['teasers'])) {
            $rows = [];
            foreach (['quiz', 'maillots', 'frise', 'contribuer'] as $k) {
                $rows[$k] = $img($in['teasers'][$k] ?? '');
            }
            Collections::save('teasers', array_filter($rows), $user, 'Vignettes de l’accueil');
            $done[] = 'vignettes';
        }
        return self::json(['ok' => true, 'message' => $done ? 'Accueil enregistré (' . implode(', ', $done) . ').' : 'Rien à enregistrer.', 'modified' => date('c'), 'savedLabel' => 'Enregistré à ' . date('H:i')]);
    }

    // ------------------------------------------------------------------ 100 moments

    /**
     * Calendrier des 100 moments : moments datés dans l'ordre de parution (numéro, date
     * modifiable tant qu'ils ne sont pas en ligne), moments à dater (à relire, brouillons) avec
     * la date anniversaire proposée, alertes et rythme à tenir jusqu'au centenaire.
     */
    public static function moments(Request $req): Response
    {
        $rows = Moments::all();
        $taken = array_values(array_filter(array_map(fn ($r) => $r['date'] ? substr($r['date'], 0, 10) : null, $rows)));
        foreach ($rows as &$r) {
            $r['suggest'] = $r['date'] === null ? Moments::anniversary($r['event'], $taken) : null;
        }
        unset($r);
        $dated = array_values(array_filter($rows, fn ($r) => $r['date'] !== null));
        $stats = [
            'online' => count(array_filter($dated, fn ($r) => $r['visible'])),
            'planned' => count(array_filter($dated, fn ($r) => !$r['visible'])),
            'review' => count(array_filter($rows, fn ($r) => $r['status'] === 'relire')),
            'drafts' => count(array_filter($rows, fn ($r) => $r['status'] === 'brouillon')),
            'ideas' => count(array_filter(\App\Services\MomentIdeas::all(), fn ($i) => $i['state'] === 'retenue')),
        ];
        return self::html('admin/editorial/moments', [
            'dated' => $dated,
            'undated' => array_values(array_filter($rows, fn ($r) => $r['date'] === null)),
            'stats' => $stats,
            'alerts' => Moments::alerts($rows),
            'pace' => Moments::pace($rows),
            'end' => Moments::end(),
        ], ['title' => '100 moments du centenaire', 'crumb' => 'Éditorial', 'nav' => 'moments']);
    }

    /** POST /admin/moments/date : nouvelle date de parution d'un moment validé, pas encore en ligne. */
    public static function momentsDate(Request $req): Response
    {
        $id = (int) $req->str('id');
        $doc = $id ? Fiches::get($id) : null;
        if (!$doc || $doc['type'] !== 'moment' || $doc['status'] !== 'planifie') {
            return self::back('/admin/moments', null, 'Seul un moment planifié change de date ici ; pour les autres, ouvrez la fiche.');
        }
        if (Fiches::isVisible($doc)) {
            return self::back('/admin/moments', null, 'Ce moment est déjà en ligne : sa date ne change plus.');
        }
        if ($m = self::lockMessage("fiche:$id")) {
            return self::back('/admin/moments', null, $m);
        }
        $day = $req->str('date');
        $time = preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $req->str('heure')) ? $req->str('heure') : Moments::HOUR;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) || !strtotime("$day $time")) {
            return self::back('/admin/moments', null, 'Date invalide.');
        }
        $before = $doc;
        $doc['publish_at'] = date('c', (int) strtotime("$day $time"));
        if ($err = Moments::check($doc, $before)) {
            return self::back('/admin/moments', null, $err);
        }
        if ($doc['publish_at'] === $before['publish_at']) {
            return self::back('/admin/moments', 'Aucun changement.');
        }
        Fiches::save($doc, self::actor(), 'Date de parution : ' . date_fr($day) . ' à ' . $time);
        Moments::renumber();
        $n = (int) (Fiches::get($id)['moment']['number'] ?? 0);
        return self::back('/admin/moments#m-' . $id, '« ' . $doc['title'] . ' » paraîtra le ' . date_fr($day) . ' à ' . $time . ($n ? ' (n° ' . $n . ').' : '.'));
    }

    // ------------------------------------------------------------------ rubriques et menus

    public static function categories(Request $req): Response
    {
        $all = Categories::all();
        $slug = $req->str('rubrique');
        if ($slug !== '' && isset($all[$slug])) {
            $cat = $all[$slug] + ['slug' => $slug];
            $items = Index::inCategory($slug);
            $inOrder = array_flip(array_map('intval', $cat['order'] ?? []));
            $rows = [];
            $unordered = 0;
            foreach ($items as $s) {
                $m = $s['m'] ?? null;
                $date = (string) ($m['date'] ?? $s['date'] ?? '');
                $meta = $m
                    ? trim(date_num(substr($date, 0, 10)) . ' · ' . ($m['competition'] ?? '') . (!empty($m['season']) ? ' · ' . $m['season'] : ''), ' ·')
                    : (Fiches::TYPES[$s['type']] ?? $s['type']) . ($date !== '' ? ' · ' . date_num(substr($date, 0, 10)) : '');
                $ordered = !$inOrder || isset($inOrder[(int) $s['id']]);
                $unordered += $ordered ? 0 : 1;
                $rows[] = [
                    'id' => (int) $s['id'], 'title' => $s['title'], 'image' => $s['image'], 'status' => $s['status'], 'type' => $s['type'],
                    'date' => $date, 'sort' => $s['type'] === 'personne' ? Index::sortName($s) : \App\Data\Names::ascii($s['title']),
                    'meta' => $meta, 'ordered' => $ordered,
                ];
            }
            return self::html('admin/editorial/category', [
                'cat' => $cat, 'rows' => $rows, 'total' => count($items), 'trail' => Categories::trail($slug), 'children' => Categories::children($slug),
                'sort' => Categories::displaySort($slug), 'unordered' => $unordered,
            ], ['title' => Categories::label($slug), 'crumb_html' => 'Éditorial › <a href="/admin/rubriques">Rubriques & menus</a>', 'nav' => 'rubriques']);
        }
        $tree = [];
        $walk = function (string $s, int $depth) use (&$walk, &$tree, $all) {
            if (!isset($all[$s])) {
                return;
            }
            $tree[] = ['slug' => $s, 'depth' => $depth, 'count' => Site::count($s)] + $all[$s];
            $kids = array_filter($all, fn ($c) => ($c['parent'] ?? null) === $s);
            uasort($kids, fn ($a, $b) => ($a['position'] ?? 999) <=> ($b['position'] ?? 999) ?: strcoll((string) ($a['season'] ?? $a['name']), (string) ($b['season'] ?? $b['name'])));
            foreach (array_keys($kids) as $k) {
                $walk((string) $k, $depth + 1);
            }
        };
        foreach (Categories::ROOTS as $r) {
            $walk($r, 0);
        }
        $seen = array_column($tree, 'slug');
        $others = array_values(array_filter(array_keys($all), fn ($s) => !in_array($s, $seen, true) && empty($all[$s]['parent'])));
        foreach ($others as $o) {
            $walk((string) $o, 0);
        }
        return self::html('admin/editorial/categories', ['tree' => $tree], ['title' => 'Rubriques & menus', 'crumb' => 'Éditorial', 'nav' => 'rubriques']);
    }

    public static function categoriesSave(Request $req): Response
    {
        $in = $req->json();
        $slug = (string) ($in['slug'] ?? '');
        $all = Categories::all();
        if (!isset($all[$slug])) {
            return self::json(['error' => 'Rubrique introuvable.'], 404);
        }
        if ($locked = self::lockedJson(self::lockKey('ecran:rubrique-', $slug), 'cette rubrique')) {
            return $locked;
        }
        $c = $all[$slug];
        $c['label'] = Html::line($in['label'] ?? '', 80) ?: null;
        $c['label_en'] = Html::line($in['label_en'] ?? '', 80) ?: null;
        $c['description'] = Html::clean((string) ($in['description'] ?? ''));
        $c['description_en'] = Html::clean((string) ($in['description_en'] ?? ''));
        $c['position'] = isset($in['position']) && $in['position'] !== '' && $in['position'] !== null ? (int) $in['position'] : null;
        $c['technical'] = !empty($in['technical']);
        $sort = (string) ($in['sort'] ?? '');
        if (isset(Categories::SORTS[$sort])) {
            $c['sort'] = $sort;
        }
        // L'ordre manuel n'est remplacé que s'il est l'ordre choisi (on garde le classement existant sinon).
        if (array_key_exists('order', $in) && $sort === 'selection') {
            $c['order'] = array_values(array_unique(array_filter(array_map(fn ($x) => (int) (is_array($x) ? ($x['id'] ?? 0) : $x), (array) $in['order']))));
        }
        $all[$slug] = $c;
        // Ordre des sous-rubriques (glisser-déposer) : positions 1, 2, 3…
        if (!empty($in['children_order']) && is_array($in['children_order'])) {
            $pos = 0;
            foreach ($in['children_order'] as $row) {
                $child = (string) (is_array($row) ? ($row['slug'] ?? '') : $row);
                if (isset($all[$child]) && ($all[$child]['parent'] ?? null) === $slug) {
                    $all[$child]['position'] = ++$pos;
                }
            }
        }
        Categories::save($all, self::actor());
        \App\Data\Derived::markDirty();
        return self::json(['ok' => true, 'message' => 'Rubrique enregistrée.', 'modified' => date('c'), 'savedLabel' => 'Enregistré à ' . date('H:i')]);
    }

    // ------------------------------------------------------------------ redirections

    public static function redirects(Request $req): Response
    {
        Redirects::merge404();
        $tab = $req->str('onglet') === 'introuvables' ? 'introuvables' : 'redirections';
        $q = mb_strtolower($req->str('q'));
        $vars = ['tab' => $tab, 'q' => $req->str('q')];
        if ($tab === 'introuvables') {
            $log = JsonStore::read(Redirects::LOG404, []) ?: [];
            $known = Redirects::all();
            $rows = [];
            foreach ($log as $path => $e) {
                if (isset($known[$path]) || ($q !== '' && !str_contains(mb_strtolower((string) $path), $q))) {
                    continue;
                }
                $rows[] = ['path' => (string) $path] + $e + ['suggest' => self::suggest((string) $path)];
            }
            usort($rows, fn ($a, $b) => $b['n'] <=> $a['n'] ?: strcmp((string) $b['last'], (string) $a['last']));
            $vars['rows'] = array_slice($rows, 0, 200);
            $vars['total'] = count($rows);
        } else {
            $all = Redirects::all();
            $rows = [];
            foreach ($all as $from => $to) {
                if ($q !== '' && !str_contains(mb_strtolower($from . ' ' . $to), $q)) {
                    continue;
                }
                $rows[] = ['from' => (string) $from, 'to' => (string) $to];
            }
            $total = count($rows);
            $pages = max(1, (int) ceil($total / 100));
            $page = min($pages, max(1, (int) $req->str('page', '1')));
            $vars += ['rows' => array_slice($rows, ($page - 1) * 100, 100), 'total' => $total, 'page' => $page, 'pages' => $pages, 'all' => count($all)];
        }
        $n404 = count(JsonStore::read(Redirects::LOG404, []) ?: []);
        return self::html('admin/editorial/redirects', $vars, ['title' => 'Redirections', 'crumb' => 'Éditorial', 'nav' => 'redirections',
            'tabs' => [['Redirections 301', '/admin/redirections', $tab === 'redirections'], ['Adresses introuvables', '/admin/redirections?onglet=introuvables', $tab === 'introuvables', $n404 ?: '']]]);
    }

    /** Fiche la plus proche d'une adresse introuvable (dernier segment de l'adresse). */
    private static function suggest(string $path): ?array
    {
        $seg = Search::norm(str_replace(['-', '_'], ' ', basename(rtrim(parse_url($path, PHP_URL_PATH) ?: '', '/'))));
        if (mb_strlen($seg) < 4) {
            return null;
        }
        $best = null;
        $bestScore = 0;
        $words = array_filter(explode(' ', $seg), fn ($w) => mb_strlen($w) > 2);
        if (!$words) {
            return null;
        }
        foreach (Index::published() as $s) {
            $hay = Search::norm($s['title'] . ' ' . str_replace('-', ' ', basename(rtrim((string) $s['path'], '/'))));
            $score = 0;
            foreach ($words as $w) {
                if (str_contains($hay, $w)) {
                    $score++;
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $s;
            }
        }
        return $best && $bestScore >= max(1, (int) ceil(count($words) * 0.6)) ? ['path' => $best['path'], 'title' => $best['title']] : null;
    }

    public static function redirectsSave(Request $req): Response
    {
        $action = (string) ($req->post['action'] ?? 'ajouter');
        $back = (string) ($req->post['back'] ?? '/admin/redirections');
        $back = str_starts_with($back, '/admin/redirections') ? $back : '/admin/redirections';
        $norm = function (string $p): string {
            $p = trim($p);
            if (preg_match('#^https?://[^/]+(/.*)?$#i', $p, $m)) {
                $host = (string) parse_url($p, PHP_URL_HOST);
                $own = (string) parse_url(base_url(), PHP_URL_HOST);
                if ($host === $own || str_ends_with($host, 'fcsochauxretro.com')) {
                    $p = $m[1] ?? '/';
                } else {
                    return $p; // destination externe
                }
            }
            return '/' . ltrim($p, '/');
        };
        if ($action === 'supprimer') {
            $from = (string) ($req->post['from'] ?? '');
            Redirects::remove($from);
            Activity::log(self::actor(), 'a supprimé la redirection', ['title' => $from]);
            return self::back($back, 'Redirection supprimée.');
        }
        if ($action === 'ignorer') {
            $path = (string) ($req->post['from'] ?? '');
            JsonStore::update(Redirects::LOG404, function ($all) use ($path) {
                unset($all[$path]);
                return $all ?: [];
            }, []);
            return self::back($back, 'Adresse retirée de la liste.');
        }
        if ($action === 'vider') {
            JsonStore::write(Redirects::LOG404, []);
            return self::back($back, 'Liste des adresses introuvables vidée.');
        }
        $from = $norm((string) ($req->post['from'] ?? ''));
        $to = $norm((string) ($req->post['to'] ?? ''));
        if ($from === '/' || $from === '' || $to === '' || preg_match('#^https?://#', $from)) {
            return self::back($back, null, 'Indiquez une ancienne adresse du site (ex. /2015/03/mon-article/) et sa nouvelle destination.');
        }
        if ($from === $to) {
            return self::back($back, null, 'L’adresse de départ et la destination sont identiques.');
        }
        // Adresses du site seulement : ni « .. » ni caractère invisible (jamais un chemin de fichier).
        foreach ([$from, $to] as $p) {
            if (preg_match('#[\x00-\x1F\x7F\\\\]|(^|/)\.\.?(/|$)#', ($u = parse_url($p, PHP_URL_PATH)) ? (string) $u : $p)) {
                return self::back($back, null, 'Adresse refusée (« .. » ou caractère invisible) : indiquez une adresse du site, par exemple /2015/03/mon-article/.');
            }
        }
        if (Index::byPath($from)) {
            return self::back($back, null, "L’adresse $from correspond à une fiche existante : une redirection la rendrait inaccessible.");
        }
        Redirects::add($from, $to);
        JsonStore::update(Redirects::LOG404, function ($all) use ($from) {
            unset($all[$from], $all[rtrim($from, '/')]);
            return $all ?: [];
        }, []);
        Activity::log(self::actor(), 'a créé la redirection', ['title' => "$from → $to"]);
        return self::back($back, "Redirection créée : $from → $to");
    }
}
