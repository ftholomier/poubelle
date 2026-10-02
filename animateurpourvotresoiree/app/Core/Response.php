<?php
declare(strict_types=1);

namespace App\Core;

/** Réponse HTTP. */
final class Response
{
    public function __construct(
        public string $body = '',
        public int $status = 200,
        public array $headers = [],
        public ?string $file = null,
    ) {
    }

    public static function html(string $html, int $status = 200, array $headers = []): self
    {
        return new self($html, $status, ['Content-Type' => 'text/html; charset=utf-8'] + $headers);
    }

    public static function json(mixed $data, int $status = 200, array $headers = []): self
    {
        return new self((string) json_encode($data, Fs::JSON_FLAGS), $status, ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'no-store'] + $headers);
    }

    public static function text(string $text, int $status = 200, string $type = 'text/plain'): self
    {
        return new self($text, $status, ['Content-Type' => $type . '; charset=utf-8']);
    }

    public static function redirect(string $url, int $status = 302): self
    {
        // On n'autorise que les redirections internes ou vers le domaine du site.
        if (!preg_match('#^(https?:)?//#i', $url) && !str_starts_with($url, '/')) {
            $url = '/' . $url;
        }
        if (str_starts_with($url, '//')) {
            $url = '/';
        }
        return new self('', $status, ['Location' => $url]);
    }

    public static function back(string $fallback = '/'): self
    {
        $ref = Request::referer();
        $base = Request::baseUrl();
        if ($ref !== '' && (str_starts_with($ref, $base) || str_starts_with($ref, '/'))) {
            return self::redirect($ref);
        }
        return self::redirect($fallback);
    }

    public static function download(string $path, string $name, string $type = 'application/octet-stream'): self
    {
        return new self('', 200, [
            'Content-Type' => $type,
            'Content-Disposition' => 'attachment; filename="' . str_replace('"', '', $name) . '"',
            'Content-Length' => (string) filesize($path),
            'Cache-Control' => 'no-store',
        ], $path);
    }

    public static function csv(array $rows, string $name): self
    {
        $fh = fopen('php://temp', 'w+');
        fwrite($fh, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            fputcsv($fh, array_map(static function ($v): string {
                $v = is_array($v) ? implode(', ', $v) : (string) $v;
                // neutralise les formules (injection CSV dans Excel / LibreOffice)
                return $v !== '' && str_contains("=+-@\t\r", $v[0]) && !is_numeric($v) ? "'" . $v : $v;
            }, $row), ';');
        }
        rewind($fh);
        $csv = (string) stream_get_contents($fh);
        fclose($fh);
        return new self($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $k => $v) {
                header($k . ': ' . $v);
            }
        }
        if ($this->file !== null) {
            readfile($this->file);
            return;
        }
        if (Request::method() !== 'HEAD') {
            echo $this->body;
        }
    }
}
