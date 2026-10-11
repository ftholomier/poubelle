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

    /** Club choisi au démarrage (Club::boot), sinon le club par défaut. */
    private static ?string $current = null;

    private static function current(): string
    {
        return self::$current ?? self::DEFAULT;
    }

    /**
     * Choisit le club servi, au démarrage (app/bootstrap.php) : variable d'environnement MUSEE_CLUB
     * (tâches planifiées, essais), sinon le nom de domaine demandé (clé « domains » des profils),
     * sinon le club par défaut.
     */
    public static function boot(?string $host): string
    {
        $env = basename((string) getenv('MUSEE_CLUB'));
        if ($env !== '' && is_file(self::file($env))) {
            return self::$current = $env;
        }
        $host = strtolower(preg_replace('/:\d+$/', '', (string) $host) ?? '');
        if ($host !== '') {
            foreach (glob(APP_ROOT . '/config/clubs/*.php') ?: [] as $f) {
                $p = (array) require $f;
                if (in_array($host, array_map('strtolower', (array) ($p['domains'] ?? [])), true)) {
                    self::$profile = $p;
                    return self::$current = basename($f, '.php');
                }
            }
        }
        return self::$current = self::DEFAULT;
    }

    /** Le club servi est-il le club historique (dossiers data/, storage/, public/media/ à la racine) ? */
    public static function isDefault(): bool
    {
        return self::current() === self::DEFAULT;
    }

    /** Tous les profils de clubs : identifiant → profil. @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        $out = [];
        foreach (glob(APP_ROOT . '/config/clubs/*.php') ?: [] as $f) {
            $out[basename($f, '.php')] = (array) require $f;
        }
        return $out;
    }

    private static function file(string $id): string
    {
        return APP_ROOT . '/config/clubs/' . basename($id) . '.php';
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
        return is_file(self::file($id)) ? (array) require self::file($id) : [];
    }
}
