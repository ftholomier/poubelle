<?php
declare(strict_types=1);

/*
 * Tâches planifiées — à lancer chaque minute par le cron du serveur :
 *   * * * * * php /chemin/vers/le/site/bin/cron.php > /dev/null 2>&1
 *
 *   php bin/cron.php                 exécute les tâches arrivées à échéance
 *   php bin/cron.php mail stats      force les tâches indiquées
 *   php bin/cron.php --list          liste les tâches et leur dernier passage
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI uniquement\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\Cron;

$args = array_slice($argv, 1);
if (in_array('--list', $args, true)) {
    $state = Cron::state();
    foreach (Cron::TASKS as $key => [$label, $every]) {
        $t = $state['tasks'][$key] ?? null;
        printf("%-10s %-48s %s\n", $key, $label, $t ? $t['at'] . ($t['ok'] ? '' : ' (erreur)') : 'jamais');
    }
    exit(0);
}
$only = array_values(array_filter($args, static fn ($a) => isset(Cron::TASKS[$a])));
$report = Cron::run('cli', $only ?: null, 240);
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
