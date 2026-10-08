<?php
declare(strict_types=1);

namespace App\Pdf;

use App\Data\Names;
use App\Front\Fiche;
use App\Shop\Poster;
use App\Shop\PosterLayout;
use App\Shop\Vector;

/**
 * Le poster du match de la boutique, repris en pleine page dans le livre (« Mon match »,
 * « Le jour de ta naissance »). Depuis une fiche du musée : le poster tel quel. Depuis une feuille
 * de match de l'association (pas encore de fiche) : les mêmes blocs remplis par la feuille seule
 * (affiche, compo, film des buts, fiche technique, récit factuel), rien d'inventé.
 */
class LivrePoster extends PosterLayout
{
    /** Calques (mm, A3 297 × 420) du poster, ou [] si le match n'a pas assez de matière. */
    public static function build4(int $id, ?array $sheet, string $pour, string $no = ''): array
    {
        if ($id > 0 && ($d = Poster::data((string) $id))) {
            return (new PosterLayout($d, $pour, $no))->build();
        }
        if (!$sheet) {
            return [];
        }
        $d = self::fromSheet($sheet);
        return $d ? (new self($d, $pour, $no))->build() : [];
    }

    /** Données du poster tirées d'une feuille de match. */
    public static function fromSheet(array $f): ?array
    {
        $rows = array_map(fn ($r) => ['position' => (string) ($r['position'] ?? ''), 'name' => (string) ($r['name'] ?? ''), 'captain' => !empty($r['captain'])], (array) ($f['lineup'] ?? []));
        $pitch = Fiche::pitch($rows);
        if (!$pitch || count($pitch['players']) < 11 || !isset($f['score_home'], $f['score_away'])) {
            return null;
        }
        foreach ($pitch['players'] as &$p) {
            $p['short'] = mb_strtoupper(Names::lineupLastName($p['name']) ?: $p['name']);
            $p['display'] = $p['name'];
        }
        unset($p);
        $clean = fn (string $t) => trim((string) preg_replace('/\s*\(.*\)\s*$/u', '', $t));
        $home = $clean((string) ($f['home'] ?? ''));
        $away = $clean((string) ($f['away'] ?? ''));
        $sh = (int) $f['score_home'];
        $sa = (int) $f['score_away'];
        $soch = !empty($f['sochaux_home']) || stripos($home, 'sochaux') !== false;
        // Le film : les buts minute par minute, la mi-temps et le coup de sifflet final.
        $film = [];
        foreach ((array) ($f['goals_by_team'] ?? []) as $g) {
            preg_match_all('/([^,()]+?)\s*\((\d+)e\s*([^)]*)\)/u', (string) ($g['text'] ?? ''), $m, PREG_SET_ORDER);
            foreach ($m as [, $who, $min, $note]) {
                $note = trim($note);
                $film[] = ['n' => (int) $min, 'min' => $min . "'", 'goal' => true,
                    'text' => 'But de ' . trim($who) . ($note === 'sp' ? ' sur penalty' : ($note === 'csc' ? ' contre son camp' : '')) . ' (' . trim((string) ($g['team'] ?? '')) . ').'];
            }
        }
        if (!$film) {
            foreach ((array) ($f['scorers'] ?? []) as $s) {
                foreach ((array) ($s['minutes'] ?? []) as $min) {
                    $film[] = ['n' => (int) $min, 'min' => $min . "'", 'goal' => true, 'text' => 'But de ' . $s['name'] . (($s['note'] ?? '') === 'sp' ? ' sur penalty' : '') . '.'];
                }
            }
        }
        if (preg_match('/^(\d+)-(\d+)$/', (string) ($f['half_time'] ?? ''), $ht)) {
            $film[] = ['n' => 45, 'min' => 'MT', 'goal' => false, 'end' => true, 'text' => 'Mi-temps : ' . $ht[1] . '-' . $ht[2] . '.'];
        }
        usort($film, fn ($a, $b) => [$a['n'], $a['goal'] ? 0 : 1] <=> [$b['n'], $b['goal'] ? 0 : 1]);
        $film[] = ['min' => 'FIN', 'goal' => false, 'end' => true, 'text' => 'Score final : ' . $home . ' ' . $sh . ', ' . $away . ' ' . $sa . '.'];
        $date = (string) ($f['date'] ?? '');
        $y = (int) substr($date, 0, 4);
        $season = (int) substr($date, 5, 2) >= 7 ? $y . '-' . ($y + 1) : ($y - 1) . '-' . $y;
        $stadium = $clean((string) ($f['stadium'] ?? ''));
        $comp = trim((string) ($f['competition'] ?? ''));
        $round = trim((string) ($f['round'] ?? ''));
        $coach = trim((string) ($f['coach'] ?? ''));
        $spect = (int) ($f['spectators'] ?? 0);
        // Récit factuel, phrase par phrase, d'après la feuille.
        $us = $soch ? $sh : $sa;
        $them = $soch ? $sa : $sh;
        $opp = $soch ? $away : $home;
        $res = $us > $them ? 'Sochaux s’impose ' . $us . '-' . $them . ' face à ' . $opp : ($us < $them ? 'Sochaux s’incline ' . $us . '-' . $them . ' face à ' . $opp : 'Sochaux et ' . $opp . ' se quittent sur un nul, ' . $us . '-' . $them);
        $t = 'Le ' . date_fr($date) . ($comp !== '' ? ', en ' . $comp . ($round !== '' ? ' (' . $round . ')' : '') : '') . ($stadium !== '' ? ', au ' . $stadium : '') . ' : ' . $res . '.';
        if ($spect > 0) {
            $t .= ' ' . number_format($spect, 0, ',', ' ') . ' spectateurs assistent à la rencontre.';
        }
        if (!empty($f['referee'])) {
            $t .= ' L’arbitre est ' . $clean((string) $f['referee']) . '.';
        }
        if (!empty($ht)) {
            $t .= ' À la pause, le score est de ' . $ht[1] . '-' . $ht[2] . '.';
        }
        $goals = array_filter($film, fn ($x) => $x['goal']);
        if ($goals) {
            $t .= ' Les buts : ' . implode(', ', array_map(fn ($x) => rtrim(preg_replace('/^But de /u', '', $x['text']), '.') . ' à la ' . $x['n'] . 'e minute', $goals)) . '.';
        }
        $names = array_map(fn ($p) => $p['name'] . (!empty($p['captain']) ? ' (capitaine)' : ''), $pitch['players']);
        $t .= ' Sochaux aligne ' . implode(', ', array_slice($names, 0, -1)) . ' et ' . end($names) . ($coach !== '' ? ', sous les ordres de ' . $coach : '') . ', en ' . $pitch['formation'] . '.';
        return [
            'id' => '0', 'dm' => ['season' => $season, 'label' => $comp, 'date' => $date],
            'kicker' => implode(' · ', array_filter([$comp, $round, $stadium])),
            'home' => $home, 'away' => $away, 'sh' => $sh, 'sa' => $sa,
            'when' => date_fr($date), 'extra' => '', 'date' => $date, 'formation' => $pitch['formation'],
            'players' => array_slice($pitch['players'], 0, 11), 'coach' => $coach, 'film' => $film, 'spectators' => $spect,
            'figure' => null, 'quotes' => [], 'season' => self::seasonOf($date, $season), 'recit' => $t, 'anecdote' => null, 'enjeu' => '', 'opp_level' => '',
            'facts' => array_filter(['Stade' => $stadium, 'Arbitre' => $clean((string) ($f['referee'] ?? '')), 'Entraîneur' => $coach, 'Système' => $pitch['formation'], 'Saison' => $season, 'Mi-temps' => $ht[0] ?? '']),
        ];
    }

    protected function facts(float $x, float $y, float $w, float $h): void
    {
        if (!empty($this->used['facts'])) {
            // La fiche technique est déjà sur le poster : ici, la feuille de match complète.
            $this->sheet($x, $y, $w, $h);
            return;
        }
        $this->used['facts'] = true;
        $rows = $this->d['facts'];
        $this->rect($x, $y, $w, $h, self::CREAM);
        $ix = $x + 4.5;
        $iw = $w - 9;
        $ty = $this->kicker($ix, $y + 4, $iw, 'La fiche du match', self::BLUE);
        $rh = min(9.0, ($y + $h - 4 - $ty) / max(1, count($rows)));
        $i = 0;
        foreach ($rows as $k => $v) {
            $ry = $ty + $i++ * $rh;
            $this->text($ix, $ry, $iw * 0.36, $k, 'display-b', self::pt(14), self::BLUE, ['upper' => true, 'spacing' => 120, 'fit' => true]);
            $this->text($ix + $iw * 0.38, $ry - 0.3, $iw * 0.62, (string) $v, 'serif-b', self::pt(16), self::NAVY, ['fit' => true]);
            $this->rect($ix, $ry + $rh - 1.6, $iw, 0.25, '#D9CFB8');
        }
    }

    /** Les feuilles de match de l'association, par date. */
    private static function sheets(): array
    {
        static $all = null;
        if ($all === null) {
            $all = [];
            foreach ((array) json_decode((string) gzdecode((string) file_get_contents(APP_DIR . '/Resources/import/feuilles-de-match.json.gz')), true) as $f) {
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($f['date'] ?? '')) && isset($f['score_home'], $f['score_away'])) {
                    $all[] = $f;
                }
            }
            usort($all, fn ($a, $b) => strcmp($a['date'], $b['date']));
        }
        return $all;
    }

    /** Sochaux : [adversaire, buts pour, buts contre] d'une feuille. */
    private static function us(array $f): array
    {
        $clean = fn (string $t) => trim((string) preg_replace('/\s*\(.*\)\s*$/u', '', $t));
        $home = !empty($f['sochaux_home']) || stripos((string) $f['home'], 'sochaux') !== false;
        return $home ? [$clean((string) $f['away']), (int) $f['score_home'], (int) $f['score_away']] : [$clean((string) $f['home']), (int) $f['score_away'], (int) $f['score_home']];
    }

    /** Bilan de la saison (toutes compétitions) d'après les feuilles. */
    public static function seasonOf(string $date, string $season): ?array
    {
        [$y0] = explode('-', $season);
        $w = $dr = $l = $gf = $ga = 0;
        foreach (self::sheets() as $f) {
            if ($f['date'] >= $y0 . '-07-01' && $f['date'] <= ((int) $y0 + 1) . '-06-30') {
                [, $a, $b] = self::us($f);
                $a > $b ? $w++ : ($a < $b ? $l++ : $dr++);
                $gf += $a;
                $ga += $b;
            }
        }
        return $w + $dr + $l >= 10 ? ['season' => $season, 'level' => 'toutes compétitions', 'w' => $w, 'd' => $dr, 'l' => $l, 'gf' => $gf, 'ga' => $ga] : null;
    }

    /** Les matchs de Sochaux autour de celui-ci (feuilles de l'association), celui du jour en avant. */
    protected function sheet(float $x, float $y, float $w, float $h): void
    {
        $all = self::sheets();
        $date = (string) $this->d['date'];
        $i0 = 0;
        foreach ($all as $i => $f) {
            if ($f['date'] === $date) {
                $i0 = $i;
            }
        }
        $list = array_slice($all, max(0, $i0 - 2), 5);
        $this->rect($x, $y, $w, $h, self::CREAM);
        $ix = $x + 4.5;
        $iw = $w - 9;
        $ty = $this->kicker($ix, $y + 4, $iw, 'Sochaux, ces semaines-là', self::BLUE);
        $rh = min(8.0, ($y + $h - 4 - $ty) / max(1, count($list)));
        foreach ($list as $k => $f) {
            [$opp, $a, $b] = self::us($f);
            $ry = $ty + $k * $rh;
            $me = $f['date'] === $date;
            if ($me) {
                $this->rect($ix - 1.5, $ry - 1.2, $iw + 3, $rh - 0.6, self::YELLOW);
            }
            $res = $a > $b ? 'V' : ($a < $b ? 'D' : 'N');
            $this->text($ix, $ry, $iw * 0.2, implode('/', array_reverse(explode('-', $f['date']))), 'display-b', self::pt(13), self::BLUE, ['fit' => true]);
            $this->text($ix + $iw * 0.22, $ry - 0.3, $iw * 0.5, (empty($f['sochaux_home']) ? 'à ' : 'contre ') . $opp, $me ? 'serif-b' : 'serif', self::pt(15), self::NAVY, ['fit' => true]);
            $this->text($ix + $iw * 0.74, $ry - 0.3, $iw * 0.26, $res . '  ' . $a . '-' . $b . '  ' . (string) ($f['competition'] ?? ''), 'display-b', self::pt(13), self::NAVY, ['fit' => true]);
        }
    }

    protected function recitKicker(): string
    {
        return 'Le match · d’après la feuille de match de l’association';
    }

    protected function footer(): void
    {
        $s = self::pt(13);
        $this->text(self::PAD, self::H - self::PAD - 3.6, 260, 'Faits tirés de la feuille de match conservée par l’association · musée Sochaux Rétro', 'serif-i', $s, self::MIST);
    }

    /** Dessine les calques (mm) sur la page courante : origine ($ox, $oy) et échelle $k en points. */
    public static function draw(Layout $l, array $layers, float $ox, float $oy, float $k): void
    {
        foreach (Vector::shapes($layers) as $sh) {
            $l->vectorPath($sh, $ox, $oy, $k);
        }
    }
}
