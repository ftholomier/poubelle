<?php
/**
 * Réactions d'après match reprises du récit (App\Services\RecitReactions) : « À venir » / « Néant »
 * remplacés, liste reflet du récit qui suit ses corrections, liste saisie à la main jamais réécrite
 * (seules les nouvelles puces du récit sont ajoutées), et passage par le formulaire de fiche.
 * Lecture seule : aucune fiche n'est enregistrée.
 * Usage : php tests/reactions-recit.php (code 1 si échec).
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Admin\FicheForm;
use App\Data\Fiches;
use App\Services\RecitReactions as R;

$ok = 0; $ko = 0;
$t = function ($label, $got, $exp) use (&$ok, &$ko) { if ($got === $exp) { $ok++; } else { $ko++; echo "ÉCHEC $label\n  obtenu: " . json_encode($got, JSON_UNESCAPED_UNICODE) . "\n  attendu: " . json_encode($exp, JSON_UNESCAPED_UNICODE) . "\n"; } };
$setRecit = function (array $d, string $html) { foreach ($d['sections'] as &$s) if (stripos((string) $s['title'], 'action') !== false) $s['html'] = $html; return $d; };

// 1. Liste « A venir », récit rempli en ligne (cas du 9/7/2000) : passe unique, remplacement.
$d = Fiches::fresh(17721);
$d = $setRecit($d, "<ul>\n<li><strong>Jean Fernandez :</strong> « <em>Bonne préparation, on a bien tenu.</em> »</li>\n<li><strong>Ivan Hasek</strong> : \" Strasbourg a manqué de rythme \"</li>\n</ul>");
$t('1 change', R::apply($d), true);
$t('1 liste', $d['match']['reactions'], [['who' => 'Jean Fernandez', 'text' => 'Bonne préparation, on a bien tenu.'], ['who' => 'Ivan Hasek', 'text' => 'Strasbourg a manqué de rythme']]);
$t('1 rejoué', R::apply($d), false);

// 2. Même chose à l'enregistrement du formulaire (avec $orig).
$o = Fiches::fresh(17721); $d = $setRecit($o, '<ul><li><strong>Jean Fernandez :</strong> « Bonne préparation »</li></ul>');
$t('2 change', R::apply($d, $o), true);
$t('2 liste', $d['match']['reactions'], [['who' => 'Jean Fernandez', 'text' => 'Bonne préparation']]);

// 3. « Néant. » → remplacé ; récit « A venir » seul → rien.
$o = Fiches::fresh(17721); $o['match']['reactions'] = [['who' => '', 'text' => 'Néant.']]; $d = $o;
$t('3 récit à venir : rien', R::apply($d, $o), false);
$t('3 néant gardé', $d['match']['reactions'], [['who' => '', 'text' => 'Néant.']]);
$d = $setRecit($o, '<p><strong>Faruk Hadzibegic : </strong>" <em>Un bon test.</em>"</p>');
$t('3 néant remplacé', R::apply($d, $o) ? $d['match']['reactions'] : null, [['who' => 'Faruk Hadzibegic', 'text' => 'Un bon test.']]);

// 4. Liste reflet du récit (1925) : une faute corrigée dans le récit est reportée, rien d'autre ne bouge.
$o = Fiches::fresh(1925);
$t('4 rien sans changement', (function () use ($o) { $d = $o; return R::apply($d, $o); })(), false);
$t('4 passe unique : rien', (function () use ($o) { $d = $o; return R::apply($d); })(), false);
$d = $o; foreach ($d['sections'] as &$s) if (stripos((string) $s['title'], 'action') !== false) $s['html'] = str_replace('Un match de rêve', 'Un vrai match de rêve', $s['html']); unset($s);
$t('4 change', R::apply($d, $o), true);
$t('4 correction reportée', str_starts_with($d['match']['reactions'][0]['text'], 'Un vrai match de rêve'), true);
$t('4 même nombre', count($d['match']['reactions']), count($o['match']['reactions']));
$t('4 autres intactes', array_slice($d['match']['reactions'], 1), array_slice($o['match']['reactions'], 1));

// 5. Liste saisie à la main, différente du récit : jamais réécrite, seule une nouvelle puce du récit est ajoutée.
$o = Fiches::fresh(1925); $o['match']['reactions'] = [['who' => 'Bernard Genghini', 'text' => 'Saisie à la main, <em>propre</em>.']];
$d = $o; foreach ($d['sections'] as &$s) if (stripos((string) $s['title'], 'action') !== false) $s['html'] = str_replace('Un match de rêve', 'Un vrai match de rêve', $s['html']); unset($s);
$t('5 correction seule : rien', R::apply($d, $o), false);
$d = $o; foreach ($d['sections'] as &$s) if (stripos((string) $s['title'], 'action') !== false) $s['html'] = str_replace('</ul>', '<li><strong>Patrick Revelli :</strong> « Quelle soirée au stade Bonal. »</li></ul>', $s['html']); unset($s);
$t('5 nouvelle puce ajoutée', R::apply($d, $o), true);
$t('5 main gardée', $d['match']['reactions'][0], $o['match']['reactions'][0]);
$t('5 ajout en fin', end($d['match']['reactions']), ['who' => 'Patrick Revelli', 'text' => 'Quelle soirée au stade Bonal.']);
$t('5 seulement 1 ajout', count($d['match']['reactions']), 2);

// 6. Pas un match / pas de section : rien.
$p = ['type' => 'personne', 'sections' => []]; $t('6 personne', R::apply($p), false);
$m = ['type' => 'match', 'sections' => [], 'match' => []]; $t('6 sans récit', R::apply($m), false);

// 7. Attentes reconnues, vraies citations courtes non.
foreach (['A venir', 'à venir', 'Néant.', 'Pas de réactions pour cette rencontre.', 'Pas de réactions d\'après match.', '.', 'Réactions à venir'] as $x) {
    $t("7 attente « $x »", R::placeholder(['text' => $x]), true);
}
foreach (['C\'est formidable', 'Notre joie est totale', 'les réactions sont à lire ci-contre.', 'Rien à dire, ils étaient meilleurs.', 'On a manqué d\'efficacité "'] as $x) {
    $t("7 citation « $x »", R::placeholder(['text' => $x]), false);
}

// 7 bis. Réactions collées en texte brut (une seule ligne de paragraphe, retours à la ligne) :
// une réaction par « Nom : », une citation sur deux lignes reste entière.
$p = ['type' => 'match', 'match' => [], 'sections' => [['title' => "Réactions d'après match", 'html' => '<p>Omar Daf : « Un vrai match de coupe. »<br>Mehdi Jeannin : « Tout le monde a été solidaire.<br>On a fait les efforts. »<br><strong>Maxence Prévot :</strong> Il faut une série.</p>']]];
$t('7 bis collé', R::extract($p), [['who' => 'Omar Daf', 'text' => 'Un vrai match de coupe.'], ['who' => 'Mehdi Jeannin', 'text' => 'Tout le monde a été solidaire. On a fait les efforts.'], ['who' => 'Maxence Prévot', 'text' => 'Il faut une série.']]);

// 7 ter. Affichage : les retours à la ligne d'un texte collé ou de l'ancien site ne coupent pas les phrases.
$t('7 ter citation d\'un seul tenant', quote_text("On a entamé le match avec la ferme intention de le gagner. C’est la\nraison pour laquelle on a pressé très haut. On ne peut pas être déçu, compte\r\ntenu du déroulement."), 'On a entamé le match avec la ferme intention de le gagner. C’est la raison pour laquelle on a pressé très haut. On ne peut pas être déçu, compte tenu du déroulement.');
$t('7 ter paragraphes gardés', quote_text("Premier paragraphe.\n\nSecond\nparagraphe."), "Premier paragraphe.\n\nSecond paragraphe.");

// 8. Formulaire de fiche : récit rempli à la place de « A venir » → réactions remplies à l'enregistrement.
$o = Fiches::fresh(17721);
$in = ['sections' => array_map(fn ($s) => ['title' => $s['title'], 'html' => stripos((string) $s['title'], 'action') !== false
    ? '<ul><li><strong>Jean Fernandez :</strong> « <em>Bonne préparation.</em> »</li></ul>' : $s['html']], $o['sections'])];
$errors = [];
$d = FicheForm::apply($o, $in, $errors);
$t('8 formulaire', $d['match']['reactions'] ?? null, [['who' => 'Jean Fernandez', 'text' => 'Bonne préparation.']]);
// Réactions saisies dans « Compo et événements » en même temps : gardées, la nouvelle puce du récit s'ajoute.
$in['match'] = ['reactions' => [['who' => 'Ivan Hasek', 'text' => 'Saisie à la main.']]];
$d = FicheForm::apply($o, $in, $errors);
$t('8 saisie gardée', array_column($d['match']['reactions'] ?? [], 'who'), ['Ivan Hasek', 'Jean Fernandez']);

echo "$ok réussis, $ko échecs\n";
exit($ko ? 1 : 0);
