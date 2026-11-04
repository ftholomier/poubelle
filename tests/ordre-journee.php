<?php
/**
 * Ordre dans la journée (ticket : amicaux du 7 juillet 2010, Neuchâtel Xamax joué avant Deportivo
 * Saprissa, affichés à l'envers) : champ « Ordre dans la journée » de la fiche match, pris en compte
 * par les listes de matchs (mosaïque d'une saison, tri naturel), l'ordre chronologique des données
 * calculées (page saison, match précédent / suivant, matchs d'un joueur). Rien n'est écrit.
 * Usage : php tests/ordre-journee.php
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Admin\FicheForm;
use App\Data\Derived;
use App\Data\Fiches;
use App\Data\Index;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};

// 1. Saisie : 1 à 9, vide sinon.
$doc = Fiches::blank('match');
$eq('modèle : pas d’ordre', $doc['match']['day_order'], null);
$errors = [];
$set = fn ($v) => FicheForm::apply($doc, ['match' => ['day_order' => $v]], $errors)['match']['day_order'];
$eq('saisie : « 1 », « 2 », vide, « 0 », « abc »', [$set('1'), $set('2'), $set(''), $set('0'), $set('abc')], [1, 2, null, null, null]);

// 2. Ordre chronologique des données calculées : date, puis ordre dans la journée.
$xamax = ['id' => 16500, 'date' => '2010-07-07', 'day' => 1, 'opp' => 'Neuchâtel Xamax'];
$saprissa = ['id' => 16499, 'date' => '2010-07-07', 'day' => 2, 'opp' => 'Deportivo Saprissa'];
$bale = ['id' => 16498, 'date' => '2010-07-10', 'day' => 0, 'opp' => 'FC Bâle'];
$list = [$bale, $saprissa, $xamax];
usort($list, fn ($a, $b) => Derived::chrono($a, $b));
$eq('chronologie : Xamax, Saprissa, puis Bâle', array_column($list, 'opp'), ['Neuchâtel Xamax', 'Deportivo Saprissa', 'FC Bâle']);
$eq('sans ordre saisi : égalité (ordre d’origine gardé)', Derived::chrono(['date' => '2010-07-07'], ['date' => '2010-07-07']), 0);

// 3. Listes de matchs (résumés de l'index) : mosaïque d'une saison (du premier au dernier) et tri naturel.
$sum = fn (array $x) => ['id' => $x['id'], 'type' => 'match', 'title' => $x['opp'], 'date' => null,
    'm' => ['date' => $x['date'], 'day' => $x['day'], 'season' => '2010-2011', 'home' => 'Sochaux', 'away' => $x['opp'], 'sh' => true]];
$items = [$sum($saprissa), $sum($xamax), $sum($bale)];
$sort = new ReflectionMethod(\App\Front\Mosaic::class, 'sort');
$eq('saison 2010-2011, du premier au dernier : Xamax avant Saprissa (pas l’ordre alphabétique)', array_column($sort->invoke(null, $items, 'ancien', 'matchs', 'annees-2010'), 'title'), ['Neuchâtel Xamax', 'Deportivo Saprissa', 'FC Bâle']);
$eq('du plus récent au plus ancien : Saprissa avant Xamax', array_column($sort->invoke(null, $items, 'recent', 'matchs', 'annees-2010'), 'title'), ['FC Bâle', 'Deportivo Saprissa', 'Neuchâtel Xamax']);
$nat = $items;
usort($nat, fn ($a, $b) => Index::naturalCompare($a, $b));
$eq('tri naturel (plus récent d’abord)', array_column($nat, 'title'), ['FC Bâle', 'Deportivo Saprissa', 'Neuchâtel Xamax']);

echo $fail ? "\n$fail échec(s).\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
