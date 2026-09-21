<?php
declare(strict_types=1);

namespace App\Services\Sources;

use App\Core\Config;
use App\Services\Http;

/**
 * Adzuna. Agrégateur généraliste avec une API libre-service (app_id + app_key,
 * palier gratuit). Sert de solution de repli quand un accès Indeed n'est pas
 * disponible : le catalogue français y recouvre une bonne partie des mêmes
 * annonces.
 */
final class AdzunaSource extends AbstractSource
{
    private const ENDPOINT = 'https://api.adzuna.com/v1/api/jobs/';

    public function name(): string
    {
        return 'Adzuna';
    }

    public function key(): string
    {
        return 'adzuna';
    }

    public function isConfigured(): bool
    {
        return Config::has('adzuna_app_id') && Config::has('adzuna_app_key');
    }

    public function search(array $criteria): array
    {
        $country = strtolower((string) $this->setting('country', 'fr'));
        $page = max(1, (int) $criteria['page']);

        $params = [
            'app_id'            => (string) Config::secret('adzuna_app_id'),
            'app_key'           => (string) Config::secret('adzuna_app_key'),
            'results_per_page'  => max(1, min(50, (int) $criteria['limit'])),
            'what_or'           => $criteria['q'] !== ''
                                    ? $criteria['q']
                                    : str_replace(' or ', ' ', (string) $this->setting('query', '')),
            'content-type'      => 'application/json',
            'sort_by'           => 'date',
        ];
        if (trim($criteria['city']) !== '') {
            $params['where'] = trim($criteria['city']);
        }

        $data = Http::json('GET', self::ENDPOINT . $country . '/search/' . $page . '?' . http_build_query($params),
            ['timeout' => 20]);
        if ($data === []) {
            return $this->fail('réponse vide');
        }

        $out = [];
        foreach ((array) ($data['results'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $salary = '';
            if (!empty($row['salary_min']) && !empty($row['salary_max'])) {
                $salary = sprintf('%s – %s €',
                    number_format((float) $row['salary_min'], 0, ',', ' '),
                    number_format((float) $row['salary_max'], 0, ',', ' '));
            }

            $normalized = $this->normalize([
                'title'        => (string) ($row['title'] ?? ''),
                'company'      => (string) ($row['company']['display_name'] ?? ''),
                'city'         => (string) ($row['location']['display_name'] ?? ''),
                'salary'       => $salary,
                'contract'     => array_filter([(string) ($row['contract_time'] ?? '')]),
                'tags'         => array_filter([(string) ($row['category']['label'] ?? '')]),
                'excerpt'      => $this->text((string) ($row['description'] ?? '')),
                'published_at' => $this->date($row['created'] ?? ''),
                'url'          => (string) ($row['redirect_url'] ?? ''),
                'id'           => (string) ($row['id'] ?? ''),
            ]);
            if ($normalized !== []) {
                $out[] = $normalized;
            }
        }
        return $out;
    }
}
