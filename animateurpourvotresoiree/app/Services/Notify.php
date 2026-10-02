<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Core\RateLimiter;
use App\Core\Url;

/**
 * Alertes : centre de notifications du back-office, emails (immédiats ou récapitulatif quotidien)
 * et notifications push sur mobile/ordinateur (Web Push).
 */
final class Notify
{
    public const TYPES = [
        'pro_registered' => 'Nouvelle inscription pro',
        'pro_updated' => 'Fiche pro modifiée',
        'request_pending' => 'Demande de devis à modérer',
        'request_new' => 'Demande de devis diffusée',
        'message_pending' => 'Message à modérer',
        'review_pending' => 'Avis à modérer',
        'contact' => 'Formulaire de contact',
        'spam' => 'Spam bloqué',
        'error' => 'Erreur technique',
        'security' => 'Sécurité',
        'system' => 'Système',
        'campaign' => 'Emailing',
        'ai' => 'Assistant IA',
    ];

    public static function admin(string $type, string $title, string $body = '', string $link = '', string $level = 'info'): void
    {
        try {
            Store::notifications()->insert([
                'type' => $type,
                'level' => $level,
                'title' => mb_substr($title, 0, 200),
                'body' => mb_substr($body, 0, 2000),
                'link' => $link,
                'read_at' => null,
            ]);
            $pref = Settings::get('notifications.types.' . $type, ['email' => 'digest', 'push' => false]);
            if (($pref['email'] ?? 'off') === 'instant' && RateLimiter::attempt('notify-mail:' . $type . ':' . md5($title), 1, 600)) {
                foreach (Mail::adminEmails() as $to) {
                    $m = Mail::build('admin_alert', ['titre' => $title, 'corps' => $body], $link !== '' ? ['bouton_url' => $link, 'bouton_label' => 'Voir dans le back-office'] : []);
                    Mail::queue($to, $m['subject'], $m['html'], ['priority' => 1]);
                }
            }
            if (!empty($pref['push'])) {
                $pushBody = $body !== '' ? $body : (self::TYPES[$type] ?? '');
                \App\Core\App::defer(static fn () => Push::toAdmins($title, mb_substr($pushBody, 0, 180), $link !== '' ? $link : Url::admin(), $type));
            }
        } catch (\Throwable $e) {
            Logger::error('Notification impossible : ' . $e->getMessage());
        }
    }

    public static function unreadCount(): int
    {
        $n = 0;
        foreach (Store::notifications()->iterate() as $row) {
            if (!$row['read']) {
                $n++;
            }
            if ($n >= 99) {
                break;
            }
        }
        return $n;
    }

    public static function latest(int $limit = 15): array
    {
        return Store::notifications()->find(null, null, $limit)['items'];
    }

    public static function markAllRead(): void
    {
        $patches = [];
        foreach (Store::notifications()->iterate() as $id => $row) {
            if (!$row['read']) {
                $patches[(int) $id] = ['read_at' => date('c')];
            }
        }
        if ($patches) {
            Store::notifications()->updateMany($patches);
        }
    }

    /** Récapitulatif quotidien envoyé aux administrateurs (cron, heure réglable). */
    public static function dailyDigest(): bool
    {
        $since = date('c', strtotime('-24 hours'));
        $items = Store::notifications()->find(static fn ($n) => $n['created'] >= $since, null, 200)['items'];
        $byType = [];
        foreach ($items as $n) {
            $byType[$n['type']][] = $n;
        }
        $pendingPros = Store::pros()->count(static fn ($p) => $p['status'] === 'pending');
        $pendingReq = Store::requests()->find(static fn ($r) => $r['status'] === 'pending', null, 1)['total'];
        $pendingMsg = Store::messages()->find(static fn ($m) => $m['status'] === 'pending', null, 1)['total'];
        $pendingRev = Store::reviews()->count(static fn ($r) => $r['status'] === 'pending');
        $rows = [
            'Visiteurs (24 h)' => (string) Stats::total('uniq', 1),
            'Pages vues (24 h)' => (string) Stats::total('pv', 1),
            'Demandes de devis (24 h)' => (string) Stats::total('devis', 1),
            'Messages aux pros (24 h)' => (string) Stats::total('message', 1),
            'Pros à valider' => (string) $pendingPros,
            'Devis à modérer' => (string) $pendingReq,
            'Messages à modérer' => (string) $pendingMsg,
            'Avis à modérer' => (string) $pendingRev,
        ];
        foreach ($byType as $type => $list) {
            $rows[self::TYPES[$type] ?? $type] = count($list) . ' : ' . implode(' · ', array_slice(array_map(static fn ($n) => $n['title'], $list), 0, 5));
        }
        $sent = false;
        foreach (Mail::adminEmails() as $to) {
            $m = Mail::build('admin_digest', [], ['details' => Mail::details($rows), 'bouton_url' => Url::admin(), 'bouton_label' => 'Ouvrir le tableau de bord']);
            Mail::queue($to, $m['subject'], $m['html'], ['priority' => 3]);
            $sent = true;
        }
        return $sent;
    }

    /** Notification push à un pro (nouvelle demande, message…). */
    public static function pro(int $proId, string $title, string $body, string $link): void
    {
        \App\Core\App::defer(static function () use ($proId, $title, $body, $link): void {
            try {
                Push::toOwner('pro', $proId, $title, $body, $link, 'pro');
            } catch (\Throwable $e) {
                Logger::log('push', 'Push pro impossible', ['pro' => $proId, 'error' => $e->getMessage()], 'warning');
            }
        });
    }
}
