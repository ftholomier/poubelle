<?php
/**
 * Le Fil jaune (App\Services\FilJaune) : réseau des coéquipiers tiré des compositions, joueurs
 * retrouvés par adresse ou par nom, chaîne la plus courte, liens, records, défi du jour.
 * Usage : php tests/filjaune.php (code de sortie 1 en cas d'échec). N'écrit que le cache des
 * records (storage/cache/fil-jaune.json, reconstructible).
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Services\FilJaune as F;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};

// Réseau.
$g = F::graph();
$adj = $g['adj'];
$eq('réseau : plus de 400 joueurs reliés', count($adj) > 400, true);
$sym = true;
foreach ($adj as $a => $row) {
    foreach ($row as $b => $n) {
        $sym = $sym && ($adj[$b][$a] ?? 0) === $n && $a !== $b;
    }
}
$eq('liens symétriques, jamais avec soi-même', $sym, true);
$eq('coéquipiers du plus fidèle au plus rare', (function () {
    $t = F::teammates(F::find('franck-sauzee'));
    $n = array_column($t, 'n');
    $s = $n;
    rsort($s);
    return $n === $s && count($t) > 10;
})(), true);

// Joueurs retrouvés.
$sauzee = F::find('franck-sauzee');
$eq('par l’adresse, le numéro, le nom, le nom de famille', [F::find((string) $sauzee), F::find('Franck Sauzée'), F::find('sauzee')], [$sauzee, $sauzee, $sauzee]);
$eq('inconnu', [F::find('Joueur Imaginaire'), F::find('')], [null, null]);
$p = F::player($sauzee);
$eq('fiche résumée', [$p['name'], $p['slug'], $p['games'] > 50, $p['mates'] === count($adj[$sauzee])], ['Franck Sauzée', 'franck-sauzee', true, true]);

// Chaîne la plus courte.
$rousset = F::find('gilles-rousset');
$eq('coéquipiers : une passe', F::path($sauzee, $rousset), [$sauzee, $rousset]);
$eq('même joueur', F::path($sauzee, $sauzee), [$sauzee]);
$rec = F::records();
[$fa, $fb] = $rec['far'];
$path = F::path($fa, $fb);
$ok = $path[0] === $fa && end($path) === $fb && count($path) - 1 === $rec['diameter'];
for ($i = 1; $i < count($path); $i++) {
    $ok = $ok && isset($adj[$path[$i - 1]][$path[$i]]);
}
$eq('les plus éloignés : chaîne valide, longueur = record', $ok, true);
$eq('chaîne = distance la plus courte', count(F::path($fa, $rousset)) - 1, F::distances($fa)[$rousset]);
$out = $rec['outside'][0] ?? null;
$eq('famille à part : pas de chaîne', $out ? F::path($out, $sauzee) : null, null);

// Lien entre deux coéquipiers.
$l = F::link($sauzee, $rousset);
$eq('lien : matchs ensemble, premier avant dernier', [$l['n'] === $adj[$sauzee][$rousset], strcmp((string) $l['first']['date'], (string) $l['last']['date']) <= 0], [true, true]);
$eq('pas de lien sans match commun', F::link($fa, $fb), null);

// Records.
$eq('records : familles = tous les joueurs', array_sum($rec['families']), count($adj));
$eq('records : la grande famille, 6 passes au plus', [$rec['family'] > 400, $rec['diameter'] >= 5, $rec['average'] > 2 && $rec['average'] < 4], [true, true, true]);
$eq('records : les plus connectés, par ordre', $rec['connected'][0][1] >= $rec['connected'][4][1] && $rec['connected'][0][1] === count($adj[$rec['connected'][0][0]]), true);
$eq('records gardés en cache', F::records(), $rec);

// Défi du jour.
$d1 = F::daily('2026-10-03');
$d2 = F::daily('2026-10-03');
$d3 = F::daily('2026-10-04');
$dist = F::distances($d1['from']);
$eq('défi : le même pour tous ce jour-là, un autre le lendemain', [$d1 === $d2, $d1 !== $d3], [true, true]);
$eq('défi : à 3 ou 4 passes, joueurs connus', [in_array($dist[$d1['to']], [3, 4], true), $d1['best'] === $dist[$d1['to']], $g['games'][$d1['from']] >= F::DAILY_MIN_GAMES && $g['games'][$d1['to']] >= F::DAILY_MIN_GAMES], [true, true, true]);

echo $fail ? "\n$fail échec(s)\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
