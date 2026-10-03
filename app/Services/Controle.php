<?php
declare(strict_types=1);

namespace App\Services;

use App\Admin\Quality;
use App\Core\JsonStore;
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
    /** Plusieurs alertes de cette nature possibles sur une même fiche : le message les distingue. */
    private const MULTI = ['rapproche', 'compo', 'resultat', 'image', 'adresse', ''];
    /** Onglets de la référence livrée (l'orthographe dépend du correcteur de chaque serveur). */
    private const REFERENCE_TABS = ['stats', 'completer', 'liens', 'site', 'credits', 'carto', 'traductions'];
    /**
     * Onglets remplis peu à peu par une tâche de fond (le correcteur relit tout le musée en
     * quelques jours) : une alerte n'y est nouvelle que si sa fiche a été modifiée depuis.
     */
    private const BACKGROUND_TABS = ['orthographe'];

    private static ?array $state = null;

    /** Clé stable d'une alerte (10 caractères). */
    public static function key(string $tab, array $item): string
    {
        $code = (string) ($item['code'] ?? '');
        $who = ($item['id'] ?? null) !== null ? '#' . (int) $item['id'] : (string) ($item['title'] ?? '');
        $detail = in_array($code, self::MULTI, true) ? (string) preg_replace('/\d+/', '#', (string) ($item['msg'] ?? '')) : '';
        return substr(sha1("$tab|$who|$code|$detail"), 0, 10);
    }

    /** Dernier contrôle enregistré (null avant le premier). */
    public static function last(): ?array
    {
        $c = JsonStore::read(self::FILE, null);
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
                'at' => (string) $last['at']];
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
            && !self::background($tab, $item, (string) ($s['at'] ?? '')));
    }

    /**
     * Alerte trouvée par une tâche de fond sur une fiche qui n'a pas changé depuis $since
     * (texte ancien relu par le correcteur) : connue, pas « nouvelle ».
     */
    private static function background(string $tab, ?array $item, string $since): bool
    {
        if (!in_array($tab, self::BACKGROUND_TABS, true) || $since === '' || empty($item['id'])) {
            return false;
        }
        $modified = strtotime((string) (Index::get((int) $item['id'])['modified'] ?? ''));
        return !$modified || $modified <= strtotime($since);
    }

    /**
     * Lance le contrôle complet : index des fiches resynchronisé si besoin, toutes les alertes
     * recalculées, comparaison avec le contrôle précédent (ou la référence), enregistrement.
     * @return array résumé du contrôle, ou ['busy' => true] si un contrôle est déjà en cours
     */
    public static function run(?array $user): array
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
            self::$state = ['keys' => [], 'new' => [], 'tabs' => [], 'since' => null];
            $all = Quality::all();
            $keys = [];
            foreach ($all as $tab => $items) {
                $keys[$tab] = array_values(array_unique(array_column($items, 'key')));
            }
            $prev = self::last();
            $ref = $prev ? null : self::reference();
            $base = $prev ? (array) $prev['keys'] : (array) ($ref['keys'] ?? []);
            $new = [];
            $fixed = 0;
            foreach ($all as $tab => $items) {
                if (!isset($base[$tab])) {
                    continue; // onglet absent de la comparaison (premier contrôle, nouvel onglet)
                }
                $before = array_fill_keys(self::split($base[$tab]), true);
                foreach ($items as $i) {
                    if (!isset($before[$i['key']]) && !self::background($tab, $i, (string) ($prev['at'] ?? ''))) {
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
            // Clés rangées par onglet, séparées par des espaces (fichier compact).
            JsonStore::write(self::FILE, ['new' => implode(' ', $new)] + $run + ['keys' => array_map(fn ($l) => implode(' ', $l), $keys), 'history' => $history]);
            self::$state = null;
            Activity::log($user, 'a lancé le contrôle complet : ' . self::counts($run), null);
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

    /**
     * Vérifications du site hors fiches : redirections, référentiels (adversaires, stades),
     * rubriques, traductions de l'interface.
     * @return list<array{tab:string,sev:string,code:string,msg:string,id:null,title:string,url:?string}>
     */
    public static function siteChecks(): array
    {
        $out = [];
        $add = function (string $tab, string $sev, string $code, string $msg, string $title, ?string $url) use (&$out) {
            $out[] = ['tab' => $tab, 'sev' => $sev, 'code' => $code, 'msg' => $msg, 'id' => null, 'title' => $title, 'url' => $url];
        };

        // Redirections : en boucle, en chaîne, vers une page absente ; inutiles (l'adresse affiche une page).
        $redirects = Redirects::all();
        foreach ($redirects as $from => $to) {
            [$from, $to] = [(string) $from, (string) $to];
            $url = '/admin/redirections?q=' . rawurlencode($from);
            $path = (string) (parse_url($to, PHP_URL_PATH) ?: '/');
            $external = (bool) preg_match('#^([a-z][a-z0-9+.-]*:|//)#i', $to);
            if (!$external && rtrim($path, '/') === rtrim($from, '/')) {
                $add('site', 'haute', 'redirection', 'Redirection vers elle-même : la page tourne en boucle', $from, $url);
            } elseif (!$external && isset($redirects[$path]) && !self::shows($path)) {
                $add('site', 'basse', 'redirection', 'Redirection en chaîne : ' . $to . ' est elle-même redirigée vers ' . $redirects[$path] . ' (viser directement la dernière adresse)', $from, $url);
            } elseif (!$external && ($why = self::missing($path)) !== null) {
                $add('site', 'moyenne', 'redirection', 'Redirection vers ' . $to . ' : ' . $why . ', l’ancien lien aboutit à « page introuvable »', $from, $url);
            } elseif (!str_contains($from, '?') && self::shows((string) (parse_url($from, PHP_URL_PATH) ?: '/'))) {
                $add('site', 'basse', 'redirection', 'Redirection jamais utilisée : l’adresse affiche déjà une page (à supprimer)', $from, $url);
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
                    $add('site', 'haute', 'referentiel', $label . ' en double (identifiant « ' . $id . ' » déjà utilisé) : à fusionner', "$label : $name", $url);
                }
                $ids[$id] = true;
                foreach (array_unique(array_merge([$name], array_map('strval', (array) ($c['aliases'] ?? [])))) as $a) {
                    $k = $keyOf($a);
                    if ($k !== '' && isset($names[$k]) && $names[$k][0] !== $id) {
                        $add('site', 'moyenne', 'referentiel', '« ' . $a . ' » désigne à la fois « ' . $names[$k][1] . ' » et « ' . $name . ' » : des matchs peuvent être rangés au mauvais endroit', "$label : $name", $url);
                    }
                    $names[$k] ??= [$id, $name];
                }
            }
        }

        // Rubriques rattachées à une rubrique parente supprimée.
        $cats = Categories::all();
        foreach ($cats as $slug => $c) {
            if (!empty($c['parent']) && !isset($cats[$c['parent']])) {
                $add('site', 'moyenne', 'rubrique', 'Rubrique rattachée à une rubrique qui n’existe plus (« ' . $c['parent'] . ' ») : à replacer', 'Rubrique : ' . ($c['name'] ?? $slug), '/admin/rubriques');
            }
        }

        // Traductions de l'interface : mêmes variables ({n}, {nom}…) et mêmes balises dans les deux langues.
        foreach ((array) (JsonStore::read(DATA_PATH . '/i18n/en.json', []) ?: []) as $fr => $en) {
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
                $add('traductions', 'haute', 'interface', 'Texte de l’interface : variables différentes en anglais (' . (implode(' ', $va) ?: 'aucune') . ' en français, ' . (implode(' ', $vb) ?: 'aucune') . ' en anglais) : le texte anglais s’affiche mal', $title, $url);
            } elseif ((bool) preg_match('#<[a-z/]#i', $fr) !== (bool) preg_match('#<[a-z/]#i', $en)) {
                $add('traductions', 'moyenne', 'interface', 'Texte de l’interface : liens ou mises en forme présents dans une seule des deux langues', $title, $url);
            }
        }
        return $out;
    }

    /** L'adresse affiche-t-elle une page du site (fiche publiée, rubrique, page calculée) ? */
    private static function shows(string $path): bool
    {
        static $seen = [];
        if (count($seen) > 20000) {
            $seen = [];
        }
        if (!isset($seen[$path])) {
            $s = Index::byPath($path);
            $seen[$path] = ($s && Index::visible($s)) || Categories::byPath($path) || in_array($path, ['/nos-lions/', '/matchs/'], true) || Kernel::isRoute($path);
        }
        return $seen[$path];
    }

    /** Pourquoi une adresse du site n'affiche rien (null si elle affiche une page). */
    private static function missing(string $path): ?string
    {
        if (self::shows($path)) {
            return null;
        }
        $s = Index::byPath($path);
        if ($s) {
            return 'fiche ' . ($s['status'] === 'corbeille' ? 'à la corbeille' : 'non publiée (' . mb_strtolower(Fiches::STATUSES[$s['status']] ?? $s['status']) . ')');
        }
        if (str_starts_with($path, '/media/')) {
            return Media::file(rawurldecode(substr($path, 7))) || is_file(PUBLIC_PATH . rawurldecode($path)) ? null : 'fichier absent';
        }
        return 'page introuvable';
    }

    /**
     * Fichiers de fiches modifiés hors du back-office (envoi par FTP) : l'index des fiches et
     * la recherche sont reconstruits. Renvoie le nombre de fiches qui n'étaient pas à jour.
     */
    private static function syncIndex(): int
    {
        Index::forget();
        $idx = Index::all();
        $stale = 0;
        foreach (Fiches::all() as $id => $doc) {
            $s = $idx[$id] ?? null;
            if (!$s || ($s['modified'] ?? null) !== ($doc['modified'] ?? null) || ($s['status'] ?? null) !== ($doc['status'] ?? null) || ($s['path'] ?? null) !== ($doc['path'] ?? null)) {
                $stale++;
            }
            unset($idx[$id]);
        }
        $stale += count($idx); // fiches supprimées du disque mais encore dans l'index
        if ($stale) {
            Index::rebuild();
            Search::rebuild();
        }
        return $stale;
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
