<?php
declare(strict_types=1);

namespace App\Ai;

use App\Config;
use App\Consent;
use App\Content;
use App\Http;
use App\I18n;
use App\Log;
use App\Offices;
use App\Router;
use App\Store;

/**
 * Assistant du site : recherche locale dans l'index puis génération Gemini.
 * Sans clé API, la réponse est composée à partir des extraits trouvés :
 * le visiteur obtient toujours une réponse sourcée, jamais une erreur.
 */
final class Gemini
{
    public const MODEL_DEFAULT = 'gemini-2.5-flash';
    private const TIMEOUT = 8;
    private const MAX_QUESTION = 500;
    private const MISSES_FILE = 'ai-misses.json';

    /** Catalogue des modèles renvoyé par Google, mis en cache hors racine web. */
    private const MODELS_FILE = 'gemini-models.json';
    private const MODELS_TTL = 86400;   // 24 h avant de redemander la liste
    private const MODELS_RETRY = 600;   // 10 min avant de retenter après un échec

    /** Modèles qui ne savent pas répondre en texte : hors liste du back-office. */
    private const MODELS_EXCLUDE = '/embedding|imagen|veo|aqa|tts|-image|native-audio|live-/i';

    public static function configured(): bool
    {
        return Config::has('GEMINI_API_KEY');
    }

    public static function model(): string
    {
        return (string) (Config::get('GEMINI_MODEL') ?? self::MODEL_DEFAULT);
    }

    /**
     * Catalogue en cache, sans le moindre appel réseau : c'est ce que lisent
     * les pages publiques et le rendu du back-office.
     *
     * @return array{models:array<int,array<string,mixed>>,fetchedAt:string,checkedAt:string,error:string}
     */
    public static function cachedModels(): array
    {
        $cache = Store::readStorage(self::MODELS_FILE);
        $models = \is_array($cache['models'] ?? null) ? $cache['models'] : [];
        return [
            'models' => array_values(array_filter($models, 'is_array')),
            'fetchedAt' => (string) ($cache['fetchedAt'] ?? ''),
            'checkedAt' => (string) ($cache['checkedAt'] ?? ''),
            'error' => (string) ($cache['error'] ?? ''),
        ];
    }

    /**
     * Liste affichée au back-office : le cache s'il est frais, sinon une
     * tentative de rafraîchissement pour que le choix apparaisse dès que la
     * clé est posée. L'échec est mémorisé 10 min afin de ne pas ralentir l'écran.
     */
    public static function models(): array
    {
        $cache = self::cachedModels();
        if (!self::configured()) {
            $cache['error'] = 'no-key';
            return $cache;
        }

        $fresh = (time() - self::stamp($cache['fetchedAt'])) < self::MODELS_TTL;
        $justTried = (time() - self::stamp($cache['checkedAt'])) < self::MODELS_RETRY;
        if (($cache['models'] !== [] && $fresh) || $justTried) {
            return $cache;
        }
        return self::refreshModels();
    }

    /** Rafraîchissement explicite : bouton du back-office ou enregistrement d'une clé. */
    public static function refreshModels(): array
    {
        $cache = self::cachedModels();
        if (!self::configured()) {
            $cache['error'] = 'no-key';
            return $cache;
        }

        $now = (new \DateTimeImmutable())->format(\DATE_ATOM);
        $models = self::fetchModels();
        if ($models === null) {
            $cache['checkedAt'] = $now;
            $cache['error'] = 'fetch';
            Store::writeStorage(self::MODELS_FILE, $cache);
            return $cache;
        }

        $payload = ['models' => $models, 'fetchedAt' => $now, 'checkedAt' => $now, 'error' => ''];
        Store::writeStorage(self::MODELS_FILE, $payload);
        return $payload;
    }

    /** true si le modèle enregistré ne figure pas (ou plus) dans le catalogue. */
    public static function modelIsKnown(): bool
    {
        foreach (self::cachedModels()['models'] as $model) {
            if ((string) ($model['id'] ?? '') === self::model()) {
                return true;
            }
        }
        return false;
    }

    /**
     * GET /v1beta/models : on ne retient que ceux qui savent répondre en texte.
     * @return array<int,array<string,mixed>>|null
     */
    private static function fetchModels(): ?array
    {
        $models = [];
        $token = '';
        for ($page = 0; $page < 5; $page++) {
            $url = 'https://generativelanguage.googleapis.com/v1beta/models?pageSize=200'
                . ($token === '' ? '' : '&pageToken=' . rawurlencode($token));
            $response = Http::getJson($url, [
                'timeout' => self::TIMEOUT,
                'headers' => [
                'x-goog-api-key' => (string) Config::get('GEMINI_API_KEY'),
                'Referer' => Config::apiReferer(),
            ],
            ]);
            if (!$response['ok'] || $response['json'] === null) {
                Log::write('ai', 'Gemini : liste des modèles, statut ' . $response['status'] . ' ' . substr($response['body'], 0, 300));
                return null;
            }
            foreach ((array) ($response['json']['models'] ?? []) as $model) {
                $entry = self::normalizeModel((array) $model);
                if ($entry !== null) {
                    $models[$entry['id']] = $entry;
                }
            }
            $token = (string) ($response['json']['nextPageToken'] ?? '');
            if ($token === '') {
                break;
            }
        }

        $models = array_values($models);
        // Modèles stables d'abord, puis du plus récent au plus ancien (2.5 avant 1.5).
        usort($models, static fn (array $a, array $b): int => [$a['preview'], $b['id']] <=> [$b['preview'], $a['id']]);
        return $models;
    }

    /** @return array<string,mixed>|null */
    private static function normalizeModel(array $model): ?array
    {
        $id = (string) preg_replace('#^models/#', '', (string) ($model['name'] ?? ''));
        $methods = array_map('strval', (array) ($model['supportedGenerationMethods'] ?? []));
        if ($id === '' || !\in_array('generateContent', $methods, true)) {
            return null;
        }
        if (preg_match(self::MODELS_EXCLUDE, $id) === 1) {
            return null;
        }

        $label = trim((string) ($model['displayName'] ?? ''));
        return [
            'id' => $id,
            'label' => $label === '' ? $id : $label,
            'description' => trim((string) ($model['description'] ?? '')),
            'input' => (int) ($model['inputTokenLimit'] ?? 0),
            'output' => (int) ($model['outputTokenLimit'] ?? 0),
            'preview' => preg_match('/preview|experimental|-exp/i', $id) === 1,
        ];
    }

    private static function stamp(string $iso): int
    {
        return $iso === '' ? 0 : (strtotime($iso) ?: 0);
    }

    public static function systemPrompt(): string
    {
        $settings = Content::settings();
        $custom = trim((string) ($settings['ai']['systemPrompt'] ?? ''));
        return $custom !== '' ? $custom : self::defaultPrompt();
    }

    public static function defaultPrompt(): string
    {
        return "Tu es l'assistant du iOiO, coworking à Besançon. Réponds en 3 phrases maximum, "
            . "dans la langue du visiteur, à partir des données du catalogue et des extraits fournis. "
            . "Les DONNÉES DU CATALOGUE font foi : reprends leurs nombres et leurs tarifs tels quels, "
            . "et cite les bureaux libres par leur nom quand la question porte sur les disponibilités. "
            . "Si l'information manque, dis-le et propose le formulaire de contact. "
            . "N'invente jamais un tarif, une disponibilité ni une adresse. "
            . "N'écris pas d'URL dans ta réponse : des boutons de navigation sont ajoutés automatiquement.";
    }

    /**
     * @param array<int,array{role:string,text:string}> $history
     * @return array{answer:string,sources:array<int,array{label:string,url:string}>,actions:array<int,array{label:string,url:string}>,engine:string}
     */
    public static function ask(string $question, string $lang, array $history = []): array
    {
        $question = trim(mb_substr($question, 0, self::MAX_QUESTION));
        if ($question === '') {
            return self::fallbackAnswer($lang, []);
        }

        // Boutons calculés côté serveur à partir du catalogue : ils sont justes
        // quelle que soit la façon dont la réponse a été rédigée.
        $actions = Facts::actionsFor($question, $lang, $history);
        $passages = Indexer::search($question, $lang, 6);

        // Sans clé — ou sans accord du visiteur pour l'envoi à Google — la
        // réponse est produite localement : d'abord les chiffres du catalogue,
        // puis les réponses rapides, puis les extraits de l'index.
        if (!self::configured() || !Consent::allows('ai')) {
            return self::localAnswer($question, $passages, $lang, $actions, $history);
        }

        $answer = self::generate($question, $passages, $lang, $history);
        if ($answer === null) {
            return self::localAnswer($question, $passages, $lang, $actions, $history);
        }

        if (self::looksLikeMiss($answer)) {
            self::logMiss($question, $lang);
        }

        return [
            'answer' => $answer,
            'sources' => self::sourcesOf($passages),
            'actions' => $actions,
            'engine' => 'gemini',
        ];
    }

    /**
     * Réponse sans appel externe, dans l'ordre de fiabilité : données du
     * catalogue, réponses rapides du back-office, extraits de l'index.
     */
    private static function localAnswer(string $question, array $passages, string $lang, array $actions, array $history = []): array
    {
        $data = Facts::answer($question, $lang, $history);
        if ($data !== null) {
            return [
                'answer' => $data['answer'],
                'sources' => self::sourcesOf($passages),
                'actions' => $data['actions'] !== [] ? $data['actions'] : $actions,
                'engine' => 'data',
            ];
        }

        $quick = self::quickAnswer($question, $lang);
        if ($quick !== null) {
            $quick['actions'] = $actions;
            return $quick;
        }

        if ($passages === []) {
            self::logMiss($question, $lang);
            return self::fallbackAnswer($lang, [], $actions);
        }

        $extractive = self::extractiveAnswer($question, $passages, $lang);
        $extractive['actions'] = $actions;
        return $extractive;
    }

    private static function generate(string $question, array $passages, string $lang, array $history): ?string
    {
        $context = '';
        foreach ($passages as $i => $passage) {
            $context .= '[' . ($i + 1) . '] ' . ($passage['source']['label'] ?? 'Source') . "\n" . $passage['text'] . "\n\n";
        }

        $contents = [];
        foreach (\array_slice($history, -4) as $turn) {
            $role = ($turn['role'] ?? '') === 'user' ? 'user' : 'model';
            $text = trim(mb_substr((string) ($turn['text'] ?? ''), 0, 800));
            if ($text !== '') {
                $contents[] = ['role' => $role, 'parts' => [['text' => $text]]];
            }
        }
        // Les chiffres du catalogue priment sur les extraits, qui peuvent dater.
        $facts = Facts::brief($lang);
        $contents[] = ['role' => 'user', 'parts' => [['text' =>
            "Langue du visiteur : {$lang}.\n\n"
            . "DONNÉES DU CATALOGUE, à jour à la seconde — elles font foi sur les nombres,\n"
            . "les tarifs, les disponibilités et les liens :\n{$facts}\n\n"
            . "Extraits des pages du site :\n{$context}Question du visiteur : {$question}",
        ]]];

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode(self::model()) . ':generateContent';
        $response = Http::postJson($url, [
            'systemInstruction' => ['parts' => [['text' => self::systemPrompt()]]],
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.2,
                'maxOutputTokens' => 400,
                'topP' => 0.9,
            ],
            'safetySettings' => [],
        ], [
            'timeout' => self::TIMEOUT,
            'headers' => [
                'x-goog-api-key' => (string) Config::get('GEMINI_API_KEY'),
                'Referer' => Config::apiReferer(),
            ],
        ]);

        if (!$response['ok'] || $response['json'] === null) {
            Log::write('ai', 'Gemini : statut ' . $response['status'] . ' ' . substr($response['body'], 0, 300));
            return null; // une seule tentative, puis dégradation propre
        }

        $text = '';
        foreach ((array) ($response['json']['candidates'][0]['content']['parts'] ?? []) as $part) {
            $text .= (string) ($part['text'] ?? '');
        }
        $text = trim($text);
        return $text === '' ? null : $text;
    }

    /**
     * Réponse composée à partir des extraits, sans appel externe : on retient
     * les phrases qui contiennent réellement les mots de la question.
     */
    private static function extractiveAnswer(string $question, array $passages, string $lang): array
    {
        $terms = Indexer::tokenize($question);
        $scored = [];
        foreach (\array_slice($passages, 0, 3) as $rank => $passage) {
            $sentences = preg_split('/(?<=[.!?])\s+|\n+/u', trim((string) $passage['text'])) ?: [];
            foreach ($sentences as $position => $sentence) {
                $sentence = trim($sentence);
                if (mb_strlen($sentence) < 25 || mb_strlen($sentence) > 320) {
                    continue;
                }
                $hits = \count(array_intersect($terms, Indexer::tokenize($sentence)));
                if ($hits === 0) {
                    continue;
                }
                $scored[] = [
                    'text' => $sentence,
                    'score' => $hits - ($rank * 0.5) - ($position * 0.02),
                ];
            }
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        $picked = [];
        foreach (\array_slice($scored, 0, 3) as $entry) {
            $picked[] = $entry['text'];
        }
        $answer = trim(implode(' ', array_unique($picked)));

        if ($answer === '') {
            return self::fallbackAnswer($lang, $passages);
        }
        return [
            'answer' => $answer,
            'sources' => self::sourcesOf($passages),
            'actions' => [],
            'engine' => 'index',
        ];
    }

    /**
     * Réponses rapides saisies au back-office (mots-clés → réponse + sources).
     * Elles servent de filet quand l'API n'est pas configurée ou ne répond pas.
     */
    private static function quickAnswer(string $question, string $lang): ?array
    {
        $settings = Content::settings();
        $entries = $settings['ai']['quickAnswers'] ?? [];
        if (!\is_array($entries) || $entries === []) {
            return null;
        }
        $needle = ' ' . implode(' ', Indexer::tokenize($question)) . ' ';

        foreach ($entries as $entry) {
            if (!\is_array($entry) || ($entry['enabled'] ?? true) === false) {
                continue;
            }
            $keywords = (array) ($entry['keywords'] ?? []);
            foreach ($keywords as $keyword) {
                foreach (Indexer::tokenize((string) $keyword) as $token) {
                    if (str_contains($needle, ' ' . $token . ' ')) {
                        $answer = Content::i18n($entry, 'answer', $lang);
                        if (trim($answer) === '') {
                            break 2;
                        }
                        $sources = [];
                        foreach ((array) ($entry['sources'] ?? []) as $source) {
                            $sources[] = \is_array($source)
                                ? ['label' => (string) ($source['label'] ?? ''), 'url' => (string) ($source['url'] ?? '')]
                                : ['label' => (string) $source, 'url' => ''];
                        }
                        return [
                            'answer' => self::withLiveFigures($answer),
                            'sources' => $sources,
                            'actions' => [],
                            'engine' => 'quick',
                        ];
                    }
                }
            }
        }
        return null;
    }

    /** Remplace les jetons {dispos}, {prixMin} par les valeurs réelles du catalogue. */
    private static function withLiveFigures(string $answer): string
    {
        $min = Offices::minPrice();
        return strtr($answer, [
            '{dispos}' => (string) Offices::availableCount(),
            '{prixMin}' => $min === null ? '' : I18n::price($min),
        ]);
    }

    /** Dégradation propre : on transmet à l'équipe. */
    private static function fallbackAnswer(string $lang, array $passages, array $actions = []): array
    {
        $sources = self::sourcesOf($passages);
        if ($sources === []) {
            $sources = [['label' => I18n::t('nav.contact'), 'url' => Router::url('contact', $lang)]];
        }
        return [
            'answer' => I18n::t('bot.fallback'),
            'sources' => $sources,
            'actions' => $actions,
            'engine' => 'fallback',
        ];
    }

    /** @return array<int,array{label:string,url:string}> */
    private static function sourcesOf(array $passages): array
    {
        $out = [];
        foreach ($passages as $passage) {
            $label = (string) ($passage['source']['label'] ?? '');
            if ($label === '' || isset($out[$label])) {
                continue;
            }
            $out[$label] = [
                'label' => $label,
                'url' => (string) ($passage['source']['url'] ?? ''),
            ];
            if (\count($out) >= 3) {
                break;
            }
        }
        return array_values($out);
    }

    private static function looksLikeMiss(string $answer): bool
    {
        $low = mb_strtolower($answer);
        foreach (["je n'ai pas", 'je ne dispose pas', "l'information manque", "i don't have", 'i do not have'] as $needle) {
            if (str_contains($low, $needle)) {
                return true;
            }
        }
        return false;
    }

    /** Journal des questions sans réponse, affiché au back-office. */
    public static function logMiss(string $question, string $lang): void
    {
        $data = Store::read(self::MISSES_FILE);
        $misses = \is_array($data['misses'] ?? null) ? $data['misses'] : [];
        $key = mb_strtolower(trim($question));
        $found = false;
        foreach ($misses as $i => $miss) {
            if (mb_strtolower((string) ($miss['q'] ?? '')) === $key) {
                $misses[$i]['n'] = (int) ($miss['n'] ?? 0) + 1;
                $misses[$i]['at'] = (new \DateTimeImmutable())->format(\DATE_ATOM);
                $found = true;
                break;
            }
        }
        if (!$found) {
            $misses[] = ['q' => mb_substr($question, 0, 200), 'lang' => $lang, 'n' => 1, 'at' => (new \DateTimeImmutable())->format(\DATE_ATOM)];
        }
        usort($misses, static fn (array $a, array $b): int => (int) $b['n'] <=> (int) $a['n']);
        Store::write(self::MISSES_FILE, ['_schema' => Config::SCHEMA, 'misses' => \array_slice($misses, 0, 200)]);
    }

    public static function misses(int $limit = 12): array
    {
        $data = Store::read(self::MISSES_FILE);
        $misses = \is_array($data['misses'] ?? null) ? $data['misses'] : [];
        return \array_slice($misses, 0, $limit);
    }

    public static function clearMisses(): void
    {
        Store::write(self::MISSES_FILE, ['_schema' => Config::SCHEMA, 'misses' => []]);
    }

    /** Suggestions cliquables du panneau, alimentées par le back-office. */
    public static function suggestions(string $lang): array
    {
        $settings = Content::settings();
        $list = $settings['ai']['suggestions'][$lang] ?? $settings['ai']['suggestions'][Config::DEFAULT_LANG] ?? [];
        if (!\is_array($list) || $list === []) {
            return [I18n::t('bot.suggest1'), I18n::t('bot.suggest2'), I18n::t('bot.suggest3')];
        }
        return array_values(array_filter($list, 'is_string'));
    }

    /** Compteur de disponibilités injecté dans la première réponse du bot. */
    public static function greeting(string $lang): string
    {
        return I18n::t('bot.greeting', ['count' => Offices::availableCount()]);
    }
}
