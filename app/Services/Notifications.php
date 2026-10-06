<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\JsonStore;
use App\Core\Settings;
use App\Data\Derived;
use App\Data\Fiches;
use App\Front\Site;

/**
 * Notifications de l'appli du musée (Web Push).
 *
 * Abonnés (storage/push/abonnes.json) : l'adresse d'abonnement que donne le navigateur et ses
 * deux clés, les sujets choisis, la langue, les dates. Ni nom, ni e-mail, ni adresse IP.
 *
 * Envois : chaque notification devient une tâche de la file (storage/push/file.json), envoyée par
 * lots à ses abonnés dans leur langue (tâche planifiée « notifications » toutes les 5 minutes, ou
 * aussitôt pour un envoi de l'équipe). Un abonnement que le service de notifications déclare
 * disparu (404, 410) est effacé. Historique : storage/push/envois.json (ouvertures comprises).
 *
 * Envois automatiques (une fois chacun, clés dans storage/push/faits.json) : coup d'envoi des
 * Rétro-Direct, parution des 100 moments, kit souvenirs du mois, « Ce jour-là » du matin. Rien de
 * ce qui existait avant la mise en route n'est envoyé ; rien la nuit (sauf un Rétro-Direct programmé).
 */
final class Notifications
{
    /** Sujet => [libellé, description, coché par défaut]. */
    public const TOPICS = [
        'retro' => ['Rétro-Direct', 'Le coup d’envoi des grands matchs rejoués en direct.', true],
        'moments' => ['Les 100 moments', 'Chaque moment du centenaire, à sa parution.', true],
        'nouvelles' => ['Les nouvelles du musée', 'Les annonces de l’équipe : nouveautés, événements, appels.', true],
        'kit' => ['Le kit souvenirs', 'Le kit du mois, à imprimer pour les anciens.', true],
        'jour' => ['Ce jour-là', 'Chaque matin, un match de l’histoire du club à cette date.', false],
    ];
    /** Abonnés au plus (protection du stockage). */
    public const MAX_SUBS = 100000;
    /** Abonnés par lot d'envoi. */
    private const BATCH = 400;

    public static ?int $now = null;

    private static function now(): int
    {
        return self::$now ?? time();
    }

    private static function f(string $name): string
    {
        return WebPush::$dir . '/' . $name . '.json';
    }

    public static function enabled(): bool
    {
        return (bool) Settings::get('app.enabled', true) && (bool) Settings::get('app.push', true) && WebPush::available();
    }

    /** Sujets actifs (un sujet automatique éteint dans les réglages n'est plus proposé). */
    public static function topics(): array
    {
        $out = [];
        foreach (self::TOPICS as $k => $t) {
            if ($k === 'nouvelles' || Settings::get('app.push_' . $k, true)) {
                $out[$k] = $t;
            }
        }
        return $out;
    }

    private static function subject(): string
    {
        foreach (['app.push_contact', 'legal.email'] as $k) {
            $e = trim((string) Settings::get($k, ''));
            if (filter_var($e, FILTER_VALIDATE_EMAIL)) {
                return 'mailto:' . $e;
            }
        }
        $b = rtrim((string) Settings::get('general.base_url', ''), '/');
        return preg_match('#^https://#', $b) ? $b : 'https://musee.fcsochauxretro.com';
    }

    // ------------------------------------------------------------------ abonnés

    public static function idOf(string $endpoint): string
    {
        return substr(hash('sha256', $endpoint), 0, 24);
    }

    /** Sujets valables, dans l'ordre. */
    private static function cleanTopics(array $topics): array
    {
        return array_values(array_intersect(array_keys(self::TOPICS), array_map('strval', $topics)));
    }

    /**
     * Abonnement (ou mise à jour) d'un navigateur. $sub : {endpoint, keys:{p256dh, auth}}.
     * $waiting : abonné depuis la page d'attente (« Prévenez-moi de l'ouverture »), gardé pour
     * l'écran Communauté › Notifications (annonce de l'ouverture).
     * @return array{ok:bool,id?:string,error?:string}
     */
    public static function subscribe(array $sub, array $topics, string $lang, bool $waiting = false): array
    {
        $endpoint = trim((string) ($sub['endpoint'] ?? ''));
        $p256dh = WebPush::unb64((string) ($sub['keys']['p256dh'] ?? ''));
        $auth = WebPush::unb64((string) ($sub['keys']['auth'] ?? ''));
        if (!WebPush::validEndpoint($endpoint)) {
            return ['ok' => false, 'error' => 'adresse'];
        }
        if (!WebPush::validPublic($p256dh) || strlen($auth) !== 16) {
            return ['ok' => false, 'error' => 'cles'];
        }
        $id = self::idOf($endpoint);
        $topics = self::cleanTopics($topics);
        $full = false;
        JsonStore::update(self::f('abonnes'), function ($all) use ($id, $endpoint, $p256dh, $auth, $topics, $lang, $waiting, &$full) {
            $all = is_array($all) ? $all : [];
            if (!isset($all[$id]) && count($all) >= self::MAX_SUBS) {
                $full = true;
                return $all;
            }
            $cur = $all[$id] ?? ['at' => date('c', self::now())];
            $all[$id] = ['e' => $endpoint, 'k' => WebPush::b64($p256dh), 'a' => WebPush::b64($auth), 't' => $topics, 'l' => $lang === 'en' ? 'en' : 'fr',
                'at' => $cur['at'], 'up' => date('c', self::now()), 'ok' => $cur['ok'] ?? null, 'f' => 0];
            if ($waiting || !empty($cur['w'])) {
                $all[$id]['w'] = 1;
            }
            return $all;
        }, []);
        return $full ? ['ok' => false, 'error' => 'complet'] : ['ok' => true, 'id' => $id];
    }

    /** Sujets d'un abonné existant. */
    public static function setTopics(string $endpoint, array $topics): bool
    {
        $id = self::idOf($endpoint);
        $found = false;
        JsonStore::update(self::f('abonnes'), function ($all) use ($id, $topics, &$found) {
            $all = is_array($all) ? $all : [];
            if (isset($all[$id])) {
                $all[$id]['t'] = self::cleanTopics($topics);
                $all[$id]['up'] = date('c', self::now());
                $found = true;
            }
            return $all;
        }, []);
        return $found;
    }

    public static function unsubscribe(string $endpoint): bool
    {
        $id = self::idOf($endpoint);
        $found = false;
        JsonStore::update(self::f('abonnes'), function ($all) use ($id, &$found) {
            $all = is_array($all) ? $all : [];
            $found = isset($all[$id]);
            unset($all[$id]);
            return $all;
        }, []);
        return $found;
    }

    /** Abonnement renouvelé par le navigateur : les sujets passent à la nouvelle adresse. */
    public static function renew(string $old, array $sub, string $lang): array
    {
        $prev = self::find($old);
        $r = self::subscribe($sub, $prev['t'] ?? self::defaults(), $prev['l'] ?? $lang);
        if ($r['ok'] && $old !== '' && $old !== (string) ($sub['endpoint'] ?? '')) {
            self::unsubscribe($old);
        }
        return $r;
    }

    public static function find(string $endpoint): ?array
    {
        if ($endpoint === '') {
            return null;
        }
        JsonStore::forget(self::f('abonnes'));
        return (JsonStore::read(self::f('abonnes'), []) ?: [])[self::idOf($endpoint)] ?? null;
    }

    /** @return list<string> sujets cochés d'office */
    public static function defaults(): array
    {
        return array_keys(array_filter(self::topics(), fn ($t) => $t[2]));
    }

    /** Chiffres pour le back-office : total, par sujet, par langue, nouveaux sur 30 jours. */
    public static function stats(): array
    {
        JsonStore::forget(self::f('abonnes'));
        $all = JsonStore::read(self::f('abonnes'), []) ?: [];
        $out = ['total' => count($all), 'topics' => array_fill_keys(array_keys(self::TOPICS), 0), 'langs' => ['fr' => 0, 'en' => 0], 'new30' => 0, 'waiting' => 0];
        $since = self::now() - 30 * 86400;
        foreach ($all as $s) {
            foreach ((array) ($s['t'] ?? []) as $t) {
                if (isset($out['topics'][$t])) {
                    $out['topics'][$t]++;
                }
            }
            $out['langs'][$s['l'] ?? 'fr'] = ($out['langs'][$s['l'] ?? 'fr'] ?? 0) + 1;
            $out['new30'] += strtotime((string) ($s['at'] ?? '')) >= $since ? 1 : 0;
            $out['waiting'] += empty($s['w']) ? 0 : 1;
        }
        return $out;
    }

    /** Vrai si la notification de cette clé est déjà partie (ex. « ouverture » : annonce de l'ouverture). */
    public static function sent(string $key): bool
    {
        JsonStore::forget(self::f('faits'));
        $f = JsonStore::read(self::f('faits'), []) ?: [];
        return isset($f['done'][$key]);
    }

    // ------------------------------------------------------------------ file d'envoi

    /**
     * Met une notification dans la file. $msg : [langue => [title, body, url, image?]] (« fr »
     * obligatoire, « en » facultatif). $o : ttl, urgency, only (identifiants d'abonnés : essai),
     * hidden (essai : absent de l'historique), by (auteur). Une même clé ne part jamais deux fois.
     * @return array{ok:bool,id?:string,n?:int,error?:string}
     */
    public static function enqueue(string $key, string $topic, array $msg, array $o = []): array
    {
        if (empty($msg['fr']['title'])) {
            return ['ok' => false, 'error' => 'Titre manquant.'];
        }
        $now = self::now();
        $dup = false;
        JsonStore::update(self::f('faits'), function ($d) use ($key, $now, &$dup) {
            $d = is_array($d) ? $d : [];
            $d['since'] ??= $now;
            if (isset($d['done'][$key])) {
                $dup = true;
                return $d;
            }
            $d['done'][$key] = $now;
            // Les clés de plus de 400 jours sont oubliées.
            $d['done'] = array_filter($d['done'], fn ($t) => $t > $now - 400 * 86400);
            return $d;
        }, []);
        if ($dup) {
            return ['ok' => false, 'error' => 'Déjà envoyée.'];
        }
        JsonStore::forget(self::f('abonnes'));
        $subs = JsonStore::read(self::f('abonnes'), []) ?: [];
        $ids = [];
        foreach ($subs as $id => $s) {
            if (isset($o['only']) ? in_array($id, (array) $o['only'], true) : in_array($topic, (array) ($s['t'] ?? []), true)) {
                $ids[] = $id;
            }
        }
        $jobId = date('ymdHis', $now) . bin2hex(random_bytes(3));
        $payloads = [];
        foreach (['fr', 'en'] as $l) {
            $m = $msg[$l] ?? $msg['fr'];
            $payloads[$l] = json_encode(array_filter([
                'title' => mb_substr(trim((string) $m['title']), 0, 80),
                'body' => mb_substr(trim((string) ($m['body'] ?? '')), 0, 240),
                'url' => self::localUrl((string) ($m['url'] ?? '/')),
                'image' => !empty($m['image']) && str_starts_with((string) $m['image'], '/') ? (string) $m['image'] : null,
                'tag' => $topic . '-' . substr(md5($key), 0, 8),
                'id' => $jobId,
                'lang' => $l,
            ], fn ($v) => $v !== null && $v !== ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $job = ['id' => $jobId, 'key' => $key, 'topic' => $topic, 'at' => date('c', $now), 'by' => (string) ($o['by'] ?? ''), 'hidden' => !empty($o['hidden']),
            'title' => (string) $msg['fr']['title'], 'body' => (string) ($msg['fr']['body'] ?? ''), 'url' => self::localUrl((string) ($msg['fr']['url'] ?? '/')),
            'p' => $payloads, 'ttl' => (int) ($o['ttl'] ?? 86400), 'urgency' => (string) ($o['urgency'] ?? 'normal'),
            'ids' => $ids, 'n' => count($ids), 'sent' => 0, 'ok' => 0, 'gone' => 0, 'failed' => 0, 'opened' => 0];
        JsonStore::update(self::f('file'), function ($q) use ($job) {
            $q = is_array($q) ? $q : [];
            $q[] = $job;
            return $q;
        }, []);
        return ['ok' => true, 'id' => $jobId, 'n' => count($ids)];
    }

    /** Adresse d'une page du musée (chemin) ; toute autre adresse devient l'accueil. */
    private static function localUrl(string $u): string
    {
        return preg_match('#^/(?!/)[^\s]*$#', $u) ? $u : '/';
    }

    /**
     * Envoie ce qui attend dans la file, dans la limite de temps. Une seule exécution à la fois.
     * @return array{sent:int,ok:int,gone:int,failed:int,jobs:int,left:int}|null null : rien à faire ou déjà en cours
     */
    public static function process(float $budget = 120): ?array
    {
        $dir = WebPush::$dir;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $fp = fopen($dir . '/envoi.lock', 'c');
        if (!$fp || !flock($fp, LOCK_EX | LOCK_NB)) {
            return null;
        }
        try {
            JsonStore::forget(self::f('file'));
            if (!(JsonStore::read(self::f('file'), []) ?: [])) {
                return null;
            }
            $t0 = microtime(true);
            $tot = ['sent' => 0, 'ok' => 0, 'gone' => 0, 'failed' => 0, 'jobs' => 0, 'left' => 0];
            $subject = self::subject();
            while (microtime(true) - $t0 < $budget) {
                JsonStore::forget(self::f('file'));
                $queue = JsonStore::read(self::f('file'), []) ?: [];
                if (!$queue) {
                    break;
                }
                $job = $queue[0];
                $batch = array_slice($job['ids'], 0, self::BATCH);
                JsonStore::forget(self::f('abonnes'));
                $subs = JsonStore::read(self::f('abonnes'), []) ?: [];
                $jobs = [];
                foreach ($batch as $id) {
                    if (isset($subs[$id])) {
                        $s = $subs[$id];
                        $jobs[$id] = [$s['e'], WebPush::unb64($s['k']), WebPush::unb64($s['a']), $job['p'][$s['l'] ?? 'fr'] ?? $job['p']['fr']];
                    }
                }
                $res = $jobs ? WebPush::send($jobs, $subject, ['ttl' => $job['ttl'], 'urgency' => $job['urgency'], 'topic' => $job['topic']]) : [];
                $c = ['sent' => count($res), 'ok' => 0, 'gone' => 0, 'failed' => 0];
                $now = self::now();
                // Abonnés : succès notés, disparus effacés, échecs comptés (effacés après 10 échecs sans succès depuis 60 jours).
                JsonStore::update(self::f('abonnes'), function ($all) use ($res, $now, &$c) {
                    $all = is_array($all) ? $all : [];
                    foreach ($res as $id => $r) {
                        if (!isset($all[$id])) {
                            continue;
                        }
                        if ($r['status'] >= 200 && $r['status'] < 300) {
                            $c['ok']++;
                            $all[$id]['ok'] = date('c', $now);
                            $all[$id]['f'] = 0;
                        } elseif (in_array($r['status'], [404, 410], true)) {
                            $c['gone']++;
                            unset($all[$id]);
                        } else {
                            $c['failed']++;
                            $all[$id]['f'] = (int) ($all[$id]['f'] ?? 0) + 1;
                            $last = strtotime((string) ($all[$id]['ok'] ?? $all[$id]['at'] ?? '')) ?: 0;
                            if ($all[$id]['f'] >= 10 && $last < $now - 60 * 86400) {
                                unset($all[$id]);
                            }
                        }
                    }
                    return $all;
                }, []);
                $errors = array_values(array_unique(array_filter(array_map(fn ($r) => $r['status'] >= 200 && $r['status'] < 300 || in_array($r['status'], [404, 410], true) ? '' : trim($r['status'] . ' ' . $r['error']), $res))));
                // La file : lot retiré ; tâche terminée → historique.
                $done = null;
                JsonStore::update(self::f('file'), function ($q) use ($job, $batch, $c, $errors, &$done) {
                    $q = is_array($q) ? $q : [];
                    foreach ($q as $i => $j) {
                        if ($j['id'] !== $job['id']) {
                            continue;
                        }
                        $j['ids'] = array_values(array_diff($j['ids'], $batch));
                        foreach (['sent', 'ok', 'gone', 'failed'] as $k) {
                            $j[$k] += $c[$k];
                        }
                        $j['errors'] = array_slice(array_values(array_unique(array_merge($j['errors'] ?? [], $errors))), 0, 5);
                        if (!$j['ids']) {
                            $done = $j;
                            unset($q[$i]);
                        } else {
                            $q[$i] = $j;
                        }
                    }
                    return array_values($q);
                }, []);
                foreach (['sent', 'ok', 'gone', 'failed'] as $k) {
                    $tot[$k] += $c[$k];
                }
                if ($done) {
                    $tot['jobs']++;
                    if (empty($done['hidden'])) {
                        self::log($done);
                    }
                }
            }
            JsonStore::forget(self::f('file'));
            foreach (JsonStore::read(self::f('file'), []) ?: [] as $j) {
                $tot['left'] += count($j['ids']);
            }
            return $tot;
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    private static function log(array $job): void
    {
        unset($job['p'], $job['ids']);
        $job['done'] = date('c', self::now());
        JsonStore::update(self::f('envois'), function ($h) use ($job) {
            $h = is_array($h) ? $h : [];
            array_unshift($h, $job);
            return array_slice($h, 0, 150);
        }, []);
    }

    /** Historique des envois (les plus récents d'abord), puis les envois en cours. */
    public static function history(): array
    {
        JsonStore::forget(self::f('envois'));
        $h = JsonStore::read(self::f('envois'), []) ?: [];
        foreach ($h as $i => $j) {
            // Ouvertures notées à part (un octet par clic) : ajoutées ici.
            $h[$i]['opened'] = (int) ($j['opened'] ?? 0) + (int) @filesize(self::opensFile((string) ($j['id'] ?? '')));
        }
        return $h;
    }

    private static function opensFile(string $jobId): string
    {
        return WebPush::$dir . '/ouvertures/' . $jobId . '.log';
    }

    /**
     * Notification d'essai (page L'appli) : chiffrée et envoyée à ce seul abonné, tout de suite,
     * sans passer par la file (qui peut contenir un envoi à des milliers d'abonnés).
     */
    public static function sendTest(string $endpoint, array $msg): bool
    {
        $s = self::find($endpoint);
        if (!$s) {
            return false;
        }
        $m = $msg[$s['l'] ?? 'fr'] ?? $msg['fr'];
        $payload = json_encode(array_filter(['title' => (string) $m['title'], 'body' => (string) ($m['body'] ?? ''), 'url' => self::localUrl((string) ($m['url'] ?? '/')),
            'tag' => 'essai', 'lang' => $s['l'] ?? 'fr']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $res = WebPush::send(['t' => [$s['e'], WebPush::unb64($s['k']), WebPush::unb64($s['a']), $payload]], self::subject(), ['ttl' => 600, 'timeout' => 10]);
        $st = (int) ($res['t']['status'] ?? 0);
        return $st >= 200 && $st < 300;
    }

    public static function queue(): array
    {
        JsonStore::forget(self::f('file'));
        return array_values(array_filter(JsonStore::read(self::f('file'), []) ?: [], fn ($j) => empty($j['hidden'])));
    }

    /**
     * Une notification ouverte (clic) : comptée sur son envoi, sans savoir par qui. Un octet ajouté
     * à un petit fichier par envoi : le jour de l'ouverture, des milliers de clics dans la même
     * minute ne réécrivent plus l'historique sous verrou.
     */
    public static function opened(string $jobId): void
    {
        if (!preg_match('/^\d{12}[a-f0-9]{6}$/', $jobId)) {
            return;
        }
        $file = self::opensFile($jobId);
        $size = @filesize($file);
        if ($size === false) {
            // Seulement pour un envoi qui existe (pas un fichier par identifiant inventé).
            $known = false;
            foreach (JsonStore::read(self::f('envois'), []) ?: [] as $j) {
                $known = $known || ($j['id'] ?? '') === $jobId;
            }
            if (!$known) {
                return;
            }
            @mkdir(dirname($file), 0775, true);
        } elseif ($size >= 10000000) {
            return;
        }
        @file_put_contents($file, '.', FILE_APPEND | LOCK_EX);
    }

    // ------------------------------------------------------------------ envois automatiques

    /**
     * Tâche planifiée : repère les notifications automatiques à envoyer, puis vide la file.
     * @return string|null compte rendu (null : rien fait)
     */
    public static function tick(): ?string
    {
        if (!self::enabled()) {
            return null;
        }
        $made = [];
        // Mise en route : la date de départ ; rien d'antérieur ne sera envoyé.
        JsonStore::update(self::f('faits'), function ($d) {
            $d = is_array($d) ? $d : [];
            $d['since'] ??= self::now();
            return $d;
        }, []);
        try {
            foreach (self::due() as [$key, $topic, $msg, $o]) {
                $r = self::enqueue($key, $topic, $msg, $o + ['by' => 'Envoi automatique']);
                if ($r['ok']) {
                    $made[] = $key . ' (' . $r['n'] . ')';
                }
            }
        } catch (\Throwable $e) {
            error_log('[notifications] ' . $e);
        }
        $p = self::process(150);
        if (!$made && !$p) {
            return null;
        }
        return trim(($made ? 'prévu : ' . implode(', ', $made) . ' ; ' : '') . ($p ? sprintf('%d envoyée(s), %d reçue(s) par le service, %d abonnement(s) disparu(s), %d échec(s)%s', $p['sent'], $p['ok'], $p['gone'], $p['failed'], $p['left'] ? ', ' . $p['left'] . ' en attente' : '') : ''), ' ;');
    }

    /** Messages dans les deux langues, construits par $fn (appelée en français puis en anglais). */
    private static function bilingual(callable $fn): array
    {
        $prev = I18n::lang();
        try {
            I18n::set('fr');
            $fr = $fn('fr');
            I18n::set('en');
            $en = $fn('en');
        } finally {
            I18n::set($prev);
        }
        return ['fr' => $fr, 'en' => $en];
    }

    /** Heures où l'on peut déranger : 8 h – 21 h 30. */
    private static function daytime(int $now): bool
    {
        $hm = (int) date('Hi', $now);
        return $hm >= 800 && $hm <= 2130;
    }

    /**
     * Notifications automatiques dues maintenant. @return list<array{0:string,1:string,2:array,3:array}>
     * [clé, sujet, messages, options]
     */
    public static function due(): array
    {
        $now = self::now();
        JsonStore::forget(self::f('faits'));
        $f = JsonStore::read(self::f('faits'), []) ?: [];
        $since = (int) ($f['since'] ?? $now);
        $done = $f['done'] ?? [];
        $out = [];

        // Rétro-Direct : 30 minutes avant le coup d'envoi (jusqu'à 10 minutes après).
        if (Settings::get('app.push_retro', true)) {
            foreach (RetroDirect::program($now, false) as $e) {
                $key = 'retro:' . $e['id'] . ':' . $e['date'];
                if (isset($done[$key]) || $e['start'] - $now > 1800 || $now - $e['start'] > 600) {
                    continue;
                }
                $dm = Derived::match((int) $e['id']);
                if (!$dm) {
                    continue;
                }
                $start = $e['start'];
                $msg = self::bilingual(fn ($l) => [
                    'title' => $start - $now > 120
                        ? ($l === 'en' ? 'Rétro-Direct · kick-off at ' . date('g:i a', $start) : 'Rétro-Direct · coup d’envoi à ' . str_replace(':', ' h ', date('H:i', $start)))
                        : ($l === 'en' ? 'Rétro-Direct · kick-off!' : 'Rétro-Direct · c’est parti !'),
                    'body' => Site::matchLabel($dm) . ', ' . date_fr((string) $dm['date']) . ($l === 'en' ? ': replayed live, minute by minute.' : ' : rejoué en direct, minute par minute.'),
                    'url' => RetroDirect::url($e['s']),
                    'image' => !empty($dm['image']) ? img((string) $dm['image'], 800) : null,
                ]);
                $out[] = [$key, 'retro', $msg, ['urgency' => 'high', 'ttl' => max(300, $start + 1800 - $now)]];
            }
        }
        if (!self::daytime($now)) {
            return $out;
        }

        // 100 moments : à leur parution (dans les 3 jours, jamais ceux d'avant la mise en route).
        if (Settings::get('app.push_moments', true)) {
            foreach (Moments::all() as $row) {
                $ts = $row['date'] ? strtotime($row['date']) : false;
                $key = 'moment:' . $row['id'];
                if (!$row['visible'] || $row['number'] === null || !$ts || $ts > $now || $ts < $since || $ts < $now - 3 * 86400 || isset($done[$key])) {
                    continue;
                }
                $msg = self::bilingual(function ($l) use ($row) {
                    $doc = Fiches::get((int) $row['id']);
                    $title = $doc ? (string) (\App\Front\Fiche::localizeDoc($doc)['title'] ?? $row['title']) : $row['title'];
                    return [
                        'title' => ($l === 'en' ? 'Moment no. ' : 'Moment n° ') . $row['number'] . ($l === 'en' ? ' · the 100 moments of the centenary' : ' · les 100 moments du centenaire'),
                        'body' => mb_substr(plain_text($title), 0, 160),
                        'url' => url($row['path']),
                        'image' => $row['image'] ? img((string) $row['image'], 800) : null,
                    ];
                });
                $out[] = [$key, 'moments', $msg, ['ttl' => 2 * 86400]];
            }
        }

        // Kit souvenirs : le kit du mois, la première semaine, à partir de 10 h.
        $ym = date('Y-m', $now);
        if (Settings::get('app.push_kit', true) && (int) date('j', $now) <= 7 && (int) date('H', $now) >= 10 && !isset($done['kit:' . $ym])
            && ($c = Souvenirs::match($ym)) && ($dm = Derived::match((int) $c['id']))) {
            $out[] = ['kit:' . $ym, 'kit', self::bilingual(fn ($l) => [
                'title' => ($l === 'en' ? 'The souvenir kit for ' : 'Le kit souvenirs de ') . Souvenirs::monthLabel($ym),
                'body' => Site::matchLabel($dm) . ($l === 'en' ? ': four pages to print for the old-timers, and their memories to share.' : ' : quatre pages à imprimer pour les anciens, et leurs souvenirs à raconter.'),
                'url' => url('/interactif/souvenirs/'),
            ]), ['ttl' => 3 * 86400, 'urgency' => 'low']];
        }

        // Ce jour-là : le grand match du jour, à l'heure réglée (dans les deux heures qui suivent).
        $hour = max(8, min(20, (int) Settings::get('app.push_hour', 9)));
        $day = date('Y-m-d', $now);
        if (Settings::get('app.push_jour', true) && (int) date('G', $now) >= $hour && (int) date('G', $now) < $hour + 2 && !isset($done['jour:' . $day])
            && ($m = Derived::onThisDay(date('m-d', $now))[0] ?? null)) {
            $out[] = ['jour:' . $day, 'jour', self::bilingual(fn ($l) => [
                'title' => ($l === 'en' ? 'On this day in ' : 'Ce jour-là, en ') . substr((string) $m['date'], 0, 4),
                'body' => Site::matchLabel($m) . ' · ' . t((string) ($m['label'] ?: $m['comp'])),
                'url' => url((string) $m['path']),
                'image' => !empty($m['image']) ? img((string) $m['image'], 800) : null,
            ]), ['ttl' => 12 * 3600, 'urgency' => 'low']];
        }
        return $out;
    }
}
