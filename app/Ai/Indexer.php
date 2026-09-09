<?php
declare(strict_types=1);

namespace App\Ai;

use App\Config;
use App\Content;
use App\Log;
use App\Offices;
use App\Router;
use App\Store;
use App\Text;

/**
 * Index de l'assistant : texte des pages publiées (toutes langues) et des
 * documents déposés au back-office, découpé en extraits d'environ 800 caractères
 * avec 100 de recouvrement. Récupération par score BM25 allégé, 100 % PHP.
 */
final class Indexer
{
    public const FILE = 'index/chunks.json';
    private const CHUNK = 800;
    private const OVERLAP = 100;

    private const STOPWORDS = [
        'le', 'la', 'les', 'un', 'une', 'des', 'du', 'de', 'et', 'ou', 'a', 'au', 'aux', 'en', 'dans', 'sur', 'pour',
        'par', 'avec', 'sans', 'que', 'qui', 'quoi', 'dont', 'est', 'sont', 'ce', 'cette', 'ces', 'se', 'sa', 'son',
        'ses', 'nos', 'notre', 'vous', 'nous', 'il', 'elle', 'ils', 'elles', 'on', 'plus', 'pas', 'ne', 'y', 'd', 'l',
        'the', 'a', 'an', 'of', 'and', 'or', 'to', 'in', 'on', 'for', 'with', 'is', 'are', 'be', 'we', 'you', 'your',
        'our', 'it', 'that', 'this', 'at', 'as', 'by', 'from',
    ];

    public static function read(): array
    {
        $data = Store::readStorage(self::FILE);
        return \is_array($data['chunks'] ?? null) ? $data['chunks'] : [];
    }

    public static function stats(): array
    {
        $data = Store::readStorage(self::FILE);
        $chunks = \is_array($data['chunks'] ?? null) ? $data['chunks'] : [];
        $bySource = [];
        foreach ($chunks as $chunk) {
            $label = (string) ($chunk['source']['label'] ?? '—');
            $bySource[$label] = ($bySource[$label] ?? 0) + 1;
        }
        return [
            'count' => \count($chunks),
            'builtAt' => (string) ($data['builtAt'] ?? ''),
            'bySource' => $bySource,
        ];
    }

    /** Reconstruit l'index complet. @return array{chunks:int,sources:int} */
    public static function rebuild(): array
    {
        $chunks = [];
        foreach (Config::LANGS as $lang) {
            foreach (self::pageTexts($lang) as $entry) {
                $chunks = array_merge($chunks, self::split($entry['text'], $entry['source'], $lang, $entry['title'] ?? ''));
            }
        }
        foreach (Docs::all() as $doc) {
            $text = Docs::extractText($doc);
            if (trim($text) === '') {
                Docs::setState((string) $doc['id'], 'error', 0);
                continue;
            }
            $docChunks = self::split($text, [
                'label' => (string) ($doc['name'] ?? 'Document'),
                'doc' => (string) ($doc['id'] ?? ''),
            ], (string) ($doc['lang'] ?? Config::DEFAULT_LANG), (string) ($doc['name'] ?? ''));
            Docs::setState((string) $doc['id'], 'indexed', \count($docChunks));
            $chunks = array_merge($chunks, $docChunks);
        }

        $sources = \count(array_unique(array_map(static fn (array $c): string => (string) ($c['source']['label'] ?? ''), $chunks)));
        Store::writeStorage(self::FILE, [
            '_schema' => Config::SCHEMA,
            'builtAt' => (new \DateTimeImmutable())->format(\DATE_ATOM),
            'chunks' => $chunks,
        ]);
        Log::write('ai', 'Index reconstruit : ' . \count($chunks) . ' extraits, ' . $sources . ' sources.');

        return ['chunks' => \count($chunks), 'sources' => $sources];
    }

    /**
     * Récupération : score BM25 allégé + bonus si le mot apparaît dans le titre.
     * @return array<int,array{id:string,text:string,source:array,score:float}>
     */
    public static function search(string $query, string $lang, int $limit = 6): array
    {
        $chunks = self::read();
        if ($chunks === []) {
            return [];
        }
        $terms = self::tokenize($query);
        if ($terms === []) {
            return [];
        }

        $total = \count($chunks);
        $avgLen = array_sum(array_map(static fn (array $c): int => (int) ($c['len'] ?? mb_strlen((string) $c['text'])), $chunks)) / max(1, $total);

        // Fréquence documentaire de chaque terme.
        $df = [];
        $tokenized = [];
        foreach ($chunks as $i => $chunk) {
            $tokens = self::tokenize((string) ($chunk['text'] ?? '') . ' ' . (string) ($chunk['title'] ?? ''));
            $tokenized[$i] = array_count_values($tokens);
            foreach (array_unique($tokens) as $token) {
                $df[$token] = ($df[$token] ?? 0) + 1;
            }
        }

        $k1 = 1.4;
        $b = 0.72;
        $scored = [];
        foreach ($chunks as $i => $chunk) {
            $counts = $tokenized[$i];
            $len = (int) ($chunk['len'] ?? mb_strlen((string) $chunk['text']));
            $score = 0.0;
            foreach ($terms as $term) {
                $f = (float) ($counts[$term] ?? 0);
                if ($f === 0.0) {
                    continue;
                }
                $idf = log(1 + (($total - ($df[$term] ?? 0) + 0.5) / (($df[$term] ?? 0) + 0.5)));
                $score += $idf * (($f * ($k1 + 1)) / ($f + $k1 * (1 - $b + $b * ($len / max(1, $avgLen)))));
            }
            if ($score <= 0.0) {
                continue;
            }
            $titleTokens = self::tokenize((string) ($chunk['title'] ?? ''));
            $score *= 1 + 0.35 * \count(array_intersect($terms, $titleTokens));
            if ((string) ($chunk['lang'] ?? '') === $lang) {
                $score *= 1.25; // on privilégie la langue du visiteur
            }
            $scored[] = ['chunk' => $chunk, 'score' => $score];
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        $out = [];
        $seen = [];
        foreach (\array_slice($scored, 0, $limit * 2) as $entry) {
            $key = ($entry['chunk']['source']['label'] ?? '') . '|' . mb_substr((string) $entry['chunk']['text'], 0, 40);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = [
                'id' => (string) ($entry['chunk']['id'] ?? ''),
                'text' => (string) ($entry['chunk']['text'] ?? ''),
                'source' => (array) ($entry['chunk']['source'] ?? []),
                'score' => round($entry['score'], 3),
            ];
            if (\count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    /** @return array<int,array{text:string,source:array,title:string}> */
    private static function pageTexts(string $lang): array
    {
        $entries = [];

        foreach (array_unique(Router::PAGE_OF_ROUTE) as $slug) {
            $page = Content::page($slug, $lang);
            if ($page === [] || !Content::isPublished($page)) {
                continue;
            }
            $routeName = array_search($slug, Router::PAGE_OF_ROUTE, true) ?: 'home';
            $entries[] = [
                'title' => Content::text($page, 'seo.title', $slug),
                'text' => self::flatten($page),
                'source' => [
                    'label' => 'Page ' . Content::text($page, 'nav', Content::text($page, 'seo.title', $slug)),
                    'url' => Router::url((string) $routeName, $lang),
                ],
            ];
        }

        $officeLines = [];
        foreach (Offices::published() as $office) {
            $decorated = Offices::decorate($office, $lang);
            $officeLines[] = sprintf(
                '%s — %s, %s, %s, %s. Statut : %s. %s %s',
                $decorated['name'],
                $decorated['typeLabel'],
                $decorated['area'],
                $decorated['siteLabel'],
                $decorated['priceLabel'] . ' HT/mois',
                $decorated['statusLabel'],
                $decorated['description'],
                implode(', ', $decorated['features'])
            );
        }
        if ($officeLines !== []) {
            $entries[] = [
                'title' => 'Bureaux et disponibilités',
                'text' => implode("\n", $officeLines),
                'source' => ['label' => 'Disponibilités (temps réel)', 'url' => Router::url('offices', $lang)],
            ];
        }

        foreach (Content::publishedPosts() as $post) {
            $entries[] = [
                'title' => Content::i18n($post, 'title', $lang),
                'text' => Content::i18n($post, 'title', $lang) . "\n" . strip_tags(Content::i18n($post, 'body', $lang) ?: Content::i18n($post, 'excerpt', $lang)),
                'source' => ['label' => 'Actualité : ' . Content::i18n($post, 'title', $lang), 'url' => Router::url('post', $lang, ['slug' => (string) ($post['slug'] ?? '')])],
            ];
        }

        return $entries;
    }

    /** Clés de structure : leur valeur n'est pas du contenu rédactionnel. */
    private const SKIP_KEYS = [
        'id', 'slug', 'lang', 'status', 'type', 'route', 'color', 'bg', 'image', 'ogImage',
        'icon', 'href', 'slot', 'n', 'updatedAt', 'updatedBy', '_schema', 'noindex', 'photos', 'slides',
    ];

    /** Aplatit un JSON de page en texte lisible, sans les valeurs techniques. */
    private static function flatten(array $node): string
    {
        $out = [];
        $walk = static function (array $branch) use (&$walk, &$out): void {
            foreach ($branch as $key => $value) {
                if (\in_array((string) $key, self::SKIP_KEYS, true)) {
                    continue;
                }
                if (\is_array($value)) {
                    $walk($value);
                    continue;
                }
                if (!\is_string($value)) {
                    continue;
                }
                $clean = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? '');
                if ($clean === '' || mb_strlen($clean) < 3) {
                    continue;
                }
                if (preg_match('#^(/|https?://|\#[0-9a-f]{3,8}$)#i', $clean)) {
                    continue;
                }
                // Une phrase se termine proprement : facilite la reprise en réponse.
                $out[] = preg_match('/[.!?]$/u', $clean) === 1 ? $clean : $clean . '.';
            }
        };
        $walk($node);
        return implode("\n", array_unique($out));
    }

    /** @return array<int,array<string,mixed>> */
    private static function split(string $text, array $source, string $lang, string $title = ''): array
    {
        $text = trim(preg_replace('/[ \t]+/u', ' ', preg_replace('/\R+/u', "\n", $text) ?? '') ?? '');
        if ($text === '') {
            return [];
        }
        $chunks = [];
        $length = mb_strlen($text);
        $start = 0;
        $n = 0;
        while ($start < $length) {
            $raw = mb_substr($text, $start, self::CHUNK);
            // On coupe sur une fin de phrase quand c'est possible.
            if ($start + self::CHUNK < $length) {
                $cut = max(
                    (int) mb_strrpos($raw, '. '),
                    (int) mb_strrpos($raw, "\n"),
                );
                if ($cut > self::CHUNK * 0.5) {
                    $raw = mb_substr($raw, 0, $cut + 1);
                }
            }
            $taken = mb_strlen($raw);
            $piece = trim($raw);
            if ($piece !== '') {
                $chunks[] = [
                    'id' => substr(sha1(($source['label'] ?? '') . '|' . $lang . '|' . $n . '|' . $piece), 0, 16),
                    'source' => $source,
                    'lang' => $lang,
                    'title' => $title,
                    'text' => $piece,
                    'len' => mb_strlen($piece),
                ];
                $n++;
            }
            // Dernier morceau : on s'arrête, sinon on avance d'un pas franc.
            if ($start + $taken >= $length) {
                break;
            }
            $start += max(1, $taken - self::OVERLAP);
        }
        return $chunks;
    }

    /** @return array<int,string> */
    public static function tokenize(string $text): array
    {
        $text = mb_strtolower(Text::deaccent($text));
        $parts = preg_split('/[^a-z0-9]+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        foreach ($parts as $part) {
            if (mb_strlen($part) < 2 || \in_array($part, self::STOPWORDS, true)) {
                continue;
            }
            $out[] = $part;
        }
        return $out;
    }
}
