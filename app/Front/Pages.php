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
            'eras' => array_map(function ($e) {
                $e = Collections::loc($e, ['name', 'text']);
                $e['facts'] = array_map(fn ($f) => Collections::loc($f, ['t']), $e['facts'] ?? []);
                return $e;
            }, Collections::get('epoques', self::defaultEras())),
            'reserves' => self::reserves(),
            'legends' => $legends,
            'teasers' => Collections::get('teasers', []),
            'decades' => self::decadeLinks(),
        ];
        $introTitle = trim((string) Settings::get('home.intro_title', ''));
        $introText = plain_text((string) Settings::get('home.intro_text', ''));
        $defaultDesc = t("Le musée en ligne du FC Sochaux-Montbéliard : près d'un siècle de matchs, de joueurs, de supporters et de symboles, rassemblés par Sochaux Rétro.");
        return self::render('home', $vars, [
            'title' => '',
            'full_title' => $introTitle !== '' && !\App\Services\I18n::isEn() ? $introTitle : null,
            'description' => $introText !== '' && !\App\Services\I18n::isEn() ? preg_replace('/\s+/u', ' ', $introText) : $defaultDesc,
            'active' => 'accueil',
            'jsonld' => [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => Settings::get('general.site_name', 'Sochaux Rétro'),
                'url' => base_url() . '/',
                'potentialAction' => ['@type' => 'SearchAction', 'target' => base_url() . '/recherche/?q={q}', 'query-input' => 'required name=q'],
            ],
            'scripts' => ['js/home.js'],
        ]);
    }

    /** Slider : N fiches « À la une » tirées au hasard (avec image), ou la sélection manuelle du back-office. */
    public static function slides(int $n): array
    {
        $conf = Collections::get('slider', ['mode' => 'random', 'ids' => []]);
        $pool = [];
        if (($conf['mode'] ?? 'random') === 'manual' && !empty($conf['ids'])) {
            foreach ($conf['ids'] as $id) {
                $s = Index::get((int) $id);
                if ($s && Index::visible($s)) {
                    $pool[] = $s;
                }
            }
        } else {
            $pool = array_values(array_filter(Index::published(), fn ($s) => $s['a_la_une'] && $s['image']));
            shuffle($pool);
        }
        $out = [];
        foreach (array_slice($pool, 0, max(1, $n)) as $s) {
            $out[] = [
                'kind' => self::kindLabel($s),
                'title' => self::shortTitle($s),
                'text' => self::slideText($s),
                'href' => url($s['path']),
                'image' => $s['image'],
                'focus' => '50% 25%',
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
            return t('Nos Lions');
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
        return [
            ['years' => '2×', 'title' => 'Champion de France'],
            ['years' => '2×', 'title' => 'Coupe de France'],
            ['years' => '1×', 'title' => 'Coupe de la Ligue'],
            ['years' => '1928', 'title' => 'Année de fondation'],
        ];
    }

    public static function counters(): array
    {
        return [
            ['n' => (string) count(Index::published('match')), 'label' => t('matchs'), 'text' => t('renseignés dans la base de données de Sochaux Rétro.')],
            ['n' => (string) Site::count(Site::C_JOUEURS), 'label' => t('joueurs'), 'text' => t('renseignés dans la base de données de Sochaux Rétro.')],
            ['n' => (string) Settings::get('home.counter_community', '11000+'), 'label' => t('membres'), 'text' => t('dans la communauté Sochaux Rétro, tous réseaux sociaux confondus.')],
            ['n' => (string) Settings::get('home.counter_videos', '1400+'), 'label' => t('vidéos'), 'text' => t('sur la chaîne YouTube de Sochaux Rétro.')],
        ];
    }

    public static function defaultEras(): array
    {
        return [
            ['range' => '1928–1945', 'name' => 'Les pionniers', 'text' => "Fondé avec le soutien de Peugeot, le club adopte très tôt le professionnalisme et s'impose parmi les meilleurs de France avant-guerre.", 'image' => null, 'href' => '/interactif/frise/#1928', 'facts' => [['y' => '1928', 't' => 'Fondation du club'], ['y' => '1935', 't' => 'Premier titre de champion de France'], ['y' => '1937', 't' => 'Coupe de France'], ['y' => '1938', 't' => 'Second titre de champion']]],
            ['range' => '1946–1970', 'name' => "L'après-guerre", 'text' => "Le club se reconstruit et forme ses propres joueurs : le centre de formation sochalien commence à faire parler de lui.", 'image' => null, 'href' => '/interactif/frise/#1946', 'facts' => [['y' => '1955', 't' => 'Coupe Gambardella'], ['y' => '1963', 't' => 'Coupe Gambardella'], ['y' => '1964', 't' => 'Coupe Gambardella']]],
            ['range' => '1971–1990', 'name' => "L'âge d'or de Bonal", 'text' => "Une génération formée au club porte Sochaux jusqu'en demi-finale européenne et fait vibrer le stade Bonal.", 'image' => null, 'href' => '/interactif/frise/#1971', 'facts' => [['y' => '1981', 't' => 'Demi-finale de Coupe UEFA'], ['y' => '1983', 't' => 'Coupe Gambardella'], ['y' => '1988', 't' => 'Finale de Coupe de France']]],
            ['range' => '1991–2010', 'name' => 'Le renouveau', 'text' => "Retour au premier plan et nouveaux trophées : le Lion rugit de nouveau au Stade de France.", 'image' => null, 'href' => '/interactif/frise/#1991', 'facts' => [['y' => '2004', 't' => 'Coupe de la Ligue'], ['y' => '2007', 't' => 'Coupe de France'], ['y' => '2007', 't' => 'Coupe Gambardella']]],
            ['range' => '2011–auj.', 'name' => "L'époque récente", 'text' => "Des hauts, des bas, et une fidélité intacte des supporters jaune et bleu.", 'image' => null, 'href' => '/interactif/frise/#2011', 'facts' => [['y' => '2028', 't' => 'Centenaire du club']]],
        ];
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
        return [
            ['slug' => 'maillots', 'name' => 'Maillots', 'desc' => 'tenues portées', 'image' => null],
            ['slug' => 'affiches', 'name' => 'Affiches', 'desc' => 'matchs & tournois', 'image' => null],
            ['slug' => 'programmes', 'name' => 'Programmes', 'desc' => 'feuilles de match', 'image' => null],
            ['slug' => 'photos', 'name' => 'Photos', 'desc' => "d'équipe & de match", 'image' => null],
            ['slug' => 'presse', 'name' => 'Presse', 'desc' => 'coupures & unes', 'image' => null],
            ['slug' => 'supporters', 'name' => 'Supporters', 'desc' => 'écharpes, billets, fanions', 'image' => null],
        ];
    }

    /** « Ils ont porté le lion » : fiches marquées « légende » (proposition automatique sinon). */
    public static function legends(int $n = 4): array
    {
        $list = array_values(array_filter(Index::published('personne'), fn ($s) => $s['p']['legend'] && $s['image']));
        if (count($list) < $n) {
            // Proposition par défaut (à valider dans le back-office) : les plus capés avec photo.
            $tot = Derived::get()['person_totals'];
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
            return Fiche::show($req, $doc);
        }
        $cat = Categories::byPath($req->path);
        if ($cat) {
            return Mosaic::show($req, $cat);
        }
        // Rubriques racines sans catégorie propre
        if ($req->path === '/nos-lions/') {
            return Mosaic::show($req, Categories::get(Site::C_LIONS));
        }
        if ($req->path === '/matchs/') {
            return Mosaic::show($req, Categories::get(Site::C_MATCHS));
        }
        return null;
    }

    // ------------------------------------------------------------------ recherche

    public static function search(Request $req): Response
    {
        $q = trim(mb_substr($req->str('q'), 0, 120));
        $type = in_array($req->str('type'), ['match', 'personne', 'article', 'page', 'objet', 'moment'], true) ? $req->str('type') : null;
        $page = max(1, (int) $req->str('page', '1'));
        $per = 20;
        $r = \App\Services\Search::query($q, $type, $per, ($page - 1) * $per);
        $pages = max(1, (int) ceil($r['total'] / $per));
        $base = url('/recherche/');
        $qs = fn (array $p) => \App\Front\Mosaic::qs(array_filter(['q' => $q, 'type' => $type] + $p, fn ($v) => $v !== null));
        $suggest = $q !== '' ? array_slice(array_values(array_filter(\App\Services\Search::suggest($q), fn ($x) => in_array($x['type'], [t('Saison'), t('Face-à-face')], true))), 0, 3) : [];
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
            'description' => t('Rechercher un match, un joueur, une saison dans le musée en ligne du FC Sochaux-Montbéliard.'),
            'noindex' => true,
            'body_class' => 'page-search',
            'styles' => ['css/mosaic.css'],
        ]);
    }

    // ------------------------------------------------------------------ attente, accès, erreurs

    public static function waiting(): Response
    {
        $html = View::render('waiting', [
            'logo' => (string) Settings::get('waiting.logo', ''),
            'title' => (string) Settings::get('waiting.title', ''),
            'text' => (string) Settings::get('waiting.text', ''),
            'countdown' => (bool) Settings::get('waiting.countdown', false),
            'countdownDate' => (string) Settings::get('waiting.countdown_date', ''),
            'countdownLabel' => (string) Settings::get('waiting.countdown_label', ''),
            'social' => (bool) Settings::get('waiting.show_social', true),
        ]);
        return new Response($html, 503, ['Content-Type' => 'text/html; charset=UTF-8', 'Retry-After' => '3600']);
    }

    /** Mot de passe d'accès au site public (pré-lancement). */
    public static function gate(Request $req, string $password): ?Response
    {
        if (Session::get('front_ok') === hash('sha256', $password)) {
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
