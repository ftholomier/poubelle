<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Content\Media;
use App\Http\Request;
use App\Http\Response;

/**
 * Distribution contrôlée des fichiers téléversés.
 * Les fichiers résident hors racine web : ils ne peuvent jamais être exécutés,
 * et seuls les types déclarés sont servis.
 */
final class MediaController
{
    public static function image(Request $request, array $args): void
    {
        self::serve(Media::KIND_MEDIA, (string) ($args['file'] ?? ''), true);
    }

    public static function document(Request $request, array $args): void
    {
        self::serve(Media::KIND_DOC, (string) ($args['file'] ?? ''), false);
    }

    private static function serve(string $kind, string $file, bool $inline): void
    {
        $path = Media::resolve($kind, $file);
        if ($path === null || !is_file($path)) {
            Response::notFound();
            echo 'Fichier introuvable.';
            return;
        }

        $item = null;
        foreach (Media::all($kind) as $candidate) {
            if ((string) $candidate['file'] === basename($path)) {
                $item = $candidate;
                break;
            }
        }
        // Un document non public n'est accessible qu'au back-office connecté.
        if ($item !== null && empty($item['public']) && !\App\Security\Auth::check()) {
            http_response_code(403);
            echo 'Accès refusé.';
            return;
        }

        $mime = (string) ($item['mime'] ?? 'application/octet-stream');
        $allowed = $kind === Media::KIND_DOC
            ? \App\Core\Config::arr('uploads.doc_mimes')
            : \App\Core\Config::arr('uploads.media_mimes');
        if (!isset($allowed[$mime])) {
            $mime = 'application/octet-stream';
        }

        $etag = '"' . substr(md5($path . filemtime($path) . filesize($path)), 0, 20) . '"';
        if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
            http_response_code(304);
            return;
        }

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        header('Content-Security-Policy: default-src \'none\'; sandbox');
        header('ETag: ' . $etag);
        header('Cache-Control: public, max-age=' . ($inline ? 31536000 : 3600) . ', immutable');
        header(sprintf(
            'Content-Disposition: %s; filename="%s"',
            $inline || $mime === 'application/pdf' ? 'inline' : 'attachment',
            preg_replace('/[^A-Za-z0-9._\-]/', '_', (string) ($item['name'] ?? basename($path))) . '.' . ($item['ext'] ?? 'bin')
        ));

        readfile($path);
    }
}
