<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Auth;
use App\Core\Content;
use App\Core\Conversations;
use App\Core\Csrf;
use App\Core\Frequentation;
use App\Core\Mediatheque;
use App\Core\Parametres;
use App\Core\Publications;
use App\Core\Reseaux;
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
        private readonly Frequentation $frequentation,
        private readonly Publications $publications,
        private readonly Conversations $conversations,
        private readonly Parametres $parametres,
        private readonly Reseaux $reseaux,
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

    /**
     * Le tableau de bord : ce qui demande une action, puis ce qui va bien.
     *
     * Un tableau de bord de mairie n'est pas une vitrine de chiffres. La
     * secrétaire l'ouvre entre deux administrés, et la seule question qui
     * compte est : **est-ce qu'il y a quelque chose à faire ?** Les alertes
     * viennent donc d'abord, et elles ne s'affichent que lorsqu'elles ont
     * lieu d'être ; un bandeau permanent qui dit « tout va bien » cesse d'être
     * lu au bout d'une semaine, et avec lui celui qui dit le contraire.
     */
    public function tableauDeBord(): string
    {
        $aujourdhui = date('Y-m-d');
        $actualites = $this->content->publies('actualites');
        $agenda = $this->content->publies('agenda');
        $aVenir = array_values(array_filter(
            $agenda,
            static fn(array $e): bool => (string) ($e['fin'] ?? $e['date'] ?? '') >= $aujourdhui
        ));
        usort($aVenir, static fn(array $a, array $b): int
            => strcmp((string) ($a['date'] ?? ''), (string) ($b['date'] ?? '')));

        usort($actualites, static fn(array $a, array $b): int
            => strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? '')));

        $serie = $this->frequentation->parJour(30);
        $precedente = $this->frequentation->parJour(60);

        return $this->view->render('admin/tableau-de-bord', [
            'page'        => ['titre' => 'Tableau de bord'],
            'aFaire'      => $this->aFaire($aVenir, $actualites),
            'audience'    => [
                'amorcee'   => $this->frequentation->amorcee(),
                'serie'     => $serie,
                'total'     => array_sum($serie),
                'precedent' => array_sum(array_slice($precedente, 0, 30)),
                'pages'     => $this->frequentation->pagesPhares(6, 30),
            ],
            'chiffres'    => [
                ['libelle' => 'Démarches en ligne',  'valeur' => count($this->content->publies('demarches')),  'url' => '/admin/demarches'],
                ['libelle' => 'Actualités publiées', 'valeur' => count($actualites),                           'url' => '/admin/actualites'],
                ['libelle' => 'Rendez-vous à venir', 'valeur' => count($aVenir),                               'url' => '/admin/listes/agenda'],
                ['libelle' => 'Documents en ligne',  'valeur' => count($this->content->publies('documents')),  'url' => '/admin/listes/documents'],
                ['libelle' => 'Photos',              'valeur' => count($this->mediatheque->lister()),          'url' => '/admin/photos'],
            ],
            'prochains'   => array_slice($aVenir, 0, 4),
            'dernieres'   => array_slice($actualites, 0, 4),
        ], 'admin/layout');
    }

    /**
     * Ce qui attend quelqu'un, du plus pressant au moins pressant.
     *
     * Chaque entrée porte un ton, un libellé, et l'écran où l'on va le régler.
     * L'ordre est celui de l'urgence réelle pour une commune : un administré
     * qui attend une réponse passe avant une clé d'API manquante.
     *
     * @param list<array<string, mixed>> $aVenir
     * @param list<array<string, mixed>> $actualites
     * @return list<array{ton: string, texte: string, url: string, action: string}>
     */
    private function aFaire(array $aVenir, array $actualites): array
    {
        $aFaire = [];

        $nonLues = $this->conversations->nonLues();
        if ($nonLues > 0) {
            $aFaire[] = [
                'ton'    => 'urgent',
                'texte'  => $nonLues . ' message' . ($nonLues > 1 ? 's' : '') . ' de l’assistant sans réponse',
                'url'    => '/admin/conversations',
                'action' => 'Lire les messages',
            ];
        }

        $enRetard = $this->publications->enRetard(time());
        if ($enRetard > 0) {
            $aFaire[] = [
                'ton'    => 'urgent',
                'texte'  => $enRetard . ' publication' . ($enRetard > 1 ? 's' : '')
                          . ' devrait déjà être partie sur les réseaux',
                'url'    => '/admin/reseaux',
                'action' => 'Voir la file',
            ];
        }

        $echecs = 0;
        foreach ($this->publications->journal() as $ligne) {
            if (($ligne['succes'] ?? true) === false) {
                $echecs++;
            }
        }
        if ($echecs > 0) {
            $aFaire[] = [
                'ton'    => 'attention',
                'texte'  => $echecs . ' publication' . ($echecs > 1 ? 's ont' : ' a')
                          . ' échoué sur les réseaux',
                'url'    => '/admin/reseaux',
                'action' => 'Voir le journal',
            ];
        }

        // Une actualité de plus de six mois en tête de page donne l'impression
        // d'une commune endormie. C'est le reproche le plus courant fait aux
        // sites de mairie, et le plus facile à éviter.
        $derniere = (string) ($actualites[0]['date'] ?? '');
        if ($derniere !== '' && $derniere < date('Y-m-d', strtotime('-6 months'))) {
            $aFaire[] = [
                'ton'    => 'attention',
                'texte'  => 'Aucune actualité depuis le ' . self::enFrancais($derniere),
                'url'    => '/admin/actualites',
                'action' => 'Écrire une actualité',
            ];
        } elseif ($actualites === []) {
            $aFaire[] = [
                'ton'    => 'attention',
                'texte'  => 'Aucune actualité publiée',
                'url'    => '/admin/actualites',
                'action' => 'Écrire une actualité',
            ];
        }

        if ($aVenir === []) {
            $aFaire[] = [
                'ton'    => 'info',
                'texte'  => 'L’agenda ne porte aucun rendez-vous à venir',
                'url'    => '/admin/listes/agenda',
                'action' => 'Ajouter un rendez-vous',
            ];
        }

        if (!$this->parametres->smtpConfigure()) {
            $aFaire[] = [
                'ton'    => 'urgent',
                'texte'  => 'Le courriel n’est pas configuré : les formulaires n’envoient rien',
                'url'    => '/admin/parametres',
                'action' => 'Configurer l’envoi',
            ];
        }

        if (!$this->reseaux->facebookPret()) {
            $aFaire[] = [
                'ton'    => 'info',
                'texte'  => 'Aucun compte Facebook ou Instagram connecté',
                'url'    => '/admin/reseaux',
                'action' => 'Connecter les comptes',
            ];
        }

        return $aFaire;
    }

    /** « 2026-06-30 » → « 30 juin 2026 ». */
    private static function enFrancais(string $jour): string
    {
        $mois = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
                 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
        [$a, $m, $j] = array_pad(explode('-', $jour), 3, '1');

        return (int) $j . ' ' . ($mois[(int) $m] ?? '') . ' ' . $a;
    }
}
