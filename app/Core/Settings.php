<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Réglages du site (l'équivalent d'un fichier .env), modifiables depuis le
 * back-office.
 *
 * - Valeurs par défaut : config/settings.php (versionné).
 * - Valeurs saisies : storage/settings.json (non versionné, hors /public).
 * - Les champs secrets (clés API, mots de passe) sont chiffrés avec
 *   libsodium ; la clé de chiffrement est générée au premier lancement dans
 *   storage/secret.key et ne quitte jamais le serveur.
 */
final class Settings
{
    private const FILE = STORAGE_PATH . '/settings.json';
    private const KEY_FILE = STORAGE_PATH . '/secret.key';
    private const PREFIX = 'enc:';

    private static ?array $values = null;

    /** Description des réglages (onglets, champs, types) pour le back-office. */
    public static function schema(): array
    {
        return require APP_ROOT . '/config/settings.php';
    }

    /** @return array<string,mixed> valeurs par défaut, à plat (« groupe.champ ») */
    public static function defaults(): array
    {
        $out = [];
        foreach (self::schema() as $group => $tab) {
            foreach ($tab['fields'] as $name => $f) {
                $out["$group.$name"] = $f['default'] ?? null;
            }
        }
        return $out;
    }

    public static function isSecret(string $key): bool
    {
        [$group, $name] = explode('.', $key, 2) + [1 => ''];
        return (self::schema()[$group]['fields'][$name]['type'] ?? '') === 'secret';
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $values = self::all();
        // Jamais enregistré : valeur par défaut du schéma (config/settings.php), sinon celle de l'appel.
        if (!array_key_exists($key, $values) || $values[$key] === null) {
            return self::defaults()[$key] ?? $default;
        }
        $v = $values[$key];
        if ($v === '') {
            return '';
        }
        if (is_string($v) && str_starts_with($v, self::PREFIX)) {
            return self::decrypt($v);
        }
        return $v;
    }

    /** @return array<string,mixed> valeurs enregistrées (secrets encore chiffrés) */
    public static function all(): array
    {
        if (self::$values === null) {
            self::$values = is_file(self::FILE) ? (JsonStore::read(self::FILE, []) ?? []) : [];
        }
        return self::$values;
    }

    /** @param array<string,mixed> $changes */
    public static function save(array $changes): void
    {
        self::$values = JsonStore::update(self::FILE, function ($current) use ($changes) {
            $current = $current ?? [];
            foreach ($changes as $k => $v) {
                if (self::isSecret($k)) {
                    if ($v === null || $v === '') {
                        continue; // champ secret laissé vide = inchangé
                    }
                    if ($v === '__delete__') {
                        unset($current[$k]);
                        continue;
                    }
                    $v = self::encrypt((string) $v);
                }
                $current[$k] = $v;
            }
            ksort($current);
            return $current;
        }, []);
        @chmod(self::FILE, 0640);
    }

    public static function hasValue(string $key): bool
    {
        $v = self::all()[$key] ?? null;
        return $v !== null && $v !== '';
    }

    public static function encrypt(string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return self::PREFIX . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, self::key()));
    }

    public static function decrypt(string $value): ?string
    {
        $raw = base64_decode(substr($value, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }
        $plain = sodium_crypto_secretbox_open(
            substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            self::key()
        );
        return $plain === false ? null : $plain;
    }

    public static function key(): string
    {
        if (!is_file(self::KEY_FILE)) {
            if (!is_dir(STORAGE_PATH)) {
                mkdir(STORAGE_PATH, 0775, true);
            }
            file_put_contents(self::KEY_FILE, base64_encode(sodium_crypto_secretbox_keygen()), LOCK_EX);
            @chmod(self::KEY_FILE, 0600);
        }
        return base64_decode(trim((string) file_get_contents(self::KEY_FILE)));
    }

    public static function reset(): void
    {
        self::$values = null;
    }
}
