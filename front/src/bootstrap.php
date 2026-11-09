<?php

declare(strict_types=1);

/*
 * Amorçage commun : configuration, chargement des classes, fonctions utiles, en-têtes de sécurité.
 * Tout le code vit hors du dossier public/ ; seul public/index.php l'inclut.
 */

const APP_ROOT = __DIR__ . '/..';
const APP_VERSION_FILE = APP_ROOT . '/storage/update/etat.json';

date_default_timezone_set('Europe/Paris');
mb_internal_encoding('UTF-8');

spl_autoload_register(static function (string $class): void {
    $f = __DIR__ . '/' . str_replace('\\', '/', $class) . '.php';
    if (is_file($f)) {
        require $f;
    }
});

function cfg(string $key, $default = null)
{
    static $c = null;
    if ($c === null) {
        $c = require APP_ROOT . '/config/config.php';
    }
    return $c[$key] ?? $default;
}

/** Échappement HTML. */
function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Texte de contenu (déjà en HTML d'origine, entités comprises) ramené à du texte simple. */
function plain(string $html): string
{
    $t = preg_replace('#<br\s*/?>#i', ' ', $html);
    $t = html_entity_decode(strip_tags((string) $t), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim((string) preg_replace('/\s+/u', ' ', $t));
}

/** Coupe un texte à $n caractères sur une fin de mot. */
function excerpt(string $text, int $n = 160): string
{
    $text = trim($text);
    if (mb_strlen($text) <= $n) {
        return $text;
    }
    $cut = mb_substr($text, 0, $n);
    $sp = mb_strrpos($cut, ' ');
    return rtrim(mb_substr($cut, 0, $sp ?: $n), " ,;:.-–") . '…';
}

/** Date au format « 14 octobre 2026 ». */
function date_fr(string $ymd): string
{
    static $m = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $t = strtotime($ymd);
    if (!$t) {
        return $ymd;
    }
    return (int) date('j', $t) . ' ' . $m[(int) date('n', $t) - 1] . ' ' . date('Y', $t);
}

/** Numéro de téléphone pour un lien tel: */
function tel(string $n): string
{
    return preg_replace('/[^0-9+]/', '', $n) ?? '';
}

/** Identifiant court (slug) à partir d'un texte. */
function slugify(string $s): string
{
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = strtr($s, ['’' => '-', "'" => '-']);
    $t = function_exists('iconv') ? @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) : $s;
    $t = strtolower((string) ($t ?: $s));
    $t = preg_replace('/[^a-z0-9]+/', '-', $t);
    return trim((string) $t, '-') ?: 'page';
}

/** Secret de l'application (généré au premier lancement, stocké hors web). */
function app_secret(): string
{
    $f = cfg('storage') . '/data/secret.key';
    if (!is_file($f)) {
        @mkdir(dirname($f), 0750, true);
        @file_put_contents($f, bin2hex(random_bytes(32)), LOCK_EX);
        @chmod($f, 0600);
    }
    return trim((string) file_get_contents($f));
}

/** Nonce CSP de la requête courante. */
function csp_nonce(): string
{
    static $n = null;
    return $n ??= base64_encode(random_bytes(16));
}

/** Version d'un fichier statique (cache navigateur). */
function asset(string $path): string
{
    $f = APP_ROOT . '/public' . $path;
    return $path . (is_file($f) ? '?v=' . substr(md5((string) filemtime($f)), 0, 8) : '');
}

function security_headers(bool $admin = false): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(self), geolocation=(), payment=()'); // micro : messages vocaux
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000');
    }
    $n = csp_nonce();
    $csp = "default-src 'self'; "
        . "script-src 'self' 'nonce-$n'; "
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
        . "font-src 'self' https://fonts.gstatic.com; "
        . "img-src 'self' data: blob: https:; "
        . "media-src 'self' https: blob:; "
        . "frame-src https://www.google.com https://maps.google.com https://www.facebook.com; "
        . "connect-src 'self'; "
        . "form-action 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'";
    header('Content-Security-Policy: ' . $csp);
    if ($admin) {
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex, nofollow');
    }
}

/** Le site est-il en cours de mise à jour (fichiers en cours de copie) ? */
function maintenance(): bool
{
    $f = APP_ROOT . '/storage/update/maintenance';
    return is_file($f) && (int) @file_get_contents($f) > time() - 900;
}
