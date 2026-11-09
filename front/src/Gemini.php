<?php

declare(strict_types=1);

/**
 * API Gemini de Google (Generative Language API), en appels cURL natifs.
 *
 *  - models()   : liste dynamique des modèles disponibles pour la clé (ceux qui savent
 *                 répondre à un message : méthode generateContent), mise en cache 24 h ;
 *  - generate() : réponse à une conversation, avec consignes système.
 *
 * La clé est envoyée dans l'en-tête x-goog-api-key (jamais dans l'adresse).
 */
final class Gemini
{
    public const BASE = 'https://generativelanguage.googleapis.com/v1beta/';
    private const CACHE_TTL = 86400;

    /** Essais : réponses HTTP simulées (méthode, url, corps) => [code, corps]. */
    public static ?Closure $http = null;

    // ------------------------------------------------------------------ modèles

    /**
     * Modèles utilisables pour discuter, du plus récent au plus ancien :
     * [['id', 'name', 'description', 'input', 'output', 'stable'], …]
     */
    public static function models(string $key, bool $force = false): array
    {
        $hash = substr(hash('sha256', $key), 0, 16);
        $cache = Store::get('gemini_models', []);
        if (!$force && ($cache['key'] ?? '') === $hash && ($cache['at'] ?? 0) > time() - self::CACHE_TTL && is_array($cache['models'] ?? null)) {
            return $cache['models'];
        }
        $out = [];
        $token = '';
        $pages = 0;
        do {
            $j = self::call('GET', 'models?pageSize=1000' . ($token !== '' ? '&pageToken=' . rawurlencode($token) : ''), $key);
            foreach ((array) ($j['models'] ?? []) as $m) {
                if (!in_array('generateContent', (array) ($m['supportedGenerationMethods'] ?? []), true)) {
                    continue;
                }
                $id = preg_replace('#^models/#', '', (string) ($m['name'] ?? ''));
                if (!self::validId($id)) {
                    continue;
                }
                $out[$id] = [
                    'id' => $id,
                    'name' => (string) ($m['displayName'] ?? $id),
                    'description' => mb_substr((string) ($m['description'] ?? ''), 0, 300),
                    'input' => (int) ($m['inputTokenLimit'] ?? 0),
                    'output' => (int) ($m['outputTokenLimit'] ?? 0),
                    'stable' => !preg_match('/(preview|exp|experimental|latest)/i', $id),
                ];
            }
            $token = (string) ($j['nextPageToken'] ?? '');
        } while ($token !== '' && ++$pages < 10);
        $out = array_values($out);
        // Stables d'abord, Gemini avant les autres familles (Gemma…), versions récentes en tête
        $rank = fn(array $m) => [$m['stable'], str_starts_with($m['id'], 'gemini-'), self::versionKey($m['id'])];
        usort($out, fn($a, $b) => $rank($b) <=> $rank($a) ?: strnatcmp($a['id'], $b['id']));
        Store::put('gemini_models', ['key' => $hash, 'at' => time(), 'models' => $out]);
        return $out;
    }

    /** Modèles en cache pour la clé enregistrée (sans appel réseau), ou []. */
    public static function cachedModels(): array
    {
        $key = Settings::secret('gemini_key');
        $cache = Store::get('gemini_models', []);
        return $key !== '' && ($cache['key'] ?? '') === substr(hash('sha256', $key), 0, 16) ? (array) ($cache['models'] ?? []) : [];
    }

    /** Modèle proposé par défaut : le plus récent « flash » stable, sinon le premier stable. */
    public static function recommended(array $models): string
    {
        foreach ($models as $m) {
            if ($m['stable'] && preg_match('/^gemini-[\d.]+-flash$/', $m['id'])) {
                return $m['id'];
            }
        }
        foreach ($models as $m) {
            if ($m['stable'] && str_starts_with($m['id'], 'gemini') && !preg_match('/(tts|image|audio|live|embedding|lite)/', $m['id'])) {
                return $m['id'];
            }
        }
        return (string) ($models[0]['id'] ?? '');
    }

    public static function validId(string $id): bool
    {
        return (bool) preg_match('/^[a-z0-9][a-z0-9._-]{1,80}$/i', $id);
    }

    /** Clé de tri : numéros de version du nom (gemini-2.5-flash → [2, 5]). */
    private static function versionKey(string $id): array
    {
        preg_match_all('/\d+/', $id, $m);
        return array_map('intval', array_slice($m[0], 0, 3)) + [0, 0, 0];
    }

    // ------------------------------------------------------------------ génération

    /**
     * Réponse du modèle. $messages : [['role' => 'user'|'assistant', 'content' => '…'], …]
     * (alternés, en commençant par l'utilisateur). Retourne '' si la réponse est bloquée.
     */
    public static function generate(string $key, string $model, string $system, array $messages, int $maxTokens = 2048): string
    {
        if (!self::validId($model)) {
            throw new RuntimeException('Modèle invalide.');
        }
        $contents = array_map(fn($m) => ['role' => $m['role'] === 'assistant' ? 'model' : 'user', 'parts' => [['text' => (string) $m['content']]]], $messages);
        $body = [
            'system_instruction' => ['parts' => [['text' => $system]]],
            'contents' => $contents,
            'generationConfig' => ['maxOutputTokens' => $maxTokens, 'temperature' => 0.4],
        ];
        try {
            $j = self::call('POST', 'models/' . rawurlencode($model) . ':generateContent', $key, $body);
        } catch (RuntimeException $e) {
            // Certains modèles (Gemma…) n'acceptent pas de consignes système : elles passent en tête du 1er message
            if (stripos($e->getMessage(), 'instruction') === false) {
                throw $e;
            }
            unset($body['system_instruction']);
            $body['contents'][0]['parts'][0]['text'] = $system . "\n\n---\n\n" . $body['contents'][0]['parts'][0]['text'];
            $j = self::call('POST', 'models/' . rawurlencode($model) . ':generateContent', $key, $body);
        }
        if (!empty($j['promptFeedback']['blockReason'])) {
            return '';
        }
        $c = $j['candidates'][0] ?? null;
        if (!$c || in_array($c['finishReason'] ?? '', ['SAFETY', 'BLOCKLIST', 'PROHIBITED_CONTENT', 'SPII', 'RECITATION'], true)) {
            return '';
        }
        $text = '';
        foreach ((array) ($c['content']['parts'] ?? []) as $p) {
            if (empty($p['thought']) && isset($p['text'])) {
                $text .= (string) $p['text'];
            }
        }
        return trim($text);
    }

    /** Transcription d'un fichier audio (envoyé en ligne, 20 Mo au plus). */
    public static function transcribe(string $key, string $model, string $file, string $mime): string
    {
        if (!self::validId($model) || !is_file($file) || filesize($file) > 18 * 1024 * 1024) {
            return '';
        }
        $body = [
            'contents' => [['role' => 'user', 'parts' => [
                ['inline_data' => ['mime_type' => $mime, 'data' => base64_encode((string) file_get_contents($file))]],
                ['text' => 'Transcris mot à mot ce message vocal en français. Réponds uniquement par la transcription, sans commentaire. Si rien n\'est audible, réponds par un texte vide.'],
            ]]],
            'generationConfig' => ['maxOutputTokens' => 4096, 'temperature' => 0],
        ];
        $j = self::call('POST', 'models/' . rawurlencode($model) . ':generateContent', $key, $body);
        $text = '';
        foreach ((array) ($j['candidates'][0]['content']['parts'] ?? []) as $p) {
            if (empty($p['thought']) && isset($p['text'])) {
                $text .= (string) $p['text'];
            }
        }
        return trim($text);
    }

    // ------------------------------------------------------------------ HTTP

    private static function call(string $method, string $path, string $key, ?array $body = null): array
    {
        if ($key === '') {
            throw new RuntimeException('Clé API Gemini manquante.');
        }
        $url = self::BASE . $path;
        $payload = $body !== null ? (string) json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        if (self::$http) {
            [$code, $raw] = (self::$http)($method, $url, $payload);
        } else {
            if (!function_exists('curl_init')) {
                throw new RuntimeException('Extension PHP cURL absente sur le serveur.');
            }
            $ch = curl_init($url);
            $opts = [
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . $key],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => $method === 'GET' ? 20 : 60,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            ];
            if ($method === 'POST') {
                $opts[CURLOPT_POST] = true;
                $opts[CURLOPT_POSTFIELDS] = $payload;
            }
            curl_setopt_array($ch, $opts);
            $raw = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            if ($raw === false) {
                throw new RuntimeException('Gemini injoignable : ' . $err);
            }
        }
        $j = json_decode((string) $raw, true);
        if ($code !== 200 || !is_array($j)) {
            throw new RuntimeException(self::error($code, is_array($j) ? $j : []));
        }
        return $j;
    }

    private static function error(int $code, array $j): string
    {
        $msg = (string) ($j['error']['message'] ?? '');
        $reason = '';
        foreach ((array) ($j['error']['details'] ?? []) as $d) {
            $reason = $reason ?: (string) ($d['reason'] ?? '');
        }
        if ($reason === 'API_KEY_INVALID' || stripos($msg, 'API key not valid') !== false) {
            return 'Clé API Gemini refusée : vérifiez-la (Google AI Studio › Clés API).';
        }
        if ($code === 403) {
            return 'Accès refusé par Google' . ($msg !== '' ? ' : ' . $msg : '') . '.';
        }
        if ($code === 404) {
            return 'Modèle introuvable pour cette clé' . ($msg !== '' ? ' : ' . $msg : '') . '.';
        }
        if ($code === 429) {
            return 'Quota Gemini atteint pour l\'instant : réessayez plus tard.';
        }
        return 'Gemini a répondu avec une erreur (code ' . $code . ')' . ($msg !== '' ? ' : ' . $msg : '') . '.';
    }
}
