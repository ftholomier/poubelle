<?php
declare(strict_types=1);

namespace App\Data;

/**
 * Adresses des fiches (schéma « option B ») :
 *   /joueurs/{nom}/, /entraineurs/{nom}/, /dirigeants/{nom}/, /personnages-emblematiques/{nom}/
 *   /matchs/{saison}/{domicile}-{exterieur}-{competition}-{jj-mm-aaaa}/
 *   /{infrastructures|symboles|supporters|articles}/{titre}/
 *   /{titre}/ pour les pages, /reserves/{collection}/{titre}/ pour les objets,
 *   /centenaire/100-moments/{numero}-{titre}/ pour les moments.
 * Une adresse modifiée crée automatiquement une redirection 301 depuis l'ancienne.
 */
final class Paths
{
    public const PERSON_BASE = ['joueur' => 'joueurs', 'entraineur' => 'entraineurs', 'dirigeant' => 'dirigeants', 'personnage' => 'personnages-emblematiques'];
    /** Premiers segments réservés aux pages du site (jamais utilisés par une page libre). */
    private const RESERVED = ['admin', 'api', 'media', 'assets', 'en', 'recherche', 'partage', 'interactif', 'centenaire', 'reserves', 'faire-un-don', 'contact', 'contribuer', 'newsletter', 'saisons', 'face-a-face', 'bilans', 'records', 'mentions-legales', 'confidentialite', 'cookies', 'partage-et-newsletter', 'robots.txt', 'sitemap.xml', 'wp-content'];

    public static function slug(string $s, int $max = 90): string
    {
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = function_exists('transliterator_transliterate') ? transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $s) : false;
        $s = $t !== false ? $t : strtolower((string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s));
        $s = trim((string) preg_replace('/[^a-z0-9]+/', '-', $s), '-');
        return rtrim(substr($s, 0, $max), '-');
    }

    /** Adresse proposée pour une fiche (sans garantie d'unicité). */
    public static function suggest(array $doc): string
    {
        $type = $doc['type'];
        $title = (string) ($doc['title'] ?? '');
        if ($type === 'personne') {
            $p = $doc['personne'] ?? [];
            $name = trim((string) ($p['display_name'] ?? '')) ?: trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '')) ?: $title;
            return '/' . (self::PERSON_BASE[$p['roles'][0] ?? 'joueur'] ?? 'joueurs') . '/' . (self::slug($name) ?: 'sans-nom') . '/';
        }
        if ($type === 'match') {
            $m = $doc['match'] ?? [];
            $season = $m['season'] ?? null;
            if (!$season && !empty($m['date'])) {
                $season = self::seasonOf((string) $m['date']);
            }
            if (!empty($m['date']) && !empty($m['home']['name']) && !empty($m['away']['name'])) {
                $comp = self::slug(($m['competition'] ?? '') === 'Championnat' ? ($m['competition_label'] ?: 'championnat') : ($m['competition'] ?? ''));
                $slug = self::slug($m['home']['name']) . '-' . self::slug($m['away']['name']) . ($comp ? "-$comp" : '') . '-' . date('d-m-Y', strtotime((string) $m['date']));
            } else {
                $slug = self::slug($title) ?: 'match';
            }
            return '/matchs/' . ($season ?: 'saison-inconnue') . '/' . $slug . '/';
        }
        if ($type === 'page') {
            $slug = self::slug($title) ?: 'page';
            return '/' . (in_array($slug, self::RESERVED, true) ? "page-$slug" : $slug) . '/';
        }
        if ($type === 'objet') {
            return '/reserves/' . (self::slug((string) ($doc['objet']['collection'] ?? 'photos')) ?: 'photos') . '/' . (self::slug($title) ?: 'objet') . '/';
        }
        if ($type === 'moment') {
            $n = (int) ($doc['moment']['number'] ?? 0);
            return '/centenaire/100-moments/' . ($n ? sprintf('%02d-', $n) : '') . (self::slug($title, 70) ?: 'moment') . '/';
        }
        // Article : rubrique racine infrastructures / symboles / supporters, sinon « articles ».
        $base = 'articles';
        foreach ($doc['categories'] ?? [] as $c) {
            $root = Categories::root((string) $c);
            if (in_array($root, ['infrastructures', 'symboles', 'supporters'], true)) {
                $base = $root;
                break;
            }
        }
        if (mb_strlen($title) > 70 && preg_match('/^(.{20,70}?)[:!?]/u', $title, $mm)) {
            $title = $mm[1];
        }
        return '/' . $base . '/' . (self::slug($title) ?: 'article') . '/';
    }

    public static function seasonOf(string $date): ?string
    {
        $ts = strtotime($date);
        if (!$ts) {
            return null;
        }
        $y = (int) date('Y', $ts);
        return (int) date('n', $ts) >= 7 ? $y . '-' . ($y + 1) : ($y - 1) . '-' . $y;
    }

    /** Adresse libre : ajoute -2, -3… si elle est déjà prise par une autre fiche ou une rubrique. */
    public static function unique(string $path, int $id): string
    {
        $path = '/' . trim(preg_replace('#/+#', '/', strtolower($path)), '/') . '/';
        $taken = function (string $p) use ($id): bool {
            $s = Index::byPath($p);
            if ($s && (int) $s['id'] !== $id) {
                return true;
            }
            return Categories::byPath($p) !== null;
        };
        if (!$taken($path)) {
            return $path;
        }
        $base = rtrim($path, '/');
        for ($i = 2; $i < 200; $i++) {
            if (!$taken("$base-$i/")) {
                return "$base-$i/";
            }
        }
        return "$base-$id/";
    }

    /** Une adresse saisie à la main est-elle acceptable ? Renvoie un message d'erreur ou null. */
    public static function check(string $path): ?string
    {
        if (!preg_match('#^/[a-z0-9][a-z0-9\-/]*/$#', $path)) {
            return 'L’adresse doit commencer et finir par « / » et ne contenir que des lettres minuscules sans accents, des chiffres et des tirets.';
        }
        $first = explode('/', trim($path, '/'))[0];
        if (in_array($first, ['admin', 'api', 'media', 'assets', 'en', 'wp-content'], true)) {
            return 'Cette adresse est réservée par le site.';
        }
        return null;
    }
}
