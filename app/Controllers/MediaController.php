<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Services\Image;
use App\Core\Request;
use App\Core\Response;
use App\Domain\CvRepository;
use App\Domain\EmployerRepository;
use App\Storage\Audit;

/**
 * Sert les fichiers déposés, qui vivent dans /data (hors racine web).
 * Chaque requête vérifie que la fiche existe, qu'elle est publique, et que le
 * chemin résolu reste bien sous data/uploads.
 */
final class MediaController extends Controller
{
    private const KINDS = ['cv', 'photo', 'logo'];

    public function serve(Request $request, array $params): Response
    {
        $kind = (string) ($params['kind'] ?? '');
        $name = (string) ($params['name'] ?? '');

        if (!in_array($kind, self::KINDS, true)) {
            return Response::text('', 404);
        }

        $id = pathinfo($name, PATHINFO_FILENAME);
        $relative = $this->resolve($kind, $id);
        if ($relative === null) {
            return Response::text('', 404);
        }

        // Les listes demandent la vignette : 256 px au lieu de l'original.
        if ($kind !== 'cv' && (string) $request->get('t', '') !== '') {
            $thumb = Image::thumbPath($relative);
            if (is_file(Config::path('data') . '/uploads/' . $thumb)) {
                $relative = $thumb;
            }
        }

        $base = realpath(Config::path('data') . '/uploads');
        $real = realpath(Config::path('data') . '/uploads/' . $relative);

        // Traversée de répertoire : le chemin final doit rester sous uploads/.
        if ($base === false || $real === false || !str_starts_with($real, $base . DIRECTORY_SEPARATOR)) {
            Audit::log('media.traversal_blocked', ['kind' => $kind, 'name' => $name]);
            return Response::text('', 404);
        }
        if (!is_file($real)) {
            return Response::text('', 404);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($real);
        $allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        if (!in_array($mime, $allowed, true)) {
            return Response::text('', 404);
        }

        return Response::file($real, $mime, basename($real))
            ->withHeader('Cache-Control', $kind === 'cv' ? 'private, max-age=600' : 'public, max-age=86400')
            // Un CV ne doit pas finir dans l'index des moteurs de recherche.
            ->withHeader('X-Robots-Tag', $kind === 'cv' ? 'noindex, nofollow' : 'noindex');
    }

    /** Chemin relatif du fichier, ou null si la fiche n'est pas publique. */
    private function resolve(string $kind, string $id): ?string
    {
        if ($kind === 'logo') {
            $employer = EmployerRepository::find($id);
            $path = (string) ($employer['logo']['path'] ?? '');
            return $path !== '' ? $path : null;
        }

        $cv = CvRepository::find($id);
        if ($cv === null || ($cv['status'] ?? '') !== 'publish' || !($cv['listed'] ?? false)) {
            return null;
        }
        $path = (string) ($kind === 'cv' ? ($cv['file']['path'] ?? '') : ($cv['photo']['path'] ?? ''));
        return $path !== '' ? $path : null;
    }
}
