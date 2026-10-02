<?php
declare(strict_types=1);

namespace App\Core;

/** Fonctions texte (UTF-8, français). */
final class Str
{
    private const ACCENTS = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'ae', 'ç' => 'c',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o', 'œ' => 'oe',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'ÿ' => 'y', 'ß' => 'ss',
        'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'AE', 'Ç' => 'C',
        'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O', 'Œ' => 'OE',
        'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y', '’' => "'", '‘' => "'", '«' => '"', '»' => '"',
    ];

    /** Nettoie une saisie : caractères de contrôle, espaces, normalisation Unicode. */
    public static function clean(string $s, bool $multiline = true): string
    {
        if (!mb_check_encoding($s, 'UTF-8')) {
            $s = mb_convert_encoding($s, 'UTF-8', 'Windows-1252');
        }
        if (class_exists(\Normalizer::class)) {
            $s = (string) \Normalizer::normalize($s, \Normalizer::FORM_C);
        }
        $s = str_replace(["\r\n", "\r"], "\n", $s);
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F\x{200B}-\x{200D}\x{FEFF}]/u', '', $s) ?? '';
        if (!$multiline) {
            $s = str_replace("\n", ' ', $s);
        }
        $s = preg_replace('/[ \t\x{00A0}]+/u', ' ', $s) ?? '';
        $s = preg_replace("/\n{3,}/", "\n\n", $s) ?? '';
        return trim($s);
    }

    public static function ascii(string $s): string
    {
        $s = strtr($s, self::ACCENTS);
        if (preg_match('/[^\x00-\x7F]/', $s)) {
            if (function_exists('transliterator_transliterate')) {
                $t = transliterator_transliterate('Any-Latin; Latin-ASCII', $s);
                if (is_string($t)) {
                    $s = $t;
                }
            } else {
                $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
                if (is_string($t)) {
                    $s = $t;
                }
            }
        }
        return $s;
    }

    public static function slug(string $s, int $max = 80): string
    {
        $s = strtolower(self::ascii($s));
        $s = str_replace(["'", '&'], ['-', '-et-'], $s);
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
        $s = trim($s, '-');
        if (strlen($s) > $max) {
            $s = rtrim(substr($s, 0, $max), '-');
            $cut = strrpos($s, '-');
            if ($cut !== false && $cut > $max * 0.6) {
                $s = substr($s, 0, $cut);
            }
        }
        return $s;
    }

    /** Forme normalisée pour la recherche : minuscules, sans accents, alphanumérique. */
    public static function norm(string $s): string
    {
        $s = mb_strtolower(self::ascii($s));
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s) ?? '';
        return trim($s);
    }

    public static function lower(string $s): string
    {
        return mb_strtolower($s);
    }

    public static function ucfirst(string $s): string
    {
        return $s === '' ? '' : mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1);
    }

    /** « JEAN-PIERRE DUPONT » → « Jean-Pierre Dupont » (uniquement si la saisie est tout en capitales ou tout en minuscules). */
    public static function nameCase(string $s): string
    {
        $s = self::clean($s, false);
        if ($s === '') {
            return '';
        }
        $letters = preg_replace('/[^\p{L}]/u', '', $s) ?? '';
        if ($letters === '' || ($letters !== mb_strtoupper($letters) && $letters !== mb_strtolower($letters))) {
            return $s;
        }
        $s = mb_convert_case(mb_strtolower($s), MB_CASE_TITLE);
        // particules en minuscules
        $s = preg_replace_callback('/(?<=\s)(De|Du|Des|La|Le|Les|Et|D\'|L\')(?=\s|\p{L})/u', static fn ($m) => mb_strtolower($m[1]), $s) ?? $s;
        return $s;
    }

    /** Majuscule en début de phrase pour un texte saisi tout en capitales. */
    public static function sentenceCase(string $s): string
    {
        $letters = preg_replace('/[^\p{L}]/u', '', $s) ?? '';
        if (mb_strlen($letters) < 12 || $letters !== mb_strtoupper($letters)) {
            return $s;
        }
        $s = mb_strtolower($s);
        return preg_replace_callback('/(^|[.!?]\s+|\n\s*)(\p{Ll})/u', static fn ($m) => $m[1] . mb_strtoupper($m[2]), $s) ?? $s;
    }

    public static function limit(string $s, int $max, string $end = '…'): string
    {
        $s = trim($s);
        if (mb_strlen($s) <= $max) {
            return $s;
        }
        $cut = mb_substr($s, 0, $max);
        $sp = mb_strrpos($cut, ' ');
        if ($sp !== false && $sp > $max * 0.6) {
            $cut = mb_substr($cut, 0, $sp);
        }
        return rtrim($cut, " ,;:.-–") . $end;
    }

    /** Texte brut à partir de HTML. */
    public static function text(string $html): string
    {
        $html = preg_replace('#<(br|/p|/div|/li|/h[1-6])\b[^>]*>#i', "\n", $html) ?? $html;
        $txt = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return self::clean($txt);
    }

    public static function excerpt(string $html, int $max = 160): string
    {
        return self::limit(str_replace("\n", ' ', self::text($html)), $max);
    }

    /** Texte brut → paragraphes HTML échappés. */
    public static function paragraphs(string $text): string
    {
        $text = self::clean($text);
        if ($text === '') {
            return '';
        }
        $out = '';
        foreach (preg_split("/\n{2,}/", $text) as $p) {
            $out .= '<p>' . nl2br(htmlspecialchars(trim($p), ENT_QUOTES, 'UTF-8'), false) . '</p>';
        }
        return $out;
    }

    public static function phone(string $s): string
    {
        $d = preg_replace('/\D+/', '', $s) ?? '';
        if (str_starts_with($d, '33') && strlen($d) === 11) {
            $d = '0' . substr($d, 2);
        }
        if (strlen($d) === 10 && $d[0] === '0') {
            return trim(chunk_split($d, 2, ' '));
        }
        return self::clean($s, false);
    }

    public static function phoneValid(string $s): bool
    {
        $d = preg_replace('/\D+/', '', $s) ?? '';
        if (str_starts_with($d, '0033')) {
            $d = '0' . substr($d, 4);
        } elseif (str_starts_with($d, '33') && strlen($d) === 11) {
            $d = '0' . substr($d, 2);
        }
        return (bool) preg_match('/^0[1-9]\d{8}$/', $d) || (strlen($d) >= 9 && strlen($d) <= 15 && str_starts_with(trim($s), '+'));
    }

    public static function email(string $s): string
    {
        return strtolower(trim($s));
    }

    public static function emailValid(string $s): bool
    {
        return (bool) filter_var($s, FILTER_VALIDATE_EMAIL) && (bool) preg_match('/^[^@\s]+@[^@\s]+\.[a-z]{2,}$/i', $s);
    }

    public static function url(string $s): string
    {
        $s = trim($s);
        if ($s === '') {
            return '';
        }
        if (!preg_match('#^https?://#i', $s)) {
            $s = 'https://' . ltrim($s, '/');
        }
        if (!filter_var($s, FILTER_VALIDATE_URL)) {
            return '';
        }
        $scheme = strtolower((string) parse_url($s, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true) ? $s : '';
    }

    public static function domain(string $url): string
    {
        $h = (string) parse_url($url, PHP_URL_HOST);
        return preg_replace('/^www\./', '', strtolower($h)) ?? $h;
    }

    public static function plural(int|float $n, string $one, ?string $many = null): string
    {
        return (abs($n) >= 2) ? ($many ?? $one . 's') : $one;
    }

    public static function initials(string $s): string
    {
        $words = preg_split('/[\s\-]+/u', trim($s)) ?: [];
        $i = '';
        foreach (array_slice($words, 0, 2) as $w) {
            $i .= mb_strtoupper(mb_substr($w, 0, 1));
        }
        return $i ?: '?';
    }

    public static function random(int $len = 16): string
    {
        $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $s = '';
        for ($i = 0; $i < $len; $i++) {
            $s .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $s;
    }

    /** Distance de Levenshtein tolérante pour les petites fautes de frappe. */
    public static function similar(string $a, string $b): bool
    {
        $a = self::norm($a);
        $b = self::norm($b);
        if ($a === $b) {
            return true;
        }
        $len = max(strlen($a), strlen($b));
        return $len > 3 && levenshtein($a, $b) <= ($len > 7 ? 2 : 1);
    }
}
