<?php
/**
 * Brèves sans « &nbsp; » en toutes lettres (ticket du 01/11/2026) : l'éditeur du back-office envoie
 * l'espace insécable sous la forme « &nbsp; » même sans mise en forme ; l'enregistrement
 * (Html::clean) la décode au lieu de l'échapper de nouveau. Affichage protégé (rich_inline, plain,
 * brèves vides retirées, PDF) ; correction ponctuelle des brèves déjà enregistrées
 * (DataFixes « 2026-11-breves-nbsp »), validation gardée. Fiche d'essai créée puis supprimée.
 * Usage : php tests/breves-nbsp.php
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Admin\Html;
use App\Data\Fiches;
use App\Services\DataFixes;
use App\Services\Validation;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
$nb = "\u{00A0}";

// 1. Enregistrement : ce que l'éditeur envoie pour une brève sans mise en forme.
$eq('éditeur : espace insécable décodée, « & » échappé une seule fois', Html::clean('Le stade&nbsp;: 12 000 spectateurs &amp; du soleil'), "<p>Le stade{$nb}: 12 000 spectateurs &amp; du soleil</p>");
$eq('éditeur : brève faite seulement d’un « &nbsp; » : vide', Html::clean('&nbsp;'), '');
$eq('éditeur : « &amp;nbsp; » déjà abîmé, ramené à une espace insécable', Html::clean('<p>Le stade&amp;nbsp;: 12 000</p>'), "<p>Le stade{$nb}: 12 000</p>");
$eq('texte tapé avec « < » : toujours échappé', Html::clean('Score &lt;script&gt; 3 < 4'), '<p>Score &lt;script&gt; 3 &lt; 4</p>');

// 2. Affichage.
$eq('affichage : HTML abîmé (« &amp;nbsp; ») montré avec une espace insécable', rich_inline('<p>Le stade&amp;nbsp;: 12 000 &amp;amp; plus</p>'), 'Le stade&nbsp;: 12 000 &amp; plus');
$eq('affichage : texte sans balise avec « &nbsp; »', rich_inline('Le stade&nbsp;: 12 000'), "Le stade{$nb}: 12 000");
$eq('affichage : brève vide (« &nbsp; », paragraphe vide)', [rich_inline('&nbsp;'), rich_inline('<p>&amp;nbsp;</p>'), rich_inline(' ')], ['', '', '']);
$eq('texte brut (PDF, audio, kit souvenirs)', plain('<p>Le stade&amp;nbsp;: 12 000</p>'), 'Le stade : 12 000');
$eq('brèves à montrer : vides retirées, ordre gardé', \App\Front\Fiche::breves(['Un', '&nbsp;', ['text' => 'Deux'], '<p>&amp;nbsp;</p>']), ['Un', ['text' => 'Deux']]);

// 3. Correction d'une brève enregistrée.
$eq('correction : « &amp;nbsp; » → espace insécable', DataFixes::breveNbsp('<p>Le stade&amp;nbsp;: 12 000</p>'), "<p>Le stade{$nb}: 12 000</p>");
$eq('correction : espaces doublées, début et fin', DataFixes::breveNbsp('&nbsp; Le&nbsp;&nbsp;stade &nbsp;'), 'Le stade');
$eq('correction : brève vide retirée', [DataFixes::breveNbsp('&nbsp;'), DataFixes::breveNbsp('<p>&amp;nbsp;</p>')], [null, null]);
$eq('correction : texte en gras gardé', DataFixes::breveNbsp('<strong>Arbitre</strong>&nbsp;: M. Wurtz'), "<strong>Arbitre</strong>{$nb}: M. Wurtz");

// 4. Correction ponctuelle sur une fiche validée : brèves corrigées, validation gardée, page propre.
$user = ['id' => 'essai-breves', 'name' => 'Essai brèves', 'email' => ''];
$id = 0;
try {
    $doc = Fiches::blank('match');
    $doc['title'] = 'Zzessai brèves nbsp';
    $doc['status'] = 'brouillon';
    $doc['match']['date'] = '1990-01-01';
    $doc['match']['season'] = '1989-1990';
    $doc['match']['home'] = ['name' => 'Sochaux', 'level' => null];
    $doc['match']['away'] = ['name' => 'Essai FC', 'level' => null];
    $doc['match']['breves'] = ['<p>Le stade&amp;nbsp;: 12 000</p>', '&nbsp;', 'Brève sans défaut', ['text' => 'Pluie&amp;nbsp;!']];
    $doc['i18n']['en']['match']['breves'] = ['The stadium&amp;nbsp;: 12,000'];
    $id = (int) Fiches::save($doc, $user, 'essai')['id'];
    Validation::set($id, $user);
    $run = new ReflectionMethod(DataFixes::class, 'brevesNbsp');
    $out = Validation::automatic(fn () => $run->invoke(null));
    $d = Fiches::fresh($id);
    $eq('fiche corrigée : brèves', $d['match']['breves'], ["<p>Le stade{$nb}: 12 000</p>", 'Brève sans défaut', ['text' => "Pluie{$nb}!"]]);
    $eq('fiche corrigée : traduction anglaise', $d['i18n']['en']['match']['breves'], ["The stadium{$nb}: 12,000"]);
    $eq('compte rendu', [in_array($id, $out['ids'], true), str_contains($out['message'], 'brève(s) vide(s) retirée(s)')], [true, true]);
    $eq('fiche validée : toujours validée', Validation::get($id) !== null, true);
    $out2 = Validation::automatic(fn () => $run->invoke(null));
    $eq('deuxième passage : rien à corriger sur cette fiche', in_array($id, $out2['ids'], true), false);
    // Page du match : aucune « &nbsp; » en toutes lettres, brèves numérotées sans trou.
    $d['match']['breves'][] = '<p>Encore&amp;nbsp;: une</p>';
    $d['match']['breves'][] = '&nbsp;';
    $req = new \App\Core\Request('GET', '/', [], [], [], ['HTTP_HOST' => 'localhost', 'REQUEST_URI' => '/', 'REMOTE_ADDR' => '127.0.0.1'], '');
    $html = \App\Front\Fiche::show($req, $d)->body;
    preg_match('#<ol class="breves">(.*?)</ol>#s', $html, $m);
    $eq('page : pas de « &nbsp; » en toutes lettres dans les brèves', str_contains($m[1] ?? '', '&amp;nbsp;') || str_contains(strip_tags($m[1] ?? ''), '&amp;'), false);
    $eq('page : 4 brèves numérotées de 01 à 04', [substr_count($m[1] ?? '', '<li>'), str_contains($m[1] ?? '', '>04<'), str_contains($m[1] ?? '', '>05<')], [4, true, false]);
} finally {
    if ($id) {
        Validation::clear($id, $user);
        Fiches::destroy($id, $user);
        exec('rm -rf ' . escapeshellarg(Fiches::VERSIONS . "/$id"));
    }
}
echo $fail ? "\n$fail échec(s).\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
