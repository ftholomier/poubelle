<?php
declare(strict_types=1);

namespace App\Admin;

use App\Data\Categories;
use App\Data\Media;
use App\Data\Names;
use App\Data\Paths;

/**
 * Applique les données saisies dans le masque de fiche (JSON envoyé par admin.js)
 * à la fiche existante. Seuls les champs connus sont pris en compte ; les textes
 * sont nettoyés, les nombres et les dates contrôlés, et les champs calculés
 * (saison, résultat, minutes des buts, tailles…) sont déduits automatiquement.
 * Les champs absents du formulaire restent inchangés (aucune donnée perdue).
 */
final class FicheForm
{
    public const COMPETITIONS = ['Championnat', 'Coupe de France', 'Coupe de la Ligue', "Coupe d'Europe", 'Barrages', 'Coupe Charles Drago', 'Coupes diverses', "Coupe d'été", 'Amical'];
    public const POSITIONS = ['G' => 'Gardien', 'D' => 'Défenseur', 'M' => 'Milieu', 'A' => 'Attaquant', 'R' => 'Remplaçant', 'E' => 'Entraîneur', '' => 'Autre'];
    public const ROLES = ['joueur' => 'Joueur', 'entraineur' => 'Entraîneur', 'dirigeant' => 'Dirigeant', 'personnage' => 'Personnage emblématique'];
    public const LINES = ['' => '—', 'G' => 'Gardien', 'D' => 'Défenseur', 'M' => 'Milieu', 'A' => 'Attaquant'];
    public const RARITIES = ['legende' => 'Légende', 'classique' => 'Classique', 'actuel' => 'Actuel'];
    public const KINDS = ['article' => 'Article', 'bilan_saison' => 'Bilan de saison', 'portrait' => 'Portrait thématique', 'dossier' => 'Dossier'];

    /** @param array<string,string> $errors champ => message */
    public static function apply(array $doc, array $in, array &$errors): array
    {
        $has = fn (string $k) => array_key_exists($k, $in);
        if ($has('title')) {
            $doc['title'] = Html::line($in['title'], 250);
        }
        if ($has('status') && isset(\App\Data\Fiches::STATUSES[$in['status']]) && $in['status'] !== 'corbeille') {
            $doc['status'] = $in['status'];
        }
        if ($has('publish_at')) {
            $doc['publish_at'] = self::datetime($in['publish_at']);
            if ($doc['status'] === 'planifie' && !$doc['publish_at']) {
                $errors['publish_at'] = 'Indiquez la date et l’heure de publication.';
            }
        }
        if ($has('a_la_une')) {
            $doc['a_la_une'] = (bool) $in['a_la_une'];
        }
        if ($has('categories') || $has('categories__present')) {
            $known = Categories::all();
            $doc['categories'] = array_values(array_unique(array_filter(array_map('strval', (array) ($in['categories'] ?? [])), fn ($c) => isset($known[$c]))));
        }
        if ($has('featured_image')) {
            $doc['featured_image'] = self::media($in['featured_image']);
        }
        if ($has('date')) {
            $doc['date'] = self::datetime($in['date']) ?? $doc['date'];
        }
        if ($has('intro')) {
            $doc['intro'] = Html::clean((string) $in['intro']);
        }
        if ($has('sections')) {
            $doc['sections'] = [];
            foreach ((array) $in['sections'] as $s) {
                $t = Html::line($s['title'] ?? '', 200);
                $h = Html::clean((string) ($s['html'] ?? ''));
                if ($t !== '' || $h !== '') {
                    $doc['sections'][] = ['title' => $t, 'html' => $h] + array_diff_key((array) $s, ['title' => 1, 'html' => 1]) ;
                }
            }
            $doc['sections'] = array_map(fn ($s) => array_intersect_key($s, ['title' => 1, 'html' => 1, 'kind' => 1]), $doc['sections']);
        }
        if ($has('key_figure')) {
            $n = Html::line($in['key_figure']['number'] ?? '', 30);
            $t = Html::line($in['key_figure']['text'] ?? '', 400);
            $doc['key_figure'] = $n === '' && $t === '' ? null : ['number' => $n, 'text' => $t];
        }
        if ($has('gallery')) {
            $doc['gallery'] = [];
            foreach ((array) $in['gallery'] as $g) {
                $img = self::media($g['image'] ?? null);
                if (!$img) {
                    continue;
                }
                $cap = Html::line($g['caption'] ?? '', 300);
                $cred = Html::line($g['credit'] ?? '', 200);
                $doc['gallery'][] = ['image' => $img, 'caption' => $cap, 'credit' => $cred, 'caption_raw' => trim($cap . ($cred !== '' ? ' – ' . $cred : ''))];
            }
        }
        if ($has('images')) {
            $doc['images'] = [];
            foreach ((array) $in['images'] as $g) {
                if ($img = self::media($g['image'] ?? null)) {
                    $doc['images'][] = ['image' => $img, 'caption' => Html::line($g['caption'] ?? '', 300), 'in_text' => (bool) ($g['in_text'] ?? false)];
                }
            }
        }
        if ($has('videos')) {
            $doc['videos'] = [];
            foreach ((array) $in['videos'] as $v) {
                $parsed = self::video((string) ($v['url'] ?? ''), (string) ($v['provider'] ?? ''), (string) ($v['id'] ?? ''));
                if ($parsed) {
                    $doc['videos'][] = $parsed + ['title' => Html::line($v['title'] ?? '', 200)];
                }
            }
        }
        if ($has('embeds')) {
            $doc['embeds'] = [];
            foreach ((array) $in['embeds'] as $em) {
                $url = trim((string) ($em['url'] ?? ''));
                if ($url !== '' && preg_match('#^https://#', $url)) {
                    $prov = preg_match('#(twitter\.com|x\.com)#', $url) ? 'x' : (str_contains($url, 'instagram.com') ? 'instagram' : (str_contains($url, 'facebook.com') ? 'facebook' : 'web'));
                    $doc['embeds'][] = ['provider' => $prov, 'url' => $url, 'text' => Html::clean(mb_substr((string) ($em['text'] ?? ''), 0, 8000))];
                }
            }
        }
        if ($has('tables')) {
            $doc['tables'] = self::tables((array) $in['tables']);
        }
        if ($has('seo')) {
            $doc['seo'] = ['title' => Html::line($in['seo']['title'] ?? '', 160), 'description' => Html::line($in['seo']['description'] ?? '', 320)];
        }

        $doc = match ($doc['type']) {
            'match' => self::match($doc, $in['match'] ?? null, $errors),
            'personne' => self::person($doc, $in['personne'] ?? null, $errors),
            'objet' => self::objet($doc, $in['objet'] ?? null),
            'moment' => self::moment($doc, $in['moment'] ?? null),
            default => self::article($doc, $in['article'] ?? null),
        };

        if ($has('i18n_en')) {
            $doc = self::english($doc, (array) $in['i18n_en']);
        }

        // Titre automatique pour les matchs et personnes sans titre saisi.
        if (trim((string) $doc['title']) === '') {
            $doc['title'] = self::autoTitle($doc);
        }
        if ($doc['title'] === '') {
            $errors['title'] = 'Le titre est obligatoire.';
        }
        return $doc;
    }

    // ------------------------------------------------------------------ types

    private static function match(array $doc, ?array $m, array &$errors): array
    {
        if ($m === null) {
            return $doc;
        }
        $cur = $doc['match'];
        if (array_key_exists('date', $m)) {
            $d = trim((string) $m['date']);
            if ($d !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                $errors['match.date'] = 'Date invalide (jj/mm/aaaa).';
            } else {
                $cur['date'] = $d ?: null;
            }
        }
        $cur['date_text'] = $cur['date'] ? date_fr($cur['date'], true) : Html::line($m['date_text'] ?? ($cur['date_text'] ?? ''), 80);
        $cur['season'] = $cur['date'] ? Paths::seasonOf($cur['date']) : (preg_match('/^\d{4}-\d{4}$/', (string) ($m['season'] ?? '')) ? $m['season'] : $cur['season']);
        foreach (['competition_label' => 80, 'competition_code' => 20, 'round' => 40, 'round_text' => 120, 'stadium' => 160, 'referee' => 120, 'goals_text' => 400, 'event' => 160, 'formation' => 20, 'spectators_text' => 80] as $k => $max) {
            if (array_key_exists($k, $m)) {
                $cur[$k] = Html::line($m[$k], $max);
            }
        }
        if (array_key_exists('competition', $m)) {
            $cur['competition'] = in_array($m['competition'], self::COMPETITIONS, true) ? $m['competition'] : Html::line($m['competition'], 80);
        }
        if (array_key_exists('venue', $m)) {
            $cur['sochaux_home'] = $m['venue'] !== 'exterieur';
        }
        if (array_key_exists('opponent', $m)) {
            $opp = Html::line($m['opponent'], 120);
            $lvlOpp = Html::line($m['opponent_level'] ?? '', 30) ?: null;
            $lvlUs = Html::line($m['sochaux_level'] ?? '', 30) ?: null;
            $us = ['name' => 'Sochaux', 'level' => $lvlUs];
            $them = ['name' => $opp, 'level' => $lvlOpp];
            $cur['home'] = $cur['sochaux_home'] ? $us : $them;
            $cur['away'] = $cur['sochaux_home'] ? $them : $us;
            $cur['opponent_club'] = $opp !== '' ? Names::clubKey($opp) : null;
        }
        if (array_key_exists('spectators', $m)) {
            $cur['spectators'] = self::int($m['spectators']);
        }
        if (array_key_exists('score_home', $m)) {
            $h = self::int($m['score_home']);
            $a = self::int($m['score_away'] ?? null);
            $extra = in_array($m['extra'] ?? '', ['ap', 'tab'], true) ? $m['extra'] : null;
            $pens = $extra === 'tab' && self::int($m['pens_home'] ?? null) !== null ? ['home' => self::int($m['pens_home']), 'away' => self::int($m['pens_away'] ?? null)] : null;
            $cur['score'] = $h === null || $a === null ? null : ['home' => $h, 'away' => $a, 'extra' => $extra, 'aet' => $extra !== null, 'pens' => $pens];
            $cur['score_raw'] = $cur['score'] ? $h . '-' . $a . ($extra === 'ap' ? ' a.p.' : '') . ($pens ? ' (' . $pens['home'] . '-' . $pens['away'] . ' tab)' : '') : '';
            $cur['result'] = self::result($cur);
        }
        if (array_key_exists('goals', $m)) {
            $cur['goals'] = [];
            foreach ((array) $m['goals'] as $g) {
                $sc = Html::line($g['scorers'] ?? '', 400);
                if ($sc !== '') {
                    $cur['goals'][] = ['team' => Html::line($g['team'] ?? '', 80), 'scorers' => $sc];
                }
            }
        }
        if (array_key_exists('header_extra', $m)) {
            $cur['header_extra'] = array_values(array_filter(array_map(fn ($x) => Html::line($x, 400), (array) $m['header_extra'])));
        }
        if (array_key_exists('lineup', $m)) {
            $rows = [];
            foreach ((array) ($m['lineup'] ?? []) as $r) {
                $name = Html::line($r['name'] ?? '', 120);
                if ($name === '') {
                    continue;
                }
                $goalsText = Html::line($r['goals_text'] ?? '', 120);
                $subText = Html::line($r['sub_text'] ?? '', 120);
                $cardsText = Html::line($r['cards_text'] ?? '', 120);
                preg_match_all("/(\d{1,3}(?:\+\d{1,2})?)\s*'?/", $goalsText, $gm);
                $subIn = preg_match("/entr[ée]e?\s*(\d{1,3})/iu", $subText, $sm) ? $sm[1] : null;
                $subOut = preg_match("/sorti[e]?\s*(\d{1,3})/iu", $subText, $so) ? $so[1] : null;
                preg_match_all("/\bJ\s*(\d{1,3})/u", $cardsText, $ym);
                preg_match_all("/\bR\s*(\d{1,3})/u", $cardsText, $rm);
                $pos = (string) ($r['position'] ?? '');
                $rows[] = [
                    'position' => array_key_exists($pos, self::POSITIONS) ? $pos : Html::line($pos, 10),
                    'name' => $name,
                    'captain' => (bool) ($r['captain'] ?? false),
                    'goals' => $goalsText !== '' ? ($gm[1] ?: [$goalsText]) : [],
                    'goals_text' => $goalsText,
                    'sub_in' => $subIn,
                    'sub_out' => $subOut,
                    'sub_text' => $subText,
                    'yellow' => $ym[1],
                    'red' => $rm[1],
                    'cards_text' => $cardsText,
                    'person_id' => self::int($r['person_id'] ?? null),
                ];
            }
            $cur['lineup']['rows'] = $rows;
            $cur['lineup']['title'] = $cur['lineup']['title'] ?? 'Composition Sochaux';
            $cur['lineup']['headers'] = $cur['lineup']['headers'] ?? ['Postes', 'Nom et prénom', 'Buts', 'Remp.', 'Cartons'];
        }
        if (array_key_exists('highlights', $m)) {
            $cur['highlights'] = [];
            foreach ((array) $m['highlights'] as $h) {
                $t = Html::line($h['text'] ?? '', 1200);
                if ($t !== '') {
                    $cur['highlights'][] = ['minute' => Html::line($h['minute'] ?? '', 10), 'text' => $t, 'goal' => (bool) ($h['goal'] ?? false), 'score' => Html::line($h['score'] ?? '', 12) ?: null];
                }
            }
        }
        if (array_key_exists('reactions', $m)) {
            $cur['reactions'] = [];
            foreach ((array) $m['reactions'] as $r) {
                $t = Html::clean(mb_substr((string) ($r['text'] ?? ''), 0, 6000));
                if ($t !== '') {
                    $cur['reactions'][] = ['who' => Html::line($r['who'] ?? '', 120), 'text' => $t];
                }
            }
        }
        if (array_key_exists('breves', $m)) {
            $cur['breves'] = array_values(array_filter(array_map(fn ($x) => Html::clean(mb_substr((string) (is_array($x) ? ($x['_'] ?? '') : $x), 0, 4000)), (array) $m['breves'])));
        }
        $doc['match'] = $cur;
        return $doc;
    }

    private static function result(array $m): ?string
    {
        $s = $m['score'] ?? null;
        if (!$s) {
            return null;
        }
        $us = $m['sochaux_home'] ? $s['home'] : $s['away'];
        $them = $m['sochaux_home'] ? $s['away'] : $s['home'];
        if ($us === $them && !empty($s['pens'])) {
            $pu = $m['sochaux_home'] ? $s['pens']['home'] : $s['pens']['away'];
            $pt = $m['sochaux_home'] ? $s['pens']['away'] : $s['pens']['home'];
            return $pu > $pt ? 'V' : ($pu < $pt ? 'D' : 'N');
        }
        return $us > $them ? 'V' : ($us < $them ? 'D' : 'N');
    }

    private static function person(array $doc, ?array $p, array &$errors): array
    {
        if ($p === null) {
            return $doc;
        }
        $cur = $doc['personne'];
        if (array_key_exists('roles', $p) || array_key_exists('roles__present', $p)) {
            $roles = array_values(array_filter((array) ($p['roles'] ?? []), fn ($r) => isset(self::ROLES[$r])));
            if (!$roles) {
                $errors['personne.roles'] = 'Choisissez au moins une rubrique (joueur, entraîneur…).';
            } else {
                $cur['roles'] = $roles;
            }
        }
        foreach (['first_name' => 80, 'last_name' => 80, 'display_name' => 160, 'nickname' => 120, 'subtitle' => 300, 'position' => 120, 'nationality' => 80, 'foot' => 40, 'shirt_numbers' => 80, 'first_match' => 300, 'last_match' => 300, 'first_goal' => 300, 'first_match_coached' => 300, 'last_match_coached' => 300] as $k => $max) {
            if (array_key_exists($k, $p)) {
                $cur[$k] = Html::line($p[$k], $max);
            }
        }
        if (($cur['display_name'] ?? '') === '') {
            $cur['display_name'] = trim(($cur['first_name'] ?? '') . ' ' . ($cur['last_name'] ?? ''));
        }
        if (array_key_exists('line', $p)) {
            $cur['line'] = isset(self::LINES[$p['line']]) && $p['line'] !== '' ? $p['line'] : null;
        }
        foreach (['formed_at_club', 'international_flag', 'is_trial', 'legend', 'on_map'] as $k) {
            if (array_key_exists($k, $p)) {
                $cur[$k] = (bool) $p[$k];
            }
        }
        if (array_key_exists('height', $p)) {
            $cur['height'] = Html::line($p['height'], 20);
            $cur['height_cm'] = preg_match('/(\d)\s*m\s*(\d{1,2})/u', $cur['height'], $hm) ? (int) $hm[1] * 100 + (int) str_pad($hm[2], 2, '0') : (preg_match('/^(\d{3})/', $cur['height'], $hm) ? (int) $hm[1] : null);
        }
        if (array_key_exists('weight', $p)) {
            $cur['weight'] = Html::line($p['weight'], 20);
            $cur['weight_kg'] = preg_match('/(\d{2,3})/', $cur['weight'], $wm) ? (int) $wm[1] : null;
        }
        foreach (['arrival', 'departure', 'arrival_coach', 'departure_coach', 'trial'] as $k) {
            if (array_key_exists($k, $p)) {
                $cur[$k] = self::fuzzyDate((string) $p[$k]);
                if (trim((string) $p[$k]) !== '' && !$cur[$k]) {
                    $errors["personne.$k"] = 'Date non reconnue : écrivez par ex. « 12/1964 », « juillet 1980 » ou « 1980 ».';
                }
            }
        }
        if (array_key_exists('birth_date', $p) || array_key_exists('birth_city', $p)) {
            $date = self::fuzzyDate((string) ($p['birth_date'] ?? ''));
            $city = Html::line($p['birth_city'] ?? '', 120);
            $country = Html::line($p['birth_country'] ?? '', 80);
            $dept = Html::line($p['birth_department'] ?? '', 10);
            $old = $cur['birth']['place'] ?? [];
            $lat = self::float($p['birth_lat'] ?? null);
            $lng = self::float($p['birth_lng'] ?? null);
            if ($city !== ($old['city'] ?? '') && $lat === ($old['lat'] ?? null)) {
                $lat = $lng = null; // nouvelle ville : géolocalisation à refaire automatiquement
            }
            $placeText = $city !== '' ? $city . ($dept !== '' ? " ($dept)" : '') . ($country !== '' && $country !== 'France' ? ", $country" : '') : '';
            $cur['birth'] = $date || $city !== '' ? [
                'date' => $date,
                'place' => $city !== '' ? ['text' => $placeText, 'city' => $city, 'department' => $dept ?: null, 'country' => $country ?: null, 'lat' => $lat, 'lng' => $lng] : null,
                'text' => trim(($date ? 'né le ' . $date['text'] : '') . ($placeText !== '' ? ' à ' . $placeText : '')),
            ] : null;
            if (trim((string) ($p['birth_date'] ?? '')) !== '' && !$date) {
                $errors['personne.birth_date'] = 'Date de naissance non reconnue.';
            }
        }
        if (array_key_exists('death_date', $p) || array_key_exists('death_place', $p)) {
            $date = self::fuzzyDate((string) ($p['death_date'] ?? ''));
            $place = Html::line($p['death_place'] ?? '', 160);
            $cur['death'] = $date || $place !== '' ? ['date' => $date, 'place' => $place !== '' ? ['text' => $place] : null, 'text' => trim(($date ? 'décédé le ' . $date['text'] : '') . ($place !== '' ? ' à ' . $place : ''))] : null;
        }
        foreach (['honours', 'then', 'international'] as $k) {
            if (array_key_exists($k, $p)) {
                $cur[$k] = array_values(array_filter(array_map(fn ($x) => Html::line(is_array($x) ? ($x['_'] ?? '') : $x, 400), (array) $p[$k])));
            }
        }
        if (array_key_exists('fiche', $p)) {
            $cur['fiche'] = [];
            foreach ((array) $p['fiche'] as $r) {
                $v = Html::line($r['value'] ?? '', 600);
                if ($v !== '') {
                    $cur['fiche'][] = ['label' => Html::line($r['label'] ?? '', 120) ?: null, 'value' => $v];
                }
            }
        }
        if (array_key_exists('stats', $p)) {
            $t = self::tables([$p['stats']]);
            $cur['stats'] = $t[0] ?? null;
        }
        if (array_key_exists('album', $p)) {
            $a = (array) $p['album'];
            $cur['album'] = ['in' => (bool) ($a['in'] ?? false), 'rarity' => isset(self::RARITIES[$a['rarity'] ?? '']) ? $a['rarity'] : null, 'number' => self::int($a['number'] ?? null)];
        }
        if (array_key_exists('highlight_matches', $p)) {
            $cur['highlight_matches'] = array_values(array_unique(array_filter(array_map(fn ($x) => self::int(is_array($x) ? ($x['id'] ?? null) : $x), (array) $p['highlight_matches']))));
        }
        $doc['personne'] = $cur;
        return $doc;
    }

    private static function article(array $doc, ?array $a): array
    {
        if ($a === null) {
            return $doc;
        }
        $cur = $doc['article'] ?? [];
        if (array_key_exists('kind', $a)) {
            $cur['kind'] = isset(self::KINDS[$a['kind']]) ? $a['kind'] : 'article';
        }
        foreach (['heading' => 300, 'subtitle' => 300] as $k => $max) {
            if (array_key_exists($k, $a)) {
                $cur[$k] = Html::line($a[$k], $max);
            }
        }
        if (array_key_exists('season', $a)) {
            $cur['season'] = preg_match('/^(\d{4})-(\d{4})$/', (string) $a['season'], $sm) && (int) $sm[2] === (int) $sm[1] + 1 ? $a['season'] : null;
        }
        $doc['article'] = $cur;
        return $doc;
    }

    private static function objet(array $doc, ?array $o): array
    {
        if ($o === null) {
            return $doc;
        }
        $cur = $doc['objet'];
        foreach (['collection' => 40, 'date_text' => 80, 'credit' => 200, 'origin' => 300] as $k => $max) {
            if (array_key_exists($k, $o)) {
                $cur[$k] = Html::line($o[$k], $max);
            }
        }
        if (array_key_exists('year', $o)) {
            $cur['year'] = self::int($o['year']);
        }
        if (array_key_exists('linked', $o)) {
            $cur['linked'] = array_values(array_unique(array_filter(array_map(fn ($x) => self::int(is_array($x) ? ($x['id'] ?? null) : $x), (array) $o['linked']))));
        }
        $doc['objet'] = $cur;
        return $doc;
    }

    private static function moment(array $doc, ?array $o): array
    {
        if ($o === null) {
            return $doc;
        }
        $cur = $doc['moment'];
        if (array_key_exists('number', $o)) {
            $n = self::int($o['number']);
            $cur['number'] = $n !== null && $n >= 1 && $n <= 100 ? $n : null;
        }
        if (array_key_exists('year', $o)) {
            $cur['year'] = self::int($o['year']);
        }
        if (array_key_exists('linked', $o)) {
            $cur['linked'] = array_values(array_unique(array_filter(array_map(fn ($x) => self::int(is_array($x) ? ($x['id'] ?? null) : $x), (array) $o['linked']))));
        }
        $doc['moment'] = $cur;
        return $doc;
    }

    /** Version anglaise corrigée à la main : marquée « manuelle » (jamais écrasée par Gemini). */
    private static function english(array $doc, array $en): array
    {
        $cur = $doc['i18n']['en'] ?? [];
        $before = json_encode(array_diff_key($cur, array_flip(['_src', '_at', '_by', '_model', '_manual'])));
        if (isset($en['title'])) {
            $cur['title'] = Html::line($en['title'], 250);
        }
        if (isset($en['intro'])) {
            $cur['intro'] = Html::clean((string) $en['intro']);
        }
        if (isset($en['sections'])) {
            $secs = [];
            foreach ((array) $en['sections'] as $i => $s) {
                $secs[$i] = ['title' => Html::line($s['title'] ?? '', 200), 'html' => Html::clean((string) ($s['html'] ?? ''))];
            }
            $cur['sections'] = $secs;
        }
        if (isset($en['key_figure_text'])) {
            $t = Html::line($en['key_figure_text'], 400);
            $cur['key_figure'] = $t !== '' ? ['number' => $doc['key_figure']['number'] ?? '', 'text' => $t] : null;
        }
        if (isset($en['seo'])) {
            $cur['seo'] = ['title' => Html::line($en['seo']['title'] ?? '', 160), 'description' => Html::line($en['seo']['description'] ?? '', 320)];
        }
        if (isset($en['subtitle']) && $doc['type'] === 'personne') {
            $cur['personne']['subtitle'] = Html::line($en['subtitle'], 300);
        }
        $cur = array_filter($cur, fn ($v) => $v !== null && $v !== '' && $v !== []);
        if (json_encode(array_diff_key($cur, array_flip(['_src', '_at', '_by', '_model', '_manual']))) !== $before) {
            $cur['_manual'] = true;
            $cur['_by'] = \App\Core\Auth::actor()['name'] ?? 'Back-office';
            $cur['_at'] = date('c');
            $doc['i18n']['en'] = $cur;
            $cur['_src'] = \App\Services\Translator::hash($doc);
        }
        $doc['i18n']['en'] = $cur;
        if (empty($cur['title'])) {
            unset($doc['i18n']['en']);
        }
        return $doc;
    }

    // ------------------------------------------------------------------ outils

    public static function autoTitle(array $doc): string
    {
        if ($doc['type'] === 'match') {
            $m = $doc['match'];
            if (empty($m['home']['name']) || empty($m['away']['name'])) {
                return (string) ($m['event'] ?? '');
            }
            $s = $m['score'] ?? null;
            $comp = $m['competition'] === 'Championnat' ? ($m['competition_label'] ?: 'Championnat') : $m['competition'];
            return trim(($m['round'] ? $m['round'] . ' – ' : '') . $m['home']['name'] . ' / ' . $m['away']['name'] . ' – ' . $comp . ($m['date'] ? ' – ' . date('d/m/Y', strtotime($m['date'])) : '') . ($s ? ' – ' . $s['home'] . '-' . $s['away'] : ''));
        }
        if ($doc['type'] === 'personne') {
            return (string) ($doc['personne']['display_name'] ?? '');
        }
        return '';
    }

    public static function int(mixed $v): ?int
    {
        if ($v === null || $v === '' || is_array($v)) {
            return null;
        }
        $s = preg_replace('/[\s\x{a0}\x{202f}.]/u', '', (string) $v);
        return preg_match('/^-?\d+$/', $s) ? (int) $s : null;
    }

    public static function float(mixed $v): ?float
    {
        if ($v === null || $v === '' || is_array($v)) {
            return null;
        }
        $s = str_replace(',', '.', trim((string) $v));
        return is_numeric($s) ? round((float) $s, 6) : null;
    }

    public static function datetime(mixed $v): ?string
    {
        $s = trim((string) $v);
        if ($s === '') {
            return null;
        }
        $ts = strtotime($s);
        return $ts ? date('c', $ts) : null;
    }

    /** Image de la médiathèque (chemin relatif connu ou fichier présent). */
    public static function media(mixed $v): ?string
    {
        $rel = ltrim(str_replace(['..', "\0", '\\'], '', trim((string) $v)), '/');
        if ($rel === '') {
            return null;
        }
        return Media::get($rel) || Media::file($rel) ? $rel : null;
    }

    /**
     * Date partielle : « 8 décembre 1964 », « 08/12/1964 », « 12/1964 », « décembre 1964 », « 1964 ».
     * @return array{iso:string,precision:string,text:string}|null
     */
    public static function fuzzyDate(string $s): ?array
    {
        $s = trim($s);
        if ($s === '') {
            return null;
        }
        $months = ['janvier' => 1, 'fevrier' => 2, 'février' => 2, 'mars' => 3, 'avril' => 4, 'mai' => 5, 'juin' => 6, 'juillet' => 7, 'aout' => 8, 'août' => 8, 'septembre' => 9, 'octobre' => 10, 'novembre' => 11, 'decembre' => 12, 'décembre' => 12];
        $names = array_flip(array_unique(array_filter(array_flip($months), fn ($k) => !in_array($k, ['fevrier', 'aout', 'decembre'], true))));
        $mname = fn (int $m) => ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'][$m - 1];
        $l = mb_strtolower($s);
        $mk = fn (int $y, int $m = 0, int $d = 0) => $d ? (checkdate($m, $d, $y) ? ['iso' => sprintf('%04d-%02d-%02d', $y, $m, $d), 'precision' => 'day', 'text' => ($d === 1 ? '1er' : $d) . ' ' . $mname($m) . " $y"] : null)
            : ($m ? ['iso' => sprintf('%04d-%02d', $y, $m), 'precision' => 'month', 'text' => $mname($m) . " $y"] : ['iso' => sprintf('%04d', $y), 'precision' => 'year', 'text' => (string) $y]);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $l, $m)) {
            return $mk((int) $m[1], (int) $m[2], (int) $m[3]);
        }
        if (preg_match('/^(\d{4})-(\d{2})$/', $l, $m)) {
            return $mk((int) $m[1], (int) $m[2]);
        }
        if (preg_match('#^(\d{1,2})[/.](\d{1,2})[/.](\d{4})$#', $l, $m)) {
            return $mk((int) $m[3], (int) $m[2], (int) $m[1]);
        }
        if (preg_match('#^(\d{1,2})[/.](\d{4})$#', $l, $m) && (int) $m[1] >= 1 && (int) $m[1] <= 12) {
            return $mk((int) $m[2], (int) $m[1]);
        }
        if (preg_match('/^(?:(\d{1,2})(?:er)?\s+)?(' . implode('|', array_keys($months)) . ')\s+(\d{4})$/u', $l, $m)) {
            return $mk((int) $m[3], $months[$m[2]], (int) ($m[1] ?? 0));
        }
        if (preg_match('/^(1[89]\d{2}|20\d{2})$/', $l, $m)) {
            return $mk((int) $m[1]);
        }
        unset($names);
        return null;
    }

    /** Lien vidéo → fournisseur + identifiant. */
    public static function video(string $url, string $provider = '', string $id = ''): ?array
    {
        $url = trim($url);
        if ($url === '' && $provider !== '' && $id !== '') {
            return ['provider' => $provider, 'id' => $id];
        }
        if (preg_match('#(?:youtube\.com/(?:watch\?v=|embed/|shorts/|live/)|youtu\.be/)([\w-]{11})#', $url, $m)) {
            return ['provider' => 'youtube', 'id' => $m[1]];
        }
        if (preg_match('#(?:dailymotion\.com/(?:video|embed/video)/|dai\.ly/)([a-z0-9]+)#i', $url, $m)) {
            return ['provider' => 'dailymotion', 'id' => $m[1]];
        }
        if (preg_match('#vimeo\.com/(?:video/)?(\d+)#', $url, $m)) {
            return ['provider' => 'vimeo', 'id' => $m[1]];
        }
        if (preg_match('#^/media/|^https?://.+\.(mp4|webm)$#i', $url)) {
            return ['provider' => 'file', 'id' => '', 'url' => $url];
        }
        return null;
    }

    /** Tableaux saisis dans la grille : titre, en-têtes, lignes (texte simple). */
    public static function tables(array $tables): array
    {
        $out = [];
        foreach ($tables as $t) {
            if (!is_array($t)) {
                continue;
            }
            $headers = array_map(fn ($h) => Html::line($h, 120), array_values((array) ($t['headers'] ?? [])));
            $rows = [];
            foreach ((array) ($t['rows'] ?? []) as $r) {
                $cells = array_map(fn ($c) => Html::line(is_array($c) ? ($c['text'] ?? '') : $c, 300), array_values((array) $r));
                if (array_filter($cells, fn ($c) => $c !== '')) {
                    $rows[] = $cells;
                }
            }
            if ($headers || $rows) {
                $out[] = ['title' => Html::line($t['title'] ?? '', 200), 'headers' => $headers, 'rows' => $rows, 'source_table' => $t['source_table'] ?? null];
            }
        }
        return $out;
    }
}
