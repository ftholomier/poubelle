<?php
declare(strict_types=1);

namespace App\Widgets\Screen;

use App\Data\Collections;
use App\Data\Media;
use App\Data\Seeds;
use App\Widgets\Cache;
use App\Widgets\Context;
use App\Widgets\Items\HistoireDuDuel;
use App\Widgets\Items\QuizDuJour;
use App\Widgets\Photos;
use App\Widgets\Registry;

/**
 * Slides du mode écran : chaque slide d'une playlist devient un « modèle » neutre (mise en page,
 * textes, chiffres, photo, QR code…) construit à partir des données du widget du même nom (mêmes
 * données, même cache : Cache::get), puis dessiné en HTML plein écran (Page) ou en PNG (Image).
 * Rien de personnel : seulement ce que les widgets publient déjà ; photos par Photos::safe.
 *
 * Mises en page : duel, liste, rang, héros, citation, carte (légende), match, photo, quiz, frise,
 * carte (stades), compte à rebours, soutien.
 */
final class Slides
{
    /**
     * Modèles de toutes les slides d'une playlist (les slides sans rien à montrer sont omises).
     * @return list<array>
     */
    public static function build(array $playlist, Context $c, string $adversaire = '', ?int $seed = null): array
    {
        $out = [];
        $adv = $adversaire !== '' ? $adversaire : (string) ($playlist['adversaire'] ?? '');
        $vary = $seed !== null && self::varyOn($playlist);
        $per = array_count_values(array_map(fn ($s) => (string) $s['type'], $playlist['slides']));
        $slot = [];
        foreach ($playlist['slides'] as $i => $s) {
            $k = $slot[$s['type']] = ($slot[$s['type']] ?? -1) + 1;
            try {
                $list = $vary ? self::variants($s, $c, $adv, $k, $per[$s['type']], (string) $playlist['id'], (int) $seed)
                    : array_values(array_filter([self::one($s, $c, $adv, $k, (string) $playlist['id'])]));
            } catch (\Throwable $e) {
                error_log('Écran ' . $playlist['id'] . ', slide ' . $s['type'] . ' : ' . $e->getMessage());
                $list = [];
            }
            if ($list) {
                $extra = ['type' => $s['type'], 'duration' => (int) $s['duration'], 'n' => $i + 1];
                $m = $list[0] + $extra;
                if (count($list) > 1) {
                    $m['variants'] = array_map(fn ($v) => $v + $extra, array_slice($list, 1));
                }
                $out[] = $m;
            }
        }
        return $out;
    }

    /** Nombre maximal de variantes préparées par slide (une par tour de boucle, puis on recommence). */
    public const VARIANTS = 6;

    /** Types de slides dont le contenu peut changer d'un tour à l'autre (les autres restent fixes). */
    public const VARYING = ['quiz', 'photo', 'frise', 'chiffre-du-jour', 'le-saviez-vous', 'carte-legende', 'records', 'ce-jour-la', 'il-y-a', 'palmares', 'boutique'];

    /** « Varier les slides à chaque tour » (réglage de la playlist, oui par défaut). */
    public static function varyOn(array $playlist): bool
    {
        return !array_key_exists('vary', $playlist) || !empty($playlist['vary']);
    }

    /**
     * Graine du tirage des variantes : le numéro de la minute. Chaque rechargement de la page (toutes
     * les X minutes) repart donc d'un tirage décalé, jamais de la même variante 1.
     */
    public static function seed(?int $now = null): int
    {
        return intdiv($now ?? time(), 60);
    }

    /**
     * Une slide peut-elle varier ? Non si son type est fixe (histoire du duel, compte à rebours,
     * soutenir, don…) ou si l'administrateur a fixé son contenu : variante « n », joueur, classement
     * ou objet choisis. « n » vide (ou automatique) : la slide varie.
     */
    public static function varies(array $s): bool
    {
        $opt = (array) ($s['opt'] ?? []);
        if (!in_array($s['type'], self::VARYING, true)) {
            return false;
        }
        $fix = ['photo' => 'n', 'frise' => 'n', 'carte-legende' => 'joueur', 'records' => 'cat', 'boutique' => 'produit'][$s['type']] ?? null;
        return $fix === null || (string) ($opt[$fix] ?? '') === '';
    }

    /**
     * Variantes d'une slide (au plus VARIANTS, sans doublon, la première montrée au premier tour) :
     * tirages consécutifs à partir de la graine, entrelacés entre les slides du même type de la
     * playlist (rotation = tour × nombre de slides du type + rang de la slide) : deux slides « photo »
     * ne montrent jamais la même photo au même tour.
     * @return list<array>
     */
    public static function variants(array $s, Context $c, string $adversaire, int $slot, int $per, string $pid, int $seed, int $max = self::VARIANTS): array
    {
        if (!self::varies($s)) {
            $m = self::one($s, $c, $adversaire, $slot, $pid);
            return $m === null ? [] : [$m];
        }
        $out = [];
        $seen = [];
        for ($k = 0; $k < $max * 2 && count($out) < $max; $k++) {
            $m = self::one($s, $c, $adversaire, $slot, $pid, ($seed + $k) * max(1, $per) + $slot);
            if ($m === null) {
                if ($k === 0) {
                    return [];
                }
                continue;
            }
            $h = md5(json_encode($m));
            if (!isset($seen[$h])) {
                $seen[$h] = true;
                $out[] = $m;
            }
        }
        return $out;
    }

    /** Raison habituelle d'une slide sautée (rien à montrer), par type. */
    public const SKIP = [
        'histoire-du-duel' => 'aucun adversaire possible (aucun match fiché, ou nom de notre propre club)',
        'ce-jour-la' => 'aucun match fiché ce jour-là',
        'carte-legende' => 'aucune légende à montrer (joueur choisi introuvable ou non publié)',
        'chiffre-du-jour' => 'aucun chiffre du jour disponible',
        'le-saviez-vous' => 'aucune anecdote « Le saviez-vous ? » publiée',
        'quiz' => 'aucune question de quiz active (2 à 4 réponses)',
        'photo' => 'aucune photo d’archives diffusable',
        'frise' => 'frise vide',
        'records' => 'classement vide',
        'palmares' => 'palmarès vide',
        'il-y-a' => 'aucun anniversaire de match (5, 10, 20, 30 ans…) ce jour-là',
        'dernier-match' => 'aucun match fiché',
        'saison' => 'saison introuvable',
        'compte-a-rebours' => 'aucune date à venir (réglage du widget Compte à rebours)',
        'retro-direct' => 'aucun Rétro-Direct programmé',
        'carte-stades' => 'aucun stade placé sur la carte',
        'soutenir' => 'aucun lien de soutien réglé',
        'boutique' => 'boutique fermée ou aucun objet à montrer',
        'don' => 'bouton « Faire un don » masqué sur le site',
    ];

    /**
     * Contrôle avant diffusion : pour une playlist et des réglages (adversaire, langue, jour), chaque
     * slide affichée ou sautée (avec la raison) et la durée réelle d'une boucle. Mêmes calculs que
     * l'écran (Slides::one) ; un autre jour que celui-ci ne change que « Ce jour-là » (les autres
     * slides du jour — quiz, photo, chiffre — sont celles d'aujourd'hui).
     * @return array{rows:list<array>,shown:int,skipped:int,duration:int,planned:int,opponent:array}
     */
    public static function check(array $playlist, Context $c, string $adversaire = '', string $day = '', ?int $seed = null): array
    {
        $vary = self::varyOn($playlist);
        $seed ??= self::seed();
        $per = array_count_values(array_map(fn ($s) => (string) $s['type'], $playlist['slides']));
        $slot = [];
        $adv = $adversaire !== '' ? $adversaire : (string) ($playlist['adversaire'] ?? '');
        $target = \App\Widgets\Items\HistoireDuDuel::target($adv);
        $rows = [];
        $total = 0;
        $planned = 0;
        $md = $day !== '' && $day !== date('Y-m-d') ? substr($day, 5) : '';
        foreach ($playlist['slides'] as $i => $s) {
            $name = Playlists::TYPES[$s['type']][0] ?? $s['type'];
            $planned += (int) $s['duration'];
            $note = '';
            if ($md !== '' && $s['type'] === 'ce-jour-la') {
                $s['opt'] = ['date' => $md] + (array) ($s['opt'] ?? []);
            }
            $k = $slot[$s['type']] = ($slot[$s['type']] ?? -1) + 1;
            $variants = 0;
            try {
                if ($vary) {
                    $list = self::variants($s, $c, $adv, $k, $per[$s['type']], (string) ($playlist['id'] ?? ''), $seed);
                    $m = $list[0] ?? null;
                    $variants = count($list);
                } else {
                    $m = self::one($s, $c, $adv, $k, (string) ($playlist['id'] ?? ''));
                    $variants = $m === null ? 0 : 1;
                }
            } catch (\Throwable $e) {
                $m = null;
                $note = 'erreur : ' . $e->getMessage();
            }
            if ($m === null) {
                $note = $note ?: (self::SKIP[$s['type']] ?? 'rien à montrer');
            } elseif ($s['type'] === 'histoire-du-duel') {
                $note = !empty($m['first']) ? 'première rencontre : version spéciale (' . $m['title'] . ')' : $m['title'];
            } elseif (isset($m['title']) && is_string($m['title'])) {
                $note = $m['title'];
            }
            if ($m !== null) {
                $total += (int) $s['duration'];
            }
            $rows[] = ['n' => $i + 1, 'type' => $s['type'], 'name' => $name, 'duration' => (int) $s['duration'], 'shown' => $m !== null, 'note' => $note, 'variants' => $variants,
                'varies' => $vary && self::varies($s)];
        }
        $shown = count(array_filter($rows, fn ($r) => $r['shown']));
        return ['rows' => $rows, 'vary' => $vary, 'shown' => $shown, 'skipped' => count($rows) - $shown, 'duration' => $total, 'planned' => $planned,
            'opponent' => $target === null ? ['name' => '', 'known' => false, 'auto' => true]
                : ['name' => $target['name'], 'known' => $target['club'] !== null, 'auto' => HistoireDuDuel::cleanName($adv) === null, 'club' => $target['club']]];
    }

    /** Données d'un widget (cache commun aux iframes et à l'API). */
    private static function data(string $key, array $opt, Context $c, ?int $rot = null): ?array
    {
        $w = Registry::get($key);
        if (!$w) {
            return null;
        }
        // Variante d'un tour de boucle (« _r » : rang du tirage, lu par le widget) : calculée à part,
        // sans passer par le cache (une entrée par tirage le remplirait pour rien).
        return $rot === null ? Cache::get($w, $w->clean($opt), $c)['data'] : $w->data($w->clean($opt) + ['_r' => $rot], $c);
    }

    /**
     * Une slide, ou null si rien à montrer. $slot : rang de la slide parmi celles du même type ;
     * $rot : rang du tirage d'une variante (null : contenu habituel, celui des exports et du contrôle).
     */
    public static function one(array $s, Context $c, string $adversaire = '', int $slot = 0, string $pid = '', ?int $rot = null): ?array
    {
        $opt = (array) ($s['opt'] ?? []);
        $date = fn (string $iso) => $c->en() ? date('j F Y', strtotime($iso)) : date_fr($iso);
        $num = fn ($n) => number_format((int) $n, 0, ',', "\u{202F}");
        switch ($s['type']) {
            case 'histoire-du-duel':
                $d = self::data('histoire-du-duel', ['adversaire' => ($opt['adversaire'] ?? '') ?: $adversaire], $c);
                if (!$d) {
                    return null;
                }
                if (!empty($d['first_meeting'])) {
                    return self::firstMeeting($d, $c, $date);
                }
                $rows = [];
                if ($d['biggest_win']) {
                    $rows[] = [$c->tr('Plus large victoire', 'Biggest win'), $d['biggest_win']['label'], $date($d['biggest_win']['date'])];
                }
                if ($d['top_scorer']) {
                    $rows[] = [$c->tr('Meilleur buteur du duel', 'Top scorer of the fixture'), $d['top_scorer']['name'], $c->tr($d['top_scorer']['goals'] . ' but' . ($d['top_scorer']['goals'] > 1 ? 's' : ''), $d['top_scorer']['goals'] . ' goal' . ($d['top_scorer']['goals'] > 1 ? 's' : ''))];
                }
                $rows[] = [$c->tr('Dernier match', 'Last meeting'), $d['last']['label'], $date($d['last']['date'])];
                return ['layout' => 'duel', 'eyebrow' => $c->tr('L’histoire du duel', 'Head-to-head') . ($d['first_year'] ? ' · ' . $c->tr('depuis ', 'since ') . $d['first_year'] : ''),
                    'title' => $d['team'] . ' × ' . $d['opponent'],
                    'stats' => [[$d['matches'], $c->tr('matchs', 'matches'), true], [$d['wins'], $c->tr('victoires', 'wins')], [$d['draws'], $c->tr('nuls', 'draws')], [$d['defeats'], $c->tr('défaites', 'defeats')]],
                    'bar' => [$d['wins'], $d['draws'], $d['defeats']],
                    'sub' => $c->tr('Buts : ' . $d['goals_for'] . ' marqués, ' . $d['goals_against'] . ' encaissés', 'Goals: ' . $d['goals_for'] . ' scored, ' . $d['goals_against'] . ' conceded'),
                    'rows' => $rows, 'qr' => $c->link($d['href'])];

            case 'ce-jour-la':
                // « date » (MM-JJ) : seulement pour le contrôle avant diffusion (un autre jour que celui-ci).
                $d = self::data('ce-jour-la', ['nombre' => $rot === null ? '3' : '8'] + (isset($opt['date']) ? ['date' => (string) $opt['date']] : []), $c);
                if (!$d || empty($d['matches'])) {
                    return null;
                }
                $rows = [];
                foreach (self::window($d['matches'], 3, $rot) as $m) {
                    $rows[] = [$c->tr('il y a ' . $m['years_ago'] . ' ans', $m['years_ago'] . ' years ago'), $m['label'], trim($m['competition'] . ($m['round'] !== '' ? ' · ' . $m['round'] : '') . ' · ' . substr($m['date'], 0, 4)), $m['result'] ?? ''];
                }
                return ['layout' => 'list', 'eyebrow' => $c->tr('Ce jour-là', 'On this day'), 'title' => $d['date_label'], 'rows' => $rows];

            case 'carte-legende':
                $d = self::data('carte-legende', $opt, $c, ($opt['joueur'] ?? '') === '' ? $rot : null);
                if (!$d) {
                    return null;
                }
                return ['layout' => 'card', 'eyebrow' => $c->tr('Une légende du club', 'A club legend'), 'title' => $d['name'], 'first' => $d['first'], 'last' => $d['last'],
                    'sub' => trim(($d['position'] ? $d['position'] . ' · ' : '') . $d['period']), 'initials' => $d['initials'],
                    'stats' => [[$d['matches'], $c->tr('matchs', 'matches')], [$d['goals'], $c->tr('buts', 'goals')], [$d['seasons'], $c->tr('saisons', 'seasons')]],
                    'image' => self::image($d['image'] ?? null), 'badge' => $d['international'] ? $c->tr('International', 'International') : ''];

            case 'chiffre-du-jour':
                $d = self::data('chiffre-du-jour', [], $c, $rot);
                if (!$d) {
                    return null;
                }
                return ['layout' => 'hero', 'eyebrow' => $c->tr('Le chiffre du jour', 'Number of the day') . ($d['chapter'] ? ' · ' . $d['chapter'] : ''), 'big' => (string) $d['value'], 'unit' => (string) $d['unit'],
                    'title' => (string) $d['label'], 'text' => (string) $d['text'], 'sub' => implode(', ', array_column((array) ($d['who'] ?? []), 'name'))];

            case 'le-saviez-vous':
                $d = self::data('le-saviez-vous', [], $c, $rot);
                if (!$d) {
                    return null;
                }
                return ['layout' => 'quote', 'eyebrow' => $c->tr('Le saviez-vous ?', 'Did you know?'), 'text' => (string) $d['text'], 'sub' => (string) ($d['source'] ?? ''), 'big' => $d['year'] ? (string) $d['year'] : ''];

            case 'quiz':
                $q = self::quiz(date('Y-m-d'), $c->lang, $slot, $pid, $rot);
                if (!$q) {
                    return null;
                }
                return ['layout' => 'quiz', 'eyebrow' => $c->tr('Le quiz du musée', 'The museum quiz'), 'quiz' => $q, 'qr' => $c->link('/interactif/quiz/'),
                    'qrLabel' => $c->tr('Plus de questions', 'More questions'), 'answerAt' => max(1, min((int) $s['duration'] - 3, max(8, (int) round(((int) $s['duration']) * 0.35))))];

            case 'photo':
                $pool = Photos::pool(1600);
                $big = array_values(array_filter($pool, fn ($p) => $p['w'] >= 900));
                $pool = $big ?: $pool;
                if (!$pool) {
                    return null;
                }
                usort($pool, fn ($a, $b) => strcmp($a['rel'], $b['rel']));
                // « n » fixé : toujours la même photo du jour ; sinon une par tour (ou par slide « photo »).
                $k = ($opt['n'] ?? '') !== '' ? 7 * ((int) $opt['n'] - 1) : ($rot ?? 7 * $slot);
                $p = $pool[(crc32(date('Y-m-d')) + $k) % count($pool)];
                $cap = $c->en() && $p['caption_en'] !== '' ? $p['caption_en'] : $p['caption'];
                return ['layout' => 'photo', 'eyebrow' => $c->tr('Photo d’archives', 'Archive photo') . ($p['year'] ? ' · ' . $p['year'] : ''), 'text' => $cap,
                    'image' => ['url' => $p['url'], 'file' => Media::file($p['rel']), 'credit' => '© ' . $p['credit'] . ($p['license'] !== '' ? ' · ' . $p['license'] : '')]];

            case 'frise':
                $d = self::data('frise', [], $c);
                if (!$d) {
                    return null;
                }
                $ev = $d['events'];
                $per = min(5, count($ev));
                $k = ($opt['n'] ?? '') !== '' ? (int) $opt['n'] - 1 : ($rot ?? $slot);
                $start = (((int) (time() / 86400)) * $per + $k * $per) % count($ev);
                $pick = [];
                for ($k = 0; $k < $per; $k++) {
                    $pick[] = $ev[($start + $k) % count($ev)];
                }
                usort($pick, fn ($a, $b) => $a['year'] <=> $b['year']);
                return ['layout' => 'timeline', 'eyebrow' => $c->tr('La frise du club', 'The club’s timeline'), 'events' => array_map(fn ($e) => [$e['year'], $e['title'], $e['text']], $pick)];

            case 'records':
                // Classement choisi, sinon automatique : l'un puis l'autre (d'un tour ou d'une slide à l'autre).
                $cats = array_keys(\App\Widgets\Items\Records::CATS);
                $d = self::data('records', ['cat' => ($opt['cat'] ?? '') !== '' ? $opt['cat'] : $cats[($rot ?? $slot) % count($cats)]], $c);
                if (!$d) {
                    return null;
                }
                return ['layout' => 'rank', 'eyebrow' => $c->tr('Le livre des records', 'The record book'), 'title' => $d['title'],
                    'rows' => array_map(fn ($r) => [$r['rank'], $r['name'], $r['meta'], $num($r['value']) . ' ' . $d['unit']], $d['rows'])];

            case 'palmares':
                $d = self::data('palmares', [], $c);
                if (!$d) {
                    return null;
                }
                $rows = [];
                foreach (array_slice($d['groups'], 0, 5) as $g) {
                    $years = array_map(fn ($t) => (string) $t['year'], $g['titles']);
                    $rows[] = [(string) $g['count'], $g['competition'], implode(', ', count($years) > 8 ? array_merge(array_slice($years, 0, 7), ['…']) : $years)];
                }
                // Un titre mis en avant (un autre à chaque tour).
                $all = [];
                foreach ($d['groups'] as $g) {
                    foreach ($g['titles'] as $t) {
                        $all[] = $g['competition'] . ' ' . ($t['season'] !== '' ? $t['season'] : $t['year']);
                    }
                }
                $feat = $all ? $all[(crc32(date('Y-m-d')) + ($rot ?? $slot)) % count($all)] : '';
                return ['layout' => 'list', 'eyebrow' => $c->tr('Le palmarès', 'Honours'), 'title' => $c->tr($d['total'] . ' titres', $d['total'] . ' titles'), 'rows' => $rows, 'trophy' => true,
                    'sub' => $feat !== '' ? '🏆 ' . $c->tr('À l’honneur : ', 'In the spotlight: ') . $feat : ''];

            case 'il-y-a':
                $d = self::data('il-y-a', [], $c);
                if (!$d || empty($d['items'])) {
                    return null;
                }
                $rows = array_map(fn ($it) => [$c->tr($it['years'] . ' ans', $it['years'] . ' years'), $it['title'] . ($it['score'] ? ' ' . $it['score'] : ''), $it['kind'] . ' · ' . $date($it['date'])], self::window($d['items'], 4, $rot));
                return ['layout' => 'list', 'eyebrow' => $c->tr('Il y a…', 'Years ago…'), 'title' => $c->tr('5, 10, 20, 30 ans', '5, 10, 20, 30 years'), 'rows' => $rows];

            case 'dernier-match':
                $d = self::data('dernier-match', [], $c);
                if (!$d) {
                    return null;
                }
                return ['layout' => 'match', 'eyebrow' => $c->tr('Dernier match fiché', 'Latest match on record') . ' · ' . $date($d['date']), 'home' => $d['home'], 'away' => $d['away'],
                    'score' => $d['score'] !== '' ? str_replace('–', '-', $d['score']) : '–', 'sub' => trim($d['competition'] . ($d['round'] !== '' ? ' · ' . $d['round'] : '')),
                    'text' => \App\Widgets\Widget::excerpt($d['text'], 220), 'image' => self::image($d['image'] ?? null)];

            case 'saison':
                $d = self::data('saison', $opt, $c);
                if (!$d) {
                    return null;
                }
                $stats = [];
                if ($d['position']) {
                    $stats[] = [$d['position']['rank'], $c->tr('classement', 'position'), true];
                }
                array_push($stats, [$d['wins'], $c->tr('victoires', 'wins')], [$d['draws'], $c->tr('nuls', 'draws')], [$d['defeats'], $c->tr('défaites', 'defeats')]);
                $rows = array_map(fn ($sc) => [(string) $sc['goals'], $sc['name'], $c->tr($sc['goals'] > 1 ? 'buts' : 'but', $sc['goals'] > 1 ? 'goals' : 'goal')], array_slice($d['scorers'], 0, 3));
                return ['layout' => 'duel', 'eyebrow' => $c->tr('La saison', 'The season') . ($d['division'] ? ' · ' . $d['division'] : ''), 'title' => $c->tr('Saison ', 'Season ') . $d['label'],
                    'stats' => $stats, 'sub' => $d['titles'] ? '🏆 ' . implode(' · ', array_column($d['titles'], 'competition')) : ($d['coach'] ? $c->tr('Entraîneur : ', 'Coach: ') . $d['coach']['name'] : ''),
                    'rows' => array_map(fn ($r) => [$c->tr('Buteur', 'Scorer'), $r[1], $r[0] . ' ' . $r[2]], $rows)];

            case 'compte-a-rebours':
                $d = self::data('compte-a-rebours', [], $c);
                if (!$d) {
                    return null;
                }
                return ['layout' => 'countdown', 'eyebrow' => $c->tr('Compte à rebours', 'Countdown'), 'title' => $d['title'], 'big' => (string) $d['days'], 'unit' => $c->tr('jours', 'days'),
                    'countdown' => (int) $d['timestamp'], 'sub' => $c->tr('Rendez-vous le ', 'See you on ') . $date($d['date'])];

            case 'retro-direct':
                $d = self::data('retro-direct', [], $c);
                if (!$d) {
                    return null;
                }
                return ['layout' => 'match', 'eyebrow' => 'Rétro-Direct · ' . \App\Widgets\Items\RetroDirectWidget::when($d, $c), 'home' => $d['home'], 'away' => $d['away'], 'score' => '×',
                    'sub' => trim($d['competition'] . ($d['played'] !== '' ? ' · ' . $date($d['played']) : '')), 'text' => $d['intro'], 'image' => self::image($d['image'] ?? null),
                    'live' => $d['state'] === 'direct', 'qr' => $c->link($d['href'])];

            case 'carte-stades':
                $d = self::data('carte-stades', $opt + ['zone' => 'france'], $c);
                if (!$d) {
                    return null;
                }
                return ['layout' => 'map', 'eyebrow' => $c->tr('Carte des stades', 'Map of the grounds'), 'title' => $c->tr($d['stadiums'] . ' stades', $d['stadiums'] . ' grounds'),
                    'map' => $d, 'rows' => array_map(fn ($s, $i) => [(string) ($i + 1), $s['name'], $s['city'], $s['matches'] . ' ' . $c->tr('matchs', 'matches')], $d['top'], array_keys($d['top']))];

            case 'soutenir':
                $d = self::data('soutenir', [], $c);
                if (!$d) {
                    return null;
                }
                return ['layout' => 'support', 'eyebrow' => $c->tr('Soutenir le musée', 'Support the museum'), 'title' => $c->tr('Le musée a besoin de vous', 'The museum needs you'),
                    'items' => array_map(fn ($l) => ['label' => $l['label'], 'text' => $l['text'], 'qr' => $c->link($l['href'])], array_slice($d['links'], 0, 3))];

            case 'boutique':
                $d = self::data('boutique', $opt, $c, ($opt['produit'] ?? '') === '' ? $rot : null);
                if (!$d) {
                    return null;
                }
                return ['layout' => 'support', 'eyebrow' => $c->tr('La boutique du musée', 'The museum shop'), 'title' => $c->tr('Aux couleurs du musée', 'In the museum colours'),
                    'items' => array_map(fn ($i) => ['label' => $i['name'], 'text' => trim($i['kind'] . ' · ' . $i['price_label'], ' ·'), 'qr' => $c->link($i['href'])], $d['items'])];

            case 'don':
                if (!\App\Widgets\Items\Don::isOpen()) {
                    return null;
                }
                $g = \App\Widgets\Items\Don::gauge();
                $euros = fn (int $n) => \App\Widgets\Items\Don::euros($n, $c);
                return ['layout' => 'don', 'eyebrow' => $c->tr('Soutenir le musée', 'Support the museum'), 'title' => $c->tr('Faire un don', 'Donate'),
                    'text' => $c->tr('Le musée vit de bénévolat et de dons : chaque euro aide à numériser et partager les archives du club.', 'The museum runs on volunteers and donations: every euro helps digitise and share the club’s archives.'),
                    'gauge' => $g ? ['raised' => $euros($g['raised']), 'goal' => $c->tr('sur ', 'of ') . $euros($g['goal']), 'pct' => (float) $g['pct'],
                        'note' => ($c->en() ? (string) $g['pct'] : str_replace('.', ',', (string) $g['pct'])) . ' % · ' . ($c->en() ? t($g['label']) : $g['label'])] : null,
                    'qr' => \App\Widgets\Items\Don::external() ?? $c->link(\App\Widgets\Items\Don::PATH), 'caption' => $c->tr('Scannez pour donner', 'Scan to donate')];
        }
        return null;
    }

    /**
     * Histoire du duel contre un club jamais affronté : « Première rencontre » (mise en page duel), avec
     * les faits du musée (bilan des premières rencontres, dernière première rencontre, clubs du même nom).
     */
    private static function firstMeeting(array $d, Context $c, \Closure $date): array
    {
        $f = $d['firsts'];
        $rows = [];
        if ($d['latest_first']) {
            $l = $d['latest_first'];
            $rows[] = [$c->tr('Dernière première rencontre', 'Latest first meeting'), $l['label'], $date($l['date'])];
        }
        foreach (array_slice($d['namesakes'], 0, 1) as $n) {
            $rows[] = [$c->tr('Déjà affronté', 'Already met'), $n['name'], $n['matches'] . ' ' . $c->tr('matchs', 'matches') . ' · ' . $n['wins'] . ' V, ' . $n['draws'] . ' N, ' . $n['defeats'] . ' D'];
        }
        return ['layout' => 'duel', 'eyebrow' => $c->tr('Première rencontre', 'First meeting'), 'title' => $d['team'] . ' × ' . $d['opponent'], 'first' => true,
            'lead' => $c->tr('Premier match officiel entre les deux clubs', 'First official match between the two clubs'),
            'stats' => [[$d['opponents'], $c->tr('adversaires déjà affrontés', 'opponents already met'), true], [$f['wins'], $c->tr('victoires', 'wins')], [$f['draws'], $c->tr('nuls', 'draws')], [$f['defeats'], $c->tr('défaites', 'defeats')]],
            'bar' => [$f['wins'], $f['draws'], $f['defeats']],
            'sub' => $c->tr('Bilan lors des ' . $f['total'] . ' premières rencontres fichées de l’histoire du club', 'Record in the club’s ' . $f['total'] . ' first meetings on record'),
            'rows' => $rows, 'qr' => $c->link($d['href'])];
    }

    /**
     * Question du quiz pour l'écran (avec la réponse : elle s'affiche à l'écran) : tirée de la même
     * collection que le quiz du site, différente pour chaque quiz de la playlist et jamais celle du
     * « quiz du jour » (dont la réponse reste secrète dans le widget).
     * @return array{q:string,a:list<string>,c:int,fact:string}|null
     */
    public static function quiz(string $date, string $lang, int $slot, string $pid = '', ?int $rot = null): ?array
    {
        $all = [];
        foreach (Collections::get('quiz', Seeds::quiz()) as $q) {
            if (is_array($q) && ($q['active'] ?? true) && !empty($q['q']) && count($q['a'] ?? []) >= 2 && count($q['a']) <= 4 && isset($q['a'][(int) ($q['c'] ?? 0)])) {
                $all[] = $q;
            }
        }
        $daily = QuizDuJour::question($date, 'fr')['q'] ?? null;
        $list = count($all) > 1 ? array_values(array_filter($all, fn ($q) => $q['q'] !== $daily)) : $all;
        if (!$list) {
            return null;
        }
        $pick = $list[(crc32('ecran|' . $date . '|' . $pid) + ($rot ?? $slot * 7)) % count($list)];
        $loc = $lang === 'en' ? Collections::loc($pick, ['q', 'a', 'fact']) : $pick;
        $a = array_values(array_map('strval', (array) $loc['a']));
        if (count($a) !== count((array) $pick['a'])) {
            $a = array_values(array_map('strval', (array) $pick['a']));
        }
        return ['q' => (string) $loc['q'], 'a' => $a, 'c' => (int) $pick['c'], 'fact' => \App\Widgets\Widget::excerpt((string) ($loc['fact'] ?? ''), 260)];
    }

    /**
     * $n éléments consécutifs d'une liste (dans l'ordre), à partir du tirage $rot (fenêtre glissante,
     * en boucle) ; la liste entière ou son début si elle est courte ou sans tirage.
     */
    private static function window(array $list, int $n, ?int $rot): array
    {
        $list = array_values($list);
        $total = count($list);
        if ($rot === null || $total <= $n) {
            return array_slice($list, 0, $n);
        }
        $out = [];
        for ($k = 0; $k < $n; $k++) {
            $out[] = $list[($rot * $n + $k) % $total];
        }
        return $out;
    }

    /** Image d'un widget (adresse complète, crédit) avec son fichier local pour le rendu PNG. */
    private static function image(?array $img): ?array
    {
        if (!$img || empty($img['url'])) {
            return null;
        }
        return ['url' => (string) $img['url'], 'file' => self::localFile($img), 'credit' => ($img['credit'] ?? '') !== '' ? '© ' . $img['credit'] . (($img['license'] ?? '') !== '' ? ' · ' . $img['license'] : '') : ''];
    }

    /** Fichier original d'une image de widget (« rel », sinon déduit de son adresse /media/<l>/…webp). */
    public static function localFile(array $img): ?string
    {
        if (!empty($img['rel'])) {
            return Media::file((string) $img['rel']);
        }
        $path = (string) parse_url((string) $img['url'], PHP_URL_PATH);
        if (preg_match('#^' . preg_quote(MEDIA_URL, '#') . '/\d+/(.+)\.webp$#', $path, $m)) {
            return Media::file(rawurldecode($m[1]));
        }
        return null;
    }
}
