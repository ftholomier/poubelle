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
    private const HOST = 'https://generativelanguage.googleapis.com/';
    private const BASE = self::HOST . 'v1beta/';
    private const MODELS_CACHE = STORAGE_PATH . '/cache/gemini-models.json';
    public const FALLBACK_MODEL = 'gemini-2.5-flash';

    public static function key(): string
    {
        $key = trim((string) Settings::get('ai.gemini_api_key', ''));
        return $key === '' && self::mock() ? 'essai' : $key;
    }

    /**
     * Adresse d'un faux Gemini pour les tests automatiques : seulement avec le serveur de
     * développement de PHP (« php -S »), jamais sur l'hébergement.
     */
    private static function mock(): ?string
    {
        $url = PHP_SAPI === 'cli-server' ? getenv('GEMINI_MOCK_URL') : false;
        return is_string($url) && preg_match('#^http://127\.0\.0\.1:\d+/$#', $url) ? $url : null;
    }

    /** Faux Gemini des essais automatiques (serveur de développement seulement) ? */
    public static function isMock(): bool
    {
        return self::mock() !== null;
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
        $empty = ['generate' => [], 'embed' => [], 'tts' => [], 'at' => null, 'error' => null];
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
                if (in_array('generateContent', $methods, true) && str_contains($id, 'tts')) {
                    $out['tts'][$id] = $label;
                }
            }
            uksort($out['generate'], [self::class, 'rank']);
            uksort($out['embed'], [self::class, 'rank']);
            uksort($out['tts'], [self::class, 'rank']);
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
     * $opt['for'] : usage facturé (assistant, traduction, correcteur…), $opt['ref'] : fiche
     * concernée ; chaque réponse est comptée dans les coûts IA (App\Services\AiCosts).
     * @return array{text:string,finish:string,tokens_in:int,tokens_out:int,model:string}
     */
    public static function generate(array $contents, ?string $system = null, array $opt = []): array
    {
        $model = $opt['model'] ?? self::model();
        $maxOut = (int) ($opt['max_tokens'] ?? 800);
        $body = self::body($contents, $system, $opt, $model);
        $path = 'models/' . rawurlencode($model) . ':generateContent';
        try {
            $r = self::request('POST', $path, $body, (int) ($opt['timeout'] ?? 45));
        } catch (\RuntimeException $e) {
            if (isset($body['generationConfig']['thinkingConfig']) && preg_match('/think/i', $e->getMessage())) {
                unset($body['generationConfig']['thinkingConfig']);
            } elseif (isset($body['generationConfig']['responseSchema']) && preg_match('/schema/i', $e->getMessage())) {
                unset($body['generationConfig']['responseSchema']);
            } else {
                throw $e;
            }
            $r = self::request('POST', $path, $body, (int) ($opt['timeout'] ?? 45));
        }
        AiCosts::record((string) ($opt['for'] ?? 'autre'), $model, AiCosts::usage($r), $opt['ref'] ?? null);
        $out = self::parse($r, $model);
        if ($out['text'] === '' && $out['finish'] === 'MAX_TOKENS' && empty($opt['_retry'])) {
            // Le raisonnement interne a consommé le budget : on réessaie avec plus de marge.
            return self::generate($contents, $system, ['max_tokens' => $maxOut * 4, '_retry' => true] + $opt);
        }
        return $out;
    }

    /**
     * Plusieurs générations en parallèle (correcteur : une fiche longue en plusieurs morceaux).
     * $jobs : liste de [contents, system, opt]. Renvoie, dans le même ordre, le résultat de
     * generate() ou ['error' => message, 'code' => code HTTP].
     */
    public static function generateMany(array $jobs): array
    {
        $out = [];
        $handles = [];
        $mh = curl_multi_init();
        foreach (array_values($jobs) as $i => [$contents, $system, $opt]) {
            $model = $opt['model'] ?? self::model();
            $ch = self::handle('POST', 'models/' . rawurlencode($model) . ':generateContent', self::body($contents, $system, $opt, $model), (int) ($opt['timeout'] ?? 60));
            curl_multi_add_handle($mh, $ch);
            $handles[$i] = [$ch, $model];
        }
        do {
            $status = curl_multi_exec($mh, $running);
            if ($running) {
                curl_multi_select($mh, 1.0);
            }
        } while ($running && $status === CURLM_OK);
        foreach ($handles as $i => [$ch, $model]) {
            $raw = curl_multi_getcontent($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
            try {
                $r = self::decode($raw === false || $raw === null ? false : (string) $raw, $code, $err);
                $opt = array_values($jobs)[$i][2] ?? [];
                AiCosts::record((string) ($opt['for'] ?? 'autre'), $model, AiCosts::usage($r), $opt['ref'] ?? null);
                $res = self::parse($r, $model);
                if ($res['text'] === '' && $res['finish'] === 'MAX_TOKENS') {
                    throw new \RuntimeException('réponse tronquée');
                }
                $out[$i] = $res;
            } catch (\RuntimeException $e) {
                // Erreur passagère ou option refusée par le modèle : un nouvel essai, seul.
                if ($e->getCode() === 429 || $e->getCode() === 401 || $e->getCode() === 403) {
                    $out[$i] = ['error' => $e->getMessage(), 'code' => $e->getCode()];
                    continue;
                }
                try {
                    [$contents, $system, $opt] = array_values($jobs)[$i];
                    $out[$i] = self::generate($contents, $system, $opt);
                } catch (\Throwable $e2) {
                    $out[$i] = ['error' => $e2->getMessage(), 'code' => $e2->getCode()];
                }
            }
        }
        curl_multi_close($mh);
        ksort($out);
        return $out;
    }

    /** Corps d'une demande de texte (traitement groupé : une ligne du fichier JSONL). */
    public static function requestBody(array $contents, ?string $system, array $opt = [], ?string $model = null): array
    {
        return self::body($contents, $system, $opt, $model ?? self::model());
    }

    /** Texte d'une réponse (hors raisonnement interne). */
    public static function responseText(array $r): string
    {
        return self::parse($r, '')['text'];
    }

    /** Corps d'une requête de génération. $opt['schema'] : structure JSON imposée à la réponse. */
    private static function body(array $contents, ?string $system, array $opt, string $model): array
    {
        $body = [
            'contents' => array_map(fn ($c) => ['role' => $c['role'] === 'model' ? 'model' : 'user', 'parts' => [['text' => (string) $c['text']]]], $contents),
            'generationConfig' => ['temperature' => (float) ($opt['temperature'] ?? 0.3), 'maxOutputTokens' => (int) ($opt['max_tokens'] ?? 800)],
            'safetySettings' => array_map(fn ($c) => ['category' => $c, 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'], ['HARM_CATEGORY_HARASSMENT', 'HARM_CATEGORY_HATE_SPEECH', 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'HARM_CATEGORY_DANGEROUS_CONTENT']),
        ];
        if ($system) {
            $body['systemInstruction'] = ['parts' => [['text' => $system]]];
        }
        if (!empty($opt['json'])) {
            $body['generationConfig']['responseMimeType'] = 'application/json';
            if (!empty($opt['schema'])) {
                $body['generationConfig']['responseSchema'] = $opt['schema'];
            }
        }
        // Raisonnement réduit au minimum : réponses rapides, jetons réservés au texte.
        if (preg_match('/gemini-2\.5-flash/', $model)) {
            $body['generationConfig']['thinkingConfig'] = ['thinkingBudget' => 0];
        } elseif (preg_match('/gemini-([3-9])/', $model)) {
            $body['generationConfig']['thinkingConfig'] = ['thinkingLevel' => 'low'];
        }
        return $body;
    }

    /** Texte de la réponse (hors raisonnement interne) et consommation. */
    private static function parse(array $r, string $model): array
    {
        $cand = $r['candidates'][0] ?? [];
        $text = '';
        foreach ($cand['content']['parts'] ?? [] as $p) {
            if (empty($p['thought'])) {
                $text .= (string) ($p['text'] ?? '');
            }
        }
        return [
            'text' => trim($text),
            'finish' => (string) ($cand['finishReason'] ?? ($r['promptFeedback']['blockReason'] ?? '')),
            'tokens_in' => (int) ($r['usageMetadata']['promptTokenCount'] ?? 0),
            'tokens_out' => (int) ($r['usageMetadata']['candidatesTokenCount'] ?? 0),
            'model' => $model,
        ];
    }

    /**
     * Embeddings d'une liste de textes (par lots de 100).
     * $task : RETRIEVAL_DOCUMENT ou RETRIEVAL_QUERY. Dimension réduite (256) si le modèle l'accepte.
     * $for : usage facturé (« index » pour l'indexation, « assistant » pour une question).
     * @return list<list<float>>
     */
    public static function embed(array $texts, string $task = 'RETRIEVAL_DOCUMENT', ?string $model = null, int $dim = 256, string $for = 'index'): array
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
            // Google n'indique pas toujours les jetons d'un embedding : estimation (4 caractères par jeton).
            $usage = AiCosts::usage($r);
            if ($usage['in'] <= 0) {
                $usage = ['in' => (int) ceil(array_sum(array_map(fn ($t) => mb_strlen(mb_substr((string) $t, 0, 8000)), $batch)) / 4), 'est' => true];
            }
            AiCosts::record($for, $model, $usage);
            foreach ($r['embeddings'] ?? [] as $em) {
                $out[] = array_map('floatval', $em['values'] ?? []);
            }
        }
        return $out;
    }

    // ------------------------------------------------------------------ voix (synthèse vocale)

    /** Modèle de voix réglé (Réglages › Fiches audio), sinon le meilleur disponible. */
    public static function ttsModel(): string
    {
        $m = trim((string) Settings::get('audio.tts_model', ''));
        if ($m !== '') {
            return $m;
        }
        $list = array_keys(self::models()['tts'] ?? []);
        foreach ($list as $id) {
            if (str_contains($id, 'flash')) {
                return $id;
            }
        }
        return $list[0] ?? 'gemini-2.5-flash-preview-tts';
    }

    /** Corps d'une demande de voix : consigne de ton puis texte, voix choisie. */
    public static function speechRequest(string $text, string $voice, string $style = ''): array
    {
        return [
            'contents' => [['role' => 'user', 'parts' => [['text' => ($style !== '' ? rtrim($style, ' :.') . ' : ' : '') . $text]]]],
            'generationConfig' => [
                'responseModalities' => ['AUDIO'],
                'speechConfig' => ['voiceConfig' => ['prebuiltVoiceConfig' => ['voiceName' => $voice]]],
            ],
        ];
    }

    /**
     * Audio d'une réponse de voix : ['pcm' => octets bruts (16 bits, mono), 'rate' => fréquence].
     * Google renvoie du PCM « audio/L16;codec=pcm;rate=24000 », parfois en plusieurs morceaux mis
     * bout à bout ici ; tout ce qui n'est pas de l'audio (texte) est laissé de côté.
     */
    public static function speechAudio(array $r): ?array
    {
        $pcm = '';
        $rate = $mime = null;
        foreach ($r['candidates'][0]['content']['parts'] ?? [] as $p) {
            $d = $p['inlineData'] ?? $p['inline_data'] ?? null;
            if (!is_array($d) || empty($d['data'])) {
                continue;
            }
            $m = (string) ($d['mimeType'] ?? $d['mime_type'] ?? '');
            if ($m !== '' && !str_starts_with($m, 'audio/')) {
                continue;
            }
            $r1 = preg_match('/rate=(\d+)/', $m, $mm) ? (int) $mm[1] : 24000;
            $chunk = base64_decode((string) $d['data'], true);
            if ($chunk === false || $chunk === '' || ($rate !== null && $r1 !== $rate)) {
                continue;
            }
            $rate ??= $r1;
            $mime ??= $m;
            $pcm .= strlen($chunk) % 2 ? substr($chunk, 0, -1) : $chunk;
        }
        return $pcm === '' ? null : ['pcm' => $pcm, 'rate' => $rate, 'mime' => $mime];
    }

    /** Lit un texte à voix haute (une demande, tarif normal) ; coût compté (usage « audio »). */
    public static function speech(string $text, string $voice, string $style = '', ?string $ref = null): array
    {
        $model = self::ttsModel();
        $r = self::request('POST', 'models/' . rawurlencode($model) . ':generateContent', self::speechRequest($text, $voice, $style), 300);
        AiCosts::record('audio', $model, AiCosts::usage($r), $ref);
        $a = self::speechAudio($r);
        if (!$a) {
            throw new \RuntimeException('Gemini : aucune voix dans la réponse.');
        }
        return $a + ['model' => $model];
    }

    // ------------------------------------------------------------------ traitement groupé (API Batch)

    /** Envoie un fichier (JSONL des demandes) à Google ; renvoie son nom (« files/… »). */
    public static function uploadFile(string $path, string $mime, string $display): string
    {
        $size = (int) filesize($path);
        $ch = curl_init((self::mock() ?? self::HOST) . 'upload/v1beta/files');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 60, CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['x-goog-api-key: ' . self::key(), 'X-Goog-Upload-Protocol: resumable', 'X-Goog-Upload-Command: start',
                'X-Goog-Upload-Header-Content-Length: ' . $size, 'X-Goog-Upload-Header-Content-Type: ' . $mime, 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode(['file' => ['display_name' => $display]]),
        ]);
        $raw = (string) curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $head = substr($raw, 0, (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE));
        curl_close($ch);
        if (!preg_match('/^x-goog-upload-url:\s*(\S+)/mi', $head, $m)) {
            throw new \RuntimeException("Gemini : envoi du fichier refusé (HTTP $code).");
        }
        $ch = curl_init(trim($m[1]));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 300, CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Content-Length: ' . $size, 'X-Goog-Upload-Offset: 0', 'X-Goog-Upload-Command: upload, finalize'],
            CURLOPT_POSTFIELDS => (string) file_get_contents($path),
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        $r = self::decode($raw === false ? false : (string) $raw, $code, $err);
        $name = (string) ($r['file']['name'] ?? '');
        if ($name === '') {
            throw new \RuntimeException('Gemini : fichier envoyé sans nom en retour.');
        }
        return $name;
    }

    /** Crée un traitement groupé à partir d'un fichier envoyé ; renvoie son nom (« batches/… »). */
    public static function batchCreate(string $model, string $file, string $display): string
    {
        $r = self::request('POST', 'models/' . rawurlencode($model) . ':batchGenerateContent', ['batch' => ['display_name' => $display, 'input_config' => ['file_name' => $file]]], 60);
        $name = (string) ($r['name'] ?? '');
        if (!str_starts_with($name, 'batches/')) {
            throw new \RuntimeException('Gemini : traitement groupé refusé.');
        }
        return $name;
    }

    /**
     * État d'un traitement groupé : ['state' => pending|running|succeeded|failed|cancelled|expired,
     * 'file' => fichier des résultats ou null, 'error' => message].
     */
    public static function batchGet(string $name): array
    {
        $r = self::request('GET', $name);
        $raw = strtolower((string) ($r['metadata']['state'] ?? $r['state'] ?? ''));
        $state = 'pending';
        foreach (['succeeded', 'failed', 'cancelled', 'expired', 'running', 'pending'] as $s) {
            if (str_ends_with($raw, $s)) {
                $state = $s;
                break;
            }
        }
        $file = $r['response']['responsesFile'] ?? $r['metadata']['output']['responsesFile'] ?? $r['output']['responsesFile'] ?? null;
        return ['state' => $state, 'file' => $file ? (string) $file : null, 'error' => (string) ($r['error']['message'] ?? '')];
    }

    public static function batchCancel(string $name): void
    {
        self::request('POST', $name . ':cancel', []);
    }

    /** Télécharge un fichier de résultats vers $dest (sans le charger en mémoire). */
    public static function download(string $file, string $dest): int
    {
        $fp = fopen($dest, 'wb');
        if (!$fp) {
            throw new \RuntimeException('Écriture impossible : ' . $dest);
        }
        $ch = curl_init((self::mock() ?? self::HOST) . 'download/v1beta/' . $file . ':download?alt=media');
        curl_setopt_array($ch, [CURLOPT_FILE => $fp, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 600, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_HTTPHEADER => ['x-goog-api-key: ' . self::key()]]);
        $ok = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        fclose($fp);
        if (!$ok || $code >= 400) {
            @unlink($dest);
            throw new \RuntimeException('Gemini : téléchargement des résultats impossible (' . ($err ?: "HTTP $code") . ').');
        }
        return (int) filesize($dest);
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
        $ch = self::handle($method, $path, $body, $timeout);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        return self::decode($raw === false ? false : (string) $raw, $code, $err);
    }

    /** @return \CurlHandle */
    private static function handle(string $method, string $path, ?array $body, int $timeout)
    {
        $ch = curl_init((self::mock() ?? self::BASE) . $path);
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
        return $ch;
    }

    private static function decode(string|false $raw, int $code, string $err): array
    {
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
