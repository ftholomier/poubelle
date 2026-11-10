<?php
/**
 * Récit importé avec « Résumé de la rencontre » et « Avant match et enjeux » inversés (ticket : Evian–
 * Sochaux 2013, minute par minute vide) : textes remis sous leur titre, temps forts repris ; rien ne
 * change pour un récit en ordre. Rien n'est écrit. Usage : php tests/recit-inverse.php
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Services\DataFixes as D;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
$avant = '<ul><li>Traoré et Roussillon blessés.</li><li>3 points séparent les deux équipes.</li></ul>';
$minutes = "<ul><li><strong>14' :</strong> Barbosa lance Khalifa, Pouplin capte.</li><li><strong>36' :</strong> Bérigaud trompe Pouplin. (1-0)</li><li><strong>50' :</strong> Sio frappe à côté.</li></ul>";
$doc = ['type' => 'match', 'match' => ['highlights' => [], 'score' => ['home' => 1, 'away' => 0]], 'sections' => [
    ['title' => 'Résumé de la rencontre', 'html' => $avant],
    ['title' => 'Avant match et enjeux', 'html' => $minutes],
]];
$new = D::unswapRecit($doc);
$eq('textes remis sous leur titre', [$new['sections'][0]['html'] ?? null, $new['sections'][1]['html'] ?? null], [$minutes, $avant]);
$eq('titres inchangés', array_column($new['sections'], 'title'), ['Résumé de la rencontre', 'Avant match et enjeux']);
\App\Services\RecitHighlights::apply($new);
$eq('minute par minute repris du résumé (3 temps forts, 1 but)', [count($new['match']['highlights']), count(array_filter($new['match']['highlights'], fn ($h) => !empty($h['goal'])))], [3, 1]);
$ok = $doc;
$ok['sections'] = array_reverse($doc['sections']);
$ok['sections'][0]['title'] = 'Résumé de la rencontre';
$ok['sections'][1]['title'] = 'Avant match et enjeux';
$eq('récit déjà en ordre : rien', D::unswapRecit($ok), null);
$two = $doc;
$two['sections'][1]['html'] = "<ul><li><strong>14' :</strong> Seul fait marquant.</li></ul>";
$eq('une seule ligne « minute » dans l’avant-match : pas sûr, rien', D::unswapRecit($two), null);
echo $fail ? "\n$fail échec(s).\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
