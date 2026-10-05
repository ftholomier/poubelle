<?php
/**
 * Boutique : poster souvenir d'un joueur (calque « poster » de genre « joueur »). Contenu tiré de la
 * fiche (grands chiffres recalculés d'après le tableau de statistiques, carrière saison par saison,
 * palmarès, records, grands matchs, jalons), propositions de joueurs, champs du client et
 * contrôles, résumé de commande, dessin vectoriel (SVG, PDF de l'imprimeur), dédicace.
 * Usage : php tests/poster-joueur.php (code de sortie 1 en cas d'échec). Les modèles existants sont
 * remis en place à la fin.
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Data\Collections;
use App\Shop\Catalog;
use App\Shop\Orders;
use App\Shop\PlayerPoster;
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

// 1. Contenu : Mecha Bazdarevic (exemple), Stéphane Paille (ligne « Total » d'origine fausse).
$d = PlayerPoster::data(PlayerPoster::SAMPLE);
$eq('Bazdarevic : nom, poste, années de joueur, chiffres', [$d['first'], $d['last'], $d['position'], $d['years'], $d['matches'], $d['seasons_n']], ['Mecha', 'Bazdarevic', 'Milieu offensif', '1987-1996', 344, 9]);
$eq('carrière saison par saison, palmarès sans les titres d’entraîneur', [count($d['seasons']) >= 9, $d['honours'][1] ?? ''], [true, 'Finaliste de la coupe de France 1988']);
$eq('records du club : premier au temps de jeu, pas de classement de groupe', [$d['records'][0]['rank'] ?? 0, in_array('Le cercle des 300 matchs', array_column($d['records'], 'label'), true)], [1, false]);
$eq('jalons : premier match, premier but, dernier match', array_keys($d['milestones']), ['Premier match', 'Premier but', 'Dernier match']);
$eq('grands matchs : la finale 1988 en fait partie', in_array('Metz 1-1 Sochaux', array_column($d['big'], 'teams'), true), true);
$p = PlayerPoster::data('10258');
$eq('Paille : totaux recalculés depuis les saisons (80 buts, pas 12)', [$p['matches'], $p['goals']], [224, 80]);
$eq('fiche inconnue, match ou identifiant invalide : pas de poster', [PlayerPoster::data('999999999'), PlayerPoster::data('abc'), PlayerPoster::data(Poster::SAMPLE)], [null, null, null]);

// 2. Propositions.
$eq('« paille » propose Stéphane Paille', array_column(PlayerPoster::search('paille'), 'id')[0] ?? null, '10258');
$eq('recherche vide', PlayerPoster::search(' '), []);

// 3. Modèle « poster joueur » : champs, contrôles, ligne de panier.
Catalog::saveModel(['id' => 'testposterj', 'name' => 'Poster joueur essai', 'support' => 'poster', 'color' => '#0E1F4D', 'active' => true,
    'sale' => ['price' => 2900, 'desc' => '', 'colors' => [], 'text_sizes' => false, 'positions' => false],
    'faces' => ['recto' => ['bg' => '#0E1F4D', 'layers' => [['id' => 'P', 'type' => 'poster', 'kind' => 'joueur', 'x' => 0, 'y' => 0, 'w' => 297, 'h' => 420]]]]]);
$m = Catalog::find('testposterj');
$eq('genre « joueur » conservé à l’enregistrement', [$m['faces']['recto']['layers'][0]['kind'] ?? '', Poster::kind($m), Poster::fieldOf($m), Catalog::unique($m)], ['joueur', 'joueur', 'poster_joueur', true]);
$eq('champs : joueur, prénom, nom', array_keys(Catalog::fields($m)), ['poster_joueur', 'poster_prenom', 'poster_nom']);
$bad = Catalog::check($m, ['poster_joueur' => Poster::SAMPLE, 'poster_prenom' => 'Jean', 'poster_nom' => 'Dupont']);
$eq('un identifiant de match n’est pas un joueur', array_keys($bad['errors']), ['poster_joueur']);
$line = Orders::line(['model' => 'testposterj', 'size' => 'A3', 'values' => ['poster_joueur' => '10258', 'poster_prenom' => 'Jean', 'poster_nom' => 'Dupont'], 'qty' => 3]);
$desc = Orders::describe($line['item']);
$eq('résumé : poster joueur, dédicace ; un seul exemplaire', [str_contains($desc, 'Poster joueur : Stéphane Paille'), str_contains($desc, 'pour Jean Dupont'), $line['item']['qty']], [true, true, 1]);
$eq('le modèle « match » garde ses champs', array_keys(Catalog::fields(['faces' => ['recto' => ['layers' => [['type' => 'poster']]]]])), ['poster_match', 'poster_prenom', 'poster_nom']);

// 4. Dessin : dans le format, dédicace, nom du joueur, PDF de l'imprimeur.
$L = PlayerPoster::layers(['x' => 0, 'y' => 0, 'w' => 297, 'h' => 420], $line['item']['values']);
$inside = true;
foreach (Vector::shapes($L) as $sh) {
    $b = Vector::bbox($sh['d']);
    $inside = $inside && (!$b || ($b[0] >= -0.2 && $b[1] >= -0.2 && $b[2] <= 297.2 && $b[3] <= 420.2));
}
$eq('tout le dessin reste dans le format A3', $inside, true);
$texts = implode(' ', array_column(array_filter($L, fn ($l) => $l['type'] === 'text'), 'text'));
$eq('dédicace, nom, numéro en attente, palmarès', [str_contains($texts, 'pour Jean Dupont'), str_contains($texts, 'Paille'), str_contains($texts, 'N° à la commande'), str_contains($texts, 'Palmarès')], [true, true, true, true]);
$face = $m['faces']['recto'] + ['w' => 297, 'h' => 420, 'bleed' => 3];
$svg = Vector::svg($face, $line['item']['values']);
$eq('SVG allégé', [str_contains($svg, '<defs>'), strlen($svg) < 900000], [true, true]);
$pdf = Catalog::printPdf($m, $line['item']['values'] + ['_poster_no' => '0042'], 'Poster joueur', [], 'A2');
preg_match('#/TrimBox \[([\d.]+) ([\d.]+) ([\d.]+) ([\d.]+)\]#', $pdf, $tb);
$eq('PDF A2 de l’imprimeur', [str_starts_with($pdf, '%PDF'), (int) round(((float) $tb[3] - (float) $tb[1]) / 72 * 25.4)], [true, 420]);

if ($backup === null) {
    @unlink($file);
} else {
    file_put_contents($file, $backup);
}
echo $fail ? "\n$fail échec(s)\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
