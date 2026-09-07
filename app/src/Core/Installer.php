<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Vérifie/crée l'arborescence de données et les protections de dossier.
 * Idempotent : exécuté à chaque requête, sans coût notable.
 */
final class Installer
{
    private const HTACCESS_DENY = "# Dossier de données privé — accès HTTP interdit\n"
        . "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
        . "<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n";

    public static function ensureFilesystem(): void
    {
        $dirs = [
            DATA_PATH,
            DATA_PATH . '/pages',
            DATA_PATH . '/i18n',
            DATA_PATH . '/backups',
            DATA_PATH . '/runtime',
            DATA_PATH . '/runtime/locks',
            DATA_PATH . '/runtime/cache',
            DATA_PATH . '/uploads',
            DATA_PATH . '/uploads/docs',
            DATA_PATH . '/uploads/media',
            STORAGE_PATH . '/logs',
        ];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0770, true);
            }
        }

        // Ceinture + bretelles : même si /data se retrouvait sous la racine web.
        foreach ([DATA_PATH, STORAGE_PATH] as $private) {
            $guard = $private . '/.htaccess';
            if (!is_file($guard)) {
                @file_put_contents($guard, self::HTACCESS_DENY);
            }
            $index = $private . '/index.html';
            if (!is_file($index)) {
                @file_put_contents($index, '');
            }
        }
    }

    /** Le site a-t-il au moins un compte administrateur ? */
    public static function needsSetup(): bool
    {
        $users = JsonStore::read(DATA_PATH . '/users.json', ['users' => []], true);
        return empty($users['users']);
    }
}
