<?php
declare(strict_types=1);

/**
 * Extraction de texte brut depuis les documents déposés dans le back-office.
 * Aucune dépendance externe : chaque format est traité avec les extensions
 * PHP standard (zip pour les .docx, zlib pour les flux PDF compressés).
 */
final class DocText
{
    public const SUPPORTED = ['txt', 'md', 'csv', 'html', 'htm', 'json', 'docx', 'pdf'];

    public static function extract(string $path, string $ext): string
    {
        $ext = strtolower($ext);
        $text = match ($ext) {
            'txt', 'md', 'csv', 'json' => (string) @file_get_contents($path),
            'html', 'htm' => self::fromHtml((string) @file_get_contents($path)),
            'docx' => self::fromDocx($path),
            'pdf' => self::fromPdf($path),
            default => '',
        };
        return self::normalize($text);
    }

    public static function normalize(string $text): string
    {
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = (string) mb_convert_encoding($text, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
        }
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
        // Retire les caractères de contrôle qui polluent les extractions PDF.
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $text) ?? $text;
        return trim($text);
    }

    private static function fromHtml(string $html): string
    {
        $html = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
        $html = preg_replace('#</(p|div|h[1-6]|li|tr|section)>#i', "\n", $html) ?? $html;
        return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /** Un .docx est une archive zip : le texte vit dans word/document.xml. */
    private static function fromDocx(string $path): string
    {
        if (!class_exists('ZipArchive')) {
            return '';
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        if ($xml === '') {
            return '';
        }
        // Lookahead : on insère un saut de ligne sans consommer le « > »,
        // sinon la balise se retrouve ouverte et strip_tags avale le texte.
        $xml = preg_replace('#<w:p(?=[\s>])#', "\n<w:p", $xml) ?? $xml;
        $xml = str_replace(['<w:br/>', '<w:tab/>'], ["\n", ' '], $xml);
        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /**
     * Extraction PDF au mieux : décompresse les flux de contenu puis relit
     * les opérateurs de texte. Suffisant pour un PDF généré depuis un
     * traitement de texte ; un PDF scanné (image) ne donnera rien.
     */
    private static function fromPdf(string $path): string
    {
        $raw = (string) @file_get_contents($path);
        if ($raw === '') {
            return '';
        }
        $out = [];
        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $raw, $m)) {
            foreach ($m[1] as $stream) {
                $data = @gzuncompress($stream);
                if ($data === false) { $data = @gzinflate($stream); }
                if ($data === false) { $data = @gzdecode($stream); }
                if ($data === false) { $data = $stream; }
                if (!is_string($data) || !str_contains($data, 'T')) { continue; }
                $out[] = self::pdfOperators($data);
            }
        }
        $text = trim(implode("\n", array_filter($out)));
        // Certains PDF stockent le texte en UTF-16BE dans les chaînes.
        if ($text !== '' && substr_count($text, "\x00") > strlen($text) / 4) {
            $text = (string) mb_convert_encoding($text, 'UTF-8', 'UTF-16BE');
        }
        return $text;
    }

    private static function pdfOperators(string $content): string
    {
        $lines = [];
        // Tj / ' / " : chaîne simple. TJ : tableau de fragments.
        if (preg_match_all('/\((?:[^()\\\\]|\\\\.)*\)\s*Tj|\[(?:[^\[\]\\\\]|\\\\.)*\]\s*TJ|T\*/', $content, $m)) {
            foreach ($m[0] as $token) {
                if ($token === 'T*') { $lines[] = "\n"; continue; }
                $chunk = '';
                if (preg_match_all('/\(((?:[^()\\\\]|\\\\.)*)\)/', $token, $sm)) {
                    foreach ($sm[1] as $piece) {
                        $chunk .= strtr($piece, [
                            '\\(' => '(', '\\)' => ')', '\\\\' => '\\',
                            '\\n' => "\n", '\\r' => '', '\\t' => ' ',
                        ]);
                    }
                }
                $lines[] = $chunk;
            }
        }
        $text = implode('', $lines);
        return preg_replace('/[ ]{2,}/', ' ', $text) ?? $text;
    }
}
