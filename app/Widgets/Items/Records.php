<?php
declare(strict_types=1);

namespace App\Widgets\Items;

use App\Front\Explore;
use App\Widgets\Context;
use App\Widgets\Widget;

/**
 * Records : le top 10 des buteurs ou des joueurs les plus utilisés, toutes époques et toutes
 * compétitions officielles, avec exactement les chiffres de la page /records/
 * (Explore::recordRows, totaux de référence du club compris : App\Data\Totals).
 */
final class Records extends Widget
{
    public const CATS = ['buteurs' => ['Meilleurs buteurs', 'Top scorers', 'buts', 'goals'], 'matchs' => ['Joueurs les plus utilisés', 'Most appearances', 'matchs', 'matches']];

    public function key(): string
    {
        return 'records';
    }

    public function name(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'Records' : 'Records';
    }

    public function description(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'Top 10 goalscorers or most-capped players in the club’s history, with the museum’s record-book figures.'
            : 'Le top 10 des buteurs ou des joueurs les plus utilisés de l’histoire du club, avec les chiffres du livre des records du musée.';
    }

    public function options(): array
    {
        return ['cat' => ['label' => 'Classement', 'label_en' => 'Ranking', 'type' => 'select', 'choices' => ['buteurs' => 'Meilleurs buteurs', 'matchs' => 'Joueurs les plus utilisés'], 'default' => 'buteurs']];
    }

    public function height(): int
    {
        return 560;
    }

    public function data(array $opt, Context $c): ?array
    {
        $cat = $opt['cat'];
        $rows = [];
        foreach (Explore::recordRows($cat, null, null, 10) as $i => $r) {
            $rows[] = ['rank' => $i + 1, 'name' => (string) $r['name'], 'meta' => (string) $r['meta'], 'value' => (int) $r['v'], 'href' => self::local($r['href'])];
        }
        if (!$rows) {
            return null;
        }
        [$fr, $en, $ufr, $uen] = self::CATS[$cat];
        return ['cat' => $cat, 'title' => $c->tr($fr, $en), 'unit' => $c->tr($ufr, $uen), 'rows' => $rows, 'href' => '/records/' . ($cat === 'buteurs' ? '' : '?cat=' . $cat)];
    }

    public function body(array $d, Context $c): string
    {
        $h = '<p class="w-eyebrow">' . e($c->tr('Le livre des records', 'The record book')) . '</p>';
        $h .= '<p class="w-title">' . e($d['title']) . '</p>';
        $h .= '<ol class="w-rank">';
        foreach ($d['rows'] as $r) {
            $h .= '<li><span class="w-rank__n">' . $r['rank'] . '</span><a href="' . e($r['href']) . '" target="_blank" rel="noopener"><b>' . e($r['name']) . '</b>'
                . ($r['meta'] !== '' ? '<small>' . e($r['meta']) . '</small>' : '') . '</a><span class="w-rank__v">' . number_format($r['value'], 0, ',', "\u{202F}") . ' <small>' . e($d['unit']) . '</small></span></li>';
        }
        return $h . '</ol><p><a class="w-btn" href="' . e($d['href']) . '" target="_blank" rel="noopener">' . e($c->tr('Tous les records', 'All records')) . ' →</a></p>';
    }
}
