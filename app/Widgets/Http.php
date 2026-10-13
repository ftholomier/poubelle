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
 *   (?categorie=<clé> : une catégorie seulement, voir Registry::CATEGORIES) ;
 * - GET /api/v1/widgets/<clé>      données d'un widget (JSON, CORS, ETag) ;
 * - GET /api/v1/widgets/<clé>/suggestions/<option>?q=…   choix d'une option filtrés (JSON :
 *                                  [{value, label}], 20 au plus ; mêmes règles d'accès que le widget) ;
 * - GET /w/<clé>                   le widget en page HTML autonome, à mettre dans une iframe ;
 * - GET /w/clic/<clé>/?to=/…       lien vers le musée depuis un widget (compté, puis redirigé) ;
 * - POST /api/v1/widgets/quiz-du-jour/verifier   vérifie une réponse du quiz du jour (la bonne
 *                                  réponse n'est jamais dans les données du widget) ;
 * - GET /embed.js                  script d'intégration (une iframe isolée par <div data-musee-widget>) ;
 * - GET /ecran/<playlist>          mode écran plein écran (accès du widget « playlist ») ;
 * - GET /ecran/sw.js               service worker du mode écran (dernière version gardée).
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
        if ($p === '/embed.js') {
            return self::embed($req);
        }
        if ($p === '/ecran/sw.js') {
            return self::screenWorker();
        }
        $api = str_starts_with($p, '/api/');
        if ($req->method === 'OPTIONS' && $api) {
            return self::cors(new Response('', 204, ['Access-Control-Allow-Methods' => 'GET, POST, OPTIONS', 'Access-Control-Allow-Headers' => 'X-Musee-Key, If-None-Match, Content-Type', 'Access-Control-Max-Age' => '86400', 'Cache-Control' => 'public, max-age=86400']), $req, null);
        }
        if ($req->method === 'POST' && $p === '/api/v1/widgets/quiz-du-jour/verifier') {
            I18n::set(self::input($req, 'lang') === 'en' ? 'en' : 'fr');
            try {
                return self::verify($req);
            } catch (\Throwable $e) {
                error_log((string) $e);
                return self::cors(Response::json(['error' => 'Erreur interne.'], 500), $req, null);
            }
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
            if (preg_match('#^/api/v1/widgets/([a-z0-9-]{1,40})/suggestions/([a-z0-9_-]{1,40})$#', $p, $m)) {
                return self::suggestions($req, $m[1], $m[2]);
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
            if (preg_match('#^/ecran/([a-z0-9-]{1,40})$#', $p, $m)) {
                return self::screen($req, $m[1]);
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
    public static function access(Widget $w, Request $req, bool $preview, bool $domains = true): array
    {
        $cfg = Store::widget($w->key());
        if ($cfg['access'] === 'desactive' && !$preview) {
            return [404, 'Ce widget n’est pas disponible.', null];
        }
        // Le mode écran (réservé, avec clé partenaire) reste visible même quand le musée est fermé.
        if (Seo::closed() && !$cfg['closed_ok'] && !$preview && $w->key() !== 'playlist') {
            return [503, 'Le musée est fermé au public pour le moment.', null];
        }
        [$code, $error, $partner] = self::partner($req, $domains);
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
    public static function partner(Request $req, bool $domains = true): array
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
        if ($domains && !empty($p['domains'])) {
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
        $cat = $req->str('categorie');
        $cat = isset(Registry::CATEGORIES[$cat]) ? $cat : '';
        $list = [];
        foreach (Registry::all() as $k => $w) {
            $cfg = Store::widget($k);
            $ok = match ($cfg['access']) {
                'libre' => true,
                'reserve' => $partner !== null,
                'payant' => $partner !== null && Store::paid($partner),
                default => false,
            };
            if ($ok && (!$closed || $cfg['closed_ok']) && ($cat === '' || in_array($cat, $w->category(), true))) {
                $q = ['lang' => $lang];
                $list[] = $w->describe($lang) + [
                    'access' => $cfg['access'],
                    'api' => Context::abs('/api/v1/widgets/' . $k . '?' . http_build_query($q)),
                    'iframe' => Context::abs('/w/' . $k . '?' . http_build_query($q + ['theme' => 'clair'])),
                ];
            }
        }
        $cats = [];
        foreach (Registry::CATEGORIES as $k => $l) {
            $cats[] = ['key' => $k, 'label' => Registry::categoryLabel($k, $lang)];
        }
        $body = ['museum' => self::attribution(new Context('', $lang, 'clair', 'standard', (string) ($partner['id'] ?? ''))), 'categories' => $cats, 'widgets' => $list];
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
                'iframe' => Context::abs('/w/' . $key . '?' . http_build_query(array_filter($opt + ['lang' => $lang, 'theme' => $theme], fn ($v) => $v !== '' && $v !== 'auto'))),
            ];
            $res = self::cached(Response::json($body), $req, $partner, $etag, self::MAX_AGE_API);
            if ($count) {
                Store::hit('api', $key, $c->partner ?: 'libre');
            }
            return $res;
        }
        $res = self::frameHeaders(Response::html(Frame::page($w, $data, $c)), $partner, $preview, $key === 'recherche');
        $res = $preview ? $res : self::cached($res, $req, $partner, $etag, self::MAX_AGE_FRAME);
        if ($count) {
            Store::hit('vues', $key, $c->partner ?: 'libre');
        }
        return $res;
    }

    /** Suggestions pour une option (configurateurs, sites partenaires) : mêmes règles d'accès que le widget. */
    private static function suggestions(Request $req, string $key, string $name): Response
    {
        $w = Registry::get($key);
        $o = $w?->options()[$name] ?? null;
        if (!$w || !$o || !in_array($o['type'] ?? 'select', ['select', 'search'], true)) {
            return self::cors(Response::json(['error' => 'Option inconnue.'], 404), $req, null);
        }
        $preview = self::preview($req);
        [$code, $error, $partner] = self::access($w, $req, $preview);
        if ($error !== null) {
            return self::cors(Response::json(['error' => $error, 'widget' => $key], $code), $req, null);
        }
        if (!$preview && !self::rate($req, $partner)) {
            return self::cors(Response::json(['error' => 'Trop de requêtes, patientez une minute.'], 429), $req, $partner);
        }
        $q = $req->str('q');
        $body = ['widget' => $key, 'option' => $name, 'q' => mb_substr($q, 0, 60), 'items' => $w->suggest($name, $q)];
        return self::cached(Response::json($body), $req, $partner, md5(json_encode($body)), self::MAX_AGE_API);
    }

    /** Message à la place du widget (accès refusé…), dans le même cadre aux couleurs du musée. */
    private static function notice(?Widget $w, string $msg, int $code, Context $c, ?array $partner = null): Response
    {
        $w ??= Registry::get('ce-jour-la');
        $res = self::frameHeaders(Response::html(Frame::page($w, null, $c, $msg), $code), $partner, false);
        $res->headers['Cache-Control'] = 'no-store';
        return $res;
    }

    /** $form : le widget envoie un formulaire au musée (recherche : form-action 'self'). */
    private static function frameHeaders(Response $res, ?array $partner, bool $preview, bool $form = false): Response
    {
        $res->headers['Content-Security-Policy'] = "default-src 'none'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; font-src 'self'; script-src 'self'; connect-src 'self'; base-uri 'none'; form-action " . ($form ? "'self'" : "'none'") . '; frame-ancestors ' . ($preview ? "'self'" : self::ancestors($partner));
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

    /** Champ d'une requête POST (JSON ou formulaire), sinon de l'adresse. */
    private static function input(Request $req, string $name): string
    {
        $json = is_array($j = json_decode((string) $req->body, true)) ? $j : [];
        $v = $json[$name] ?? $req->post[$name] ?? $req->query[$name] ?? '';
        return is_scalar($v) ? trim((string) $v) : '';
    }

    /**
     * Vérifie une réponse du quiz du jour : {date, choix, lang, key?} → {correct, bonne, reponse,
     * explication}. Mêmes règles d'accès que le widget ; la page du widget elle-même (servie par le
     * musée, donc d'origine le musée) passe le contrôle des domaines de la clé. Rien n'est enregistré.
     */
    private static function verify(Request $req): Response
    {
        $w = Registry::get('quiz-du-jour');
        $cfg = Store::widget('quiz-du-jour');
        if (!$w || ($cfg['access'] === 'desactive' && !self::preview($req))) {
            return self::cors(Response::json(['error' => 'Ce widget n’est pas disponible.'], 404), $req, null);
        }
        if (Seo::closed() && !$cfg['closed_ok'] && !self::preview($req)) {
            return self::cors(Response::json(['error' => 'Le musée est fermé au public pour le moment.'], 503), $req, null);
        }
        $key = self::input($req, 'key') ?: (string) ($req->header('X-Musee-Key') ?? '');
        $partner = null;
        if ($key !== '') {
            $partner = Store::partnerByKey($key);
            if (!$partner || ($partner['status'] ?? '') !== 'actif') {
                return self::cors(Response::json(['error' => 'Clé partenaire invalide ou suspendue.'], 401), $req, null);
            }
            $host = self::caller($req);
            if (!empty($partner['domains']) && $host !== Context::host() && !Store::hostAllowed($host, (array) $partner['domains'])) {
                return self::cors(Response::json(['error' => 'Clé non autorisée sur ce domaine.'], 403), $req, null);
            }
        }
        if (!self::preview($req)) {
            if (in_array($cfg['access'], ['reserve', 'payant'], true) && !$partner) {
                return self::cors(Response::json(['error' => 'Widget réservé aux partenaires du musée : clé requise.'], 401), $req, null);
            }
            if ($cfg['access'] === 'payant' && !Store::paid($partner)) {
                return self::cors(Response::json(['error' => 'Widget réservé aux partenaires ayant une offre payante en cours.'], 402), $req, null);
            }
        }
        if (!RateLimiter::hit('widgets-quiz', $req->ip(), 60, 60)) {
            return self::cors(Response::json(['error' => 'Trop de requêtes, patientez une minute.'], 429), $req, null);
        }
        $date = self::input($req, 'date');
        $choice = self::input($req, 'choix');
        // Question du jour, ou de la veille (page ouverte avant minuit) : pas d'autre date.
        if (!in_array($date, [date('Y-m-d'), date('Y-m-d', strtotime('-1 day'))], true) || !ctype_digit($choice)) {
            return self::cors(Response::json(['error' => 'Question inconnue ou expirée : rechargez le widget.'], 400), $req, null);
        }
        $q = Items\QuizDuJour::question($date, I18n::lang());
        if (!$q || !isset($q['a'][(int) $choice])) {
            return self::cors(Response::json(['error' => 'Question inconnue ou expirée : rechargez le widget.'], 400), $req, null);
        }
        $res = Response::json(['correct' => (int) $choice === $q['c'], 'bonne' => $q['c'], 'reponse' => $q['a'][$q['c']], 'explication' => $q['fact']]);
        $res->headers['Cache-Control'] = 'no-store';
        return self::cors($res, $req, $partner);
    }

    // ------------------------------------------------------------------ mode écran

    /**
     * Mode écran : /ecran/<playlist>?key=…&format=paysage|vertical&lang=fr|en&adversaire=<club>&horloge=1|0.
     * Accès : celui du widget « playlist » (réservé par défaut : clé partenaire dans l'adresse) ; les
     * domaines d'une clé ne s'appliquent pas (un écran ouvre la page directement, sans page
     * d'origine). L'équipe connectée voit l'aperçu quel que soit l'accès.
     */
    private static function screen(Request $req, string $id): Response
    {
        $w = Registry::get('playlist');
        $p = Screen\Playlists::get($id);
        $lang = in_array($req->str('lang'), ['fr', 'en'], true) ? $req->str('lang') : (string) ($p['lang'] ?? 'fr');
        I18n::set($lang);
        $format = in_array($req->str('format'), ['paysage', 'vertical'], true) ? $req->str('format') : (string) ($p['format'] ?? 'paysage');
        $preview = self::preview($req);
        [$code, $error, $partner] = self::access($w, $req, $preview, false);
        $c = new Context('playlist', $lang, 'sombre', 'ecran', (string) ($partner['id'] ?? ''), $preview);
        if ($error === null && !$p) {
            [$code, $error] = [404, $c->tr('Playlist inconnue.', 'Unknown playlist.')];
        }
        if ($error === null && !$preview && !self::rate($req, $partner)) {
            [$code, $error] = [429, 'Trop de requêtes, patientez une minute.'];
        }
        if ($error !== null) {
            $res = self::screenHeaders(Response::html(Screen\Page::notice($error, $c, $format), $code), $partner);
            $res->headers['Cache-Control'] = 'no-store';
            return $res;
        }
        $adv = Screen\Playlists::slug($req->str('adversaire'));
        $clock = $req->str('horloge') !== '' ? $req->str('horloge') === '1' : !empty($p['clock']);
        $slides = Screen\Slides::build($p, $c, $adv);
        $html = Screen\Page::render($slides, $c, ['format' => $format, 'clock' => $clock, 'reload' => (int) $p['reload'], 'name' => (string) $p['name'], 'id' => $id]);
        $res = self::screenHeaders(Response::html($html), $partner);
        $res->headers['Cache-Control'] = 'private, no-cache';
        if (!$preview && $req->str('apercu') !== '1') {
            Store::hit('vues', 'playlist', $c->partner ?: 'libre');
        }
        return $res;
    }

    private static function screenHeaders(Response $res, ?array $partner): Response
    {
        $res->headers['Content-Security-Policy'] = "default-src 'none'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; font-src 'self'; script-src 'self'; connect-src 'self'; worker-src 'self'; base-uri 'none'; form-action 'none'; frame-ancestors " . self::ancestors($partner);
        $res->headers['X-Content-Type-Options'] = 'nosniff';
        $res->headers['Referrer-Policy'] = 'no-referrer';
        $res->headers['X-Robots-Tag'] = 'noindex';
        return $res;
    }

    /** Service worker du mode écran (portée /ecran/). */
    private static function screenWorker(): Response
    {
        $body = (string) @file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/ecran-sw.js');
        return new Response($body, $body === '' ? 404 : 200, ['Content-Type' => 'application/javascript; charset=UTF-8', 'Cache-Control' => 'no-cache', 'X-Content-Type-Options' => 'nosniff']);
    }

    /**
     * Script d'intégration (/embed.js, aussi /assets/js/embed.js) : adresse fixe, gardé une heure,
     * chargeable depuis tout site (aucun cookie, aucune donnée).
     */
    private static function embed(Request $req): Response
    {
        $file = dirname(__DIR__, 2) . '/public/assets/js/embed.js';
        $body = (string) @file_get_contents($file);
        $res = new Response($body, $body === '' ? 404 : 200, ['Content-Type' => 'application/javascript; charset=UTF-8']);
        $res->headers['Cache-Control'] = 'public, max-age=3600';
        $res->headers['Access-Control-Allow-Origin'] = '*';
        $res->headers['Cross-Origin-Resource-Policy'] = 'cross-origin';
        $res->headers['X-Content-Type-Options'] = 'nosniff';
        $etag = '"' . md5($body) . '"';
        $res->headers['ETag'] = $etag;
        if (trim((string) ($req->header('If-None-Match') ?? '')) === $etag) {
            $res->status = 304;
            $res->body = '';
        }
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
