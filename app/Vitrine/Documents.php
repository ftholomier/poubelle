<?php
declare(strict_types=1);

namespace App\Vitrine;

use App\Core\Response;

/**
 * Fichiers publics de l'association (statuts, comptes rendus, bulletin d'adhésion…), déposés
 * dans le back-office : data/vitrine/documents/, servis par /documents/{fichier}.
 * Plus le dossier de présentation livré avec le site (app/Resources/vitrine/fichiers/).
 */
final class Documents
{
    public const DIR = DATA_PATH . '/vitrine/documents';
    private const SHIPPED = APP_DIR . '/Resources/vitrine/fichiers';
    public const EXT = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'zip' => 'application/zip'];
    public const MAX = 20 * 1024 * 1024;

    /** Chemin d'un fichier déposé (ou livré avec le site), null s'il n'existe pas ou si le nom est suspect. */
    public static function path(string $name): ?string
    {
        if (!preg_match('/^[a-z0-9][a-z0-9._-]{0,120}\.([a-z0-9]{2,4})$/', $name, $m) || !isset(self::EXT[$m[1]]) || str_contains($name, '..')) {
            return null;
        }
        foreach ([self::DIR, self::SHIPPED] as $dir) {
            if (is_file("$dir/$name")) {
                return "$dir/$name";
            }
        }
        return null;
    }

    public static function url(string $name): string
    {
        return Host::url('/documents/' . rawurlencode($name));
    }

    public static function size(string $name): int
    {
        $p = self::path($name);
        return $p ? (int) filesize($p) : 0;
    }

    /** GET /documents/{fichier} */
    public static function serve(string $name): ?Response
    {
        $p = self::path($name);
        if ($p === null) {
            return null;
        }
        $ext = strtolower(pathinfo($p, PATHINFO_EXTENSION));
        $res = new Response('', 200, [
            'Content-Type' => self::EXT[$ext],
            'Content-Length' => (string) filesize($p),
            'Content-Disposition' => ($ext === 'pdf' || str_starts_with(self::EXT[$ext], 'image/') ? 'inline' : 'attachment') . '; filename="' . $name . '"',
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $res->file = $p;
        return $res;
    }

    /** Nom de fichier sûr et libre, d'après le nom d'origine (« Statuts 2026.pdf » → « statuts-2026.pdf »). */
    public static function freeName(string $original, string $ext): string
    {
        $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(slugify(pathinfo($original, PATHINFO_FILENAME)))), '-') ?: 'document';
        $base = substr($base, 0, 80);
        $name = "$base.$ext";
        for ($i = 2; is_file(self::DIR . "/$name") && $i < 1000; $i++) {
            $name = "$base-$i.$ext";
        }
        return $name;
    }
}
