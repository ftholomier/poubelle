<?php
declare(strict_types=1);

/** GET : avis Google en cache (jamais d'appel à Google au moment du rendu). */

require __DIR__ . '/../../app/bootstrap.php';

use App\Api;
use App\Reviews;

Api::boot();
Api::requireMethod('GET');
Api::lang($_GET);

$limit = max(1, min(10, (int) ($_GET['limit'] ?? 4)));
$data = Reviews::get($limit);

Api::respond([
    'ok' => true,
    'rating' => $data['rating'],
    'count' => $data['count'],
    'reviews' => array_map(static fn (array $r): array => [
        'name' => (string) ($r['name'] ?? ''),
        'rating' => (int) ($r['rating'] ?? 5),
        'text' => (string) ($r['text'] ?? ''),
        'at' => (string) ($r['at'] ?? ''),
    ], $data['reviews']),
    'source' => $data['source'],
    'url' => $data['url'],
    'updatedAt' => $data['updatedAt'],
]);
