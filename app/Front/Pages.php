<?php
declare(strict_types=1);

namespace App\Front;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Settings;
use App\Core\View;
use App\Data\Categories;
use App\Data\Collections;
use App\Data\Derived;
use App\Data\Fiches;
use App\Data\Index;
use App\Data\Media;
use App\Services\I18n;

/** Pages publiques : accueil, fiches, rubriques (mosaïques), recherche, attente, 404. */
final class Pages
{
    public static function render(string $tpl, array $vars, array $page): Response
    {
        $page['path'] = $page['path'] ?? ($_SERVER['REQUEST_URI'] ?? '/');
        return Response::html(View::render($tpl, $vars + ['page' => $page], 'layout'));
    }

    // ------------------------------------------------------------------ accueil

    /** Photo du jour : une photo d'archive des murs de photos, la même toute la journée (plutôt grande). */
    public static function photoOfDay(): ?array
    {
        $all = \App\Services\PhotoWall::photos();
        $big = array_values(array_filter($all, fn ($p) => ($p['w'] ?? 0) >= 900 && ($p['f'] ?? 0)));
        $pool = $big ?: $all;
        if (!$pool) {
            return null;
        }
        $p = $pool[crc32(date('Y-m-d')) % count($pool)];
        $e = !empty($p['f']) ? Index::get((int) $p['f']) : null;
        return ['image' => $p['r'], 'caption' => (string) ((\App\Services\I18n::isEn() && ($p['cap_en'] ?? '') !== '') ? $p['cap_en'] : ($p['cap'] ?? '')),
            'credit' => (string) ($p['c'] ?? ''), 'year' => (int) ($p['y'] ?? 0), 'href' => $e ? url((string) $e['path']) : null];
    }

    /**
     * « Il y a N ans » : pour chaque écart, à tour de rôle selon le jour, un match (le plus proche de
     * la même date, dans les 6 semaines), un joueur arrivé au club cette année-là ou un fait de la
     * frise de cette année ; sinon la saison.
     * @return list<array{n:int,date:string,kind:string,title:string,text:string,href:?string,image:?string,score:?string}>
     */
    public static function yearsAgo(array $years): array
    {
        // Choix du jour gardé (le même toute la journée) ; fiches déjà montrées dans les 30 derniers
        // jours écartées, pour ne pas revoir les mêmes d'un jour à l'autre. Les textes et les liens
        // dépendent de la langue (t(), url()) : une entrée par langue et par liste d'écarts, les
        // fiches montrées (« keys ») étant communes. Le choix ne dépend que des fiches et du journal
        // des jours précédents : il est le même dans chaque langue.
        $logFile = STORAGE_PATH . '/home/il-y-a.json';
        $log = (array) (\App\Core\JsonStore::read($logFile, []) ?: []);
        $today = date('Y-m-d');
        $slot = \App\Services\I18n::lang() . '|' . implode(',', $years);
        if (isset($log[$today]['by'][$slot]) && is_array($log[$today]['by'][$slot])) {
            return $log[$today]['by'][$slot];
        }
        $seen = [];
        foreach ($log as $day => $entry) {
            if ($day !== $today && $day >= date('Y-m-d', strtotime('-30 days'))) {
                // « keys » : fiches montrées ce jour-là ; ancien format : éléments complets (« items »).
                foreach ((array) ($entry['keys'] ?? array_column((array) ($entry['items'] ?? []), 'key')) as $k) {
                    $seen[(string) $k] = true;
                }
            }
        }
        $matches = [];
        foreach (Index::published('match') as $e) {
            if (!empty($e['m']['date'])) {
                $matches[] = $e;
            }
        }
        $people = Index::published('personne');
        // Clé d'un fait de la frise tirée du titre français : la même dans chaque langue.
        $frise = array_map(fn ($e) => ['_key' => 'frise' . md5((string) ($e['title'] ?? ''))] + Collections::loc($e, ['title', 'text']), Collections::get('frise', \App\Data\Seeds::frise()));
        $seed = (int) date('z');
        $bilans = array_values(array_filter(Index::published('article'), fn ($e) => !empty($e['a']['season'])));
        $moments = array_values(array_filter(\App\Services\Moments::all(), fn ($m) => !empty($m['visible'])));
        $out = [];
        foreach ($years as $n) {
            $target = strtotime('-' . $n . ' years', strtotime('today'));
            $y = (int) date('Y', $target);
            $base = ['n' => $n, 'date' => date('Y-m-d', $target), 'kind' => '', 'title' => '', 'text' => '', 'href' => null, 'image' => null, 'score' => null];
            $make = [
                'match' => function () use ($matches, $target, $base, &$seen) {
                    $best = null;
                    $bestD = 86400 * 42;
                    foreach ($matches as $e) {
                        if (isset($seen['f' . $e['id']])) {
                            continue;
                        }
                        $d = abs(strtotime((string) $e['m']['date']) - $target);
                        if ($d < $bestD) {
                            [$best, $bestD] = [$e, $d];
                        }
                    }
                    if (!$best) {
                        return null;
                    }
                    $m = $best['m'];
                    $sc = $m['sh_score'] ?? null;
                    $txt = (int) round($bestD / 86400) === 0 ? t('Jour pour jour, le {d}.', ['d' => date_fr((string) $m['date'])]) : t('Le {d}, à quelques jours près.', ['d' => date_fr((string) $m['date'])]);
                    return ['kind' => trim(($m['label'] ?? '') ?: ($m['competition'] ?? '')) ?: t('Match'), 'title' => trim(($m['home'] ?? '') . ' – ' . ($m['away'] ?? ''), ' –'),
                        'score' => is_array($sc) && isset($sc[0], $sc[1]) ? ($m['sh'] ? $sc[0] . ' – ' . $sc[1] : $sc[1] . ' – ' . $sc[0]) : null,
                        'text' => trim($txt . ' ' . ($best['excerpt'] ?? '')), 'href' => url((string) $best['path']), 'image' => $best['image'] ?? null, 'key' => 'f' . $best['id'], 'exact' => $bestD < 43200] + $base;
                },
                'joueur' => function () use ($people, $y, $seed, $base, &$seen) {
                    $list = array_values(array_filter($people, fn ($e) => (int) ($e['p']['arrival'] ?? 0) === $y && !empty($e['image']) && !isset($seen['f' . $e['id']])));
                    if (!$list) {
                        return null;
                    }
                    $e = $list[($seed + $y) % count($list)];
                    $p = $e['p'];
                    $role = in_array('entraineur', (array) ($p['roles'] ?? []), true) ? t('entraîneur') : (string) (($p['position'] ?? '') ?: t('joueur'));
                    $stay = (int) ($p['departure'] ?? 0) > $y ? t('Il restera au club jusqu’en {d}.', ['d' => (int) $p['departure']]) : '';
                    return ['kind' => t('Arrivée au club'), 'title' => (string) ($p['name'] ?? $e['title']),
                        'text' => trim(t('Il y a {n} ans, {name} arrivait {city_a} ({role}).', ['n' => $base['n'], 'name' => (string) ($p['name'] ?? $e['title']), 'role' => $role]) . ' ' . $stay),
                        'href' => url((string) $e['path']), 'image' => $e['image'], 'key' => 'f' . $e['id']] + $base;
                },
                'depart' => function () use ($people, $y, $seed, $base, &$seen) {
                    $list = array_values(array_filter($people, fn ($e) => (int) ($e['p']['departure'] ?? 0) === $y && (int) ($e['p']['arrival'] ?? 0) > 0 && !empty($e['image']) && !isset($seen['f' . $e['id']])));
                    if (!$list) {
                        return null;
                    }
                    $e = $list[($seed + $y * 7) % count($list)];
                    $p = $e['p'];
                    $len = $y - (int) $p['arrival'];
                    return ['kind' => t('Départ du club'), 'title' => (string) ($p['name'] ?? $e['title']),
                        'text' => t('Il y a {n} ans, {name} quittait {team}, arrivé en {a}{d}.', ['n' => $base['n'], 'name' => (string) ($p['name'] ?? $e['title']), 'a' => (int) $p['arrival'],
                            'd' => $len > 1 ? ' (' . t('{x} saisons au club', ['x' => $len]) . ')' : '']),
                        'href' => url((string) $e['path']), 'image' => $e['image'], 'key' => 'f' . $e['id']] + $base;
                },
                'bilan' => function () use ($bilans, $target, $base, &$seen) {
                    $y = (int) date('Y', $target);
                    $season = (int) date('n', $target) >= 7 ? $y . '-' . ($y + 1) : ($y - 1) . '-' . $y;
                    foreach ($bilans as $e) {
                        if (($e['a']['season'] ?? '') === $season && !isset($seen['f' . $e['id']])) {
                            return ['kind' => t('Bilan de saison'), 'title' => (string) $e['title'], 'text' => (string) ($e['excerpt'] ?? t('Le bilan complet de la saison {s}.', ['s' => $season])),
                                'href' => url((string) $e['path']), 'image' => $e['image'] ?? null, 'key' => 'f' . $e['id']] + $base;
                        }
                    }
                    return null;
                },
                'moment' => function () use ($moments, $y, $base, &$seen) {
                    foreach ($moments as $mo) {
                        if ((int) ($mo['year'] ?? 0) === $y && !isset($seen['f' . $mo['id']])) {
                            return ['kind' => t('100 moments') . ($mo['number'] ? ' · n° ' . (int) $mo['number'] : ''), 'title' => (string) $mo['title'], 'text' => (string) ($mo['event'] ?? ''),
                                'href' => url((string) $mo['path']), 'image' => $mo['image'] ?? null, 'key' => 'f' . $mo['id']] + $base;
                        }
                    }
                    return null;
                },
                'fait' => function () use ($frise, $y, $base, &$seen) {
                    $list = array_values(array_filter($frise, fn ($f) => (int) ($f['year'] ?? 0) === $y && !isset($seen[$f['_key']])));
                    if (!$list) {
                        return null;
                    }
                    $f = $list[0];
                    return ['kind' => t('Fait marquant') . ' · ' . $y, 'title' => (string) $f['title'], 'text' => (string) ($f['text'] ?? ''),
                        'href' => !empty($f['href']) ? url((string) $f['href']) : url('/interactif/frise/'), 'image' => $f['image'] ?? null, 'key' => $f['_key']] + $base;
                },
            ];
            // Un match joué le jour J passe avant tout ; sinon match le plus proche, joueur ou fait, à tour de rôle.
            $item = $make['match']();
            if (!$item || empty($item['exact'])) {
                $order = ['match', 'joueur', 'moment', 'depart', 'fait', 'bilan'];
                $k = ($seed + $n) % count($order);
                $order = array_merge(array_slice($order, $k), array_slice($order, 0, $k));
                $item = null;
                foreach ($order as $type) {
                    if ($item = $make[$type]()) {
                        break;
                    }
                }
            }
            if ($item && !empty($item['key'])) {
                $seen[$item['key']] = true; // pas deux fois la même fiche le même jour
            }
            if (!$item) {
                // Saison de l'époque : sa page (/matchs/AAAA-AAAA/) n'existe que si des matchs y sont relevés.
                $season = (int) date('n', $target) >= 7 ? $y . '-' . ($y + 1) : ($y - 1) . '-' . $y;
                $item = ['kind' => t('Saison'), 'title' => t('La saison {s}', ['s' => $season]), 'text' => t('Les matchs, les joueurs et les souvenirs de cette saison-là.'),
                    'href' => isset(Derived::part('seasons')[$season]) ? url('/matchs/' . $season . '/') : null] + $base;
            }
            $out[] = $item;
        }
        $log = array_filter($log, fn ($k) => $k >= date('Y-m-d', strtotime('-40 days')), ARRAY_FILTER_USE_KEY);
        $entry = isset($log[$today]['by']) ? $log[$today] : ['keys' => [], 'by' => []];
        $entry['keys'] = array_values(array_unique(array_merge((array) $entry['keys'], array_values(array_filter(array_column($out, 'key'))))));
        $entry['by'][$slot] = $out;
        $log[$today] = $entry;
        @mkdir(dirname($logFile), 0775, true);
        \App\Core\JsonStore::write($logFile, $log);
        return $out;
    }

    public static function home(Request $req): Response
    {
        $slides = self::slides((int) Settings::get('home.slider_count', 5));
        $today = Derived::onThisDay();
        $jour = $today[0] ?? null;
        $jourDate = time();
        if (!$jour) {
            // Aucun match ce jour : le plus proche dans les 10 jours suivants.
            for ($i = 1; $i <= 10 && !$jour; $i++) {
                $ts = strtotime("+$i day");
                $jour = Derived::onThisDay(date('m-d', $ts))[0] ?? null;
                $jourDate = $ts;
            }
        }
        $jourDoc = $jour ? Fiches::get((int) $jour['id']) : null;
        $legends = self::legends();
        $vars = [
            'slides' => $slides,
            'palmares' => array_map(fn ($p) => Collections::loc($p, ['title']), Collections::get('palmares', self::defaultPalmares())),
            'counters' => self::counters(),
            'jour' => $jour,
            'jourDoc' => $jourDoc,
            'jourLabel' => Site::dayMonth($jourDate),
            'photoDay' => self::photoOfDay(),
            'ago' => self::yearsAgo([5, 10, 20, 30]),
            'chiffre' => Settings::get('home.daily_figure', true) ? \App\Services\Chiffres::daily() : null,
            'teaser' => Settings::get('home.teaser', true) && is_file(self::TEASER . '.mp4'),
            'shop' => \App\Shop\ShopPages::homePicks(4),
            'eras' => array_map(function ($e) {
                $e = Collections::loc($e, ['name', 'text']);
                $e['facts'] = array_map(fn ($f) => Collections::loc($f, ['t']), $e['facts'] ?? []);
                return $e;
            }, Collections::get('epoques', self::defaultEras())),
            'reserves' => self::reserves(),
            'legends' => $legends,
            'teasers' => Collections::get('teasers', self::defaultTeasers()),
            'decades' => self::decadeLinks(),
        ];
        self::fillEmpty($vars);
        $introTitle = trim((string) Settings::get('home.intro_title', ''));
        $introText = plain_text((string) Settings::get('home.intro_text', ''));
        $defaultDesc = t("Le musée en ligne {club_de} : près d'un siècle de matchs, de joueurs, de supporters et de symboles, rassemblés par {site}.");
        return self::render('home', $vars, [
            'title' => '',
            'full_title' => $introTitle !== '' && !\App\Services\I18n::isEn() ? $introTitle : null,
            'description' => $introText !== '' && !\App\Services\I18n::isEn() ? preg_replace('/\s+/u', ' ', $introText) : $defaultDesc,
            'active' => 'accueil',
            'jsonld' => [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => Settings::get('general.site_name', \App\Core\Club::s('site')),
                'url' => base_url() . '/',
                'potentialAction' => ['@type' => 'SearchAction', 'target' => base_url() . '/recherche/?q={q}', 'query-input' => 'required name=q'],
            ],
            'scripts' => ['js/home.js'],
        ]);
    }

    /**
     * Taille minimale (largeur, hauteur en pixels) d'une photo tirée au hasard pour le grand slider :
     * plein écran (jusqu'à 1 920 × 840) avec un léger zoom, une photo plus petite y paraît floue.
     * Valeur de départ ; réglable dans Accueil & bandeau › Grand slider (slideOpts).
     */
    public static array $slideMin = [1200, 600];

    /** Réglages du tirage (Accueil & bandeau › Grand slider), avec leurs valeurs de départ. */
    public static function slideOpts(): array
    {
        $o = (array) (Collections::get('slider', [])['opts'] ?? []);
        $d = ['min_w' => self::$slideMin[0], 'min_h' => self::$slideMin[1], 'old_before' => 1980, 'old_min_w' => 600, 'old_min_h' => 380,
            'n_match' => 3, 'n_lion' => 1, 'n_other' => 1, 'n_old' => 2];
        foreach ($d as $k => $v) {
            $d[$k] = isset($o[$k]) && is_numeric($o[$k]) ? max(0, (int) $o[$k]) : $v;
        }
        return $d;
    }

    /** Année d'une fiche pour le slider : date du match, arrivée du joueur, sinon saison ; null si inconnue. */
    public static function slideYear(array $s): ?int
    {
        $y = (int) substr((string) ($s['m']['date'] ?? ''), 0, 4)
            ?: (int) ($s['p']['arrival'] ?? 0) ?: (int) ($s['p']['departure'] ?? 0)
            ?: (int) substr((string) ($s['m']['season'] ?? $s['season'] ?? ''), 0, 4);
        return $y >= 1900 ? $y : null;
    }

    /**
     * Photo assez grande pour le grand slider (dimensions de la médiathèque). Une fiche ancienne
     * (avant l'année réglée) a droit à une taille plus petite : les photos d'époque sont rarement grandes.
     */
    public static function slideReady(?string $image, ?int $year = null): bool
    {
        $m = $image ? Media::get($image) : null;
        $o = self::slideOpts();
        [$w, $h] = $year !== null && $year < $o['old_before'] ? [$o['old_min_w'], $o['old_min_h']] : [$o['min_w'], $o['min_h']];
        return (int) ($m['width'] ?? 0) >= $w && (int) ($m['height'] ?? 0) >= $h;
    }

    /** Genre d'une fiche pour l'équilibre du slider : match, lion (personne) ou autre. */
    private static function slideKind(array $s): string
    {
        return isset($s['m']) || ($s['type'] ?? '') === 'match' ? 'match' : (isset($s['p']) || ($s['type'] ?? '') === 'personne' ? 'lion' : 'other');
    }

    /**
     * Fiches que le tirage au hasard peut montrer : vraie photo, assez grande (selon l'époque).
     * Recalculé quand les fiches, la médiathèque ou les réglages changent.
     * @return list<array{id:int,k:string,old:bool}>
     */
    public static function slidePool(): array
    {
        $o = self::slideOpts();
        return \App\Core\Memo::get('slider-tirage-v2', [Index::CACHE, Media::FILE, __FILE__, DATA_PATH . '/collections/slider.json'], json_encode($o), function () use ($o) {
            $out = [];
            foreach (Index::published() as $s) {
                if (!$s['image'] || Index::isPlaceholderImage($s['image'])) {
                    continue;
                }
                $y = self::slideYear($s);
                if (self::slideReady($s['image'], $y)) {
                    $out[] = ['id' => (int) $s['id'], 'k' => self::slideKind($s), 'old' => $y !== null && $y < $o['old_before']];
                }
            }
            return $out;
        });
    }

    /**
     * Slider : N fiches tirées au hasard parmi celles dont la photo est assez grande, avec au moins
     * le nombre réglé de matchs, de lions, d'autres fiches et de fiches anciennes (s'il y en a) ;
     * ou la sélection manuelle du back-office.
     */
    public static function slides(int $n): array
    {
        $conf = Collections::get('slider', ['mode' => 'random', 'ids' => []]);
        $n = max(1, $n);
        $pool = [];
        if (($conf['mode'] ?? 'random') === 'manual' && !empty($conf['ids'])) {
            foreach ($conf['ids'] as $id) {
                $s = Index::get((int) $id);
                if ($s && Index::visible($s)) {
                    $pool[] = $s;
                }
            }
        } else {
            $o = self::slideOpts();
            $cands = self::slidePool();
            shuffle($cands);
            // Fiches déjà montrées à ce visiteur (cookie « sl ») : reléguées en fin de tirage, pour varier d'une visite à l'autre.
            $seen = array_flip(array_map('intval', array_filter(explode('.', (string) ($_COOKIE['sl'] ?? '')), 'ctype_digit')));
            if ($seen) {
                usort($cands, fn ($a, $b) => isset($seen[$a['id']]) <=> isset($seen[$b['id']]));
            }
            $picked = [];
            $take = function (callable $ok, int $count) use (&$cands, &$picked, $n): void {
                foreach ($cands as $i => $c) {
                    if ($count <= 0 || count($picked) >= $n) {
                        return;
                    }
                    if ($ok($c)) {
                        $s = Index::get($c['id']);
                        unset($cands[$i]);
                        if ($s && Index::visible($s)) {
                            $picked[] = $c + ['s' => $s];
                            $count--;
                        }
                    }
                }
            };
            $has = function (string $k) use (&$picked): int {
                return count(array_filter($picked, fn ($p) => $p['k'] === $k));
            };
            // D'abord les anciennes, en respectant l'équilibre des genres, puis les quotas, puis le reste.
            $take(fn ($c) => $c['old'] && $c['k'] === 'match', min($o['n_old'], $o['n_match']));
            $take(fn ($c) => $c['old'], $o['n_old'] - count(array_filter($picked, fn ($p) => $p['old'])));
            foreach (['match' => $o['n_match'], 'lion' => $o['n_lion'], 'other' => $o['n_other']] as $k => $min) {
                $take(fn ($c) => $c['k'] === $k, $min - $has($k));
            }
            $take(fn ($c) => true, $n);
            shuffle($picked);
            $pool = array_column($picked, 's');
            // Le cookie « sl » est posé par home.js (data-sl de l'accueil) : un Set-Cookie ici empêcherait
            // le cache des pages de garder l'accueil.
            if (!$pool) {
                $pool = array_values(array_filter(Index::published(), fn ($s) => $s['image'] && !Index::isPlaceholderImage($s['image'])));
                shuffle($pool);
            }
        }
        $out = [];
        foreach (array_slice($pool, 0, $n) as $s) {
            $out[] = [
                'kind' => self::kindLabel($s),
                'title' => self::shortTitle($s),
                'text' => self::slideText($s),
                'href' => url($s['path']),
                'image' => $s['image'],
                'focus' => '50% 25%',
                'id' => (int) $s['id'],
            ];
        }
        return $out;
    }

    private static function slideText(array $s): string
    {
        if (isset($s['m'])) {
            $m = Derived::match($s['id']);
            $parts = array_filter([$s['m']['label'] ?: $s['m']['competition'], $s['m']['date'] ? date_fr($s['m']['date']) : null, $s['m']['stadium']]);
            return implode(' · ', $parts) . ($m && $m['us'] !== null ? '' : '');
        }
        if (isset($s['p'])) {
            $years = array_filter([$s['p']['arrival'], $s['p']['departure']]);
            $role = $s['p']['position'] ?: (in_array('entraineur', $s['p']['roles'], true) ? t('Entraîneur') : '');
            $line = trim(($role ? ucfirst($role) : '') . ($years ? ' · ' . t('au club') . ' ' . implode('–', array_unique($years)) : ''), ' ·');
            return $s['p']['subtitle'] ?: ($line ?: $s['excerpt']);
        }
        return $s['excerpt'];
    }

    /** Titre court pour l'affichage (le titre complet d'un match est long). */
    public static function shortTitle(array $s): string
    {
        if (isset($s['m'])) {
            if (!$s['m']['home'] && $s['m']['event']) {
                return $s['m']['event'];
            }
            // Match sans Sochaux, rangé pour son événement (« Inauguration du nouveau Stade Bonal ») :
            // l'événement d'abord, l'affiche ensuite.
            $round = trim((string) ($s['m']['round'] ?? ''));
            if (!\App\Core\Club::isUs((string) $s['m']['home']) && !\App\Core\Club::isUs((string) $s['m']['away']) && mb_strlen($round) >= 12 && str_contains($round, ' ') && !preg_match('/^(J\d|\d|journ|tour|finale|demi|quart|huiti|seizi|poule|groupe)/iu', $round)) {
                return $round . ' (' . $s['m']['home'] . ' – ' . $s['m']['away'] . ')';
            }
            $score = $s['m']['sh_score'] ? ' ' . $s['m']['sh_score'][0] . '-' . $s['m']['sh_score'][1] : '';
            return $s['m']['home'] . ' – ' . $s['m']['away'] . $score;
        }
        if (isset($s['p'])) {
            return $s['p']['name'];
        }
        return $s['title'];
    }

    public static function kindLabel(array $s): string
    {
        if ($s['type'] === 'match') {
            return t('Matchs');
        }
        if ($s['type'] === 'personne') {
            return t(\App\Core\Club::content('libelles', [])['nos_lions'] ?? 'Nos joueurs');
        }
        foreach ($s['categories'] as $c) {
            $root = Categories::root($c);
            if (in_array($root, ['infrastructures', 'symboles', 'supporters'], true)) {
                return t(Categories::label($root));
            }
        }
        return t('Le musée');
    }

    public static function defaultPalmares(): array
    {
        // Palmarès résumé du club (config/clubs/<id>/accueil-palmares.php), puis l'année de fondation.
        $list = (array) \App\Core\Club::content('accueil-palmares', []);
        if (\App\Core\Club::s('founded') !== '') {
            $list[] = ['years' => \App\Core\Club::s('founded'), 'title' => 'Année de fondation'];
        }
        return $list;
    }

    public static function counters(): array
    {
        return [
            ['n' => (string) count(Index::published('match')), 'label' => t('matchs'), 'text' => t('renseignés dans la base de données de {site}.')],
            ['n' => (string) Site::count(Site::C_JOUEURS), 'label' => t('joueurs'), 'text' => t('renseignés dans la base de données de {site}.')],
            ['n' => (string) Settings::get('home.counter_community', '11000+'), 'label' => t('membres'), 'text' => t('dans la communauté {site}, tous réseaux sociaux confondus.')],
            ['n' => (string) Settings::get('home.counter_videos', '1400+'), 'label' => t('vidéos'), 'text' => t('sur la chaîne YouTube de {site}.')],
        ];
    }

    public static function defaultEras(): array
    {
        // Grandes époques du club (config/clubs/<id>/accueil-epoques.php) ; aucune pour un musée neuf.
        return (array) \App\Core\Club::content('accueil-epoques', []);
    }

    /** Tuiles « Les réserves du musée » (6 collections d'objets). */
    public static function reserves(): array
    {
        $conf = array_map(fn ($r) => Collections::loc($r, ['name', 'desc']), Collections::get('reserves', self::defaultReserves()));
        foreach ($conf as &$r) {
            $r['href'] = url('/reserves/' . $r['slug'] . '/');
            if (empty($r['image'])) {
                foreach (Index::published('objet') as $o) {
                    if (($o['o']['collection'] ?? '') === $r['slug'] && $o['image']) {
                        $r['image'] = $o['image'];
                        break;
                    }
                }
            }
            $r['count'] = count(array_filter(Index::published('objet'), fn ($o) => ($o['o']['collection'] ?? '') === $r['slug']));
        }
        return $conf;
    }

    public static function defaultReserves(): array
    {
        // Images par défaut propres au club (sinon : premier objet de la collection, voir reserves()).
        $img = (array) (\App\Core\Club::content('accueil-images', [])['reserves'] ?? []);
        $list = [
            ['slug' => 'maillots', 'name' => 'Maillots', 'desc' => 'tenues portées'],
            ['slug' => 'affiches', 'name' => 'Affiches', 'desc' => 'matchs & tournois'],
            ['slug' => 'programmes', 'name' => 'Programmes', 'desc' => 'feuilles de match'],
            ['slug' => 'photos', 'name' => 'Photos', 'desc' => "d'équipe & de match"],
            ['slug' => 'presse', 'name' => 'Presse', 'desc' => 'coupures & unes'],
            ['slug' => 'supporters', 'name' => 'Supporters', 'desc' => 'écharpes, billets, fanions'],
        ];
        foreach ($list as &$r) {
            $r['image'] = $img[$r['slug']] ?? '';
        }
        return $list;
    }

    /**
     * Cases de l'accueil restées sans image (« Ce jour-là », « Il y a N ans », photo du jour, grand slider) :
     * photos par défaut du club (clé « cases » de config/clubs/<club>/accueil-images.php), tirées selon
     * le jour, avec leur crédit. Sans cette clé (Sochaux), rien ne change.
     */
    private static function fillEmpty(array &$vars): void
    {
        $conf = (array) (\App\Core\Club::content('accueil-images', [])['cases'] ?? []);
        $pool = array_values(array_filter((array) ($conf['photos'] ?? []), fn ($r) => \App\Data\Media::file((string) $r)));
        if (!$pool) {
            return;
        }
        $i = crc32(date('Y-m-d')) % count($pool);
        $next = function () use (&$i, $pool): string {
            return $pool[$i++ % count($pool)];
        };
        $credit = fn (string $rel) => (string) (\App\Data\Media::get($rel)['credit'] ?? '');
        if (!empty($vars['jour']) && empty($vars['jour']['image'])) {
            $r = $next();
            $vars['jour']['image'] = $r;
            $vars['jour']['image_credit'] = $credit($r);
        }
        foreach ($vars['ago'] as &$a) {
            if (empty($a['image'])) {
                $r = $next();
                $a['image'] = $r;
                $a['image_credit'] = $credit($r);
            }
        }
        unset($a);
        if (empty($vars['photoDay'])) {
            $r = $next();
            $m = (array) \App\Data\Media::get($r);
            $vars['photoDay'] = ['image' => $r, 'caption' => (string) ($m['title'] ?? ''), 'credit' => $credit($r), 'year' => (int) ($m['year'] ?? 0), 'href' => null];
        }
        if (empty($vars['slides'])) {
            foreach ((array) ($conf['slider'] ?? []) as $s) {
                if (\App\Data\Media::file((string) ($s['image'] ?? ''))) {
                    $vars['slides'][] = ['kind' => t((string) ($s['kind'] ?? '')), 'title' => t((string) ($s['title'] ?? '')), 'text' => t((string) ($s['text'] ?? '')),
                        'href' => url((string) ($s['href'] ?? '/')), 'image' => $s['image'], 'focus' => (string) ($s['focus'] ?? '50% 50%'), 'credit' => $credit($s['image'])];
                }
            }
        }
    }

    /** Images des encarts de l'accueil (« Jouez avec l'histoire », « Contribuer »), modifiables dans le back-office. */
    public static function defaultTeasers(): array
    {
        return (array) (\App\Core\Club::content('accueil-images', [])['teasers'] ?? []);
    }

    /** « Ils ont porté le lion » : fiches marquées « légende » (proposition automatique sinon). */
    public static function legends(int $n = 4): array
    {
        $list = array_values(array_filter(Index::published('personne'), fn ($s) => $s['p']['legend'] && $s['image']));
        if (count($list) < $n) {
            // Proposition par défaut (à valider dans le back-office) : les plus capés avec photo.
            $tot = Derived::part('person_totals');
            $cands = array_values(array_filter(Index::published('personne'), fn ($s) => $s['image'] && in_array('joueur', $s['p']['roles'], true) && !$s['p']['legend']));
            usort($cands, fn ($a, $b) => ($tot[$b['id']]['matches'] ?? 0) <=> ($tot[$a['id']]['matches'] ?? 0));
            $list = array_merge($list, array_slice($cands, 0, $n - count($list)));
        }
        return array_slice($list, 0, $n);
    }

    private static function decadeLinks(): array
    {
        $out = [];
        foreach (Categories::children(Site::C_MATCHS) as $c) {
            if (preg_match('/^annees-(\d+)/', $c['slug'], $m) && Site::count($c['slug']) > 0) {
                $full = strlen($m[1]) === 2 ? '19' . $m[1] : $m[1];
                $out[(int) $full] = ['label' => $full, 'href' => url($c['path'])];
            }
        }
        ksort($out);
        return array_values($out);
    }

    // ------------------------------------------------------------------ adresses

    public static function byPath(Request $req): ?Response
    {
        $s = Index::byPath($req->path);
        if ($s) {
            $doc = Fiches::get($s['id']);
            if (!$doc) {
                return null;
            }
            $preview = isset($req->query['apercu']) && \App\Core\Auth::user();
            if (!Fiches::isVisible($doc) && !$preview) {
                return null;
            }
            // Ancienne page WordPress faite d'une simple liste d'articles : sa rubrique la remplace.
            $cat = isset($doc['listing']['category']) && !self::hasContent($doc) ? Categories::get($doc['listing']['category']) : null;
            if ($cat && !empty($cat['path']) && $cat['path'] !== $req->path) {
                return Response::redirect(url($cat['path']), 301);
            }
            return Fiche::show($req, $doc);
        }
        if ($req->path === self::teamPath()) {
            return self::team();
        }
        // Sous-rubrique calculée « Match officiel » (liste livrée avec le code, musées qui en ont une).
        if ($req->path === \App\Data\OfficialPlayers::PATH && ($oc = \App\Data\OfficialPlayers::category())) {
            return Mosaic::show($req, $oc);
        }
        $cat = Categories::byPath($req->path);
        if ($cat) {
            return Mosaic::show($req, $cat);
        }
        // Rubriques racines sans catégorie propre
        // (absentes d'un musée tout juste créé, avant que ses rubriques soient générées)
        if ($req->path === '/nos-lions/' && ($c = Categories::get(Site::C_LIONS))) {
            return Mosaic::show($req, $c);
        }
        if ($req->path === '/matchs/' && ($c = Categories::get(Site::C_MATCHS))) {
            return Mosaic::show($req, $c);
        }
        return null;
    }

    /**
     * Adresse de Sochaux, gardée pour compatibilité : utiliser teamPath(), qui suit le club servi.
     * @deprecated
     */
    public const TEAM_PATH = '/supporters/l-equipe-de-sochaux-retro/';

    /**
     * Rubrique héritée de WordPress, remplacée par les vignettes de l'équipe (saisies côté association) :
     * clé « team_path » du profil du club, sinon l'adresse des musées neufs (modèle de rubriques).
     */
    public static function teamPath(): string
    {
        return \App\Core\Club::s('team_path') ?: '/supporters/l-equipe-du-musee/';
    }

    /** L'équipe de Sochaux Rétro en cartes à collectionner. */
    public static function team(): Response
    {
        return self::render('equipe', [
            'team' => \App\Vitrine\Content::team(),
            'joinUrl' => \App\Vitrine\Host::abs('/nous-soutenir/benevolat/'),
        ], [
            'title' => t('L’équipe de {site}'),
            'description' => t('Les bénévoles qui font vivre le musée {site}, en vignettes à collectionner : mission et anecdote de chacun.'),
            'active' => 'supporters',
            'styles' => ['css/explore.css', 'css/chiffres.css', 'css/team.css'],
            'scripts' => ['js/team.js'],
        ]);
    }

    /** La fiche a-t-elle un contenu propre (texte, images, vidéos, tableaux) ? */
    /** Ancienne page-liste WordPress remplacée par sa rubrique (redirigée, donc absente du plan du site). */
    public static function isListingRedirect(array $doc): bool
    {
        return isset($doc['listing']['category']) && !self::hasContent($doc);
    }

    private static function hasContent(array $doc): bool
    {
        foreach ($doc['sections'] ?? [] as $s) {
            if (trim(strip_tags((string) ($s['html'] ?? ''))) !== '' || str_contains((string) ($s['html'] ?? ''), '<img')) {
                return true;
            }
        }
        return trim(strip_tags((string) ($doc['intro'] ?? ''))) !== ''
            || !empty($doc['gallery']) || !empty($doc['images']) || !empty($doc['videos'])
            || !empty($doc['embeds']) || !empty($doc['tables']);
    }

    // ------------------------------------------------------------------ recherche

    public static function search(Request $req): Response
    {
        $q = trim(mb_substr($req->str('q'), 0, 120));
        // Une seule adresse ne peut pas occuper tous les processus avec des recherches en rafale.
        if ($q !== '' && !\App\Core\RateLimiter::hit('recherche', $req->ip(), 30, 60)) {
            return new Response(t('Trop de recherches d’affilée : patientez une minute.'), 429, ['Content-Type' => 'text/plain; charset=UTF-8', 'Retry-After' => '60']);
        }
        $type = in_array($req->str('type'), ['match', 'personne', 'article', 'page', 'objet', 'moment'], true) ? $req->str('type') : null;
        $page = max(1, (int) $req->str('page', '1'));
        $per = 20;
        $r = \App\Services\Search::query($q, $type, $per, ($page - 1) * $per);
        $pages = max(1, (int) ceil($r['total'] / $per));
        $base = url('/recherche/');
        $qs = fn (array $p) => \App\Front\Mosaic::qs(array_replace(['q' => $q, 'type' => $type], $p));
        // Raccourcis (saison, face-à-face) tirés de la même recherche, sans la refaire.
        $suggest = $q !== '' ? array_slice(\App\Services\Search::shortcuts($q, $r), 0, 3) : [];
        return self::render('search', [
            'q' => $q,
            'r' => $r,
            'type' => $type,
            'pageNum' => $page,
            'pages' => $pages,
            'base' => $base,
            'qs' => $qs,
            'shortcuts' => $suggest,
        ], [
            'title' => $q !== '' ? t('Recherche') . ' : ' . $q : t('Recherche'),
            'description' => t('Rechercher un match, un joueur, une saison dans le musée en ligne {club_de}.'),
            'noindex' => true,
            'body_class' => 'page-search',
            'styles' => ['css/mosaic.css'],
        ]);
    }

    // ------------------------------------------------------------------ attente, accès, erreurs

    /** Page d'attente. $teaser : aperçu de l'équipe avec le teaser, même s'il n'est pas encore activé. */
    public static function waiting(bool $teaser = false): Response
    {
        $html = View::render('waiting', [
            'logo' => (string) Settings::get('waiting.logo', ''),
            'title' => (string) Settings::get('waiting.title', ''),
            'text' => (string) Settings::get('waiting.text', ''),
            'countdown' => (bool) Settings::get('waiting.countdown', false),
            'countdownDate' => (string) Settings::get('waiting.countdown_date', ''),
            'countdownLabel' => (string) Settings::get('waiting.countdown_label', ''),
            'social' => (bool) Settings::get('waiting.show_social', true),
            'teaser' => ($teaser || Settings::get('waiting.teaser', false)) && is_file(self::TEASER . '.mp4'),
            'app' => (bool) Settings::get('app.enabled', true),
            'push' => Settings::get('app.push_waiting', true) && \App\Services\Notifications::enabled(),
        ]);
        return new Response($html, 503, ['Content-Type' => 'text/html; charset=UTF-8', 'Retry-After' => '3600', 'Cache-Control' => 'no-store', 'X-Robots-Tag' => 'noindex, nofollow']);
    }

    /** Teaser vidéo (1 min 55) et son image d'attente, hors de public/ : jamais servis directement. */
    private const TEASER = APP_DIR . '/Resources/video/teaser';

    /**
     * /video/teaser.mp4 et .jpg : pour le public, seulement quand le teaser est montré (page
     * d'attente, ou accueil une fois le site ouvert) ; pour l'équipe connectée, toujours (aperçu) ;
     * sinon introuvables, pour garder la surprise.
     */
    /** Le teaser est-il montré au public sur le site ouvert (case « Teaser » d'Éditorial › Accueil) ? */
    public static function teaserPublic(): bool
    {
        return (bool) Settings::get('home.teaser', true) && is_file(self::TEASER . '.mp4');
    }

    public static function teaser(Request $req, string $path): ?Response
    {
        $jpg = str_ends_with($path, '.jpg');
        $file = self::TEASER . ($jpg ? '.jpg' : '.mp4');
        $on = (bool) Settings::get('waiting.teaser', false) || (!Seo::closed() && self::teaserPublic());
        if (!is_file($file) || (!$on && !\App\Core\Auth::user())) {
            return null;
        }
        $res = Response::media($file, $jpg ? 'image/jpeg' : 'video/mp4', $req->server['HTTP_RANGE'] ?? null);
        $res->headers['Cache-Control'] = $on ? 'public, max-age=86400' : 'private, no-store';
        $res->headers['X-Content-Type-Options'] = 'nosniff';
        return $res;
    }

    /** /teaser/ : la vidéo de présentation du musée (lien du pied de page). */
    public static function teaserPage(Request $req): Response
    {
        if (!is_file(self::TEASER . '.mp4') || (!self::teaserPublic() && !\App\Core\Auth::user())) {
            return self::notFound(); // décoché : la page reste visible par l'équipe connectée (aperçu)
        }
        return self::render('teaser', [], [
            'title' => t('Le teaser du musée'),
            'description' => t('Deux minutes pour découvrir le musée : près d’un siècle de matchs, de joueurs et de souvenirs {club_de}.'),
            'active' => '',
        ]);
    }

    /** Mot de passe d'accès au site public (pré-lancement). */
    /** Le visiteur a déjà saisi le mot de passe d'accès (pages et appels de l'API). */
    public static function gateOpen(string $password): bool
    {
        return Session::get('front_ok') === hash('sha256', $password);
    }

    public static function gate(Request $req, string $password): ?Response
    {
        if (self::gateOpen($password)) {
            return null;
        }
        $error = '';
        if ($req->method === 'POST' && isset($req->post['front_password'])) {
            if (\App\Core\RateLimiter::hit('gate', $req->ip(), 20, 600) && hash_equals($password, (string) $req->post['front_password'])) {
                Session::set('front_ok', hash('sha256', $password));
                return Response::redirect($req->path);
            }
            $error = t('Mot de passe incorrect.');
        }
        return new Response(View::render('gate', ['error' => $error]), 401, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function notFound(): Response
    {
        $res = self::render('errors/404', [], ['title' => t('Page introuvable'), 'noindex' => true]);
        $res->status = 404;
        return $res;
    }
}
