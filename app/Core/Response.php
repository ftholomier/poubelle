<?php
declare(strict_types=1);

namespace App\Core;

/** Réponse HTTP : corps, code, en-têtes. */
final class Response
{
    private function __construct(
        private string $body,
        private int $status = 200,
        private array $headers = [],
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function json(array $data, int $status = 200): self
    {
        $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        return new self($body, $status, ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return new self('', $status, ['Location' => $location]);
    }

    public static function text(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /** Sert un fichier stocké hors /public, après contrôle des droits par l'appelant. */
    public static function file(string $absolutePath, string $mime, string $downloadName = ''): self
    {
        $response = new self('', 200, [
            'Content-Type'   => $mime,
            'Content-Length' => (string) filesize($absolutePath),
            'X-Content-Type-Options' => 'nosniff',
        ]);
        if ($downloadName !== '') {
            $response->headers['Content-Disposition'] =
                'inline; filename="' . preg_replace('/[^\w\.\-]/', '_', $downloadName) . '"';
        }
        $response->body = (string) file_get_contents($absolutePath);
        return $response;
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        // Une réponse à HEAD porte les en-têtes de la réponse GET, sans le
        // corps. Apache le retire de lui-même ; le serveur intégré de PHP non.
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
            return;
        }
        echo $this->body;
    }
}
