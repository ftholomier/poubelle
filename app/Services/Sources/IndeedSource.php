<?php
declare(strict_types=1);

namespace App\Services\Sources;

use App\Core\Config;
use App\Services\Http;

/**
 * Indeed.
 *
 * ⚠ L'ancienne API publisher (`api.indeed.com/ads/apisearch`), celle qu'utilisait
 * l'extension WordPress du site, a été fermée par Indeed : leur recherche est
 * passée en accès partenaire, sans inscription libre-service. L'identifiant
 * publisher du site (repris depuis l'export WordPress) ne renvoie plus rien.
 *
 * Trois voies d'accès sont donc gérées, dans cet ordre :
 *
 *   1. `indeed_feed_url`     — un flux XML/JSON fourni par Indeed dans le cadre
 *                              d'un accord partenaire. C'est la voie qui marche
 *                              aujourd'hui pour un site d'emploi.
 *   2. `indeed_api_base`     — un point d'entrée de recherche partenaire, si
 *                              Indeed vous en donne un (format compatible avec
 *                              l'ancienne API).
 *   3. `indeed_publisher_id` — l'ancienne API publisher, conservée pour le cas
 *                              où un accès historique fonctionnerait encore.
 *
 * Aucune de ces voies n'implique de contourner quoi que ce soit : le scraping
 * des pages d'Indeed est contraire à leurs conditions et n'est pas implémenté.
 */
final class IndeedSource extends AbstractSource
{
    private const LEGACY_API = 'https://api.indeed.com/ads/apisearch';

    public function name(): string
    {
        return 'Indeed';
    }

    public function key(): string
    {
        return 'indeed';
    }

    public function isConfigured(): bool
    {
        return Config::has('indeed_feed_url')
            || Config::has('indeed_api_base')
            || Config::has('indeed_publisher_id');
    }

    public function search(array $criteria): array
    {
        if (Config::has('indeed_feed_url')) {
            return $this->fromFeed($criteria);
        }
        if (Config::has('indeed_api_base') || Config::has('indeed_publisher_id')) {
            return $this->fromApi($criteria);
        }
        return [];
    }

    /* ------------------------------------------------------- flux partenaire */

    /**
     * Flux XML (format « job feed » standard : <job> avec <title>, <company>…)
     * ou JSON. Le flux entier est récupéré puis filtré localement, car il n'est
     * pas paramétrable par requête.
     */
    private function fromFeed(array $criteria): array
    {
        $response = Http::request('GET', (string) Config::secret('indeed_feed_url'), ['timeout' => 25]);
        if ($response['status'] !== 200 || $response['body'] === '') {
            return $this->fail('flux injoignable', ['status' => $response['status']]);
        }

        $body = ltrim($response['body']);
        $jobs = str_starts_with($body, '{') || str_starts_with($body, '[')
            ? $this->parseJsonFeed($body)
            : $this->parseXmlFeed($body);

        return $this->filter($jobs, $criteria);
    }

    private function parseXmlFeed(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($document === false) {
            return $this->fail('flux XML illisible');
        }

        // Les flux d'emploi nomment l'élément <job> ou <item> selon la variante.
        $nodes = $document->xpath('//job') ?: $document->xpath('//item') ?: [];

        $out = [];
        foreach ($nodes as $node) {
            $normalized = $this->normalize([
                'title'        => (string) ($node->title ?? ''),
                'company'      => (string) ($node->company ?? ''),
                'city'         => (string) ($node->city ?? $node->location ?? ''),
                'region'       => (string) ($node->state ?? ''),
                'salary'       => (string) ($node->salary ?? ''),
                'contract'     => array_filter([(string) ($node->jobtype ?? '')]),
                'excerpt'      => $this->text((string) ($node->description ?? '')),
                'published_at' => $this->date((string) ($node->date ?? $node->pubDate ?? '')),
                'url'          => (string) ($node->url ?? $node->link ?? ''),
                'id'           => (string) ($node->referencenumber ?? ''),
            ]);
            if ($normalized !== []) {
                $out[] = $normalized;
            }
        }
        return $out;
    }

    private function parseJsonFeed(string $json): array
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return $this->fail('flux JSON illisible');
        }
        $rows = $data['jobs'] ?? $data['results'] ?? $data['data'] ?? $data;

        $out = [];
        foreach ((array) $rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized = $this->normalize([
                'title'        => (string) ($row['title'] ?? ''),
                'company'      => (string) ($row['company'] ?? $row['companyName'] ?? ''),
                'city'         => (string) ($row['city'] ?? $row['location'] ?? ''),
                'salary'       => (string) ($row['salary'] ?? ''),
                'excerpt'      => $this->text((string) ($row['description'] ?? $row['snippet'] ?? '')),
                'published_at' => $this->date($row['date'] ?? $row['datePosted'] ?? ''),
                'url'          => (string) ($row['url'] ?? $row['applyUrl'] ?? ''),
                'id'           => (string) ($row['id'] ?? ''),
            ]);
            if ($normalized !== []) {
                $out[] = $normalized;
            }
        }
        return $out;
    }

    /* ----------------------------------------------- API de recherche Indeed */

    private function fromApi(array $criteria): array
    {
        $base = Config::has('indeed_api_base')
            ? (string) Config::secret('indeed_api_base')
            : self::LEGACY_API;

        $params = [
            'publisher' => (string) Config::secret('indeed_publisher_id'),
            'q'         => $criteria['q'] !== '' ? $criteria['q'] : (string) $this->setting('query', ''),
            'l'         => $criteria['city'] !== '' ? $criteria['city'] : (string) $this->setting('location', 'France'),
            'co'        => (string) $this->setting('country', 'fr'),
            'sort'      => 'date',
            'limit'     => max(1, min(25, (int) $criteria['limit'])),
            'start'     => max(0, ((int) $criteria['page'] - 1) * (int) $criteria['limit']),
            'v'         => '2',
            'format'    => 'json',
            'userip'    => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'useragent' => $_SERVER['HTTP_USER_AGENT'] ?? 'intermittent.fr',
        ];

        $data = Http::json('GET', $base . '?' . http_build_query($params), ['timeout' => 15]);
        if ($data === []) {
            return $this->fail('API sans réponse exploitable (accès publisher probablement fermé)');
        }

        $out = [];
        foreach ((array) ($data['results'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized = $this->normalize([
                'title'        => (string) ($row['jobtitle'] ?? ''),
                'company'      => (string) ($row['company'] ?? ''),
                'city'         => (string) ($row['city'] ?? ''),
                'region'       => (string) ($row['state'] ?? ''),
                'excerpt'      => $this->text((string) ($row['snippet'] ?? '')),
                'published_at' => $this->date($row['date'] ?? ''),
                'url'          => (string) ($row['url'] ?? ''),
                'id'           => (string) ($row['jobkey'] ?? ''),
            ]);
            if ($normalized !== []) {
                $out[] = $normalized;
            }
        }
        return $out;
    }

    /** Filtre local, pour les flux qui ne prennent pas de requête. */
    private function filter(array $jobs, array $criteria): array
    {
        $query = trim($criteria['q']) !== '' ? $criteria['q'] : (string) $this->setting('query', '');
        $city = trim($criteria['city']);

        $words = array_values(array_filter(
            preg_split('/\s+or\s+|[,\s]+/i', mb_strtolower($query)) ?: [],
            static fn(string $w) => mb_strlen($w) > 2,
        ));

        $matched = [];
        foreach ($jobs as $job) {
            if ($words !== []) {
                $head = mb_strtolower($job['title'] . ' ' . $job['company']);
                $body = mb_strtolower($job['excerpt']);

                // Un mot-clé dans l'intitulé suffit ; dans la seule description,
                // il en faut deux. Sinon « comptable » passe parce que son texte
                // contient le mot « spectacle ».
                $inHead = 0;
                $inBody = 0;
                foreach ($words as $word) {
                    if (str_contains($head, $word)) {
                        $inHead++;
                    } elseif (str_contains($body, $word)) {
                        $inBody++;
                    }
                }
                if ($inHead === 0 && $inBody < 2) {
                    continue;
                }
            }
            if ($city !== '' && !str_contains(mb_strtolower($job['city'] . ' ' . $job['region']), mb_strtolower($city))) {
                continue;
            }
            $matched[] = $job;
        }

        return array_slice($matched, 0, max(1, (int) $criteria['limit']));
    }
}
