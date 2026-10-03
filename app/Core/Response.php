<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    /** Fichier envoyé en flux (gros téléchargements) à la place du corps. */
    public ?string $file = null;

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
        // Jamais d'adresse « //hôte » ou « /\hôte » (redirection vers un autre site) par accident.
        if (preg_match('#^/[/\\\\]#', $to)) {
            $to = '/' . ltrim($to, '/\\');
        }
        return new self('', $status, ['Location' => $to]);
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
            readfile($this->file);
            return;
        }
        echo $this->body;
    }
}
