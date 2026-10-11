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

    public static function id(): string
    {
        return (string) self::get('id', self::DEFAULT);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::$profile ??= self::load(self::DEFAULT);
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
        self::$profile ??= self::load(self::DEFAULT);
        return array_map('strval', array_filter(self::$profile, 'is_scalar'));
    }

    /** @return array<string, mixed> */
    private static function load(string $id): array
    {
        $file = APP_ROOT . '/config/clubs/' . basename($id) . '.php';
        return is_file($file) ? (array) require $file : [];
    }
}
