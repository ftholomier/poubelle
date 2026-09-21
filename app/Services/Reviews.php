<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Storage\Json;

/**
 * Avis Google via Places API, appelés côté serveur, mis en cache 12 h.
 * Sans clé, le bloc d'avis du pied de page n'est tout simplement pas affiché —
 * il n'y a jamais de note inventée.
 */
final class Reviews
{
    private const ENDPOINT = 'https://places.googleapis.com/v1/places/';

    public static function available(): bool
    {
        return Config::has('places_api_key') && (string) Config::get('reviews.place_id') !== '';
    }

    private static function cachePath(): string
    {
        return Config::path('data') . '/index/google-reviews.json';
    }

    /**
     * @return array{rating:float,count:int,reviews:array<int,array{author:string,text:string,rating:int}>,url:string}|null
     */
    public static function get(): ?array
    {
        $cache = Json::read(self::cachePath());
        $ttl = (int) Config::get('reviews.ttl', 43200);

        if ($cache !== [] && (time() - (int) ($cache['fetched_at'] ?? 0)) < $ttl) {
            return $cache['data'] ?? null;
        }
        if (!self::available()) {
            return $cache['data'] ?? null;   // dernier cache connu, sinon rien
        }

        $placeId = (string) Config::get('reviews.place_id');
        $response = Http::json('GET', self::ENDPOINT . rawurlencode($placeId), [
            'headers' => [
                'X-Goog-Api-Key: ' . (string) Config::secret('places_api_key'),
                'X-Goog-FieldMask: rating,userRatingCount,googleMapsUri,reviews',
            ],
            'timeout' => 15,
        ]);

        if ($response === []) {
            // L'API a échoué : on prolonge le cache existant plutôt que d'afficher un trou.
            if ($cache !== []) {
                $cache['fetched_at'] = time() - (int) ($ttl * 0.75);
                Json::write(self::cachePath(), $cache);
                return $cache['data'] ?? null;
            }
            return null;
        }

        $reviews = [];
        foreach (array_slice((array) ($response['reviews'] ?? []), 0, 2) as $review) {
            $reviews[] = [
                'author' => (string) ($review['authorAttribution']['displayName'] ?? '—'),
                'text'   => str_excerpt((string) ($review['originalText']['text'] ?? $review['text']['text'] ?? ''), 150),
                'rating' => (int) ($review['rating'] ?? 5),
            ];
        }

        $data = [
            'rating'  => round((float) ($response['rating'] ?? 0), 1),
            'count'   => (int) ($response['userRatingCount'] ?? 0),
            'reviews' => $reviews,
            'url'     => (string) ($response['googleMapsUri'] ?? ''),
        ];

        Json::write(self::cachePath(), ['fetched_at' => time(), 'data' => $data]);
        return $data;
    }
}
