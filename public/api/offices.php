<?php
declare(strict_types=1);

/** GET : disponibilités du catalogue (JSON public). */

require __DIR__ . '/../../app/bootstrap.php';

use App\Api;
use App\Config;
use App\I18n;
use App\Offices;
use App\Router;
use App\Store;

Api::boot();
Api::requireMethod('GET');

$lang = Api::lang($_GET);
$filters = [
    'site' => (string) ($_GET['site'] ?? ''),
    'status' => (string) ($_GET['status'] ?? ''),
    'type' => (string) ($_GET['type'] ?? ''),
];
foreach ($filters as $key => $value) {
    $allowed = match ($key) {
        'site' => Config::SITES,
        'type' => Config::TYPES,
        default => Config::STATUSES,
    };
    if ($value !== '' && !\in_array($value, $allowed, true)) {
        Api::fail('Filtre « ' . $key . ' » invalide.', 422);
    }
}

$offices = [];
foreach (Offices::decorateAll(Offices::filter(Offices::published(), $filters), $lang) as $office) {
    $offices[] = [
        'id' => $office['id'],
        'name' => $office['name'],
        'site' => $office['site'],
        'siteLabel' => $office['siteLabel'],
        'type' => $office['type'],
        'typeLabel' => $office['typeLabel'],
        'area' => $office['area'],
        'price' => $office['price'],
        'currency' => $office['currency'] ?? 'EUR',
        'period' => $office['period'] ?? 'month',
        'vat' => $office['vat'] ?? 'excl',
        'status' => $office['status'],
        'statusLabel' => $office['statusLabel'],
        'availableFrom' => $office['availableFrom'],
        'photos' => $office['photos'],
        'url' => Router::absolute('office', $lang, ['id' => (string) $office['id']]),
    ];
}

$catalogue = Store::read(Offices::FILE);

Api::respond([
    'ok' => true,
    'offices' => $offices,
    'available' => Offices::availableCount($filters['site'] !== '' ? $filters['site'] : null),
    'total' => \count(Offices::published()),
    'minPrice' => Offices::minPrice(),
    'updatedAt' => (string) ($catalogue['updatedAt'] ?? ''),
]);
