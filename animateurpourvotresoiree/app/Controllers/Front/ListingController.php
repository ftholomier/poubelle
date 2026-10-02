<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Url;
use App\Core\View;
use App\Services\Ai;
use App\Services\Categories;
use App\Services\Geo;
use App\Services\Pages;
use App\Services\Pros;
use App\Services\Search;
use App\Services\Seo;
use App\Services\Settings;
use App\Services\Stats;

/**
 * Pages SEO « métier × lieu » :
 *   /dj/  /dj/auvergne-rhone-alpes/  /dj/rhone-69/  /dj/rhone-69/lyon/
 *   /animateurs/...  (tous métiers)   /animation-mariage/  /animation-mariage/rhone-69/
 * Elles utilisent l'explorateur (liste à gauche, carte synchronisée à droite).
 */
final class ListingController extends Controller
{
    private static function cat(string $slug): array|false|null
    {
        if ($slug === Url::ALL) {
            return null;
        }
        return Categories::get($slug) ?? false;
    }

    public function one(string $a): Response
    {
        $cat = self::cat($a);
        if ($cat !== false) {
            return $this->listing($cat, null, null, null);
        }
        if ($occ = Categories::occasion($a)) {
            return $this->occasion($occ, null);
        }
        if ($page = Pages::bySlug($a)) {
            return (new PageController())->show($page);
        }
        // installation neuve : les pages obligatoires (mentions légales, CGU…) sont créées à la volée
        if (in_array($a, Pages::RESERVED, true) && Pages::seedIfEmpty() > 0 && ($page = Pages::bySlug($a))) {
            return (new PageController())->show($page);
        }
        throw new HttpException(404);
    }

    public function two(string $a, string $b): Response
    {
        $cat = self::cat($a);
        if ($cat !== false) {
            if ($region = Geo::regionBySlug($b)) {
                return $this->listing($cat, $region, null, null);
            }
            if ($dep = Geo::depBySlug($b)) {
                return $this->listing($cat, null, $dep, null);
            }
            throw new HttpException(404);
        }
        if (($occ = Categories::occasion($a)) && ($dep = Geo::depBySlug($b))) {
            return $this->occasion($occ, $dep);
        }
        throw new HttpException(404);
    }

    public function three(string $a, string $b, string $c): Response
    {
        $cat = self::cat($a);
        $dep = Geo::depBySlug($b);
        if ($cat === false || !$dep) {
            throw new HttpException(404);
        }
        $city = Geo::communeBySlug($dep['code'], $c);
        if (!$city) {
            throw new HttpException(404);
        }
        return $this->listing($cat, null, $dep, $city);
    }

    private function listing(?array $cat, ?array $region, ?array $dep, ?array $city): Response
    {
        $catSlug = $cat['slug'] ?? null;
        $criteria = array_filter(['cat' => $catSlug, 'region' => $region['code'] ?? null, 'dep' => $city ? null : ($dep['code'] ?? null), 'insee' => $city['insee'] ?? null]);
        $page = max(1, (int) Request::query('page', 1));
        $res = Search::run($criteria + ['page' => $page, 'per' => 24]);
        $vars = Seo::catVars($cat) + ['nb' => $res['total'], 'site' => Settings::siteName()];
        $crumbs = [['Accueil', '/'], [$cat['name'] ?? 'Tous les pros', Url::category($catSlug)]];
        $children = [];
        if ($city) {
            $vars += ['in' => Geo::inCity($city['n']), 'code' => $dep['code'], 'city' => $city['n']];
            $type = $cat ? 'category_city' : 'all_city';
            $h1 = ($cat ? Seo::catVars($cat)['Many'] : 'Animateurs et artistes') . ' ' . Geo::inCity($city['n']);
            $crumbs[] = [$dep['name'] . ' (' . $dep['code'] . ')', Url::dep($catSlug, $dep['code'])];
            $crumbs[] = [$city['n'], Url::city($catSlug, $city['insee'])];
            $placeIn = Geo::inCity($city['n']);
            $children = $this->nearbyCities($catSlug, $city);
            $self = Url::city($catSlug, $city['insee']);
        } elseif ($dep) {
            $vars += ['in' => $dep['in'], 'code' => $dep['code']];
            $type = $cat ? 'category_dep' : 'all_dep';
            $h1 = ($cat ? Seo::catVars($cat)['Many'] : 'Animateurs et artistes') . ' ' . $dep['in'] . ' (' . $dep['code'] . ')';
            $reg = Geo::region($dep['region']);
            if ($reg) {
                $crumbs[] = [$reg['name'], Url::region($catSlug, $reg['code'])];
            }
            $crumbs[] = [$dep['name'] . ' (' . $dep['code'] . ')', Url::dep($catSlug, $dep['code'])];
            $placeIn = $dep['in'];
            $children = $this->citiesOf($catSlug, $dep['code']);
            $self = Url::dep($catSlug, $dep['code']);
        } elseif ($region) {
            $vars += ['in' => $region['in'], 'code' => ''];
            $type = $cat ? 'category_region' : 'all_region';
            $h1 = ($cat ? Seo::catVars($cat)['Many'] : 'Animateurs et artistes') . ' ' . $region['in'];
            $crumbs[] = [$region['name'], Url::region($catSlug, $region['code'])];
            $placeIn = $region['in'];
            foreach ($region['deps'] as $d) {
                $dd = Geo::dep($d);
                $children[] = [$dd['name'] . ' (' . $d . ')', Url::dep($catSlug, $d)];
            }
            $self = Url::region($catSlug, $region['code']);
        } else {
            $type = $cat ? 'category' : 'all';
            $h1 = $cat ? Seo::catVars($cat)['Many'] . ' pour vos événements' : 'Tous les pros de l\'animation';
            $placeIn = null;
            foreach (Geo::regions() as $code => $r) {
                $children[] = [$r['name'], Url::region($catSlug, (string) $code)];
            }
            $self = Url::category($catSlug);
            array_pop($crumbs);
            $crumbs[] = [$cat['name'] ?? 'Tous les pros', $self];
        }
        $meta = Seo::meta($type, $vars);
        $landing = Seo::landingText($self);
        $intro = $landing['intro'] ?? (($placeIn === null && $cat && trim((string) ($cat['intro'] ?? '')) !== '') ? (string) $cat['intro'] : Seo::autoIntro($cat, $placeIn, $res['total'], $this->topCityNames($res['ids'])));
        if (!empty($landing['title'])) {
            $meta['title'] = $landing['title'];
        }
        if (!empty($landing['description'])) {
            $meta['description'] = $landing['description'];
        }
        $empty = $res['total'] === 0;
        $robots = ($empty && Settings::get('seo.noindex_empty', true)) || $page > 1 ? 'noindex, follow' : 'index, follow';
        Stats::hit('pv');
        return $this->explorer([
            'h1' => $h1,
            'kicker' => $cat ? ($cat['emoji'] ?? '') . ' ' . $cat['name'] : '🎉 Tous les métiers',
            'intro' => $intro,
            'crumbs' => $crumbs,
            'criteria' => $criteria,
            'state' => $criteria + ['ou' => $city ? Geo::label($city) : ''],
            'result' => $res,
            'page' => $page,
            'self' => $self,
            'children' => $children,
            'related' => $this->relatedCategories($catSlug, $dep, $city, $region),
            'cat' => $cat,
            'place' => ['city' => $city, 'dep' => $dep, 'region' => $region, 'in' => $placeIn],
            'seo_content' => $landing['content'] ?? '',
            'meta' => $meta + [
                'canonical' => Url::abs($self) . ($page > 1 ? '?page=' . $page : ''),
                'robots' => $robots,
                'jsonld' => [Seo::breadcrumbs($crumbs), Seo::itemList($res['items'])],
                'prev' => $page > 1 ? $self . ($page > 2 ? '?page=' . ($page - 1) : '') : null,
                'next' => $page < $res['pages'] ? $self . '?page=' . ($page + 1) : null,
            ],
        ]);
    }

    private function occasion(array $occ, ?array $dep): Response
    {
        $criteria = array_filter(['occasion' => $occ['slug'], 'dep' => $dep['code'] ?? null]);
        $page = max(1, (int) Request::query('page', 1));
        $res = Search::run($criteria + ['page' => $page, 'per' => 24]);
        $crumbs = [['Accueil', '/'], [$occ['title'], Url::occasion($occ['slug'])]];
        $vars = ['occasion' => $occ['title'], 'nb' => $res['total']];
        if ($dep) {
            $vars += ['in' => $dep['in'], 'code' => $dep['code']];
            $crumbs[] = [$dep['name'] . ' (' . $dep['code'] . ')', Url::occasion($occ['slug'], $dep['code'])];
            $self = Url::occasion($occ['slug'], $dep['code']);
            $h1 = $occ['title'] . ' ' . $dep['in'];
        } else {
            $self = Url::occasion($occ['slug']);
            $h1 = $occ['title'] . ' : les pros qui assurent';
        }
        $meta = Seo::meta($dep ? 'occasion_dep' : 'occasion', $vars);
        $landing = Seo::landingText($self);
        if (!empty($landing['title'])) {
            $meta['title'] = $landing['title'];
        }
        $children = [];
        if (!$dep) {
            $counts = [];
            foreach (Pros::publicIndex() as $p) {
                if ($p['dep']) {
                    $counts[$p['dep']] = ($counts[$p['dep']] ?? 0) + 1;
                }
            }
            arsort($counts);
            foreach (array_slice(array_keys($counts), 0, 30) as $d) {
                $dd = Geo::dep((string) $d);
                $children[] = [$dd['name'] . ' (' . $dd['code'] . ')', Url::occasion($occ['slug'], (string) $d)];
            }
        }
        $related = [];
        foreach ($occ['cats'] ?? [] as $c) {
            if ($cc = Categories::get($c)) {
                $related[] = [$cc['name'] . ($dep ? ' ' . $dep['in'] : ''), $dep ? Url::dep($c, $dep['code']) : Url::category($c)];
            }
        }
        Stats::hit('pv');
        return $this->explorer([
            'h1' => $h1,
            'kicker' => $occ['emoji'] . ' ' . $occ['name'],
            'intro' => $landing['intro'] ?? (!$dep && trim((string) ($occ['intro'] ?? '')) !== '' ? (string) $occ['intro'] : null) ?? ('Pour réussir votre ' . mb_strtolower($occ['name']) . ($dep ? ' ' . $dep['in'] : '') . ', comparez ' . $res['total'] . ' professionnels : ' . mb_strtolower($occ['hint']) . '… Consultez leurs fiches, leurs avis et demandez vos devis gratuitement, en une seule fois.'),
            'crumbs' => $crumbs,
            'criteria' => $criteria,
            'state' => $criteria,
            'result' => $res,
            'page' => $page,
            'self' => $self,
            'children' => $children,
            'related' => $related,
            'cat' => null,
            'occasion' => $occ,
            'place' => ['city' => null, 'dep' => $dep, 'region' => null, 'in' => $dep['in'] ?? null],
            'seo_content' => $landing['content'] ?? '',
            'meta' => $meta + [
                'canonical' => Url::abs($self),
                'robots' => $res['total'] === 0 || $page > 1 ? 'noindex, follow' : 'index, follow',
                'jsonld' => [Seo::breadcrumbs($crumbs), Seo::itemList($res['items'])],
            ],
        ]);
    }

    /** Rendu commun de l'explorateur (également utilisé par /recherche/). */
    public function explorer(array $ctx): Response
    {
        $ctx['meta']['body_class'] = 'page-explore';
        $ctx['meta']['scripts'] = [asset('js/explorer.js')];
        $ctx['meta']['local_links'] = false;
        return $this->view('front/explorer', $ctx);
    }

    private function topCityNames(array $ids): array
    {
        $count = [];
        $idx = Pros::publicIndex();
        foreach ($ids as $id) {
            $c = $idx[$id]['city'] ?? '';
            if ($c !== '') {
                $count[$c] = ($count[$c] ?? 0) + 1;
            }
        }
        arsort($count);
        return array_slice(array_keys($count), 0, 5);
    }

    private function citiesOf(?string $cat, string $dep): array
    {
        $count = [];
        foreach (Pros::publicIndex() as $p) {
            if ($p['dep'] === $dep && $p['insee'] && (!$cat || in_array($cat, $p['cats'], true))) {
                $count[$p['insee']] = ($count[$p['insee']] ?? 0) + 1;
            }
        }
        arsort($count);
        $out = [];
        foreach (array_slice(array_keys($count), 0, 30) as $insee) {
            $c = Geo::commune((string) $insee);
            if ($c) {
                $out[] = [$c['n'], Url::city($cat, (string) $insee)];
            }
        }
        if (count($out) < 8) {
            // compléter avec les grandes villes du département
            $big = Geo::communesOfDep($dep);
            uasort($big, static fn ($a, $b) => $b['p'] <=> $a['p']);
            foreach (array_slice($big, 0, 12, true) as $insee => $c) {
                $u = Url::city($cat, (string) $insee);
                if (!in_array($u, array_column($out, 1), true)) {
                    $out[] = [$c['n'], $u];
                }
            }
        }
        return $out;
    }

    private function nearbyCities(?string $cat, array $city): array
    {
        $rows = [];
        foreach (Geo::communesOfDep($city['d']) as $insee => $c) {
            if ((string) $insee === $city['insee'] || $c['p'] < 3000) {
                continue;
            }
            $rows[(string) $insee] = Geo::distance((float) $city['la'], (float) $city['lo'], (float) $c['la'], (float) $c['lo']);
        }
        asort($rows);
        $out = [];
        foreach (array_slice($rows, 0, 14, true) as $insee => $d) {
            $c = Geo::commune((string) $insee);
            $out[] = [$c['n'], Url::city($cat, (string) $insee)];
        }
        $dep = Geo::dep($city['d']);
        $out[] = ['Tout le département ' . $dep['of'], Url::dep($cat, $city['d'])];
        return $out;
    }

    private function relatedCategories(?string $cat, ?array $dep, ?array $city, ?array $region): array
    {
        $out = [];
        $by = [];
        foreach (Search::run(array_filter(['dep' => $city ? null : ($dep['code'] ?? null), 'insee' => $city['insee'] ?? null, 'region' => $region['code'] ?? null, 'per' => 1]))['ids'] as $id) {
            foreach (Pros::publicIndex()[$id]['cats'] ?? [] as $c) {
                $by[$c] = ($by[$c] ?? 0) + 1;
            }
        }
        arsort($by);
        foreach ($by as $c => $n) {
            if ($c === $cat || !($cc = Categories::get($c))) {
                continue;
            }
            $url = $city ? Url::city($c, $city['insee']) : ($dep ? Url::dep($c, $dep['code']) : ($region ? Url::region($c, $region['code']) : Url::category($c)));
            $out[] = [$cc['name'] . ' (' . $n . ')', $url];
        }
        if ($cat) {
            $all = $city ? Url::city(null, $city['insee']) : ($dep ? Url::dep(null, $dep['code']) : ($region ? Url::region(null, $region['code']) : Url::category(null)));
            array_unshift($out, ['Tous les métiers', $all]);
        }
        return $out;
    }
}
