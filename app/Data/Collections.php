<?php
declare(strict_types=1);

namespace App\Data;

use App\Core\JsonStore;

/**
 * Petites collections éditoriales (data/collections/{nom}.json) : frise, maillots,
 * partenaires, quiz, époques, palmarès, bandeau, slider, lieux, clubs, stades…
 * Chaque modification est conservée (storage/versions/collections/{nom}/).
 */
final class Collections
{
    public const DIR = DATA_PATH . '/collections';

    public static function get(string $name, mixed $default = []): mixed
    {
        return JsonStore::read(self::DIR . "/$name.json", $default) ?? $default;
    }

    public static function save(string $name, mixed $data, ?array $user = null, string $message = ''): void
    {
        $file = self::DIR . "/$name.json";
        $before = JsonStore::read($file);
        if ($before === $data) {
            return;
        }
        JsonStore::write($file, $data);
        $vdir = STORAGE_PATH . "/versions/collections/$name";
        JsonStore::update("$vdir/index.json", function ($idx) use ($vdir, $data, $user, $message) {
            $idx = $idx ?: [];
            $n = ($idx ? max(array_column($idx, 'n')) : 0) + 1;
            JsonStore::write("$vdir/$n.json", $data);
            $idx[] = ['n' => $n, 'at' => date('c'), 'by' => $user['name'] ?? 'Système', 'message' => $message ?: 'Modification'];
            return array_slice($idx, -200);
        }, []);
        Derived::markDirty();
        Activity::log($user, 'a modifié', ['title' => self::label($name), 'path' => '']);
    }

    public static function label(string $name): string
    {
        return [
            'frise' => 'la frise', 'maillots' => 'les maillots', 'partenaires' => 'les partenaires', 'quiz' => 'le quiz',
            'epoques' => 'les grandes époques', 'palmares' => 'le palmarès', 'ticker' => 'le bandeau défilant',
            'slider' => "le slider d'accueil", 'lieux' => 'les lieux', 'clubs' => 'les adversaires', 'stades' => 'les stades',
            'menus' => 'les menus', 'paliers' => 'les paliers de dons', 'reserves' => 'les réserves du musée',
        ][$name] ?? $name;
    }
}
