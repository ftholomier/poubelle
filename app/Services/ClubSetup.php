<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Club;
use App\Core\JsonStore;

/**
 * Démarrage d'un nouveau musée (php bin/console.php club-creer <id>) : rubriques de départ, vides.
 * Modèle commun : config/clubs/_modele/categories.json (rubriques du musée historique, sans celles
 * qui lui sont propres) ; décennies et saisons calculées depuis l'année de fondation du profil.
 * Ne touche jamais aux rubriques d'un musée qui en a déjà.
 */
final class ClubSetup
{
    /** @return string message */
    public static function categories(): string
    {
        $file = DATA_PATH . '/categories.json';
        if (is_file($file) && (JsonStore::read($file, []) ?: [])) {
            return 'Rubriques déjà présentes : rien changé.';
        }
        $model = json_decode((string) file_get_contents(APP_ROOT . '/config/clubs/_modele/categories.json'), true) ?: [];
        $out = [];
        $id = 1;
        foreach ($model as $key => $c) {
            if (str_contains((string) $c['name'], '{')) {
                $c['name'] = mb_strtoupper(club((string) $c['name'])); // « NOS {nickname} » → « NOS PARISIENS »
            }
            $out[$key] = ['id' => $id++] + $c + ['season' => null, 'old_path' => null, 'order' => [], 'wp_count' => 0];
        }
        // Décennies et saisons, de la fondation à la saison en cours.
        $from = (int) Club::s('founded') ?: (int) date('Y');
        $to = (int) date('n') >= 7 ? (int) date('Y') : (int) date('Y') - 1;
        for ($y = $from; $y <= $to; $y++) {
            $dec = intdiv($y, 10) * 10;
            $short = $dec < 2000 ? (string) ($dec % 100) : (string) $dec;
            $dkey = 'annees-' . $short . '-fc-sochaux-retro-fcsm';
            $out[$dkey] ??= ['id' => $id++, 'slug' => $dkey, 'name' => 'Années ' . $short, 'label' => null, 'position' => $dec, 'description' => '',
                'parent' => 'matchs-fc-sochaux-retro-fcsm', 'path' => '/matchs/annees-' . $short . '/', 'technical' => false, 'season' => null, 'old_path' => null, 'order' => [], 'wp_count' => 0];
            $season = $y . '-' . ($y + 1);
            $out[$season] = ['id' => $id++, 'slug' => $season, 'name' => $season, 'label' => null, 'position' => null, 'description' => '',
                'parent' => $dkey, 'path' => '/matchs/' . $season . '/', 'technical' => false, 'season' => $season, 'old_path' => null, 'order' => [], 'wp_count' => 0];
        }
        JsonStore::write($file, $out);
        return count($out) . ' rubriques créées (' . ($to - $from + 1) . ' saisons depuis ' . $from . ').';
    }
}
