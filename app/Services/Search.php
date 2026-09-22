<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Storage\Index;

/**
 * Recherche et filtrage sur les index dénormalisés.
 * Tout passe par des paramètres GET : les filtres fonctionnent sans JavaScript.
 */
final class Search
{
    /**
     * @param array{q?:string,city?:string,category?:string[],contract?:string[],region?:string[],
     *              sort?:string,page?:int,per_page?:int,status?:string} $criteria
     * @return array{items:array,total:int,page:int,pages:int,per_page:int,facets:array}
     */
    public static function jobs(array $criteria): array
    {
        $items = Index::load('jobs');
        $status = $criteria['status'] ?? 'publish';
        if ($status !== 'any') {
            $items = array_values(array_filter($items, static fn(array $i) => ($i['status'] ?? '') === $status));
        }

        // Provenance : une sélection qui ne mentionne pas « site » écarte les
        // annonces déposées ici, les partenaires étant servis par l'agrégateur.
        $sources = array_filter(array_map('strval', (array) ($criteria['source'] ?? [])), 'strlen');
        if ($sources !== [] && !in_array('site', $sources, true)) {
            $items = [];
        }

        $items = self::applyText($items, (string) ($criteria['q'] ?? ''));
        $items = self::applyCity($items, (string) ($criteria['city'] ?? ''));
        $items = self::applyList($items, 'category', $criteria['category'] ?? []);
        $items = self::applyList($items, 'contract', $criteria['contract'] ?? []);
        $items = self::applyList($items, 'region', $criteria['region'] ?? [], true);

        $items = self::sort($items, (string) ($criteria['sort'] ?? 'recent'));

        return self::paginate($items, $criteria) + ['facets' => Index::meta('jobs')];
    }

    /** @return array{items:array,total:int,page:int,pages:int,per_page:int,facets:array} */
    public static function cv(array $criteria): array
    {
        $items = Index::load('cv');
        $items = array_values(array_filter($items, static fn(array $i) => ($i['status'] ?? '') === 'publish'));

        $items = self::applyText($items, (string) ($criteria['q'] ?? ''));
        $items = self::applyCity($items, (string) ($criteria['city'] ?? ''));
        $items = self::applyList($items, 'skills', $criteria['skill'] ?? []);
        $items = self::applyList($items, 'region', $criteria['region'] ?? [], true);

        if (!empty($criteria['available'])) {
            $items = array_values(array_filter($items, static fn(array $i) => (bool) ($i['available'] ?? false)));
        }

        $items = self::sort($items, (string) ($criteria['sort'] ?? 'recent'));

        return self::paginate($items, $criteria) + ['facets' => Index::meta('cv')];
    }

    /** @return array{items:array,total:int,page:int,pages:int,per_page:int,facets:array} */
    public static function employers(array $criteria): array
    {
        $items = Index::load('employers');
        $items = self::applyText($items, (string) ($criteria['q'] ?? ''));
        $items = self::applyCity($items, (string) ($criteria['city'] ?? ''));

        if (!empty($criteria['hiring'])) {
            $items = array_values(array_filter($items, static fn(array $i) => (int) ($i['job_count'] ?? 0) > 0));
        }

        return self::paginate($items, $criteria + ['per_page' => 24]) + ['facets' => Index::meta('employers')];
    }

    /** Les N offres les plus récentes — bloc « Fraîchement en ligne ». */
    public static function latestJobs(int $limit = 4): array
    {
        $live = array_filter(Index::load('jobs'), static fn(array $i) => ($i['status'] ?? '') === 'publish');
        return array_slice(array_values($live), 0, $limit);
    }

    /** Les N profils les plus récents — bloc « Des profils prêts à embarquer ». */
    public static function latestCv(int $limit = 4): array
    {
        $live = array_filter(Index::load('cv'), static fn(array $i) => ($i['status'] ?? '') === 'publish');
        return array_slice(array_values($live), 0, $limit);
    }

    /** Familles de métier avec leur compteur — grille de l'accueil. */
    public static function jobFamilies(int $limit = 6): array
    {
        $categories = (array) (Index::meta('jobs')['categories'] ?? []);
        $out = [];
        foreach (array_slice($categories, 0, $limit, true) as $name => $count) {
            $out[] = ['name' => $name, 'slug' => slugify((string) $name), 'count' => (int) $count];
        }
        return $out;
    }

    /** Recherche plein texte tolérante aux accents ; tous les mots doivent être présents. */
    private static function applyText(array $items, string $query): array
    {
        $needle = Index::haystack([$query]);
        if ($needle === '') {
            return $items;
        }
        $words = array_filter(explode(' ', $needle), static fn(string $w) => strlen($w) > 1);
        if ($words === []) {
            return $items;
        }

        $scored = [];
        foreach ($items as $item) {
            $hay = (string) ($item['haystack'] ?? '');
            $score = 0;
            foreach ($words as $word) {
                $hits = substr_count($hay, $word);
                if ($hits === 0) {
                    continue 2;   // un mot manquant écarte le résultat
                }
                $score += $hits;
                // Un mot présent dans le titre pèse plus lourd.
                if (str_contains(Index::haystack([$item['title'] ?? $item['name'] ?? '']), $word)) {
                    $score += 6;
                }
            }
            $item['_score'] = $score;
            $scored[] = $item;
        }

        usort($scored, static fn(array $a, array $b) => $b['_score'] <=> $a['_score']);
        return $scored;
    }

    private static function applyCity(array $items, string $city): array
    {
        $needle = Index::haystack([$city]);
        if ($needle === '') {
            return $items;
        }
        return array_values(array_filter($items, static function (array $item) use ($needle): bool {
            $hay = Index::haystack([$item['city'] ?? '', $item['region'] ?? '']);
            return $hay !== '' && str_contains($hay, $needle);
        }));
    }

    /** Filtre « au moins une valeur en commun » (OU à l'intérieur d'une facette). */
    private static function applyList(array $items, string $field, array $values, bool $scalar = false): array
    {
        $values = array_values(array_filter(array_map('strval', $values), 'strlen'));
        if ($values === []) {
            return $items;
        }
        $wanted = array_map(static fn(string $v) => Index::haystack([$v]), $values);

        return array_values(array_filter($items, static function (array $item) use ($field, $wanted, $scalar): bool {
            $candidates = $scalar ? [(string) ($item[$field] ?? '')] : (array) ($item[$field] ?? []);
            foreach ($candidates as $candidate) {
                if (in_array(Index::haystack([$candidate]), $wanted, true)) {
                    return true;
                }
            }
            return false;
        }));
    }

    private static function sort(array $items, string $sort): array
    {
        if ($sort === 'relevance' && isset($items[0]['_score'])) {
            return $items;   // déjà trié par applyText()
        }
        usort($items, static function (array $a, array $b) use ($sort) {
            return match ($sort) {
                'oldest'   => strcmp((string) $a['published_at'], (string) $b['published_at']),
                'title'    => strcasecmp((string) ($a['title'] ?? $a['name'] ?? ''), (string) ($b['title'] ?? $b['name'] ?? '')),
                default    => strcmp((string) $b['published_at'], (string) $a['published_at']),
            };
        });
        return $items;
    }

    private static function paginate(array $items, array $criteria): array
    {
        $perPage = max(1, (int) ($criteria['per_page'] ?? Config::get('search.per_page', 6)));
        $total = count($items);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, (int) ($criteria['page'] ?? 1)), $pages);

        return [
            'items'    => array_slice($items, ($page - 1) * $perPage, $perPage),
            'total'    => $total,
            'page'     => $page,
            'pages'    => $pages,
            'per_page' => $perPage,
        ];
    }

    /** Reconstruit une URL de filtres en changeant une seule clé. */
    public static function urlWith(array $current, string $key, mixed $value): string
    {
        $params = $current;
        if ($value === null || $value === '' || $value === []) {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
        unset($params['lang']);
        $query = http_build_query($params);
        return $query === '' ? '' : '?' . $query;
    }
}
