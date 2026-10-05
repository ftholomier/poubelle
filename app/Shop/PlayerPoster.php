<?php
declare(strict_types=1);

namespace App\Shop;

use App\Data\Derived;
use App\Data\Fiches;
use App\Services\Chiffres;
use App\Services\FicheAudio;
use App\Services\Gemini;

/**
 * Poster souvenir d'un joueur : le pendant du poster d'un match (voir Poster). Le client choisit un
 * Lionceau ; le musée compose l'affiche d'après sa fiche (nom en grand, grands chiffres, carrière
 * saison par saison, fiche d'identité, palmarès, grands matchs, records, coéquipiers, récit) et la
 * dédicace (« pour Prénom Nom »). C'est un calque « poster » de genre « joueur ».
 *
 * Champs du client : poster_joueur (identifiant de la fiche du joueur), poster_prenom, poster_nom.
 * L'IA, si elle est disponible, ajoute une anecdote, une citation (mot pour mot), un portrait en une
 * phrase et condense le récit ; tout est vérifié contre la fiche et mis en cache
 * (storage/shop/posters/j{id}.json).
 */
final class PlayerPoster
{
    public const FIELD = 'poster_joueur';
    public const FIELDS = ['poster_joueur' => 'Votre joueur', 'poster_prenom' => 'Prénom', 'poster_nom' => 'Nom'];
    /** Exemple de l'éditeur et de la vitrine : Mecha Bazdarevic. */
    public const SAMPLE = '4901';
    private const VERSION = 1;
    /** Matchs joués au minimum pour avoir son poster. */
    private const MIN_MATCHES = 30;

    /** @var array<string,?array> */
    private static array $cache = [];

    // ------------------------------------------------------------------ choix du joueur

    public static function eligible(string $id): bool
    {
        return self::data($id) !== null;
    }

    /** Libellé d'un joueur pour la liste de propositions : « Mecha Bazdarevic · milieu offensif · 1987-1996 ». */
    public static function label(array $d): string
    {
        return implode(' · ', array_filter([$d['name'], $d['position'], $d['years']]));
    }

    /** Propositions pour la saisie « Votre joueur », les plus capés d'abord. @return list<array{id:string,label:string}> */
    public static function search(string $q, int $limit = 12): array
    {
        $words = array_values(array_filter(preg_split('/[\s,\/·-]+/u', mb_strtolower(\App\Data\Names::ascii(trim($q)))) ?: [], fn ($w) => $w !== ''));
        if (!$words) {
            return [];
        }
        $tot = Derived::part('person_totals');
        $hits = [];
        foreach (\App\Data\Index::all() as $s) {
            if (($s['type'] ?? '') !== 'personne' || !\App\Data\Index::visible($s) || (int) ($tot[$s['id']]['matches'] ?? 0) < self::MIN_MATCHES) {
                continue;
            }
            $p = (array) ($s['p'] ?? []);
            $hay = ' ' . mb_strtolower(\App\Data\Names::ascii(($p['name'] ?? $s['title']) . ' ' . ($p['nickname'] ?? ''))) . ' ';
            foreach ($words as $w) {
                if (!str_contains($hay, $w)) {
                    continue 2;
                }
            }
            $hits[] = [(string) $s['id'], (int) $tot[$s['id']]['matches']];
        }
        usort($hits, fn ($a, $b) => $b[1] <=> $a[1]);
        $out = [];
        foreach ($hits as [$id]) {
            if ($d = self::data($id)) {
                $out[] = ['id' => $id, 'label' => self::label($d)];
                if (count($out) >= $limit) {
                    break;
                }
            }
        }
        return $out;
    }

    // ------------------------------------------------------------------ contenu

    /** Tout le contenu du poster d'un joueur (null : pas de poster possible). */
    public static function data(string $id): ?array
    {
        if (!ctype_digit($id)) {
            return null;
        }
        if (array_key_exists($id, self::$cache)) {
            return self::$cache[$id];
        }
        $doc = Fiches::get((int) $id);
        $p = (array) ($doc['personne'] ?? []);
        $tot = Derived::part('person_totals')[(int) $id] ?? null;
        if (!$doc || !Fiches::isVisible($doc) || !$p || !in_array('joueur', (array) ($p['roles'] ?? []), true) || (int) ($tot['matches'] ?? 0) < self::MIN_MATCHES) {
            return self::$cache[$id] = null;
        }
        $source = Poster::source($doc);
        if (mb_strlen($source) < 300) {
            return self::$cache[$id] = null;
        }
        $games = array_values(array_filter(Derived::personMatches((int) $id), fn ($x) => $x['role'] === 'player'));
        $official = array_values(array_filter($games, fn ($x) => ($x['comp'] ?? '') !== 'Amical'));
        $table = self::statsSeasons($p['stats'] ?? null);
        $st = $table ? ['matches' => array_sum(array_column($table, 'm')), 'goals' => array_sum(array_column($table, 'g')), 'seasons' => count($table)] : [];
        $name = trim((string) ($p['display_name'] ?: $doc['title']));
        $first = trim((string) ($p['first_name'] ?? ''));
        $last = trim((string) ($p['last_name'] ?? ''));
        if ($last === '' || !str_contains($name, $last)) {
            $parts = preg_split('/\s+/u', $name) ?: [$name];
            $last = (string) array_pop($parts);
            $first = implode(' ', $parts);
        }
        $d = [
            'id' => $id, 'name' => $name, 'first' => $first, 'last' => $last, 'nickname' => trim((string) ($p['nickname'] ?? '')),
            'position' => \App\Front\Fiche::roleLabel($p), 'years' => self::years($p),
            // Matchs officiels (sans les amicaux) : le tableau de statistiques de la fiche s'il existe, sinon les compositions.
            'matches' => (int) ($st['matches'] ?? 0) ?: count($official), 'goals' => (int) ($st['goals'] ?? 0) ?: (int) array_sum(array_column($official, 'goals')),
            'seasons_n' => (int) ($st['seasons'] ?? 0) ?: count(array_unique(array_column($official, 'season'))), 'minutes' => (int) array_sum(array_column($official, 'minutes')),
            'identity' => self::identity($p), 'honours' => self::honours($p), 'seasons' => $table ?: self::seasons($games),
            'big' => self::bigMatches($games), 'records' => self::records((int) $id, $name), 'mates' => self::mates((int) $id),
            'milestones' => self::milestones($p), 'figure' => self::figure($doc), 'legend' => !empty($p['legend']),
            'recit' => self::recitText($doc), 'anecdote' => null, 'quotes' => [], 'portrait' => '',
        ];
        if ($ai = self::enriched($id)) {
            $d['anecdote'] = $ai['anecdote'] ?? null;
            $d['quotes'] = $ai['quotes'] ?? [];
            $d['portrait'] = (string) ($ai['portrait'] ?? '');
            if (($ai['recit'] ?? '') !== '') {
                $d['recit'] = $ai['recit'];
            }
        }
        return self::$cache[$id] = $d;
    }

    /** Années de joueur au club : « 1987-1996 ». */
    private static function years(array $p): string
    {
        $a = substr((string) ($p['arrival']['iso'] ?? ''), 0, 4);
        $b = substr((string) ($p['departure']['iso'] ?? ''), 0, 4);
        return $a !== '' && $b !== '' ? ($a === $b ? $a : $a . '-' . $b) : ($a !== '' ? $a : \App\Front\Fiche::personYears($p));
    }

    /** Fiche d'identité : naissance, taille, poids, pied, sélections. @return array<string,string> */
    private static function identity(array $p): array
    {
        $born = '';
        if (($b = \App\Front\Fiche::birthRow($p)) !== null) {
            $born = $b[1];
            $city = trim((string) ($p['birth']['place']['city'] ?? ''));
            $country = trim((string) ($p['birth']['place']['country'] ?? ''));
            $place = $city !== '' ? $city . ($country !== '' && $country !== 'France' ? ' (' . $country . ')' : '') : trim((string) ($p['birth']['place']['text'] ?? ''));
            $born .= $place !== '' ? ' à ' . $place : '';
        }
        $intl = '';
        foreach ((array) ($p['international'] ?? []) as $i) {
            $intl = trim(Poster::tidy((string) $i));
            break;
        }
        return array_filter([
            'Né le' => $born,
            'Poste' => trim((string) ($p['position'] ?? '')),
            'Taille' => trim((string) ($p['height'] ?? '')),
            'Poids' => trim((string) ($p['weight'] ?? '')),
            'Pied' => trim((string) ($p['foot'] ?? '')),
            'Arrivée' => trim((string) ($p['arrival']['text'] ?? '')),
            'Sélection' => $intl,
        ], fn ($v) => $v !== '' && !\App\Front\Unknown::has($v));
    }

    /** Palmarès au FCSM, les titres de joueur d'abord. @return list<string> */
    private static function honours(array $p): array
    {
        $out = [];
        foreach ((array) ($p['honours'] ?? []) as $h) {
            $h = trim(Poster::tidy((string) $h));
            if ($h === '' || preg_match('/entra[iî]neur/iu', $h)) {
                continue;
            }
            $out[] = trim((string) preg_replace('/\s*\(joueur\)\s*$/iu', '', $h));
        }
        return array_slice($out, 0, 5);
    }

    /**
     * Matchs et buts saison par saison d'après le tableau de statistiques de la fiche (colonnes
     * « Total matches » et « Total buts » ; la ligne « Total » d'origine, parfois fausse, est
     * recalculée). @return list<array{season:string,m:int,g:int}>
     */
    private static function statsSeasons(?array $stats): array
    {
        if (!$stats || empty($stats['headers']) || empty($stats['rows'])) {
            return [];
        }
        $iM = $iG = null;
        foreach ($stats['headers'] as $i => $h) {
            $h = mb_strtolower((string) $h);
            if (str_starts_with($h, 'total match')) {
                $iM = $i;
            }
            if (str_starts_with($h, 'total but')) {
                $iG = $i;
            }
        }
        if ($iM === null) {
            return [];
        }
        $out = [];
        foreach ($stats['rows'] as $r) {
            if (!preg_match('/^(\d{4})\s*[-\/]\s*(\d{2,4})/', trim((string) ($r[0] ?? '')), $mm)) {
                continue;
            }
            $m = (int) preg_replace('/\D/', '', (string) ($r[$iM] ?? ''));
            if ($m > 0) {
                $out[] = ['season' => $mm[1] . '-' . (strlen($mm[2]) === 2 ? substr($mm[1], 0, 2) . $mm[2] : $mm[2]), 'm' => $m, 'g' => $iG !== null ? (int) preg_replace('/\D/', '', (string) ($r[$iG] ?? '')) : 0];
            }
        }
        return $out;
    }

    /** Matchs et buts saison par saison (compositions reliées). @return list<array{season:string,m:int,g:int}> */
    private static function seasons(array $games): array
    {
        $by = [];
        foreach ($games as $g) {
            $s = (string) ($g['season'] ?? '');
            if ($s === '' || ($g['comp'] ?? '') === 'Amical') {
                continue;
            }
            $by[$s] ??= ['season' => $s, 'm' => 0, 'g' => 0];
            $by[$s]['m']++;
            $by[$s]['g'] += (int) $g['goals'];
        }
        ksort($by);
        return array_values($by);
    }

    /** Ses grands matchs : les plus marquants du musée, puis ceux où il a marqué. @return list<array> */
    private static function bigMatches(array $games): array
    {
        $official = array_values(array_filter($games, fn ($g) => ($g['comp'] ?? '') !== 'Amical'));
        usort($official, fn ($a, $b) => ((int) ($b['hl'] ?? 0) + 4 * (int) $b['goals']) <=> ((int) ($a['hl'] ?? 0) + 4 * (int) $a['goals']) ?: strcmp((string) $a['date'], (string) $b['date']));
        $out = [];
        foreach (array_slice($official, 0, 5) as $g) {
            $score = $g['sh'] ? $g['us'] . '-' . $g['them'] : $g['them'] . '-' . $g['us'];
            $out[] = ['date' => (string) $g['date'], 'teams' => $g['home'] . ' ' . $score . ' ' . $g['away'], 'comp' => trim(implode(' · ', array_unique(array_filter([(string) ($g['label'] ?: $g['comp']), (string) ($g['round'] ?? '')])))),
                'goals' => (int) $g['goals'], 'result' => (string) $g['result'], 'captain' => !empty($g['captain'])];
        }
        usort($out, fn ($a, $b) => strcmp($a['date'], $b['date']));
        return $out;
    }

    /** Ses places dans les 100 chiffres du FCSM (dans les dix premiers). @return list<array{rank:int,label:string,value:string}> */
    private static function records(int $id, string $name): array
    {
        $out = [];
        try {
            $flat = Chiffres::flat();
        } catch (\Throwable) {
            return [];
        }
        foreach ($flat as $c) {
            $rank = null;
            $val = '';
            foreach ((array) ($c['who'] ?? []) as $w) {
                if ((int) ($w['id'] ?? 0) === $id || ($w['name'] ?? '') === $name) {
                    [$rank, $val] = [1, (string) $c['value']];
                }
            }
            foreach ((array) ($c['more'] ?? []) as $i => $w) {
                if ($rank === null && ($w['name'] ?? '') === $name && $i < 9) {
                    [$rank, $val] = [$i + 2, (string) ($w['v'] ?? '')];
                }
            }
            // Classements de groupe (« Le cercle des 300 matchs ») : pas un record personnel.
            if ($rank !== null && $val !== '' && !preg_match('/joueurs?$/u', (string) ($c['unit'] ?? ''))) {
                $out[] = ['rank' => $rank, 'label' => trim(strip_tags((string) $c['label'])), 'value' => trim($val . ' ' . (string) ($c['unit'] ?? ''))];
            }
        }
        usort($out, fn ($a, $b) => $a['rank'] <=> $b['rank']);
        return array_slice($out, 0, 4);
    }

    /** Ses coéquipiers les plus fidèles. @return list<array{name:string,n:int}> */
    private static function mates(int $id): array
    {
        $out = [];
        try {
            foreach (array_slice(\App\Services\FilJaune::teammates($id), 0, 3) as $t) {
                $pl = \App\Services\FilJaune::player($t['id']);
                if ($pl) {
                    $out[] = ['name' => $pl['name'], 'n' => (int) $t['n']];
                }
            }
        } catch (\Throwable) {
        }
        return $out;
    }

    /** Premier match, premier but, dernier match. @return array<string,string> */
    private static function milestones(array $p): array
    {
        $out = [];
        foreach (['first_match' => 'Premier match', 'first_goal' => 'Premier but', 'last_match' => 'Dernier match'] as $k => $lab) {
            $v = trim((string) ($p[$k] ?? ''));
            if ($v === '' || \App\Front\Unknown::has($v)) {
                continue;
            }
            // « Lyon - Sochaux du 08/08/1987 : 1-7 - But à la 5' » → « Lyon - Sochaux, 08/08/1987, 1-7 (5e minute) ».
            $v = (string) preg_replace(['/\s+du\s+(\d)/u', '/\s*[:-]\s*(\d+-\d+)/u', "/\s*-\s*But à la (\d+)'?/u"], [', $1', ', $1', ' (but à la $1e minute)'], $v);
            $out[$lab] = $v;
        }
        return $out;
    }

    /** @return array{n:string,text:string}|null */
    private static function figure(array $doc): ?array
    {
        $k = (array) ($doc['key_figure'] ?? []);
        $n = trim((string) ($k['number'] ?? ''));
        $t = Poster::tidy((string) ($k['text'] ?? ''));
        return $n === '' || $t === '' || mb_strlen($n) > 6 ? null : ['n' => $n, 'text' => $t];
    }

    /** Le récit : le résumé audio du musée, sinon les rubriques de la fiche. */
    private static function recitText(array $doc): string
    {
        $t = '';
        try {
            $t = (string) (FicheAudio::current($doc, 'fr')['text'] ?? '');
        } catch (\Throwable) {
        }
        if (mb_strlen($t) < 300) {
            $t = '';
            foreach ((array) ($doc['sections'] ?? []) as $s) {
                if (preg_match('/statistiques/iu', (string) ($s['title'] ?? ''))) {
                    continue;
                }
                $t .= ' ' . html_entity_decode(strip_tags((string) preg_replace('/<\/(li|p)>/i', '. ', (string) ($s['html'] ?? ''))), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }
        $t = (string) preg_replace('/\s*(pic\.twitter\.com|https?:\/\/)\S*/u', '', $t);
        return trim((string) preg_replace(['/\.\s*\./u', '/\s+/u'], ['.', ' '], $t));
    }

    // ------------------------------------------------------------------ enrichissement par l'IA

    private static function cacheFile(string $id): string
    {
        return STORAGE_PATH . '/shop/posters/j' . $id . '.json';
    }

    public static function enriched(string $id): ?array
    {
        $c = \App\Core\JsonStore::read(self::cacheFile($id));
        return is_array($c) && ($c['v'] ?? 0) === self::VERSION ? $c : null;
    }

    /** Prépare (une fois par joueur) l'anecdote, la citation, le portrait et le récit condensé. */
    public static function enrich(string $id): bool
    {
        if (self::enriched($id)) {
            return true;
        }
        $doc = ctype_digit($id) ? Fiches::get((int) $id) : null;
        $d = self::data($id);
        if (!$doc || !$d || !Gemini::ready()) {
            return false;
        }
        $src = Poster::source($doc);
        $audio = self::recitText($doc);
        $system = 'Tu prépares le contenu d’un poster souvenir consacré à un joueur du FC Sochaux-Montbéliard pour le musée Sochaux Rétro. '
            . 'Tu n’utilises QUE les faits de la fiche fournie : aucun nom, nombre, date ou fait qui n’y figure pas. Français soigné, ton factuel et vivant, sans question, sans « Le saviez-vous ».';
        $prompt = 'JOUEUR : ' . $d['name'] . "\n\nFICHE :\n" . mb_substr($src, 0, 9000) . "\n\nRÉSUMÉ AUDIO :\n" . mb_substr($audio, 0, 4000) . "\n\n"
            . "Réponds en JSON : {\"anecdote\":{\"titre\":\"3 à 5 mots, accrocheur\",\"texte\":\"une anecdote marquante de sa carrière (record, coulisses, geste resté dans les mémoires…), 160 à 230 caractères\"},"
            . "\"citations\":[{\"qui\":\"Prénom Nom\",\"role\":\"fonction\",\"texte\":\"citation recopiée MOT POUR MOT depuis la fiche, 40 à 150 caractères\"}] (au plus 2, uniquement de vraies déclarations de la fiche ; liste vide sinon),"
            . "\"portrait\":\"qui il était pour Sochaux, en une phrase de 120 à 200 caractères\","
            . "\"recit\":\"sa carrière au FCSM racontée et condensée, 900 à 1400 caractères, au passé\"}";
        try {
            $r = Gemini::generate([['role' => 'user', 'parts' => [['text' => $prompt]]]], $system, ['max_tokens' => 2200, 'json' => true, 'for' => 'boutique-poster', 'ref' => 'fiche:' . $id, 'timeout' => 60]);
        } catch (\Throwable) {
            return false;
        }
        $j = json_decode((string) preg_replace('/^```(?:json)?|```$/m', '', trim($r['text'])), true);
        if (!is_array($j)) {
            return false;
        }
        $out = ['v' => self::VERSION, 'at' => date('c')];
        $an = (array) ($j['anecdote'] ?? []);
        $at = trim((string) ($an['texte'] ?? ''));
        if ($at !== '' && mb_strlen($at) <= 260 && Poster::grounded($at, $src)) {
            $out['anecdote'] = ['title' => mb_substr(trim((string) ($an['titre'] ?? 'L’anecdote')), 0, 40), 'text' => $at];
        }
        $norm = fn (string $s) => mb_strtolower((string) preg_replace('/[^\p{L}\p{N}]+/u', '', \App\Data\Names::ascii($s)));
        $q = [];
        foreach (array_slice((array) ($j['citations'] ?? []), 0, 2) as $c) {
            $t = trim((string) ($c['texte'] ?? ''), " \"«»“”");
            $who = trim((string) ($c['qui'] ?? ''));
            if ($t !== '' && $who !== '' && mb_strlen($t) <= 170 && str_contains($norm($src), $norm($t)) && Poster::grounded($who, $src)) {
                $q[] = ['who' => $who, 'role' => mb_substr(trim((string) ($c['role'] ?? '')), 0, 50), 'text' => $t, 'side' => ''];
            }
        }
        if ($q) {
            $out['quotes'] = $q;
        }
        $pt = trim((string) ($j['portrait'] ?? ''));
        if ($pt !== '' && mb_strlen($pt) <= 230 && Poster::grounded($pt, $src)) {
            $out['portrait'] = $pt;
        }
        $rc = trim((string) ($j['recit'] ?? ''));
        if (mb_strlen($rc) >= 400 && mb_strlen($rc) <= 1700 && Poster::grounded($rc, $src . "\n" . $audio)) {
            $out['recit'] = $rc;
        }
        \App\Core\JsonStore::write(self::cacheFile($id), $out);
        unset(self::$cache[$id]);
        return true;
    }

    // ------------------------------------------------------------------ dessin

    /** Calques (mm) du poster dans le cadre du calque ; sans joueur valable, le joueur d'exemple. @return list<array> */
    public static function layers(array $frame, array $values): array
    {
        $d = self::data((string) ($values[self::FIELD] ?? '')) ?? self::data(self::SAMPLE);
        if (!$d) {
            return [];
        }
        $pour = trim(mb_substr(trim((string) ($values['poster_prenom'] ?? '')), 0, 30) . ' ' . mb_substr(trim((string) ($values['poster_nom'] ?? '')), 0, 30));
        $no = (string) preg_replace('/[^0-9A-Z-]/', '', strtoupper((string) ($values['_poster_no'] ?? '')));
        return Poster::fit((new PlayerPosterLayout($d, $pour !== '' ? $pour : 'Prénom Nom', $no))->build(), $frame);
    }
}
