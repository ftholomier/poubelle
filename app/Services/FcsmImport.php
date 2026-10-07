<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\JsonStore;
use App\Data\Categories;
use App\Data\Fiches;
use App\Data\Index;
use App\Data\Names;
use App\Data\Paths;

/**
 * Import des années 1928-1969 depuis fcsmstory.com, en plusieurs étapes suivies dans
 * storage/import/fcsmstory/state.json :
 *  1. plan() : ce qui sera créé (matchs, saisons, tournois, articles, portraits manquants) ;
 *     aucun doublon : un match déjà au musée (même date) ou un joueur déjà présent est écarté ;
 *  2. run() (tâche planifiée « fcsmstory », ou bouton) : réécriture par Gemini, contrôle de
 *     ressemblance avec le texte d'origine (relancée si trop proche), puis création de la fiche
 *     publiée, avec une ligne « Sources ».
 * Les faits (date, score, composition, buteurs) sont repris tels quels ; aucune image.
 */
final class FcsmImport
{
    /** Part maximale des suites de 6 mots du texte d'origine retrouvées dans la réécriture. */
    public const SIM_MAX = 0.06;
    /** Récits plus courts : la fiche est faite sans IA, à partir des seuls faits. */
    private const MIN_REPORT = 160;
    private const PARALLEL = 6;
    /** Générateur de texte remplaçable (essais automatiques) : fn(array $jobs): array comme Gemini::generateMany. */
    public static $generate = null;
    private const AUTHOR = ['name' => 'Reprise FCSM Story'];

    // ------------------------------------------------------------------ plan

    private static function file(): string
    {
        return FcsmStory::$dir . '/state.json';
    }

    public static function state(): array
    {
        return JsonStore::read(self::file(), []) + ['items' => [], 'status' => 'vide', 'at' => null, 'running' => false, 'log' => []];
    }

    /**
     * Prépare (ou met à jour) la liste des fiches à créer. Les éléments déjà faits gardent leur état.
     * @return array l'état
     */
    public static function plan(?array $analysis = null): array
    {
        $a = $analysis ?? FcsmStory::analyse();
        $existingDates = [];
        $existingPeople = [];
        foreach (Index::all() as $id => $s) {
            if (($s['type'] ?? '') === 'match' && !empty($s['m']['date'])) {
                $existingDates[(string) $s['m']['date']][] = (int) $id;
            } elseif (($s['type'] ?? '') === 'personne') {
                $existingPeople[] = [(int) $id, Names::tokens(preg_replace('/\(.*?\)/u', '', (string) $s['title']) ?? '')];
            }
        }
        $items = [];
        foreach ($a['matches'] as $m) {
            $key = 'match:' . FcsmStory::key($m);
            $items[$key] = ['key' => $key, 'kind' => 'match', 'label' => self::matchLabel($m), 'data' => $m, 'source' => $m['source']];
        }
        foreach ($a['others']['match'] as $o) {
            if (empty($o['merged'])) {
                $items['page:' . $o['slug']] = ['key' => 'page:' . $o['slug'], 'kind' => 'match-page', 'label' => $o['title'], 'source' => $o['link'], 'date' => $o['date']];
            } else {
                // Récit à part d'un match de saison : il enrichit ce match.
                $k = 'match:' . $o['key'];
                if (isset($items[$k])) {
                    $items[$k]['extra'][] = $o['link'];
                }
            }
        }
        foreach ($a['seasons'] as $s) {
            $items['season:' . $s['season']] = ['key' => 'season:' . $s['season'], 'kind' => 'season', 'label' => $s['title'], 'source' => $s['link'], 'season' => $s['season']];
        }
        foreach (['tournament', 'article'] as $kind) {
            foreach ($a['others'][$kind] as $o) {
                $items['article:' . $o['slug']] = ['key' => 'article:' . $o['slug'], 'kind' => $kind, 'label' => $o['title'], 'source' => $o['link']];
            }
        }
        foreach ($a['others']['player'] as $o) {
            $name = self::personName($o['title']);
            $pid = self::samePerson($name, $existingPeople);
            $items['player:' . $o['slug']] = ['key' => 'player:' . $o['slug'], 'kind' => 'player', 'label' => $name, 'source' => $o['link']]
                + ($pid ? ['status' => 'existe', 'fiche' => $pid, 'why' => 'Déjà au musée : fiche laissée telle quelle'] : []);
        }
        // Doublons avec le musée : un match à la même date est laissé de côté.
        foreach ($items as $k => &$it) {
            $date = $it['data']['date'] ?? ($it['date'] ?? null);
            if (in_array($it['kind'], ['match', 'match-page'], true) && $date && isset($existingDates[$date]) && empty($it['status'])) {
                $it['status'] = 'doublon';
                $it['fiche'] = $existingDates[$date][0];
                $it['why'] = 'Un match du ' . date_fr($date) . ' est déjà au musée';
            }
        }
        unset($it);
        $prev = self::state();
        $out = [];
        foreach ($items as $k => $it) {
            $old = $prev['items'][$k] ?? null;
            // Ce qui est déjà fait (ou en cours) ne repart pas de zéro.
            $out[$k] = $old && in_array($old['status'] ?? '', ['fait', 'erreur', 'trop-proche'], true) ? $old : $it + ['status' => 'a-faire', 'tries' => 0];
        }
        $state = ['items' => $out, 'status' => 'pret', 'at' => date('c'), 'running' => $prev['running'] ?? false, 'log' => $prev['log'] ?? [],
            'unlinked' => self::unlinkedPlayers($a['matches'])];
        JsonStore::write(self::file(), $state);
        return $state;
    }

    /** Compte par type et par état. */
    public static function summary(?array $state = null): array
    {
        $state ??= self::state();
        $out = [];
        foreach ($state['items'] as $it) {
            $out[$it['kind']][$it['status']] = ($out[$it['kind']][$it['status']] ?? 0) + 1;
        }
        return $out;
    }

    public static function start(bool $on, ?array $user = null): void
    {
        JsonStore::update(self::file(), function ($s) use ($on, $user) {
            $s['running'] = $on;
            $s['log'][] = ['at' => date('c'), 'text' => ($on ? 'Import lancé' : 'Import mis en pause') . ($user ? ' par ' . ($user['name'] ?? '') : '')];
            $s['log'] = array_slice($s['log'], -40);
            return $s;
        }, []);
    }

    // ------------------------------------------------------------------ traitement

    /** Tâche planifiée : un lot si l'import est lancé. */
    public static function tick(): ?string
    {
        $s = self::state();
        if (empty($s['running'])) {
            return null;
        }
        $r = self::run(24);
        if ($r['left'] === 0) {
            self::start(false);
        }
        return $r['done'] . ' créée(s), ' . $r['errors'] . ' erreur(s), ' . $r['left'] . ' restante(s)';
    }

    /** Remet à faire les éléments en erreur ou trop proches. */
    public static function retry(): void
    {
        JsonStore::update(self::file(), function ($s) {
            foreach ($s['items'] as &$it) {
                if (in_array($it['status'] ?? '', ['erreur', 'trop-proche'], true)) {
                    $it['status'] = 'a-faire';
                    $it['tries'] = 0;
                }
            }
            return $s;
        }, []);
    }

    /**
     * Traite au plus $max éléments à faire (ou ceux de $only). Renvoie le nombre créé et les erreurs.
     * @return array{done:int,errors:int,close:int,left:int}
     */
    public static function run(int $max = 30, ?array $only = null): array
    {
        $state = self::state();
        $todo = array_values(array_filter($state['items'], fn ($it) => ($it['status'] ?? '') === 'a-faire' && ($only === null || in_array($it['key'], $only, true))));
        $todo = array_slice($todo, 0, $max);
        $raw = [];
        foreach (FcsmStory::raw() as $r) {
            $raw[$r['link']] = $r;
        }
        $res = ['done' => 0, 'errors' => 0, 'close' => 0];
        foreach (array_chunk($todo, self::PARALLEL) as $chunk) {
            $jobs = [];
            $plain = [];
            foreach ($chunk as $it) {
                $src = self::sourceText($it, $raw);
                if ($it['kind'] === 'match' && mb_strlen($src) < self::MIN_REPORT) {
                    $plain[] = $it; // pas de récit à réécrire : fiche faite des seuls faits
                    continue;
                }
                $jobs[$it['key']] = [$it, $src];
            }
            foreach ($plain as $it) {
                self::finish($it, null, '', $res);
            }
            if (!$jobs) {
                continue;
            }
            $answers = self::ask(array_map(fn ($j) => self::prompt($j[0], $j[1], (int) ($j[0]['tries'] ?? 0)), $jobs));
            foreach ($jobs as $key => [$it, $src]) {
                self::finish($it, $answers[$key] ?? ['error' => 'pas de réponse'], $src, $res);
            }
        }
        $res['left'] = count(array_filter(self::state()['items'], fn ($it) => ($it['status'] ?? '') === 'a-faire'));
        return $res;
    }

    /** Appelle Gemini (ou le générateur des essais) : [clé => job] → [clé => réponse]. */
    private static function ask(array $jobs): array
    {
        $keys = array_keys($jobs);
        $gen = self::$generate ?? fn (array $j) => Gemini::generateMany($j);
        $out = $gen(array_values($jobs));
        return array_combine($keys, array_map(fn ($i) => $out[$i] ?? ['error' => 'pas de réponse'], array_keys($keys)));
    }

    /** Réponse reçue : contrôle, puis création de la fiche (ou nouvel essai). */
    private static function finish(array $it, ?array $answer, string $src, array &$res): void
    {
        $update = ['tries' => (int) ($it['tries'] ?? 0) + 1];
        try {
            $text = null;
            if ($answer !== null) {
                if (isset($answer['error'])) {
                    throw new \RuntimeException('IA : ' . $answer['error']);
                }
                $text = self::decode((string) ($answer['text'] ?? ''));
                $sim = self::similarity($src, self::flatten($text));
                $update['similarity'] = round($sim, 3);
                if ($sim > self::SIM_MAX) {
                    // Trop proche du texte d'origine : un nouvel essai, avec une consigne plus ferme.
                    if ($update['tries'] < 3) {
                        self::setItem($it['key'], $update + ['status' => 'a-faire', 'why' => 'Trop proche de l’original (' . round($sim * 100) . ' %), réécrit à nouveau']);
                        $res['close']++;
                        return;
                    }
                    self::setItem($it['key'], $update + ['status' => 'trop-proche', 'why' => 'Encore trop proche après 3 essais (' . round($sim * 100) . ' %) : non créé']);
                    $res['errors']++;
                    return;
                }
            }
            $id = Fiches::batch(fn () => self::create($it, $text));
            self::setItem($it['key'], $update + ['status' => 'fait', 'fiche' => $id, 'at' => date('c'), 'why' => '']);
            $res['done']++;
        } catch (\Throwable $e) {
            $final = $update['tries'] >= 3;
            self::setItem($it['key'], $update + ['status' => $final ? 'erreur' : 'a-faire', 'why' => mb_substr($e->getMessage(), 0, 240)]);
            $res['errors']++;
        }
    }

    private static function setItem(string $key, array $patch): void
    {
        JsonStore::update(self::file(), function ($s) use ($key, $patch) {
            if (isset($s['items'][$key])) {
                $s['items'][$key] = $patch + $s['items'][$key];
            }
            return $s;
        }, []);
    }

    /** Texte d'origine d'un élément (récit du match, page entière, ou introduction + bilan de la saison). */
    private static function sourceText(array $it, array $raw): string
    {
        if ($it['kind'] === 'match') {
            $t = (string) ($it['data']['report'] ?? '');
            foreach ($it['extra'] ?? [] as $link) {
                $t .= isset($raw[$link]) ? "\n\n" . FcsmStory::text($raw[$link]['html']) : '';
            }
            return trim($t);
        }
        $r = $raw[$it['source']] ?? null;
        if (!$r) {
            throw new \RuntimeException('Page d’origine absente : relancez « Analyser ».');
        }
        if ($it['kind'] === 'season') {
            $p = FcsmStory::season($r['html'], (string) $it['season']);
            return trim($p['intro'] . "\n\n" . $p['outro']);
        }
        return FcsmStory::text($r['html']);
    }

    // ------------------------------------------------------------------ consignes

    private const STYLE = <<<TXT
Tu écris pour Sochaux Rétro, le musée en ligne du FC Sochaux-Montbéliard, en français.
Tu reçois un texte d'origine (recherches d'un historien, souvent des comptes rendus de presse de l'époque).
Tu dois le RÉÉCRIRE ENTIÈREMENT avec tes propres mots, pour un lecteur d'aujourd'hui :
- ne reprends jamais une suite de plus de quatre mots du texte d'origine (sauf noms propres, titres de compétition et une seule citation de presse de moins de 15 mots, entre guillemets, attribuée à « la presse de l'époque ») ;
- change l'ordre de présentation quand c'est possible (commence par l'enjeu, le contexte ou le fait marquant plutôt que par le coup d'envoi), change la construction des phrases et le vocabulaire ;
- garde tous les faits exacts (dates, noms, scores, minutes, buteurs, lieux), n'invente rien, ne romance pas ;
- écris comme un historien passionné et sobre : phrases de longueurs variées, verbes concrets, une touche d'humour ou d'émotion quand le fait s'y prête, jamais de formules toutes faites ;
- interdits : « véritable », « riche en émotions », « en somme », « il convient de », « force est de constater », « un match à rebondissements », « mémorable », les tirets longs, les énumérations de trois adjectifs, les conclusions morales, les points d'exclamation en série ;
- noms des clubs et des joueurs comme dans les faits fournis ; le club est « Sochaux », « le FC Sochaux » ou « les Sochaliens ».
Réponds UNIQUEMENT par un objet JSON valide, sans texte autour.
TXT;

    /** @return array [contents, system, opt] pour Gemini::generateMany */
    public static function prompt(array $it, string $src, int $tries = 0): array
    {
        $firm = $tries > 0 ? "\nATTENTION : ta version précédente reprenait trop de passages du texte d'origine. Reformule plus librement, change l'angle et l'ordre, n'emprunte aucune tournure." : '';
        $src = mb_substr($src, 0, 24000);
        switch ($it['kind']) {
            case 'match':
                $facts = self::factsLine($it['data']);
                $ask = "Faits du match (exacts, à respecter) :\n$facts\n\nTexte d'origine :\n$src\n\n"
                    . 'Rends : {"intro": "2 ou 3 phrases d\'accroche", "sections": [{"titre": "...", "texte": "paragraphes séparés par une ligne vide"}], '
                    . '"temps_forts": [{"minute": "37 ou vide", "texte": "une phrase", "but": true}], "chiffre": {"nombre": "...", "texte": "une phrase"}}. '
                    . '2 à 4 sections (par exemple : contexte, déroulement, les hommes du match, après le match), au total 150 à 450 mots selon la richesse du texte. Temps forts dans l\'ordre du match.';
                break;
            case 'match-page':
                $ask = "Texte d'origine (un match du FC Sochaux, date : " . ($it['date'] ?? 'voir texte') . ") :\n$src\n\n"
                    . 'Rends : {"faits": {"date": "AAAA-MM-JJ", "domicile": "club", "exterieur": "club", "buts_domicile": 0, "buts_exterieur": 0, "competition": "Championnat, Coupe de France, Match amical…", "stade": "", "spectateurs": null, '
                    . '"composition_sochaux": ["Nom", "…"], "buteurs_sochaux": "Nom 12\', Nom 70\'"}, "intro": "…", "sections": [{"titre": "…", "texte": "…"}], "temps_forts": [{"minute": "", "texte": "", "but": false}], "chiffre": {"nombre": "", "texte": ""}}. '
                    . 'Faits strictement tirés du texte (null ou vide si inconnus). 3 à 5 sections, 300 à 700 mots.';
                break;
            case 'season':
                $ask = "Saison $it[season] du FC Sochaux. Texte d'origine (contexte, effectif, transferts, championnat, bilan) :\n$src\n\n"
                    . 'Rends : {"intro": "3 ou 4 phrases qui résument la saison", "sections": [{"titre": "…", "texte": "…"}]}. 3 à 6 sections (le contexte, l\'effectif, les compétitions, les grands rendez-vous, le bilan), 300 à 800 mots.';
                break;
            case 'player':
                $ask = "Portrait d'un joueur du FC Sochaux. Texte d'origine :\n$src\n\n"
                    . 'Rends : {"prenom": "", "nom": "", "poste": "gardien, défenseur, demi, avant…", "nationalite": "", "intro": "2 ou 3 phrases", "sections": [{"titre": "Son recrutement", "texte": ""}, {"titre": "Sa carrière au FCSM", "texte": ""}, {"titre": "Sa carrière après le FCSM", "texte": ""}]}. 300 à 700 mots au total.';
                break;
            default: // tournoi, coupe, article
                $ask = "Texte d'origine (" . ($it['kind'] === 'tournament' ? 'un tournoi ou une coupe disputé par le FC Sochaux' : 'un récit sur l\'histoire du FC Sochaux') . ") :\n$src\n\n"
                    . 'Rends : {"titre": "un titre neuf, court, sans deux-points si possible", "intro": "2 ou 3 phrases", "sections": [{"titre": "…", "texte": "…"}]}. 3 à 6 sections, 300 à 900 mots selon la richesse du texte.';
        }
        return [[['role' => 'user', 'parts' => [['text' => $ask . $firm]]]], self::STYLE,
            ['for' => 'import', 'ref' => $it['key'], 'max_tokens' => 4000, 'json' => true, 'temperature' => 0.95, 'timeout' => 120]];
    }

    /** Faits d'un match en quelques lignes (pour la consigne). */
    private static function factsLine(array $m): string
    {
        $score = $m['us'] !== null ? ($m['home'] ? "$m[us]-$m[them]" : "$m[them]-$m[us]") . ' (Sochaux ' . $m['us'] . ', adversaire ' . $m['them'] . ')' : ($m['forfeit'] ? 'décision sur tapis vert' : 'inconnu');
        $l = ['Date : ' . date_fr($m['date']), 'Lieu : ' . $m['place'] . ($m['stadium'] ? ', ' . $m['stadium'] : ''),
            'Adversaire : ' . $m['opponent'], 'Compétition : ' . trim($m['competition'] . ' ' . $m['round']), 'Score : ' . $score];
        if ($m['lineup']) {
            $l[] = 'Sochaux : ' . implode(', ', array_column($m['lineup'], 'name'));
        }
        if ($m['scorers']) {
            $l[] = 'Buteurs sochaliens : ' . implode(', ', array_map(fn ($s) => $s['name'] . ($s['goals'] > 1 ? " ($s[goals])" : '') . ($s['minutes'] ? ' ' . implode("', ", $s['minutes']) . "'" : ''), $m['scorers']));
        }
        if ($m['note']) {
            $l[] = 'Note : ' . $m['note'];
        }
        return implode("\n", $l);
    }

    /** JSON de la réponse (tolère un bloc ```json). */
    private static function decode(string $t): array
    {
        $t = trim(preg_replace('/^```(?:json)?\s*|\s*```$/u', '', trim($t)) ?? '');
        $d = json_decode($t, true);
        if (!is_array($d) || (empty($d['intro']) && empty($d['sections']))) {
            throw new \RuntimeException('Réponse de l’IA illisible');
        }
        return $d;
    }

    /** Tout le texte d'une réponse, pour la mesure de ressemblance. */
    private static function flatten(array $d): string
    {
        $parts = [(string) ($d['intro'] ?? ''), (string) ($d['titre'] ?? '')];
        foreach ((array) ($d['sections'] ?? []) as $s) {
            $parts[] = ($s['titre'] ?? '') . "\n" . ($s['texte'] ?? '');
        }
        foreach ((array) ($d['temps_forts'] ?? []) as $h) {
            $parts[] = (string) ($h['texte'] ?? '');
        }
        return implode("\n", $parts);
    }

    /**
     * Ressemblance : part des suites de 6 mots de la réécriture qui figurent telles quelles dans le
     * texte d'origine (noms propres et chiffres compris). 0 = rien de repris, 1 = copie.
     */
    public static function similarity(string $src, string $out): float
    {
        $grams = function (string $s): array {
            $w = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower(Names::ascii($s)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $g = [];
            for ($i = 0; $i + 6 <= count($w); $i++) {
                $g[implode(' ', array_slice($w, $i, 6))] = true;
            }
            return $g;
        };
        $o = $grams($out);
        if (!$o) {
            return 0.0;
        }
        $s = $grams($src);
        return count(array_intersect_key($o, $s)) / count($o);
    }

    // ------------------------------------------------------------------ création des fiches

    /** @return int numéro de la fiche créée (ou de la rubrique pour une saison : 0) */
    private static function create(array $it, ?array $t): int
    {
        return match ($it['kind']) {
            'match' => self::createMatch($it['data'], $t, array_merge([$it['source']], $it['extra'] ?? []), $it['key']),
            'match-page' => self::createMatchPage($it, $t),
            'season' => self::applySeason($it, (array) $t),
            'player' => self::createPlayer($it, (array) $t),
            default => self::createArticle($it, (array) $t),
        };
    }

    private static function createMatch(array $m, ?array $t, array $sources, string $key): int
    {
        if ($id = self::already($key)) {
            return $id;
        }
        $doc = Fiches::blank('match');
        $opp = $m['opponent'] !== '' ? $m['opponent'] : 'Adversaire inconnu';
        $comp = self::competition($m);
        $x = &$doc['match'];
        $x['date'] = $m['date'];
        $x['date_text'] = ucfirst(date_fr($m['date'], true));
        $x['season'] = FcsmStory::seasonFor($m['date']);
        [$x['competition'], $x['competition_label'], $x['competition_code']] = $comp;
        $x['round'] = $m['round'];
        $x['round_text'] = $m['round'];
        $x['home'] = ['name' => $m['home'] ? 'Sochaux' : $opp, 'level' => null];
        $x['away'] = ['name' => $m['home'] ? $opp : 'Sochaux', 'level' => null];
        $x['sochaux_home'] = $m['home'];
        if ($m['us'] !== null) {
            $h = $m['home'] ? $m['us'] : $m['them'];
            $a = $m['home'] ? $m['them'] : $m['us'];
            $x['score'] = ['home' => $h, 'away' => $a, 'extra' => null, 'aet' => false, 'pens' => null];
            $x['score_raw'] = "$h-$a";
            $x['score_line'] = $x['home']['name'] . ' / ' . $x['away']['name'] . " : $h-$a";
        }
        $x['result'] = $m['result'];
        $x['stadium'] = $m['stadium'] !== '' ? $m['stadium'] : '';
        $x['header_extra'] = array_values(array_filter([
            $m['stadium'] === '' && $m['place'] !== '' ? ['Lieu : ' . $m['place']] : null,
            $m['forfeit'] ? ['Décision sur tapis vert' . ($m['note'] ? ' : ' . $m['note'] : '')] : ($m['note'] && !preg_match('/^amical$/iu', $m['note']) ? ['À noter : ' . $m['note']] : null),
        ]));
        $goals = [];
        foreach ($m['scorers'] as $s) {
            $goals[Names::lineupLastName(self::lineupName($s['name']))] = $s;
        }
        $x['goals_text'] = implode(', ', array_map(fn ($s) => $s['name'] . ($s['minutes'] ? ' ' . implode("', ", $s['minutes']) . "'" : '') . ($s['goals'] > 1 && !$s['minutes'] ? " ($s[goals])" : ''), $m['scorers']));
        $x['goals'] = $m['scorers'] ? [['team' => 'Sochaux', 'scorers' => $x['goals_text']]] : [];
        foreach ($m['lineup'] as $p) {
            $name = self::lineupName($p['name']);
            $g = $goals[Names::lineupLastName($name)] ?? null;
            $gl = $g ? ($g['minutes'] ?: array_fill(0, $g['goals'], '')) : [];
            $x['lineup']['rows'][] = ['position' => $p['position'], 'name' => $name, 'number' => null, 'extra' => null, 'captain' => false,
                'goals' => array_values(array_filter($gl, fn ($v) => $v !== '')), 'own_goals' => [], 'goals_text' => $g ? ($g['minutes'] ? implode("', ", $g['minutes']) . "'" : ($g['goals'] > 1 ? $g['goals'] . ' buts' : '1 but')) : '',
                'sub_in' => null, 'sub_out' => null, 'sub_text' => $p['sub'] !== '' ? 'Remplacé par ' . $p['sub'] : '', 'yellow' => [], 'red' => [], 'cards_text' => '', 'person_id' => null];
        }
        unset($x);
        $doc['title'] = self::matchTitle($doc['match']);
        if ($t) {
            self::fillText($doc, $t);
            foreach ((array) ($t['temps_forts'] ?? []) as $hl) {
                if (trim((string) ($hl['texte'] ?? '')) !== '') {
                    $doc['match']['highlights'][] = ['minute' => preg_replace('/\D/', '', (string) ($hl['minute'] ?? '')), 'text' => trim((string) $hl['texte']), 'goal' => !empty($hl['but']), 'score' => null];
                }
            }
        } else {
            $doc['intro'] = self::plainIntro($m, $comp[1]);
        }
        return self::store($doc, $m['season'], $sources, $key);
    }

    /** Match raconté sur sa propre page : faits tirés par l'IA, contrôlés. */
    private static function createMatchPage(array $it, ?array $t): int
    {
        $f = (array) ($t['faits'] ?? []);
        $date = (string) ($f['date'] ?? '') ?: (string) ($it['date'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || (int) substr($date, 0, 4) > FcsmStory::LAST || (int) substr($date, 0, 4) < FcsmStory::FIRST) {
            throw new \RuntimeException('Date du match introuvable');
        }
        $home = trim((string) ($f['domicile'] ?? ''));
        $away = trim((string) ($f['exterieur'] ?? ''));
        $sh = (bool) preg_match('/sochaux/iu', $home);
        $opp = $sh ? $away : $home;
        $bh = is_numeric($f['buts_domicile'] ?? null) ? (int) $f['buts_domicile'] : null;
        $ba = is_numeric($f['buts_exterieur'] ?? null) ? (int) $f['buts_exterieur'] : null;
        $us = $bh === null ? null : ($sh ? $bh : $ba);
        $them = $ba === null ? null : ($sh ? $ba : $bh);
        $lineup = [];
        foreach (array_values((array) ($f['composition_sochaux'] ?? [])) as $k => $n) {
            $lineup[] = ['name' => (string) $n, 'position' => $k === 0 ? 'G' : ($k <= 2 ? 'D' : ($k <= 5 ? 'M' : 'A')), 'sub' => ''];
        }
        $m = ['date' => $date, 'season' => FcsmStory::seasonFor($date), 'place' => '', 'home' => $sh, 'round' => '', 'stadium' => (string) ($f['stade'] ?? ''),
            'result' => $us === null || $them === null ? null : ($us > $them ? 'V' : ($us < $them ? 'D' : 'N')), 'us' => $us, 'them' => $them,
            'opponent' => $opp !== '' ? $opp : 'Adversaire inconnu', 'note' => '', 'forfeit' => false, 'competition' => (string) ($f['competition'] ?? ''), 'club' => '',
            'lineup' => count($lineup) >= 7 ? $lineup : [], 'scorers' => FcsmStory::scorers((string) ($f['buteurs_sochaux'] ?? '')), 'amical' => (bool) preg_match('/amical/iu', (string) ($f['competition'] ?? '')), 'report' => ''];
        $id = self::createMatch($m, $t, [$it['source']], $it['key']);
        if (is_numeric($f['spectateurs'] ?? null) && ($doc = Fiches::fresh($id))) {
            $doc['match']['spectators'] = (int) $f['spectateurs'];
            $doc['match']['spectators_text'] = number_format((int) $f['spectateurs'], 0, ',', "\u{202F}") . ' spectateurs';
            Fiches::save($doc, self::AUTHOR, 'Reprise FCSM Story : affluence');
        }
        return $id;
    }

    /** Récit de saison : texte de la rubrique de la saison (complété, jamais remplacé s'il existe déjà). */
    private static function applySeason(array $it, array $t): int
    {
        $slug = (string) $it['season'];
        self::ensureSeason($slug);
        $cats = Categories::all();
        if (!isset($cats[$slug])) {
            throw new \RuntimeException("Rubrique de la saison $slug absente");
        }
        if (trim(strip_tags((string) ($cats[$slug]['description'] ?? ''))) !== '') {
            return 0; // la saison a déjà un texte au musée : pas de conflit, on n'y touche pas
        }
        $html = '<p>' . e(trim((string) ($t['intro'] ?? ''))) . '</p>';
        foreach ((array) ($t['sections'] ?? []) as $s) {
            $html .= '<h2>' . e((string) ($s['titre'] ?? '')) . '</h2>' . self::paras((string) ($s['texte'] ?? ''));
        }
        $html .= self::sourcesHtml([$it['source']]);
        $cats[$slug]['description'] = $html;
        Categories::save($cats, self::AUTHOR);
        Categories::forget();
        return 0;
    }

    private static function createPlayer(array $it, array $t): int
    {
        if ($id = self::already($it['key'])) {
            return $id;
        }
        $doc = Fiches::blank('personne');
        $p = &$doc['personne'];
        $p['first_name'] = trim((string) ($t['prenom'] ?? ''));
        $p['last_name'] = trim((string) ($t['nom'] ?? '')) ?: $it['label'];
        $p['display_name'] = trim($p['first_name'] . ' ' . mb_convert_case($p['last_name'], MB_CASE_TITLE));
        $p['subtitle'] = trim((string) ($t['poste'] ?? ''));
        $p['nationality'] = trim((string) ($t['nationalite'] ?? ''));
        unset($p);
        $doc['title'] = $doc['personne']['display_name'] ?: $it['label'];
        self::fillText($doc, $t);
        return self::store($doc, null, [$it['source']], $it['key']);
    }

    private static function createArticle(array $it, array $t): int
    {
        if ($id = self::already($it['key'])) {
            return $id;
        }
        $doc = Fiches::blank('article');
        $doc['title'] = trim((string) ($t['titre'] ?? '')) ?: $it['label'];
        self::fillText($doc, $t);
        $years = FcsmStory::years($it['label'] . ' ' . $it['source']);
        $season = $years ? FcsmStory::seasonFor(sprintf('%04d-09-01', $years[0])) : null;
        return self::store($doc, $season && Categories::get($season) ? $season : null, [$it['source']], $it['key']);
    }

    /** Intro et sections de la réponse dans la fiche, plus « Sources ». */
    private static function fillText(array &$doc, array $t): void
    {
        $doc['intro'] = trim((string) ($t['intro'] ?? ''));
        foreach ((array) ($t['sections'] ?? []) as $s) {
            $title = trim((string) ($s['titre'] ?? ''));
            $html = self::paras((string) ($s['texte'] ?? ''));
            if ($html !== '') {
                $doc['sections'][] = ['title' => $title, 'html' => $html];
            }
        }
        $c = (array) ($t['chiffre'] ?? []);
        if (trim((string) ($c['nombre'] ?? '')) !== '' && trim((string) ($c['texte'] ?? '')) !== '') {
            $doc['key_figure'] = ['number' => trim((string) $c['nombre']), 'text' => trim((string) $c['texte'])];
        }
    }

    /** Enregistre la fiche publiée, rangée dans sa saison et sa décennie, avec ses sources. */
    private static function store(array $doc, ?string $season, array $sources, string $key): int
    {
        $doc['status'] = 'publie';
        $cats = [];
        if ($season && self::ensureSeason($season) && ($c = Categories::get($season))) {
            $cats[] = $season;
            if (!empty($c['parent'])) {
                $cats[] = $c['parent'];
                $root = Categories::root($season);
                if ($root !== $c['parent']) {
                    $cats[] = $root;
                }
            }
        }
        $doc['categories'] = array_values(array_unique($cats));
        $doc['sections'][] = ['title' => 'Sources', 'html' => self::sourcesHtml($sources)];
        $doc['legacy'] = ['fcsmstory' => $key, 'sources' => array_values(array_unique($sources))];
        $doc['path'] = Paths::unique(Paths::suggest($doc), -1);
        $doc['slug'] = basename(rtrim($doc['path'], '/'));
        $saved = Fiches::save($doc, self::AUTHOR, 'Reprise des années ' . ($season ? substr($season, 0, 4) : '1928-1969') . ' (FCSM Story)');
        return (int) $saved['id'];
    }

    /**
     * Rubrique d'une saison (« 1946-1947 »), créée si elle manque, sous sa décennie (année de début),
     * au même format que les autres. @return bool rubrique disponible
     */
    public static function ensureSeason(string $season): bool
    {
        if (!preg_match('/^(\d{4})-(\d{4})$/', $season, $m) || (int) $m[2] !== (int) $m[1] + 1) {
            return false;
        }
        if (Categories::get($season)) {
            return true;
        }
        $decade = 'annees-' . substr($m[1], 2, 1) . '0-fc-sochaux-retro-fcsm';
        $cats = Categories::all();
        if (!isset($cats[$decade])) {
            return false;
        }
        $path = '/matchs/' . $season . '/';
        if (Index::byPath($path)) {
            return false;
        }
        $cats[$season] = ['id' => max(array_map(fn ($c) => (int) ($c['id'] ?? 0), $cats)) + 1, 'slug' => $season, 'name' => $season, 'label' => null, 'position' => null,
            'description' => '', 'parent' => $decade, 'path' => $path, 'season' => $season, 'technical' => false, 'old_path' => null, 'order' => [], 'wp_count' => 0];
        Categories::save($cats, self::AUTHOR);
        Categories::forget();
        return true;
    }

    /** Fiche déjà créée par une reprise précédente (aucun doublon si l'import est relancé). */
    private static function already(string $key): ?int
    {
        foreach (self::state()['items'] as $it) {
            if ($it['key'] === $key && !empty($it['fiche']) && ($it['status'] ?? '') === 'fait' && Fiches::get((int) $it['fiche'])) {
                return (int) $it['fiche'];
            }
        }
        return null;
    }

    private static function sourcesHtml(array $links): string
    {
        $a = implode(', ', array_map(fn ($l) => '<a href="' . e($l) . '" rel="noopener" target="_blank">' . e(preg_replace('#^https?://#', '', rtrim($l, '/'))) . '</a>', array_values(array_unique($links))));
        return '<p>Texte rédigé par Sochaux Rétro d’après les recherches de <b>FCSM Story</b> (' . $a . ') et la presse de l’époque.</p>';
    }

    private static function paras(string $t): string
    {
        $ps = array_filter(array_map('trim', preg_split('/\n\s*\n|\n/u', trim($t)) ?: []));
        return implode('', array_map(fn ($p) => '<p>' . e($p) . '</p>', $ps));
    }

    // ------------------------------------------------------------------ outils

    /** [compétition, libellé, code] au format du musée. */
    private static function competition(array $m): array
    {
        $c = trim($m['competition']);
        $l = mb_strtolower(Names::ascii($c . ' ' . $m['note']));
        return match (true) {
            str_contains($l, 'coupe de france') || preg_match('/\bcdf\b/', $l) === 1 => ['Coupe de France', 'Coupe de France', 'CDF'],
            str_contains($l, 'amical') || $c === '' && $m['amical'] => ['Amical', 'Amical', 'Amical'],
            str_contains($l, 'championnat') => ['Championnat', $c, ''],
            $c === '' => ['Match', 'Match', ''],
            default => ['Coupe', $c, ''],
        };
    }

    /** « J Laurent » → « LAURENT J », « Leslie Miller » → « MILLER Leslie », « De James » → « DE JAMES ». */
    public static function lineupName(string $n): string
    {
        $n = trim(preg_replace('/\s+/u', ' ', str_replace('.', ' ', $n)) ?? $n);
        $w = explode(' ', $n);
        $particles = ['de', 'van', 'von', 'le', 'la', 'di', 'da', 'du', 'del', 'des', 'mac', 'mc'];
        if (count($w) === 1) {
            return mb_strtoupper($n);
        }
        if (mb_strlen($w[0]) === 1) {
            return mb_strtoupper(implode(' ', array_slice($w, 1))) . ' ' . $w[0];
        }
        if (in_array(mb_strtolower($w[0]), $particles, true)) {
            return mb_strtoupper($n);
        }
        // Chiffre romain (« Mykowski I ») : partie du nom.
        if (count($w) === 2 && preg_match('/^(I|II|III|IV)$/', $w[1])) {
            return mb_strtoupper($w[0]) . ' ' . $w[1];
        }
        return mb_strtoupper((string) array_pop($w)) . ' ' . implode(' ', $w);
    }

    /** Titre au format du musée : « Amical – Stade Français / Sochaux – 08/09/1929 – 3-6 ». */
    private static function matchTitle(array $x): string
    {
        $head = match ($x['competition']) {
            'Amical' => 'Amical',
            'Coupe de France' => 'CDF' . ($x['round'] ? ' ' . $x['round'] : ''),
            'Championnat' => 'Championnat',
            default => $x['competition_label'] ?: 'Match',
        };
        return $head . ' – ' . $x['home']['name'] . ' / ' . $x['away']['name'] . ' – ' . date('d/m/Y', strtotime((string) $x['date']))
            . ($x['score_raw'] !== '' ? ' – ' . $x['score_raw'] : '');
    }

    private static function matchLabel(array $m): string
    {
        return date('d/m/Y', strtotime($m['date'])) . ' · ' . ($m['home'] ? 'Sochaux – ' . $m['opponent'] : $m['opponent'] . ' – Sochaux')
            . ($m['us'] !== null ? ' · ' . ($m['home'] ? "$m[us]-$m[them]" : "$m[them]-$m[us]") : '');
    }

    /** Accroche sans IA (match sans récit) : les faits, en une phrase. */
    private static function plainIntro(array $m, string $comp): string
    {
        $where = $m['place'] !== '' ? ' à ' . $m['place'] : '';
        $res = match ($m['result']) {
            'V' => $m['us'] !== null ? "Victoire sochalienne $m[us] à $m[them]" : 'Victoire sochalienne',
            'D' => $m['us'] !== null ? "Défaite $m[us] à $m[them]" : 'Défaite',
            'N' => $m['us'] !== null ? "Match nul $m[us] partout" : 'Match nul',
            default => 'Rencontre',
        };
        return $res . ' face à ' . $m['opponent'] . $where . ', le ' . date_fr($m['date']) . ($comp && $comp !== 'Match' ? ' (' . mb_strtolower($comp) . ')' : '') . '.';
    }

    /** « Miguel Angel Michel LAURI » → « Miguel Angel Michel Lauri ». */
    private static function personName(string $t): string
    {
        return trim(preg_replace_callback('/\b(\p{Lu}[\p{Lu}\'’-]+)\b/u', fn ($m) => mb_convert_case($m[1], MB_CASE_TITLE), $t) ?? $t);
    }

    /**
     * Fiche existante d'une même personne : même nom de famille et au moins un prénom commun
     * (« Miguel Angel Michel Lauri » = « Michel Lauri »). @param list<array{0:int,1:list<string>}> $people
     */
    public static function samePerson(string $name, array $people): ?int
    {
        $t = Names::tokens($name);
        if (count($t) < 2) {
            return null;
        }
        $last = end($t);
        $first = array_slice($t, 0, -1);
        foreach ($people as [$id, $pt]) {
            if (count($pt) >= 2 && end($pt) === $last && array_intersect($first, array_slice($pt, 0, -1))) {
                return $id;
            }
        }
        return null;
    }

    /** Noms des compositions qu'aucune fiche du musée ne porte (pour les historiens). */
    private static function unlinkedPlayers(array $matches): array
    {
        $known = [];
        foreach (Index::all() as $s) {
            if (($s['type'] ?? '') === 'personne') {
                $known[Names::lineupLastName(mb_strtoupper(preg_replace('/\(.*?\)/u', '', (string) $s['title']) ?? ''))] = true;
                $parts = preg_split('/\s+/u', trim(preg_replace('/\(.*?\)/u', '', (string) $s['title']) ?? '')) ?: [];
                for ($n = 1; $n <= min(3, count($parts) - 1); $n++) {
                    $known[implode(' ', Names::tokens(implode(' ', array_slice($parts, -$n))))] = true;
                }
            }
        }
        $out = [];
        foreach ($matches as $m) {
            foreach ($m['lineup'] as $p) {
                $last = Names::lineupLastName(self::lineupName($p['name']));
                if ($last !== '' && !isset($known[$last])) {
                    $out[$p['name']] = ($out[$p['name']] ?? 0) + 1;
                }
            }
        }
        arsort($out);
        return $out;
    }
}
