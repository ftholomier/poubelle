<?php
declare(strict_types=1);

namespace App\Widgets\Items;

use App\Data\Collections;
use App\Data\Seeds;
use App\Widgets\Context;
use App\Widgets\Frame;
use App\Widgets\Photos;
use App\Widgets\Widget;

/**
 * Maillot de l'époque : un maillot de la collection « maillots » (celle de /interactif/maillots/),
 * avec son époque et son histoire. Époque choisie (?epoque=1930) ; par défaut, un maillot
 * différent chaque jour. Photo seulement si elle est diffusable (Photos::safe), sinon un visuel
 * neutre (l'année de l'époque).
 */
final class Maillot extends Widget
{
    public function key(): string
    {
        return 'maillot';
    }

    public function name(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'Shirt of an era' : 'Maillot de l’époque';
    }

    public function description(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'A club shirt from a given era, with its story.' : 'Un maillot du club d’une époque, avec son histoire.';
    }

    public function options(): array
    {
        return ['epoque' => ['label' => 'Époque', 'label_en' => 'Era', 'type' => 'select', 'default' => 'auto',
            'choices' => function () {
                $out = ['auto' => 'Automatique : un maillot différent chaque jour'];
                foreach (self::eras() as $e) {
                    $out[(string) (int) $e['era']] = (int) $e['era'] . ' : ' . (string) ($e['label'] ?? '');
                }
                return $out;
            }]];
    }

    /** Une année quelconque (?epoque=1935) donne l'époque qui la couvre ; sinon : valeur de la liste. */
    public function clean(array $query): array
    {
        $y = is_scalar($query['epoque'] ?? null) ? trim((string) $query['epoque']) : '';
        if (preg_match('/^(18|19|20)\d\d$/', $y)) {
            $cover = null;
            foreach (self::eras() as $e) {
                if ((int) $e['era'] <= (int) $y) {
                    $cover = (string) (int) $e['era'];
                }
            }
            $query['epoque'] = $cover ?? '';
        }
        return parent::clean($query);
    }

    public function height(): int
    {
        return 560;
    }

    /** Maillots de la collection (traduits), dans l'ordre des époques. @return list<array> */
    public static function eras(): array
    {
        $list = [];
        foreach ((array) Collections::get('maillots', Seeds::maillots()) as $e) {
            if (is_array($e) && (int) ($e['era'] ?? 0) > 0) {
                $list[] = Collections::loc($e, ['label', 'text']);
            }
        }
        usort($list, fn ($a, $b) => (int) $a['era'] <=> (int) $b['era']);
        return $list;
    }

    public function data(array $opt, Context $c): ?array
    {
        $eras = self::eras();
        if (!$eras) {
            return null;
        }
        $pick = null;
        if ($opt['epoque'] !== 'auto') {
            foreach ($eras as $e) {
                if ((int) $e['era'] <= (int) $opt['epoque']) {
                    $pick = $e; // l'époque qui couvre l'année demandée
                }
            }
        }
        $pick ??= $eras[crc32('maillot|' . date('Y-m-d')) % count($eras)];
        $photo = Photos::safe((string) ($pick['image'] ?? ''), 800);
        $i = array_search($pick, $eras, true);
        $next = $eras[$i + 1] ?? null;
        return [
            'era' => (int) $pick['era'],
            'until' => $next ? (int) $next['era'] - 1 : null,
            'label' => (string) ($pick['label'] ?? $pick['era']),
            'text' => self::excerpt((string) ($pick['text'] ?? ''), 360),
            'image' => $photo ? ['url' => $photo['url'], 'credit' => $photo['credit'], 'license' => $photo['license'], 'rel' => $photo['rel']] : null,
            'count' => count($eras),
            'href' => '/interactif/maillots/',
        ];
    }

    public function body(array $d, Context $c): string
    {
        $h = '<p class="w-eyebrow">' . e($c->tr('Le maillot de l’époque', 'Shirt of an era')) . '</p>';
        $h .= '<p class="w-title"><a href="' . e($d['href']) . '" target="_blank" rel="noopener">' . e($d['label']) . '</a></p>';
        $h .= $d['image'] ? Frame::media($d['image'], $d['label']) : '<div class="w-era" aria-hidden="true"><b>' . $d['era'] . '</b>' . ($d['until'] ? '<span>' . $d['era'] . '–' . $d['until'] . '</span>' : '') . '</div>';
        if ($d['text'] !== '') {
            $h .= '<p class="w-text w-clamp">' . e($d['text']) . '</p>';
        }
        return $h . '<p><a class="w-btn" href="' . e($d['href']) . '" target="_blank" rel="noopener">' . e($c->tr('Tous les maillots', 'All the shirts')) . ' →</a></p>';
    }
}
