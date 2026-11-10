<?php
/**
 * IA coupée pour un musée (Réglages › Coûts IA, « Couper toute l'IA de ce musée », demande : musée de
 * Paris en pause, plus aucune dépense) : Gemini indisponible, tout appel refusé avant de partir, tâches
 * d'IA sautées. Réglages remis en état à la fin. Usage : php tests/ia-coupee.php
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Settings;
use App\Services\Cron;
use App\Services\Gemini;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
$saved = is_file(Settings::FILE) ? (string) file_get_contents(Settings::FILE) : null;
$tmp = sys_get_temp_dir() . '/ia-coupee-' . bin2hex(random_bytes(3));
mkdir($tmp);
try {
    Settings::save(['ai.gemini_api_key' => 'cle-essai', 'couts.ia_off' => false]);
    $eq('IA allumée : clé disponible', [Gemini::off(), Gemini::ready()], [false, true]);
    Settings::save(['couts.ia_off' => true]);
    $eq('IA coupée : Gemini indisponible', [Gemini::off(), Gemini::ready(), Gemini::key()], [true, false, '']);
    $refused = '';
    try {
        Gemini::generate([['role' => 'user', 'text' => 'Bonjour']]);
    } catch (\RuntimeException $e) {
        $refused = $e->getMessage();
    }
    $eq('IA coupée : appel refusé avant de partir', str_contains($refused, 'IA coupée pour ce musée'), true);
    Cron::$stateFile = "$tmp/cron.json";
    Cron::$lockFile = "$tmp/cron.lock";
    $calls = [];
    Cron::$runner = function (string $t) use (&$calls) {
        $calls[] = $t;
        return null;
    };
    ob_start();
    Cron::run(null, 30);
    ob_end_clean();
    $eq('IA coupée : aucune tâche d’IA lancée', array_values(array_intersect($calls, Cron::AI_TASKS)), []);
    $eq('IA coupée : les autres tâches tournent', in_array('publication', $calls, true), true);
} finally {
    Cron::$runner = null;
    $saved === null ? @unlink(Settings::FILE) : file_put_contents(Settings::FILE, $saved);
    exec('rm -rf ' . escapeshellarg($tmp));
}
echo $fail ? "\n$fail échec(s).\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
