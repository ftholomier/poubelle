<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\JsonStore;
use App\Data\Derived;
use App\Data\Index;
use App\Data\OfficialPlayers;
use App\Front\AlbumBook;

/**
 * « Mon album » : la collection personnelle de vignettes de l'Album Sochini / Parini.
 *
 * Sans compte : une collection = un fichier storage/vignettes/c/{id}.json. Le cookie first-party
 * « sr_vignettes » (id.jeton, seule l'empreinte du jeton est gardée) l'ouvre sur l'appareil ; le
 * même « code de sauvegarde » la rouvre ailleurs. Un visiteur qui a un carnet du supporter garde sa
 * collection dans le carnet (fichier « k{id du carnet} ») : elle le suit sur tous ses appareils, et
 * la collection anonyme de l'appareil y est versée à la première visite.
 *
 * Tous les tirages sont faits ici (random_int), jamais dans le navigateur ; chaque pochette porte une
 * signature HMAC (id, date, vignettes), les codes d'échange aussi. Pas de donnée personnelle : des
 * numéros de fiches et des dates. Collections inactives depuis 18 mois effacées par le cron.
 */
final class Vignettes
{
    public static string $dir = STORAGE_PATH . '/vignettes';
    public const COOKIE = 'sr_vignettes';
    public const INACTIVE_MONTHS = 18;
    public const TRADE_DAYS = 14;
    /** Réglages par défaut (back-office : Interactif › Mon album). */
    public const DEFAULTS = ['enabled' => true, 'packs' => 1, 'size' => 5, 'shiny' => 12, 'gold' => 2, 'fiche' => 25, 'fiche_max' => 3, 'defi' => 3];
    private const ALPHA = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /** Date du jour figée par les tests. */
    public static ?string $today = null;

    public static function today(): string
    {
        return self::$today ?? date('Y-m-d');
    }

    // ------------------------------------------------------------------ réglages et statistiques

    public static function settings(): array
    {
        $s = JsonStore::read(self::$dir . '/reglages.json', []);
        return (is_array($s) ? array_intersect_key($s, self::DEFAULTS) : []) + self::DEFAULTS;
    }

    public static function saveSettings(array $in): array
    {
        $s = [
            'enabled' => !empty($in['enabled']),
            'packs' => max(0, min(10, (int) ($in['packs'] ?? 1))),
            'size' => max(1, min(10, (int) ($in['size'] ?? 5))),
            'shiny' => max(0, min(60, (int) ($in['shiny'] ?? 12))),
            'gold' => max(0, min(20, (int) ($in['gold'] ?? 2))),
            'fiche' => max(0, min(100, (int) ($in['fiche'] ?? 25))),
            'fiche_max' => max(0, min(20, (int) ($in['fiche_max'] ?? 3))),
            'defi' => max(0, min(10, (int) ($in['defi'] ?? 3))),
        ];
        JsonStore::write(self::$dir . '/reglages.json', $s);
        return $s;
    }

    private static function count(string $k, int $n = 1): void
    {
        JsonStore::update(self::$dir . '/stats.json', function ($s) use ($k, $n) {
            $s = is_array($s) ? $s : [];
            $s[$k] = ($s[$k] ?? 0) + $n;
            return $s;
        }, []);
    }

    /** Pour le back-office : collectionneurs, actifs sur 30 jours, pochettes, vignettes, échanges. */
    public static function stats(): array
    {
        $s = JsonStore::read(self::$dir . '/stats.json', []) ?: [];
        $files = glob(self::$dir . '/c/*.json') ?: [];
        $active = 0;
        $complete = 0;
        foreach ($files as $f) {
            if (filemtime($f) > time() - 30 * 86400) {
                $active++;
            }
        }
        $trades = glob(self::$dir . '/echanges/*.json') ?: [];
        return ['collectors' => count($files), 'active' => $active, 'packs' => (int) ($s['packs'] ?? 0), 'stickers' => (int) ($s['stickers'] ?? 0),
            'bonus' => (int) ($s['bonus'] ?? 0), 'trades' => (int) ($s['trades'] ?? 0), 'offers' => count($trades), 'pages' => (int) ($s['pages'] ?? 0), 'pool' => count(self::pool())];
    }

    // ------------------------------------------------------------------ vignettes à collectionner

    /**
     * Les vignettes : joueurs de la rubrique « Match officiel » (sinon : joueurs avec fiche visible
     * entrés en jeu en match officiel), numérotés, avec leur rareté (n normale, b brillante, o dorée).
     * @return array<int,array{no:int,name:string,line:string,r:string,image:?string,href:string}>
     */
    public static function pool(): array
    {
        static $memo = null;
        if ($memo !== null) {
            return $memo;
        }
        $file = STORAGE_PATH . '/cache/album/sochini/vignettes.php';
        $built = Derived::built() . '|' . (@filemtime(\App\Data\Index::CACHE) ?: 0);
        $c = \App\Core\PhpCache::read($file);
        if ($c && ($c['built'] ?? null) === $built) {
            return $memo = $c['data'];
        }
        $shiny = [];
        foreach (['buteurs', 'fideles', 'internationaux'] as $t) {
            foreach (AlbumBook::raw($t)['items'] ?? [] as $p) {
                if ($p['pid']) {
                    $shiny[(int) $p['pid']] = true;
                }
            }
        }
        $people = OfficialPlayers::items();
        if (!$people) {
            $seen = [];
            foreach (AlbumBook::catalog()['decennie'] ?? [] as $v => $_) {
                foreach (AlbumBook::raw('decennie', (string) $v)['items'] ?? [] as $p) {
                    if ($p['pid'] && empty($p['coach']) && ($s = Index::get((int) $p['pid'])) && Index::visible($s)) {
                        $seen[(int) $p['pid']] = $s;
                    }
                }
            }
            $people = array_values($seen);
        }
        usort($people, fn ($a, $b) => strcmp(Index::sortName($a), Index::sortName($b)));
        $out = [];
        $no = 0;
        foreach ($people as $s) {
            $p = $s['p'] ?? [];
            $r = !empty($p['legend']) ? 'o' : (isset($shiny[(int) $s['id']]) || !empty($p['intl']) ? 'b' : 'n');
            $out[(int) $s['id']] = ['no' => ++$no, 'name' => (string) ($p['name'] ?? $s['title']), 'line' => (string) ($p['line'] ?? ''), 'r' => $r,
                'image' => !empty($s['image']) && !Index::isPlaceholderImage($s['image']) ? (string) $s['image'] : null, 'href' => (string) $s['path']];
        }
        try {
            \App\Core\PhpCache::write($file, ['built' => $built, 'data' => $out]);
        } catch (\Throwable) {
        }
        return $memo = $out;
    }

    /**
     * Chapitres (une page = un album thématique) : l'album général, puis les décennies, les épopées
     * et les albums thématiques de l'Album Sochini, réduits aux vignettes collectionnables.
     * @return array<string,array{title:string,ids:list<int>}>
     */
    public static function chapters(): array
    {
        $pool = self::pool();
        $out = ['general' => ['title' => OfficialPlayers::enabled() ? t('Match officiel : l’album général') : t('L’album général'), 'ids' => array_keys($pool)]];
        foreach (['decennie', 'epopee', 'lionceaux', 'gardiens', 'buteurs', 'fideles'] as $type) {
            foreach (AlbumBook::catalog()[$type] ?? [] as $v => $a) {
                $ids = [];
                foreach (AlbumBook::raw($type, (string) $v)['items'] ?? [] as $p) {
                    if ($p['pid'] && isset($pool[(int) $p['pid']])) {
                        $ids[] = (int) $p['pid'];
                    }
                }
                $ids = array_values(array_unique($ids));
                if (count($ids) >= 3) {
                    $out[$type . ($v !== '' ? '-' . $v : '')] = ['title' => AlbumBook::title($a), 'ids' => $ids];
                }
            }
        }
        return $out;
    }

    // ------------------------------------------------------------------ collections

    private static function file(string $id): string
    {
        return self::$dir . '/c/' . $id . '.json';
    }

    private static function validId(string $id): bool
    {
        return (bool) preg_match('/^(a[a-f0-9]{16}|k[a-f0-9]{16})$/', $id);
    }

    public static function get(string $id): ?array
    {
        if (!self::validId($id)) {
            return null;
        }
        JsonStore::forget(self::file($id));
        $c = JsonStore::read(self::file($id), null);
        return is_array($c) ? $c : null;
    }

    private static function blank(string $id): array
    {
        return ['id' => $id, 'created' => date('c'), 'seen' => date('c'), 's' => [], 'days' => [], 'fiches' => [], 'fdays' => [], 'defi' => [], 'done' => [], 'offers' => [], 'packs' => 0, 'trades' => 0, 'last' => null];
    }

    /** Nouvelle collection anonyme : [collection, « id.jeton » à garder dans le cookie]. */
    public static function create(): array
    {
        $id = 'a' . bin2hex(random_bytes(8));
        $tok = bin2hex(random_bytes(16));
        $c = self::blank($id) + ['tok' => hash('sha256', $tok)];
        JsonStore::write(self::file($id), $c);
        return [$c, $id . '.' . $tok];
    }

    /** Collection ouverte par « id.jeton » (cookie ou code de sauvegarde), ou null. */
    public static function open(string $cred): ?array
    {
        $cred = strtolower(str_replace(['-', ' '], '', trim($cred)));
        if (!preg_match('/^(a[a-f0-9]{16})\.?([a-f0-9]{32})$/', $cred, $m)) {
            return null;
        }
        $c = self::get($m[1]);
        return $c && hash_equals((string) ($c['tok'] ?? ''), hash('sha256', $m[2])) ? $c : null;
    }

    /** Code de sauvegarde lisible (groupes de 4) d'un « id.jeton ». */
    public static function saveCode(string $cred): string
    {
        return strtoupper(implode('-', str_split(str_replace('.', '', $cred), 7)));
    }

    /**
     * Collection du visiteur : celle de son carnet s'il en a un (la collection anonyme de l'appareil
     * y est versée), sinon celle du cookie. null s'il n'en a pas encore.
     * @return array{0:?array,1:?string} [collection, nouveau cookie à poser (« » pour l'effacer) ou null]
     */
    public static function current(?string $cookie, ?array $carnet): array
    {
        $anon = $cookie ? self::open($cookie) : null;
        if ($carnet && preg_match('/^[a-f0-9]{16}$/', (string) $carnet['id'])) {
            $kid = 'k' . $carnet['id'];
            $c = self::get($kid);
            if ($anon) {
                $c = self::merge($anon, $kid);
                return [$c, ''];
            }
            return [$c, null];
        }
        return [$anon, null];
    }

    /** Verse une collection anonyme dans celle d'un carnet (vignettes, doubles, pages complétées). */
    public static function merge(array $anon, string $kid): array
    {
        $c = JsonStore::update(self::file($kid), function ($c) use ($anon, $kid) {
            $c = is_array($c) ? $c : self::blank($kid);
            foreach ($anon['s'] as $pid => $n) {
                $c['s'][$pid] = ($c['s'][$pid] ?? 0) + $n;
            }
            foreach (['days', 'fdays'] as $k) {
                foreach ($anon[$k] as $d => $n) {
                    $c[$k][$d] = max($c[$k][$d] ?? 0, $n);
                }
            }
            $c['fiches'] = array_values(array_unique(array_merge($c['fiches'], $anon['fiches'])));
            $c['defi'] = array_values(array_unique(array_merge($c['defi'], $anon['defi'])));
            $c['done'] += $anon['done'];
            $c['offers'] = array_values(array_unique(array_merge($c['offers'], $anon['offers'])));
            $c['packs'] += $anon['packs'];
            $c['trades'] += $anon['trades'];
            $c['seen'] = date('c');
            return $c;
        }, null);
        // Offres d'échange en cours : elles appartiennent désormais au carnet.
        foreach ($anon['offers'] as $code) {
            if (!is_file(self::tradeFile($code))) {
                continue;
            }
            JsonStore::update(self::$dir . '/echanges/' . $code . '.json', fn ($t) => is_array($t) && $t['from'] === $anon['id'] ? ['from' => $kid] + $t : $t, null);
        }
        JsonStore::delete(self::file($anon['id']));
        return $c;
    }

    /** Applique $fn à la collection (créée si besoin pour un carnet) sous verrou ; renvoie [collection, résultat]. */
    private static function mutate(string $id, callable $fn): array
    {
        $out = null;
        $c = JsonStore::update(self::file($id), function ($c) use ($fn, $id, &$out) {
            $c = is_array($c) ? $c : self::blank($id);
            $out = $fn($c);
            $c['seen'] = date('c');
            // Garde les compteurs des 7 derniers jours seulement.
            foreach (['days', 'fdays'] as $k) {
                $c[$k] = array_filter($c[$k], fn ($d) => $d >= date('Y-m-d', strtotime(self::today() . ' -7 days')), ARRAY_FILTER_USE_KEY);
            }
            $c['defi'] = array_values(array_filter($c['defi'], fn ($d) => $d >= date('Y-m-d', strtotime(self::today() . ' -7 days'))));
            return $c;
        }, null);
        return [$c, $out];
    }

    public static function sign(string ...$parts): string
    {
        return substr(hash_hmac('sha256', implode('|', $parts), site_key('vignettes')), 0, 24);
    }

    // ------------------------------------------------------------------ pochettes et bonus

    /** Pochettes encore disponibles aujourd'hui. */
    public static function packsLeft(array $c): int
    {
        return max(0, self::settings()['packs'] - (int) ($c['days'][self::today()] ?? 0));
    }

    /** Tire $n vignettes (rareté puis joueur au hasard), côté serveur. @return list<array{pid:int,r:string}> */
    public static function draw(int $n): array
    {
        $pool = self::pool();
        if (!$pool) {
            return [];
        }
        $set = self::settings();
        $tiers = ['n' => [], 'b' => [], 'o' => []];
        foreach ($pool as $pid => $p) {
            $tiers[$p['r']][] = $pid;
        }
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $roll = random_int(1, 1000);
            $r = $roll <= $set['gold'] * 10 ? 'o' : ($roll <= ($set['gold'] + $set['shiny']) * 10 ? 'b' : 'n');
            if (!$tiers[$r]) {
                $r = $tiers['n'] ? 'n' : ($tiers['b'] ? 'b' : 'o');
            }
            $pid = $tiers[$r][random_int(0, count($tiers[$r]) - 1)];
            $out[] = ['pid' => $pid, 'r' => $pool[$pid]['r']];
        }
        return $out;
    }

    /** Colle des vignettes : renvoie celles-ci avec « new » (première fois) ; met à jour les pages complétées. */
    private static function stick(array &$c, array $drawn): array
    {
        foreach ($drawn as &$d) {
            $d['new'] = empty($c['s'][$d['pid']]);
            $c['s'][$d['pid']] = ($c['s'][$d['pid']] ?? 0) + 1;
        }
        unset($d);
        return $drawn;
    }

    /** Pages complétées depuis le dernier passage (enregistrées avec leur date). */
    private static function checkPages(array &$c): array
    {
        $new = [];
        foreach (self::chapters() as $k => $ch) {
            if (isset($c['done'][$k])) {
                continue;
            }
            if ($ch['ids'] && !array_diff($ch['ids'], array_map('intval', array_keys(array_filter($c['s']))))) {
                $c['done'][$k] = self::today();
                $new[] = $k;
            }
        }
        return $new;
    }

    /**
     * Ouvre la pochette du jour. ['error'] si plus de pochette (ou album désactivé).
     * @return array{pack?:list<array>,sig?:string,pages?:list<string>,error?:string}
     */
    public static function openPack(string $id): array
    {
        $set = self::settings();
        if (!$set['enabled']) {
            return ['error' => t('L’album est fermé pour le moment.')];
        }
        [$c, $res] = self::mutate($id, function (array &$c) use ($set) {
            if (self::packsLeft($c) <= 0) {
                return ['error' => t('Pochette du jour déjà ouverte : revenez demain, ou gagnez des vignettes bonus en visitant le musée.')];
            }
            $drawn = self::draw($set['size']);
            if (!$drawn) {
                return ['error' => t('Aucune vignette à collectionner pour le moment.')];
            }
            $c['days'][self::today()] = ($c['days'][self::today()] ?? 0) + 1;
            $c['packs']++;
            $pack = self::stick($c, $drawn);
            $sig = self::sign($c['id'], self::today(), (string) $c['packs'], implode(',', array_column($pack, 'pid')));
            $c['last'] = ['at' => date('c'), 'pids' => array_column($pack, 'pid'), 'sig' => $sig];
            return ['pack' => $pack, 'sig' => $sig, 'pages' => self::checkPages($c)];
        });
        if (isset($res['pack'])) {
            self::count('packs');
            self::count('stickers', count($res['pack']));
            if ($res['pages']) {
                self::count('pages', count($res['pages']));
            }
        }
        return $res;
    }

    /**
     * Bonus en visitant le musée : « fiche » (première visite d'une fiche joueur : une chance sur
     * N de gagner sa vignette, plafonné par jour) ou « defi » (défi du jour terminé avec au moins
     * 5 bonnes réponses : quelques vignettes, une fois par jour).
     */
    public static function bonus(string $id, string $kind, array $data = []): array
    {
        $set = self::settings();
        if (!$set['enabled']) {
            return ['pack' => []];
        }
        $pool = self::pool();
        [$c, $res] = self::mutate($id, function (array &$c) use ($kind, $data, $set, $pool) {
            $today = self::today();
            if ($kind === 'fiche') {
                $pid = (int) ($data['pid'] ?? 0);
                if (!isset($pool[$pid]) || in_array($pid, $c['fiches'], true)) {
                    return ['pack' => []];
                }
                $c['fiches'][] = $pid;
                $c['fiches'] = array_slice($c['fiches'], -3000);
                if (($c['fdays'][$today] ?? 0) >= $set['fiche_max'] || random_int(1, 100) > $set['fiche']) {
                    return ['pack' => []];
                }
                $c['fdays'][$today] = ($c['fdays'][$today] ?? 0) + 1;
                $pack = self::stick($c, [['pid' => $pid, 'r' => $pool[$pid]['r']]]);
            } elseif ($kind === 'defi') {
                if (in_array($today, $c['defi'], true) || empty($data['ok'])) {
                    return ['pack' => []];
                }
                $c['defi'][] = $today;
                $pack = self::stick($c, self::draw($set['defi']));
            } else {
                return ['pack' => []];
            }
            return ['pack' => $pack, 'sig' => self::sign($c['id'], $today, $kind, implode(',', array_column($pack, 'pid'))), 'pages' => self::checkPages($c)];
        });
        if (!empty($res['pack'])) {
            self::count('bonus', count($res['pack']));
            self::count('stickers', count($res['pack']));
        }
        return $res;
    }

    /** Défi du jour réussi (au moins 5 bonnes réponses) pour la clé du joueur ? */
    public static function defiPassed(?string $key): bool
    {
        if ($key === null) {
            return false;
        }
        $s = DailyQuiz::state(DailyQuiz::today(), $key);
        return ($s['phase'] ?? '') === 'end' && (int) ($s['good'] ?? 0) >= 5;
    }

    // ------------------------------------------------------------------ échanges

    private static function tradeFile(string $code): string
    {
        return self::$dir . '/echanges/' . $code . '.json';
    }

    public static function normCode(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
    }

    /** « Je donne X contre Y » : X doit être un double ; il est mis de côté jusqu'à l'échange ou l'annulation. */
    public static function offer(string $id, int $give, int $want): array
    {
        $pool = self::pool();
        if (!isset($pool[$give], $pool[$want]) || $give === $want) {
            return ['error' => t('Choisissez une vignette à donner et une autre à recevoir.')];
        }
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= self::ALPHA[random_int(0, strlen(self::ALPHA) - 1)];
        }
        [, $res] = self::mutate($id, function (array &$c) use ($give, $want, $code) {
            if (($c['s'][$give] ?? 0) < 2) {
                return ['error' => t('Seul un double peut être échangé.')];
            }
            if (count($c['offers']) >= 10) {
                return ['error' => t('Dix échanges en attente au plus : annulez-en un.')];
            }
            $c['s'][$give]--;
            $c['offers'][] = $code;
            return ['ok' => true];
        });
        if (!empty($res['error'])) {
            return $res;
        }
        JsonStore::write(self::tradeFile($code), ['code' => $code, 'from' => $id, 'give' => $give, 'want' => $want, 'at' => date('c'), 'sig' => self::sign($code, $id, (string) $give, (string) $want)]);
        return ['code' => substr($code, 0, 4) . '-' . substr($code, 4)];
    }

    /** Offre d'échange lisible (pour la montrer avant d'accepter), ou null. */
    public static function trade(string $code): ?array
    {
        $code = self::normCode($code);
        if (!preg_match('/^[A-Z0-9]{8}$/', $code)) {
            return null;
        }
        JsonStore::forget(self::tradeFile($code));
        $t = JsonStore::read(self::tradeFile($code), null);
        if (!is_array($t) || !hash_equals($t['sig'], self::sign($t['code'], $t['from'], (string) $t['give'], (string) $t['want']))) {
            return null;
        }
        return $t;
    }

    /** Accepte un échange : je reçois X et je donne Y (un double). Un code ne sert qu'une fois. */
    public static function accept(string $id, string $code): array
    {
        $t = self::trade($code);
        if (!$t || !empty($t['used'])) {
            return ['error' => t('Code d’échange inconnu, expiré ou déjà utilisé.')];
        }
        if ($t['from'] === $id) {
            return ['error' => t('C’est votre propre offre : donnez ce code à un autre collectionneur.')];
        }
        // Le code est « consommé » d'abord (sous verrou) : deux acceptations simultanées ne passent pas.
        $claimed = false;
        JsonStore::update(self::tradeFile($t['code']), function ($x) use ($id, &$claimed) {
            if (!is_array($x) || !empty($x['used'])) {
                return $x;
            }
            $claimed = true;
            return ['used' => date('c'), 'by' => $id] + $x;
        }, null);
        if (!$claimed) {
            return ['error' => t('Code d’échange inconnu, expiré ou déjà utilisé.')];
        }
        [, $res] = self::mutate($id, function (array &$c) use ($t) {
            if (($c['s'][$t['want']] ?? 0) < 2) {
                return ['error' => t('Il vous faut la vignette demandée en double pour cet échange.')];
            }
            $c['s'][$t['want']]--;
            $c['s'][$t['give']] = ($c['s'][$t['give']] ?? 0) + 1;
            $c['trades']++;
            return ['ok' => true, 'pages' => self::checkPages($c), 'got' => $t['give']];
        });
        if (!empty($res['error'])) {
            // Rendu au donneur : le code reste valable.
            JsonStore::update(self::tradeFile($t['code']), fn ($x) => array_diff_key((array) $x, ['used' => 1, 'by' => 1]), null);
            return $res;
        }
        self::mutate($t['from'], function (array &$c) use ($t) {
            $c['s'][$t['want']] = ($c['s'][$t['want']] ?? 0) + 1;
            $c['offers'] = array_values(array_diff($c['offers'], [$t['code']]));
            $c['trades']++;
            self::checkPages($c);
            return null;
        });
        self::count('trades');
        return $res;
    }

    /** Annule une offre : le double mis de côté revient au donneur. */
    public static function cancel(string $id, string $code): bool
    {
        $t = self::trade($code);
        if (!$t || $t['from'] !== $id || !empty($t['used'])) {
            return false;
        }
        JsonStore::delete(self::tradeFile($t['code']));
        self::mutate($id, function (array &$c) use ($t) {
            $c['s'][$t['give']] = ($c['s'][$t['give']] ?? 0) + 1;
            $c['offers'] = array_values(array_diff($c['offers'], [$t['code']]));
            return null;
        });
        return true;
    }

    // ------------------------------------------------------------------ état, badges, purge

    /** État envoyé au navigateur : vignettes (n° fiche => exemplaires), pochettes, pages, offres. */
    public static function state(?array $c): array
    {
        $chapters = [];
        $owned = $c ? array_map('intval', array_keys(array_filter($c['s']))) : [];
        $ownedSet = array_flip($owned);
        foreach (self::chapters() as $k => $ch) {
            $have = count(array_filter($ch['ids'], fn ($id) => isset($ownedSet[$id])));
            $chapters[] = ['k' => $k, 'title' => $ch['title'], 'ids' => $ch['ids'], 'have' => $have, 'total' => count($ch['ids']), 'done' => $c['done'][$k] ?? null];
        }
        $offers = [];
        foreach ($c['offers'] ?? [] as $code) {
            if ($t = self::trade($code)) {
                $offers[] = ['code' => substr($code, 0, 4) . '-' . substr($code, 4), 'give' => $t['give'], 'want' => $t['want'], 'used' => !empty($t['used'])];
            }
        }
        $s = $c['s'] ?? [];
        return ['s' => (object) $s, 'packsLeft' => $c ? self::packsLeft($c) : self::settings()['packs'], 'size' => self::settings()['size'],
            'chapters' => $chapters, 'offers' => $offers, 'doubles' => array_sum(array_map(fn ($n) => max(0, $n - 1), $s)),
            'distinct' => count($owned), 'packs' => (int) ($c['packs'] ?? 0), 'linked' => $c && str_starts_with($c['id'], 'k'), 'enabled' => self::settings()['enabled']];
    }

    /** Pages complétées d'un carnet (badges de la page publique). @return list<array{title:string,date:string,k:string}> */
    public static function badges(string $carnetId): array
    {
        $c = self::get('k' . $carnetId);
        if (!$c || !$c['done']) {
            return [];
        }
        $ch = self::chapters();
        $out = [];
        foreach ($c['done'] as $k => $d) {
            if (isset($ch[$k])) {
                $out[] = ['k' => $k, 'title' => $ch[$k]['title'], 'date' => $d];
            }
        }
        return $out;
    }

    /** Carnet supprimé : sa collection de vignettes aussi. */
    public static function forget(string $carnetId): void
    {
        if (preg_match('/^[a-f0-9]{16}$/', $carnetId)) {
            JsonStore::delete(self::file('k' . $carnetId));
        }
    }

    /** Cron : collections inactives depuis 18 mois, offres d'échange expirées (double rendu au donneur). */
    public static function purge(): int
    {
        $n = 0;
        $limit = time() - self::INACTIVE_MONTHS * 31 * 86400;
        foreach (glob(self::$dir . '/c/*.json') ?: [] as $f) {
            $c = JsonStore::read($f, null);
            $seen = is_array($c) ? strtotime((string) ($c['seen'] ?? '')) : false;
            if (($seen ?: filemtime($f)) < $limit) {
                JsonStore::delete($f);
                $n++;
            }
        }
        foreach (glob(self::$dir . '/echanges/*.json') ?: [] as $f) {
            $t = JsonStore::read($f, null);
            $at = is_array($t) ? strtotime((string) (($t['used'] ?? null) ?: $t['at'])) : 0;
            if ($at < time() - self::TRADE_DAYS * 86400) {
                if (is_array($t) && empty($t['used']) && self::get($t['from'])) {
                    self::cancel($t['from'], $t['code']);
                } else {
                    JsonStore::delete($f);
                }
                $n++;
            }
        }
        return $n;
    }
}
