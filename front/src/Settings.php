<?php

declare(strict_types=1);

/**
 * Réglages privés (jamais affichés sur le site) : notifications, assistant IA, Facebook,
 * envoi d'e-mails, mises à jour. Les secrets (clés, mots de passe, jetons) sont dans
 * storage/data/secrets.json et ne sont jamais renvoyés au navigateur.
 */
final class Settings
{
    public const DEFAULTS = [
        'notify_email' => 'accueil@renovest.fr',
        'notify_cc' => '',
        'bot_model' => '',
        'bot_extra' => '',
        'bot_log' => true,
        'fb_page_id' => '',
        'fb_default_cat' => 'Agence',
        'fb_rules' => "#chantier=Chantiers\n#realisation=Chantiers\n#conseil=Conseils\n#astuce=Conseils\n#agence=Agence",
        'fb_auto_hours' => 6,
        'fb_publish_default' => false,
        'smtp_host' => '',
        'smtp_port' => 587,
        'smtp_user' => '',
        'smtp_secure' => 'tls',
        'update_repo' => '',
        'update_branch' => '',
    ];


    public static function all(): array
    {
        return array_replace(self::DEFAULTS, Store::get('settings', []));
    }

    public static function get(string $key, $default = null)
    {
        $a = self::all();
        return $a[$key] ?? $default;
    }

    public static function set(array $pairs): void
    {
        Store::update('settings', fn(array $s) => array_replace($s, $pairs));
    }

    public static function secret(string $key): string
    {
        return (string) (Store::get('secrets', [])[$key] ?? '');
    }

    public static function hasSecret(string $key): bool
    {
        return self::secret($key) !== '';
    }

    public static function setSecret(string $key, string $value): void
    {
        Store::update('secrets', function (array $s) use ($key, $value) {
            if ($value === '') {
                unset($s[$key]);
            } else {
                $s[$key] = $value;
            }
            return $s;
        });
        @chmod(cfg('storage') . '/data/secrets.json', 0600);
    }
}
