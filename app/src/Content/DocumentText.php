<?php

declare(strict_types=1);

namespace App\Content;

/**
 * Extraction de texte brut des documents téléversés, en PHP pur,
 * pour alimenter la base de connaissance de l'assistant IA.
 */
final class DocumentText
{
    public static function extract(string $path, string $ext): string
    {
        $text = match (strtolower($ext)) {
            'txt', 'md', 'csv' => (string) @file_get_contents($path),
            'pdf'  => self::fromPdf($path),
            'docx' => self::fromDocx($path),
            default => '',
        };

        $text = (string) preg_replace('/\s+/u', ' ', $text);
        return trim(mb_substr($text, 0, 120_000));
    }

    /** Extraction PDF : décompression des flux de contenu + opérateurs de texte. */
    private static function fromPdf(string $path): string
    {
        $raw = @file_get_contents($path);
        if (!is_string($raw) || $raw === '') {
            return '';
        }
        $out = [];

        // Flux compressés (FlateDecode), cas le plus courant.
        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $raw, $matches)) {
            foreach ($matches[1] as $stream) {
                $data = @gzuncompress($stream);
                if ($data === false) {
                    $data = @gzinflate($stream);
                }
                if ($data === false) {
                    $data = $stream;
                }
                $out[] = self::pdfOperators((string) $data);
            }
        }
        $text = trim(implode(' ', array_filter($out)));

        // Repli : certains PDF stockent le texte non compressé.
        if ($text === '') {
            $text = self::pdfOperators($raw);
        }
        return $text;
    }

    private static function pdfOperators(string $data): string
    {
        $pieces = [];
        // Tj / TJ : chaînes entre parenthèses.
        if (preg_match_all('/\(((?:\\\\.|[^\\\\()])*)\)\s*T[Jj]/', $data, $m)) {
            foreach ($m[1] as $chunk) {
                $pieces[] = self::unescapePdf($chunk);
            }
        }
        if (preg_match_all('/\[(.*?)\]\s*TJ/s', $data, $m)) {
            foreach ($m[1] as $array) {
                if (preg_match_all('/\(((?:\\\\.|[^\\\\()])*)\)/', $array, $inner)) {
                    foreach ($inner[1] as $chunk) {
                        $pieces[] = self::unescapePdf($chunk);
                    }
                }
            }
        }
        $text = implode(' ', $pieces);
        // On écarte les flux binaires (images) mal décodés.
        return preg_match('//u', $text) ? $text : '';
    }

    private static function unescapePdf(string $value): string
    {
        return str_replace(
            ['\\(', '\\)', '\\\\', '\\n', '\\r', '\\t'],
            ['(', ')', '\\', "\n", "\r", "\t"],
            $value
        );
    }

    /** DOCX = archive ZIP contenant word/document.xml. */
    private static function fromDocx(string $path): string
    {
        if (!class_exists(\ZipArchive::class)) {
            return '';
        }
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if (!is_string($xml) || $xml === '') {
            return '';
        }
        $xml = preg_replace('/<w:p[^>]*>/', "\n", $xml) ?? $xml;
        $xml = preg_replace('/<w:tab[^>]*>/', ' ', $xml) ?? $xml;
        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
