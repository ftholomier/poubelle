<?php
/**
 * Album Sochini / Parini (App\Front\AlbumBook, AlbumPages) et « Mon album » (App\Services\Vignettes) :
 * chaque type d'album produit au moins un album (Sochaux), adresses ?album=…&v=… valides et invalides,
 * chiffres des matchs officiels seulement, albums vides non proposés, nom du club ;
 * collection : pochette limitée par jour, signatures, échanges (valide, invalide, double usage), doubles, progression.
 * Usage : php tests/album-sochini.php (MUSEE_CLUB=paris pour le second club). Code 1 si échec.
 * Ne touche pas aux vraies collections : storage/vignettes est remplacé par un dossier temporaire.
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Request;
use App\Data\Derived;
use App\Front\AlbumBook;
use App\Front\AlbumPages;
use App\Front\SeasonAlbum;
use App\Services\Vignettes;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
$page = function (array $q): \App\Core\Response {
    $_SERVER['REQUEST_URI'] = AlbumBook::PATH . '?' . http_build_query($q);
    return AlbumPages::page(new Request('GET', AlbumBook::PATH, $q, [], [], ['HTTP_HOST' => 'localhost', 'REQUEST_URI' => $_SERVER['REQUEST_URI'], 'REMOTE_ADDR' => '127.0.0.1'], ''));
};
$club = \App\Core\Club::id();
$cat = AlbumBook::catalog();

// Nom propre au club
$eq('nom de la rubrique', AlbumBook::name(), $club === 'paris' ? 'Album Parini' : 'Album Sochini');
$eq('menu Interactif › Explorer l’histoire', (function () {
    foreach (\App\Front\Menus::get()['interactif'] as $col) {
        if ($col['title'] === "Explorer l'histoire") {
            return in_array(AlbumBook::PATH, array_column($col['tools'], 'href'), true);
        }
    }
    return false;
})(), true);

// Albums vides jamais proposés
$empty = 0;
foreach ($cat as $t => $albums) {
    foreach ($albums as $a) {
        $empty += $a['count'] === 0 ? 1 : 0;
    }
}
$eq('aucun album vide au catalogue', $empty, 0);
$eq('types connus seulement', array_values(array_diff(array_keys($cat), AlbumBook::TYPES)), []);

if ($club !== 'paris' || $cat) {
    if ($club === 'sochaux') {
        foreach (AlbumBook::TYPES as $t) {
            $eq("type « $t » : au moins un album", count($cat[$t] ?? []) >= 1, true);
        }
        $eq('décennie 1980 présente', isset($cat['decennie']['1980']), true);
        $eq('épopée Coupe de France 1987-1988 (finale)', !empty($cat['epopee']['coupe-de-france-1987-1988']['final']), true);
        $ep = AlbumBook::raw('epopee', 'coupe-de-france-1987-1988');
        $eq('héros de la finale en vignettes brillantes', count(array_filter($ep['items'], fn ($p) => $p['shine'])) >= 11, true);
        $bu = AlbumBook::raw('buteurs');
        $eq('buteurs : top 50, 10 brillantes', [count($bu['items']) <= AlbumBook::TOP_SCORERS, count(array_filter($bu['items'], fn ($p) => $p['shine']))], [true, AlbumBook::SHINY_SCORERS]);
        $eq('buteurs rangés par buts', array_column($bu['items'], 'goals') === array_values(array_reverse(array_values(array_merge([], (function ($g) { sort($g); return $g; })(array_column($bu['items'], 'goals')))))), true);
        $fi = AlbumBook::raw('fideles');
        $eq('fidèles : seuils respectés', !array_filter($fi['items'], fn ($p) => $p['mj'] < AlbumBook::LOYAL_MATCHES && $p['seasons'] < AlbumBook::LOYAL_SEASONS), true);
        $g = AlbumBook::raw('gardiens')['items'];
        $eq('gardiens par ordre chronologique', array_column($g, 'first') === (function ($x) { sort($x); return $x; })(array_column($g, 'first')), true);
        foreach ($cat['entraineur'] as $v => $a) {
            $eq("entraîneur $v : au moins " . AlbumBook::MIN_COACH . ' matchs', $a['coach']['mj'] >= AlbumBook::MIN_COACH, true);
            break;
        }
    }
    // Chiffres officiels seulement : l'album de saison = SeasonAlbum (qui compte les amicaux à part).
    $best = null;
    foreach ($cat['saison'] ?? [] as $s => $a) {
        if ($best === null || $a['count'] > $cat['saison'][$best]['count']) {
            $best = (string) $s;
        }
    }
    if ($best) {
        $raw = SeasonAlbum::raw($best);
        $mine = array_values(array_filter(AlbumBook::raw('saison', $best)['items'], fn ($p) => empty($p['coach'])));
        $eq("saison $best : mêmes joueurs et chiffres que l’album de saison", array_map(fn ($p) => [$p['name'], $p['mj'], $p['goals'], $p['amic']], $mine),
            array_map(fn ($p) => [$p['name'], $p['mj'], $p['goals'], $p['amic']], array_values(array_filter($raw['players'], fn ($p) => $p['mj'] > 0))));
        // Référence indépendante : nombre de matchs officiels (hors amicaux) avec composition dans la saison.
        $M = Derived::part('matches');
        $off = 0;
        foreach (Derived::part('seasons')[$best]['matches'] as $mid) {
            if (($M[$mid]['v'] ?? false) && $M[$mid]['comp'] !== 'Amical' && SeasonAlbum::lineup((int) $mid)) {
                $off++;
            }
        }
        $eq('aucun joueur au-delà des matchs officiels de la saison', max(array_column($mine, 'mj')) <= $off, true);
        $dec = AlbumBook::raw('decennie', (string) (intdiv((int) substr($best, 0, 4), 10) * 10));
        $eq('décennie : contient les joueurs de la saison', !array_diff(array_filter(array_column($mine, 'pid')), array_column($dec['items'], 'pid')), true);

        // Adresses
        $r = $page(['album' => 'saison', 'v' => $best]);
        $eq('?album=saison&v=… : 200', $r->status, 200);
        $eq('titre et vignettes', [str_contains($r->body, 'id="alb-title"'), substr_count($r->body, 'class="alb__item') === min(AlbumBook::PAGE, count(AlbumBook::raw('saison', $best)['items']))], [true, true]);
        $eq('adresse canonique', str_contains($r->body, '<link rel="canonical" href="' . e(base_url() . AlbumBook::url('saison', $best))) || str_contains($r->body, e(AlbumBook::url('saison', $best))), true);
        $eq('lien depuis la page saison', str_contains(\App\Front\Explore::season(new Request('GET', '/', [], [], [], ['HTTP_HOST' => 'localhost', 'REQUEST_URI' => '/', 'REMOTE_ADDR' => '127.0.0.1'], ''), $best)->body, e(AlbumBook::url('saison', $best))), true);
    }
    $eq('?album=inconnu : 404', $page(['album' => 'inconnu'])->status, 404);
    $eq('?album=saison&v=1800-1801 : 404 noindex', [($x = $page(['album' => 'saison', 'v' => '1800-1801']))->status, str_contains($x->body, 'noindex')], [404, true]);
    $eq('sans paramètre : 200', $page([])->status, 200);
    $one = array_key_first(array_intersect_key($cat, array_flip(AlbumBook::SINGLE)));
    if ($one) {
        $eq("?album=$one (album unique) : 200, v ignoré", [$page(['album' => $one])->status, $page(['album' => $one, 'v' => 'nimporte'])->status], [200, 200]);
    }
    // Pagination au-delà de PAGE vignettes
    foreach ($cat as $t => $albums) {
        foreach ($albums as $v => $a) {
            if ($a['count'] > AlbumBook::PAGE) {
                $html = $page(['album' => $t, 'v' => (string) $v])->body;
                $eq("album long ($t $v) : " . AlbumBook::PAGE . ' vignettes puis « suite »', [substr_count($html, 'class="alb__item'), str_contains($html, 'data-snb-next')], [AlbumBook::PAGE, true]);
                $suite = $page(['album' => $t, 'v' => (string) $v, 'p' => 2, 'part' => 'suite'])->body;
                $eq('suite : vignettes numérotées à partir de ' . (AlbumBook::PAGE + 1), str_contains($suite, '<span class="alb__no">' . (AlbumBook::PAGE + 1) . '</span>') || str_contains($suite, '>' . (AlbumBook::PAGE + 1) . '</span>'), true);
                break 2;
            }
        }
    }
    // Cache relu tel quel
    AlbumBook::forget();
    $eq('cache : catalogue relu identique', AlbumBook::catalog(), $cat);
}

// ------------------------------------------------------------------ Mon album
$tmp = sys_get_temp_dir() . '/vignettes-test-' . bin2hex(random_bytes(4));
Vignettes::$dir = $tmp;
$pool = Vignettes::pool();
if (!$pool) {
    echo "(aucune vignette à collectionner pour ce club : Mon album non testé)\n";
} else {
    Vignettes::$today = '2026-10-13';
    [$a, $ca] = Vignettes::create();
    [$b, $cb] = Vignettes::create();
    $eq('collection ouverte par son code', Vignettes::open($ca)['id'], $a['id']);
    $eq('code de sauvegarde lisible → même collection', Vignettes::open(Vignettes::saveCode($ca))['id'], $a['id']);
    $eq('mauvais jeton refusé', Vignettes::open($a['id'] . '.' . str_repeat('0', 32)), null);
    $p1 = Vignettes::openPack($a['id']);
    $eq('pochette : ' . Vignettes::settings()['size'] . ' vignettes', count($p1['pack'] ?? []), Vignettes::settings()['size']);
    $eq('pochette signée', $p1['sig'], Vignettes::sign($a['id'], '2026-10-13', '1', implode(',', array_column($p1['pack'], 'pid'))));
    $eq('une seule pochette par jour', isset(Vignettes::openPack($a['id'])['error']), true);
    Vignettes::$today = '2026-10-14';
    $eq('le lendemain : nouvelle pochette', isset(Vignettes::openPack($a['id'])['pack']), true);
    $c = Vignettes::get($a['id']);
    $eq('vignettes comptées', array_sum($c['s']), 2 * Vignettes::settings()['size']);
    // Doubles et échange
    $ids = array_keys($pool);
    [$x, $y] = [$ids[0], $ids[1]];
    \App\Core\JsonStore::update($tmp . '/c/' . $a['id'] . '.json', fn ($c) => ['s' => [$x => 3]] + $c);
    \App\Core\JsonStore::update($tmp . '/c/' . $b['id'] . '.json', fn ($c) => ['s' => [$y => 2]] + $c);
    $eq('doubles comptés', Vignettes::state(Vignettes::get($a['id']))['doubles'], 2);
    $eq('échange refusé sans double', isset(Vignettes::offer($b['id'], $x, $y)['error']), true);
    $o = Vignettes::offer($a['id'], $x, $y);
    $eq('code d’échange créé, double mis de côté', [(bool) preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}$/', $o['code'] ?? ''), Vignettes::get($a['id'])['s'][$x]], [true, 2]);
    $eq('code inventé refusé', isset(Vignettes::accept($b['id'], 'ZZZZ-ZZZZ')['error']), true);
    $eq('propre offre refusée', isset(Vignettes::accept($a['id'], $o['code'])['error']), true);
    $forged = $o['code'];
    $t = json_decode((string) file_get_contents($tmp . '/echanges/' . str_replace('-', '', $forged) . '.json'), true);
    file_put_contents($tmp . '/echanges/' . str_replace('-', '', $forged) . '.json', json_encode(['give' => $y] + $t));
    $eq('offre modifiée à la main (signature) refusée', Vignettes::trade($forged), null);
    file_put_contents($tmp . '/echanges/' . str_replace('-', '', $forged) . '.json', json_encode($t));
    $r = Vignettes::accept($b['id'], $o['code']);
    $eq('échange valide', [$r['ok'] ?? false, Vignettes::get($b['id'])['s'][$x] ?? 0, Vignettes::get($b['id'])['s'][$y], Vignettes::get($a['id'])['s'][$y] ?? 0], [true, 1, 1, 1]);
    $eq('code utilisé deux fois : refusé', isset(Vignettes::accept($b['id'], $o['code'])['error']), true);
    // Annulation
    $o2 = Vignettes::offer($a['id'], $x, $y);
    $eq('annulation : double rendu', [Vignettes::cancel($a['id'], $o2['code']), Vignettes::get($a['id'])['s'][$x]], [true, 2]);
    // Progression et page complétée
    $chs = Vignettes::chapters();
    $small = null;
    foreach ($chs as $k => $ch) {
        if ($k !== 'general' && ($small === null || count($ch['ids']) < count($chs[$small]['ids']))) {
            $small = $k;
        }
    }
    if ($small) {
        $ids = $chs[$small]['ids'];
        \App\Core\JsonStore::update($tmp . '/c/' . $b['id'] . '.json', fn ($c) => ['s' => array_fill_keys(array_slice($ids, 1), 1) + [$ids[0] => 0, $y => 2]] + $c);
        $st = Vignettes::state(Vignettes::get($b['id']));
        $row = array_values(array_filter($st['chapters'], fn ($c) => $c['k'] === $small))[0];
        $eq("progression ($small)", [$row['have'], $row['total'], $row['done']], [count($ids) - 1, count($ids), null]);
        \App\Core\JsonStore::update($tmp . '/c/' . $b['id'] . '.json', function ($c) use ($ids) { $c['s'][$ids[0]] = 1; return $c; });
        Vignettes::bonus($b['id'], 'fiche', ['pid' => 0]); // une action qui relit la collection
        $r = Vignettes::offer($b['id'], $y, $ids[1]); // déclenche une écriture
        $c = Vignettes::get($b['id']);
        Vignettes::$today = '2026-10-15';
        $p = Vignettes::openPack($b['id']);
        $eq('page complétée enregistrée', isset(Vignettes::get($b['id'])['done'][$small]), true);
    }
    // Bonus fiche : une seule fois par fiche
    Vignettes::saveSettings(['enabled' => 1, 'packs' => 1, 'size' => 5, 'shiny' => 12, 'gold' => 2, 'fiche' => 100, 'fiche_max' => 3, 'defi' => 3]);
    $pid = array_key_last($pool);
    $eq('bonus fiche : première visite', count(Vignettes::bonus($a['id'], 'fiche', ['pid' => $pid])['pack']), 1);
    $eq('bonus fiche : pas deux fois', count(Vignettes::bonus($a['id'], 'fiche', ['pid' => $pid])['pack']), 0);
    $eq('bonus défi sans défi réussi : rien', count(Vignettes::bonus($a['id'], 'defi', ['ok' => false])['pack']), 0);
    $eq('stats', Vignettes::stats()['collectors'], 2);
    // Purge : collection inactive depuis 18 mois
    \App\Core\JsonStore::update($tmp . '/c/' . $a['id'] . '.json', fn ($c) => ['seen' => date('c', strtotime('-19 months'))] + $c);
    Vignettes::purge();
    $eq('purge des collections inactives', [Vignettes::get($a['id']), (bool) Vignettes::get($b['id'])], [null, true]);
}
exec('rm -rf ' . escapeshellarg($tmp));

echo $fail ? "\n$fail échec(s)\n" : "\nTout est OK\n";
exit($fail ? 1 : 0);
