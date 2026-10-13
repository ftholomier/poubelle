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
        'records' => Items\Records::class,
        'quiz-du-jour' => Items\QuizDuJour::class,
        'defi-du-jour' => Items\DefiDuJour::class,
        'dernier-match' => Items\DernierMatch::class,
        'saison' => Items\Saison::class,
        'palmares' => Items\Palmares::class,
        'frise' => Items\Frise::class,
        'bandeau' => Items\Bandeau::class,
        // Vague 3
        'histoire-du-duel' => Items\HistoireDuDuel::class,
        'carte-stades' => Items\CarteStades::class,
        'maillot' => Items\Maillot::class,
        'compte-a-rebours' => Items\CompteARebours::class,
        'soutenir' => Items\Soutenir::class,
        'recherche' => Items\Recherche::class,
        'retro-direct' => Items\RetroDirectWidget::class,
        'playlist' => Items\Playlist::class,
    ];

    /**
     * Widgets à venir : clé => [nom, description, phase]. Vide depuis la vague 3 (les 8 widgets
     * annoncés sont prêts ; « avant-match » est devenu « histoire-du-duel », « playlist » le mode écran).
     */
    public const PLANNED = [];

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
