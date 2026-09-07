<?php

declare(strict_types=1);

namespace App\Content;

use App\Core\Config;
use App\Core\JsonStore;
use App\Core\Logger;

/**
 * Avis Google.
 *  - mode « auto »   : appel de l'API Google Places (clé côté serveur uniquement),
 *                      résultat mis en cache dans data/reviews-cache.json ;
 *  - repli manuel    : avis saisis dans le back-office, utilisés si l'API est
 *                      indisponible ou non configurée. Le front affiche donc
 *                      toujours quelque chose.
 */
final class Reviews
{
    private static function manualFile(): string
    {
        return DATA_PATH . '/reviews.json';
    }

    private static function cacheFile(): string
    {
        return DATA_PATH . '/runtime/reviews-cache.json';
    }

    public static function reviewSchema(): array
    {
        return [
            'id'       => '',
            'author'   => '',
            'role'     => '',
            'rating'   => 5,
            'text'     => '',
            'date'     => '',
            'avatar'   => '',
            'source'   => 'manual',
            'featured' => true,
        ];
    }

    /**
     * @return array{rating:float,total:int,url:string,items:array<int,array<string,mixed>>,source:string}
     */
    public static function get(int $limit = 6): array
    {
        $manual = self::manual();

        if (Settings::str('reviews.mode', 'auto') !== 'manual') {
            $google = self::google();
            if ($google !== null && $google['items'] !== []) {
                $min = (int) Settings::get('reviews.min_rating', 4);
                $google['items'] = array_values(array_filter(
                    $google['items'],
                    static fn (array $r): bool => (int) $r['rating'] >= $min
                ));
                if ($google['items'] !== []) {
                    $google['items'] = array_slice($google['items'], 0, $limit);
                    return $google;
                }
            }
        }

        return [
            'rating' => self::averageOf($manual['items']),
            'total'  => count($manual['items']),
            'url'    => Settings::str('reviews.profile_url', ''),
            'items'  => array_slice($manual['items'], 0, $limit),
            'source' => 'manual',
        ];
    }

    /** @return array{items:array<int,array<string,mixed>>} */
    public static function manual(): array
    {
        $data = JsonStore::read(self::manualFile(), ['items' => []], true);
        $items = [];
        foreach ($data['items'] ?? [] as $item) {
            if (is_array($item)) {
                $items[] = \App\Core\Schema::normalize($item, self::reviewSchema());
            }
        }
        return ['items' => $items];
    }

    public static function saveManual(array $items): void
    {
        $clean = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $review = \App\Core\Schema::normalize($item, self::reviewSchema());
            if (trim((string) $review['author']) === '' && trim((string) $review['text']) === '') {
                continue;
            }
            if ($review['id'] === '') {
                $review['id'] = bin2hex(random_bytes(6));
            }
            $review['rating'] = max(1, min(5, (int) $review['rating']));
            $clean[] = $review;
        }
        JsonStore::write(self::manualFile(), ['items' => $clean, 'updated_at' => date('c')]);
        Logger::audit('reviews.save', ['count' => count($clean)]);
    }

    /**
     * Appel API Google Places (New) avec cache disque.
     * @return array{rating:float,total:int,url:string,items:array<int,array<string,mixed>>,source:string}|null
     */
    private static function google(): ?array
    {
        $key     = (string) Config::get('reviews.api_key', '');
        $placeId = Settings::str('reviews.place_id', '') ?: (string) Config::get('reviews.place_id', '');
        if ($key === '' || $placeId === '') {
            return null;
        }

        $cache = JsonStore::read(self::cacheFile(), ['fetched_at' => 0, 'payload' => []], true);
        $ttl   = Config::int('reviews.cache_ttl', 43200);
        if ((int) $cache['fetched_at'] + $ttl > time() && is_array($cache['payload']) && $cache['payload'] !== []) {
            return $cache['payload'];
        }

        $url = rtrim((string) Config::get('reviews.endpoint'), '/') . '/' . rawurlencode($placeId);
        $response = \App\Core\Http::get($url, [
            'X-Goog-Api-Key'   => $key,
            'X-Goog-FieldMask' => 'displayName,rating,userRatingCount,googleMapsUri,reviews',
        ], 12);

        if (!$response['ok'] || !is_array($response['json'])) {
            Logger::warning('Avis Google indisponibles', ['status' => $response['status']]);
            // On sert le cache périmé plutôt que rien.
            return is_array($cache['payload']) && $cache['payload'] !== [] ? $cache['payload'] : null;
        }

        $body = $response['json'];
        $items = [];
        foreach (($body['reviews'] ?? []) as $review) {
            if (!is_array($review)) {
                continue;
            }
            $items[] = [
                'id'       => substr(hash('sha256', (string) ($review['name'] ?? random_bytes(4))), 0, 12),
                'author'   => (string) ($review['authorAttribution']['displayName'] ?? 'Client'),
                'role'     => '',
                'rating'   => (int) ($review['rating'] ?? 5),
                'text'     => (string) ($review['originalText']['text'] ?? $review['text']['text'] ?? ''),
                'date'     => (string) ($review['relativePublishTimeDescription'] ?? ''),
                'avatar'   => (string) ($review['authorAttribution']['photoUri'] ?? ''),
                'source'   => 'google',
                'featured' => true,
            ];
        }

        $payload = [
            'rating' => (float) ($body['rating'] ?? 0),
            'total'  => (int) ($body['userRatingCount'] ?? 0),
            'url'    => (string) ($body['googleMapsUri'] ?? Settings::str('reviews.profile_url', '')),
            'items'  => $items,
            'source' => 'google',
        ];

        JsonStore::write(self::cacheFile(), ['fetched_at' => time(), 'payload' => $payload], false);
        return $payload;
    }

    public static function clearCache(): void
    {
        JsonStore::write(self::cacheFile(), ['fetched_at' => 0, 'payload' => []], false);
    }

    private static function averageOf(array $items): float
    {
        if ($items === []) {
            return 0.0;
        }
        $sum = 0;
        foreach ($items as $item) {
            $sum += (int) ($item['rating'] ?? 5);
        }
        return round($sum / count($items), 1);
    }
}
