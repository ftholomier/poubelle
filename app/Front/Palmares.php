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
    /** Titres de référence : [compétition, année de la saison (fin), statut]. */
    public const DEFAULT = [
        ['comp' => 'Championnat de France', 'year' => 1935, 'kind' => 'titre'],
        ['comp' => 'Championnat de France', 'year' => 1938, 'kind' => 'titre'],
        ['comp' => 'Championnat de France de D2', 'year' => 1947, 'kind' => 'titre'],
        ['comp' => 'Championnat de France de D2', 'year' => 1964, 'kind' => 'titre'],
        ['comp' => 'Championnat de France de D2', 'year' => 2001, 'kind' => 'titre'],
        ['comp' => 'Coupe de France', 'year' => 1937, 'kind' => 'titre'],
        ['comp' => 'Coupe de France', 'year' => 2007, 'kind' => 'titre'],
        ['comp' => 'Coupe de la Ligue', 'year' => 2004, 'kind' => 'titre'],
        ['comp' => 'Coupe de France', 'year' => 1959, 'kind' => 'finale'],
        ['comp' => 'Coupe de France', 'year' => 1967, 'kind' => 'finale'],
        ['comp' => 'Coupe de France', 'year' => 1988, 'kind' => 'finale'],
        ['comp' => 'Coupe de la Ligue', 'year' => 2003, 'kind' => 'finale'],
    ];

    /** Ordre d'affichage des compétitions (les autres suivent, par ordre alphabétique). */
    private const ORDER = ['Championnat de France', 'Coupe de France', 'Coupe de la Ligue', 'Championnat de France de D2', 'Coupe Gambardella', 'Coupe Charles Drago'];

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
            $comp = self::canonical($comp);
            $year = (int) substr((string) ($m['date'] ?? ''), 0, 4);
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
            $groups[$r['comp']][$r['kind'] === 'titre' ? 'titles' : 'finals'][] = $item;
        }
        uksort($groups, function ($a, $b) {
            $ia = array_search($a, self::ORDER, true);
            $ib = array_search($b, self::ORDER, true);
            return [$ia === false ? 99 : $ia, $a] <=> [$ib === false ? 99 : $ib, $b];
        });
        $count = ['titre' => 0, 'finale' => 0];
        foreach ($groups as &$g) {
            $g += ['titles' => [], 'finals' => []];
            usort($g['titles'], fn ($a, $b) => $a['year'] <=> $b['year']);
            usort($g['finals'], fn ($a, $b) => $a['year'] <=> $b['year']);
            $count['titre'] += count($g['titles']);
            $count['finale'] += count($g['finals']);
        }
        unset($g);
        return ['groups' => array_values(array_filter($groups, fn ($g) => $g['titles'] || $g['finals'])), 'count' => $count];
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
            'description' => t('Les titres et les finales du FC Sochaux-Montbéliard depuis 1928 : championnats, Coupes de France, Coupe de la Ligue…'),
            'active' => 'interactif',
            'styles' => ['css/mosaic.css', 'css/explore.css'],
        ]);
    }
}
