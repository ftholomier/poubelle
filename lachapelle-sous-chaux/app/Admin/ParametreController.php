<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Auth;
use App\Core\Content;
use App\Core\Csrf;
use App\Core\Mailer;
use App\Core\Parametres;
use App\Core\Permissions;
use App\Core\Session;
use App\Core\View;
use RuntimeException;
use Throwable;

/**
 * Écran Paramètres : envoi des e-mails, compte administrateur, diagnostic
 * du serveur. C'est ici que se règle tout ce qui dépend de l'hébergement.
 */
final class ParametreController
{
    private readonly Permissions $permissions;

    public function __construct(
        private readonly View $view,
        private readonly Parametres $parametres,
        private readonly Content $content,
        private readonly Auth $auth,
        private readonly Mailer $mailer,
        private readonly string $racine,
        private readonly string $racineWeb,
    ) {
        $this->permissions = new Permissions($racine);
    }

    private function rediriger(string $chemin = '/admin/parametres'): string
    {
        header('Location: ' . url($chemin), true, 303);
        return '';
    }

    public function ecran(): string
    {
        return $this->view->render('admin/parametres', [
            'page'        => ['titre' => 'Paramètres'],
            'parametres'  => $this->parametres->tout(),
            'identifiant' => $this->auth->identifiant(),
            'diagnostic'  => $this->diagnostic(),
            'droits'      => $this->permissions->analyser(),
            'destinataireEffectif' => $this->destinataireEffectif(),
            'trace'       => Session::flash('trace_smtp'),
        ], 'admin/layout');
    }

    /**
     * Destinataire réellement utilisé : celui des Paramètres, ou à défaut
     * l'e-mail public saisi dans Coordonnées.
     */
    private function destinataireEffectif(): string
    {
        return (string) $this->parametres->get('contact.destinataire')
            ?: (string) $this->content->get('site', 'contact.email', '');
    }

    // ------------------------------------------------------- mesure d'audience

    public function mesureEnvoi(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger();
        }

        $identifiant = trim((string) ($_POST['mesure_identifiant'] ?? ''));

        // un identifiant mal saisi ne remonterait aucune statistique, sans
        // que rien ne le signale : autant le refuser tout de suite
        if ($identifiant !== '' && preg_match('/^(G|UA|GTM)-[A-Z0-9\-]{4,}$/i', $identifiant) !== 1) {
            Session::flash('erreur', 'Identifiant de mesure inattendu. Il ressemble à '
                . '« G-XXXXXXXXXX » pour Google Analytics 4.');
            return $this->rediriger();
        }

        $actuel = $this->parametres->tout();
        $actuel['mesure'] = ['identifiant' => $identifiant];

        try {
            $this->parametres->enregistrer($actuel);
            Session::flash('succes', $identifiant === ''
                ? 'Mesure d’audience désactivée : plus aucun script de suivi n’est chargé.'
                : 'Mesure d’audience enregistrée. Elle ne se chargera que pour les '
                  . 'visiteurs qui l’auront acceptée dans le bandeau cookies.');
        } catch (RuntimeException $e) {
            Session::flash('erreur', $e->getMessage());
        }

        return $this->rediriger();
    }

    // ---------------------------------------------------- protection des formulaires

    /**
     * Clés Cloudflare Turnstile.
     *
     * Le secret de signature qui vit dans la même section n'est pas touché :
     * il est créé par le module et n'a rien à faire dans un écran de saisie.
     */
    public function antispamEnvoi(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger();
        }

        $site    = trim((string) ($_POST['antispam_cle_site'] ?? ''));
        $secrete = trim((string) ($_POST['antispam_cle_secrete'] ?? ''));

        // une seule des deux clés ne sert à rien : le widget s'afficherait
        // sans que sa réponse puisse être vérifiée, ou l'inverse
        if (($site === '') !== ($secrete === '')) {
            Session::flash('erreur', 'Turnstile demande ses deux clés : celle du site '
                . 'et celle du serveur. Videz les deux pour l’éteindre.');
            return $this->rediriger();
        }

        $actuel = $this->parametres->tout();
        $actuel['antispam']['cle_site']    = $site;
        $actuel['antispam']['cle_secrete'] = $secrete;

        try {
            $this->parametres->enregistrer($actuel);
            Session::flash('succes', $site === ''
                ? 'Turnstile éteint. Les formulaires restent protégés par le piège, '
                  . 'l’horloge et le quota horaire.'
                : 'Turnstile activé sur les deux formulaires du site.');
        } catch (RuntimeException $e) {
            Session::flash('erreur', $e->getMessage());
        }

        return $this->rediriger();
    }

    // ------------------------------------------------------------ envoi mails

    public function messagerieEnvoi(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger();
        }

        $actuel = $this->parametres->tout();
        $securite = (string) ($_POST['securite'] ?? 'tls');

        $motDePasse = (string) ($_POST['mot_de_passe'] ?? '');
        // champ laissé vide = on conserve le mot de passe déjà enregistré
        if ($motDePasse === '') {
            $motDePasse = (string) $actuel['smtp']['mot_de_passe'];
        }

        $actuel['smtp'] = [
            'actif'          => isset($_POST['actif']),
            'hote'           => trim((string) ($_POST['hote'] ?? '')),
            'port'           => max(1, min(65535, (int) ($_POST['port'] ?? 587))),
            'securite'       => in_array($securite, ['tls', 'ssl', 'aucune'], true) ? $securite : 'tls',
            'identifiant'    => trim((string) ($_POST['identifiant'] ?? '')),
            'mot_de_passe'   => $motDePasse,
            'expediteur'     => trim((string) ($_POST['expediteur'] ?? '')),
            'nom_expediteur' => trim((string) ($_POST['nom_expediteur'] ?? '')) ?: 'Mairie de Lachapelle-sous-Chaux',
        ];
        $actuel['contact'] = [
            'destinataire' => trim((string) ($_POST['destinataire'] ?? '')),
            'copie'        => trim((string) ($_POST['copie'] ?? '')),
        ];

        try {
            $this->parametres->enregistrer($actuel);
            Session::flash('succes', 'Paramètres d’envoi enregistrés.');
        } catch (RuntimeException $e) {
            Session::flash('erreur', $e->getMessage());
        }

        return $this->rediriger();
    }

    /**
     * Envoie un message de test au destinataire des demandes et conserve la
     * trace du dialogue SMTP pour comprendre un échec.
     */
    public function test(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger();
        }

        $destinataire = trim((string) ($_POST['destinataire_test'] ?? ''))
            ?: $this->destinataireEffectif();

        try {
            $this->mailer->envoyer(
                $destinataire,
                'Test d’envoi — site de la commune',
                "Bonjour,\n\n"
                . "Ce message confirme que l’envoi des e-mails du site fonctionne\n"
                . "correctement. Les demandes envoyées depuis le formulaire de contact\n"
                . "vous parviendront donc bien.\n\n"
                . 'Message envoyé le ' . date('d/m/Y') . ' à ' . date('H\hi')
                . ' depuis ' . ($_SERVER['HTTP_HOST'] ?? 'le serveur') . ".\n"
            );
            Session::flash('succes', 'E-mail de test envoyé à ' . $destinataire . '. Vérifiez la boîte de réception (et les indésirables).');
        } catch (Throwable $e) {
            Session::flash('erreur', 'Échec de l’envoi : ' . $e->getMessage());
        }

        $journal = $this->mailer->journal();
        if ($journal !== []) {
            Session::flash('trace_smtp', implode("\n", $journal));
        }

        return $this->rediriger();
    }

    // -------------------------------------------------------------- le compte

    public function compteEnvoi(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger();
        }

        $actuelMdp    = (string) ($_POST['mot_de_passe_actuel'] ?? '');
        $identifiant  = trim((string) ($_POST['identifiant'] ?? ''));
        $nouveau      = (string) ($_POST['nouveau_mot_de_passe'] ?? '');
        $confirmation = (string) ($_POST['confirmation'] ?? '');

        if (!$this->auth->verifier($this->auth->identifiant(), $actuelMdp)) {
            Session::flash('erreur', 'Mot de passe actuel incorrect.');
            return $this->rediriger();
        }
        if (mb_strlen($identifiant) < 3) {
            Session::flash('erreur', 'L’identifiant doit contenir au moins 3 caractères.');
            return $this->rediriger();
        }
        if ($nouveau !== '' && mb_strlen($nouveau) < 12) {
            Session::flash('erreur', 'Le nouveau mot de passe doit contenir au moins 12 caractères.');
            return $this->rediriger();
        }
        if ($nouveau !== $confirmation) {
            Session::flash('erreur', 'La confirmation ne correspond pas au nouveau mot de passe.');
            return $this->rediriger();
        }

        try {
            $this->auth->modifierCompte($identifiant, $nouveau);
            Session::flash('succes', $nouveau !== ''
                ? 'Identifiant et mot de passe mis à jour.'
                : 'Identifiant mis à jour.');
        } catch (Throwable $e) {
            Session::flash('erreur', $e->getMessage());
        }

        return $this->rediriger();
    }

    /**
     * Remet dossiers et fichiers aux droits attendus : 0755, 0644, et 0640
     * pour ce qui contient un secret.
     */
    public function droitsEnvoi(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger();
        }

        $bilan = $this->permissions->reparer();
        $total = $bilan['dossiers'] + $bilan['fichiers'] + $bilan['secrets'];

        if ($bilan['echecs'] > 0) {
            Session::flash('erreur', $total . ' droit(s) corrigés, mais ' . $bilan['echecs']
                . ' refus — ces fichiers appartiennent probablement à un autre compte.');
        } elseif ($total === 0) {
            Session::flash('succes', 'Les droits étaient déjà corrects, rien à changer.');
        } else {
            Session::flash('succes', $total . ' droit(s) corrigés ('
                . $bilan['dossiers'] . ' dossiers, ' . $bilan['fichiers'] . ' fichiers, '
                . $bilan['secrets'] . ' fichiers sensibles).');
        }

        return $this->rediriger();
    }

    // ------------------------------------------------------------ diagnostic

    /**
     * Contrôles utiles au moment de l'installation sur l'hébergement.
     *
     * @return array<int, array{libelle:string, valeur:string, ok:bool|null}>
     */
    private function diagnostic(): array
    {
        $controles = [];

        $phpOk = PHP_VERSION_ID >= 80100;
        $controles[] = ['libelle' => 'Version de PHP', 'valeur' => PHP_VERSION, 'ok' => $phpOk];

        foreach (['gd' => 'Traitement des images', 'fileinfo' => 'Contrôle des fichiers envoyés',
                  'mbstring' => 'Textes accentués', 'openssl' => 'SMTP chiffré'] as $ext => $role) {
            $controles[] = [
                'libelle' => 'Extension ' . $ext . ' — ' . $role,
                'valeur'  => extension_loaded($ext) ? 'présente' : 'absente',
                'ok'      => extension_loaded($ext),
            ];
        }

        $dossiers = [
            'Contenu'               => $this->racine . '/data',
            'Compte et paramètres'  => $this->racine . '/data/admin',
            'Sauvegardes'           => $this->racine . '/storage',
            'Photos'                => $this->racineWeb . '/assets/img/site',
        ];
        foreach ($dossiers as $role => $chemin) {
            $inscriptible = is_dir($chemin) && is_writable($chemin);
            $controles[] = [
                'libelle' => $role . ' — écriture dans ' . $this->relatif($chemin),
                'valeur'  => !is_dir($chemin) ? 'dossier absent' : ($inscriptible ? 'autorisée' : 'refusée'),
                'ok'      => $inscriptible,
            ];
        }

        // data/ sous la racine web = contenu, compte et mot de passe SMTP
        // atteignables par URL si Apache ne bloque pas
        $data = realpath($this->racine . '/data');
        $web  = realpath($this->racineWeb);
        $exposee = $data !== false && $web !== false
            && ($data === $web || str_starts_with($data, $web . DIRECTORY_SEPARATOR));
        $controles[] = [
            'libelle' => 'Dossier data hors de la racine web',
            'valeur'  => $exposee
                ? 'exposé — vérifiez que /data/site.json renvoie une erreur 403'
                : 'protégé',
            'ok'      => !$exposee,
        ];

        // 8 Mo : en dessous, les photos d'appareil passent mal
        foreach (['upload_max_filesize' => 'Taille maximale d’une photo envoyée',
                  'post_max_size' => 'Taille maximale d’un formulaire'] as $cle => $role) {
            $brut = ini_get($cle) ?: '';
            $controles[] = [
                'libelle' => $role . ' (' . $cle . ')',
                'valeur'  => $brut !== '' ? $brut : 'inconnue',
                'ok'      => $brut !== '' ? $this->enOctets($brut) >= 8 * 1024 * 1024 : null,
            ];
        }
        $droits = $this->permissions->analyser(1);
        $controles[] = [
            'libelle' => 'Droits d’accès des fichiers et dossiers',
            'valeur'  => $droits['anomalies'] === []
                ? 'conformes'
                : 'anomalie détectée — voir ci-dessous',
            'ok'      => $droits['anomalies'] === [],
        ];

        $controles[] = [
            'libelle' => 'Envoi par SMTP',
            'valeur'  => $this->parametres->smtpConfigure() ? 'configuré' : 'non configuré — mail() de PHP utilisé',
            'ok'      => $this->parametres->smtpConfigure() ? true : null,
        ];

        return $controles;
    }

    /**
     * Chemin affiché relativement à la racine du projet.
     */
    private function relatif(string $chemin): string
    {
        $racine = realpath($this->racine);
        $reel   = realpath($chemin) ?: $chemin;
        return $racine !== false && str_starts_with($reel, $racine)
            ? '/' . ltrim(substr($reel, strlen($racine)), '/')
            : $reel;
    }

    /**
     * Convertit une valeur php.ini (« 2M », « 512K », « 1G ») en octets.
     */
    private function enOctets(string $valeur): int
    {
        $valeur = trim($valeur);
        $nombre = (int) $valeur;
        return match (strtolower(substr($valeur, -1))) {
            'g'     => $nombre * 1024 ** 3,
            'm'     => $nombre * 1024 ** 2,
            'k'     => $nombre * 1024,
            default => $nombre,
        };
    }
}
