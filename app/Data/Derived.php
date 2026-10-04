<?php
declare(strict_types=1);

namespace App\Data;

use App\Core\JsonStore;

/**
 * Données calculées à partir des fiches, sans aucune saisie :
 * liens joueurs ↔ matchs, saisons, face-à-face, bilans par compétition et par stade,
 * records, « Ce jour-là », contrôles qualité.
 *
 * Recalcul paresseux : une modification marque le cache « sale », le prochain
 * besoin le reconstruit (quelques secondes pour ~3 000 fiches).
 */
final class Derived
{
    private const CACHE = STORAGE_PATH . '/cache/derived.php';
    private const DIRTY = STORAGE_PATH . '/cache/derived.dirty';
    /** Marque « sale » prise en charge par le recalcul en cours (un enregistrement fait pendant le calcul recrée DIRTY). */
    private const CLAIM = STORAGE_PATH . '/cache/derived.building';
    private const VERSION = 7;
    private static ?array $data = null;

    public const OFFICIAL_EXCLUDED = ['Amical', "Coupe d'été", 'Coupes diverses'];

    public static function markDirty(): void
    {
        $dir = dirname(self::DIRTY);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @touch(self::DIRTY);
        self::$data = null;
        self::scheduleRebuild();
    }

    /**
     * Données calculées. Si le cache est périmé, on sert la version précédente et on
     * recalcule après l'envoi de la page (le visiteur ou l'historien n'attend pas).
     */
    public static function get(): array
    {
        if (self::$data !== null) {
            return self::$data;
        }
        if ($d = self::cached()) {
            if (self::isDirty()) {
                self::scheduleRebuild();
            }
            return self::$data = $d;
        }
        return self::$data = self::rebuildLocked();
    }

    /** Dernier calcul enregistré (null s'il manque ou date d'une version précédente). */
    private static function cached(): ?array
    {
        if (!is_file(self::CACHE)) {
            return null;
        }
        $d = include self::CACHE;
        return is_array($d) && ($d['version'] ?? 0) === self::VERSION ? $d : null;
    }

    private static bool $scheduled = false;

    public static function scheduleRebuild(): void
    {
        if (self::$scheduled || PHP_SAPI === 'cli') {
            return;
        }
        self::$scheduled = true;
        register_shutdown_function(function () {
            \App\Core\Response::detach();
            set_time_limit(300);
            // Les données refaites servent aussi aux recalculs qui suivent (les chiffres du FCSM).
            // Recalcul déjà en cours dans un autre processus : on ne l'attend pas.
            self::$data = self::rebuildLocked(false) ?? self::$data;
        });
    }

    public static function isDirty(): bool
    {
        clearstatcache();
        if (!is_file(self::CACHE) || is_file(self::DIRTY)) {
            return true;
        }
        // Recalcul interrompu (délai ou mémoire dépassés) : à refaire.
        return is_file(self::CLAIM) && (@filemtime(self::CLAIM) ?: time()) < time() - 900;
    }

    /** Recalcule si c'est encore nécessaire ; sans attente ($wait = false), null si un recalcul est déjà en cours. */
    private static function rebuildLocked(bool $wait = true): ?array
    {
        $fp = self::lock($wait);
        if (!$fp) {
            return null;
        }
        try {
            // Un autre processus a peut-être reconstruit pendant l'attente.
            if (!self::isDirty() && ($d = self::cached())) {
                return $d;
            }
            return self::build();
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /** Recalcul complet immédiat (console, tâche planifiée), un seul à la fois. */
    public static function rebuild(): array
    {
        $fp = self::lock(true);
        try {
            return self::build();
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /** @return resource|null */
    private static function lock(bool $wait)
    {
        $dir = dirname(self::CACHE);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $fp = fopen(self::CACHE . '.lock', 'c');
        if (!$fp) {
            throw new \RuntimeException('Verrou du recalcul impossible.');
        }
        if (!flock($fp, $wait ? LOCK_EX : LOCK_EX | LOCK_NB)) {
            fclose($fp);
            if ($wait) {
                throw new \RuntimeException('Verrou du recalcul impossible.');
            }
            return null;
        }
        return $fp;
    }

    private static function build(): array
    {
        // La marque « sale » est prise en charge maintenant : un enregistrement fait pendant
        // le calcul la recrée, et un nouveau recalcul suivra.
        clearstatcache();
        if (is_file(self::DIRTY)) {
            @rename(self::DIRTY, self::CLAIM);
        }
        @touch(self::CLAIM);
        // ~200 Mo pour 3 000 fiches : la limite par défaut des hébergements (128 Mo) ne suffit pas.
        $limit = (string) ini_get('memory_limit');
        if ($limit !== '-1' && self::bytes($limit) < 512 * 1048576) {
            @ini_set('memory_limit', '512M');
        }
        try {
            $data = self::compute();
        } catch (\Throwable $e) {
            @touch(self::DIRTY);
            @unlink(self::CLAIM);
            throw $e;
        }
        @unlink(self::CLAIM);
        return self::$data = $data;
    }

    private static function bytes(string $v): int
    {
        $n = (int) $v;
        return match (strtolower(substr(trim($v), -1))) {
            'g' => $n * 1073741824,
            'm' => $n * 1048576,
            'k' => $n * 1024,
            default => $n,
        };
    }

    private static function compute(): array
    {
        $t0 = microtime(true);
        $matches = [];
        $persons = [];
        $articles = [];
        $mediaRefs = [];
        $ficheAlerts = [];
        $read = [];   // fiches lues (les fichiers illisibles manquent)
        $paths = [];  // adresse => fiches
        $cats = Categories::all();
        $library = Media::all();
        $usedFiles = [];  // images de la médiathèque utilisées par les fiches
        $goneFiles = [];  // fiche => fichiers absents du serveur
        $broken = [];     // fiches aux données inattendues, écartées des calculs
        foreach (Fiches::all() as $id => $doc) {
            $read[$id] = true;
            $undo = count($ficheAlerts);
            try {
                $mediaRefs[$id] = Media::refsIn($doc);
                if (($doc['status'] ?? '') === 'corbeille') {
                    continue;
                }
                $vis = Fiches::isVisible($doc);
                $paths[(string) ($doc['path'] ?? '')][] = (int) $id;
                foreach (self::ficheChecks($doc, $vis, $cats, $library) as $a) {
                    $ficheAlerts[] = $a + ['id' => (int) $id];
                }
                [$known, $gone] = self::imageFiles($doc, $library, $mediaRefs[$id]);
                $usedFiles += array_fill_keys($known, true);
                if ($gone) {
                    $goneFiles[(int) $id] = $gone;
                }
                if ($vis && ($places = self::unknownPlaces($doc))) {
                    $ficheAlerts[] = ['sev' => 'basse', 'code' => 'inconnu', 'msg' => 'Information inconnue notée « xx » sur l’ancien site (cachée sur le site public), à compléter ou à retirer : ' . implode(' ; ', $places), 'id' => (int) $id];
                }
                foreach ($doc['videos'] ?? [] as $v) {
                    if ($vis && !in_array($v['provider'] ?? '', ['youtube', 'dailymotion', 'vimeo', 'rutube', 'file'], true)) {
                        $ficheAlerts[] = ['sev' => 'basse', 'code' => 'video', 'msg' => 'Lien vidéo de l’ancien site non reconnu : recoller le bon lien (YouTube, Dailymotion…) dans l’onglet Médias', 'id' => (int) $id];
                        break;
                    }
                }
                // Version anglaise dépassée : le texte français a changé depuis la traduction.
                if ($vis && !empty($doc['i18n']['en']['title']) && \App\Services\Translator::status($doc) === 'stale') {
                    $ficheAlerts[] = !empty($doc['i18n']['en']['_manual'])
                        ? ['sev' => 'moyenne', 'code' => 'traduction', 'ref' => 'manuelle', 'msg' => 'Version anglaise corrigée à la main : le texte français a changé depuis, à revoir (onglet Version EN)', 'id' => (int) $id]
                        : ['sev' => 'basse', 'code' => 'traduction', 'ref' => 'auto', 'msg' => 'Version anglaise dépassée : le texte français a changé depuis (refaite par la traduction automatique, ou depuis l’onglet Version EN)', 'id' => (int) $id];
                }
                if ($doc['type'] === 'match') {
                    $matches[$id] = $doc + ['_visible' => $vis];
                } elseif ($doc['type'] === 'personne') {
                    $persons[$id] = $doc + ['_visible' => $vis];
                } elseif (in_array($doc['type'], ['article', 'page'], true)) {
                    $articles[$id] = ['id' => $id, 'title' => $doc['title'], 'kind' => $doc['article']['kind'] ?? 'article', 'season' => $doc['article']['season'] ?? null, '_visible' => $vis];
                }
            } catch (\Throwable $e) {
                // Données dans un format inattendu (fichier retouché à la main) : fiche écartée des
                // calculs et signalée, au lieu d'empêcher tout le calcul (et l'affichage du site).
                error_log('Fiche ' . $id . ' : ' . $e->getMessage());
                array_splice($ficheAlerts, $undo);
                unset($matches[$id], $persons[$id], $articles[$id]);
                $mediaRefs[$id] ??= [];
                $broken[(int) $id] = true;
            }
        }
        $ficheAlerts = array_merge($ficheAlerts, self::photoAlerts($goneFiles, count($usedFiles)));
        // Champs d'un type inattendu (fichier retouché à la main) : lus correctement, à réparer.
        foreach (Fiches::repaired() as $rid => $fields) {
            if (isset($read[$rid]) && !isset($broken[$rid])) {
                $ficheAlerts[] = ['sev' => 'moyenne', 'code' => 'fichier', 'ref' => 'format', 'id' => (int) $rid,
                    'msg' => 'Données dans un format inattendu (' . implode(', ', array_slice($fields, 0, 4)) . (count($fields) > 4 ? '…' : '') . ') : lues correctement en attendant ; ouvrir la fiche et l’enregistrer pour les réparer'];
            }
        }
        // Fichiers de fiches illisibles (JSON abîmé, envoi par FTP interrompu), incomplets ou mal numérotés.
        foreach (glob(Fiches::DIR . '/*.json') ?: [] as $file) {
            if (!isset($read[(int) basename($file, '.json')])) {
                $ficheAlerts[] = ['sev' => 'haute', 'code' => 'fichier', 'msg' => 'Fichier de fiche illisible, incomplet ou mal numéroté : la fiche n’apparaît nulle part. À remplacer par sa dernière sauvegarde.', 'id' => null, 'title' => 'data/fiches/' . basename($file)];
            }
        }
        // Deux fiches à la même adresse : une seule des deux s'affiche sur le site.
        foreach ($paths as $path => $ids) {
            foreach ($path !== '' && count($ids) > 1 ? $ids : [] as $id) {
                $others = array_map(fn ($o) => '« ' . (Index::get($o)['title'] ?? $o) . ' »', array_diff($ids, [$id]));
                $ficheAlerts[] = ['sev' => 'haute', 'code' => 'adresse', 'msg' => 'Même adresse (' . $path . ') que ' . implode(', ', $others) . ' : une seule des deux fiches s’affiche (adresse à changer dans Classement & SEO)', 'id' => $id, 'ref' => 'meme:' . $path];
            }
        }

        // ---------------------------------------------------------- personnes
        $byKey = [];
        $byLast = [];
        $byLetters = [];
        $nameKeys = []; // pid => [clé, mots] pour les rapprochements approchés
        foreach ($persons as $pid => $p) {
            $pp = $p['personne'];
            $names = array_filter(array_merge([
                $pp['display_name'] ?: $p['title'],
                trim(($pp['first_name'] ?? '') . ' ' . ($pp['last_name'] ?? '')),
                $p['title'],
                $pp['nickname'] ?? '',
            ], (array) ($pp['aliases'] ?? [])), fn ($n) => is_string($n) && trim($n) !== '');
            foreach (array_unique(array_map([Names::class, 'personKey'], $names)) as $k) {
                if ($k !== '') {
                    $byKey[$k][] = $pid;
                    $nameKeys[$pid][] = [$k, explode(' ', $k)];
                }
            }
            foreach (array_unique(array_map([Names::class, 'letterKey'], $names)) as $k) {
                if (strlen($k) >= 6) {
                    $byLetters[$k][] = $pid;
                }
            }
            $last = implode(' ', Names::tokens($pp['last_name'] ?? ''));
            if ($last !== '') {
                $byLast[$last][] = $pid;
            }
        }
        $period = function (array $p): array {
            $pp = $p['personne'];
            $years = [];
            foreach (['arrival', 'departure', 'arrival_coach', 'departure_coach', 'trial'] as $k) {
                if (!empty($pp[$k]['iso'])) {
                    $years[] = (int) substr((string) $pp[$k]['iso'], 0, 4);
                }
            }
            return $years ? [min($years) - 1, max($years) + 1] : [null, null];
        };
        $periods = array_map($period, $persons);

        $approx = [];
        $approxUsed = []; // nom de composition => [fiche, manière, nombre] : signalés aux historiens
        $resolve = function (string $name, ?string $date) use ($byKey, $byLast, $byLetters, $nameKeys, $periods, $persons, &$approx, &$approxUsed): ?int {
            $year = $date ? (int) substr($date, 0, 4) : null;
            $pick = function (array $ids) use ($year, $periods) {
                $ids = array_values(array_unique($ids));
                if (count($ids) === 1) {
                    return $ids[0];
                }
                if ($year) {
                    $in = array_values(array_filter($ids, fn ($id) => $periods[$id][0] !== null && $year >= $periods[$id][0] && $year <= $periods[$id][1]));
                    if (count($in) === 1) {
                        return $in[0];
                    }
                }
                return null;
            };
            $k = Names::personKey($name);
            if ($k !== '' && isset($byKey[$k])) {
                return $pick($byKey[$k]);
            }
            // Nom de famille seul (« PIERRE », « Fofana »), avec contrôle de période.
            $last = Names::lineupLastName($name);
            if ($last !== '' && isset($byLast[$last]) && ($pid = $pick($byLast[$last]))) {
                return $pid;
            }
            // Nom incomplet : jamais vers un joueur dont la période au club (connue) exclut la date.
            $pickNear = function (array $ids) use ($year, $periods, $pick) {
                if ($year) {
                    $ids = array_filter($ids, fn ($id) => $periods[$id][0] === null || ($year >= $periods[$id][0] && $year <= $periods[$id][1]));
                }
                return $ids ? $pick($ids) : null;
            };
            // Même nom écrit autrement : apostrophe, trait d'union, espace (« N'Diaye » / « Ndiaye »).
            $lk = Names::letterKey($name);
            if (isset($byLetters[$lk]) && ($pid = $pick($byLetters[$lk]))) {
                $approxUsed[$name] = [$pid, 'graphie', ($approxUsed[$name][2] ?? 0) + 1];
                return $pid;
            }
            if ($k === '') {
                return null;
            }
            // Rapprochements approchés, calculés une fois par nom : une lettre de différence
            // (deux pour un nom long), ou nom incomplet (« Carlao » pour « Carlao Roberto Da Cruz »).
            // Retenus seulement si un seul joueur correspond (ou un seul à cette période).
            if (!isset($approx[$k])) {
                $toks = explode(' ', $k);
                $typo = [];
                $part = [];
                $max = strlen($k) >= 15 ? 2 : (strlen($k) >= 9 ? 1 : 0);
                foreach ($nameKeys as $pid => $list) {
                    foreach ($list as [$pk, $ptoks]) {
                        if ($max && abs(strlen($pk) - strlen($k)) <= $max && levenshtein($k, $pk) <= $max) {
                            $typo[] = $pid;
                        }
                        if (count($ptoks) > count($toks) && !array_diff($toks, $ptoks) && (count($toks) > 1 || strlen($k) >= 4)) {
                            $part[] = $pid;
                        }
                    }
                }
                $approx[$k] = [array_values(array_unique($typo)), array_values(array_unique($part))];
            }
            foreach ($approx[$k] as $i => $ids) {
                if ($ids && ($pid = $i === 0 ? $pick($ids) : $pickNear($ids))) {
                    $approxUsed[$name] = [$pid, $i === 0 ? 'orthographe' : 'nom incomplet', ($approxUsed[$name][2] ?? 0) + 1];
                    return $pid;
                }
            }
            return null;
        };

        // ---------------------------------------------------------- clubs et stades
        $clubs = Collections::get('clubs', []);
        $stades = Collections::get('stades', []);
        $clubIds = array_column($clubs, 'id');
        $stadeIds = array_column($stades, 'id');
        $clubAlias = [];
        foreach ($clubs as $c) {
            $clubAlias[Names::clubKey($c['name'])] = $c['id'];
            foreach ($c['aliases'] ?? [] as $a) {
                $clubAlias[Names::clubKey($a)] = $c['id'];
            }
        }
        $stadeAlias = [];
        foreach ($stades as $s) {
            $stadeAlias[Names::stadiumKey($s['name'])] = $s['id'];
            foreach ($s['aliases'] ?? [] as $a) {
                $stadeAlias[Names::stadiumKey($a)] = $s['id'];
            }
        }
        $clubNames = [];
        $stadeNames = [];
        $newClubs = false;
        $newStades = false;

        // ---------------------------------------------------------- matchs
        $M = [];          // résumé par match
        $apps = [];       // apparitions : [person, match, goals, minutes, yellow, red, role, captain]
        $scorers = [];    // buteurs sochaliens : match => [[person|null, nom, minutes, penaltys]]
        $unlinked = [];   // noms de composition sans fiche
        $quality = $ficheAlerts;
        $tableUse = [];
        $onThisDay = [];

        foreach ($matches as $mid => $doc) {
            $undo = [count($apps), count($quality)];
            try {
                $m = $doc['match'];
                $sochauxHome = (bool) ($m['sochaux_home'] ?? true);
                $oppName = $sochauxHome ? ($m['away']['name'] ?? '') : ($m['home']['name'] ?? '');
                $clubId = null;
                if ($oppName !== '' && !preg_match('/sochaux/iu', $oppName)) {
                    $key = Names::clubKey($oppName);
                    $clubId = $m['opponent_club'] ?: ($clubAlias[$key] ?? null);
                    if (!$clubId) {
                        $clubId = $key;
                        $clubAlias[$key] = $clubId;
                        $clubs[] = ['id' => $clubId, 'name' => $oppName, 'aliases' => [], 'city' => '', 'country' => '', 'logo' => null, 'lat' => null, 'lng' => null, 'auto' => true];
                        $newClubs = true;
                    }
                    $clubNames[$clubId][$oppName] = ($clubNames[$clubId][$oppName] ?? 0) + 1;
                }
                $stadeId = null;
                // Stade inconnu (« xx » de l'ancien site) : pas de stade créé dans le référentiel.
                if (!empty($m['stadium']) && is_string($m['stadium']) && !\App\Front\Unknown::has($m['stadium'])) {
                    $sk = Names::stadiumKey($m['stadium']);
                    $stadeId = $m['stadium_id'] ?: ($stadeAlias[$sk] ?? null);
                    if (!$stadeId && $sk !== '') {
                        $stadeId = $sk;
                        $stadeAlias[$sk] = $stadeId;
                        $city = preg_match('/\(([^)]+)\)/u', $m['stadium'], $cm) ? $cm[1] : '';
                        $stades[] = ['id' => $stadeId, 'name' => trim(preg_replace('/\s*\([^)]*\)\s*/u', ' ', $m['stadium'])), 'aliases' => [], 'city' => '', 'department' => $city,
                            'country' => '', 'lat' => null, 'lng' => null, 'auto' => true];
                        $newStades = true;
                    }
                    if ($stadeId) {
                        $stadeNames[$stadeId][$m['stadium']] = ($stadeNames[$stadeId][$m['stadium']] ?? 0) + 1;
                    }
                }

                $score = is_array($m['score'] ?? null) ? $m['score'] : null;
                $us = $them = null;
                if (isset($score['home'])) {
                    // Entiers (un fichier retouché à la main peut contenir « 2 » ou 2.0)
                    [$us, $them] = array_map(fn ($v) => self::goals($v), $sochauxHome ? [$score['home'], $score['away'] ?? null] : [$score['away'] ?? null, $score['home']]);
                }
                // Date valide seulement (une date impossible est signalée par matchChecks)
                $date = $m['date'] ?? null;
                $date = is_string($date) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $dm) && checkdate((int) $dm[2], (int) $dm[3], (int) $dm[1]) ? $date : null;
                $decade = $date ? ((int) floor((int) substr($date, 0, 4) / 10) * 10) : null;
                $aet = (bool) ($score['aet'] ?? false);
                $M[$mid] = [
                    'id' => $mid,
                    'v' => $doc['_visible'],
                    'path' => $doc['path'],
                    'title' => $doc['title'],
                    'date' => $date,
                    'season' => $m['season'] ?? null,
                    'decade' => $decade,
                    'comp' => $m['competition'] ?? 'Championnat',
                    'label' => $m['competition_label'] ?? '',
                    'round' => $m['round'] ?? '',
                    'home' => $m['home']['name'] ?? '',
                    'away' => $m['away']['name'] ?? '',
                    'sh' => $sochauxHome,
                    'club' => $clubId,
                    'opp' => $oppName,
                    'stade' => $stadeId,
                    'us' => $us,
                    'them' => $them,
                    'extra' => $score['extra'] ?? null,
                    'result' => $m['result'] ?? null,
                    // Affluence impossible (plus de 90 000 : faute de frappe) écartée des records et bilans
                    'spectators' => isset($m['spectators']) && (int) $m['spectators'] > 0 && (int) $m['spectators'] <= 90000 ? (int) $m['spectators'] : null,
                    'image' => $doc['featured_image'] ?? null,
                    'event' => $m['event'] ?? null,
                    'hl' => count($m['highlights'] ?? []),
                ];
                if ($date && $doc['_visible']) {
                    $onThisDay[substr($date, 5, 5)][] = $mid;
                }

                // Composition → apparitions
                $rows = $m['lineup']['rows'] ?? [];
                if (!empty($m['lineup']['source_table'])) {
                    $tableUse[$m['lineup']['source_table']][] = $mid;
                }
                $full = $aet ? 120 : 90;
                $teamGoals = 0;
                $inMatch = [];   // joueur => [rang de son apparition, minutes de ses buts]
                $dupNames = [];  // joueurs inscrits deux fois dans la composition
                foreach ($rows as $r) {
                    $pos = strtoupper((string) ($r['position'] ?? ''));
                    $name = (string) ($r['name'] ?? '');
                    if ($name === '') {
                        continue;
                    }
                    $pid = $r['person_id'] ?? null;
                    if (!$pid || !isset($persons[$pid])) {
                        $pid = $resolve($name, $date);
                    }
                    $g = count($r['goals'] ?? []);
                    $min = 0;
                    $role = $pos === 'E' ? 'coach' : 'player';
                    if ($role === 'player') {
                        $in = $r['sub_in'] ?? null;
                        $out = $r['sub_out'] ?? null;
                        $mi = fn ($v) => (int) explode('+', (string) $v)[0];
                        if ($pos === 'R') {
                            // Entré puis sorti : temps passé entre les deux.
                            $end = ($out !== null && $out !== '' && $mi($out) > $mi((string) $in)) ? $mi($out) : $full;
                            $min = ($in !== null && $in !== '') ? max(1, $end - $mi($in)) : 0;
                        } else {
                            $min = ($out !== null && $out !== '') ? $mi($out) : $full;
                        }
                    }
                    if ($role === 'player' && $pos === 'R' && $min === 0) {
                        // Remplaçant non entré : pas d'apparition, mais présence sur la feuille.
                        $role = 'bench';
                    }
                    $mins = array_values(array_map('strval', $r['goals'] ?? []));
                    $prev = $pid && $role !== 'coach' ? ($inMatch[$pid] ?? null) : null;
                    if ($prev !== null) {
                        // Même joueur inscrit deux fois dans la composition : une seule apparition
                        // (buts sans doublon de minute, temps de jeu plafonné à la durée du match).
                        $dupNames[] = Names::display($name);
                        $a = &$apps[$prev[0]];
                        $all = array_merge($prev[1], $mins);
                        $known = !in_array('', $all, true);
                        $new = $known ? array_values(array_diff(array_unique($mins), $prev[1])) : $mins;
                        $a[2] = $known ? count(array_unique($all)) : $a[2] + $g;
                        $a[3] = min($full, $a[3] + $min);
                        $a[4] += count($r['yellow'] ?? []);
                        $a[5] += count($r['red'] ?? []);
                        if ($a[6] === 'bench' && $role === 'player') {
                            [$a[6], $a[8]] = ['player', $pos];
                        }
                        $a[7] = $a[7] || !empty($r['captain']);
                        unset($a);
                        $inMatch[$pid][1] = array_values(array_unique($all));
                        $g = count($new);
                        $mins = $new;
                    }
                    $teamGoals += $g; // après le dédoublonnage : un but noté deux fois ne compte qu'une fois
                    if ($g > 0) {
                        // Minutes des buts et penaltys marqués (« 32' s.p. », « 12' sp et 56' »).
                        $scorers[$mid][] = [$pid ?: null, Names::display($name), $mins,
                            preg_match_all('/(?<![\p{L}])s\.?\s?p\.?(?![\p{L}])|\bpen(?:alty)?\b/iu', (string) ($r['goals_text'] ?? ''))];
                    }
                    if ($prev !== null) {
                        continue;
                    }
                    if ($pid) {
                        if ($role !== 'coach') {
                            $inMatch[$pid] = [count($apps), $mins];
                        }
                        $apps[] = [$pid, $mid, $g, $min, count($r['yellow'] ?? []), count($r['red'] ?? []), $role, (bool) ($r['captain'] ?? false), $pos];
                    } elseif ($doc['_visible']) {
                        $unlinked[Names::personKey($name)]['name'] = Names::display($name);
                        $unlinked[Names::personKey($name)]['matches'][] = $mid;
                    }
                }

                // Contrôles qualité du match (les buts contre son camp de l'adversaire, cités avec
                // les buteurs sochaliens, comptent pour Sochaux sans figurer dans la composition).
                foreach ($m['goals'] ?? [] as $gl) {
                    if (stripos((string) ($gl['team'] ?? ''), 'sochaux') !== false) {
                        $teamGoals += preg_match_all('/c\s*\.?\s*s\s*\.?\s*c|contre son camp/iu', (string) ($gl['scorers'] ?? ''));
                    }
                }
                if ($dupNames && $doc['_visible'] && !in_array($m['competition'] ?? '', self::OFFICIAL_EXCLUDED, true)) {
                    $quality[] = ['sev' => 'moyenne', 'code' => 'doublon', 'msg' => 'Joueur inscrit deux fois dans la composition : ' . implode(', ', array_unique($dupNames)) . ' (compté une seule fois)', 'id' => $mid];
                }
                if ($us !== null && $rows && $teamGoals > 0 && $teamGoals !== $us) {
                    $quality[] = ['sev' => 'haute', 'code' => 'buts', 'msg' => "Total des buts ($us) ≠ somme des buteurs de la composition ($teamGoals)", 'id' => $mid];
                }
                if (!empty($m['date_text']) && is_string($m['date_text']) && $date) {
                    $pd = self::frDate($m['date_text']);
                    $wd = self::weekday($m['date_text']);
                    if ($pd && $pd !== $date) {
                        $quality[] = ['sev' => 'moyenne', 'code' => 'date', 'ref' => 'ecart', 'msg' => 'Date en toutes lettres (« ' . $m['date_text'] . ' ») ≠ date de la fiche (' . date('d/m/Y', strtotime($date)) . ')', 'id' => $mid];
                    } elseif (!$pd) {
                        $quality[] = ['sev' => 'basse', 'code' => 'date', 'ref' => 'illisible', 'msg' => 'Date en toutes lettres illisible (« ' . $m['date_text'] . ' ») ; date de la fiche : ' . date('d/m/Y', strtotime($date)), 'id' => $mid];
                    } elseif ($wd !== null && $wd !== (int) date('N', strtotime($date))) {
                        $quality[] = ['sev' => 'basse', 'code' => 'date', 'ref' => 'jour', 'msg' => 'Jour de la semaine incohérent dans « ' . $m['date_text'] . ' » : le ' . date('d/m/Y', strtotime($date)) . ' était un ' . self::WEEKDAYS[(int) date('N', strtotime($date)) - 1] . ' (jour ou date à vérifier)', 'id' => $mid];
                    }
                }
                $ex = (string) ($m['score']['extra'] ?? '');
                if ($doc['_visible'] && stripos($ex, 'tab') !== false && empty($m['score']['pens'])) {
                    $quality[] = ['sev' => 'moyenne', 'code' => 'tab', 'msg' => 'Tirs au but sans le score de la séance (« ' . $ex . ' ») : le saisir dans Score › Tirs au but', 'id' => $mid];
                }
                if ((int) ($m['spectators'] ?? 0) > 90000) {
                    $quality[] = ['sev' => 'haute', 'code' => 'affluence', 'msg' => 'Affluence improbable (' . number_format((int) $m['spectators'], 0, ',', ' ') . ' spectateurs) : faute de frappe ? Elle est écartée des records.', 'id' => $mid];
                }
                if (empty($m['referee']) && $decade && $decade >= 1970 && $doc['_visible'] && ($m['competition'] ?? '') !== 'Amical') {
                    $quality[] = ['sev' => 'basse', 'code' => 'arbitre', 'msg' => 'Arbitre non renseigné', 'id' => $mid];
                }
                foreach (self::matchChecks($m, $us, $them, $doc['_visible']) as $a) {
                    $quality[] = $a + ['id' => $mid];
                }
            } catch (\Throwable $e) {
                // Match aux données inattendues (fichier retouché à la main) : retiré des statistiques
                // (apparitions, buteurs, bilans) et signalé, le reste du calcul continue.
                error_log('Match ' . $mid . ' : ' . $e->getMessage());
                unset($a);
                array_splice($apps, $undo[0]);
                array_splice($quality, $undo[1]);
                unset($M[$mid], $scorers[$mid]);
                foreach ($onThisDay as $k => $ids) {
                    $onThisDay[$k] = array_values(array_diff($ids, [$mid]));
                }
                foreach ($tableUse as $k => $ids) {
                    $tableUse[$k] = array_values(array_diff($ids, [$mid]));
                }
                foreach ($unlinked as $k => $u) {
                    $unlinked[$k]['matches'] = array_values(array_diff($u['matches'] ?? [], [$mid]));
                    if (!$unlinked[$k]['matches']) {
                        unset($unlinked[$k]);
                    }
                }
                $broken[(int) $mid] = true;
            }
        }

        // Même match saisi deux fois : même jour, même adversaire (fiches publiées, hors tournois).
        $sameDay = [];
        foreach ($M as $mid => $x) {
            if ($x['v'] && $x['date'] && empty($x['event']) && ($x['club'] ?? '') !== '') {
                $sameDay[$x['date'] . '|' . $x['club']][] = $mid;
            }
        }
        foreach ($sameDay as $ids) {
            foreach (count($ids) > 1 ? $ids : [] as $mid) {
                $others = array_map(fn ($o) => '« ' . $M[$o]['title'] . ' »', array_diff($ids, [$mid]));
                $quality[] = ['sev' => 'haute', 'code' => 'doublon-match', 'msg' => 'Même jour et même adversaire que ' . implode(', ', $others) . ' : match saisi deux fois ? (compté deux fois dans les bilans)', 'id' => $mid];
            }
        }

        // Tableaux de composition partagés par plusieurs matchs
        foreach ($tableUse as $tid => $mids) {
            if (count($mids) > 1) {
                foreach ($mids as $mid) {
                    $others = array_map(fn ($o) => $M[$o]['title'], array_diff($mids, [$mid]));
                    $quality[] = ['sev' => 'haute', 'code' => 'tableau', 'msg' => 'Même tableau de composition que : ' . implode(', ', array_slice($others, 0, 3)), 'id' => $mid];
                }
            }
        }

        // Dates des personnes impossibles ou invraisemblables : à vérifier dans la fiche.
        $today = date('Y-m-d');
        foreach ($persons as $pid => $p) {
            $pp = $p['personne'];
            $b = (string) ($pp['birth']['date']['iso'] ?? '');
            $dth = (string) ($pp['death']['date']['iso'] ?? '');
            $arr = (string) ($pp['arrival']['iso'] ?? '');
            $dep = (string) ($pp['departure']['iso'] ?? '');
            $by = $b !== '' ? (int) substr($b, 0, 4) : null;
            $player = in_array('joueur', (array) ($pp['roles'] ?? []), true);
            // Arrivée tardive normale pour un entraîneur-joueur ou un dirigeant.
            $onlyPlayer = $player && !array_intersect((array) ($pp['roles'] ?? []), ['entraineur', 'dirigeant']);
            $msgs = [];
            if ($by && ($b > $today || $by < 1860 || ($player && $by > (int) date('Y') - 14))) {
                $msgs[] = "naissance improbable ($b)";
            }
            if ($b !== '' && $dth !== '' && $dth < $b) {
                $msgs[] = "décès ($dth) avant la naissance ($b)";
            }
            if ($arr !== '' && $dep !== '' && substr($dep, 0, 7) < substr($arr, 0, 7)) {
                $msgs[] = "départ ($dep) avant l’arrivée ($arr)";
            }
            foreach (['naissance' => $b, 'décès' => $dth, 'arrivée' => $arr, 'départ' => $dep] as $what => $iso) {
                $mo = (int) substr($iso, 5, 2);
                if ((strlen($iso) >= 10 && !checkdate($mo, (int) substr($iso, 8, 2), (int) substr($iso, 0, 4))) || (strlen($iso) === 7 && ($mo < 1 || $mo > 12))) {
                    $msgs[] = "date de $what impossible ($iso)";
                }
            }
            if ($by && $arr !== '' && $player) {
                $age = (int) substr($arr, 0, 4) - $by;
                if ($age < 5 || ($age > 38 && $onlyPlayer)) {
                    $msgs[] = "arrivée au club à $age ans (né en $by, arrivée en " . substr($arr, 0, 4) . ')';
                }
            }
            if ($msgs) {
                $quality[] = ['sev' => 'moyenne', 'code' => 'dates', 'msg' => 'Dates à vérifier : ' . implode(' ; ', $msgs), 'id' => $pid];
            }
            if ($p['_visible'] && empty($pp['roles'])) {
                $quality[] = ['sev' => 'basse', 'code' => 'role', 'msg' => 'Aucune rubrique cochée (joueur, entraîneur, dirigeant…) dans l’onglet Identité', 'id' => $pid];
            }
        }

        // Deux fiches de personne au même nom : fiche en double, ou homonymes à distinguer
        // (dates de naissance connues et différentes : rien à signaler).
        $sameName = [];
        foreach ($persons as $pid => $p) {
            $k = $p['_visible'] ? Names::personKey((string) (($p['personne']['display_name'] ?? '') ?: $p['title'])) : '';
            if ($k !== '') {
                $sameName[$k][] = $pid;
            }
        }
        foreach ($sameName as $ids) {
            $births = array_map(fn ($i) => (string) ($persons[$i]['personne']['birth']['date']['iso'] ?? ''), $ids);
            if (count($ids) < 2 || (!in_array('', $births, true) && count(array_unique($births)) === count($births))) {
                continue;
            }
            foreach ($ids as $pid) {
                $others = array_map(fn ($o) => '« ' . $persons[$o]['title'] . ' » (fiche n° ' . $o . ')', array_diff($ids, [$pid]));
                $quality[] = ['sev' => 'moyenne', 'code' => 'homonyme', 'msg' => 'Même nom que ' . implode(', ', $others) . ' : fiche en double, ou homonymes à distinguer par la date de naissance', 'id' => $pid];
            }
        }

        // Tableaux de statistiques recopiés d'une fiche à l'autre (modèle de l'ancien site) :
        // les chiffres affichés sont ceux d'un autre joueur.
        $statSig = [];
        foreach ($persons as $pid => $p) {
            if (!empty($p['personne']['stats']['rows'])) {
                $statSig[md5(json_encode($p['personne']['stats']['rows']))][] = $pid;
            }
        }
        foreach ($statSig as $ids) {
            foreach (count($ids) > 1 ? $ids : [] as $pid) {
                $quality[] = ['sev' => 'moyenne', 'code' => 'stats-copie', 'msg' => 'Tableau de statistiques identique à celui de ' . (count($ids) - 1) . ' autre' . (count($ids) > 2 ? 's' : '') . ' fiche' . (count($ids) > 2 ? 's' : '') . ' (modèle recopié ?) : à vérifier', 'id' => $pid];
            }
        }

        // Fiches « à venir », photos sans crédit, statistiques incohérentes
        // Union (et non array_merge, qui renumérote) : chaque alerte garde l'identifiant de sa fiche.
        foreach ($matches + $persons as $id => $doc) {
            $txt = '';
            foreach ($doc['sections'] ?? [] as $s) {
                $txt .= ' ' . strip_tags((string) $s['html']);
            }
            if (preg_match('/\b(à|a) venir\b/iu', $txt)) {
                $quality[] = ['sev' => 'moyenne', 'code' => 'avenir', 'msg' => 'Fiche marquée « à venir »', 'id' => $id];
            }
            if (!empty($doc['personne']['stats']['rows'])) {
                $issue = self::checkStatsTotals($doc['personne']['stats']);
                if ($issue) {
                    $quality[] = ['sev' => 'haute', 'code' => 'stats', 'msg' => $issue, 'id' => $id];
                }
            }
        }
        // Index d'utilisation des médias (médiathèque du back-office)
        $usage = [];
        foreach ($mediaRefs as $id => $refs) {
            foreach ($refs as $rel) {
                $usage[$rel][] = (int) $id;
            }
        }
        unset($mediaRefs);
        $usage = self::collectionMediaUsage($usage);
        Media::saveUsage($usage);

        $noCredit = 0;
        foreach (Media::all() as $rel => $mm) {
            if (str_starts_with((string) ($mm['mime'] ?? ''), 'image/') && trim((string) ($mm['credit'] ?? '')) === '') {
                $noCredit++;
            }
        }

        // ---------------------------------------------------------- agrégats
        $byPerson = [];
        foreach ($apps as $a) {
            [$pid, $mid] = $a;
            $byPerson[$pid][] = $a;
        }
        // Tri chronologique des matchs de chaque personne
        foreach ($byPerson as $pid => &$list) {
            usort($list, fn ($x, $y) => strcmp((string) $M[$x[1]]['date'], (string) $M[$y[1]]['date']));
        }
        unset($list);

        $personTotals = [];
        foreach ($byPerson as $pid => $list) {
            $t = ['matches' => 0, 'goals' => 0, 'yellow' => 0, 'red' => 0, 'minutes' => 0, 'coached' => 0, 'v' => 0, 'n' => 0, 'd' => 0, 'seasons' => [], 'first' => null, 'last' => null];
            foreach ($list as [$p, $mid, $g, $min, $y, $r, $role]) {
                if (!$M[$mid]['v']) {
                    continue;
                }
                if ($role === 'coach') {
                    $t['coached']++;
                    $res = $M[$mid]['result'];
                    if ($res) {
                        $t[strtolower($res)]++;
                    }
                } elseif ($role === 'player') {
                    $t['matches']++;
                    $t['goals'] += $g;
                    $t['minutes'] += $min;
                    $t['first'] ??= $mid;
                    $t['last'] = $mid;
                }
                $t['yellow'] += $y;
                $t['red'] += $r;
                if ($M[$mid]['season']) {
                    $t['seasons'][$M[$mid]['season']] = true;
                }
            }
            $t['seasons'] = count($t['seasons']);
            $personTotals[$pid] = $t;
        }

        $seasons = [];
        foreach ($M as $mid => $x) {
            if (!$x['v'] || !$x['season']) {
                continue;
            }
            $s = &$seasons[$x['season']];
            $s['matches'][] = $mid;
            $s['comps'][$x['comp']] = ($s['comps'][$x['comp']] ?? 0) + 1;
            if ($x['comp'] === 'Championnat' && $x['label']) {
                $s['division'][$x['label']] = ($s['division'][$x['label']] ?? 0) + 1;
            }
            if ($x['result']) {
                $s['res'][$x['result']] = ($s['res'][$x['result']] ?? 0) + 1;
            }
            unset($s);
        }
        foreach ($seasons as $sk => &$s) {
            usort($s['matches'], fn ($a, $b) => strcmp((string) $M[$a]['date'], (string) $M[$b]['date']));
            $div = $s['division'] ?? [];
            arsort($div);
            $s['division'] = $div ? array_key_first($div) : null;
            $s['res'] = ($s['res'] ?? []) + ['V' => 0, 'N' => 0, 'D' => 0];
            $s['comps'] ??= [];
        }
        unset($s);
        // Effectif et buteurs par saison
        foreach ($apps as [$pid, $mid, $g, $min, , , $role, , $pos]) {
            $sk = $M[$mid]['season'];
            if (!$sk || !$M[$mid]['v'] || !isset($seasons[$sk])) {
                continue;
            }
            if ($role === 'player' || $role === 'bench') {
                $e = &$seasons[$sk]['squad'][$pid];
                $e['mj'] = ($e['mj'] ?? 0) + ($role === 'player' ? 1 : 0);
                $e['goals'] = ($e['goals'] ?? 0) + $g;
                $e['pos'][$pos] = ($e['pos'][$pos] ?? 0) + 1;
                unset($e);
            } elseif ($role === 'coach') {
                $seasons[$sk]['coaches'][$pid] = ($seasons[$sk]['coaches'][$pid] ?? 0) + 1;
            }
        }

        $clubStats = self::groupStats($M, 'club');
        $stadeStats = self::groupStats($M, 'stade');
        $compStats = self::groupStats($M, 'comp');

        // Noms canoniques des clubs et stades créés automatiquement : le plus fréquent.
        foreach ($clubs as &$c) {
            if (!empty($c['auto']) && isset($clubNames[$c['id']])) {
                arsort($clubNames[$c['id']]);
                $c['name'] = array_key_first($clubNames[$c['id']]);
                $c['aliases'] = array_values(array_diff(array_keys($clubNames[$c['id']]), [$c['name']]));
            }
        }
        unset($c);
        foreach ($stades as &$st) {
            if (!empty($st['auto']) && isset($stadeNames[$st['id']])) {
                arsort($stadeNames[$st['id']]);
                $st['name'] = trim(preg_replace('/\s*\([^)]*\)\s*/u', ' ', array_key_first($stadeNames[$st['id']])));
                $st['aliases'] = array_values(array_diff(array_keys($stadeNames[$st['id']]), [array_key_first($stadeNames[$st['id']])]));
                if ($st['id'] === 'auguste-bonal' && empty($st['lat'])) {
                    $st += ['city' => 'Montbéliard'];
                    $st['city'] = $st['city'] ?: 'Montbéliard';
                    $st['lat'] = 47.5119;
                    $st['lng'] = 6.8116;
                }
            }
        }
        unset($st);
        if ($newClubs || array_filter($clubs, fn ($c) => !empty($c['auto']))) {
            self::mergeRefs('clubs', $clubs, $clubIds);
        }
        if ($newStades || array_filter($stades, fn ($s) => !empty($s['auto']))) {
            self::mergeRefs('stades', $stades, $stadeIds);
        }

        foreach ($unlinked as $k => $u) {
            $u['matches'] = array_values(array_unique($u['matches']));
            $unlinked[$k] = $u;
        }
        uasort($unlinked, fn ($a, $b) => count($b['matches']) <=> count($a['matches']));
        foreach (array_slice($unlinked, 0, 300, true) as $u) {
            $quality[] = ['sev' => 'basse', 'code' => 'nonrelie', 'msg' => "Joueur cité dans " . count($u['matches']) . " composition(s) sans fiche : {$u['name']}", 'id' => $u['matches'][0]];
        }
        // Noms reliés par rapprochement (autre graphie, faute de frappe, nom incomplet) : à vérifier.
        // Graphies qui s'affichent pareil (« PELISSARD Selim », « Selim Pelissard ») : une seule alerte.
        $shown = [];
        foreach ($approxUsed as $name => [$pid, $how, $n]) {
            $k = Names::display($name) . '|' . $pid;
            $shown[$k] = [Names::display($name), $pid, $shown[$k][2] ?? $how, ($shown[$k][3] ?? 0) + $n];
        }
        uasort($shown, fn ($a, $b) => $b[3] <=> $a[3]);
        foreach ($shown as $k => [$name, $pid, $how, $n]) {
            $quality[] = ['sev' => 'basse', 'code' => 'rapproche', 'msg' => 'Nom « ' . $name . " » relié par rapprochement ($how, $n composition" . ($n > 1 ? 's' : '') . ') à la fiche : ' . ($persons[$pid]['title'] ?? $pid), 'id' => $pid, 'ref' => $name];
        }

        // Fiches écartées des calculs (données dans un format inattendu) : à reprendre.
        foreach (array_keys($broken) as $bid) {
            $quality[] = ['sev' => 'haute', 'code' => 'fichier', 'ref' => 'donnees', 'id' => $bid,
                'msg' => 'Données de la fiche dans un format inattendu (fichier retouché à la main ?) : fiche écartée des statistiques. L’ouvrir et l’enregistrer ; sinon la remplacer par sa dernière sauvegarde.'];
        }

        $data = [
            'version' => self::VERSION,
            'built' => date('c'),
            'duration' => round(microtime(true) - $t0, 2),
            'matches' => $M,
            'apps' => $apps,
            'scorers' => $scorers,
            'person_totals' => $personTotals,
            'by_person' => array_map(fn ($l) => array_map(fn ($a) => $a[1], $l), $byPerson),
            'seasons' => $seasons,
            'clubs' => $clubStats,
            'stades' => $stadeStats,
            'comps' => $compStats,
            'on_this_day' => $onThisDay,
            'unlinked' => array_slice($unlinked, 0, 500, true),
            'quality' => $quality,
            'no_credit' => $noCredit,
            'bilans' => array_values(array_filter($articles, fn ($a) => $a['kind'] === 'bilan_saison')),
        ];
        $tmp = self::CACHE . '.' . bin2hex(random_bytes(4));
        file_put_contents($tmp, '<?php return ' . var_export($data, true) . ";\n", LOCK_EX);
        rename($tmp, self::CACHE);
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate(self::CACHE, true);
        }
        return $data;
    }

    /**
     * Clubs et stades créés automatiquement : ajouts et noms recalculés appliqués au référentiel
     * relu sous verrou, pour ne jamais écraser une correction faite pendant le calcul.
     * $initial : identifiants présents au début du calcul (une entrée supprimée entre-temps ne revient pas).
     */
    private static function mergeRefs(string $name, array $computed, array $initial): void
    {
        $byId = [];
        foreach ($computed as $c) {
            $byId[(string) $c['id']] = $c;
        }
        $known = array_flip(array_map('strval', $initial));
        JsonStore::update(Collections::DIR . "/$name.json", function ($cur) use ($byId, $known) {
            $cur = is_array($cur) ? array_values($cur) : [];
            $have = [];
            foreach ($cur as $i => $c) {
                $id = (string) ($c['id'] ?? '');
                $have[$id] = true;
                $new = $byId[$id] ?? null;
                if ($new && !empty($c['auto']) && !empty($new['auto'])) {
                    $cur[$i]['name'] = $new['name'];
                    $cur[$i]['aliases'] = $new['aliases'] ?? [];
                    foreach (['city', 'lat', 'lng'] as $k) {
                        if (empty($c[$k]) && !empty($new[$k])) {
                            $cur[$i][$k] = $new[$k];
                        }
                    }
                }
            }
            foreach ($byId as $id => $c) {
                if (!isset($have[$id]) && !isset($known[$id])) {
                    $cur[] = $c;
                }
            }
            return $cur;
        }, []);
    }

    /** Ajoute à l'index d'utilisation les médias des collections éditoriales et des rubriques. */
    private static function collectionMediaUsage(array $usage): array
    {
        foreach (glob(Collections::DIR . '/*.json') ?: [] as $f) {
            $name = basename($f, '.json');
            if ($name === 'geo') {
                continue;
            }
            foreach (Media::refsIn(JsonStore::read($f, [])) as $rel) {
                $usage[$rel][] = 'c:' . $name;
            }
        }
        // Collections jamais enregistrées : leurs images par défaut sont affichées, donc utilisées.
        $defaults = [
            'epoques' => fn () => \App\Front\Pages::defaultEras(),
            'reserves' => fn () => \App\Front\Pages::defaultReserves(),
            'teasers' => fn () => \App\Front\Pages::defaultTeasers(),
            'frise' => fn () => Seeds::frise(),
            'maillots' => fn () => Seeds::maillots(),
        ];
        foreach ($defaults as $name => $default) {
            if (!is_file(Collections::DIR . "/$name.json")) {
                foreach (Media::refsIn($default()) as $rel) {
                    $usage[$rel][] = 'c:' . $name;
                }
            }
        }
        foreach (Media::refsIn(Categories::all()) as $rel) {
            $usage[$rel][] = 'c:rubriques';
        }
        return $usage;
    }

    /** Bilans regroupés (par adversaire, stade ou compétition). */
    private static function groupStats(array $M, string $key): array
    {
        $out = [];
        foreach ($M as $mid => $x) {
            if (!$x['v'] || $x[$key] === null || $x[$key] === '') {
                continue;
            }
            $g = &$out[$x[$key]];
            $g['matches'][] = $mid;
            if ($x['result']) {
                $g[strtolower($x['result'])] = ($g[strtolower($x['result'])] ?? 0) + 1;
            }
            if ($x['us'] !== null) {
                $g['gf'] = ($g['gf'] ?? 0) + $x['us'];
                $g['ga'] = ($g['ga'] ?? 0) + $x['them'];
            }
            unset($g);
        }
        foreach ($out as &$g) {
            $g += ['v' => 0, 'n' => 0, 'd' => 0, 'gf' => 0, 'ga' => 0];
            usort($g['matches'], fn ($a, $b) => strcmp((string) $M[$a]['date'], (string) $M[$b]['date']));
            $g['count'] = count($g['matches']);
        }
        unset($g);
        return $out;
    }

    private static function checkStatsTotals(array $stats): ?string
    {
        $rows = $stats['rows'];
        $last = end($rows);
        if (!$last || !preg_match('/^total/iu', (string) ($last[0] ?? ''))) {
            return null;
        }
        $body = array_slice($rows, 0, -1);
        foreach ($last as $ci => $tot) {
            if ($ci === 0 || !is_numeric($tot)) {
                continue;
            }
            $sum = 0;
            $ok = false;
            foreach ($body as $r) {
                if (isset($r[$ci]) && is_numeric($r[$ci])) {
                    $sum += (int) $r[$ci];
                    $ok = true;
                }
            }
            if ($ok && $sum !== (int) $tot) {
                $col = $stats['headers'][$ci] ?? "colonne $ci";
                return "Statistiques : total « $col » ($tot) ≠ somme des saisons ($sum)";
            }
        }
        return null;
    }

    private const WEEKDAYS = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];

    /** Buts saisis : entier (ou « 2 », 2.0 d'un fichier retouché à la main) ; null s'il n'y en a pas de lisible. */
    private static function goals(mixed $v): ?int
    {
        if (is_int($v)) {
            return $v >= 0 ? $v : null;
        }
        return is_numeric($v) && (float) $v >= 0 && (float) $v === floor((float) $v) ? (int) $v : null;
    }

    /**
     * Match : date, saison, score et résultat cohérents ; composition d'un match officiel.
     * (Le masque de saisie calcule la saison et le résultat : ces écarts viennent surtout
     * de l'ancien site ou de fichiers modifiés à la main.)
     * @return list<array{sev:string,code:string,msg:string}>
     */
    private static function matchChecks(array $m, ?int $us, ?int $them, bool $vis): array
    {
        $out = [];
        $date = is_scalar($m['date'] ?? null) ? (string) $m['date'] : null;
        $today = date('Y-m-d');
        $official = !in_array($m['competition'] ?? '', self::OFFICIAL_EXCLUDED, true) && empty($m['event']);
        if (!$date) {
            if ($vis) {
                $out[] = ['sev' => 'haute', 'code' => 'match-date', 'ref' => 'absente', 'msg' => 'Date du match non renseignée : absent des saisons, des bilans et de « Ce jour-là » (onglet Infos)'];
            }
        } elseif (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $d) || !checkdate((int) $d[2], (int) $d[3], (int) $d[1])) {
            $out[] = ['sev' => 'haute', 'code' => 'match-date', 'ref' => 'impossible', 'msg' => 'Date du match impossible (« ' . $date . ' ») (onglet Infos)'];
        } else {
            $fr = date('d/m/Y', strtotime($date));
            $season = (string) ($m['season'] ?? '');
            // Saison du 1er juillet au 30 juin ; les amicaux de fin juin peuvent ouvrir la saison
            // suivante et ceux de juillet clore la précédente.
            $start = preg_match('/^(\d{4})-(\d{4})$/', $season, $sm) && (int) $sm[2] === (int) $sm[1] + 1 ? (int) $sm[1] : null;
            if ($season === '') {
                if ($vis) {
                    $out[] = ['sev' => 'moyenne', 'code' => 'saison', 'ref' => 'vide', 'msg' => 'Saison non renseignée : le match n’apparaît dans aucune saison'];
                }
            } elseif ($start === null || !(((int) $d[1] === $start && (int) $d[2] >= 6) || ((int) $d[1] === $start + 1 && (int) $d[2] <= 7))) {
                $out[] = ['sev' => 'moyenne', 'code' => 'saison', 'ref' => 'ecart', 'msg' => "Match du $fr rangé dans la saison « $season » : date ou saison à vérifier"];
            }
            if ($us !== null && $date > $today) {
                $out[] = ['sev' => 'moyenne', 'code' => 'score', 'ref' => 'avenir', 'msg' => "Score saisi pour un match à venir (le $fr) : date à vérifier"];
            } elseif ($us === null && $vis && $official && $date < $today) {
                $out[] = ['sev' => 'moyenne', 'code' => 'score', 'ref' => 'absent', 'msg' => 'Score non renseigné pour ce match officiel (onglet Infos)'];
            }
        }
        if ($us !== null && $them !== null) {
            $pens = $m['score']['pens'] ?? null;
            $want = $us <=> $them;
            if (is_array($pens) && isset($pens['home'], $pens['away'])) {
                if ($us !== $them) {
                    $out[] = ['sev' => 'moyenne', 'code' => 'resultat', 'ref' => 'tab', 'msg' => "Tirs au but saisis alors que le score n’est pas nul ($us-$them pour Sochaux) (onglet Infos)"];
                } else {
                    $sh = (bool) ($m['sochaux_home'] ?? true);
                    $want = $sh ? ((int) $pens['home'] <=> (int) $pens['away']) : ((int) $pens['away'] <=> (int) $pens['home']);
                }
            }
            $res = $m['result'] ?? null;
            // Tirs au but dont la séance n'est pas connue : victoire ou défaite sur un score nul.
            $tabUnknown = $us === $them && !is_array($pens) && stripos((string) ($m['score']['extra'] ?? ''), 'tab') !== false;
            $labels = ['V' => 'victoire', 'N' => 'nul', 'D' => 'défaite'];
            if ($res !== null && !$tabUnknown && $res !== [1 => 'V', 0 => 'N', -1 => 'D'][$want]) {
                $out[] = ['sev' => 'haute', 'code' => 'resultat', 'ref' => 'incoherent', 'msg' => 'Résultat « ' . ($labels[$res] ?? $res) . " » incohérent avec le score ($us-$them pour Sochaux) : réenregistrer le score (onglet Infos)"];
            }
        }
        // Composition d'un match officiel : 11 titulaires au plus, un seul gardien, pas de minute
        // d'entrée pour un titulaire (son temps de jeu compterait 90 minutes).
        $rows = array_filter((array) ($m['lineup']['rows'] ?? []), fn ($r) => trim((string) ($r['name'] ?? '')) !== '');
        if ($vis && $official && $rows) {
            $starters = 0;
            $keepers = 0;
            $entered = [];
            foreach ($rows as $r) {
                $pos = strtoupper((string) ($r['position'] ?? ''));
                if (!in_array($pos, ['G', 'D', 'M', 'A'], true)) {
                    continue;
                }
                if (trim((string) ($r['sub_in'] ?? '')) !== '') {
                    $entered[] = Names::display((string) $r['name']) . ' (' . trim((string) $r['sub_in']) . '’)';
                    continue;
                }
                $starters++;
                $keepers += $pos === 'G' ? 1 : 0;
            }
            if ($entered) {
                // Onze titulaires par ailleurs : remplaçant mal noté ; sinon, minute de sortie mal placée.
                $out[] = ['sev' => 'moyenne', 'code' => 'compo', 'ref' => 'entree', 'msg' => $starters >= 11
                    ? 'Joueur entré en cours de jeu noté titulaire : ' . implode(', ', $entered) . ' (poste « Remplaçant » à choisir dans Compo & événements)'
                    : 'Minute d’entrée en jeu notée pour un titulaire : ' . implode(', ', $entered) . ' (minute de sortie mal placée ? Compo & événements)'];
            }
            if ($starters > 11) {
                $out[] = ['sev' => 'moyenne', 'code' => 'compo', 'ref' => 'trop', 'msg' => "$starters titulaires dans la composition (11 au plus) : remplaçant noté titulaire ?"];
            } elseif ($starters > 0 && $starters < 9) {
                $out[] = ['sev' => 'basse', 'code' => 'compo', 'ref' => 'incomplete', 'msg' => "Composition incomplète : $starters titulaires"];
            }
            if ($keepers > 1) {
                $out[] = ['sev' => 'moyenne', 'code' => 'compo', 'ref' => 'gardiens', 'msg' => "$keepers gardiens titulaires dans la composition"];
            }
        }
        return $out;
    }

    /**
     * Fiche abîmée (envoi par FTP, retouche à la main, média ou rubrique supprimés) :
     * titre, adresse, rubriques, images absentes de la médiathèque.
     * @return list<array{sev:string,code:string,msg:string}>
     */
    private static function ficheChecks(array $doc, bool $vis, array $cats, array $library): array
    {
        $out = [];
        if (trim((string) ($doc['title'] ?? '')) === '') {
            $out[] = ['sev' => 'haute', 'code' => 'titre', 'msg' => 'Titre vide'];
        }
        $path = (string) ($doc['path'] ?? '');
        if ($path === '' ? $vis : !preg_match('#^/(?:[^/\s?\#]+/)*$#u', $path)) {
            $out[] = ['sev' => 'haute', 'code' => 'adresse', 'ref' => 'forme', 'msg' => $path === ''
                ? 'Adresse de la page vide : la fiche n’est pas accessible sur le site (Classement & SEO)'
                : 'Adresse de la page mal formée (« ' . $path . ' ») : elle commence et finit par « / », sans espace (Classement & SEO)'];
        }
        $lost = array_diff(array_filter((array) ($doc['categories'] ?? []), 'is_string'), array_keys($cats));
        if ($lost) {
            $out[] = ['sev' => 'moyenne', 'code' => 'rubrique', 'msg' => 'Rangée dans une rubrique qui n’existe plus : ' . implode(', ', $lost) . ' (à décocher dans Classement & SEO)'];
        }
        // Images nommées dans la fiche (une, galerie, images du texte) absentes de la médiathèque.
        $unknown = array_values(array_unique(array_filter(self::namedImages($doc), fn ($r) => !isset($library[$r]))));
        if ($unknown) {
            $out[] = ['sev' => 'moyenne', 'code' => 'image', 'ref' => 'mediatheque', 'msg' => 'Image absente de la médiathèque (supprimée ?) : ' . self::fileList($unknown) . ' (onglet Médias)'];
        }
        return $out;
    }

    /** Images nommées dans la fiche : image à la une, galerie, images du texte. */
    private static function namedImages(array $doc): array
    {
        return array_values(array_filter(array_merge([$doc['featured_image'] ?? null], array_column((array) ($doc['gallery'] ?? []), 'image'), array_column((array) ($doc['images'] ?? []), 'image')), fn ($r) => is_string($r) && $r !== ''));
    }

    /**
     * Images de la médiathèque utilisées par la fiche (nommées ou citées dans le texte) :
     * [toutes, celles dont le fichier manque sur le serveur].
     * @return array{0:list<string>,1:list<string>}
     */
    private static function imageFiles(array $doc, array $library, array $refs): array
    {
        $known = array_values(array_unique(array_merge($refs, array_filter(self::namedImages($doc), fn ($r) => isset($library[$r])))));
        return [$known, array_values(array_filter($known, fn ($r) => !is_file(Media::ORIGINALS . '/' . Media::safeRel($r))))];
    }

    /**
     * Fichiers d'images absents du serveur : une alerte par fiche. S'il en manque beaucoup à la
     * fois (photos pas encore copiées sur un serveur neuf, dossier perdu), une seule alerte.
     * @param array<int,list<string>> $goneByFiche
     */
    private static function photoAlerts(array $goneByFiche, int $used): array
    {
        $gone = count(array_unique(array_merge([], ...array_values($goneByFiche))));
        if ($gone >= 50 && $gone > 0.2 * $used) {
            return [['sev' => 'haute', 'code' => 'photos', 'id' => null, 'title' => 'storage/media/originals',
                'msg' => 'Photos originales absentes du serveur : ' . number_format($gone, 0, ',', ' ') . ' fichiers sur ' . number_format($used, 0, ',', ' ') . ' utilisés par les fiches. Copie des photos depuis WordPress pas encore faite ou pas terminée (mise en ligne, § 4).']];
        }
        $out = [];
        foreach ($goneByFiche as $id => $files) {
            $out[] = ['sev' => 'moyenne', 'code' => 'image', 'ref' => 'serveur', 'msg' => 'Fichier d’image absent du serveur : ' . self::fileList($files) . ' (à renvoyer dans la médiathèque)', 'id' => (int) $id];
        }
        return $out;
    }

    /** « a.jpg, b.jpg, c.jpg et 2 autres » */
    private static function fileList(array $files): string
    {
        $n = count($files);
        return implode(', ', array_slice($files, 0, 3)) . ($n > 3 ? ' et ' . ($n - 3) . ' autre' . ($n > 4 ? 's' : '') : '');
    }

    /** Où une fiche contient des « xx » de l'ancien site (information inconnue), pour l'écran Qualité. */
    private static function unknownPlaces(array $doc): array
    {
        $has = fn ($v) => \App\Front\Unknown::has($v);
        $out = [];
        $p = $doc['personne'] ?? null;
        if ($p) {
            foreach ($p['fiche'] ?? [] as $r) {
                if ($has($r['value'] ?? '')) {
                    $out[] = 'fiche d’identité « ' . mb_strimwidth(trim((string) $r['value']), 0, 60, '…') . ' »';
                    break;
                }
            }
            foreach (['birth' => 'naissance', 'death' => 'décès'] as $k => $l) {
                if ($has($p[$k]['date']['text'] ?? '') || $has($p[$k]['place']['city'] ?? '')) {
                    $out[] = $l;
                }
            }
            foreach (['subtitle' => 'sous-titre', 'first_match' => 'premier match', 'last_match' => 'dernier match', 'first_goal' => 'premier but', 'height' => 'taille', 'arrival' => 'arrivée', 'departure' => 'départ'] as $k => $l) {
                if ($has($p[$k] ?? '')) {
                    $out[] = $l;
                }
            }
        }
        $m = $doc['match'] ?? null;
        if ($m) {
            foreach (['referee' => 'arbitre', 'spectators_text' => 'spectateurs', 'stadium' => 'stade', 'goals' => 'buteurs', 'highlights' => 'temps forts', 'reactions' => 'réactions', 'breves' => 'brèves'] as $k => $l) {
                if ($has($m[$k] ?? '')) {
                    $out[] = $l;
                }
            }
        }
        if ($has($doc['key_figure'] ?? '')) {
            $out[] = 'chiffre clé';
        }
        if ($has($doc['intro'] ?? '') || $has(array_column($doc['sections'] ?? [], 'html'))) {
            $out[] = 'texte';
        }
        return array_slice(array_values(array_unique($out)), 0, 5);
    }

    /** Jour de la semaine écrit en tête d'une date en lettres (1 = lundi), null s'il n'y en a pas. */
    private static function weekday(string $s): ?int
    {
        $w = strtolower((string) preg_replace('/[^a-z].*$/s', '', Names::ascii(trim($s))));
        $i = array_search($w, self::WEEKDAYS, true);
        return $i === false ? null : $i + 1;
    }

    /** « Mardi 3 Novembre 1987 » → 1987-11-03 */
    private static function frDate(string $s): ?string
    {
        $months = ['janvier' => 1, 'fevrier' => 2, 'mars' => 3, 'avril' => 4, 'mai' => 5, 'juin' => 6, 'juillet' => 7, 'aout' => 8, 'septembre' => 9, 'octobre' => 10, 'novembre' => 11, 'decembre' => 12];
        $a = Names::ascii($s);
        if (preg_match('/(\d{1,2})(?:er)?\s+([a-z]+)\s+(\d{4})/', $a, $m) && isset($months[$m[2]]) && checkdate($months[$m[2]], (int) $m[1], (int) $m[3])) {
            return sprintf('%04d-%02d-%02d', $m[3], $months[$m[2]], $m[1]);
        }
        return null;
    }

    // ------------------------------------------------------------------ lectures

    public static function match(int $id): ?array
    {
        return self::get()['matches'][$id] ?? null;
    }

    /** @return list<array> matchs (résumés) d'une personne, avec sa ligne de composition */
    public static function personMatches(int $pid): array
    {
        $d = self::get();
        $out = [];
        foreach ($d['apps'] as $a) {
            if ($a[0] === $pid && $d['matches'][$a[1]]['v']) {
                $out[] = $d['matches'][$a[1]] + ['goals' => $a[2], 'minutes' => $a[3], 'yellow' => $a[4], 'red' => $a[5], 'role' => $a[6], 'captain' => $a[7], 'pos' => $a[8]];
            }
        }
        usort($out, fn ($x, $y) => strcmp((string) $x['date'], (string) $y['date']));
        return $out;
    }

    /** Identifiant de fiche relié à une ligne de composition (pour les liens). */
    public static function lineupLinks(int $mid): array
    {
        $out = [];
        foreach (self::get()['apps'] as $a) {
            if ($a[1] === $mid) {
                $out[] = $a;
            }
        }
        return $out;
    }

    /** Matchs joués à cette date (jour et mois), du plus récent au plus ancien. */
    public static function onThisDay(?string $mmdd = null): array
    {
        $d = self::get();
        $ids = $d['on_this_day'][$mmdd ?? date('m-d')] ?? [];
        $list = array_map(fn ($id) => $d['matches'][$id], $ids);
        usort($list, fn ($a, $b) => strcmp((string) $b['date'], (string) $a['date']));
        return $list;
    }
}
