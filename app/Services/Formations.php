<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Systèmes tactiques proposés dans la fiche match (ticket n° 26) : chacun dit combien de joueurs
 * placer sur chaque ligne du terrain, de l'arrière vers l'avant. Les défenseurs (D) remplissent la
 * ligne de défense ; les milieux et les attaquants (M et A, postes mêlés), dans l'ordre de la
 * composition, remplissent les lignes suivantes : les milieux défensifs se listent donc avant les
 * offensifs, et un attaquant listé avant un milieu passe devant lui.
 * Dans chaque ligne, le premier joueur listé est à droite (Fiche::pitch()).
 */
final class Formations
{
    /** clé => [libellé du menu, lignes de défense, lignes de milieux et d'attaquants (de l'arrière vers l'avant)] */
    public const ALL = [
        '4-4-2' => ['4-4-2', [4], [4, 2]],
        '4-1-2-1-2' => ['4-1-2-1-2 (4-4-2 en losange)', [4], [1, 2, 1, 2]],
        '4-3-3' => ['4-3-3', [4], [3, 3]],
        '4-2-3-1' => ['4-2-3-1', [4], [2, 3, 1]],
        '4-1-3-2' => ['4-1-3-2', [4], [1, 3, 2]],
        '4-1-4-1' => ['4-1-4-1', [4], [1, 4, 1]],
        '4-3-1-2' => ['4-3-1-2', [4], [3, 1, 2]],
        '4-3-2-1' => ['4-3-2-1 (« sapin de Noël »)', [4], [3, 2, 1]],
        '4-4-1-1' => ['4-4-1-1', [4], [4, 1, 1]],
        '4-5-1' => ['4-5-1', [4], [5, 1]],
        '4-2-2-2' => ['4-2-2-2 (« carré magique »)', [4], [2, 2, 2]],
        '4-2-4' => ['4-2-4', [4], [2, 4]],
        '3-5-2' => ['3-5-2', [3], [5, 2]],
        '3-4-3' => ['3-4-3', [3], [4, 3]],
        '3-4-1-2' => ['3-4-1-2', [3], [4, 1, 2]],
        '3-4-2-1' => ['3-4-2-1', [3], [4, 2, 1]],
        '5-3-2' => ['5-3-2', [5], [3, 2]],
        '5-4-1' => ['5-4-1', [5], [4, 1]],
        '2-3-5' => ['2-3-5 (« la pyramide », années 1920-1930)', [2], [3, 5]],
        '3-2-2-3' => ['3-2-2-3 (« le WM », années 1930-1950)', [3], [2, 2, 3]],
    ];

    /** Choix du menu : clé => libellé. */
    public static function options(): array
    {
        return array_map(fn ($f) => $f[0], self::ALL);
    }

    /**
     * Système reconnu dans le champ « Formation » (clé), ou null : « 4-4-2 », « 442 », « 4.4.2 »,
     * « 4-4-2 losange », « WM », « pyramide » ; texte libre non reconnu ou vide : null.
     */
    public static function key(?string $text): ?string
    {
        $t = mb_strtolower(trim((string) $text));
        if ($t === '') {
            return null;
        }
        if (isset(self::ALL[$t])) {
            return $t;
        }
        if (preg_match('/\bwm\b/u', $t)) {
            return '3-2-2-3';
        }
        if (str_contains($t, 'pyramide')) {
            return '2-3-5';
        }
        $digits = preg_replace('/\D+/', '', $t);
        if (str_contains($t, 'losange') && $digits === '442') {
            return '4-1-2-1-2';
        }
        $k = implode('-', str_split((string) $digits));
        return isset(self::ALL[$k]) ? $k : null;
    }

    /**
     * Lignes du système pour ces postes : ['key', 'd' => lignes de défense, 'up' => lignes de milieux
     * et d'attaquants], ou null (pas de système, ou postes qui ne collent pas : placement automatique).
     */
    public static function lines(?string $formation, int $defenders, int $others): ?array
    {
        $k = self::key($formation);
        if ($k === null) {
            return null;
        }
        [, $d, $up] = self::ALL[$k];
        return array_sum($d) === $defenders && array_sum($up) === $others ? ['key' => $k, 'd' => $d, 'up' => $up] : null;
    }

    /**
     * Pourquoi le système choisi n'est pas appliqué (message pour l'historien), ou null s'il l'est
     * ou si aucun n'est choisi.
     * @param list<array> $rows lignes de la composition (position G, D, M, A, R, E)
     */
    public static function problem(?string $formation, array $rows): ?string
    {
        if (trim((string) $formation) === '') {
            return null;
        }
        $k = self::key($formation);
        if ($k === null) {
            return 'Formation « ' . trim((string) $formation) . ' » non reconnue : les joueurs sont placés automatiquement. Choisissez un système dans la liste.';
        }
        $n = ['D' => 0, 'M' => 0, 'A' => 0];
        foreach ($rows as $r) {
            $p = (string) ($r['position'] ?? '');
            if (isset($n[$p])) {
                $n[$p]++;
            }
        }
        if ($n['D'] + $n['M'] + $n['A'] === 0 || self::lines($k, $n['D'], $n['M'] + $n['A'])) {
            return null;
        }
        [, $d, $up] = self::ALL[$k];
        return 'Le ' . $k . ' demande ' . array_sum($d) . ' défenseur' . (array_sum($d) > 1 ? 's' : '') . ' et ' . array_sum($up) . ' milieux et attaquants ; la composition compte '
            . $n['D'] . ' D, ' . $n['M'] . ' M et ' . $n['A'] . ' A : les joueurs sont placés automatiquement. Corrigez les postes ou le système.';
    }
}
