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
    // Profil du club : {site}, {sigle_de}, {city_a}… (config/clubs/).
    if (str_contains($s, '{')) {
        $s = club($s);
    }
    return club_words($s);
}

/** Mots propres au club servi (« Fil jaune » → « Fil bleu »…), d'après config/clubs/<club>/mots.php. */
function club_words(string $s): string
{
    static $map = null;
    $map ??= (array) \App\Core\Club::content('mots', []);
    return $map ? strtr($s, $map) : $s;
}

/** URL d'une image redimensionnée à la volée, en WebP (largeurs : voir Services\Images::WIDTHS). */
function img(?string $rel, int $width = 800): string
{
    if (!$rel) {
        return '/assets/img/placeholder.svg';
    }
    if (str_ends_with(strtolower($rel), '.svg')) {
        return MEDIA_URL . '/full/' . str_replace('%2F', '/', rawurlencode($rel));
    }
    $v = \App\Data\Media::version($rel);
    return MEDIA_URL . '/' . $width . '/' . str_replace('%2F', '/', rawurlencode($rel)) . '.webp' . ($v !== null ? '?v=' . $v : '');
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
    return $rel ? MEDIA_URL . '/full/' . str_replace('%2F', '/', rawurlencode($rel)) : '/assets/img/placeholder.svg';
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

/** Jeton propre à la requête autorisant les rares scripts écrits dans la page (politique CSP). */
function csp_nonce(): string
{
    static $nonce = null;
    return $nonce ??= rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
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

/** Phrase terminée par un point, sauf si elle finit déjà par une ponctuation (« né en Italie ? »). */
function sentence(string $s): string
{
    $s = rtrim($s);
    return $s === '' || preg_match('/[.!?…]$/u', $s) ? $s : $s . '.';
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

/** Clé interne du site pour un usage donné (dérivée de storage/secret.key, jamais exposée). */
function site_key(string $purpose): string
{
    static $k = null;
    $k ??= \App\Core\Settings::key();
    return hash_hmac('sha256', $purpose, $k);
}

/** Horodatage signé des formulaires publics (anti-robot) : posé par le serveur, valable sans JavaScript. */
function form_ts(): string
{
    $t = (string) time();
    return $t . '.' . substr(hash_hmac('sha256', $t, site_key('form-ts')), 0, 20);
}

/** Âge en secondes d'un horodatage form_ts(), ou null s'il manque ou a été falsifié. */
function form_ts_age(string $v): ?int
{
    if (!preg_match('/^(\d{9,11})\.([a-f0-9]{20})$/', $v, $m) || !hash_equals(substr(hash_hmac('sha256', $m[1], site_key('form-ts')), 0, 20), $m[2])) {
        return null;
    }
    return time() - (int) $m[1];
}

/** Empreinte d'une adresse IP, valable un jour (RGPD : l'adresse elle-même n'est jamais conservée). */
function ip_hash(string $ip, int $len = 64): string
{
    return substr(hash_hmac('sha256', $ip . '|' . date('Y-m-d'), site_key('ip')), 0, $len);
}

/** Description pour Google (environ 160 caractères affichés) : coupée sur un mot. */
function meta_description(string $text, int $len = 158): string
{
    $t = trim((string) preg_replace('/\s+/u', ' ', $text));
    if (mb_strlen($t) <= $len) {
        return $t;
    }
    $cut = mb_substr($t, 0, $len);
    $sp = mb_strrpos($cut, ' ');
    return rtrim($sp > $len * 0.6 ? mb_substr($cut, 0, $sp) : $cut, " ,;:.–-") . '…';
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
    $html = preg_replace('#<(script|style|iframe|frame|frameset|object|embed|applet|form|svg|math|template|noscript)\b[^>]*>.*?</\1\s*>#is', '', $html);
    $html = preg_replace('#</?(script|style|iframe|frame|frameset|object|embed|applet|form|input|button|select|textarea|meta|link|base|svg|math|template|noscript)\b[^>]*>#i', '', $html);
    // Attributs d'événement, y compris collés au nom de balise ou à un autre attribut (« <a/onclick=… », « "onclick=… »).
    $html = preg_replace('/(?<=[\s\/"\'])on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
    // Adresses : décodées comme le ferait le navigateur (entités répétées, « &#106 » sans point-virgule,
    // blancs et caractères de contrôle retirés), puis seuls http(s):, mailto:, tel: (et les images
    // data: pour src) passent ; une adresse sans protocole (« / », « # », relative) reste permise.
    $html = preg_replace_callback('/\b(href|src|action|formaction|xlink:href)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', function ($m) {
        $v = trim($m[2], '"\'');
        for ($i = 0; $i < 5; $i++) {
            $prev = $v;
            $v = (string) preg_replace_callback('/&#(x[0-9a-f]+|\d+);?/i', function ($e) {
                $n = $e[1][0] === 'x' || $e[1][0] === 'X' ? hexdec(substr($e[1], 1)) : (int) $e[1];
                return $n > 0 && $n < 0x110000 ? mb_chr((int) $n, 'UTF-8') : '';
            }, $v);
            $v = html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($v === $prev) {
                break;
            }
        }
        $v = strtolower((string) preg_replace('/[\s\x00-\x20\x7f]+|\p{C}+/u', '', $v));
        if (!preg_match('#^[a-z][a-z0-9+.\-]*:#', $v)) {
            return $m[0]; // pas de protocole : « / », « # », « ? » ou adresse relative
        }
        $ok = preg_match('#^(https?|mailto|tel):#', $v) || (strtolower($m[1]) === 'src' && preg_match('#^data:image/(png|jpe?g|gif|webp);#', $v));
        return $ok ? $m[0] : $m[1] . '="#"';
    }, $html);
    // Liens internes absolus de l'ancien site → relatifs ; images de l'ancien site → médiathèque.
    $html = str_replace(['https://www.fcsochauxretro.com/', 'http://www.fcsochauxretro.com/'], '/', $html);
    $html = preg_replace_callback('#(src)="/wp-content/uploads/([^"]+?)(-\d+x\d+)?(\.\w+)"#', function ($m) {
        return 'src="' . img($m[2] . $m[4], 1200) . '" loading="lazy"';
    }, $html);
    // Citation (souvent un tweet) posée par l'ancien site directement dans une liste, entre deux
    // éléments : mise dans son propre élément de liste, pour les lecteurs d'écran (sans puce).
    for ($i = 0; $i < 5 && preg_match('#(?:<(?:ul|ol)\b[^>]*>|</li>)\s*<blockquote\b#i', $html); $i++) {
        $html = (string) preg_replace('#(<(?:ul|ol)\b[^>]*>|</li>)(\s*)(<blockquote\b(?:(?!<blockquote\b).)*?</blockquote>)#is', '$1$2<li class="li-quote">$3</li>', $html);
    }
    // Tweets cités par l'ancien site (le texte seul, la vidéo ou la photo perdue) : la vraie publication.
    $html = (string) preg_replace_callback('#<blockquote\b(?:(?!<blockquote\b).)*?</blockquote>#is', function ($m) {
        $id = tweet_id($m[0]);
        return $id ? tweet_embed($id) : $m[0];
    }, $html);
    // Liens vers d'anciennes adresses : redirigés par le serveur, on garde tels quels.
    return $html;
}

/** Numéro de la publication X (Twitter) citée dans un bloc, ou null. */
function tweet_id(string $html): ?string
{
    return preg_match('#(?:twitter|x)\.com/[A-Za-z0-9_]{1,15}/status(?:es)?/(\d{5,25})#i', $html, $m) ? $m[1] : null;
}

/**
 * Publication X (Twitter) affichée en vrai (vidéo, photos) par le lecteur officiel, sans suivi
 * publicitaire (dnt). La hauteur s'ajuste toute seule (site.js).
 */
function tweet_embed(string $id): string
{
    $id = preg_replace('/\D/', '', $id);
    $src = 'https://platform.twitter.com/embed/Tweet.html?dnt=true&lang=' . (\App\Services\I18n::lang() === 'en' ? 'en' : 'fr') . '&id=' . $id;
    return '<div class="tweet"><iframe src="' . e($src) . '" loading="lazy" title="' . e(t('Publication X')) . '" scrolling="no" allowfullscreen></iframe>'
        . '<a class="tweet__link" href="https://x.com/i/status/' . $id . '" target="_blank" rel="noopener">' . e(t('Voir sur X')) . ' ↗</a></div>';
}

/**
 * Tweets d'une section, chacun avec la minute du fait qu'il illustre (dernière minute citée juste
 * avant lui), pour les poser dans le déroulé du match.
 * @return list<array{min:string,id:string}>
 */
function tweets_by_minute(string $html): array
{
    $out = [];
    if (preg_match_all('#<blockquote\b(?:(?!<blockquote\b).)*?</blockquote>#is', $html, $all, PREG_OFFSET_CAPTURE)) {
        foreach ($all[0] as [$bq, $pos]) {
            if (!$id = tweet_id($bq)) {
                continue;
            }
            $before = strip_tags(substr($html, max(0, $pos - 600), min(600, $pos)));
            // « 90'+2 » (temps additionnel après l'apostrophe), « 90+2' », « 52' » ou « 52e min ».
            $min = '';
            if (preg_match_all("/(\d{1,3})(?:\s*['’]\s*(\+\s*\d+)|\s*(\+\s*\d+)?\s*(?:['’]|e\s*min))/u", $before, $mm, PREG_SET_ORDER)) {
                $last = end($mm);
                $min = (string) preg_replace('/\s+/', '', $last[1] . (($last[2] ?? '') ?: ($last[3] ?? '')));
            }
            $out[] = ['min' => (string) $min, 'id' => $id];
        }
    }
    return $out;
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
        'dailymotion' => ['provider' => 'dailymotion', 'id' => $id, 'embed' => 'https://www.dailymotion.com/embed/video/' . rawurlencode((string) $id), 'link' => 'https://www.dailymotion.com/video/' . rawurlencode((string) $id), 'thumb' => $thumb, 'title' => (string) ($v['title'] ?? '')],
        'vimeo' => ['provider' => 'vimeo', 'id' => $id, 'embed' => 'https://player.vimeo.com/video/' . rawurlencode((string) $id) . '?dnt=1', 'link' => 'https://vimeo.com/' . rawurlencode((string) $id), 'thumb' => $thumb, 'title' => (string) ($v['title'] ?? '')],
        'rutube' => ['provider' => 'rutube', 'id' => $id, 'embed' => 'https://rutube.ru/play/embed/' . rawurlencode((string) $id), 'link' => 'https://rutube.ru/video/' . rawurlencode((string) $id) . '/', 'thumb' => $thumb, 'title' => (string) ($v['title'] ?? '')],
        'x' => preg_match('/^\d{5,25}$/', (string) $id) ? ['provider' => 'x', 'id' => $id, 'embed' => 'https://platform.twitter.com/embed/Tweet.html?dnt=true&lang=' . (\App\Services\I18n::lang() === 'en' ? 'en' : 'fr') . '&id=' . $id, 'link' => 'https://x.com/i/status/' . $id, 'thumb' => null, 'title' => (string) ($v['title'] ?? '')] : null,
        'file' => ['provider' => 'file', 'id' => null, 'embed' => preg_replace('#^https?://(www\.)?fcsochauxretro\.com/wp-content/uploads/#', MEDIA_URL . '/full/', $url), 'link' => $url, 'thumb' => null, 'title' => (string) ($v['title'] ?? '')],
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

/**
 * Adresse du site pour un lien envoyé par e-mail : seulement le réglage (Réglages › Général),
 * jamais l'en-tête « Host » de la requête (un tiers pourrait détourner le lien vers son site).
 * Chaîne vide si l'adresse n'est pas réglée : l'appelant n'envoie alors pas le lien.
 */
function mail_base_url(): string
{
    return rtrim((string) Settings::get('general.base_url', ''), '/');
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

/**
 * Ligne d'export CSV sans formule : une cellule (saisie par le public) qui commence par
 * = + - @ ou une tabulation est préfixée d'une apostrophe pour Excel et LibreOffice.
 */
function csv_safe(array $row): array
{
    return array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) && !is_numeric($v) ? "'" . $v : $v, $row);
}

/** Texte traduit au singulier ou au pluriel selon $n (en français, 0 et 1 au singulier) ; {n} est remplacé. */
function tn(int|float $n, string $one, string $many, array $vars = []): string
{
    $plural = \App\Services\I18n::isEn() ? abs($n) != 1 : abs($n) >= 2;
    return t($plural ? $many : $one, $vars + ['n' => $n]);
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

/**
 * Adresse du teaser (vidéo .mp4 ou image .jpg). Montré au public : copie à adresse versionnée
 * dans public/assets/video/, servie directement par Apache (plages d'octets, cache d'un an) ;
 * jamais par PHP, qui garderait un processus occupé pendant tout le téléchargement (huit
 * visiteurs lents suffisaient à bloquer le site). Aperçu de l'équipe sur un site fermé :
 * l'adresse /video/teaser.* passe par PHP, qui vérifie l'accès.
 */
function teaser_src(string $ext, bool $public = true): string
{
    $ext = $ext === 'jpg' ? 'jpg' : 'mp4';
    $src = APP_DIR . '/Resources/video/teaser.' . $ext;
    if (!$public || !is_file($src)) {
        return '/video/teaser.' . $ext;
    }
    $name = 'teaser-' . substr(md5(filesize($src) . '-' . filemtime($src)), 0, 10) . '.' . $ext;
    $dest = PUBLIC_PATH . '/assets/video/' . $name;
    if (!is_file($dest)) {
        @mkdir(dirname($dest), 0775, true);
        $tmp = $dest . '.' . getmypid() . '.tmp';
        if (!@copy($src, $tmp) || !@rename($tmp, $dest)) {
            @unlink($tmp);
            return '/video/teaser.' . $ext;
        }
    }
    return '/assets/video/' . $name;
}

/** Remplace les variables du profil du club ({site}, {sigle_de}, {city_a}…) dans un texte. */
function club(string $text): string
{
    static $map = null;
    $map ??= array_combine(array_map(fn ($k) => '{' . $k . '}', array_keys(\App\Core\Club::vars())), \App\Core\Club::vars());
    return strtr($text, $map);
}

/** Adresse du bilan dans le stade du club (/bilans/stade-auguste-bonal/ pour Sochaux), d'après stadium_full du profil. */
function stadium_bilan_path(): string
{
    return '/bilans/stade-' . stadium_key() . '/';
}

/** Clé du stade du club dans les données (« auguste-bonal »), d'après stadium_full du profil. */
function stadium_key(): string
{
    // Même clé que celle des stades dans les données (Names::stadiumKey : « Parc des Princes » → « princes »).
    return \App\Data\Names::stadiumKey(\App\Core\Club::s('stadium_full'));
}

/**
 * Texte rédigé propre au club (config/clubs/<id>/<fichier>.php, clé $key), sinon $neutral
 * (musée neuf). À passer ensuite à t() pour la traduction.
 */
function club_text(string $key, string $neutral, string $file = 'libelles'): string
{
    $c = \App\Core\Club::content($file, []);
    return is_array($c) && isset($c[$key]) ? (string) $c[$key] : $neutral;
}

/**
 * Temps fort (texte en minuscules) où un penalty est accordé et tiré : but, transformé, arrêté,
 * raté… ou « penalty pour X » (l'issue au temps fort suivant). Jamais un penalty réclamé, non sifflé
 * ni « évident mais oublié » par l'arbitre.
 */
function penalty_taken(string $tl, bool $goal): bool
{
    if (!preg_match('/p[ée]n[a-z]*lty|\bs\.\s?p\.|tirs? au but/u', $tl) || str_contains($tl, 'point de p')) {
        return false;
    }
    if ($goal || str_contains($tl, 'tirs au but') || str_contains($tl, 'tir au but')) {
        return true;
    }
    // Issue racontée : vaut même si une autre action non sifflée est citée dans le même temps fort.
    if (preg_match('/transform|\brat[ée]|manqu[ée]|arr[êe]t[ée]? (par|la frappe|le tir|le p)|arr[êe]t de|d[ée]tourn|repouss|stopp|capt[ée]|sur le poteau|la barre|transversale|nuages|s[\'’]en charge|se charge|tir[ée]e? par|frappe le p|tire le p|\bmarque|égalise|egalise|recolle|réduit|ouvre le score|trompe|converti|s[\'’]impose face|se fait justice|sur p[ée]nalty|gratte un p|frappe en force/u', $tl)) {
        return true;
    }
    if (preg_match('/ne siffle|n[\'’]accorde pas|ne donne pas|ne bronche|ne dit rien|pas de p[ée]nalty|r[ée]clam|aurait|cru obtenir|croit obtenir|pense [àa] un p|tout proche|crie au|refus|simulation|p[ée]nalty \?|en vain/u', $tl)) {
        return false;
    }
    return (bool) preg_match('/p[ée]nalty (siffl[ée] |accord[ée] )?pour|siffle (un|le) p|accorde (un|le) p|obtient un p|p[ée]nalty indiscutable|p[ée]nalty ?[!.]/u', $tl);
}
