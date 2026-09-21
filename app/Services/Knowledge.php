<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Domain\PageRepository;
use App\Storage\Index;
use App\Storage\Json;

/**
 * Base de connaissance locale de l'assistant.
 * Contenu : pages publiées, offres, profils, et documents ajoutés depuis le
 * back-office (PDF, Markdown, JSON). Régénérée à chaque publication.
 */
final class Knowledge
{
    private const CHUNK = 900;     // caractères par fragment
    private const OVERLAP = 120;   // recouvrement, pour ne pas couper une phrase utile

    private static function path(): string
    {
        return Config::path('data') . '/index/knowledge.json';
    }

    public static function docsDir(): string
    {
        $dir = Config::path('data') . '/private/ai';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    /** @return array{count:int, generated_at:string} */
    public static function rebuild(): array
    {
        $chunks = [];

        foreach (PageRepository::published('fr') as $page) {
            self::addSections($chunks, (string) $page['title'], '/' . $page['slug'], (string) $page['body']);
        }

        foreach (Index::load('jobs') as $job) {
            if (($job['status'] ?? '') !== 'publish') {
                continue;
            }
            $body = implode("\n", array_filter([
                (string) $job['title'],
                'Structure : ' . (string) $job['company'],
                'Lieu : ' . (string) ($job['city'] ?: $job['region']),
                'Contrat : ' . implode(', ', (array) $job['contract']),
                'Rémunération : ' . ((string) $job['salary'] !== '' ? (string) $job['salary'] : 'non précisée'),
                (string) $job['excerpt'],
            ]));
            self::addChunks($chunks, (string) $job['title'], '/offre/' . $job['slug'], 'offre', $body);
        }

        foreach (Index::load('cv') as $cv) {
            $body = implode("\n", array_filter([
                (string) $cv['name'] . ' — ' . (string) $cv['title'],
                'Ville : ' . (string) $cv['city'],
                'Compétences : ' . implode(', ', (array) $cv['skills']),
                (string) $cv['excerpt'],
            ]));
            self::addChunks($chunks, (string) $cv['name'], '/cv/' . $cv['slug'], 'profil', $body);
        }

        foreach (self::documents() as $doc) {
            self::addChunks($chunks, (string) $doc['title'], '', 'document', (string) $doc['text']);
        }

        Json::write(self::path(), [
            'generated_at' => date('c'),
            'chunks'       => $chunks,
        ]);

        return ['count' => count($chunks), 'generated_at' => date('c')];
    }

    /** Documents indexés, ajoutés depuis le back-office. */
    public static function documents(): array
    {
        $out = [];
        foreach (glob(self::docsDir() . '/*.json') ?: [] as $file) {
            $doc = Json::read($file);
            if ($doc !== []) {
                $out[] = $doc;
            }
        }
        usort($out, static fn(array $a, array $b) => strcmp((string) $b['added_at'], (string) $a['added_at']));
        return $out;
    }

    /** Ajoute un document à la base (texte déjà extrait). */
    public static function addDocument(string $title, string $text, string $kind = 'md'): string
    {
        $id = substr(sha1($title . microtime()), 0, 12);
        Json::write(self::docsDir() . '/' . $id . '.json', [
            'id'       => $id,
            'title'    => $title,
            'kind'     => $kind,
            'text'     => Sanitizer::text($text, 200000),
            'chars'    => mb_strlen($text),
            'added_at' => date('c'),
        ]);
        self::rebuild();
        return $id;
    }

    public static function removeDocument(string $id): bool
    {
        $file = self::docsDir() . '/' . preg_replace('/[^a-z0-9]/i', '', $id) . '.json';
        $ok = Json::delete($file);
        if ($ok) {
            self::rebuild();
        }
        return $ok;
    }

    /**
     * Fragments les plus proches de la question.
     * Score simple : fréquence des mots, bonus si le mot est dans le titre.
     *
     * @return array<int, array{title:string,url:string,kind:string,text:string,score:float}>
     */
    public static function search(string $question, int $limit = 5): array
    {
        $data = Json::read(self::path());
        $chunks = $data['chunks'] ?? [];
        if ($chunks === []) {
            self::rebuild();
            $chunks = Json::read(self::path())['chunks'] ?? [];
        }

        $words = array_values(array_filter(
            explode(' ', Index::haystack([$question])),
            static fn(string $w) => mb_strlen($w) > 2 && !in_array($w, self::STOP, true),
        ));
        if ($words === []) {
            return [];
        }

        // Pondération IDF : un mot présent partout (« offre », « emploi », « cv »)
        // ne doit pas faire remonter la page la plus longue du site.
        $total = count($chunks);
        $idf = [];
        foreach ($words as $word) {
            $documents = 0;
            foreach ($chunks as $chunk) {
                if (str_contains((string) $chunk['haystack'], $word)) {
                    $documents++;
                }
            }
            $idf[$word] = $documents > 0 ? log(1 + ($total - $documents + 0.5) / ($documents + 0.5)) : 0.0;
        }

        $scored = [];
        foreach ($chunks as $chunk) {
            $score = 0.0;
            $length = max(1, mb_strlen((string) $chunk['haystack']));

            foreach ($words as $word) {
                $weight = $idf[$word] ?? 0.0;
                if ($weight <= 0) {
                    continue;
                }
                $hits = substr_count((string) $chunk['haystack'], $word);
                if ($hits > 0) {
                    // Saturation logarithmique + normalisation par la longueur :
                    // un fragment court et précis bat un long fragment vague.
                    $score += $weight * (1 + log(1 + $hits)) * (600 / ($length + 400));
                }
                if (str_contains(Index::haystack([$chunk['title']]), $word)) {
                    $score += $weight * 2.0;
                }
            }
            if ($score > 0) {
                $found = 0;
                foreach ($words as $word) {
                    if (str_contains((string) $chunk['haystack'], $word)) {
                        $found++;
                    }
                }
                $chunk['score'] = round($score, 3);
                // Un seul mot rare touché par hasard ne fait pas une réponse :
                // la couverture dit quelle part de la question est vraiment traitée.
                $chunk['coverage'] = round($found / count($words), 3);
                $scored[] = $chunk;
            }
        }

        usort($scored, static fn(array $a, array $b) => $b['score'] <=> $a['score']);
        return array_slice($scored, 0, $limit);
    }

    public static function stats(): array
    {
        $data = Json::read(self::path());
        return [
            'chunks'       => count($data['chunks'] ?? []),
            'documents'    => count(self::documents()),
            'generated_at' => (string) ($data['generated_at'] ?? ''),
        ];
    }

    /**
     * Découpe une page éditoriale à ses intertitres : chaque section devient un
     * fragment autonome, donc une réponse citable telle quelle.
     */
    private static function addSections(array &$chunks, string $title, string $url, string $html): void
    {
        // On coupe avant chaque <h2>, en gardant le titre avec son contenu.
        $parts = preg_split('#(?=<h2[^>]*>)#i', $html) ?: [$html];

        foreach ($parts as $part) {
            $heading = preg_match('#<h2[^>]*>(.*?)</h2>#is', $part, $m)
                ? trim(strip_tags($m[1]))
                : '';
            $text = Sanitizer::text($part);
            if (mb_strlen($text) < 40) {
                continue;
            }
            // Une section très longue est re-découpée par taille.
            if (mb_strlen($text) > self::CHUNK * 1.6) {
                self::addChunks($chunks, $heading !== '' ? $title . ' — ' . $heading : $title, $url, 'page', $text);
                continue;
            }
            $chunks[] = [
                'title'    => $heading !== '' ? $title . ' — ' . $heading : $title,
                'url'      => $url,
                'kind'     => 'page',
                'text'     => $text,
                'haystack' => Index::haystack([$title, $heading, $text]),
            ];
        }
    }

    private static function addChunks(array &$chunks, string $title, string $url, string $kind, string $text): void
    {
        $text = trim((string) preg_replace('/[ \t]+/', ' ', $text));
        if ($text === '') {
            return;
        }

        $length = mb_strlen($text);
        $step = self::CHUNK - self::OVERLAP;
        for ($offset = 0; $offset < $length; $offset += $step) {
            $piece = mb_substr($text, $offset, self::CHUNK);

            // On ne coupe ni en plein mot ni en plein milieu de phrase :
            // un fragment doit rester lisible tel quel s'il est cité.
            if ($offset > 0) {
                $start = self::firstBreak($piece);
                if ($start > 0 && $start < 80) {
                    $piece = mb_substr($piece, $start + 1);
                }
            }
            if ($offset + self::CHUNK < $length) {
                $cut = max(
                    mb_strrpos($piece, '. ') ?: 0,
                    mb_strrpos($piece, "\n") ?: 0,
                );
                if ($cut > self::CHUNK * 0.5) {
                    $piece = mb_substr($piece, 0, $cut + 1);
                }
            }

            $piece = trim($piece);
            if (mb_strlen($piece) < 40) {
                continue;
            }
            $chunks[] = [
                'title'    => $title,
                'url'      => $url,
                'kind'     => $kind,
                'text'     => $piece,
                'haystack' => Index::haystack([$title, $piece]),
            ];
            if ($length <= self::CHUNK) {
                break;
            }
        }
    }

    /** Position du premier séparateur (espace ou retour ligne), ou 0. */
    private static function firstBreak(string $text): int
    {
        $space = mb_strpos($text, ' ');
        $newline = mb_strpos($text, "\n");
        $candidates = array_filter([$space, $newline], static fn($v) => $v !== false);
        return $candidates === [] ? 0 : (int) min($candidates);
    }

    private const STOP = ['les', 'des', 'une', 'pour', 'dans', 'avec', 'sur', 'par', 'que', 'qui',
                          'est', 'sont', 'the', 'and', 'vous', 'nous', 'mon', 'mes', 'comment', 'quoi'];
}
