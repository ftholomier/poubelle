<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Collection;
use App\Core\Doc;
use App\Core\Str;

/** Registre des collections JSON et de leurs index. */
final class Store
{
    /** @var array<string,Collection> */
    private static array $cols = [];
    /** @var array<string,Doc> */
    private static array $docs = [];

    public static function col(string $name): Collection
    {
        if (!isset(self::$cols[$name])) {
            [$indexer, $secondary] = self::definition($name);
            self::$cols[$name] = new Collection($name, $indexer, $secondary);
        }
        return self::$cols[$name];
    }

    public static function doc(string $name, array $defaults = []): Doc
    {
        $key = $name . '#' . md5(serialize(array_keys($defaults)));
        return self::$docs[$key] ??= new Doc($name, $defaults);
    }

    public static function reset(): void
    {
        self::$cols = [];
        self::$docs = [];
    }

    public static function pros(): Collection { return self::col('pros'); }
    public static function requests(): Collection { return self::col('requests'); }
    public static function messages(): Collection { return self::col('messages'); }
    public static function reviews(): Collection { return self::col('reviews'); }
    public static function prospects(): Collection { return self::col('prospects'); }
    public static function articles(): Collection { return self::col('articles'); }
    public static function pages(): Collection { return self::col('pages'); }
    public static function admins(): Collection { return self::col('admins'); }
    public static function tokens(): Collection { return self::col('tokens'); }
    public static function campaigns(): Collection { return self::col('campaigns'); }
    public static function mailQueue(): Collection { return self::col('mail_queue'); }
    public static function notifications(): Collection { return self::col('notifications'); }
    public static function pushSubs(): Collection { return self::col('push_subs'); }
    public static function chats(): Collection { return self::col('chats'); }
    public static function contacts(): Collection { return self::col('contacts'); }

    /** @return array{0:callable,1:array} */
    private static function definition(string $name): array
    {
        return match ($name) {
            'pros' => [static fn (array $p): array => Pros::light($p), []],
            'requests' => [static fn (array $r): array => [
                'id' => $r['id'],
                'status' => $r['status'] ?? 'pending',
                'created' => $r['created_at'] ?? '',
                'name' => trim(($r['client']['first_name'] ?? '') . ' ' . ($r['client']['last_name'] ?? '')) ?: ($r['client']['company'] ?? ''),
                'email' => $r['client']['email'] ?? '',
                'phone' => $r['client']['phone'] ?? '',
                'city' => $r['event']['city'] ?? ($r['client']['city'] ?? ''),
                'dep' => $r['event']['dep'] ?? '',
                'date' => $r['event']['date'] ?? '',
                'type' => $r['event']['type'] ?? '',
                'cats' => $r['event']['categories'] ?? [],
                'n' => count($r['recipients'] ?? []),
                'score' => (int) ($r['spam']['score'] ?? 0),
                'src' => $r['source'] ?? 'form',
                'excerpt' => Str::limit(str_replace("\n", ' ', (string) ($r['event']['message'] ?? '')), 140),
            ], ['recipients' => 'many', 'client.email' => 'one']],
            'messages' => [static fn (array $m): array => [
                'id' => $m['id'],
                'status' => $m['status'] ?? 'pending',
                'pro' => (int) ($m['pro_id'] ?? 0),
                'created' => $m['created_at'] ?? '',
                'name' => $m['name'] ?? '',
                'email' => $m['email'] ?? '',
                'phone' => $m['phone'] ?? '',
                'score' => (int) ($m['spam']['score'] ?? 0),
                'read' => !empty($m['read_at']),
                'excerpt' => Str::limit(str_replace("\n", ' ', (string) ($m['message'] ?? '')), 140),
            ], ['pro_id' => 'one']],
            'reviews' => [static fn (array $r): array => [
                'id' => $r['id'],
                'status' => $r['status'] ?? 'pending',
                'pro' => (int) ($r['pro_id'] ?? 0),
                'rating' => (int) ($r['rating'] ?? 0),
                'author' => $r['author_name'] ?? '',
                'created' => $r['created_at'] ?? '',
                'verified' => !empty($r['verified_client']),
                'excerpt' => Str::limit((string) ($r['body'] ?? ''), 120),
            ], ['pro_id' => 'one']],
            'prospects' => [static fn (array $p): array => [
                'id' => $p['id'],
                'email' => $p['email'] ?? '',
                'created' => $p['created_at'] ?? '',
                'src' => $p['source'] ?? '',
                'unsub' => !empty($p['unsubscribed_at']),
            ], ['email' => 'one']],
            'articles' => [static fn (array $a): array => [
                'id' => $a['id'],
                'status' => $a['status'] ?? 'draft',
                'slug' => $a['slug'] ?? '',
                'title' => $a['title'] ?? '',
                'published' => $a['published_at'] ?? '',
                'cat' => $a['category'] ?? '',
                'views' => (int) ($a['views'] ?? 0),
                'legacy' => $a['legacy_url'] ?? '',
                'image' => $a['image'] ?? '',
                'excerpt' => Str::limit((string) ($a['excerpt'] ?? ''), 200),
                'sponsored' => !empty($a['sponsored']),
            ], []],
            'pages' => [static fn (array $p): array => [
                'id' => $p['id'],
                'status' => $p['status'] ?? 'published',
                'slug' => $p['slug'] ?? '',
                'title' => $p['title'] ?? '',
                'updated' => $p['updated_at'] ?? '',
            ], []],
            'admins' => [static fn (array $a): array => [
                'id' => $a['id'],
                'email' => $a['email'] ?? '',
                'name' => $a['name'] ?? '',
                'role' => $a['role'] ?? 'admin',
                'status' => $a['status'] ?? 'active',
                'login' => $a['last_login_at'] ?? '',
                'totp' => !empty($a['totp_enabled']),
            ], []],
            'tokens' => [static fn (array $t): array => [
                'id' => $t['id'],
                'type' => $t['type'] ?? '',
                'owner' => ($t['owner_type'] ?? '') . ':' . ($t['owner_id'] ?? ''),
                'exp' => $t['expires_at'] ?? '',
                'used' => !empty($t['used_at']),
            ], ['hash' => 'one']],
            'campaigns' => [static fn (array $c): array => [
                'id' => $c['id'],
                'name' => $c['name'] ?? '',
                'status' => $c['status'] ?? 'draft',
                'subject' => $c['subject'] ?? '',
                'created' => $c['created_at'] ?? '',
                'scheduled' => $c['scheduled_at'] ?? '',
                'sent' => (int) ($c['stats']['sent'] ?? 0),
                'total' => (int) ($c['stats']['total'] ?? 0),
                'opens' => (int) ($c['stats']['opens'] ?? 0),
                'clicks' => (int) ($c['stats']['clicks'] ?? 0),
            ], []],
            'mail_queue' => [static fn (array $m): array => [
                'id' => $m['id'],
                'status' => $m['status'] ?? 'queued',
                'to' => $m['to'] ?? '',
                'subject' => $m['subject'] ?? '',
                'campaign' => (int) ($m['campaign_id'] ?? 0),
                'prio' => (int) ($m['priority'] ?? 5),
                'after' => $m['send_after'] ?? '',
                'attempts' => (int) ($m['attempts'] ?? 0),
                'created' => $m['created_at'] ?? '',
                'error' => Str::limit((string) ($m['error'] ?? ''), 120),
            ], ['status' => 'one', 'campaign_id' => 'one']],
            'notifications' => [static fn (array $n): array => [
                'id' => $n['id'],
                'type' => $n['type'] ?? '',
                'level' => $n['level'] ?? 'info',
                'title' => $n['title'] ?? '',
                'body' => Str::limit((string) ($n['body'] ?? ''), 200),
                'link' => $n['link'] ?? '',
                'read' => !empty($n['read_at']),
                'created' => $n['created_at'] ?? '',
            ], []],
            'push_subs' => [static fn (array $s): array => [
                'id' => $s['id'],
                'owner' => ($s['owner_type'] ?? '') . ':' . ($s['owner_id'] ?? ''),
                'created' => $s['created_at'] ?? '',
                'ua' => Str::limit((string) ($s['ua'] ?? ''), 60),
                'fails' => (int) ($s['fails'] ?? 0),
            ], ['owner' => 'one', 'endpoint_hash' => 'one']],
            'chats' => [static fn (array $c): array => [
                'id' => $c['id'],
                'created' => $c['created_at'] ?? '',
                'updated' => $c['updated_at'] ?? '',
                'n' => count($c['messages'] ?? []),
                'first' => Str::limit((string) ($c['messages'][0]['text'] ?? ''), 120),
                'ip' => $c['ip_hash'] ?? '',
                'cards' => (int) ($c['cards_shown'] ?? 0),
            ], []],
            'contacts' => [static fn (array $c): array => [
                'id' => $c['id'],
                'status' => $c['status'] ?? 'new',
                'name' => $c['name'] ?? '',
                'email' => $c['email'] ?? '',
                'subject' => $c['subject'] ?? '',
                'created' => $c['created_at'] ?? '',
                'score' => (int) ($c['spam']['score'] ?? 0),
            ], []],
            default => [null, []],
        };
    }
}
