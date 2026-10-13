<?php
declare(strict_types=1);

namespace App\Widgets\Screen;

use App\Data\Collections;
use App\Data\Media;
use App\Data\Seeds;
use App\Widgets\Cache;
use App\Widgets\Context;
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
    public static function build(array $playlist, Context $c, string $adversaire = ''): array
    {
        $out = [];
        $quiz = 0;
        foreach ($playlist['slides'] as $i => $s) {
            try {
                $m = self::one($s, $c, $adversaire !== '' ? $adversaire : (string) ($playlist['adversaire'] ?? ''), $s['type'] === 'quiz' ? $quiz++ : 0, (string) $playlist['id']);
            } catch (\Throwable $e) {
                error_log('Écran ' . $playlist['id'] . ', slide ' . $s['type'] . ' : ' . $e->getMessage());
                $m = null;
            }
            if ($m !== null) {
                $out[] = $m + ['type' => $s['type'], 'duration' => (int) $s['duration'], 'n' => $i + 1];
            }
        }
        return $out;
    }

    /** Données d'un widget (cache commun aux iframes et à l'API). */
    private static function data(string $key, array $opt, Context $c): ?array
    {
        $w = Registry::get($key);
        return $w ? Cache::get($w, $w->clean($opt), $c)['data'] : null;
    }

    /** Une slide, ou null si rien à montrer. */
    public static function one(array $s, Context $c, string $adversaire = '', int $quizSlot = 0, string $pid = ''): ?array
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
                $d = self::data('ce-jour-la', ['nombre' => '3'], $c);
                if (!$d || empty($d['matches'])) {
                    return null;
                }
                $rows = [];
                foreach (array_slice($d['matches'], 0, 3) as $m) {
                    $rows[] = [$c->tr('il y a ' . $m['years_ago'] . ' ans', $m['years_ago'] . ' years ago'), $m['label'], trim($m['competition'] . ($m['round'] !== '' ? ' · ' . $m['round'] : '') . ' · ' . substr($m['date'], 0, 4)), $m['result'] ?? ''];
                }
                return ['layout' => 'list', 'eyebrow' => $c->tr('Ce jour-là', 'On this day'), 'title' => $d['date_label'], 'rows' => $rows];

            case 'carte-legende':
                $d = self::data('carte-legende', [], $c);
                if (!$d) {
                    return null;
                }
                return ['layout' => 'card', 'eyebrow' => $c->tr('Une légende du club', 'A club legend'), 'title' => $d['name'], 'first' => $d['first'], 'last' => $d['last'],
                    'sub' => trim(($d['position'] ? $d['position'] . ' · ' : '') . $d['period']), 'initials' => $d['initials'],
                    'stats' => [[$d['matches'], $c->tr('matchs', 'matches')], [$d['goals'], $c->tr('buts', 'goals')], [$d['seasons'], $c->tr('saisons', 'seasons')]],
                    'image' => self::image($d['image'] ?? null), 'badge' => $d['international'] ? $c->tr('International', 'International') : ''];

            case 'chiffre-du-jour':
                $d = self::data('chiffre-du-jour', [], $c);
                if (!$d) {
                    return null;
                }
                return ['layout' => 'hero', 'eyebrow' => $c->tr('Le chiffre du jour', 'Number of the day') . ($d['chapter'] ? ' · ' . $d['chapter'] : ''), 'big' => (string) $d['value'], 'unit' => (string) $d['unit'],
                    'title' => (string) $d['label'], 'text' => (string) $d['text'], 'sub' => implode(', ', array_column((array) ($d['who'] ?? []), 'name'))];

            case 'le-saviez-vous':
                $d = self::data('le-saviez-vous', [], $c);
                if (!$d) {
                    return null;
                }
                return ['layout' => 'quote', 'eyebrow' => $c->tr('Le saviez-vous ?', 'Did you know?'), 'text' => (string) $d['text'], 'sub' => (string) ($d['source'] ?? ''), 'big' => $d['year'] ? (string) $d['year'] : ''];

            case 'quiz':
                $q = self::quiz(date('Y-m-d'), $c->lang, $quizSlot, $pid);
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
                $p = $pool[(crc32(date('Y-m-d')) + 7 * ((int) ($opt['n'] ?? 1) - 1)) % count($pool)];
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
                $start = (((int) (time() / 86400)) * $per + ((int) ($opt['n'] ?? 1) - 1) * $per) % count($ev);
                $pick = [];
                for ($k = 0; $k < $per; $k++) {
                    $pick[] = $ev[($start + $k) % count($ev)];
                }
                usort($pick, fn ($a, $b) => $a['year'] <=> $b['year']);
                return ['layout' => 'timeline', 'eyebrow' => $c->tr('La frise du club', 'The club’s timeline'), 'events' => array_map(fn ($e) => [$e['year'], $e['title'], $e['text']], $pick)];

            case 'records':
                $d = self::data('records', ['cat' => $opt['cat'] ?? 'buteurs'], $c);
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
                return ['layout' => 'list', 'eyebrow' => $c->tr('Le palmarès', 'Honours'), 'title' => $c->tr($d['total'] . ' titres', $d['total'] . ' titles'), 'rows' => $rows, 'trophy' => true];

            case 'il-y-a':
                $d = self::data('il-y-a', [], $c);
                if (!$d || empty($d['items'])) {
                    return null;
                }
                $rows = array_map(fn ($it) => [$c->tr($it['years'] . ' ans', $it['years'] . ' years'), $it['title'] . ($it['score'] ? ' ' . $it['score'] : ''), $it['kind'] . ' · ' . $date($it['date'])], array_slice($d['items'], 0, 4));
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
                $d = self::data('saison', ['saison' => $opt['saison'] ?? ''], $c);
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

            case 'maillot':
                $d = self::data('maillot', [], $c);
                if (!$d) {
                    return null;
                }
                return ['layout' => 'hero', 'eyebrow' => $c->tr('Le maillot de l’époque', 'Shirt of an era'), 'big' => (string) $d['era'], 'unit' => $d['until'] ? '– ' . $d['until'] : '',
                    'title' => $d['label'], 'text' => $d['text'], 'image' => self::image($d['image'] ?? null)];

            case 'retro-direct':
                $d = self::data('retro-direct', [], $c);
                if (!$d) {
                    return null;
                }
                return ['layout' => 'match', 'eyebrow' => 'Rétro-Direct · ' . \App\Widgets\Items\RetroDirectWidget::when($d, $c), 'home' => $d['home'], 'away' => $d['away'], 'score' => '×',
                    'sub' => trim($d['competition'] . ($d['played'] !== '' ? ' · ' . $date($d['played']) : '')), 'text' => $d['intro'], 'image' => self::image($d['image'] ?? null),
                    'live' => $d['state'] === 'direct', 'qr' => $c->link($d['href'])];

            case 'carte-stades':
                $d = self::data('carte-stades', ['zone' => 'france'], $c);
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
                $d = self::data('boutique', [], $c);
                if (!$d) {
                    return null;
                }
                return ['layout' => 'support', 'eyebrow' => $c->tr('La boutique du musée', 'The museum shop'), 'title' => $c->tr('Aux couleurs du musée', 'In the museum colours'),
                    'items' => array_map(fn ($i) => ['label' => $i['name'], 'text' => trim($i['kind'] . ' · ' . $i['price_label'], ' ·'), 'qr' => $c->link($i['href'])], $d['items'])];
        }
        return null;
    }

    /**
     * Question du quiz pour l'écran (avec la réponse : elle s'affiche à l'écran) : tirée de la même
     * collection que le quiz du site, différente pour chaque quiz de la playlist et jamais celle du
     * « quiz du jour » (dont la réponse reste secrète dans le widget).
     * @return array{q:string,a:list<string>,c:int,fact:string}|null
     */
    public static function quiz(string $date, string $lang, int $slot, string $pid = ''): ?array
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
        $pick = $list[(crc32('ecran|' . $date . '|' . $pid) + $slot * 7) % count($list)];
        $loc = $lang === 'en' ? Collections::loc($pick, ['q', 'a', 'fact']) : $pick;
        $a = array_values(array_map('strval', (array) $loc['a']));
        if (count($a) !== count((array) $pick['a'])) {
            $a = array_values(array_map('strval', (array) $pick['a']));
        }
        return ['q' => (string) $loc['q'], 'a' => $a, 'c' => (int) $pick['c'], 'fact' => \App\Widgets\Widget::excerpt((string) ($loc['fact'] ?? ''), 260)];
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
