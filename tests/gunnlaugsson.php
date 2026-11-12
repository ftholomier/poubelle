<?php
/**
 * Orthographe « Gunnlaugsson » (DataFixes::respell, ticket du 12/11/2026) : graphies fautives corrigées
 * dans la casse d'origine ; bonne graphie, adresses et noms de fichiers jamais touchés. Rien n'est
 * écrit. Usage : php tests/gunnlaugsson.php
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
$f = fn (string $s) => D::respell($s, D::GUNNLAUGSSON, 'Gunnlaugsson');
$eq('compositions et textes', [$f('GUNLAUGSSON Arnar'), $f('GUNNLAUGASSON Arnar'), $f('Arnar Gunlaugsson était en sélection'), $f('Pamic et Gunnlagsson)')], ['GUNNLAUGSSON Arnar', 'GUNNLAUGSSON Arnar', 'Arnar Gunnlaugsson était en sélection', 'Pamic et Gunnlaugsson)']);
$eq('casse gardée (titre de photo en minuscules)', $f('gunnlaugson'), 'gunnlaugsson');
$eq('bonne graphie inchangée', [$f('Arnar Gunnlaugsson'), $f('GUNNLAUGSSON')], ['Arnar Gunnlaugsson', 'GUNNLAUGSSON']);
$eq('adresses et noms de fichiers inchangés', [$f('2024/03/gunnlaugson.jpg'), $f('/joueurs/arnar-gunlaugsson/')], ['2024/03/gunnlaugson.jpg', '/joueurs/arnar-gunlaugsson/']);
echo $fail ? "\n$fail échec(s).\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
