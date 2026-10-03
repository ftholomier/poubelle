<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\JsonStore;
use App\Core\Settings;

/**
 * Client de l'API Gemini (Google AI Studio) : liste dynamique des modèles disponibles
 * pour la clé, génération de texte (assistant, traductions) et embeddings (recherche
 * sémantique de l'assistant). La clé est lue dans les réglages chiffrés et transmise
 * en en-tête HTTP, jamais dans l'adresse ni au navigateur.
 */
final class Gemini
{
    private const BASE = 'https://generativelanguage.googleapis.com/v1beta/';
    private const MODELS_CACHE = STORAGE_PATH . '/cache/gemini-models.json';
    public const FALLBACK_MODEL = 'gemini-2.5-flash';

    public static function key(): string
    {
        return trim((string) Settings::get('ai.gemini_api_key', ''));
    }

    public static function ready(): bool
    {
        return self::key() !== '';
    }

    /**
     * Modèles accessibles avec la clé, classés : ['generate' => [id => libellé], 'embed' => [id => libellé]].
     * Mis en cache 24 h (ou rechargés à la demande depuis les réglages).
     */
    public static function models(bool $refresh = false): array
    {
        $empty = ['generate' => [], 'embed' => [], 'at' => null, 'error' => null];
        if (!self::ready()) {
            return $empty + ['error' => 'Aucune clé API Gemini enregistrée.'];
        }
        $keyHash = substr(hash('sha256', self::key()), 0, 12);
        $cache = JsonStore::read(self::MODELS_CACHE, null);
        if (!$refresh && is_array($cache) && ($cache['key'] ?? '') === $keyHash && ($cache['ts'] ?? 0) > time() - 86400 && empty($cache['error'])) {
            return $cache;
        }
        $out = $empty;
        try {
            $token = '';
            $all = [];
            do {
                $r = self::request('GET', 'models?pageSize=1000' . ($token !== '' ? '&pageToken=' . rawurlencode($token) : ''));
                $all = array_merge($all, $r['models'] ?? []);
                $token = (string) ($r['nextPageToken'] ?? '');
            } while ($token !== '' && count($all) < 5000);
            foreach ($all as $m) {
                $id = preg_replace('#^models/#', '', (string) ($m['name'] ?? ''));
                $methods = $m['supportedGenerationMethods'] ?? [];
                $label = trim(($m['displayName'] ?? $id) . ($id !== ($m['displayName'] ?? '') ? " ($id)" : ''));
                if (in_array('generateContent', $methods, true) && !preg_match('/(image|tts|audio|live|embedding|aqa|veo|imagen|robotics|computer-use)/i', $id)) {
                    $out['generate'][$id] = $label;
                }
                if (in_array('embedContent', $methods, true) || in_array('batchEmbedContents', $methods, true)) {
                    $out['embed'][$id] = $label;
                }
            }
            uksort($out['generate'], [self::class, 'rank']);
            uksort($out['embed'], [self::class, 'rank']);
        } catch (\Throwable $e) {
            $out['error'] = $e->getMessage();
        }
        $out['at'] = date('c');
        JsonStore::write(self::MODELS_CACHE, $out + ['key' => $keyHash, 'ts' => time()]);
        return $out;
    }

    /** Ordre d'affichage : versions récentes d'abord, « flash » avant « pro », préversions en dernier. */
    private static function rank(string $a, string $b): int
    {
        $score = function (string $id): array {
            preg_match('/(\d+(?:\.\d+)?)/', $id, $m);
            $v = (float) ($m[1] ?? 0);
            $pre = preg_match('/(preview|exp|experimental|\d{2}-\d{2})/i', $id) ? 1 : 0;
            return [$pre, -$v, str_contains($id, 'flash') ? 0 : 1, str_contains($id, 'lite') ? 1 : 0, $id];
        };
        return $score($a) <=> $score($b);
    }

    /** Modèle de génération réglé, ou le meilleur disponible. */
    public static function model(): string
    {
        $m = trim((string) Settings::get('ai.model', ''));
        if ($m !== '') {
            return $m;
        }
        $list = self::models()['generate'] ?? [];
        foreach (array_keys($list) as $id) {
            if (str_contains($id, 'flash') && !str_contains($id, 'lite')) {
                return $id;
            }
        }
        return (string) (array_key_first($list) ?? self::FALLBACK_MODEL);
    }

    public static function embedModel(): ?string
    {
        $m = trim((string) Settings::get('ai.embedding_model', ''));
        return $m !== '' ? $m : null;
    }

    /**
     * Génère une réponse. $contents : [['role' => 'user'|'model', 'text' => '…'], …].
     * @return array{text:string,finish:string,tokens_in:int,tokens_out:int,model:string}
     */
    public static function generate(array $contents, ?string $system = null, array $opt = []): array
    {
        $model = $opt['model'] ?? self::model();
        $maxOut = (int) ($opt['max_tokens'] ?? 800);
        $body = [
            'contents' => array_map(fn ($c) => ['role' => $c['role'] === 'model' ? 'model' : 'user', 'parts' => [['text' => (string) $c['text']]]], $contents),
            'generationConfig' => ['temperature' => (float) ($opt['temperature'] ?? 0.3), 'maxOutputTokens' => $maxOut],
            'safetySettings' => array_map(fn ($c) => ['category' => $c, 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'], ['HARM_CATEGORY_HARASSMENT', 'HARM_CATEGORY_HATE_SPEECH', 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'HARM_CATEGORY_DANGEROUS_CONTENT']),
        ];
        if ($system) {
            $body['systemInstruction'] = ['parts' => [['text' => $system]]];
        }
        if (!empty($opt['json'])) {
            $body['generationConfig']['responseMimeType'] = 'application/json';
        }
        // Raisonnement réduit au minimum : réponses rapides, jetons réservés au texte.
        if (preg_match('/gemini-2\.5-flash/', $model)) {
            $body['generationConfig']['thinkingConfig'] = ['thinkingBudget' => 0];
        } elseif (preg_match('/gemini-([3-9])/', $model)) {
            $body['generationConfig']['thinkingConfig'] = ['thinkingLevel' => 'low'];
        }
        $path = 'models/' . rawurlencode($model) . ':generateContent';
        try {
            $r = self::request('POST', $path, $body, (int) ($opt['timeout'] ?? 45));
        } catch (\RuntimeException $e) {
            if (!isset($body['generationConfig']['thinkingConfig']) || !preg_match('/think/i', $e->getMessage())) {
                throw $e;
            }
            unset($body['generationConfig']['thinkingConfig']);
            $r = self::request('POST', $path, $body, (int) ($opt['timeout'] ?? 45));
        }
        $cand = $r['candidates'][0] ?? [];
        $text = '';
        foreach ($cand['content']['parts'] ?? [] as $p) {
            if (empty($p['thought'])) {
                $text .= (string) ($p['text'] ?? '');
            }
        }
        $finish = (string) ($cand['finishReason'] ?? ($r['promptFeedback']['blockReason'] ?? ''));
        if ($text === '' && $finish === 'MAX_TOKENS' && empty($opt['_retry'])) {
            // Le raisonnement interne a consommé le budget : on réessaie avec plus de marge.
            return self::generate($contents, $system, ['max_tokens' => $maxOut * 4, '_retry' => true] + $opt);
        }
        return [
            'text' => trim($text),
            'finish' => $finish,
            'tokens_in' => (int) ($r['usageMetadata']['promptTokenCount'] ?? 0),
            'tokens_out' => (int) ($r['usageMetadata']['candidatesTokenCount'] ?? 0),
            'model' => $model,
        ];
    }

    /**
     * Embeddings d'une liste de textes (par lots de 100).
     * $task : RETRIEVAL_DOCUMENT ou RETRIEVAL_QUERY. Dimension réduite (256) si le modèle l'accepte.
     * @return list<list<float>>
     */
    public static function embed(array $texts, string $task = 'RETRIEVAL_DOCUMENT', ?string $model = null, int $dim = 256): array
    {
        $model ??= self::embedModel() ?? 'text-embedding-004';
        $out = [];
        foreach (array_chunk(array_values($texts), 100) as $batch) {
            $req = fn (bool $withDim) => ['requests' => array_map(fn ($t) => ['model' => 'models/' . $model, 'content' => ['parts' => [['text' => mb_substr((string) $t, 0, 8000)]]], 'taskType' => $task] + ($withDim ? ['outputDimensionality' => $dim] : []), $batch)];
            try {
                $r = self::request('POST', 'models/' . rawurlencode($model) . ':batchEmbedContents', $req(true), 60);
            } catch (\RuntimeException $e) {
                if (!preg_match('/dimensional/i', $e->getMessage())) {
                    throw $e;
                }
                $r = self::request('POST', 'models/' . rawurlencode($model) . ':batchEmbedContents', $req(false), 60);
            }
            foreach ($r['embeddings'] ?? [] as $em) {
                $out[] = array_map('floatval', $em['values'] ?? []);
            }
        }
        return $out;
    }

    /** Vérifie la clé (page Réglages) : renvoie null si tout va bien, sinon le message d'erreur. */
    public static function check(): ?string
    {
        try {
            $m = self::models(true);
            return $m['error'] ?? (empty($m['generate']) ? 'Aucun modèle de génération disponible pour cette clé.' : null);
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    private static function request(string $method, string $path, ?array $body = null, int $timeout = 30): array
    {
        $ch = curl_init(self::BASE . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . self::key()],
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false || $code === 0) {
            throw new \RuntimeException('Gemini injoignable : ' . $err);
        }
        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            throw new \RuntimeException("Gemini : réponse illisible (HTTP $code)");
        }
        if ($code >= 400) {
            $msg = (string) ($data['error']['message'] ?? "erreur HTTP $code");
            throw new \RuntimeException(match (true) {
                $code === 400 && str_contains($msg, 'API key') => 'Gemini : clé API invalide.',
                $code === 403 => 'Gemini : accès refusé (' . $msg . ')',
                $code === 429 => 'Gemini : quota atteint, réessayez plus tard.',
                default => 'Gemini : ' . $msg,
            }, $code);
        }
        return $data;
    }
}
