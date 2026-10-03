<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\JsonStore;

/**
 * Langues du site : français (référence) et anglais.
 * - Libellés d'interface : data/i18n/en.json (« texte français » → « English text »).
 * - Contenus des fiches : champ i18n.en de chaque fiche (traduction Gemini corrigeable).
 */
final class I18n
{
    public const DEFAULT = 'fr';
    public const LANGS = ['fr' => 'Français', 'en' => 'English'];
    private static string $lang = self::DEFAULT;
    private static ?array $dict = null;
    /** Textes déjà traduits (valeurs du dictionnaire) : ne sont pas retraduits. */
    private static ?array $done = null;

    public static function set(string $lang): void
    {
        self::$lang = isset(self::LANGS[$lang]) ? $lang : self::DEFAULT;
        self::$dict = null;
        self::$done = null;
    }

    public static function lang(): string
    {
        return self::$lang;
    }

    public static function isEn(): bool
    {
        return self::$lang === 'en';
    }

    public static function enabled(): array
    {
        $codes = array_map('trim', explode(',', (string) \App\Core\Settings::get('translation.languages', 'fr,en')));
        return array_values(array_intersect(array_keys(self::LANGS), $codes)) ?: ['fr'];
    }

    public static function text(string $fr): string
    {
        if (self::$lang === self::DEFAULT) {
            return $fr;
        }
        if (self::$dict === null) {
            try {
                $d = JsonStore::read(DATA_PATH . '/i18n/' . self::$lang . '.json', []);
            } catch (\RuntimeException $e) {
                error_log($e->getMessage()); // fichier abîmé : textes en français, signalé dans Qualité
                $d = [];
            }
            self::$dict = is_array($d) ? $d : [];
        }
        $t = self::$dict[$fr] ?? '';
        if ($t === '') {
            // Texte déjà en anglais (traduit plus haut, par ex. un élément de collection) :
            // rendu tel quel, sans le signaler comme libellé manquant.
            self::$done ??= array_flip(array_filter(array_map('strval', self::$dict), fn ($v) => $v !== ''));
            if (isset(self::$done[$fr])) {
                return $fr;
            }
            self::missing($fr);
            return $fr;
        }
        return $t;
    }

    /** Libellés sans traduction : relevés pour l'écran « Traductions EN » du back-office. */
    private static function missing(string $fr): void
    {
        static $seen = [];
        if (isset($seen[$fr]) || mb_strlen($fr) > 400) {
            return;
        }
        $seen[$fr] = true;
        $file = STORAGE_PATH . '/i18n-missing-' . self::$lang . '.json';
        try {
            $all = JsonStore::read($file, []);
        } catch (\RuntimeException) {
            $all = []; // simple relevé : un fichier abîmé repart de zéro
        }
        $all = is_array($all) ? $all : [];
        if (!isset($all[$fr])) {
            $all[$fr] = date('c');
            @JsonStore::write($file, $all);
        }
    }

    /** Champ traduit d'une fiche (repli sur le français). */
    public static function field(array $doc, string $key, mixed $default = null): mixed
    {
        if (self::$lang !== self::DEFAULT) {
            $v = $doc['i18n'][self::$lang][$key] ?? null;
            if ($v !== null && $v !== '' && $v !== []) {
                return $v;
            }
        }
        return $doc[$key] ?? $default;
    }

    /** Adresse équivalente dans l'autre langue. */
    public static function switchUrl(string $path, string $to): string
    {
        $bare = preg_replace('#^/en(?=/|$)#', '', $path) ?: '/';
        return $to === self::DEFAULT ? $bare : '/' . $to . ($bare === '/' ? '/' : $bare);
    }
}
