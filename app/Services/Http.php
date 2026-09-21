<?php
declare(strict_types=1);

namespace App\Services;

use App\Storage\Audit;

/** Client HTTP minimal (cURL), seule dépendance runtime hors PHP. */
final class Http
{
    /**
     * @return array{status:int, body:string, error:string}
     */
    public static function request(string $method, string $url, array $options = []): array
    {
        if (!function_exists('curl_init')) {
            return ['status' => 0, 'body' => '', 'error' => 'extension cURL absente'];
        }

        $ch = curl_init($url);
        $headers = $options['headers'] ?? [];
        $body = $options['json'] ?? null;

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_TIMEOUT        => (int) ($options['timeout'] ?? 20),
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_USERAGENT      => 'intermittent.fr/1.0',
        ]);

        if ($body !== null) {
            $encoded = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $encoded);
            $headers[] = 'Content-Type: application/json';
        }
        if ($headers !== []) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error !== '') {
            Audit::log('http.error', ['url' => parse_url($url, PHP_URL_HOST), 'error' => $error]);
        }

        return [
            'status' => $status,
            'body'   => is_string($response) ? $response : '',
            'error'  => $error,
        ];
    }

    /** Décode une réponse JSON, ou [] en cas d'échec. */
    public static function json(string $method, string $url, array $options = []): array
    {
        $response = self::request($method, $url, $options);
        if ($response['status'] < 200 || $response['status'] >= 300) {
            return [];
        }
        $data = json_decode($response['body'], true);
        return is_array($data) ? $data : [];
    }
}
