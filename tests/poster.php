<?php
/**
 * Boutique : poster souvenir d'un match (calque « poster »). Contenu tiré de la fiche (compo, film,
 * chiffre, citations mot pour mot), propositions de matchs, champs du client et contrôles, contrôle
 * des propositions de l'IA (nombres et noms absents de la fiche écartés), dessin vectoriel (SVG
 * allégé, PDF de l'imprimeur), numéro de pièce attribué une seule fois, dédicace.
 * Usage : php tests/poster.php (code de sortie 1 en cas d'échec). Les modèles existants sont remis
 * en place à la fin.
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Data\Collections;
use App\Shop\Catalog;
use App\Shop\Orders;
use App\Shop\Poster;
use App\Shop\Vector;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
$file = Collections::DIR . '/' . Catalog::MODELS_FILE . '.json';
$backup = is_file($file) ? (string) file_get_contents($file) : null;
$numbers = STORAGE_PATH . '/shop/posters-numeros.json';
$nbackup = is_file($numbers) ? (string) file_get_contents($numbers) : null;

// 1. Contenu d'un match : finale de la Coupe de France 1988.
$d = Poster::data(Poster::SAMPLE);
$eq('finale 1988 : score, onze, chiffre, tribunes', [$d['home'], $d['sh'], $d['sa'], count($d['players']), $d['figure']['n'] ?? null, $d['spectators']], ['Metz', 1, 1, 11, '3', 44531]);
$eq('film : buts présents, prolongation et tirs au but en fin', [count(array_filter($d['film'], fn ($f) => $f['goal'])) >= 2, end($d['film'])['min']], [true, 'TAB']);
$eq('film : tweet recopié dans la fiche retiré', array_filter($d['film'], fn ($f) => str_contains($f['text'], 'twitter') || str_contains($f['text'], '#FCSM')), []);
$eq('citations de la fiche, mot pour mot', in_array('Faruk Hadzibegic', array_column($d['quotes'], 'who'), true), true);
$eq('match inconnu ou identifiant invalide : pas de poster', [Poster::data('999999999'), Poster::data('abc')], [null, null]);

// 2. Propositions.
$ids = array_column(Poster::search('metz 88'), 'id');
$eq('« metz 88 » propose la finale en premier', $ids[0] ?? null, Poster::SAMPLE);
$eq('recherche trop courte ou vide', [Poster::search(''), Poster::search('   ')], [[], []]);

// 3. Contrôle des textes de l'IA contre la fiche.
$src = Poster::source(\App\Data\Fiches::get((int) Poster::SAMPLE));
$eq('texte fidèle accepté', Poster::grounded('En 1937, leurs aînés avaient gagné un coupé 201.', $src), true);
$eq('nombre inventé refusé', Poster::grounded('Devant 60 000 spectateurs.', $src), false);
$eq('nom inventé refusé', Poster::grounded('Un but de Michel Platini.', $src), false);

// 4. Modèle « poster » : champs, contrôles, ligne de panier.
Catalog::saveModel(['id' => 'testposter', 'name' => 'Poster essai', 'support' => 'poster-a3', 'color' => '#0E1F4D', 'active' => true,
    'sale' => ['price' => 2900, 'desc' => '', 'colors' => [], 'text_sizes' => false, 'positions' => false],
    'faces' => ['recto' => ['bg' => '#0E1F4D', 'layers' => [['id' => 'P', 'type' => 'poster', 'x' => 0, 'y' => 0, 'w' => 297, 'h' => 420]]]]]);
$m = Catalog::find('testposter');
$eq('calque poster conservé à l’enregistrement', [$m['faces']['recto']['layers'][0]['type'], Poster::isFor($m)], ['poster', true]);
$eq('champs : match, prénom, nom', array_keys(Catalog::fields($m)), ['poster_match', 'poster_prenom', 'poster_nom']);
$bad = Catalog::check($m, ['poster_match' => '12', 'poster_prenom' => '<script>', 'poster_nom' => str_repeat('a', 31)]);
$eq('match hors liste, nom avec balise, nom trop long : refusés', array_keys($bad['errors']), ['poster_match', 'poster_prenom', 'poster_nom']);
$ok = Catalog::check($m, ['poster_match' => Poster::SAMPLE, 'poster_prenom' => 'Jean-Pierre', 'poster_nom' => 'D’Arcy']);
$eq('prénom composé et apostrophe acceptés', $ok['errors'], []);
$line = Orders::line(['model' => 'testposter', 'values' => ['poster_match' => Poster::SAMPLE, 'poster_prenom' => 'Jean'], 'qty' => 1]);
$eq('nom manquant : refusé', $line['error'] ?? '', 'Complétez « Nom ».');
$line = Orders::line(['model' => 'testposter', 'values' => ['poster_match' => Poster::SAMPLE, 'poster_prenom' => 'Jean', 'poster_nom' => 'Dupont'], 'qty' => 1]);
$eq('résumé de la commande', str_contains(Orders::describe($line['item']), 'Poster : Metz 1-1 Sochaux') && str_contains(Orders::describe($line['item']), 'pour Jean Dupont'), true);

// 5. Dessin : SVG (lettres réutilisées), dédicace, PDF de l'imprimeur.
$face = $m['faces']['recto'] + ['w' => 297, 'h' => 420, 'bleed' => 3];
$svg = Vector::svg($face, $line['item']['values']);
$eq('SVG : lettres définies une fois, réutilisées', [str_contains($svg, '<defs>'), substr_count($svg, '<use ') > 1000, strlen($svg) < 900000], [true, true, true]);
$shapes = Vector::shapes(Poster::layers(['x' => 0, 'y' => 0, 'w' => 297, 'h' => 420], $line['item']['values']));
$inside = true;
foreach ($shapes as $sh) {
    $b = Vector::bbox($sh['d']);
    $inside = $inside && (!$b || ($b[0] >= -0.2 && $b[1] >= -0.2 && $b[2] <= 297.2 && $b[3] <= 420.2));
}
$eq('tout le dessin reste dans le format A3', $inside, true);
$texts = implode(' ', array_column(array_filter(Poster::layers(['x' => 0, 'y' => 0, 'w' => 297, 'h' => 420], $line['item']['values']), fn ($l) => $l['type'] === 'text'), 'text'));
$eq('dédicace « pour Jean Dupont » et numéro en attente', [str_contains($texts, 'pour Jean Dupont'), str_contains($texts, 'N° à la commande')], [true, true]);
$pdf = Catalog::printPdf($m, $line['item']['values'] + ['_poster_no' => '0007'], 'Poster essai');
$eq('PDF de l’imprimeur : une page A3 + fonds perdus et marges', [str_starts_with($pdf, '%PDF'), (bool) preg_match('#/MediaBox \[0 0 9(1[0-9]|0[0-9])\.\d+ 12[0-9]{2}\.\d+\]#', $pdf)], [true, true]);

// 6. Formats A4, A3, A2 : le même dessin réduit ou agrandi à l'identique.
Catalog::saveModel(['id' => 'testposter', 'support' => 'poster'] + $m);
$m2 = Catalog::find('testposter');
$dims = [];
foreach (['A4', 'A3', 'A2'] as $sz) {
    $f2 = Catalog::scaleFace($m2['faces']['recto'], $sz);
    $dims[] = [(int) $f2['w'], (int) $f2['h'], (int) $f2['layers'][0]['w']];
    preg_match('#/TrimBox \[([\d.]+) ([\d.]+) ([\d.]+) ([\d.]+)\]#', Catalog::printPdf($m2, $line['item']['values'], 'Poster', [], $sz), $tb);
    $dims[] = (int) round(((float) $tb[3] - (float) $tb[1]) / 72 * 25.4);
}
$eq('A4, A3, A2 : format, cadre du poster, format fini du PDF', $dims, [[210, 297, 210], 210, [297, 420, 297], 297, [420, 594, 420], 420]);
$line2 = Orders::line(['model' => 'testposter', 'size' => 'A2', 'values' => $line['item']['values'], 'qty' => 1]);
$eq('format choisi par le client et résumé « Format A2 »', [$line2['item']['size'] ?? '', str_starts_with(Orders::describe($line2['item']), 'Format A2')], ['A2', true]);
$eq('un t-shirt n’est jamais redimensionné', Catalog::scaleFace(['w' => 280, 'h' => 350, 'layers' => []], 'A4')['w'], 280);

$eq('un seul exemplaire par article', Orders::line(['model' => 'testposter', 'size' => 'A3', 'values' => $line['item']['values'], 'qty' => 5])['item']['qty'], 1);
$eq('pièce unique (pastille)', Catalog::unique($m2), true);
[$mo, $opt] = Catalog::applyOptions($m2, ['color' => '#F6C400', 'tcolor' => '#FFFFFF']);
$eq('aucun choix de couleur pris en compte', [$mo['faces']['recto']['bg'], $opt['tcolor']], ['#0E1F4D', '']);

// 7. Numéro de pièce : attribué une seule fois par article.
@unlink($numbers);
$a = Poster::number('SR1-1');
$b = Poster::number('SR2-1');
$eq('numéros successifs, stables pour un même article', [$a, $b, Poster::number('SR1-1')], ['0001', '0002', '0001']);

if ($nbackup === null) {
    @unlink($numbers);
} else {
    file_put_contents($numbers, $nbackup);
}
if ($backup === null) {
    @unlink($file);
} else {
    file_put_contents($file, $backup);
}
echo $fail ? "\n$fail échec(s)\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
