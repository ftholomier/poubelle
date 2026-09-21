#!/usr/bin/env php
<?php
/**
 * Reconstruit les index de recherche et la base de connaissance.
 * À lancer après une modification directe des fichiers JSON, ou si un index
 * a été supprimé. Sans effet de bord : les index sont dérivés des fiches.
 *
 *   php bin/reindex.php
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Config;
use App\Domain\UserRepository;
use App\Services\Knowledge;
use App\Storage\Index;

$t0 = microtime(true);

foreach (Index::rebuildAll() as $name => $count) {
    printf("  %-12s %d entrée(s)\n", $name, $count);
}

UserRepository::reindex();
echo "  users        index e-mail reconstruit\n";

$stats = Knowledge::rebuild();
printf("  knowledge    %d fragment(s)\n", $stats['count']);

// Le plan du site est en cache : il doit repartir de zéro.
@unlink(Config::path('data') . '/index/sitemap.xml');
echo "  sitemap      cache vidé\n";

printf("\nTerminé en %.2f s.\n", microtime(true) - $t0);
