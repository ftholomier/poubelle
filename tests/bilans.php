<?php
/**
 * Bilans des joueurs (App\Services\Bilans) : données lues, noms découpés (initiale avant ou après),
 * libellés des compétitions, liens vers les fiches sans ambiguïté, bilan d'un joueur, classement
 * « Temps de jeu », classement final d'une saison. Usage : php tests/bilans.php
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Data\Index;
use App\Services\Bilans as B;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};

$eq('données lues (plus de 4 000 lignes, 80 saisons)', [count(B::rows()) > 4000, count(array_unique(array_column(B::rows(), 'season'))) >= 80], [true, true]);
$eq('noms découpés', [B::split('REVELLI P.'), B::split('M. MARTIN'), B::split('BENOÎT'), B::pretty('STOPYRA Y.')], [['revelli', 'p'], ['martin', 'm'], ['benoit', ''], ['Y. Stopyra'][0]]);
$eq('compétitions', [B::competition('D1-J', '1978-1979'), B::competition('D1-J', '2005-2006'), B::competition('CF-J', '1990-1991'), B::competition('D3-J', '2023-2024')], ['Division 1', 'Ligue 1', 'Coupe de France', 'National']);
$eq('classement final 1978-1979', B::season('1978-1979')['DIVISION 1']['rank'] ?? null, '9ème (39 pts)');

// Lien vers une fiche : seulement si elle existe au musée.
$revelli = null;
foreach (Index::all() as $e) {
    if (($e['type'] ?? '') === 'personne' && ($e['p']['last'] ?? '') === 'Revelli' && str_starts_with((string) ($e['p']['first'] ?? ''), 'Patrick')) {
        $revelli = (int) $e['id'];
    }
}
if ($revelli) {
    $eq('« REVELLI P. » relié à Patrick Revelli', B::links()['REVELLI P.'] ?? null, $revelli);
    $b = B::forPerson($revelli);
    $eq('bilan 1978-1979 en D1 : 35 matchs, 35 titularisations, 3 039 min, 11 buts', array_values(array_intersect_key($b['rows'][0], array_flip(['season', 'comp', 'matches', 'starts', 'minutes', 'goals']))), ['1978-1979', 'Division 1', 35, 35, 3039, 11]);
    $eq('totaux additionnés', $b['total']['minutes'] === array_sum(array_column($b['rows'], 'minutes')), true);
} else {
    echo "--   fiche de Patrick Revelli absente : liens non testés\n";
}
$eq('aucun nom relié à deux fiches à la fois', count(B::links()) === count(array_unique(array_keys(B::links()))), true);
$top = B::top('minutes', 5);
$eq('Temps de jeu : 5 lignes, triées', [count($top), $top[0]['value'] >= $top[4]['value']], [5, true]);
$eq('filtre décennie : seules les années 50', count(array_filter(B::top('minutes', 10, 1950), fn ($r) => (int) substr($r['years'], 0, 4) < 1950 && (int) substr($r['years'], 5, 4) < 1950)), 0);
echo $fail ? "\n$fail échec(s).\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
