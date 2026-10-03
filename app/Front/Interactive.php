<?php
declare(strict_types=1);

namespace App\Front;

use App\Core\JsonStore;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Data\Collections;
use App\Data\Derived;
use App\Data\Index;
use App\Data\Seeds;

/**
 * Rubrique INTERACTIF : quiz, album, maillots, frise, carto, centenaire (100 moments,
 * Onze de légende), réserves du musée. Contenus éditables dans le back-office
 * (collections), avec des contenus de départ « à valider ».
 */
final class Interactive
{
    private const VOTES = STORAGE_PATH . '/votes';

    public static function landing(Request $req): Response
    {
        return Pages::render('interactif/landing', ['groups' => Site::interactiveTools()], [
            'title' => t('Interactif : jouez et explorez l’histoire du FCSM'),
            'description' => t('Quiz, album de cartes, carte des stades et des origines, frise, comparateur de maillots, vote du Onze de légende : le musée Sochaux Rétro en version interactive.'),
            'active' => 'interactif',
            'styles' => ['css/interactif.css'],
        ]);
    }

    // ------------------------------------------------------------------ quiz

    public static function quiz(Request $req): Response
    {
        $all = Collections::get('quiz', Seeds::quiz());
        $pool = array_values(array_filter($all, fn ($q) => ($q['active'] ?? true) && !empty($q['q']) && count($q['a'] ?? []) >= 2));
        $pool = array_map(fn ($q) => Collections::loc($q, ['q', 'a', 'fact']), $pool);
        return Pages::render('interactif/quiz', ['pool' => $pool, 'count' => min(6, count($pool))], [
            'title' => t('Quiz : êtes-vous un vrai Lionceau ?'),
            'description' => t('Six questions sur l’histoire du FC Sochaux-Montbéliard. Testez vos connaissances et partagez votre score.'),
            'active' => 'interactif',
            'body_class' => 'page-quiz',
            'styles' => ['css/interactif.css'],
            'scripts' => ['js/interactif.js'],
        ]);
    }

    /** Statistiques anonymes des parties (score seulement). */
    public static function quizResult(Request $req): Response
    {
        if (!RateLimiter::hit('quiz', $req->ip(), 30, 3600)) {
            return Response::json(['ok' => false], 429);
        }
        $d = $req->json();
        $score = (int) ($d['score'] ?? -1);
        $total = (int) ($d['total'] ?? 0);
        if ($total < 1 || $total > 20 || $score < 0 || $score > $total) {
            return Response::json(['ok' => false], 422);
        }
        $stats = JsonStore::update(self::VOTES . '/quiz.json', function ($s) use ($score, $total) {
            $s = $s ?: ['games' => 0, 'scores' => []];
            $s['games']++;
            $s['scores']["$score/$total"] = ($s['scores']["$score/$total"] ?? 0) + 1;
            return $s;
        }, []);
        $better = 0;
        foreach ($stats['scores'] as $k => $n) {
            [$sc, $tt] = array_map('intval', explode('/', $k));
            if ($tt === $total && $sc < $score) {
                $better += $n;
            }
        }
        return Response::json(['ok' => true, 'games' => $stats['games'], 'better' => $stats['games'] ? round(100 * $better / $stats['games']) : 0]);
    }

    // ------------------------------------------------------------------ album

    /** Les cartes de l'album : sélection du back-office, sinon proposition automatique. */
    public static function albumCards(): array
    {
        $tot = Derived::get()['person_totals'] ?? [];
        $people = array_values(array_filter(Index::published('personne'), fn ($s) => !empty($s['p']['album']['in'])));
        if (!$people) {
            // Proposition (à valider) : légendes puis joueurs et entraîneurs les plus présents, avec photo.
            $cands = array_values(array_filter(Index::published('personne'), fn ($s) => $s['image']));
            usort($cands, fn ($a, $b) => ((int) $b['p']['legend'] <=> (int) $a['p']['legend'])
                ?: (($tot[$b['id']]['matches'] ?? 0) + ($tot[$b['id']]['coached'] ?? 0)) <=> (($tot[$a['id']]['matches'] ?? 0) + ($tot[$a['id']]['coached'] ?? 0)));
            $people = array_slice($cands, 0, 120);
        }
        usort($people, fn ($a, $b) => ($a['p']['album']['number'] ?? 9999) <=> ($b['p']['album']['number'] ?? 9999) ?: strcmp(Index::sortName($a), Index::sortName($b)));
        $current = Explore::currentSeason();
        $prevSeason = ((int) substr($current, 0, 4) - 1) . '-' . substr($current, 0, 4);
        $M = Derived::get()['matches'] ?? [];
        $lines = ['G' => t('Gardien'), 'D' => t('Défenseur'), 'M' => t('Milieu'), 'A' => t('Attaquant')];
        $out = [];
        foreach ($people as $i => $s) {
            $p = $s['p'];
            $last = $tot[$s['id']]['last'] ?? null;
            $lastSeason = $last ? ($M[$last]['season'] ?? null) : null;
            $tier = !empty($p['album']['rarity']) ? (string) $p['album']['rarity']
                : (!empty($p['legend']) || $i < 15 ? 'legende' : (in_array($lastSeason, [$current, $prevSeason], true) ? 'actuel' : 'classique'));
            $role = $p['roles'][0] ?? 'joueur';
            $out[] = [
                'id' => $s['id'],
                'n' => (int) ($p['album']['number'] ?? ($i + 1)),
                'name' => $p['name'],
                'pos' => $role === 'joueur' ? ($lines[$p['line'] ?? ''] ?? t('Joueur')) : Fiche::roleLabel(['roles' => $p['roles'], 'position' => $p['position'], 'subtitle' => $p['subtitle']]),
                'tier' => $tier,
                'href' => url($s['path']),
                'image' => $s['image'] ? img($s['image'], 320) : null,
            ];
        }
        return $out;
    }

    public static function album(Request $req): Response
    {
        $cards = self::albumCards();
        return Pages::render('interactif/album', ['cards' => $cards, 'total' => max(120, count($cards))], [
            'title' => t('L’album du centenaire : collectionnez les Lions'),
            'description' => t('Chaque fiche visitée débloque la carte d’un joueur. Un sans-faute au quiz en offre une rare.'),
            'active' => 'interactif',
            'styles' => ['css/interactif.css'],
            'scripts' => ['js/interactif.js'],
        ]);
    }

    // ------------------------------------------------------------------ maillots, frise

    public static function jerseys(Request $req): Response
    {
        $eras = array_map(fn ($e) => Collections::loc($e, ['label', 'text']), Collections::get('maillots', Seeds::maillots()));
        return Pages::render('interactif/maillots', ['eras' => array_values($eras)], [
            'title' => t('Maillots : un maillot, deux époques'),
            'description' => t('Comparez les maillots du FC Sochaux-Montbéliard d’une époque à l’autre.'),
            'active' => 'interactif',
            'styles' => ['css/interactif.css'],
            'scripts' => ['js/interactif.js'],
        ]);
    }

    public static function timeline(Request $req): Response
    {
        $events = array_map(fn ($e) => Collections::loc($e, ['title', 'text']), Collections::get('frise', Seeds::frise()));
        usort($events, fn ($a, $b) => (int) $a['year'] <=> (int) $b['year']);
        return Pages::render('interactif/frise', ['events' => $events], [
            'title' => t('La frise : de 1928 à aujourd’hui'),
            'description' => t('Les grandes dates du FC Sochaux-Montbéliard, de la fondation au centenaire.'),
            'active' => 'interactif',
            'styles' => ['css/interactif.css'],
            'scripts' => ['js/interactif.js'],
        ]);
    }

    // ------------------------------------------------------------------ carto

    public static function carto(Request $req): Response
    {
        return Pages::render('interactif/carto', [], [
            'title' => t('Carto : stades, origines, épopées et lieux du FCSM'),
            'description' => t('Une carte interactive : partout où le Lion a joué, d’où viennent nos joueurs, les grandes épopées et les lieux du club.'),
            'active' => 'interactif',
            'body_class' => 'page-carto',
            'bare' => false,
            'no_chat' => true,
            'styles' => ['vendor/leaflet/leaflet.css', 'css/carto.css'],
            'scripts' => ['vendor/leaflet/leaflet.js', 'vendor/topojson-client.min.js', 'js/carto.js'],
        ]);
    }

    /** Données de la carte (stades et matchs, origines, épopées, lieux). */
    public static function mapData(Request $req): Response
    {
        $cacheFile = STORAGE_PATH . '/cache/carte-' . \App\Services\I18n::lang() . '.json';
        $derivedFile = STORAGE_PATH . '/cache/derived.php';
        if (is_file($cacheFile) && (!is_file($derivedFile) || filemtime($cacheFile) >= filemtime($derivedFile)) && filemtime($cacheFile) > time() - 3600) {
            $res = new Response((string) file_get_contents($cacheFile), 200, ['Content-Type' => 'application/json; charset=UTF-8']);
            $res->headers['Cache-Control'] = 'public, max-age=600';
            return $res;
        }
        $d = Derived::get();
        $M = $d['matches'];
        $stades = [];
        foreach (Collections::get('stades', []) as $st) {
            $stades[$st['id']] = $st;
        }
        $places = [];
        foreach ($d['stades'] ?? [] as $key => $st) {
            $info = $stades[$key] ?? null;
            if (!$info || !isset($info['lat'], $info['lng']) || $info['lat'] === null) {
                continue;
            }
            $recs = [];
            foreach ($st['matches'] as $mid) {
                $x = $M[$mid] ?? null;
                if (!$x || !$x['v']) {
                    continue;
                }
                $recs[] = [
                    'y' => (int) substr((string) $x['date'], 0, 4),
                    'comp' => $x['comp'],
                    'res' => $x['result'] ?: '?',
                    'label' => Site::matchLabel($x),
                    'href' => url($x['path']),
                    'h' => $x['sh'] ? 1 : 0,
                ];
            }
            $places[] = ['id' => $key, 'name' => $info['name'], 'city' => $info['city'] ?? '', 'll' => [(float) $info['lat'], (float) $info['lng']], 'home' => $key === 'auguste-bonal', 'recs' => $recs];
        }
        // Origines : lieu de naissance géolocalisé
        $geo = Collections::get('geo', []);
        $people = [];
        $tot = $d['person_totals'] ?? [];
        foreach (Index::published('personne') as $s) {
            $p = $s['p'];
            $placeKey = trim(($p['birth_place'] ?? '') . '|' . ($p['birth_country'] ?? ''), '|');
            $g = $geo[$placeKey] ?? null;
            if (!$g || !isset($g['lat'])) {
                continue;
            }
            $role = $p['roles'][0] ?? 'joueur';
            $people[] = [
                'name' => $p['name'],
                'city' => $p['birth_place'] ?: ($g['city'] ?? ''),
                'country' => $p['birth_country'] ?: ($g['country'] ?? ''),
                'iso' => $g['iso'] ?? null,
                'll' => [(float) $g['lat'], (float) $g['lng']],
                'rub' => ['joueur' => 'Joueur', 'entraineur' => 'Entraîneur', 'dirigeant' => 'Dirigeant', 'personnage' => 'Personnage'][$role] ?? 'Joueur',
                'poste' => ['G' => 'Gardien', 'D' => 'Défenseur', 'M' => 'Milieu', 'A' => 'Attaquant'][$p['line'] ?? ''] ?? null,
                'dec' => $p['arrival'] ? intdiv((int) $p['arrival'], 10) * 10 : null,
                'forme' => (bool) $p['formed'],
                'intl' => (bool) $p['intl'],
                'm' => $tot[$s['id']]['matches'] ?? 0,
                'href' => url($s['path']),
            ];
        }
        $data = [
            'places' => $places,
            'people' => $people,
            'steps' => array_values(array_map(fn ($x) => Collections::loc($x, ['type', 't', 'h', 'p']), array_filter(Collections::get('epopees', Seeds::epopees()), fn ($x) => !empty($x['ll'])))),
            'lieux' => array_values(array_map(fn ($x) => Collections::loc($x, ['n', 't', 'd']), array_filter(Collections::get('lieux', Seeds::lieux()), fn ($x) => !empty($x['ll'])))),
            'home' => [47.5122, 6.8111],
            'now' => (int) date('Y'),
        ];
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        @file_put_contents($cacheFile, $json, LOCK_EX);
        $res = new Response((string) $json, 200, ['Content-Type' => 'application/json; charset=UTF-8']);
        $res->headers['Cache-Control'] = 'public, max-age=600';
        return $res;
    }

    // ------------------------------------------------------------------ centenaire

    public static function centenary(Request $req): Response
    {
        return Pages::render('interactif/centenaire', [
            'target' => Site::centenaryDate(),
            'moments' => self::moments100(),
            'onze' => self::onzeCandidates(),
            'results' => self::onzeResults(),
            'votes' => self::onzeVoters(),
        ], [
            'title' => t('Le centenaire du FC Sochaux-Montbéliard : 1928-2028'),
            'description' => t('Compte à rebours jusqu’au 20 mai 2028, 100 moments de l’histoire du club et vote du Onze de légende.'),
            'active' => 'interactif',
            'body_class' => 'page-centenary',
            'styles' => ['css/interactif.css'],
            'scripts' => ['js/interactif.js'],
        ]);
    }

    public static function moments(Request $req): Response
    {
        return Pages::render('interactif/moments', ['moments' => self::moments100()], [
            'title' => t('100 ans, 100 moments'),
            'description' => t('Jusqu’au centenaire, un moment de l’histoire du FCSM publié chaque semaine.'),
            'active' => 'interactif',
            'styles' => ['css/interactif.css'],
            'scripts' => ['js/interactif.js'],
        ]);
    }

    /** 100 cases : une par semaine depuis la date de lancement, reliées aux fiches « moment ». */
    public static function moments100(): array
    {
        $start = strtotime((string) Settings::get('centenary.moments_start', '2026-06-11')) ?: strtotime('2026-06-11');
        $byNumber = [];
        foreach (Index::published('moment') as $s) {
            if (!empty($s['mo']['number'])) {
                $byNumber[(int) $s['mo']['number']] = $s;
            }
        }
        $out = [];
        for ($i = 1; $i <= 100; $i++) {
            $date = strtotime('+' . (($i - 1) * 7) . ' days', $start);
            $s = $byNumber[$i] ?? null;
            $open = $date <= time() && $s;
            $out[] = [
                'n' => $i,
                'date' => date('Y-m-d', $date),
                'open' => (bool) $open,
                'due' => $date <= time(),
                'title' => $s['title'] ?? null,
                'year' => $s['mo']['year'] ?? null,
                'href' => $open ? url($s['path']) : null,
                'image' => $open ? $s['image'] : null,
                'excerpt' => $open ? $s['excerpt'] : null,
            ];
        }
        return $out;
    }

    /** Candidats au Onze : joueurs des fiches, triés par nombre de matchs. */
    public static function onzeCandidates(): array
    {
        $tot = Derived::get()['person_totals'] ?? [];
        $out = [];
        foreach (Index::published('personne') as $s) {
            if (!in_array('joueur', $s['p']['roles'], true) || empty($s['p']['line'])) {
                continue;
            }
            $out[] = ['id' => $s['id'], 'name' => $s['p']['name'], 'line' => $s['p']['line'], 'm' => $tot[$s['id']]['matches'] ?? 0];
        }
        usort($out, fn ($a, $b) => $b['m'] <=> $a['m'] ?: strcoll($a['name'], $b['name']));
        return $out;
    }

    public static function onzeVote(Request $req): Response
    {
        $d = $req->json();
        $picks = $d['picks'] ?? null;
        if (!is_array($picks) || count($picks) !== 11) {
            return Response::json(['ok' => false, 'error' => t('Composez les 11 postes avant de voter.')], 422);
        }
        // Un vote par appareil et par jour (empreinte anonyme de l'adresse IP)
        $who = substr(hash('sha256', $req->ip() . '|' . date('Y-m-d') . '|onze'), 0, 20);
        if (!RateLimiter::hit('onze', $req->ip(), 3, 86400)) {
            return Response::json(['ok' => false, 'error' => t('Vous avez déjà voté aujourd’hui. Merci !')], 429);
        }
        $valid = [];
        foreach (self::onzeCandidates() as $c) {
            $valid[$c['id']] = $c;
        }
        $clean = [];
        foreach ($picks as $p) {
            $id = (int) ($p['id'] ?? 0);
            $line = (string) ($p['line'] ?? '');
            if (!isset($valid[$id]) || !in_array($line, ['G', 'D', 'M', 'A'], true)) {
                return Response::json(['ok' => false, 'error' => t('Sélection invalide.')], 422);
            }
            $clean[] = [$line, $id];
        }
        if (count(array_unique(array_column($clean, 1))) !== 11) {
            return Response::json(['ok' => false, 'error' => t('Un joueur ne peut occuper qu’un seul poste.')], 422);
        }
        JsonStore::update(self::VOTES . '/onze.json', function ($s) use ($clean, $who) {
            $s = $s ?: ['voters' => 0, 'lines' => ['G' => [], 'D' => [], 'M' => [], 'A' => []], 'seen' => []];
            if (isset($s['seen'][$who])) {
                return $s;
            }
            $s['seen'][$who] = date('Y-m-d');
            $s['voters']++;
            foreach ($clean as [$line, $id]) {
                $s['lines'][$line][$id] = ($s['lines'][$line][$id] ?? 0) + 1;
            }
            // Les empreintes de plus de 2 jours sont oubliées (RGPD)
            $s['seen'] = array_filter($s['seen'], fn ($day) => $day >= date('Y-m-d', strtotime('-2 days')));
            return $s;
        }, []);
        return Response::json(['ok' => true, 'results' => self::onzeResults(), 'voters' => self::onzeVoters()]);
    }

    public static function onzeVoters(): int
    {
        $s = JsonStore::read(self::VOTES . '/onze.json', []);
        return (int) ($s['voters'] ?? 0);
    }

    /** Le Onze du public : 1 gardien, 4 défenseurs, 3 milieux, 3 attaquants les plus cités. */
    public static function onzeResults(): array
    {
        $s = JsonStore::read(self::VOTES . '/onze.json', []);
        $voters = max(1, (int) ($s['voters'] ?? 0));
        $out = [];
        foreach (['G' => 1, 'D' => 4, 'M' => 3, 'A' => 3] as $line => $n) {
            $list = $s['lines'][$line] ?? [];
            arsort($list);
            foreach (array_slice($list, 0, $n, true) as $id => $count) {
                $p = Index::get((int) $id);
                if ($p) {
                    $out[] = ['line' => $line, 'name' => $p['p']['name'], 'pct' => round(100 * $count / $voters), 'href' => url($p['path'])];
                }
            }
        }
        return $out;
    }

    // ------------------------------------------------------------------ réserves

    public static function reserves(Request $req, ?string $collection = null): ?Response
    {
        $cols = Pages::reserves();
        if ($collection === null) {
            return Pages::render('interactif/reserves', ['cols' => $cols, 'col' => null, 'items' => []], [
                'title' => t('Les réserves du musée'),
                'description' => t('Maillots, affiches, programmes, photos, presse et objets de supporters : les collections de Sochaux Rétro.'),
                'active' => 'interactif',
                'styles' => ['css/mosaic.css', 'css/interactif.css'],
            ]);
        }
        $col = null;
        foreach ($cols as $c) {
            if ($c['slug'] === $collection) {
                $col = $c;
            }
        }
        if (!$col) {
            return null;
        }
        $items = array_values(array_filter(Index::published('objet'), fn ($o) => ($o['o']['collection'] ?? '') === $collection));
        usort($items, fn ($a, $b) => ($a['o']['year'] ?? 9999) <=> ($b['o']['year'] ?? 9999));
        return Pages::render('interactif/reserves', ['cols' => $cols, 'col' => $col, 'items' => $items], [
            'title' => t('Les réserves du musée') . ' : ' . t($col['name']),
            'description' => t('{name} : {desc}. Les collections de Sochaux Rétro.', ['name' => t($col['name']), 'desc' => t($col['desc'])]),
            'active' => 'interactif',
            'styles' => ['css/mosaic.css', 'css/interactif.css'],
        ]);
    }
}
