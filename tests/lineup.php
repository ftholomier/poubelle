<?php
/**
 * Lecture des cellules de composition (App\Data\Lineup) sur les formats rencontrés dans
 * les fiches d'origine. Usage : php tests/lineup.php (code de sortie 1 en cas d'échec).
 */
declare(strict_types=1);

require __DIR__ . '/../app/Data/Lineup.php';

use App\Data\Lineup as L;

$fail = 0;
$eq = function ($label, $got, $exp) use (&$fail) { $ok = $got === $exp; if (!$ok) $fail++; echo ($ok ? 'OK   ' : 'FAIL ') . $label . ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ($ok ? '' : ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n"; };
// Buts
$eq("33'", L::goals("33'"), ['goals' => ['33'], 'own' => []]);
$eq("⚽ 45'+2 s.p.", L::goals("⚽ 45'+2 s.p."), ['goals' => ['45+2'], 'own' => []]);
$eq("31' csc", L::goals("31' csc"), ['goals' => [], 'own' => ['31']]);
$eq("⚽ 35' csc", L::goals("⚽ 35' csc"), ['goals' => [], 'own' => ['35']]);
$eq("12' csc, 45'", L::goals("12' csc, 45'"), ['goals' => ['45'], 'own' => ['12']]);
$eq("9' et 9'", L::goals("12' et 78'"), ['goals' => ['12', '78'], 'own' => []]);
$eq("⚽ ⚽ 9' 9'", L::goals("⚽ ⚽ 12' 78'"), ['goals' => ['12', '78'], 'own' => []]);
$eq("x2", L::goals("x2"), ['goals' => ['', ''], 'own' => []]);
$eq("TàB", L::goals("TàB"), ['goals' => [], 'own' => []]);
$eq("⚽", L::goals("⚽"), ['goals' => [''], 'own' => []]);
$eq("⚽⚽ 9' sp et 9'", L::goals("⚽⚽ 9' sp et 70'"), ['goals' => ['9', '70'], 'own' => []]);
$eq("90'+3", L::goals("90'+3"), ['goals' => ['90+3'], 'own' => []]);
$eq("9'+9 csc", L::goals("45'+1 csc"), ['goals' => [], 'own' => ['45+1']]);
$eq("9', 9' et 9' et 9'", L::goals("1', 2' et 3' et 4'"), ['goals' => ['1', '2', '3', '4'], 'own' => []]);
// Remplacements
$eq("Sortie 75'", L::subs("Sortie 75'"), ['in' => null, 'out' => '75']);
$eq("Entrée 60'", L::subs("Entrée 60'"), ['in' => '60', 'out' => null]);
$eq("↑ 46' ↓ 80'", L::subs("↑ 46' ↓ 80'"), ['in' => '46', 'out' => '80']);
$eq("↓ 60' ↑ 70'", L::subs("↓ 60' ↑ 70'"), ['in' => '70', 'out' => '60']);
$eq("Entrée 46' Sortie 80'", L::subs("Entrée 46' Sortie 80'"), ['in' => '46', 'out' => '80']);
$eq("↑ 9'+9", L::subs("↑ 90'+2"), ['in' => '90+2', 'out' => null]);
$eq("🔻 9'", L::subs("🔻 63'"), ['in' => null, 'out' => '63']);
$eq("Entée 9'", L::subs("Entée 70'"), ['in' => '70', 'out' => null]);
$eq("Sorite 9'", L::subs("Sorite 70'"), ['in' => null, 'out' => '70']);
$eq("Sorti à la 9'", L::subs("Sorti à la 70'"), ['in' => null, 'out' => '70']);
$eq("Entré à la 9'", L::subs("Entré à la 70'"), ['in' => '70', 'out' => null]);
$eq("Sortie 9ème", L::subs("Sortie 70ème"), ['in' => null, 'out' => '70']);
$eq("Entrée 9' et sortie 9'", L::subs("Entrée 46' et sortie 80'"), ['in' => '46', 'out' => '80']);
$eq("Entrée 9' / Sortie 9'", L::subs("Entrée 46' / Sortie 80'"), ['in' => '46', 'out' => '80']);
$eq("Sortie 9' Entrée9'", L::subs("Sortie 46' Entrée80'"), ['in' => '80', 'out' => '46']);
$eq("Entrée 9''", L::subs("Entrée 70''"), ['in' => '70', 'out' => null]);
$eq("↓ 9+9'", L::subs("↓ 45+1'"), ['in' => null, 'out' => '45+1']);
// Cartons
$eq("J 47'", L::cards("J 47'"), ['yellow' => ['47'], 'red' => []]);
$eq("J 35' R 80'", L::cards("J 35' R 80'"), ['yellow' => ['35'], 'red' => ['80']]);
$eq("🟨 9' 🟥 9'", L::cards("🟨 20' 🟥 70'"), ['yellow' => ['20'], 'red' => ['70']]);
$eq("J 9' 9' R 9'", L::cards("J 20' 50' R 50'"), ['yellow' => ['20', '50'], 'red' => ['50']]);
$eq("J9' et J9' R 9'", L::cards("J20' et J50' R 50'"), ['yellow' => ['20', '50'], 'red' => ['50']]);
$eq("CJ 9'", L::cards("CJ 40'"), ['yellow' => ['40'], 'red' => []]);
$eq("CR 9'", L::cards("CR 40'"), ['yellow' => [], 'red' => ['40']]);
$eq("J 9' puis R 9'", L::cards("J 20' puis R 80'"), ['yellow' => ['20'], 'red' => ['80']]);
$eq("↓ 9'", L::cards("↓ 75'"), ['yellow' => [], 'red' => []]);
$eq("9'", L::cards("75'"), ['yellow' => [], 'red' => []]);
$eq("J 9'+ 9", L::cards("J 90'+ 2"), ['yellow' => ['90+2'], 'red' => []]);
$eq("J 9ème", L::cards("J 12ème"), ['yellow' => ['12'], 'red' => []]);
$eq("R 9'", L::cards("R 88'"), ['yellow' => [], 'red' => ['88']]);
$eq("J 9' ; R 9'", L::cards("J 20' ; R 80'"), ['yellow' => ['20'], 'red' => ['80']]);
$eq("🟨 9'+9 🟥 9'", L::cards("🟨 45'+1 🟥 80'"), ['yellow' => ['45+1'], 'red' => ['80']]);
echo $fail ? "$fail échec(s)\n" : "Tout est bon\n";
exit($fail ? 1 : 0);
