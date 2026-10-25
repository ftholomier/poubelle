<?php
declare(strict_types=1);

namespace LD;

/**
 * Coffre des clés API (chiffrées dans storage/secrets/secrets.json).
 */
final class Secrets
{
    private const FILE = 'secrets/secrets.json';

    public static function get(string $name): ?string
    {
        $all = Store::read(self::FILE, []);
        if (empty($all[$name]['v'])) {
            return null;
        }
        return Crypto::decrypt($all[$name]['v']);
    }

    public static function has(string $name): bool
    {
        $v = self::get($name);
        return $v !== null && $v !== '';
    }

    public static function set(string $name, string $value): void
    {
        $value = trim($value);
        Store::update(self::FILE, static function (array &$all) use ($name, $value): void {
            if ($value === '') {
                unset($all[$name]);
                return;
            }
            $all[$name] = ['v' => Crypto::encrypt($value), 'updated_at' => date('c')];
        });
        @chmod(Store::path(self::FILE), 0600);
    }

    public static function delete(string $name): void
    {
        self::set($name, '');
    }

    public static function updatedAt(string $name): ?string
    {
        $all = Store::read(self::FILE, []);
        return $all[$name]['updated_at'] ?? null;
    }

    /** Version masquée pour l'affichage : « sk-a…9f3c ». */
    public static function masked(string $name): string
    {
        $v = self::get($name);
        if (!$v) {
            return '';
        }
        $len = strlen($v);
        if ($len <= 10) {
            return str_repeat('•', $len);
        }
        return substr($v, 0, 4) . '…' . substr($v, -4);
    }
}
