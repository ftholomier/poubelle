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
        '2026-10-feuilles-2022-2023' => 'sheets2223',
        '2026-10-saison-des-feuilles' => 'sheetSeasons',
        '2026-10-vignettes-dailymotion' => 'dailymotionThumbs',
        '2026-10-olympique-de-marseille' => 'marseilleName',
        '2026-10-buteurs-apostrophe' => 'scorersApostrophe',
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

    /**
     * Feuilles de match 2022-2023 vérifiées (Transfermarkt, footballdatabase.eu, Wikipédia…) :
     * compositions provisoires remplacées, amicaux créés, écarts envoyés dans Trouvailles.
     */
    private static function sheets2223(): array
    {
        if (!Club::isDefault()) {
            return ['message' => 'feuilles de match 2022-2023 : sans objet pour ce musée', 'ids' => []];
        }
        $r = VerifiedSheets::import(APP_ROOT . '/app/Resources/import/feuilles-2022-2023.json');
        return ['message' => $r['message'], 'ids' => $r['ids'], 'created' => $r['created'], 'proposals' => $r['proposals']];
    }

    /**
     * Matchs créés par l'import des feuilles de l'association rangés dans la saison de leur fichier
     * (FM_198586 → 1985-1986) : l'import changeait de saison au 1er août, puis au 1er juillet, et
     * envoyait dans la saison précédente les matchs de juillet et les amicaux et la Coupe des Alpes
     * de fin juin. Une saison choisie à la main (ni l'une ni l'autre règle) n'est pas touchée.
     */
    private static function sheetSeasons(): array
    {
        if (!Club::isDefault()) {
            return ['message' => 'saison des feuilles : sans objet pour ce musée', 'ids' => []];
        }
        Categories::ensureSeasons();
        $ids = Fiches::batch(function () {
            $ids = [];
            foreach (Fiches::all() as $id => $doc) {
                $file = (string) ($doc['legacy']['file'] ?? '');
                $date = (string) ($doc['match']['date'] ?? '');
                if (($doc['type'] ?? '') !== 'match' || empty($doc['legacy']['feuilles']) || $file === '' || !preg_match('/^(\d{4})-(\d{2})-\d{2}$/', $date, $d)) {
                    continue;
                }
                $want = FeuillesImport::seasonOf(['file' => $file, 'date' => $date]);
                $now = (string) ($doc['match']['season'] ?? '');
                [$y, $mo] = [(int) $d[1], (int) $d[2]];
                $august = $mo >= 8 ? $y . '-' . ($y + 1) : ($y - 1) . '-' . $y;
                if ($now !== $want && ($now === $august || $now === FcsmStory::seasonFor($date) || $now === '') && self::moveToSeason((int) $id, $want)) {
                    $ids[] = (int) $id;
                }
            }
            return $ids;
        });
        return ['message' => count($ids) . ' match(s) des feuilles de l’association rangé(s) dans la saison de leur feuille', 'ids' => $ids];
    }

    /**
     * Vignettes Dailymotion désormais lues par l'API : les échecs passés (ancienne adresse) sont
     * oubliés pour que la tâche « videos » les retente tout de suite au lieu d'attendre une semaine.
     */
    private static function dailymotionThumbs(): array
    {
        $file = STORAGE_PATH . '/cache/video-thumbs.json';
        $state = JsonStore::read($file, []) ?: [];
        $n = 0;
        foreach (array_keys($state) as $k) {
            if (str_starts_with((string) $k, 'dailymotion-')) {
                unset($state[$k]);
                $n++;
            }
        }
        JsonStore::write($file, $state);
        return ['message' => $n . ' vignette(s) Dailymotion à retenter', 'ids' => []];
    }

    /**
     * Marseille nommé partout « Olympique de Marseille » (au lieu de « Marseille », « Ol. Marseille »,
     * « O. Marseille », « OM ») : équipes du match et titre de la fiche. Les autres clubs marseillais
     * (Endoume, Consolat…) ne sont pas touchés : seul le nom exact de l'équipe est remplacé.
     */
    private static function marseilleName(): array
    {
        if (!Club::isDefault()) {
            return ['message' => 'Olympique de Marseille : sans objet pour ce musée', 'ids' => []];
        }
        $full = 'Olympique de Marseille';
        $ids = Fiches::batch(function () use ($full) {
            $ids = [];
            foreach (Fiches::all() as $id => $doc) {
                if (($doc['type'] ?? '') !== 'match') {
                    continue;
                }
                $old = [];
                foreach (['home', 'away'] as $side) {
                    $name = trim((string) ($doc['match'][$side]['name'] ?? ''));
                    if ($name !== $full && preg_match('/^(?:(?:ol|o)\.?\s*|olympique\s+(?:de\s+)?)?marseille$|^om$/iu', $name)) {
                        $old[] = $name;
                    }
                }
                if (!$old) {
                    continue;
                }
                $doc = Fiches::fresh((int) $id);
                foreach (['home', 'away'] as $side) {
                    if (in_array(trim((string) ($doc['match'][$side]['name'] ?? '')), $old, true)) {
                        $doc['match'][$side]['name'] = $full;
                    }
                }
                foreach (array_unique($old) as $o) {
                    $doc['title'] = (string) preg_replace('/(?<![\w.-])(?<!de )' . preg_quote($o, '/') . '(?![\w-])/u', $full, (string) $doc['title']);
                }
                Fiches::save($doc, self::AUTHOR, 'Nom du club : ' . implode(', ', array_unique($old)) . ' → ' . $full);
                $ids[] = (int) $id;
            }
            return $ids;
        });
        return ['message' => count($ids) . ' match(s) renommé(s) avec « ' . $full . ' »', 'ids' => $ids];
    }

    /**
     * Buteurs des matchs créés par l'import des feuilles écrits « Genghini (24e) » : passés au format
     * du musée « Genghini 24' » (équipes de l'en-tête et ligne des buts). Seuls les textes avec une
     * minute entre parenthèses sont touchés.
     */
    private static function scorersApostrophe(): array
    {
        $ids = Fiches::batch(function () {
            $ids = [];
            $old = '/\(\s*\d{1,3}\s*(?:e|ème)?\b/u';
            foreach (Fiches::all() as $id => $doc) {
                if (($doc['type'] ?? '') !== 'match' || empty($doc['legacy']['feuilles'])) {
                    continue;
                }
                $m = $doc['match'] ?? [];
                $hit = preg_match($old, (string) ($m['goals_text'] ?? ''));
                foreach ((array) ($m['goals'] ?? []) as $g) {
                    $hit = $hit || preg_match($old, (string) ($g['scorers'] ?? ''));
                }
                if (!$hit) {
                    continue;
                }
                $doc = Fiches::fresh((int) $id);
                if (preg_match($old, (string) ($doc['match']['goals_text'] ?? ''))) {
                    $doc['match']['goals_text'] = FeuillesImport::scorersText((string) $doc['match']['goals_text']);
                }
                foreach ((array) ($doc['match']['goals'] ?? []) as $i => $g) {
                    if (preg_match($old, (string) ($g['scorers'] ?? ''))) {
                        $doc['match']['goals'][$i]['scorers'] = FeuillesImport::scorersText((string) $g['scorers']);
                    }
                }
                Fiches::save($doc, self::AUTHOR, 'Buteurs au format du musée (minute avec apostrophe)');
                $ids[] = (int) $id;
            }
            return $ids;
        });
        return ['message' => count($ids) . ' match(s) : buteurs écrits « Nom 24\' »', 'ids' => $ids];
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
