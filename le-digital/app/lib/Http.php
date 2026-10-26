<?php
declare(strict_types=1);

namespace LD;

/**
 * Requête entrante, réponses JSON et client HTTP sortant (cURL).
 */
final class Http
{
    private static ?array $json = null;

    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function path(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = '/' . trim(rawurldecode($path), '/');
        return $path;
    }

    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    }

    public static function baseUrl(): string
    {
        $configured = rtrim((string) Settings::get('site.url', ''), '/');
        if ($configured !== '') {
            return $configured;
        }
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return (self::isHttps() ? 'https' : 'http') . '://' . $host;
    }

    public static function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    /** Corps JSON de la requête (décodé une seule fois). */
    public static function json(): array
    {
        if (self::$json === null) {
            $raw = file_get_contents('php://input', false, null, 0, 512 * 1024) ?: '';
            $data = json_decode($raw, true);
            self::$json = is_array($data) ? $data : [];
        }
        return self::$json;
    }

    public static function input(string $key, mixed $default = null): mixed
    {
        $json = self::json();
        if (array_key_exists($key, $json)) {
            return $json[$key];
        }
        return $_POST[$key] ?? $default;
    }

    /** Texte nettoyé (trim, longueur bornée, sans caractères de contrôle). */
    public static function str(string $key, int $max = 500): string
    {
        $v = self::input($key, '');
        if (!is_scalar($v)) {
            return '';
        }
        return clean_text((string) $v, $max);
    }

    public static function sendJson(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /** Répond tout de suite au navigateur, puis poursuit le travail (enregistrement, e-mail…) sans le faire attendre. */
    public static function sendJsonThen(mixed $data, callable $after): never
    {
        $body = (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        ignore_user_abort(true);
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        if (function_exists('fastcgi_finish_request') || function_exists('litespeed_finish_request')) {
            echo $body;
            function_exists('fastcgi_finish_request') ? fastcgi_finish_request() : litespeed_finish_request();
        } else {
            // Sans PHP-FPM : longueur annoncée pour que le navigateur n'attende pas la suite (sauf compression par PHP).
            if (!ini_get('zlib.output_compression')) {
                header('Content-Length: ' . strlen($body));
                header('Connection: close');
            }
            echo $body;
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            flush();
        }
        try {
            $after();
        } catch (\Throwable $e) {
            error_log('LE-DIGITAL : ' . $e->getMessage());
        }
        exit;
    }

    public static function fail(string $message, int $status = 400, array $extra = []): never
    {
        self::sendJson(['ok' => false, 'error' => $message] + $extra, $status);
    }

    public static function redirect(string $to, int $status = 302): never
    {
        header('Location: ' . $to, true, $status);
        exit;
    }

    /**
     * Requête HTTP sortante. Retourne ['status' => int, 'body' => string, 'json' => ?array, 'error' => ?string].
     */
    public static function request(string $method, string $url, array $headers = [], mixed $body = null, int $timeout = 60): array
    {
        $ch = curl_init($url);
        $hdrs = [];
        foreach ($headers as $k => $v) {
            $hdrs[] = is_int($k) ? $v : $k . ': ' . $v;
        }
        if (is_array($body)) {
            $body = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $hdrs[] = 'Content-Type: application/json';
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $hdrs,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT      => 'LE-DIGITAL.com/' . LD_VERSION,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $resp = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = $resp === false ? curl_error($ch) : null;
        curl_close($ch);
        $resp = $resp === false ? '' : (string) $resp;
        $json = json_decode($resp, true);
        return ['status' => $status, 'body' => $resp, 'json' => is_array($json) ? $json : null, 'error' => $err];
    }

    /**
     * Récupère une page web publique (protection SSRF : uniquement des IP publiques, taille et durée bornées).
     */
    public static function fetchPublicPage(string $url, int $maxBytes = 600000): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        if (!preg_match('~^https?://~i', $url)) {
            $url = 'https://' . $url;
        }
        for ($hop = 0; $hop < 4; $hop++) {
            $parts = parse_url($url);
            if (!$parts || empty($parts['host']) || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
                return null;
            }
            $host = $parts['host'];
            $port = $parts['port'] ?? (strtolower($parts['scheme']) === 'https' ? 443 : 80);
            if (!in_array((int) $port, [80, 443], true)) {
                return null;
            }
            $ips = @gethostbynamel($host) ?: [];
            if (!$ips) {
                return null;
            }
            foreach ($ips as $ip) {
                if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return null;
                }
            }
            $data = '';
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_HEADER         => false,
                CURLOPT_TIMEOUT        => 8,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_RESOLVE        => [$host . ':' . $port . ':' . $ips[0]],
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; LE-DIGITAL-Analyse/1.0)',
                CURLOPT_HTTPHEADER     => ['Accept: text/html,application/xhtml+xml', 'Accept-Language: fr-FR,fr;q=0.9'],
                CURLOPT_WRITEFUNCTION  => static function ($ch, $chunk) use (&$data, $maxBytes) {
                    $data .= $chunk;
                    return strlen($data) > $maxBytes ? 0 : strlen($chunk);
                },
            ]);
            curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $location = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
            curl_close($ch);
            if ($status >= 300 && $status < 400 && $location !== '') {
                $url = $location;
                continue;
            }
            return ($status >= 200 && $status < 300 && $data !== '') ? $data : null;
        }
        return null;
    }
}
