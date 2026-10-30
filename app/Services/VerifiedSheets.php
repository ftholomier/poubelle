<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Club;
use App\Data\Fiches;
use App\Data\Index;

/**
 * Feuilles de match vérifiées (app/Resources/import/feuilles-AAAA-AAAA.json) : composition complète
 * (titulaires, entrées et sorties, buts, cartons, brassard, remplaçants restés sur le banc,
 * entraîneur), buteurs des deux équipes, arbitre, affluence, établies en recoupant plusieurs sources.
 * Appliquées une seule fois par la tâche « corrections » (DataFixes) :
 *
 * – match absent du musée : fiche créée, publiée et rangée dans sa saison ;
 * – fiche dont la composition est vide (ajoutée) ou provisoire (la même dans plusieurs matchs de la
 *   saison, recopiée d'un modèle : remplacée) : composition et buteurs pris dans la feuille vérifiée ;
 * – fiche dont la composition a été saisie : rien n'est écrasé, la feuille vérifiée part dans
 *   Trouvailles (« Feuille de match ») si elle diffère ;
 * – arbitre, affluence, stade vides et score à la mi-temps (en-tête) : complétés ; un arbitre noté par son
 *   initiale (« A. Petit ») reçoit son prénom ; tout autre écart (score, date, domicile, arbitre,
 *   affluence) part dans Trouvailles, où l'historien valide ou écarte.
 *
 * La note de la feuille (« note », facultative : d'où vient quoi, ce qui reste inconnu) s'ajoute à la
 * section des sources. Une feuille marquée « existing » ne complète qu'une fiche existante : si le
 * match n'est pas retrouvé, rien n'est créé (compté « introuvable »). Chaque fiche modifiée garde sa version précédente (Historique).
 */
final class VerifiedSheets
{
    private const AUTHOR = ['name' => 'Feuilles de match vérifiées'];
    private const SECTION = 'Sources de la feuille de match';

    /** @return array{message:string, ids:list<int>, created:list<int>, proposals:int} */
    public static function import(string $file): array
    {
        $data = json_decode((string) @file_get_contents($file), true);
        $sheets = (array) ($data['matchs'] ?? []);
        if (!$sheets) {
            return ['message' => 'feuilles vérifiées : fichier ' . basename($file) . ' introuvable ou vide', 'ids' => [], 'created' => [], 'proposals' => 0];
        }
        \App\Data\Categories::ensureSeasons();
        $byDate = FeuillesImport::byDate();
        $models = self::models((string) ($data['saison'] ?? ''));
        $ids = $created = [];
        $replaced = $added = $completed = $props = $missing = 0;
        Fiches::batch(function () use ($sheets, $byDate, $models, &$ids, &$created, &$replaced, &$added, &$completed, &$props, &$missing) {
            $used = [];
            foreach ($sheets as $s) {
                $id = FeuillesImport::nearest($s, $byDate, $used);
                if ($id === null && !empty($s['existing'])) {
                    $missing++; // match connu du musée mais pas retrouvé : jamais de fiche en double
                    continue;
                }
                if ($id === null) {
                    $id = self::create($s);
                    $created[] = $ids[] = $id;
                    $used[$id] = true;
                    continue;
                }
                $used[$id] = true;
                [$changed, $p, $whole, $empty] = self::update($id, $s, $models);
                $props += $p;
                if ($changed) {
                    $ids[] = $id;
                    $whole ? ($empty ? $added++ : $replaced++) : $completed++;
                }
            }
        });
        $label = (string) ($data['saison'] ?? '');
        return ['message' => 'feuilles de match ' . $label . ' : ' . count($created) . ' match(s) créé(s), ' . ($added ? $added . ' composition(s) ajoutée(s), ' : '') . $replaced . ' composition(s) provisoire(s) remplacée(s), ' . $completed . ' fiche(s) complétée(s), ' . $props . ' proposition(s) dans Trouvailles' . ($missing ? ', ' . $missing . ' fiche(s) introuvable(s) (rien créé)' : ''),
            'ids' => $ids, 'created' => $created, 'proposals' => $props];
    }

    /** Match absent : créé comme une feuille de l'association, puis rempli avec la feuille vérifiée. */
    private static function create(array $s): int
    {
        $id = FeuillesImport::create(array_intersect_key($s, array_flip(['kind', 'date', 'home', 'away', 'sochaux_home', 'score_home', 'score_away', 'half_time', 'score_note', 'place', 'stadium', 'spectators', 'referee', 'competition', 'round']))
            + ['key' => 'verifiee-' . $s['date'], 'file' => 'feuille vérifiée', 'coach' => '', 'lineup' => [], 'scorers' => [], 'goals_by_team' => [], 'goals_text' => '']);
        $doc = Fiches::fresh($id);
        self::fill($doc, $s);
        $doc['sections'] = array_values(array_filter($doc['sections'], fn ($x) => ($x['title'] ?? '') !== 'Sources'));
        self::sources($doc, $s);
        $doc['legacy'] = ['feuille_verifiee' => $s['date']];
        Fiches::save($doc, self::AUTHOR, 'Match créé d’après la feuille vérifiée (' . implode(', ', (array) $s['sources']) . ')');
        return $id;
    }

    /** Fiche existante : [modifiée, propositions, composition remplacée ou ajoutée, fiche sans composition]. */
    private static function update(int $id, array $s, array $models): array
    {
        $doc = Fiches::fresh($id);
        if (!$doc || ($doc['type'] ?? '') !== 'match') {
            return [false, 0, false, false];
        }
        $before = $doc;
        $m = &$doc['match'];
        $items = [];
        $propose = function (string $field, string $value, string $current, string $type = 'divergence') use (&$items, $s) {
            $items[] = ['field' => $field, 'value' => $value, 'current' => $current, 'type' => $type, 'origin' => FeuillesImport::ORIGIN,
                'sources' => [['label' => 'Feuille de match vérifiée (' . implode(', ', (array) $s['sources']) . ')', 'url' => '', 'snippet' => '', 'kind' => FeuillesImport::ORIGIN]]];
        };
        $rows = (array) ($m['lineup']['rows'] ?? []);
        $empty = !$rows;
        $whole = $empty || isset($models[self::fingerprint($rows)]);
        unset($m);
        if ($whole) {
            self::fill($doc, $s);
        } else {
            $ours = Compos::ours($doc['match']);
            if (Compos::diff($ours, self::players($s))) {
                $propose('feuille', self::lines($s), Compos::lines($ours, self::coachOf($doc['match'])));
            }
        }
        $m = &$doc['match'];
        // Arbitre : vide ou inconnu (« xx ») complété ; initiale (« A. Petit ») remplacée par le nom complet.
        $ref = trim((string) ($m['referee'] ?? ''));
        $want = trim((string) ($s['referee'] ?? ''));
        if ($want !== '' && !Trouvailles::same($ref, $want)) {
            if ($ref === '' || preg_match('/^(m\.?\s*)?x+$/iu', $ref) || self::lastWord($ref) === self::lastWord($want)) {
                $m['referee'] = $want;
            } else {
                $propose('arbitre', $want, $ref);
            }
        }
        if (isset($s['spectators']) && $s['spectators'] !== null) {
            $now = $m['spectators'] ?? null;
            if ($now === null || $now === '') {
                $m['spectators'] = (int) $s['spectators'];
                $m['spectators_text'] = (int) $s['spectators'] ? number_format((int) $s['spectators'], 0, ',', "\u{202F}") . ' spectateurs' : 'Huis clos';
            } elseif (abs((int) $now - (int) $s['spectators']) > max(50, 0.02 * (int) $now)) {
                $propose('affluence', (string) (int) $s['spectators'], (string) (int) $now);
            }
        }
        if (trim((string) ($m['stadium'] ?? '')) === '' && trim((string) ($s['stadium'] ?? '')) !== '') {
            $m['stadium'] = trim((string) $s['stadium']);
        }
        self::halfTime($m, $s);
        $sh = (bool) ($m['sochaux_home'] ?? true);
        $us = $s['sochaux_home'] ? [(int) $s['score_home'], (int) $s['score_away']] : [(int) $s['score_away'], (int) $s['score_home']];
        if (isset($m['score']['home'])) {
            $cur = $sh ? [(int) $m['score']['home'], (int) $m['score']['away']] : [(int) $m['score']['away'], (int) $m['score']['home']];
            if ($cur !== $us) {
                $propose('score', $us[0] . '-' . $us[1], $cur[0] . '-' . $cur[1]);
            }
        }
        if (($m['date'] ?? '') !== $s['date']) {
            $propose('date', date('d/m/Y', strtotime((string) $s['date'])), !empty($m['date']) ? date('d/m/Y', strtotime((string) $m['date'])) : '');
        }
        if ($sh !== (bool) $s['sochaux_home'] && empty($s['neutral'])) {
            $propose('info', club('Selon les sources de la feuille vérifiée, {team} jouait ') . ($s['sochaux_home'] ? 'à domicile' : 'à l’extérieur') . ' (' . $s['home'] . ' – ' . $s['away'] . (!empty($s['stadium']) ? ', ' . $s['stadium'] : '') . ').', '', 'info');
        }
        unset($m);
        $props = $items ? Trouvailles::propose($id, $items) : 0;
        if ($doc == $before) {
            return [false, $props, false, $empty];
        }
        self::sources($doc, $s);
        Fiches::save($doc, self::AUTHOR, $whole ? ($empty ? 'Composition ajoutée d’après la feuille de match vérifiée' : 'Composition provisoire remplacée par la feuille de match vérifiée') : 'Feuille de match vérifiée : informations complétées');
        return [true, $props, $whole, $empty];
    }

    /** Composition, buteurs et mentions de la feuille vérifiée écrits dans la fiche. */
    private static function fill(array &$doc, array $s): void
    {
        $m = &$doc['match'];
        Compos::apply($m, self::lines($s));
        // Fiche qui n'avait pas de tableau de composition : titre et colonnes habituels.
        $m['lineup']['title'] ??= 'Composition ' . Club::team();
        $m['lineup']['headers'] ??= ['Postes', 'Nom et prénom', 'Buts', 'Remp.', 'Cartons'];
        // Entré en jeu à une minute inconnue (amicaux) : une apparition, temps de jeu inconnu.
        $entered = array_map(fn ($p) => (string) $p['name'], array_filter((array) $s['players'], fn ($p) => !empty($p['entered'])));
        foreach ($m['lineup']['rows'] as &$r) {
            if ($r['position'] === 'R' && in_array($r['name'], $entered, true)) {
                $r['sub_text'] = 'Entré en jeu';
            }
        }
        unset($r);
        $opp = $s['sochaux_home'] ? (string) $s['away'] : (string) $s['home'];
        // Nom de l'adversaire tel que la fiche l'écrit (« Nîmes »), sinon celui de la feuille.
        $oppName = (string) (($m['sochaux_home'] ?? true) ? ($m['away']['name'] ?? '') : ($m['home']['name'] ?? '')) ?: $opp;
        $goals = [];
        if (trim((string) $s['goals_sochaux']) !== '') {
            $goals[] = ['team' => Club::team(), 'scorers' => (string) $s['goals_sochaux']];
        }
        if (trim((string) $s['goals_opponent']) !== '') {
            $goals[] = ['team' => $oppName, 'scorers' => (string) $s['goals_opponent']];
        }
        $m['goals'] = $goals;
        $m['goals_text'] = implode(' ; ', array_map(fn ($g) => $g['scorers'] . ' pour ' . $g['team'], $goals));
        self::halfTime($m, $s);
        $note = trim((string) ($s['score_note'] ?? ''));
        if ($note !== '' && !preg_grep('/' . preg_quote($note, '/') . '/u', (array) ($m['header_extra'] ?? []))) {
            $m['header_extra'][] = 'À noter : ' . $note;
        }
        unset($m);
    }

    /** Score à la mi-temps dans les mentions d'en-tête, s'il n'y est pas déjà. */
    private static function halfTime(array &$m, array $s): void
    {
        if (!empty($s['half_time']) && !preg_grep('/^Mi-temps\b/u', (array) ($m['header_extra'] ?? []))) {
            $m['header_extra'][] = 'Mi-temps : ' . $s['half_time'];
        }
    }

    /** Lignes de composition au format de Compos (« D · NOM Prénom · entré 63' »), entraîneurs compris. */
    private static function lines(array $s): string
    {
        $out = Compos::lines(self::players($s));
        foreach ((array) ($s['coaches'] ?? []) as $c) {
            $out .= "\nE · " . $c;
        }
        return $out;
    }

    /** @return list<array> joueurs au format commun de Compos */
    private static function players(array $s): array
    {
        return array_map(fn ($p) => ['name' => (string) $p['name'], 'pos' => (string) $p['pos'], 'in' => Compos::min($p['in'] ?? null), 'out' => Compos::min($p['out'] ?? null),
            'goals' => array_map([Compos::class, 'min'], (array) ($p['goals'] ?? [])), 'yellow' => array_map([Compos::class, 'min'], (array) ($p['yellow'] ?? [])),
            'red' => array_map([Compos::class, 'min'], (array) ($p['red'] ?? [])), 'cap' => !empty($p['cap']), 'num' => null, 'key' => Compos::key((string) $p['name'])], (array) $s['players']);
    }

    /** Section « Sources de la feuille de match » (remplacée si elle existe déjà). */
    private static function sources(array &$doc, array $s): void
    {
        $html = '<p>Feuille de match établie en recoupant ' . e(implode(', ', (array) $s['sources'])) . '.'
            . (($s['confidence'] ?? 'haute') !== 'haute' ? ' Composition reconstituée en partie (titulaires et remplaçants moins sûrs, minutes inconnues) : à confirmer.' : '') . '</p>'
            . (trim((string) ($s['note'] ?? '')) !== '' ? '<p>' . e(trim((string) $s['note'])) . '</p>' : '');
        foreach ($doc['sections'] as &$sec) {
            if (($sec['title'] ?? '') === self::SECTION) {
                $sec['html'] = $html;
                return;
            }
        }
        unset($sec);
        $doc['sections'][] = ['title' => self::SECTION, 'html' => $html];
    }

    /**
     * Compositions recopiées d'un modèle : la même (postes, noms, remplacements, cartons) dans au
     * moins trois matchs de la saison. @return array<string,true>
     */
    private static function models(string $season): array
    {
        $count = [];
        foreach (Index::all() as $e) {
            if (($e['type'] ?? '') !== 'match' || ($e['m']['season'] ?? null) !== $season || !($doc = Fiches::get((int) $e['id']))) {
                continue;
            }
            $rows = (array) ($doc['match']['lineup']['rows'] ?? []);
            if ($rows) {
                $f = self::fingerprint($rows);
                $count[$f] = ($count[$f] ?? 0) + 1;
            }
        }
        return array_fill_keys(array_keys(array_filter($count, fn ($n) => $n >= 3)), true);
    }

    private static function fingerprint(array $rows): string
    {
        return md5((string) json_encode(array_map(fn ($r) => [$r['position'] ?? '', $r['name'] ?? '', $r['sub_text'] ?? '', $r['cards_text'] ?? ''], $rows), JSON_UNESCAPED_UNICODE));
    }

    private static function coachOf(array $m): string
    {
        foreach ((array) ($m['lineup']['rows'] ?? []) as $r) {
            if (($r['position'] ?? '') === 'E') {
                return (string) $r['name'];
            }
        }
        return '';
    }

    private static function lastWord(string $name): string
    {
        $w = preg_split('/[\s.]+/u', trim($name)) ?: [];
        return mb_strtolower(\App\Data\Names::ascii((string) end($w)));
    }
}
