<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Content;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;

/**
 * Galerie et médias : upload avec optimisation GD (1920 px + vignette
 * 640 px), rattachement à une catégorie, retrait de la galerie.
 */
final class MediaController
{
    private const CATEGORIES = [
        'domaine' => 'Le domaine',
        'carelie' => 'Lodge Carélie',
        'indus'   => "Lodge L'Indus",
        'gite'    => 'Le Gîte',
        'ferme'   => 'La ferme',
    ];

    private const MAX_OCTETS = 15_000_000;

    public function __construct(
        private readonly View $view,
        private readonly Content $content,
        private readonly string $dossierImages,
    ) {
    }

    private function rediriger(string $chemin): string
    {
        header('Location: ' . url($chemin), true, 303);
        return '';
    }

    public function galerie(): string
    {
        return $this->view->render('admin/galerie', [
            'page'       => ['titre' => 'Galerie'],
            'medias'     => $this->content->load('galerie')['medias'] ?? [],
            'categories' => self::CATEGORIES,
        ], 'admin/layout');
    }

    public function ajout(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger('/admin/galerie');
        }

        $fichier = $_FILES['image'] ?? null;
        $categorie = (string) ($_POST['categorie'] ?? 'domaine');
        if (!isset(self::CATEGORIES[$categorie])) {
            $categorie = 'domaine';
        }
        $alt = trim((string) ($_POST['alt'] ?? '')) ?: self::CATEGORIES[$categorie];

        if (!is_array($fichier) || ($fichier['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Session::flash('erreur', 'Aucun fichier reçu (ou fichier trop lourd pour le serveur).');
            return $this->rediriger('/admin/galerie');
        }
        if (($fichier['size'] ?? 0) > self::MAX_OCTETS) {
            Session::flash('erreur', 'Image trop lourde (15 Mo maximum).');
            return $this->rediriger('/admin/galerie');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($fichier['tmp_name']) ?: '';
        $source = match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($fichier['tmp_name']),
            'image/png'  => imagecreatefrompng($fichier['tmp_name']),
            'image/webp' => imagecreatefromwebp($fichier['tmp_name']),
            default      => false,
        };
        if ($source === false) {
            Session::flash('erreur', 'Format non pris en charge (JPEG, PNG ou WebP attendu).');
            return $this->rediriger('/admin/galerie');
        }

        // nom de fichier propre et unique
        $base = pathinfo($fichier['name'] ?? 'image', PATHINFO_FILENAME);
        $base = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $base) ?? '', '-')) ?: 'image';
        $slug = $base;
        $n = 1;
        while (is_file($this->dossierImages . '/' . $slug . '.jpg')) {
            $slug = $base . '-' . (++$n);
        }

        $this->enregistrerJpeg($source, $this->dossierImages . '/' . $slug . '.jpg', 1920, 82);
        $this->enregistrerJpeg($source, $this->dossierImages . '/' . $slug . '-mini.jpg', 640, 78);
        imagedestroy($source);

        $galerie = $this->content->load('galerie');
        $galerie['medias'][] = [
            'src' => 'assets/img/site/' . $slug . '.jpg',
            'alt' => $alt,
            'cat' => $categorie,
        ];
        $this->content->save('galerie', $galerie);

        Session::flash('succes', 'Image ajoutée à la galerie.');
        return $this->rediriger('/admin/galerie');
    }

    public function retrait(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger('/admin/galerie');
        }
        $src = (string) ($_POST['src'] ?? '');

        $galerie = $this->content->load('galerie');
        $avant = count($galerie['medias']);
        $galerie['medias'] = array_values(array_filter(
            $galerie['medias'],
            fn(array $m) => ($m['src'] ?? '') !== $src
        ));

        if (count($galerie['medias']) < $avant) {
            $this->content->save('galerie', $galerie);
            Session::flash('succes', 'Image retirée de la galerie. Le fichier reste disponible sur le serveur.');
        }
        return $this->rediriger('/admin/galerie');
    }

    /**
     * Redimensionne (sans agrandir) et écrit un JPEG progressif.
     *
     * @param \GdImage $source
     */
    private function enregistrerJpeg(\GdImage $source, string $destination, int $maxCote, int $qualite): void
    {
        $l = imagesx($source);
        $h = imagesy($source);
        $ratio = min(1, $maxCote / max($l, $h));
        $nl = max(1, (int) round($l * $ratio));
        $nh = max(1, (int) round($h * $ratio));

        $image = imagecreatetruecolor($nl, $nh);
        // fond blanc pour les PNG transparents convertis en JPEG
        $blanc = imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $blanc);
        imagecopyresampled($image, $source, 0, 0, 0, 0, $nl, $nh, $l, $h);
        imageinterlace($image, true);
        imagejpeg($image, $destination, $qualite);
        imagedestroy($image);
    }
}
