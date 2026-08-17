<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Bibliothèque d'images du site.
 *
 * Centralise le listing, l'envoi et l'optimisation : la galerie du site et
 * les photos des hébergements et des étangs passent toutes par ici.
 */
final class Mediatheque
{
    private const MAX_OCTETS  = 15_000_000;
    private const LARGEUR_MAX = 1920;
    private const LARGEUR_MINI = 640;

    public function __construct(
        private readonly string $dossier,
        private readonly string $prefixe = 'assets/img/site',
    ) {
    }

    /**
     * Toutes les images disponibles, chemins relatifs à /public, triées par
     * date d'ajout décroissante (les dernières envoyées en premier).
     *
     * @return string[]
     */
    public function lister(): array
    {
        $fichiers = glob($this->dossier . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [];
        $fichiers = array_filter($fichiers, fn(string $f) => !str_contains(basename($f), '-mini.'));

        usort($fichiers, fn(string $a, string $b) => filemtime($b) <=> filemtime($a));

        return array_map(fn(string $f) => $this->prefixe . '/' . basename($f), $fichiers);
    }

    /**
     * Vignette d'une image, ou l'image elle-même si aucune n'existe.
     */
    public function vignette(string $chemin): string
    {
        $mini = preg_replace('/\.(jpe?g|png|webp)$/i', '-mini.jpg', $chemin) ?? $chemin;
        return is_file($this->dossier . '/' . basename($mini)) ? $mini : $chemin;
    }

    public function existe(string $chemin): bool
    {
        return str_starts_with($chemin, $this->prefixe . '/')
            && !str_contains($chemin, '..')
            && is_file($this->dossier . '/' . basename($chemin));
    }

    /**
     * Envoie une image, la redimensionne et génère sa vignette.
     *
     * @param array{name?:string, tmp_name?:string, size?:int, error?:int} $fichier
     * @return string chemin relatif de l'image enregistrée
     */
    public function televerser(array $fichier): string
    {
        $erreur = $fichier['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($erreur !== UPLOAD_ERR_OK) {
            throw new RuntimeException(match ($erreur) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Image trop lourde pour le serveur.',
                UPLOAD_ERR_NO_FILE => 'Aucun fichier sélectionné.',
                default => 'Envoi interrompu.',
            });
        }
        if (($fichier['size'] ?? 0) > self::MAX_OCTETS) {
            throw new RuntimeException('Image trop lourde (15 Mo maximum).');
        }

        $chemin = $fichier['tmp_name'] ?? '';
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($chemin) ?: '';
        $source = match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($chemin),
            'image/png'  => imagecreatefrompng($chemin),
            'image/webp' => imagecreatefromwebp($chemin),
            default      => false,
        };
        if ($source === false) {
            throw new RuntimeException('Format non pris en charge (JPEG, PNG ou WebP attendu).');
        }

        $slug = $this->nomDisponible($fichier['name'] ?? 'image');
        $this->ecrireJpeg($source, $this->dossier . '/' . $slug . '.jpg', self::LARGEUR_MAX, 82);
        $this->ecrireJpeg($source, $this->dossier . '/' . $slug . '-mini.jpg', self::LARGEUR_MINI, 78);
        imagedestroy($source);

        return $this->prefixe . '/' . $slug . '.jpg';
    }

    private function nomDisponible(string $nomOrigine): string
    {
        $base = pathinfo($nomOrigine, PATHINFO_FILENAME);
        $base = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $base) ?? '', '-')) ?: 'image';
        $slug = $base;
        $n = 1;
        while (is_file($this->dossier . '/' . $slug . '.jpg')) {
            $slug = $base . '-' . (++$n);
        }
        return $slug;
    }

    /**
     * Redimensionne sans agrandir, aplatit la transparence sur blanc,
     * écrit un JPEG progressif.
     */
    private function ecrireJpeg(\GdImage $source, string $destination, int $largeurMax, int $qualite): void
    {
        $l = imagesx($source);
        $h = imagesy($source);
        $ratio = min(1, $largeurMax / max($l, $h));
        $nl = max(1, (int) round($l * $ratio));
        $nh = max(1, (int) round($h * $ratio));

        $image = imagecreatetruecolor($nl, $nh);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagecopyresampled($image, $source, 0, 0, 0, 0, $nl, $nh, $l, $h);
        imageinterlace($image, true);
        imagejpeg($image, $destination, $qualite);
        imagedestroy($image);
    }
}
