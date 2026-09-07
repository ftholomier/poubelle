<?php

declare(strict_types=1);

namespace App\Content;

use App\Core\Config;
use App\Core\JsonStore;
use App\Core\Logger;

/**
 * Fichiers téléversés depuis le back-office.
 * Ils sont stockés HORS de la racine web et servis par une route contrôlée
 * (/media/…), ce qui interdit toute exécution de code téléversé.
 */
final class Media
{
    public const KIND_MEDIA = 'media';
    public const KIND_DOC   = 'docs';

    private static function indexFile(string $kind): string
    {
        return DATA_PATH . '/' . ($kind === self::KIND_DOC ? 'documents' : 'medias') . '.json';
    }

    public static function dir(string $kind): string
    {
        return DATA_PATH . '/uploads/' . ($kind === self::KIND_DOC ? 'docs' : 'media');
    }

    /**
     * @param array<string,mixed> $file entrée de $_FILES
     * @return array{ok:bool,error?:string,item?:array<string,mixed>}
     */
    public static function store(array $file, string $kind, array $meta = []): array
    {
        if (!isset($file['tmp_name'], $file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'upload_failed'];
        }
        if (!is_uploaded_file((string) $file['tmp_name'])) {
            return ['ok' => false, 'error' => 'not_uploaded'];
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > Config::int('uploads.max_size', 12582912)) {
            return ['ok' => false, 'error' => 'too_large'];
        }

        // Le type est déterminé par le contenu réel, jamais par le nom du fichier.
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = (string) $finfo->file((string) $file['tmp_name']);
        $allowed = $kind === self::KIND_DOC
            ? Config::arr('uploads.doc_mimes')
            : Config::arr('uploads.media_mimes');

        if (!isset($allowed[$mime])) {
            return ['ok' => false, 'error' => 'mime_not_allowed'];
        }
        $ext = (string) $allowed[$mime];

        // Les SVG sont nettoyés (ils peuvent embarquer du script).
        if ($ext === 'svg') {
            $svg = (string) file_get_contents((string) $file['tmp_name']);
            if (preg_match('/<script|onload=|onerror=|javascript:/i', $svg)) {
                return ['ok' => false, 'error' => 'svg_unsafe'];
            }
        }

        $dir = self::dir($kind);
        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }

        $id   = bin2hex(random_bytes(8));
        $name = \App\Security\Sanitizer::slug(pathinfo((string) ($file['name'] ?? 'fichier'), PATHINFO_FILENAME));
        $stored = $id . '-' . substr($name, 0, 60) . '.' . $ext;
        $target = $dir . '/' . $stored;

        if (!@move_uploaded_file((string) $file['tmp_name'], $target)) {
            return ['ok' => false, 'error' => 'move_failed'];
        }
        @chmod($target, 0640);

        $item = [
            'id'         => $id,
            'file'       => $stored,
            'name'       => (string) ($meta['name'] ?? pathinfo((string) $file['name'], PATHINFO_FILENAME)),
            'mime'       => $mime,
            'ext'        => $ext,
            'size'       => $size,
            'kind'       => $kind,
            'url'        => '/media/' . ($kind === self::KIND_DOC ? 'doc' : 'img') . '/' . $stored,
            'alt'        => (string) ($meta['alt'] ?? ''),
            'in_kb'      => (bool) ($meta['in_kb'] ?? ($kind === self::KIND_DOC)),
            'public'     => (bool) ($meta['public'] ?? ($kind !== self::KIND_DOC)),
            'text'       => '',
            'created_at' => date('c'),
        ];

        if ($kind === self::KIND_DOC) {
            $item['text'] = DocumentText::extract($target, $ext);
        }

        JsonStore::mutate(self::indexFile($kind), static function (array $data) use ($item): array {
            $data['items'][] = $item;
            return $data;
        }, ['items' => []]);

        Logger::audit('media.upload', ['kind' => $kind, 'file' => $stored, 'mime' => $mime]);
        return ['ok' => true, 'item' => $item];
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(string $kind): array
    {
        $data = JsonStore::read(self::indexFile($kind), ['items' => []], true);
        $items = array_values(array_filter($data['items'] ?? [], 'is_array'));
        usort($items, static fn (array $a, array $b): int => strcmp((string) $b['created_at'], (string) $a['created_at']));
        return $items;
    }

    public static function find(string $kind, string $id): ?array
    {
        foreach (self::all($kind) as $item) {
            if ((string) $item['id'] === $id) {
                return $item;
            }
        }
        return null;
    }

    /** Résout un nom de fichier stocké vers un chemin absolu, sans traversée possible. */
    public static function resolve(string $kind, string $storedName): ?string
    {
        $safe = basename($storedName);
        if ($safe === '' || $safe !== $storedName || str_contains($safe, '..')) {
            return null;
        }
        $path = self::dir($kind) . '/' . $safe;
        $real = realpath($path);
        $base = realpath(self::dir($kind));
        if ($real === false || $base === false || !str_starts_with($real, $base)) {
            return null;
        }
        return $real;
    }

    public static function update(string $kind, string $id, array $changes): bool
    {
        $found = false;
        JsonStore::mutate(self::indexFile($kind), static function (array $data) use ($id, $changes, &$found): array {
            foreach ($data['items'] as $i => $item) {
                if (is_array($item) && (string) ($item['id'] ?? '') === $id) {
                    $data['items'][$i] = array_merge($item, $changes);
                    $found = true;
                }
            }
            return $data;
        }, ['items' => []]);
        return $found;
    }

    public static function delete(string $kind, string $id): bool
    {
        $item = self::find($kind, $id);
        if (!$item) {
            return false;
        }
        $path = self::resolve($kind, (string) $item['file']);
        if ($path !== null) {
            @unlink($path);
        }
        JsonStore::mutate(self::indexFile($kind), static function (array $data) use ($id): array {
            $data['items'] = array_values(array_filter(
                $data['items'],
                static fn ($item): bool => !is_array($item) || (string) ($item['id'] ?? '') !== $id
            ));
            return $data;
        }, ['items' => []]);
        Logger::audit('media.delete', ['kind' => $kind, 'id' => $id]);
        return true;
    }
}
