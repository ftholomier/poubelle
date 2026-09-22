<?php
declare(strict_types=1);

namespace App\Storage;

use App\Core\Config;
use App\Domain\CvRepository;
use App\Domain\EmployerRepository;
use App\Domain\JobRepository;
use App\Domain\PageRepository;

/**
 * Index de recherche dénormalisé, régénéré à chaque publication.
 * Le front ne lit qu'un seul fichier par collection au lieu d'ouvrir 89 JSON.
 */
final class Index
{
    public static function path(string $name): string
    {
        return Config::path('data') . '/index/' . $name . '.json';
    }

    public static function load(string $name): array
    {
        $data = Json::read(self::path($name));
        if ($data === []) {
            // Index absent (premier démarrage, restauration) : on le reconstruit.
            self::rebuild($name);
            $data = Json::read(self::path($name));
        }
        return $data['items'] ?? [];
    }

    public static function meta(string $name): array
    {
        $data = Json::read(self::path($name));
        return $data['meta'] ?? [];
    }

    public static function rebuildAll(): array
    {
        $report = [];
        foreach (['jobs', 'cv', 'employers', 'pages'] as $name) {
            $report[$name] = self::rebuild($name);
        }
        return $report;
    }

    public static function rebuild(string $name): int
    {
        $items = match ($name) {
            'jobs'      => self::buildJobs(),
            'cv'        => self::buildCv(),
            'employers' => self::buildEmployers(),
            'pages'     => self::buildPages(),
            default     => [],
        };

        Json::write(self::path($name), [
            'schema'       => Schema::version(),
            'generated_at' => date('c'),
            'meta'         => self::facetsFor($name, $items),
            'items'        => $items,
        ]);

        return count($items);
    }

    private static function buildJobs(): array
    {
        // Le logo appartient à l'employeur : on le reporte sur ses offres pour
        // que la liste n'ait pas à ouvrir une seconde fiche par ligne.
        $logos = [];
        foreach (EmployerRepository::all() as $employer) {
            if (($employer['logo']['path'] ?? '') !== '') {
                $logos[(string) $employer['slug']] = (string) $employer['id'];
            }
        }

        $items = [];
        foreach (JobRepository::all() as $job) {
            if (($job['status'] ?? '') === 'spam') {
                continue;
            }
            $items[] = [
                'id'        => $job['id'],
                'slug'      => $job['slug'],
                'title'     => $job['title'],
                'status'    => $job['status'],
                'company'   => $job['company']['name'] ?? '',
                'company_slug' => $job['company']['slug'] ?? '',
                'company_tagline' => $job['company']['tagline'] ?? '',
                'logo_id'   => $logos[(string) ($job['company']['slug'] ?? '')] ?? '',
                'city'      => $job['location']['city'] ?? '',
                'region'    => $job['location']['region'] ?? '',
                'remote'    => (bool) ($job['location']['remote'] ?? false),
                'salary'    => $job['salary'] ?? '',
                'contract'  => array_values((array) ($job['contract'] ?? [])),
                'category'  => array_values((array) ($job['category'] ?? [])),
                'tags'      => array_slice(array_values((array) ($job['tags'] ?? [])), 0, 8),
                'excerpt'   => str_excerpt((string) ($job['description'] ?? ''), 180),
                'published_at' => $job['published_at'] ?: $job['created_at'],
                'starts_at' => $job['starts_at'] ?? '',
                'expires_at'=> $job['expires_at'] ?? '',
                'filled'    => (bool) ($job['filled'] ?? false),
                'featured'  => (bool) ($job['featured'] ?? false),
                'haystack'  => self::haystack([
                    $job['title'], $job['company']['name'] ?? '', $job['location']['city'] ?? '',
                    $job['location']['region'] ?? '', implode(' ', (array) ($job['tags'] ?? [])),
                    implode(' ', (array) ($job['category'] ?? [])), $job['description'] ?? '',
                ]),
            ];
        }
        usort($items, static fn(array $a, array $b) => strcmp((string) $b['published_at'], (string) $a['published_at']));
        return $items;
    }

    private static function buildCv(): array
    {
        $items = [];
        foreach (CvRepository::all() as $cv) {
            // « listed » ne suffit pas : un brouillon listé figurerait dans le
            // sitemap alors que sa fiche répond 404.
            if (!($cv['listed'] ?? true) || ($cv['status'] ?? '') !== 'publish') {
                continue;
            }
            $items[] = [
                'id'       => $cv['id'],
                'slug'     => $cv['slug'],
                'status'   => $cv['status'],
                'name'     => $cv['name'],
                'title'    => $cv['title'],
                'city'     => $cv['location']['city'] ?? '',
                'region'   => $cv['location']['region'] ?? '',
                'years'    => (int) ($cv['experience_years'] ?? 0),
                'skills'   => array_slice(array_values((array) ($cv['skills'] ?? [])), 0, 8),
                'available'=> (bool) ($cv['available'] ?? true),
                'has_file' => ($cv['file']['path'] ?? '') !== '' || ($cv['file']['legacy_url'] ?? '') !== '',
                'has_photo'=> ($cv['photo']['path'] ?? '') !== '',
                'excerpt'  => str_excerpt((string) ($cv['summary'] ?? ''), 150),
                'published_at' => $cv['published_at'] ?: $cv['created_at'],
                'haystack' => self::haystack([
                    $cv['name'], $cv['title'], $cv['location']['city'] ?? '',
                    implode(' ', (array) ($cv['skills'] ?? [])), $cv['summary'] ?? '',
                ]),
            ];
        }
        usort($items, static fn(array $a, array $b) => strcmp((string) $b['published_at'], (string) $a['published_at']));
        return $items;
    }

    private static function buildEmployers(): array
    {
        $jobCounts = [];
        foreach (JobRepository::all() as $job) {
            if (($job['status'] ?? '') !== 'publish') {
                continue;
            }
            $slug = (string) ($job['company']['slug'] ?? '');
            if ($slug !== '') {
                $jobCounts[$slug] = ($jobCounts[$slug] ?? 0) + 1;
            }
        }

        $items = [];
        foreach (EmployerRepository::all() as $employer) {
            $slug = (string) $employer['slug'];
            $items[] = [
                'id'        => $employer['id'],
                'slug'      => $slug,
                'name'      => $employer['name'],
                'kind'      => $employer['kind'],
                'city'      => $employer['location']['city'] ?? '',
                'website'   => $employer['website'],
                'tagline'   => str_excerpt((string) ($employer['tagline'] ?: $employer['description']), 110),
                'has_logo'  => ($employer['logo']['path'] ?? '') !== '',
                'job_count' => $jobCounts[$slug] ?? 0,
                'haystack'  => self::haystack([$employer['name'], $employer['kind'],
                                               $employer['location']['city'] ?? '', $employer['description']]),
            ];
        }
        usort($items, static function (array $a, array $b) {
            return [$b['job_count'], mb_strtolower((string) $a['name'])]
               <=> [$a['job_count'], mb_strtolower((string) $b['name'])];
        });
        return $items;
    }

    private static function buildPages(): array
    {
        $items = [];
        foreach (PageRepository::published('fr') as $page) {
            $items[] = [
                'slug'    => $page['slug'],
                'title'   => $page['title'],
                'excerpt' => str_excerpt((string) $page['excerpt'] ?: (string) $page['body'], 200),
                'menu'    => (bool) ($page['menu'] ?? false),
                'updated_at' => $page['updated_at'],
            ];
        }
        return $items;
    }

    /** Compteurs de facettes affichés dans les filtres et sur l'accueil. */
    private static function facetsFor(string $name, array $items): array
    {
        if ($name === 'jobs') {
            // Une annonce périmée ne se parcourt plus : elle ne doit pas non
            // plus être comptée. Les compteurs annonçaient cinquante et une
            // offres au-dessus d'une liste vide.
            $live = array_filter(
                $items,
                static fn(array $i) => $i['status'] === 'publish'
                    && !\App\Services\JobLifecycle::isExpired($i),
            );
            $categories = $contracts = $regions = [];
            foreach ($live as $item) {
                foreach ((array) $item['category'] as $c) {
                    $categories[$c] = ($categories[$c] ?? 0) + 1;
                }
                foreach ((array) $item['contract'] as $c) {
                    $contracts[$c] = ($contracts[$c] ?? 0) + 1;
                }
                if ($item['region'] !== '') {
                    $regions[$item['region']] = ($regions[$item['region']] ?? 0) + 1;
                }
            }
            arsort($categories);
            arsort($contracts);
            arsort($regions);

            $today = date('Y-m-d');
            return [
                'total'      => count($live),
                'today'      => count(array_filter($live,
                                  static fn(array $i) => str_starts_with((string) $i['published_at'], $today))),
                'categories' => $categories,
                'contracts'  => $contracts,
                'regions'    => $regions,
            ];
        }

        if ($name === 'cv') {
            $skills = [];
            foreach ($items as $item) {
                foreach ((array) $item['skills'] as $s) {
                    $skills[$s] = ($skills[$s] ?? 0) + 1;
                }
            }
            arsort($skills);
            return ['total' => count($items), 'skills' => array_slice($skills, 0, 40, true)];
        }

        return ['total' => count($items)];
    }

    /** Chaîne de recherche normalisée : minuscules, sans accents. */
    public static function haystack(array $parts): string
    {
        $text = mb_strtolower(implode(' ', array_map('strval', $parts)));
        $text = strtr($text, [
            'à'=>'a','â'=>'a','ä'=>'a','á'=>'a','ã'=>'a','å'=>'a','ç'=>'c','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
            'î'=>'i','ï'=>'i','í'=>'i','ô'=>'o','ö'=>'o','ó'=>'o','õ'=>'o','ù'=>'u','û'=>'u','ü'=>'u','ú'=>'u',
            'ÿ'=>'y','ñ'=>'n','œ'=>'oe','æ'=>'ae',
        ]);
        $text = (string) preg_replace('/<[^>]+>/', ' ', $text);
        $text = (string) preg_replace('/[^a-z0-9]+/', ' ', $text);
        return trim((string) preg_replace('/\s+/', ' ', $text));
    }
}
