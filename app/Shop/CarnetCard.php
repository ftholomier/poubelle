<?php
declare(strict_types=1);

namespace App\Shop;

use App\Services\Carnet;
use App\Services\QuizChampionship;

/**
 * Carte du carnet du supporter : le bilan des matchs vus au stade, au format carte postale
 * (148 × 105 mm), en deux designs au choix : « a » (charte du musée, bleu nuit et jaune) et « b »
 * (billet de match d'époque, papier crème, talon détachable). Dessinée en calques du moteur
 * vectoriel de la boutique : la même carte sert au PDF téléchargé depuis le carnet et au calque
 * « Carte du carnet » des goodies (Vector::shapes, genre de poster « carte »).
 *
 * Pseudo : celui de la page publique du carnet, sinon celui du quiz et du défi du jour, sinon
 * rien (« Mon carnet de supporter »). Aucun appel à l'IA.
 */
final class CarnetCard
{
    public const W = 148.0;
    public const H = 105.0;
    public const STYLES = ['a' => 'Charte du musée', 'b' => 'Billet de match'];
    /** Matchs vus au minimum pour qu'une carte existe. */
    public const MIN = 1;

    private const NAVY = '#0E1F4D';
    private const BLUE = '#1E3FA8';
    private const YELLOW = '#F6C400';
    private const CREAM = '#F3EDDF';
    private const PAPER = '#F4EBD3';
    private const MIST = '#AAB4D2';
    private const PT = 72 / 25.4; // mm → points

    private array $L = [];
    private int $n = 0;

    private function __construct(private array $d)
    {
    }

    public static function style(?string $s): string
    {
        return isset(self::STYLES[(string) $s]) ? (string) $s : 'a';
    }

    /** Pseudo affiché pour ce carnet : page publique, sinon quiz et défi du jour, sinon ''. */
    public static function pseudo(array $c): string
    {
        $p = trim((string) ($c['pseudo'] ?? ''));
        if ($p === '') {
            $p = (string) (QuizChampionship::player((string) $c['id'])['pseudo'] ?? '');
        }
        return mb_substr($p, 0, 30);
    }

    /**
     * Ce que montre la carte, déjà rédigé dans la langue de la page. @return array|null (null : aucun match)
     */
    public static function data(array $ids, string $pseudo = ''): ?array
    {
        $s = Carnet::stats($ids);
        if ($s['n'] < self::MIN) {
            return null;
        }
        $y1 = substr((string) $s['first']['date'], 0, 4);
        $y2 = substr((string) $s['last']['date'], 0, 4);
        $nSeasons = count($s['seasons']);
        $period = $nSeasons <= 1
            ? t('saison {s}', ['s' => (string) $s['first']['season']])
            : t('de {a} à {b}', ['a' => $y1, 'b' => $y2]) . ' · ' . tn($nSeasons, '{n} saison', '{n} saisons');
        $score = fn (array $x): string => $x['home'] . ' ' . ($x['sh'] ? $x['us'] . '–' . $x['them'] : $x['them'] . '–' . $x['us']) . ' ' . $x['away'];
        $where = fn (array $x): string => date_fr((string) $x['date']) . ($x['sh'] ? ', ' . t('à Bonal') : '');
        $facts = [];
        $m = $s['best'] ?? null;
        $facts[] = $m ? [t('Mon plus beau match'), $score($m), $where($m)] : [t('Mon premier match'), $score($s['first']), $where($s['first'])];
        if ($s['scorers']) {
            $name = (string) array_key_first($s['scorers']);
            $facts[] = [t('Mon buteur'), $name, tn((int) $s['scorers'][$name], '{n} but vu au stade', '{n} buts vus au stade')];
        } elseif ($s['opps']) {
            $o = (string) array_key_first($s['opps']);
            $facts[] = [t('Mon adversaire'), $o, tn((int) $s['opps'][$o], 'vu {n} fois', 'vu {n} fois')];
        }
        if ($s['luck']) {
            $l = (int) $s['luck']['diff'];
            $facts[] = [t('Porte-bonheur'), tn(abs($l), '{s}{n} point', '{s}{n} points', ['s' => $l >= 0 ? '+' : '−']),
                self::nb(t('{m} % de victoires avec moi, {c} % pour le club', ['m' => $s['luck']['mine'], 'c' => $s['luck']['club']]))];
        } else {
            $facts[] = [t('La suite ?'), t('Cochez vos matchs'), t('porte-bonheur calculé dès 5 matchs')];
        }
        $on = count(array_filter($s['badges'], fn ($b) => $b['on']));
        $total = max(1, $s['v'] + $s['nul'] + $s['d']);
        return [
            'pseudo' => trim($pseudo), 'n' => $s['n'], 'v' => $s['v'], 'nul' => $s['nul'], 'd' => $s['d'],
            'matches' => $s['n'] > 1 ? t('matchs vus au stade') : t('match vu au stade'),
            'period' => $period,
            'tiles' => [[$s['v'], tn($s['v'], 'victoire', 'victoires')], [$s['nul'], tn($s['nul'], 'match nul', 'matchs nuls')], [$s['d'], tn($s['d'], 'défaite', 'défaites')]],
            'goals' => [tn($s['gf'], '{n} but', '{n} buts'), t('du FCSM vus'), tn($s['ga'], '{n} encaissé', '{n} encaissés')],
            'shares' => [$s['v'] / $total, $s['nul'] / $total, $s['d'] / $total],
            'pct' => self::nb(t('{p} % de victoires', ['p' => (int) round(100 * $s['v'] / $total)])),
            'badges' => tn($on, '{n} badge gagné sur {t}', '{n} badges gagnés sur {t}', ['t' => count($s['badges'])]),
            'facts' => array_slice($facts, 0, 3),
            'host' => mb_strtoupper((string) (parse_url(base_url(), PHP_URL_HOST) ?: 'musee.fcsochauxretro.com')),
        ];
    }

    /** Espace insécable devant « % » : le nombre et son signe restent sur la même ligne. */
    private static function nb(string $t): string
    {
        return str_replace(' %', "\u{00A0}%", $t);
    }

    /** Calques de la carte (mm, cadre 148 × 105 à l'origine). @return list<array> */
    public static function build(array $d, string $style = 'a'): array
    {
        $c = new self($d);
        self::style($style) === 'b' ? $c->ticket() : $c->museum();
        return $c->L;
    }

    /**
     * Calque « Carte du carnet » d'un modèle de la boutique (goodies) : la carte du client, dans le
     * cadre du calque. Matchs : la liste figée dans la commande (_carnet_ids), sinon le carnet
     * choisi (le sien sur l'appareil, ou une page publique), sinon l'exemple. @return list<array>
     */
    public static function layers(array $frame, array $values): array
    {
        $frozen = (string) ($values['_carnet_ids'] ?? '');
        $id = (string) ($values[CarnetPoster::FIELD] ?? '');
        $ids = $frozen !== '' ? array_map('intval', explode(',', $frozen)) : (CarnetPoster::idsFor($id) ?? null);
        if (array_key_exists('_carnet_pseudo', $values)) {
            $pseudo = (string) $values['_carnet_pseudo'];
        } else {
            $c = $ids && $id !== CarnetPoster::SAMPLE ? Carnet::get($id) : null;
            $pseudo = $c ? self::pseudo($c) : 'Lionceau88'; // l'exemple a un pseudo
        }
        $d = ($ids ? self::data($ids, $pseudo) : null) ?? self::data(CarnetPoster::idsFor(CarnetPoster::SAMPLE) ?? [], 'Lionceau88');
        if (!$d) {
            return [];
        }
        $style = self::style((string) ($frame['style'] ?? 'a'));
        $L = self::build($d, $style);
        // Fond de la carte (le calque ne compte pas sur le fond de la face du produit).
        array_unshift($L, ['id' => 'cbg', 'type' => 'rect', 'x' => 0, 'y' => 0, 'w' => self::W, 'h' => self::H, 'fill' => $style === 'b' ? self::PAPER : self::NAVY, 'stroke' => '', 'sw' => 0, 'r' => 0]);
        return self::fit($L, $frame);
    }

    /** Le modèle porte-t-il une carte du carnet ? */
    public static function isFor(array $model): bool
    {
        foreach ($model['faces'] as $f) {
            foreach ($f['layers'] as $l) {
                if (($l['type'] ?? '') === 'carte') {
                    return true;
                }
            }
        }
        return false;
    }

    /** Calques de la carte dans un cadre quelconque (proportions gardées, centrée). */
    public static function fit(array $L, array $frame): array
    {
        $fw = max(10.0, (float) ($frame['w'] ?? self::W));
        $fh = max(7.0, (float) ($frame['h'] ?? self::H));
        $k = min($fw / self::W, $fh / self::H);
        $ox = (float) ($frame['x'] ?? 0) + ($fw - self::W * $k) / 2;
        $oy = (float) ($frame['y'] ?? 0) + ($fh - self::H * $k) / 2;
        foreach ($L as &$l) {
            $l['x'] = round($ox + $l['x'] * $k, 3);
            $l['y'] = round($oy + $l['y'] * $k, 3);
            $l['w'] = round($l['w'] * $k, 3);
            foreach (['h', 'size', 'min', 'sw', 'r'] as $p) {
                if (isset($l[$p])) {
                    $l[$p] = round($l[$p] * $k, 3);
                }
            }
        }
        return $L;
    }

    private static function side(array $d, string $style): array
    {
        return ['w' => self::W, 'h' => self::H, 'bleed' => 0, 'bg' => self::style($style) === 'b' ? self::PAPER : self::NAVY, 'layers' => self::build($d, $style)];
    }

    /** Aperçu SVG de la carte (page du carnet). */
    public static function svg(array $d, string $style = 'a'): string
    {
        return Vector::svg(self::side($d, $style));
    }

    /** PDF de la carte (RVB, sans traits de coupe) : ce que télécharge le supporter. */
    public static function pdf(array $d, string $style = 'a'): string
    {
        return Vector::pdf([['name' => '', 'side' => self::side($d, $style)]], ['cmyk' => false, 'marks' => false, 'title' => t('Mon carnet de supporter') . ' · Sochaux Rétro']);
    }

    // ------------------------------------------------------------------ primitives

    private function rect(float $x, float $y, float $w, float $h, string $fill = '', string $stroke = '', float $sw = 0, float $r = 0): void
    {
        $this->L[] = ['id' => 'c' . (++$this->n), 'type' => 'rect', 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'fill' => $fill, 'stroke' => $stroke, 'sw' => $sw, 'r' => $r];
    }

    /** Texte : taille en mm (corps du caractère), comme dans la maquette. */
    private function text(float $x, float $y, float $w, string $t, string $font, float $mm, string $color, array $o = []): array
    {
        $l = ['id' => 'c' . (++$this->n), 'type' => 'text', 'x' => $x, 'y' => $y, 'w' => max(1.0, $w), 'text' => $t, 'font' => $font, 'size' => $mm * self::PT, 'color' => $color,
            'align' => $o['align'] ?? 'left', 'upper' => $o['upper'] ?? false, 'spacing' => $o['spacing'] ?? 0, 'lh' => $o['lh'] ?? 1.1,
            'h' => $o['h'] ?? 0, 'fit' => $o['fit'] ?? true, 'min' => ($o['min'] ?? $mm * 0.55) * self::PT, 'valign' => $o['valign'] ?? 'top', 'mode' => 'fixed'];
        if (empty($o['dry'])) {
            $this->L[] = $l;
        }
        return $l;
    }

    private static function width(string $t, string $font, float $mm, float $spacing = 0): float
    {
        $f = Vector::font($font);
        return ($f->width($t) / 1000 + $spacing / 1000 * max(0, mb_strlen($t) - 1)) * $mm;
    }

    private function logo(float $x, float $y, float $w): void
    {
        $this->L[] = ['id' => 'c' . (++$this->n), 'type' => 'logo', 'x' => $x, 'y' => $y, 'w' => $w, 'style' => 'couleurs', 'color' => '#FDC729'];
    }

    /** Barre victoires / nuls / défaites. */
    private function bar(float $x, float $y, float $w, float $h, array $cols, string $frame, float $fw): void
    {
        $this->rect($x - $fw, $y - $fw, $w + 2 * $fw, $h + 2 * $fw, $frame);
        $pen = $x;
        foreach ($this->d['shares'] as $k => $p) {
            $seg = $w * $p;
            if ($seg > 0.05) {
                $this->rect($pen, $y, $seg, $h, $cols[$k]);
            }
            $pen += $seg;
        }
    }

    // ------------------------------------------------------------------ design A : charte du musée

    private function museum(): void
    {
        $d = $this->d;
        $this->rect(0, 0, self::W, self::H, self::NAVY);
        $this->rect(0, 0, self::W, 2.2, self::YELLOW);
        // Colonne du blason : le logo en grand, puis « Mon carnet de supporter » et le pseudo.
        $this->logo(9, 9, 34);
        $ky = 9 + 34 * 1407 / 1242 + 3.2;
        $kicker = $d['pseudo'] !== '' ? 3.3 : 4.2;
        $this->text(7, $ky, 38, (string) preg_replace('/ (de supporter)$/u', "\n$1", t('Mon carnet de supporter')), 'display-b', $kicker, self::YELLOW, ['align' => 'center', 'upper' => true, 'spacing' => 97, 'lh' => 1.02, 'fit' => false]);
        if ($d['pseudo'] !== '') {
            $this->text(7, $ky + 2 * $kicker * 1.02 + 1.4, 38, $d['pseudo'], 'display', 5.6, self::CREAM, ['align' => 'center', 'upper' => true, 'h' => 12, 'lh' => 1.0, 'min' => 3.6]);
        }
        // Le nombre de matchs, puis le bilan.
        $x = 51.0;
        $w = self::W - 7 - $x;
        $big = (string) $d['n'];
        $bs = min(31.0, 31.0 * 38 / max(1.0, self::width($big, 'display', 31)));
        $bw = self::width($big, 'display', $bs);
        $this->text($x - 0.6, 8.4 + (31 - $bs) * 0.7, $bw + 2, $big, 'display', $bs, self::CREAM, ['lh' => 0.86, 'fit' => false]);
        $hx = $x + $bw + 3;
        $this->text($hx, 14.2, $x + $w - $hx, str_replace(' au ', "\nau ", $d['matches']), 'display', 7.4, self::YELLOW, ['upper' => true, 'lh' => 0.92, 'h' => 14]);
        $this->text($hx, 28.8, $x + $w - $hx, $d['period'], 'serif-i', 3.6, self::CREAM);
        // Trois cases : victoires, nuls, défaites (en toutes lettres).
        $tw = ($w - 2 * 1.8) / 3;
        $ty = 38.0;
        foreach ($d['tiles'] as $k => [$num, $lab]) {
            $tx = $x + $k * ($tw + 1.8);
            [$bg, $fg] = [[self::YELLOW, self::NAVY], [self::CREAM, self::NAVY], [self::BLUE, self::CREAM]][$k];
            $this->rect($tx, $ty, $tw, 14.2, $bg, $k === 2 ? '#5C6FB8' : '', $k === 2 ? 0.3 : 0, 1);
            $this->text($tx + 2.2, $ty + 1.6, $tw - 4, (string) $num, 'display', 8.6, $fg, ['lh' => 0.95]);
            $this->text($tx + 2.2, $ty + 10.2, $tw - 4, $lab, 'display-b', 2.9, $fg, ['upper' => true, 'spacing' => 86]);
        }
        // Buts vus, barre, pourcentage et badges.
        [$g1, $g2, $g3] = $d['goals'];
        $gy = $ty + 14.2 + 3.2;
        $w1 = self::width(mb_strtoupper($g1), 'display-b', 4.4, 34) + 1.2;
        $this->text($x, $gy, $w1 + 0.5, $g1, 'display-b', 4.4, self::YELLOW, ['upper' => true, 'spacing' => 34]);
        $this->text($x + $w1, $gy, $w - $w1, $g2 . ' · ' . $g3, 'display-b', 4.4, self::CREAM, ['upper' => true, 'spacing' => 34]);
        $this->bar($x, $gy + 8.4, $w, 3.0, [self::YELLOW, self::CREAM, self::BLUE], self::CREAM, 0.35);
        $this->text($x, $gy + 13.0, $w / 2, $d['pct'], 'display-b', 2.6, self::MIST, ['upper' => true, 'spacing' => 96]);
        $this->text($x + $w / 2, $gy + 13.0, $w / 2, $d['badges'], 'display-b', 2.6, self::MIST, ['upper' => true, 'spacing' => 96, 'align' => 'right']);
        // Bandeau crème : plus beau match, buteur, porte-bonheur.
        $fy = self::H - 24;
        $this->rect(0, $fy, self::W, 24, self::CREAM);
        $cols = [7.0, 58.0, 99.0];
        $widths = [47.0, 37.0, 42.0];
        foreach ($d['facts'] as $k => [$kick, $main, $sub]) {
            $fx = $cols[$k];
            $this->text($fx, $fy + 3.2, $widths[$k], $kick, 'display-b', 2.6, self::BLUE, ['upper' => true, 'spacing' => 115]);
            // Titre long (« Sochaux 7–0 Jeunesse d'Esch ») : réduit, puis sur deux lignes ; la ligne du dessous descend d'autant.
            $mh = Vector::textHeight($this->text($fx, $fy + 6.6, $widths[$k], $main, 'display', 4.2, self::NAVY, ['upper' => true, 'min' => 3.0]));
            $sy = $fy + 6.6 + max(4.6, $mh + 0.4);
            $this->text($fx, $sy, $widths[$k], $sub, 'serif', 2.9, self::NAVY, ['lh' => 1.2, 'h' => $fy + 19.2 - $sy, 'min' => 2.3]);
        }
        $this->text(self::W - 7 - 60, self::H - 3.9, 60, $d['host'], 'display-b', 2.3, self::BLUE, ['align' => 'right', 'spacing' => 150]);
    }

    // ------------------------------------------------------------------ design B : billet de match

    private function ticket(): void
    {
        $d = $this->d;
        $this->rect(0, 0, self::W, self::H, self::PAPER);
        // Partie principale : cadre double et bandeau du stade.
        $mx = 3.5;
        $mw = self::W - 41 - $mx;
        $this->rect($mx, 3.5, $mw, self::H - 7, '', self::NAVY, 0.5, 1.2);
        $this->rect($mx + 1, 4.5, $mw - 2, self::H - 9, '', self::NAVY, 0.2, 0.8);
        $this->rect($mx, 3.5, $mw, 9, self::NAVY, '', 0, 1.2);
        $this->text($mx + 4, 6.4, $mw - 30, t('FC Sochaux-Montbéliard · Stade Auguste-Bonal'), 'display-b', 3.2, self::YELLOW, ['upper' => true, 'spacing' => 110]);
        $this->text($mx + $mw - 26, 6.6, 22, 'Sochaux Rétro', 'display-b', 2.9, self::CREAM, ['upper' => true, 'spacing' => 110, 'align' => 'right']);
        $x = 9.0;
        $w = $mw + $mx - 5.5 - $x;
        $y = 16.0;
        if ($d['pseudo'] !== '') {
            $this->text($x, $y, $w, t('Carnet de supporter de'), 'display-b', 3.2, self::BLUE, ['upper' => true, 'spacing' => 125]);
            $this->text($x, $y + 4.2, $w, $d['pseudo'], 'display', 6.6, self::NAVY, ['upper' => true, 'min' => 4]);
            $y += 12.4;
        } else {
            $this->text($x, $y, $w, t('Mon carnet de supporter'), 'display', 6.6, self::NAVY, ['upper' => true]);
            $y += 8.6;
        }
        $big = (string) $d['n'];
        $bs = min(22.0, 22.0 * 26 / max(1.0, self::width($big, 'display', 22)));
        $bw = self::width($big, 'display', $bs);
        $this->text($x - 0.4, $y + (22 - $bs) * 0.7, $bw + 2, $big, 'display', $bs, self::NAVY, ['lh' => 0.86, 'fit' => false]);
        $hx = $x + $bw + 2.4;
        $this->text($hx, $y + 7.6, $x + $w - $hx, $d['matches'], 'display', 6.2, self::NAVY, ['upper' => true, 'lh' => 0.95]);
        $this->text($hx, $y + 14.0, $x + $w - $hx, $d['period'], 'serif-i', 3.4, self::NAVY);
        // Trois tampons.
        $sy = $y + 20.6;
        $sw = ($w - 2 * 1.8) / 3;
        foreach ($d['tiles'] as $k => [$num, $lab]) {
            $sx = $x + $k * ($sw + 1.8);
            [$bg, $fg] = [[self::YELLOW, self::NAVY], ['#FBF6EA', self::NAVY], [self::NAVY, self::CREAM]][$k];
            $this->rect($sx, $sy, $sw, 11.2, $bg, self::NAVY, 0.45, 0.8);
            $this->text($sx, $sy + 1.0, $sw, (string) $num, 'display', 7.6, $fg, ['align' => 'center', 'lh' => 0.95]);
            $this->text($sx, $sy + 7.9, $sw, $lab, 'display-b', 2.7, $fg, ['align' => 'center', 'upper' => true, 'spacing' => 92]);
        }
        $this->bar($x, $sy + 14.0, $w, 2.2, [self::YELLOW, '#FFFFFF', self::NAVY], self::NAVY, 0.4);
        // Plus beau match, buteur, porte-bonheur.
        $fy = $sy + 19.6;
        $fw = [$w * 0.42, $w * 0.27, $w * 0.31];
        $fx = $x;
        foreach ($d['facts'] as $k => [$kick, $main, $sub]) {
            $this->text($fx, $fy, $fw[$k] - 2.5, $kick, 'display-b', 2.5, self::BLUE, ['upper' => true, 'spacing' => 120]);
            $mh = Vector::textHeight($this->text($fx, $fy + 3.0, $fw[$k] - 2.5, $main, 'display-b', 3.4, self::NAVY, ['upper' => true, 'min' => 2.6]));
            $sy = $fy + 3.0 + max(4.0, $mh + 0.3);
            $this->text($fx, $sy, $fw[$k] - 2.5, $sub, 'serif', 2.8, self::NAVY, ['lh' => 1.15, 'h' => $fy + 14.0 - $sy, 'min' => 2.2]);
            $fx += $fw[$k];
        }
        [$g1, $g2, $g3] = $d['goals'];
        $gw = self::width(mb_strtoupper($g1), 'display-b', 3.2, 60) + 1;
        $this->text($x, self::H - 10.4, $gw + 0.5, $g1, 'display-b', 3.2, self::BLUE, ['upper' => true, 'spacing' => 60]);
        $this->text($x + $gw, self::H - 10.4, $w - $gw, $g2 . ' · ' . $g3, 'display-b', 3.2, self::NAVY, ['upper' => true, 'spacing' => 60]);
        // Pointillés de découpe et talon : le blason en grand, le numéro du billet.
        for ($py = 1.0; $py < self::H; $py += 2.2) {
            $this->rect(self::W - 37.6, $py, 0.3, 1.2, '#7D84A0');
        }
        $tx = self::W - 33.5;
        $this->rect($tx, 3.5, 30, self::H - 7, '', self::NAVY, 0.5, 1.2);
        $this->logo($tx + 4, 8.5, 22);
        $this->text($tx, 37.5, 30, t('Billet n°'), 'display-b', 3.0, self::NAVY, ['align' => 'center', 'upper' => true, 'spacing' => 130]);
        $this->text($tx, 41.6, 30, sprintf('%04d', $d['n']), 'display', 7.0, self::BLUE, ['align' => 'center']);
        $this->text($tx + 2, 52, 26, t('Tribune de mes souvenirs'), 'display-b', 2.5, self::NAVY, ['align' => 'center', 'upper' => true, 'spacing' => 120, 'lh' => 1.15, 'h' => 7]);
        $this->text($tx + 1, self::H - 11.5, 28, str_replace('.COM', "\n.COM", $d['host']), 'display-b', 2.1, self::NAVY, ['align' => 'center', 'spacing' => 80, 'lh' => 1.2, 'h' => 6]);
    }
}
