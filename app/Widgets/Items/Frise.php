<?php
declare(strict_types=1);

namespace App\Widgets\Items;

use App\Data\Collections;
use App\Data\Seeds;
use App\Widgets\Context;
use App\Widgets\Widget;

/**
 * Frise express : les dates clés de la frise publique (/interactif/frise/, collection « frise »),
 * en bande compacte qui défile toute seule (CSS ; arrêt au survol, défilement à la main si le
 * visiteur a demandé moins d'animations).
 */
final class Frise extends Widget
{
    public function key(): string
    {
        return 'frise';
    }

    public function name(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'Timeline' : 'Frise express';
    }

    public function description(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'A compact scrolling timeline of the key dates in the club’s history.'
            : 'Une frise chronologique compacte qui défile : les dates clés de l’histoire du club.';
    }

    public function height(): int
    {
        return 300;
    }

    public function data(array $opt, Context $c): ?array
    {
        $events = array_map(fn ($e) => Collections::loc($e, ['title', 'text']), array_filter((array) Collections::get('frise', Seeds::frise()), 'is_array'));
        usort($events, fn ($a, $b) => (int) $a['year'] <=> (int) $b['year']);
        $out = [];
        foreach ($events as $e) {
            if ((int) ($e['year'] ?? 0) > 0 && trim((string) ($e['title'] ?? '')) !== '') {
                $out[] = ['year' => (int) $e['year'], 'title' => (string) $e['title'], 'text' => self::excerpt((string) ($e['text'] ?? ''), 110), 'href' => !empty($e['href']) && str_starts_with((string) $e['href'], '/') ? self::local((string) $e['href']) : '/interactif/frise/'];
            }
        }
        return $out ? ['events' => $out, 'href' => '/interactif/frise/'] : null;
    }

    public function body(array $d, Context $c): string
    {
        $item = fn (array $e, bool $copy) => '<li' . ($copy ? ' aria-hidden="true"' : '') . '><a href="' . e($e['href']) . '" target="_blank" rel="noopener"' . ($copy ? ' tabindex="-1"' : '') . '><b>' . $e['year'] . '</b><span>' . e($e['title']) . '</span><small>' . e($e['text']) . '</small></a></li>';
        $h = '<p class="w-eyebrow">' . e($c->tr('La frise du club', 'The club’s timeline')) . '</p>';
        $h .= '<div class="w-tl" style="--n:' . count($d['events']) . '"><ol class="w-tl__track">';
        foreach ([false, true] as $copy) {
            foreach ($d['events'] as $e) {
                $h .= $item($e, $copy);
            }
        }
        $h .= '</ol></div>';
        return $h . '<p><a class="w-btn" href="' . e($d['href']) . '" target="_blank" rel="noopener">' . e($c->tr('Toute la frise', 'The full timeline')) . ' →</a></p>';
    }
}
