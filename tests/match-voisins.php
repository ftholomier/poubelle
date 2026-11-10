<?php
/**
 * Match précédent / match suivant en bas de la fiche match (ticket) : ordre chronologique de la saison,
 * passage à la saison d'avant ou d'après au premier ou au dernier match, date et compétition affichées.
 * Rien n'est écrit. Usage : php tests/match-voisins.php
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Data\Derived;
use App\Data\Fiches;
use App\Front\Fiche;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
$S = Derived::part('seasons');
$M = Derived::part('matches');
$vis = fn (string $s) => array_values(array_filter($S[$s]['matches'] ?? [], fn ($id) => !empty($M[$id]['v'])));
$a = $vis('2010-2011');
$b = $vis('2011-2012');
$adj = fn (int $id) => array_map(fn ($x) => $x['id'] ?? null, Fiche::adjacentMatches(Fiches::fresh($id)));

$eq('milieu de saison : voisins de la même saison', $adj($b[5]), [$b[4], $b[6]]);
$eq('premier match de 2011-2012 : précédent = dernier match de 2010-2011', $adj($b[0]), [end($a), $b[1]]);
$eq('dernier match de 2010-2011 : suivant = premier match de 2011-2012', $adj(end($a)), [$a[count($a) - 2], $b[0]]);
$eq('chronologique', $M[$adj($b[5])[0]]['date'] <= $M[$b[5]]['date'] && $M[$b[5]]['date'] <= $M[$adj($b[5])[1]]['date'], true);

// La barre en bas de la fiche : boutons, affiche, date et compétition, saison quand elle change.
$req = new \App\Core\Request('GET', '/', [], [], [], ['HTTP_HOST' => 'localhost', 'REQUEST_URI' => '/', 'REMOTE_ADDR' => '127.0.0.1'], '');
$html = Fiche::show($req, Fiches::fresh($b[0]))->body;
preg_match('#<nav class="prevnext".*?</nav>#s', $html, $m);
$nav = $m[0] ?? '';
$eq('fiche : deux boutons, précédent et suivant', [substr_count($nav, 'prevnext__btn'), str_contains($nav, 'rel="prev"'), str_contains($nav, 'rel="next"')], [2, true, true]);
$eq('fiche : date du match précédent et saison d’avant indiquées', [str_contains($nav, date_num($M[end($a)]['date'])), str_contains($nav, 'Saison 2010-2011')], [true, true]);

echo $fail ? "\n$fail échec(s).\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
