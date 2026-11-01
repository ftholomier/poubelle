<?php
/**
 * Orthographe « Manac'h » (DataFixes::manach) : graphies fautives corrigées, bonne graphie, adresses,
 * noms de fichiers et autres mots jamais touchés. Rien n'est écrit.
 * Usage : php tests/manach.php
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Services\DataFixes as D;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
$cases = [
    'Manac sans h (buteur)' => ["Manac 18'", "Manac'h 18'"],
    'Manach sans apostrophe' => ['Erwan Manach', "Erwan Manac'h"],
    'Manach’ (apostrophe après le h)' => ['Erwan Manach’ revient', 'Erwan Manac’h revient'],
    'Mana’ch' => ['(blessure de Mana’ch)', '(blessure de Manac’h)'],
    'fin de phrase' => ['appeler Erwan Manach’. »', 'appeler Erwan Manac’h. »'],
    'capitales (compositions)' => ['MANAC Erwan', "MANAC'H Erwan"],
    'entité HTML' => ['Manach&#8217; est là', 'Manac’h est là'],
    'bonne graphie inchangée' => ["Erwan Manac'h, Manac’h et MANAC'H", "Erwan Manac'h, Manac’h et MANAC'H"],
    'bonne graphie en entité inchangée' => ['Manac&#8217;h', 'Manac&#8217;h'],
    'charnière Manac’h-Flachez inchangée' => ["Manac'h-Flachez", "Manac'h-Flachez"],
    'adresse inchangée' => ['/joueurs/erwan-manach/', '/joueurs/erwan-manach/'],
    'nom de fichier inchangé' => ['2025/05/Manach-Reinoso.png', '2025/05/Manach-Reinoso.png'],
    'image dans un texte inchangée' => ['<img src="/media/2026/07/Erwan-Manach-en-mars.png">', '<img src="/media/2026/07/Erwan-Manach-en-mars.png">'],
    'autres mots inchangés' => ['Manacle, Manchester, Managua', 'Manacle, Manchester, Managua'],
];
foreach ($cases as $label => [$in, $out]) {
    $eq($label, D::manach($in), $out);
}
echo $fail ? "\n$fail échec(s).\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
