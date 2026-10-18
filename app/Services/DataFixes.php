<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Club;
use App\Core\JsonStore;
use App\Data\Categories;
use App\Data\Fiches;
use App\Data\Paths;
use App\Data\Redirects;

/**
 * Corrections de données ponctuelles, livrées avec le code et appliquées une seule fois par la
 * tâche planifiée « corrections » (les données en ligne ne sont jamais remplacées par une mise à
 * jour). Chaque correction est notée dans storage/corrections.json avec ce qu'elle a changé.
 */
final class DataFixes
{
    public const FILE = STORAGE_PATH . '/corrections.json';
    private const AUTHOR = ['name' => 'Rangement des saisons'];

    /** Corrections à appliquer : clé (jamais réutilisée) => méthode. */
    private const FIXES = [
        '2026-10-avant-saison-2002-2009' => 'preseason2002',
        '2026-10-juillet-1978' => 'july1978',
    ];

    /** Applique les corrections pas encore faites ; compte rendu ou null s'il n'y a rien à faire. */
    public static function run(): ?string
    {
        $done = JsonStore::read(self::FILE, []) ?: [];
        $out = [];
        foreach (self::FIXES as $key => $method) {
            if (isset($done[$key])) {
                continue;
            }
            $r = self::$method();
            JsonStore::update(self::FILE, function (?array $all) use ($key, $r) {
                $all = $all ?: [];
                $all[$key] = ['at' => date('c')] + $r;
                return $all;
            }, []);
            $out[] = $r['message'];
        }
        return $out ? implode(' ; ', $out) : null;
    }

    /**
     * Matchs d'avant-saison des années 2000 rangés dans la saison précédente (import des feuilles,
     * qui changeait de saison au 1er août) : juin-juillet 2002 (Intertoto) et juillet 2003 à 2009
     * vont dans la saison qui commence. Saison, rubrique de saison (Classement & SEO) et adresse
     * (avec redirection de l'ancienne) sont mis à jour ; musée historique seulement.
     */
    private static function preseason2002(): array
    {
        return self::preseason('d’avant-saison 2002-2009', fn (int $y, int $mo) => ($y === 2002 && in_array($mo, [6, 7], true)) || ($y >= 2003 && $y <= 2009 && $mo === 7));
    }

    /** Matchs de juillet 1978 (avant-saison) rangés dans la saison 1978-1979. */
    private static function july1978(): array
    {
        return self::preseason('de juillet 1978', fn (int $y, int $mo) => $y === 1978 && $mo === 7);
    }

    /**
     * Range dans la saison qui commence les matchs dont l'année et le mois de la date répondent à
     * $wanted(année, mois) ; musée historique seulement. $label complète le compte rendu.
     */
    private static function preseason(string $label, callable $wanted): array
    {
        if (!Club::isDefault()) {
            return ['message' => 'matchs ' . $label . ' : sans objet pour ce musée', 'ids' => []];
        }
        Categories::ensureSeasons();
        $ids = Fiches::batch(function () use ($wanted) {
            $ids = [];
            foreach (Fiches::all() as $id => $doc) {
                if (($doc['type'] ?? '') !== 'match' || ($doc['status'] ?? '') === 'corbeille') {
                    continue;
                }
                $date = (string) ($doc['match']['date'] ?? '');
                if (!preg_match('/^(\d{4})-(\d{2})-\d{2}/', $date, $m)) {
                    continue;
                }
                [$y, $mo] = [(int) $m[1], (int) $m[2]];
                if (!$wanted($y, $mo)) {
                    continue;
                }
                if (self::moveToSeason((int) $id, $y . '-' . ($y + 1))) {
                    $ids[] = (int) $id;
                }
            }
            return $ids;
        });
        return ['message' => count($ids) . ' match(s) ' . $label . ' rangé(s) dans la bonne saison', 'ids' => $ids];
    }

    /** Range un match dans une saison : champ saison, rubrique de saison, adresse. true s'il a changé. */
    public static function moveToSeason(int $id, string $season): bool
    {
        $orig = $doc = Fiches::fresh($id);
        if (!$doc || !Categories::get($season)) {
            return false;
        }
        $old = (string) ($doc['match']['season'] ?? '');
        $doc['match']['season'] = $season;
        // Rubriques : les autres saisons retirées, la bonne ajoutée (le reste ne bouge pas).
        $cats = array_values(array_filter((array) ($doc['categories'] ?? []), fn ($c) => empty(Categories::get((string) $c)['season']) || $c === $season));
        if (!in_array($season, $cats, true)) {
            $cats[] = $season;
        }
        $doc['categories'] = $cats;
        // Adresse /matchs/<saison>/… : suit la saison ; l'ancienne est redirigée.
        $before = (string) ($doc['path'] ?? '');
        if (preg_match('#^/matchs/(\d{4}-\d{4})/(.+)$#', $before, $p) && $p[1] !== $season) {
            $doc['path'] = Paths::unique('/matchs/' . $season . '/' . $p[2], $id);
            $doc['slug'] = basename(rtrim($doc['path'], '/'));
        }
        $saved = Fiches::save($doc, self::AUTHOR, 'Saison corrigée : ' . ($old ?: '—') . ' → ' . $season . ' (avant-saison)');
        if ($saved === $orig) {
            return false; // déjà bien rangée : Fiches::save ne réécrit rien
        }
        if ($before !== '' && $before !== ($saved['path'] ?? '')) {
            Redirects::add($before, (string) $saved['path']);
        }
        // Ordre manuel de l'ancienne saison : la fiche n'y figure plus.
        if ($old !== '' && $old !== $season && in_array($id, array_map('intval', (array) (Categories::get($old)['order'] ?? [])), true)) {
            $all = Categories::all();
            $all[$old]['order'] = array_values(array_filter($all[$old]['order'], fn ($x) => (int) $x !== $id));
            Categories::save($all, self::AUTHOR);
            Categories::forget();
        }
        return true;
    }
}
