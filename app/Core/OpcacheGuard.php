<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Nettoyage automatique d'OPcache. Quand un cache PHP est réécrit (fiche modifiée par une tâche
 * planifiée, enregistrement au back-office), OPcache compile la nouvelle version mais garde
 * l'ancienne en mémoire « perdue » ; il ne la récupère qu'une fois toute sa mémoire pleine.
 * Ici, après l'envoi d'une page (le visiteur n'attend pas), au plus une fois toutes les 5 minutes :
 * si la mémoire perdue dépasse le quart d'OPcache, il est vidé et se remplit de nouveau
 * (quelques dizaines de ms de recompilation, réparties sur les pages suivantes).
 * Les tâches planifiées tournent en ligne de commande, hors de l'OPcache du site : d'où ce passage par le web.
 */
final class OpcacheGuard
{
    /** Ce qui a été tenté au dernier vidage (affiché après le bouton). */
    public static string $detail = '';
    private const STATE = STORAGE_PATH . '/opcache-guard.json';
    private const EVERY = 300;
    /** Part de la mémoire d'OPcache perdue au-delà de laquelle il est vidé. */
    private const WASTED = 0.25;

    public static function tick(): void
    {
        if (!function_exists('opcache_get_status')) {
            return;
        }
        $mt = @filemtime(self::STATE);
        if ($mt && $mt > time() - self::EVERY) {
            return;
        }
        @touch(self::STATE);
        Response::detach();
        $st = @opcache_get_status(false);
        if (!is_array($st) || empty($st['opcache_enabled'])) {
            return;
        }
        $m = $st['memory_usage'] ?? [];
        $wasted = (int) ($m['wasted_memory'] ?? 0);
        $total = $wasted + (int) ($m['used_memory'] ?? 0) + (int) ($m['free_memory'] ?? 0);
        self::alerts($total > 0 ? ($wasted + (int) ($m['used_memory'] ?? 0)) / $total : 0, $total);
        if ($total <= 0 || ($wasted / $total < self::WASTED && empty($st['cache_full']))) {
            return;
        }
        if (self::reset()) {
            $s = self::state();
            JsonStore::write(self::STATE, ['count' => $s['count'] + 1, 'last' => date('c'), 'freed' => (int) round($wasted / 1048576)]);
        }
    }

    /**
     * Vide OPcache pour de bon. opcache_reset() seul ne suffit pas toujours chez o2switch : LiteSpeed
     * fait tourner PHP en mode « détaché », et la fonction peut répondre oui sans rien vider. On demande
     * donc aussi à LiteSpeed de redémarrer les processus PHP du compte (fichier ~/.lsphp_restart.txt,
     * prévu pour ça) : OPcache repart vide, sans coupure (les pages en cours se terminent).
     */
    public static function reset(): bool
    {
        $ok = function_exists('opcache_reset') && @opcache_reset();
        self::$detail = 'opcache_reset : ' . ($ok ? 'accepté' : 'refusé');
        if (preg_match('#^(/home\d*/[^/]+)/#', APP_ROOT . '/', $m)) {
            $t = @touch($m[1] . '/.lsphp_restart.txt');
            self::$detail .= ' · ' . $m[1] . '/.lsphp_restart.txt : ' . ($t ? 'créé' : 'impossible à créer');
            $ok = $t || $ok;
        }
        return $ok;
    }

    /**
     * État des alertes mémoire : commun à tous les musées (APP_ROOT/storage), comme OPcache et Redis
     * eux-mêmes. Contient les envois par mémoire et par adresse, et les liens déjà utilisés.
     */
    public static string $alertsFile = APP_ROOT . '/storage/memoire-alertes.json';
    /** @var null|callable(string,string,string):bool envoi d'e-mail remplaçable (tests) */
    public static $mailer = null;

    /**
     * E-mail aux administrateurs quand OPcache ou Redis atteint 90 % (au plus un par jour, par mémoire
     * et par adresse : un administrateur de plusieurs musées ne le reçoit qu'une fois). Vérifié ici,
     * côté site : les tâches planifiées ne voient pas l'OPcache du site.
     */
    private static function alerts(float $opRatio, int $opTotal): void
    {
        $hits = [];
        if ($opRatio >= 0.9) {
            $hits['opcache'] = 'OPcache est plein à ' . round($opRatio * 100) . ' % (' . round($opRatio * $opTotal / 1048576) . ' Mo sur ' . round($opTotal / 1048576) . ').';
        }
        $rd = \App\Services\ServerCheck::redis();
        if ($rd && $rd['max'] > 0 && $rd['used'] / $rd['max'] >= 0.9 && !\App\Services\ServerCheck::redisSelfEvicts((string) ($rd['policy'] ?? ''))) {
            $hits['redis'] = 'Redis est plein à ' . round(100 * $rd['used'] / $rd['max']) . ' % (' . $rd['used'] . ' Mo sur ' . $rd['max'] . ').';
        }
        if (!$hits) {
            return;
        }
        $to = array_values(array_unique(array_filter(array_map(fn ($u) => ($u['role'] ?? '') === 'admin' && ($u['status'] ?? '') === 'active' ? mb_strtolower(trim((string) ($u['email'] ?? ''))) : '', Auth::users()))));
        if (!$to) {
            return;
        }
        // Envois décidés et notés sous verrou (le même fichier que les liens utilisés), puis faits hors verrou.
        $plan = [];
        JsonStore::update(self::$alertsFile, function ($d) use ($hits, $to, &$plan) {
            $d = is_array($d) ? $d : [];
            $mails = (array) ($d['mails'] ?? []);
            foreach ($hits as $k => $text) {
                foreach ($to as $mail) {
                    $h = md5($mail);
                    if (($mails[$k][$h] ?? 0) > time() - 86400) {
                        continue;
                    }
                    $mails[$k][$h] = time();
                    $plan[$k][] = $mail;
                }
                $mails[$k] = array_filter((array) ($mails[$k] ?? []), fn ($t) => $t > time() - 2 * 86400);
            }
            $d['mails'] = $mails;
            return $d;
        }, []);
        foreach ($plan as $k => $mails) {
            $site = (string) Settings::get('general.site_name', 'Le musée');
            $url = rtrim((string) Settings::get('general.base_url', ''), '/') . '/admin/taches#serveur';
            $html = '<p>' . htmlspecialchars($hits[$k]) . '</p><p>Le site continue de fonctionner, mais il vaut mieux vider cette mémoire (commune à tous les musées du serveur).</p>'
                . '<p><a href="' . htmlspecialchars(self::link($k)) . '"><b>Vider ' . ($k === 'opcache' ? 'OPcache' : 'Redis') . ' maintenant</b></a> (lien sécurisé, valable 24 h, une seule fois ; une confirmation est demandée)</p>'
                . '<p><a href="' . htmlspecialchars($url) . '">Ouvrir le pavé Serveur</a></p>';
            foreach ($mails as $mail) {
                $subject = '[' . $site . '] Alerte mémoire : ' . ($k === 'opcache' ? 'OPcache' : 'Redis') . ' à 90 %';
                self::$mailer ? (self::$mailer)($mail, $subject, $html) : \App\Services\Mailer::send($mail, $subject, $html);
            }
        }
    }

    /** Lien signé de l'e-mail d'alerte : vide OPcache ou Redis sans connexion (24 h, une seule fois). */
    public static function link(string $k): string
    {
        $e = time() + 86400;
        return rtrim((string) Settings::get('general.base_url', ''), '/') . '/api/memoire/vider?' . http_build_query(['k' => $k, 'e' => $e, 's' => self::sig($k, $e)]);
    }

    private static function sig(string $k, int $e): string
    {
        return substr(hash_hmac('sha256', $k . '|' . $e, site_key('memoire')), 0, 32);
    }

    /** Vérifie le lien et vide ; message pour la page, ou null si le lien est invalide, expiré ou déjà utilisé. */
    public static function fromLink(string $k, int $e, string $s): ?string
    {
        if (!in_array($k, ['opcache', 'redis'], true) || $e < time() || !hash_equals(self::sig($k, $e), $s)) {
            return null;
        }
        $used = false;
        JsonStore::update(self::$alertsFile, function ($d) use ($s, &$used) {
            $d = is_array($d) ? $d : [];
            $d['used'] = array_filter((array) ($d['used'] ?? []), fn ($t) => $t > time() - 2 * 86400);
            $used = isset($d['used'][$s]);
            $d['used'][$s] = time();
            return $d;
        }, []);
        if ($used) {
            return null;
        }
        if ($k === 'opcache') {
            return self::reset() ? 'OPcache vidé : il se remplit de nouveau tout seul.' : 'Impossible de vider OPcache sur ce serveur.';
        }
        $n = PhpCache::flushRedis();
        return $n === null ? 'Redis n’est pas connecté.' : "Cache Redis vidé ($n entrées) : il se remplit de nouveau tout seul.";
    }

    /** @return array{count:int,last:?string,freed:int} */
    public static function state(): array
    {
        $s = @json_decode((string) @file_get_contents(self::STATE), true);
        return ['count' => (int) ($s['count'] ?? 0), 'last' => $s['last'] ?? null, 'freed' => (int) ($s['freed'] ?? 0)];
    }
}
