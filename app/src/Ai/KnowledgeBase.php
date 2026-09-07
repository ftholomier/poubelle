<?php

declare(strict_types=1);

namespace App\Ai;

use App\Content\Media;
use App\Content\Pages;
use App\Content\Settings;
use App\Core\JsonStore;
use App\I18n\Translator;

/**
 * Base de connaissance de l'assistant : contenu des pages (y compris WYSIWYG)
 * + documents téléversés depuis le back-office.
 *
 * L'index est reconstruit automatiquement dès qu'une page ou un document change
 * (empreinte de fraîcheur), sans intervention manuelle.
 */
final class KnowledgeBase
{
    private const CHUNK_SIZE = 900;   // caractères par extrait
    private const OVERLAP    = 150;

    private static function indexFile(): string
    {
        return DATA_PATH . '/runtime/kb-index.json';
    }

    /** Empreinte des sources : change dès qu'un contenu est modifié. */
    private static function fingerprint(): string
    {
        $parts = [];
        foreach (glob(Pages::dir() . '/*.json') ?: [] as $file) {
            $parts[] = basename($file) . ':' . filemtime($file);
        }
        foreach ([DATA_PATH . '/documents.json', DATA_PATH . '/settings.json'] as $file) {
            if (is_file($file)) {
                $parts[] = basename($file) . ':' . filemtime($file);
            }
        }
        return md5(implode('|', $parts));
    }

    /** @return array<int,array{id:string,source:string,url:string,title:string,text:string,tokens:array<string,int>}> */
    public static function chunks(bool $force = false): array
    {
        $index = JsonStore::read(self::indexFile(), ['fingerprint' => '', 'chunks' => []], true);
        $current = self::fingerprint();

        if (!$force && $index['fingerprint'] === $current && !empty($index['chunks'])) {
            return $index['chunks'];
        }

        $chunks = self::build();
        JsonStore::write(self::indexFile(), [
            'fingerprint' => $current,
            'built_at'    => date('c'),
            'chunks'      => $chunks,
        ], false);

        return $chunks;
    }

    /** @return array<int,array<string,mixed>> */
    private static function build(): array
    {
        $chunks = [];
        $lang = Settings::str('i18n.default', 'fr');

        /* 1. Pages publiées — tous les blocs, WYSIWYG compris. */
        foreach (Pages::index(true) as $entry) {
            if (($entry['status'] ?? '') !== 'published') {
                continue;
            }
            $page = Pages::find((string) $entry['slug']);
            if (!$page) {
                continue;
            }
            $title = Translator::pick($page['title'], $lang);
            // La page d'accueil est servie à la racine : on cite l'URL réelle.
            $url   = !empty($page['home']) ? '/' : '/' . ltrim((string) $page['slug'], '/');
            $text  = self::flattenBlocks($page['blocks'], $lang);

            foreach (self::split($text) as $i => $piece) {
                $chunks[] = self::chunk('page:' . $page['slug'] . ':' . $i, 'page', $url, $title, $piece);
            }
        }

        /* 2. Documents téléversés marqués « base de connaissance ». */
        foreach (Media::all(Media::KIND_DOC) as $doc) {
            if (empty($doc['in_kb']) || trim((string) $doc['text']) === '') {
                continue;
            }
            $url = !empty($doc['public']) ? (string) $doc['url'] : '';
            foreach (self::split((string) $doc['text']) as $i => $piece) {
                $chunks[] = self::chunk('doc:' . $doc['id'] . ':' . $i, 'document', $url, (string) $doc['name'], $piece);
            }
        }

        /* 3. Fiche d'identité du cabinet. */
        $identity = sprintf(
            "%s. %s. Adresse : %s, %s %s. Téléphone : %s. E-mail : %s. Horaires : %s.",
            Settings::str('site.name'),
            Translator::pick(Settings::get('site.tagline'), $lang),
            Settings::str('site.address'),
            Settings::str('site.zip'),
            Settings::str('site.city'),
            Settings::str('site.phone_display') ?: Settings::str('site.phone'),
            Settings::str('site.email'),
            Translator::pick(Settings::get('site.hours'), $lang)
        );
        $chunks[] = self::chunk('identity', 'cabinet', '/contact', 'Coordonnées du cabinet', $identity);

        return $chunks;
    }

    private static function chunk(string $id, string $source, string $url, string $title, string $text): array
    {
        return [
            'id'     => $id,
            'source' => $source,
            'url'    => $url,
            'title'  => $title,
            'text'   => $text,
            'tokens' => self::tokenize($title . ' ' . $text),
        ];
    }

    /** Aplatit tous les blocs d'une page en texte lisible. */
    private static function flattenBlocks(array $blocks, string $lang): string
    {
        $out = [];
        $walk = static function ($value) use (&$walk, &$out, $lang): void {
            if (is_string($value)) {
                $clean = trim(html_entity_decode(strip_tags($value), ENT_QUOTES, 'UTF-8'));
                if ($clean !== '' && !preg_match('#^(/|https?://|\#)#', $clean)) {
                    $out[] = $clean;
                }
                return;
            }
            if (is_array($value)) {
                // Champ multilingue : on ne prend que la langue utile.
                $keys = array_keys($value);
                $isLangMap = $keys !== [] && count(array_diff($keys, ['fr', 'en', 'es', 'de'])) === 0;
                if ($isLangMap) {
                    $walk(Translator::pick($value, $lang));
                    return;
                }
                foreach ($value as $item) {
                    $walk($item);
                }
            }
        };

        foreach ($blocks as $block) {
            if (!is_array($block) || empty($block['enabled'])) {
                continue;
            }
            $walk($block['data'] ?? []);
        }
        return implode(' ', array_unique($out));
    }

    /** Longueur en deçà de laquelle un extrait n'apporte rien à la recherche. */
    private const MIN_CHUNK = 60;

    /** @return array<int,string> */
    private static function split(string $text): array
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        if ($text === '') {
            return [];
        }
        $length = mb_strlen($text);
        if ($length <= self::CHUNK_SIZE) {
            return [$text];
        }

        $pieces = [];
        $offset = 0;

        while ($offset < $length) {
            $piece  = mb_substr($text, $offset, self::CHUNK_SIZE);
            $isLast = ($offset + self::CHUNK_SIZE) >= $length;

            // Coupe sur une fin de phrase quand c'est possible, sauf sur le
            // dernier morceau qu'il faut conserver entier.
            if (!$isLast) {
                $cut = max(
                    (int) mb_strrpos($piece, '. '),
                    (int) mb_strrpos($piece, ' ! '),
                    (int) mb_strrpos($piece, ' ? ')
                );
                if ($cut > self::CHUNK_SIZE * 0.5) {
                    $piece = mb_substr($piece, 0, $cut + 1);
                }
            }

            $clean = trim($piece);
            if ($clean !== '') {
                $pieces[] = $clean;
            }

            // Sortie explicite en fin de texte : sans elle, la fenêtre
            // n'avancerait plus que d'un caractère et produirait des centaines
            // de fragments inutiles.
            if ($isLast) {
                break;
            }
            $offset += max(1, mb_strlen($piece) - self::OVERLAP);
        }

        return array_values(array_filter(
            $pieces,
            static fn (string $piece): bool => mb_strlen($piece) >= self::MIN_CHUNK
        ));
    }

    /**
     * Recherche lexicale pondérée (TF + bonus de couverture).
     * Suffisant et rapide sans base de données ni service d'embeddings.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function search(string $query, int $limit = 5): array
    {
        $queryTokens = self::tokenize($query);
        if ($queryTokens === []) {
            return [];
        }
        $chunks = self::chunks();
        if ($chunks === []) {
            return [];
        }

        // Fréquence documentaire pour pondérer les mots rares (IDF simplifié).
        $documentFrequency = [];
        foreach ($chunks as $chunk) {
            foreach (array_keys($chunk['tokens']) as $token) {
                $documentFrequency[$token] = ($documentFrequency[$token] ?? 0) + 1;
            }
        }
        $total = count($chunks);

        $scored = [];
        foreach ($chunks as $chunk) {
            $score = 0.0;
            $matched = 0;
            foreach ($queryTokens as $token => $count) {
                if (!isset($chunk['tokens'][$token])) {
                    continue;
                }
                $matched++;
                $idf = log(1 + $total / max(1, $documentFrequency[$token] ?? 1));
                $score += $chunk['tokens'][$token] * $idf * $count;
            }
            if ($matched === 0) {
                continue;
            }
            $coverage = $matched / count($queryTokens);
            // Bonus si la question est bien couverte par l'extrait.
            $score *= 1 + $coverage;
            $scored[] = ['score' => $score, 'coverage' => $coverage, 'chunk' => $chunk];
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        return array_map(
            static fn (array $row): array => $row['chunk'] + [
                'score'    => round($row['score'], 3),
                'coverage' => round($row['coverage'], 3),
            ],
            array_slice($scored, 0, $limit)
        );
    }

    /** Correspondance accents → lettres simples, indépendante de la locale. */
    private const FOLD = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
        'ý' => 'y', 'ÿ' => 'y', 'ñ' => 'n', 'ç' => 'c',
        'œ' => 'oe', 'æ' => 'ae', 'ß' => 'ss',
    ];

    /** @return array<string,int> */
    private static function tokenize(string $text): array
    {
        $text = strtr(mb_strtolower($text), self::FOLD);
        $words = preg_split('/[^a-z0-9]+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $stop = array_flip([
            'le','la','les','un','une','des','du','de','et','ou','a','au','aux','en','pour','par','sur','dans',
            'que','qui','quoi','est','sont','ce','cette','ces','vous','nous','je','il','elle','se','sa','son',
            'ses','avec','plus','pas','ne','the','and','for','with','you','your','are','was','from','this','that',
        ]);

        $tokens = [];
        foreach ($words as $word) {
            if (mb_strlen($word) < 3 || isset($stop[$word])) {
                continue;
            }
            $tokens[$word] = ($tokens[$word] ?? 0) + 1;
        }
        return $tokens;
    }

    public static function rebuild(): int
    {
        return count(self::chunks(true));
    }

    public static function stats(): array
    {
        $index = JsonStore::read(self::indexFile(), ['fingerprint' => '', 'chunks' => [], 'built_at' => ''], true);
        $sources = [];
        foreach ($index['chunks'] as $chunk) {
            $source = (string) ($chunk['source'] ?? 'autre');
            $sources[$source] = ($sources[$source] ?? 0) + 1;
        }
        return [
            'chunks'   => count($index['chunks']),
            'built_at' => (string) $index['built_at'],
            'sources'  => $sources,
            'stale'    => $index['fingerprint'] !== self::fingerprint(),
        ];
    }
}
