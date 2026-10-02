<?php
declare(strict_types=1);

use App\Core\Settings;
use App\Core\Session;
use App\Services\I18n;

/** Échappement HTML. */
function e(mixed $v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function setting(string $key, mixed $default = null): mixed
{
    return Settings::get($key, $default);
}

/** Traduction d'un libellé d'interface. */
function t(string $text, array $vars = []): string
{
    $s = I18n::text($text);
    foreach ($vars as $k => $v) {
        $s = str_replace('{' . $k . '}', (string) $v, $s);
    }
    return $s;
}

/** URL d'une image redimensionnée à la volée (largeurs autorisées : voir ImageService). */
function img(?string $rel, int $width = 800): string
{
    if (!$rel) {
        return '/assets/img/placeholder.svg';
    }
    return '/media/' . $width . '/' . str_replace('%2F', '/', rawurlencode($rel));
}

/** URL de l'image originale. */
function img_original(?string $rel): string
{
    return $rel ? '/media/full/' . str_replace('%2F', '/', rawurlencode($rel)) : '/assets/img/placeholder.svg';
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Session::csrfToken()) . '">';
}

function url(string $path = '/'): string
{
    $lang = I18n::lang();
    if ($lang !== I18n::DEFAULT && !str_starts_with($path, '/admin') && !str_starts_with($path, '/api')) {
        return '/' . $lang . $path;
    }
    return $path;
}

function asset(string $path): string
{
    $file = PUBLIC_PATH . '/assets/' . $path;
    $v = is_file($file) ? substr(md5((string) filemtime($file)), 0, 8) : '0';
    return '/assets/' . $path . '?v=' . $v;
}

/** Date française lisible : « 15 avril 2023 ». */
function date_fr(?string $iso, bool $withDay = false): string
{
    if (!$iso) {
        return '';
    }
    $ts = strtotime($iso);
    if ($ts === false) {
        return $iso;
    }
    $months = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $days = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    $s = (int) date('j', $ts) . ($withDay ? '' : '') . ' ' . $months[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts);
    return $withDay ? ucfirst($days[(int) date('w', $ts)]) . ' ' . $s : $s;
}

/** Date courte jj/mm/aaaa (cartes des mosaïques). */
function date_short(?string $iso): string
{
    return $iso && ($ts = strtotime($iso)) ? date('d/m/Y', $ts) : '';
}

function slugify(string $s): string
{
    $s = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $s) ?: strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-');
}

/** Texte brut à partir de HTML (extraits, recherche, IA). */
function plain(string $html): string
{
    $html = preg_replace('#<(br|/p|/li|/h\d|/tr)[^>]*>#i', "\n", $html);
    $t = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace("/[ \t\x{00A0}]+/u", ' ', preg_replace("/\n\s*\n+/", "\n", $t)));
}

function excerpt(string $html, int $len = 180): string
{
    $t = preg_replace('/\s+/u', ' ', plain($html));
    return mb_strlen($t) > $len ? rtrim(mb_substr($t, 0, $len - 1)) . '…' : $t;
}

/**
 * HTML éditorial autorisé : on retire scripts, gestionnaires d'événements et
 * URLs javascript: (contenu saisi dans le back-office ou importé).
 */
function safe_html(?string $html): string
{
    if (!$html) {
        return '';
    }
    $html = preg_replace('#<(script|style|iframe|object|embed|form)\b[^>]*>.*?</\1>#is', '', $html);
    $html = preg_replace('#<(script|style|iframe|object|embed|form|input|button|meta|link)\b[^>]*>#i', '', $html);
    $html = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
    $html = preg_replace('/(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2/i', '$1="#"', $html);
    // Liens internes absolus de l'ancien site → relatifs.
    return str_replace(['https://www.fcsochauxretro.com/', 'http://www.fcsochauxretro.com/'], '/', $html);
}
