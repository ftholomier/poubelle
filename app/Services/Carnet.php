<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\JsonStore;
use App\Data\Derived;
use App\Data\Index;

/**
 * Carnet du supporter : les matchs qu'un supporter a vus au stade (à Bonal ou en déplacement),
 * son bilan, ses badges, une carte à partager et, s'il le veut, une page publique sous pseudo.
 *
 * Pas de compte ni de mot de passe : un carnet = un fichier storage/carnets/{id}.json et un jeton
 * secret (seule son empreinte est gardée). Le cookie « sr_carnet » (id.jeton) ouvre le carnet sur
 * l'appareil ; le même lien, envoyé à l'e-mail du supporter (obligatoire), l'ouvre ailleurs. Un
 * e-mail n'a qu'un carnet : le redemander renvoie le lien à cette adresse, jamais à celui qui tape.
 */
final class Carnet
{
    public static string $dir = STORAGE_PATH . '/carnets';
    public const COOKIE = 'sr_carnet';
    public const MAX = 3000;

    /** Adversaires des derbys de l'Est. */
    private const DERBYS = ['Besançon', 'Nancy', 'Metz', 'Strasbourg', 'Mulhouse', 'Dijon'];

    // ------------------------------------------------------------------ carnets

    private static function file(string $id): string
    {
        return self::$dir . '/' . $id . '.json';
    }

    private static function indexFile(): string
    {
        return self::$dir . '/index.json';
    }

    public static function emailKey(string $email): string
    {
        return hash_hmac('sha256', mb_strtolower(trim($email)), site_key('carnet-email'));
    }

    public static function get(string $id): ?array
    {
        if (!preg_match('/^[a-f0-9]{16}$/', $id)) {
            return null;
        }
        JsonStore::forget(self::file($id));
        $c = JsonStore::read(self::file($id), null);
        return is_array($c) ? $c : null;
    }

    /** Carnet ouvert par « id.jeton » (cookie ou lien), ou null. */
    public static function open(string $credential): ?array
    {
        if (!preg_match('/^([a-f0-9]{16})\.([a-f0-9]{32})$/', $credential, $m)) {
            return null;
        }
        $c = self::get($m[1]);
        if (!$c) {
            return null;
        }
        $h = hash('sha256', $m[2]);
        foreach ((array) $c['tokens'] as $t) {
            if (hash_equals((string) $t, $h)) {
                return $c;
            }
        }
        return null;
    }

    /** Carnet de la personne qui fait la requête (cookie), ou null. */
    public static function current(): ?array
    {
        return self::open((string) ($_COOKIE[self::COOKIE] ?? ''));
    }

    /**
     * Crée le carnet de cet e-mail. @return array{carnet:?array,credential:?string,existing:bool}
     * Si l'e-mail a déjà un carnet, rien n'est créé : l'appelant envoie un lien à cette adresse.
     */
    public static function create(string $email, string $lang = 'fr'): array
    {
        $key = self::emailKey($email);
        $existing = null;
        $id = bin2hex(random_bytes(8));
        $token = bin2hex(random_bytes(16));
        JsonStore::update(self::indexFile(), function ($idx) use ($key, $id, &$existing) {
            $idx = is_array($idx) ? $idx : [];
            $idx['emails'] ??= [];
            $idx['slugs'] ??= [];
            if (isset($idx['emails'][$key])) {
                $existing = $idx['emails'][$key];
                return $idx;
            }
            $idx['emails'][$key] = $id;
            return $idx;
        }, []);
        if ($existing !== null) {
            return ['carnet' => self::get($existing), 'credential' => null, 'existing' => true];
        }
        $c = ['id' => $id, 'email' => mb_strtolower(trim($email)), 'tokens' => [hash('sha256', $token)], 'created' => date('c'), 'confirmed' => false,
            'lang' => $lang === 'en' ? 'en' : 'fr', 'pseudo' => '', 'public' => false, 'slug' => '', 'matches' => []];
        JsonStore::write(self::file($id), $c);
        return ['carnet' => $c, 'credential' => $id . '.' . $token, 'existing' => false];
    }

    /**
     * Nouveau lien d'accès pour un carnet existant (autre appareil, lien perdu), envoyé seulement
     * à l'adresse du carnet. Les cinq derniers liens restent valables (appareils déjà ouverts).
     */
    public static function newCredential(string $id): ?string
    {
        $token = bin2hex(random_bytes(16));
        $ok = false;
        JsonStore::update(self::file($id), function ($c) use ($token, &$ok) {
            if (!is_array($c)) {
                return $c;
            }
            $ok = true;
            $c['tokens'] = array_slice(array_merge((array) $c['tokens'], [hash('sha256', $token)]), -5);
            return $c;
        }, null);
        return $ok ? $id . '.' . $token : null;
    }

    /** Carnet d'un e-mail, ou null. */
    public static function byEmail(string $email): ?array
    {
        $id = (JsonStore::read(self::indexFile(), []) ?: [])['emails'][self::emailKey($email)] ?? null;
        return $id ? self::get($id) : null;
    }

    /** Envoie le lien du carnet à son adresse. */
    public static function sendLink(array $c, string $credential): bool
    {
        $link = base_url() . '/carnet/acces/' . $credential . '/';
        $html = '<p>' . e(t('Bonjour,')) . '</p>'
            . '<p>' . e(t('Voici le lien personnel de votre carnet du supporter Sochaux Rétro : il l’ouvre sur n’importe quel téléphone ou ordinateur. Gardez-le pour vous.')) . '</p>'
            . '<p style="margin:24px 0"><a href="' . e($link) . '" style="background:#F6C400;color:#0E1F4D;padding:12px 20px;text-decoration:none;font-weight:bold">' . e(t('Ouvrir mon carnet')) . '</a></p>'
            . '<p style="font-size:13px;color:#555">' . e(t('Vous n’avez rien demandé ? Ignorez ce message : sans ce lien, personne ne peut ouvrir le carnet.')) . '</p>';
        return Mailer::send($c['email'], t('Votre carnet du supporter Sochaux Rétro'), Mailer::layout(t('Votre carnet du supporter'), $html));
    }

    public static function confirm(string $id): void
    {
        JsonStore::update(self::file($id), function ($c) {
            if (is_array($c)) {
                $c['confirmed'] = true;
            }
            return $c;
        }, null);
    }

    /** Ajoute (true) ou retire (false) des matchs. @return list<int>|null matchs du carnet */
    public static function setMatches(string $id, array $mids, bool $add, ?array &$added = null): ?array
    {
        $M = Derived::part('matches');
        $added = [];
        $out = null;
        JsonStore::update(self::file($id), function ($c) use ($mids, $add, $M, &$added, &$out) {
            if (!is_array($c)) {
                return $c;
            }
            foreach ($mids as $mid) {
                $mid = (int) $mid;
                if ($add && isset($M[$mid]) && $M[$mid]['v'] && !isset($c['matches'][$mid]) && count($c['matches']) < self::MAX) {
                    $c['matches'][$mid] = date('Y-m-d');
                    $added[] = $mid;
                } elseif (!$add) {
                    unset($c['matches'][$mid]);
                }
            }
            $out = array_map('intval', array_keys($c['matches']));
            return $c;
        }, null);
        return $out;
    }

    /** Pseudo et page publique. @return string|null erreur */
    public static function setPublic(string $id, string $pseudo, bool $on): ?string
    {
        $pseudo = trim((string) preg_replace('/\s+/u', ' ', strip_tags($pseudo)));
        if ($on && (mb_strlen($pseudo) < 2 || mb_strlen($pseudo) > 30)) {
            return t('Choisissez un pseudo de 2 à 30 caractères.');
        }
        $slug = $on ? trim(substr(slugify($pseudo), 0, 30), '-') . '-' . substr($id, 0, 4) : '';
        JsonStore::update(self::file($id), function ($c) use ($pseudo, $on, $slug) {
            if (is_array($c)) {
                $c['pseudo'] = $pseudo;
                $c['public'] = $on;
                $c['slug'] = $slug;
            }
            return $c;
        }, null);
        JsonStore::update(self::indexFile(), function ($idx) use ($id, $slug) {
            $idx = is_array($idx) ? $idx : [];
            $idx['slugs'] = array_filter((array) ($idx['slugs'] ?? []), fn ($v) => $v !== $id);
            if ($slug !== '') {
                $idx['slugs'][$slug] = $id;
            }
            return $idx;
        }, []);
        return null;
    }

    public static function bySlug(string $slug): ?array
    {
        $id = (JsonStore::read(self::indexFile(), []) ?: [])['slugs'][$slug] ?? null;
        $c = $id ? self::get($id) : null;
        return $c && $c['public'] && $c['slug'] === $slug ? $c : null;
    }

    // ------------------------------------------------------------------ anniversaires

    /**
     * Anniversaires des matchs vus : e-mail (case cochée) et/ou notification sur les appareils
     * choisis. $email : null = inchangé ; $subId : abonnement aux notifications à ajouter ;
     * $pushOff : retire toutes les notifications.
     */
    public static function setReminders(string $id, ?bool $email, ?string $subId = null, bool $pushOff = false): ?array
    {
        $out = null;
        JsonStore::update(self::file($id), function ($c) use ($email, $subId, $pushOff, &$out) {
            if (!is_array($c)) {
                return $c;
            }
            if ($email !== null) {
                $c['remind_email'] = $email;
            }
            $push = $pushOff ? [] : (array) ($c['remind_push'] ?? []);
            if ($subId !== null && !in_array($subId, $push, true)) {
                $push = array_slice(array_merge($push, [$subId]), -5);
            }
            $c['remind_push'] = array_values($push);
            $out = $c;
            return $c;
        }, null);
        return $out;
    }

    /** Signature du lien « ne plus recevoir » de l'e-mail d'anniversaire. */
    public static function stopSig(string $id): string
    {
        return substr(hash_hmac('sha256', $id, site_key('carnet-arret')), 0, 24);
    }

    /**
     * Le match à fêter aujourd'hui pour ces matchs : même jour et même mois, une année passée.
     * Le plus ancien d'abord (« il y a 40 ans ») ; @return array{m:array,years:int,others:int}|null
     */
    public static function anniversaryOf(array $mids, string $today): ?array
    {
        $M = Derived::part('matches');
        $md = substr($today, 5, 5);
        $year = (int) substr($today, 0, 4);
        $hits = [];
        foreach ($mids as $mid) {
            $x = $M[(int) $mid] ?? null;
            if ($x && $x['v'] && substr((string) $x['date'], 5, 5) === $md && (int) substr((string) $x['date'], 0, 4) < $year) {
                $hits[] = $x;
            }
        }
        if (!$hits) {
            return null;
        }
        usort($hits, fn ($a, $b) => strcmp((string) $a['date'], (string) $b['date']));
        return ['m' => $hits[0], 'years' => $year - (int) substr((string) $hits[0]['date'], 0, 4), 'others' => count($hits) - 1];
    }

    /** Texte du rappel dans la langue courante. @return array{title:string,body:string,url:string} */
    public static function anniversaryMessage(array $a): array
    {
        $m = $a['m'];
        $title = $a['years'] > 1 ? t('Il y a {n} ans jour pour jour, vous étiez au stade', ['n' => $a['years']]) : t('Il y a un an jour pour jour, vous étiez au stade');
        $body = $m['home'] . ' ' . ($m['sh'] ? $m['us'] . '–' . $m['them'] : $m['them'] . '–' . $m['us']) . ' ' . $m['away'] . ' · ' . trim($m['label'] . ' ' . $m['round']);
        if ($a['others'] > 0) {
            $body .= ' · ' . t($a['others'] > 1 ? 'et {n} autres matchs de votre carnet ce jour-là' : 'et un autre match de votre carnet ce jour-là', ['n' => $a['others']]);
        }
        return ['title' => $title, 'body' => $body, 'url' => url((string) $m['path'])];
    }

    /**
     * Tâche planifiée, une fois par jour à partir de 9 h : pour chaque carnet qui le demande, le
     * match du jour (anniversaire) par e-mail et/ou notification. Un seul rappel par carnet et par
     * jour (noté avant l'envoi : jamais deux fois). @return array{emails:int,push:int}
     */
    public static function anniversaries(?string $today = null, ?int $hour = null): array
    {
        $today ??= date('Y-m-d');
        $hour ??= (int) date('G');
        $done = ['emails' => 0, 'push' => 0];
        if ($hour < 9) {
            return $done;
        }
        $byMatch = [];
        foreach (glob(self::$dir . '/*.json') ?: [] as $f) {
            $id = basename($f, '.json');
            if (!preg_match('/^[a-f0-9]{16}$/', $id) || !($c = self::get($id))) {
                continue;
            }
            $email = !empty($c['remind_email']) && !empty($c['confirmed']);
            $push = (array) ($c['remind_push'] ?? []);
            if ((!$email && !$push) || ($c['reminded'] ?? '') === $today) {
                continue;
            }
            $a = self::anniversaryOf(array_keys($c['matches']), $today);
            if (!$a) {
                continue;
            }
            JsonStore::update(self::file($id), function ($x) use ($today) {
                if (is_array($x)) {
                    $x['reminded'] = $today;
                }
                return $x;
            }, null);
            $prev = I18n::lang();
            I18n::set($c['lang'] ?? 'fr');
            try {
                if ($email) {
                    $msg = self::anniversaryMessage($a);
                    $stop = base_url() . '/carnet/rappels/arret/' . $id . '/' . self::stopSig($id) . '/';
                    $html = '<p style="font-size:20px"><b>' . e($msg['title']) . '</b></p><p style="font-size:18px">' . e($msg['body']) . '</p>'
                        . '<p style="margin:24px 0"><a href="' . e(base_url() . $msg['url']) . '" style="background:#F6C400;color:#0E1F4D;padding:12px 20px;text-decoration:none;font-weight:bold">' . e(t('Revivre le match')) . '</a></p>'
                        . '<p><a href="' . e(base_url() . url('/carnet/')) . '">' . e(t('Mon carnet du supporter')) . '</a></p>'
                        . '<p style="font-size:13px;color:#555"><a href="' . e($stop) . '" style="color:#555">' . e(t('Ne plus recevoir ces rappels')) . '</a></p>';
                    if (Mailer::send($c['email'], $msg['title'], Mailer::layout($msg['title'], $html))) {
                        $done['emails']++;
                    }
                }
            } finally {
                I18n::set($prev);
            }
            if ($push) {
                $byMatch[$a['m']['id']]['a'] = $a;
                $byMatch[$a['m']['id']]['ids'] = array_merge($byMatch[$a['m']['id']]['ids'] ?? [], $push);
            }
        }
        // Notifications : un envoi par match fêté, vers les seuls appareils concernés.
        if ($byMatch && Notifications::enabled()) {
            foreach ($byMatch as $mid => $g) {
                $msg = [];
                $prev = I18n::lang();
                foreach (['fr', 'en'] as $l) {
                    I18n::set($l);
                    $msg[$l] = self::anniversaryMessage($g['a']);
                }
                I18n::set($prev);
                $ids = array_values(array_unique($g['ids']));
                $r = Notifications::enqueue('carnet:' . $mid . ':' . $today . ':' . substr(md5(implode(',', $ids)), 0, 8), 'carnet', $msg, ['only' => $ids, 'hidden' => true, 'ttl' => 12 * 3600]);
                $done['push'] += $r['ok'] ? (int) $r['n'] : 0;
            }
        }
        return $done;
    }

    /** Supprime le carnet et tout ce qui le désigne (RGPD). */
    public static function delete(string $id): void
    {
        @unlink(self::file($id));
        @unlink(self::file($id) . '.lock');
        JsonStore::update(self::indexFile(), function ($idx) use ($id) {
            $idx = is_array($idx) ? $idx : [];
            foreach (['emails', 'slugs'] as $k) {
                $idx[$k] = array_filter((array) ($idx[$k] ?? []), fn ($v) => $v !== $id);
            }
            return $idx;
        }, []);
    }

    /** Ménage : carnets jamais ouverts par le lien de l'e-mail et vides après 90 jours. @return int supprimés */
    public static function purge(): int
    {
        $n = 0;
        foreach (glob(self::$dir . '/*.json') ?: [] as $f) {
            $id = basename($f, '.json');
            if (!preg_match('/^[a-f0-9]{16}$/', $id) || !($c = self::get($id))) {
                continue;
            }
            if (!$c['confirmed'] && !$c['matches'] && strtotime((string) $c['created']) < time() - 90 * 86400) {
                self::delete($id);
                $n++;
            }
        }
        return $n;
    }

    /** Chiffres pour le back-office : carnets, matchs cochés, matchs les plus vécus. */
    public static function overview(): array
    {
        $count = 0;
        $public = 0;
        $ticks = 0;
        $by = [];
        foreach (glob(self::$dir . '/*.json') ?: [] as $f) {
            $c = json_decode((string) file_get_contents($f), true);
            if (!is_array($c) || !isset($c['matches'])) {
                continue;
            }
            $count++;
            $public += $c['public'] ? 1 : 0;
            $ticks += count($c['matches']);
            foreach (array_keys($c['matches']) as $mid) {
                $by[$mid] = ($by[$mid] ?? 0) + 1;
            }
        }
        arsort($by);
        $M = Derived::part('matches');
        $top = [];
        foreach (array_slice($by, 0, 15, true) as $mid => $n) {
            if (isset($M[$mid])) {
                $top[] = ['m' => $M[$mid], 'n' => $n];
            }
        }
        return ['count' => $count, 'public' => $public, 'ticks' => $ticks, 'top' => $top];
    }

    // ------------------------------------------------------------------ bilan

    /**
     * Bilan d'une liste de matchs : totaux, records, adversaires, buteurs et joueurs vus,
     * « porte-bonheur », décennies, badges, liste chronologique.
     */
    public static function stats(array $mids): array
    {
        $M = Derived::part('matches');
        $scorers = Derived::part('scorers');
        $list = [];
        foreach ($mids as $mid) {
            $x = $M[(int) $mid] ?? null;
            if ($x && $x['v']) {
                $list[] = $x;
            }
        }
        usort($list, fn ($a, $b) => strcmp((string) $a['date'], (string) $b['date']));
        $s = ['n' => count($list), 'v' => 0, 'nul' => 0, 'd' => 0, 'gf' => 0, 'ga' => 0, 'home' => 0, 'away' => 0, 'first' => $list[0] ?? null, 'last' => $list ? $list[count($list) - 1] : null,
            'best' => null, 'opps' => [], 'decades' => [], 'comps' => [], 'scorers' => [], 'players' => [], 'seasons' => [], 'list' => array_reverse($list), 'luck' => null, 'badges' => []];
        $people = [];
        foreach ($list as $x) {
            match ($x['result']) {
                'V' => $s['v']++,
                'D' => $s['d']++,
                default => $s['nul']++,
            };
            $s['gf'] += (int) $x['us'];
            $s['ga'] += (int) $x['them'];
            $x['sh'] ? $s['home']++ : $s['away']++;
            $diff = (int) $x['us'] - (int) $x['them'];
            if ($x['result'] === 'V' && (!$s['best'] || $diff > $s['best']['_d'] || ($diff === $s['best']['_d'] && $x['us'] > $s['best']['us']))) {
                $s['best'] = $x + ['_d' => $diff];
            }
            $opp = (string) $x['opp'];
            if ($opp !== '') {
                $s['opps'][$opp] = ($s['opps'][$opp] ?? 0) + 1;
            }
            $s['decades'][(int) $x['decade']] = ($s['decades'][(int) $x['decade']] ?? 0) + 1;
            $s['comps'][(string) $x['comp']] = ($s['comps'][(string) $x['comp']] ?? 0) + 1;
            $s['seasons'][(string) $x['season']] = true;
            foreach ((array) ($scorers[$x['id']] ?? []) as $g) {
                $name = (string) ($g[1] ?? '');
                if ($name !== '') {
                    $s['scorers'][$name] = ($s['scorers'][$name] ?? 0) + max(1, count((array) ($g[2] ?? [])));
                }
            }
            foreach (Derived::lineupLinks((int) $x['id']) as $a) {
                if (($a[6] ?? 'player') !== 'player') {
                    continue; // entraîneurs
                }
                $people[(int) $a[0]] = ($people[(int) $a[0]] ?? 0) + 1;
            }
        }
        arsort($s['opps']);
        arsort($s['scorers']);
        ksort($s['decades']);
        arsort($people);
        foreach (array_slice($people, 0, 8, true) as $pid => $n) {
            $p = Index::get($pid);
            if ($p && Index::visible($p)) {
                $s['players'][] = ['name' => (string) $p['title'], 'path' => (string) ($p['path'] ?? ''), 'n' => $n];
            }
        }
        $s['scorers'] = array_slice($s['scorers'], 0, 8, true);
        $s['luck'] = self::luck($list, $M);
        $s['badges'] = self::badges($s, $list);
        return $s;
    }

    /**
     * « Porte-bonheur » : taux de victoires des matchs vus comparé à celui du club sur les mêmes
     * saisons (tous les matchs fichés). Seulement à partir de 5 matchs.
     */
    private static function luck(array $list, array $M): ?array
    {
        if (count($list) < 5) {
            return null;
        }
        $seasons = array_flip(array_column($list, 'season'));
        $all = 0;
        $wins = 0;
        foreach ($M as $x) {
            if ($x['v'] && isset($seasons[$x['season']])) {
                $all++;
                $wins += $x['result'] === 'V' ? 1 : 0;
            }
        }
        $mine = count(array_filter($list, fn ($x) => $x['result'] === 'V')) / count($list);
        $club = $all ? $wins / $all : 0;
        return ['mine' => (int) round($mine * 100), 'club' => (int) round($club * 100), 'diff' => (int) round(($mine - $club) * 100)];
    }

    /** Badges obtenus (et ceux qui restent à décrocher). @return list<array{key,icon,label,d,on}> */
    private static function badges(array $s, array $list): array
    {
        $has = fn (callable $f) => (bool) array_filter($list, $f);
        $defs = [
            ['premier', '★', t('Premier match'), t('Votre premier match dans le carnet.'), $s['n'] >= 1],
            ['m10', '10', t('10 matchs'), t('Dix matchs vus au stade.'), $s['n'] >= 10],
            ['m50', '50', t('50 matchs'), t('Un habitué des tribunes.'), $s['n'] >= 50],
            ['m100', '100', t('Centurion'), t('Cent matchs vus au stade.'), $s['n'] >= 100],
            ['bonal25', 'B', t('Pilier de Bonal'), t('25 matchs à domicile.'), $s['home'] >= 25],
            ['deplacement', '→', t('Supporter en déplacement'), t('Un match à l’extérieur.'), $s['away'] >= 1],
            ['globe', '✈', t('Globe-trotteur'), t('Dix matchs à l’extérieur.'), $s['away'] >= 10],
            ['coupe', '🏆', t('Soirée de coupe'), t('Un match de coupe.'), $has(fn ($x) => stripos((string) $x['comp'], 'coupe') !== false)],
            ['finale', 'F', t('Jour de finale'), t('Une finale vécue au stade.'), $has(fn ($x) => stripos((string) $x['round'], 'finale') === 0)],
            ['europe', '★★', t('Nuit européenne'), t('Un match de coupe d’Europe.'), $has(fn ($x) => preg_match('/europe|uefa|intertoto|champions|C[123]\b/i', (string) $x['comp'] . ' ' . (string) $x['label']))],
            ['derby', '⚔', t('Derby de l’Est'), t('Un derby contre un voisin de l’Est.'), $has(fn ($x) => in_array((string) $x['opp'], self::DERBYS, true))],
            ['festival', '5', t('Festival'), t('Une victoire par quatre buts d’écart ou plus.'), $has(fn ($x) => (int) $x['us'] - (int) $x['them'] >= 4)],
            ['decennies', '⌛', t('Trois décennies'), t('Des matchs vus sur trois décennies.'), count($s['decades']) >= 3],
            ['porte', '☘', t('Porte-bonheur'), t('Au moins 10 points de victoires de plus que le club.'), $s['luck'] && $s['n'] >= 10 && $s['luck']['diff'] >= 10],
        ];
        return array_map(fn ($d) => ['key' => $d[0], 'icon' => $d[1], 'label' => $d[2], 'd' => $d[3], 'on' => $d[4]], $defs);
    }

    /** Saisons proposées dans la saisie rapide, de la plus récente à la plus ancienne. */
    public static function seasons(): array
    {
        $out = [];
        foreach (Derived::part('matches') as $x) {
            if ($x['v']) {
                $out[(string) $x['season']] = ($out[(string) $x['season']] ?? 0) + 1;
            }
        }
        krsort($out);
        return $out;
    }

    /** Matchs d'une saison, dans l'ordre des dates. */
    public static function seasonMatches(string $season): array
    {
        $out = array_values(array_filter(Derived::part('matches'), fn ($x) => $x['v'] && $x['season'] === $season));
        usort($out, fn ($a, $b) => strcmp((string) $a['date'], (string) $b['date']));
        return $out;
    }
}
