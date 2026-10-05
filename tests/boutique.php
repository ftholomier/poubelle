<?php
/**
 * Boutique, lot A : contours des lettres (TrueType), moteur de dessin (logo, textes, formes →
 * SVG et PDF vectoriel CMJN avec format fini, fonds perdus et traits de coupe), supports
 * (casquette et écharpe comprises), modèles (enregistrement, nettoyage des calques, champs du
 * client), aperçus de tous les supports, écrans réservés aux administrateurs.
 * Usage : php tests/boutique.php (code de sortie 1 en cas d'échec). Les modèles existants sont
 * remis en place à la fin.
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Admin\Router;
use App\Data\Collections;
use App\Shop\Catalog;
use App\Shop\Mockup;
use App\Shop\Texts;
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
$tfile = Collections::DIR . '/' . Texts::FILE . '.json';
$tbackup = is_file($tfile) ? (string) file_get_contents($tfile) : null;
@unlink($tfile);

// 1. Polices : contours des lettres, lettres accentuées (glyphes composés).
$f = Vector::font('display');
$a = $f->outline($f->glyphOf('A'));
$e = $f->outline($f->glyphOf('é'));
$eq('contours de « A » et de « é » (avec son accent)', [count($a) > 3, $a[0][0], count($e) > count($f->outline($f->glyphOf('e')))], [true, 'M', true]);

// 2. Dessin : logo (deux couleurs ou une), texte en tracés, formes.
$layers = [
    ['id' => 'l', 'type' => 'logo', 'x' => 10, 'y' => 10, 'w' => 50],
    ['id' => 'm', 'type' => 'logo', 'x' => 10, 'y' => 10, 'w' => 50, 'style' => 'mono', 'color' => '#FFFFFF'],
    ['id' => 't', 'type' => 'text', 'x' => 0, 'y' => 80, 'w' => 100, 'text' => 'Jaune et bleu', 'font' => 'display', 'size' => 30, 'color' => '#F6C400', 'align' => 'center'],
    ['id' => 'r', 'type' => 'rect', 'x' => 5, 'y' => 5, 'w' => 90, 'h' => 120, 'stroke' => '#0E1F4D', 'sw' => 1, 'r' => 4],
];
$sh = Vector::shapes($layers);
$by = fn ($id) => array_values(array_filter($sh, fn ($s) => $s['layer'] === $id));
$eq('logo en couleurs : 2 tracés (bleu, jaune) ; en une couleur : 1 ; texte : 1 tracé jaune ; cadre arrondi', [count($by('l')), $by('l')[0]['fill'], count($by('m')), $by('m')[0]['fill'], count($by('t')), $by('t')[0]['fill'], $by('r')[0]['stroke']], [2, '#094687', 1, '#FFFFFF', 1, '#F6C400', '#0E1F4D']);
$b = Vector::bbox($by('t')[0]['d']);
$eq('texte centré dans sa largeur (100 mm)', abs(($b[0] + $b[2]) / 2 - 50) < 1, true);
$client = ['id' => 'c', 'type' => 'text', 'x' => 0, 'y' => 0, 'w' => 40, 'text' => 'Prénom', 'mode' => 'client', 'field' => 'prenom', 'max' => 8, 'fit' => true, 'size' => 60, 'upper' => true];
$eq('champ du client : texte du client, coupé au maximum, en capitales ; sans réponse : le texte d’exemple', [Vector::textOf($client, ['prenom' => 'Jean-Baptiste']), Vector::textOf($client, [])], ['JEAN-BAP', 'PRÉNOM']);
$wide = Vector::bbox(Vector::shapes([$client], ['prenom' => 'Maximilien'])[0]['d']);
$eq('« Réduire pour tenir » : le prénom tient dans les 40 mm', $wide[2] - $wide[0] <= 40.5, true);

// 3. SVG et PDF de l'imprimeur.
$side = ['w' => 297, 'h' => 420, 'bleed' => 3, 'bg' => '#0E1F4D', 'layers' => $layers];
$svg = Vector::svg($side, [], true);
$eq('SVG : format avec fonds perdus, en mm, sans image', [str_contains($svg, 'viewBox="-3 -3 303 426"'), str_contains($svg, 'width="303mm"'), str_contains($svg, '<image')], [true, true, false]);
$pdf = Vector::pdf([['name' => 'Essai', 'side' => $side]]);
preg_match('#/TrimBox \[([\d. ]+)\]#', $pdf, $tb);
$trim = array_map('floatval', explode(' ', $tb[1] ?? '0 0 0 0'));
$eq('PDF : format fini A3 (TrimBox), fonds perdus (BleedBox), sans image', [str_starts_with($pdf, '%PDF-'), round(($trim[2] - $trim[0]) * 25.4 / 72), round(($trim[3] - $trim[1]) * 25.4 / 72), str_contains($pdf, '/BleedBox'), str_contains($pdf, '/Subtype /Image')], [true, 297.0, 420.0, true, false]);
$eq('couleurs CMJN de la charte (jaune : 0 20 100 0)', Vector::toCmyk('#F6C400'), [0, .2, 1, 0]);

// 4. Supports.
$sup = Catalog::supports();
$eq('supports : t-shirt, sweat, mug, tote bag, casquette, écharpe, posters, carte, sticker', array_values(array_intersect(['tshirt', 'sweat', 'mug', 'tote', 'casquette', 'echarpe', 'poster-a3', 'poster-a2', 'carte', 'sticker'], array_keys($sup))), ['tshirt', 'sweat', 'mug', 'tote', 'casquette', 'echarpe', 'poster-a3', 'poster-a2', 'carte', 'sticker']);
$eq('casquette : face avant 100 × 55 mm ; écharpe : 1 400 × 180 mm imprimée en entier', [[$sup['casquette']['faces']['avant']['w'], $sup['casquette']['faces']['avant']['h']], [$sup['echarpe']['faces']['recto']['w'], $sup['echarpe']['colors']]], [[100.0, 55.0], [1400.0, []]]);
$okMock = true;
foreach ($sup as $k => $s) {
    $fk = array_key_first($s['faces']);
    $r = Mockup::render($s['mockup'], $fk, $s['faces'][$fk] + ['bg' => '', 'layers' => [$layers[0]]], (string) (reset($s['colors']) ?: ''));
    $okMock = $okMock && str_starts_with($r['svg'], '<svg') && $r['area']['w'] > 0 && str_contains($r['svg'], '#094687');
}
$eq('aperçu sur le produit : tous les supports, logo visible', $okMock, true);

// 5. Modèles.
$clean = Catalog::cleanLayer(['type' => 'text', 'x' => 99999, 'font' => 'Comic', 'color' => 'rouge', 'size' => 9999, 'text' => str_repeat('a', 900), 'mode' => 'client', 'field' => 'Mon Prénom!']);
$eq('calque nettoyé : position, police, couleur, corps, longueur, nom de champ', [$clean['x'], $clean['font'], $clean['color'], $clean['size'], mb_strlen($clean['text']), $clean['field']], [3000.0, 'display', '#0E1F4D', 400.0, 600, 'monprnom']);
$m = Catalog::saveModel(['name' => 'Essai test', 'support' => 'casquette', 'color' => '#1A1A1A', 'faces' => ['avant' => ['layers' => [$client + ['label' => 'Votre prénom']]]]]);
$back = Catalog::find($m['id']);
$eq('modèle enregistré et relu : support, couleur, champs du client', [$back['support'], $back['color'], array_keys(Catalog::fields($back)), Catalog::fields($back)['prenom']['label']], ['casquette', '#1A1A1A', ['prenom'], 'Votre prénom']);
Catalog::deleteModel($m['id']);
$eq('modèle supprimé', Catalog::find($m['id']), null);

// 6. Banque de textes : listes de départ, choix du client limité aux phrases validées, IA.
$lists = Texts::lists();
$eq('listes de départ : 46 slogans validés, 14 anecdotes à faire valider', [$lists[0]['id'], count(Texts::choices('slogans')), $lists[1]['id'], count($lists[1]['items']), count(Texts::choices('anecdotes'))], ['slogans', 46, 'anecdotes', 14, 0]);
$pick = ['id' => 'p', 'type' => 'text', 'x' => 0, 'y' => 0, 'w' => 200, 'text' => 'Exemple', 'mode' => 'client', 'field' => 'phrase', 'list' => 'slogans', 'max' => 5];
$eq('choix dans une liste : phrase validée acceptée (sans coupure), texte inventé refusé', [Vector::textOf($pick, ['phrase' => 'Né pour rugir.']), Vector::textOf($pick, ['phrase' => 'Texte inventé'])], ['Né pour rugir.', 'Exemple']);
$eq('champ du client relié à la liste (phrases proposées)', [Catalog::cleanLayer($pick)['list'], count(Catalog::fields(['faces' => [['layers' => [Catalog::cleanLayer($pick)]]]])['phrase']['choices'])], ['slogans', 46]);
Texts::$ai = fn ($sys, $ask) => json_encode(['Né pour rugir.', 'Bonal, même sous la pluie.', 'Le lion ne s\'excuse pas.']);
$n = Texts::suggest('slogans', 10);
$sl = Texts::find('slogans');
$last = end($sl['items']);
$eq('IA : doublons écartés, nouvelles phrases « à valider », pas encore proposées au client', [$n, $last['text'], $last['ok'], count(Texts::choices('slogans'))], [2, 'Le lion ne s’excuse pas.', false, 46]);
Texts::$ai = null;

// 7. Accès.
$eq('écrans de la boutique réservés aux administrateurs', [Router::adminOnly('/admin/boutique'), Router::adminOnly('/admin/boutique/modeles/abc/pdf'), Router::adminOnly('/admin/boutique/textes')], [true, true, true]);

if ($tbackup === null) {
    @unlink($tfile);
} else {
    file_put_contents($tfile, $tbackup);
}
if ($backup === null) {
    @unlink($file);
} else {
    file_put_contents($file, $backup);
}
echo $fail ? "\n$fail échec(s)\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
