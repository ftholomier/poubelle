<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;

/** Compteurs du back-office (pastilles du menu), mis en cache une minute. */
final class AdminStats
{
    public static function counts(): array
    {
        return Cache::remember('admin_counts', 60, static function (): array {
            $c = ['pros' => 0, 'requests' => 0, 'messages' => 0, 'contacts' => 0, 'reviews' => 0, 'notifications' => 0, 'queue_failed' => 0];
            foreach (Store::pros()->iterate() as $p) {
                if ($p['status'] === 'pending') {
                    $c['pros']++;
                }
            }
            $since = date('c', strtotime('-120 days'));
            foreach (Store::requests()->iterate() as $r) {
                if ($r['created'] < $since) {
                    break;
                }
                if ($r['status'] === 'pending') {
                    $c['requests']++;
                }
            }
            foreach (Store::messages()->iterate() as $m) {
                if ($m['created'] < $since) {
                    break;
                }
                if ($m['status'] === 'pending') {
                    $c['messages']++;
                }
            }
            foreach (Store::contacts()->iterate() as $m) {
                if ($m['status'] === 'new') {
                    $c['contacts']++;
                }
            }
            foreach (Store::reviews()->iterate() as $r) {
                if ($r['status'] === 'pending') {
                    $c['reviews']++;
                }
            }
            $c['notifications'] = Notify::unreadCount();
            $c['queue_failed'] = count(Store::mailQueue()->ids('status', 'failed'));
            return $c;
        });
    }

    public static function forget(): void
    {
        Cache::forget('admin_counts');
    }
}
