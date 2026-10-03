<?php
/**
 * Console d'administration (ligne de commande).
 *
 *   php bin/console.php index            reconstruit l'index des fiches
 *   php bin/console.php derived          recalcule statistiques, liens, bilans, qualité
 *   php bin/console.php search           reconstruit l'index de recherche
 *   php bin/console.php cron             tâches planifiées (à lancer toutes les 5 min)
 *   php bin/console.php admin <email> <nom>   crée un compte administrateur (mot de passe demandé)
 *   php bin/console.php backup           sauvegarde immédiate
 *   php bin/console.php rag              (ré)indexe les données pour l'assistant IA
 *   php bin/console.php images [largeur] pré-génère les vignettes
 *   php bin/console.php medias           complète dimensions, poids et empreintes des médias
 *   php bin/console.php videos           copie les vignettes des vidéos (YouTube, Dailymotion…)
 *   php bin/console.php geo [--hors-ligne]  géolocalise stades et lieux de naissance (carte)
 *   php bin/console.php correcteur [secondes]  vérifie l'orthographe de toutes les fiches (sans plafond quotidien)
 */

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Data\Derived;
use App\Data\Index;

$cmd = $argv[1] ?? 'help';
$t0 = microtime(true);

switch ($cmd) {
    case 'index':
        $items = Index::rebuild();
        echo count($items) . " fiches indexées\n";
        break;

    case 'derived':
        $d = Derived::rebuild();
        echo sprintf("%d matchs, %d apparitions, %d personnes reliées, %d saisons, %d adversaires, %d stades, %d alertes qualité (%.1fs)\n",
            count($d['matches']), count($d['apps']), count($d['person_totals']), count($d['seasons']), count($d['clubs']), count($d['stades']), count($d['quality']), $d['duration']);
        break;

    case 'search':
        $n = \App\Services\Search::rebuild();
        echo "$n entrées dans l'index de recherche\n";
        break;

    case 'geo':
        $r = \App\Services\Geo::run(10000, !in_array('--hors-ligne', $argv, true));
        echo sprintf("%d stades et %d lieux géolocalisés, %d restants\n", $r['stades'], $r['lieux'], $r['restants']);
        break;

    case 'cron':
        \App\Services\Cron::run();
        break;

    case 'admin':
        [$email, $name] = [$argv[2] ?? '', $argv[3] ?? 'Administrateur'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            fwrite(STDERR, "E-mail invalide\n");
            exit(1);
        }
        $pwd = getenv('ADMIN_PASSWORD') ?: (function () {
            echo 'Mot de passe : ';
            system('stty -echo 2>/dev/null');
            $p = trim((string) fgets(STDIN));
            system('stty echo 2>/dev/null');
            echo "\n";
            return $p;
        })();
        $u = \App\Core\Auth::createUser($email, $name, 'admin', $pwd);
        echo "Compte administrateur créé : {$u['email']}\n";
        break;

    case 'backup':
        $r = \App\Services\Backup::run(true);
        echo json_encode($r, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        break;

    case 'correcteur':
        // Premier passage complet du correcteur (ou après un changement de clé) : sans plafond
        // quotidien d'appels à Gemini, dans la limite du temps donné (30 minutes par défaut).
        @set_time_limit(0);
        $r = \App\Services\Proofreader::run(max(10, (int) ($argv[2] ?? 1800)), PHP_INT_MAX);
        echo json_encode($r ?? ['fiches' => 0, 'message' => 'tout est déjà vérifié'], JSON_UNESCAPED_UNICODE) . "\n";
        break;

    case 'rag':
        $r = \App\Services\Rag::reindex(fn ($m) => print("$m\n"));
        echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
        break;

    case 'images':
        $w = (int) ($argv[2] ?? 600);
        $n = \App\Services\Images::warmup($w, fn ($m) => print("$m\n"));
        echo "$n vignettes générées\n";
        break;

    case 'medias':
        $total = 0;
        $rounds = intdiv(count(\App\Data\Media::all()), 500) + 2;
        while ($rounds-- > 0 && ($r = \App\Services\Cron::mediaFacts(500)) !== null) {
            $total += (int) $r;
            echo "$total média(s) complété(s)\n";
        }
        echo "Médiathèque à jour\n";
        break;

    case 'videos':
        $r = \App\Services\VideoThumbs::run(100000);
        echo sprintf("%d vignette(s) copiée(s), %d échec(s) (nouvel essai dans une semaine)\n", $r['faites'], $r['echecs']);
        break;

    default:
        // Aide : la liste des commandes en tête de ce fichier.
        preg_match_all('/^ \*   (php .+)$/m', (string) file_get_contents(__FILE__), $m);
        echo "Commandes :\n  " . implode("\n  ", $m[1]) . "\n";
}
printf("(%.1fs)\n", microtime(true) - $t0);
