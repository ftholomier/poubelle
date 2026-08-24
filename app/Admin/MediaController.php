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
 * Médiathèque : les photos disponibles pour illustrer le site.
 *
 * Elle n'a pas de contenu propre — c'est un dossier d'images dans lequel les
 * écrans d'édition viennent puiser. Les images envoyées sont redimensionnées
 * et accompagnées d'une vignette par Mediatheque.
 */
final class MediaController
{
    public function __construct(
        private readonly View $view,
        private readonly Content $content,
        private readonly Mediatheque $mediatheque,
    ) {
    }

    /**
     * Contenus susceptibles de citer une photo : ils servent à dire où une
     * image est employée, et à suivre un renommage.
     */
    private const CONTENUS = [
        'site'                   => 'Coordonnées',
        'services'               => 'Services',
        'valeurs'                => 'Valeurs',
        'realisations'           => 'Réalisations',
        'pages/accueil'          => 'Page d’accueil',
        'pages/la-societe'       => 'Page « La société »',
        'pages/nos-services'     => 'Page « Nos services »',
        'pages/nos-valeurs'      => 'Page « Nos valeurs »',
        'pages/realisations'     => 'Page « Réalisations »',
        'pages/faq'              => 'Page « Questions fréquentes »',
        'pages/contact'          => 'Page « Contact »',
    ];

    private function rediriger(string $chemin = '/admin/photos'): string
    {
        header('Location: ' . url($chemin), true, 303);
        return '';
    }

    public function galerie(): string
    {
        return $this->view->render('admin/photos', [
            'page'   => ['titre' => 'Photos'],
            'medias' => $this->mediatheque->lister(),
            'usages' => $this->usages(),
        ], 'admin/layout');
    }

    /**
     * Où chaque photo est utilisée.
     *
     * Une image supprimée alors qu'elle illustre encore une page laisserait
     * un visuel « photo à venir » à sa place, sans que personne ne comprenne
     * pourquoi : autant le dire avant.
     *
     * @return array<string, string[]>
     */
    private function usages(): array
    {
        $usages = [];
        foreach (self::CONTENUS as $nom => $libelle) {
            try {
                $donnees = $this->content->load($nom);
            } catch (RuntimeException) {
                continue;
            }

            foreach (self::cheminsImages($donnees) as $chemin) {
                if (!in_array($libelle, $usages[$chemin] ?? [], true)) {
                    $usages[$chemin][] = $libelle;
                }
            }
        }

        return $usages;
    }

    /**
     * Relève tous les chemins d'image d'une structure, à n'importe quelle
     * profondeur : les photos ne sont pas rangées au même endroit selon les
     * pages, et une liste écrite à la main se périmerait au premier ajout.
     *
     * @param array<mixed> $donnees
     * @return string[]
     */
    private static function cheminsImages(array $donnees): array
    {
        $trouves = [];

        array_walk_recursive($donnees, static function (mixed $valeur) use (&$trouves): void {
            if (is_string($valeur) && preg_match('#^assets/img/site/[^/]+\.(jpe?g|png|webp)$#i', $valeur) === 1) {
                $trouves[] = $valeur;
            }
        });

        return array_unique($trouves);
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

        $envoi = $_FILES['images'] ?? null;
        if (!is_array($envoi) || !isset($envoi['name'])) {
            Session::flash('erreur', 'Aucun fichier reçu.');
            return $this->rediriger();
        }

        // le champ accepte plusieurs fichiers : PHP fournit alors des tableaux
        $noms = (array) $envoi['name'];
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
                $this->mediatheque->televerser($fichier);
                $ajoutees++;
            } catch (RuntimeException $e) {
                $echecs[] = ($fichier['name'] ?: 'fichier ' . ($i + 1)) . ' — ' . $e->getMessage();
            }
        }

        if ($ajoutees > 0) {
            Session::flash('succes', $ajoutees > 1
                ? $ajoutees . ' photos ajoutées à la médiathèque.'
                : 'Photo ajoutée à la médiathèque.');
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
     * Suppression définitive du fichier et de sa vignette.
     */
    public function retrait(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger();
        }

        $chemin = trim((string) ($_POST['src'] ?? ''));
        if ($chemin === '' || !$this->mediatheque->existe($chemin)) {
            Session::flash('erreur', 'Photo introuvable.');
            return $this->rediriger();
        }

        // Une photo encore utilisée se supprime quand même, si le client
        // insiste — mais jamais par inadvertance.
        $usages = $this->usages()[$chemin] ?? [];
        if ($usages !== [] && ($_POST['confirme'] ?? '') === '') {
            Session::flash('erreur', 'Cette photo illustre encore : ' . implode(', ', $usages)
                . '. Retirez-la de ces pages, ou cochez la case de confirmation.');
            return $this->rediriger();
        }

        $this->mediatheque->supprimer($chemin);
        Session::flash('succes', 'Photo supprimée.');

        return $this->rediriger();
    }

    /**
     * Pivote une photo d'un quart de tour.
     *
     * L'écran d'origine est repris dans le formulaire : la rotation se
     * déclenche aussi bien depuis la médiathèque que depuis l'écran des
     * réalisations, et l'exploitant revient là où il était.
     */
    public function rotation(): string
    {
        $retour = (string) ($_POST['retour'] ?? '/admin/photos');
        // seule une adresse du back-office est acceptée : un champ de
        // formulaire ne doit pas pouvoir servir de rebond vers l'extérieur
        if (!preg_match('#^/admin/[a-z0-9/\-]*$#', $retour)) {
            $retour = '/admin/photos';
        }

        if (!Csrf::verifier()) {
            return $this->rediriger($retour);
        }

        $chemin = trim((string) ($_POST['src'] ?? ''));
        $sens = ($_POST['sens'] ?? 'droite') === 'gauche' ? -1 : 1;

        try {
            $this->mediatheque->pivoter($chemin, $sens);
            $this->corrigerExtension($chemin);
            Session::flash('succes', 'Photo pivotée.');
        } catch (RuntimeException $e) {
            Session::flash('erreur', $e->getMessage());
        }

        return $this->rediriger($retour);
    }

    /**
     * Une photo PNG ou WebP pivotée devient un JPEG : les contenus qui la
     * citaient doivent suivre, faute de quoi l'image disparaîtrait du site.
     */
    private function corrigerExtension(string $ancien): void
    {
        $nouveau = preg_replace('/\.(png|webp|jpeg)$/i', '.jpg', $ancien) ?? $ancien;
        if ($nouveau === $ancien) {
            return;
        }

        foreach (array_keys(self::CONTENUS) as $nom) {
            $donnees = $this->content->load($nom);
            $json = json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false || !str_contains($json, $ancien)) {
                continue;
            }
            $this->content->save($nom, json_decode(str_replace($ancien, $nouveau, $json), true));
        }
    }
}
