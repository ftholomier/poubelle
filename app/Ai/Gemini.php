<?php
declare(strict_types=1);

namespace App\Ai;

use App\Config;
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

    public static function configured(): bool
    {
        return Config::has('GEMINI_API_KEY');
    }

    public static function model(): string
    {
        return (string) (Config::get('GEMINI_MODEL') ?? self::MODEL_DEFAULT);
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
            . "dans la langue du visiteur, uniquement à partir des extraits fournis. Cite les sources. "
            . "Si l'information manque, dis-le et propose le formulaire de contact ou le bouton "
            . "« Réservez votre bureau ». N'invente jamais un tarif ni une disponibilité.";
    }

    /**
     * @param array<int,array{role:string,text:string}> $history
     * @return array{answer:string,sources:array<int,array{label:string,url:string}>,engine:string}
     */
    public static function ask(string $question, string $lang, array $history = []): array
    {
        $question = trim(mb_substr($question, 0, self::MAX_QUESTION));
        if ($question === '') {
            return self::fallbackAnswer($lang, []);
        }

        $passages = Indexer::search($question, $lang, 6);
        if ($passages === []) {
            // Index vide ou question hors sujet : les réponses rapides du
            // back-office restent utilisables avant de renvoyer vers l'équipe.
            $quick = self::quickAnswer($question, $lang);
            if ($quick !== null) {
                return $quick;
            }
            self::logMiss($question, $lang);
            return self::fallbackAnswer($lang, []);
        }

        if (!self::configured()) {
            return self::quickAnswer($question, $lang) ?? self::extractiveAnswer($question, $passages, $lang);
        }

        $answer = self::generate($question, $passages, $lang, $history);
        if ($answer === null) {
            // Gemini indisponible : on répond quand même, à partir de l'index local.
            return self::quickAnswer($question, $lang) ?? self::extractiveAnswer($question, $passages, $lang);
        }

        if (self::looksLikeMiss($answer)) {
            self::logMiss($question, $lang);
        }

        return [
            'answer' => $answer,
            'sources' => self::sourcesOf($passages),
            'engine' => 'gemini',
        ];
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
        $contents[] = ['role' => 'user', 'parts' => [['text' =>
            "Langue du visiteur : {$lang}.\n\nExtraits disponibles :\n{$context}Question du visiteur : {$question}",
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
            'headers' => ['x-goog-api-key' => (string) Config::get('GEMINI_API_KEY')],
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
    private static function fallbackAnswer(string $lang, array $passages): array
    {
        $sources = self::sourcesOf($passages);
        if ($sources === []) {
            $sources = [['label' => I18n::t('nav.contact'), 'url' => Router::url('contact', $lang)]];
        }
        return [
            'answer' => I18n::t('bot.fallback'),
            'sources' => $sources,
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
