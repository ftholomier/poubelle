<?php
declare(strict_types=1);

namespace App\Services;

use App\Data\Fiches;
use App\Data\Names;
use App\Data\PersonResolver;

/**
 * Compositions reliées aux fiches des joueurs (tâche planifiée « liens-compositions ») : chaque ligne
 * dont le nom désigne sûrement une fiche personne reçoit le numéro de cette fiche (person_id, que les
 * statistiques lisent en premier) et, s'il manque, le prénom de la fiche : « BERNARDET » devient
 * « BERNARDET Steve », « TELLECHÉA J » devient « TELLECHÉA Jacques ». Un nom déjà accompagné de son
 * prénom n'est pas réécrit (la composition peut être plus juste que la fiche : accents, nom composé).
 *
 * Sûrement : même rapprochement que les statistiques (PersonResolver), limité au nom complet, au nom
 * de famille seul plausible à cette date (y compris écrit autrement, « NDIAYE » / « N'Diaye ») et au
 * même nom écrit autrement ; chaque prénom ou initiale de la composition doit en plus correspondre à
 * la fiche (une faute de frappe dans un prénom est tolérée : « Silvester » / « Sylvester »). Les
 * rapprochements approchés (nom incomplet, faute dans le nom) ne sont jamais enregistrés : ils restent
 * proposés dans Qualité. Un lien déjà posé (choisi dans la liste du back-office) est gardé. Relancer
 * ne refait rien.
 */
final class LineupLinks
{
    private const AUTHOR = ['name' => 'Liens des compositions'];
    private const NOTE = PersonResolver::NOTE;

    /**
     * Relie les compositions des fiches de match (toutes, ou celles de $only), dans le temps donné
     * (secondes) ; compte rendu.
     * @param list<int>|null $only
     */
    public static function run(int $budget = 60, ?array $only = null): string
    {
        $end = microtime(true) + $budget;
        [$persons, $matches] = self::load();
        if ($only !== null) {
            $matches = array_values(array_intersect($matches, $only));
        }
        if (!$persons || !$matches) {
            return 'aucune composition à relier';
        }
        $resolver = new PersonResolver($persons);
        $done = $rows = 0;
        $left = count($matches);
        Fiches::batch(function () use ($matches, $persons, $resolver, $end, &$done, &$rows, &$left) {
            foreach ($matches as $id) {
                if (microtime(true) > $end) {
                    break;
                }
                $left--;
                $doc = Fiches::fresh($id);
                if (!$doc || ($doc['type'] ?? '') !== 'match') {
                    continue;
                }
                [$new, $n] = self::apply($doc, $resolver, $persons);
                if ($n > 0) {
                    Fiches::save($new, self::AUTHOR, $n . ' joueur(s) de la composition relié(s) à leur fiche');
                    $done++;
                    $rows += $n;
                }
            }
        });
        $rest = $left > 0 ? ', ' . $left . ' fiche(s) à voir au prochain passage' : '';
        return $done ? $rows . ' ligne(s) de composition reliée(s) dans ' . $done . ' fiche(s)' . $rest : 'rien de nouveau' . $rest;
    }

    /**
     * Compo d'une fiche match reliée : [fiche, nombre de lignes changées].
     * @param array<int,array> $persons fiches personnes (celles du résolveur)
     */
    public static function apply(array $doc, PersonResolver $resolver, array $persons): array
    {
        $date = (string) ($doc['match']['date'] ?? '') ?: null;
        $n = 0;
        foreach ((array) ($doc['match']['lineup']['rows'] ?? []) as $i => $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $pid = (int) ($row['person_id'] ?? 0);
            if (!$pid || !isset($persons[$pid])) {
                $coach = strtoupper((string) ($row['position'] ?? '')) === 'E';
                $bare = trim((string) preg_replace(self::NOTE, '', $name));
                $pid = (int) ($resolver->resolve($bare, $date, $coach, true) ?? $resolver->resolveSurname($bare, $date, $coach));
                if (!$pid || !PersonResolver::agrees($name, $persons[$pid])) {
                    // Homonymes de la même époque (« BOUGER B » / « BOUGER Y ») : départagés par le
                    // prénom ou l'initiale de la composition, s'il y en a un.
                    $pid = 0;
                    if (self::hasFirstName($name)) {
                        $ok = array_values(array_filter($resolver->surnameCandidates($bare, $date, $coach), fn ($c) => PersonResolver::agrees($name, $persons[$c])));
                        $pid = (int) $resolver->only($ok, $date);
                    }
                    if (!$pid) {
                        continue;
                    }
                }
            }
            $new = ['person_id' => $pid] + $row;
            if (($full = self::withFirstName($name, $persons[$pid])) !== null) {
                $new['name'] = $full;
            }
            if ($new != $row) {
                $doc['match']['lineup']['rows'][$i] = array_merge($row, $new);
                $n++;
            }
        }
        return [$doc, $n];
    }

    /**
     * Le nom de la composition avec le prénom de la fiche, quand il n'a qu'un nom de famille (en
     * majuscules) et au plus des initiales : « TELLECHÉA J » → « TELLECHÉA Jacques ». Null s'il a déjà
     * un prénom, si la fiche n'en a pas de connu (« ? », « xx », initiale) ou s'il est déjà écrit.
     */
    public static function withFirstName(string $name, array $person): ?string
    {
        $first = trim((string) ($person['personne']['first_name'] ?? ''));
        if ($first === '' || preg_match('/^(\?+|x+|\p{L}\.?)$/iu', $first)) {
            return null;
        }
        preg_match(self::NOTE, $name, $note);
        $bare = trim((string) preg_replace(self::NOTE, '', $name));
        $surname = [];
        foreach (preg_split('/\s+/u', $bare) ?: [] as $w) {
            if (mb_strlen($w) > 1 && mb_strtoupper($w) === $w && preg_match('/\p{L}/u', $w)) {
                $surname[] = $w;
            } elseif (!preg_match('/^\p{L}\.?$/u', $w)) {
                return null; // déjà un prénom (ou autre chose qu'une initiale)
            }
        }
        if (!$surname || array_intersect(Names::tokens($first), Names::tokens(implode(' ', $surname)))) {
            return null;
        }
        return implode(' ', $surname) . ' ' . $first . (isset($note[0]) ? ' ' . trim($note[0]) : '');
    }

    /** La composition donne un prénom ou une initiale en plus du nom de famille (en majuscules). */
    private static function hasFirstName(string $name): bool
    {
        foreach (preg_split('/\s+/u', trim((string) preg_replace(self::NOTE, '', $name))) ?: [] as $w) {
            if (!(mb_strlen($w) > 1 && mb_strtoupper($w) === $w) && preg_match('/\p{L}/u', $w)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Fiches personnes (champs utiles au rapprochement) et numéros des fiches de match qui ont une
     * composition, hors corbeille. @return array{0: array<int,array>, 1: list<int>}
     */
    private static function load(): array
    {
        $persons = $matches = [];
        foreach (Fiches::all() as $id => $doc) {
            if (($doc['status'] ?? '') === 'corbeille') {
                continue;
            }
            if (($doc['type'] ?? '') === 'personne') {
                $pp = (array) ($doc['personne'] ?? []);
                $persons[(int) $id] = ['title' => (string) ($doc['title'] ?? ''), 'personne' => array_intersect_key($pp, array_flip(['first_name', 'last_name', 'display_name', 'nickname', 'aliases', 'birth', 'arrival', 'departure', 'arrival_coach', 'departure_coach', 'trial'])) + ['display_name' => '']];
            } elseif (($doc['type'] ?? '') === 'match' && !empty($doc['match']['lineup']['rows'])) {
                $matches[] = (int) $id;
            }
        }
        return [$persons, $matches];
    }
}
