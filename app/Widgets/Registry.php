<?php
declare(strict_types=1);

namespace App\Widgets;

/**
 * Registre des widgets du musée (docs/widgets-proposition.pdf) : les widgets prêts (une classe
 * chacun dans app/Widgets/Items/) et ceux à venir, montrés « à venir » dans le back-office.
 */
final class Registry
{
    /** Widgets prêts, dans l'ordre du catalogue : clé => classe. */
    public const CLASSES = [
        'ce-jour-la' => Items\CeJourLa::class,
        'chiffre-du-jour' => Items\ChiffreDuJour::class,
        'il-y-a' => Items\IlYA::class,
        'carte-legende' => Items\CarteLegende::class,
        'photo-du-jour' => Items\PhotoDuJour::class,
        'le-saviez-vous' => Items\LeSaviezVous::class,
    ];

    /** Widgets à venir (phase 2 et 3) : clé => [nom, description, phase]. */
    public const PLANNED = [
        'avant-match' => ['Avant-match : l’histoire du duel', 'Bilan historique contre l’adversaire du soir, calculé depuis le face-à-face.', 3],
        'records' => ['Records', 'Top 10 des buteurs ou des joueurs les plus utilisés.', 2],
        'quiz-du-jour' => ['Quiz du jour', 'Une question à choix multiples, réponse après un compte à rebours sur écran.', 2],
        'defi-du-jour' => ['Défi du jour', 'Le défi quotidien du musée, avec le score des participants.', 2],
        'dernier-match' => ['Dernier match fiché', 'Le dernier match ajouté au musée, avec son récit.', 2],
        'saison' => ['Saison en un coup d’œil', 'Classement, bilan, meilleurs buteurs, entraîneur et titres d’une saison.', 2],
        'palmares' => ['Palmarès', 'Les titres du club, en bandeau ou en liste.', 2],
        'frise' => ['Frise express', 'Une frise chronologique compacte qui défile.', 2],
        'maillot' => ['Maillot de l’époque', 'Un maillot d’une saison, avec son histoire.', 3],
        'retro-direct' => ['Rétro-Direct', 'Un grand match rejoué minute par minute le jour anniversaire.', 3],
        'bandeau' => ['Bandeau « En direct du musée »', 'Le bandeau défilant du musée.', 2],
        'carte-stades' => ['Carte des stades', 'La carte des stades et des lieux du club.', 3],
        'compte-a-rebours' => ['Compte à rebours', 'Jusqu’à un anniversaire (centenaire, date ronde d’un titre).', 3],
        'soutenir' => ['Soutenir le musée', 'Encart don, boutique, contribuer.', 3],
        'recherche' => ['Recherche du musée', 'Un champ « cherchez un match, un joueur » qui ouvre le musée.', 3],
        'playlist' => ['Playlist écran', 'Enchaînement de plusieurs widgets en plein écran.', 3],
    ];

    /** @var array<string,Widget> */
    private static array $made = [];

    /** @return array<string,Widget> */
    public static function all(): array
    {
        $out = [];
        foreach (array_keys(self::CLASSES) as $k) {
            $out[$k] = self::get($k);
        }
        return $out;
    }

    public static function get(string $key): ?Widget
    {
        $cls = self::CLASSES[$key] ?? null;
        if ($cls === null) {
            return null;
        }
        return self::$made[$key] ??= new $cls();
    }
}
