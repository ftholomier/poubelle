<?php
declare(strict_types=1);

namespace App;

/**
 * Consentement aux cookies et aux traitements tiers.
 *
 * Quatre catégories seulement, parce qu'il n'y en a pas davantage sur ce site :
 *  - necessary : session anti-spam des formulaires, langue choisie, fermeture
 *                des fenêtres. Toujours actives, sans consentement (art. 82 LIL).
 *  - analytics : mesure d'audience (Plausible ou Matomo), chargée uniquement si acceptée.
 *  - ai        : l'assistant envoie la question posée à Google (API Gemini).
 *                Refusé, il répond uniquement à partir de l'index local du site.
 *  - maps      : plans Google Maps. Acceptés, ils s'affichent directement, sans
 *                clic supplémentaire ; refusés, un aperçu propose de les charger.
 */
final class Consent
{
    public const COOKIE = 'ioio_consent';
    public const VERSION = 1;
    public const CATEGORIES = ['analytics', 'ai', 'maps'];
    private const TTL = 33_696_000; // 13 mois, durée maximale recommandée par la CNIL

    private static ?array $cache = null;

    /** @return array{decided:bool,analytics:bool,ai:bool,maps:bool,at:string} */
    public static function state(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $raw = (string) ($_COOKIE[self::COOKIE] ?? '');
        $data = $raw === '' ? null : json_decode($raw, true);

        if (!\is_array($data) || (int) ($data['v'] ?? 0) !== self::VERSION) {
            return self::$cache = ['decided' => false, 'analytics' => false, 'ai' => false, 'maps' => false, 'at' => ''];
        }
        return self::$cache = [
            'decided' => true,
            'analytics' => ($data['analytics'] ?? false) === true,
            'ai' => ($data['ai'] ?? false) === true,
            'maps' => ($data['maps'] ?? false) === true,
            'at' => (string) ($data['at'] ?? ''),
        ];
    }

    public static function decided(): bool
    {
        return self::state()['decided'];
    }

    /** Une catégorie non décidée vaut refus : rien ne se déclenche par défaut. */
    public static function allows(string $category): bool
    {
        return (bool) (self::state()[$category] ?? false);
    }

    /** Écrit le choix du visiteur. */
    public static function store(array $choices): void
    {
        $value = ['v' => self::VERSION, 'at' => (new \DateTimeImmutable())->format(\DATE_ATOM)];
        foreach (self::CATEGORIES as $category) {
            $value[$category] = ($choices[$category] ?? false) === true;
        }
        self::$cache = null;

        if (!headers_sent()) {
            setcookie(self::COOKIE, (string) json_encode($value), [
                'expires' => time() + self::TTL,
                'path' => Config::basePath() . '/',
                'secure' => Config::isHttps(),
                'httponly' => false, // relu par le JS pour rejouer le choix sans recharger
                'samesite' => 'Lax',
            ]);
        }
        $_COOKIE[self::COOKIE] = (string) json_encode($value);
    }

    /** Détail des cookies déposés, affiché dans le panneau de paramétrage. */
    public static function inventory(): array
    {
        return [
            'necessary' => ['ioio_session', 'ioio_lang', 'ioio_consent', 'ioio_exit_seen'],
            'analytics' => ['plausible / matomo'],
            'ai' => ['—'],
            'maps' => ['google.com (NID)'],
        ];
    }
}
