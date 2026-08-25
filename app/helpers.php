<?php
declare(strict_types=1);

/** Échappement HTML systématique. */
function e(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Accès pointé dans un tableau : arr($content, 'hero.title', 'défaut') */
function arr(array $data, string $path, mixed $default = null): mixed
{
    $cur = $data;
    foreach (explode('.', $path) as $seg) {
        if (is_array($cur) && array_key_exists($seg, $cur)) {
            $cur = $cur[$seg];
        } else {
            return $default;
        }
    }
    return $cur;
}

function content(?string $path = null, mixed $default = null): mixed
{
    static $c = null;
    if ($c === null) {
        $c = Store::read('content');
    }
    return $path === null ? $c : arr($c, $path, $default);
}

function settings(?string $path = null, mixed $default = null): mixed
{
    static $s = null;
    if ($s === null) {
        $s = Store::read('settings');
    }
    return $path === null ? $s : arr($s, $path, $default);
}

function url(string $path = '/'): string
{
    $base = rtrim((string) settings('site.base_path', ''), '/');
    return $base . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = PUBLIC_DIR . '/' . ltrim($path, '/');
    $v = is_file($file) ? (string) filemtime($file) : '1';
    return url('assets/' . ltrim($path, '/')) . '?v=' . $v;
}

function slugify(string $text): string
{
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    $text = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $text) ?? '');
    return trim($text, '-') ?: 'article';
}

function fr_date(?string $iso, bool $withTime = false): string
{
    if (!$iso) { return ''; }
    $ts = strtotime($iso);
    if ($ts === false) { return ''; }
    $mois = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $out = date('j', $ts) . ' ' . $mois[(int) date('n', $ts)] . ' ' . date('Y', $ts);
    return $withTime ? $out . ' à ' . date('H\hi', $ts) : $out;
}

function excerpt(string $html, int $len = 160): string
{
    $t = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    if (mb_strlen($t) <= $len) { return $t; }
    return mb_substr($t, 0, $len) . '…';
}

/** Rendu d'une vue dans un layout. */
function view(string $tpl, array $vars = [], string $layout = 'layout'): string
{
    extract($vars, EXTR_SKIP);
    ob_start();
    require VIEW_DIR . '/' . $tpl . '.php';
    $content_for_layout = ob_get_clean();
    if ($layout === '') {
        return (string) $content_for_layout;
    }
    ob_start();
    require VIEW_DIR . '/' . $layout . '.php';
    return (string) ob_get_clean();
}

function partial(string $name, array $vars = []): void
{
    extract($vars, EXTR_SKIP);
    require VIEW_DIR . '/partials/' . $name . '.php';
}

function json_out(mixed $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function redirect(string $to): never
{
    header('Location: ' . $to, true, 302);
    exit;
}

/** Corps JSON d'une requête API, sinon $_POST. */
function request_payload(): array
{
    $ctype = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($ctype, 'application/json')) {
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
    return $_POST;
}

function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/** Empreinte anonyme (RGPD-friendly) d'un visiteur pour la déduplication. */
function visitor_hash(): string
{
    return substr(hash('sha256', client_ip() . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . (settings('security.salt') ?? 'suisse-immo')), 0, 16);
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function old(string $key, mixed $default = ''): string
{
    return e($_POST[$key] ?? $default);
}

function nb(mixed $v): string
{
    return number_format((float) $v, 0, ',', ' ');
}

function euro(mixed $v): string
{
    return nb($v) . ' €';
}
