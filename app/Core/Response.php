<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    /** Fichier envoyé en flux (gros téléchargements) à la place du corps. */
    public ?string $file = null;
    /** @var array{0:int,1:int}|null partie du fichier à envoyer, octets [début, fin] (requête « Range ») */
    public ?array $range = null;

    public function __construct(
        public string $body = '',
        public int $status = 200,
        public array $headers = [],
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self(
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $status,
            ['Content-Type' => 'application/json; charset=UTF-8', 'Cache-Control' => 'no-store']
        );
    }

    public static function redirect(string $to, int $status = 302): self
    {
        // Jamais d'adresse « //hôte » ou « /\hôte » (redirection vers un autre site) par accident,
        // même déguisée par des caractères de contrôle ou des espaces que le navigateur ignorerait.
        $to = ltrim((string) preg_replace('/[\x00-\x1F\x7F]/', '', $to), ' ');
        if (preg_match('#^/[/\\\\]#', $to)) {
            $to = '/' . ltrim($to, '/\\');
        }
        return new self('', $status, ['Location' => $to]);
    }

    /**
     * Fichier audio ou vidéo, envoyé par morceaux à la demande du navigateur (en-tête « Range ») :
     * avance rapide, et lecture sur iPhone et Mac (Safari n'en lit pas autrement).
     */
    public static function media(string $file, string $type, ?string $range): self
    {
        $size = (int) @filesize($file);
        $res = new self('', 200, ['Content-Type' => $type, 'Accept-Ranges' => 'bytes', 'Content-Length' => (string) $size]);
        $res->file = $file;
        if ($range !== null && $size > 0 && preg_match('/^\s*bytes=(\d*)-(\d*)\s*$/', $range, $m) && ($m[1] !== '' || $m[2] !== '')) {
            // « bytes=500- » : jusqu'à la fin ; « bytes=-500 » : les 500 derniers octets.
            $start = $m[1] === '' ? max(0, $size - (int) $m[2]) : (int) $m[1];
            $end = $m[1] === '' || $m[2] === '' ? $size - 1 : min((int) $m[2], $size - 1);
            if ($start > $end) {
                return new self('', 416, ['Content-Range' => "bytes */$size"]);
            }
            $res->status = 206;
            $res->range = [$start, $end];
            $res->headers['Content-Range'] = "bytes $start-$end/$size";
            $res->headers['Content-Length'] = (string) ($end - $start + 1);
        }
        return $res;
    }

    public static function notFound(): self
    {
        return self::html(View::render('errors/404', [], 'layout'), 404);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $k => $v) {
            header("$k: $v");
        }
        if ($this->file !== null && is_file($this->file)) {
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            if ($this->range === null) {
                readfile($this->file);
                return;
            }
            [$start, $end] = $this->range;
            $fp = @fopen($this->file, 'rb');
            if ($fp) {
                fseek($fp, $start);
                for ($left = $end - $start + 1; $left > 0 && !feof($fp); $left -= strlen($chunk)) {
                    $chunk = (string) fread($fp, min(262144, $left));
                    if ($chunk === '') {
                        break;
                    }
                    echo $chunk;
                }
                fclose($fp);
            }
            return;
        }
        echo $this->body;
    }
}
