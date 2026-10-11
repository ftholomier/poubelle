<?php
/**
 * Traductions anglaises des libellés passés au profil du club : un libellé du code qui contient
 * {site}, {sigle_de}… et qui n'est pas encore traduit reprend la traduction de sa forme « Sochaux »
 * (l'ancien libellé), dans laquelle les valeurs du profil redeviennent des variables.
 *
 * Usage : php scripts/club-i18n.php [--essai]
 */

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Club;

$file = DATA_PATH . '/i18n/en.json';
$en = json_decode((string) file_get_contents($file), true) ?: [];
$vars = Club::vars();
$keys = array_map(fn ($k) => '{' . $k . '}', array_keys($vars));

// Valeurs du profil → variables, les plus longues d'abord (« Sochaux Rétro » avant « Sochaux »).
$back = [];
foreach ($vars as $k => $v) {
    if (mb_strlen($v) >= 4 && !isset($back[$v]) && !in_array($k, ['id', 'team_regex', 'sport'], true)) {
        $back[$v] = '{' . $k . '}';
    }
}
uksort($back, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APP_ROOT, FilesystemIterator::SKIP_DOTS));
$added = 0;
$missing = [];
foreach ($it as $f) {
    $p = $f->getPathname();
    if (!str_ends_with($p, '.php') || preg_match('#/(data|storage|vendor|tests|docs)/#', $p)) {
        continue;
    }
    preg_match_all('/\bt\(\s*(\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*")/s', (string) file_get_contents($p), $m);
    foreach ($m[1] as $lit) {
        $s = $lit[0] === "'" ? str_replace(["\\'", '\\\\'], ["'", '\\'], substr($lit, 1, -1)) : stripcslashes(substr($lit, 1, -1));
        if (isset($en[$s]) || !array_filter($keys, fn ($k) => str_contains($s, $k))) {
            continue;
        }
        $old = club($s);
        if (!isset($en[$old])) {
            $missing[$s] = str_replace(APP_ROOT . '/', '', $p);
            continue;
        }
        $en[$s] = strtr($en[$old], $back);
        $added++;
    }
}
echo "$added traduction(s) reportée(s)\n";
foreach ($missing as $s => $p) {
    echo "Sans traduction : $s ($p)\n";
}
if (!in_array('--essai', $argv, true) && $added) {
    file_put_contents($file, json_encode($en, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
}
