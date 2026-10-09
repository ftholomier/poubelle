<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Protection contre l'aspiration du site : robots d'IA et aspirateurs refusés par leur nom,
 * faux Google et faux Bing démasqués (DNS inverse), et débit limité par visiteur. Les vrais
 * moteurs de recherche, les aperçus de partage (Facebook, WhatsApp…) et l'équipe connectée
 * passent toujours. robots.txt reste lisible par tous (c'est là qu'on leur dit non).
 */
final class Shield
{
    /** Robots d'IA, aspirateurs de sites et outils de script : refusés partout. */
    public const BLOCKED = [
        'GPTBot', 'ChatGPT-User', 'OAI-SearchBot', 'ClaudeBot', 'Claude-Web', 'Claude-User', 'Claude-SearchBot', 'anthropic-ai',
        'CCBot', 'Bytespider', 'PerplexityBot', 'Perplexity-User', 'Amazonbot', 'meta-externalagent', 'meta-externalfetcher', 'FacebookBot',
        'cohere-ai', 'cohere-training-data-crawler', 'Diffbot', 'ImagesiftBot', 'Omgilibot', 'Omgili', 'YouBot', 'Timpibot', 'AI2Bot', 'Ai2Bot-Dolma',
        'PanguBot', 'Kangaroo Bot', 'Scrapy', 'img2dataset', 'DuckAssistBot', 'MistralAI-User', 'Novellum', 'Brightbot', 'iaskspider',
        'AhrefsBot', 'SemrushBot', 'MJ12bot', 'DotBot', 'PetalBot', 'DataForSeoBot', 'BLEXBot', 'serpstatbot', 'MegaIndex', 'SeekportBot',
        'HTTrack', 'Wget', 'curl', 'python-requests', 'python-urllib', 'python-httpx', 'aiohttp', 'Go-http-client', 'Java/', 'libwww-perl',
        'okhttp', 'node-fetch', 'axios', 'undici', 'Apache-HttpClient', 'HeadlessChrome', 'PhantomJS', 'colly', 'SiteSucker', 'WebCopier',
        'Offline Explorer', 'Teleport', 'WebZIP', 'WebStripper', 'Website Downloader', 'Zeus', 'grab-site', 'ArchiveBot', 'webscraper',
    ];

    /** Robots acceptés en robots.txt et ici (référencement, aperçus de partage). */
    public const VERIFIED = ['Googlebot' => ['.googlebot.com', '.google.com'], 'Google-InspectionTool' => ['.googlebot.com', '.google.com'], 'bingbot' => ['.search.msn.com']];

    /** Pages : par minute et par heure. Images fabriquées à la volée : par minute. */
    public const PAGES_MIN = 180;
    public const PAGES_HOUR = 2000;
    public const MEDIA_MIN = 400;
    public const FULL_MIN = 60;

    private const LOG = STORAGE_PATH . '/stats/blocages.json';
    private const DNS = STORAGE_PATH . '/ratelimit/robots-verifies.json';

    public static function check(Request $req): ?Response
    {
        $path = $req->path;
        if ($path === '/robots.txt' || $path === '/admin' || str_starts_with($path, '/admin/')) {
            return null;
        }
        // Paiements, notifications de l'appli, désinscription en un clic des messageries : appels de services, jamais filtrés.
        if (preg_match('#^/api/(dons/(stripe|paypal)/webhook|push/)|^/boutique/.*webhook|^(/en)?/newsletter/(desinscription|o|c)/#', $path)) {
            return null;
        }
        $ip = $req->ip();
        if (in_array($ip, ['127.0.0.1', '::1', (string) ($req->server['SERVER_ADDR'] ?? '-')], true)) {
            return null;
        }
        $ua = trim((string) $req->header('User-Agent'));
        if ($ua === '' || self::blocked($ua)) {
            self::log('robot');
            return self::deny(403);
        }
        foreach (self::VERIFIED as $name => $domains) {
            if (stripos($ua, $name) !== false) {
                if (self::verified($ip, $domains)) {
                    return null;
                }
                self::log('faux-moteur');
                return self::deny(403);
            }
        }
        if (isset($req->server['HTTP_COOKIE']) && str_contains((string) $req->server['HTTP_COOKIE'], 'sr_session') && Auth::user()) {
            return null;
        }
        $media = str_starts_with($path, '/media/');
        if (str_starts_with($path, '/media/full/')) {
            // Grand format : pas d'affichage sur d'autres sites, et débit plus serré.
            $ref = strtolower((string) parse_url((string) $req->header('Referer'), PHP_URL_HOST));
            $host = strtolower((string) ($req->server['HTTP_HOST'] ?? ''));
            if ($ref !== '' && $ref !== $host && !str_ends_with($ref, 'fcsochauxretro.com') && !in_array($ref, ['localhost', '127.0.0.1'], true)) {
                self::log('photo-ailleurs');
                return self::deny(403);
            }
            if (!RateLimiter::hit('aspi-full', $ip, self::FULL_MIN, 60)) {
                self::log('trop-rapide');
                return self::deny(429);
            }
        }
        if ($media) {
            $ok = RateLimiter::hit('aspi-media', $ip, self::MEDIA_MIN, 60);
        } elseif (str_starts_with($path, '/api/')) {
            $ok = true; // l'API a ses propres limites
        } else {
            $ok = RateLimiter::hit('aspi-pages', $ip, self::PAGES_MIN, 60) && RateLimiter::hit('aspi-heure', $ip, self::PAGES_HOUR, 3600);
        }
        if (!$ok) {
            self::log('trop-rapide');
            return self::deny(429);
        }
        return null;
    }

    public static function blocked(string $ua): bool
    {
        static $re = null;
        $re ??= '~' . implode('|', array_map(fn ($s) => preg_quote($s, '~'), self::BLOCKED)) . '~i';
        return (bool) preg_match($re, $ua);
    }

    /** Vrai Google ou Bing : le DNS inverse de l'adresse finit par leur domaine et pointe vers la même adresse (gardé un jour). */
    private static function verified(string $ip, array $domains): bool
    {
        $cache = (array) JsonStore::read(self::DNS, []);
        if (isset($cache[$ip]) && $cache[$ip][1] > time() - 86400) {
            return (bool) $cache[$ip][0];
        }
        $host = @gethostbyaddr($ip);
        $ok = false;
        if (is_string($host) && $host !== $ip) {
            foreach ($domains as $d) {
                if (str_ends_with(strtolower($host), $d)) {
                    $ok = in_array($ip, (array) @gethostbynamel($host), true) || str_contains($ip, ':');
                    break;
                }
            }
        }
        JsonStore::update(self::DNS, function ($c) use ($ip, $ok) {
            $c = is_array($c) ? $c : [];
            $c[$ip] = [$ok, time()];
            if (count($c) > 5000) {
                $c = array_slice($c, -4000, null, true);
            }
            return $c;
        }, []);
        return $ok;
    }

    /** Blocages du jour, par motif (robot, faux-moteur, trop-rapide), gardés 90 jours. */
    private static function log(string $why): void
    {
        $day = date('Y-m-d');
        JsonStore::update(self::LOG, function ($c) use ($day, $why) {
            $c = is_array($c) ? $c : [];
            $c[$day][$why] = ($c[$day][$why] ?? 0) + 1;
            ksort($c);
            return array_slice($c, -90, null, true);
        }, []);
    }

    /** @return array<string,array<string,int>> jour => motif => nombre */
    public static function stats(): array
    {
        return (array) JsonStore::read(self::LOG, []);
    }

    private static function deny(int $code): Response
    {
        $msg = $code === 429
            ? 'Trop de pages demandées en peu de temps. Merci de patienter quelques minutes avant de continuer la visite.'
            : 'Accès refusé aux robots et aux outils d’aspiration. Les contenus du musée ne peuvent pas être copiés en masse ni servir à entraîner une intelligence artificielle.';
        $html = '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><meta name="robots" content="noindex"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Sochaux Rétro</title></head>'
            . '<body style="font-family:Georgia,serif;background:#F3EDDF;color:#0E1F4D;display:grid;place-items:center;min-height:90vh;margin:0;padding:20px"><div style="max-width:520px;text-align:center"><h1 style="font-family:Impact,sans-serif;letter-spacing:.04em">SOCHAUX RÉTRO</h1><p>' . $msg . '</p></div></body></html>';
        $h = ['Content-Type' => 'text/html; charset=UTF-8', 'Cache-Control' => 'no-store', 'X-Robots-Tag' => 'noindex'];
        if ($code === 429) {
            $h['Retry-After'] = '300';
        }
        return new Response($html, $code, $h);
    }
}
