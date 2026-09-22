<?php
declare(strict_types=1);

/** Échappement HTML — à utiliser sur toute sortie dans les gabarits. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Accès tolérant à un tableau imbriqué : `arr_get($a, 'a.b.c', $defaut)`. */
function arr_get(array $array, string $path, mixed $default = null): mixed
{
    $node = $array;
    foreach (explode('.', $path) as $segment) {
        if (!is_array($node) || !array_key_exists($segment, $node)) {
            return $default;
        }
        $node = $node[$segment];
    }
    return $node;
}

/** Slug ASCII stable, utilisé pour les URLs et les noms de fichiers JSON. */
function slugify(string $text, int $max = 90): string
{
    // La décomposition Unicode sépare les accents de leur lettre, qu'on retire
    // ensuite. Elle réclame ext-intl : on vérifie sa présence avant d'appeler,
    // sinon iconv fait seul le travail.
    $text = (string) preg_replace('/\p{Mn}/u', '', class_exists('Normalizer')
        ? \Normalizer::normalize($text, \Normalizer::FORM_D)
        : $text);
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    $text = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $text));
    $text = trim($text, '-');
    if ($text === '') {
        $text = 'item';
    }
    return substr($text, 0, $max);
}

/** Initiales affichées dans les tuiles colorées des cartes. */
function initials(string $name, int $count = 2): string
{
    $parts = preg_split('/[\s\-\']+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $out = '';
    foreach (array_slice($parts, 0, $count) as $part) {
        $out .= mb_strtoupper(mb_substr($part, 0, 1));
    }
    return $out !== '' ? $out : '·';
}

/** « il y a 3 jours » — la maquette affiche partout une ancienneté relative. */
function time_ago(?string $iso, string $lang = 'fr'): string
{
    if (!$iso) {
        return '';
    }
    $then = strtotime($iso);
    if ($then === false) {
        return '';
    }
    $diff = max(0, time() - $then);
    // Singulier et pluriel sont deux chaînes distinctes : « il y a 1 ans »
    // trahissait le gabarit.
    $plural = static function (string $key, int $n): string {
        $suffix = $n > 1 ? 's' : '';
        return sprintf(App\Services\I18n::t($key . $suffix), $n);
    };
    $t = static fn(string $k) => App\Services\I18n::t($k);

    return match (true) {
        $diff < 3600    => $t('ago.now'),
        $diff < 86400   => $plural('ago.hour', (int) floor($diff / 3600)),
        $diff < 172800  => $t('ago.yesterday'),
        $diff < 2592000 => $plural('ago.day', (int) floor($diff / 86400)),
        $diff < 31536000=> $plural('ago.month', max(1, (int) floor($diff / 2592000))),
        default         => $plural('ago.year', max(1, (int) floor($diff / 31536000))),
    };
}

/**
 * Date lisible dans la langue servie.
 *
 * « 12/03/2026 » se lit 12 mars en France et 3 décembre ailleurs : afficher le
 * format français à un visiteur anglophone change le sens de l'information.
 */
function format_date(?string $iso, bool $long = false): string
{
    $timestamp = $iso ? strtotime($iso) : false;
    if ($timestamp === false) {
        return '';
    }

    $locale = App\Services\I18n::locale();
    if (class_exists('IntlDateFormatter')) {
        $formatter = new IntlDateFormatter(
            $locale,
            $long ? IntlDateFormatter::LONG : IntlDateFormatter::MEDIUM,
            IntlDateFormatter::NONE,
        );
        $out = $formatter->format($timestamp);
        if (is_string($out) && $out !== '') {
            return $out;
        }
    }

    // Sans ext-intl : format ISO, sans ambiguïté dans aucune langue.
    return date($long ? 'Y-m-d' : 'Y-m-d', $timestamp);
}

/** Une des six couleurs de la charte, choisie de façon stable d'après une clé. */
function tile_color(string $key): string
{
    $palette = ['#FF4B3E', '#FFC531', '#6D4AFF', '#0FBFA4', '#FF7AB8', '#17123A'];
    return $palette[abs(crc32($key)) % count($palette)];
}

/** Texte lisible (encre ou blanc) au-dessus d'une couleur de tuile. */
function on_color(string $hex): string
{
    return in_array(strtoupper($hex), ['#FFC531', '#0FBFA4', '#FF7AB8'], true) ? '#17123A' : '#FFFFFF';
}

function str_excerpt(string $text, int $max = 160): string
{
    $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($text)));
    if (mb_strlen($text) <= $max) {
        return $text;
    }
    return mb_substr($text, 0, $max - 1) . '…';
}

/**
 * URL d'un fichier statique, suffixée de sa date de modification.
 *
 * Les assets sont servis avec un cache d'un an et l'attribut « immutable » :
 * sans empreinte qui change à chaque édition, un visiteur déjà venu garderait
 * l'ancienne feuille de style pendant des mois.
 */
function asset(string $path): string
{
    static $stamps = [];

    $path = '/' . ltrim($path, '/');
    if (!isset($stamps[$path])) {
        $file = App\Core\Config::path('public') . $path;
        $time = is_file($file) ? (int) filemtime($file) : 0;
        $stamps[$path] = $time > 0 ? (string) $time : (string) App\Core\Config::get('storage.schema', 1);
    }

    return $path . '?v=' . $stamps[$path];
}
