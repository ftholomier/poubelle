<?php
declare(strict_types=1);

namespace App\Widgets\Items;

use App\Front\Palmares as Source;
use App\Widgets\Context;
use App\Widgets\Widget;

/**
 * Palmarès : les titres du club, mêmes données que /palmares/ (Front\Palmares::data : liste de
 * référence et finales gagnées des fiches matchs). En liste (compétition et années) ou en
 * bandeau (nombre de titres par compétition) : option « affichage » (et non « format », déjà
 * pris par le format standard / compact commun à tous les widgets).
 */
final class Palmares extends Widget
{
    public function key(): string
    {
        return 'palmares';
    }

    public function name(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'Honours' : 'Palmarès';
    }

    public function description(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'The club’s titles, as a list (competition and years) or as a banner.'
            : 'Les titres du club, en liste (compétition et années) ou en bandeau.';
    }

    public function options(): array
    {
        return ['affichage' => ['label' => 'Affichage', 'label_en' => 'Layout', 'type' => 'select', 'choices' => ['liste' => 'Liste (compétitions et années)', 'bandeau' => 'Bandeau (nombre de titres)'], 'default' => 'liste']];
    }

    public function height(): int
    {
        return 500;
    }

    public function data(array $opt, Context $c): ?array
    {
        $d = Source::data();
        $groups = [];
        foreach ($d['groups'] as $g) {
            if (!$g['titles']) {
                continue;
            }
            $groups[] = [
                'competition' => (string) $g['comp'],
                'count' => count($g['titles']),
                'titles' => array_map(fn ($t) => ['year' => (int) $t['year'], 'season' => (string) $t['season']] + ($t['href'] ? ['href' => self::local($t['href'])] : []), $g['titles']),
            ];
        }
        if (!$groups) {
            return null;
        }
        return ['layout' => $opt['affichage'], 'total' => (int) $d['count']['titre'], 'groups' => $groups, 'href' => '/palmares/'];
    }

    public function body(array $d, Context $c): string
    {
        $h = '<p class="w-eyebrow">' . e($c->tr('Palmarès', 'Honours')) . '</p>';
        $h .= '<p class="w-big">' . $d['total'] . ' <small>' . e($c->tr($d['total'] > 1 ? 'titres' : 'titre', $d['total'] > 1 ? 'titles' : 'title')) . '</small></p>';
        if ($d['layout'] === 'bandeau') {
            $h .= '<div class="w-cups">';
            foreach ($d['groups'] as $g) {
                $h .= '<span><b>' . $g['count'] . '×</b>' . e($g['competition']) . '</span>';
            }
            $h .= '</div>';
        } else {
            $h .= '<ul class="w-list w-palm">';
            foreach ($d['groups'] as $g) {
                $years = array_map(fn ($t) => isset($t['href']) ? '<a href="' . e($t['href']) . '" target="_blank" rel="noopener">' . $t['year'] . '</a>' : (string) $t['year'], $g['titles']);
                $h .= '<li><b>' . e($g['competition']) . '</b> <span class="w-palm__n">' . $g['count'] . '</span><br><span class="w-palm__y">' . implode(', ', $years) . '</span></li>';
            }
            $h .= '</ul>';
        }
        return $h . '<p><a class="w-btn" href="' . e($d['href']) . '" target="_blank" rel="noopener">' . e($c->tr('Tout le palmarès', 'All honours')) . ' →</a></p>';
    }
}
