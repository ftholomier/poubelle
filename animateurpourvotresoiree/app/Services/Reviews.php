<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Crypto;
use App\Core\Request;
use App\Core\Str;
use App\Core\Url;

/** Avis clients : confirmation par email, modération, réponse du pro, invitations « client vérifié ». */
final class Reviews
{
    public const STATUSES = ['unverified' => 'Email non confirmé', 'pending' => 'À modérer', 'approved' => 'Publié', 'rejected' => 'Refusé'];

    public static function create(array $pro, array $in, bool $invited = false): array
    {
        $rev = Store::reviews()->insert([
            'pro_id' => (int) $pro['id'],
            'status' => $invited ? 'pending' : 'unverified',
            'rating' => max(1, min(5, (int) $in['rating'])),
            'author_name' => $in['name'],
            'author_email' => $in['email'],
            'title' => $in['title'] ?? '',
            'body' => $in['body'],
            'event_type' => $in['event_type'] ?? '',
            'event_date' => $in['event_date'] ?? '',
            'verified_email' => $invited,
            'verified_client' => $invited,
            'reply' => null,
            'ip_hash' => Request::ipHash(),
            'spam' => $in['spam'] ?? null,
        ]);
        Stats::hit('review', (int) $pro['id']);
        if ($invited) {
            self::afterVerified($rev, $pro);
        } else {
            $token = Crypto::sign(['r' => (int) $rev['id']], 'review', 86400 * 14);
            Mail::send((string) $in['email'], 'review_verify', ['prenom' => $in['name'], 'fiche' => Pros::displayName($pro)], ['bouton_url' => '/avis/confirmer/' . $token . '/', 'bouton_label' => 'Confirmer mon avis']);
        }
        return $rev;
    }

    public static function confirm(string $token): ?array
    {
        $p = Crypto::verify($token, 'review');
        if (!$p) {
            return null;
        }
        $rev = Store::reviews()->get((int) $p['r']);
        if (!$rev) {
            return null;
        }
        if ($rev['status'] === 'unverified') {
            $rev = Store::reviews()->update((int) $rev['id'], ['status' => 'pending', 'verified_email' => true, 'verified_at' => date('c')]);
            $pro = Store::pros()->get((int) $rev['pro_id']);
            if ($pro) {
                self::afterVerified($rev, $pro);
            }
        }
        return $rev;
    }

    private static function afterVerified(array $rev, array $pro): void
    {
        $mode = (string) Settings::get('moderation.reviews_mode', 'manual');
        $clean = ($rev['spam']['decision'] ?? 'clean') === 'clean';
        if ($mode === 'auto' && $clean) {
            self::approve((int) $rev['id']);
            return;
        }
        Notify::admin('review_pending', 'Avis à modérer (' . $rev['rating'] . '/5) sur ' . Pros::displayName($pro), Str::limit((string) $rev['body'], 160), Url::admin('avis'), 'warning');
    }

    public static function approve(int $id, ?string $by = null): void
    {
        $rev = Store::reviews()->update($id, ['status' => 'approved', 'approved_at' => date('c'), 'moderated_by' => $by]);
        if (!$rev) {
            return;
        }
        self::recompute((int) $rev['pro_id']);
        $pro = Store::pros()->get((int) $rev['pro_id']);
        if ($pro && !empty($pro['email'])) {
            Mail::send((string) $pro['email'], 'review_published', ['prenom' => $pro['first_name'] ?: Pros::displayName($pro), 'note' => (string) $rev['rating'], 'avis' => Str::limit((string) $rev['body'], 400)], ['avis' => e(Str::limit((string) $rev['body'], 400)), 'bouton_url' => '/espace-pro/avis/', 'bouton_label' => 'Voir et répondre']);
            Notify::pro((int) $pro['id'], '⭐ Nouvel avis publié', $rev['rating'] . '/5 — ' . Str::limit((string) $rev['body'], 80), '/espace-pro/avis/');
        }
    }

    public static function reject(int $id, ?string $by = null): void
    {
        $rev = Store::reviews()->update($id, ['status' => 'rejected', 'moderated_by' => $by]);
        if ($rev) {
            self::recompute((int) $rev['pro_id']);
        }
    }

    /** Recalcule la note moyenne publiée d'un pro. */
    public static function recompute(int $proId): void
    {
        $sum = 0;
        $n = 0;
        foreach (Store::reviews()->ids('pro_id', $proId) as $rid) {
            $l = Store::reviews()->light($rid);
            if ($l && $l['status'] === 'approved') {
                $sum += $l['rating'];
                $n++;
            }
        }
        Pros::save($proId, static function (array $p) use ($sum, $n): array {
            $p['rating'] = ['avg' => $n ? round($sum / $n, 2) : 0, 'count' => $n];
            return $p;
        });
    }

    /** @return array<int,array> avis publiés d'un pro, du plus récent au plus ancien */
    public static function published(int $proId): array
    {
        $out = [];
        foreach (array_reverse(Store::reviews()->ids('pro_id', $proId)) as $rid) {
            $r = Store::reviews()->get($rid);
            if ($r && $r['status'] === 'approved') {
                $out[] = $r;
            }
        }
        return $out;
    }

    /** Invitation d'un client par le pro (avis « client vérifié »). */
    public static function invite(array $pro, string $email, string $name = ''): bool
    {
        $token = Crypto::sign(['p' => (int) $pro['id'], 'e' => Str::email($email), 'n' => $name], 'review-invite', 86400 * 30);
        return Mail::send($email, 'review_invite', ['fiche' => Pros::displayName($pro)], ['bouton_url' => '/avis/invitation/' . $token . '/', 'bouton_label' => 'Donner mon avis']);
    }

    public static function inviteToken(string $token): ?array
    {
        return Crypto::verify($token, 'review-invite');
    }
}
