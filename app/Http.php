<?php
declare(strict_types=1);

namespace App;

/** Petit client HTTP (cURL, repli sur les flux) pour les API externes. */
final class Http
{
    /**
     * @return array{ok:bool,status:int,body:string,json:?array,error:string}
     */
    public static function request(string $method, string $url, array $options = []): array
    {
        $timeout = (int) ($options['timeout'] ?? 8);
        $headers = (array) ($options['headers'] ?? []);
        $body = $options['body'] ?? null;
        if (\is_array($body)) {
            $body = json_encode($body, JSON_UNESCAPED_UNICODE);
            $headers['Content-Type'] = 'application/json';
        }

        $flat = [];
        foreach ($headers as $name => $value) {
            $flat[] = $name . ': ' . $value;
        }

        if (\function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
                CURLOPT_HTTPHEADER => $flat,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'iOiO/1.0 (+' . Config::baseUrl() . ')',
            ]);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
            $raw = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error = (string) curl_error($ch);
            curl_close($ch);
            $raw = \is_string($raw) ? $raw : '';
        } else {
            $context = stream_context_create(['http' => [
                'method' => $method,
                'header' => implode("\r\n", $flat),
                'content' => $body,
                'timeout' => $timeout,
                'ignore_errors' => true,
            ]]);
            $raw = @file_get_contents($url, false, $context);
            $raw = \is_string($raw) ? $raw : '';
            $status = 0;
            foreach ($http_response_header ?? [] as $line) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m) === 1) {
                    $status = (int) $m[1];
                }
            }
            $error = $raw === '' && $status === 0 ? 'Requête impossible' : '';
        }

        $json = $raw !== '' ? json_decode($raw, true) : null;
        return [
            'ok' => $error === '' && $status >= 200 && $status < 300,
            'status' => $status,
            'body' => $raw,
            'json' => \is_array($json) ? $json : null,
            'error' => $error,
        ];
    }

    public static function getJson(string $url, array $options = []): array
    {
        return self::request('GET', $url, $options);
    }

    public static function postJson(string $url, array $payload, array $options = []): array
    {
        return self::request('POST', $url, array_replace($options, ['body' => $payload]));
    }
}
