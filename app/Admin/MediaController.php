<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Content;
use App\Core\Csrf;
use App\Core\Mediatheque;
use App\Core\Session;
use App\Core\View;
use RuntimeException;
use Throwable;

/**
 * Galerie publique du site : envoi d'images par lot, classement, catégorie,
 * mise hors ligne, retrait.
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

    private function rediriger(string $chemin = '/admin/galerie'): string
    {
        header('Location: ' . url($chemin), true, 303);
        return '';
    }

    public function galerie(): string
    {
        $medias = $this->content->load('galerie')['medias'] ?? [];

        $compte = ['tout' => count($medias)];
        foreach (self::CATEGORIES as $cle => $_) {
            $compte[$cle] = 0;
        }
        foreach ($medias as $m) {
            $cat = $m['cat'] ?? 'domaine';
            if (isset($compte[$cat])) {
                $compte[$cat]++;
            }
        }

        return $this->view->render('admin/galerie', [
            'page'       => ['titre' => 'Galerie'],
            'medias'     => $medias,
            'categories' => self::CATEGORIES,
            'compte'     => $compte,
        ], 'admin/layout');
    }

    /**
     * Envoi d'une ou plusieurs images en une fois.
     *
     * Chaque fichier est traité indépendamment : un fichier refusé n'annule
     * pas les autres, et le compte rendu dit lesquels ont échoué.
     */
    public function ajout(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger();
        }

        $categorie = (string) ($_POST['categorie'] ?? 'domaine');
        if (!isset(self::CATEGORIES[$categorie])) {
            $categorie = 'domaine';
        }
        $alt = trim((string) ($_POST['alt'] ?? ''));

        $envoi = $_FILES['images'] ?? null;
        if (!is_array($envoi) || !isset($envoi['name'])) {
            Session::flash('erreur', 'Aucun fichier reçu.');
            return $this->rediriger();
        }

        // le champ accepte plusieurs fichiers : PHP fournit alors des tableaux
        $noms = (array) $envoi['name'];
        $galerie = $this->content->load('galerie');
        $ajoutees = 0;
        $echecs = [];

        foreach (array_keys($noms) as $i) {
            $fichier = [
                'name'     => $envoi['name'][$i] ?? '',
                'tmp_name' => $envoi['tmp_name'][$i] ?? '',
                'size'     => $envoi['size'][$i] ?? 0,
                'error'    => $envoi['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            ];
            if ($fichier['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            try {
                $chemin = $this->mediatheque->televerser($fichier);
            } catch (RuntimeException $e) {
                $echecs[] = ($fichier['name'] ?: 'fichier ' . ($i + 1)) . ' — ' . $e->getMessage();
                continue;
            }

            $galerie['medias'][] = [
                'src'   => $chemin,
                'alt'   => $alt !== '' ? $alt : self::CATEGORIES[$categorie],
                'cat'   => $categorie,
                'actif' => true,
            ];
            $ajoutees++;
        }

        if ($ajoutees > 0) {
            $this->content->save('galerie', $galerie);
            Session::flash('succes', $ajoutees > 1
                ? $ajoutees . ' photos ajoutées à la galerie.'
                : 'Photo ajoutée à la galerie.');
        }
        if ($echecs !== []) {
            Session::flash('erreur', count($echecs) . ' fichier(s) refusé(s) : ' . implode(' ; ', $echecs));
        }
        if ($ajoutees === 0 && $echecs === []) {
            Session::flash('erreur', 'Aucun fichier sélectionné.');
        }

        return $this->rediriger();
    }

    /**
     * Bascule visible / masqué d'une image.
     */
    public function publication(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger();
        }
        $src = (string) ($_POST['src'] ?? '');

        $galerie = $this->content->load('galerie');
        foreach ($galerie['medias'] as $i => $m) {
            if (($m['src'] ?? '') !== $src) {
                continue;
            }
            $visible = !Content::estPublie($m);
            $galerie['medias'][$i]['actif'] = $visible;
            $this->content->save('galerie', $galerie);
            Session::flash('succes', $visible
                ? 'Photo remise en ligne.'
                : 'Photo masquée : elle ne s’affiche plus sur le site.');
            break;
        }

        return $this->rediriger();
    }

    /**
     * Change la catégorie d'une image.
     */
    public function categorie(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger();
        }
        $src = (string) ($_POST['src'] ?? '');
        $categorie = (string) ($_POST['cat'] ?? '');
        if (!isset(self::CATEGORIES[$categorie])) {
            return $this->rediriger();
        }

        $galerie = $this->content->load('galerie');
        foreach ($galerie['medias'] as $i => $m) {
            if (($m['src'] ?? '') === $src) {
                $galerie['medias'][$i]['cat'] = $categorie;
                $this->content->save('galerie', $galerie);
                Session::flash('succes', 'Catégorie mise à jour : ' . self::CATEGORIES[$categorie] . '.');
                break;
            }
        }

        return $this->rediriger();
    }

    /**
     * Nouvel ordre de la galerie, envoyé après un glisser-déposer.
     *
     * Répond en JSON : le classement se fait sans recharger la page.
     */
    public function ordre(): string
    {
        if (!Csrf::verifier()) {
            return json_response(['erreur' => 'Jeton invalide.'], 403);
        }

        $ordre = $_POST['ordre'] ?? [];
        if (is_string($ordre)) {
            $ordre = array_filter(explode("\n", $ordre));
        }
        if (!is_array($ordre) || $ordre === []) {
            return json_response(['erreur' => 'Ordre vide.'], 422);
        }

        try {
            $galerie = $this->content->load('galerie');
            $parSrc = [];
            foreach ($galerie['medias'] as $m) {
                $parSrc[$m['src'] ?? ''] = $m;
            }

            $nouveau = [];
            foreach ($ordre as $src) {
                $src = (string) $src;
                if (isset($parSrc[$src])) {
                    $nouveau[] = $parSrc[$src];
                    unset($parSrc[$src]);
                }
            }
            // toute image absente de la liste reçue reste en fin de galerie
            foreach ($parSrc as $reste) {
                $nouveau[] = $reste;
            }

            $galerie['medias'] = $nouveau;
            $this->content->save('galerie', $galerie);
        } catch (Throwable $e) {
            return json_response(['erreur' => $e->getMessage()], 500);
        }

        return json_response(['ok' => true, 'total' => count($nouveau)]);
    }

    public function retrait(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger();
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
            Session::flash('succes', 'Image retirée de la galerie. Le fichier reste dans la médiathèque.');
        }
        return $this->rediriger();
    }
}
