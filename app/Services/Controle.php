<?php
declare(strict_types=1);

namespace App\Services;

use App\Admin\Quality;
use App\Core\JsonStore;
use App\Core\PhpCache;
use App\Data\Activity;
use App\Data\Categories;
use App\Data\Collections;
use App\Data\Derived;
use App\Data\Fiches;
use App\Data\Index;
use App\Data\Media;
use App\Data\Names;
use App\Data\Redirects;
use App\Kernel;

/**
 * Contrôle complet à la demande (bouton « Contrôler maintenant » de l'écran Qualité) :
 * toutes les vérifications sont refaites sur toutes les fiches, puis comparées au contrôle
 * précédent. Les anomalies apparues depuis sont marquées « Nouveau ».
 *
 * Chaque alerte a une clé stable (onglet, fiche ou nom, nature de l'anomalie) : une alerte
 * dont seuls les chiffres changent n'est pas « nouvelle ». Avant le tout premier contrôle,
 * la comparaison se fait avec la référence livrée avec le code (contrôle complet d'octobre 2026).
 */
final class Controle
{
    public const FILE = STORAGE_PATH . '/controle.json';
    /** Clés des anomalies au contrôle complet d'octobre 2026 (php bin/console.php controle-reference). */
    public const REFERENCE = APP_DIR . '/Resources/controle-reference.json';
    private const LOCK = STORAGE_PATH . '/controle.lock';
    /** Contrôles gardés dans l'historique affiché. */
    private const HISTORY = 8;
    /** Plusieurs alertes de cette nature possibles sur une même fiche : sans « ref », le message les distingue. */
    private const MULTI = ['rapproche', 'compo', 'resultat', 'image', 'adresse', 'referentiel', ''];
    /** Onglets de la référence livrée (l'orthographe dépend du correcteur de chaque serveur). */
    private const REFERENCE_TABS = ['stats', 'completer', 'liens', 'site', 'credits', 'carto', 'traductions'];
    /**
     * Onglets remplis peu à peu par une tâche de fond (le correcteur relit tout le musée en
     * quelques jours) : une alerte n'y est nouvelle que si les textes de sa fiche ont changé
     * depuis le contrôle (une traduction ou un numéro d'album ne comptent pas).
     */
    private const BACKGROUND_TABS = ['orthographe'];

    private static ?array $state = null;

    /**
     * Clé stable d'une alerte (10 caractères) : onglet, fiche (ou nom), nature du problème et ce qu'il
     * vise (« ref » : nom rapproché, adresse, sorte d'écart…), jamais le libellé, qui peut changer
     * (nombre de compositions, titre d'une autre fiche, liste de fichiers).
     */
    public static function key(string $tab, array $item): string
    {
        $code = (string) ($item['code'] ?? '');
        $who = ($item['id'] ?? null) !== null ? '#' . (int) $item['id'] : (string) ($item['title'] ?? '');
        $ref = (string) ($item['ref'] ?? '');
        $detail = $ref !== '' ? $ref : (in_array($code, self::MULTI, true) ? (string) preg_replace('/\d+/', '#', (string) ($item['msg'] ?? '')) : '');
        return substr(sha1("$tab|$who|$code|$detail"), 0, 10);
    }

    /** Dernier contrôle enregistré (null avant le premier). */
    public static function last(): ?array
    {
        try {
            $c = JsonStore::read(self::FILE, null);
        } catch (\Throwable) {
            return null; // fichier abîmé : le prochain contrôle le réécrit
        }
        return is_array($c) && !empty($c['at']) ? $c : null;
    }

    /** Référence livrée : contrôle complet fait avant la mise en service du bouton. */
    public static function reference(): ?array
    {
        $r = is_file(self::REFERENCE) ? json_decode((string) file_get_contents(self::REFERENCE), true) : null;
        return is_array($r) && isset($r['keys']) ? $r : null;
    }

    /**
     * Ce qui sert à marquer les alertes « Nouveau » : nouvelles au dernier contrôle, ou apparues
     * depuis (absentes du dernier contrôle). Sans contrôle : comparaison avec la référence.
     * @return array{keys:array<string,true>,new:array<string,true>,tabs:list<string>,since:?array}
     */
    public static function state(): array
    {
        if (self::$state !== null) {
            return self::$state;
        }
        // Les alertes marquées sont nouvelles depuis la base du dernier contrôle (le contrôle d'avant,
        // ou la référence) ; sans base, depuis le dernier contrôle lui-même.
        $last = self::last();
        if ($last) {
            return self::$state = ['keys' => self::flat($last['keys'] ?? []), 'new' => array_fill_keys(self::split($last['new'] ?? ''), true),
                'tabs' => array_keys((array) ($last['keys'] ?? [])), 'since' => $last['since'] ?: ['at' => $last['at'], 'by' => $last['by'] ?? null, 'label' => null],
                'at' => (string) $last['at'], 'texts' => self::texts((string) ($last['texts'] ?? ''))];
        }
        $ref = self::reference();
        return self::$state = $ref
            ? ['keys' => self::flat($ref['keys']), 'new' => [], 'tabs' => array_keys((array) $ref['keys']), 'since' => ['at' => $ref['at'], 'by' => null, 'label' => $ref['label'] ?? null]]
            : ['keys' => [], 'new' => [], 'tabs' => [], 'since' => null];
    }

    public static function isNew(string $tab, string $key, ?array $item = null): bool
    {
        $s = self::state();
        return isset($s['new'][$key]) || (in_array($tab, $s['tabs'], true) && !isset($s['keys'][$key])
            && !self::background($tab, $item, $s['texts'] ?? []));
    }

    /**
     * Alerte trouvée par une tâche de fond sur une fiche dont les textes n'ont pas changé depuis
     * le contrôle (texte ancien relu par le correcteur) : connue, pas « nouvelle ».
     * @param array<int,string> $texts empreinte des textes de chaque fiche au contrôle
     */
    private static function background(string $tab, ?array $item, array $texts): bool
    {
        if (!in_array($tab, self::BACKGROUND_TABS, true) || empty($item['id'])) {
            return false;
        }
        $id = (int) $item['id'];
        return isset($texts[$id]) && $texts[$id] === substr((string) (Index::get($id)['tsig'] ?? ''), 0, 8);
    }

    /** Empreintes des textes des fiches : « 12:ab12cd34 13:… » ↔ [12 => 'ab12cd34', …]. */
    private static function texts(string $packed): array
    {
        $out = [];
        foreach (self::split($packed) as $pair) {
            [$id, $sig] = explode(':', $pair, 2) + [1 => ''];
            $out[(int) $id] = $sig;
        }
        return $out;
    }

    /**
     * Lance le contrôle complet : index des fiches resynchronisé si besoin, toutes les alertes
     * recalculées, comparaison avec le contrôle précédent (ou la référence), enregistrement.
     * @param bool $journal noté dans le journal d'activité (non pour les essais automatiques)
     * @return array résumé du contrôle, ou ['busy' => true] si un contrôle est déjà en cours
     */
    public static function run(?array $user, bool $journal = true): array
    {
        @set_time_limit(300);
        ignore_user_abort(true);
        $t0 = microtime(true);
        if (!is_dir(dirname(self::LOCK))) {
            mkdir(dirname(self::LOCK), 0775, true);
        }
        $lock = fopen(self::LOCK, 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            return ['busy' => true];
        }
        try {
            $repaired = self::syncIndex();
            Derived::rebuild();
            @unlink(self::SITE_CACHE); // tout est revérifié
            self::$state = ['keys' => [], 'new' => [], 'tabs' => [], 'since' => null];
            $all = Quality::all();
            $keys = [];
            foreach ($all as $tab => $items) {
                $keys[$tab] = array_values(array_unique(array_column($items, 'key')));
            }
            $prev = self::last();
            $ref = $prev ? null : self::reference();
            $base = $prev ? (array) $prev['keys'] : (array) ($ref['keys'] ?? []);
            $texts = self::texts((string) ($prev['texts'] ?? ''));
            $new = [];
            $fixed = 0;
            foreach ($all as $tab => $items) {
                if (!isset($base[$tab])) {
                    continue; // onglet absent de la comparaison (premier contrôle, nouvel onglet)
                }
                $before = array_fill_keys(self::split($base[$tab]), true);
                foreach ($items as $i) {
                    if (!isset($before[$i['key']]) && !self::background($tab, $i, $texts)) {
                        $new[$i['key']] = true;
                    }
                }
                $fixed += count(array_diff_key($before, array_fill_keys($keys[$tab], true)));
            }
            $new = array_keys($new);
            $total = array_sum(array_map('count', $keys));
            $since = $prev ? ['at' => $prev['at'], 'by' => $prev['by'] ?? null, 'label' => null]
                : ($ref ? ['at' => $ref['at'], 'by' => null, 'label' => $ref['label'] ?? null] : null);
            $run = [
                'at' => date('c'),
                'by' => $user['name'] ?? 'Système',
                'ms' => (int) round((microtime(true) - $t0) * 1000),
                'total' => $total,
                'counts' => array_map('count', $keys),
                'new' => $new,
                'fixed' => $fixed,
                'since' => $since,
                'repaired' => $repaired,
            ];
            $history = array_slice(array_merge([['at' => $run['at'], 'by' => $run['by'], 'total' => $total, 'new' => count($new), 'fixed' => $fixed]], (array) ($prev['history'] ?? [])), 0, self::HISTORY);
            // Clés rangées par onglet, séparées par des espaces (fichier compact) ; empreinte des textes
            // de chaque fiche, pour reconnaître au contrôle suivant ce que le correcteur relit sans changement.
            $sigs = [];
            foreach (Index::all() as $id => $s) {
                $sigs[] = $id . ':' . substr((string) ($s['tsig'] ?? ''), 0, 8);
            }
            JsonStore::write(self::FILE, ['new' => implode(' ', $new)] + $run + ['keys' => array_map(fn ($l) => implode(' ', $l), $keys), 'history' => $history, 'texts' => implode(' ', $sigs)]);
            self::$state = null;
            if ($journal) {
                Activity::log($user, 'a lancé le contrôle complet : ' . self::counts($run), null);
            }
            return $run;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** « 1 234 anomalies, dont 5 nouvelles depuis le contrôle du 3 octobre à 9 h 12 ; 8 corrigées » */
    public static function counts(array $run): string
    {
        $nb = fn (int $n, string $word) => number_format($n, 0, ',', ' ') . ' ' . $word . ($n > 1 ? 's' : '');
        $n = is_array($run['new']) ? count($run['new']) : count(self::split((string) $run['new']));
        $s = $nb((int) $run['total'], 'anomalie');
        if (empty($run['since'])) {
            return $s . ' (premier contrôle : les suivants signaleront les nouvelles)';
        }
        $s .= ', dont ' . ($n ? $nb($n, 'nouvelle') : 'aucune nouvelle') . ' depuis ' . self::sinceLabel($run['since']);
        if ((int) $run['fixed'] > 0) {
            $s .= ' ; ' . $nb((int) $run['fixed'], 'corrigée');
        }
        return $s;
    }

    /** « le contrôle du 3 octobre à 9 h 12 (Frédéric) » ou « le contrôle complet du 3 octobre 2026 » */
    public static function sinceLabel(?array $since): string
    {
        if (!$since) {
            return '';
        }
        if (!empty($since['label'])) {
            return (string) $since['label'];
        }
        $ts = strtotime((string) $since['at']);
        $when = date('Y-m-d', $ts) === date('Y-m-d') ? 'd’aujourd’hui à ' . date('G \h i', $ts) : 'du ' . date_fr(date('Y-m-d', $ts)) . ' à ' . date('G \h i', $ts);
        return 'le contrôle ' . $when . (!empty($since['by']) ? ' (' . $since['by'] . ')' : '');
    }

    private const SITE_CACHE = STORAGE_PATH . '/cache/controle-site.php';

    /**
     * Vérifications du site hors fiches : redirections, référentiels (adversaires, stades),
     * rubriques, traductions de l'interface. Gardées en cache tant que rien de ce qu'elles lisent
     * ne change (le nombre d'alertes graves s'affiche sur chaque page du back-office).
     * @return list<array{tab:string,sev:string,code:string,msg:string,id:null,title:string,url:?string,ref:string}>
     */
    public static function siteChecks(): array
    {
        clearstatcache();
        $sig = md5(implode('|', array_map(fn ($f) => @filemtime($f) . ':' . @filesize($f), [
            Redirects::FILE, DATA_PATH . '/i18n/en.json', Collections::DIR . '/clubs.json', Collections::DIR . '/stades.json',
            DATA_PATH . '/categories.json', DATA_PATH . '/media.json', Index::CACHE, STORAGE_PATH . '/cache/derived.php',
        ])));
        $c = PhpCache::read(self::SITE_CACHE);
        if (($c['sig'] ?? null) === $sig && is_array($c['items'] ?? null)) {
            return $c['items'];
        }
        $items = self::computeSiteChecks();
        try {
            PhpCache::write(self::SITE_CACHE, ['sig' => $sig, 'items' => $items]);
        } catch (\Throwable) {
            // cache facultatif
        }
        return $items;
    }

    private static function computeSiteChecks(): array
    {
        $out = [];
        $add = function (string $tab, string $sev, string $code, string $msg, string $title, ?string $url, string $ref = '') use (&$out) {
            $out[] = ['tab' => $tab, 'sev' => $sev, 'code' => $code, 'msg' => $msg, 'id' => null, 'title' => $title, 'url' => $url, 'ref' => $ref];
        };

        // Redirections : chaque ancienne adresse est suivie comme par un visiteur (Kernel::probe) :
        // jamais utilisée, vers une page absente, en chaîne, en boucle.
        if (!self::readable(Redirects::FILE)) {
            $add('site', 'haute', 'fichier', 'Fichier des redirections illisible (JSON abîmé) : les anciens liens ne sont plus redirigés. À remplacer par sa dernière sauvegarde.', 'data/redirects.json', null, 'redirects');
        }
        $seen = [];
        $probe = function (string $u) use (&$seen): array {
            $path = (string) preg_replace('/[\x00-\x1F\x7F]/', '', rawurldecode((string) (parse_url($u, PHP_URL_PATH) ?: '/')));
            $path = '/' . ltrim((string) preg_replace('#/+#', '/', str_replace('\\', '/', $path)), '/');
            parse_str((string) parse_url($u, PHP_URL_QUERY), $q);
            $k = $path . ($q ? '?' . http_build_query($q) : '');
            return $seen[$k] ??= [...Kernel::probe($path, $q), $k];
        };
        $external = fn (string $u) => (bool) preg_match('#^([a-z][a-z0-9+.-]*:|//)#i', $u);
        foreach (Redirects::all() as $from => $to) {
            [$from, $to] = [(string) $from, (string) $to];
            $url = '/admin/redirections?q=' . rawurlencode($from);
            [$st, $loc, $why, $fromKey] = $probe($from);
            if (!$external($to) && $probe($to)[3] === $fromKey) {
                $add('site', 'moyenne', 'redirection', 'Redirection vers elle-même : sans effet (à supprimer)', $from, $url, 'soi');
                continue;
            }
            if (!in_array($why, ['redirection', 'ancien lien'], true) || $loc !== $to) {
                $add('site', 'basse', 'redirection', match (true) {
                    $st < 300 => 'Redirection jamais utilisée : l’adresse affiche déjà une page (à supprimer)',
                    $why === 'barre' => 'Redirection jamais utilisée : l’adresse devient « ' . $loc . ' » (avec « / » final) avant d’être cherchée ; écrire l’ancienne adresse avec le « / » final',
                    $st >= 400 => 'Redirection jamais utilisée : l’adresse n’est jamais reconnue telle quelle',
                    default => 'Redirection jamais utilisée : l’adresse est d’abord redirigée vers « ' . $loc . ' »',
                }, $from, $url, 'inutile');
                continue;
            }
            if ($external($to)) {
                continue;
            }
            // Destination suivie jusqu'à une page (5 étapes au plus).
            $visited = [$fromKey => true];
            $cur = $to;
            $chain = false;
            $problem = null;
            for ($hop = 0; $hop <= 5 && !$external($cur); $hop++) {
                [$st, $loc, $why, $key] = $probe($cur);
                if ($st < 300) {
                    break;
                }
                if ($st >= 400) {
                    $problem = ['moyenne', 'Redirection vers ' . $to . ' : ' . self::why404($key) . ', l’ancien lien aboutit à « page introuvable »', 'absente'];
                    break;
                }
                if (isset($visited[$key])) {
                    $problem = ['haute', 'Redirections en boucle : ' . $to . ' ramène à une adresse déjà passée, la page ne s’affiche jamais', 'boucle'];
                    break;
                }
                $visited[$key] = true;
                $chain = $chain || $why === 'redirection';
                $cur = (string) $loc;
            }
            if (!$problem && $hop > 5) {
                $problem = ['moyenne', 'Redirection vers ' . $to . ' : plus de 5 redirections à la suite, le navigateur peut abandonner', 'longue'];
            }
            if (!$problem && $chain) {
                $problem = ['basse', 'Redirection en chaîne : ' . $to . ' est elle-même redirigée vers ' . $cur . ' (viser directement la dernière adresse)', 'chaine'];
            }
            if ($problem) {
                $add('site', $problem[0], 'redirection', $problem[1], $from, $url, $problem[2]);
            }
        }

        // Référentiels : identifiant en double, nom ou autre graphie qui désigne deux adversaires (ou stades).
        foreach (['clubs' => ['adversaires', 'Adversaire', [Names::class, 'clubKey']], 'stades' => ['stades', 'Stade', [Names::class, 'stadiumKey']]] as $col => [$tab, $label, $keyOf]) {
            $ids = [];
            $names = [];
            foreach ((array) Collections::get($col, []) as $c) {
                $id = (string) ($c['id'] ?? '');
                $name = (string) ($c['name'] ?? $id);
                $url = '/admin/referentiels?onglet=' . $tab . '&q=' . rawurlencode($name);
                if ($id === '' || isset($ids[$id])) {
                    $add('site', 'haute', 'referentiel', $label . ' en double (identifiant « ' . $id . ' » déjà utilisé) : à fusionner', "$label : $name", $url, "double:$id");
                }
                $ids[$id] = true;
                foreach (array_unique(array_merge([$name], array_map('strval', (array) ($c['aliases'] ?? [])))) as $a) {
                    $k = $keyOf($a);
                    if ($k !== '' && isset($names[$k]) && $names[$k][0] !== $id) {
                        $add('site', 'moyenne', 'referentiel', '« ' . $a . ' » désigne à la fois « ' . $names[$k][1] . ' » et « ' . $name . ' » : des matchs peuvent être rangés au mauvais endroit', "$label : $name", $url, "graphie:$k");
                    }
                    $names[$k] ??= [$id, $name];
                }
            }
        }

        // Rubriques rattachées à une rubrique parente supprimée.
        $cats = Categories::all();
        foreach ($cats as $slug => $c) {
            if (!empty($c['parent']) && !isset($cats[$c['parent']])) {
                $add('site', 'moyenne', 'rubrique', 'Rubrique rattachée à une rubrique qui n’existe plus (« ' . $c['parent'] . ' ») : à replacer', 'Rubrique : ' . ($c['name'] ?? $slug), '/admin/rubriques', (string) $slug);
            }
        }

        // Traductions de l'interface : mêmes variables ({n}, {nom}…) et mêmes balises dans les deux langues.
        $dict = DATA_PATH . '/i18n/en.json';
        if (!self::readable($dict)) {
            $add('traductions', 'haute', 'fichier', 'Fichier des textes anglais de l’interface illisible (JSON abîmé) : le site anglais s’affiche en français. À remplacer par sa dernière sauvegarde.', 'data/i18n/en.json', null, 'en.json');
        }
        foreach (self::readable($dict) ? (array) (JsonStore::read($dict, []) ?: []) : [] as $fr => $en) {
            [$fr, $en] = [(string) $fr, (string) $en];
            if ($en === '') {
                continue; // pas encore traduit : le texte français s'affiche
            }
            preg_match_all('/\{[a-z_]+\}/', $fr, $a);
            preg_match_all('/\{[a-z_]+\}/', $en, $b);
            $va = array_values(array_unique($a[0]));
            $vb = array_values(array_unique($b[0]));
            sort($va);
            sort($vb);
            $title = mb_strimwidth(trim((string) preg_replace('/\s+/u', ' ', strip_tags($fr))), 0, 90, '…');
            $url = '/admin/traductions?q=' . rawurlencode(mb_substr(trim(strip_tags($fr)), 0, 50));
            if ($va !== $vb) {
                $add('traductions', 'haute', 'interface', 'Texte de l’interface : variables différentes en anglais (' . (implode(' ', $va) ?: 'aucune') . ' en français, ' . (implode(' ', $vb) ?: 'aucune') . ' en anglais) : le texte anglais s’affiche mal', $title, $url, sha1($fr));
            } elseif ((bool) preg_match('#<[a-z/]#i', $fr) !== (bool) preg_match('#<[a-z/]#i', $en)) {
                $add('traductions', 'moyenne', 'interface', 'Texte de l’interface : liens ou mises en forme présents dans une seule des deux langues', $title, $url, sha1($fr));
            }
        }
        return $out;
    }

    /** Pourquoi une adresse renvoie « page introuvable » (fiche non publiée, fichier absent…). */
    private static function why404(string $path): string
    {
        $p = (string) preg_replace('#^/en(?=/|$)#', '', (string) preg_replace('/\?.*$/', '', $path)) ?: '/';
        if ($s = Index::byPath($p)) {
            return 'fiche ' . ($s['status'] === 'corbeille' ? 'à la corbeille' : 'non publiée (' . mb_strtolower(Fiches::STATUSES[$s['status']] ?? (string) $s['status']) . ')');
        }
        return preg_match('#^/(media|wp-content)/#', $p) ? 'fichier absent' : 'page inexistante';
    }

    /** Fichier JSON lisible (absent compris) : un fichier abîmé est signalé au lieu de faire échouer l'écran. */
    private static function readable(string $file): bool
    {
        try {
            JsonStore::read($file, null);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Fichiers de fiches modifiés hors du back-office (envoi par FTP) : l'index des fiches et
     * la recherche sont reconstruits. Renvoie le nombre de fiches qui n'étaient pas à jour.
     */
    private static function syncIndex(): int
    {
        Index::forget();
        $idx = Index::all();
        // Résumé recalculé depuis le fichier : tout écart (titre, adresse, statut, dates…) compte,
        // même si la date de modification interne n'a pas changé (restauration, envoi par FTP).
        $stale = [];
        foreach (Fiches::all() as $id => $doc) {
            if (($idx[$id] ?? null) !== Index::summary($doc)) {
                $stale[$id] = true;
            }
            unset($idx[$id]);
        }
        $stale += $idx; // fiches supprimées du disque mais encore dans l'index
        // Texte modifié sans toucher au résumé : le fichier est plus récent que la recherche
        // (absente, elle se reconstruit d'elle-même à la première recherche).
        clearstatcache();
        if ($searched = @filemtime(STORAGE_PATH . '/cache/search.php')) {
            foreach (glob(Fiches::DIR . '/*.json') ?: [] as $file) {
                if ((@filemtime($file) ?: 0) > $searched) {
                    $stale[(int) basename($file, '.json')] = true;
                }
            }
        }
        if ($stale) {
            Index::rebuild();
            Search::rebuild();
        }
        return count($stale);
    }

    /** Écrit la référence livrée avec le code à partir des données actuelles (développement). */
    public static function writeReference(string $label): int
    {
        Derived::rebuild();
        self::$state = ['keys' => [], 'new' => [], 'tabs' => [], 'since' => null];
        $keys = [];
        foreach (Quality::all() as $tab => $items) {
            if (in_array($tab, self::REFERENCE_TABS, true)) {
                $keys[$tab] = array_values(array_unique(array_column($items, 'key')));
                sort($keys[$tab]);
            }
        }
        self::$state = null;
        file_put_contents(self::REFERENCE, json_encode(['at' => date('c'), 'label' => $label, 'keys' => array_map(fn ($l) => implode(' ', $l), $keys)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        return array_sum(array_map('count', $keys));
    }

    /** @return array<string,true> */
    private static function flat(array $byTab): array
    {
        $out = [];
        foreach ($byTab as $list) {
            foreach (self::split($list) as $k) {
                $out[$k] = true;
            }
        }
        return $out;
    }

    /** Clés enregistrées « k1 k2 k3 » (ou en liste) → liste. */
    private static function split(mixed $list): array
    {
        return is_array($list) ? array_map('strval', $list) : array_values(array_filter(explode(' ', (string) $list)));
    }
}
