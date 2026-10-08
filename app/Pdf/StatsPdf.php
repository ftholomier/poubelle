<?php
declare(strict_types=1);

namespace App\Pdf;

use App\Services\Stats;
use App\Services\StatsReport;

/**
 * Rapport PDF des statistiques du musée (A4) : chiffres clés comparés, « à retenir »,
 * fréquentation, heures, appareils, provenances, décennies et classements.
 */
final class StatsPdf
{
    private Layout $l;
    private float $y = 0;
    private const M = 40.0;

    public static function build(array $r, string $label): string
    {
        return (new self())->render($r, $label);
    }

    private function fmt(float|int $n): string
    {
        return number_format((float) $n, 0, ',', "\u{202F}");
    }

    private function page(): void
    {
        $l = $this->l;
        $l->page = $l->pdf->addPage($l->pw, $l->ph);
        $l->rect(0, 0, $l->pw, $l->ph, 'paper');
        $l->rect(0, 0, $l->pw, 6, 'yellow');
        $this->y = self::M + 10;
    }

    private function need(float $h): void
    {
        if ($this->y + $h > $this->l->ph - self::M) {
            $this->page();
        }
    }

    private function title(string $t): void
    {
        $this->need(60);
        $l = $this->l;
        $l->rect(self::M, $this->y, 22, 3.5, 'yellow');
        $l->text(self::M, $this->y + 22, mb_strtoupper($t), 'display', 15, 'navy', 0.6);
        $this->y += 34;
    }

    private function render(array $r, string $label): string
    {
        $this->l = $l = new Layout();
        $l->pw = 595.28;
        $l->ph = 841.89;
        $w = $l->pw - 2 * self::M;
        $this->page();
        // En-tête bleu nuit
        $l->rect(0, 0, $l->pw, 120, 'navy');
        $l->rect(0, 120, $l->pw, 4, 'yellow');
        $l->logo(self::M, 22, 76);
        $l->text(self::M + 80, 52, 'STATISTIQUES DU MUSÉE', 'display', 26, 'yellow', 0.5);
        $l->text(self::M + 80, 74, $label . ' · du ' . date('d/m/Y', strtotime($r['from'])) . ' au ' . date('d/m/Y', strtotime($r['to'])), 'serif-i', 11, 'cream');
        $l->text(self::M + 80, 92, 'Comparé aux ' . $r['len'] . ' jours précédents · rapport du ' . date('d/m/Y à H:i'), 'serif', 9, 'mist');
        $this->y = 146;
        // Chiffres clés
        $k = $r['kpi'];
        $p = $r['prev'];
        $tiles = [['visitors', 'visiteurs', $this->fmt($k['visitors']), false], ['visits', 'visites', $this->fmt($k['visits']), false], ['views', 'pages vues', $this->fmt($k['views']), false],
            ['ppv', 'pages par visite', str_replace('.', ',', (string) $k['ppv']), false], ['bounce', 'visites d’une page', $k['bounce'] . ' %', true], ['en_pct', 'en anglais', $k['en_pct'] . ' %', false]];
        $tw = ($w - 5 * 8) / 6;
        foreach ($tiles as $i => [$key, $lab, $val, $low]) {
            $x = self::M + $i * ($tw + 8);
            $l->rect($x, $this->y, $tw, 66, 'white', 'navy', 1.2);
            $l->text($x + 8, $this->y + 30, $val, 'display', 22, 'navy');
            $l->text($x + 8, $this->y + 45, $lab, 'serif', 8, 'muted');
            $d = $key === 'en_pct' ? null : StatsReport::delta($k[$key], $p[$key]);
            if ($d !== null) {
                $good = $low ? $d <= 0 : $d >= 0;
                $l->text($x + 8, $this->y + 58, ($d > 0 ? '+' : '') . $d . ' %', 'display-b', 8.5, $good ? 'green' : 'red');
            }
        }
        $this->y += 86;
        // À retenir
        $tips = StatsReport::tips($r);
        if ($tips) {
            $lines = [];
            foreach ($tips as $t) {
                $lines = array_merge($lines, $l->wrap([Layout::run('•  ' . $t, 'serif', 10, 'ink')], $w - 30, 1.35));
            }
            $h = array_sum(array_column($lines, 'h')) + 40;
            $l->rect(self::M, $this->y, $w, $h, 'butter', 'navy', 1);
            $l->text(self::M + 14, $this->y + 22, 'À RETENIR', 'display', 13, 'navy', 0.6);
            $l->drawLines($lines, self::M + 14, $this->y + 30, $w - 30);
            $this->y += $h + 20;
        }
        // Fréquentation
        $this->title('Fréquentation ' . ($r['step'] > 1 ? 'semaine par semaine' : 'jour par jour'));
        $ser = $r['series'];
        $ch = 150;
        $mx = max(1, max(array_map(fn ($x) => $x['views'], $ser ?: [['views' => 0]])));
        $n = max(1, count($ser));
        $bw = $w / $n;
        $i = 0;
        $every = max(1, (int) ceil($n / 8));
        foreach ($ser as $d => $x) {
            $bh = $x['views'] / $mx * ($ch - 20);
            $l->rect(self::M + $i * $bw + $bw * 0.12, $this->y + $ch - 14 - $bh, $bw * 0.76, $bh, 'yellow');
            $vh = $x['visitors'] / $mx * ($ch - 20);
            $l->rect(self::M + $i * $bw + $bw * 0.3, $this->y + $ch - 14 - $vh, $bw * 0.4, $vh, 'navy');
            if ($i % $every === 0) {
                $l->text(self::M + $i * $bw, $this->y + $ch, date('d/m', strtotime($d)), 'serif', 7, 'muted');
            }
            $i++;
        }
        $l->rect(self::M, $this->y + $ch - 14, $w, 0.8, 'line');
        $l->text(self::M + $w - 160, $this->y + 8, 'jaune : pages vues · bleu : visiteurs', 'serif-i', 8, 'muted');
        $this->y += $ch + 24;
        // Heures et appareils
        $this->title('Heures de visite et appareils');
        $hm = max(1, max($r['hours']));
        $hw = ($w * 0.6) / 24;
        foreach ($r['hours'] as $h => $c) {
            $bh = $c / $hm * 90;
            $l->rect(self::M + $h * $hw + 1, $this->y + 96 - $bh, $hw - 2, $bh, 'navy');
            if ($h % 6 === 0) {
                $l->text(self::M + $h * $hw, $this->y + 108, $h . ' h', 'serif', 7, 'muted');
            }
        }
        $dx = self::M + $w * 0.66;
        $dt = max(1, array_sum($r['dev']));
        $yy = $this->y + 10;
        foreach ($r['dev'] as $dv => $c) {
            $pct = $c / $dt;
            $l->text($dx, $yy + 10, (Stats::DEVICES[$dv] ?? $dv) . ' · ' . round($pct * 100) . ' %', 'display-b', 9, 'navy');
            $l->rect($dx, $yy + 14, $w * 0.34 * $pct, 7, $dv === 'mobile' ? 'yellow' : 'blue');
            $yy += 30;
        }
        $this->y += 128;
        // Décennies
        $this->title('Décennies les plus consultées');
        $dm = max(1, max($r['decades'] ?: [0]));
        $decs = range(1920, intdiv((int) date('Y'), 10) * 10, 10);
        $cw = $w / count($decs);
        foreach ($decs as $i => $dc) {
            $c = $r['decades'][(string) $dc] ?? 0;
            $bh = $c / $dm * 80;
            $l->rect(self::M + $i * $cw + 4, $this->y + 86 - $bh, $cw - 8, $bh, 'navy');
            $l->rect(self::M + $i * $cw + 4, $this->y + 86 - $bh, $cw - 8, 2.5, 'yellow');
            $l->text(self::M + $i * $cw + 6, $this->y + 98, "'" . substr((string) $dc, 2), 'display-b', 9, 'navy');
            if ($c) {
                $l->text(self::M + $i * $cw + 6, $this->y + 82 - $bh, $this->fmt($c), 'serif', 7, 'muted');
            }
        }
        $this->y += 116;
        // Classements, sur deux colonnes
        $lists = [['Top 10 joueurs', $r['players'], true], ['Top 10 matchs', $r['matches'], true], ['Top 10 récits et articles', $r['stories'], true],
            ['Top 10 rubriques', $r['sections'], false], ['Provenance des visites', $r['ref'], false], ['Jeux et expériences', $r['games'], false],
            ['Top 15 pages', $r['top'], true], ['Ce que les visiteurs cherchent', $r['searches'], false]];
        $col = ($w - 20) / 2;
        $this->need(300);
        $this->title('Classements');
        $ys = [$this->y, $this->y];
        foreach ($lists as $i => [$t, $list, $isPath]) {
            $c = $ys[0] <= $ys[1] ? 0 : 1;
            $x = self::M + $c * ($col + 20);
            $y = $ys[$c];
            $h = 26 + max(1, count($list)) * 16;
            if ($y + $h > $l->ph - self::M) {
                $this->page();
                $ys = [$this->y, $this->y];
                $c = 0;
                $x = self::M;
                $y = $this->y;
            }
            $l->text($x, $y + 12, mb_strtoupper($t), 'display-b', 10, 'navy', 0.8);
            $l->rect($x, $y + 16, $col, 1.2, 'yellow');
            $y += 26;
            $m = max(1, max($list ?: [0]));
            $rank = 0;
            foreach ($list as $key => $n) {
                $rank++;
                $name = $isPath ? StatsReport::label($r, (string) $key) : (string) $key;
                $name = mb_strimwidth($name, 0, 52, '…');
                $l->rect($x, $y + 10, $col * $n / $m, 2, 'butter');
                $l->text($x, $y + 9, $rank . '.', 'display-b', 8.5, 'blue');
                $l->text($x + 16, $y + 9, $name, 'serif', 8.5, 'ink');
                $nv = $this->fmt($n);
                $l->text($x + $col - $l->width($nv, 'display-b', 8.5), $y + 9, $nv, 'display-b', 8.5, 'navy');
                $y += 16;
            }
            if (!$list) {
                $l->text($x, $y + 9, 'Pas encore de données sur la période.', 'serif-i', 8.5, 'muted');
                $y += 16;
            }
            $ys[$c] = $y + 16;
        }
        $this->y = max($ys);
        $this->need(40);
        $nl = $l->wrap([Layout::run('Mesure interne sans cookie et sans adresse IP conservée : un visiteur est reconnu par une empreinte anonyme qui change chaque jour. Une visite s’arrête après 30 minutes sans page vue. Robots exclus.', 'serif-i', 8, 'muted')], $w, 1.3);
        $l->drawLines($nl, self::M, $this->y, $w);
        // Folios
        $total = $l->pdf->pageCount();
        for ($pg = 0; $pg < $total; $pg++) {
            $l->page = $pg;
            $f = 'Musée Sochaux Rétro · statistiques · ' . ($pg + 1) . ' / ' . $total;
            $l->text($l->pw - self::M - $l->width($f, 'serif', 8), $l->ph - 20, $f, 'serif', 8, 'muted');
        }
        return $l->pdf->output();
    }
}
