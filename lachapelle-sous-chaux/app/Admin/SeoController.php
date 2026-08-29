<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Content;
use App\Core\Csrf;
use App\Core\Mediatheque;
use App\Core\Seo;
use App\Core\Session;
use App\Core\View;
use RuntimeException;

/**
 * Écran Référencement : adresses des pages, balises titre et description,
 * indexation, redirections permanentes.
 *
 * Tout est écrit dans data/seo.json ; les routes s'y adaptent au chargement
 * suivant, sans qu'aucun fichier de code soit à modifier.
 */
final class SeoController
{
    /** Longueurs au-delà desquelles Google tronque l'affichage. */
    private const TITRE_MAX       = 60;
    private const DESCRIPTION_MIN = 120;
    private const DESCRIPTION_MAX = 158;

    public function __construct(
        private readonly View $view,
        private readonly Content $content,
        private readonly Seo $seo,
        private readonly Mediatheque $mediatheque,
    ) {
    }

    private function rediriger(string $ancre = ''): string
    {
        header('Location: ' . url('/admin/referencement') . $ancre, true, 303);
        return '';
    }

    public function ecran(): string
    {
        $pages = [];
        foreach ($this->seo->pages() as $cle => $page) {
            $meta = $this->seo->meta($cle);
            $pages[$cle] = $page + [
                'chemin'    => $this->seo->chemin($cle),
                'effectif'  => $meta,
                'alertes'   => $this->alertes($meta),
                'collection' => Seo::COLLECTIONS[$cle] ?? null,
            ];
        }

        $fiches = [];
        foreach (Seo::COLLECTIONS as $cle => $collection) {
            foreach ($this->content->load($collection)['items'] ?? [] as $item) {
                $slug = (string) ($item['slug'] ?? '');
                if ($slug === '') {
                    continue;
                }
                $meta = $this->seo->meta($cle, [
                    'titre' => $item['nom'] ?? '',
                    'meta'  => $item['meta'] ?? [],
                ], true);
                $fiches[$cle][] = [
                    'slug'        => $slug,
                    'nom'         => (string) ($item['nom'] ?? $slug),
                    'description' => (string) ($item['meta']['description'] ?? ''),
                    'chemin'      => $this->seo->chemin($cle, $slug),
                    'en_ligne'    => Content::estPublie($item),
                    'effectif'    => $meta,
                    'alertes'     => $this->alertes($meta),
                ];
            }
        }

        return $this->view->render('admin/referencement', [
            'page'         => ['titre' => 'Référencement'],
            'general'      => $this->seo->tout(),
            'pages'        => $pages,
            'fiches'       => $fiches,
            'redirections' => $this->seo->redirections(),
            'photos'       => $this->mediatheque->lister(),
            'limites'      => [
                'titre_max'       => self::TITRE_MAX,
                'description_min' => self::DESCRIPTION_MIN,
                'description_max' => self::DESCRIPTION_MAX,
            ],
        ], 'admin/layout');
    }

    /**
     * Signale ce qui nuirait à l'affichage dans les résultats de recherche.
     *
     * @param array{titre: string, description: string, titre_herite: bool, description_heritee: bool} $meta
     * @return string[]
     */
    private function alertes(array $meta): array
    {
        $alertes = [];
        $titre = mb_strlen($meta['titre']);
        $desc  = mb_strlen($meta['description']);

        if ($titre > self::TITRE_MAX) {
            $alertes[] = 'Titre trop long (' . $titre . ' caractères) : Google le coupera vers '
                . self::TITRE_MAX . '.';
        }
        if ($desc < self::DESCRIPTION_MIN) {
            $alertes[] = 'Description courte (' . $desc . ' caractères) : visez '
                . self::DESCRIPTION_MIN . ' à ' . self::DESCRIPTION_MAX . '.';
        } elseif ($desc > self::DESCRIPTION_MAX) {
            $alertes[] = 'Description trop longue (' . $desc . ' caractères) : elle sera coupée vers '
                . self::DESCRIPTION_MAX . '.';
        }

        return $alertes;
    }

    // ----- enregistrements ------------------------------------------------

    public function generalEnvoi(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger();
        }

        $this->seo->majGeneral([
            'image_partage' => $_POST['image_partage'] ?? '',
            'indexer_site'  => isset($_POST['indexer_site']),
        ]);

        Session::flash(
            'succes',
            isset($_POST['indexer_site'])
                ? 'Réglages enregistrés. Le site est ouvert aux moteurs de recherche.'
                : 'Réglages enregistrés. Le site est fermé aux moteurs : robots.txt interdit tout.'
        );
        return $this->rediriger();
    }

    public function pageEnvoi(string $cle): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger();
        }

        try {
            Session::flash('succes', $this->seo->majPage($cle, [
                'slug'        => $_POST['slug'] ?? '',
                'titre'       => $_POST['titre'] ?? '',
                'description' => $_POST['description'] ?? '',
                'image'       => $_POST['image'] ?? '',
                'indexer'     => isset($_POST['indexer']),
            ]));
        } catch (RuntimeException $e) {
            Session::flash('erreur', $e->getMessage());
        }

        return $this->rediriger('#page-' . rawurlencode($cle));
    }

    public function ficheEnvoi(string $cle, string $slug): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger();
        }

        try {
            Session::flash('succes', $this->seo->majFiche($cle, $slug, [
                'slug'        => $_POST['slug'] ?? $slug,
                'description' => $_POST['description'] ?? '',
            ]));
        } catch (RuntimeException $e) {
            Session::flash('erreur', $e->getMessage());
        }

        return $this->rediriger('#fiches');
    }

    public function redirectionAjout(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger();
        }

        try {
            $this->seo->ajouterRedirection(
                (string) ($_POST['depuis'] ?? ''),
                (string) ($_POST['vers'] ?? '')
            );
            Session::flash('succes', 'Redirection ajoutée.');
        } catch (RuntimeException $e) {
            Session::flash('erreur', $e->getMessage());
        }

        return $this->rediriger('#redirections');
    }

    public function redirectionRetrait(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger();
        }

        $this->seo->retirerRedirection((string) ($_POST['depuis'] ?? ''));
        Session::flash('succes', 'Redirection supprimée. L’ancienne adresse renverra une page introuvable.');

        return $this->rediriger('#redirections');
    }
}
