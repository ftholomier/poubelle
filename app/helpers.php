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

/**
 * URL d'une ressource statique, suffixée de sa date de modification.
 *
 * Le chemin était résolu sans le dossier « assets » : la version valait
 * toujours 1 et les navigateurs conservaient indéfiniment une ancienne
 * feuille de style après une mise en ligne.
 */
function asset(string $path): string
{
    $relatif = 'assets/' . ltrim($path, '/');
    $fichier = PUBLIC_DIR . '/' . $relatif;
    $version = is_file($fichier) ? (string) filemtime($fichier) : '1';
    return url($relatif) . '?v=' . $version;
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

/**
 * Rendu d'une vue dans un layout.
 *
 * Les variables internes portent un préfixe : sans lui, une clé nommée
 * « tpl » ou « layout » dans les données passées à la vue écraserait le
 * chemin du gabarit à inclure. EXTR_SKIP protège déjà des collisions,
 * mais l'ordre d'extraction rendait le code fragile à la relecture.
 */
function view(string $__tpl, array $__vars = [], string $__layout = 'layout'): string
{
    extract($__vars, EXTR_SKIP);
    ob_start();
    require VIEW_DIR . '/' . $__tpl . '.php';
    $content_for_layout = ob_get_clean();
    if ($__layout === '') {
        return (string) $content_for_layout;
    }
    ob_start();
    require VIEW_DIR . '/' . $__layout . '.php';
    return (string) ob_get_clean();
}

function partial(string $__name, array $__vars = []): void
{
    extract($__vars, EXTR_SKIP);
    require VIEW_DIR . '/partials/' . $__name . '.php';
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

/**
 * La requête courante est-elle chiffrée ?
 *
 * Derrière un répartiteur de charge, HTTPS n'apparaît que dans
 * X-Forwarded-Proto : sans cette lecture, l'en-tête HSTS ne serait
 * jamais émis.
 */
function is_https(): bool
{
    if (($_SERVER['HTTPS'] ?? '') !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        return true;
    }
    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
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

/**
 * Neutralise l'injection de formules dans un export CSV.
 *
 * Un tableur interprète une cellule commençant par =, +, - ou @ comme une
 * formule : une candidature nommée « =1+1 » ou, pire, contenant un appel
 * réseau, s'exécuterait à l'ouverture du fichier. L'apostrophe de tête
 * force le tableur à traiter la valeur comme du texte.
 */
function csv_safe(mixed $value): string
{
    $v = (string) $value;
    if ($v !== '' && str_contains("=+-@\t\r", $v[0])) {
        return "'" . $v;
    }
    return $v;
}

/**
 * Ramène un texte à la longueur maximale admise par les moteurs de
 * recherche, en coupant sur une frontière de mot plutôt qu'au milieu.
 *
 * Sans ce garde-fou, un extrait d'article se termine par un mot tronqué
 * dans la balise description, ce qui dégrade l'aperçu en résultat.
 */
function meta_trim(string $text, int $max): string
{
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    if ($text === '' || mb_strlen($text) <= $max) {
        return $text;
    }
    $cut = mb_substr($text, 0, $max - 1);
    $space = mb_strrpos($cut, ' ');
    if ($space !== false && $space > $max * 0.6) {
        $cut = mb_substr($cut, 0, $space);
    }
    return rtrim($cut, " ,;:.–—-") . '…';
}

/**
 * Jeton à usage unique autorisant les scripts en ligne de la page.
 *
 * La politique de sécurité du contenu interdit `unsafe-inline` pour les
 * scripts : les quelques blocs en ligne du site portent ce nonce, ce qui
 * neutralise l'injection d'un script tiers même en cas de faille XSS.
 */
function csp_nonce(): string
{
    static $nonce = null;
    if ($nonce === null) {
        $nonce = base64_encode(random_bytes(16));
    }
    return $nonce;
}
