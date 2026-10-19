<?php
declare(strict_types=1);

namespace App\Data;

/**
 * Rapprochement d'un nom de composition (« WEISSBECK Gaëtan », « BERNARDET », « TELLECHÉA J »)
 * avec une fiche personne : celui des statistiques (Derived) et des liens enregistrés dans les
 * compositions (LineupLinks), pour que les deux suivent exactement les mêmes règles.
 *
 * Ordre : nom complet (toutes graphies de la fiche, surnom, autres graphies), nom de famille seul
 * (âge plausible cette année-là, période au club pour départager), même nom écrit autrement
 * (apostrophe, trait d'union), puis rapprochements approchés (une lettre de différence, nom
 * incomplet), retenus seulement si un seul joueur correspond.
 */
final class PersonResolver
{
    /** Manières sûres : nom complet, nom de famille seul plausible, même nom écrit autrement. */
    public const SURE = ['exact', 'nom', 'graphie'];
    /** Mentions gardées à la fin d'un nom de composition (« (A l'essai) », « (c) ») : ni prénom ni nom. */
    public const NOTE = '/\s*\((?:[aà] l.essai|essai|c|cap\.?|capitaine|g)\)\s*$/iu';

    /** Manière du dernier rapprochement (exact, nom, graphie, orthographe, nom incomplet), null sans résultat. */
    public ?string $how = null;
    /** Nom de composition => [fiche, manière, nombre] des rapprochements non exacts : signalés aux historiens. */
    public array $approxUsed = [];

    private array $byKey = [];
    private array $byLast = [];
    private array $byLetters = [];
    private array $byLastLetters = [];
    private array $nameKeys = []; // pid => [clé, mots] pour les rapprochements approchés
    private array $periods = [];
    private array $born = [];
    private array $approx = [];
    private array $persons;

    /** @param array<int,array> $persons fiches personnes (hors corbeille), par numéro */
    public function __construct(array $persons)
    {
        $this->persons = $persons;
        foreach ($persons as $pid => $p) {
            $pp = $p['personne'];
            $names = array_filter(array_merge([
                $pp['display_name'] ?: $p['title'],
                trim(($pp['first_name'] ?? '') . ' ' . ($pp['last_name'] ?? '')),
                $p['title'],
                $pp['nickname'] ?? '',
            ], (array) ($pp['aliases'] ?? [])), fn ($n) => is_string($n) && trim($n) !== '');
            foreach (array_unique(array_map([Names::class, 'personKey'], $names)) as $k) {
                if ($k !== '') {
                    $this->byKey[$k][] = $pid;
                    $this->nameKeys[$pid][] = [$k, explode(' ', $k)];
                }
            }
            foreach (array_unique(array_map([Names::class, 'letterKey'], $names)) as $k) {
                if (strlen($k) >= 6) {
                    $this->byLetters[$k][] = $pid;
                }
            }
            $last = implode(' ', Names::tokens($pp['last_name'] ?? ''));
            if ($last !== '') {
                $this->byLast[$last][] = $pid;
                $lk = Names::letterKey((string) $pp['last_name']);
                if (strlen($lk) >= 5) {
                    $this->byLastLetters[$lk][] = $pid;
                }
            }
            $years = [];
            foreach (['arrival', 'departure', 'arrival_coach', 'departure_coach', 'trial'] as $k) {
                if (!empty($pp[$k]['iso'])) {
                    $years[] = (int) substr((string) $pp[$k]['iso'], 0, 4);
                }
            }
            $this->periods[$pid] = $years ? [min($years) - 1, max($years) + 1] : [null, null];
            // Année de naissance : un nom seul ne relie jamais quelqu'un qui n'avait pas l'âge de jouer
            // (15 à 42 ans) ou d'entraîner (25 à 85 ans) cette année-là : un « Bernardet » de 1953 n'est
            // pas Steve Bernardet (2003-2005). Les dates d'arrivée et de départ ne bornent rien : un
            // joueur revenu au club, ou devenu entraîneur, garde ses matchs.
            $b = (int) substr((string) ($pp['birth']['date']['iso'] ?? $pp['birth']['iso'] ?? ''), 0, 4);
            $this->born[$pid] = $b > 1850 ? $b : null;
        }
    }

    /** Fiche du nom à cette date ($coach : entraîneur) ; $sureOnly : sans les rapprochements approchés. */
    public function resolve(string $name, ?string $date, bool $coach = false, bool $sureOnly = false): ?int
    {
        $this->how = null;
        $year = $date ? (int) substr($date, 0, 4) : null;
        $periods = $this->periods;
        $born = $this->born;
        // Écarte ceux qui n'avaient pas l'âge de jouer (ou d'entraîner) cette année-là.
        [$min, $max] = $coach ? [25, 85] : [15, 42];
        $plausible = fn (array $ids) => $year ? array_values(array_filter($ids, fn ($id) => $born[$id] === null || ($year >= $born[$id] + $min && $year <= $born[$id] + $max))) : $ids;
        $pick = function (array $ids) use ($year, $periods) {
            $ids = array_values(array_unique($ids));
            if (count($ids) === 1) {
                return $ids[0];
            }
            if ($year) {
                $in = array_values(array_filter($ids, fn ($id) => $periods[$id][0] !== null && $year >= $periods[$id][0] && $year <= $periods[$id][1]));
                if (count($in) === 1) {
                    return $in[0];
                }
            }
            return null;
        };
        $found = function (?int $pid, string $how): ?int {
            $this->how = $pid ? $how : null;
            return $pid;
        };
        $k = Names::personKey($name);
        if ($k !== '' && isset($this->byKey[$k])) {
            return $found($pick($this->byKey[$k]), 'exact');
        }
        // Nom de famille seul (« PIERRE », « Fofana »), avec contrôle de période.
        $last = Names::lineupLastName($name);
        if ($last !== '' && isset($this->byLast[$last]) && ($ids = $plausible($this->byLast[$last])) && ($pid = $pick($ids))) {
            // Prénom de la composition absent de la fiche (« BAZDAREVIC Mehmed » / Mecha Bazdarevic) :
            // relié pour les statistiques, signalé pour que l'historien confirme (autre graphie).
            if (!self::agrees($name, $this->persons[$pid])) {
                $this->approxUsed[$name] = [$pid, 'prénom différent', ($this->approxUsed[$name][2] ?? 0) + 1];
            }
            return $found($pid, 'nom');
        }
        // Nom incomplet : jamais vers un joueur dont la période au club (connue) exclut la date.
        $pickNear = function (array $ids) use ($year, $periods, $pick) {
            if ($year) {
                $ids = array_filter($ids, fn ($id) => $periods[$id][0] === null || ($year >= $periods[$id][0] && $year <= $periods[$id][1]));
            }
            return $ids ? $pick($ids) : null;
        };
        // Même nom écrit autrement : apostrophe, trait d'union, espace (« N'Diaye » / « Ndiaye »).
        $lk = Names::letterKey($name);
        if (isset($this->byLetters[$lk]) && ($ids = $plausible($this->byLetters[$lk])) && ($pid = $pick($ids))) {
            $this->approxUsed[$name] = [$pid, 'graphie', ($this->approxUsed[$name][2] ?? 0) + 1];
            return $found($pid, 'graphie');
        }
        if ($k === '' || $sureOnly) {
            return null;
        }
        // Rapprochements approchés, calculés une fois par nom : une lettre de différence
        // (deux pour un nom long), ou nom incomplet (« Carlao » pour « Carlao Roberto Da Cruz »).
        // Retenus seulement si un seul joueur correspond (ou un seul à cette période).
        if (!isset($this->approx[$k])) {
            $toks = explode(' ', $k);
            $typo = [];
            $part = [];
            $maxd = strlen($k) >= 15 ? 2 : (strlen($k) >= 9 ? 1 : 0);
            foreach ($this->nameKeys as $pid => $list) {
                foreach ($list as [$pk, $ptoks]) {
                    if ($maxd && abs(strlen($pk) - strlen($k)) <= $maxd && levenshtein($k, $pk) <= $maxd) {
                        $typo[] = $pid;
                    }
                    if (count($ptoks) > count($toks) && !array_diff($toks, $ptoks) && (count($toks) > 1 || strlen($k) >= 4)) {
                        $part[] = $pid;
                    }
                }
            }
            $this->approx[$k] = [array_values(array_unique($typo)), array_values(array_unique($part))];
        }
        foreach ($this->approx[$k] as $i => $ids) {
            $ids = $plausible($ids);
            if ($ids && ($pid = $i === 0 ? $pick($ids) : $pickNear($ids))) {
                $how = $i === 0 ? 'orthographe' : 'nom incomplet';
                $this->approxUsed[$name] = [$pid, $how, ($this->approxUsed[$name][2] ?? 0) + 1];
                return $found($pid, $how);
            }
        }
        return null;
    }

    /**
     * Nom de famille seul écrit autrement (« NDIAYE R » / Rassoul N'Diaye, « MBAKATA » / M'Bakata) :
     * âge plausible, un seul candidat (ou un seul à cette période). Pour les liens enregistrés dans
     * les compositions (LineupLinks), après resolve().
     */
    public function resolveSurname(string $name, ?string $date, bool $coach = false): ?int
    {
        $this->how = null;
        $last = Names::lineupLastName($name);
        $ids = $last !== '' ? ($this->byLastLetters[Names::letterKey($last)] ?? []) : [];
        return $this->only($this->plausible($ids, $date, $coach), $date);
    }

    /**
     * Fiches qui portent ce nom de famille (tel quel ou écrit autrement) et avaient l'âge de jouer (ou
     * d'entraîner) à cette date : pour départager des homonymes par le prénom ou l'initiale.
     * @return list<int>
     */
    public function surnameCandidates(string $name, ?string $date, bool $coach = false): array
    {
        $last = Names::lineupLastName($name);
        if ($last === '') {
            return [];
        }
        return $this->plausible(array_merge($this->byLast[$last] ?? [], $this->byLastLetters[Names::letterKey($last)] ?? []), $date, $coach);
    }

    /** Un seul candidat (ou un seul dont la période au club couvre la date) : il est retenu (« nom »). */
    public function only(array $ids, ?string $date): ?int
    {
        $this->how = null;
        $year = $date ? (int) substr($date, 0, 4) : null;
        $ids = array_values(array_unique($ids));
        if (count($ids) > 1 && $year) {
            $ids = array_values(array_filter($ids, fn ($id) => $this->periods[$id][0] !== null && $year >= $this->periods[$id][0] && $year <= $this->periods[$id][1]));
        }
        if (count($ids) !== 1) {
            return null;
        }
        $this->how = 'nom';
        return $ids[0];
    }

    /** @return list<int> */
    private function plausible(array $ids, ?string $date, bool $coach): array
    {
        $year = $date ? (int) substr($date, 0, 4) : null;
        [$min, $max] = $coach ? [25, 85] : [15, 42];
        return array_values(array_unique($year ? array_filter($ids, fn ($id) => $this->born[$id] === null || ($year >= $this->born[$id] + $min && $year <= $this->born[$id] + $max)) : $ids));
    }

    /**
     * Le nom de la composition ne contredit pas la fiche : chacun de ses mots est un nom, un prénom,
     * le surnom ou une autre graphie de la fiche, une initiale d'un prénom, ou un prénom à une faute de
     * frappe près (même initiale) ; ou bien c'est le même nom écrit autrement (« NDIAYE Rassoul » /
     * « Rassoul N'Diaye »).
     */
    public static function agrees(string $name, array $person): bool
    {
        $pp = (array) ($person['personne'] ?? []);
        $names = array_merge([(string) ($person['title'] ?? ''), (string) ($pp['display_name'] ?? ''), trim(($pp['first_name'] ?? '') . ' ' . ($pp['last_name'] ?? '')), (string) ($pp['nickname'] ?? '')], array_map('strval', (array) ($pp['aliases'] ?? [])));
        $bare = (string) preg_replace(self::NOTE, '', $name);
        $letters = Names::letterKey($bare);
        $known = [];
        foreach ($names as $x) {
            if (trim($x) === '') {
                continue;
            }
            if (strlen($letters) >= 6 && Names::letterKey($x) === $letters) {
                return true;
            }
            $known = array_merge($known, Names::tokens($x));
        }
        $firsts = array_merge(Names::tokens((string) ($pp['first_name'] ?? '')), Names::tokens((string) ($pp['nickname'] ?? '')));
        // Nom de famille écrit autrement (« NDIAYE » / « N'Diaye ») : mêmes lettres que celui de la fiche.
        $rowLast = Names::lineupLastName($bare);
        $lastLetters = Names::letterKey((string) ($pp['last_name'] ?? ''));
        $surname = $rowLast !== '' && strlen($lastLetters) >= 5 && Names::letterKey($rowLast) === $lastLetters ? explode(' ', $rowLast) : [];
        $tokens = Names::tokens($bare);
        foreach ($tokens as $t) {
            if (in_array($t, $known, true) || in_array($t, $surname, true) || (strlen($t) === 1 && in_array($t, array_map(fn ($f) => $f[0], $firsts), true))) {
                continue;
            }
            $close = false;
            foreach ($firsts as $f) {
                if (strlen($t) >= 4 && $t[0] === $f[0] && levenshtein($t, $f) <= (strlen($f) >= 8 ? 2 : 1)) {
                    $close = true;
                    break;
                }
            }
            if (!$close) {
                return false;
            }
        }
        return $tokens !== [];
    }
}
