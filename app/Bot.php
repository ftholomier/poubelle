<?php
declare(strict_types=1);

/**
 * Assistant conversationnel adossé à l'API Gemini.
 *
 * Le bot ne « sait » rien de lui-même : à chaque question, on assemble une
 * base de connaissances (contenu du site, actualités, documents déposés,
 * consignes libres saisies dans le back-office), on en sélectionne les
 * passages pertinents, et on les fournit au modèle comme seule source
 * autorisée. La clé API ne quitte jamais le serveur.
 */
final class Bot
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta';
    private const DOC_DIR = 'bot';
    /** Budget de contexte transmis au modèle, en caractères. */
    private const CONTEXT_BUDGET = 14000;

    public static function defaults(): array
    {
        return [
            'enabled' => false,
            'api_key' => '',
            'model' => 'models/gemini-2.0-flash',
            'models' => [],
            'models_fetched_at' => '',
            'name' => 'Léa',
            'role' => 'assistante recrutement Suisse Immo',
            'greeting' => 'Bonjour ! Je réponds à vos questions sur le métier d’agent commercial immobilier et sur le réseau Suisse Immo. Que souhaitez-vous savoir ?',
            'temperature' => 0.35,
            'max_tokens' => 700,
            'persona' => "Tu es {name}, {role}.\nTon rôle est de renseigner les personnes qui envisagent de devenir agent commercial immobilier indépendant chez Suisse Immo, puis de les inviter à candidater.\n\nRègles :\n- Réponds uniquement à partir des informations fournies dans la base de connaissances ci-dessous.\n- Si l'information ne s'y trouve pas, dis-le simplement et propose de contacter l'équipe ou de candidater pour en parler de vive voix. N'invente jamais un chiffre, un taux de commission ou une condition contractuelle.\n- Réponds en français, sur un ton chaleureux et direct, en 4 phrases maximum sauf si la question exige plus.\n- Termine par une invitation concrète quand c'est pertinent (candidater, simuler ses revenus, appeler l'agence).",
            'sources' => ['content' => true, 'posts' => true, 'company' => true, 'documents' => true, 'notes' => true],
            'notes' => "",
            'suggestions' => [
                'Faut-il un diplôme pour commencer ?',
                'Quel statut aurai-je ?',
                'Comment suis-je rémunéré ?',
                'Sur quels secteurs recrutez-vous ?',
            ],
        ];
    }

    public static function config(): array
    {
        return array_replace(self::defaults(), Store::read('bot'));
    }

    public static function save(array $patch): array
    {
        $cfg = array_replace(self::config(), $patch);
        Store::write('bot', $cfg);
        return $cfg;
    }

    public static function isReady(): bool
    {
        $c = self::config();
        return !empty($c['enabled']) && trim((string) $c['api_key']) !== '' && trim((string) $c['model']) !== '';
    }

    // ------------------------------------------------------------- modèles

    /** Liste les modèles Gemini disponibles pour la clé configurée. */
    public static function fetchModels(?string $apiKey = null): array
    {
        $key = trim((string) ($apiKey ?? self::config()['api_key']));
        if ($key === '') {
            return ['ok' => false, 'error' => 'Renseignez d’abord une clé API Gemini.'];
        }
        $res = self::http('GET', self::ENDPOINT . '/models?pageSize=200&key=' . rawurlencode($key));
        if (!$res['ok']) {
            return $res;
        }
        $models = [];
        foreach ($res['data']['models'] ?? [] as $m) {
            $methods = $m['supportedGenerationMethods'] ?? [];
            if (!in_array('generateContent', $methods, true)) {
                continue;
            }
            $models[] = [
                'id' => (string) ($m['name'] ?? ''),
                'label' => (string) ($m['displayName'] ?? $m['name'] ?? ''),
                'description' => mb_substr((string) ($m['description'] ?? ''), 0, 200),
                'input' => (int) ($m['inputTokenLimit'] ?? 0),
                'output' => (int) ($m['outputTokenLimit'] ?? 0),
            ];
        }
        usort($models, static fn ($a, $b) => strcmp($a['label'], $b['label']));
        return ['ok' => true, 'models' => $models];
    }

    // ------------------------------------------------- base de connaissances

    /**
     * Découpe toutes les sources activées en fragments titrés.
     * @return array<int,array{source:string,title:string,text:string}>
     */
    public static function knowledge(): array
    {
        $cfg = self::config();
        $on = $cfg['sources'] ?? [];
        $chunks = [];

        // Chaque source porte un poids : les consignes saisies à la main
        // priment sur le contenu du site, qui prime sur les actualités.
        $push = static function (string $source, string $title, string $text, float $weight = 1.0, bool $pinned = false) use (&$chunks): void {
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
            if (mb_strlen($text) < 30) { return; }
            foreach (self::split($text, 1200) as $part) {
                $chunks[] = ['source' => $source, 'title' => $title, 'text' => $part, 'weight' => $weight, 'pinned' => $pinned];
            }
        };

        if (!empty($on['content'])) {
            foreach (Store::read('content') as $section => $value) {
                $label = ContentSchema::all()[$section]['label'] ?? $section;
                $push('Site — ' . $label, $label, self::flatten($value), 1.6);
            }
        }

        if (!empty($on['company'])) {
            $c = (array) settings('company');
            $push('Coordonnées', 'Contact et mentions légales', sprintf(
                '%s, %s au capital de %s. Adresse : %s, %s %s. Téléphone : %s. E-mail : %s. SIRET : %s. RCS : %s. TVA : %s. Assurance RCP : %s. Délai de réponse annoncé aux candidats : %s.',
                $c['legal_name'] ?? '', $c['form'] ?? '', $c['capital'] ?? '',
                $c['address'] ?? '', $c['zip'] ?? '', $c['city'] ?? '',
                $c['phone'] ?? '', $c['email'] ?? '', $c['siret'] ?? '',
                $c['rcs'] ?? '', $c['vat'] ?? '', $c['insurance'] ?? '',
                settings('funnel.response_delay', '48 heures ouvrées')
            ), 2.0);
        }

        if (!empty($on['posts'])) {
            foreach (SiteController::published() as $post) {
                $push(
                    'Actualité — ' . ($post['title'] ?? ''),
                    (string) ($post['title'] ?? ''),
                    ($post['excerpt'] ?? '') . ' ' . strip_tags((string) ($post['body'] ?? '')),
                    0.6
                );
            }
        }

        if (!empty($on['documents'])) {
            foreach (self::documents() as $doc) {
                $file = DATA_DIR . '/uploads/' . self::DOC_DIR . '/' . basename((string) ($doc['text_file'] ?? ''));
                if (is_file($file)) {
                    $push('Document — ' . ($doc['name'] ?? ''), (string) ($doc['name'] ?? ''), (string) file_get_contents($file), 1.8);
                }
            }
        }

        if (!empty($on['notes']) && trim((string) $cfg['notes']) !== '') {
            // Épinglées : toujours transmises, quelle que soit la question.
            $push('Consignes internes', 'Informations complémentaires', (string) $cfg['notes'], 3.0, true);
        }

        return $chunks;
    }

    /** Clés purement techniques : leur nom n'apporte rien au modèle. */
    private const SKIP_KEYS = ['icon', 'key', 'n', 'suffix', 'badge', 'eyebrow', 'columns', 'step', 'min', 'max', 'default', 'rotating'];
    /** Clés dont le nom éclaire vraiment la valeur. */
    private const LABEL_KEYS = [
        'q' => 'Question', 'a' => 'Réponse',
        'pain' => 'Frustration exprimée', 'gain' => 'Réponse de Suisse Immo',
        'duration' => 'Durée', 'rate' => 'Part reversée (%)', 'from' => 'À partir de',
        'to' => 'Jusqu’à', 'author' => 'Auteur', 'rating' => 'Note',
        'agency_fee_rate' => 'Honoraires d’agence en % du prix de vente',
        'disclaimer' => 'Mention légale',
    ];

    /** Aplatit une structure de contenu en texte lisible par le modèle. */
    private static function flatten(mixed $value, int $depth = 0): string
    {
        if (is_scalar($value)) {
            return (string) $value;
        }
        if (!is_array($value) || $depth > 6) {
            return '';
        }
        $parts = [];
        foreach ($value as $k => $v) {
            if (is_string($k) && in_array($k, self::SKIP_KEYS, true)) { continue; }
            $flat = self::flatten($v, $depth + 1);
            if ($flat === '') { continue; }
            if (is_string($k) && isset(self::LABEL_KEYS[$k])) {
                $parts[] = self::LABEL_KEYS[$k] . ' : ' . $flat;
            } else {
                $parts[] = $flat;
            }
        }
        return implode('. ', $parts);
    }

    /** @return array<int,string> */
    private static function split(string $text, int $size): array
    {
        if (mb_strlen($text) <= $size) {
            return [$text];
        }
        $out = [];
        $sentences = preg_split('/(?<=[.!?])\s+/u', $text) ?: [$text];
        $buf = '';
        foreach ($sentences as $sentence) {
            if (mb_strlen($buf) + mb_strlen($sentence) > $size && $buf !== '') {
                $out[] = trim($buf);
                $buf = '';
            }
            $buf .= $sentence . ' ';
        }
        if (trim($buf) !== '') { $out[] = trim($buf); }
        return $out;
    }

    /** Sélection des fragments les plus proches de la question posée. */
    public static function retrieve(string $question, array $chunks): array
    {
        $terms = self::terms($question);
        if (!$terms) {
            return array_slice($chunks, 0, 8);
        }
        $out = [];
        $budget = self::CONTEXT_BUDGET;

        // Les fragments épinglés (consignes internes) passent toujours.
        foreach ($chunks as $chunk) {
            if (empty($chunk['pinned'])) { continue; }
            $len = mb_strlen($chunk['text']);
            if ($len > $budget) { continue; }
            $out[] = $chunk;
            $budget -= $len;
        }

        $scored = [];
        foreach ($chunks as $i => $chunk) {
            if (!empty($chunk['pinned'])) { continue; }
            $hay = ' ' . self::fold($chunk['title'] . ' ' . $chunk['text']) . ' ';
            $score = 0.0;
            foreach ($terms as $term => $weight) {
                $n = substr_count($hay, ' ' . $term);
                if ($n === 0 && mb_strlen($term) > 5) {
                    $n = substr_count($hay, $term) > 0 ? 1 : 0;   // tolère les variantes
                }
                $score += $n * $weight;
            }
            // Un fragment long ne doit pas gagner par simple répétition.
            $score = $score / (1 + mb_strlen($chunk['text']) / 2500) * (float) ($chunk['weight'] ?? 1.0);
            if ($score > 0) {
                $scored[] = ['i' => $i, 'score' => $score, 'chunk' => $chunk];
            }
        }
        usort($scored, static fn ($a, $b) => $b['score'] <=> $a['score'] ?: $a['i'] <=> $b['i']);

        foreach ($scored as $row) {
            $len = mb_strlen($row['chunk']['text']);
            if ($len > $budget) { continue; }
            $out[] = $row['chunk'];
            $budget -= $len;
            if ($budget < 400) { break; }
        }
        // Toujours donner un minimum de contexte, même sans correspondance.
        return $out ?: array_slice($chunks, 0, 6);
    }

    /** @return array<string,float> terme => poids */
    private static function terms(string $q): array
    {
        $stop = ['le','la','les','un','une','des','du','de','et','ou','a','au','aux','en','dans','pour','par','sur','avec','sans','est','sont','que','qui','quoi','quel','quelle','quels','quelles','je','tu','il','elle','on','nous','vous','ils','elles','ce','cet','cette','ces','mon','ma','mes','votre','vos','son','sa','ses','y','ne','pas','plus','moins','tres','bien','comment','combien','pourquoi','faut','il','y','a','me','te','se','mais','donc','or','ni','car','si','the','of'];
        $words = preg_split('/[^\p{L}\p{N}]+/u', self::fold($q)) ?: [];
        $terms = [];
        foreach ($words as $w) {
            if (mb_strlen($w) < 3 || in_array($w, $stop, true)) { continue; }
            $terms[$w] = (float) max(1, mb_strlen($w) - 2);
        }
        return $terms;
    }

    private static function fold(string $s): string
    {
        $s = mb_strtolower($s);
        $s = strtr($s, ['à'=>'a','â'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','î'=>'i','ï'=>'i','ô'=>'o','ö'=>'o','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c','’'=>' ',"'"=>' ']);
        return preg_replace('/[^a-z0-9]+/', ' ', $s) ?? $s;
    }

    // -------------------------------------------------------------- dialogue

    /**
     * @param array<int,array{role:string,text:string}> $history
     * @return array{ok:bool,answer?:string,error?:string,used?:array,model?:string}
     */
    public static function ask(string $question, array $history = []): array
    {
        $cfg = self::config();
        $key = trim((string) $cfg['api_key']);
        if ($key === '') {
            return ['ok' => false, 'error' => 'Aucune clé API Gemini configurée.'];
        }
        $question = mb_substr(trim($question), 0, 1000);
        if ($question === '') {
            return ['ok' => false, 'error' => 'Posez une question.'];
        }

        $chunks = self::retrieve($question, self::knowledge());
        $context = '';
        $used = [];
        foreach ($chunks as $c) {
            $context .= "### " . $c['title'] . "\n" . $c['text'] . "\n\n";
            $used[] = $c['source'];
        }

        $persona = strtr((string) $cfg['persona'], [
            '{name}' => (string) $cfg['name'],
            '{role}' => (string) $cfg['role'],
        ]);
        $system = $persona . "\n\n=== BASE DE CONNAISSANCES ===\n"
            . ($context !== '' ? $context : "(vide)\n")
            . "=== FIN DE LA BASE ===";

        $contents = [];
        foreach (array_slice($history, -8) as $turn) {
            $role = ($turn['role'] ?? '') === 'bot' ? 'model' : 'user';
            $text = mb_substr(trim((string) ($turn['text'] ?? '')), 0, 1500);
            if ($text !== '') {
                $contents[] = ['role' => $role, 'parts' => [['text' => $text]]];
            }
        }
        $contents[] = ['role' => 'user', 'parts' => [['text' => $question]]];

        $model = ltrim((string) $cfg['model'], '/');
        if (!str_starts_with($model, 'models/')) { $model = 'models/' . $model; }

        $res = self::http('POST', self::ENDPOINT . '/' . $model . ':generateContent?key=' . rawurlencode($key), [
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => max(0, min(2, (float) $cfg['temperature'])),
                'maxOutputTokens' => max(64, min(4096, (int) $cfg['max_tokens'])),
                'topP' => 0.95,
            ],
            'safetySettings' => [],
        ]);

        if (!$res['ok']) {
            return $res;
        }
        $answer = '';
        foreach ($res['data']['candidates'][0]['content']['parts'] ?? [] as $part) {
            $answer .= (string) ($part['text'] ?? '');
        }
        $answer = trim($answer);
        if ($answer === '') {
            $reason = (string) ($res['data']['candidates'][0]['finishReason'] ?? 'inconnue');
            return ['ok' => false, 'error' => 'Le modèle n’a rien renvoyé (motif : ' . $reason . ').'];
        }
        return ['ok' => true, 'answer' => $answer, 'used' => array_values(array_unique($used)), 'model' => $model];
    }

    // ------------------------------------------------------------ documents

    public static function documents(): array
    {
        return Store::read('bot-docs');
    }

    public static function docDir(): string
    {
        $dir = DATA_DIR . '/uploads/' . self::DOC_DIR;
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        return $dir;
    }

    /** @return array{ok:bool,error?:string,doc?:array} */
    public static function addDocument(array $file): array
    {
        if (($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Le fichier n’a pas pu être envoyé.'];
        }
        if (($file['size'] ?? 0) > 8 * 1024 * 1024) {
            return ['ok' => false, 'error' => 'Document trop volumineux (8 Mo maximum).'];
        }
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, DocText::SUPPORTED, true)) {
            return ['ok' => false, 'error' => 'Formats acceptés : ' . implode(', ', DocText::SUPPORTED) . '.'];
        }
        $text = DocText::extract((string) $file['tmp_name'], $ext);
        if (mb_strlen($text) < 40) {
            return ['ok' => false, 'error' => 'Aucun texte exploitable n’a pu être extrait (document scanné ou protégé ?). Copiez son contenu dans le champ « Informations complémentaires ».'];
        }
        $id = Store::uid('doc-');
        $stored = $id . '.' . $ext;
        $textFile = $id . '.txt';
        @move_uploaded_file((string) $file['tmp_name'], self::docDir() . '/' . $stored);
        file_put_contents(self::docDir() . '/' . $textFile, $text);

        $doc = Store::push('bot-docs', [
            'id' => $id,
            'name' => mb_substr((string) $file['name'], 0, 140),
            'ext' => $ext,
            'file' => $stored,
            'text_file' => $textFile,
            'chars' => mb_strlen($text),
            'preview' => mb_substr($text, 0, 220),
        ]);
        return ['ok' => true, 'doc' => $doc];
    }

    public static function deleteDocument(string $id): bool
    {
        $doc = Store::find('bot-docs', $id);
        if ($doc === null) { return false; }
        foreach (['file', 'text_file'] as $k) {
            if (!empty($doc[$k])) { @unlink(self::docDir() . '/' . basename((string) $doc[$k])); }
        }
        return Store::delete('bot-docs', $id);
    }

    // ----------------------------------------------------------- historique

    public static function logConversation(string $question, string $answer, bool $ok, string $origin = 'site'): void
    {
        Store::mutate('bot-chats', static function (array $rows) use ($question, $answer, $ok, $origin): array {
            array_unshift($rows, [
                'id' => Store::uid('chat-'),
                'question' => mb_substr($question, 0, 600),
                'answer' => mb_substr($answer, 0, 2000),
                'ok' => $ok,
                'origin' => $origin,
                'visitor' => visitor_hash(),
                'created_at' => date('c'),
            ]);
            return array_slice($rows, 0, 200);
        });
    }

    // ---------------------------------------------------------------- HTTP

    /** @return array{ok:bool,data?:array,error?:string,status?:int} */
    private static function http(string $method, string $url, ?array $body = null): array
    {
        $payload = $body === null ? null : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $raw = null;
        $status = 0;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_TIMEOUT => 45,
                CURLOPT_CONNECTTIMEOUT => 12,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            ]);
            if ($payload !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            }
            $raw = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            if ($raw === false) {
                return ['ok' => false, 'error' => 'Connexion à l’API Gemini impossible : ' . ($err ?: 'erreur réseau') . '.'];
            }
        } else {
            $ctx = stream_context_create(['http' => [
                'method' => $method,
                'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content' => $payload ?? '',
                'timeout' => 45,
                'ignore_errors' => true,
            ]]);
            $raw = @file_get_contents($url, false, $ctx);
            foreach ($http_response_header ?? [] as $h) {
                if (preg_match('#HTTP/\S+\s+(\d{3})#', $h, $m)) { $status = (int) $m[1]; }
            }
            if ($raw === false) {
                return ['ok' => false, 'error' => 'Connexion à l’API Gemini impossible.'];
            }
        }

        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            return ['ok' => false, 'error' => 'Réponse illisible de l’API Gemini.', 'status' => $status];
        }
        if ($status >= 400 || isset($data['error'])) {
            $msg = (string) ($data['error']['message'] ?? 'erreur HTTP ' . $status);
            if ($status === 400 && str_contains($msg, 'API key')) {
                $msg = 'Clé API refusée par Google. Vérifiez qu’elle est active et autorisée pour l’API Generative Language.';
            } elseif ($status === 403) {
                $msg = 'Accès refusé (403). La clé n’a pas les droits sur ce modèle, ou l’API n’est pas activée sur le projet Google.';
            } elseif ($status === 429) {
                $msg = 'Quota Gemini atteint. Réessayez dans quelques instants.';
            }
            return ['ok' => false, 'error' => $msg, 'status' => $status];
        }
        return ['ok' => true, 'data' => $data, 'status' => $status];
    }
}
