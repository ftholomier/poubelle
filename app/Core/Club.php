<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Profil du club du musée (config/clubs/<id>.php) : noms, dates, lieux, identifiants des sources.
 * Un seul club pour l'instant (Sochaux) ; le choix par nom de domaine viendra avec le multi-clubs.
 */
final class Club
{
    public const DEFAULT = 'sochaux';

    /** @var array<string, mixed>|null */
    private static ?array $profile = null;

    /** Club servi : variable d'environnement MUSEE_CLUB (essais), sinon le club par défaut. */
    private static function current(): string
    {
        $id = (string) (getenv('MUSEE_CLUB') ?: self::DEFAULT);
        return is_file(APP_ROOT . '/config/clubs/' . basename($id) . '.php') ? basename($id) : self::DEFAULT;
    }

    public static function id(): string
    {
        return (string) self::get('id', self::DEFAULT);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::$profile ??= self::load(self::current());
        return self::$profile[$key] ?? $default;
    }

    /** Valeur texte du profil (vide si absente). */
    public static function s(string $key): string
    {
        return (string) self::get($key, '');
    }

    /** Nom de l'équipe du club dans les fiches de match (« Sochaux »). */
    public static function team(): string
    {
        return self::s('team');
    }

    /** Ce nom d'équipe désigne-t-il le club du musée ? */
    public static function isUs(string $name): bool
    {
        return $name !== '' && (bool) preg_match(self::s('team_regex'), $name);
    }

    /**
     * Variables du profil pour t() et les gabarits : {site}, {sigle_de}, {city_a}…
     *
     * @return array<string, string>
     */
    public static function vars(): array
    {
        self::$profile ??= self::load(self::current());
        return array_map('strval', array_filter(self::$profile, 'is_scalar'));
    }

    /** Couleur de la charte (« navy », « yellow »…), en hexadécimal. */
    public static function color(string $name): string
    {
        return (string) (self::get('theme', [])[$name] ?? self::THEME[$name] ?? '#000000');
    }

    /** Couleur en « r, g, b » (pour rgba() en PHP). */
    public static function rgb(string $name): string
    {
        [$r, $g, $b] = sscanf(ltrim(self::color($name), '#'), '%02x%02x%02x');
        return "$r, $g, $b";
    }

    /**
     * Bloc <style> qui redéfinit les variables CSS du thème quand le club s'écarte de la charte
     * de base (celle de site.css) ; vide sinon.
     */
    public static function themeCss(): string
    {
        $out = '';
        foreach ((array) self::get('theme', []) as $k => $v) {
            if (isset(self::THEME[$k]) && strcasecmp((string) $v, self::THEME[$k]) !== 0 && preg_match('/^#[0-9a-f]{6}$/i', (string) $v)) {
                $out .= "--$k:$v;--$k-rgb:" . implode(' ', sscanf(substr((string) $v, 1), '%02x%02x%02x')) . ';';
            }
        }
        return $out === '' ? '' : '<style>:root{' . $out . '}</style>';
    }

    /** Charte de base, identique aux variables de public/assets/css/site.css. */
    public const THEME = [
        'navy' => '#0E1F4D', 'navy-2' => '#1A2D63', 'blue' => '#1F3FA8', 'yellow' => '#F6C400',
        'cream' => '#F3EDDF', 'paper' => '#FFFDF6', 'sand' => '#E8DFC9', 'muted' => '#3A4A75',
        'mist' => '#C9CFE0', 'red' => '#D9342B', 'butter' => '#FFF3C2',
    ];

    /** @return array<string, mixed> */
    private static function load(string $id): array
    {
        $file = APP_ROOT . '/config/clubs/' . basename($id) . '.php';
        return is_file($file) ? (array) require $file : [];
    }
}
