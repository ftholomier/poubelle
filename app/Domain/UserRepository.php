<?php
declare(strict_types=1);

namespace App\Domain;

use App\Storage\Repository;
use App\Storage\Schema;
use App\Storage\Json;

final class UserRepository extends Repository
{
    protected static function dir(): string
    {
        return self::dataPath('users');
    }

    protected static function type(): string
    {
        return 'user';
    }

    /** Index e-mail -> identifiant, pour ne pas relire 233 fichiers à chaque connexion. */
    private static function indexPath(): string
    {
        return self::dataPath('index/users-by-email.json');
    }

    public static function findByEmail(string $email): ?array
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }

        $index = Json::read(self::indexPath());
        if (isset($index[$email])) {
            $found = self::find((string) $index[$email]);
            if ($found !== null && strtolower((string) $found['email']) === $email) {
                return $found;
            }
        }

        // Index absent ou périmé : balayage complet puis reconstruction.
        foreach (self::all() as $user) {
            if (strtolower((string) ($user['email'] ?? '')) === $email) {
                self::reindex();
                return $user;
            }
        }
        return null;
    }

    public static function reindex(): void
    {
        $index = [];
        foreach (self::all() as $user) {
            $email = strtolower(trim((string) ($user['email'] ?? '')));
            if ($email !== '') {
                $index[$email] = $user['id'];
            }
        }
        Json::write(self::indexPath(), $index);
    }

    /** Identifiants numériques pour rester compatible avec les anciens ID WordPress. */
    public static function nextId(): string
    {
        $max = 0;
        foreach (Json::listFiles(self::dir()) as $file) {
            $max = max($max, (int) basename($file, '.json'));
        }
        return (string) ($max + 1);
    }

    public static function byRole(string $role): array
    {
        return array_values(array_filter(self::all(), static fn(array $u) => ($u['role'] ?? '') === $role));
    }
}
