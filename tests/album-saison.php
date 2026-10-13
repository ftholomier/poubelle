<?php
/**
 * Album Panini d'une saison (App\Front\SeasonAlbum, partials/season-album.php) : bloc présent
 * sur une saison avec compositions, une vignette par joueur entré en jeu, tri par poste puis
 * matchs, liens vers les fiches, cases « non collées », pas de bloc sans composition.
 * Usage : php tests/album-saison.php (MUSEE_CLUB=paris pour le second club). Code 1 si échec.
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Request;
use App\Data\Derived;
use App\Data\Fiches;
use App\Front\Explore;
use App\Front\SeasonAlbum;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
$req = new Request('GET', '/', [], [], [], ['HTTP_HOST' => 'localhost', 'REQUEST_URI' => '/', 'REMOTE_ADDR' => '127.0.0.1'], '');

// Saison la plus riche en compositions (1987-1988 pour Sochaux), quel que soit le club.
$best = null;
$bestN = 0;
foreach (Derived::part('seasons') as $s => $S) {
    $n = count(SeasonAlbum::raw($s)['players']);
    if ($n > $bestN) {
        [$best, $bestN] = [$s, $n];
    }
}
if ($best === null) {
    // Club sans aucune composition (Paris au démarrage) : aucun album nulle part.
    echo "(aucune composition pour ce club : vérification de l'absence de bloc seulement)\n";
    goto sans;
}
$eq('une saison avec compositions', $best !== null, true);

// Référence indépendante : joueurs distincts (fiche ou nom) titulaires ou remplaçants entrés.
$M = Derived::part('matches');
$ref = [];
foreach (Derived::part('seasons')[$best]['matches'] as $mid) {
    if (!($M[$mid]['v'] ?? false)) {
        continue;
    }
    foreach (Fiches::get((int) $mid)['match']['lineup']['rows'] ?? [] as $r) {
        $name = trim((string) ($r['name'] ?? ''));
        if ($name === '' || $r['position'] === 'E' || ($r['position'] === 'R' && (string) ($r['sub_in'] ?? '') === '')) {
            continue;
        }
        $ref[$name] = true;
    }
}
$album = SeasonAlbum::build($best);
$all = array_merge(...array_values($album['groups']));
// Un même joueur peut être écrit de deux façons dans les fiches mais relié à la même fiche :
// le nombre de vignettes est au plus le nombre de noms distincts, et égal au compte du bloc.
$eq("vignettes = joueurs distincts entrés en jeu ($best)", [count($all) === $album['count'], count($all) <= count($ref), count($all) >= (int) (0.8 * count($ref))], [true, true, true]);
$eq('pas de joueur en double', count(array_unique(array_map(fn ($p) => $p['pid'] ? 'p' . $p['pid'] : 'n' . $p['name'], $all))), count($all));
$eq('numéros d’album 1..n', array_column($all, 'no'), range(1, count($all)));

$okSort = true;
$prev = null;
foreach ($all as $p) {
    $k = [SeasonAlbum::ORDER[$p['line']], -$p['mj']];
    if ($prev !== null && $k < $prev) {
        $okSort = false;
    }
    $prev = $k;
}
$eq('tri par poste (G, D, M, A, inconnus) puis matchs', $okSort, true);
$eq('groupes dans l’ordre des postes', array_keys($album['groups']), array_values(array_intersect(array_keys(SeasonAlbum::ORDER), array_keys($album['groups']))));

// Page rendue
$html = Explore::season($req, $best)->body;
$eq('bloc album présent', str_contains($html, 'id="album"'), true);
$eq('titre « Album »', str_contains($html, e(t('Album {s}', ['s' => $best]))), true);
$eq('une vignette (li) par joueur, + entraîneur', substr_count($html, 'class="alb__item'), count($all) + ($album['coach'] ? 1 : 0));
$stuck = array_values(array_filter($all, fn ($p) => $p['stuck']));
$eq('vignette collée : lien vers la fiche', $stuck && str_contains($html, 'class="alb__card" href="' . e($stuck[0]['href']) . '"'), true);
$eq('images en lazy-loading', !preg_match('/<img(?![^>]*loading="lazy")[^>]*alb/', $html) && substr_count($html, 'loading="lazy" decoding="async"') >= count(array_filter($stuck, fn ($p) => $p['image'])), true);
$empty = array_filter($all, fn ($p) => !$p['stuck']);
$eq('joueurs sans fiche : cases non collées', substr_count($html, 'alb__slot is-empty') >= count($empty), true);

// Cache : relu tel quel tant que Derived n'a pas été recalculé.
$eq('cache relu', SeasonAlbum::raw($best), SeasonAlbum::compute($best));

// Saison sans composition : pas de bloc.
sans:
$eq('saison inconnue : pas d’album', SeasonAlbum::build('1800-1801'), null);
$noLineup = null;
foreach (\App\Data\Categories::all() as $c) {
    $s = $c['season'] ?? null;
    if ($s && !SeasonAlbum::raw($s)['players'] && ($r = Explore::season($req, $s))) {
        $noLineup = [$s, $r->body];
        break;
    }
}
if ($noLineup) {
    $eq("saison {$noLineup[0]} sans composition : bloc masqué", str_contains($noLineup[1], 'id="album"'), false);
}

echo $fail ? "\n$fail échec(s)\n" : "\nTout est OK\n";
exit($fail ? 1 : 0);
