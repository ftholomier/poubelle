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
     * Contrôles communs à tous les formulaires publics : jeton CSRF, jeton
     * signé, pixel de présence, champs leurres, note de suspicion et quotas.
     *
     * Renvoie ce que l'appelant doit savoir pour la suite : un envoi mis en
     * quarantaine est enregistré mais n'est pas transmis par email.
     *
     * @return array{quarantine:bool,score:int,reasons:array<int,string>}
     */
    public static function guard(array $input, string $form): array
    {
        if (!Csrf::check((string) ($input['csrf'] ?? ''), $form)) {
            self::fail(I18n::t('form.csrf'), 419);
        }
        if (!Spam::enabled()) {
            return ['quarantine' => false, 'score' => 0, 'reasons' => []];
        }

        // Récidiviste déjà bloqué : on n'explique rien, on ne garde rien.
        if (Spam::blocked()) {
            Log::write('spam', 'Envoi refusé (IP bloquée) sur ' . $form . '.');
            self::respond(['ok' => true]);
        }

        $judgement = Spam::score($input, $form);
        $verdict = Spam::verdict($judgement['score']);

        // Note intermédiaire : une question suffit à prouver qu'on est humain.
        // Ce tour-là ne consomme pas de quota — rien n'a encore été envoyé, et
        // le visiteur ne doit pas être bloqué pour avoir simplement répondu.
        if ($verdict === 'challenge') {
            $answer = trim((string) ($input['challenge'] ?? ''));
            if ($answer === '' || !Spam::checkChallenge($answer)) {
                Log::write('spam', 'Question posée sur ' . $form . ' (note ' . $judgement['score'] . ' : '
                    . implode(', ', $judgement['reasons']) . ').');
                self::respond(['ok' => false, 'challenge' => Spam::makeChallenge()]);
            }
        }

        $config = Spam::config();
        if (!RateLimit::allow($form, (int) $config['perIp'], (int) $config['perIpWindow'])) {
            self::fail(I18n::t('form.throttled'), 429);
        }

        $email = self::email($input);
        if ($email !== '' && !RateLimit::allow('email', (int) $config['perEmailPerDay'], 86400, $email)) {
            self::fail(I18n::t('form.throttled'), 429);
        }

        if ($verdict === 'challenge') {
            return ['quarantine' => false, 'score' => $judgement['score'], 'reasons' => $judgement['reasons']];
        }

        if ($verdict === 'quarantine') {
            Spam::strike();
            Log::write('spam', 'Quarantaine sur ' . $form . ' (note ' . $judgement['score'] . ' : '
                . implode(', ', $judgement['reasons']) . ').');
            return ['quarantine' => true, 'score' => $judgement['score'], 'reasons' => $judgement['reasons']];
        }

        return ['quarantine' => false, 'score' => $judgement['score'], 'reasons' => $judgement['reasons']];
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
