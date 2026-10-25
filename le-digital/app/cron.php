<?php
/**
 * Tâche planifiée (à lancer toutes les heures) : rappels de rendez-vous la veille, ménage.
 *   0 * * * * php /chemin/vers/le-digital/app/cron.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI uniquement');
}
require __DIR__ . '/bootstrap.php';
echo LD\Cron::run();
