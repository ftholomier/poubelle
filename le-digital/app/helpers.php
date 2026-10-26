<?php
declare(strict_types=1);

use LD\Settings;

/** Échappement HTML. */
function e(mixed $v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Accès « a.b.c » dans un tableau. */
function array_get(array $arr, string $path, mixed $default = null): mixed
{
    if ($path === '') {
        return $arr;
    }
    $cur = $arr;
    foreach (explode('.', $path) as $k) {
        if (!is_array($cur) || !array_key_exists($k, $cur)) {
            return $default;
        }
        $cur = $cur[$k];
    }
    return $cur;
}

/** Texte saisi par un visiteur : sans balises ni caractères de contrôle, longueur bornée. */
function clean_text(string $v, int $max = 500): string
{
    $v = strip_tags($v);
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
    $v = trim($v);
    return mb_substr($v, 0, $max);
}

function url(string $path = '/'): string
{
    return $path === '' ? '/' : $path;
}

function abs_url(string $path = '/'): string
{
    if (preg_match('~^https?://~', $path)) {
        return $path;
    }
    return LD\Http::baseUrl() . '/' . ltrim($path, '/');
}

/** URL d'un fichier statique avec empreinte de version (cache navigateur longue durée). */
function asset(string $path): string
{
    $file = LD_PUBLIC . '/assets/' . ltrim($path, '/');
    $v = is_file($file) ? substr(base_convert((string) filemtime($file), 10, 36), -6) : LD_VERSION;
    return '/assets/' . ltrim($path, '/') . '?v=' . $v;
}

function slugify(string $s): string
{
    $s = mb_strtolower(trim($s));
    $s = strtr($s, ['à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u', 'ÿ' => 'y', 'œ' => 'oe', 'æ' => 'ae', '’' => '-', "'" => '-']);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    return trim($s, '-') ?: 'article';
}

function nbsp(string $s): string
{
    // Espaces insécables avant la ponctuation haute française et entre nombre et unité.
    return preg_replace(['/ ([:;!?»%])/u', '/« /u', '/(\d) (h|%|min)\b/u'], ["\u{00A0}$1", "«\u{00A0}", "$1\u{00A0}$2"], $s) ?? $s;
}

/** Texte de contenu échappé avec typographie française. */
function t(mixed $v): string
{
    return e(nbsp((string) ($v ?? '')));
}

/** Paragraphe multi-lignes (retours à la ligne conservés). */
function tp(mixed $v): string
{
    return nl2br(t($v), false);
}

/** Lien de téléphone à partir du numéro saisi dans les paramètres. */
function tel_href(?string $phone = null): string
{
    $phone ??= (string) Settings::get('contact.phone', '');
    $digits = preg_replace('/[^\d+]/', '', $phone) ?? '';
    return 'tel:' . $digits;
}

function phone_label(string $fallback = ''): string
{
    $p = trim((string) Settings::get('contact.phone', ''));
    return $p !== '' ? $p : $fallback;
}

function linkedin_url(): string
{
    $u = trim((string) Settings::get('contact.linkedin', ''));
    return $u !== '' ? $u : '#';
}

/** Lien externe : ajoute target/rel si l'URL sort du site. */
function link_attrs(string $href): string
{
    if (preg_match('~^https?://~', $href) && !str_contains($href, $_SERVER['HTTP_HOST'] ?? '__none__')) {
        return ' href="' . e($href) . '" target="_blank" rel="noopener"';
    }
    return ' href="' . e($href) . '"';
}

/** Date française lisible : « 12 mars 2026 ». */
function date_fr(string $date, bool $withWeekday = false, bool $withYear = true): string
{
    $ts = strtotime($date);
    if (!$ts) {
        return '';
    }
    $months = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $days = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    $s = (int) date('j', $ts) . ' ' . $months[(int) date('n', $ts) - 1] . ($withYear ? ' ' . date('Y', $ts) : '');
    return $withWeekday ? $days[(int) date('w', $ts)] . ' ' . $s : $s;
}

/**
 * Image responsive. $src peut être un média du back-office (/uploads/...) ou un visuel livré (/assets/img/...).
 * Retourne '' si aucune source : le gabarit affiche alors l'emplacement réservé de la maquette.
 */
function img_tag(?string $src, string $alt, array $opt = []): string
{
    $src = trim((string) $src);
    if ($src === '') {
        return '';
    }
    $sizes = $opt['sizes'] ?? '100vw';
    $class = isset($opt['class']) ? ' class="' . e($opt['class']) . '"' : '';
    $style = isset($opt['style']) ? ' style="' . e($opt['style']) . '"' : '';
    $loading = ($opt['eager'] ?? false) ? ' fetchpriority="high"' : ' loading="lazy"';
    $srcset = LD\Media::srcset($src);
    $dim = LD\Media::dimensions($src);
    $wh = $dim ? ' width="' . $dim[0] . '" height="' . $dim[1] . '"' : '';
    return '<img src="' . e(LD\Media::best($src)) . '"' . ($srcset ? ' srcset="' . e($srcset) . '" sizes="' . e($sizes) . '"' : '')
        . ' alt="' . e($alt) . '"' . $wh . $loading . ' decoding="async"' . $class . $style . '>';
}

/** Emplacement d'image réservé (style « image-slot » de la maquette) ou image réelle. */
function slot(?string $src, string $placeholder, string $alt = '', array $opt = []): string
{
    // Sans image : rien d'affiché (jamais d'encart « à fournir » côté visiteurs).
    return img_tag($src, $alt !== '' ? $alt : $placeholder, $opt + ['class' => 'slot-img']);
}

/** Icônes SVG utilisées dans la maquette. */
function icon(string $name, int $size = 16, string $stroke = 'currentColor', float $width = 2): string
{
    $paths = [
        'check'   => ['0 0 10 10', '<path d="M2 5.3l2 2L8 3"/>'],
        'check16' => ['0 0 16 16', '<path d="M3 8.5l3.2 3L13 4.5"/>'],
        'phone'   => ['0 0 24 24', '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 005 5L15 13l5 2v4a2 2 0 01-2 2A16 16 0 013 6a2 2 0 012-2"/>'],
        'clock'   => ['0 0 24 24', '<circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.5 2M9 2h6"/>'],
        'mic'     => ['0 0 24 24', '<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0014 0M12 18v3"/>'],
        'spark'   => ['0 0 24 24', '<path d="M12 3l1.8 4.7L18.5 9.5l-4.7 1.8L12 16l-1.8-4.7L5.5 9.5l4.7-1.8z"/><path d="M18 15l.8 2.2L21 18l-2.2.8L18 21l-.8-2.2L15 18l2.2-.8z"/>'],
        'spark1'  => ['0 0 24 24', '<path d="M12 3l1.8 4.7L18.5 9.5l-4.7 1.8L12 16l-1.8-4.7L5.5 9.5l4.7-1.8z"/>'],
        'send'    => ['0 0 24 24', '<path d="M5 12h14M13 6l6 6-6 6"/>'],
        'close'   => ['0 0 14 14', '<path d="M2 2l10 10M12 2L2 12"/>'],
        'menu'    => ['0 0 20 20', '<path d="M3 6h14M3 14h14"/>'],
    ];
    [$vb, $inner] = $paths[$name] ?? $paths['check'];
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="' . $vb . '" fill="none" stroke="' . e($stroke) . '" stroke-width="' . $width . '" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
}

/** Logo du site (couleur ou blanc). Les anciens logos PNG livrés sont remplacés par leur version vectorielle, nette à toute taille. */
function site_logo(bool $white = false): string
{
    $key = $white ? 'site.logo_white' : 'site.logo';
    $logo = trim((string) LD\Settings::get($key, ''));
    $legacy = $white ? '/assets/img/logo-blanc.png' : '/assets/img/logo.png';
    return $logo === '' || $logo === $legacy ? substr($legacy, 0, -4) . '.svg' : $logo;
}

/** Photo ronde de Fred (barre du bas, assistant, articles) : l'ancien cadrage large est remplacé par le cadrage serré sur le visage. */
function site_avatar(): string
{
    $a = trim((string) LD\Settings::get('site.avatar', ''));
    return $a === '' || $a === '/assets/img/fred-avatar.webp' ? '/assets/img/fred-avatar-face.webp' : $a;
}

/** Petit drapeau français (rappels « Made in France »). */
function fr_flag(): string
{
    return '<span class="fr-flag" aria-hidden="true"><i></i><i></i><i></i></span>';
}

/** Logo « G » de Google (attribution des avis importés). */
function google_g(int $size = 16): string
{
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
        . '<path fill="#4285F4" d="M23.5 12.3c0-.8-.1-1.6-.2-2.3H12v4.5h6.5a5.5 5.5 0 01-2.4 3.6v3h3.9c2.2-2.1 3.5-5.1 3.5-8.8z"/>'
        . '<path fill="#34A853" d="M12 24c3.2 0 6-1.1 7.9-2.9l-3.9-3c-1 .7-2.4 1.1-4 1.1-3.1 0-5.7-2.1-6.7-4.9h-4v3.1A12 12 0 0012 24z"/>'
        . '<path fill="#FBBC05" d="M5.3 14.3a7.2 7.2 0 010-4.6V6.6h-4a12 12 0 000 10.8l4-3.1z"/>'
        . '<path fill="#EA4335" d="M12 4.8c1.8 0 3.3.6 4.6 1.8l3.4-3.4A12 12 0 001.3 6.6l4 3.1C6.3 6.9 8.9 4.8 12 4.8z"/>'
        . '</svg>';
}

/** Puce verte cochée de la maquette. */
function tick(int $box = 22, int $iconSize = 10): string
{
    return '<span class="tick" style="width:' . $box . 'px;height:' . $box . 'px">' . icon('check', $iconSize, '#161922') . '</span>';
}

/** Bulle « Toi » (question du visiteur) utilisée en tête de section. */
function ask(string $text, bool $dark = false, bool $reveal = true): string
{
    if (trim($text) === '') {
        return '';
    }
    return '<div class="ask' . ($dark ? ' ask--dark' : '') . '"' . ($reveal ? ' data-reveal' : '') . '><span class="ask__av" aria-hidden="true">Toi</span><span class="ask__txt">' . t($text) . '</span></div>';
}

function json_attr(mixed $data): string
{
    return e(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/** JSON sûr pour une balise <script type="application/json">. */
function json_script(mixed $data): string
{
    return str_replace('</', '<\/', (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP));
}

/** Découpe un texte en mots (pour l'estimation du temps de lecture). */
function reading_minutes(string $html): int
{
    $words = str_word_count(strip_tags($html), 0, 'àâäçéèêëîïôöùûüÿœæÀÂÄÇÉÈÊËÎÏÔÖÙÛÜŸŒÆ’\'-');
    return max(1, (int) ceil($words / 220));
}
