<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Club;
use App\Core\JsonStore;
use App\Data\Derived;
use App\Data\Index;
use App\Data\Names;

/**
 * Relevé automatique des totaux de référence des joueurs (matchs et buts officiels au club) depuis les
 * classements publiés que déclare le musée (config/clubs/<id>/totaux-sources.php). Les deux pages sont
 * téléchargées, leurs classements lus, chaque nom rapproché d'une fiche joueur du musée (mêmes mots sans
 * accents ni majuscules, puis mots inclus ; noms rebelles : totaux-alias.php), et le résultat, par id de
 * fiche, est écrit dans STORAGE_PATH/totaux-reference.json, que lit App\Data\Totals avant le fichier livré
 * config/clubs/<id>/totaux-reference.php.
 *
 * Garde-fous : si le relevé compte nettement moins de joueurs que le précédent, ou si des totaux baissent
 * fortement (page en travaux, mise en page changée), rien n'est écrit ; l'anomalie est journalisée et
 * affichée dans Système › Serveur / tâches planifiées. Musée sans sources : rien ne se passe.
 */
final class ReferenceTotals
{
    public const FILE = STORAGE_PATH . '/totaux-reference.json';
    public const STATE = STORAGE_PATH . '/totaux-reference-etat.json';
    /** En dessous de cette part du relevé précédent (nombre de joueurs), le relevé est refusé. */
    private const MIN_SHARE = 0.9;
    /** Classement trop court pour être le bon : refusé d'office. */
    private const MIN_PLAYERS = 50;

    /** Sources déclarées par le musée, ou null. */
    public static function sources(): ?array
    {
        $s = Club::content('totaux-sources', []);
        return is_array($s) && !empty($s['buteurs']) && !empty($s['matchs']) ? $s : null;
    }

    public static function enabled(): bool
    {
        return self::sources() !== null;
    }

    /** Dernier relevé (état) : at, ok, message, joueurs, non_rapproches, refus. */
    public static function state(): array
    {
        return (array) (JsonStore::read(self::STATE, []) ?: []);
    }

    /** Totaux relevés (fichier de STORAGE_PATH), ou null. */
    public static function stored(): ?array
    {
        $d = JsonStore::read(self::FILE);
        return is_array($d) && !empty($d['joueurs']) ? $d : null;
    }

    /**
     * Relève, vérifie et écrit. @return array{ok:bool,message:string,joueurs:int,non_rapproches:list<string>}
     * $dry : calcule sans rien écrire (essais).
     */
    public static function run(bool $dry = false): array
    {
        $src = self::sources();
        if (!$src) {
            return ['ok' => true, 'message' => 'aucune source déclarée', 'joueurs' => 0, 'non_rapproches' => []];
        }
        try {
            $ref = self::fetch($src);
            $res = self::match($ref);
        } catch (\Throwable $e) {
            return self::fail('relevé impossible : ' . $e->getMessage(), [], $dry);
        }
        $problem = self::check($res['joueurs'], self::previous());
        if ($problem) {
            return self::fail($problem, $res['non_rapproches'], $dry, count($res['joueurs']));
        }
        $data = [
            'source' => [
                'label' => (string) ($src['label'] ?? ''),
                'url' => (string) $src['buteurs'],
                'url_matchs' => (string) $src['matchs'],
                'date' => date('Y-m-d'),
                'modifie' => $ref['modified'],
            ],
            'joueurs' => $res['joueurs'],
            'non_rapproches' => $res['non_rapproches'],
        ];
        $msg = count($res['joueurs']) . ' joueurs' . ($res['non_rapproches'] ? ', ' . count($res['non_rapproches']) . ' nom(s) non rapproché(s)' : '');
        if ($dry) {
            return ['ok' => true, 'message' => $msg, 'joueurs' => count($res['joueurs']), 'non_rapproches' => $res['non_rapproches'], 'data' => $data];
        }
        $old = self::stored();
        $changed = !$old || ($old['joueurs'] ?? null) != $data['joueurs'];
        if ($changed || ($old['source'] ?? []) != $data['source']) {
            JsonStore::write(self::FILE, $data);
        }
        if ($changed) {
            self::invalidate();
            $msg .= ' (totaux mis à jour)';
        }
        JsonStore::write(self::STATE, ['at' => time(), 'ok' => true, 'message' => $msg, 'joueurs' => count($res['joueurs']), 'non_rapproches' => $res['non_rapproches']]);
        return ['ok' => true, 'message' => $msg, 'joueurs' => count($res['joueurs']), 'non_rapproches' => $res['non_rapproches']];
    }

    private static function fail(string $msg, array $unmatched, bool $dry, int $n = 0): array
    {
        error_log('[totaux-reference] ' . Club::id() . ' : ' . $msg);
        if (!$dry) {
            $prev = self::state();
            JsonStore::write(self::STATE, ['at' => time(), 'ok' => false, 'message' => $msg, 'joueurs' => $prev['joueurs'] ?? 0,
                'non_rapproches' => $unmatched ?: ($prev['non_rapproches'] ?? []), 'dernier_bon' => $prev['ok'] ?? false ? ($prev['at'] ?? null) : ($prev['dernier_bon'] ?? null)]);
        }
        return ['ok' => false, 'message' => $msg, 'joueurs' => $n, 'non_rapproches' => $unmatched];
    }

    /** Totaux en vigueur (relevé précédent, sinon fichier livré), par id de fiche. */
    private static function previous(): array
    {
        if ($s = self::stored()) {
            return array_map(fn ($t) => (array) $t, (array) $s['joueurs']);
        }
        $out = [];
        foreach ((array) (Club::content('totaux-reference', [])['joueurs'] ?? []) as $path => $t) {
            if ($f = Index::byPath((string) $path)) {
                $out[(string) $f['id']] = (array) $t;
            }
        }
        return $out;
    }

    /** Garde-fous : message du problème, ou null si le relevé est vraisemblable. */
    public static function check(array $new, array $old): ?string
    {
        if (count($new) < self::MIN_PLAYERS) {
            return 'relevé refusé : ' . count($new) . ' joueurs seulement (classement illisible ?)';
        }
        if ($old && count($new) < self::MIN_SHARE * count($old)) {
            return sprintf('relevé refusé : %d joueurs au lieu de %d la fois précédente', count($new), count($old));
        }
        $drops = [];
        $sum = ['matchs' => [0, 0], 'buts' => [0, 0]];
        foreach ($old as $id => $o) {
            $n = $new[$id] ?? null;
            foreach (['matchs', 'buts'] as $k) {
                $sum[$k][0] += (int) ($o[$k] ?? 0);
                $sum[$k][1] += (int) ($n[$k] ?? 0);
                if ($n && ($a = (int) ($o[$k] ?? 0)) - ($b = (int) ($n[$k] ?? 0)) >= 5 && $b < 0.8 * $a) {
                    $drops[] = ($o['nom'] ?? "#$id") . " ($k : $a → $b)";
                }
            }
        }
        foreach ($sum as $k => [$a, $b]) {
            if ($a > 0 && $b < 0.97 * $a) {
                return "relevé refusé : total des $k en baisse ($a → $b)";
            }
        }
        if ($drops) {
            return 'relevé refusé : totaux en forte baisse pour ' . implode(', ', array_slice($drops, 0, 5)) . (count($drops) > 5 ? '…' : '');
        }
        return null;
    }

    /** Vide les caches qui montrent ces totaux (pages, « Les chiffres »). */
    public static function invalidate(): void
    {
        \App\Data\Totals::forget();
        foreach (glob(STORAGE_PATH . '/cache/chiffres-*.json') ?: [] as $f) {
            @unlink($f);
        }
        \App\Core\PageCache::purge();
    }

    // ------------------------------------------------------------------ téléchargement et lecture

    /** @return array{buteurs:list<array{0:string,1:int}>,matchs:list<array{0:string,1:int}>,modified:?string} */
    public static function fetch(array $src): array
    {
        $out = ['modified' => null];
        foreach (['buteurs', 'matchs'] as $k) {
            [$text, $mod] = self::page((string) $src[$k]);
            $out[$k] = $k === 'buteurs' ? self::parseScorers($text) : self::parseApps($text);
            $out['modified'] = max((string) $out['modified'], (string) $mod) ?: null;
            if (!$out[$k]) {
                throw new \RuntimeException("classement des $k illisible ($src[$k])");
            }
        }
        return $out;
    }

    /** Texte d'une page WordPress : API wp-json (articles puis pages), sinon la page HTML. @return array{0:string,1:?string} */
    private static function page(string $url): array
    {
        $p = parse_url($url);
        $slug = basename(rtrim((string) ($p['path'] ?? ''), '/'));
        $base = ($p['scheme'] ?? 'https') . '://' . ($p['host'] ?? '');
        foreach (['posts', 'pages'] as $type) {
            $j = json_decode((string) self::get("$base/wp-json/wp/v2/$type?slug=" . rawurlencode($slug) . '&_fields=modified,content'), true);
            if (!empty($j[0]['content']['rendered'])) {
                return [self::text((string) $j[0]['content']['rendered']), $j[0]['modified'] ?? null];
            }
        }
        $html = self::get($url);
        if ($html === null) {
            throw new \RuntimeException("page injoignable ($url)");
        }
        return [self::text($html), null];
    }

    private static function get(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 4,
            // Adresses web seulement, redirections comprises (jamais file://, gopher://…).
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'MuseeRetro/1.0 (' . base_url() . ')',
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return $code === 200 && is_string($body) && $body !== '' ? $body : null;
    }

    /** Texte brut : balises, codes courts du constructeur de pages ([et_pb_…]) et entités retirés. */
    public static function text(string $html): string
    {
        $html = (string) preg_replace('#<(script|style)\b.*?</\1>#is', ' ', $html);
        $s = html_entity_decode(strip_tags((string) preg_replace('#<br\s*/?>|</p>#i', "\n", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = (string) preg_replace('/\[[^\]]*\]/u', ' ', $s);
        return trim((string) preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', $s)));
    }

    /** « 1er – Kylian MBAPPE 256 buts 2e – … » (ex aequo séparés par des virgules). @return list<array{0:string,1:int}> */
    public static function parseScorers(string $t): array
    {
        $t = substr($t, (int) strpos($t, '1er'));
        preg_match_all('/(\d+)(?:er|e) –(.*?)(\d+) buts?/u', $t, $m, PREG_SET_ORDER);
        $out = [];
        foreach ($m as $x) {
            foreach (self::names($x[2]) as $n) {
                $out[] = [$n, (int) $x[3]];
            }
        }
        return $out;
    }

    /** « 1) Marquinhos 529 2) … ». @return list<array{0:string,1:int}> */
    public static function parseApps(string $t): array
    {
        $t = substr($t, (int) strpos($t, '1)'));
        preg_match_all('/(\d+)\)(.*?)(\d+)(?= \d+\)| *$)/u', $t, $m, PREG_SET_ORDER);
        $out = [];
        foreach ($m as $x) {
            foreach (self::names(str_replace('Zlatan, Ibrahimovic', 'Zlatan Ibrahimovic', $x[2])) as $n) {
                $out[] = [$n, (int) $x[3]];
            }
        }
        return $out;
    }

    /** @return list<string> */
    private static function names(string $s): array
    {
        return array_values(array_filter(array_map(fn ($n) => trim($n, " ,–-"), explode(',', $s)), fn ($n) => $n !== ''));
    }

    // ------------------------------------------------------------------ rapprochement

    /** Mots d'un nom, sans accents ni majuscules (« Jr » et « Junior » ignorés). @return list<string> */
    public static function tokens(string $s): array
    {
        $w = preg_split('/[^a-z]+/', Names::ascii(str_replace('’', "'", $s))) ?: [];
        return array_values(array_unique(array_filter($w, fn ($x) => $x !== '' && $x !== 'jr' && $x !== 'junior')));
    }

    /**
     * Noms des classements => fiches joueurs du musée (ceux qui ont joué, ou marqué pour les buteurs, en
     * match officiel), comme le rapprochement manuel : alias, mêmes mots, mots inclus dans un sens puis
     * dans l'autre ; à égalité, la fiche pas encore prise par un nom mieux classé.
     *
     * @return array{joueurs:array<string,array{nom:string,ref:string,matchs:?int,buts:int}>,non_rapproches:list<string>}
     */
    public static function match(array $ref): array
    {
        $M = Derived::part('matches');
        $pool = ['matchs' => [], 'buteurs' => []];
        foreach (Derived::part('apps') as $a) {
            [$pid, $mid, $g, , , , $role] = $a;
            $x = $M[$mid] ?? null;
            if ($role !== 'player' || !$x || !$x['v'] || in_array($x['comp'], Derived::OFFICIAL_EXCLUDED, true)) {
                continue;
            }
            $pool['matchs'][$pid] = true;
            if ($g > 0) {
                $pool['buteurs'][$pid] = true;
            }
        }
        $title = [];
        $byTitle = [];
        foreach (array_keys($pool['matchs']) as $pid) {
            $s = Index::get((int) $pid);
            if ($s && ($s['type'] ?? '') === 'personne') {
                $title[$pid] = (string) $s['title'];
                $byTitle[(string) $s['title']] ??= $pid;
            }
        }
        $alias = (array) Club::content('totaux-alias', []);
        $out = [];
        $unmatched = [];
        $all = array_map(fn ($n) => self::tokens($n), $title);
        foreach (['matchs', 'buteurs'] as $k) {
            $cand = [];
            foreach ($pool[$k] as $pid => $_) {
                if (isset($title[$pid])) {
                    $cand[$pid] = self::tokens($title[$pid]);
                }
            }
            $used = [];
            foreach ($ref[$k] as [$name, $v]) {
                $pid = null;
                if (isset($alias[$name])) {
                    $a = (string) $alias[$name];
                    $f = str_starts_with($a, '/') ? Index::byPath($a) : null;
                    $pid = $f ? (int) $f['id'] : ($byTitle[$a] ?? null);
                }
                if (!$pid) {
                    $t = self::tokens($name);
                    $found = [];
                    // Buteur que les comptes rendus du musée ne font pas marquer : cherché parmi tous les joueurs.
                    foreach ($k === 'buteurs' ? [$cand, $all] : [$cand] as $set) {
                        foreach ([fn ($s) => !array_diff($s, $t) && !array_diff($t, $s), fn ($s) => $t && !array_diff($t, $s), fn ($s) => $s && !array_diff($s, $t)] as $test) {
                            if ($found = array_keys(array_filter($set, $test))) {
                                break 2;
                            }
                        }
                    }
                    if (count($found) > 1) {
                        $free = array_values(array_filter($found, fn ($p) => !isset($used[$p])));
                        $found = $free ?: $found;
                    }
                    $pid = count($found) === 1 ? (int) $found[0] : null;
                }
                if (!$pid) {
                    $unmatched[] = "$name ($v $k)";
                    continue;
                }
                $used[$pid] = true;
                $id = (string) $pid;
                $out[$id] ??= ['nom' => $title[$pid] ?? (string) (Index::get($pid)['title'] ?? $name), 'ref' => $name, 'matchs' => null, 'buts' => 0];
                $out[$id][$k === 'matchs' ? 'matchs' : 'buts'] = $v;
            }
        }
        uasort($out, fn ($a, $b) => [$b['matchs'], $b['buts'], $a['nom']] <=> [$a['matchs'], $a['buts'], $b['nom']]);
        return ['joueurs' => $out, 'non_rapproches' => $unmatched];
    }
}
