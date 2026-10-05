<?php
/**
 * Boutique : anecdote sur un sujet choisi par le client (un match, un joueur, un entraîneur).
 * Propositions de sujets, fait tiré de la fiche du sujet, anecdote de l'IA vérifiée contre ce fait
 * (noms et nombres), réserve par sujet, et repli au hasard (avec un mot pour le client) sans IA.
 * Usage : php tests/anecdotes-sujets.php (code de sortie 1 en cas d'échec).
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Shop\Anecdotes;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
$anFile = STORAGE_PATH . '/shop/anecdotes-reserve.json';
$anSold = STORAGE_PATH . '/shop/anecdotes-vendues.json';
$bk = [is_file($anFile) ? file_get_contents($anFile) : null, is_file($anSold) ? file_get_contents($anSold) : null];
@unlink($anFile);
@unlink($anSold);

// 1. Propositions.
$paille = Anecdotes::topics('paille');
$eq('« paille » : Stéphane Paille, joueur', [$paille[0]['key'] ?? '', str_starts_with($paille[0]['label'] ?? '', 'Stéphane Paille')], ['p:10258', true]);
$eq('« metz 88 » : la finale en premier', Anecdotes::topics('metz 88')[0]['key'] ?? '', 'm:14188');
$eq('trop court, inconnu : rien', [Anecdotes::topics('a'), Anecdotes::topics('zzzzqq')], [[], []]);

// 2. Faits sur le sujet.
[$f] = Anecdotes::fact('m:14188');
$eq('match : date, affiche, buteur', [str_contains($f, '11 juin 1988'), str_contains($f, 'Metz 1-1 Sochaux'), str_contains($f, 'Paille')], [true, true, true]);
$ok = true;
for ($i = 0; $i < 6; $i++) {
    $ok = $ok && str_contains(Anecdotes::fact('p:10258')[0], 'Stéphane Paille') || str_contains(Anecdotes::fact('p:10258')[0], 'Paille');
}
$eq('joueur : chaque fait le nomme', $ok, true);
$facts = Anecdotes::topicFacts('p:10258');
$eq('joueur : record d’abord, puis son histoire, le simple « il marque » en dernier', [str_contains($facts[0][0], 'classement'), str_contains($facts[1][0], '224 matchs'), str_contains(end($facts)[0], 'marque pour Sochaux')], [true, true, true]);
$eq('« Une autre » : fait suivant', [Anecdotes::fact('p:10258', 0)[0] === $facts[0][0], Anecdotes::fact('p:10258', 1)[0] === $facts[1][0]], [true, true]);
$eq('pas de fait en double', count($facts), count(array_unique(array_column($facts, 0))));
$eq('sujet inconnu ou mal formé : refusé', [Anecdotes::topicFact('p:999999999'), Anecdotes::topicFact('m:abc'), Anecdotes::topicFact('../x')], [null, null, null]);

// 3. Anecdote sur le sujet : l'IA rédige d'après le fait du sujet, vérifiée.
$layer = ['id' => 'a', 'type' => 'text', 'x' => 0, 'y' => 0, 'w' => 250, 'h' => 60, 'text' => 'Anecdote', 'mode' => 'client', 'field' => 'anecdote', 'max' => 140, 'fit' => true, 'size' => 20, 'min' => 6, 'font' => 'serif-b'];
$asked = '';
Anecdotes::$ai = function ($sys, $ask) use (&$asked) {
    $asked = $ask;
    return 'Le 11 juin 1988, Stéphane Paille marque pour Sochaux en finale de la Coupe de France face à Metz.';
};
$r = Anecdotes::pick([$layer], [], true, 'm:14188');
$eq('le fait envoyé à l’IA est celui du sujet', str_contains($asked, 'Metz 1-1 Sochaux'), true);
$eq('anecdote sur le sujet, signée', [str_contains($r['text'] ?? '', 'Paille'), Anecdotes::valid($r['text'] ?? '', $r['sig'] ?? '')], [true, true]);
Anecdotes::$ai = fn ($sys, $ask) => 'Le 11 juin 1988, Michel Platini marque trois buts pour Sochaux.';
$eq('nom et nombre absents du fait : refusés', isset(Anecdotes::draw([$layer], [], 'm:14188')['text']) && str_contains(Anecdotes::draw([$layer], [], 'm:14188')['text'], 'Platini'), false);

// 4. Réserve par sujet ; sans IA, au hasard avec un mot pour le client.
$eq('réserve : resservie pour ce sujet, pas pour un autre', [Anecdotes::fromPool([$layer], [], 'm:14188')['text'] ?? '', isset(Anecdotes::fromPool([$layer], [], 'p:10258')['error'])], [$r['text'] ?? '', true]);
Anecdotes::$ai = fn ($sys, $ask) => 'Une phrase générique sur les Lionceaux et leur public fidèle.';
Anecdotes::draw([$layer]); // une anecdote au hasard dans la réserve
$r2 = Anecdotes::pick([$layer], [$r['text'] ?? ''], false, 'm:14188');
$eq('sujet épuisé et pas d’IA : une au hasard, en le disant', [$r2['text'] ?? '', isset($r2['note'])], ['Une phrase générique sur les Lionceaux et leur public fidèle.', true]);

Anecdotes::$ai = null;
foreach ([$anFile, $anSold] as $i => $file) {
    $bk[$i] === null ? @unlink($file) : file_put_contents($file, $bk[$i]);
}
echo $fail ? "\n$fail échec(s)\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
