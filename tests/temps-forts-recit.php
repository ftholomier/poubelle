<?php
/**
 * Temps forts repris du récit (App\Services\RecitHighlights) : buts reconnus au score qui avance d'un
 * but (« 0-1. » puis « 0-2. », gras ou non, même avec « hors-jeu » dans le texte), score glissé dans
 * une phrase ou inchangé jamais compté, résumé recopié deux fois, et passe qui coche seulement « But ».
 * Lecture seule : aucune fiche n'est enregistrée.
 * Usage : php tests/temps-forts-recit.php (code 1 si échec).
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Services\RecitHighlights as H;

$ok = 0;
$ko = 0;
$t = function (string $label, $got, $exp) use (&$ok, &$ko) {
    if ($got === $exp) {
        $ok++;
        return;
    }
    $ko++;
    echo "ÉCHEC $label\n  obtenu: " . json_encode($got, JSON_UNESCAPED_UNICODE) . "\n  attendu: " . json_encode($exp, JSON_UNESCAPED_UNICODE) . "\n";
};
$doc = fn (string $html, array $score = ['home' => 0, 'away' => 3], array $hl = []) => ['type' => 'match',
    'sections' => [['title' => 'Résumé de la rencontre', 'html' => $html]], 'match' => ['score' => $score, 'highlights' => $hl]];
$goals = fn (array $d) => implode(' ', array_map(fn ($h) => $h['minute'] . ($h['goal'] ? '✔' : '·'), H::extract($d)));

// 1. Cas du ticket (Sochaux–Zagreb, 14/7/2000) : le 2e but parle de hors-jeu, il compte quand même.
$zag = ['43’ : Polovanec ouvre pour Balaban, qui fusille Villechalane avec un tir sous la barre. 0-1.',
    '60’ : Mikic marque, tout en étant en position de hors-jeu. 0-2.',
    '75’ : Sokota conclut la rencontre, avec un troisième but. 0-3.'];
$t('1 paragraphes', $goals($doc(implode('', array_map(fn ($l) => "<p>$l</p>", $zag)))), '43✔ 60✔ 75✔');
$t('1 gras', $goals($doc(implode('', array_map(fn ($l) => "<p><strong>$l</strong></p>", $zag)))), '43✔ 60✔ 75✔');
$t('1 puces', $goals($doc('<ul>' . implode('', array_map(fn ($l) => "<li>$l</li>", $zag)) . '</ul>')), '43✔ 60✔ 75✔');

// 2. Score glissé dans une phrase, score inchangé : pas un but.
$t('2 « signer le 0-2 »', $goals($doc('<p>41\' : Prevot manque sa sortie, le ballon termine au fond. (0-1)</p><p>55\' : Contre de Tramoni avant de centrer sur Cuyper à deux doigts de signer le 0-2.</p><p>68\' : Penalty transformé par Bayala. (0-2)</p>')), '41✔ 55· 68✔');
$t('2 score inchangé', $goals($doc('<p>30\' : Frau marque. 1-0.</p><p>94\' : Coup de sifflet final, Sochaux gagne (1-0)</p>', ['home' => 1, 'away' => 0])), '30✔ 94·');

// 3. But refusé sans score : toujours rien ; score qui saute de deux buts : règles habituelles (gras).
$t('3 refusé', $goals($doc('<p><strong>20\' : Frau marque mais le but est refusé pour hors-jeu.</strong></p>')), '20·');
$t('3 saut de score', $goals($doc('<p>50\' : Frau marque. 2-0.</p><p><strong>70\' : Frau marque encore. 3-0.</strong></p>', ['home' => 3, 'away' => 0])), '50· 70✔');

// 4. Résumé recopié deux fois : la copie repart de 0-0.
$one = '<p>10\' : Frau marque. 1-0.</p><p>80\' : Égalisation adverse. 1-1.</p>';
$t('4 copie', $goals($doc($one . $one, ['home' => 1, 'away' => 1])), '10✔ 80✔ 10✔ 80✔');

// 5. Passe sur les temps forts déjà enregistrés : coche seulement « But », rien d'ajouté ni de retiré.
$hl = [['minute' => '43', 'text' => 'Polovanec ouvre pour Balaban, qui fusille Villechalane avec un tir sous la barre. 0-1.', 'goal' => true, 'score' => '0-1'],
    ['minute' => '60', 'text' => 'Mikic marque, tout en étant en position de hors-jeu. 0-2.', 'goal' => false, 'score' => '0-2']];
$d = $doc(implode('', array_map(fn ($l) => "<p>$l</p>", $zag)), ['home' => 0, 'away' => 3], $hl);
$t('5 coché', H::markGoals($d), 1);
$t('5 minutes inchangées', array_column($d['match']['highlights'], 'minute'), ['43', '60']);
$t('5 60\' but', $d['match']['highlights'][1]['goal'], true);
$t('5 rejoué', H::markGoals($d), 0);
$d = $doc(implode('', array_map(fn ($l) => "<p>$l</p>", $zag)), ['home' => 0, 'away' => 1], $hl);
$t('5 plus de buts que le score final : rien', H::markGoals($d), 0);
$t('5 sans temps forts : rien', (function () use ($doc, $zag) { $d = $doc(implode('', array_map(fn ($l) => "<p>$l</p>", $zag))); return H::markGoals($d); })(), 0);

// 6. Toutes les façons de taper : retours à la ligne (texte collé, Maj+Entrée), bouton ⏱ Minute,
//    formats de minute et de score. Chaque fois : 4 temps forts, 3 buts.
$L = ["12' : Tir de Frau sur le poteau.", "30' : Frau marque. 1-0.", "55' : Égalisation de Lens. 1-1.", "80' : Pedretti donne la victoire. 2-1."];
$all = '12· 30✔ 55✔ 80✔';
$fmt = [
    'paragraphes' => fn ($l) => "<p>$l</p>",
    'puces' => fn ($l) => "<li>$l</li>",
    'tout en gras' => fn ($l) => "<p><strong>$l</strong></p>",
    'bouton ⏱ Minute' => fn ($l) => '<p>' . preg_replace("/^(\\d+)' : /", "<strong>$1'</strong>&nbsp;", $l) . '</p>',
    'score (1-0)' => fn ($l) => '<p>' . preg_replace('/\\. (\\d-\\d)\\.$/', ' ($1)', $l) . '</p>',
    'score 1–0' => fn ($l) => '<p>' . preg_replace('/(\\d)-(\\d)/', '$1–$2', $l) . '</p>',
    'score 1 à 0' => fn ($l) => '<p>' . preg_replace('/(\\d)-(\\d)/', '$1 à $2', $l) . '</p>',
    'score sans point' => fn ($l) => '<p>' . rtrim($l, '.') . '</p>',
    'score …' => fn ($l) => '<p>' . preg_replace('/\\.$/', '…', $l) . '</p>',
    '30e :' => fn ($l) => '<p>' . preg_replace("/^(\\d+)'/", '$1e', $l) . '</p>',
    '30ème minute :' => fn ($l) => '<p>' . preg_replace("/^(\\d+)'/", '$1ème minute', $l) . '</p>',
    '30 min -' => fn ($l) => '<p>' . preg_replace("/^(\\d+)' :/", '$1 min -', $l) . '</p>',
    "30' sans deux-points" => fn ($l) => '<p>' . preg_replace("/^(\\d+)' :/", "$1'", $l) . '</p>',
    '30’' => fn ($l) => '<p>' . str_replace("'", '’', $l) . '</p>',
    '30‘' => fn ($l) => '<p>' . str_replace("'", '‘', $l) . '</p>',
    "30''" => fn ($l) => '<p>' . str_replace("'", "''", $l) . '</p>',
    "(30')" => fn ($l) => '<p>' . preg_replace("/^(\\d+)'/", "($1')", $l) . '</p>',
    "30' —" => fn ($l) => '<p>' . str_replace("' :", "' —", $l) . '</p>',
];
foreach ($fmt as $label => $f) {
    $t("6 $label", $goals($doc(implode('', array_map($f, $L)), ['home' => 2, 'away' => 1])), $all);
}
$t('6 texte collé (<br>)', $goals($doc('<p>' . implode('<br>', $L) . '</p>', ['home' => 2, 'away' => 1])), $all);
$t('6 Maj+Entrée en gras', $goals($doc('<p>' . implode('<br>', array_map(fn ($l) => "<strong>$l</strong>", $L)) . '</p>', ['home' => 2, 'away' => 1])), $all);
$t('6 lignes collées', $goals($doc('<p>30\' : Frau marque. (1-0)55\' : Égalisation de Lens. (1-1)</p>', ['home' => 1, 'away' => 1])), '30✔ 55✔');
$t('6 retour à la ligne sans minute', array_column(H::extract($doc('<p>30\' : Frau marque<br>de la tête. 1-0.</p>', ['home' => 1, 'away' => 0])), 'text'), ['Frau marque de la tête. 1-0.']);
$t('6 90+2\' et 90\'+2', $goals($doc('<p>90+2\' : Frau marque. 1-0.</p><p>90\'+3 : Fin.</p>', ['home' => 1, 'away' => 0])), '90+2✔ 90+3·');
$t('6 « 43\' et 44\' : », « 55-61\' : »', $goals($doc('<p>43\' et 44\' : Deux occasions.</p><p>55-61\' : Penalty transformé. 1-0.</p>', ['home' => 1, 'away' => 0])), '43· 55✔');
$t('6 pas des minutes', $goals($doc('<p>1 - 1 entre Rouen et Sochaux.</p><p>4-4-2 : le dispositif.</p><p>2-1</p><p>12 000 spectateurs.</p>')), '');
// L'introduction du résumé s'arrête à la première minute, même après un retour à la ligne.
$t('6 intro', \App\Front\Fiche::resumeIntro('<p>Belle affiche.<br>30\' : Frau marque. 1-0.</p>'), '<p>Belle affiche.');

echo "$ok réussis, $ko échecs\n";
exit($ko ? 1 : 0);
