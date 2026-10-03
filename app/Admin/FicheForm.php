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
        $orig = $doc;
        $has = fn (string $k) => array_key_exists($k, $in);
        $origCats = array_values(array_map('strval', $doc['categories'] ?? []));
        if ($has('title')) {
            $doc['title'] = Html::line($in['title'], 250);
        }
        if ($has('status') && isset(\App\Data\Fiches::STATUSES[$in['status']]) && $in['status'] !== 'corbeille') {
            $doc['status'] = $in['status'];
        }
        if ($has('publish_at')) {
            $doc['publish_at'] = self::sameMinute($orig['publish_at'] ?? null, self::datetime($in['publish_at']));
            if ($doc['status'] === 'planifie' && !$doc['publish_at']) {
                $errors['publish_at'] = 'Indiquez la date et l’heure de publication.';
            }
        }
        if ($has('a_la_une')) {
            $doc['a_la_une'] = (bool) $in['a_la_une'];
        }
        if ($has('categories') || $has('categories__present')) {
            $known = Categories::all();
            $offered = Fiches::catOptions();
            $checked = array_filter(array_map('strval', (array) ($in['categories'] ?? [])), fn ($c) => isset($known[$c], $offered[$c]));
            // Rubriques que le formulaire ne propose pas (saisons, « À la une », rubriques techniques) : conservées.
            $kept = array_filter(array_map('strval', $doc['categories'] ?? []), fn ($c) => !isset($offered[$c]));
            $doc['categories'] = array_values(array_unique(array_merge($checked, $kept)));
        }
        if ($has('featured_image')) {
            $doc['featured_image'] = self::media($in['featured_image'], [$orig['featured_image'] ?? null]);
        }
        if ($has('date')) {
            $doc['date'] = self::sameMinute($orig['date'] ?? null, self::datetime($in['date'])) ?? $doc['date'];
        }
        $oldHtml = self::cleaned(array_merge([$orig['intro'] ?? ''], array_column($orig['sections'] ?? [], 'html')));
        if ($has('intro')) {
            $doc['intro'] = self::html($in['intro'], $oldHtml);
        }
        if ($has('sections')) {
            $old = array_values($orig['sections'] ?? []);
            $doc['sections'] = [];
            foreach (array_values((array) $in['sections']) as $i => $s) {
                $t = Html::line($s['title'] ?? '', 200);
                $h = self::html($s['html'] ?? '', $oldHtml);
                $prev = $old[$i] ?? null;
                if ($t !== '' || $h !== '') {
                    $doc['sections'][] = ['title' => $t, 'html' => $h] + array_intersect_key((array) $s, ['kind' => 1]);
                } elseif ($prev !== null && Html::line($prev['title'] ?? '') === '' && Html::clean((string) ($prev['html'] ?? '')) === '') {
                    $doc['sections'][] = $prev; // section vide laissée telle quelle (elle reste modifiable)
                }
            }
        }
        if ($has('key_figure')) {
            $n = Html::line($in['key_figure']['number'] ?? '', 30);
            $t = Html::line($in['key_figure']['text'] ?? '', 400);
            $doc['key_figure'] = $n === '' && $t === '' ? null : ['number' => $n, 'text' => $t];
        }
        if ($has('gallery')) {
            $old = $orig['gallery'] ?? [];
            $doc['gallery'] = [];
            foreach ((array) $in['gallery'] as $g) {
                $img = self::media($g['image'] ?? null, array_column($old, 'image'));
                if (!$img) {
                    continue;
                }
                $cap = Html::line($g['caption'] ?? '', 300);
                $cred = Html::line($g['credit'] ?? '', 200);
                $item = ['image' => $img, 'caption' => $cap, 'credit' => $cred, 'caption_raw' => trim($cap . ($cred !== '' ? ' – ' . $cred : ''))];
                $doc['gallery'][] = self::unchanged($old, $item, ['image' => 0, 'caption' => 300, 'credit' => 200]) ?? $item;
            }
        }
        if ($has('images')) {
            $old = $orig['images'] ?? [];
            $doc['images'] = [];
            foreach ((array) $in['images'] as $g) {
                if ($img = self::media($g['image'] ?? null, array_column($old, 'image'))) {
                    $item = ['image' => $img, 'caption' => Html::line($g['caption'] ?? '', 300), 'in_text' => (bool) ($g['in_text'] ?? false)];
                    $doc['images'][] = self::unchanged($old, $item, ['image' => 0, 'caption' => 300, 'in_text' => 0]) ?? $item;
                }
            }
        }
        if ($has('videos')) {
            // Vidéo déjà enregistrée, lien inchangé : gardée telle quelle (anciens liens « iframe » compris).
            $old = [];
            foreach ($orig['videos'] ?? [] as $ov) {
                if (is_array($ov)) {
                    $old[(string) (video_embed($ov)['link'] ?? ($ov['url'] ?? ''))] = $ov;
                }
            }
            $doc['videos'] = [];
            foreach ((array) $in['videos'] as $v) {
                $url = trim((string) ($v['url'] ?? ''));
                if ($url !== '' && isset($old[$url])) {
                    $ov = $old[$url];
                    $ov['title'] = Html::line($v['title'] ?? '', 200);
                    $doc['videos'][] = $ov;
                    continue;
                }
                $parsed = self::video($url, (string) ($v['provider'] ?? ''), (string) ($v['id'] ?? ''));
                if ($parsed) {
                    $doc['videos'][] = $parsed + ['title' => Html::line($v['title'] ?? '', 200)];
                } elseif ($url !== '') {
                    $errors['videos'] = 'Lien de vidéo non reconnu : ' . mb_substr($url, 0, 80) . ' (YouTube, Dailymotion, Vimeo, Rutube ou fichier vidéo).';
                }
            }
        }
        if ($has('embeds')) {
            $oldTexts = array_column($doc['embeds'] ?? [], 'text');
            $doc['embeds'] = [];
            foreach ((array) $in['embeds'] as $em) {
                $url = trim((string) ($em['url'] ?? ''));
                if ($url !== '' && preg_match('#^https://#', $url)) {
                    $prov = preg_match('#(twitter\.com|x\.com)#', $url) ? 'x' : (str_contains($url, 'instagram.com') ? 'instagram' : (str_contains($url, 'facebook.com') ? 'facebook' : 'web'));
                    $doc['embeds'][] = ['provider' => $prov, 'url' => $url, 'text' => self::rich($em['text'] ?? '', $oldTexts, 8000)];
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
        $doc = self::autoCategories($doc, $origCats);

        // Titre automatique pour les matchs et personnes sans titre saisi.
        if (trim((string) $doc['title']) === '') {
            $doc['title'] = self::autoTitle($doc);
        }
        if ($doc['title'] === '') {
            $errors['title'] = 'Le titre est obligatoire.';
        }
        return self::settle($orig, $doc);
    }

    /**
     * Enregistrer sans rien changer ne modifie pas la fiche : un champ resté vide garde sa
     * forme d'origine (null, "" ou liste vide) et un champ vide absent de la fiche n'est pas ajouté.
     */
    private static function settle(mixed $old, mixed $new): mixed
    {
        $blank = fn ($v) => $v === null || $v === '' || $v === [];
        if (!is_array($old) || !is_array($new)) {
            return $blank($old) && $blank($new) ? $old : $new;
        }
        $list = array_is_list($new);
        if ($list && (!array_is_list($old) || count($old) !== count($new))) {
            return $new;
        }
        foreach ($new as $k => $v) {
            if (array_key_exists($k, $old)) {
                $new[$k] = self::settle($old[$k], $v);
            } elseif (!$list && $blank($v)) {
                unset($new[$k]);
            }
        }
        return $new;
    }

    // ------------------------------------------------------------------ types

    private static function match(array $doc, ?array $m, array &$errors): array
    {
        if ($m === null) {
            return $doc;
        }
        $cur = $doc['match'];
        $oldDate = $cur['date'] ?? null;
        if (array_key_exists('date', $m)) {
            $d = trim((string) $m['date']);
            if ($d !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                $errors['match.date'] = 'Date invalide (jj/mm/aaaa).';
            } else {
                $cur['date'] = $d ?: null;
            }
        }
        // Date inchangée : date en toutes lettres et saison gardées (les amicaux de fin juin
        // sont rangés par les historiens dans la saison qui commence).
        if ($cur['date'] !== $oldDate || array_key_exists('date_text', $m) || array_key_exists('season', $m)) {
            $cur['date_text'] = $cur['date'] ? date_fr($cur['date'], true) : Html::line($m['date_text'] ?? ($cur['date_text'] ?? ''), 80);
            $sameMonth = $cur['date'] && $oldDate && substr($cur['date'], 0, 7) === substr((string) $oldDate, 0, 7) && !empty($cur['season']);
            $cur['season'] = $sameMonth ? $cur['season'] : ($cur['date'] ? Paths::seasonOf($cur['date']) : (preg_match('/^\d{4}-\d{4}$/', (string) ($m['season'] ?? '')) ? $m['season'] : $cur['season']));
        }
        foreach (['competition_label' => 80, 'competition_code' => 20, 'round' => 40, 'round_text' => 120, 'stadium' => 160, 'referee' => 120, 'goals_text' => 400, 'event' => 160, 'formation' => 20, 'spectators_text' => 80] as $k => $max) {
            if (array_key_exists($k, $m)) {
                $cur[$k] = Html::line($m[$k], $max);
                if ($cur[$k] === '' && ($doc['match'][$k] ?? null) === null) {
                    $cur[$k] = null; // champ vide resté vide
                }
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
            // Lien explicite vers un adversaire conservé tant que le nom ne change pas ; sinon
            // l'adversaire est retrouvé par son nom et ses variantes (référentiels).
            $prevOpp = (bool) ($doc['match']['sochaux_home'] ?? true) ? ($doc['match']['away']['name'] ?? '') : ($doc['match']['home']['name'] ?? '');
            $cur['opponent_club'] = $opp !== '' && Names::clubKey($opp) === Names::clubKey((string) $prevOpp) ? ($cur['opponent_club'] ?? null) : null;
        }
        if (array_key_exists('spectators', $m)) {
            $cur['spectators'] = self::int($m['spectators']);
        }
        if (array_key_exists('score_home', $m)) {
            $h = self::int($m['score_home']);
            $a = self::int($m['score_away'] ?? null);
            $extra = in_array($m['extra'] ?? '', ['ap', 'tab'], true) ? $m['extra'] : null;
            $ph = self::int($m['pens_home'] ?? null);
            $pa = self::int($m['pens_away'] ?? null);
            // Score inchangé : il est gardé tel quel (mentions d'origine « a.p », « (4-5 tab) »…).
            $old = $doc['match']['score'] ?? null;
            $same = $old ? $h === ($old['home'] ?? null) && $a === ($old['away'] ?? null) && (string) $extra === self::extraKind($old)
                    && ($extra !== 'tab' || ($ph === ($old['pens']['home'] ?? null) && $pa === ($old['pens']['away'] ?? null)))
                : $h === null || $a === null;
            if (!$same) {
                $pens = $extra === 'tab' && $ph !== null ? ['home' => $ph, 'away' => $pa] : null;
                $cur['score'] = $h === null || $a === null ? null : ['home' => $h, 'away' => $a, 'extra' => $extra, 'aet' => $extra !== null, 'pens' => $pens];
                $cur['score_raw'] = $cur['score'] ? $h . '-' . $a . ($extra === 'ap' ? ' a.p.' : '') . ($pens ? ' (' . $pens['home'] . '-' . $pens['away'] . ' tab)' : '') : '';
            }
            if (!$same || $cur['sochaux_home'] !== (bool) ($doc['match']['sochaux_home'] ?? true)) {
                $cur['result'] = self::result($cur);
            }
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
                $parsed = \App\Data\Lineup::parse($goalsText, $subText, $cardsText);
                $pos = (string) ($r['position'] ?? '');
                $extra = [];
                foreach ((array) ($r['extra'] ?? []) as $ek => $ev) {
                    if (is_scalar($ev) && trim((string) $ev) !== '') {
                        $extra[Html::line($ek, 60)] = Html::line($ev, 120);
                    }
                }
                $rows[] = [
                    'position' => array_key_exists($pos, self::POSITIONS) ? $pos : Html::line($pos, 10),
                    'name' => $name,
                    'number' => Html::line($r['number'] ?? '', 6) ?: null,
                    'extra' => $extra ?: null,
                    'captain' => (bool) ($r['captain'] ?? false),
                    'goals' => $parsed['goals'],
                    'own_goals' => $parsed['own_goals'],
                    'goals_text' => $goalsText,
                    'sub_in' => $parsed['sub_in'],
                    'sub_out' => $parsed['sub_out'],
                    'sub_text' => $subText,
                    'yellow' => $parsed['yellow'],
                    'red' => $parsed['red'],
                    'cards_text' => $cardsText,
                    'person_id' => self::int($r['person_id'] ?? null),
                ];
            }
            if ($rows || !empty($cur['lineup']['rows'])) {
                $cur['lineup']['rows'] = $rows;
                $cur['lineup']['title'] = $cur['lineup']['title'] ?? 'Composition Sochaux';
                $cur['lineup']['headers'] = $cur['lineup']['headers'] ?? ['Postes', 'Nom et prénom', 'Buts', 'Remp.', 'Cartons'];
            }
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
            $oldTexts = array_column($cur['reactions'] ?? [], 'text');
            $cur['reactions'] = [];
            foreach ((array) $m['reactions'] as $r) {
                $t = self::rich($r['text'] ?? '', $oldTexts, 6000);
                if ($t !== '') {
                    $cur['reactions'][] = ['who' => Html::line($r['who'] ?? '', 120), 'text' => $t];
                }
            }
        }
        if (array_key_exists('breves', $m)) {
            $oldTexts = array_map(fn ($b) => is_array($b) ? (string) ($b['text'] ?? '') : (string) $b, $cur['breves'] ?? []);
            $cur['breves'] = array_values(array_filter(array_map(fn ($x) => self::rich(is_array($x) ? ($x['_'] ?? '') : $x, $oldTexts, 4000), (array) $m['breves'])));
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
        if (array_key_exists('aliases', $p)) {
            $cur['aliases'] = array_values(array_unique(array_filter(array_map(fn ($a) => Html::line($a, 120), (array) $p['aliases']), fn ($a) => $a !== '')));
        }
        if (array_key_exists('line', $p)) {
            // Valeur reprise de l'ancien site hors liste (ex. « E ») : gardée tant qu'on n'y touche pas.
            $cur['line'] = (string) $p['line'] !== '' && (string) $p['line'] === (string) ($cur['line'] ?? '') ? $cur['line']
                : (isset(self::LINES[$p['line']]) && $p['line'] !== '' ? $p['line'] : null);
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
                $cur[$k] = self::keepDate($cur[$k] ?? null, $p[$k], $bad);
                if ($bad) {
                    $errors["personne.$k"] = 'Date non reconnue : écrivez par ex. « 12/1964 », « juillet 1980 » ou « 1980 ».';
                }
            }
        }
        if (array_key_exists('birth_date', $p) || array_key_exists('birth_city', $p)) {
            $ob = $cur['birth'] ?? null;
            $old = $ob['place'] ?? [];
            $date = self::keepDate($ob['date'] ?? null, $p['birth_date'] ?? '', $bad);
            $city = Html::line($p['birth_city'] ?? '', 120);
            $country = Html::line($p['birth_country'] ?? '', 80);
            $dept = Html::line($p['birth_department'] ?? '', 10);
            $lat = self::float($p['birth_lat'] ?? null);
            $lng = self::float($p['birth_lng'] ?? null);
            $samePlace = $city === Html::line($old['city'] ?? '', 120) && $dept === Html::line($old['department'] ?? '', 10) && $country === Html::line($old['country'] ?? '', 80)
                && $lat === self::float($old['lat'] ?? null) && $lng === self::float($old['lng'] ?? null);
            if ($samePlace && $date === ($ob['date'] ?? null)) {
                // Rien n'a changé : naissance gardée telle quelle (texte d'origine compris).
            } else {
                if ($city !== ($old['city'] ?? '') && $lat === ($old['lat'] ?? null)) {
                    $lat = $lng = null; // nouvelle ville : géolocalisation à refaire automatiquement
                }
                $placeText = $city !== '' ? $city . ($dept !== '' ? " ($dept)" : '') . ($country !== '' && $country !== 'France' ? ", $country" : '') : '';
                $place = $samePlace && $city !== '' ? $old : ($city !== '' ? ['text' => $placeText, 'city' => $city, 'department' => $dept ?: null, 'country' => $country ?: null, 'lat' => $lat, 'lng' => $lng] : null);
                $cur['birth'] = $date || $place ? [
                    'date' => $date,
                    'place' => $place,
                    'text' => trim(($date ? 'né le ' . ($date['text'] ?? '') : '') . ($place ? ' à ' . ($place['text'] ?? $placeText) : '')),
                ] : null;
            }
            if ($bad) {
                $errors['personne.birth_date'] = 'Date de naissance non reconnue.';
            }
        }
        if (array_key_exists('death_date', $p) || array_key_exists('death_place', $p)) {
            $od = $cur['death'] ?? null;
            $date = self::keepDate($od['date'] ?? null, $p['death_date'] ?? '', $bad);
            $place = Html::line($p['death_place'] ?? '', 160);
            $samePlace = $place === Html::line($od['place']['text'] ?? '', 160);
            if (!$samePlace || $date !== ($od['date'] ?? null)) {
                // Lieu inchangé : ville, département, pays et coordonnées d'origine conservés.
                $placeObj = $place === '' ? null : ($samePlace ? $od['place'] : ['text' => $place]);
                $cur['death'] = $date || $placeObj ? ['date' => $date, 'place' => $placeObj, 'text' => trim(($date ? 'décédé le ' . ($date['text'] ?? '') : '') . ($place !== '' ? ' à ' . $place : ''))] : null;
            }
            if ($bad) {
                $errors['personne.death_date'] = 'Date de décès non reconnue.';
            }
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
                $l = Html::line($r['label'] ?? '', 120) ?: null;
                // Une ligne avec seulement un libellé (intertitre « Passage comme joueur »…) est gardée.
                if ($v !== '' || $l !== null) {
                    $cur['fiche'][] = ['label' => $l, 'value' => $v];
                }
            }
        }
        if (array_key_exists('stats', $p)) {
            $t = self::tables([$p['stats']]);
            $cur['stats'] = $t[0] ?? null;
        }
        if (array_key_exists('album', $p)) {
            $a = (array) $p['album'];
            // Rareté vide = automatique (légende, actuel ou classique selon la carrière).
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
        if (array_key_exists('heading', $a)) {
            $cur['heading'] = Html::line($a['heading'], 300);
        }
        if (array_key_exists('subtitle', $a)) {
            // Plusieurs lignes possibles (résultats d'une saison, une compétition par ligne).
            $cur['subtitle'] = Html::text($a['subtitle'], 2000);
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
        $oldHtml = self::cleaned(array_merge([$cur['intro'] ?? ''], array_column($cur['sections'] ?? [], 'html')));
        if (isset($en['intro'])) {
            $cur['intro'] = self::html($en['intro'], $oldHtml);
        }
        if (isset($en['sections'])) {
            $secs = [];
            foreach ((array) $en['sections'] as $i => $s) {
                $secs[$i] = ['title' => Html::line($s['title'] ?? '', 200), 'html' => self::html($s['html'] ?? '', $oldHtml)];
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

    /** Date et heure saisies à la minute près : si elles n'ont pas bougé, la valeur d'origine (secondes comprises) est gardée. */
    private static function sameMinute(?string $old, ?string $new): ?string
    {
        if ($old !== null && $new !== null && ($a = strtotime($old)) && ($b = strtotime($new)) && intdiv($a, 60) === intdiv($b, 60)) {
            return $old;
        }
        return $new;
    }

    /** @return array<string,array{0:string,1:string}> HTML d'origine => [version brute, version nettoyée] aux fins de ligne unifiées */
    private static function cleaned(array $htmls): array
    {
        $nl = fn (string $h) => str_replace(["\r\n", "\r"], "\n", $h);
        $out = [];
        foreach ($htmls as $h) {
            if (is_string($h) && trim($h) !== '' && !isset($out[$h])) {
                $out[$h] = [$nl($h), $nl(Html::clean($h))];
            }
        }
        return $out;
    }

    /**
     * Texte riche saisi : s'il ne diffère d'un texte déjà enregistré que par le nettoyage
     * (balises h5 de l'ancien site, blocs vides, fins de ligne…), ce texte est gardé tel quel.
     * @param array<string,array{0:string,1:string}> $old
     */
    private static function html(mixed $v, array $old): string
    {
        $raw = is_scalar($v) ? (string) $v : '';
        $new = Html::clean($raw);
        $rawN = str_replace(["\r\n", "\r"], "\n", $raw);
        $newN = str_replace(["\r\n", "\r"], "\n", $new);
        foreach ($old as $orig => [$o, $c]) {
            if ($rawN === $o || ($newN !== '' && $newN === $c)) {
                return (string) $orig;
            }
        }
        return $new;
    }

    /**
     * Élément de liste (image de galerie…) identique à un élément enregistré : l'ancien est gardé
     * avec ses champs d'origine. $keys : champ => longueur maximale des textes (0 = comparaison directe).
     */
    private static function unchanged(array $olds, array $item, array $keys): ?array
    {
        foreach ($olds as $o) {
            if (!is_array($o)) {
                continue;
            }
            foreach ($keys as $k => $max) {
                $a = $max ? Html::line($o[$k] ?? '', $max) : ($o[$k] ?? null);
                if ($a !== $item[$k] && !(is_bool($item[$k]) && (bool) $a === $item[$k])) {
                    continue 2;
                }
            }
            return $o;
        }
        return null;
    }

    /** Date partielle telle que le masque l'affiche : texte d'origine, sinon date ISO. */
    public static function dateText(mixed $d): string
    {
        return is_array($d) ? (string) ($d['text'] ?? $d['iso'] ?? '') : (string) ($d ?? '');
    }

    /**
     * Date partielle saisie : inchangée, l'ancienne valeur est gardée telle quelle, même si elle
     * n'est pas reconnue (« juin 1978 ? », « xx ») ; $bad signale une nouvelle saisie illisible.
     */
    private static function keepDate(mixed $old, mixed $in, ?bool &$bad = null): mixed
    {
        $s = trim(is_scalar($in) ? (string) $in : '');
        if ($old !== null && $s === trim(self::dateText($old))) {
            $bad = false;
            return $old;
        }
        $d = self::fuzzyDate($s);
        $bad = $s !== '' && !$d;
        return $d;
    }

    /** Prolongation telle que le masque la propose ('' non, 'ap', 'tab'), y compris pour les mentions reprises (« a.p », « (4-5 tab) »). */
    public static function extraKind(?array $score): string
    {
        if (!$score) {
            return '';
        }
        $x = (string) ($score['extra'] ?? '');
        if ($x === 'tab' || !empty($score['pens']) || stripos($x, 'tab') !== false) {
            return 'tab';
        }
        return $x === 'ap' || !empty($score['aet']) || preg_match('/a\.?\s*p\b/i', $x) ? 'ap' : '';
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

    /**
     * Image de la médiathèque (chemin relatif connu ou fichier présent). Une image déjà
     * enregistrée sur la fiche ($keep) reste acceptée même si son fichier manque.
     */
    public static function media(mixed $v, array $keep = []): ?string
    {
        $rel = ltrim(str_replace(["\0", '\\'], '', trim(is_scalar($v) ? (string) $v : '')), '/');
        if ($rel === '' || preg_match('#(^|/)\.\.?(/|$)#', $rel)) {
            return null;
        }
        return Media::get($rel) || Media::file($rel) || in_array($rel, $keep, true) ? $rel : null;
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
        // Date incertaine des historiens : « juillet 1970 ? » (le point d'interrogation est gardé).
        if (preg_match('/^(.+?)\s*\?$/u', $s, $q) && ($d = self::fuzzyDate($q[1]))) {
            $d['text'] .= ' ?';
            return $d;
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
            // Identifiant fourni directement : contrôlé comme ceux lus dans un lien.
            $ok = match ($provider) {
                'youtube' => preg_match('/^[\w-]{11}$/', $id),
                'dailymotion' => preg_match('/^[a-z0-9]+$/i', $id),
                'vimeo' => preg_match('/^\d+$/', $id),
                'rutube' => preg_match('/^[a-f0-9]{20,}$/i', $id),
                default => 0,
            };
            return $ok ? ['provider' => $provider, 'id' => $id] : null;
        }
        if (preg_match('#(?:youtube\.com/(?:watch\?v=|embed/|shorts/|live/)|youtu\.be/)([\w-]{11})#', $url, $m)) {
            return ['provider' => 'youtube', 'id' => $m[1]];
        }
        if (preg_match('#(?:dailymotion\.com/(?:video|embed/video)/|geo\.dailymotion\.com/player(?:/[\w-]+)?\.html\?(?:.*&)?video=|dai\.ly/)([a-z0-9]+)#i', $url, $m)) {
            return ['provider' => 'dailymotion', 'id' => $m[1]];
        }
        if (preg_match('#vimeo\.com/(?:video/)?(\d+)#', $url, $m)) {
            return ['provider' => 'vimeo', 'id' => $m[1]];
        }
        if (preg_match('#rutube\.ru/(?:play/embed|video)/([a-f0-9]{20,})#i', $url, $m)) {
            return ['provider' => 'rutube', 'id' => strtolower($m[1])];
        }
        if (preg_match('#^/media/|^https?://.+\.(mp4|webm)$#i', $url)) {
            return ['provider' => 'file', 'id' => '', 'url' => $url];
        }
        return null;
    }

    /** Tableaux saisis dans la grille : titre, en-têtes, lignes (texte simple). */
    /**
     * Classement automatique : la case « À la une » et la rubrique du même nom vont
     * de pair ; un match est rangé dans la rubrique de sa saison (et ses parentes).
     */
    private static function autoCategories(array $doc, array $orig = []): array
    {
        $all = Categories::all();
        $cats = array_values(array_map('strval', $doc['categories'] ?? []));
        if (isset($all['a-la-une'])) {
            $cats = !empty($doc['a_la_une']) ? array_merge($cats, ['a-la-une']) : array_diff($cats, ['a-la-une']);
        }
        if ($doc['type'] === 'match' && !empty($doc['match']['season'])) {
            $seasonCat = null;
            foreach ($all as $slug => $c) {
                if (($c['season'] ?? null) === $doc['match']['season']) {
                    $seasonCat = (string) $slug;
                    break;
                }
            }
            if ($seasonCat) {
                // Une seule saison par match : celle de sa date.
                $cats = array_filter($cats, fn ($c) => empty($all[$c]['season']) || $c === $seasonCat);
                foreach (Categories::trail($seasonCat) as $t) {
                    $cats[] = (string) ($t['slug'] ?? '');
                }
            }
        }
        $cats = array_values(array_unique(array_filter($cats, fn ($c) => $c !== '' && isset($all[$c]))));
        // Ordre d'origine conservé (les nouvelles rubriques viennent à la suite).
        $pos = array_flip($orig);
        $rank = array_flip($cats);
        usort($cats, fn ($a, $b) => [$pos[$a] ?? PHP_INT_MAX, $rank[$a]] <=> [$pos[$b] ?? PHP_INT_MAX, $rank[$b]]);
        $doc['categories'] = $cats;
        return $doc;
    }

    /**
     * Texte riche court (réactions, brèves, publications) : un texte ancien renvoyé tel
     * quel par l'éditeur est conservé sans conversion ; sinon il est nettoyé.
     */
    private static function rich(mixed $v, array $old, int $max): string
    {
        $v = (string) $v;
        foreach ($old as $o) {
            if (trim((string) $o) === trim($v)) {
                return (string) $o;
            }
        }
        return Html::clean(mb_substr($v, 0, $max));
    }

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
