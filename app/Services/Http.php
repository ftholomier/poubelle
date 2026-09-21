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
        } elseif (!empty($options['form'])) {
            // OAuth2 client_credentials attend un corps de formulaire, pas du JSON.
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query((array) $options['form']));
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
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
        $call = self::call($method, $url, $options);
        return $call['ok'] ? $call['data'] : [];
    }

    /**
     * Comme json(), mais conserve la raison de l'échec.
     *
     * Une API refuse rarement sans l'expliquer : Google répond 403 avec un
     * message précis (« API non activée », « clé invalide », « facturation
     * absente »). json() jette ce corps avec le reste, et l'appelant ne peut
     * alors que deviner. Cette variante le remonte tel quel.
     *
     * @return array{ok:bool, status:int, data:array, error:string}
     */
    public static function call(string $method, string $url, array $options = []): array
    {
        $response = self::request($method, $url, $options);
        $data = json_decode($response['body'], true);
        $data = is_array($data) ? $data : [];

        $ok = $response['status'] >= 200 && $response['status'] < 300;
        if ($ok) {
            return ['ok' => true, 'status' => $response['status'], 'data' => $data, 'error' => ''];
        }

        // Message de l'API d'abord, erreur réseau ensuite, code HTTP en dernier.
        $error = trim((string) ($data['error']['message'] ?? $data['error_description'] ?? ''));
        if ($error === '' && is_string($data['error'] ?? null)) {
            $error = trim((string) $data['error']);
        }
        if ($error === '') {
            $error = $response['error'] !== ''
                ? $response['error']
                : ($response['status'] === 0
                    ? 'aucune réponse du serveur'
                    : 'réponse HTTP ' . $response['status']);
        }

        return ['ok' => false, 'status' => $response['status'], 'data' => $data, 'error' => $error];
    }
}
