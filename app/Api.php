<?php
declare(strict_types=1);

namespace App;

/** Socle commun des endpoints JSON : réponses, validation, garde-fous. */
final class Api
{
    public static function boot(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
        Session::start();
    }

    public static function respond(array $payload, int $status = 200): never
    {
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function fail(string $error, int $status = 400): never
    {
        self::respond(['ok' => false, 'error' => $error], $status);
    }

    public static function requireMethod(string $method): void
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== $method) {
            header('Allow: ' . $method);
            self::fail('Méthode non autorisée.', 405);
        }
    }

    /** Corps JSON d'une requête POST application/json. */
    public static function jsonBody(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);
        return \is_array($data) ? $data : [];
    }

    /**
     * Contrôles communs à tous les formulaires publics :
     * jeton CSRF, honeypot vide, délai minimal de 2 s, quota par IP.
     */
    public static function guard(array $input, string $form, int $max = 5, int $window = 600): void
    {
        if (!Csrf::check((string) ($input['csrf'] ?? ''), $form)) {
            self::fail(I18n::t('form.csrf'), 419);
        }
        if (trim((string) ($input['hp'] ?? '')) !== '') {
            Log::write('spam', 'Honeypot rempli sur ' . $form . ' depuis ' . RateLimit::ip());
            self::respond(['ok' => true]); // On ne renseigne pas les robots.
        }
        $ts = (int) ($input['ts'] ?? 0);
        if ($ts > 0 && time() - $ts < 2) {
            self::fail(I18n::t('form.tooFast'), 429);
        }
        if (!RateLimit::allow($form, $max, $window)) {
            self::fail(I18n::t('form.throttled'), 429);
        }
    }

    public static function str(array $input, string $key, int $max = 200): string
    {
        $value = $input[$key] ?? '';
        if (!\is_scalar($value)) {
            return '';
        }
        return trim(mb_substr((string) $value, 0, $max));
    }

    public static function email(array $input, string $key = 'email'): string
    {
        $value = self::str($input, $key, 160);
        return filter_var($value, FILTER_VALIDATE_EMAIL) ? mb_strtolower($value) : '';
    }

    /** Langue demandée par le formulaire, repli sur la détection habituelle. */
    public static function lang(array $input): string
    {
        $lang = self::str($input, 'lang', 5);
        $lang = \in_array($lang, Config::LANGS, true) ? $lang : Router::detectLang();
        I18n::setLang($lang);
        return $lang;
    }
}
