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

    /* Bornes du décodage. Elles ne protègent pas du poids du fichier — c'est
       MAX_OCTETS qui s'en charge — mais de ce qu'il coûte une fois décodé :
       quatre octets par pixel en mémoire. Quarante millions de pixels font
       déjà 160 Mo, au-delà de ce qu'un mutualisé accorde à PHP. Aucune photo
       d'appareil courant n'approche ces valeurs. */
    private const COTE_MAX   = 8000;
    private const PIXELS_MAX = 40000000;
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
        $this->verifierDimensions($chemin);
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
     * Supprime une image et la vignette qui l'accompagne.
     *
     * Le chemin est validé par existe() avant toute suppression : il vient
     * d'un formulaire, et basename() seul ne suffirait pas à écarter une
     * tentative de sortir du dossier.
     */
    /**
     * Pivote une photo d'un quart de tour, et refait sa vignette.
     *
     * Les photos prises au téléphone arrivent souvent couchées : l'appareil note
     * l'orientation dans l'EXIF, que le redimensionnement à l'envoi ne
     * conserve pas. Plutôt que de deviner, on laisse l'exploitant redresser
     * ce qui doit l'être, depuis l'écran où il voit le résultat.
     *
     * La rotation est appliquée au fichier lui-même, non par une classe CSS :
     * l'image part ensuite redressée partout — page publique, vignette,
     * partage sur les réseaux — et rien n'est à recalculer à l'affichage.
     */
    public function pivoter(string $chemin, int $degres): void
    {
        if (!$this->existe($chemin)) {
            throw new RuntimeException('Photo introuvable.');
        }

        $degres = match (true) {
            $degres > 0 => -90,   // GD tourne dans le sens trigonométrique
            default     => 90,
        };

        $fichier = $this->dossier . '/' . basename($chemin);
        $source = $this->ouvrir($fichier);

        $pivotee = imagerotate($source, $degres, 0);
        imagedestroy($source);
        if ($pivotee === false) {
            throw new RuntimeException('La rotation a échoué.');
        }

        // On réécrit dans le format de destination du socle — JPEG — quelle
        // que soit l'origine : c'est déjà ce que fait l'envoi.
        $destination = preg_replace('/\.(jpe?g|png|webp)$/i', '.jpg', $fichier) ?? $fichier;
        $this->ecrireJpeg($pivotee, $destination, self::LARGEUR_MAX, 82);

        $mini = preg_replace('/\.jpg$/', '-mini.jpg', $destination) ?? '';
        if ($mini !== '') {
            $this->ecrireJpeg($pivotee, $mini, self::LARGEUR_MINI, 76);
        }
        imagedestroy($pivotee);

        // Un PNG ou un WebP pivoté devient un JPEG : l'ancien fichier n'a plus
        // lieu d'être, et le contenu qui le référence sera corrigé par
        // l'appelant.
        if ($destination !== $fichier) {
            @unlink($fichier);
        }
    }

    /**
     * Refuse une image trop grande AVANT de la décoder.
     *
     * Le poids du fichier ne dit rien de ce qu'il coûtera en mémoire : un PNG
     * de 20 000 × 20 000 pixels tient en deux cents kilo-octets et demande
     * 1,6 Go une fois décodé. `memory_limit` est alors atteint au milieu de
     * `imagecreatefrompng()` — page blanche, et sur mutualisé parfois le
     * processus tué par l'hébergeur. La seule parade est de mesurer d'abord :
     * `getimagesize()` lit l'en-tête, pas les pixels.
     */
    private function verifierDimensions(string $fichier): void
    {
        $taille = @getimagesize($fichier);
        if ($taille === false) {
            throw new RuntimeException('Ce fichier n’est pas une image lisible.');
        }

        [$largeur, $hauteur] = $taille;
        if ($largeur > self::COTE_MAX || $hauteur > self::COTE_MAX
            || $largeur * $hauteur > self::PIXELS_MAX) {
            throw new RuntimeException(sprintf(
                'Image trop grande : %d × %d pixels. Le maximum est de %d pixels de '
                . 'côté et %d millions de pixels au total. Réduisez-la avant de l’envoyer.',
                $largeur,
                $hauteur,
                self::COTE_MAX,
                (int) (self::PIXELS_MAX / 1000000)
            ));
        }
    }

    /** Ouvre une image, quel que soit son format d'origine. */
    private function ouvrir(string $fichier): \GdImage
    {
        $this->verifierDimensions($fichier);
        $type = @exif_imagetype($fichier);
        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($fichier),
            IMAGETYPE_PNG  => @imagecreatefrompng($fichier),
            IMAGETYPE_WEBP => @imagecreatefromwebp($fichier),
            default        => false,
        };

        if ($image === false) {
            throw new RuntimeException('Ce fichier n’est pas une image lisible.');
        }

        return $image;
    }

    public function supprimer(string $chemin): bool
    {
        if (!$this->existe($chemin)) {
            return false;
        }

        $fichier = $this->dossier . '/' . basename($chemin);
        $mini    = $this->dossier . '/' . basename(
            preg_replace('/\.(jpe?g|png|webp)$/i', '-mini.jpg', $chemin) ?? $chemin
        );

        $retire = @unlink($fichier);
        if (is_file($mini)) {
            @unlink($mini);
        }

        return $retire;
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
        @chmod($destination, Permissions::FICHIER);
    }
}
