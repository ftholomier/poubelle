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
            'intro' => 'Les sept emplacements du site. Sans identifiant, ils affichent le cadre '
                     . 'en pointillés de la maquette. Les scripts ne sont chargés qu’après '
                     . 'consentement du visiteur.',
            'doc'   => ['Console AdSense', 'https://www.google.com/adsense/'],
            'keys'  => [
                'adsense_client' => [
                    'label' => 'Identifiant éditeur',
                    'help'  => 'AdSense → Compte → Informations sur le compte. Commence par ca-pub-.',
                    'placeholder' => 'ca-pub-0000000000000000',
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
            'label' => 'Envoi d’e-mails',
            'intro' => 'Utilisé pour les liens de récupération de mot de passe. Si le serveur '
                     . 'n’a pas de MTA, les messages sont archivés dans data/logs/mail/ '
                     . 'plutôt que perdus.',
            'keys'  => [
                'mail_from' => [
                    'label' => 'Adresse d’expédition',
                    'help'  => 'Doit appartenir au domaine du site, sans quoi les messages '
                             . 'partiront en indésirable.',
                    'placeholder' => 'no-reply@intermittent.fr',
                    'public' => true,
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
    public static function save(array $values, array $clear = [], ?int $userId = null): bool
    {
        $store = self::all();
        $changed = [];

        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $sub = array_filter(array_map('trim', array_map('strval', $value)), 'strlen');
                if ($sub !== []) {
                    $store[$key] = array_replace((array) ($store[$key] ?? []), $sub);
                    $changed[] = $key;
                }
                continue;
            }
            $value = trim((string) $value);
            if ($value !== '' && $value !== (string) ($store[$key] ?? '')) {
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
