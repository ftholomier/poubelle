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
    private const STATE = STORAGE_PATH . '/opcache-guard.json';
    private const EVERY = 300;
    /** Part de la mémoire d'OPcache perdue au-delà de laquelle il est vidé. */
    private const WASTED = 0.25;

    public static function tick(): void
    {
        if (!function_exists('opcache_get_status') || !function_exists('opcache_reset')) {
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
        if ($total <= 0 || ($wasted / $total < self::WASTED && empty($st['cache_full']))) {
            return;
        }
        if (@opcache_reset()) {
            $s = self::state();
            JsonStore::write(self::STATE, ['count' => $s['count'] + 1, 'last' => date('c'), 'freed' => (int) round($wasted / 1048576)]);
        }
    }

    /** @return array{count:int,last:?string,freed:int} */
    public static function state(): array
    {
        $s = @json_decode((string) @file_get_contents(self::STATE), true);
        return ['count' => (int) ($s['count'] ?? 0), 'last' => $s['last'] ?? null, 'freed' => (int) ($s['freed'] ?? 0)];
    }
}
