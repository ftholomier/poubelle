<?php
declare(strict_types=1);

namespace App\Services;

use App\Data\Derived;
use App\Data\Index;
use App\Front\Explore;

/**
 * Pages de synthèse racontées à voix haute : face-à-face, bilans (compétition, stade), saisons,
 * livre des records et chiffres du FCSM.
 *
 * Ces pages sont calculées depuis toutes les fiches matchs ; leur récit l'est aussi, à chaque
 * affichage : une accroche, le bilan, les faits marquants (première et dernière rencontre, plus
 * large victoire, plus lourde défaite, affluence, buteurs, séries…), une conclusion. En français
 * ou en anglais, dans la durée maximale des fiches audio (Réglages › Fiches audio). Toujours à
 * jour et gratuit : la voix du navigateur le lit (bouton « Écouter », comme sur les fiches).
 * Seuls les faits calculés sont dits : rien n'est inventé.
 */
final class PageAudio
{
    /**
     * Bouton « Écouter » (même forme que FicheAudio::forPage) pour des paragraphes, chacun une
     * liste de phrases ; null si l'audio est désactivé ou le texte vide.
     */
    public static function forPage(array $paras, bool $en): ?array
    {
        if (!FicheAudio::enabled()) {
            return null;
        }
        $out = [];
        foreach ($paras as $p) {
            $p = trim((string) preg_replace('/\s+/u', ' ', implode(' ', array_filter((array) $p, fn ($s) => trim((string) $s) !== ''))));
            if ($p !== '') {
                $out[] = self::speakable($p, $en);
            }
        }
        $text = FicheAudio::fitText(implode("\n\n", $out), FicheAudio::maxWords());
        if ($text === '') {
            return null;
        }
        return ['text' => $text, 'url' => null, 'lang' => FicheAudio::LANGS[$en ? 'en' : 'fr'], 'dur' => null,
            'secs' => (int) round(FicheAudio::words($text) / FicheAudio::WPM * 60)];
    }

    // ------------------------------------------------------------------ face-à-face

    /** Face-à-face contre un club. $v : variables de la page (Explore::opponentData). */
    public static function opponent(string $name, array $v, bool $en): ?array
    {
        $chrono = self::chrono($v['list'] ?? []);
        $t = $v['t'] ?? [];
        $n = (int) ($t['count'] ?? 0);
        if (!$chrono || $n === 0) {
            return null;
        }
        $first = $chrono[0];
        $last = $chrono[count($chrono) - 1];
        [$y1, $y2] = [self::year($first), self::year($last)];
        [$V, $N, $D] = [(int) $t['V'], (int) $t['N'], (int) $t['D']];

        if ($n === 1) {
            return self::forPage([[
                $en ? "Sochaux and $name have met only once, " . self::on($first, $en) . ', ' . self::inComp($first, $en) . ': ' . self::result($first, $en) . '.'
                    : "Sochaux et $name ne se sont affrontés qu’une seule fois, " . self::on($first, $en) . ', ' . self::inComp($first, $en) . ' : ' . self::result($first, $en) . '.',
                $en ? 'A single chapter, to relive in its match page.' : 'Un chapitre unique, à revivre dans sa fiche de match.',
            ]], $en);
        }

        $intro = [];
        if ($n >= 30) {
            $intro[] = $en ? "Between Sochaux and $name, it is a long story: $n meetings, from $y1 to $y2." : "Entre Sochaux et $name, c’est une longue histoire : $n rencontres, de $y1 à $y2.";
        } elseif ($n >= 10) {
            $intro[] = $en ? "Sochaux and $name have faced each other $n times, from $y1 to $y2." : "Sochaux et $name se sont affrontés $n fois, de $y1 à $y2.";
        } else {
            $intro[] = $en ? "Sochaux and $name have crossed paths only $n times" . ($y1 === $y2 ? ", in $y1." : ", between $y1 and $y2.")
                : "Sochaux et $name ne se sont croisés que $n fois" . ($y1 === $y2 ? ", en $y1." : ", entre $y1 et $y2.");
        }
        $rec = self::record($V, $N, $D, $en, true);
        $intro[] = match (self::trend($V, $N, $D)) {
            'domine' => $en ? "And the record leans clearly towards Sochaux: $rec." : "Et le bilan penche nettement du côté des Lionceaux : $rec.",
            'subit' => $en ? "$name have often hurt Sochaux: $rec." : "$name a souvent fait souffrir les Sochaliens : $rec.",
            'favorable' => $en ? "The record favours Sochaux: $rec." : "Le bilan est favorable aux Lionceaux : $rec.",
            'defavorable' => $en ? "The record favours $name: $rec." : "Le bilan est à l’avantage de $name : $rec.",
            'serre' => $en ? "The record could hardly be closer: $rec." : "Le bilan est très serré : $rec.",
            default => $en ? "The record is perfectly balanced: $rec." : "Le bilan est parfaitement équilibré : $rec.",
        };
        $intro[] = self::goals((int) $t['gf'], (int) $t['ga'], $en);

        // Domicile, extérieur, compétitions.
        $where = [];
        $home = array_values(array_filter($chrono, fn ($x) => !empty($x['sh'])));
        $away = array_values(array_filter($chrono, fn ($x) => empty($x['sh'])));
        if (count($home) >= 2 && count($away) >= 2) {
            [$hv, $hn, $hd] = self::vnd($home);
            [$av, $an, $ad] = self::vnd($away);
            $where[] = $en ? 'At home: ' . self::record($hv, $hn, $hd, true) . '. Away: ' . self::record($av, $an, $ad, true) . '.'
                : 'À domicile, ' . self::record($hv, $hn, $hd, false) . ' ; à l’extérieur, ' . self::record($av, $an, $ad, false) . '.';
        }
        $where[] = self::comps($v['comps'] ?? [], $en);

        // Les grands moments.
        $story = [];
        $story[] = $en ? 'It all began ' . self::on($first, $en) . ', ' . self::inComp($first, $en) . ': ' . self::result($first, $en) . '.'
            : 'Tout a commencé ' . self::on($first, $en) . ', ' . self::inComp($first, $en) . ' : ' . self::result($first, $en) . '.';
        [$best, $worst, $crowd] = self::extremes($chrono);
        if ($best && $best['id'] !== $first['id']) {
            $story[] = self::bestWin($best, $en, $name);
        }
        if ($worst && $worst['id'] !== $first['id']) {
            $story[] = self::worstLoss($worst, $en, $name);
        }
        if ($crowd) {
            $story[] = self::crowd($crowd, $en, $name);
        }
        $sc = self::scorers(array_column($chrono, 'id'));
        if ($sc && $sc[0]['g'] >= 2) {
            $story[] = self::scorerSentence($sc, $en, $name);
        }
        $run = self::unbeaten($chrono);
        if ($run && $n >= 6) {
            $story[] = $en ? "Sochaux even went {$run['n']} games unbeaten against $name, from " . self::date($run['from'], true) . ' to ' . self::date($run['to'], true) . '.'
                : "Les Lionceaux ont même enchaîné {$run['n']} matchs sans défaite contre $name, du " . self::date($run['from'], false) . ' au ' . self::date($run['to'], false) . '.';
        }

        $end = [];
        $end[] = $en ? 'The latest chapter was played ' . self::on($last, $en) . ', ' . self::inComp($last, $en) . ': ' . self::result($last, $en) . '.'
            : 'Le dernier épisode s’est joué ' . self::on($last, $en) . ', ' . self::inComp($last, $en) . ' : ' . self::result($last, $en) . '.';
        $end[] = match (self::trend($V, $N, $D)) {
            'domine' => $en ? 'An opponent Sochaux have often tamed: a fine page in the history of the club.' : 'Un adversaire que les Lionceaux ont souvent su dompter : une belle page de l’histoire du FCSM.',
            'favorable' => $en ? 'A fixture that has often smiled on the yellow and blue, and the story goes on.' : 'Un duel qui a souvent souri aux Jaune et Bleu, et l’histoire continue.',
            'subit', 'defavorable' => $en ? 'A tough opponent who has given Sochaux plenty of trouble: there is always a score to settle.' : 'Un adversaire coriace, qui a donné du fil à retordre aux Jaune et Bleu : une revanche est toujours à prendre.',
            default => $en ? 'A balanced rivalry, where nothing was ever decided in advance.' : 'Un duel équilibré, où rien n’a jamais été joué d’avance.',
        };
        return self::forPage([$intro, $where, $story, $end], $en);
    }

    // ------------------------------------------------------------------ bilans

    /** Bilan d'une compétition (/bilans/coupe-de-france/…). $label : nom affiché de la compétition. */
    public static function competition(string $key, string $label, array $v, bool $en): ?array
    {
        $chrono = self::chrono($v['list'] ?? []);
        $t = $v['t'] ?? [];
        $n = (int) ($t['count'] ?? 0);
        if (!$chrono || $n === 0) {
            return null;
        }
        [$y1, $y2] = [self::year($chrono[0]), self::year($chrono[count($chrono) - 1])];
        [$V, $N, $D] = [(int) $t['V'], (int) $t['N'], (int) $t['D']];
        $intro = [match ($key) {
            'championnat' => $en ? "The league is the club’s daily bread: $n matches recorded in the museum, from $y1 to $y2." : "Le championnat, c’est le quotidien du club : $n matchs fichés dans le musée, de $y1 à $y2.",
            'coupe-de-france' => $en ? "The Coupe de France, the competition of every upset: the museum holds $n Sochaux matches in it, from $y1 to $y2." : "La Coupe de France, l’épreuve de tous les exploits : le musée y compte $n matchs de Sochaux, de $y1 à $y2.",
            'coupe-d-europe' => $en ? "Europe and its great nights: $n European matches of Sochaux are recorded in the museum, from $y1 to $y2." : "L’Europe et ses grandes soirées : $n matchs européens du FCSM sont fichés dans le musée, de $y1 à $y2.",
            default => $en ? "$label: $n Sochaux matches recorded in the museum, from $y1 to $y2." : "$label : $n matchs de Sochaux fichés dans le musée, de $y1 à $y2.",
        }];
        $intro[] = ($en ? 'The record: ' : 'Le bilan : ') . self::record($V, $N, $D, $en) . '.';
        $intro[] = self::goals((int) $t['gf'], (int) $t['ga'], $en);

        $story = [];
        $groups = $v['groups'] ?? [];
        if ($key === 'championnat') {
            // La saison la plus victorieuse (au moins 10 matchs fichés).
            $top = null;
            foreach ($groups as $g) {
                if ($g['n'] >= 10 && (!$top || $g['V'] / $g['n'] > $top['V'] / $top['n'])) {
                    $top = $g;
                }
            }
            if ($top) {
                $story[] = $en ? 'The most successful season: ' . self::seasonLabel($top['key']) . ", with {$top['V']} wins in {$top['n']} matches recorded." : 'La saison la plus victorieuse : ' . self::seasonLabel($top['key']) . ", avec {$top['V']} victoires en {$top['n']} matchs fichés.";
            }
        } else {
            $count = count($groups);
            if ($count > 1) {
                $story[] = $en ? "Sochaux took part in it in $count seasons recorded in the museum." : "Sochaux y a pris part lors de $count saisons fichées dans le musée.";
            }
            // Le plus beau parcours : les finales, sinon le tour le plus avancé.
            $finals = array_values(array_filter($chrono, fn ($x) => self::rank((string) ($x['round'] ?? '')) === 100));
            if ($finals) {
                $k = count($finals);
                if ($k === 1) {
                    $f = $finals[0];
                    $story[] = $en ? 'Sochaux reached the final ' . self::on($f, true) . ", against {$f['opp']}: " . self::result($f, true) . '.'
                        : 'Sochaux a atteint la finale ' . self::on($f, false) . ", contre {$f['opp']} : " . self::result($f, false) . '.';
                } else {
                    $story[] = $en ? "Sochaux reached the final $k times." : "Sochaux a atteint la finale $k fois.";
                    foreach (array_slice($finals, 0, 4) as $f) {
                        $story[] = $en ? ucfirst(self::on($f, true)) . ", against {$f['opp']}: " . self::result($f, true) . '.'
                            : self::ucfirst(self::on($f, false)) . ", contre {$f['opp']} : " . self::result($f, false) . '.';
                    }
                }
            } else {
                $deep = null;
                foreach ($chrono as $x) {
                    if (($r = self::rank((string) ($x['round'] ?? ''))) > 0 && (!$deep || $r > self::rank((string) $deep['round']))) {
                        $deep = $x;
                    }
                }
                if ($deep && ($round = self::round((string) $deep['round'], $en)) !== '') {
                    $story[] = $en ? "The finest run went as far as the $round, in " . self::seasonLabel((string) $deep['season']) . ': ' . self::result($deep, true) . " against {$deep['opp']}."
                        : "Le plus beau parcours va jusqu’" . (ctype_digit($round[0]) ? 'au ' : 'en ') . "$round, en " . self::seasonLabel((string) $deep['season']) . ' : ' . self::result($deep, false) . " contre {$deep['opp']}.";
                }
            }
        }
        [$best, $worst, $crowd] = self::extremes($chrono);
        if ($best) {
            $story[] = self::bestWin($best, $en);
        }
        if ($worst) {
            $story[] = self::worstLoss($worst, $en);
        }
        if ($crowd) {
            $story[] = self::crowd($crowd, $en);
        }
        $sc = self::scorers(array_column($chrono, 'id'));
        if ($sc && $sc[0]['g'] >= 2) {
            $story[] = self::scorerSentence($sc, $en, null);
        }
        $end = [$en ? 'A competition to relive season after season, match by match, in the museum.' : 'Une épreuve à revivre saison après saison, match après match, dans le musée.'];
        return self::forPage([$intro, $story, $end], $en);
    }

    /** Bilan dans un stade (/bilans/stade-auguste-bonal/…). */
    public static function stadium(string $key, string $stadium, array $v, bool $en): ?array
    {
        $chrono = self::chrono($v['list'] ?? []);
        $t = $v['t'] ?? [];
        $n = (int) ($t['count'] ?? 0);
        if (!$chrono || $n === 0) {
            return null;
        }
        [$y1, $y2] = [self::year($chrono[0]), self::year($chrono[count($chrono) - 1])];
        [$V, $N, $D] = [(int) $t['V'], (int) $t['N'], (int) $t['D']];
        $bonal = $key === 'auguste-bonal';
        $at = self::stadiumAt($stadium, $en);
        $intro = [$bonal
            ? ($en ? "Stade Auguste-Bonal is the home of the Lionceaux: $n matches recorded in the museum, from $y1 to $y2." : "Le stade Auguste-Bonal, c’est la maison des Lionceaux : $n matchs fichés dans le musée, de $y1 à $y2.")
            : ($en ? ucfirst($at) . ", the museum holds $n Sochaux matches, from $y1 to $y2." : self::ucfirst($at) . ", le musée compte $n matchs de Sochaux, de $y1 à $y2.")];
        $intro[] = ($en ? 'The record: ' : 'Le bilan : ') . self::record($V, $N, $D, $en) . '.';
        $intro[] = self::goals((int) $t['gf'], (int) $t['ga'], $en);

        $story = [];
        $first = $chrono[0];
        $story[] = $en ? 'The first match recorded here dates from ' . self::date($first, true) . ': ' . self::result($first, true, false) . " against {$first['opp']}."
            : 'Le premier match fiché ici date du ' . self::date($first, false) . ' : ' . self::result($first, false, false) . " contre {$first['opp']}.";
        // La décennie la plus heureuse (au moins 20 matchs fichés).
        $top = null;
        foreach ($v['groups'] ?? [] as $g) {
            if ($g['n'] >= 20 && is_numeric($g['key']) && (!$top || $g['V'] / $g['n'] > $top['V'] / $top['n'])) {
                $top = $g;
            }
        }
        if ($top) {
            $story[] = $en ? "The {$top['key']}s were the happiest years here: {$top['V']} wins in {$top['n']} matches."
                : "Les années {$top['key']} y furent les plus heureuses : {$top['V']} victoires en {$top['n']} matchs.";
        }
        [$best, $worst, $crowd] = self::extremes($chrono);
        if ($best) {
            $story[] = self::bestWin($best, $en);
        }
        if ($worst) {
            $story[] = self::worstLoss($worst, $en);
        }
        if ($crowd) {
            $story[] = self::crowd($crowd, $en);
        }
        $end = [$bonal
            ? ($en ? 'Bonal, where so many pages of the club’s history were written.' : 'Bonal, où se sont écrites tant de pages de l’histoire du FCSM.')
            : ($en ? 'Every match is in the museum, one match page at a time.' : 'Tous ces matchs sont à revivre, fiche après fiche, dans le musée.')];
        return self::forPage([$intro, $story, $end], $en);
    }

    // ------------------------------------------------------------------ saisons

    /** Saison (/matchs/1987-1988/). $v : variables de la page (Explore::seasonData). */
    public static function season(array $v, ?string $division, bool $en): ?array
    {
        $matches = array_values(array_filter($v['matches'] ?? [], fn ($x) => !empty($x['date'])));
        if (!$matches) {
            return null;
        }
        $label = self::seasonLabel((string) $v['season']);
        [$V, $N, $D] = self::vnd($matches);
        $gf = $ga = 0;
        foreach ($matches as $x) {
            if ($x['us'] !== null) {
                $gf += (int) $x['us'];
                $ga += (int) $x['them'];
            }
        }
        $n = count($v['matches']);
        $intro = [];
        if (!empty($v['current'])) {
            $intro[] = $en ? "The $label season is under way" . ($division ? ", in $division." : '.') : "La saison $label est en cours" . ($division ? ", en $division." : '.');
        } else {
            $intro[] = $en ? "A look back at the $label season" . ($division ? ", played in $division." : '.') : "Retour sur la saison $label" . ($division ? ", vécue en $division." : '.');
        }
        $intro[] = $en ? "The museum holds $n " . ($n > 1 ? 'matches' : 'match') . ' of it: ' . self::record($V, $N, $D, true) . '.'
            : "Le musée en compte $n " . ($n > 1 ? 'matchs' : 'match') . ' : ' . self::record($V, $N, $D, false) . '.';
        $intro[] = self::goals($gf, $ga, $en);

        $men = [];
        $coaches = array_slice($v['coaches'] ?? [], 0, 3);
        if ($coaches) {
            $names = array_column($coaches, 'name');
            $men[] = ($en ? 'In the dugout: ' : 'Sur le banc : ') . self::join($names, $en) . '.';
        }
        $sc = array_slice($v['scorers'] ?? [], 0, 3);
        if ($sc) {
            $g = (int) $sc[0]['g'];
            $s0 = $en ? "The season’s top scorer is {$sc[0]['name']}, with $g " . ($g > 1 ? 'goals' : 'goal') : "Le meilleur buteur de la saison est {$sc[0]['name']}, avec $g " . ($g > 1 ? 'buts' : 'but');
            $rest = array_map(fn ($x) => $x['name'] . ' (' . $x['g'] . ')', array_slice($sc, 1));
            $men[] = $s0 . ($rest ? ($en ? ', ahead of ' : ', devant ') . self::join($rest, $en) : '') . '.';
        }
        $squad = $v['squad'] ?? [];
        usort($squad, fn ($a, $b) => $b['mj'] <=> $a['mj']);
        if ($squad && $squad[0]['mj'] >= 3) {
            $p = $squad[0];
            $men[] = $en ? "{$p['name']} was the most used player, with {$p['mj']} appearances." : "{$p['name']} est le joueur le plus utilisé, avec {$p['mj']} matchs.";
        }

        // Les coupes : jusqu'où ?
        $cups = [];
        $byComp = [];
        foreach ($matches as $x) {
            if (!in_array($x['comp'], ['Championnat', 'Amical'], true)) {
                $byComp[$x['comp']][] = $x;
            }
        }
        foreach ($byComp as $list) {
            $lastCup = $list[count($list) - 1];
            $cup = trim((string) (($lastCup['label'] ?? '') ?: $lastCup['comp']));
            $round = self::round((string) ($lastCup['round'] ?? ''), $en);
            $final = self::rank((string) ($lastCup['round'] ?? '')) === 100;
            if ($final && $lastCup['result'] === 'V') {
                $cups[] = $en ? "In the $cup, Sochaux went all the way: the final was won, " . self::result($lastCup, true) . " against {$lastCup['opp']}."
                    : "En $cup, l’aventure va jusqu’au bout : la finale est gagnée, " . self::result($lastCup, false) . " contre {$lastCup['opp']}.";
            } elseif ($lastCup['result'] === 'D' && $round !== '') {
                $cups[] = $en ? "In the $cup, the run ended in the $round: " . self::result($lastCup, true) . " against {$lastCup['opp']}."
                    : "En $cup, l’aventure s’arrête en $round : " . self::result($lastCup, false) . " contre {$lastCup['opp']}.";
            }
        }
        [$best, $worst] = self::extremes($matches);
        if ($best) {
            $cups[] = $en ? 'The biggest win: ' . self::score($best, true) . ' against ' . $best['opp'] . ', ' . self::on($best, true) . '.'
                : 'La plus large victoire : ' . self::score($best, false) . ' contre ' . $best['opp'] . ', ' . self::on($best, false) . '.';
        }
        if ($worst) {
            $cups[] = $en ? 'The heaviest defeat: ' . self::score($worst, true) . ' against ' . $worst['opp'] . ', ' . self::on($worst, true) . '.'
                : 'La plus lourde défaite : ' . self::score($worst, false) . ' contre ' . $worst['opp'] . ', ' . self::on($worst, false) . '.';
        }

        $end = [];
        if (!empty($v['bilan']['title'])) {
            $end[] = $en ? 'The full season review is told in the page “' . $v['bilan']['title'] . '”.' : 'Le bilan complet de la saison est raconté dans la fiche « ' . $v['bilan']['title'] . ' ».';
        }
        $played = $V + $N + $D;
        $end[] = match (true) {
            $played >= 15 && $V / $played >= 0.6 => $en ? 'A golden season, the kind people love to tell.' : 'Une saison faste, de celles qu’on aime raconter.',
            $played >= 15 && $V / $played <= 0.25 => $en ? 'A difficult season, but one that belongs to the story too.' : 'Une saison difficile, mais qui fait aussi partie de l’histoire.',
            default => $en ? 'A season to relive match by match in the museum.' : 'Une saison à revivre match après match dans le musée.',
        };
        return self::forPage([$intro, $men, $cups, $end], $en);
    }

    // ------------------------------------------------------------------ records et chiffres

    /** Livre des records : le classement affiché. $rows : Explore::recordRows. */
    public static function records(string $cat, string $title, string $unit, string $scope, array $rows, bool $en): ?array
    {
        if (!$rows) {
            return null;
        }
        $val = function (array $r) use ($cat, $unit, $en): string {
            $v = trim((string) $r['v']);
            if ($cat === 'victoires') {
                $d = (int) ltrim($v, '+');
                return $en ? "$d-goal margin" : "$d buts d’écart";
            }
            return $v . ' ' . $unit;
        };
        $intro = [($en ? 'The Sochaux record book: ' : 'Le livre des records du FCSM : ') . self::lcfirst($title, $en) . ', ' . self::lcfirst($scope, $en) . '.'];
        $top = $rows[0];
        $intro[] = ($en ? 'At the top: ' : 'En tête du classement : ') . $top['name'] . ', ' . $val($top) . (trim((string) ($top['meta'] ?? '')) !== '' ? ' (' . $top['meta'] . ')' : '') . '.';
        // « Du 3 octobre 1987 au… » (séries) se dit en minuscule au milieu de la phrase.
        $next = array_map(fn ($r) => (string) preg_replace('/^(Du|From) /u', $en ? 'from ' : 'du ', (string) $r['name']) . ', ' . $val($r), array_slice($rows, 1, 4));
        if ($next) {
            $intro[] = ($en ? 'Next come ' : 'Suivent ') . self::join($next, $en, $en ? '; ' : ' ; ') . '.';
        }
        $end = [$en ? 'The full ranking, and the other records, are on this page.' : 'Le classement complet et les autres records sont à découvrir sur cette page.'];
        return self::forPage([$intro, $end], $en);
    }

    /** « Les chiffres du FCSM » : le plus marquant de chaque chapitre. */
    public static function chiffres(array $chapters, int $count, bool $en): ?array
    {
        if (!$chapters) {
            return null;
        }
        $paras = [[$en ? "Sochaux in numbers: $count statistics drawn from the whole memory of the museum. Here are the most striking, chapter by chapter."
            : "Les chiffres du FCSM : $count statistiques tirées de toute la mémoire du musée. Voici les plus marquantes, chapitre par chapitre."]];
        $stat = function (array $st) use ($en): array {
            $who = array_values(array_filter(array_map(fn ($w) => isset($w['id']) || isset($w['last']) ? (string) $w['name'] : '', $st['who'] ?? []), fn ($w) => $w !== ''));
            $value = trim($st['value'] . ' ' . ($st['unit'] ?? ''));
            return [rtrim((string) $st['label'], ' .') . ($en ? ': ' : ' : ') . ($who ? self::join($who, $en) . ', ' . $value : $value) . '.', trim((string) ($st['text'] ?? ''))];
        };
        // Le premier chiffre de chaque chapitre, puis le deuxième tant que la durée le permet.
        $words = fn (array $p) => FicheAudio::words(implode(' ', $p));
        $total = $words($paras[0]) + 12;
        $byChapter = [];
        foreach ($chapters as $i => $ch) {
            if (!empty($ch['stats'][0])) {
                $byChapter[$i] = array_merge([rtrim((string) $ch['title'], '.') . '.'], $stat($ch['stats'][0]));
                $total += $words($byChapter[$i]);
            }
        }
        foreach ($chapters as $i => $ch) {
            if (isset($byChapter[$i]) && !empty($ch['stats'][1])) {
                $more = $stat($ch['stats'][1]);
                if ($total + $words($more) <= FicheAudio::maxWords()) {
                    $byChapter[$i] = array_merge($byChapter[$i], $more);
                    $total += $words($more);
                }
            }
        }
        $paras = array_merge($paras, array_values($byChapter));
        $paras[] = [$en ? 'And there are many more to discover on this page.' : 'Et bien d’autres sont à découvrir sur cette page.'];
        return self::forPage($paras, $en);
    }

    // ------------------------------------------------------------------ phrases

    /** Matchs datés, du plus ancien au plus récent. */
    private static function chrono(array $list): array
    {
        $list = array_values(array_filter($list, fn ($x) => !empty($x['date'])));
        usort($list, fn ($a, $b) => strcmp((string) $a['date'], (string) $b['date']));
        return $list;
    }

    private static function year(array $x): int
    {
        return (int) substr((string) $x['date'], 0, 4);
    }

    /** [victoires, nuls, défaites] d'une liste de matchs. */
    private static function vnd(array $list): array
    {
        $r = ['V' => 0, 'N' => 0, 'D' => 0];
        foreach ($list as $x) {
            if (isset($r[$x['result'] ?? ''])) {
                $r[$x['result']]++;
            }
        }
        return [$r['V'], $r['N'], $r['D']];
    }

    private static function trend(int $V, int $N, int $D): string
    {
        $n = $V + $N + $D;
        return match (true) {
            $V >= 3 && $V >= 2 * $D => 'domine',
            $D >= 3 && $D >= 2 * $V => 'subit',
            $V !== $D && $n >= 10 && abs($V - $D) <= max(1, (int) round($n / 10)) => 'serre',
            $V > $D => 'favorable',
            $D > $V => 'defavorable',
            default => 'equilibre',
        };
    }

    /** « 12 victoires, 5 nuls et 3 défaites » ($whose : « 12 victoires sochaliennes… », face à un adversaire). */
    private static function record(int $V, int $N, int $D, bool $en, bool $whose = false): string
    {
        $nb = fn (int $n, string $one, string $many, string $zero) => $n === 0 ? $zero : $n . ' ' . ($n > 1 ? $many : $one);
        return $en ? $nb($V, $whose ? 'Sochaux win' : 'win', $whose ? 'Sochaux wins' : 'wins', 'no wins') . ', ' . $nb($N, 'draw', 'draws', 'no draws') . ' and ' . $nb($D, 'defeat', 'defeats', 'no defeats')
            : $nb($V, $whose ? 'victoire sochalienne' : 'victoire', $whose ? 'victoires sochaliennes' : 'victoires', 'aucune victoire') . ', ' . $nb($N, 'nul', 'nuls', 'aucun nul') . ' et ' . $nb($D, 'défaite', 'défaites', 'aucune défaite');
    }

    private static function goals(int $gf, int $ga, bool $en): string
    {
        if ($en) {
            return 'In all, Sochaux scored ' . $gf . ' ' . ($gf === 1 ? 'goal' : 'goals') . ' and conceded ' . $ga . '.';
        }
        return 'Au total, ' . ($gf ? 'Sochaux a marqué ' . $gf . ' ' . ($gf > 1 ? 'buts' : 'but') : 'Sochaux n’a marqué aucun but')
            . ($ga ? ' et en a encaissé ' . $ga : ' et n’en a encaissé aucun') . '.';
    }

    /** Compétitions jouées : « 30 fois en championnat, 4 en Coupe de France et 2 en match amical ». */
    private static function comps(array $comps, bool $en): string
    {
        arsort($comps);
        $name = fn (string $c) => match ($c) {
            'Championnat' => $en ? 'in the league' : 'en championnat',
            'Amical' => $en ? 'in friendlies' : 'en match amical',
            'Barrages' => $en ? 'in play-offs' : 'en barrages',
            'Coupes diverses' => $en ? 'in other cups' : 'dans d’autres coupes',
            default => $en ? 'in the ' . $c : 'en ' . $c,
        };
        if (count($comps) === 1) {
            return $en ? 'All these matches were played ' . $name((string) array_key_first($comps)) . '.' : 'Toutes ces rencontres se sont jouées ' . $name((string) array_key_first($comps)) . '.';
        }
        $parts = [];
        $i = 0;
        foreach ($comps as $c => $k) {
            $parts[] = ($i++ === 0 ? $k . ($en ? ($k > 1 ? ' times ' : ' time ') : ' fois ') : $k . ' ') . $name((string) $c);
        }
        return ($en ? 'They met ' : 'Ces duels se sont joués ') . self::join($parts, $en) . '.';
    }

    /** [plus large victoire, plus lourde défaite, plus grosse affluence] (mêmes règles que les pages). */
    private static function extremes(array $list): array
    {
        $best = $worst = $crowd = null;
        foreach ($list as $x) {
            if ($x['us'] !== null && $x['us'] !== '') {
                $diff = (int) $x['us'] - (int) $x['them'];
                if ($x['result'] === 'V' && (!$best || $diff > $best['_d'] || ($diff === $best['_d'] && $x['us'] > $best['us']))) {
                    $best = $x + ['_d' => $diff];
                }
                if ($x['result'] === 'D' && (!$worst || -$diff > $worst['_d'] || (-$diff === $worst['_d'] && $x['them'] > $worst['them']))) {
                    $worst = $x + ['_d' => -$diff];
                }
            }
            if (($x['spectators'] ?? 0) > 1000 && ($x['spectators'] ?? 0) > ($crowd['spectators'] ?? 0)) {
                $crowd = $x;
            }
        }
        return [$best, $worst, $crowd];
    }

    private static function bestWin(array $x, bool $en, ?string $opp = null): string
    {
        $vs = $opp === null ? ($en ? ' against ' : ' contre ') . $x['opp'] : '';
        return $en ? 'The biggest win: ' . self::score($x, true) . self::place($x, true) . $vs . ', ' . self::inComp($x, true) . ', ' . self::on($x, true) . '.'
            : 'La plus large victoire : ' . self::score($x, false) . self::place($x, false) . $vs . ', ' . self::inComp($x, false) . ', ' . self::on($x, false) . '.';
    }

    private static function worstLoss(array $x, bool $en, ?string $opp = null): string
    {
        $vs = $opp === null ? ($en ? ' against ' : ' contre ') . $x['opp'] : '';
        return $en ? 'The heaviest defeat: ' . self::score($x, true) . self::place($x, true) . $vs . ', ' . self::inComp($x, true) . ', ' . self::on($x, true) . '.'
            : 'La plus lourde défaite : ' . self::score($x, false) . self::place($x, false) . $vs . ', ' . self::inComp($x, false) . ', ' . self::on($x, false) . '.';
    }

    private static function crowd(array $x, bool $en, ?string $opp = null): string
    {
        $n = (int) $x['spectators'];
        $vs = $opp === null ? ($en ? ', against ' : ', contre ') . $x['opp'] : '';
        return $en ? "The biggest crowd: $n spectators, " . self::on($x, true) . $vs . ', ' . self::inComp($x, true) . '.'
            : "La plus forte affluence : $n spectateurs, " . self::on($x, false) . $vs . ', ' . self::inComp($x, false) . '.';
    }

    /** Meilleurs buteurs sochaliens dans une série de matchs (compositions des fiches). */
    private static function scorers(array $matchIds, int $limit = 3): array
    {
        $want = array_flip(array_map('intval', $matchIds));
        $acc = [];
        foreach (Derived::get()['apps'] ?? [] as $a) {
            if (isset($want[(int) $a[1]]) && ($a[6] ?? '') === 'player' && (int) $a[2] > 0) {
                $acc[(int) $a[0]] = ($acc[(int) $a[0]] ?? 0) + (int) $a[2];
            }
        }
        arsort($acc);
        $out = [];
        foreach ($acc as $pid => $g) {
            $s = Index::get($pid);
            if (!$s || !Index::visible($s)) {
                continue;
            }
            $out[] = ['name' => (string) $s['p']['name'], 'g' => $g];
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    private static function scorerSentence(array $sc, bool $en, ?string $against): string
    {
        $g = $sc[0]['g'];
        $tops = array_column(array_filter($sc, fn ($x) => $x['g'] === $g), 'name');
        $rest = array_map(fn ($x) => $x['name'] . ' (' . $x['g'] . ')', array_filter($sc, fn ($x) => $x['g'] !== $g));
        $vs = $against !== null ? ($en ? " against $against" : " contre $against") : '';
        if (count($tops) > 1) {
            $s = $en ? 'Among the scorers, ' . self::join($tops, true) . " found the net $g times each$vs" : 'Côté buteurs, ' . self::join($tops, false) . " ont marqué $g buts chacun$vs";
        } else {
            $s = $en ? "Among the scorers, {$tops[0]} found the net $g times$vs" : "Côté buteurs, {$tops[0]} a marqué $g buts$vs";
        }
        return $s . ($rest ? ($en ? ', ahead of ' : ', devant ') . self::join(array_values($rest), $en) : '') . '.';
    }

    /** Plus longue série sans défaite (4 matchs au moins). */
    private static function unbeaten(array $chrono): ?array
    {
        $best = [];
        $cur = [];
        foreach ($chrono as $x) {
            if (!$x['result']) {
                continue;
            }
            if ($x['result'] === 'D') {
                $cur = [];
                continue;
            }
            $cur[] = $x;
            if (count($cur) > count($best)) {
                $best = $cur;
            }
        }
        return count($best) >= 4 ? ['n' => count($best), 'from' => $best[0], 'to' => $best[count($best) - 1]] : null;
    }

    /** « une victoire 3 à 1 à Bonal », « a 3–1 win at Bonal » (tirs au but et prolongation compris). */
    private static function result(array $x, bool $en, bool $withPlace = true): string
    {
        if ($x['us'] === null || $x['us'] === '') {
            return $en ? 'score unknown' : 'score inconnu';
        }
        $us = (int) $x['us'];
        $them = (int) $x['them'];
        $extra = mb_strtolower((string) ($x['extra'] ?? ''));
        $aet = (bool) preg_match('/\ba\.?\s?p\b|prol/u', $extra);
        $pens = null;
        if (str_contains($extra, 'tab') && preg_match('/(\d+)\s*-\s*(\d+)/', $extra, $m)) {
            $pens = [max((int) $m[1], (int) $m[2]), min((int) $m[1], (int) $m[2])];
        }
        if ($us === $them && $pens && in_array($x['result'], ['V', 'D'], true)) {
            $won = $x['result'] === 'V';
            $s = $en ? "a {$us}–{$them} draw" . ($aet ? ' after extra time' : '') . ', then a penalty shoot-out ' . ($won ? 'won' : 'lost') . " {$pens[0]}–{$pens[1]}"
                : "un match nul $us à $them" . ($aet ? ' après prolongation' : '') . ', puis une séance de tirs au but ' . ($won ? 'gagnée' : 'perdue') . " {$pens[0]} à {$pens[1]}";
        } else {
            $sc = self::score($x, $en);
            $s = match ($x['result']) {
                'V' => $en ? "a $sc win" : "une victoire $sc",
                'D' => $en ? "a $sc defeat" : "une défaite $sc",
                default => $en ? "a $sc draw" : "un match nul $sc",
            } . ($aet ? ($en ? ' after extra time' : ' après prolongation') : '');
        }
        return $withPlace ? $s . self::place($x, $en) : $s;
    }

    /** Score, le plus grand nombre d'abord : « 3 à 1 », « 3–1 ». */
    private static function score(array $x, bool $en): string
    {
        $hi = max((int) $x['us'], (int) $x['them']);
        $lo = min((int) $x['us'], (int) $x['them']);
        return $en ? "{$hi}–{$lo}" : "$hi à $lo";
    }

    /** « à Bonal », « à domicile », « à l’extérieur », « au Parc des Princes » (finale). */
    private static function place(array $x, bool $en): string
    {
        if (self::rank((string) ($x['round'] ?? '')) === 100 && !empty($x['stade'])) {
            return ' ' . self::stadiumAt(Explore::stadiumName((string) $x['stade']), $en);
        }
        if (!empty($x['sh'])) {
            return ($x['stade'] ?? '') === 'auguste-bonal' ? ($en ? ' at Bonal' : ' à Bonal') : ($en ? ' at home' : ' à domicile');
        }
        return $en ? ' away' : ' à l’extérieur';
    }

    /** « au stade Louis II », « at the Stade Louis II ». */
    private static function stadiumAt(string $name, bool $en): string
    {
        $name = trim($name);
        if ($en) {
            return 'at the ' . $name;
        }
        return 'au ' . (preg_match('/^stade\b/iu', $name) ? 'stade' . mb_substr($name, 5) : $name);
    }

    /** « en Coupe de France », « en huitième de finale de la Coupe de France », « en match amical ». */
    private static function inComp(array $x, bool $en): string
    {
        $label = trim((string) (($x['label'] ?? '') ?: ($x['comp'] ?? '')));
        if ($label === '') {
            return '';
        }
        if (($x['comp'] ?? '') === 'Amical' || stripos($label, 'amical') !== false) {
            return $en ? 'in a friendly' : 'en match amical';
        }
        $round = self::round((string) ($x['round'] ?? ''), $en);
        $cup = (bool) preg_match('/^(coupe|trophée|tournoi|challenge)\b/iu', $label) || stripos($label, 'cup') !== false;
        if ($round !== '' && $cup) {
            return $en ? (preg_match('/^round \d/', $round) ? "in $round of the $label" : "in the $round of the $label")
                : (ctype_digit($round[0]) ? 'au ' : 'en ') . "$round de " . (preg_match('/^coupe\b/iu', $label) ? 'la ' : '') . $label;
        }
        if ($en) {
            return ($cup ? 'in the ' : 'in ') . $label;
        }
        return (preg_match('/^(trophée|tournoi|challenge)\b/iu', $label) ? 'au ' : 'en ') . $label;
    }

    /** Tour de coupe dit en toutes lettres : « huitième de finale », « round of 16 », « 7e tour ». */
    private static function round(string $r, bool $en): string
    {
        $r = trim((string) preg_replace('/\b(aller|retour)\b|de finale/u', '', mb_strtolower(trim($r))));
        if (preg_match('#^1\s*/\s*(\d+)#', $r, $m)) {
            return match ((int) $m[1]) {
                2 => $en ? 'semi-final' : 'demi-finale',
                4 => $en ? 'quarter-final' : 'quart de finale',
                8 => $en ? 'round of 16' : 'huitième de finale',
                16 => $en ? 'round of 32' : 'seizième de finale',
                32 => $en ? 'round of 64' : 'trente-deuxième de finale',
                default => '',
            };
        }
        if (preg_match('/^finale?\b/u', $r)) {
            return $en ? 'final' : 'finale';
        }
        if (preg_match('/^(\d+)\s*(e|è|ème|eme)\s*tour/u', $r, $m)) {
            return $en ? 'round ' . $m[1] : $m[1] . 'e tour';
        }
        return '';
    }

    /** Rang d'un tour de coupe (finale 100, demi 90… tours préliminaires 10 + n ; 0 si inconnu). */
    private static function rank(string $r): int
    {
        $r = mb_strtolower(trim($r));
        if (preg_match('#^1\s*/\s*(\d+)#', $r, $m)) {
            return match ((int) $m[1]) { 2 => 90, 4 => 80, 8 => 70, 16 => 60, 32 => 50, default => 40 };
        }
        if (preg_match('/^finale?\b/u', $r)) {
            return 100;
        }
        return preg_match('/^(\d+)\s*(e|è|ème|eme)\s*tour/u', $r, $m) ? 10 + (int) $m[1] : 0;
    }

    /** « le 7 avril 2017 », « on 7 April 2017 » ; l'année seule si la date est incomplète. */
    private static function on(array $x, bool $en): string
    {
        $d = self::date($x, $en);
        if ($d !== '') {
            return ($en ? 'on ' : 'le ') . $d;
        }
        return ($en ? 'in ' : 'en ') . self::year($x);
    }

    private static function date(array $x, bool $en): string
    {
        $iso = (string) ($x['date'] ?? '');
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])/', $iso) ? FicheAudio::date($iso, $en, false) : (string) self::year($x);
    }

    /** Saison « 1987‑1988 » : trait d'union insécable, pour qu'elle ne soit pas lue « de 1987 à 1988 ». */
    private static function seasonLabel(string $s): string
    {
        return str_replace('-', "\u{2011}", $s);
    }

    /** « A, B et C », « A, B and C ». */
    private static function join(array $items, bool $en, string $sep = ', '): string
    {
        $items = array_values(array_filter(array_map('strval', $items), fn ($s) => trim($s) !== ''));
        if (count($items) < 2) {
            return $items[0] ?? '';
        }
        $last = array_pop($items);
        return implode($sep, $items) . ($en ? ' and ' : ' et ') . $last;
    }

    private static function ucfirst(string $s): string
    {
        return mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1);
    }

    /** Titre ou portée en milieu de phrase : « meilleurs buteurs », « toutes époques ». */
    private static function lcfirst(string $s, bool $en): string
    {
        // Pas pour un nom propre (« Années 80 » → « années 80 », mais « Coupe de France » reste).
        return preg_match('/^(Meilleurs|Joueurs|Plus|Entraîneurs|Toutes|Années|Top|Biggest|Most|Longest|All|The)\b/u', $s) ? mb_strtolower(mb_substr($s, 0, 1)) . mb_substr($s, 1) : $s;
    }

    /**
     * Texte prêt pour la voix : nombres sans espace de milliers (« 19 994 » → « 19994 »), scores
     * dits (« 7-0 » → « 7 à 0 »), dates « 12/09/1989 » en toutes lettres, tirets et puces en virgules.
     */
    public static function speakable(string $s, bool $en): string
    {
        $s = (string) preg_replace('/(?<=\d)[ \x{00A0}\x{202F}](?=\d{3}(?!\d))/u', '', $s);
        $s = (string) preg_replace_callback('#\b(0?[1-9]|[12]\d|3[01])/(0?[1-9]|1[0-2])/(\d{4})\b#', fn ($m) => FicheAudio::date(sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]), $en, false), $s);
        $s = (string) preg_replace('/(?<![\d\/])(\d{1,2})\s?[-–]\s?(\d{1,2})(?![\d\/])/u', $en ? '$1–$2' : '$1 à $2', $s);
        // Années « 1933–1952 » : de… à… ; deux années qui se suivent sont une saison (« 1987‑1988 »).
        $s = (string) preg_replace_callback('/(?<!\d)(\d{4})\s?[–-]\s?(\d{4})(?!\d)/u', fn ($m) => (int) $m[2] === (int) $m[1] + 1 ? $m[1] . "\u{2011}" . $m[2] : ($en ? "from {$m[1]} to {$m[2]}" : "de {$m[1]} à {$m[2]}"), $s);
        $s = str_replace([' – ', ' — ', ' · '], [$en ? ' against ' : ' contre ', ', ', ', '], $s);
        return trim((string) preg_replace('/\s+([,.])/u', '$1', $s));
    }
}
