<?php
declare(strict_types=1);

namespace LD;

/**
 * Idées d'articles proposées par l'IA : veille de l'actualité et des tendances (Google Actualités et Google Tendances,
 * plus la recherche web du modèle quand il en dispose), croisée avec les thèmes et les articles du site.
 * Chaque lot d'idées est gardé (data/blog-ideas.json) : Fred coche celles qu'il veut et choisit leur date de parution.
 */
final class BlogIdeas
{
    private const FILE = 'data/blog-ideas.json';

    /** Recherches de veille dans Google Actualités (30 derniers jours). */
    public const QUERIES = [
        'intelligence artificielle TPE PME', 'IA artisans commerçants', 'site internet petite entreprise',
        'référencement local Google entreprise', 'webmarketing TPE', 'CRM PME logiciel', 'formation digitale entreprise CPF',
        'transformation numérique entrepreneurs',
    ];

    /** Derniers lots d'idées (le plus récent en premier). */
    public static function batches(): array
    {
        return Store::read(self::FILE, []);
    }

    public static function latest(): ?array
    {
        return self::batches()[0] ?? null;
    }

    /**
     * Signaux du moment : titres récents de Google Actualités sur les thèmes du site, et tendances de recherche en France.
     * @return array{news: string[], trends: string[]}
     */
    public static function signals(string $focus = ''): array
    {
        $queries = self::QUERIES;
        if ($focus !== '') {
            array_unshift($queries, $focus);
        }
        $news = [];
        $seen = [];
        foreach (array_slice($queries, 0, 9) as $q) {
            $url = 'https://news.google.com/rss/search?q=' . rawurlencode($q . ' when:30d') . '&hl=fr&gl=FR&ceid=FR:fr';
            foreach (array_slice(self::rss($url), 0, 6) as $it) {
                $key = mb_strtolower(mb_substr($it['title'], 0, 60));
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $news[] = '- ' . ($it['date'] ? date('d/m', $it['date']) . ' · ' : '') . $it['title'] . ' [recherche « ' . $q . ' »]';
            }
        }
        $trends = [];
        foreach (array_slice(self::rss('https://trends.google.com/trending/rss?geo=FR'), 0, 15) as $it) {
            $trends[] = '- ' . $it['title'] . ($it['traffic'] !== '' ? ' (' . $it['traffic'] . ' recherches)' : '');
        }
        return ['news' => array_slice($news, 0, 45), 'trends' => $trends];
    }

    /** Lecture d'un flux RSS (titre, date, trafic approximatif pour Google Tendances). */
    private static function rss(string $url): array
    {
        $r = Http::request('GET', $url, ['Accept' => 'application/rss+xml, application/xml'], null, 12);
        if ($r['error'] || $r['status'] !== 200 || !str_contains((string) $r['body'], '<item')) {
            return [];
        }
        $prev = libxml_use_internal_errors(true);
        $xml = simplexml_load_string((string) $r['body'], \SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NONET);
        libxml_use_internal_errors($prev);
        if (!$xml) {
            return [];
        }
        $out = [];
        foreach ($xml->channel->item ?? [] as $item) {
            $ht = $item->children('https://trends.google.com/trending/rss');
            $out[] = [
                'title'   => clean_text(html_entity_decode((string) $item->title, ENT_QUOTES, 'UTF-8'), 200),
                'date'    => strtotime((string) $item->pubDate) ?: 0,
                'traffic' => clean_text((string) ($ht->approx_traffic ?? ''), 20),
            ];
        }
        return $out;
    }

    /** Demande à l'IA une série d'idées d'articles, enregistrées comme nouveau lot. */
    public static function propose(string $focus, int $count): array
    {
        $count = max(3, min(10, $count));
        $themes = Content::get('blog')['themes'];
        $g = Content::get('global');
        $services = implode(' ; ', array_filter(array_map(static fn($s) => trim(strip_tags((string) ($s['title'] ?? ''))), (array) ($g['services'] ?? []))));
        $existing = array_map(static fn($p) => '- ' . $p['title'] . (empty($p['published']) ? ' (brouillon)' : ''), array_slice(Posts::all(), 0, 40));
        $ideas = [];
        foreach (array_slice(self::batches(), 0, 3) as $b) {
            foreach ($b['ideas'] as $i) {
                $ideas[] = '- ' . $i['titre'];
            }
        }
        $sig = self::signals($focus);
        $raw = Ai::complete('blog_ideas', '', [['role' => 'user', 'content' => Ai::fill(Ai::prompt('blog_ideas'), [
            'date'      => date('d/m/Y'),
            'nombre'    => $count,
            'themes'    => implode(', ', $themes),
            'services'  => $services !== '' ? $services : 'sites web, CRM métier, outils IA sur mesure, formation digitale',
            'solutions' => Solutions::summary(),
            'existants' => $existing ? implode("\n", $existing) : '(aucun article pour l’instant)',
            'deja'      => $ideas ? implode("\n", array_slice($ideas, 0, 30)) : '(aucune)',
            'actus'     => $sig['news'] ? implode("\n", $sig['news']) : '(actualités indisponibles)',
            'tendances' => $sig['trends'] ? implode("\n", $sig['trends']) : '(tendances indisponibles)',
            'focus'     => $focus !== '' ? $focus : 'aucune consigne particulière : choisis les sujets les plus porteurs pour le site',
        ])]], null, true);
        $usd = Ai::$last['usd'] ?? null;
        $web = (bool) (Ai::$last['web'] ?? false);
        $j = Ai::parseJson($raw);
        $list = (array) ($j['propositions'] ?? $j['articles'] ?? []);
        $out = [];
        foreach ($list as $p) {
            if (!is_array($p) || trim((string) ($p['titre'] ?? '')) === '') {
                continue;
            }
            $theme = (string) ($p['theme'] ?? '');
            $sources = [];
            foreach (array_slice((array) ($p['sources'] ?? []), 0, 4) as $src) {
                $u = is_array($src) ? (string) ($src['url'] ?? '') : (string) $src;
                if (preg_match('~^https?://~i', $u)) {
                    $sources[] = ['titre' => clean_text(is_array($src) ? (string) ($src['titre'] ?? $src['title'] ?? '') : '', 140) ?: (string) parse_url($u, PHP_URL_HOST), 'url' => mb_substr($u, 0, 500)];
                }
            }
            $out[] = [
                'id'        => bin2hex(random_bytes(4)),
                'titre'     => clean_text((string) $p['titre'], 200),
                'theme'     => in_array($theme, $themes, true) ? $theme : ($themes[0] ?? ''),
                'sujet'     => clean_text((string) ($p['sujet'] ?? $p['titre']), 300),
                'angle'     => clean_text((string) ($p['angle'] ?? ''), 1500),
                'mots_cles' => clean_text((string) ($p['mots_cles'] ?? ''), 300),
                'public'    => clean_text((string) ($p['public'] ?? ''), 200),
                'pourquoi'  => clean_text((string) ($p['pourquoi'] ?? ''), 600),
                'tendance'  => max(1, min(5, (int) ($p['tendance'] ?? 3))),
                'longueur'  => isset(BlogWriter::LENGTHS[(string) ($p['longueur'] ?? '')]) ? (string) $p['longueur'] : '1000',
                'sources'   => $sources,
                'status'    => 'new',
                'post_id'   => '',
            ];
        }
        if (!$out) {
            throw new AiException('L’IA n’a pas renvoyé de propositions exploitables. Relance la recherche.');
        }
        $batch = ['id' => date('ymdHis') . '-' . bin2hex(random_bytes(2)), 'at' => date('c'), 'focus' => $focus, 'web' => $web,
                  'signals' => count($sig['news']) + count($sig['trends']), 'usd' => $usd, 'ideas' => $out];
        Store::update(self::FILE, static function (array &$all) use ($batch): void {
            array_unshift($all, $batch);
            $all = array_slice($all, 0, 10);
        });
        return $batch;
    }

    public static function find(string $batchId, string $ideaId): ?array
    {
        foreach (self::batches() as $b) {
            if ($b['id'] === $batchId) {
                foreach ($b['ideas'] as $i) {
                    if ($i['id'] === $ideaId) {
                        return $i;
                    }
                }
            }
        }
        return null;
    }

    public static function mark(string $batchId, string $ideaId, array $changes): void
    {
        Store::update(self::FILE, static function (array &$all) use ($batchId, $ideaId, $changes): void {
            foreach ($all as &$b) {
                if ($b['id'] !== $batchId) {
                    continue;
                }
                foreach ($b['ideas'] as &$i) {
                    if ($i['id'] === $ideaId) {
                        $i = array_merge($i, $changes);
                    }
                }
            }
        });
    }
}
