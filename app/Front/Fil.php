<?php
declare(strict_types=1);

namespace App\Front;

use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Services\FilJaune;

/**
 * Le Fil jaune (rubrique INTERACTIF › Jouer) : relier deux joueurs par leurs matchs joués
 * ensemble, constellation des coéquipiers d'un joueur, records, défi du jour.
 */
final class Fil
{
    private const PAGE = ['active' => 'interactif', 'body_class' => 'page-fil', 'styles' => ['css/mosaic.css', 'css/interactif.css', 'css/filjaune.css'], 'scripts' => ['js/filjaune.js']];

    public static function base(): string
    {
        return url('/interactif/fil-jaune/');
    }

    /** Accueil : recherche de deux joueurs, défi du jour, records. ?a=…&b=… : vers la chaîne ; ?de=… : premier joueur déjà choisi. */
    public static function landing(Request $req): Response
    {
        $error = null;
        if ($req->str('a') !== '' || $req->str('b') !== '') {
            $a = FilJaune::find($req->str('a'));
            $b = FilJaune::find($req->str('b'));
            if ($a && $b) {
                return Response::redirect(self::chainUrl($a, $b), 302);
            }
            if ($a && $req->str('b') === '') {
                return Response::redirect(self::starUrl($a), 302);
            }
            $error = t('Joueur introuvable : choisissez un nom dans la liste proposée (seuls les joueurs présents dans les compositions du musée sont reliés).');
        }
        $rec = FilJaune::records();
        $p = fn ($id) => $id ? FilJaune::player((int) $id) : null;
        $daily = FilJaune::daily();
        return Pages::render('interactif/fil-jaune', [
            'players' => self::playerList(), 'rec' => $rec, 'error' => $error, 'qa' => $req->str('a') ?: mb_substr($req->str('de'), 0, 80), 'qb' => $req->str('b'),
            // Un joueur retiré du site entre deux calculs des records est simplement écarté.
            'daily' => $daily && $p($daily['from']) && $p($daily['to']) ? $daily + ['a' => $p($daily['from']), 'b' => $p($daily['to'])] : null,
            'connected' => array_values(array_filter(array_map(fn ($x) => ($a = $p($x[0])) ? $a + ['n' => $x[1]] : null, $rec['connected']))),
            'duos' => array_values(array_filter(array_map(fn ($x) => ($a = $p($x[0])) && ($b = $p($x[1])) ? ['a' => $a, 'b' => $b, 'n' => $x[2]] : null, $rec['duos']))),
            'far' => $rec['far'] && $p($rec['far'][0]) && $p($rec['far'][1]) ? [$p($rec['far'][0]), $p($rec['far'][1])] : null,
            'outside' => array_values(array_filter(array_map($p, $rec['outside']))),
        ], self::PAGE + [
            'title' => t('Le Fil jaune : tous les Lionceaux sont reliés'),
            'description' => t('Choisissez deux joueurs du FC Sochaux-Montbéliard : le musée trouve la chaîne des matchs joués ensemble qui les relie. Défi du jour, records, constellations des coéquipiers.'),
        ]);
    }

    /** /interactif/fil-jaune/{a}/{b}/ : la chaîne la plus courte de a à b. */
    public static function chain(Request $req, string $sa, string $sb): ?Response
    {
        $a = FilJaune::find($sa);
        $b = FilJaune::find($sb);
        if (!$a || !$b) {
            return null;
        }
        if ($sa !== FilJaune::slug($a) || $sb !== FilJaune::slug($b)) {
            return Response::redirect(self::chainUrl($a, $b), 301);
        }
        $pa = FilJaune::player($a);
        $pb = FilJaune::player($b);
        $path = FilJaune::path($a, $b);
        $steps = [];
        foreach ($path ?? [] as $i => $pid) {
            $steps[] = ['p' => FilJaune::player($pid), 'link' => $i ? self::linkView(FilJaune::link($path[$i - 1], $pid)) : null];
        }
        $n = $path ? count($path) - 1 : null;
        return Pages::render('interactif/fil-jaune-chaine', [
            'a' => $pa, 'b' => $pb, 'steps' => $steps, 'n' => $n, 'players' => self::playerList(),
        ], self::PAGE + [
            'title' => t('Fil jaune : de {a} à {b}', ['a' => $pa['name'], 'b' => $pb['name']]),
            'description' => $n === null
                ? t('{a} et {b} ne sont pas encore reliés par les compositions du musée Sochaux Rétro.', ['a' => $pa['name'], 'b' => $pb['name']])
                : t('{a} et {b} sont reliés en {n} passe(s) par les matchs joués ensemble avec le FC Sochaux-Montbéliard.', ['a' => $pa['name'], 'b' => $pb['name'], 'n' => $n]),
            'image' => $pa['image'] ? img($pa['image'], 1200) : null,
        ]);
    }

    /** /interactif/fil-jaune/{a}/ : la constellation des coéquipiers d'un joueur. */
    public static function star(Request $req, string $sa): ?Response
    {
        $a = FilJaune::find($sa);
        if (!$a) {
            return null;
        }
        if ($sa !== FilJaune::slug($a)) {
            return Response::redirect(self::starUrl($a), 301);
        }
        $pa = FilJaune::player($a);
        $mates = [];
        foreach (FilJaune::teammates($a) as $t) {
            $mates[] = FilJaune::player($t['id']) + ['n' => $t['n']];
        }
        $top = array_map(fn ($m) => $m + ['link' => self::linkView(FilJaune::link($a, $m['id']))], array_slice($mates, 0, 5));
        return Pages::render('interactif/fil-jaune-joueur', [
            'a' => $pa, 'mates' => $mates, 'top' => $top, 'svg' => self::constellation($pa, $mates), 'players' => self::playerList(),
        ], self::PAGE + [
            'title' => t('La constellation de {a} : ses {n} coéquipiers', ['a' => $pa['name'], 'n' => count($mates)]),
            'description' => t('Les {n} coéquipiers de {a} au FC Sochaux-Montbéliard, du plus fidèle au plus rare, d’après les compositions du musée.', ['a' => $pa['name'], 'n' => count($mates)]),
            'image' => $pa['image'] ? img($pa['image'], 1200) : null,
        ]);
    }

    /** API GET /api/fil-jaune?id=… : les coéquipiers d'un joueur (défi du jour). */
    public static function api(Request $req): Response
    {
        if (!RateLimiter::hit('fil-jaune', $req->ip(), 300, 60)) {
            return Response::json(['ok' => false], 429);
        }
        $p = FilJaune::player((int) $req->str('id'));
        if (!$p) {
            return Response::json(['ok' => false], 404);
        }
        $mates = [];
        foreach (FilJaune::teammates($p['id']) as $t) {
            $q = FilJaune::player($t['id']);
            $mates[] = ['id' => $q['id'], 'name' => $q['name'], 'years' => $q['years'], 'n' => $t['n']];
        }
        usort($mates, fn ($x, $y) => strcmp(\App\Data\Index::sortName(\App\Data\Index::get($x['id'])), \App\Data\Index::sortName(\App\Data\Index::get($y['id']))));
        $res = Response::json(['ok' => true, 'id' => $p['id'], 'name' => $p['name'], 'teammates' => $mates]);
        $res->headers['Cache-Control'] = 'public, max-age=3600';
        return $res;
    }

    public static function chainUrl(int $a, int $b): string
    {
        return url('/interactif/fil-jaune/' . FilJaune::slug($a) . '/' . FilJaune::slug($b) . '/');
    }

    public static function starUrl(int $a): string
    {
        return url('/interactif/fil-jaune/' . FilJaune::slug($a) . '/');
    }

    /** Joueurs du réseau pour la liste de choix (nom, années, adresse), par nom. */
    private static function playerList(): array
    {
        $out = [];
        foreach (array_keys(FilJaune::graph()['adj']) as $pid) {
            $p = FilJaune::player((int) $pid);
            $out[] = ['id' => $p['id'], 'name' => $p['name'], 'years' => $p['years'], 'slug' => $p['slug'], 'sort' => \App\Data\Index::sortName(\App\Data\Index::get((int) $pid))];
        }
        usort($out, fn ($a, $b) => strcmp($a['sort'], $b['sort']));
        return $out;
    }

    /** Lien entre deux coéquipiers prêt à afficher : nombre de matchs, années, premier match. */
    private static function linkView(?array $l): ?array
    {
        if (!$l) {
            return null;
        }
        $y1 = substr((string) ($l['first']['date'] ?? ''), 0, 4);
        $y2 = substr((string) ($l['last']['date'] ?? ''), 0, 4);
        return ['n' => $l['n'], 'years' => $y1 === $y2 ? $y1 : "{$y1}–{$y2}",
            'first' => $l['first'] ? ['label' => Site::matchLabel($l['first']), 'date' => date_fr($l['first']['date']), 'href' => url($l['first']['path']),
                'comp' => (string) ($l['first']['label'] ?: $l['first']['comp'])] : null];
    }

    /**
     * Constellation (SVG) : le joueur au centre, ses coéquipiers en spirale du plus fidèle
     * (au plus près) au plus rare ; taille du point selon le nombre de matchs ensemble.
     */
    private static function constellation(array $a, array $mates): array
    {
        $W = 800;
        $c = $W / 2;
        $n = count($mates);
        $max = max(1, $mates[0]['n'] ?? 1);
        $k = $n > 1 ? (330 - 74) / sqrt($n) : 0;
        $dots = [];
        foreach ($mates as $i => $m) {
            $r = 74 + $k * sqrt($i + 1);
            $ang = deg2rad($i * 137.508 - 90);
            $dots[] = ['x' => round($c + $r * cos($ang), 1), 'y' => round($c + $r * sin($ang), 1), 'r' => round(4 + 10 * sqrt($m['n'] / $max), 1),
                'top' => $i < 10, 'label' => $i < 8, 'm' => $m];
        }
        return ['w' => $W, 'c' => $c, 'dots' => $dots];
    }
}
