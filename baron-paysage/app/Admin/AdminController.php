<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Auth;
use App\Core\Content;
use App\Core\Csrf;
use App\Core\Mailer;
use App\Core\Parametres;
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
        private readonly Mailer $mailer,
        private readonly Parametres $parametres,
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

    // ----- récupération du mot de passe ----------------------------------

    public function motDePasseOublie(): string
    {
        if (!$this->auth->compteExiste()) {
            return $this->rediriger('/admin/configuration');
        }

        return $this->view->render('admin/mot-de-passe-oublie', [
            'page'    => ['titre' => 'Mot de passe oublié'],
            'message' => null,
            'erreur'  => null,
            'adresse' => $this->adresseDeSecours(),
        ], 'admin/layout-hors-ligne');
    }

    /**
     * Envoie le lien de récupération à l'adresse réglée dans Paramètres.
     *
     * Le lien ne part JAMAIS vers une adresse saisie dans le formulaire : il
     * suffirait alors d'en taper une pour se faire remettre les clés. Il part
     * à l'adresse configurée du site, que seul son propriétaire relève.
     *
     * Et la réponse affichée est la même que l'identifiant existe ou non :
     * dire « cet identifiant est inconnu » livre la moitié du secret à qui
     * tâtonne.
     */
    public function motDePasseOublieEnvoi(): string
    {
        if (!$this->auth->compteExiste()) {
            return $this->rediriger('/admin/configuration');
        }
        if (!Csrf::verifier()) {
            return $this->rediriger('/admin/mot-de-passe-oublie');
        }

        $ip = $this->ip();
        if (($reste = $this->auth->verrouille($ip)) !== null) {
            http_response_code(429);
            return $this->ecranOubli(null, 'Trop de demandes. Réessayez dans '
                . (int) ceil($reste / 60) . ' min.');
        }

        $identifiant = trim((string) ($_POST['identifiant'] ?? ''));
        $adresse = $this->adresseDeSecours();

        // Le compteur d'échecs sert aussi ici : sans lui, on pourrait inonder
        // la boîte du client de courriels de récupération.
        $this->auth->noterEchec($ip);

        $rassurant = 'Si cet identifiant est le bon, un lien vient de partir '
            . 'vers l’adresse de secours du site. Il vaut une heure.';

        if ($adresse === '') {
            error_log('Récupération de mot de passe : aucune adresse de secours configurée.');
            http_response_code(500);
            return $this->ecranOubli(null, 'Aucune adresse de secours n’est configurée pour ce '
                . 'site. Voir DEPLOIEMENT.md, § « Mot de passe perdu ».');
        }

        if (!hash_equals($this->auth->identifiantDuCompte(), $identifiant)) {
            return $this->ecranOubli($rassurant, null);
        }

        $jeton = $this->auth->jetonRecuperation();
        if ($jeton === null) {
            return $this->ecranOubli($rassurant, null);
        }

        $lien = $this->urlAbsolue('/admin/nouveau-mot-de-passe?jeton=' . rawurlencode($jeton));

        try {
            $this->mailer->envoyer(
                $adresse,
                'Réinitialiser le mot de passe du back-office',
                $this->corpsRecuperation($lien)
            );
        } catch (\Throwable $e) {
            error_log('Lien de récupération non envoyé : ' . $e->getMessage());
            http_response_code(500);
            return $this->ecranOubli(null, 'L’envoi a échoué. Vérifiez les réglages SMTP, ou '
                . 'reportez-vous à DEPLOIEMENT.md, § « Mot de passe perdu ».');
        }

        return $this->ecranOubli($rassurant, null);
    }

    public function nouveauMotDePasse(): string
    {
        $jeton = (string) ($_GET['jeton'] ?? '');
        if (!$this->auth->jetonValide($jeton)) {
            http_response_code(410);
            return $this->ecranOubli(null, 'Ce lien n’est plus valable : il a expiré, ou il a '
                . 'déjà servi. Demandez-en un nouveau.');
        }

        return $this->view->render('admin/nouveau-mot-de-passe', [
            'page'   => ['titre' => 'Nouveau mot de passe'],
            'jeton'  => $jeton,
            'erreur' => null,
        ], 'admin/layout-hors-ligne');
    }

    public function nouveauMotDePasseEnvoi(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger('/admin/connexion');
        }

        $jeton = (string) ($_POST['jeton'] ?? '');
        if (!$this->auth->jetonValide($jeton)) {
            http_response_code(410);
            return $this->ecranOubli(null, 'Ce lien n’est plus valable : il a expiré, ou il a '
                . 'déjà servi. Demandez-en un nouveau.');
        }

        $nouveau = (string) ($_POST['mot_de_passe'] ?? '');
        $confirmation = (string) ($_POST['confirmation'] ?? '');

        $erreur = null;
        if (mb_strlen($nouveau) < 8) {
            $erreur = 'Le mot de passe doit faire au moins 8 caractères.';
        } elseif ($nouveau !== $confirmation) {
            $erreur = 'Les deux saisies ne correspondent pas.';
        }

        if ($erreur !== null) {
            http_response_code(422);
            return $this->view->render('admin/nouveau-mot-de-passe', [
                'page'   => ['titre' => 'Nouveau mot de passe'],
                'jeton'  => $jeton,
                'erreur' => $erreur,
            ], 'admin/layout-hors-ligne');
        }

        $this->auth->redefinirMotDePasse($nouveau);
        $this->auth->noterSucces($this->ip());
        Session::flash('succes', 'Mot de passe changé. Vous pouvez vous connecter.');

        return $this->rediriger('/admin/connexion');
    }

    private function ecranOubli(?string $message, ?string $erreur): string
    {
        return $this->view->render('admin/mot-de-passe-oublie', [
            'page'    => ['titre' => 'Mot de passe oublié'],
            'message' => $message,
            'erreur'  => $erreur,
            'adresse' => $this->adresseDeSecours(),
        ], 'admin/layout-hors-ligne');
    }

    /**
     * L'adresse qui reçoit le lien : celle des Paramètres, ou à défaut
     * l'e-mail public du site.
     */
    private function adresseDeSecours(): string
    {
        $adresse = (string) $this->parametres->get('contact.destinataire')
            ?: (string) $this->content->get('site', 'contact.email', '');

        return filter_var($adresse, FILTER_VALIDATE_EMAIL) ? $adresse : '';
    }

    private function urlAbsolue(string $chemin): string
    {
        $protocole = (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $hote = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');

        return $protocole . '://' . $hote . url($chemin);
    }

    private function corpsRecuperation(string $lien): string
    {
        $lignes = [
            'Bonjour,',
            '',
            'Quelqu’un — vous, probablement — a demandé à réinitialiser le mot',
            'de passe du back-office de votre site.',
            '',
            'Ouvrez ce lien pour en choisir un nouveau :',
            '',
            '  ' . $lien,
            '',
            'Il vaut une heure, et une seule fois.',
            '',
            'Si vous n’êtes pas à l’origine de cette demande, ignorez ce message :',
            'votre mot de passe actuel reste valable et ce lien s’éteindra seul.',
        ];

        return implode("\n", $lignes) . "\n";
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
        // question que se pose le client en ouvrant cet écran.
        $services = $this->content->publies('services');
        $valeurs  = $this->content->publies('valeurs');
        $questions = $this->content->load('pages/contact')['faq']['items'] ?? [];

        return $this->view->render('admin/tableau-de-bord', [
            'page'   => ['titre' => 'Tableau de bord'],
            'stats'  => [
                ['libelle' => 'Services en ligne', 'valeur' => count($services), 'url' => '/admin/services'],
                ['libelle' => 'Valeurs en ligne',  'valeur' => count($valeurs),  'url' => '/admin/valeurs'],
                ['libelle' => 'Questions fréquentes', 'valeur' => count($questions), 'url' => '/admin/contact'],
                ['libelle' => 'Photos',            'valeur' => count($this->mediatheque->lister()), 'url' => '/admin/photos'],
            ],
        ], 'admin/layout');
    }
}
