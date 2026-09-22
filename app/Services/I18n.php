<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Storage\Json;

/**
 * Sept langues, le français comme pivot.
 * Les chaînes d'interface viennent de app/Services/lang/fr.php ; les autres langues
 * sont des traductions mises en cache dans data/i18n/{lang}.json. Une clé non traduite
 * retombe toujours sur le français : aucune page ne peut afficher un identifiant brut.
 */
final class I18n
{
    private static string $lang = 'fr';
    private static array $pivot = [];
    private static array $strings = [];

    public static function boot(string $lang): void
    {
        self::$lang = self::isSupported($lang) ? $lang : 'fr';
        self::$pivot = require Config::path('root') . '/app/Services/lang/fr.php';
        self::$strings = self::$lang === 'fr' ? [] : Json::read(self::cachePath(self::$lang));
    }

    public static function lang(): string
    {
        return self::$lang;
    }

    public static function isPivot(): bool
    {
        return self::$lang === 'fr';
    }

    public static function isSupported(string $lang): bool
    {
        return array_key_exists($lang, (array) Config::get('i18n.languages', []));
    }

    /** @return array<string, array{name:string,locale:string}> */
    public static function languages(): array
    {
        return (array) Config::get('i18n.languages', []);
    }

    public static function locale(?string $lang = null): string
    {
        $lang ??= self::$lang;
        return (string) (self::languages()[$lang]['locale'] ?? 'fr_FR');
    }

    /** Traduit une clé. Les arguments passent par sprintf. */
    public static function t(string $key, mixed ...$args): string
    {
        $text = self::$strings[$key] ?? self::$pivot[$key] ?? $key;
        if ($args === []) {
            return (string) $text;
        }
        // Un motif sprintf mal formé ne doit jamais casser la page.
        $formatted = @vsprintf((string) $text, $args);
        return $formatted === false ? (string) $text : $formatted;
    }

    /**
     * Préfixe un chemin avec la langue active : '/offres' -> '/fr/offres'.
     *
     * Le chemin reçu est toujours l'adresse interne, celle qu'écrit le code.
     * Seo la traduit en adresse publique du moment, ce qui permet de renommer
     * une rubrique depuis le back-office sans toucher à un seul gabarit.
     */
    public static function url(string $path = '/', ?string $lang = null): string
    {
        $lang ??= self::$lang;
        $path = Seo::publicPath('/' . ltrim($path, '/'));
        return $path === '/' ? '/' . $lang . '/' : '/' . $lang . rtrim($path, '/');
    }

    /** Toutes les variantes d'une page, pour les balises hreflang. */
    public static function alternates(string $path): array
    {
        $out = [];
        foreach (array_keys(self::languages()) as $code) {
            $out[$code] = self::url($path, $code);
        }
        return $out;
    }

    /** Retire le segment de langue d'un chemin pour reconstruire les alternatives. */
    public static function stripPrefix(string $path): string
    {
        if (preg_match('#^/([a-z]{2})(/.*)?$#', $path, $m) && self::isSupported($m[1])) {
            return $m[2] ?? '/';
        }
        return $path;
    }

    /**
     * Langue à servir : segment d'URL, puis cookie, puis en-tête du navigateur.
     * Le cookie n'est posé que sur choix explicite (voir Controllers\LanguageController).
     */
    public static function detect(string $path): string
    {
        if (preg_match('#^/([a-z]{2})(/|$)#', $path, $m) && self::isSupported($m[1])) {
            return $m[1];
        }
        $cookie = (string) ($_COOKIE['imtt_lang'] ?? '');
        if (self::isSupported($cookie)) {
            return $cookie;
        }
        foreach (self::parseAcceptLanguage((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')) as $code) {
            if (self::isSupported($code)) {
                return $code;
            }
        }
        return 'fr';
    }

    /** @return string[] codes de langue, du plus au moins souhaité */
    private static function parseAcceptLanguage(string $header): array
    {
        $out = [];
        foreach (explode(',', $header) as $chunk) {
            $parts = explode(';q=', trim($chunk));
            $code = strtolower(substr(trim($parts[0]), 0, 2));
            if ($code !== '') {
                $out[$code] = (float) ($parts[1] ?? 1.0);
            }
        }
        arsort($out);
        return array_keys($out);
    }

    /** Vrai si la langue courante dispose d'un cache de traduction non vide. */
    public static function hasTranslations(?string $lang = null): bool
    {
        $lang ??= self::$lang;
        if ($lang === 'fr') {
            return true;
        }
        return $lang === self::$lang
            ? self::$strings !== []
            : Json::read(self::cachePath($lang)) !== [];
    }

    public static function cachePath(string $lang): string
    {
        return Config::path('data') . '/i18n/' . preg_replace('/[^a-z]/', '', $lang) . '.json';
    }

    /** Régénère le cache d'interface d'une langue. Renvoie le nombre de clés traduites. */
    public static function refresh(string $lang): int
    {
        if ($lang === 'fr' || !self::isSupported($lang)) {
            return 0;
        }
        $pivot = require Config::path('root') . '/app/Services/lang/fr.php';
        $existing = Json::read(self::cachePath($lang));

        // On ne retraduit que ce qui manque : l'API n'est appelée qu'une fois par clé.
        $missing = array_diff_key($pivot, $existing);
        if ($missing === []) {
            return 0;
        }

        $translated = Translator::translate(array_values($missing), $lang);
        if ($translated === []) {
            return 0;
        }

        $keys = array_keys($missing);
        foreach ($translated as $i => $text) {
            if (isset($keys[$i])) {
                $existing[$keys[$i]] = $text;
            }
        }
        Json::write(self::cachePath($lang), $existing);
        return count($translated);
    }
}
