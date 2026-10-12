<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Club;
use App\Core\JsonStore;
use App\Data\Categories;
use App\Data\Fiches;
use App\Data\Paths;

/**
 * Contenus de départ d'un nouveau musée : fiches préparées hors ligne (faits repris des sources,
 * textes entièrement rédigés à neuf), livrées avec le code dans config/clubs/<club>/import/*.json.gz
 * et créées d'un clic dans le back-office, publiées tout de suite (vérifiées ensuite). Chaque fiche n'est créée qu'une
 * fois (clé de la source) : relancer est sans risque, et une fiche supprimée n'est pas recréée.
 *
 * Format d'un paquet : liste de fiches partielles
 * { key, source, type, title, season?, intro?, sections?, key_figure?, match|personne|article? }.
 */
final class StarterPack
{
    public const AUTHOR = ['name' => 'Contenus de départ'];

    public static function dir(): string
    {
        return APP_ROOT . '/config/clubs/' . Club::id() . '/import';
    }

    private static function stateFile(): string
    {
        return STORAGE_PATH . '/import/depart.json';
    }

    /** Paquets disponibles : nom => libellé. @return array<string, string> */
    public static function packs(): array
    {
        $out = [];
        foreach (glob(self::dir() . '/*.json.gz') ?: [] as $f) {
            $name = basename($f, '.json.gz');
            $out[$name] = ucfirst((string) preg_replace('/-/', ' ', (string) preg_replace('/^\d+-/', '', $name), 1)); // « 20-matchs-1970-1979 » → « Matchs 1970-1979 »
        }
        ksort($out);
        return $out;
    }

    /** @return list<array<string, mixed>> */
    public static function items(string $pack): array
    {
        $f = self::dir() . '/' . basename($pack) . '.json.gz';
        if (!is_file($f)) {
            return [];
        }
        $raw = gzdecode((string) file_get_contents($f));
        return $raw === false ? [] : (json_decode($raw, true) ?: []);
    }

    /** @return array{done: array<string, int>, running: bool} */
    public static function state(): array
    {
        $s = JsonStore::read(self::stateFile(), []) ?: [];
        return ['done' => (array) ($s['done'] ?? []), 'running' => !empty($s['running'])];
    }

    public static function start(bool $on): void
    {
        @mkdir(dirname(self::stateFile()), 0775, true);
        JsonStore::update(self::stateFile(), fn ($s) => array_merge(is_array($s) ? $s : [], ['running' => $on]), []);
    }

    /** Par paquet : total et fiches déjà créées. @return array<string, array{label:string,total:int,done:int}> */
    public static function summary(): array
    {
        $done = self::state()['done'];
        $out = [];
        foreach (self::packs() as $name => $label) {
            $items = self::items($name);
            $n = 0;
            foreach ($items as $it) {
                if (isset($done[(string) ($it['key'] ?? '')])) {
                    $n++;
                }
            }
            $out[$name] = ['label' => $label, 'total' => count($items), 'done' => $n];
        }
        return $out;
    }

    /**
     * Crée jusqu'à $limit fiches pas encore créées, paquet après paquet (dans l'ordre des noms).
     *
     * @return array{created:int, left:int, errors:list<string>} left : 1 s'il en reste, 0 sinon
     */
    public static function run(int $limit = 150): array
    {
        @mkdir(dirname(self::stateFile()), 0775, true);
        $fp = fopen(self::stateFile() . '.run.lock', 'c');
        if (!$fp || !flock($fp, LOCK_EX | LOCK_NB)) {
            return ['created' => 0, 'left' => -1, 'errors' => ['un autre lot est déjà en cours']];
        }
        try {
            $done = self::state()['done'];
            $revs = (array) ((JsonStore::read(self::stateFile(), []) ?: [])['rev'] ?? []);
            $created = 0;
            $errors = [];
            $left = 0;
            Fiches::batch(function () use (&$done, &$revs, &$created, &$errors, &$left, $limit) {
                // Fiches créées auparavant « À relire » et jamais retouchées : publiées à leur tour.
                foreach ($done as $id) {
                    if ($created >= $limit) {
                        $left = 1;
                        return;
                    }
                    $doc = $id ? Fiches::get((int) $id) : null;
                    if ($doc && ($doc['status'] ?? '') === 'relire' && ($doc['modified_by'] ?? '') === self::AUTHOR['name']) {
                        $doc['status'] = 'publie';
                        Fiches::save($doc, self::AUTHOR, 'Contenus de départ : mise en ligne');
                        $created++;
                    }
                }
                foreach (array_keys(self::packs()) as $pack) {
                    foreach (self::items($pack) as $it) {
                        $key = (string) ($it['key'] ?? '');
                        if ($key === '') {
                            continue;
                        }
                        if (isset($done[$key])) {
                            // Nouvelle version d'une fiche déjà créée (récit ajouté…) : appliquée si personne n'y a touché.
                            $rev = (int) ($it['rev'] ?? 0);
                            if ($rev > (int) ($revs[$key] ?? 0)) {
                                if ($created >= $limit) {
                                    $left = 1;
                                    return;
                                }
                                if (self::refresh((int) $done[$key], $it)) {
                                    $created++;
                                }
                                $revs[$key] = $rev;
                            }
                            continue;
                        }
                        if ($created >= $limit) {
                            $left = 1; // il en reste (paquets suivants non relus : mémoire)
                            return;
                        }
                        try {
                            $revs[$key] = (int) ($it['rev'] ?? 0);
                            $done[$key] = self::create($it);
                            $created++;
                        } catch (\Throwable $e) {
                            $done[$key] = 0;
                            $errors[] = ($it['title'] ?? $key) . ' : ' . $e->getMessage();
                        }
                    }
                }
            });
            JsonStore::update(self::stateFile(), fn ($s) => array_merge(is_array($s) ? $s : [], ['done' => $done, 'rev' => $revs, 'at' => date('c')]), []);
            return ['created' => $created, 'left' => $left, 'errors' => $errors];
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /** Une fiche « À relire », rangée dans sa saison et sa décennie, avec ses sources. */
    public static function create(array $it): int
    {
        $type = (string) ($it['type'] ?? '');
        $doc = Fiches::blank($type);
        $part = $type === 'page' ? 'article' : $type;
        if (isset($it[$part]) && is_array($it[$part])) {
            $doc[$part] = array_replace($doc[$part], $it[$part]);
        }
        foreach (['title', 'intro', 'sections', 'key_figure', 'seo'] as $k) {
            if (isset($it[$k])) {
                $doc[$k] = $it[$k];
            }
        }
        $doc['status'] = 'publie'; // en ligne tout de suite : la vérification se fait après coup
        $season = (string) ($it['season'] ?? '');
        $cats = (array) ($it['categories'] ?? []);
        if ($season !== '' && ($c = Categories::get($season))) {
            $cats[] = $season;
            if (!empty($c['parent'])) {
                $cats[] = (string) $c['parent'];
            }
        }
        $doc['categories'] = array_values(array_unique(array_filter($cats, fn ($s) => Categories::get((string) $s) !== null)));
        $sources = array_values(array_filter((array) ($it['sources'] ?? [$it['source'] ?? ''])));
        // Pas de section « Sources » : sources gardées en interne (legacy.sources), créditées dans les mentions légales.
        $doc['legacy'] = ['depart' => (string) $it['key'], 'sources' => $sources];
        $doc['path'] = Paths::unique(Paths::suggest($doc), -1);
        $doc['slug'] = basename(rtrim($doc['path'], '/'));
        return (int) Fiches::save($doc, self::AUTHOR, 'Contenus de départ')['id'];
    }

    /** Textes d'une nouvelle version posés sur la fiche déjà créée, si elle n'a été modifiée par personne. */
    private static function refresh(int $id, array $it): bool
    {
        $doc = $id ? Fiches::get($id) : null;
        if (!$doc || ($doc['modified_by'] ?? '') !== self::AUTHOR['name']) {
            return false;
        }
        $type = (string) $doc['type'];
        foreach (['title', 'intro', 'sections', 'key_figure'] as $k) {
            if (isset($it[$k])) {
                $doc[$k] = $it[$k];
            }
        }
        foreach ((array) ($it['refresh'] ?? []) as $k) {
            if (isset($it[$type][$k])) {
                $doc[$type][$k] = $it[$type][$k];
            }
        }
        // Plus de section « Sources » sur les fiches (gardées en interne, créditées dans les mentions légales).
        $doc['sections'] = array_values(array_filter((array) $doc['sections'], fn ($s) => ($s['title'] ?? '') !== 'Sources'));
        Fiches::save($doc, self::AUTHOR, 'Contenus de départ : nouvelle version');
        return true;
    }
}
