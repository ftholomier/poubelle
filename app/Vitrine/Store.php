<?php
declare(strict_types=1);

namespace App\Vitrine;

use App\Core\JsonStore;
use App\Data\Activity;

/**
 * Contenus du site de l'association.
 *
 * - Contenus de départ : app/Resources/vitrine/{nom}.php, livrés avec le code (et donc mis à
 *   jour depuis GitHub tant que l'administrateur ne les a pas modifiés).
 * - Contenus modifiés dans le back-office : data/vitrine/{nom}.json, jamais touchés par les
 *   mises à jour ; chaque enregistrement est conservé (storage/versions/vitrine/{nom}/).
 *
 * Les données personnelles (adhésions, candidatures de bénévoles) ne sont pas ici : elles
 * restent dans storage/vitrine/, hors du dépôt.
 */
final class Store
{
    public const DIR = DATA_PATH . '/vitrine';
    public const DEFAULTS = APP_DIR . '/Resources/vitrine';

    /** @var array<string,array> */
    private static array $cache = [];

    /** Contenu enregistré dans le back-office, sinon celui de départ. */
    public static function get(string $name): array
    {
        if (!self::validName($name)) {
            return [];
        }
        if (!isset(self::$cache[$name])) {
            $saved = JsonStore::read(self::DIR . "/$name.json");
            self::$cache[$name] = is_array($saved) ? $saved : self::defaults($name);
        }
        return self::$cache[$name];
    }

    /** Contenu de départ (livré avec le site). */
    public static function defaults(string $name): array
    {
        $file = self::DEFAULTS . "/$name.php";
        if (!self::validName($name) || !is_file($file)) {
            return [];
        }
        $d = require $file;
        return is_array($d) ? $d : [];
    }

    /** Encore le contenu de départ (jamais enregistré dans le back-office) ? */
    public static function isDefault(string $name): bool
    {
        return !is_file(self::DIR . "/$name.json");
    }

    public static function save(string $name, array $data, ?array $user = null, string $message = ''): void
    {
        if (!self::validName($name)) {
            throw new \InvalidArgumentException('Nom de contenu invalide');
        }
        $file = self::DIR . "/$name.json";
        if (is_file($file) && JsonStore::read($file) === $data) {
            return;
        }
        JsonStore::write($file, $data);
        self::$cache[$name] = $data;
        $vdir = STORAGE_PATH . "/versions/vitrine/$name";
        JsonStore::update("$vdir/index.json", function ($idx) use ($vdir, $data, $user, $message) {
            $idx = $idx ?: [];
            $n = ($idx ? max(array_column($idx, 'n')) : 0) + 1;
            JsonStore::write("$vdir/$n.json", $data);
            $idx[] = ['n' => $n, 'at' => date('c'), 'by' => $user['name'] ?? 'Système', 'message' => $message ?: 'Modification'];
            return array_slice($idx, -100);
        }, []);
        Activity::log($user, 'a modifié', ['title' => 'Site de l’association · ' . ($message ?: $name), 'path' => '']);
    }

    /** Dernières versions enregistrées (plus récente d'abord). */
    public static function versions(string $name, int $max = 8): array
    {
        if (!self::validName($name)) {
            return [];
        }
        $idx = JsonStore::read(STORAGE_PATH . "/versions/vitrine/$name/index.json", []) ?: [];
        return array_reverse(array_slice($idx, -$max));
    }

    /** Version n d'un contenu (restauration). */
    public static function version(string $name, int $n): ?array
    {
        if (!self::validName($name) || $n < 1) {
            return null;
        }
        $v = JsonStore::read(STORAGE_PATH . "/versions/vitrine/$name/$n.json");
        return is_array($v) ? $v : null;
    }

    /** Oublie ce qui a été lu (tests, enregistrement suivi d'un nouvel affichage). */
    public static function forget(): void
    {
        self::$cache = [];
    }

    private static function validName(string $name): bool
    {
        return (bool) preg_match('/^[a-z0-9][a-z0-9-]{0,40}$/', $name);
    }
}
