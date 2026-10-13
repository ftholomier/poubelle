<?php
declare(strict_types=1);

namespace App\Widgets\Items;

use App\Services\Chiffres;
use App\Widgets\Context;
use App\Widgets\Widget;

/** Le chiffre du jour : une des statistiques de la page « Les chiffres » (Chiffres::daily), une par jour. */
final class ChiffreDuJour extends Widget
{
    public function key(): string
    {
        return 'chiffre-du-jour';
    }

    public function name(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'Stat of the day' : 'Chiffre du jour';
    }

    public function description(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'A striking statistic from the club’s history, calculated from the museum’s records, changing every day.'
            : 'Une statistique forte de l’histoire du club, calculée depuis les fiches du musée, qui change chaque jour.';
    }

    public function height(): int
    {
        return 340;
    }

    public function data(array $opt, Context $c): ?array
    {
        $day = Chiffres::daily(null, Chiffres::cached() ?? Chiffres::all());
        if (!$day) {
            return null;
        }
        $s = $day['stat'];
        $who = [];
        foreach ((array) ($s['who'] ?? []) as $w) {
            if (($w['name'] ?? '') !== '') {
                $who[] = ['name' => (string) $w['name']] + (!empty($w['href']) ? ['href' => (string) $w['href']] : []);
            }
        }
        return [
            'key' => (string) $s['key'],
            'value' => (string) $s['value'],
            'unit' => (string) ($s['unit'] ?? ''),
            'label' => (string) ($s['label'] ?? ''),
            'text' => trim(strip_tags((string) ($s['text'] ?? ''))),
            'who' => array_slice($who, 0, 3),
            'chapter' => (string) $day['chapter'],
            'count' => (int) $day['count'],
            'href' => '/chiffres/#' . rawurlencode((string) $s['key']),
        ];
    }

    public function body(array $d, Context $c): string
    {
        $h = '<p class="w-eyebrow">' . e($c->tr('Le chiffre du jour', 'Stat of the day')) . ' · ' . e($d['chapter']) . '</p>';
        $h .= '<p class="w-big">' . e($d['value']) . ($d['unit'] !== '' ? ' <small>' . e($d['unit']) . '</small>' : '') . '</p>';
        $h .= '<p class="w-title">' . e($d['label']) . '</p>';
        if ($d['text'] !== '') {
            $h .= '<p class="w-text">' . e($d['text']) . '</p>';
        }
        if ($d['who']) {
            $h .= '<p class="w-text w-who">' . implode(', ', array_map(fn ($w) => isset($w['href']) ? '<a href="' . e($w['href']) . '" target="_blank" rel="noopener">' . e($w['name']) . '</a>' : e($w['name']), $d['who'])) . '</p>';
        }
        return $h . '<p><a class="w-btn" href="' . e($d['href']) . '" target="_blank" rel="noopener">' . e($c->tr('Les ' . $d['count'] . ' chiffres du club', 'All ' . $d['count'] . ' stats')) . ' →</a></p>';
    }
}
