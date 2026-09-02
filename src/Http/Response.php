<?php

declare(strict_types=1);

namespace App\Http;

use App\Config;

/**
 * Fabrique de réponses HTTP.
 */
final class Response
{
    /**
     * @param array<string,mixed>|list<mixed> $data
     */
    public static function json(array $data, int $status = 200, int $ttl = 0): void
    {
        $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            $body = json_encode(['error' => 'Encodage JSON impossible']);
            $status = 500;
        }
        self::send((string) $body, 'application/json; charset=utf-8', $status, $ttl);
    }

    /**
     * Nuage de points au format binaire : trois flottants 32 bits par particule.
     * Environ quatre fois plus léger que le JSON et directement transférable
     * dans un Float32Array, sans passe d'analyse côté navigateur.
     *
     * @param list<float> $positions
     */
    public static function float32(array $positions, int $ttl = 0): void
    {
        $body = pack('g*', ...array_map('floatval', $positions));
        header('X-Point-Count: ' . intdiv(count($positions), 3));
        self::send($body, 'application/octet-stream', 200, $ttl);
    }

    public static function error(string $message, int $status = 400, ?string $detail = null): void
    {
        $payload = ['error' => $message, 'status' => $status];
        if ($detail !== null && Config::get('debug', false)) {
            $payload['detail'] = $detail;
        }
        self::json($payload, $status);
    }

    private static function send(string $body, string $contentType, int $status, int $ttl): void
    {
        if (headers_sent()) {
            echo $body;
            return;
        }

        http_response_code($status);
        header('Content-Type: ' . $contentType);
        header('X-Content-Type-Options: nosniff');

        if ($ttl > 0 && $status === 200) {
            header('Cache-Control: public, max-age=' . $ttl);
            $etag = '"' . substr(hash('xxh128', $body), 0, 20) . '"';
            header('ETag: ' . $etag);
            // Le navigateur possède déjà cette version : on lui évite le transfert.
            if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
                http_response_code(304);
                return;
            }
        } else {
            header('Cache-Control: no-store');
        }

        header('Content-Length: ' . strlen($body));
        echo $body;
    }
}
