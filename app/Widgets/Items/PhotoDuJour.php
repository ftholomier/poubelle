<?php
declare(strict_types=1);

namespace App\Widgets\Items;

use App\Data\Index;
use App\Widgets\Context;
use App\Widgets\Frame;
use App\Widgets\Photos;
use App\Widgets\Widget;

/**
 * Photo du jour : une photo d'archives légendée et créditée, la même toute la journée, choisie
 * parmi les seules photos diffusables (Photos::pool : licence libre ou médiathèque créditée).
 */
final class PhotoDuJour extends Widget
{
    public function key(): string
    {
        return 'photo-du-jour';
    }

    public function name(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'Photo of the day' : 'Photo du jour';
    }

    public function description(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'An archive photo with caption and credit, a new one every day.'
            : 'Une photo d’archives légendée et créditée, une nouvelle chaque jour.';
    }

    public function height(): int
    {
        return 480;
    }

    public function data(array $opt, Context $c): ?array
    {
        $pool = Photos::pool(1200);
        if (!$pool) {
            return null;
        }
        $big = array_values(array_filter($pool, fn ($p) => $p['w'] >= 900));
        $pool = $big ?: $pool;
        usort($pool, fn ($a, $b) => strcmp($a['rel'], $b['rel']));
        $p = $pool[crc32(date('Y-m-d')) % count($pool)];
        $fiche = $p['fiche'] ? Index::get($p['fiche']) : null;
        $caption = $c->en() && $p['caption_en'] !== '' ? $p['caption_en'] : $p['caption'];
        return [
            'image' => ['url' => $p['url'], 'width' => $p['w'], 'height' => $p['h']],
            'caption' => $caption,
            'year' => $p['year'] ?: null,
            'credit' => $p['credit'],
            'license' => $p['license'],
            'license_url' => $p['license_url'],
            'source' => $p['source'],
            'href' => $fiche && Index::visible($fiche) ? (string) $fiche['path'] : '/interactif/planche-contact/',
        ];
    }

    public function body(array $d, Context $c): string
    {
        $h = '<p class="w-eyebrow">' . e($c->tr('La photo du jour', 'Photo of the day')) . ($d['year'] ? ' · ' . e((string) $d['year']) : '') . '</p>';
        $h .= '<a class="w-photo" href="' . e($d['href']) . '" target="_blank" rel="noopener"><img src="' . e($d['image']['url']) . '" alt="' . e($d['caption']) . '" decoding="async"></a>';
        $credit = '© ' . e($d['credit']);
        if ($d['license'] !== '') {
            $credit .= ' · ' . ($d['license_url'] !== '' ? '<a href="' . e($d['license_url']) . '" target="_blank" rel="noopener license">' . e($d['license']) . '</a>' : e($d['license']));
        }
        if ($d['source'] !== '') {
            $credit .= ' · <a href="' . e($d['source']) . '" target="_blank" rel="noopener">' . e($c->tr('source', 'source')) . '</a>';
        }
        $h .= '<p class="w-credit">' . $credit . '</p>';
        if ($d['caption'] !== '') {
            $h .= '<p class="w-text w-clamp">' . e($d['caption']) . '</p>';
        }
        return $h;
    }
}
