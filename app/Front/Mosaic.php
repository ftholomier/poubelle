<?php
declare(strict_types=1);

namespace App\Front;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Data\Categories;
use App\Data\Index;
use App\Data\Names;

/**
 * Pages de rubrique en mosaïque (maquette « Mosaïque ») : onglets de sous-rubriques,
 * recherche, tris, vue mosaïque / liste, filtres (compétitions, décennies, saisons, postes),
 * pagination « Afficher plus » (fragment HTML chargé par mosaic.js).
 */
final class Mosaic
{
    public const PER_PAGE = 24;

    /** Compétitions : clé d'URL => [libellé, rubrique WordPress, famille dans les fiches]. */
    public const COMPS = [
        'championnat' => ['Championnat', null, 'Championnat'],
        'coupe-de-france' => ['Coupe de France', 'coupe-de-france', 'Coupe de France'],
        'coupe-de-la-ligue' => ['Coupe de la Ligue', 'coupe-de-la-ligue', 'Coupe de la Ligue'],
        'coupe-d-europe' => ["Coupe d'Europe", 'coupe-deurope', "Coupe d'Europe"],
        'barrages' => ['Barrages', 'barrages', 'Barrages'],
        'coupe-charles-drago' => ['Coupe Charles Drago', 'coupe-charles-drago', 'Coupe Charles Drago'],
        'coupes-diverses' => ['Coupes diverses', 'coupes-diverses', 'Coupes diverses'],
        'coupe-d-ete' => ["Coupe d'été", 'coupe-dete', "Coupe d'été"],
        'amical' => ['Amical', 'amical', 'Amical'],
    ];

    private const ROOTS = [
        Site::C_MATCHS => ['eyebrow' => 'Les archives des rencontres', 'unit' => 'matchs archivés', 'intro' => 'Fiches, résumés, compositions et photos de chaque rencontre retrouvée.', 'ph' => 'Adversaire, stade, saison…'],
        Site::C_LIONS => ['eyebrow' => 'Joueurs, entraîneurs, dirigeants', 'unit' => 'portraits', 'intro' => "Ceux qui ont porté le lion sur le cœur, des pionniers aux Lionceaux d'aujourd'hui.", 'ph' => "Nom d'un joueur, d'un entraîneur…"],
        'supporters' => ['eyebrow' => 'La tribune', 'unit' => 'souvenirs', 'intro' => 'Livres, films, magazines, associations : la mémoire de ceux qui font vivre le lion.', 'ph' => 'Un livre, un film, un groupe…'],
        'infrastructures' => ['eyebrow' => 'Les lieux du club', 'unit' => 'lieux', 'intro' => "Stades, pelouse et centre de formation : là où l'histoire s'est écrite.", 'ph' => 'Un stade, une époque…'],
        'symboles' => ['eyebrow' => 'Ce qui nous identifie', 'unit' => 'pièces', 'intro' => 'Écussons, maillots, hymnes, couleurs : tout ce qui fait le jaune et le bleu.', 'ph' => 'Un logo, un hymne…'],
    ];

    public static function show(Request $req, array $cat): Response
    {
        $slug = $cat['slug'];
        $root = Categories::root($slug);
        $kind = match ($root) {
            Site::C_MATCHS => 'matchs',
            Site::C_LIONS => 'lions',
            default => 'articles',
        };
        $conf = self::ROOTS[$root] ?? self::ROOTS['symboles'];
        $base = (string) $cat['path'];

        // ------------------------------------------------------------ filtres
        $q = trim(mb_substr($req->str('q'), 0, 80));
        $sort = in_array($req->str('tri'), ['recent', 'ancien', 'az', 'selection'], true) ? $req->str('tri') : null;
        $view = $req->str('vue') === 'liste' ? 'list' : 'grid';
        $page = max(1, (int) $req->str('page', '1'));
        $state = ['q' => $q, 'tri' => $sort, 'vue' => $view === 'list' ? 'liste' : null];

        $items = Index::inCategory($slug);
        $hasOrder = !empty($cat['order']);
        $panel = null;
        $chips = [];
        $catComp = null;
        $catDecade = null;

        $extras = [];
        if ($kind === 'matchs') {
            // Les autres fiches de la rubrique (bilans de saison…) restent visibles au-dessus des matchs.
            $extras = array_values(array_filter($items, fn ($s) => $s['type'] !== 'match'));
            $items = array_values(array_filter($items, fn ($s) => $s['type'] === 'match'));
            foreach (self::COMPS as $k => [$label, $cslug]) {
                if ($cslug === $slug) {
                    $catComp = $k;
                }
            }
            if (preg_match('/^annees-(\d+)/', $slug, $mm)) {
                $catDecade = strlen($mm[1]) === 2 ? (int) ('19' . $mm[1]) : (int) $mm[1];
            }
            $comp = $catComp ?? (isset(self::COMPS[$req->str('f')]) ? $req->str('f') : null);
            $decade = $catDecade ?? (preg_match('/^(19|20)\d0$/', $req->str('decennie')) ? (int) $req->str('decennie') : null);
            $season = preg_match('/^\d{4}-\d{4}$/', $req->str('saison')) ? $req->str('saison') : null;
            if (!$catComp && $comp) {
                $state['f'] = $comp;
            }
            if (!$catDecade && $decade) {
                $state['decennie'] = (string) $decade;
            }
            if ($season) {
                $state['saison'] = $season;
                $extras = array_values(array_filter($extras, fn ($s) => ($s['a']['season'] ?? null) === $season));
            }
            usort($extras, fn ($a, $b) => strcmp((string) ($b['a']['season'] ?? $b['date']), (string) ($a['a']['season'] ?? $a['date'])));

            $all = $items;
            $byComp = $comp ? array_values(array_filter($all, fn ($s) => ($s['m']['competition'] ?? '') === self::COMPS[$comp][2])) : $all;
            $byDecade = $decade ? array_values(array_filter($byComp, fn ($s) => self::decadeOf($s) === $decade)) : $byComp;
            $items = $season ? array_values(array_filter($byDecade, fn ($s) => ($s['m']['season'] ?? '') === $season)) : $byDecade;

            // Pastilles des compétitions (lien vers la rubrique d'origine quand elle existe)
            $decQ = $decade && !$catDecade ? ['decennie' => (string) $decade] : [];
            $decadeCat = $decade ? self::decadeCategory($decade) : null;
            $chips[] = ['label' => t('Toutes'), 'on' => !$comp, 'href' => $decadeCat ? url((string) $decadeCat['path']) : url('/matchs/') . self::qs($decade && !$decadeCat ? ['decennie' => (string) $decade] : [])];
            foreach (self::COMPS as $k => [$label, $cslug]) {
                $n = count(array_filter($decade ? array_filter($all, fn ($s) => self::decadeOf($s) === $decade) : $all, fn ($s) => ($s['m']['competition'] ?? '') === self::COMPS[$k][2]));
                if ($n === 0 && $k !== $comp) {
                    continue;
                }
                $c = $cslug ? Categories::get($cslug) : null;
                $href = $c ? url((string) $c['path']) . self::qs($decade ? ['decennie' => (string) $decade] : []) : url('/matchs/') . self::qs(['f' => $k] + ($decade ? ['decennie' => (string) $decade] : []));
                $chips[] = ['label' => t($label), 'on' => $comp === $k, 'href' => $href, 'count' => $n];
            }

            // Décennies + saisons
            $compBase = $comp ? (self::COMPS[$comp][1] && ($cc = Categories::get(self::COMPS[$comp][1])) ? url((string) $cc['path']) : url('/matchs/')) : null;
            $tiles = [];
            foreach (Categories::children(Site::C_MATCHS) as $c) {
                if (!preg_match('/^annees-(\d+)/', $c['slug'], $dm)) {
                    continue;
                }
                $d = strlen($dm[1]) === 2 ? (int) ('19' . $dm[1]) : (int) $dm[1];
                $n = count(array_filter($byComp, fn ($s) => self::decadeOf($s) === $d));
                $href = $comp ? $compBase . self::qs(($comp && !self::COMPS[$comp][1] ? ['f' => $comp] : []) + ['decennie' => (string) $d]) : url((string) $c['path']);
                $tiles[$d] = ['label' => $d < 2000 ? "'" . substr((string) $d, 2) : (string) $d, 'full' => (string) $d, 'on' => $decade === $d, 'href' => $href, 'count' => $n];
            }
            ksort($tiles);
            $seasons = [];
            if ($decade) {
                foreach ($byDecade as $s) {
                    $se = $s['m']['season'] ?? null;
                    if ($se) {
                        $seasons[$se] = ($seasons[$se] ?? 0) + 1;
                    }
                }
                ksort($seasons);
            }
            $here = $catComp || $catDecade ? url($base) : url('/matchs/');
            $keep = array_filter(['f' => !$catComp ? $comp : null, 'decennie' => !$catDecade && $decade ? (string) $decade : null]);
            $panel = [
                'tiles' => array_values($tiles),
                'allDecades' => $decade && !$catDecade ? ($comp ? $compBase . self::qs(!self::COMPS[$comp][1] ? ['f' => $comp] : []) : url('/matchs/')) : ($catDecade ? ($comp ? $compBase : url('/matchs/')) : null),
                'decadeName' => $decade ? ($decade < 2000 ? t('Années') . ' ' . substr((string) $decade, 2) : t('Années') . ' ' . $decade) : null,
                'seasons' => array_map(fn ($se, $n) => ['label' => str_replace('-', ' - ', $se), 'count' => $n, 'on' => $season === $se, 'href' => $here . self::qs($keep + ['saison' => $se])], array_keys($seasons), $seasons),
                'seasonAll' => $season ? $here . self::qs($keep) : null,
                'seasonPage' => $season ? url('/matchs/' . $season . '/') : null,
                'season' => $season,
            ];
        } elseif ($kind === 'lions') {
            $items = array_values(array_filter($items, fn ($s) => $s['type'] === 'personne'));
            $poste = in_array($req->str('poste'), ['G', 'D', 'M', 'A'], true) ? $req->str('poste') : null;
            $legend = $req->str('f') === 'legendes';
            if ($poste) {
                $state['poste'] = $poste;
                $items = array_values(array_filter($items, fn ($s) => ($s['p']['line'] ?? null) === $poste));
            }
            if ($legend) {
                $state['f'] = 'legendes';
                $items = array_values(array_filter($items, fn ($s) => !empty($s['p']['legend'])));
            }
            // Sous-rubriques (rubriques actuelles) puis postes
            $parentForChips = Categories::children($slug) ? $slug : ($cat['parent'] ?? null);
            if ($parentForChips && $parentForChips !== Site::C_LIONS) {
                $chips[] = ['label' => t('Tous'), 'on' => $slug === $parentForChips && !$poste && !$legend, 'href' => Site::catUrl($parentForChips)];
                foreach (Categories::children($parentForChips) as $ch) {
                    $chips[] = ['label' => t(Categories::label($ch['slug'])), 'on' => $ch['slug'] === $slug, 'href' => url((string) $ch['path']), 'count' => Site::count($ch['slug'])];
                }
            }
            if (in_array($slug, [Site::C_LIONS, Site::C_JOUEURS], true) || Categories::get($slug)['parent'] === Site::C_JOUEURS) {
                if ($chips) {
                    $chips[] = ['sep' => true];
                }
                foreach (['G' => 'Gardiens', 'D' => 'Défenseurs', 'M' => 'Milieux', 'A' => 'Attaquants'] as $k => $label) {
                    $chips[] = ['label' => t($label), 'on' => $poste === $k, 'href' => url($base) . self::qs(array_filter(['poste' => $poste === $k ? null : $k, 'q' => $q])) ];
                }
                if (array_filter(Index::inCategory($slug), fn ($s) => !empty($s['p']['legend']))) {
                    $chips[] = ['label' => t('Légendes'), 'on' => $legend, 'href' => url($base) . self::qs(array_filter(['f' => $legend ? null : 'legendes']))];
                }
            }
        } else {
            $parentForChips = Categories::children($slug) ? $slug : ($cat['parent'] ?? null);
            if ($parentForChips && $parentForChips !== $root) {
                foreach (Categories::children($parentForChips) as $ch) {
                    $chips[] = ['label' => t(Categories::label($ch['slug'])), 'on' => $ch['slug'] === $slug, 'href' => url((string) $ch['path']), 'count' => Site::count($ch['slug'])];
                }
            }
        }

        // Recherche dans la rubrique
        if ($q !== '') {
            $needle = Names::ascii($q);
            $items = array_values(array_filter($items, function ($s) use ($needle) {
                $hay = $s['title'] . ' ' . ($s['p']['name'] ?? '') . ' ' . ($s['p']['nickname'] ?? '') . ' ' . ($s['m']['home'] ?? '') . ' ' . ($s['m']['away'] ?? '') . ' ' . ($s['m']['stadium'] ?? '') . ' ' . ($s['m']['season'] ?? '') . ' ' . $s['excerpt'];
                return str_contains(Names::ascii($hay), $needle);
            }));
        }

        // Tris
        $defaultSort = match ($kind) { 'matchs' => 'recent', 'lions' => 'az', default => $hasOrder ? 'selection' : 'recent' };
        $sortNow = $sort ?? $defaultSort;
        $items = self::sort($items, $sortNow, $kind, $slug);
        $sorts = [];
        if ($kind === 'articles' && $hasOrder) {
            $sorts[] = ['k' => 'selection', 'label' => t('Sélection')];
        }
        foreach ([['recent', t('Récent')], ['ancien', t('Ancien')], ['az', t('A–Z')]] as [$k, $label]) {
            $sorts[] = ['k' => $k, 'label' => $label];
        }
        foreach ($sorts as &$so) {
            $so['on'] = $so['k'] === $sortNow;
            $so['href'] = url($base) . self::qs(array_filter(['tri' => $so['k'] === $defaultSort ? null : $so['k']] + $state, fn ($v, $k) => $k !== 'tri' ? $v !== null && $v !== '' : $v !== null, ARRAY_FILTER_USE_BOTH));
        }
        unset($so);

        // Pagination
        $total = count($items);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $pages);
        $slice = array_slice($items, ($page - 1) * self::PER_PAGE, self::PER_PAGE);
        $nextHref = $page < $pages ? url($base) . self::qs(array_filter($state + ['page' => (string) ($page + 1)])) : null;
        $prevHref = $page > 1 ? url($base) . self::qs(array_filter($state + ['page' => $page > 2 ? (string) ($page - 1) : null])) : null;

        $itemsHtml = View::partial('partials/mosaic-items', [
            'items' => $slice, 'kind' => $kind, 'view' => $view, 'offset' => ($page - 1) * self::PER_PAGE, 'catSlug' => $slug,
        ]);
        if ($req->str('fragment') === '1') {
            return Response::json([
                'html' => $itemsHtml,
                'next' => $nextHref,
                'total' => $total,
                'count' => self::countLabel($total, $kind),
            ]);
        }

        // Onglets : sous-rubriques de la racine (ou outils d'exploration pour les matchs)
        $tabs = [];
        if ($kind === 'matchs') {
            $tabs = [
                ['label' => t('Tous les matchs'), 'href' => url('/matchs/'), 'on' => true],
                ['label' => t('Saisons'), 'href' => url('/saisons/'), 'on' => false],
                ['label' => t('Face-à-face'), 'href' => url('/face-a-face/'), 'on' => false],
                ['label' => t('Records'), 'href' => url('/records/'), 'on' => false],
                ['label' => t('Bilan à Bonal'), 'href' => url('/bilans/stade-auguste-bonal/'), 'on' => false],
                ['label' => t('Bilan en Coupe de France'), 'href' => url('/bilans/coupe-de-france/'), 'on' => false],
            ];
        } else {
            $trail = Categories::trail($slug);
            $level1 = $trail[1]['slug'] ?? null;
            $rootCat = Categories::get($root);
            $tabs[] = ['label' => t('Tout'), 'href' => url((string) ($rootCat['path'] ?? '/')), 'on' => $slug === $root];
            foreach (Categories::children($root) as $ch) {
                if (Site::count($ch['slug']) === 0 && $ch['slug'] !== $slug) {
                    continue;
                }
                $tabs[] = ['label' => t(Categories::label($ch['slug'])), 'href' => url((string) $ch['path']), 'on' => $ch['slug'] === $level1];
            }
        }

        $crumbs = [['label' => t('Accueil'), 'href' => url('/')]];
        $trail = Categories::trail($slug);
        foreach (array_slice($trail, 0, -1) as $c) {
            if (!empty($c['path'])) {
                $crumbs[] = ['label' => t(Categories::label($c['slug'])), 'href' => url((string) $c['path'])];
            }
        }
        $label = t(Categories::label($slug));
        if ($slug === Site::C_MATCHS && isset($comp) && $comp) {
            $label = t(self::COMPS[$comp][0]);
        }
        $parent = count($trail) > 1 ? t(Categories::label($trail[count($trail) - 2]['slug'])) : t($conf['eyebrow']);
        $intro = trim(strip_tags((string) ($cat['description'] ?? ''))) ?: ($slug === $root ? t($conf['intro']) : '');
        $titleSeo = $label . ($kind === 'matchs' ? ' – ' . t('Matchs du FC Sochaux-Montbéliard') : ($kind === 'lions' && $slug !== Site::C_LIONS ? ' – ' . t('Nos Lions du FCSM') : ''));
        if ($page > 1) {
            $titleSeo .= ' – ' . t('page {n}', ['n' => $page]);
        }

        return Pages::render('mosaic', [
            'cat' => $cat,
            'kind' => $kind,
            'label' => $label,
            'eyebrow' => $slug === $root ? t($conf['eyebrow']) : $parent,
            'intro' => $intro,
            'unit' => t($conf['unit']),
            'total' => $total,
            'grandTotal' => Site::count($slug),
            'countLabel' => self::countLabel($total, $kind),
            'tabs' => $tabs,
            'chips' => $chips,
            'panel' => $panel,
            'sorts' => $sorts,
            'view' => $view,
            'q' => $q,
            'state' => $state,
            'base' => url($base),
            'placeholder' => t($conf['ph']),
            'itemsHtml' => $itemsHtml,
            'extras' => $page === 1 && $q === '' ? $extras : [],
            'nextHref' => $nextHref,
            'prevHref' => $prevHref,
            'pageNum' => $page,
            'pages' => $pages,
            'crumbs' => $crumbs,
            'hasFilter' => $q !== '' || count(array_filter($state)) > 0,
            'resetHref' => url($base),
        ], [
            'title' => $titleSeo,
            'description' => $intro ?: t('{label} : {n} fiches du musée en ligne du FC Sochaux-Montbéliard.', ['label' => $label, 'n' => $total]),
            'active' => match ($kind) { 'matchs' => 'matchs', 'lions' => 'nos-lions', default => $root },
            'body_class' => 'page-mosaic',
            'styles' => ['css/mosaic.css'],
            'scripts' => ['js/mosaic.js'],
            'noindex' => $q !== '' || isset($state['tri']) || isset($state['vue']),
            'canonical' => url($base) . ($page > 1 ? '?page=' . $page : ''),
            'prev' => $prevHref,
            'next' => $nextHref,
        ]);
    }

    /** Décennie d'un match (année de début de saison). */
    public static function decadeOf(array $s): ?int
    {
        $season = $s['m']['season'] ?? null;
        $y = $season ? (int) substr($season, 0, 4) : (isset($s['m']['date']) ? (int) substr((string) $s['m']['date'], 0, 4) : 0);
        return $y ? intdiv($y, 10) * 10 : null;
    }

    private static function decadeCategory(int $decade): ?array
    {
        foreach (Categories::children(Site::C_MATCHS) as $c) {
            if (preg_match('/^annees-(\d+)/', $c['slug'], $dm)) {
                $d = strlen($dm[1]) === 2 ? (int) ('19' . $dm[1]) : (int) $dm[1];
                if ($d === $decade) {
                    return $c;
                }
            }
        }
        return null;
    }

    private static function sort(array $items, string $sort, string $kind, string $slug): array
    {
        $date = fn ($s) => (string) ($kind === 'matchs' ? ($s['m']['date'] ?? '') : ($kind === 'lions' ? sprintf('%04d', $s['p']['arrival'] ?? $s['p']['birth_year'] ?? 0) : ($s['date'] ?? '')));
        $name = fn ($s) => $kind === 'lions' ? Index::sortName($s) : ($kind === 'matchs' ? Names::ascii(($s['m']['sh'] ?? true) ? ($s['m']['away'] ?? '') : ($s['m']['home'] ?? '')) : Names::ascii($s['title']));
        switch ($sort) {
            case 'selection':
                return Index::ordered($items, $slug);
            case 'recent':
                usort($items, fn ($a, $b) => strcmp($date($b), $date($a)) ?: strcmp($name($a), $name($b)));
                return $items;
            case 'ancien':
                usort($items, fn ($a, $b) => strcmp($date($a), $date($b)) ?: strcmp($name($a), $name($b)));
                return $items;
            default:
                usort($items, fn ($a, $b) => strcmp($name($a), $name($b)) ?: strcmp($date($b), $date($a)));
                return $items;
        }
    }

    private static function countLabel(int $n, string $kind): string
    {
        return match ($kind) {
            'matchs' => $n . ' ' . ($n > 1 ? t('matchs') : t('match')),
            'lions' => $n . ' ' . ($n > 1 ? t('portraits') : t('portrait')),
            default => $n . ' ' . ($n > 1 ? t('résultats') : t('résultat')),
        };
    }

    /** Chaîne de requête « ?a=b&c=d » (vide si aucun paramètre). */
    public static function qs(array $params): string
    {
        $params = array_filter($params, fn ($v) => $v !== null && $v !== '');
        return $params ? '?' . http_build_query($params) : '';
    }
}
