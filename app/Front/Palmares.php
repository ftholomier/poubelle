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
    /** Titres de référence (Wikipédia et fcsmstory.com, vérifiés le 8 octobre 2026) : titre, finale (finaliste ou vice-champion), demi. */
    public const DEFAULT = [
        ['comp' => 'Championnat de France', 'year' => 1935, 'kind' => 'titre'],
        ['comp' => 'Championnat de France', 'year' => 1938, 'kind' => 'titre'],
        ['comp' => 'Championnat de France', 'year' => 1937, 'kind' => 'finale'],
        ['comp' => 'Championnat de France', 'year' => 1953, 'kind' => 'finale'],
        ['comp' => 'Championnat de France', 'year' => 1980, 'kind' => 'finale'],
        ['comp' => 'Coupe de France', 'year' => 1937, 'kind' => 'titre'],
        ['comp' => 'Coupe de France', 'year' => 2007, 'kind' => 'titre'],
        ['comp' => 'Coupe de France', 'year' => 1959, 'kind' => 'finale'],
        ['comp' => 'Coupe de France', 'year' => 1967, 'kind' => 'finale'],
        ['comp' => 'Coupe de France', 'year' => 1988, 'kind' => 'finale'],
        ['comp' => 'Coupe de la Ligue', 'year' => 2004, 'kind' => 'titre'],
        ['comp' => 'Coupe de la Ligue', 'year' => 2003, 'kind' => 'finale'],
        ['comp' => 'Trophée des champions', 'year' => 2007, 'kind' => 'finale'],
        ['comp' => 'Championnat de France de D2', 'year' => 1947, 'kind' => 'titre'],
        ['comp' => 'Championnat de France de D2', 'year' => 2001, 'kind' => 'titre'],
        ['comp' => 'Championnat de France de D2', 'year' => 1964, 'kind' => 'finale'],
        ['comp' => 'Championnat de France de D2', 'year' => 1988, 'kind' => 'finale'],
        ['comp' => 'Division 3 (équipe réserve)', 'year' => 1978, 'kind' => 'titre'],
        ['comp' => 'Division 3 (équipe réserve)', 'year' => 1987, 'kind' => 'titre'],
        ['comp' => 'Coupe UEFA', 'year' => 1981, 'kind' => 'demi'],
        ['comp' => 'Coupe Intertoto', 'year' => 2002, 'kind' => 'demi'],
        ['comp' => 'Coupe Gambardella', 'year' => 1983, 'kind' => 'titre'],
        ['comp' => 'Coupe Gambardella', 'year' => 2007, 'kind' => 'titre'],
        ['comp' => 'Coupe Gambardella', 'year' => 2015, 'kind' => 'titre'],
        ['comp' => 'Coupe Gambardella', 'year' => 1975, 'kind' => 'finale'],
        ['comp' => 'Coupe Gambardella', 'year' => 2010, 'kind' => 'finale'],
        ['comp' => 'Coupe Charles Drago', 'year' => 1953, 'kind' => 'titre'],
        ['comp' => 'Coupe Charles Drago', 'year' => 1963, 'kind' => 'titre'],
        ['comp' => 'Coupe Charles Drago', 'year' => 1964, 'kind' => 'titre'],
        ['comp' => 'Coupe Peugeot', 'year' => 1931, 'kind' => 'titre'],
        // Débuts du club (fcsmstory.com, saisons 1929-1930 à 1931-1932, Coupe Dupuich)
        ['comp' => 'Championnat de Bourgogne-Franche-Comté', 'year' => 1932, 'kind' => 'titre'],
        ['comp' => 'Championnat de promotion (Ligue de Bourgogne-Franche-Comté)', 'year' => 1931, 'kind' => 'titre'],
        ['comp' => 'Challenge Maurice de Turckheim', 'year' => 1929, 'kind' => 'titre'],
        ['comp' => 'Challenge Maurice de Turckheim', 'year' => 1930, 'kind' => 'titre'],
        ['comp' => 'Challenge Maurice de Turckheim', 'year' => 1931, 'kind' => 'titre'],
        ['comp' => 'Tournoi de Bruxelles (Coupe Dupuich)', 'year' => 1930, 'kind' => 'finale'],
        ['comp' => 'Coupe des Alpes', 'year' => 1981, 'kind' => 'finale'],
        ['comp' => 'Tournoi de Casablanca', 'year' => 1989, 'kind' => 'titre'],
        ['comp' => 'Trophée Joan Gamper', 'year' => 1989, 'kind' => 'finale'],
    ];

    /** Ordre d'affichage des compétitions (les autres suivent, par ordre alphabétique). */
    public const ORDER = ['Championnat de France', 'Coupe de France', 'Coupe de la Ligue', 'Trophée des champions', 'Coupe UEFA', 'Coupe Intertoto', 'Championnat de France de D2', 'Division 3 (équipe réserve)', 'Championnat de Bourgogne-Franche-Comté', 'Championnat de promotion (Ligue de Bourgogne-Franche-Comté)', 'Coupe Gambardella', 'Coupe Charles Drago', 'Coupe Peugeot', 'Challenge Maurice de Turckheim', 'Tournoi de Bruxelles (Coupe Dupuich)', 'Coupe des Alpes', 'Tournoi de Casablanca', 'Trophée Joan Gamper'];

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
        foreach (Collections::get('palmares_titres', self::DEFAULT) as $t) {
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
        $rows = array_values(array_filter((array) Collections::get('palmares_titres', self::DEFAULT), 'is_array'));
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
            'title' => t('Palmarès du FC Sochaux-Montbéliard'),
            'description' => t('Les titres, finales et places d’honneur du FC Sochaux-Montbéliard depuis 1928 : championnats, Coupes de France, Coupe de la Ligue, Gambardella, Coupe Drago…'),
            'active' => 'interactif',
            'styles' => ['css/mosaic.css', 'css/explore.css'],
        ]);
    }
}
