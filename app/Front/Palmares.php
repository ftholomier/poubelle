<?php
declare(strict_types=1);

namespace App\Front;

use App\Core\Request;
use App\Core\Response;
use App\Data\Collections;
use App\Data\Index;
use App\Data\Names;

/**
 * Palmarès du FCSM : titres et finales. Une liste de référence (modifiable : collection
 * « palmares_titres ») est complétée automatiquement par les finales trouvées dans les fiches
 * matchs (gagnées : trophée ; perdues : finaliste), et chaque titre est relié à la fiche de sa
 * finale et à sa saison.
 */
final class Palmares
{
    /**
     * Titres de référence du club (config/clubs/<id>/palmares-titres.php) : titre, finale
     * (finaliste ou vice-champion), demi. Aucun pour un musée neuf (finales des fiches matchs seules).
     */
    public static function defaults(): array
    {
        return (array) \App\Core\Club::content('palmares-titres', []);
    }

    /** Ordre d'affichage des compétitions (les autres suivent, par ordre alphabétique). */
    public const ORDER = ['Championnat de France', 'Coupe de France', 'Coupe de la Ligue', 'Trophée des champions', 'Ligue des champions', 'Coupe des coupes', 'Supercoupe de l\'UEFA', 'Coupe intercontinentale', 'Coupe du monde des clubs', 'Coupe UEFA', 'Coupe Intertoto', 'Championnat de France de D2', 'Championnat de France de D3', 'Division 3 (équipe réserve)', 'Championnat de Bourgogne-Franche-Comté', 'Championnat de promotion (Ligue de Bourgogne-Franche-Comté)', 'Coupe Gambardella', 'Coupe Charles Drago', 'Coupe Peugeot', 'Challenge Maurice de Turckheim', 'Tournoi de Bruxelles (Coupe Dupuich)', 'Coupe des Alpes', 'Tournoi de Casablanca', 'Trophée Joan Gamper'];

    /** Finale d'une compétition (et pas un tour qui contient le mot « finale »). */
    public static function isFinal(string $round): bool
    {
        $r = mb_strtolower(Names::ascii($round));
        return (bool) preg_match('/(^|[\s–-])finale\b/u', $r) && !preg_match('/(demi|quart|huiti|seizi|petite|\d\s*(e|er|eme|è)?\s*de\s*finale|1\/\d)/u', $r);
    }

    /** @return array{groups:list<array{comp:string,titles:list<array>,finals:list<array>}>,count:array{titre:int,finale:int}} */
    public static function data(): array
    {
        $rows = [];
        foreach (Collections::get('palmares_titres', self::defaults()) as $t) {
            $rows[self::key((string) $t['comp'], (int) $t['year'])] = ['comp' => (string) $t['comp'], 'year' => (int) $t['year'], 'kind' => (string) $t['kind'], 'match' => null];
        }
        // Finales des fiches matchs
        foreach (Index::published('match') as $e) {
            $m = $e['m'] ?? [];
            if (!self::isFinal((string) ($m['round'] ?? '') . ' ' . ($m['round_text'] ?? '')) || ($m['competition'] ?? '') === 'Amical') {
                continue;
            }
            $comp = trim((string) ($m['competition_label'] ?? '')) ?: (string) ($m['competition'] ?? '');
            $year = (int) substr((string) ($m['date'] ?? ''), 0, 4);
            // « Coupe » tout court entre 1953 et 1965 : la Coupe Charles Drago (coupe des éliminés
            // de la Coupe de France), ou toute finale dont le titre la nomme.
            if (preg_match('/drago/i', (string) $e['title'] . ' ' . ($m['round_text'] ?? '') . ' ' . ($m['event'] ?? '')) || (mb_strtolower(trim($comp)) === 'coupe' && $year >= 1953 && $year <= 1965)) {
                $comp = 'Coupe Charles Drago';
            }
            $comp = self::canonical($comp);
            if (!$year || $comp === '') {
                continue;
            }
            $kind = ($m['result'] ?? '') === 'V' ? 'titre' : (($m['result'] ?? '') === 'D' ? 'finale' : null);
            $k = self::key($comp, $year);
            if (isset($rows[$k])) {
                $rows[$k]['match'] = $e;
            } elseif ($kind) {
                $rows[$k] = ['comp' => $comp, 'year' => $year, 'kind' => $kind, 'match' => $e];
            }
        }
        $groups = [];
        foreach ($rows as $r) {
            $season = ($r['year'] - 1) . '-' . $r['year'];
            if ($r['match']) {
                $season = (string) ($r['match']['m']['season'] ?? $season);
            }
            $item = [
                'year' => $r['year'], 'season' => $season, 'seasonHref' => url('/matchs/' . $season . '/'),
                'href' => $r['match'] ? url($r['match']['path']) : null,
                'label' => $r['match'] ? Pages::shortTitle($r['match']) : null,
                'image' => $r['match'] && !Index::isPlaceholderImage((string) ($r['match']['image'] ?? '')) ? $r['match']['image'] : null,
            ];
            $groups[$r['comp']]['comp'] = $r['comp'];
            $groups[$r['comp']][$r['kind'] === 'titre' ? 'titles' : ($r['kind'] === 'demi' ? 'semis' : 'finals')][] = $item;
        }
        uksort($groups, function ($a, $b) {
            $ia = array_search($a, self::ORDER, true);
            $ib = array_search($b, self::ORDER, true);
            return [$ia === false ? 99 : $ia, $a] <=> [$ib === false ? 99 : $ib, $b];
        });
        $count = ['titre' => 0, 'finale' => 0];
        foreach ($groups as &$g) {
            $g += ['titles' => [], 'finals' => [], 'semis' => []];
            $g['second'] = preg_match('/^(championnat|division)/i', $g['comp']) ? 'Vice-champion' : 'Finaliste';
            usort($g['titles'], fn ($a, $b) => $a['year'] <=> $b['year']);
            usort($g['finals'], fn ($a, $b) => $a['year'] <=> $b['year']);
            $count['titre'] += count($g['titles']);
            $count['finale'] += count($g['finals']);
        }
        unset($g);
        return ['groups' => array_values(array_filter($groups, fn ($g) => $g['titles'] || $g['finals'] || $g['semis'])), 'count' => $count];
    }

    /** Liste éditable dans le back-office (Accueil & bandeau › Palmarès), triée comme la page. */
    public static function titles(): array
    {
        $rows = array_values(array_filter((array) Collections::get('palmares_titres', self::defaults()), 'is_array'));
        usort($rows, function ($a, $b) {
            $ia = array_search($a['comp'] ?? '', self::ORDER, true);
            $ib = array_search($b['comp'] ?? '', self::ORDER, true);
            return [$ia === false ? 99 : $ia, $a['comp'] ?? '', (int) ($a['year'] ?? 0)] <=> [$ib === false ? 99 : $ib, $b['comp'] ?? '', (int) ($b['year'] ?? 0)];
        });
        return $rows;
    }

    private static function key(string $comp, int $year): string
    {
        return mb_strtolower(Names::ascii(self::canonical($comp))) . '|' . $year;
    }

    /** Noms de compétition harmonisés (« Coupe De La Ligue », « CDF »…). */
    private static function canonical(string $c): string
    {
        $l = mb_strtolower(Names::ascii($c));
        return match (true) {
            str_contains($l, 'coupe de france') || $l === 'cdf' => 'Coupe de France',
            str_contains($l, 'coupe de la ligue') || $l === 'cdl' => 'Coupe de la Ligue',
            str_contains($l, 'gambardella') => 'Coupe Gambardella',
            str_contains($l, 'drago') => 'Coupe Charles Drago',
            default => trim($c),
        };
    }

    public static function page(Request $req): Response
    {
        $d = self::data();
        return Pages::render('palmares', $d, [
            'title' => t('Palmarès {club_de}'),
            'description' => t('Les titres, finales et places d’honneur {club_de} depuis {founded} : championnats, Coupes de France, Coupe de la Ligue, Gambardella, Coupe Drago…'),
            'active' => 'interactif',
            'styles' => ['css/mosaic.css', 'css/explore.css'],
        ]);
    }
}
