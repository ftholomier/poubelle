#!/usr/bin/env php
<?php
/**
 * Tâches planifiées du site. Idempotent : rien ne casse à le relancer.
 *
 *   php bin/cron.php              toutes les tâches dues
 *   php bin/cron.php --task=expire  une seule
 *   php bin/cron.php --force      ignore les intervalles
 *   php bin/cron.php --quiet      n'affiche que les erreurs
 *
 * Crontab conseillée (o2switch, cPanel → « Tâches planifiées ») :
 *   0,30 * * * * /usr/local/bin/php /home/COMPTE/site/bin/cron.php --quiet
 *
 * Chaque tâche porte son propre intervalle : la passe tourne toutes les
 * trente minutes, mais la purge des journaux ne s'exécute qu'une fois par jour.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Config;
use App\Services\Aggregator;
use App\Services\ContentTranslator;
use App\Services\JobLifecycle;
use App\Services\Mailer;
use App\Services\Trades;
use App\Storage\Audit;
use App\Storage\Backup;
use App\Storage\Index;
use App\Storage\Json;

$options = getopt('', ['task::', 'force', 'quiet', 'list']);
$only  = (string) ($options['task'] ?? '');
$force = array_key_exists('force', $options);
$quiet = array_key_exists('quiet', $options);

$say = static function (string $line) use ($quiet): void {
    if (!$quiet) {
        echo $line, PHP_EOL;
    }
};

/** Dernière exécution de chaque tâche, hors racine web. */
$statePath = Config::path('data') . '/private/cron.json';
$state = Json::read($statePath);

/**
 * Chaque tâche : intervalle minimal en secondes, et ce qu'elle fait.
 * Le retour est une ligne de compte rendu, ou '' quand il n'y avait rien à faire.
 */
$tasks = [
    // Les annonces périmées sortent des listes et des compteurs.
    'expire' => [1800, static function (): string {
        $count = JobLifecycle::expire();
        return $count > 0 ? $count . ' annonce(s) passée(s) en expirée' : '';
    }],

    // Le cache des partenaires se rafraîchit ici, jamais dans une requête de
    // visiteur : sinon la page d'accueil attend quatre API en série.
    'sources' => [1800, static function (): string {
        if (!Config::get('sources.enabled')) {
            return '';
        }
        $before = microtime(true);
        $jobs = Aggregator::fetch([], 40, true);
        return sprintf('%d offre(s) partenaire en cache (%.1f s)', count($jobs), microtime(true) - $before);
    }],

    // Flux partenaire des fiches métiers, quelques-uns par passage : la page
    // d'une fiche ne fait jamais d'appel réseau, elle lit ce que ceci prépare.
    'trades' => [1800, static function (): string {
        if (!Config::get('sources.enabled')) {
            return '';
        }
        return Trades::refreshPartnerFeeds((int) Config::get('trades.partner_refresh_per_run', 1));
    }],

    // Traduction par lot, bornée : les fiches consultées dans une autre langue
    // sont prêtes d'avance, sans facturer une visite de robot.
    'translate' => [3600, static function (): string {
        $result = ContentTranslator::translateMissing(null, 40);
        return ($result['done'] ?? 0) > 0
            ? sprintf('%d fiche(s) traduite(s), %d restante(s)', $result['done'], $result['remaining'] ?? 0)
            : '';
    }],

    // Les index dénormalisés suivent les changements de statut.
    'reindex' => [86400, static function (): string {
        foreach (['jobs', 'cv', 'employers', 'pages', 'trades'] as $name) {
            Index::rebuild($name);
        }
        return 'index reconstruits';
    }],

    // Rétention : journal à douze mois, traces d'e-mail à sept jours.
    'purge' => [86400, static function (): string {
        $logs = Audit::purge(12);
        $mails = Mailer::purge(7);
        $locks = purgeLocks(7);
        $parts = [];
        if ($logs > 0)  { $parts[] = $logs . ' journal(aux)'; }
        if ($mails > 0) { $parts[] = $mails . ' trace(s) d’e-mail'; }
        if ($locks > 0) { $parts[] = $locks . ' verrou(s)'; }
        return $parts === [] ? '' : 'purge : ' . implode(', ', $parts);
    }],

    // Instantané quotidien, avec rotation.
    'backup' => [86400, static function (): string {
        $name = Backup::snapshot('cron');
        return ($name ?? '') === '' ? '' : 'sauvegarde ' . $name;
    }],
];

/** Les verrous de Lock s'accumulaient sans jamais être nettoyés. */
function purgeLocks(int $days): int
{
    $limit = time() - max(1, $days) * 86400;
    $removed = 0;
    foreach (glob(Config::path('data') . '/private/locks/*.mutex') ?: [] as $file) {
        if ((int) @filemtime($file) < $limit) {
            $removed += @unlink($file) ? 1 : 0;
        }
    }
    return $removed;
}

$now = time();
$ran = 0;
$failed = 0;

foreach ($tasks as $name => [$interval, $run]) {
    if ($only !== '' && $only !== $name) {
        continue;
    }
    $last = (int) ($state[$name]['at'] ?? 0);
    if (!$force && $only === '' && $now - $last < $interval) {
        continue;
    }

    $started = microtime(true);
    try {
        $report = $run();
        $state[$name] = ['at' => $now, 'ok' => true, 'report' => $report];
        $ran++;
        if ($report !== '') {
            $say(sprintf('[%s] %s (%.1f s)', $name, $report, microtime(true) - $started));
        }
    } catch (\Throwable $e) {
        $failed++;
        $state[$name] = ['at' => $now, 'ok' => false, 'report' => $e->getMessage()];
        Audit::log('cron.failed', ['task' => $name, 'message' => $e->getMessage()]);
        fwrite(STDERR, sprintf('[%s] échec : %s' . PHP_EOL, $name, $e->getMessage()));
    }
}

Json::write($statePath, $state);

if ($ran > 0 || $failed > 0) {
    Audit::log('cron.run', ['tasks' => $ran, 'failed' => $failed]);
}
$say(sprintf('%d tâche(s) exécutée(s), %d en échec.', $ran, $failed));

exit($failed > 0 ? 1 : 0);
