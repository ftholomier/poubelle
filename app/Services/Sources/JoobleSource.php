<?php
declare(strict_types=1);

namespace App\Services\Sources;

use App\Core\Config;
use App\Services\Http;

/**
 * Jooble. Agrégateur avec une API simple et une clé obtenue en libre-service.
 * Utile en complément : son catalogue français reprend de nombreuses annonces
 * de sites d'emploi qui ne sont pas ailleurs.
 */
final class JoobleSource extends AbstractSource
{
    private const ENDPOINT = 'https://jooble.org/api/';

    public function name(): string
    {
        return 'Jooble';
    }

    public function key(): string
    {
        return 'jooble';
    }

    public function isConfigured(): bool
    {
        return Config::has('jooble_key');
    }

    public function search(array $criteria): array
    {
        $keywords = $criteria['q'] !== ''
            ? $criteria['q']
            : str_replace(' or ', ' ', (string) $this->setting('query', ''));

        $data = Http::json('POST', self::ENDPOINT . rawurlencode((string) Config::secret('jooble_key')), [
            'json' => [
                'keywords' => mb_substr($keywords, 0, 200),
                'location' => $criteria['city'] !== '' ? $criteria['city'] : (string) $this->setting('location', 'France'),
                'page'     => max(1, (int) $criteria['page']),
            ],
            'timeout' => self::timeout(),
        ]);
        if ($data === []) {
            return $this->fail('réponse vide');
        }

        $out = [];
        foreach (array_slice((array) ($data['jobs'] ?? []), 0, max(1, (int) $criteria['limit'])) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized = $this->normalize([
                'title'        => (string) ($row['title'] ?? ''),
                'company'      => (string) ($row['company'] ?? ''),
                'city'         => (string) ($row['location'] ?? ''),
                'salary'       => (string) ($row['salary'] ?? ''),
                'contract'     => array_filter([(string) ($row['type'] ?? '')]),
                'excerpt'      => $this->text((string) ($row['snippet'] ?? '')),
                'published_at' => $this->date($row['updated'] ?? ''),
                'url'          => (string) ($row['link'] ?? ''),
                'id'           => (string) ($row['id'] ?? ''),
            ]);
            if ($normalized !== []) {
                $out[] = $normalized;
            }
        }
        return $out;
    }
}
