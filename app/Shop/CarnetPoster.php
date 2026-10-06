<?php
declare(strict_types=1);

namespace App\Shop;

use App\Services\Carnet;

/**
 * Poster « Ma vie en jaune et bleu » : le carnet du supporter en affiche A3 (voir Poster pour le
 * match et PlayerPoster pour le joueur). C'est un calque « poster » de genre « carnet ».
 *
 * Champs du client : poster_carnet (identifiant du carnet : le sien, ouvert sur l'appareil, ou une
 * page publique), poster_prenom, poster_nom (la dédicace). À la mise au panier, la liste des matchs
 * est figée dans la commande (_carnet_ids) : le fichier d'impression reste le même si le carnet
 * change ou disparaît ensuite. Aucun appel à l'IA.
 */
final class CarnetPoster
{
    public const FIELD = 'poster_carnet';
    public const FIELDS = ['poster_carnet' => 'Votre carnet', 'poster_prenom' => 'Prénom', 'poster_nom' => 'Nom'];
    /** Exemple de l'éditeur et de la vitrine : un abonné de Bonal à la fin des années 1980. */
    public const SAMPLE = 'exemple';
    /** Matchs vus au minimum pour commander son poster. */
    public const MIN = 5;

    /** Matchs de l'exemple : les matchs à domicile de 1985-1986 à 1991-1992 (un sur deux). */
    private static function sampleIds(): array
    {
        $ids = [];
        foreach (['1985-1986', '1986-1987', '1987-1988', '1988-1989', '1989-1990', '1990-1991', '1991-1992'] as $s) {
            foreach (Carnet::seasonMatches($s) as $i => $x) {
                if ($x['sh'] && $i % 2 === 0) {
                    $ids[] = (int) $x['id'];
                }
            }
        }
        return $ids;
    }

    /** Matchs d'un carnet commandable par ce visiteur : le sien (cookie) ou une page publique. */
    public static function idsFor(string $id): ?array
    {
        if ($id === self::SAMPLE) {
            return self::sampleIds();
        }
        $c = Carnet::get($id);
        if (!$c) {
            return null;
        }
        $mine = (Carnet::current()['id'] ?? '') === $id;
        return $mine || !empty($c['public']) ? array_map('intval', array_keys($c['matches'])) : null;
    }

    public static function eligible(string $id): bool
    {
        $ids = $id !== self::SAMPLE ? self::idsFor($id) : null;
        return $ids !== null && count($ids) >= self::MIN;
    }

    public static function label(string $id): ?string
    {
        $ids = self::idsFor($id);
        return $ids === null ? null : 'carnet du supporter : ' . count($ids) . ' matchs';
    }

    /**
     * Données du poster pour ces matchs : le bilan du carnet, plus les saisons (matchs vus et
     * victoires), les grands matchs et l'affluence la plus forte. @return array|null
     */
    public static function data(array $ids): ?array
    {
        $s = Carnet::stats($ids);
        if ($s['n'] < 1) {
            return null;
        }
        $seasons = [];
        $crowd = null;
        foreach (array_reverse($s['list']) as $x) {
            $k = (string) $x['season'];
            $seasons[$k] ??= ['season' => $k, 'm' => 0, 'v' => 0];
            $seasons[$k]['m']++;
            $seasons[$k]['v'] += $x['result'] === 'V' ? 1 : 0;
            if ((int) ($x['spectators'] ?? 0) > (int) ($crowd['spectators'] ?? 0)) {
                $crowd = $x;
            }
        }
        $big = [];
        foreach ([['Mon premier match', $s['first']], ['Ma plus belle victoire', $s['best']], ['La plus grosse affluence', $crowd], ['Mon dernier match', $s['n'] > 1 ? $s['last'] : null]] as [$lab, $x]) {
            if ($x && !in_array($x['id'], array_column($big, 'id'), true)) {
                $big[] = ['label' => $lab, 'id' => $x['id'], 'date' => (string) $x['date'], 'teams' => $x['home'] . ' ' . ($x['sh'] ? $x['us'] . '–' . $x['them'] : $x['them'] . '–' . $x['us']) . ' ' . $x['away'],
                    'comp' => trim($x['label'] . ' ' . $x['round']) . ((int) ($x['spectators'] ?? 0) > 0 ? ' · ' . number_format((int) $x['spectators'], 0, ',', ' ') . ' spectateurs' : '')];
            }
        }
        return $s + ['seasons_list' => array_values($seasons), 'big' => $big,
            'years' => $s['first'] ? substr((string) $s['first']['date'], 0, 4) . ($s['n'] > 1 && substr((string) $s['last']['date'], 0, 4) !== substr((string) $s['first']['date'], 0, 4) ? '-' . substr((string) $s['last']['date'], 0, 4) : '') : ''];
    }

    /**
     * Calques (mm) du poster dans le cadre du calque. Matchs : la liste figée dans la commande
     * (_carnet_ids), sinon le carnet choisi, sinon l'exemple. @return list<array>
     */
    public static function layers(array $frame, array $values): array
    {
        $frozen = (string) ($values['_carnet_ids'] ?? '');
        $ids = $frozen !== '' ? array_map('intval', explode(',', $frozen)) : (self::idsFor((string) ($values[self::FIELD] ?? '')) ?? null);
        $d = ($ids ? self::data($ids) : null) ?? self::data(self::sampleIds());
        if (!$d) {
            return [];
        }
        $pour = trim(mb_substr(trim((string) ($values['poster_prenom'] ?? '')), 0, 30) . ' ' . mb_substr(trim((string) ($values['poster_nom'] ?? '')), 0, 30));
        $no = (string) preg_replace('/[^0-9A-Z-]/', '', strtoupper((string) ($values['_poster_no'] ?? '')));
        return Poster::fit((new CarnetPosterLayout($d, $pour !== '' ? $pour : 'Prénom Nom', $no))->build(), $frame);
    }
}
