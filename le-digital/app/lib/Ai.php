<?php
declare(strict_types=1);

namespace LD;

/**
 * Passerelle IA multi-fournisseurs (appels HTTP natifs via cURL).
 * Les clés sont chiffrées dans le coffre ; la liste des modèles est récupérée en direct auprès de chaque fournisseur.
 */
final class Ai
{
    public const PROVIDERS = [
        'anthropic'  => ['label' => 'Anthropic (Claude)', 'type' => 'anthropic', 'base' => 'https://api.anthropic.com/v1', 'console' => 'https://console.anthropic.com/settings/keys', 'hint' => 'sk-ant-…'],
        'openai'     => ['label' => 'OpenAI (GPT)', 'type' => 'openai', 'base' => 'https://api.openai.com/v1', 'console' => 'https://platform.openai.com/api-keys', 'hint' => 'sk-…', 'tokens' => 'max_completion_tokens'],
        'gemini'     => ['label' => 'Google Gemini', 'type' => 'gemini', 'base' => 'https://generativelanguage.googleapis.com/v1beta', 'console' => 'https://aistudio.google.com/app/apikey', 'hint' => 'AIza…'],
        'mistral'    => ['label' => 'Mistral AI', 'type' => 'openai', 'base' => 'https://api.mistral.ai/v1', 'console' => 'https://console.mistral.ai/api-keys', 'hint' => ''],
        'groq'       => ['label' => 'Groq', 'type' => 'openai', 'base' => 'https://api.groq.com/openai/v1', 'console' => 'https://console.groq.com/keys', 'hint' => 'gsk_…'],
        'openrouter' => ['label' => 'OpenRouter', 'type' => 'openai', 'base' => 'https://openrouter.ai/api/v1', 'console' => 'https://openrouter.ai/settings/keys', 'hint' => 'sk-or-…'],
        'deepseek'   => ['label' => 'DeepSeek', 'type' => 'openai', 'base' => 'https://api.deepseek.com', 'console' => 'https://platform.deepseek.com/api_keys', 'hint' => 'sk-…'],
        'xai'        => ['label' => 'xAI (Grok)', 'type' => 'openai', 'base' => 'https://api.x.ai/v1', 'console' => 'https://console.x.ai', 'hint' => 'xai-…'],
    ];

    public const FEATURES = [
        'chat'    => ['label' => 'Assistant du site (bouton « IA » et bulle de chat)', 'max_tokens' => 1500],
        'brief'   => ['label' => 'Brief transmis à Fred (fin de conversation)', 'max_tokens' => 1200],
        'voice'   => ['label' => 'Résumé IA des vocaux', 'max_tokens' => 800],
        'devis'   => ['label' => 'Démo « Ton devis en 10 secondes »', 'max_tokens' => 2000],
        'analyse' => ['label' => 'Analyse express de l’activité', 'max_tokens' => 2000],
        'explain' => ['label' => '« Explique-moi comme à un débutant »', 'max_tokens' => 1000],
    ];

    private const ANTHROPIC_VERSION = '2026-10-21';
    /** Modèles Claude acceptant le paramètre « fallbacks » côté serveur. */
    private const ANTHROPIC_FALLBACK_MODELS = ['claude-fable-5-1', 'claude-opus-5-5', 'claude-opus-5', 'claude-sonnet-5-5'];

    private static ?array $config = null;

    // ------------------------------------------------------------------ configuration

    public static function config(): array
    {
        if (self::$config === null) {
            self::$config = Content::merge(Content::defaults('ai'), Store::read('content/ai.json', []));
        }
        return self::$config;
    }

    public static function saveConfig(array $config): void
    {
        Store::write('content/ai.json', Content::changes(Content::defaults('ai'), $config));
        self::$config = null;
        PageCache::clear();
    }

    public static function keyName(string $provider): string
    {
        return 'ai_' . $provider . '_key';
    }

    public static function hasKey(string $provider): bool
    {
        return isset(self::PROVIDERS[$provider]) && Secrets::has(self::keyName($provider));
    }

    /** Fournisseur + modèle utilisés pour une fonctionnalité (réglage spécifique ou réglage par défaut). */
    public static function resolve(string $feature): ?array
    {
        $c = self::config();
        $f = $c['features'][$feature] ?? [];
        $provider = !empty($f['provider']) ? $f['provider'] : ($c['default_provider'] ?? '');
        $model = !empty($f['provider']) ? ($f['model'] ?? '') : ($c['default_model'] ?? '');
        if ($provider === '' || $model === '' || !self::hasKey($provider)) {
            return null;
        }
        return [$provider, $model];
    }

    public static function enabled(string $feature): bool
    {
        $c = self::config();
        if (empty($c['enabled'])) {
            return false;
        }
        if (isset($c['features'][$feature]['enabled']) && !$c['features'][$feature]['enabled']) {
            return false;
        }
        return self::resolve($feature) !== null;
    }

    public static function prompt(string $name): string
    {
        return (string) (self::config()['prompts'][$name] ?? '');
    }

    /** Remplace les {variables} d'un modèle de prompt. */
    public static function fill(string $template, array $vars): string
    {
        $map = [];
        foreach ($vars as $k => $v) {
            $map['{' . $k . '}'] = (string) $v;
        }
        return strtr($template, $map);
    }

    // ------------------------------------------------------------------ appels

    /**
     * @param array $messages [['role' => 'user'|'assistant', 'content' => string], ...]
     */
    public static function complete(string $feature, string $system, array $messages, ?int $maxTokens = null): string
    {
        $resolved = self::resolve($feature);
        if (!$resolved) {
            throw new AiException('IA non configurée');
        }
        [$provider, $model] = $resolved;
        $cap = (int) (self::config()['daily_cap'] ?? 300);
        if ($cap > 0 && self::todayCalls() >= $cap) {
            throw new AiException('Quota quotidien atteint');
        }
        $maxTokens ??= (int) (self::config()['features'][$feature]['max_tokens'] ?? self::FEATURES[$feature]['max_tokens'] ?? 1500);
        $t0 = microtime(true);
        try {
            $out = self::call($provider, $model, $system, $messages, $maxTokens);
            self::track($feature, $provider, $model, true, $out['in'] ?? 0, $out['out'] ?? 0, microtime(true) - $t0);
            return trim($out['text']);
        } catch (AiException $e) {
            self::track($feature, $provider, $model, false, 0, 0, microtime(true) - $t0, $e->getMessage());
            throw $e;
        }
    }

    /** Appel brut à un fournisseur (utilisé aussi par le bouton « Tester » du back-office). */
    public static function call(string $provider, string $model, string $system, array $messages, int $maxTokens = 1000): array
    {
        $def = self::PROVIDERS[$provider] ?? null;
        $key = Secrets::get(self::keyName($provider));
        if (!$def || !$key) {
            throw new AiException('Clé API manquante pour ' . ($def['label'] ?? $provider));
        }
        $messages = self::normalizeMessages($messages);
        return match ($def['type']) {
            'anthropic' => self::callAnthropic($model, $key, $system, $messages, $maxTokens),
            'gemini'    => self::callGemini($def, $model, $key, $system, $messages, $maxTokens),
            default     => self::callOpenAi($provider, $def, $model, $key, $system, $messages, $maxTokens),
        };
    }

    /** Alterne correctement user/assistant et commence par un message utilisateur. */
    private static function normalizeMessages(array $messages): array
    {
        $out = [];
        foreach ($messages as $m) {
            $role = ($m['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $content = trim((string) ($m['content'] ?? ''));
            if ($content === '') {
                continue;
            }
            if (!$out && $role === 'assistant') {
                continue;
            }
            if ($out && end($out)['role'] === $role) {
                $out[count($out) - 1]['content'] .= "\n\n" . $content;
                continue;
            }
            $out[] = ['role' => $role, 'content' => $content];
        }
        if (!$out) {
            throw new AiException('Message vide');
        }
        return $out;
    }

    private static function anthropicSupportsEffort(string $model): bool
    {
        return (bool) preg_match('/^claude-(opus-4-[5-9]|opus-5|sonnet-4-[6-9]|sonnet-5|haiku-5|fable|mythos)/', $model);
    }

    private static function callAnthropic(string $model, string $key, string $system, array $messages, int $maxTokens): array
    {
        $body = ['model' => $model, 'max_tokens' => $maxTokens, 'messages' => $messages];
        if ($system !== '') {
            $body['system'] = $system;
        }
        $effort = (string) (self::config()['anthropic_effort'] ?? 'low');
        if (in_array($effort, ['low', 'medium', 'high'], true) && self::anthropicSupportsEffort($model)) {
            $body['output_config'] = ['effort' => $effort];
        }
        $headers = ['x-api-key' => $key, 'anthropic-version' => self::ANTHROPIC_VERSION];
        $withFallback = in_array($model, self::ANTHROPIC_FALLBACK_MODELS, true);
        if ($withFallback) {
            // En cas de refus par les filtres de sécurité, l'API bascule d'elle-même sur un modèle de repli.
            $headers['anthropic-beta'] = 'server-side-fallback-2026-07-01';
            $body['fallbacks'] = 'default';
        }
        $r = Http::request('POST', self::PROVIDERS['anthropic']['base'] . '/messages', $headers, $body, 90);
        if ($withFallback && $r['status'] === 400 && str_contains($r['body'], 'fallback')) {
            unset($headers['anthropic-beta'], $body['fallbacks']);
            $r = Http::request('POST', self::PROVIDERS['anthropic']['base'] . '/messages', $headers, $body, 90);
        }
        self::assertOk($r);
        $j = $r['json'] ?? [];
        if (($j['stop_reason'] ?? '') === 'refusal') {
            throw new AiException('Réponse refusée par le modèle');
        }
        $text = '';
        foreach ($j['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'];
            }
        }
        if (trim($text) === '') {
            throw new AiException('Réponse vide (augmente le nombre maximal de jetons)');
        }
        return ['text' => $text, 'in' => (int) ($j['usage']['input_tokens'] ?? 0), 'out' => (int) ($j['usage']['output_tokens'] ?? 0)];
    }

    private static function callOpenAi(string $provider, array $def, string $model, string $key, string $system, array $messages, int $maxTokens): array
    {
        $msgs = $system !== '' ? array_merge([['role' => 'system', 'content' => $system]], $messages) : $messages;
        $body = ['model' => $model, 'messages' => $msgs, ($def['tokens'] ?? 'max_tokens') => $maxTokens];
        $headers = ['Authorization' => 'Bearer ' . $key];
        if ($provider === 'openrouter') {
            $headers['HTTP-Referer'] = Http::baseUrl();
            $headers['X-Title'] = 'LE-DIGITAL.com';
        }
        $r = Http::request('POST', $def['base'] . '/chat/completions', $headers, $body, 90);
        self::assertOk($r);
        $j = $r['json'] ?? [];
        $content = $j['choices'][0]['message']['content'] ?? '';
        if (is_array($content)) {
            $content = implode('', array_map(static fn($p) => is_array($p) ? ($p['text'] ?? '') : (string) $p, $content));
        }
        if (trim((string) $content) === '') {
            throw new AiException('Réponse vide (augmente le nombre maximal de jetons)');
        }
        return ['text' => (string) $content, 'in' => (int) ($j['usage']['prompt_tokens'] ?? 0), 'out' => (int) ($j['usage']['completion_tokens'] ?? 0)];
    }

    private static function callGemini(array $def, string $model, string $key, string $system, array $messages, int $maxTokens): array
    {
        $contents = array_map(static fn($m) => ['role' => $m['role'] === 'assistant' ? 'model' : 'user', 'parts' => [['text' => $m['content']]]], $messages);
        // Les modèles « thinking » consomment une partie du budget : on laisse de la marge.
        $body = ['contents' => $contents, 'generationConfig' => ['maxOutputTokens' => max($maxTokens * 4, 2048)]];
        if ($system !== '') {
            $body['systemInstruction'] = ['parts' => [['text' => $system]]];
        }
        $model = preg_replace('~^models/~', '', $model);
        $r = Http::request('POST', $def['base'] . '/models/' . rawurlencode((string) $model) . ':generateContent', ['x-goog-api-key' => $key], $body, 90);
        self::assertOk($r);
        $j = $r['json'] ?? [];
        $text = '';
        foreach ($j['candidates'][0]['content']['parts'] ?? [] as $part) {
            if (empty($part['thought']) && isset($part['text'])) {
                $text .= $part['text'];
            }
        }
        if (trim($text) === '') {
            throw new AiException('Réponse vide (' . ($j['candidates'][0]['finishReason'] ?? 'inconnue') . ')');
        }
        return ['text' => $text, 'in' => (int) ($j['usageMetadata']['promptTokenCount'] ?? 0), 'out' => (int) ($j['usageMetadata']['candidatesTokenCount'] ?? 0)];
    }

    private static function assertOk(array $r): void
    {
        if ($r['error']) {
            throw new AiException('Connexion impossible : ' . $r['error']);
        }
        if ($r['status'] < 200 || $r['status'] >= 300) {
            $j = $r['json'] ?? [];
            $msg = $j['error']['message'] ?? ($j['error'] ?? null);
            if (is_array($msg)) {
                $msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
            }
            $msg = $msg ?: ($j['message'] ?? mb_substr(strip_tags($r['body']), 0, 200));
            throw new AiException('Erreur ' . $r['status'] . ' : ' . $msg, $r['status']);
        }
    }

    // ------------------------------------------------------------------ modèles (liste dynamique)

    /**
     * Liste des modèles disponibles pour la clé enregistrée (mise en cache 24 h, rafraîchissable).
     * @return array<int, array{id:string, name:string, created:int}>
     */
    public static function models(string $provider, bool $refresh = false): array
    {
        $def = self::PROVIDERS[$provider] ?? null;
        if (!$def) {
            throw new AiException('Fournisseur inconnu');
        }
        $cacheFile = 'cache/models/' . $provider . '.json';
        $cached = Store::read($cacheFile, []);
        if (!$refresh && !empty($cached['models']) && ($cached['fetched_at'] ?? 0) > time() - 86400) {
            return $cached['models'];
        }
        $key = Secrets::get(self::keyName($provider));
        if (!$key) {
            throw new AiException('Enregistre d’abord la clé API');
        }
        $models = match ($def['type']) {
            'anthropic' => self::fetchAnthropicModels($key),
            'gemini'    => self::fetchGeminiModels($def, $key),
            default     => self::fetchOpenAiModels($provider, $def, $key),
        };
        usort($models, static fn($a, $b) => ($b['created'] <=> $a['created']) ?: strcmp($a['id'], $b['id']));
        Store::write($cacheFile, ['fetched_at' => time(), 'models' => $models]);
        return $models;
    }

    public static function cachedModels(string $provider): array
    {
        return Store::read('cache/models/' . $provider . '.json', [])['models'] ?? [];
    }

    private static function fetchAnthropicModels(string $key): array
    {
        $out = [];
        $after = null;
        for ($page = 0; $page < 10; $page++) {
            $url = self::PROVIDERS['anthropic']['base'] . '/models?limit=1000' . ($after ? '&after_id=' . rawurlencode($after) : '');
            $r = Http::request('GET', $url, ['x-api-key' => $key, 'anthropic-version' => self::ANTHROPIC_VERSION], null, 30);
            self::assertOk($r);
            foreach ($r['json']['data'] ?? [] as $m) {
                $out[] = ['id' => $m['id'], 'name' => $m['display_name'] ?? $m['id'], 'created' => (int) strtotime($m['created_at'] ?? '')];
            }
            if (empty($r['json']['has_more']) || empty($r['json']['last_id'])) {
                break;
            }
            $after = $r['json']['last_id'];
        }
        return $out;
    }

    private static function fetchGeminiModels(array $def, string $key): array
    {
        $out = [];
        $token = null;
        for ($page = 0; $page < 10; $page++) {
            $url = $def['base'] . '/models?pageSize=1000' . ($token ? '&pageToken=' . rawurlencode($token) : '');
            $r = Http::request('GET', $url, ['x-goog-api-key' => $key], null, 30);
            self::assertOk($r);
            foreach ($r['json']['models'] ?? [] as $m) {
                $id = preg_replace('~^models/~', '', (string) $m['name']);
                if (!in_array('generateContent', $m['supportedGenerationMethods'] ?? [], true)) {
                    continue;
                }
                if (preg_match('/(embedding|aqa|imagen|veo|tts|image-generation|learnlm)/i', (string) $id)) {
                    continue;
                }
                $out[] = ['id' => $id, 'name' => $m['displayName'] ?? $id, 'created' => 0];
            }
            $token = $r['json']['nextPageToken'] ?? null;
            if (!$token) {
                break;
            }
        }
        return $out;
    }

    private static function fetchOpenAiModels(string $provider, array $def, string $key): array
    {
        if ($provider === 'openrouter') {
            // La liste des modèles d'OpenRouter est publique : on vérifie la clé à part.
            self::assertOk(Http::request('GET', $def['base'] . '/key', ['Authorization' => 'Bearer ' . $key], null, 20));
        }
        $r = Http::request('GET', $def['base'] . '/models', ['Authorization' => 'Bearer ' . $key], null, 30);
        self::assertOk($r);
        $out = [];
        foreach ($r['json']['data'] ?? [] as $m) {
            $id = (string) ($m['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $skip = match ($provider) {
                'openai'  => !preg_match('/^(gpt-|o\d|chatgpt-)/', $id) || preg_match('/(embedding|whisper|tts|dall-e|audio|realtime|transcribe|image|search|moderation|instruct|computer-use)/', $id),
                'mistral' => (isset($m['capabilities']['completion_chat']) && !$m['capabilities']['completion_chat']) || preg_match('/(embed|moderation|ocr)/', $id),
                'groq'    => (bool) preg_match('/(whisper|guard|tts|playai|orpheus)/i', $id),
                'xai'     => (bool) preg_match('/(image|imagine|vision-beta)/i', $id),
                default   => false,
            };
            if ($skip) {
                continue;
            }
            $out[$id] = ['id' => $id, 'name' => (string) ($m['name'] ?? $id), 'created' => (int) ($m['created'] ?? 0)];
        }
        return array_values($out);
    }

    // ------------------------------------------------------------------ transcription des vocaux (optionnelle, OpenAI)

    public static function transcribe(string $file, string $mime): ?string
    {
        $c = self::config();
        if (empty($c['transcription']['enabled']) || !self::hasKey('openai')) {
            return null;
        }
        $ch = curl_init(self::PROVIDERS['openai']['base'] . '/audio/transcriptions');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 90,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . Secrets::get(self::keyName('openai'))],
            CURLOPT_POSTFIELDS     => [
                'file'     => new \CURLFile($file, $mime, 'vocal.' . (str_contains($mime, 'mp4') ? 'mp4' : (str_contains($mime, 'ogg') ? 'ogg' : 'webm'))),
                'model'    => (string) ($c['transcription']['model'] ?: 'whisper-1'),
                'language' => 'fr',
            ],
        ]);
        $resp = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $j = is_string($resp) ? json_decode($resp, true) : null;
        if ($status !== 200 || empty($j['text'])) {
            Store::appendLog('ai', ['feature' => 'transcription', 'ok' => false, 'status' => $status]);
            return null;
        }
        return trim((string) $j['text']);
    }

    // ------------------------------------------------------------------ utilitaires

    /** Extrait le premier objet JSON d'une réponse texte. */
    public static function parseJson(string $raw): ?array
    {
        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }
        $data = json_decode(substr($raw, $start, $end - $start + 1), true);
        return is_array($data) ? $data : null;
    }

    private static function todayCalls(): int
    {
        $u = Store::read('data/ai-usage.json', []);
        return (int) ($u[date('Y-m-d')]['calls'] ?? 0);
    }

    private static function track(string $feature, string $provider, string $model, bool $ok, int $in, int $out, float $secs, ?string $error = null): void
    {
        $day = date('Y-m-d');
        Store::update('data/ai-usage.json', static function (array &$u) use ($day, $feature, $ok, $in, $out): void {
            $d = $u[$day] ?? ['calls' => 0, 'errors' => 0, 'in' => 0, 'out' => 0, 'features' => []];
            $d['calls']++;
            if (!$ok) {
                $d['errors']++;
            }
            $d['in'] += $in;
            $d['out'] += $out;
            $d['features'][$feature] = ($d['features'][$feature] ?? 0) + 1;
            $u[$day] = $d;
            ksort($u);
            $u = array_slice($u, -90, null, true);
        });
        Store::appendLog('ai', ['feature' => $feature, 'provider' => $provider, 'model' => $model, 'ok' => $ok, 'in' => $in, 'out' => $out, 'secs' => round($secs, 2), 'error' => $error]);
    }
}
