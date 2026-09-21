#!/usr/bin/env php
<?php
/**
 * Instantanés du contenu.
 *
 *   php bin/backup.php                          créer un instantané
 *   php bin/backup.php --list                   lister les instantanés
 *   php bin/backup.php --restore=snapshot-….zip restaurer
 *   php bin/backup.php --prune                  ne garder que les plus récents
 *
 * Une restauration sauvegarde l'état courant avant d'écraser : elle est
 * réversible.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Storage\Backup;

$options = getopt('', ['list', 'restore:', 'prune', 'reason::']);

if (isset($options['list'])) {
    $backups = Backup::listAll();
    if ($backups === []) {
        echo "Aucun instantané.\n";
        exit(0);
    }
    printf("%-46s %10s  %-19s %s\n", 'FICHIER', 'TAILLE', 'DATE', 'MOTIF');
    foreach ($backups as $backup) {
        printf("%-46s %9s Ko  %-19s %s\n",
            $backup['name'],
            number_format($backup['size'] / 1024, 0, ',', ' '),
            date('d/m/Y H:i:s', (int) strtotime((string) $backup['created_at'])),
            $backup['reason'],
        );
    }
    exit(0);
}

if (isset($options['restore'])) {
    $name = (string) $options['restore'];
    echo "Restauration de $name…\n";
    if (Backup::restore($name)) {
        echo "Restauré. L'état précédent a été sauvegardé avant écrasement.\n";
        exit(0);
    }
    fwrite(STDERR, "Échec : instantané introuvable ou illisible.\n");
    exit(1);
}

if (isset($options['prune'])) {
    $before = count(Backup::listAll());
    Backup::snapshot('purge');          // la purge s'exécute après chaque création
    Backup::delete(Backup::listAll()[0]['name'] ?? '');
    printf("%d instantané(s) avant, %d après.\n", $before, count(Backup::listAll()));
    exit(0);
}

$name = Backup::snapshot((string) ($options['reason'] ?? 'cli'));
if ($name === null) {
    fwrite(STDERR, "Échec : extension zip absente ou dossier non inscriptible.\n");
    exit(1);
}
printf("Instantané créé : %s\n", $name);
