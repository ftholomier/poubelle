<?php
declare(strict_types=1);

namespace App\Ai;

use App\Config;
use App\Log;
use App\Store;
use App\Text;

/**
 * Documents de l'assistant (PDF, DOCX, TXT). Stockés dans storage/docs,
 * hors racine web : ils ne sont jamais servis en direct.
 */
final class Docs
{
    public const FILE = 'docs.json';
    public const MAX_BYTES = 10_485_760; // 10 Mo
    private const MIMES = [
        'application/pdf' => 'pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'text/plain' => 'txt',
        'text/markdown' => 'txt',
    ];

    public static function all(): array
    {
        $data = Store::read(self::FILE);
        $docs = \is_array($data['docs'] ?? null) ? $data['docs'] : [];
        usort($docs, static fn (array $a, array $b): int => strcmp((string) ($b['at'] ?? ''), (string) ($a['at'] ?? '')));
        return $docs;
    }

    public static function find(string $id): ?array
    {
        foreach (self::all() as $doc) {
            if ((string) ($doc['id'] ?? '') === $id) {
                return $doc;
            }
        }
        return null;
    }

    /** @return array{ok:bool,id?:string,error?:string} */
    public static function store(array $file, string $by, string $lang = Config::DEFAULT_LANG, bool $public = true): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => $error === UPLOAD_ERR_NO_FILE ? 'Aucun fichier reçu.' : 'Envoi interrompu (code ' . $error . ').'];
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ((int) ($file['size'] ?? 0) > self::MAX_BYTES) {
            return ['ok' => false, 'error' => 'Fichier trop lourd (10 Mo maximum).'];
        }

        $mime = self::detectMime($tmp, (string) ($file['name'] ?? ''));
        if (!isset(self::MIMES[$mime])) {
            return ['ok' => false, 'error' => 'Format non accepté : PDF, DOCX ou TXT uniquement.'];
        }
        $ext = self::MIMES[$mime];

        $dir = Config::storagePath('docs');
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return ['ok' => false, 'error' => 'Dossier storage/docs inaccessible.'];
        }

        $id = Text::slug(pathinfo((string) ($file['name'] ?? 'document'), PATHINFO_FILENAME));
        $id = ($id === '' ? 'document' : mb_substr($id, 0, 60)) . '-' . substr(bin2hex(random_bytes(4)), 0, 6);
        $target = $dir . '/' . $id . '.' . $ext;

        $moved = is_uploaded_file($tmp) ? @move_uploaded_file($tmp, $target) : @rename($tmp, $target);
        if (!$moved) {
            return ['ok' => false, 'error' => 'Copie du fichier impossible.'];
        }
        @chmod($target, 0640);

        $docs = self::all();
        array_unshift($docs, [
            'id' => $id,
            'name' => (string) ($file['name'] ?? $id),
            'file' => basename($target),
            'ext' => $ext,
            'bytes' => filesize($target) ?: 0,
            'lang' => \in_array($lang, Config::LANGS, true) ? $lang : Config::DEFAULT_LANG,
            'public' => $public,
            'state' => 'pending',
            'chunks' => 0,
            'at' => (new \DateTimeImmutable())->format(\DATE_ATOM),
            'by' => $by,
        ]);
        Store::write(self::FILE, ['_schema' => Config::SCHEMA, 'docs' => $docs], $by);
        Log::write('ai', 'Document ajouté : ' . $id);

        return ['ok' => true, 'id' => $id];
    }

    public static function delete(string $id, string $by): bool
    {
        $doc = self::find($id);
        if ($doc === null) {
            return false;
        }
        $path = Config::storagePath('docs/' . basename((string) $doc['file']));
        if (is_file($path)) {
            @unlink($path);
        }
        $docs = array_values(array_filter(self::all(), static fn (array $d): bool => (string) ($d['id'] ?? '') !== $id));
        return Store::write(self::FILE, ['_schema' => Config::SCHEMA, 'docs' => $docs], $by);
    }

    public static function setState(string $id, string $state, int $chunks): void
    {
        $docs = self::all();
        $changed = false;
        foreach ($docs as $i => $doc) {
            if ((string) ($doc['id'] ?? '') === $id) {
                $docs[$i]['state'] = $state;
                $docs[$i]['chunks'] = $chunks;
                $docs[$i]['indexedAt'] = (new \DateTimeImmutable())->format(\DATE_ATOM);
                $changed = true;
            }
        }
        if ($changed) {
            Store::write(self::FILE, ['_schema' => Config::SCHEMA, 'docs' => $docs]);
        }
    }

    /** Extraction du texte : pdftotext si présent, sinon lecture des flux du PDF. */
    public static function extractText(array $doc): string
    {
        $path = Config::storagePath('docs/' . basename((string) ($doc['file'] ?? '')));
        if (!is_readable($path)) {
            return '';
        }
        return match ((string) ($doc['ext'] ?? '')) {
            'txt' => (string) file_get_contents($path),
            'docx' => self::fromDocx($path),
            'pdf' => self::fromPdf($path),
            default => '',
        };
    }

    private static function fromDocx(string $path): string
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }
        $xml = $zip->getFromName('word/document.xml') ?: '';
        $zip->close();
        if ($xml === '') {
            return '';
        }
        $xml = preg_replace('#</w:p>#', "\n", $xml) ?? $xml;
        $xml = preg_replace('#<w:tab[^>]*/>#', ' ', $xml) ?? $xml;
        return trim(html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8'));
    }

    private static function fromPdf(string $path): string
    {
        // Chemin nominal : l'utilitaire pdftotext (poppler-utils).
        if (\function_exists('shell_exec') && self::hasBinary('pdftotext')) {
            $out = @shell_exec('pdftotext -layout -enc UTF-8 ' . escapeshellarg($path) . ' - 2>/dev/null');
            if (\is_string($out) && trim($out) !== '') {
                return $out;
            }
        }
        // Repli sans binaire externe : décompression des flux de contenu.
        $raw = (string) file_get_contents($path);
        $text = '';
        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $raw, $matches) === false) {
            return '';
        }
        foreach ($matches[1] ?? [] as $stream) {
            $inflated = @gzuncompress($stream) ?: @gzinflate(substr($stream, 2));
            if (!\is_string($inflated)) {
                continue;
            }
            if (preg_match_all('/\((?:\\\\.|[^\\\\()])*\)/s', $inflated, $strings) !== false) {
                foreach ($strings[0] ?? [] as $chunk) {
                    $text .= stripcslashes(substr($chunk, 1, -1)) . ' ';
                }
            }
            $text .= "\n";
        }
        $text = trim(preg_replace('/[ \t]{2,}/', ' ', $text) ?? $text);
        if ($text === '') {
            Log::write('ai', 'PDF non extractible (installez poppler-utils) : ' . basename($path));
        }
        return $text;
    }

    private static function detectMime(string $tmp, string $name): string
    {
        $mime = '';
        if (\function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = (string) finfo_file($finfo, $tmp);
                finfo_close($finfo);
            }
        }
        // Un DOCX est un ZIP : on se fie alors à l'extension déclarée.
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext === 'docx' && \in_array($mime, ['application/zip', 'application/octet-stream'], true)) {
            return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        }
        if ($ext === 'txt' || $ext === 'md') {
            return 'text/plain';
        }
        return $mime;
    }

    private static function hasBinary(string $binary): bool
    {
        if (!\function_exists('shell_exec')) {
            return false;
        }
        $which = @shell_exec('command -v ' . escapeshellarg($binary) . ' 2>/dev/null');
        return \is_string($which) && trim($which) !== '';
    }

    public static function humanSize(int $bytes): string
    {
        if ($bytes >= 1_048_576) {
            return number_format($bytes / 1_048_576, 1, ',', ' ') . ' Mo';
        }
        return number_format(max(0, $bytes) / 1024, 0, ',', ' ') . ' Ko';
    }
}
