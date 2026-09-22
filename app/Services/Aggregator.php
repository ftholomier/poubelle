<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Services\Sources\AdzunaSource;
use App\Services\Sources\FranceTravailSource;
use App\Services\Sources\IndeedSource;
use App\Services\Sources\JobSource;
use App\Services\Sources\JoobleSource;
use App\Services\Sources\Sector;
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

    /**
     * Partenaires effectivement interrogeables : clés configurées et source
     * active. C'est la seule liste qu'il soit honnête de proposer au visiteur.
     *
     * @return array<string, string> clé => nom affiché
     */
    public static function activePartners(): array
    {
        if (!Config::get('sources.enabled', true)) {
            return [];
        }
        $out = [];
        foreach (self::sources() as $key => $source) {
            if ($source->isConfigured() && self::isSourceEnabled($key)) {
                $out[$key] = $source->name();
            }
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

    /* -------------------------------------------- réglages du back-office */

    /**
     * Les mots-clés envoyés aux agrégateurs — ce que vous taperiez dans leur
     * propre moteur. Modifiables depuis le back-office ; à défaut, la liste
     * des trente métiers reprise de l'ancien site.
     */
    public static function query(): string
    {
        $stored = trim((string) (Json::read(self::statePath())['query'] ?? ''));
        return $stored !== '' ? $stored : trim((string) Config::get('sources.query', ''));
    }

    /**
     * Mots à écarter en plus de la liste intégrée : de quoi bannir un intitulé
     * qui reviendrait sans cesse sans relever du secteur.
     *
     * @return string[]
     */
    public static function exclude(): array
    {
        $raw = (string) (Json::read(self::statePath())['exclude'] ?? '');
        $terms = preg_split('/[,;\r\n]+/', $raw) ?: [];
        return array_values(array_filter(array_map('trim', $terms), 'strlen'));
    }

    /**
     * Codes ROME envoyés à France Travail.
     *
     * Cette source ne se pilote pas aux mots-clés : son répertoire des métiers
     * est un filtre sectoriel autrement plus sûr qu'une recherche plein texte.
     * C'est donc ce levier-là qui est rendu modifiable — l'équivalent, pour
     * elle, de la liste de mots-clés des autres.
     *
     * @return string[]
     */
    public static function romeCodes(): array
    {
        $raw = trim((string) (Json::read(self::statePath())['rome'] ?? ''));
        if ($raw === '') {
            return (array) Config::get('sources.france_travail.rome', []);
        }
        $codes = preg_split('/[^A-Za-z0-9]+/', strtoupper($raw)) ?: [];
        return array_values(array_filter($codes, static fn(string $c): bool
            => preg_match('/^[A-Z]\d{4}$/', $c) === 1));
    }

    /** Le tri par secteur s'applique-t-il aux offres externes ? */
    public static function filterEnabled(): bool
    {
        return (bool) (Json::read(self::statePath())['filter'] ?? true);
    }

    public static function saveSettings(string $query, string $exclude, bool $filter, string $rome = ''): void
    {
        $state = Json::read(self::statePath());
        $state['query']   = mb_substr(trim($query), 0, 2000);
        $state['exclude'] = mb_substr(trim($exclude), 0, 2000);
        $state['rome']    = mb_substr(trim($rome), 0, 600);
        $state['filter']  = $filter;
        Json::write(self::statePath(), $state);
    }

    /* ---------------------------------------------------------- recherche */

    /**
     * Offres externes pour un jeu de critères, toutes sources confondues.
     *
     * @param  array{q?:string,city?:string,page?:int} $criteria
     * @return array<int, array<string, mixed>>
     */
    /**
     * @param bool $refresh true dans le cron : autorise l'appel réseau quand le
     *                      cache est périmé. Dans une requête de visiteur, on
     *                      sert le cache tel quel — une page d'accueil ne doit
     *                      pas attendre quatre API en série.
     */
    public static function fetch(array $criteria, int $limit, bool $refresh = false): array
    {
        if ($limit <= 0 || !Config::get('sources.enabled', true)) {
            return [];
        }

        $query = [
            'q'      => trim((string) ($criteria['q'] ?? '')),
            'city'   => trim((string) ($criteria['city'] ?? '')),
            'page'   => max(1, (int) ($criteria['page'] ?? 1)),
            'limit'  => $limit,
            'source' => $criteria['source'] ?? [],
        ];

        // Provenance demandée : on n'interroge que ces partenaires. « site »
        // ne désigne aucune source — le demander seul ne remonte donc rien.
        $wanted = array_filter(array_map('strval', (array) ($criteria['source'] ?? [])), 'strlen');

        $collected = [];
        foreach (self::sources() as $key => $source) {
            if (!$source->isConfigured() || !self::isSourceEnabled($key)) {
                continue;
            }
            if ($wanted !== [] && !in_array($key, $wanted, true)) {
                continue;
            }
            foreach (self::cached($source, $query, $refresh) as $job) {
                $collected[$job['id']] = $job;
            }
        }

        $jobs = self::dedupe(array_values($collected));
        $jobs = self::applyCriteria($jobs, $criteria);

        // Aucun agrégateur ne sait filtrer par branche : le tri se fait ici,
        // sur l'intitulé et le résumé de chaque offre remontée.
        if (self::filterEnabled()) {
            $exclude = self::exclude();
            $jobs = array_values(array_filter($jobs, static fn(array $j): bool => Sector::matches(
                (string) ($j['title'] ?? ''),
                (string) ($j['excerpt'] ?? ''),
                (string) ($j['company'] ?? ''),
            ) && !Sector::excluded($exclude,
                (string) ($j['title'] ?? ''), (string) ($j['excerpt'] ?? ''))));
        }

        usort($jobs, static fn(array $a, array $b) => strcmp((string) $b['published_at'], (string) $a['published_at']));
        return array_slice($jobs, 0, $limit);
    }

    /**
     * Applique aux offres externes les filtres de la page, autant que leurs
     * données le permettent.
     *
     * Un agrégateur ne renvoie qu'un intitulé, une entreprise, un lieu, parfois
     * un type de contrat. Quand un critère porte sur une donnée absente, l'offre
     * est écartée plutôt que montrée au hasard : une liste filtrée sur « CDI »
     * ne doit contenir que des CDI, fût-ce au prix de quelques offres en moins.
     *
     * @param  array<int, array<string, mixed>> $jobs
     * @return array<int, array<string, mixed>>
     */
    public static function applyCriteria(array $jobs, array $criteria): array
    {
        $words = array_filter(
            explode(' ', Index::haystack([(string) ($criteria['q'] ?? '')])),
            static fn(string $w): bool => strlen($w) > 1,
        );
        $city      = Index::haystack([(string) ($criteria['city'] ?? '')]);
        $regions   = array_filter(array_map('strval', (array) ($criteria['region'] ?? [])), 'strlen');
        $contracts = array_filter(array_map('strval', (array) ($criteria['contract'] ?? [])), 'strlen');
        // Les offres externes ne portent pas les catégories du site : un filtre
        // par catégorie ne peut pas les concerner.
        $categories = array_filter(array_map('strval', (array) ($criteria['category'] ?? [])), 'strlen');

        if ($categories !== []) {
            return [];
        }

        return array_values(array_filter($jobs, static function (array $job) use ($words, $city, $regions, $contracts): bool {
            $lieu = Index::haystack([(string) ($job['city'] ?? ''), (string) ($job['region'] ?? '')]);
            $texte = Index::haystack([
                (string) ($job['title'] ?? ''),
                (string) ($job['company'] ?? ''),
                (string) ($job['excerpt'] ?? ''),
            ]);

            foreach ($words as $word) {
                if (!str_contains($texte, $word)) {
                    return false;
                }
            }
            if ($city !== '' && !str_contains($lieu, $city)) {
                return false;
            }
            if ($regions !== []) {
                $trouve = false;
                foreach ($regions as $region) {
                    if (str_contains($lieu, Index::haystack([$region]))) {
                        $trouve = true;
                        break;
                    }
                }
                if (!$trouve) {
                    return false;
                }
            }
            if ($contracts !== []) {
                $declares = array_map(
                    static fn($c): string => Index::haystack([(string) $c]),
                    (array) ($job['contract'] ?? []),
                );
                $declares = array_filter($declares, 'strlen');
                if ($declares === []) {
                    return false;   // contrat inconnu : on ne l'annonce pas comme tel
                }
                foreach ($contracts as $wanted) {
                    if (in_array(Index::haystack([$wanted]), $declares, true)) {
                        return true;
                    }
                }
                return false;
            }
            return true;
        }));
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

        $external = self::fetch($criteria, $before + $after, false);
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
    private static function cached(JobSource $source, array $query, bool $refresh = false): array
    {
        $file = self::cachePath($source, $query);
        $ttl = max(60, (int) Config::get('sources.cache_ttl', 3600));

        $cache = Json::read($file);
        $fresh = $cache !== [] && (time() - (int) ($cache['at'] ?? 0)) < $ttl;
        if ($fresh) {
            return (array) ($cache['jobs'] ?? []);
        }

        // Cache périmé hors du cron : on sert quand même. Mieux vaut une offre
        // d'hier tout de suite qu'une page bloquée vingt secondes sur une API
        // tierce. Le rafraîchissement viendra de la passe planifiée.
        if (!$refresh && $cache !== []) {
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
