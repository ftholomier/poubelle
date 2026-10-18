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
 * « Ce jour-là », carte, votes, Rétro-Direct, Fil jaune, dons (webhooks Stripe / PayPal), newsletter,
 * notifications de l'appli (/api/push/…).
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
                $p === '/api/vu' && $post => self::seen($req),
                $p === '/api/memoire/vider' => self::memoryFlush($req, $post),
                $p === '/api/ce-jour-la' => self::onThisDay($req),
                $p === '/api/chat' && $post => \App\Services\Rag::endpoint($req),
                $p === '/api/chat/avis' && $post => \App\Services\Rag::feedback($req),
                $p === '/api/carte' => Interactive::mapData($req),
                $p === '/api/onze' && $post => Interactive::onzeVote($req),
                $p === '/api/quiz' && $post => Interactive::quizResult($req),
                $p === '/api/retro-direct' && $post => Retro::api($req),
                $p === '/api/carnet' && $post => CarnetPages::api($req),
                $p === '/api/quiz-live' && $post => QuizLivePages::api($req),
                $p === '/api/defi' && $post => DailyQuizPages::api($req),
                $p === '/api/fil-jaune' => Fil::api($req),
                $p === '/api/album-vignettes' && $post => AlbumPages::api($req),
                $p === '/api/album-vignettes/vignettes.json' => AlbumPages::pool($req),
                (bool) preg_match('#^/api/album-vignettes/partage/([a-z0-9-]+)\.png$#', $p, $am) => AlbumPages::share($req, $am[1]),
                $p === '/api/dons/session' && $post => Donations::checkout($req),
                $p === '/api/dons/paypal/capture' && $post => Donations::paypalCapture($req),
                $p === '/api/dons/stripe/webhook' && $post => Donations::stripeWebhook($req),
                $p === '/api/dons/paypal/webhook' && $post => Donations::paypalWebhook($req),
                $p === '/api/dons/jauge' => Donations::gaugeJson(),
                $p === '/api/newsletter' && $post => Community::newsletterApi($req),
                str_starts_with($p, '/api/push/') => Appli::api($req, substr($p, 10)),
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
            // Rien de personnel : le navigateur peut garder la réponse 5 minutes.
            $res = Response::json(['results' => Search::suggest($q)]);
            $res->headers['Cache-Control'] = 'public, max-age=300';
            return $res;
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

    /**
     * Lien de l'e-mail d'alerte mémoire (OPcache ou Redis à 90 %) : la page demande une confirmation
     * (les antivirus de messagerie ouvrent les liens tout seuls), puis le vidage est fait en POST JSON.
     */
    private static function memoryFlush(Request $req, bool $post): Response
    {
        if ($post) {
            $in = $req->json() ?: [];
            $msg = \App\Core\OpcacheGuard::fromLink((string) ($in['k'] ?? ''), (int) ($in['e'] ?? 0), (string) ($in['s'] ?? ''));
            return Response::json($msg === null ? ['error' => 'Lien invalide, expiré ou déjà utilisé.'] : ['ok' => true, 'message' => $msg], $msg === null ? 403 : 200);
        }
        $k = (string) ($req->query['k'] ?? '');
        $name = $k === 'redis' ? 'Redis' : 'OPcache';
        $data = json_encode(['k' => $k, 'e' => (int) ($req->query['e'] ?? 0), 's' => (string) ($req->query['s'] ?? '')], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $html = '<!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Vider ' . $name . '</title>'
            . '<body style="font-family:Georgia,serif;background:#F3EDDF;color:#0E1F4D;max-width:520px;margin:60px auto;padding:0 16px">'
            . '<h1 style="font-family:Arial Narrow,sans-serif">Vider ' . $name . '</h1><p>La mémoire se remplit de nouveau toute seule ; les premières pages seront un peu plus lentes pendant quelques secondes.</p>'
            . '<p><button id="go" style="font:bold 18px Arial Narrow,sans-serif;background:#F6C400;color:#0E1F4D;border:0;padding:12px 22px;cursor:pointer">Vider ' . $name . ' maintenant</button></p><p id="out" role="status"></p>'
            . '<script nonce="' . csp_nonce() . '">document.getElementById("go").onclick=function(){var b=this;b.disabled=true;fetch(location.pathname,{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(' . $data . ')}).then(function(r){return r.json()}).then(function(r){document.getElementById("out").textContent=r.message||r.error}).catch(function(){document.getElementById("out").textContent="Erreur réseau.";b.disabled=false})}</script>';
        return new Response($html, 200, ['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'no-store']);
    }

    /**
     * Visite d'une page gardée en cache (la page porte <meta name="sr-vu">) : comptée ici, envoyée par
     * le navigateur, car LSCache peut la servir sans passer par PHP. Même mesure anonyme que les autres.
     */
    private static function seen(Request $req): Response
    {
        $in = $req->json() ?: [];
        $path = (string) ($in['p'] ?? '');
        // Adresse du site seulement : « //hôte » ou « /\hôte » (un autre site pour le navigateur) refusés.
        // (Délimiteur « ~ » : avec « # », présent dans la classe de caractères, le motif était invalide.)
        if (!preg_match('~^/(?![/\\\\])[^\s?#\\\\]{0,200}$~', $path) || preg_match('#^/(en/)?(admin|api|w|ecran)(/|$)#', $path)
            || \App\Front\Seo::closed() || !\App\Core\RateLimiter::hit('vu', $req->ip(), 120, 60)) {
            return new Response('', 204);
        }
        $_SERVER['HTTP_REFERER'] = substr((string) ($in['r'] ?? ''), 0, 500);
        \App\Services\Stats::hit($path, (string) ($req->server['HTTP_USER_AGENT'] ?? ''), str_starts_with($path, '/en/') || $path === '/en' ? 'en' : 'fr');
        return new Response('', 204);
    }

    /** Preuve du consentement aux cookies (RGPD) : choix, date, empreinte anonymisée. */
    private static function consent(Request $req): Response
    {
        // Pas de limitation par adresse (un seul choix par nouveau visiteur ; au lancement, des
        // milliers à la fois) : le journal du mois est simplement plafonné en taille.
        $log = STORAGE_PATH . '/consent/' . date('Y-m') . '.jsonl';
        if (@filesize($log) > 200 * 1048576) {
            return Response::json(['ok' => true]);
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
        \App\Core\JsonStore::append($log, $line);
        return Response::json(['ok' => true]);
    }

    private static function salt(): string
    {
        $f = STORAGE_PATH . '/secret.key';
        return is_file($f) ? substr(hash('sha256', (string) file_get_contents($f)), 0, 16) : slugify(\App\Core\Club::s('site'));
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
