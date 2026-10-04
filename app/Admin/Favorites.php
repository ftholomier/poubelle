<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;

/**
 * Favoris du back-office : liens directs choisis par chaque personne, affichés dans la bande du
 * haut (« + Ajouter un favori » ouvre la liste de tout ce qu'on peut y mettre). Rangés dans son
 * compte (storage/users.json, clé « favoris »), jamais vers une adresse hors du back-office.
 */
final class Favorites extends Base
{
    public const MAX = 12;
    private const LABEL = 40;

    /** Favoris de la personne connectée (ceux qu'elle ne peut plus ouvrir sont écartés). */
    public static function mine(): array
    {
        $out = [];
        foreach ((array) (Auth::user()['favoris'] ?? []) as $f) {
            $url = is_array($f) ? self::clean((string) ($f['url'] ?? '')) : null;
            if ($url !== null && self::allowed($url)) {
                $out[] = ['label' => self::label((string) ($f['label'] ?? ''), $url), 'url' => $url];
            }
        }
        return array_slice($out, 0, self::MAX);
    }

    /**
     * Tout ce qu'on peut mettre en favori, par rubrique : les écrans du menu, les créations
     * (« + Nouveau »), et pour les administrateurs chaque groupe des Réglages.
     * @return array<string,list<array{label:string,url:string}>>
     */
    public static function choices(): array
    {
        $admin = Auth::isAdmin();
        $out = [];
        foreach (self::NAV as $group => $items) {
            foreach ($items as [, $label, $href, $adminOnly]) {
                if (!$adminOnly || $admin) {
                    $out[$group][] = ['label' => $label, 'url' => $href];
                }
            }
        }
        foreach (self::createLinks($admin) as [$label, $href]) {
            $out['Créer'][] = ['label' => $label, 'url' => $href];
        }
        if ($admin) {
            foreach (Settings::schema() as $key => $tab) {
                $out['Réglages'][] = ['label' => 'Réglages › ' . $tab['label'], 'url' => '/admin/reglages?groupe=' . $key];
            }
        }
        return $out;
    }

    /** La page affichée, telle qu'on peut la mettre en favori (null : adresse impossible). */
    public static function here(array $meta): ?array
    {
        $url = self::clean((string) ($_SERVER['REQUEST_URI'] ?? ''));
        return $url === null ? null : ['label' => self::label((string) ($meta['title'] ?? ''), $url), 'url' => $url];
    }

    /** POST /admin/api/favoris : {action: ajouter|retirer|renommer|ordre, url, label, urls}. */
    public static function api(Request $req): Response
    {
        $user = Auth::user();
        if (!$user) {
            return self::json(['ok' => false, 'error' => 'Session expirée : reconnectez-vous.'], 401);
        }
        $in = $req->json() ?: $req->post;
        $favs = self::mine();
        $url = self::clean((string) ($in['url'] ?? ''));
        $at = $url === null ? false : array_search($url, array_column($favs, 'url'), true);
        switch ((string) ($in['action'] ?? '')) {
            case 'ajouter':
                if ($url === null || !self::allowed($url)) {
                    return self::json(['ok' => false, 'error' => 'Cette adresse ne peut pas être mise en favori.'], 422);
                }
                if ($at === false) {
                    if (count($favs) >= self::MAX) {
                        return self::json(['ok' => false, 'error' => self::MAX . ' favoris au plus : retirez-en un d’abord.'], 422);
                    }
                    $favs[] = ['label' => self::label((string) ($in['label'] ?? ''), $url), 'url' => $url];
                }
                break;
            case 'retirer':
                if ($at !== false) {
                    array_splice($favs, $at, 1);
                }
                break;
            case 'renommer':
                if ($at !== false) {
                    $favs[$at]['label'] = self::label((string) ($in['label'] ?? ''), $url);
                }
                break;
            case 'ordre':
                $pos = array_flip(array_values(array_filter(array_map(fn ($u) => self::clean((string) $u), (array) ($in['urls'] ?? [])))));
                usort($favs, fn ($a, $b) => ($pos[$a['url']] ?? PHP_INT_MAX) <=> ($pos[$b['url']] ?? PHP_INT_MAX));
                break;
            default:
                return self::json(['ok' => false, 'error' => 'Action inconnue.'], 400);
        }
        Auth::update((string) $user['id'], ['favoris' => $favs]);
        return self::json(['ok' => true, 'favs' => $favs]);
    }

    /** Adresse du back-office seulement (/admin, /admin/…, /admin?…), sans espace ni autre site. */
    public static function clean(string $url): ?string
    {
        $url = trim($url);
        if (strlen($url) > 300 || !preg_match('~^/admin(?:[/?#][^\s"<>\\\\]*)?$~', $url) || str_contains($url, '//')) {
            return null;
        }
        return $url;
    }

    /** Les écrans réservés aux administrateurs ne restent pas en favori d'un autre compte. */
    private static function allowed(string $url): bool
    {
        if (Auth::isAdmin()) {
            return true;
        }
        $path = (string) parse_url($url, PHP_URL_PATH);
        foreach (self::NAV as $items) {
            foreach ($items as [, , $href, $adminOnly]) {
                if ($adminOnly && ($path === $href || str_starts_with($path, $href . '/'))) {
                    return false;
                }
            }
        }
        return !Router::adminOnly($path);
    }

    private static function label(string $label, string $url): string
    {
        $label = trim((string) preg_replace('/\s+/u', ' ', strip_tags($label)));
        if ($label === '') {
            $label = ucfirst(str_replace('-', ' ', basename((string) parse_url($url, PHP_URL_PATH)) ?: 'Tableau de bord'));
        }
        return mb_strimwidth($label, 0, self::LABEL, '…');
    }
}
