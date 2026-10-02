<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Crypto;
use App\Core\Sanitizer;
use App\Core\Url;

/**
 * Campagnes d'emailing vers les adhérents : segments, variables, file d'envoi à débit limité,
 * suivi des ouvertures et des clics, désinscription en un clic.
 */
final class Mailing
{
    public const SEGMENT_FIELDS = [
        'status' => 'Statut de la fiche',
        'cats' => 'Catégories',
        'deps' => 'Départements',
        'regions' => 'Régions',
        'photos' => 'Photos',
        'login' => 'Dernière connexion',
        'score' => 'Complétude',
    ];

    /** Pros correspondant à un segment. @return array<int,array> index léger */
    public static function audience(array $seg): array
    {
        $statuses = (array) ($seg['status'] ?? ['active']);
        $cats = (array) ($seg['cats'] ?? []);
        $deps = (array) ($seg['deps'] ?? []);
        $regions = (array) ($seg['regions'] ?? []);
        $photos = (string) ($seg['photos'] ?? '');
        $login = (string) ($seg['login'] ?? '');
        $scoreMax = (int) ($seg['score_max'] ?? 100);
        $unsub = Store::doc('unsubscribed')->get('pros', []);
        $out = [];
        foreach (Store::pros()->iterate(false) as $id => $p) {
            if (!$p['email'] || !\App\Core\Str::emailValid($p['email']) || isset($unsub[(string) $id])) {
                continue;
            }
            if ($statuses && !in_array($p['status'], $statuses, true)) {
                continue;
            }
            if ($cats && !array_intersect($cats, $p['cats'])) {
                continue;
            }
            if ($deps && !in_array($p['dep'], $deps, true) && !array_intersect($deps, $p['zones'])) {
                continue;
            }
            if ($regions && !in_array($p['region'], $regions, true)) {
                continue;
            }
            if ($photos === 'none' && $p['photos'] > 0) {
                continue;
            }
            if ($photos === 'some' && $p['photos'] === 0) {
                continue;
            }
            if ($login === 'never' && $p['login_at']) {
                continue;
            }
            if ($login === '90' && $p['login_at'] && $p['login_at'] > date('c', strtotime('-90 days'))) {
                continue;
            }
            if ($p['score'] > $scoreMax) {
                continue;
            }
            $out[(int) $id] = $p;
        }
        // un seul envoi par adresse email
        $seen = [];
        foreach ($out as $id => $p) {
            $e = mb_strtolower($p['email']);
            if (isset($seen[$e])) {
                unset($out[$id]);
            }
            $seen[$e] = true;
        }
        return $out;
    }

    /** Lance l'envoi d'une campagne : personnalise et met chaque email en file. */
    public static function launch(int $campaignId): int
    {
        $c = Store::campaigns()->get($campaignId);
        if (!$c || !in_array($c['status'], ['draft', 'scheduled'], true)) {
            return 0;
        }
        $audience = self::audience($c['segment'] ?? []);
        $n = 0;
        foreach ($audience as $id => $p) {
            $pro = Store::pros()->get($id);
            if (!$pro) {
                continue;
            }
            $vars = [
                'prenom' => $pro['first_name'] ?: \App\Services\Pros::displayName($pro),
                'nom' => $pro['last_name'] ?? '',
                'fiche' => Pros::displayName($pro),
                'login' => $pro['login'] ?? '',
                'ville' => $pro['city'] ?? '',
                'lien_fiche' => Url::abs(Url::pro($pro)),
                'lien_espace' => Url::abs('/espace-pro/'),
            ];
            $body = (string) preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', static fn ($m) => e($vars[$m[1]] ?? ''), (string) $c['body']);
            $subject = (string) preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', static fn ($m) => (string) ($vars[$m[1]] ?? ''), (string) $c['subject']);
            $token = Crypto::sign(['c' => $campaignId, 'p' => $id], 'trk');
            if (Settings::get('mailing.track_clicks', true)) {
                $body = (string) preg_replace_callback('/href="(https?:\/\/[^"]+)"/i', static function ($m) use ($token): string {
                    $url = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    if (str_contains($url, '/e/u/')) {
                        return $m[0]; // lien de désinscription : jamais réécrit
                    }
                    return 'href="' . e(Url::abs('/e/c/' . $token . '?u=' . rawurlencode($url) . '&h=' . self::clickHash($token, $url))) . '"';
                }, $body);
            }
            $unsub = Mail::unsubscribeUrl('pro', $id);
            $footer = '<p style="font-size:12px;color:#6b5f80">Vous recevez cet email car vous êtes inscrit sur ' . e(Settings::siteName()) . '. <a href="' . e($unsub) . '" style="color:#6b5f80">Se désinscrire</a></p>';
            if (Settings::get('mailing.track_opens', true)) {
                $footer .= '<img src="' . e(Url::abs('/e/o/' . $token . '.gif')) . '" width="1" height="1" alt="" style="display:block;border:0">';
            }
            $html = \App\Core\View::partial('emails/layout', ['subject' => $subject, 'body' => $body, 'footer' => $footer]);
            Mail::queue((string) $pro['email'], $subject, $html, ['campaign_id' => $campaignId, 'ref' => 'pro:' . $id, 'unsubscribe' => $unsub, 'priority' => 7]);
            $n++;
        }
        Store::campaigns()->update($campaignId, ['status' => 'sending', 'launched_at' => date('c'), 'stats' => ['total' => $n, 'sent' => 0, 'failed' => 0, 'opens' => 0, 'clicks' => 0, 'unsub' => 0]]);
        \App\Core\Logger::audit('Campagne lancée', ['id' => $campaignId, 'destinataires' => $n]);
        return $n;
    }

    /** Empreinte qui empêche d'utiliser /e/c/ comme redirection ouverte. */
    public static function clickHash(string $token, string $url): string
    {
        return substr(hash_hmac('sha256', $token . '|' . $url, Crypto::key()), 0, 16);
    }

    public static function countSent(int $id): void
    {
        Store::campaigns()->update($id, static function (array $c): array {
            $c['stats']['sent'] = (int) ($c['stats']['sent'] ?? 0) + 1;
            if ($c['stats']['sent'] + (int) ($c['stats']['failed'] ?? 0) >= (int) ($c['stats']['total'] ?? 0) && $c['status'] === 'sending') {
                $c['status'] = 'sent';
                $c['finished_at'] = date('c');
                Notify::admin('campaign', 'Campagne envoyée : ' . ($c['name'] ?? ''), ($c['stats']['sent'] ?? 0) . ' emails envoyés', Url::admin('emailing/' . $c['id']), 'success');
            }
            return $c;
        }, false);
    }

    public static function countFailed(int $id): void
    {
        Store::campaigns()->update($id, static function (array $c): array {
            $c['stats']['failed'] = (int) ($c['stats']['failed'] ?? 0) + 1;
            return $c;
        }, false);
    }

    public static function trackOpen(string $token): void
    {
        $p = Crypto::verify($token, 'trk');
        if (!$p) {
            return;
        }
        $key = 'open:' . $p['c'] . ':' . $p['p'];
        if (!\App\Core\RateLimiter::attempt($key, 1, 86400 * 30)) {
            return;
        }
        Store::campaigns()->update((int) $p['c'], static function (array $c): array {
            $c['stats']['opens'] = (int) ($c['stats']['opens'] ?? 0) + 1;
            return $c;
        }, false);
    }

    public static function trackClick(string $token): void
    {
        $p = Crypto::verify($token, 'trk');
        if (!$p || !\App\Core\RateLimiter::attempt('click:' . $p['c'] . ':' . $p['p'], 1, 86400 * 30)) {
            return;
        }
        Store::campaigns()->update((int) $p['c'], static function (array $c): array {
            $c['stats']['clicks'] = (int) ($c['stats']['clicks'] ?? 0) + 1;
            return $c;
        }, false);
    }

    public static function sanitizeBody(string $html): string
    {
        return Sanitizer::html($html, ['images' => true, 'tables' => true, 'link_rel' => 'noopener']);
    }

    /** Campagnes programmées arrivées à échéance (cron). */
    public static function launchScheduled(): int
    {
        $n = 0;
        $now = date('c');
        foreach (Store::campaigns()->iterate() as $c) {
            if ($c['status'] === 'scheduled' && $c['scheduled'] !== '' && $c['scheduled'] <= $now) {
                $n += self::launch((int) $c['id']);
            }
        }
        return $n;
    }
}
