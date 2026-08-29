<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Auth;
use App\Core\Content;
use App\Core\Csrf;
use App\Core\Mediatheque;
use App\Core\Session;
use App\Core\View;

/**
 * Écrans d'accès du back-office : première configuration, connexion,
 * déconnexion, tableau de bord.
 */
final class AdminController
{
    public function __construct(
        private readonly View $view,
        private readonly Content $content,
        private readonly Auth $auth,
        private readonly Mediatheque $mediatheque,
    ) {
    }

    private function ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? 'inconnue';
    }

    private function rediriger(string $chemin): string
    {
        header('Location: ' . url($chemin), true, 303);
        return '';
    }

    // ----- première configuration ---------------------------------------

    public function configuration(): string
    {
        if ($this->auth->compteExiste()) {
            return $this->rediriger('/admin/connexion');
        }
        return $this->view->render('admin/configuration', [
            'page'    => ['titre' => 'Première configuration'],
            'erreurs' => [],
            'valeurs' => [],
        ], 'admin/layout-hors-ligne');
    }

    public function configurationEnvoi(): string
    {
        if ($this->auth->compteExiste()) {
            return $this->rediriger('/admin/connexion');
        }
        if (!Csrf::verifier()) {
            return $this->rediriger('/admin/configuration');
        }

        $identifiant  = trim((string) ($_POST['identifiant'] ?? ''));
        $motDePasse   = (string) ($_POST['mot_de_passe'] ?? '');
        $confirmation = (string) ($_POST['confirmation'] ?? '');

        $erreurs = [];
        if (mb_strlen($identifiant) < 3) {
            $erreurs['identifiant'] = 'Choisissez un identifiant d\'au moins 3 caractères.';
        }
        if (mb_strlen($motDePasse) < 12) {
            $erreurs['mot_de_passe'] = 'Le mot de passe doit contenir au moins 12 caractères.';
        }
        if ($motDePasse !== $confirmation) {
            $erreurs['confirmation'] = 'La confirmation ne correspond pas.';
        }

        if ($erreurs !== []) {
            http_response_code(422);
            return $this->view->render('admin/configuration', [
                'page'    => ['titre' => 'Première configuration'],
                'erreurs' => $erreurs,
                'valeurs' => ['identifiant' => $identifiant],
            ], 'admin/layout-hors-ligne');
        }

        $this->auth->creerCompte($identifiant, $motDePasse);
        $this->auth->connecter($identifiant);
        Session::flash('succes', 'Compte administrateur créé. Bienvenue !');
        return $this->rediriger('/admin');
    }

    // ----- connexion -----------------------------------------------------

    public function connexion(): string
    {
        if (!$this->auth->compteExiste()) {
            return $this->rediriger('/admin/configuration');
        }
        if ($this->auth->connecte()) {
            return $this->rediriger('/admin');
        }
        return $this->view->render('admin/connexion', [
            'page'    => ['titre' => 'Connexion'],
            'erreur'  => null,
            'verrou'  => $this->auth->verrouille($this->ip()),
        ], 'admin/layout-hors-ligne');
    }

    public function connexionEnvoi(): string
    {
        if (!$this->auth->compteExiste()) {
            return $this->rediriger('/admin/configuration');
        }
        if (!Csrf::verifier()) {
            return $this->rediriger('/admin/connexion');
        }

        $ip = $this->ip();
        if (($reste = $this->auth->verrouille($ip)) !== null) {
            http_response_code(429);
            return $this->view->render('admin/connexion', [
                'page'   => ['titre' => 'Connexion'],
                'erreur' => null,
                'verrou' => $reste,
            ], 'admin/layout-hors-ligne');
        }

        $identifiant = trim((string) ($_POST['identifiant'] ?? ''));
        $motDePasse  = (string) ($_POST['mot_de_passe'] ?? '');

        if ($this->auth->verifier($identifiant, $motDePasse)) {
            $this->auth->noterSucces($ip);
            $this->auth->connecter($identifiant);
            return $this->rediriger('/admin');
        }

        $this->auth->noterEchec($ip);
        http_response_code(401);
        return $this->view->render('admin/connexion', [
            'page'   => ['titre' => 'Connexion'],
            'erreur' => 'Identifiant ou mot de passe incorrect.',
            'verrou' => $this->auth->verrouille($ip),
        ], 'admin/layout-hors-ligne');
    }

    public function deconnexion(): string
    {
        if (Csrf::verifier()) {
            $this->auth->deconnecter();
        }
        return $this->rediriger('/admin/connexion');
    }

    // ----- tableau de bord ----------------------------------------------

    public function tableauDeBord(): string
    {
        // Le compte porte sur ce qui est réellement en ligne : c'est la
        // question que se pose la mairie en ouvrant cet écran. Un chiffre qui
        // compterait aussi les fiches dépubliées ne dirait rien.
        $aujourdhui = date('Y-m-d');
        $aVenir = array_filter(
            $this->content->publies('agenda'),
            static fn(array $e): bool => (string) ($e['fin'] ?? $e['date'] ?? '') >= $aujourdhui
        );

        return $this->view->render('admin/tableau-de-bord', [
            'page'   => ['titre' => 'Tableau de bord'],
            'stats'  => [
                ['libelle' => 'Démarches en ligne', 'valeur' => count($this->content->publies('demarches')), 'url' => '/admin/demarches'],
                ['libelle' => 'Actualités publiées', 'valeur' => count($this->content->publies('actualites')), 'url' => '/admin/actualites'],
                ['libelle' => 'Rendez-vous à venir', 'valeur' => count($aVenir), 'url' => '/admin/listes/agenda'],
                ['libelle' => 'Documents en ligne', 'valeur' => count($this->content->publies('documents')), 'url' => '/admin/listes/documents'],
                ['libelle' => 'Photos', 'valeur' => count($this->mediatheque->lister()), 'url' => '/admin/photos'],
            ],
        ], 'admin/layout');
    }
}
