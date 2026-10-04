<?php
/**
 * Recherche sur le web d'une fiche (App\Services\WebCheck) : description envoyée à Gemini,
 * propositions lues dans la réponse et reliées à leurs sources, résultat gardé 30 jours.
 * Gemini est simulé : aucune requête réelle. Usage : php tests/recherche.php (code de sortie 1 en
 * cas d'échec). N'écrit que dans un dossier temporaire.
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\JsonStore;
use App\Data\Fiches;
use App\Services\AiCosts;
use App\Services\WebCheck as W;

$tmp = sys_get_temp_dir() . '/recherche-test-' . bin2hex(random_bytes(4));
W::$dir = $tmp;
AiCosts::$dir = "$tmp/ia";
$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
$has = fn (string $text, string $line) => str_contains($text, $line);

// Description de la fiche envoyée à Gemini.
$m = W::describe(Fiches::get(10040));
$eq('match : date, compétition, équipes, score', [$has($m, 'Date : Vendredi 7 avril 2017'), $has($m, 'Ligue 2'), $has($m, 'Score : '), $has($m, 'Composition de Sochaux : ')], [true, true, true, true]);
$x = W::describe(Fiches::get(22962));
$eq('en-tête contredit (Xamax) : ni « 21 aout » ni « 3è journée »', [$has($x, '21 aout'), $has($x, '3è journée')], [false, false]);
$p = W::describe(Fiches::get(5944));
$eq('personne : naissance, décès, années au club, sélections', [$has($p, 'Naissance : né le 24 décembre 1905 à Belfort (90)'), $has($p, 'Décès : Décédé le 23 mars 1986'), $has($p, 'Au FCSM : 1929-1946'), $has($p, 'Sélections : International français')], [true, true, true, true]);
$blank = Fiches::blank('match');
$blank['id'] = 999999;
$blank['title'] = 'Essai';
$eq('champs vides signalés « non renseigné »', $has(W::describe($blank), 'Arbitre : non renseigné'), true);
[$system] = W::prompt(Fiches::get(10040));
$eq('consigne : sources seulement, pas d’homonyme, réponse JSON', [$has($system, 'n\'invente rien'), $has($system, 'pas un homonyme'), $has($system, '"propositions"')], [true, true, true]);

// Réponse de Gemini : propositions et pages qui les appuient (positions en octets).
$answer = "```json\n" . json_encode(['resume' => 'Deux points à vérifier.', 'propositions' => [
    ['type' => 'complément', 'champ' => 'Affluence', 'fiche' => '', 'proposition' => 'Les journaux donnent 9 312 spectateurs.', 'confiance' => 'Moyenne'],
    ['type' => 'divergence', 'champ' => 'Arbitre', 'fiche' => 'M. Durand', 'proposition' => 'L’arbitre était M. Dupont selon deux sources.', 'confiance' => 'haute'],
    ['type' => 'piste', 'champ' => 'Archives', 'fiche' => '', 'proposition' => 'Un résumé vidéo existe sur l’INA.', 'confiance' => 'inconnue'],
    ['type' => 'complement', 'champ' => 'Vide', 'proposition' => '   '],
]], JSON_UNESCAPED_UNICODE) . "\n```";
$at = fn (string $s) => [strpos($answer, $s), strpos($answer, $s) + strlen($s)];
[$a1, $b1] = $at('L’arbitre était M. Dupont');
[$a2, $b2] = $at('9 312 spectateurs');
$raw = ['candidates' => [['groundingMetadata' => [
    'webSearchQueries' => ['Sochaux Niort 7 avril 2017 arbitre', 'Sochaux Niort affluence'],
    'searchEntryPoint' => ['renderedContent' => '<style>.c{}</style><div class="c"><a href="https://www.google.com/search?q=x">x</a></div>'],
    'groundingChunks' => [
        ['web' => ['uri' => 'https://vertexaisearch.cloud.google.com/grounding-api-redirect/AAA', 'title' => 'lequipe.fr']],
        ['web' => ['uri' => 'javascript:alert(1)', 'title' => 'piège']],
        ['web' => ['uri' => 'https://vertexaisearch.cloud.google.com/grounding-api-redirect/BBB', 'title' => 'estrepublicain.fr']],
    ],
    'groundingSupports' => [
        ['segment' => ['startIndex' => $a1, 'endIndex' => $b1], 'groundingChunkIndices' => [0, 2]],
        ['segment' => ['startIndex' => $a2, 'endIndex' => $b2], 'groundingChunkIndices' => [2, 1]],
    ],
]]]];
$r = W::parse($answer, $raw);
$eq('divergences d’abord, puis compléments, puis pistes ; proposition vide écartée', array_column($r['items'], 'type'), ['divergence', 'complement', 'piste']);
$eq('confiance normalisée', array_column($r['items'], 'confidence'), ['haute', 'moyenne', 'moyenne']);
$eq('adresse non web écartée des sources', array_column($r['sources'], 'title'), ['lequipe.fr', 'estrepublicain.fr']);
$eq('chaque proposition reliée à ses pages', [$r['items'][0]['sources'], $r['items'][1]['sources'], $r['items'][2]['sources']], [[0, 1], [1], []]);
$eq('ce que dit la fiche gardé', $r['items'][0]['fiche'], 'M. Durand');
$eq('recherches et suggestions de Google gardées', [count($r['queries']), str_contains($r['suggestions'], 'google.com/search')], [2, true]);
$eq('résumé', $r['summary'], 'Deux points à vérifier.');
$eq('réponse illisible : rien proposé, message clair', [W::parse('Désolé, pas de JSON.', [])['items'], str_contains(W::parse('Désolé', [])['summary'], 'Relancez')], [[], true]);
// Réponse en deux parties, la première précédée d'un saut de ligne (rogné par generate()) : les
// positions sont comptées dans chaque partie.
$cut = strpos($answer, '"divergence"');
[$part1, $part2] = ["\n" . substr($answer, 0, $cut), substr($answer, $cut)];
$in2 = strpos($part2, 'L’arbitre était M. Dupont');
$raw2 = ['candidates' => [['content' => ['parts' => [['text' => $part1], ['text' => $part2]]], 'groundingMetadata' => [
    'groundingChunks' => $raw['candidates'][0]['groundingMetadata']['groundingChunks'],
    'groundingSupports' => [['segment' => ['partIndex' => 1, 'startIndex' => $in2, 'endIndex' => $in2 + 20], 'groundingChunkIndices' => [2]]],
]]]];
$eq('réponse en plusieurs parties : sources bien placées', W::parse(trim($part1 . $part2), $raw2)['items'][0]['sources'], [1]);
$odd = W::parse('{"propositions":[{"type":["x"],"champ":{"a":1},"proposition":"Texte","confiance":["y"]}]}', []);
$eq('champs mal formés : proposition gardée, valeurs par défaut', [$odd['items'][0]['type'], $odd['items'][0]['confidence'], $odd['items'][0]['field']], ['complement', 'moyenne', '']);

// Lancement (Gemini simulé) : résultat gardé pour la fiche, relu tant qu'il a moins de 30 jours.
$sent = null;
W::$ai = function (string $system, string $text, array $opt) use (&$sent, $answer, $raw) {
    $sent = $opt;
    return ['text' => $answer, 'raw' => $raw, 'model' => 'essai'];
};
$eq('activée avec Gemini (simulé)', W::enabled(), true);
$doc = Fiches::get(10040);
$res = W::run($doc);
$eq('recherche Google demandée, usage « recherche », fiche notée', [json_encode($sent['tools']), $sent['for'], $sent['ref']], ['[{"google_search":{}}]', 'recherche', 'fiche:10040']);
$eq('résultat gardé et relu', [W::last(10040)['items'] ?? null, W::last(10040)['model'] ?? null], [$res['items'], 'essai']);
$old = W::last(10040);
$old['at'] = date('c', time() - 31 * 86400);
JsonStore::write("$tmp/10040.json", $old);
$eq('plus de 30 jours : oublié', W::last(10040), null);
touch("$tmp/10040.json", time() - 31 * 86400);
$eq('ménage des résultats de plus de 30 jours', [W::purge(), is_file("$tmp/10040.json")], [1, false]);
$eq('fiche jamais cherchée : rien', W::last(1), null);
W::$ai = null;

// Coût des recherches Google : part gratuite du mois (Gemini 3), puis 1,4 centime de dollar l'une.
$eq('dans les 5 000 gratuites du mois : 0', AiCosts::searchCost('gemini-3-flash', 3, date('Y-m'), date('d')), 0.0);
JsonStore::write("$tmp/ia/totaux.json", [date('Y-m') => ['search' => 4999]]);
$eq('au-delà : seules les recherches en trop comptent', round(AiCosts::searchCost('gemini-3-flash', 3, date('Y-m'), date('d')), 6), 0.028);
$eq('modèle plus ancien : par demande, 1 500 gratuites par jour', [AiCosts::searchCost('gemini-2.5-flash', 4, date('Y-m'), date('d'))], [0.0]);
JsonStore::write("$tmp/ia/totaux.json", [date('Y-m') => ['days' => [date('d') => ['sp' => 1500]]]]);
$eq('modèle plus ancien au-delà : 3,5 centimes de dollar la demande', AiCosts::searchCost('gemini-2.5-flash', 4, date('Y-m'), date('d')), 0.035);
JsonStore::write("$tmp/ia/totaux.json", []);
AiCosts::record('recherche', 'gemini-3-flash', ['in' => 9000, 'out' => 1200, 'search' => 3], 'fiche:10040');
$t = AiCosts::totals()[date('Y-m')] ?? [];
$eq('appel compté : usage « recherche », recherches du mois', [$t['uses']['recherche']['calls'] ?? 0, $t['search'] ?? 0], [1, 3]);
$eq('plafond mensuel : 1 recherche comptée', W::quota()['used'], 1);

array_map('unlink', array_merge(glob("$tmp/ia/*") ?: [], glob("$tmp/*.json") ?: [], glob("$tmp/*.lock") ?: []));
@rmdir("$tmp/ia");
@rmdir($tmp);
echo $fail ? "\n$fail échec(s)\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
