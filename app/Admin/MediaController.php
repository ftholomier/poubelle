<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Content;
use App\Core\Csrf;
use App\Core\Mediatheque;
use App\Core\Session;
use App\Core\View;
use RuntimeException;

/**
 * Galerie publique du site : ajout d'images (optimisées par la
 * médiathèque), rattachement à une catégorie, retrait.
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

    public function __construct(
        private readonly View $view,
        private readonly Content $content,
        private readonly Mediatheque $mediatheque,
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

        $categorie = (string) ($_POST['categorie'] ?? 'domaine');
        if (!isset(self::CATEGORIES[$categorie])) {
            $categorie = 'domaine';
        }
        $alt = trim((string) ($_POST['alt'] ?? '')) ?: self::CATEGORIES[$categorie];

        try {
            $chemin = $this->mediatheque->televerser($_FILES['image'] ?? []);
        } catch (RuntimeException $e) {
            Session::flash('erreur', $e->getMessage());
            return $this->rediriger('/admin/galerie');
        }

        $galerie = $this->content->load('galerie');
        $galerie['medias'][] = ['src' => $chemin, 'alt' => $alt, 'cat' => $categorie];
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
}
