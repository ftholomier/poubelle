<?php
declare(strict_types=1);

/**
 * Tâches de maintenance nocturnes.
 *   php bin/cron.php            toutes les tâches
 *   php bin/cron.php index      réindexation de l'assistant
 *   php bin/cron.php reviews    rafraîchissement des avis Google
 *   php bin/cron.php backup     sauvegarde datée de content/ et storage/docs
 *   php bin/cron.php prune      nettoyage des compteurs et des sessions
 *
 * Exemple de crontab (3 h 20 du matin) :
 *   20 3 * * * /usr/bin/php /chemin/du/site/bin/cron.php >> /chemin/du/site/storage/logs/cron.log 2>&1
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Ai\Indexer;
use App\Config;
use App\Log;
use App\RateLimit;
use App\Reviews;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce script s'exécute en ligne de commande uniquement.\n");
}

$task = $argv[1] ?? 'all';
$say = static function (string $message): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
    Log::write('cron', $message);
};

if ($task === 'all' || $task === 'index') {
    $result = Indexer::rebuild();
    $say("Index reconstruit : {$result['chunks']} extraits sur {$result['sources']} sources.");
}

if ($task === 'all' || $task === 'reviews') {
    if (Reviews::configured()) {
        $result = Reviews::refresh();
        $say($result['ok'] ? 'Avis Google rafraîchis : ' . ($result['count'] ?? 0) . '.' : 'Avis Google : ' . ($result['error'] ?? 'échec'));
    } else {
        $say('Avis Google : pas de clé configurée, rien à faire.');
    }
}

if ($task === 'all' || $task === 'prune') {
    $say('Compteurs de quota nettoyés : ' . RateLimit::prune() . ' fichier(s).');

    $sessions = glob(Config::storagePath('sessions/sess_*')) ?: [];
    $removed = 0;
    foreach ($sessions as $file) {
        if (filemtime($file) < time() - 604800) {
            @unlink($file);
            $removed++;
        }
    }
    $say('Sessions expirées supprimées : ' . $removed . '.');
}

if ($task === 'all' || $task === 'backup') {
    $dir = Config::storagePath('backups');
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $archive = $dir . '/ioio-' . date('Y-m-d') . '.tar.gz';

    $command = sprintf(
        'tar -czf %s -C %s content storage/docs 2>&1',
        escapeshellarg($archive),
        escapeshellarg(Config::root())
    );
    $output = shell_exec($command);
    if (is_file($archive)) {
        $say('Sauvegarde : ' . basename($archive) . ' (' . round((filesize($archive) ?: 0) / 1024) . ' Ko).');
    } else {
        $say('Sauvegarde impossible : ' . trim((string) $output));
    }

    // Rétention 14 jours.
    $removed = 0;
    foreach (glob($dir . '/ioio-*.tar.gz') ?: [] as $file) {
        if (filemtime($file) < time() - 1_209_600) {
            @unlink($file);
            $removed++;
        }
    }
    if ($removed > 0) {
        $say('Anciennes sauvegardes supprimées : ' . $removed . '.');
    }
}

$say('Terminé.');
