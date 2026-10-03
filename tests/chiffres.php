<?php
/**
 * « Les chiffres du FCSM » (App\Services\Chiffres) : tableaux de carrière, tableaux recopiés,
 * buts minute par minute, séries, passeurs, quotas et mise en forme, puis les 100 chiffres
 * calculés sur les données du musée (français et anglais).
 * Usage : php tests/chiffres.php (code de sortie 1 en cas d'échec). N'écrit rien.
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Services\Chiffres as C;
use App\Services\I18n;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};

// Tableaux de carrière.
$k = C::career(['headers' => ['Saisons', 'Division 1', 'Buts', 'Division 2', 'Buts', 'Coupe de France', 'Buts', 'Total matches', 'Total Buts'], 'rows' => [
    ['1933 - 1934', '23', '23', '-', '-', '2', '2', '25', '25'],
    ['1945 - 1946', '?', '?', '-', '-', '?', '?', '?', '?'],
    ['1946 - 1947', '-', '-', '?', '28', '?', '?', '?', '28'],
    ['1947-1948', '28', '12', '-', '-', '4', '3', '32', '15'],
    ['TOTAL', '51', '35', '?', '28', '6', '5', '57', '68'],
]]);
$eq('carrière : totaux, saisons (même aux chiffres perdus), meilleure saison', [$k['m'], $k['g'], $k['seasons'], $k['from'], $k['to'], $k['best']['g'], $k['best']['y']], [57, 68, 4, 1933, 1948, 28, 1946]);
$eq('carrière : par compétition', [$k['by']['d1'], $k['by']['d2'], $k['by']['cdf']], [['m' => 51, 'g' => 35], ['g' => 28], ['m' => 6, 'g' => 5]]);
$k = C::career(['headers' => ['Saisons', 'Division 1', 'Buts', 'Coupe Drago', 'Buts', 'Total matchs', 'Total Buts'], 'rows' => [
    ['1952 - 1953', '34', '16', '5', '8', '', ''], ['1953 - 1954', '27', '9', '1', '1', '', ''], ['TOTAL', '61', '25', '6', '9', '67', '34'],
]]);
$eq('carrière sans totaux par saison : somme des compétitions', [$k['m'], $k['g'], $k['by']['drago']], [67, 34, ['m' => 6, 'g' => 9]]);
$eq('carrière vide', C::career(['headers' => ['Saisons', 'Division 1', 'Buts'], 'rows' => [['1990-1991', '-', '-'], ['Total', '0', '0']]]), null);
$eq('compétitions des colonnes', array_map([C::class, 'compKey'], ['Division 1', 'Division2', 'Ligue 2', 'Coupe de l\'UEFA', 'Barrages D1/D2', 'Coupe Drago', 'Championnat de France professionnel', 'National 1', 'Qualification Ligue Europa', 'Trophée des champions', 'Coupe de la Ligue']),
    ['d1', 'd2', 'd2', 'europe', 'barrages', 'drago', 'd1', 'national', 'europe', 'autre', 'cdl']);

// Tableaux recopiés d'une fiche à l'autre.
$car = ['from' => 1990, 'to' => 1991, 'm' => 22, 'g' => 4];
$P = [1 => ['career' => $car], 2 => ['career' => $car], 3 => ['career' => $car], 4 => ['career' => $car], 5 => ['career' => $car], 6 => ['career' => $car]];
$periods = [1 => [1990, 1991], 2 => [1949, 1951], 3 => [null, null], 4 => [1989, 1992], 5 => [1990, 1993], 6 => [1990, 1991]];
$out = C::dropCopiedCareers($P, $periods, [1 => 'a', 2 => 'a', 3 => 'a', 4 => 'b', 5 => 'b', 6 => 'c']);
$eq('tableau recopié : gardé pour la seule fiche aux bonnes dates', [isset($out[1]['career']), isset($out[2]['career']), isset($out[3]['career'])], [true, false, false]);
$eq('tableau recopié sur deux fiches aux bonnes dates : écarté partout ; tableau unique gardé', [isset($out[4]['career']), isset($out[5]['career']), isset($out[6]['career'])], [false, false, true]);
$eq('dates de la fiche et saisons du tableau', [C::periodMatches($car, [1990, 1991]), C::periodMatches($car, [1949, 1951]), C::periodMatches($car, [null, null]), C::periodMatches($car, [1988, null])], [true, false, false, true]);

// Buts minute par minute (temps forts).
$hl = fn (string $min, string $score) => ['minute' => $min, 'text' => 'But', 'goal' => true, 'score' => $score];
$m = ['sochaux_home' => false, 'score' => ['home' => 1, 'away' => 2], 'highlights' => [$hl('10', '1-0'), ['minute' => '30', 'text' => 'Occasion', 'goal' => false, 'score' => null], $hl('50', '1-1'), $hl('90+2', '1-2')]];
$seq = C::goalSequence($m);
$eq('déroulé : qui marque, à quelle minute (Sochaux à l’extérieur)', array_map(fn ($g) => [$g['min'], $g['add'], $g['us']], $seq ?? []), [[10, 0, false], [50, 0, true], [90, 2, true]]);
$eq('déroulé incohérent (deux buts d’un coup) : ignoré', C::goalSequence(['sochaux_home' => true, 'score' => ['home' => 2, 'away' => 0], 'highlights' => [$hl('10', '2-0')]]), null);
$eq('déroulé qui ne retrouve pas le score final : ignoré', C::goalSequence(['sochaux_home' => true, 'score' => ['home' => 2, 'away' => 0], 'highlights' => [$hl('10', '1-0')]]), null);
$eq('déroulé dans le désordre : ignoré', C::goalSequence(['sochaux_home' => true, 'score' => ['home' => 2, 'away' => 0], 'highlights' => [$hl('60', '1-0'), $hl('20', '2-0')]]), null);
$eq('minutes', [C::minute('45+2'), C::minute("90'"), C::minute('88’'), C::minute('x'), C::minute('0'), C::minute('')], [[45, 2], [90, 0], [88, 0], null, null, null]);

// Séries : jamais à cheval sur deux blocs de saisons, la plus récente à égalité.
$ok = fn ($id) => in_array($id, [1, 2, 3, 5, 6, 7, 8], true);
$eq('plus longue série', C::longestRun([[1, 2, 3, 4], [5, 6, 7, 8]], $ok), [5, 6, 7, 8]);
$eq('série coupée par un bloc de saisons manquantes', C::longestRun([[1, 2], [3]], fn () => true), [1, 2]);
$eq('à égalité : la plus récente', C::longestRun([[1, 2], [3, 4]], fn () => true), [3, 4]);

// Quotas et ordre des chapitres.
$eq('quotas : un chapitre trop court est complété par les réserves des autres', C::quotas(['a' => 5, 'b' => 1, 'c' => 4], ['a' => 3, 'b' => 3, 'c' => 3], 9), ['a' => 4, 'b' => 1, 'c' => 4]);
$eq('quotas : pas assez de chiffres en tout', C::quotas(['a' => 1, 'b' => 1], ['a' => 3, 'b' => 3], 6), ['a' => 1, 'b' => 1]);
$eq('ordre de priorité d’un chapitre', array_column(C::ordered('scores', ['moyenne' => ['key' => 'moyenne'], 'inconnu' => ['key' => 'inconnu'], 'large-victoire' => ['key' => 'large-victoire']]), 'key'), ['large-victoire', 'moyenne', 'inconnu']);
$eq('100 chiffres prévus', array_sum(array_column(C::CHAPTERS, 2)), C::COUNT);

// Passeurs cités dans les récits.
$squad = [9771, 6470]; // Florian Martin, Ryad Boudebouz
$eq('passeur reconnu dans l’effectif du match', [C::passer('Sur une passe de Martin, Boudebouz trompe le gardien. (1-0)', $squad), C::passer('Sur un corner de Ryad Boudebouz, Martin reprend de la tête.', $squad)], [9771, 6470]);
$eq('pas de passeur cité, ou passeur absent de l’effectif', [C::passer('Frappe de Martin, le gardien est battu.', $squad), C::passer('Sur un centre de Larbi, Martin conclut.', $squad)], [null, null]);

// Âges et mise en forme.
$eq('âge en années et jours', [C::age('1997-04-09', '2016-01-19'), C::age('1980-04-15', '1998-04-29')], [[18, 285], [18, 14]]);
I18n::set('fr');
$eq('nombres et pourcentages en français', [C::num(5411061), C::num(0.9, 2), C::pct(0.543), C::nth(1), C::nth(88)], ["5\u{00A0}411\u{00A0}061", '0,90', "54\u{00A0}%", '1re', '88e']);
I18n::set('en');
$eq('nombres et pourcentages en anglais', [C::num(5411061), C::num(0.9, 2), C::pct(0.543), C::nth(1), C::nth(120)], ['5,411,061', '0.90', '54%', '1st', '120th']);
I18n::set('fr');

// Les 100 chiffres du musée.
$all = C::build();
$flat = C::flat($all);
$nums = [];
$okStat = true;
foreach ($all['chapters'] as $ch) {
    foreach ($ch['stats'] as $s) {
        $nums[] = $s['n'];
        $okStat = $okStat && $s['value'] !== '' && $s['label'] !== '' && $s['text'] !== '' && isset(C::SOURCES[$s['src']])
            && !array_filter($s['who'], fn ($w) => !str_starts_with((string) $w['href'], '/') || $w['name'] === '')
            && !array_filter($s['more'], fn ($w) => !str_starts_with((string) $w['href'], '/'));
    }
}
$eq('100 chiffres, numérotés de 1 à 100, clés uniques', [$all['count'], $nums, count($flat)], [100, range(1, 100), 100]);
$eq('11 chapitres, chaque chiffre complet (valeur, titre, texte, source, liens)', [count($all['chapters']), $okStat], [11, true]);
$eq('meilleur buteur et recordman des matchs', [$flat['buteur']['value'], $flat['buteur']['who'][0]['name'], $flat['matchs']['value'], $flat['matchs']['who'][0]['name']], ['254', 'Roger Courtois', '456', 'Albert Rust']);
$eq('plus longue invincibilité (saison 1987-1988)', [$flat['invincibilite']['value'], str_contains($flat['invincibilite']['text'], '1987')], ['32', true]);
// Garde-fous : compositions recopiées, date de naissance fausse, tableaux de carrière recopiés.
$eq('composition recopiée d’un autre match écartée (pas de série de 18 buts)', (int) $flat['serie-buteur']['value'] < 10, true);
$eq('date de naissance invraisemblable écartée (doyen)', $flat['doyen']['who'][0]['name'] !== 'Florent Ogier', true);
$eq('match de 1980 à la composition fausse écarté (plus jeune Lionceau)', $flat['plus-jeune']['who'][0]['name'] !== 'Stéphane Paille', true);
$eq('pas de chiffre en double (même match, même joueur)', [isset($flat['festival']), isset($flat['saisons'])], [false, false]);
I18n::set('en');
$en = C::flat(C::build());
I18n::set('fr');
$eq('version anglaise : titres traduits, liens /en/', [$en['buteur']['label'], str_starts_with($en['buteur']['who'][0]['href'], '/en/'), $en['buteur']['value']], ['The all-time top scorer', true, '254']);

echo $fail ? "\n$fail échec(s)\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
