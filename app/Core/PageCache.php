<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Cache des pages du musée pour les visiteurs anonymes (Réglages › Général, « Cache des
 * pages »). Une page déjà calculée est resservie telle quelle en 1 ms environ au lieu de 15 à
 * 35 ms : au lancement, le même hébergement sert environ quatre fois plus de visiteurs.
 *
 * Jamais pour : l'équipe connectée ou tout visiteur qui a une session (formulaires, jeton
 * CSRF, messages, mot de passe d'accès), le site fermé, le site de l'association, l'API, le
 * back-office, la boutique, les dons, les formulaires, la recherche, le Rétro-Direct (heure du
 * serveur), les adresses avec des paramètres inconnus. Rien n'est gardé si la page a ouvert
 * une session, posé un cookie ou demandé à ne pas être gardée.
 *
 * Fraîcheur : une page est refaite dès qu'une fiche, un réglage, une rubrique, un média ou une
 * traduction change (empreinte de ces fichiers), et au plus tard au bout d'une minute (bandeau
 * « En direct », publications programmées, chiffre du jour) ; à minuit, rien de la veille.
 * Pendant qu'un visiteur la refait, les autres reçoivent la précédente.
 */
final class PageCache
{
    public const DIR = STORAGE_PATH . '/cache/pages';
    /** Âge au-delà duquel la page est refaite (le bandeau « En direct » suit à une minute près). */
    private const FRESH = 60;
    /** Au-delà, jamais servie, même pendant le recalcul. */
    private const MAX = 600;

    /** Pages jamais gardées (après « /en » éventuel). */
    private const SKIP = '#^/(admin|api|imprimeur|apercu-association|boutique|faire-un-don|contribuer|contact|newsletter|souvenir|carnet|recherche|interactif/retro-direct|video|media|pdf|partage|wp-)#';
    /** Paramètres que les pages publiques lisent (listes, filtres, pagination). */
    private const PARAMS = ['page', 'tri', 'vue', 'f', 'type', 'decennie', 'cat', 'comp', 'saison', 'poste', 'objet', 'motif', 'photographe', 'pp'];
    /** Paramètres de suivi publicitaire ignorés (même page que sans eux). */
    private const TRACKING = '#^(utm_\w+|fbclid|gclid|dclid|msclkid|mc_cid|mc_eid|_ga|_gl|igshid)$#';
    /** Pages tirées au hasard à chaque affichage : quelques versions gardées, une au hasard. */
    private const VARIANTS = '#^/(en/)?(|interactif/(planche-contact|le-lion-illustre|mur-du-vestiaire|mosaique)/)$#';

    private static ?string $key = null;

    public static function enabled(): bool
    {
        return (bool) Settings::get('general.page_cache', true);
    }

    /** Page gardée pour cette requête, ou null (à calculer normalement). */
    public static function serve(Request $req): ?Response
    {
        self::$key = null;
        $key = self::key($req);
        if ($key === null) {
            return null;
        }
        self::$key = $key;
        $file = self::path($key);
        $fp = @fopen($file, 'rb');
        if (!$fp) {
            return null;
        }
        $meta = json_decode((string) fgets($fp), true);
        $now = time();
        if (!is_array($meta) || ($meta['stamp'] ?? '') !== self::stamp() || $now - (int) $meta['at'] > self::MAX || date('Ymd', (int) $meta['at']) !== date('Ymd', $now)) {
            fclose($fp);
            return null;
        }
        if ($now - (int) $meta['at'] > self::FRESH) {
            // Périmée : un seul visiteur la refait, les autres reçoivent celle-ci.
            $lock = @fopen($file . '.lock', 'c');
            if ($lock && flock($lock, LOCK_EX | LOCK_NB)) {
                $GLOBALS['__page_cache_lock'] = $lock;
                fclose($fp);
                return null;
            }
            if ($lock) {
                fclose($lock);
            }
        }
        $body = (string) stream_get_contents($fp);
        fclose($fp);
        self::$key = null; // rien à réenregistrer
        if (str_starts_with($req->path, '/en/') || $req->path === '/en') {
            \App\Services\I18n::set('en'); // mesure d'audience par langue
        }
        $etag = '"' . $meta['etag'] . '"';
        $headers = (array) $meta['headers'] + ['ETag' => $etag, 'Cache-Control' => 'public, max-age=0, must-revalidate', 'X-Page-Cache' => 'HIT'];
        if (trim((string) ($req->server['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
            return new Response('', 304, array_intersect_key($headers, array_flip(['ETag', 'Cache-Control', 'X-Page-Cache'])));
        }
        return new Response($body, 200, $headers);
    }

    /** Garde la page calculée si elle peut l'être ; rend la réponse à envoyer. */
    public static function store(Request $req, Response $res): Response
    {
        $key = self::$key;
        self::$key = null;
        $lock = $GLOBALS['__page_cache_lock'] ?? null;
        unset($GLOBALS['__page_cache_lock']);
        try {
            if ($key === null || !self::storable($res)) {
                return $res;
            }
            $etag = substr(sha1($res->body), 0, 20);
            $headers = array_diff_key($res->headers, array_flip(['Set-Cookie', 'ETag', 'Cache-Control']));
            $meta = json_encode(['at' => time(), 'stamp' => self::stamp(), 'etag' => $etag, 'headers' => $headers], JSON_UNESCAPED_SLASHES);
            $file = self::path($key);
            if (!is_dir(dirname($file))) {
                @mkdir(dirname($file), 0775, true);
            }
            $tmp = $file . '.' . getmypid() . '.tmp';
            if (@file_put_contents($tmp, $meta . "\n" . $res->body) !== false) {
                @rename($tmp, $file);
            } else {
                @unlink($tmp);
            }
            $res->headers['ETag'] = '"' . $etag . '"';
            $res->headers['Cache-Control'] = 'public, max-age=0, must-revalidate';
            $res->headers['X-Page-Cache'] = 'MISS';
            if (random_int(1, 500) === 1) {
                self::prune();
            }
            return $res;
        } finally {
            if ($lock) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }

    /** Vide le cache (réglage, commande « pages-vider »). */
    public static function purge(): int
    {
        $n = 0;
        foreach (glob(self::DIR . '/*/*') ?: [] as $f) {
            $n += @unlink($f) ? 1 : 0;
        }
        return $n;
    }

    /** Clé de la requête, ou null si elle ne doit jamais passer par le cache. */
    private static function key(Request $req): ?string
    {
        if (!in_array($req->method, ['GET', 'HEAD'], true) || !self::enabled()) {
            return null;
        }
        // Toute session (équipe, formulaire, mot de passe d'accès) : la page lui est propre.
        if (isset($_COOKIE['sr_session']) || isset($req->server['HTTP_AUTHORIZATION'])) {
            return null;
        }
        $path = $req->path;
        if (!str_ends_with($path, '/') || preg_match(self::SKIP, (string) preg_replace('#^/en(?=/)#', '', $path))) {
            return null;
        }
        if (preg_match('#^/(en/)?interactif/fil-jaune/[^/]+/#', $path)) {
            return null; // paires de joueurs : trop de combinaisons
        }
        if (\App\Front\Seo::closed() || \App\Vitrine\Host::matches($req) || \App\Vitrine\Host::isPreview($path)) {
            return null;
        }
        $query = [];
        foreach ($req->query as $k => $v) {
            if (preg_match(self::TRACKING, (string) $k)) {
                continue;
            }
            if (!in_array($k, self::PARAMS, true) || !is_string($v) || !preg_match('/^[\p{L}\p{N} _.-]{0,40}$/u', $v)) {
                return null;
            }
            $query[$k] = $v;
        }
        if (count($query) > 4) {
            return null;
        }
        ksort($query);
        $variant = preg_match(self::VARIANTS, $path) ? random_int(0, 3) : 0;
        $host = strtolower((string) ($req->server['HTTP_HOST'] ?? ''));
        return sha1($host . '|' . $path . '|' . http_build_query($query) . '|' . $variant);
    }

    private static function storable(Response $res): bool
    {
        if ($res->status !== 200 || $res->file !== null || isset($res->headers['Cache-Control'])
            || !str_starts_with((string) ($res->headers['Content-Type'] ?? ''), 'text/html')
            || strlen($res->body) < 1024 || strlen($res->body) > 1048576
            || session_status() === PHP_SESSION_ACTIVE) {
            return false;
        }
        foreach (headers_list() as $h) {
            if (stripos($h, 'Set-Cookie:') === 0) {
                return false;
            }
        }
        return true;
    }

    /** Empreinte de ce qui change le contenu des pages : un enregistrement la change. */
    private static function stamp(): string
    {
        static $stamp = null;
        return $stamp ??= Memo::stamp([
            \App\Data\Index::CACHE,
            STORAGE_PATH . '/cache/derived.php',
            STORAGE_PATH . '/settings.json',
            DATA_PATH . '/categories.json',
            DATA_PATH . '/media.json',
            DATA_PATH . '/redirects.json',
            DATA_PATH . '/i18n/en.json',
            DATA_PATH . '/collections',
            DATA_PATH . '/fiches',
            PUBLIC_PATH . '/assets',
        ]);
    }

    private static function path(string $key): string
    {
        return self::DIR . '/' . substr($key, 0, 2) . '/' . $key . '.html';
    }

    /** Ménage : pages trop vieilles pour servir encore. */
    private static function prune(): void
    {
        foreach (glob(self::DIR . '/*/*') ?: [] as $f) {
            if (@filemtime($f) < time() - self::MAX - 60) {
                @unlink($f);
            }
        }
    }
}
