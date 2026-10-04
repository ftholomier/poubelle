<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\JsonStore;
use App\Core\Settings;
use App\Data\Categories;
use App\Data\Derived;
use App\Data\Fiches;
use App\Data\Names;
use App\Front\Fiche;
use App\Front\Unknown;

/**
 * Recherche sur le web pour une fiche (aide à l'historien, bouton de l'éditeur) : Gemini cherche
 * avec Google ce qui pourrait corriger ou compléter la fiche et renvoie des propositions courtes
 * (divergences, compléments, pistes), chacune reliée aux pages qui l'appuient. Rien n'est écrit
 * dans la fiche : l'historien ouvre la source, vérifie, puis reporte lui-même. Lancée seulement
 * à la demande (jamais en tâche de fond) ; le dernier résultat de chaque fiche est gardé 30 jours
 * (storage/recherche-web/{id}.json) pour être relu sans nouvelle recherche.
 */
final class WebCheck
{
    public const KEEP_DAYS = 30;
    public const MAX_ITEMS = 12;
    public const TYPES = ['divergence' => 'Divergence', 'complement' => 'Complément', 'piste' => 'Piste'];

    public static string $dir = STORAGE_PATH . '/recherche-web';
    /** Essais automatiques : remplace l'appel à Gemini (consigne, texte, options → réponse de generate() avec « raw »). */
    public static ?\Closure $ai = null;

    public static function enabled(): bool
    {
        return (bool) Settings::get('recherche.enabled', true) && (self::$ai !== null || Gemini::ready());
    }

    /** Modèle réglé pour la recherche, sinon le « Modèle de réponse » de l'assistant. */
    public static function model(): string
    {
        $m = trim((string) Settings::get('recherche.model', ''));
        return $m !== '' ? $m : Gemini::model();
    }

    /** Recherches faites ce mois-ci et plafond réglé (0 : aucun). */
    public static function quota(): array
    {
        $used = (int) (AiCosts::month(date('Y-m'))['uses']['recherche']['calls'] ?? 0);
        $limit = max(0, (int) Settings::get('recherche.monthly_limit', 300));
        return ['used' => $used, 'limit' => $limit, 'over' => $limit > 0 && $used >= $limit];
    }

    /** Dernier résultat de la fiche (moins de 30 jours), sinon null. */
    public static function last(int $id): ?array
    {
        $f = self::$dir . "/$id.json";
        $r = is_file($f) ? JsonStore::read($f, null) : null;
        if (!is_array($r) || !is_array($r['items'] ?? null) || (int) strtotime((string) ($r['at'] ?? '')) < time() - self::KEEP_DAYS * 86400) {
            return null;
        }
        return $r;
    }

    /** Lance la recherche pour la fiche : propositions et sources, gardées pour la fiche. */
    public static function run(array $doc): array
    {
        [$system, $text] = self::prompt($doc);
        $opt = [
            'for' => 'recherche', 'ref' => 'fiche:' . (int) $doc['id'], 'model' => self::model(),
            'tools' => [['google_search' => new \stdClass()]], 'max_tokens' => 4096, 'temperature' => 0.2, 'timeout' => 100, 'raw' => true,
        ];
        $r = self::$ai ? (self::$ai)($system, $text, $opt) : Gemini::generate([['role' => 'user', 'text' => $text]], $system, $opt);
        $out = self::parse((string) ($r['text'] ?? ''), (array) ($r['raw'] ?? []));
        $out += ['id' => (int) $doc['id'], 'at' => date('c'), 'by' => (string) (Auth::user()['name'] ?? ''), 'model' => (string) ($r['model'] ?? $opt['model'])];
        try {
            JsonStore::write(self::$dir . '/' . (int) $doc['id'] . '.json', $out);
        } catch (\Throwable $e) {
            error_log('[recherche web] ' . $e->getMessage()); // résultat affiché quand même
        }
        return $out;
    }

    /** Efface les résultats de plus de 30 jours (tâche planifiée). */
    public static function purge(): int
    {
        clearstatcache();
        $n = 0;
        foreach (glob(self::$dir . '/*.json') ?: [] as $f) {
            if (@filemtime($f) < time() - self::KEEP_DAYS * 86400 && @unlink($f)) {
                $n++;
            }
        }
        return $n;
    }

    // ------------------------------------------------------------------ consigne

    /**
     * Consigne et description de la fiche envoyées à Gemini.
     * @return array{0:string,1:string}
     */
    public static function prompt(array $doc): array
    {
        $system = <<<'TXT'
Tu aides les historiens du musée en ligne Sochaux Rétro, consacré à l'histoire du FC Sochaux-Montbéliard (FCSM). On te donne une fiche du musée. Cherche sur le web, avec Google, des informations fiables sur ce sujet précis, puis compare-les avec la fiche.
Propose :
– des divergences : ce que les sources disent autrement que la fiche (date, score, buteurs et minutes, affluence, arbitre, stade, composition, naissance, parcours, chiffres) ;
– des compléments : ce qui manque à la fiche (champs « non renseigné », faits, chiffres, contexte utile) ;
– des pistes : pages, archives, photos ou vidéos qui méritent d'être consultées.
Règles :
– seulement ce que disent les pages trouvées : n'invente rien, ne devine rien ; une information sans source n'est pas proposée ;
– reste sur le sujet exact : pas un autre match, pas un homonyme, pas une autre saison ;
– pour une divergence, rappelle ce que dit la fiche et ce que disent les sources ; si les sources ne sont pas d'accord entre elles, dis-le ;
– phrases courtes et précises, en français ;
– au plus 12 propositions, les plus utiles d'abord ; si rien de fiable n'est trouvé, aucune proposition, et dis-le dans le résumé.
Réponds uniquement par un objet JSON, sans texte autour :
{"resume": "une ou deux phrases", "propositions": [{"type": "divergence | complement | piste", "champ": "ce que ça concerne (ex. Arbitre, Score, Naissance)", "fiche": "ce que dit la fiche (vide pour un complément ou une piste)", "proposition": "ce que disent les sources", "confiance": "haute | moyenne | faible"}]}
TXT;
        return [$system, self::describe($doc)];
    }

    /** La fiche en quelques lignes, telle qu'elle s'affiche (« xx » de l'ancien site retirés). */
    public static function describe(array $doc): string
    {
        $d = Unknown::doc(MatchText::header($doc, false));
        $none = 'non renseigné';
        $v = fn (mixed $x) => ($s = trim(preg_replace('/\s+/u', ' ', is_scalar($x) ? (string) $x : '') ?? '')) !== '' ? $s : $none;
        $lines = ['Fiche du musée Sochaux Rétro (FC Sochaux-Montbéliard).', 'Titre : ' . $v($d['title'] ?? '')];
        $type = (string) ($d['type'] ?? '');
        if ($type === 'match' && is_array($d['match'] ?? null)) {
            $m = $d['match'];
            $lines[] = 'Type : match';
            $lines[] = 'Date : ' . (!empty($m['date']) ? date_fr((string) $m['date'], true) : $none) . (!empty($m['season']) ? ' (saison ' . $m['season'] . ')' : '');
            $lines[] = 'Compétition : ' . $v(implode(' · ', array_unique(array_filter([(string) ($m['competition'] ?? ''), (string) ($m['competition_label'] ?? ''), (string) ($m['round_text'] ?? '')]))));
            $home = (string) ($m['home']['name'] ?? '');
            $away = (string) ($m['away']['name'] ?? '');
            $lines[] = 'Match : ' . $v(trim($home . ' – ' . $away, ' –')) . (!empty($m['sochaux_home']) ? ' (Sochaux à domicile)' : ' (Sochaux à l’extérieur)');
            $s = $m['score'] ?? null;
            $lines[] = 'Score : ' . (isset($s['home'], $s['away']) ? $s['home'] . '-' . $s['away'] . (!empty($s['aet']) ? ' après prolongation' : '') . (isset($s['pens']['home']) ? ', tirs au but ' . $s['pens']['home'] . '-' . $s['pens']['away'] : '') : $none);
            $goals = array_filter(array_map(fn ($g) => is_array($g) && trim((string) ($g['scorers'] ?? '')) !== '' ? trim((string) ($g['team'] ?? '')) . ' : ' . trim((string) $g['scorers']) : '', (array) ($m['goals'] ?? [])));
            $lines[] = 'Buteurs : ' . $v($goals ? implode(' ; ', $goals) : ($m['goals_text'] ?? ''));
            $lines[] = 'Stade : ' . $v($m['stadium'] ?? '');
            $lines[] = 'Spectateurs : ' . (!empty($m['spectators']) ? number_format((int) $m['spectators'], 0, ',', ' ') : $v($m['spectators_text'] ?? ''));
            $lines[] = 'Arbitre : ' . $v($m['referee'] ?? '');
            if (!empty($m['event'])) {
                $lines[] = 'Événement : ' . $v($m['event']);
            }
            $rows = array_filter((array) ($m['lineup']['rows'] ?? []), fn ($r) => is_array($r) && trim((string) ($r['name'] ?? '')) !== '');
            $lines[] = 'Composition de Sochaux : ' . ($rows ? implode(', ', array_map(fn ($r) => trim(($r['position'] ?? '') . ' ' . Names::display((string) $r['name'])), array_slice($rows, 0, 30))) : $none);
        } elseif ($type === 'personne' && is_array($d['personne'] ?? null)) {
            $p = $d['personne'];
            $lines[] = 'Type : personne (' . implode(', ', array_map('strval', (array) ($p['roles'] ?? []))) . ')';
            $lines[] = 'Nom : ' . $v($p['display_name'] ?? '') . (!empty($p['nickname']) ? ' (surnom : ' . $p['nickname'] . ')' : '');
            $lines[] = 'Poste : ' . $v($p['position'] ?? '');
            $lines[] = 'Naissance : ' . $v($p['birth']['text'] ?? '');
            $lines[] = 'Décès : ' . $v($p['death']['text'] ?? 'non renseigné (vivant ou inconnu)');
            $lines[] = 'Nationalité : ' . $v($p['nationality'] ?? '');
            $lines[] = 'Au FCSM : ' . $v(Fiche::personYears($p));
            $tot = Derived::part('person_totals')[(int) $d['id']] ?? null;
            if ($tot) {
                $lines[] = 'Au FCSM d’après les compositions du musée : ' . (int) ($tot['matches'] ?? 0) . ' matchs, ' . (int) ($tot['goals'] ?? 0) . ' buts' . (!empty($tot['coached']) ? ', ' . (int) $tot['coached'] . ' matchs comme entraîneur' : '');
            }
            foreach ((array) ($p['fiche'] ?? []) as $row) {
                if (is_array($row) && !empty($row['label']) && trim((string) ($row['value'] ?? '')) !== '') {
                    $lines[] = trim((string) $row['label']) . ' : ' . $v($row['value']);
                }
            }
            if (!empty($p['international'])) {
                $lines[] = 'Sélections : ' . $v(implode(' ; ', array_map('strval', (array) $p['international'])));
            }
            if (!empty($p['subtitle'])) {
                $lines[] = 'Présentation : ' . $v($p['subtitle']);
            }
        } else {
            $lines[] = 'Type : ' . mb_strtolower(Fiches::TYPES[$type] ?? 'article');
            $cat = Categories::primaryOf((array) ($d['categories'] ?? []));
            if ($cat) {
                $lines[] = 'Rubrique : ' . Categories::label($cat);
            }
            $year = $d['objet']['year'] ?? ($d['moment']['year'] ?? null);
            if ($year) {
                $lines[] = 'Année : ' . (int) $year;
            }
        }
        $story = trim((string) preg_replace('/\s+/u', ' ', plain(implode("\n", array_merge([(string) ($d['intro'] ?? '')], array_map(fn ($s) => is_array($s) ? (string) ($s['html'] ?? '') : '', (array) ($d['sections'] ?? [])))))));
        $lines[] = 'Texte de la fiche' . (mb_strlen($story) > 2500 ? ' (début)' : '') . ' : ' . ($story !== '' ? mb_substr($story, 0, 2500) : $none);
        return implode("\n", $lines);
    }

    // ------------------------------------------------------------------ réponse

    /**
     * Propositions lues dans la réponse (JSON), chacune reliée aux pages qui appuient son passage
     * (groundingSupports : positions en octets dans le texte de la réponse), sources consultées,
     * recherches lancées et suggestions de recherche de Google (à afficher telles quelles).
     */
    public static function parse(string $text, array $raw): array
    {
        $meta = (array) ($raw['candidates'][0]['groundingMetadata'] ?? []);
        // Les positions des passages appuyés se rapportent au texte brut de chaque partie de la
        // réponse (le texte reçu de generate() est rogné et ses parties mises bout à bout).
        $starts = [];
        $full = '';
        foreach ((array) ($raw['candidates'][0]['content']['parts'] ?? []) as $k => $part) {
            if (is_array($part) && empty($part['thought']) && is_string($part['text'] ?? null)) {
                $starts[(int) $k] = strlen($full);
                $full .= $part['text'];
            }
        }
        if (trim($full) !== '') {
            $text = $full;
        } else {
            $starts = [0 => 0];
        }
        $sources = [];
        $index = []; // rang de la page chez Google => rang dans « sources »
        foreach ((array) ($meta['groundingChunks'] ?? []) as $i => $c) {
            $uri = trim((string) ($c['web']['uri'] ?? ''));
            if (!preg_match('#^https?://[^\s"<>]+$#i', $uri)) {
                continue;
            }
            $title = self::clean($c['web']['title'] ?? '', 120);
            $index[(int) $i] = count($sources);
            $sources[] = ['title' => $title !== '' ? $title : (string) parse_url($uri, PHP_URL_HOST), 'url' => $uri];
        }
        $json = null;
        $a = strpos($text, '{');
        $b = strrpos($text, '}');
        if ($a !== false && $b !== false && $b > $a) {
            $json = json_decode(substr($text, $a, $b - $a + 1), true);
        }
        $items = [];
        foreach (is_array($json['propositions'] ?? null) ? $json['propositions'] : [] as $p) {
            if (!is_array($p) || ($said = self::clean($p['proposition'] ?? '', 700)) === '') {
                continue;
            }
            $type = Names::ascii(self::clean($p['type'] ?? '', 40));
            $conf = Names::ascii(self::clean($p['confiance'] ?? '', 40));
            $items[] = [
                'type' => str_starts_with($type, 'diverg') ? 'divergence' : (str_starts_with($type, 'piste') ? 'piste' : 'complement'),
                'field' => self::clean($p['champ'] ?? '', 80),
                'fiche' => self::clean($p['fiche'] ?? '', 300),
                'text' => $said,
                'confidence' => in_array($conf, ['haute', 'moyenne', 'faible'], true) ? $conf : 'moyenne',
                'sources' => self::supports($text, (string) $p['proposition'], (array) ($meta['groundingSupports'] ?? []), $index, $starts),
            ];
            if (count($items) >= self::MAX_ITEMS) {
                break;
            }
        }
        // Divergences d'abord, puis compléments, puis pistes (ordre de Gemini gardé dans chaque groupe).
        $rank = array_flip(array_keys(self::TYPES));
        usort($items, fn ($x, $y) => $rank[$x['type']] <=> $rank[$y['type']]);
        $summary = self::clean($json['resume'] ?? '', 600);
        if (!is_array($json)) {
            $summary = 'La réponse de l’IA n’a pas pu être lue. Relancez la recherche.';
        }
        return [
            'summary' => $summary,
            'items' => $items,
            'sources' => $sources,
            'queries' => array_values(array_filter(array_map(fn ($q) => self::clean($q, 160), array_slice((array) ($meta['webSearchQueries'] ?? []), 0, 10)))),
            'suggestions' => mb_substr((string) ($meta['searchEntryPoint']['renderedContent'] ?? ''), 0, 60000),
        ];
    }

    /**
     * Pages qui appuient une proposition : passages de la réponse (groundingSupports) qui recouvrent
     * l'endroit où la proposition est écrite ($starts : début de chaque partie dans le texte).
     * @return list<int> rangs dans « sources »
     */
    private static function supports(string $text, string $said, array $supports, array $index, array $starts): array
    {
        $needle = substr((string) json_encode($said, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 1, -1);
        $at = $needle !== '' ? strpos($text, $needle) : false;
        if ($at === false) {
            $needle = substr($said, 0, 40);
            $at = $needle !== '' ? strpos($text, $needle) : false;
        }
        if ($at === false) {
            return [];
        }
        $end = $at + strlen($needle);
        $out = [];
        foreach ($supports as $s) {
            $from = $starts[(int) ($s['segment']['partIndex'] ?? 0)] ?? null;
            if ($from === null) {
                continue;
            }
            $a = $from + (int) ($s['segment']['startIndex'] ?? 0);
            $b = $from + (int) ($s['segment']['endIndex'] ?? 0);
            if ($b > $at && $a < $end) {
                foreach ((array) ($s['groundingChunkIndices'] ?? []) as $c) {
                    if (isset($index[(int) $c])) {
                        $out[$index[(int) $c]] = true;
                    }
                }
            }
        }
        $out = array_keys($out);
        sort($out);
        return array_slice($out, 0, 5);
    }

    /** Texte court et propre (une ligne, sans caractères de contrôle). */
    private static function clean(mixed $s, int $max): string
    {
        $s = is_scalar($s) ? (string) $s : '';
        $s = trim((string) preg_replace(['/[\x00-\x1F\x7F]+/u', '/\s+/u'], ' ', $s));
        return mb_strlen($s) > $max ? rtrim(mb_substr($s, 0, $max - 1)) . '…' : $s;
    }
}
