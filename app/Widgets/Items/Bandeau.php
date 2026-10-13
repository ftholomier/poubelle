<?php
declare(strict_types=1);

namespace App\Widgets\Items;

use App\Front\Site;
use App\Widgets\Context;
use App\Widgets\Widget;

/**
 * Bandeau « En direct du musée » : les mêmes messages que le bandeau du site (Site::ticker :
 * ce jour-là, centenaire, dernier match fiché, Rétro-Direct, messages de l'équipe), qui défilent
 * en CSS, sur une hauteur fixe. Logo, mention « non officiel » et lien restent visibles.
 */
final class Bandeau extends Widget
{
    public function key(): string
    {
        return 'bandeau';
    }

    public function name(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'Live from the museum ticker' : 'Bandeau « En direct du musée »';
    }

    public function description(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'The museum’s scrolling ticker: on this day, latest match, centenary countdown and news, on a single line.'
            : 'Le bandeau défilant du musée : ce jour-là, dernier match fiché, compte à rebours du centenaire et nouveautés, sur une ligne.';
    }

    public function ttl(): int
    {
        return 1800;
    }

    public function height(): int
    {
        return 64;
    }

    public function data(array $opt, Context $c): ?array
    {
        $out = [];
        foreach (Site::ticker() as $t) {
            if (trim((string) $t['v']) !== '') {
                $out[] = ['label' => (string) $t['k'], 'text' => (string) $t['v'], 'href' => self::local((string) $t['href'])];
            }
        }
        return $out ? ['messages' => $out] : null;
    }

    public function body(array $d, Context $c): string
    {
        $h = '<div class="w-tk" style="--n:' . count($d['messages']) . '"><span class="w-tk__live"><i aria-hidden="true"></i>' . e($c->tr('En direct', 'Live')) . '</span><div class="w-tk__vp"><div class="w-tk__track">';
        foreach ([false, true] as $copy) {
            foreach ($d['messages'] as $m) {
                $h .= '<a href="' . e($m['href']) . '" target="_blank" rel="noopener"' . ($copy ? ' aria-hidden="true" tabindex="-1"' : '') . '><b>' . e($m['label']) . '</b> ' . e($m['text']) . '<span aria-hidden="true">★</span></a>';
            }
        }
        return $h . '</div></div></div>';
    }
}
