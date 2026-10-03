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

/** URL d'une image redimensionnée à la volée, en WebP (largeurs : voir Services\Images::WIDTHS). */
function img(?string $rel, int $width = 800): string
{
    if (!$rel) {
        return '/assets/img/placeholder.svg';
    }
    if (str_ends_with(strtolower($rel), '.svg')) {
        return '/media/full/' . str_replace('%2F', '/', rawurlencode($rel));
    }
    return '/media/' . $width . '/' . str_replace('%2F', '/', rawurlencode($rel)) . '.webp';
}

/** srcset WebP pour une image responsive. */
function srcset(?string $rel, array $widths = [480, 800, 1200]): string
{
    if (!$rel || str_ends_with(strtolower($rel), '.svg')) {
        return '';
    }
    return implode(', ', array_map(fn ($w) => img($rel, $w) . " {$w}w", $widths));
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

/** Date lisible dans la langue de la page : « 1er avril 2023 », « 1 April 2023 ». */
function date_fr(?string $iso, bool $withDay = false): string
{
    if (!$iso) {
        return '';
    }
    $ts = strtotime($iso);
    if ($ts === false) {
        return $iso;
    }
    $en = \App\Services\I18n::isEn();
    $months = $en
        ? ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']
        : ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $days = $en
        ? ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']
        : ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    $d = (int) date('j', $ts);
    $s = ($d === 1 && !$en ? '1er' : (string) $d) . ' ' . $months[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts);
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
/**
 * Texte court saisi au WYSIWYG du back-office (ou texte brut repris de l'ancien site),
 * affiché en ligne : les paragraphes deviennent des retours à la ligne.
 */
function rich_inline(?string $s): string
{
    $s = trim((string) $s);
    if ($s === '') {
        return '';
    }
    if (!preg_match('#<(p|br|strong|em|a|u|sup|sub|ul|ol|li|h\d|blockquote)\b#i', $s)) {
        return nl2br(e($s), false);
    }
    $h = preg_replace(['#</p>\s*<p>#i', '#</?(p|h\d|blockquote)>#i'], ['<br><br>', ''], safe_html($s));
    return trim((string) $h);
}

/** Texte brut d'un contenu saisi au WYSIWYG (méta-descriptions, e-mails texte…). */
function plain_text(?string $s): string
{
    $s = preg_replace(['#<br\s*/?>#i', '#</(p|li|h\d|blockquote)>#i'], ["\n", "\n\n"], (string) $s);
    return trim(preg_replace("/\n{3,}/", "\n\n", html_entity_decode(strip_tags((string) $s), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
}

function safe_html(?string $html): string
{
    if (!$html) {
        return '';
    }
    $html = preg_replace('#<(script|style|iframe|object|embed|form)\b[^>]*>.*?</\1>#is', '', $html);
    $html = preg_replace('#<(script|style|iframe|object|embed|form|input|button|meta|link)\b[^>]*>#i', '', $html);
    $html = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
    $html = preg_replace('/(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2/i', '$1="#"', $html);
    // Liens internes absolus de l'ancien site → relatifs ; images de l'ancien site → médiathèque.
    $html = str_replace(['https://www.fcsochauxretro.com/', 'http://www.fcsochauxretro.com/'], '/', $html);
    $html = preg_replace_callback('#(src)="/wp-content/uploads/([^"]+?)(-\d+x\d+)?(\.\w+)"#', function ($m) {
        return 'src="' . img($m[2] . $m[4], 1200) . '" loading="lazy"';
    }, $html);
    // Liens vers d'anciennes adresses : redirigés par le serveur, on garde tels quels.
    return $html;
}

/** Pictogramme « photo » des emplacements vides (maquette). */
function icon_photo(): string
{
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="1"/><circle cx="8.5" cy="9.5" r="1.8"/><path d="M4 18l5-5 4 4 3-3 4 4"/></svg>';
}

/** Compte à rebours (jours, heures, minutes, secondes) — animé par site.js. */
function countdown_html(string $date, string $time = '00:00:00'): string
{
    $iso = str_contains($date, 'T') ? $date : $date . 'T' . $time;
    $left = max(0, strtotime(str_replace('T', ' ', $iso)) - time());
    $cells = [
        ['d', (string) intdiv($left, 86400), t('jours')],
        ['h', str_pad((string) (intdiv($left, 3600) % 24), 2, '0', STR_PAD_LEFT), t('heures')],
        ['m', str_pad((string) (intdiv($left, 60) % 60), 2, '0', STR_PAD_LEFT), t('min')],
        ['s', str_pad((string) ($left % 60), 2, '0', STR_PAD_LEFT), t('sec')],
    ];
    $h = '<div class="countdown" data-countdown="' . e($iso) . '" role="timer">';
    foreach ($cells as [$k, $v, $l]) {
        $h .= '<div class="countdown__cell"><b data-cd="' . $k . '">' . e($v) . '</b><span>' . e($l) . '</span></div>';
    }
    return $h . '</div>';
}

/** Numéro sur deux chiffres. */
function pad2(int|string $n): string
{
    return str_pad((string) $n, 2, '0', STR_PAD_LEFT);
}

/**
 * Vidéo normalisée pour l'affichage : URL d'intégration (chargée après consentement),
 * lien d'origine et vignette locale éventuelle (récupérée par « console.php videos »).
 * @return array{provider:string, id:?string, embed:string, link:string, thumb:?string, title:string}|null
 */
function video_embed(array $v): ?array
{
    $provider = $v['provider'] ?? '';
    $id = $v['id'] ?? null;
    $url = (string) ($v['url'] ?? '');
    // Adresses YouTube mal saisies dans l'ancien site (« embed/https://www.youtube.com/watch?v=… »).
    if ($provider === 'iframe' && preg_match('#(?:youtube(?:-nocookie)?\.com/(?:embed/|watch\?v=|shorts/)|youtu\.be/)([\w-]{11})#', $url, $m)) {
        $provider = 'youtube';
        $id = $m[1];
        if (preg_match('#watch\?v=([\w-]{11})#', $url, $m2)) {
            $id = $m2[1];
        }
    }
    $thumbRel = $id ? '_video/' . $provider . '-' . $id . '.jpg' : null;
    $thumb = $thumbRel && is_file(\App\Data\Media::ORIGINALS . '/' . $thumbRel) ? $thumbRel : null;
    return match ($provider) {
        'youtube' => ['provider' => 'youtube', 'id' => $id, 'embed' => 'https://www.youtube-nocookie.com/embed/' . rawurlencode((string) $id) . '?rel=0', 'link' => 'https://www.youtube.com/watch?v=' . rawurlencode((string) $id), 'thumb' => $thumb, 'title' => (string) ($v['title'] ?? '')],
        'dailymotion' => ['provider' => 'dailymotion', 'id' => $id, 'embed' => 'https://geo.dailymotion.com/player.html?video=' . rawurlencode((string) $id), 'link' => 'https://www.dailymotion.com/video/' . rawurlencode((string) $id), 'thumb' => $thumb, 'title' => (string) ($v['title'] ?? '')],
        'vimeo' => ['provider' => 'vimeo', 'id' => $id, 'embed' => 'https://player.vimeo.com/video/' . rawurlencode((string) $id) . '?dnt=1', 'link' => 'https://vimeo.com/' . rawurlencode((string) $id), 'thumb' => $thumb, 'title' => (string) ($v['title'] ?? '')],
        'file' => ['provider' => 'file', 'id' => null, 'embed' => preg_replace('#^https?://(www\.)?fcsochauxretro\.com/wp-content/uploads/#', '/media/full/', $url), 'link' => $url, 'thumb' => null, 'title' => (string) ($v['title'] ?? '')],
        'iframe' => $url !== '' && preg_match('#^https://#', $url) ? ['provider' => 'iframe', 'id' => null, 'embed' => $url, 'link' => $url, 'thumb' => null, 'title' => (string) ($v['title'] ?? '')] : null,
        default => null,
    };
}

/** Libellé « Photos : X » quand toute une galerie partage le même crédit. */
function gallery_credit(array $items): ?string
{
    $credits = array_values(array_unique(array_filter(array_map(fn ($g) => trim((string) ($g['credit'] ?? '')), $items))));
    $withCredit = count(array_filter($items, fn ($g) => trim((string) ($g['credit'] ?? '')) !== ''));
    return count($credits) === 1 && $withCredit === count($items) ? $credits[0] : null;
}

/** Date courte jj/mm/aaaa à partir d'une date ISO (éventuellement partielle). */
function date_num(?string $iso): string
{
    if (!$iso) {
        return '';
    }
    $p = explode('-', substr($iso, 0, 10));
    return match (count($p)) {
        3 => "$p[2]/$p[1]/$p[0]",
        2 => "$p[1]/$p[0]",
        default => $p[0],
    };
}

/** Adresse publique du site, sans barre finale (réglage, sinon déduite de la requête). */
function base_url(): string
{
    $b = rtrim((string) Settings::get('general.base_url', ''), '/');
    if ($b !== '') {
        return $b;
    }
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host === '' || !preg_match('/^[a-z0-9.\-]+(:\d+)?$/i', $host)) {
        return '';
    }
    $https = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    return ($https ? 'https://' : 'http://') . $host;
}

/** « Compétition · tour » sans répétition (un match amical a pour tour « Amical »). */
function comp_round(?string $comp, ?string $round): string
{
    $comp = trim((string) $comp);
    $round = trim((string) $round);
    if ($round === '' || mb_strtolower($round) === mb_strtolower($comp)) {
        return $comp;
    }
    return $comp === '' ? $round : "$comp · $round";
}

/** Libellé d'une décennie : « Années 80 » / « The '80s » ; $full : « années 1980 » / « the 1980s ». */
function decade_label(int $decade, bool $full = false, bool $lower = false): string
{
    if (\App\Services\I18n::isEn()) {
        $s = $full || $decade >= 2000 ? "The {$decade}s" : "The '" . substr((string) $decade, 2) . 's';
    } else {
        $s = 'Années ' . ($full || $decade >= 2000 ? $decade : substr((string) $decade, 2));
    }
    return $lower ? mb_strtolower(mb_substr($s, 0, 1)) . mb_substr($s, 1) : $s;
}

/** Nombre ordinal : 1er, 2e… / 1st, 2nd, 3rd, 4th… */
function ordinal(int $n): string
{
    if (!\App\Services\I18n::isEn()) {
        return $n === 1 ? '1er' : $n . 'e';
    }
    $s = in_array($n % 100, [11, 12, 13], true) ? 'th' : (['th', 'st', 'nd', 'rd'][$n % 10] ?? 'th');
    return $n . $s;
}
