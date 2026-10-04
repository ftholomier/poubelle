<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Résultats de calculs coûteux (réseau du Fil jaune, menus, compteurs…) gardés en cache PHP,
 * lus par OPcache sans copie, tant que leurs sources ne changent pas : fichiers dont ils
 * dépendent (index des fiches, données calculées, code du calcul) et, pour les publications
 * programmées, au plus TTL secondes. Un cache illisible ou manquant est simplement recalculé.
 */
final class Memo
{
    public const DIR = STORAGE_PATH . '/cache/memo';
    private const TTL = 300;

    /** @var array<string,mixed> valeurs déjà lues pendant la requête */
    private static array $seen = [];

    /**
     * @param string $name nom du cache
     * @param list<string> $files fichiers dont dépend le calcul (date et taille)
     * @param string $extra autre empreinte (version des données calculées, langue…)
     */
    public static function get(string $name, array $files, string $extra, callable $compute): mixed
    {
        $stamp = self::stamp($files) . '|' . $extra;
        if (array_key_exists("$name|$stamp", self::$seen)) {
            return self::$seen["$name|$stamp"];
        }
        // Nom lisible, plus une empreinte : deux noms différents n'ont jamais le même fichier.
        $file = self::DIR . '/' . substr((string) preg_replace('/[^a-z0-9-]/i', '-', $name), 0, 80) . '-' . substr(md5($name), 0, 8) . '.php';
        $c = is_file($file) ? @include $file : null;
        if (is_array($c) && ($c['name'] ?? null) === $name && ($c['stamp'] ?? null) === $stamp && ($c['at'] ?? 0) > time() - self::TTL) {
            return self::$seen["$name|$stamp"] = $c['value'];
        }
        $value = $compute();
        try {
            PhpCache::write($file, ['name' => $name, 'stamp' => $stamp, 'at' => time(), 'value' => $value]);
        } catch (\Throwable $e) {
            // cache facultatif : la valeur calculée sert quand même
        }
        return self::$seen["$name|$stamp"] = $value;
    }

    /** Empreinte de fichiers : date de modification et taille (fichier absent : « - »). */
    public static function stamp(array $files): string
    {
        $out = [];
        foreach ($files as $f) {
            $st = @stat($f);
            $out[] = $st ? $st['mtime'] . ':' . $st['size'] : '-';
        }
        return implode(',', $out);
    }

    /** Oublie les valeurs lues pendant la requête (tests). */
    public static function forget(): void
    {
        self::$seen = [];
    }
}
