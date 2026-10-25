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
        '2026-10-rubriques-des-personnes' => 'personRubrics',
        '2026-10-bourgeois-directeur-sportif' => 'bourgeoisDirector',
        '2026-10-validations-retablies' => 'restoreValidations',
        '2026-10-temps-forts-du-recit' => 'recitHighlights',
        '2026-10-reactions-du-recit' => 'recitReactions',
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

    /**
     * Personnes dont le classement (Classement & SEO) ne correspond plus au rôle de l'onglet Identité,
     * parce qu'un historien a coché ou décoché une rubrique avant que les deux soient reliés : le
     * classement l'emporte (« Directeurs sportifs » sans « Joueurs » → dirigeant), et l'adresse suit
     * le premier rôle (/joueurs/x/ → /dirigeants/x/, ancienne adresse redirigée).
     */
    private static function personRubrics(): array
    {
        $ids = Fiches::batch(function () {
            $ids = [];
            foreach (Fiches::all() as $id => $doc) {
                if (($doc['type'] ?? '') !== 'personne' || ($doc['status'] ?? '') === 'corbeille') {
                    continue;
                }
                $fromCats = \App\Admin\FicheForm::rolesFromCategories(array_map('strval', (array) ($doc['categories'] ?? [])));
                $roles = array_values((array) ($doc['personne']['roles'] ?? []));
                if (!$fromCats || (!array_diff($roles, $fromCats) && !array_diff($fromCats, $roles))) {
                    continue;
                }
                $doc = Fiches::fresh((int) $id);
                $new = array_values(array_intersect($roles, $fromCats));
                foreach ($fromCats as $r) {
                    if (!in_array($r, $new, true)) {
                        $new[] = $r;
                    }
                }
                $doc['personne']['roles'] = $new;
                $before = (string) ($doc['path'] ?? '');
                $old = $roles[0] ?? null;
                if ($old !== $new[0] && isset(Paths::PERSON_BASE[$old], Paths::PERSON_BASE[$new[0]]) && str_starts_with($before, '/' . Paths::PERSON_BASE[$old] . '/')) {
                    $doc['path'] = Paths::unique('/' . Paths::PERSON_BASE[$new[0]] . '/' . substr($before, strlen('/' . Paths::PERSON_BASE[$old] . '/')), (int) $id);
                    $doc['slug'] = basename(rtrim($doc['path'], '/'));
                }
                $saved = Fiches::save($doc, self::AUTHOR, 'Rôle aligné sur le classement : ' . implode(', ', $new));
                if ($before !== '' && $before !== ($saved['path'] ?? '')) {
                    Redirects::add($before, (string) $saved['path']);
                }
                $ids[] = (int) $id;
            }
            return $ids;
        });
        return ['message' => count($ids) . ' personne(s) : rôle aligné sur le classement choisi par les historiens', 'ids' => $ids];
    }

    /**
     * M. Bourgeois (fiche 1003037, créée en ligne comme joueur) : directeur sportif, rubrique
     * Dirigeants › Directeurs sportifs, plus dans Joueurs ni dans « Match officiel » ; adresse
     * /dirigeants/… avec redirection. Seulement si la fiche est bien une personne nommée Bourgeois.
     */
    private static function bourgeoisDirector(): array
    {
        $id = 1003037;
        $doc = Fiches::fresh($id);
        if (!Club::isDefault() || !$doc || ($doc['type'] ?? '') !== 'personne' || stripos((string) ($doc['title'] ?? ''), 'bourgeois') === false) {
            return ['message' => 'M. Bourgeois : fiche 1003037 introuvable ou différente, rien de changé', 'ids' => []];
        }
        $players = Categories::descendants(\App\Front\Site::C_JOUEURS);
        $cats = array_values(array_filter(array_map('strval', (array) ($doc['categories'] ?? [])), fn ($c) => !in_array($c, $players, true)));
        foreach (['directeurs-sportifs', \App\Front\Site::C_DIRIGEANTS] as $c) {
            if (Categories::get($c) && !in_array($c, $cats, true)) {
                $cats[] = $c;
            }
        }
        $doc['categories'] = $cats;
        $doc['personne']['roles'] = ['dirigeant'];
        if (trim((string) ($doc['personne']['subtitle'] ?? '')) === '') {
            $doc['personne']['subtitle'] = 'Directeur sportif';
        }
        $before = (string) ($doc['path'] ?? '');
        if (preg_match('#^/joueurs/(.+)$#', $before, $m)) {
            $doc['path'] = Paths::unique('/dirigeants/' . $m[1], $id);
            $doc['slug'] = basename(rtrim($doc['path'], '/'));
        }
        $saved = Fiches::save($doc, self::AUTHOR, 'Classé directeur sportif (et non joueur)');
        if ($before !== '' && $before !== ($saved['path'] ?? '')) {
            Redirects::add($before, (string) $saved['path']);
        }
        // Plus dans la liste « Joueurs › Match officiel ».
        if (\App\Data\OfficialPlayers::enabled() && \App\Data\OfficialPlayers::has($id)) {
            $a = \App\Data\OfficialPlayers::adjustments();
            \App\Data\OfficialPlayers::saveAdjustments(array_diff($a['add'], [$id]), array_merge($a['remove'], [$id]), self::AUTHOR);
        }
        return ['message' => 'M. Bourgeois classé directeur sportif (' . ($saved['path'] ?? '') . ')', 'ids' => [$id]];
    }

    /**
     * Validations effacées par l'ancienne règle (toute modification retirait la validation, même une
     * correction d'orthographe) : rétablies d'après le journal d'activité, avec leur auteur et leur date.
     */
    private static function restoreValidations(): array
    {
        $lost = Validation::lostFromJournal();
        Validation::restore($lost);
        // Visible dans le Journal du back-office : quelles fiches ont retrouvé leur validation.
        foreach (array_keys($lost) as $id) {
            $s = \App\Data\Index::get((int) $id);
            \App\Data\Activity::log(['name' => 'Rétablissement des validations'], 'a rétabli la validation (validée par ' . $lost[$id]['by'] . ') de la fiche', ['id' => (int) $id, 'title' => $s['title'] ?? '', 'type' => $s['type'] ?? null], ['force' => true]);
        }
        return ['message' => count($lost) . ' validation(s) rétablie(s) d’après le journal', 'ids' => array_map('intval', array_keys($lost))];
    }

    /**
     * Tous les matchs : les actions minute par minute du résumé (récit) passent dans les temps forts
     * (« Compo et événements »), buts en gras marqués « But » ; les temps forts déjà saisis restent.
     */
    private static function recitHighlights(): array
    {
        $stats = ['added' => 0];
        $ids = Fiches::batch(function () use (&$stats) {
            $ids = [];
            foreach (Fiches::all() as $id => $doc) {
                if (($doc['type'] ?? '') !== 'match' || ($doc['status'] ?? '') === 'corbeille') {
                    continue;
                }
                $doc = Fiches::fresh((int) $id);
                $n = count((array) ($doc['match']['highlights'] ?? []));
                if (!RecitHighlights::apply($doc)) {
                    continue;
                }
                $stats['added'] += count($doc['match']['highlights']) - $n;
                Fiches::save($doc, ['name' => 'Temps forts du récit'], 'Temps forts repris du récit (minute par minute, buts en gras)');
                $ids[] = (int) $id;
            }
            return $ids;
        });
        return ['message' => count($ids) . ' match(s) : temps forts repris du récit (' . $stats['added'] . ' ajouté(s), buts marqués)', 'ids' => $ids];
    }

    /**
     * Réactions d'après match tapées en puces dans le récit (section « Réactions d'après match ») :
     * reprises dans les réactions (encadrés avec guillemet) des matchs dont la liste est vide ou ne
     * contient que des attentes (« À venir », « Néant ») ; les listes déjà remplies ne bougent pas.
     */
    private static function recitReactions(): array
    {
        $stats = ['added' => 0];
        $ids = Fiches::batch(function () use (&$stats) {
            $ids = [];
            foreach (Fiches::all() as $id => $doc) {
                if (($doc['type'] ?? '') !== 'match' || ($doc['status'] ?? '') === 'corbeille') {
                    continue;
                }
                $doc = Fiches::fresh((int) $id);
                if (!RecitReactions::apply($doc)) {
                    continue;
                }
                $stats['added'] += count($doc['match']['reactions']);
                Fiches::save($doc, ['name' => 'Réactions du récit'], 'Réactions d\'après match reprises du récit (à la place de « À venir » / « Néant »)');
                $ids[] = (int) $id;
            }
            return $ids;
        });
        return ['message' => count($ids) . ' match(s) : réactions d\'après match reprises du récit (' . $stats['added'] . ' réaction(s))', 'ids' => $ids];
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
