<?php
declare(strict_types=1);

// Tâches automatiques : relances, comptes rendus du vendredi, échéances de vente, briefing du matin, ménage…
// À lancer toutes les 10 minutes par le cron de l'hébergeur :
//   */10 * * * * php /chemin/vers/visite-immo/app/cron.php >> /chemin/vers/visite-immo/data/cron.log 2>&1
// Chaque tâche décide elle-même si elle a quelque chose à faire (elle mémorise ce qu'elle a déjà fait).
// Options : --tache=<nom> pour n'en lancer qu'une, --maintenant=<AAAA-MM-JJTHH:MM> pour simuler une date (tests).

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Ce script se lance en ligne de commande (cron).');
}

require __DIR__ . '/bootstrap.php';

$opts = getopt('', ['tache:', 'maintenant:']);
if (!empty($opts['maintenant'])) define('CRON_MAINTENANT', strtotime($opts['maintenant']));

// Un seul passage à la fois
if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0770, true);
$verrou = fopen(DATA_DIR . '/cron.lock', 'c');
if (!flock($verrou, LOCK_EX | LOCK_NB)) exit("Passage précédent encore en cours.\n");

$debut = microtime(true);
$bilan = [];
foreach (users() as $agent) {
    $dossiers = dossiers($agent);
    foreach ($TACHES_CRON as $nom => $fn) {
        if (!empty($opts['tache']) && $opts['tache'] !== $nom) continue;
        try {
            $n = (int) ($fn($agent, $dossiers) ?? 0);
            if ($n) $bilan[] = "$nom ({$agent['login']}) : $n";
            if ($n) $dossiers = dossiers($agent); // une tâche a modifié des dossiers
        } catch (Throwable $e) {
            $bilan[] = "ERREUR $nom ({$agent['login']}) : " . $e->getMessage();
            error_log("cron $nom : $e");
        }
    }
}
write_json(DATA_DIR . '/cron.json', ['dernier' => date('c'), 'duree' => round(microtime(true) - $debut, 2), 'bilan' => $bilan]);
echo date('c') . ' · ' . (count($bilan) ? implode(' · ', $bilan) : 'rien à faire') . "\n";
flock($verrou, LOCK_UN);
