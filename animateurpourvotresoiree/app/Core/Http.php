<?php
declare(strict_types=1);

namespace App\Core;

/** Client HTTP natif (cURL si disponible, sinon flux PHP). */
final class Http
{
    /**
     * @return array{status:int, body:string, headers:array<string,string>, error:?string}
     */
    public static function request(string $method, string $url, array $headers = [], ?string $body = null, int $timeout = 20, bool $follow = true): array
    {
        if (!preg_match('#^https?://#i', $url)) {
            return ['status' => 0, 'body' => '', 'headers' => [], 'error' => 'URL invalide'];
        }
        $headers += ['User-Agent' => 'APVS/' . APP_VERSION . ' (+' . rtrim((string) Env::get('APP_URL', ''), '/') . ')'];
        $lines = [];
        foreach ($headers as $k => $v) {
            $lines[] = $k . ': ' . $v;
        }
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            $respHeaders = [];
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => strtoupper($method),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $lines,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
                CURLOPT_FOLLOWLOCATION => $follow,
                CURLOPT_MAXREDIRS => $follow ? 4 : 0,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_HEADERFUNCTION => static function ($ch, string $h) use (&$respHeaders): int {
                    $p = strpos($h, ':');
                    if ($p !== false) {
                        $respHeaders[strtolower(trim(substr($h, 0, $p)))] = trim(substr($h, $p + 1));
                    }
                    return strlen($h);
                },
            ]);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
            $res = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $err = $res === false ? curl_error($ch) : null;
            curl_close($ch);
            return ['status' => $status, 'body' => is_string($res) ? $res : '', 'headers' => $respHeaders, 'error' => $err];
        }
        $ctx = stream_context_create([
            'http' => [
                'method' => strtoupper($method),
                'header' => implode("\r\n", $lines),
                'content' => $body ?? '',
                'timeout' => $timeout,
                'ignore_errors' => true,
                'follow_location' => $follow ? 1 : 0,
                'max_redirects' => $follow ? 4 : 1,
            ],
        ]);
        $res = @file_get_contents($url, false, $ctx);
        $status = 0;
        $respHeaders = [];
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
                $status = (int) $m[1];
            } elseif (($p = strpos($h, ':')) !== false) {
                $respHeaders[strtolower(trim(substr($h, 0, $p)))] = trim(substr($h, $p + 1));
            }
        }
        return ['status' => $status, 'body' => is_string($res) ? $res : '', 'headers' => $respHeaders, 'error' => $res === false ? 'Requête échouée' : null];
    }

    public static function get(string $url, array $headers = [], int $timeout = 20): array
    {
        return self::request('GET', $url, $headers, null, $timeout);
    }

    public static function postJson(string $url, array $data, array $headers = [], int $timeout = 30): array
    {
        return self::request('POST', $url, ['Content-Type' => 'application/json'] + $headers, (string) json_encode($data, Fs::JSON_FLAGS), $timeout);
    }

    public static function postForm(string $url, array $data, array $headers = [], int $timeout = 20): array
    {
        return self::request('POST', $url, ['Content-Type' => 'application/x-www-form-urlencoded'] + $headers, http_build_query($data), $timeout);
    }
}
