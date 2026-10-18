<?php
/**
 * Tâches planifiées (App\Services\Cron) : tâches courtes (publications, notifications…) avant
 * les longues (IA…), ordre des longues qui tourne d'un passage à l'autre, budget de temps réel
 * du passage, intervalle respecté même pour une tâche qui n'a rien à faire (vérification GitHub
 * plus toutes les 5 minutes). Les tâches sont simulées ; état et verrou dans un dossier temporaire.
 * Usage : php tests/lot1-cron.php (code de sortie 1 en cas d'échec).
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Services\Cron;

$tmp = sys_get_temp_dir() . '/cron-lot1-' . bin2hex(random_bytes(4));
mkdir($tmp);
Cron::$stateFile = "$tmp/cron.json";
Cron::$lockFile = "$tmp/cron.lock";
$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};

$calls = [];
$slow = null;
Cron::$runner = function (string $task) use (&$calls, &$slow) {
    $calls[] = $task;
    if ($task === $slow) {
        usleep(6_000_000); // une tâche longue qui prend presque tout le temps restant
    }
    return match ($task) {
        'publication' => '1 fiche(s) publiée(s)',
        'notifications' => '2 notification(s)',
        'mises-a-jour' => null, // rien de neuf sur GitHub
        default => in_array($task, Cron::LONG, true) ? 'fait' : null,
    };
};

// 1. Ordre : tâches courtes d'abord.
$order = Cron::order(0);
$firstLong = min(array_map(fn ($t) => array_search($t, $order, true), Cron::LONG));
$eq('publications avant toute tâche longue', array_search('publication', $order, true) < $firstLong, true);
$eq('notifications avant toute tâche longue', array_search('notifications', $order, true) < $firstLong, true);
$eq('newsletter avant toute tâche longue', array_search('newsletter', $order, true) < $firstLong, true);
$eq('toutes les tâches présentes une fois', count($order) === count(Cron::TASKS) && !array_diff(array_keys(Cron::TASKS), $order), true);
$eq('ordre des longues qui tourne', [Cron::order(0)[$firstLong], Cron::order(1)[$firstLong]], [Cron::LONG[0], Cron::LONG[1]]);

// 2. Passage avec un budget de 20 s : la première tâche longue en prend 6, les suivantes attendent.
ob_start();
$slow = Cron::LONG[0];
$t0 = microtime(true);
$r = Cron::run(null, 20);
$dt = microtime(true) - $t0;
ob_end_clean();
$longDone = array_values(array_intersect($calls, Cron::LONG));
$eq('budget : une seule tâche longue lancée quand il ne reste plus assez de temps', $longDone, [Cron::LONG[0]]);
$eq('budget : passage terminé dans le temps imparti', $dt < 20, true);
$eq('publications et notifications faites', [isset($r['publication']), isset($r['notifications'])], [true, true]);
$eq('tâche sans rien à faire : absente du compte rendu', isset($r['mises-a-jour']), false);
$st = Cron::state();
$eq('tâche sans rien à faire : heure notée pour l’intervalle', ($st['mises-a-jour']['tried'] ?? 0) > time() - 60, true);
$eq('tâche sans rien à faire : pas de faux résultat journalisé', isset($st['mises-a-jour']['at']), false);

// 3. Passage suivant (plein budget) : vérification GitHub pas refaite (intervalle de 3 h) ; la
// rotation fait passer une autre tâche longue en premier.
$calls = [];
$slow = null;
ob_start();
Cron::run();
ob_end_clean();
$eq('intervalle respecté pour une tâche qui n’avait rien à faire', in_array('mises-a-jour', $calls, true), false);
$eq('rotation : autre tâche longue en tête', array_values(array_intersect($calls, Cron::LONG))[0] ?? null, Cron::LONG[1]);

// 4. Lancement à la main d'une tâche : toujours exécutée, et compte rendu vide si rien à faire.
$calls = [];
ob_start();
$r = Cron::run('mises-a-jour');
ob_end_clean();
$eq('lancement à la main : exécutée malgré l’intervalle', $calls, ['mises-a-jour']);
$eq('lancement à la main : « rien à faire »', $r, []);

Cron::$runner = null;
exec('rm -rf ' . escapeshellarg($tmp));
echo $fail ? "\n$fail échec(s)\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
