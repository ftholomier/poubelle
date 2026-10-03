<?php
declare(strict_types=1);

namespace App\Front;

use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Data\Derived;
use App\Services\Search;

/**
 * API JSON du site public (/api/…) : recherche, consentement, assistant,
 * « Ce jour-là », carte, votes, dons (webhooks Stripe / PayPal), newsletter.
 * Jamais bloquée par la page d'attente (les webhooks doivent toujours passer).
 */
final class Api
{
    public static function handle(Request $req): Response
    {
        $p = rtrim($req->path, '/');
        $post = $req->method === 'POST';
        // Contre la falsification de requêtes : hors webhooks (signés), un POST doit être du JSON.
        // Un formulaire d'un autre site ne peut pas en envoyer, et fetch() depuis un autre site serait bloqué (CORS).
        $ctype = strtolower((string) ($req->server['CONTENT_TYPE'] ?? $req->server['HTTP_CONTENT_TYPE'] ?? ''));
        if ($post && !str_ends_with($p, '/webhook') && !str_starts_with($ctype, 'application/json')) {
            return Response::json(['error' => t('Requête refusée.')], 415);
        }
        try {
            $res = match (true) {
                $p === '/api/recherche' => self::search($req),
                $p === '/api/consentement' && $post => self::consent($req),
                $p === '/api/ce-jour-la' => self::onThisDay($req),
                $p === '/api/chat' && $post => \App\Services\Rag::endpoint($req),
                $p === '/api/chat/avis' && $post => \App\Services\Rag::feedback($req),
                $p === '/api/carte' => Interactive::mapData($req),
                $p === '/api/onze' && $post => Interactive::onzeVote($req),
                $p === '/api/quiz' && $post => Interactive::quizResult($req),
                $p === '/api/dons/session' && $post => Donations::checkout($req),
                $p === '/api/dons/paypal/capture' && $post => Donations::paypalCapture($req),
                $p === '/api/dons/stripe/webhook' && $post => Donations::stripeWebhook($req),
                $p === '/api/dons/paypal/webhook' && $post => Donations::paypalWebhook($req),
                $p === '/api/dons/jauge' => Donations::gaugeJson(),
                $p === '/api/newsletter' && $post => Community::newsletterApi($req),
                default => Response::json(['error' => t('Ressource introuvable')], 404),
            };
        } catch (\Throwable $e) {
            error_log((string) $e);
            $res = Response::json(['error' => t('Erreur interne')], 500);
        }
        $res->headers['Cache-Control'] ??= 'no-store';
        $res->headers['X-Content-Type-Options'] = 'nosniff';
        return $res;
    }

    private static function search(Request $req): Response
    {
        if (!RateLimiter::hit('search', $req->ip(), 240, 60)) {
            return Response::json(['error' => t('Trop de requêtes, patientez un instant.')], 429);
        }
        $q = $req->str('q');
        if ($req->str('suggest') === '1') {
            return Response::json(['results' => Search::suggest($q)]);
        }
        $type = $req->str('type') ?: null;
        $limit = min(50, max(1, (int) $req->str('limit', '20')));
        $offset = max(0, (int) $req->str('offset', '0'));
        $r = Search::query($q, $type, $limit, $offset);
        return Response::json([
            'q' => $r['q'],
            'total' => $r['total'],
            'counts' => $r['counts'],
            'items' => array_map(fn ($s) => Search::describe($s) + [
                'href' => url($s['path']),
                'image' => $s['image'] ? img($s['image'], 160) : null,
                'snippet' => Search::snippet($s, $r['tokens']),
            ], $r['items']),
        ]);
    }

    /** Preuve du consentement aux cookies (RGPD) : choix, date, empreinte anonymisée. */
    private static function consent(Request $req): Response
    {
        if (!RateLimiter::hit('consent', $req->ip(), 30, 3600)) {
            return Response::json(['ok' => false], 429);
        }
        $c = $req->json() ?: [];
        $choices = [];
        foreach (['necessary', 'video', 'social', 'stats'] as $k) {
            $choices[$k] = !empty($c[$k]);
        }
        $line = [
            'at' => date('c'),
            'choices' => $choices,
            'v' => (int) ($c['v'] ?? 1),
            // Adresse IP jamais stockée en clair : empreinte salée qui change chaque jour.
            'ip' => substr(hash('sha256', $req->ip() . date('Y-m-d') . self::salt()), 0, 16),
            'ua' => mb_substr((string) ($req->header('User-Agent') ?? ''), 0, 120),
        ];
        \App\Core\JsonStore::append(STORAGE_PATH . '/consent/' . date('Y-m') . '.jsonl', $line);
        return Response::json(['ok' => true]);
    }

    private static function salt(): string
    {
        $f = STORAGE_PATH . '/secret.key';
        return is_file($f) ? substr(hash('sha256', (string) file_get_contents($f)), 0, 16) : 'sochaux-retro';
    }

    private static function onThisDay(Request $req): Response
    {
        $mmdd = preg_match('/^\d{2}-\d{2}$/', $req->str('date')) ? $req->str('date') : date('m-d');
        $list = array_map(fn ($x) => [
            'label' => Site::matchLabel($x),
            'date' => $x['date'],
            'competition' => $x['label'] ?: $x['comp'],
            'result' => $x['result'],
            'href' => url($x['path']),
            'image' => $x['image'] ? img($x['image'], 480) : null,
        ], Derived::onThisDay($mmdd));
        $res = Response::json(['date' => $mmdd, 'matches' => $list]);
        $res->headers['Cache-Control'] = 'public, max-age=3600';
        return $res;
    }
}
