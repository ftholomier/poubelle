<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Services\Sources\AdzunaSource;
use App\Services\Sources\FranceTravailSource;
use App\Services\Sources\IndeedSource;
use App\Services\Sources\JobSource;
use App\Services\Sources\JoobleSource;
use App\Storage\Audit;
use App\Storage\Index;
use App\Storage\Json;

/**
 * Complète les annonces déposées sur le site par des offres venues de sources
 * externes, comme le faisait l'extension Indeed du site WordPress.
 *
 * Trois principes :
 *
 *  • Les offres externes ne sont **jamais** stockées comme des annonces locales.
 *    Elles vivent en cache, portent la mention de leur provenance, et renvoient
 *    vers le site d'origine. Elles n'entrent ni dans le plan du site, ni dans
 *    l'index de recherche, ni dans les compteurs affichés.
 *  • Une source sans identifiants est ignorée en silence.
 *  • Une source en panne ne fait jamais échouer la page : au pire, il n'y a que
 *    les annonces locales.
 */
final class Aggregator
{
    /** @return array<string, JobSource> */
    public static function sources(): array
    {
        $all = [
            new IndeedSource(),
            new FranceTravailSource(),
            new AdzunaSource(),
            new JoobleSource(),
        ];

        $out = [];
        foreach ($all as $source) {
            $out[$source->key()] = $source;
        }
        return $out;
    }

    public static function isEnabled(): bool
    {
        if (!Config::get('sources.enabled', true)) {
            return false;
        }
        foreach (self::sources() as $key => $source) {
            if ($source->isConfigured() && self::isSourceEnabled($key)) {
                return true;
            }
        }
        return false;
    }

    /* ------------------------------------------------------ état par source */

    private static function statePath(): string
    {
        return Config::path('data') . '/private/sources.json';
    }

    public static function isSourceEnabled(string $key): bool
    {
        $state = Json::read(self::statePath());
        return (bool) ($state[$key] ?? true);
    }

    public static function setSourceEnabled(string $key, bool $enabled): void
    {
        $state = Json::read(self::statePath());
        $state[$key] = $enabled;
        Json::write(self::statePath(), $state);
    }

    /* ---------------------------------------------------------- recherche */

    /**
     * Offres externes pour un jeu de critères, toutes sources confondues.
     *
     * @param  array{q?:string,city?:string,page?:int} $criteria
     * @return array<int, array<string, mixed>>
     */
    public static function fetch(array $criteria, int $limit): array
    {
        if ($limit <= 0 || !Config::get('sources.enabled', true)) {
            return [];
        }

        $query = [
            'q'     => trim((string) ($criteria['q'] ?? '')),
            'city'  => trim((string) ($criteria['city'] ?? '')),
            'page'  => max(1, (int) ($criteria['page'] ?? 1)),
            'limit' => $limit,
        ];

        $collected = [];
        foreach (self::sources() as $key => $source) {
            if (!$source->isConfigured() || !self::isSourceEnabled($key)) {
                continue;
            }
            foreach (self::cached($source, $query) as $job) {
                $collected[$job['id']] = $job;
            }
        }

        $jobs = self::dedupe(array_values($collected));

        usort($jobs, static fn(array $a, array $b) => strcmp((string) $b['published_at'], (string) $a['published_at']));
        return array_slice($jobs, 0, $limit);
    }

    /**
     * Insère les offres externes autour des annonces locales, en reprenant la
     * répartition de l'ancien site : quelques-unes avant, le complément après.
     *
     * @return array{items:array, external:int}
     */
    public static function blend(array $localItems, array $criteria): array
    {
        $before = max(0, (int) Config::get('sources.before', 0));
        $after = max(0, (int) Config::get('sources.after', 0));

        if ($before + $after === 0 || !self::isEnabled()) {
            return ['items' => $localItems, 'external' => 0];
        }

        $external = self::fetch($criteria, $before + $after);
        if ($external === []) {
            return ['items' => $localItems, 'external' => 0];
        }

        // On écarte ce qui fait doublon avec une annonce déjà publiée ici.
        $external = self::withoutLocalDuplicates($external, $localItems);

        $head = array_slice($external, 0, $before);
        $tail = array_slice($external, $before, $after);

        return [
            'items'    => array_merge($head, $localItems, $tail),
            'external' => count($head) + count($tail),
        ];
    }

    /* ------------------------------------------------------------- cache */

    private static function cachePath(JobSource $source, array $query): string
    {
        $dir = Config::path('data') . '/index/sources';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $key = sha1($source->key() . '|' . $query['q'] . '|' . $query['city'] . '|' . $query['page'] . '|' . $query['limit']);
        return $dir . '/' . $source->key() . '-' . substr($key, 0, 16) . '.json';
    }

    /** @return array<int, array<string, mixed>> */
    private static function cached(JobSource $source, array $query): array
    {
        $file = self::cachePath($source, $query);
        $ttl = max(60, (int) Config::get('sources.cache_ttl', 3600));

        $cache = Json::read($file);
        if ($cache !== [] && (time() - (int) ($cache['at'] ?? 0)) < $ttl) {
            return (array) ($cache['jobs'] ?? []);
        }

        $jobs = [];
        try {
            $jobs = $source->search($query);
        } catch (\Throwable $e) {
            // Une source tierce ne doit jamais casser la page de résultats.
            Audit::log('source.exception', ['source' => $source->key(), 'message' => $e->getMessage()]);
        }

        // Même un résultat vide est mis en cache : sinon chaque visiteur relance
        // un appel réseau sur une source qui ne répond pas.
        Json::write($file, ['at' => time(), 'jobs' => $jobs, 'source' => $source->key()]);

        return $jobs;
    }

    public static function clearCache(): int
    {
        $files = glob(Config::path('data') . '/index/sources/*.json') ?: [];
        foreach ($files as $file) {
            @unlink($file);
        }
        return count($files);
    }

    /** @return array<string, array{jobs:int, age:int}> */
    public static function cacheStatus(): array
    {
        $out = [];
        foreach (glob(Config::path('data') . '/index/sources/*.json') ?: [] as $file) {
            $cache = Json::read($file);
            $key = (string) ($cache['source'] ?? 'inconnu');
            $out[$key]['jobs'] = ($out[$key]['jobs'] ?? 0) + count((array) ($cache['jobs'] ?? []));
            $out[$key]['age'] = min($out[$key]['age'] ?? PHP_INT_MAX, time() - (int) ($cache['at'] ?? 0));
        }
        return $out;
    }

    /* --------------------------------------------------- dédoublonnage */

    /** Deux sources renvoient souvent la même annonce : on n'en garde qu'une. */
    private static function dedupe(array $jobs): array
    {
        $seen = [];
        $out = [];
        foreach ($jobs as $job) {
            $fingerprint = self::fingerprint($job);
            if (isset($seen[$fingerprint])) {
                continue;
            }
            $seen[$fingerprint] = true;
            $out[] = $job;
        }
        return $out;
    }

    /** Une annonce déposée ici prime toujours sur sa reprise chez un agrégateur. */
    private static function withoutLocalDuplicates(array $external, array $localItems): array
    {
        $local = [];
        foreach ($localItems as $item) {
            $local[self::fingerprint($item)] = true;
        }
        // On compare aussi à l'ensemble des annonces publiées, pas seulement à
        // la page courante : un doublon peut être sur une autre page.
        foreach (Index::load('jobs') as $item) {
            if (($item['status'] ?? '') === 'publish') {
                $local[self::fingerprint($item)] = true;
            }
        }

        return array_values(array_filter(
            $external,
            static fn(array $job) => !isset($local[self::fingerprint($job)]),
        ));
    }

    private static function fingerprint(array $job): string
    {
        return Index::haystack([
            mb_substr((string) ($job['title'] ?? ''), 0, 60),
            (string) ($job['company'] ?? ''),
        ]);
    }
}
