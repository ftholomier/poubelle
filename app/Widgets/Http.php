<?php
declare(strict_types=1);

namespace App\Widgets;

use App\Core\Auth;
use App\Core\Club;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Front\Seo;
use App\Services\I18n;

/**
 * Adresses publiques des widgets (appelées par App\Kernel avant la page d'attente) :
 * - GET /api/v1/widgets            catalogue des widgets accessibles (JSON) ;
 * - GET /api/v1/widgets/<clé>      données d'un widget (JSON, CORS, ETag) ;
 * - GET /w/<clé>                   le widget en page HTML autonome, à mettre dans une iframe ;
 * - GET /w/clic/<clé>/?to=/…       lien vers le musée depuis un widget (compté, puis redirigé).
 * Clé partenaire : ?key=… ou en-tête X-Musee-Key. Aucun cookie posé, aucune donnée personnelle.
 */
final class Http
{
    /** Requêtes par minute : par clé partenaire, et par adresse IP sans clé. */
    public const PER_KEY = 1200;
    public const PER_IP = 240;
    /** Fraîcheur dans le navigateur ou un cache intermédiaire, en secondes. */
    public const MAX_AGE_API = 300;
    public const MAX_AGE_FRAME = 120;

    public static function handle(Request $req): Response
    {
        $p = rtrim($req->path, '/');
        $api = str_starts_with($p, '/api/');
        if ($req->method === 'OPTIONS' && $api) {
            return self::cors(new Response('', 204, ['Access-Control-Allow-Methods' => 'GET, OPTIONS', 'Access-Control-Allow-Headers' => 'X-Musee-Key, If-None-Match', 'Access-Control-Max-Age' => '86400', 'Cache-Control' => 'public, max-age=86400']), $req, null);
        }
        if (!in_array($req->method, ['GET', 'HEAD'], true)) {
            return $api ? Response::json(['error' => 'Méthode non autorisée.'], 405) : Response::html('Méthode non autorisée.', 405);
        }
        $lang = $req->str('lang') === 'en' ? 'en' : 'fr';
        I18n::set($lang);
        try {
            if ($p === '/api/v1/widgets') {
                return self::catalog($req, $lang);
            }
            if (preg_match('#^/api/v1/widgets/([a-z0-9-]{1,40})$#', $p, $m)) {
                return self::serve($req, $m[1], $lang, true);
            }
            if (preg_match('#^/w/clic/([a-z0-9-]{1,40})$#', $p, $m)) {
                return self::click($req, $m[1]);
            }
            if (preg_match('#^/w/([a-z0-9-]{1,40})$#', $p, $m)) {
                return self::serve($req, $m[1], $lang, false);
            }
        } catch (\Throwable $e) {
            error_log((string) $e);
            return $api ? self::cors(Response::json(['error' => 'Erreur interne.'], 500), $req, null) : Response::html('Erreur interne.', 500);
        }
        return $api ? self::cors(Response::json(['error' => 'Widget inconnu.'], 404), $req, null) : self::notice(null, 'Widget inconnu.', 404, new Context('?', $lang));
    }

    // ------------------------------------------------------------------ accès

    /** L'équipe connectée au back-office (aperçu) : jamais de cookie posé pour les autres. */
    public static function preview(Request $req): bool
    {
        return isset($req->server['HTTP_COOKIE']) && str_contains((string) $req->server['HTTP_COOKIE'], 'sr_session') && Auth::user() !== null;
    }

    /** Clé partenaire envoyée (?key= ou en-tête X-Musee-Key). */
    public static function key(Request $req): string
    {
        $k = $req->query['key'] ?? $req->header('X-Musee-Key') ?? '';
        return is_string($k) ? trim($k) : '';
    }

    /** Hôte de la page qui affiche le widget (Origin, sinon Referer), en minuscules ; '' si inconnu. */
    public static function caller(Request $req): string
    {
        foreach ([$req->header('Origin'), $req->header('Referer')] as $h) {
            $host = $h && $h !== 'null' ? strtolower((string) parse_url($h, PHP_URL_HOST)) : '';
            if ($host !== '') {
                return (string) preg_replace('/^www\./', '', $host);
            }
        }
        return '';
    }

    /**
     * Décision d'accès à un widget : [code HTTP, message d'erreur ou null, partenaire ou null].
     * Ordre : widget désactivé, site fermé, clé (invalide, suspendue, domaine), accès réservé ou payant.
     * @return array{0:int,1:?string,2:?array}
     */
    public static function access(Widget $w, Request $req, bool $preview): array
    {
        $cfg = Store::widget($w->key());
        if ($cfg['access'] === 'desactive' && !$preview) {
            return [404, 'Ce widget n’est pas disponible.', null];
        }
        if (Seo::closed() && !$cfg['closed_ok'] && !$preview) {
            return [503, 'Le musée est fermé au public pour le moment.', null];
        }
        [$code, $error, $partner] = self::partner($req);
        if ($error !== null) {
            return [$code, $error, null];
        }
        if ($preview) {
            return [200, null, $partner];
        }
        if (in_array($cfg['access'], ['reserve', 'payant'], true) && !$partner) {
            return [401, 'Widget réservé aux partenaires du musée : clé requise.', null];
        }
        if ($cfg['access'] === 'payant' && !Store::paid($partner)) {
            return [402, 'Widget réservé aux partenaires ayant une offre payante en cours.', null];
        }
        return [200, null, $partner];
    }

    /**
     * Partenaire de la clé envoyée : [200, null, partenaire|null] ou [code, erreur, null] (clé
     * invalide, suspendue, ou page appelante hors des domaines autorisés de la clé).
     * @return array{0:int,1:?string,2:?array}
     */
    public static function partner(Request $req): array
    {
        $key = self::key($req);
        if ($key === '') {
            return [200, null, null];
        }
        $p = Store::partnerByKey($key);
        if (!$p) {
            return [401, 'Clé partenaire invalide.', null];
        }
        if (($p['status'] ?? '') !== 'actif') {
            return [403, 'Clé partenaire suspendue : contactez le musée.', null];
        }
        if (!empty($p['domains'])) {
            $host = self::caller($req);
            if ($host === '') {
                return [403, 'Clé limitée à des domaines : page d’origine inconnue (en-tête Origin ou Referer absent).', null];
            }
            if (!Store::hostAllowed($host, (array) $p['domains'])) {
                return [403, 'Clé non autorisée sur ce domaine (' . $host . ').', null];
            }
        }
        return [200, null, $p];
    }

    /** Débit : par clé partenaire, sinon par adresse IP. */
    private static function rate(Request $req, ?array $partner): bool
    {
        return $partner ? RateLimiter::hit('widgets-cle', (string) $partner['id'], self::PER_KEY, 60) : RateLimiter::hit('widgets-ip', $req->ip(), self::PER_IP, 60);
    }

    /** Pages qui peuvent afficher l'iframe : partout, ou les domaines de la clé (et le musée lui-même). */
    public static function ancestors(?array $partner): string
    {
        if (!$partner || empty($partner['domains'])) {
            return '*';
        }
        $out = ["'self'"];
        foreach ((array) $partner['domains'] as $d) {
            array_push($out, 'https://' . $d, 'https://*.' . $d, 'http://' . $d, 'http://*.' . $d);
        }
        return implode(' ', $out);
    }

    // ------------------------------------------------------------------ réponses

    private static function catalog(Request $req, string $lang): Response
    {
        [$code, $error, $partner] = self::partner($req);
        if ($error !== null) {
            return self::cors(Response::json(['error' => $error], $code), $req, null);
        }
        if (!self::rate($req, $partner)) {
            return self::cors(Response::json(['error' => 'Trop de requêtes, patientez une minute.'], 429), $req, $partner);
        }
        $closed = Seo::closed();
        $list = [];
        foreach (Registry::all() as $k => $w) {
            $cfg = Store::widget($k);
            $ok = match ($cfg['access']) {
                'libre' => true,
                'reserve' => $partner !== null,
                'payant' => $partner !== null && Store::paid($partner),
                default => false,
            };
            if ($ok && (!$closed || $cfg['closed_ok'])) {
                $q = ['lang' => $lang];
                $list[] = $w->describe($lang) + [
                    'access' => $cfg['access'],
                    'api' => Context::abs('/api/v1/widgets/' . $k . '?' . http_build_query($q)),
                    'iframe' => Context::abs('/w/' . $k . '?' . http_build_query($q + ['theme' => 'clair'])),
                ];
            }
        }
        $body = ['museum' => self::attribution(new Context('', $lang, 'clair', 'standard', (string) ($partner['id'] ?? ''))), 'widgets' => $list];
        return self::cached(Response::json($body), $req, $partner, md5(json_encode($body)), self::MAX_AGE_API);
    }

    /** Mention obligatoire, aussi dans l'API : nom du musée, « non officiel », lien et logo. */
    public static function attribution(Context $c): array
    {
        return [
            'name' => Club::s('site'),
            'mention' => Frame::mention($c),
            'disclaimer' => Club::disclaimer(),
            'url' => $c->link('/'),
            'logo' => Context::abs((string) (Club::s('logo_400') ?: Club::s('logo_png'))),
            'required' => $c->tr('Affichez le nom du musée, la mention « non officiel » et un lien cliquable vers le musée avec ces données.', 'Display the museum name, the “unofficial” mention and a clickable link to the museum with this data.'),
        ];
    }

    private static function serve(Request $req, string $key, string $lang, bool $api): Response
    {
        $w = Registry::get($key);
        $theme = in_array($req->str('theme'), ['sombre', 'dark'], true) ? 'sombre' : 'clair';
        $format = $req->str('format') === 'compact' ? 'compact' : 'standard';
        $preview = self::preview($req);
        if (!$w) {
            return $api ? self::cors(Response::json(['error' => 'Widget inconnu.'], 404), $req, null) : self::notice(null, 'Widget inconnu.', 404, new Context($key, $lang, $theme));
        }
        [$code, $error, $partner] = self::access($w, $req, $preview);
        $c = new Context($key, $lang, $theme, $format, (string) ($partner['id'] ?? ''), $preview);
        if ($error !== null) {
            return $api ? self::cors(Response::json(['error' => $error, 'widget' => $key], $code), $req, null) : self::notice($w, $error, $code, $c);
        }
        if (!$preview && !self::rate($req, $partner)) {
            return $api ? self::cors(Response::json(['error' => 'Trop de requêtes, patientez une minute.'], 429), $req, $partner) : self::notice($w, 'Trop de requêtes, patientez une minute.', 429, $c, $partner);
        }
        $opt = $w->clean($req->query);
        $hit = Cache::get($w, $opt, $c);
        $data = $hit['data'] === null ? null : Frame::finalize($hit['data'], $c);
        $etag = md5(json_encode([$key, $opt, $lang, $theme, $format, $c->partner, $hit['at'], $api, $preview]));
        $count = !$preview && $req->str('apercu') !== '1';
        if ($api) {
            $body = [
                'widget' => $key,
                'name' => $w->name($lang),
                'lang' => $lang,
                'options' => $opt,
                'generated' => date('c', $hit['at']),
                'data' => $data,
                'attribution' => self::attribution($c),
                'iframe' => Context::abs('/w/' . $key . '?' . http_build_query(array_filter($opt + ['lang' => $lang, 'theme' => $theme], fn ($v) => $v !== ''))),
            ];
            $res = self::cached(Response::json($body), $req, $partner, $etag, self::MAX_AGE_API);
            if ($count) {
                Store::hit('api', $key, $c->partner ?: 'libre');
            }
            return $res;
        }
        $res = self::frameHeaders(Response::html(Frame::page($w, $data, $c)), $partner, $preview);
        $res = $preview ? $res : self::cached($res, $req, $partner, $etag, self::MAX_AGE_FRAME);
        if ($count) {
            Store::hit('vues', $key, $c->partner ?: 'libre');
        }
        return $res;
    }

    /** Message à la place du widget (accès refusé…), dans le même cadre aux couleurs du musée. */
    private static function notice(?Widget $w, string $msg, int $code, Context $c, ?array $partner = null): Response
    {
        $w ??= Registry::get('ce-jour-la');
        $res = self::frameHeaders(Response::html(Frame::page($w, null, $c, $msg), $code), $partner, false);
        $res->headers['Cache-Control'] = 'no-store';
        return $res;
    }

    private static function frameHeaders(Response $res, ?array $partner, bool $preview): Response
    {
        $res->headers['Content-Security-Policy'] = "default-src 'none'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; font-src 'self'; script-src 'self'; base-uri 'none'; form-action 'none'; frame-ancestors " . ($preview ? "'self'" : self::ancestors($partner));
        $res->headers['X-Content-Type-Options'] = 'nosniff';
        $res->headers['Referrer-Policy'] = 'strict-origin-when-cross-origin';
        $res->headers['X-Robots-Tag'] = 'noindex';
        $res->headers['Cross-Origin-Resource-Policy'] = 'cross-origin';
        return $res;
    }

    /** En-têtes de cache (public sans clé, privé avec une clé limitée à des domaines), ETag et 304. */
    private static function cached(Response $res, Request $req, ?array $partner, string $etag, int $maxAge): Response
    {
        $etag = '"' . $etag . '"';
        $scope = $partner && !empty($partner['domains']) ? 'private' : 'public';
        $res->headers['Cache-Control'] = $scope . ', max-age=' . $maxAge;
        $res->headers['ETag'] = $etag;
        $res->headers['Vary'] = 'Origin, X-Musee-Key';
        $res = self::cors($res, $req, $partner);
        $inm = (string) ($req->header('If-None-Match') ?? '');
        if ($inm !== '' && in_array($etag, array_map('trim', explode(',', $inm)), true)) {
            $res->status = 304;
            $res->body = '';
        }
        return $res;
    }

    /** CORS : ouvert à tous sans clé ; avec une clé limitée, seulement l'origine autorisée. */
    private static function cors(Response $res, Request $req, ?array $partner): Response
    {
        if (!str_starts_with($req->path, '/api/')) {
            return $res;
        }
        $origin = (string) ($req->header('Origin') ?? '');
        if ($partner && !empty($partner['domains'])) {
            if ($origin !== '') {
                $res->headers['Access-Control-Allow-Origin'] = $origin;
            }
        } else {
            $res->headers['Access-Control-Allow-Origin'] = '*';
        }
        $res->headers['Access-Control-Expose-Headers'] = 'ETag';
        $res->headers['X-Content-Type-Options'] = 'nosniff';
        return $res;
    }

    /** Lien d'un widget vers le musée : compté (sans rien de personnel), puis redirection vers une page du musée. */
    private static function click(Request $req, string $key): Response
    {
        $to = $req->str('to', '/');
        if (!preg_match('#^/(?!/)[^\s\\\\]*$#', $to)) {
            $to = '/';
        }
        $pid = $req->str('p');
        $pid = preg_match('/^p[a-f0-9]{8}$/', $pid) && Store::partner($pid) ? $pid : '';
        if (Registry::get($key) && RateLimiter::hit('widgets-clic', $req->ip(), 30, 60)) {
            Store::hit('clics', $key, $pid ?: 'libre');
        }
        [$path, $frag] = array_pad(explode('#', $to, 2), 2, null);
        $sep = str_contains($path, '?') ? '&' : '?';
        $dest = $path . $sep . http_build_query(['utm_source' => 'widget', 'utm_medium' => $key] + ($pid ? ['utm_campaign' => $pid] : [])) . ($frag !== null ? '#' . $frag : '');
        $res = Response::redirect($dest, 302);
        $res->headers['Cache-Control'] = 'no-store';
        $res->headers['Referrer-Policy'] = 'no-referrer';
        return $res;
    }
}
