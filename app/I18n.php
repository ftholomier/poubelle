<?php
declare(strict_types=1);

namespace App;

/** Dictionnaires d'interface (app/lang/xx.json), repli FR + journal des clés manquantes. */
final class I18n
{
    private static string $lang = Config::DEFAULT_LANG;
    /** @var array<string,array<string,string>> */
    private static array $dicts = [];

    public static function setLang(string $lang): void
    {
        self::$lang = \in_array($lang, Config::LANGS, true) ? $lang : Config::DEFAULT_LANG;
    }

    public static function lang(): string
    {
        return self::$lang;
    }

    public static function isDefault(): bool
    {
        return self::$lang === Config::DEFAULT_LANG;
    }

    public static function t(string $key, array $replace = []): string
    {
        $value = self::dict(self::$lang)[$key]
            ?? self::dict(Config::DEFAULT_LANG)[$key]
            ?? null;

        if ($value === null) {
            Log::write('i18n', 'Clé absente [' . self::$lang . '] : ' . $key);
            $value = $key;
        }
        foreach ($replace as $k => $v) {
            $value = str_replace('{' . $k . '}', (string) $v, $value);
        }
        return $value;
    }

    /** Langues disponibles, dans l'ordre de Config::LANGS. */
    public static function langs(): array
    {
        return Config::LANGS;
    }

    private static function dict(string $lang): array
    {
        if (!isset(self::$dicts[$lang])) {
            $file = Config::path('app/lang/' . preg_replace('/[^a-z]/', '', $lang) . '.json');
            $raw = is_readable($file) ? file_get_contents($file) : '';
            $data = $raw === '' || $raw === false ? null : json_decode($raw, true);
            self::$dicts[$lang] = \is_array($data) ? $data : [];
        }
        return self::$dicts[$lang];
    }

    /** Formatage monétaire selon la locale (intl si présent). */
    public static function price(float|int $amount, string $currency = 'EUR'): string
    {
        if (class_exists(\NumberFormatter::class)) {
            $fmt = new \NumberFormatter(self::locale(), \NumberFormatter::CURRENCY);
            $fmt->setAttribute(\NumberFormatter::FRACTION_DIGITS, fmod((float) $amount, 1.0) === 0.0 ? 0 : 2);
            $out = $fmt->formatCurrency((float) $amount, $currency);
            if ($out !== false) {
                return str_replace("\u{00A0}", "\u{202F}", $out);
            }
        }
        return number_format((float) $amount, 0, ',', "\u{202F}") . ' €';
    }

    public static function date(string $iso, int $style = \IntlDateFormatter::LONG): string
    {
        $ts = strtotime($iso);
        if ($ts === false) {
            return $iso;
        }
        if (class_exists(\IntlDateFormatter::class)) {
            $fmt = new \IntlDateFormatter(self::locale(), $style, \IntlDateFormatter::NONE);
            $out = $fmt->format($ts);
            if ($out !== false) {
                return $out;
            }
        }
        return date('d/m/Y', $ts);
    }

    public static function locale(): string
    {
        return match (self::$lang) {
            'en' => 'en_GB',
            'de' => 'de_DE',
            default => 'fr_FR',
        };
    }
}
