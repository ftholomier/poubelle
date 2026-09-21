<?php
declare(strict_types=1);

namespace App\Storage;

use App\Core\Config;

/**
 * Collection d'entités, un fichier JSON par enregistrement.
 * Les classes filles fixent le dossier et le type de schéma.
 */
abstract class Repository
{
    abstract protected static function dir(): string;

    abstract protected static function type(): string;

    protected static function path(string $id): string
    {
        return static::dir() . '/' . static::safeId($id) . '.json';
    }

    /** Un identifiant ne sort jamais du dossier de la collection. */
    protected static function safeId(string $id): string
    {
        $clean = preg_replace('/[^a-z0-9\-_]/i', '', $id) ?? '';
        return $clean !== '' ? $clean : 'invalid';
    }

    /** Lecture tolérante : renvoie null si absent, jamais d'exception. */
    public static function find(string $id): ?array
    {
        $data = Json::read(static::path($id));
        return $data === [] ? null : Schema::upgrade($data, static::type());
    }

    public static function findBySlug(string $slug): ?array
    {
        foreach (static::all() as $record) {
            if (($record['slug'] ?? '') === $slug) {
                return $record;
            }
        }
        return null;
    }

    /** Toutes les entités du dossier, remontées au schéma courant. */
    public static function all(): array
    {
        $out = [];
        foreach (Json::listFiles(static::dir()) as $file) {
            $data = Json::read($file);
            if ($data !== []) {
                $out[] = Schema::upgrade($data, static::type());
            }
        }
        return $out;
    }

    public static function count(): int
    {
        return count(Json::listFiles(static::dir()));
    }

    /** Écriture atomique sous verrou, avec horodatage automatique. */
    public static function save(array $record): bool
    {
        $record = Schema::upgrade($record, static::type());
        $id = (string) ($record['id'] ?? '');
        if ($id === '') {
            $id = $record['id'] = static::nextId();
        }

        $now = date('c');
        $record['updated_at'] = $now;
        if (($record['created_at'] ?? '') === '') {
            $record['created_at'] = $now;
        }

        return Lock::transaction(static::type() . ':' . $id,
            static fn(): bool => Json::write(static::path($id), $record));
    }

    public static function delete(string $id): bool
    {
        return Lock::transaction(static::type() . ':' . $id,
            static fn(): bool => Json::delete(static::path($id)));
    }

    /** Identifiant court, trié dans le temps, sans collision pratique. */
    public static function nextId(): string
    {
        return base_convert((string) time(), 10, 36) . bin2hex(random_bytes(3));
    }

    /** Slug unique dans la collection ; suffixe -2, -3… en cas de collision. */
    public static function uniqueSlug(string $base, string $exceptId = ''): string
    {
        $slug = slugify($base);
        $taken = [];
        foreach (static::all() as $record) {
            if (($record['id'] ?? '') !== $exceptId) {
                $taken[$record['slug'] ?? ''] = true;
            }
        }
        if (!isset($taken[$slug])) {
            return $slug;
        }
        for ($i = 2; $i < 500; $i++) {
            if (!isset($taken[$slug . '-' . $i])) {
                return $slug . '-' . $i;
            }
        }
        return $slug . '-' . bin2hex(random_bytes(3));
    }

    protected static function ensureDir(): void
    {
        if (!is_dir(static::dir())) {
            @mkdir(static::dir(), 0775, true);
        }
    }

    protected static function dataPath(string $sub): string
    {
        return Config::path('data') . '/' . $sub;
    }
}
