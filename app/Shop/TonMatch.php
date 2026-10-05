<?php
declare(strict_types=1);

namespace App\Shop;

use App\Data\Derived;
use App\Data\Index;

/**
 * « Ton match » : le client donne une date (jour, mois et année ; mois et année ; ou l'année
 * seule), le musée retrouve le match de Sochaux de ce jour-là (ou le plus proche, ou le plus
 * marquant du mois ou de l'année) et remplit les champs « match_… » d'un modèle : date,
 * affiche et score, compétition, stade, buteurs, spectateurs, phrase « Ce jour-là… ».
 * Un modèle est « Ton match » dès qu'un de ses champs du client commence par « match_ ».
 */
final class TonMatch
{
    public const FIELDS = [
        'match_annee' => 'Année (« 1988 »)',
        'match_date' => 'Date (« 11 juin 1988 »)',
        'match_affiche' => 'Affiche et score (« Sochaux 1-1 Metz »)',
        'match_compet' => 'Compétition (« Coupe de France · finale »)',
        'match_lieu' => 'Stade',
        'match_buteurs' => 'Buteurs sochaliens',
        'match_public' => 'Spectateurs',
        'match_phrase' => 'Phrase « Ce jour-là… »',
    ];
    private const MONTHS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

    /** Le modèle a-t-il des champs remplis par « Ton match » ? */
    public static function isFor(array $model): bool
    {
        foreach (array_keys(Catalog::fields($model)) as $k) {
            if (str_starts_with($k, 'match_')) {
                return true;
            }
        }
        return false;
    }

    /** Date saisie : « 1988-06-11 », « 1988-06 » ou « 1988 » (null si invalide). @return array{0:string,1:string}|null [précision, valeur] */
    public static function parse(string $d): ?array
    {
        $d = trim($d);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return ['day', $d];
        }
        if (preg_match('/^(\d{4})-(\d{2})$/', $d, $m) && (int) $m[2] >= 1 && (int) $m[2] <= 12) {
            return ['month', $d];
        }
        if (preg_match('/^(\d{4})$/', $d)) {
            return ['year', $d];
        }
        return null;
    }

    /**
     * Le match d'une date. Jour : ce jour-là, sinon le plus proche ; mois ou année : le plus
     * marquant de la période, sinon le plus proche. @return array{dm:array,exact:bool}|null
     */
    public static function find(string $date): ?array
    {
        $p = self::parse($date);
        if (!$p) {
            return null;
        }
        [$prec, $val] = $p;
        $all = array_filter(Derived::part('matches'), fn ($m) => !empty($m['v']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($m['date'] ?? '')));
        if (!$all) {
            return null;
        }
        $in = array_filter($all, fn ($m) => str_starts_with((string) $m['date'], $val));
        if ($in) {
            usort($in, fn ($a, $b) => ((int) ($b['hl'] ?? 0) <=> (int) ($a['hl'] ?? 0)) ?: strcmp((string) $a['date'], (string) $b['date']));
            return ['dm' => $in[0], 'exact' => true];
        }
        $target = strtotime($prec === 'day' ? $val : ($prec === 'month' ? $val . '-15' : $val . '-07-01'));
        $best = null;
        foreach ($all as $m) {
            $diff = abs(strtotime((string) $m['date']) - $target);
            if ($best === null || $diff < $best[0]) {
                $best = [$diff, $m];
            }
        }
        return ['dm' => $best[1], 'exact' => false];
    }

    public static function frDate(string $ymd): string
    {
        [$y, $m, $d] = array_map('intval', explode('-', $ymd));
        return ($d === 1 ? '1er' : (string) $d) . ' ' . self::MONTHS[$m - 1] . ' ' . $y;
    }

    /** Valeurs des champs « match_… » pour une date. @return array<string,string> */
    public static function values(string $date): array
    {
        $f = self::find($date);
        if (!$f) {
            return [];
        }
        $m = $f['dm'];
        $doc = Index::get((int) $m['id']);
        $mm = (array) ($doc['m'] ?? []);
        $home = (string) $m['home'];
        $away = (string) $m['away'];
        $us = (string) $m['us'];
        $them = (string) $m['them'];
        $score = $m['sh'] ? "$us-$them" : "$them-$us";
        // « a.p (5-4 tab) » → « a.p., 5-4 t.a.b. »
        $raw = trim((string) preg_replace('/[()]/', ' ', (string) ($m['extra'] ?? '')));
        $tab = (bool) preg_match('/\btab\b/i', $raw);
        $extra = trim((string) preg_replace(['/\ba\.?p\b\.?/i', '/\btab\b/i', '/\s+/'], ['a.p.,', 't.a.b.', ' '], $raw), ' ,');
        $buteurs = [];
        foreach ((array) (Derived::part('scorers')[$m['id']] ?? Derived::part('scorers')[(string) $m['id']] ?? []) as $s) {
            $parts = preg_split('/\s+/u', trim((string) ($s[1] ?? '')));
            $name = count($parts) > 1 ? mb_substr($parts[0], 0, 1) . '. ' . implode(' ', array_slice($parts, 1)) : (string) ($parts[0] ?? '');
            $min = implode(', ', array_map(fn ($x) => $x . "'", (array) ($s[2] ?? [])));
            if ($name !== '') {
                $buteurs[] = $name . ($min !== '' ? ' ' . $min : '');
            }
        }
        $opp = (string) ($m['opp'] ?? ($m['sh'] ? $away : $home));
        $phrase = $tab ? "Sochaux et $opp se quittaient sur un $us-$them, " . ((string) $m['result'] === 'V' ? 'avant de l’emporter' : 'avant de s’incliner') . ' aux tirs au but'
            : match ((string) $m['result']) {
                'V' => "Sochaux battait $opp $us-$them",
                'D' => "Sochaux s’inclinait $us-$them face à $opp",
                default => "Sochaux et $opp se quittaient sur un $us-$them",
            };
        $when = self::frDate((string) $m['date']);
        $comp = implode(' · ', array_unique(array_filter([(string) ($m['label'] ?: $m['comp']), (string) ($m['round'] ?? '')])));
        return [
            'match_annee' => substr((string) $m['date'], 0, 4),
            'match_date' => $when,
            'match_affiche' => "$home $score $away" . ($extra !== '' ? " · $extra" : ''),
            'match_compet' => $comp,
            'match_lieu' => (string) ($mm['stadium'] ?? ''),
            'match_buteurs' => $buteurs ? implode(' · ', $buteurs) : '',
            'match_public' => (int) ($m['spectators'] ?? 0) > 0 ? number_format((int) $m['spectators'], 0, ',', "\u{202F}") . ' spectateurs' : '',
            'match_phrase' => ($f['exact'] && self::parse($date)[0] === 'day' ? 'Ce jour-là, ' : 'Le ' . $when . ', ') . $phrase . '.',
            '_match' => (string) $m['id'], '_exact' => $f['exact'] ? '1' : '0', '_path' => (string) ($m['path'] ?? ''),
        ];
    }

    /** Message pour le client : quel match a été trouvé. */
    public static function note(array $v, string $date): string
    {
        if (!$v) {
            return 'Indiquez une date (jour, mois, année ; ou seulement le mois et l’année ; ou l’année).';
        }
        $prec = self::parse($date)[0] ?? 'day';
        return ($v['_exact'] === '1' ? ($prec === 'day' ? 'Ce jour-là, Sochaux jouait : ' : 'Le match le plus marquant de cette période : ') : 'Pas de match ce jour-là : voici le plus proche, ') . $v['match_affiche'] . ', le ' . $v['match_date'] . '.';
    }
}
