<?php
/**
 * Joueur connu sous un seul nom dans les compositions (ticket : « CARLAO », indiqué sans fiche alors
 * que Carlao Roberto Da Cruz en a une) : relié par ses « Autres graphies » seulement pendant sa
 * période au club (et à l'âge de jouer) ; un autre « CARLAO », six ans plus tôt, reste sans fiche.
 * Fiches en mémoire, rien n'est écrit. Usage : php tests/surnom-compositions.php
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Data\PersonResolver;
use App\Services\LineupLinks;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
$person = fn (string $first, string $last, array $aliases, ?string $arrival, ?string $departure, ?string $birth) => ['type' => 'personne', 'title' => "$first $last", 'status' => 'publie',
    'personne' => ['display_name' => '', 'first_name' => $first, 'last_name' => $last, 'nickname' => '', 'aliases' => $aliases,
        'arrival' => $arrival ? ['iso' => $arrival] : null, 'departure' => $departure ? ['iso' => $departure] : null, 'birth' => ['date' => ['iso' => $birth]]]];
$persons = [
    7296 => $person('Carlao Roberto', 'Da Cruz', ['CARLAO'], '2008-07', '2014-06', '1986-01-19'),
    500 => $person('Jean', 'Dupont', [], '1960-07', '1965-06', '1940-01-01'),
];
$r = new PersonResolver($persons);

// 1. Le surnom des « Autres graphies » pendant sa période au club : relié, sûrement (lien enregistré).
$eq('« CARLAO », 2010-2011 : sa fiche, rapprochement sûr', [$r->resolve('CARLAO', '2010-09-11', false, true), $r->how], [7296, 'exact']);
$eq('« CARLÃO » (avec le tilde), 2012 : sa fiche', $r->resolve('CARLÃO', '2012-10-20', false, true), 7296);
$eq('fin de sa période (mai 2014) : sa fiche', $r->resolve('CARLAO', '2014-05-17', false, true), 7296);

// 2. Hors de sa période : un autre joueur, jamais relié à lui.
$eq('« CARLAO », Coupe Intertoto 2002 (six ans avant son arrivée) : sans fiche', [$r->resolve('CARLAO', '2002-07-06', false, true), $r->resolve('CARLAO', '2002-07-06')], [null, null]);
$eq('« CARLAO », 2000 (14 ans) : sans fiche', $r->resolve('CARLAO', '2000-07-22'), null);

// 3. Nom complet : rien ne change (un joueur revenu au club garde ses matchs, même hors période).
$eq('nom complet hors période : toujours relié', $r->resolve('DUPONT Jean', '1970-09-01', false, true), 500);

// 4. Enregistrement du lien dans la composition (tâche « Liens des compositions ») : le nom reste « CARLAO ».
$doc = ['type' => 'match', 'match' => ['date' => '2010-08-03', 'lineup' => ['rows' => [['position' => 'M', 'name' => 'CARLAO', 'person_id' => null]]]]];
[$out, $n] = LineupLinks::apply($doc, $r, $persons);
$eq('composition 2010 : reliée à sa fiche, nom inchangé', [$n, $out['match']['lineup']['rows'][0]['person_id'] ?? null, $out['match']['lineup']['rows'][0]['name']], [1, 7296, 'CARLAO']);
$doc['match']['date'] = '2002-07-06';
[$out, $n] = LineupLinks::apply($doc, $r, $persons);
$eq('composition 2002 : pas reliée', [$n, $out['match']['lineup']['rows'][0]['person_id'] ?? null], [0, null]);

echo $fail ? "\n$fail échec(s).\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
