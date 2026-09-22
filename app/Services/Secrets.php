<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Storage\Audit;
use App\Storage\Json;

/**
 * Clés d'API et identifiants, pilotés depuis le back-office.
 *
 * Deux sources, dans cet ordre :
 *   1. data/private/secrets.json — écrit depuis l'interface d'administration ;
 *   2. config/secrets.php        — fichier posé à la main sur le serveur.
 *
 * La première l'emporte, ce qui permet de démarrer avec un fichier et de
 * basculer ensuite sur l'interface sans rien casser. Le stockage est hors
 * racine web, en 0600, et une valeur n'est jamais renvoyée en clair au
 * navigateur : l'interface n'affiche qu'une empreinte partielle.
 */
final class Secrets
{
    /**
     * Catalogue des réglages. Chaque entrée porte de quoi construire le
     * formulaire, expliquer à quoi sert la clé et où aller la chercher.
     */
    public const CATALOG = [
        'regie' => [
            'label' => 'Assistant Régie',
            'intro' => 'Sans clé, l’assistant répond quand même en citant la base de connaissance '
                     . 'du site, mais sans reformulation ni compréhension fine des questions.',
            'doc'   => ['Obtenir une clé Gemini', 'https://aistudio.google.com/apikey'],
            'keys'  => [
                'gemini_api_key' => [
                    'label' => 'Clé d’API Gemini',
                    'help'  => 'Google AI Studio → « Get API key ». Gratuit jusqu’à un quota '
                             . 'généreux. La clé reste sur le serveur, jamais exposée au visiteur.',
                    'placeholder' => 'AIza…',
                ],
            ],
        ],

        'translate' => [
            'label' => 'Traduction automatique',
            'intro' => 'Traduit les pages du français vers les six autres langues, côté serveur, '
                     . 'avec mise en cache. Sans clé, les pages non traduites sont servies en '
                     . 'français sous un bandeau qui le signale.',
            'doc'   => ['Console Google Cloud Translation', 'https://console.cloud.google.com/apis/library/translate.googleapis.com'],
            'keys'  => [
                'translate_api_key' => [
                    'label' => 'Clé d’API Cloud Translation',
                    'help'  => 'Activez l’API « Cloud Translation » dans un projet Google Cloud, '
                             . 'puis créez une clé sous « Identifiants ». Service payant à l’usage : '
                             . 'pensez à restreindre la clé à cette seule API.',
                    'placeholder' => 'AIza…',
                ],
            ],
        ],

        'reviews' => [
            'label' => 'Avis Google',
            'intro' => 'Alimente le bloc d’avis du pied de page, avec un cache de 12 heures. '
                     . 'Sans clé, le bloc n’est pas affiché — aucune note n’est inventée.',
            'doc'   => ['Console Google Places API', 'https://console.cloud.google.com/apis/library/places-backend.googleapis.com'],
            'keys'  => [
                'places_api_key' => [
                    'label' => 'Clé d’API Places',
                    'help'  => 'Activez « Places API (New) » dans Google Cloud, puis créez une clé.',
                    'placeholder' => 'AIza…',
                ],
                'places_id' => [
                    'label' => 'Identifiant de la fiche Google',
                    'help'  => 'L’identifiant de votre établissement, du type ChIJ… '
                             . 'Trouvable avec l’outil « Place ID Finder » de Google.',
                    'placeholder' => 'ChIJ…',
                    'public' => true,   // ce n'est pas un secret : affichable en clair
                ],
            ],
        ],

        'adsense' => [
            'label' => 'Publicité AdSense',
            'intro' => 'Les sept emplacements du site. Une seule unité « Display » suffit : '
                     . 'renseignez l’unité par défaut, les emplacements laissés vides la '
                     . 'reprennent. Sans aucune unité, ils affichent le cadre en pointillés de '
                     . 'la maquette. Les scripts ne sont chargés qu’après consentement du visiteur.',
            'doc'   => ['Console AdSense', 'https://www.google.com/adsense/'],
            'keys'  => [
                'adsense_client' => [
                    'label' => 'Identifiant éditeur',
                    'help'  => 'AdSense → Compte → Informations sur le compte. Commence par ca-pub-.',
                    'placeholder' => 'ca-pub-0000000000000000',
                    'public' => true,
                ],
                'adsense_default_slot' => [
                    'label' => 'Unité par défaut (display)',
                    'help'  => 'Identifiant utilisé par tout emplacement laissé vide ci-dessous. '
                             . 'Une seule unité « Display » responsive suffit donc à couvrir les '
                             . 'sept emplacements. Vide à son tour, l’emplacement affiche le cadre '
                             . 'de la maquette.',
                    'doc'   => ['Créer une unité Display', 'https://support.google.com/adsense/answer/9183549'],
                    'placeholder' => '0000000000',
                    'public' => true,
                ],
                'adsense_infeed_layout' => [
                    'label' => 'Clé de mise en page in-feed',
                    'help'  => 'Uniquement si l’emplacement « In-feed liste » reçoit une unité de '
                             . 'type In-feed : AdSense fournit alors un data-ad-layout-key à recopier '
                             . 'ici. Laissé vide, cet emplacement se comporte comme un bloc display '
                             . 'classique, ce qui convient à une unité « Display ».',
                    'doc'   => ['Créer une unité In-feed', 'https://support.google.com/adsense/answer/9183363'],
                    'placeholder' => '-fb+5w+4e-db+86',
                    'public' => true,
                ],
            ],
            'slots' => true,   // les sept identifiants d'emplacement, générés
        ],

        'sources' => [
            'label' => 'Offres externes',
            'intro' => 'Complètent les annonces déposées sur le site. Chaque source s’active '
                     . 'dès que ses identifiants sont renseignés.',
            'keys'  => [
                'francetravail_client_id' => [
                    'label' => 'France Travail — identifiant client',
                    'help'  => 'Gratuit et en libre-service : créez une application sur '
                             . 'francetravail.io et souscrivez à l’API « Offres d’emploi v2 ». '
                             . 'C’est la source la plus pertinente ici : elle couvre nativement '
                             . 'le domaine « spectacle » du référentiel ROME.',
                    'doc'   => ['Créer une application France Travail', 'https://francetravail.io/data/api/offres-emploi'],
                    'public' => true,
                ],
                'francetravail_client_secret' => [
                    'label' => 'France Travail — clé secrète',
                    'help'  => 'Fournie avec l’identifiant client, sur la même page.',
                ],
                'indeed_feed_url' => [
                    'label' => 'Indeed — adresse du flux partenaire',
                    'help'  => 'L’ancienne API publisher d’Indeed est fermée depuis leur passage '
                             . 'en accès partenaire. Si vous obtenez un flux XML ou JSON dans ce '
                             . 'cadre, collez son adresse ici. Le scraping de leurs pages est '
                             . 'contraire à leurs conditions et n’est pas proposé.',
                    'doc'   => ['Programme partenaire Indeed', 'https://www.indeed.com/publisher'],
                    'placeholder' => 'https://…',
                    'public' => true,
                ],
                'indeed_publisher_id' => [
                    'label' => 'Indeed — ancien identifiant publisher',
                    'help'  => 'Repris de votre installation WordPress. Conservé au cas où un '
                             . 'accès historique fonctionnerait encore ; ne renvoie probablement '
                             . 'plus rien.',
                    'public' => true,
                ],
                'adzuna_app_id' => [
                    'label' => 'Adzuna — identifiant d’application',
                    'help'  => 'Palier gratuit en libre-service. Bon repli généraliste quand '
                             . 'aucun accès Indeed n’est disponible.',
                    'doc'   => ['Créer un compte développeur Adzuna', 'https://developer.adzuna.com/'],
                    'public' => true,
                ],
                'adzuna_app_key' => [
                    'label' => 'Adzuna — clé d’application',
                    'help'  => 'Fournie avec l’identifiant, sur le tableau de bord développeur.',
                ],
                'jooble_key' => [
                    'label' => 'Jooble — clé d’API',
                    'help'  => 'Clé obtenue en libre-service. Complément utile : son catalogue '
                             . 'français reprend des annonces absentes ailleurs.',
                    'doc'   => ['Demander une clé Jooble', 'https://jooble.org/api/about'],
                ],
            ],
        ],

        'mail' => [
            // Rendu par l'écran « Alertes & e-mails », pas par « Clés d'API ».
            'screen' => 'alerts',
            'label' => 'Envoi des e-mails',
            'intro' => 'Deux transports possibles. Sans serveur SMTP renseigné, le site utilise '
                     . 'la fonction mail() de PHP, qui suffit tant que l’hébergement porte un '
                     . 'MTA. Renseigner un serveur SMTP authentifié améliore nettement la '
                     . 'délivrabilité : les alertes cessent de partir en indésirable.',
            'keys'  => [
                'alert_email' => [
                    'label' => 'Adresse qui reçoit les alertes',
                    'help'  => 'Toute l’activité du site y est envoyée : dépôts, modération, '
                             . 'candidatures, messages aux candidats, signalements, incidents. '
                             . 'Plusieurs adresses possibles, séparées par des virgules. '
                             . 'Vide : les alertes partent à l’adresse de contact du site.',
                    'placeholder' => 'vous@votre-domaine.fr',
                    'public' => true,
                ],
                'mail_from' => [
                    'label' => 'Adresse d’expédition',
                    'help'  => 'Doit appartenir au domaine du site, sans quoi les messages '
                             . 'partiront en indésirable.',
                    'placeholder' => 'no-reply@intermittent.fr',
                    'public' => true,
                ],
                'smtp_host' => [
                    'label' => 'Serveur SMTP',
                    'help'  => 'Laissez vide pour utiliser mail(). Chez o2switch : '
                             . 'mail.votre-domaine.fr, ou le serveur de votre service d’envoi.',
                    'placeholder' => 'mail.intermittent.fr',
                    'public' => true,
                ],
                'smtp_port' => [
                    'label' => 'Port',
                    'help'  => '587 avec STARTTLS (recommandé), 465 en SSL direct, 25 sans '
                             . 'chiffrement. Vide : 587.',
                    'placeholder' => '587',
                    'public' => true,
                ],
                'smtp_secure' => [
                    'label' => 'Chiffrement',
                    'help'  => '« tls » pour STARTTLS sur le port 587, « ssl » pour le port 465, '
                             . '« none » pour une liaison en clair — à éviter.',
                    'placeholder' => 'tls',
                    'public' => true,
                ],
                'smtp_user' => [
                    'label' => 'Identifiant SMTP',
                    'help'  => 'En général l’adresse e-mail complète du compte d’envoi.',
                    'placeholder' => 'no-reply@intermittent.fr',
                    'public' => true,
                ],
                'smtp_pass' => [
                    'label' => 'Mot de passe SMTP',
                    'help'  => 'Stocké hors racine web, en 0600, et jamais réaffiché. '
                             . 'Il n’apparaît dans aucun journal.',
                ],
            ],
        ],

        'app' => [
            'label' => 'Sécurité de l’application',
            'intro' => 'Sert à signer les jetons internes et à anonymiser les adresses IP dans '
                     . 'les compteurs de limitation de débit.',
            'keys'  => [
                'app_key' => [
                    'label' => 'Clé de signature',
                    'help'  => 'Chaîne aléatoire d’au moins 32 caractères. La changer invalide '
                             . 'les compteurs de limitation en cours, sans autre conséquence.',
                    'generate' => true,
                ],
            ],
        ],
    ];

    private static ?array $store = null;

    private static function path(): string
    {
        return Config::path('data') . '/private/secrets.json';
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        if (self::$store === null) {
            self::$store = Json::read(self::path());
        }
        return self::$store;
    }

    public static function get(string $key, mixed $default = ''): mixed
    {
        $value = self::all()[$key] ?? null;
        if (is_array($value)) {
            return $value;
        }
        return is_string($value) && trim($value) !== '' ? $value : $default;
    }

    public static function has(string $key): bool
    {
        $value = self::all()[$key] ?? '';
        return is_array($value) ? $value !== [] : trim((string) $value) !== '';
    }

    /**
     * Enregistre les valeurs fournies. Une valeur vide laisse l'existant en
     * place : le formulaire renvoie des champs vides pour ne pas réafficher
     * les secrets, une saisie vide ne doit donc pas effacer.
     *
     * @param array<string, string|array> $values
     * @param string[]                    $clear  clés à effacer explicitement
     */
    /**
     * Un champ vide efface ou conserve, selon ce que le formulaire réaffiche.
     *
     * Une clé masquée n'est jamais réaffichée : son champ est vide à chaque
     * ouverture de l'écran, et un simple enregistrement effacerait tout. Elle
     * ne peut donc s'effacer que par la case « Effacer ».
     *
     * Une valeur affichée en clair — identifiant éditeur, identifiants
     * d'emplacement AdSense — est au contraire pré-remplie : le champ porte
     * l'état voulu, et le vider l'efface.
     */
    public static function save(array $values, array $clear = [], ?int $userId = null): bool
    {
        $store = self::all();
        $meta = self::flatten();
        $changed = [];

        foreach ($values as $key => $value) {
            // Les listes sont toujours réaffichées en entier : le formulaire
            // renvoie l'état voulu, champs vides compris, et remplace.
            if (is_array($value)) {
                $sub = [];
                foreach ($value as $name => $item) {
                    $item = trim((string) $item);
                    if ($item !== '') {
                        $sub[(string) $name] = $item;
                    }
                }
                if ($sub === (array) ($store[$key] ?? [])) {
                    continue;
                }
                if ($sub === []) {
                    unset($store[$key]);
                    $changed[] = $key . ' (effacée)';
                } else {
                    $store[$key] = $sub;
                    $changed[] = $key;
                }
                continue;
            }

            $value = trim((string) $value);
            $current = (string) ($store[$key] ?? '');

            if ($value === '' && empty($meta[$key]['public'])) {
                continue;
            }
            if ($value === $current) {
                continue;
            }
            if ($value === '') {
                unset($store[$key]);
                $changed[] = $key . ' (effacée)';
            } else {
                $store[$key] = $value;
                $changed[] = $key;
            }
        }

        foreach ($clear as $key) {
            if (isset($store[$key])) {
                unset($store[$key]);
                $changed[] = $key . ' (effacée)';
            }
        }

        if ($changed === []) {
            return true;
        }

        $ok = Json::write(self::path(), $store);
        if ($ok) {
            @chmod(self::path(), 0600);
            self::$store = $store;
            // Le journal retient quelles clés ont bougé, jamais leur valeur.
            Audit::log('secrets.updated', ['keys' => $changed], $userId);
        }
        return $ok;
    }

    /** Empreinte affichable : « AIza••••••••4f2a ». Jamais la valeur entière. */
    public static function mask(string $key): string
    {
        $value = (string) self::get($key, '');
        if ($value === '') {
            return '';
        }
        $length = mb_strlen($value);
        if ($length <= 8) {
            return str_repeat('•', $length);
        }
        return mb_substr($value, 0, 4) . str_repeat('•', min(10, $length - 8)) . mb_substr($value, -4);
    }

    /** Une clé non secrète (identifiant public) s'affiche en clair. */
    public static function display(string $key, bool $public): string
    {
        return $public ? (string) self::get($key, '') : self::mask($key);
    }

    public static function generateKey(): string
    {
        return bin2hex(random_bytes(24));
    }

    /**
     * Clé interne de l'installation, utilisée pour hacher les adresses IP des
     * limiteurs de débit et du journal. Elle est créée au premier appel :
     * aucune installation ne tourne plus avec une constante publique, qui
     * rendrait les empreintes d'IP réversibles par simple énumération.
     */
    public static function appKey(): string
    {
        static $key = null;
        if ($key !== null) {
            return $key;
        }

        $stored = (string) self::get('app_key', '');
        if ($stored !== '') {
            return $key = $stored;
        }

        // config/secrets.php peut la porter : on la reprend sans rien écrire.
        $fromFile = (string) Config::secret('app_key', '');
        if ($fromFile !== '') {
            return $key = $fromFile;
        }

        $fresh = self::generateKey();
        $store = self::all();
        $store['app_key'] = $fresh;
        if (Json::write(self::path(), $store)) {
            @chmod(self::path(), 0600);
            self::$store = $store;
            Audit::log('secrets.app_key_generated');
            return $key = $fresh;
        }

        // Stockage en lecture seule : on ne peut pas persister, mais on ne
        // retombe pas sur une constante connue de tous.
        Audit::log('secrets.app_key_unwritable');
        return $key = hash('sha256', __DIR__ . '|' . (string) Config::get('site.url'));
    }

    /** Toutes les clés du catalogue, aplaties. @return array<string, array> */
    public static function flatten(): array
    {
        $out = [];
        foreach (self::CATALOG as $group) {
            foreach ((array) ($group['keys'] ?? []) as $key => $meta) {
                $out[$key] = $meta;
            }
        }
        return $out;
    }
}
