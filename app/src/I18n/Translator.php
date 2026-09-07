<?php

declare(strict_types=1);

namespace App\I18n;

use App\Content\Settings;
use App\Core\JsonStore;

/**
 * Multilingue :
 *  1. contenus traduits saisis dans le back-office (champs { "fr": …, "en": … }) ;
 *  2. repli automatique sur la langue par défaut si la traduction manque ;
 *  3. widget Google Traduction pour les langues non gérées manuellement.
 */
final class Translator
{
    private static string $lang = 'fr';
    private static string $fallback = 'fr';
    /** @var array<string,array<string,string>> */
    private static array $dictionaries = [];

    public static function boot(string $lang): void
    {
        $languages = Settings::languages();
        self::$fallback = Settings::str('i18n.default', 'fr');
        self::$lang = in_array($lang, $languages, true) ? $lang : self::$fallback;
    }

    public static function lang(): string
    {
        return self::$lang;
    }

    public static function fallback(): string
    {
        return self::$fallback;
    }

    public static function isDefault(): bool
    {
        return self::$lang === self::$fallback;
    }

    /** Locale complète pour <html lang> / hreflang / og:locale. */
    public static function locale(?string $lang = null): string
    {
        return match ($lang ?? self::$lang) {
            'fr' => 'fr_FR',
            'en' => 'en_GB',
            'es' => 'es_ES',
            'de' => 'de_DE',
            default => 'fr_FR',
        };
    }

    /** @return array<string,string> code => libellé natif */
    public static function labels(): array
    {
        return ['fr' => 'Français', 'en' => 'English', 'es' => 'Español', 'de' => 'Deutsch'];
    }

    public static function label(string $code): string
    {
        return self::labels()[$code] ?? strtoupper($code);
    }

    /**
     * Résout un champ potentiellement multilingue.
     * Accepte une chaîne simple (contenu unique) ou une carte { lang: valeur }.
     */
    public static function pick(mixed $value, ?string $lang = null): string
    {
        $lang = $lang ?? self::$lang;
        if (is_string($value)) {
            return $value;
        }
        if (is_array($value)) {
            if (isset($value[$lang]) && is_scalar($value[$lang]) && trim((string) $value[$lang]) !== '') {
                return (string) $value[$lang];
            }
            $fb = self::$fallback;
            if (isset($value[$fb]) && is_scalar($value[$fb]) && trim((string) $value[$fb]) !== '') {
                return (string) $value[$fb];
            }
            foreach ($value as $candidate) {
                if (is_scalar($candidate) && trim((string) $candidate) !== '') {
                    return (string) $candidate;
                }
            }
        }
        return is_scalar($value) ? (string) $value : '';
    }

    /** Une traduction native existe-t-elle pour cette langue ? */
    public static function hasNative(mixed $value, ?string $lang = null): bool
    {
        $lang = $lang ?? self::$lang;
        return is_array($value) && isset($value[$lang]) && trim((string) $value[$lang]) !== '';
    }

    /** Chaînes d'interface (data/i18n/{lang}.json). */
    public static function t(string $key, array $replace = [], ?string $lang = null): string
    {
        $lang = $lang ?? self::$lang;
        $dict = self::dictionary($lang);
        $text = $dict[$key] ?? null;

        if ($text === null && $lang !== self::$fallback) {
            $text = self::dictionary(self::$fallback)[$key] ?? null;
        }
        $text ??= self::builtin()[$key] ?? $key;

        foreach ($replace as $search => $value) {
            $text = str_replace(':' . $search, (string) $value, $text);
        }
        return $text;
    }

    /** @return array<string,string> */
    private static function dictionary(string $lang): array
    {
        if (isset(self::$dictionaries[$lang])) {
            return self::$dictionaries[$lang];
        }
        $file = DATA_PATH . '/i18n/' . preg_replace('/[^a-z]/', '', $lang) . '.json';
        $data = is_file($file) ? JsonStore::read($file, []) : [];
        $flat = [];
        foreach ($data as $key => $value) {
            if (is_scalar($value)) {
                $flat[(string) $key] = (string) $value;
            }
        }
        return self::$dictionaries[$lang] = $flat;
    }

    /** Filet de sécurité si les fichiers de langue disparaissent. */
    private static function builtin(): array
    {
        return [
            'nav.menu'        => 'Menu',
            'nav.close'       => 'Fermer',
            'cta.contact'     => 'Contactez-moi',
            'cta.coaching'    => 'Demandez un accompagnement',
            'form.name'       => 'Nom et prénom',
            'form.email'      => 'Adresse e-mail',
            'form.phone'      => 'Téléphone',
            'form.company'    => 'Entreprise',
            'form.subject'    => 'Sujet',
            'form.message'    => 'Votre message',
            'form.send'       => 'Envoyer ma demande',
            'form.sending'    => 'Envoi en cours…',
            'form.consent'    => 'J’accepte que mes informations soient utilisées pour être recontacté.',
            'form.required'   => 'Ce champ est obligatoire.',
            'form.error'      => 'Une erreur est survenue. Merci de réessayer.',
            'chat.title'      => 'Assistant',
            'chat.launcher'   => 'Une question ?',
            'chat.send'       => 'Envoyer',
            'reviews.title'   => 'Ils en parlent mieux que moi',
            'footer.legal'    => 'Mentions légales',
            'lang.switch'     => 'Choisir la langue',
            'skip.content'    => 'Aller au contenu principal',
        ];
    }

    /** Chemin d'URL préfixé par la langue quand ce n'est pas la langue par défaut. */
    public static function url(string $path = '/', ?string $lang = null): string
    {
        $lang = $lang ?? self::$lang;
        $path = '/' . ltrim($path, '/');
        if ($lang === self::$fallback) {
            return $path === '/' ? '/' : rtrim($path, '/');
        }
        return '/' . $lang . ($path === '/' ? '' : rtrim($path, '/'));
    }
}
