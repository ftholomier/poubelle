<?php
/**
 * Outils communs aux scripts d'import WordPress.
 *
 * Configuration via variables d'environnement (jamais versionnées) :
 *   WP_URL       URL du site WordPress (défaut : https://www.fcsochauxretro.com)
 *   WP_PASSWORD  mot de passe de l'extension « Password Protected » (front)
 */

declare(strict_types=1);

const ROOT = __DIR__ . '/../..';
const IMPORT_DIR = ROOT . '/storage/import';

function wp_url(): string
{
    return rtrim(getenv('WP_URL') ?: 'https://www.fcsochauxretro.com', '/');
}

function out(string $msg): void
{
    fwrite(STDOUT, '[' . date('H:i:s') . "] $msg\n");
}

function ensure_dir(string $dir): void
{
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException("Impossible de créer $dir");
    }
}

/** Écriture atomique : fichier temporaire puis rename(). */
function write_file(string $path, string $data): void
{
    ensure_dir(dirname($path));
    $tmp = $path . '.tmp' . getmypid();
    file_put_contents($tmp, $data);
    rename($tmp, $path);
}

function write_json(string $path, mixed $data): void
{
    write_file($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function read_json(string $path): mixed
{
    return json_decode((string) file_get_contents($path), true);
}

function cookie_jar(): string
{
    ensure_dir(IMPORT_DIR);
    return IMPORT_DIR . '/cookies.txt';
}

/**
 * Requête HTTP avec cookie de session, reprises automatiques et
 * reconnexion si l'écran de mot de passe réapparaît.
 *
 * @return array{code:int, body:string, headers:array<string,string>, url:string}
 */
function http(string $url, array $opts = [], int $tries = 5): array
{
    $last = null;
    for ($i = 1; $i <= $tries; $i++) {
        $headers = [];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => $opts['timeout'] ?? 120,
            CURLOPT_COOKIEJAR => cookie_jar(),
            CURLOPT_COOKIEFILE => cookie_jar(),
            CURLOPT_USERAGENT => 'SochauxRetro-Import/1.0',
            CURLOPT_ENCODING => '',
            CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$headers) {
                $p = strpos($line, ':');
                if ($p !== false) {
                    $headers[strtolower(trim(substr($line, 0, $p)))] = trim(substr($line, $p + 1));
                }
                return strlen($line);
            },
        ] + ($opts['curl'] ?? []));
        if (isset($opts['post'])) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($opts['post']));
        }
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $eff = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $err = curl_error($ch);
        curl_close($ch);

        if ($body !== false && $code > 0 && $code < 500 && $code !== 429) {
            if (empty($opts['no_login_check']) && str_contains($eff, 'password-protected=login')) {
                login();
                continue;
            }
            return ['code' => $code, 'body' => (string) $body, 'headers' => $headers, 'url' => $eff];
        }
        $last = $err ?: "HTTP $code";
        sleep(min(60, 2 ** $i));
    }
    throw new RuntimeException("Échec $url : $last");
}

function login(): void
{
    $pwd = getenv('WP_PASSWORD');
    if ($pwd === false || $pwd === '') {
        throw new RuntimeException('Variable WP_PASSWORD manquante (mot de passe du front).');
    }
    $base = wp_url();
    http($base . '/?password-protected=login&redirect_to=' . rawurlencode($base . '/'), [
        'no_login_check' => true,
        'post' => [
            'password_protected_pwd' => $pwd,
            'password_protected_cookie_test' => '1',
            'password-protected' => 'login',
            'redirect_to' => $base . '/',
        ],
    ]);
    out('Connexion au front protégé effectuée');
}

/** Récupère toutes les pages d'une collection de l'API REST. */
function rest_all(string $route, array $query = []): array
{
    $items = [];
    $page = 1;
    do {
        $q = http_build_query($query + ['per_page' => 100, 'page' => $page]);
        $r = http(wp_url() . "/wp-json/wp/v2/$route?$q");
        $data = json_decode($r['body'], true);
        if (!is_array($data)) {
            throw new RuntimeException("Réponse invalide pour $route page $page");
        }
        $items = array_merge($items, $data);
        $total = (int) ($r['headers']['x-wp-totalpages'] ?? 1);
        out("$route : page $page/$total");
        $page++;
        usleep(300000);
    } while ($page <= $total);
    return $items;
}
