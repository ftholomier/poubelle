<?php
declare(strict_types=1);

namespace App;

/**
 * Avis Google (Places API) avec cache 24 h côté serveur.
 * Sans clé ou sans réseau, on sert les avis saisis au back-office :
 * la page ne dépend jamais d'un appel externe au moment du rendu.
 */
final class Reviews
{
    public const CACHE = 'reviews.cache.json';
    public const MANUAL = 'reviews.json';
    private const TTL = 86_400;

    /**
     * @return array{rating:float,count:int,reviews:array<int,array>,source:string,updatedAt:string,url:string}
     */
    public static function get(int $limit = 4): array
    {
        $cache = Store::read(self::CACHE);
        $fresh = (strtotime((string) ($cache['fetchedAt'] ?? '')) ?: 0) + self::TTL > time();

        if (!$fresh && self::configured()) {
            $fetched = self::fetchFromGoogle();
            if ($fetched !== null) {
                Store::write(self::CACHE, $fetched);
                $cache = $fetched;
            }
        }

        $googleReviews = \is_array($cache['reviews'] ?? null) ? $cache['reviews'] : [];
        if ($googleReviews !== []) {
            return [
                'rating' => (float) ($cache['rating'] ?? 5),
                'count' => (int) ($cache['count'] ?? \count($googleReviews)),
                'reviews' => \array_slice($googleReviews, 0, $limit),
                'source' => 'google',
                'updatedAt' => (string) ($cache['fetchedAt'] ?? ''),
                'url' => (string) ($cache['url'] ?? self::profileUrl()),
            ];
        }

        return self::manual($limit);
    }

    /** Avis saisis au back-office (repli et source par défaut). */
    public static function manual(int $limit = 4): array
    {
        $data = Store::read(self::MANUAL);
        $reviews = \is_array($data['reviews'] ?? null) ? array_values($data['reviews']) : [];
        $reviews = array_values(array_filter($reviews, static fn ($r): bool => \is_array($r) && ($r['enabled'] ?? true)));
        $ratings = array_map(static fn (array $r): float => (float) ($r['rating'] ?? 5), $reviews);

        return [
            'rating' => (float) ($data['rating'] ?? ($ratings === [] ? 5.0 : round(array_sum($ratings) / \count($ratings), 1))),
            'count' => (int) ($data['count'] ?? \count($reviews)),
            'reviews' => \array_slice($reviews, 0, $limit),
            'source' => 'manual',
            'updatedAt' => (string) ($data['updatedAt'] ?? ''),
            'url' => (string) ($data['url'] ?? self::profileUrl()),
        ];
    }

    public static function configured(): bool
    {
        return Config::has('GOOGLE_PLACES_KEY') && Config::has('GOOGLE_PLACE_ID');
    }

    public static function profileUrl(): string
    {
        $placeId = Config::get('GOOGLE_PLACE_ID');
        return $placeId ? 'https://search.google.com/local/reviews?placeid=' . rawurlencode($placeId) : '';
    }

    /** Force le rafraîchissement (bouton du back-office et cron). */
    public static function refresh(): array
    {
        if (!self::configured()) {
            return ['ok' => false, 'error' => 'Clé Google Places ou identifiant de fiche manquant.'];
        }
        $fetched = self::fetchFromGoogle();
        if ($fetched === null) {
            return ['ok' => false, 'error' => 'Google n\'a pas répondu correctement. Voir storage/logs/reviews.log.'];
        }
        Store::write(self::CACHE, $fetched);
        return ['ok' => true, 'count' => \count($fetched['reviews'])];
    }

    private static function fetchFromGoogle(): ?array
    {
        $key = (string) Config::get('GOOGLE_PLACES_KEY');
        $placeId = (string) Config::get('GOOGLE_PLACE_ID');
        $url = 'https://places.googleapis.com/v1/places/' . rawurlencode($placeId) . '?languageCode=' . I18n::lang();

        $response = Http::getJson($url, [
            'headers' => [
                'X-Goog-Api-Key' => $key,
                'X-Goog-FieldMask' => 'id,rating,userRatingCount,googleMapsUri,reviews',
            ],
            'timeout' => 8,
        ]);

        if (!$response['ok'] || $response['json'] === null) {
            Log::write('reviews', 'Places API : statut ' . $response['status'] . ' ' . substr($response['body'], 0, 300));
            return null;
        }

        $payload = $response['json'];
        $reviews = [];
        foreach ((array) ($payload['reviews'] ?? []) as $review) {
            $text = (string) ($review['originalText']['text'] ?? $review['text']['text'] ?? '');
            if (trim($text) === '') {
                continue;
            }
            $name = (string) ($review['authorAttribution']['displayName'] ?? 'Client');
            $reviews[] = [
                'name' => $name,
                'initials' => Text::initials($name),
                'rating' => (int) ($review['rating'] ?? 5),
                'text' => $text,          // jamais retouché
                'at' => (string) ($review['publishTime'] ?? ''),
                'url' => (string) ($review['googleMapsUri'] ?? ''),
                'enabled' => true,
            ];
        }
        usort($reviews, static fn (array $a, array $b): int => strcmp((string) $b['at'], (string) $a['at']));

        return [
            '_schema' => Config::SCHEMA,
            'rating' => (float) ($payload['rating'] ?? 5),
            'count' => (int) ($payload['userRatingCount'] ?? \count($reviews)),
            'url' => (string) ($payload['googleMapsUri'] ?? self::profileUrl()),
            'reviews' => $reviews,
            'fetchedAt' => (new \DateTimeImmutable())->format(\DATE_ATOM),
        ];
    }
}
