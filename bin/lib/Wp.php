<?php
declare(strict_types=1);

namespace Bin;

/** Utilitaires de conversion WordPress -> modèle intermittent.fr. */
final class Wp
{
    /** Titre de brouillon fait de 10 lettres minuscules = spam d'octobre 2025. */
    public static function isSpamTitle(string $title): bool
    {
        return (bool) preg_match('/^[a-z]{10}$/', trim($title));
    }

    /**
     * Nettoie le HTML WordPress en conservant une mise en forme simple.
     *
     * `strip_tags` ne filtre que les balises : les attributs des balises
     * gardées passeraient tels quels, `onclick` et `href="javascript:"`
     * compris. Le résultat est donc repassé par le Sanitizer du site, qui
     * applique une liste blanche d'attributs et vérifie les URL.
     */
    public static function cleanHtml(string $html): string
    {
        $html = str_replace(["\r\n", "\r"], "\n", $html);
        $html = preg_replace('/<!--\s*\/?wp:[^>]*-->/', '', $html) ?? $html;
        $html = preg_replace('/\[\/?[a-z_]+[^\]]*\]/i', '', $html) ?? $html;   // shortcodes
        $html = str_replace(['&nbsp;', "\xc2\xa0"], ' ', $html);
        $html = strip_tags($html, '<p><br><strong><em><ul><ol><li><h2><h3><h4><a><blockquote>');
        $html = preg_replace('/\n{3,}/', "\n\n", $html) ?? $html;
        return \App\Services\Sanitizer::html(trim($html));
    }

    /** Texte brut, pour les extraits et l'index de recherche. */
    public static function toText(string $html): string
    {
        $text = self::cleanHtml($html);
        $text = preg_replace('/<\/(p|h[2-4]|li|blockquote)>/', "\n", $text) ?? $text;
        $text = strip_tags($text);
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        return trim((string) preg_replace('/\n{3,}/', "\n\n", $text));
    }

    /** Transforme un texte en liste de puces (section « Profil recherché »). */
    public static function toBullets(string $text): array
    {
        $out = [];
        foreach (preg_split('/\n+/', self::toText($text)) ?: [] as $line) {
            $line = trim(ltrim(trim($line), "-–—•*·\t "));
            if ($line !== '' && mb_strlen($line) > 2) {
                $out[] = $line;
            }
        }
        return $out;
    }

    /** Désérialise une méta WordPress sans jamais lever d'erreur. */
    public static function unserialize(mixed $value): array
    {
        if (!is_string($value) || $value === '') {
            return [];
        }
        $data = @unserialize($value, ['allowed_classes' => false]);
        return is_array($data) ? $data : [];
    }

    /** Première entrée d'une méta de type `a:1:{i:0;s:N:"...";}`. */
    public static function firstOf(mixed $value): string
    {
        $data = self::unserialize($value);
        $first = reset($data);
        return is_string($first) ? $first : (is_string($value) ? $value : '');
    }

    public static function date(mixed $value): string
    {
        $raw = trim((string) $value);
        if ($raw === '' || str_starts_with($raw, '0000')) {
            return '';
        }
        $ts = strtotime($raw);
        return $ts === false ? '' : date('c', $ts);
    }

    public static function bool(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'yes', 'true', 'on'], true);
    }

    public static function email(mixed $value): string
    {
        $raw = trim((string) $value);
        return filter_var($raw, FILTER_VALIDATE_EMAIL) ? strtolower($raw) : '';
    }

    public static function url(mixed $value): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }
        if (!preg_match('#^https?://#i', $raw)) {
            $raw = 'https://' . ltrim($raw, '/');
        }
        return filter_var($raw, FILTER_VALIDATE_URL) ? $raw : '';
    }

    /** @deprecated Conservé pour les scripts d’import : voir App\Services\Geo. */
    public static function normalize(string $text): string
    {
        return \App\Services\Geo::normalize($text);
    }

    /** @deprecated Voir App\Services\Geo::parseLocation(). */
    public static function parseLocation(string $raw): array
    {
        return \App\Services\Geo::parseLocation($raw);
    }

    /** @deprecated Voir App\Services\Geo::prettyCity(). */
    public static function prettyCity(string $raw): string
    {
        return \App\Services\Geo::prettyCity($raw);
    }


    /**
     * Découpe une liste de compétences saisie librement.
     * Les séparateurs observés sur le site : virgule, point-virgule, barre oblique,
     * retour à la ligne, puce.
     *
     * @return string[]
     */
    public static function splitSkills(string $raw): array
    {
        $raw = (string) preg_replace('/\((?:[^)]*)\)/u', ' ', $raw);   // « (Vérification VI, …) »
        $parts = preg_split('#[,;/\n•·|]+#u', $raw) ?: [];
        $out = [];

        foreach ($parts as $part) {
            $skill = trim($part, " \t\n\r.-–—:");
            $skill = (string) preg_replace('/\s+/u', ' ', $skill);
            if ($skill === '' || mb_strlen($skill) < 2 || mb_strlen($skill) > 48) {
                continue;
            }
            $skill = trim((string) preg_replace('/\\s+(etc|and|autres)\\.?$/iu', '', $skill));
            if ($skill === '' || in_array(mb_strtolower($skill), ['etc', 'etc.', 'and', 'et', 'ou', 'autres'], true)) {
                continue;
            }
            // Une phrase n'est pas une compétence.
            if (str_word_count($skill, 0, 'àâäéèêëîïôöùûüçÀÂÄÉÈÊËÎÏÔÖÙÛÜÇ-') > 6) {
                continue;
            }
            $out[mb_strtolower($skill)] = $skill;
        }
        return array_values($out);
    }

    /** Estime des années d'expérience à partir du texte libre du CV. */
    public static function guessYears(string $text): int
    {
        if (preg_match('/(\d{1,2})\s*(?:ans|années|years)\b/iu', $text, $m)) {
            return min(50, (int) $m[1]);
        }
        if (preg_match('/\bdepuis\s+(19|20)(\d{2})\b/iu', $text, $m)) {
            return max(0, min(50, (int) date('Y') - (int) ($m[1] . $m[2])));
        }
        return 0;
    }
}
