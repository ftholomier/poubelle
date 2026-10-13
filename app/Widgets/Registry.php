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
        'compte-a-rebours' => Items\CompteARebours::class,
        'soutenir' => Items\Soutenir::class,
        'recherche' => Items\Recherche::class,
        'retro-direct' => Items\RetroDirectWidget::class,
        'playlist' => Items\Playlist::class,
        // Après la vague 3
        'boutique' => Items\Boutique::class,
    ];

    /** Catégories du catalogue (filtres du back-office, de /widgets/ et de l'API) : clé => [fr, en]. */
    public const CATEGORIES = [
        'matchs' => ['Matchs & adversaires', 'Matches & opponents'],
        'joueurs' => ['Joueurs & légendes', 'Players & legends'],
        'chiffres' => ['Chiffres & records', 'Figures & records'],
        'jeux' => ['Jeux', 'Games'],
        'histoire' => ['Photos & histoire', 'Photos & history'],
        'musee' => ['Musée & soutien', 'Museum & support'],
        'ecrans' => ['Écrans', 'Screens'],
    ];

    /** Catégories de chaque widget (une ou deux), voir Widget::category(). */
    public const CATEGORY_OF = [
        'ce-jour-la' => ['matchs', 'histoire'],
        'chiffre-du-jour' => ['chiffres'],
        'il-y-a' => ['matchs', 'histoire'],
        'carte-legende' => ['joueurs'],
        'photo-du-jour' => ['histoire'],
        'le-saviez-vous' => ['histoire'],
        'records' => ['chiffres', 'joueurs'],
        'quiz-du-jour' => ['jeux'],
        'defi-du-jour' => ['jeux'],
        'dernier-match' => ['matchs'],
        'saison' => ['chiffres', 'matchs'],
        'palmares' => ['chiffres'],
        'frise' => ['histoire'],
        'bandeau' => ['musee'],
        'histoire-du-duel' => ['matchs'],
        'carte-stades' => ['histoire'],
        'compte-a-rebours' => ['musee'],
        'soutenir' => ['musee'],
        'recherche' => ['musee'],
        'retro-direct' => ['matchs'],
        'playlist' => ['ecrans'],
        'boutique' => ['musee'],
    ];

    /** Libellé d'une catégorie. */
    public static function categoryLabel(string $k, string $lang = 'fr'): string
    {
        return self::CATEGORIES[$k][$lang === 'en' ? 1 : 0] ?? $k;
    }

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
