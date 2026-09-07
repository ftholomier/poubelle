<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Client HTTP minimal (cURL, repli sur les flux PHP).
 * Utilisé pour les API Google (avis) et Gemini (assistant).
 */
final class Http
{
    /** @return array{ok:bool,status:int,body:string,json:mixed} */
    public static function get(string $url, array $headers = [], int $timeout = 15): array
    {
        return self::request('GET', $url, null, $headers, $timeout);
    }

    /** @return array{ok:bool,status:int,body:string,json:mixed} */
    public static function postJson(string $url, array $payload, array $headers = [], int $timeout = 25): array
    {
        $headers['Content-Type'] = 'application/json';
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return self::request('POST', $url, $body === false ? '{}' : $body, $headers, $timeout);
    }

    /** @return array{ok:bool,status:int,body:string,json:mixed} */
    private static function request(string $method, string $url, ?string $body, array $headers, int $timeout): array
    {
        if (!preg_match('#^https://#i', $url)) {
            return ['ok' => false, 'status' => 0, 'body' => 'URL non sécurisée', 'json' => null];
        }

        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST  => $method,
                CURLOPT_HTTPHEADER     => $lines,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_USERAGENT      => 'LeComptableALunettes/1.0',
            ]);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
            $raw    = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error  = curl_error($ch);
            curl_close($ch);

            if ($raw === false) {
                Logger::warning('Appel HTTP échoué', ['url' => parse_url($url, PHP_URL_HOST), 'error' => $error]);
                return ['ok' => false, 'status' => $status, 'body' => $error, 'json' => null];
            }
            return self::result($status, (string) $raw);
        }

        $context = stream_context_create([
            'http' => [
                'method'        => $method,
                'header'        => implode("\r\n", $lines),
                'content'       => $body ?? '',
                'timeout'       => $timeout,
                'ignore_errors' => true,
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $raw = @file_get_contents($url, false, $context);
        $status = 0;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $m)) {
                $status = (int) $m[1];
            }
        }
        return self::result($status, is_string($raw) ? $raw : '');
    }

    private static function result(int $status, string $raw): array
    {
        $json = json_decode($raw, true);
        return [
            'ok'     => $status >= 200 && $status < 300,
            'status' => $status,
            'body'   => $raw,
            'json'   => is_array($json) ? $json : null,
        ];
    }
}
