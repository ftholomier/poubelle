<?php
declare(strict_types=1);

namespace App\Vitrine;

use App\Core\Request;
use App\Core\Settings;

/**
 * Adresses du site de l'association (www) : la même application que le musée répond aux
 * deux domaines. Le site de l'association a son adresse (Réglages du pavé), d'autres adresses
 * peuvent y mener (domaine sans « www ») ; tout le reste est le musée.
 *
 * Aperçu : depuis le back-office du musée, l'administrateur voit le site de l'association sous
 * /apercu-association/…, même fermé et même avant que le domaine mène ici. Les liens internes
 * passent par url(), qui ajoute alors ce préfixe.
 */
final class Host
{
    public const PREVIEW = '/apercu-association';

    /** Préfixe des liens internes : vide sur le site, PREVIEW dans l'aperçu. */
    public static string $prefix = '';

    /** « https://www.fcsochauxretro.com » (sans barre finale). */
    public static function base(): string
    {
        $b = rtrim(trim((string) Settings::get('vitrine.base_url', 'https://www.fcsochauxretro.com')), '/');
        return preg_match('#^https?://[a-z0-9.\-]+(:\d+)?$#i', $b) ? $b : 'https://www.fcsochauxretro.com';
    }

    /** Nom de domaine du site (« www.fcsochauxretro.com »). */
    public static function host(): string
    {
        return strtolower((string) parse_url(self::base(), PHP_URL_HOST));
    }

    /** Autres domaines redirigés vers le site. @return list<string> */
    public static function aliases(): array
    {
        $out = [];
        foreach (preg_split('/[\s,;]+/', strtolower((string) Settings::get('vitrine.aliases', ''))) ?: [] as $h) {
            $h = (string) preg_replace('#^https?://#', '', rtrim($h, '/'));
            if ($h !== '' && preg_match('/^[a-z0-9.\-]+$/', $h) && $h !== self::host() && $h !== self::museumHost()) {
                $out[] = $h;
            }
        }
        return array_values(array_unique($out));
    }

    /** Domaine du musée (adresse réglée dans Réglages › Général). */
    public static function museumHost(): string
    {
        return strtolower((string) parse_url(base_url(), PHP_URL_HOST));
    }

    /** Domaine demandé, sans le port. */
    public static function requested(Request $req): string
    {
        $h = strtolower(trim((string) ($req->server['HTTP_HOST'] ?? '')));
        return preg_match('/^[a-z0-9.\-]+(:\d+)?$/', $h) ? (string) preg_replace('/:\d+$/', '', $h) : '';
    }

    /** La requête vise le site de l'association (son adresse ou une adresse redirigée). */
    public static function matches(Request $req): bool
    {
        $h = self::requested($req);
        if ($h === '' || $h === self::museumHost()) {
            return false;
        }
        return $h === self::host() || in_array($h, self::aliases(), true);
    }

    public static function isAlias(Request $req): bool
    {
        return in_array(self::requested($req), self::aliases(), true);
    }

    /** Adresse de l'aperçu du back-office (/apercu-association/…). */
    public static function isPreview(string $path): bool
    {
        return $path === self::PREVIEW || str_starts_with($path, self::PREVIEW . '/');
    }

    /** Lien interne du site (avec le préfixe de l'aperçu). */
    public static function url(string $path = '/'): string
    {
        return self::$prefix . $path;
    }

    /** Adresse complète d'une page du site, telle que publiée (jamais celle de l'aperçu). */
    public static function abs(string $path = '/'): string
    {
        return self::base() . $path;
    }

    /** Adresse complète d'une page du musée. */
    public static function museum(string $path = '/'): string
    {
        return base_url() . $path;
    }
}
