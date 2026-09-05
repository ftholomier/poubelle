<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Réglages techniques du site (SMTP, destinataire des demandes).
 *
 * Stockés dans data/admin/parametres.json : hors racine web, hors git, et
 * jamais exposés par l'éditeur avancé — ce fichier contient des mots de
 * passe.
 */
final class Parametres
{
    private const DEFAUTS = [
        'smtp' => [
            'actif'        => false,
            'hote'         => '',
            'port'         => 587,
            'securite'     => 'tls',      // tls | ssl | aucune
            'identifiant'  => '',
            'mot_de_passe' => '',
            'expediteur'   => '',
            // vide = le nom de la collectivité, voir nom_du_site()
            'nom_expediteur' => '',
        ],
        'contact' => [
            'destinataire' => '',
            'copie'        => '',
        ],
        // protection des formulaires : les barrières natives tournent sans
        // réglage. Les deux clés n'allument que l'étage Turnstile, et le
        // secret sert à signer les jetons — d'où sa place parmi les secrets.
        'antispam' => [
            'cle_site'    => '',
            'cle_secrete' => '',
            'secret'      => '',
        ],
        // mesure d'audience : chargée seulement après accord du visiteur
        'mesure' => [
            'identifiant' => '',          // ex. G-XXXXXXXXXX (Google Analytics 4)
        ],
        // disposition du menu : réglage de présentation, hors git, pour
        // qu'une mise à jour du code ne le remette pas à sa valeur d'origine
        'apparence' => [
            // Six rubriques tiennent dans une barre : la navigation reste
            // visible, ce qu'un burger sur grand écran ne donne pas. Le
            // panneau latéral reprend la main sous 1080 px.
            'menu' => 'horizontal',       // lateral | horizontal
            // Hauteur du logo dans la barre, en pixels, sur grand écran.
            // Voir le jeton --logo-ref en tête de site.css.
            'logo' => 52,
            // Ce que fait la barre quand le logo grandit : false, elle suit
            // et s'épaissit ; true, elle garde sa hauteur et le logo la
            // dépasse par le bas.
            'logo_deborde' => false,
        ],
        // assistant de discussion : la clé Gemini est un secret, au même
        // titre que le mot de passe SMTP — d'où sa place dans ce fichier
        'assistant' => [
            'actif'       => false,
            'cle'         => '',
            'modele'      => '',          // vide = modèle par défaut du socle
            'titre'       => '',
            'accueil'     => '',
            'source_site' => true,        // le contenu du site fait partie du corpus
        ],
        // traduction automatique : sans clé, on se rabat sur des services
        // gratuits que beaucoup d'hébergements mutualisés voient refusés
        // (HTTP 429), leur adresse IP étant partagée avec d'autres sites
        'traduction' => [
            'cle_deepl' => '',
            // Voir App\Core\TraductionAuto : le repli gratuit envoie le texte
            // des pages chez Google puis MyMemory, et cela se décide.
            'gratuits'  => false,
            // l'offre gratuite de DeepL n'accorde ce million qu'une fois, pour
            // la vie du compte : sans compteur, on ne sait qu'on l'a épuisé
            // qu'au moment où le service refuse
            'deepl_caracteres' => 0,
        ],
        // avis Google : la clé d'API est un secret, au même titre que le
        // mot de passe SMTP — d'où sa place dans ce fichier
        'avis' => [
            'actif'     => false,
            'cle_api'   => '',
            'place_id'  => '',
            'note_mini' => 4,             // les avis en dessous ne sont pas affichés
            'pause'     => 6,             // secondes entre deux avis ; 0 = défilement à l'arrêt
            'dates'     => true,          // afficher la date de parution de chaque avis
            'total'     => true,          // afficher « sur N avis » à côté de la note
        ],
        /* Réseaux sociaux. Toute section doit être déclarée ICI, et pas
           seulement écrite : `tout()` part de ce tableau et n'y fusionne que
           les sections qu'il connaît. Une section absente est écrite dans le
           fichier puis ignorée à la relecture — sans erreur, sans alerte. Le
           réglage paraît alors ne pas s'enregistrer, et l'on cherche du côté
           des droits d'écriture pendant une heure.

           Le secret de l'application et le jeton de la Page sont des secrets
           au même titre que le mot de passe SMTP : ils n'ont leur place ici
           que parce que ce fichier est hors racine web et hors git. */
        'reseaux' => [
            'application'   => '',        // identifiant de l'application Meta
            'secret'        => '',        // clé secrète de l'application
            'jeton_page'    => '',        // jeton de la Page — n'expire pas
            'page_id'       => '',
            'page_nom'      => '',
            'instagram_id'  => '',
            'instagram_nom' => '',
            'connecte_le'   => '',
            'cle_tache'     => '',        // autorise l'adresse de dépilage
        ],
    ];

    /** @var array<string, mixed>|null */
    private ?array $cache = null;

    public function __construct(private readonly string $fichier)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function tout(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $donnees = [];
        if (is_file($this->fichier)) {
            $brut = json_decode((string) file_get_contents($this->fichier), true);
            if (is_array($brut)) {
                $donnees = $brut;
            }
        }

        // fusion profonde avec les valeurs par défaut
        $resultat = self::DEFAUTS;
        foreach ($donnees as $section => $valeurs) {
            if (isset($resultat[$section]) && is_array($valeurs)) {
                $resultat[$section] = array_merge($resultat[$section], $valeurs);
            }
        }

        return $this->cache = $resultat;
    }

    public function get(string $chemin, mixed $defaut = null): mixed
    {
        $valeur = $this->tout();
        foreach (explode('.', $chemin) as $segment) {
            if (!is_array($valeur) || !array_key_exists($segment, $valeur)) {
                return $defaut;
            }
            $valeur = $valeur[$segment];
        }
        return $valeur;
    }

    /**
     * @param array<string, mixed> $donnees
     */
    /**
     * Le fichier de réglages peut-il être écrit ?
     *
     * Sur un hébergement dont data/ est en lecture seule, le secret de
     * signature anti-spam ne peut pas être conservé : Antispam se rabat alors
     * sur un secret dérivé, plus faible, et l'écrit dans le journal — que
     * personne ne lit. Le tableau de bord pose la question ici.
     */
    public function inscriptible(): bool
    {
        return is_file($this->fichier)
            ? is_writable($this->fichier)
            : is_dir(dirname($this->fichier)) && is_writable(dirname($this->fichier));
    }

    public function enregistrer(array $donnees): void
    {
        $dossier = dirname($this->fichier);
        if (!is_dir($dossier)) {
            $ancien = umask(0);
            @mkdir($dossier, Permissions::DOSSIER, true);
            umask($ancien);
            if (!is_dir($dossier)) {
                throw new RuntimeException('Impossible de créer ' . $dossier);
            }
        }

        $json = json_encode(
            $donnees,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        $tmp = $this->fichier . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (file_put_contents($tmp, $json . "\n", LOCK_EX) === false || !rename($tmp, $this->fichier)) {
            @unlink($tmp);
            throw new RuntimeException('Écriture impossible : parametres.json');
        }
        @chmod($this->fichier, Permissions::SECRET);

        $this->cache = null;
    }

    /**
     * L'adresse expéditrice n'est pas exigée : le Mailer en déduit une du
     * domaine servant le site. L'omettre ne doit pas désactiver le SMTP
     * alors que le serveur et les identifiants sont renseignés.
     */
    public function smtpConfigure(): bool
    {
        return (bool) $this->get('smtp.actif')
            && $this->get('smtp.hote') !== '';
    }
}
