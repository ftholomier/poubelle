<?php
declare(strict_types=1);

namespace LD;

/**
 * Rédacteur d'articles du blog : à partir du brief de Fred (thème, idée, angle, mots-clés…), l'IA écrit un article complet
 * enregistré en brouillon, puis Google « Nano Banana » génère sa photo d'illustration. Rien n'est publié sans validation.
 */
final class BlogWriter
{
    public const LENGTHS = ['600' => 'Court (≈ 600 mots)', '1000' => 'Moyen (≈ 1 000 mots)', '1500' => 'Long (≈ 1 500 mots)'];
    public const SIZES = ['1K' => '1K (léger, conseillé)', '2K' => '2K (plus net)'];

    /**
     * Directions photo : à chaque image, un cadrage, une lumière et des couleurs différents de ceux des dernières photos
     * du blog, pour que deux articles n'aient jamais la même allure (même rédigés à la suite).
     */
    private const LOOKS = [
        'cadrage' => [
            'close'    => 'tight close-up on hands and the meaningful objects, shallow depth of field',
            'wide'     => 'wide establishing shot that shows the whole place and its atmosphere',
            'overhead' => 'top-down overhead view, seen from directly above',
            'shoulder' => 'over-the-shoulder view of the person in action',
            'candid'   => 'eye-level candid documentary moment, natural and unposed',
            'low'      => 'low-angle shot that gives a sense of momentum',
            'space'    => 'medium shot with the subject on one side and generous empty space on the other',
            'frame'    => 'shot through a foreground element (doorway, window, shelf) for depth',
        ],
        'lumiere' => [
            'morning'  => 'soft morning daylight',
            'golden'   => 'warm late-afternoon sun with long shadows',
            'overcast' => 'bright, even overcast daylight',
            'blue'     => 'cool blue-hour light through a window',
            'lamp'     => 'cosy warm evening lamp light',
            'crisp'    => 'crisp midday light with strong contrast',
        ],
        'couleurs' => [
            'muted'   => 'natural muted tones',
            'earthy'  => 'warm earthy tones (wood, terracotta, ochre)',
            'fresh'   => 'fresh greens and blues',
            'vivid'   => 'vivid saturated colours',
            'pastel'  => 'soft pastel tones',
            'deep'    => 'deep rich colours with dark accents',
        ],
    ];

    public static function settings(): array
    {
        return ((array) (Ai::config()['blog_writer'] ?? [])) + ['image_model' => 'gemini-nano-banana-2.1', 'image_size' => '1K', 'image_style' => 'photo éditoriale réaliste'];
    }

    public static function canWrite(): bool
    {
        return Ai::resolve('blog') !== null;
    }

    public static function canImage(): bool
    {
        return Ai::hasKey('gemini');
    }

    /**
     * Écrit l'article et l'enregistre en brouillon. Retourne le brouillon (article du blog) et le coût de l'appel.
     * $web : l'IA peut vérifier l'actualité du sujet sur le web (nombre maximal de recherches) ; $extra : infos gardées avec l'article.
     * @param array{theme:string, sujet:string, angle:string, mots_cles:string, public:string, longueur:string} $brief
     */
    public static function write(array $brief, bool|int $web = false, array $extra = []): array
    {
        $raw = Ai::complete('blog', '', [['role' => 'user', 'content' => Ai::fill(Ai::prompt('blog_writer'), [
            'photos_recentes' => self::recentScenes(),
            'theme'     => $brief['theme'],
            'sujet'     => $brief['sujet'],
            'angle'     => $brief['angle'] !== '' ? $brief['angle'] : 'à toi de choisir l’angle le plus utile pour le public visé',
            'mots_cles' => $brief['mots_cles'] !== '' ? $brief['mots_cles'] : 'à déduire du sujet',
            'public'    => $brief['public'] !== '' ? $brief['public'] : 'dirigeants de TPE, artisans, commerçants et indépendants',
            'longueur'  => $brief['longueur'],
        ])]], null, $web);
        $cost = Ai::$last['usd'] ?? null;
        [$meta, $html] = self::parse($raw);
        $title = clean_text((string) ($meta['titre'] ?? ''), 200);
        if ($title === '' || mb_strlen(strip_tags($html)) < 300) {
            throw new AiException('L’article reçu est incomplet. Relance la rédaction (ou augmente le nombre maximal de jetons de « Rédaction d’articles » dans IA & clés API).');
        }
        $post = Posts::save([
            'id' => '', 'title' => $title, 'slug' => '', 'theme' => $brief['theme'],
            'excerpt' => clean_text((string) ($meta['extrait'] ?? ''), 400),
            'image' => '', 'content' => $html, 'date' => date('Y-m-d'), 'published' => false,
            'seo_title' => clean_text((string) ($meta['seo_titre'] ?? ''), 160),
            'seo_description' => clean_text((string) ($meta['seo_description'] ?? ''), 320),
            'ai' => $extra + [
                'brief' => $brief, 'at' => date('c'), 'model' => (Ai::$last['provider'] ?? '') . ':' . (Ai::$last['model'] ?? ''),
                'image_prompt' => clean_text((string) ($meta['image_prompt'] ?? ''), 1200),
                'image_alt' => clean_text((string) ($meta['image_alt'] ?? $title), 200),
                'cost_text' => $cost, 'cost_image' => null,
            ],
        ]);
        return $post;
    }

    /**
     * Génère (ou régénère) la photo de l'article avec Nano Banana et l'enregistre dans la médiathèque.
     * $scene : la photo décrite par Fred ; vide, l'IA reprend la scène prévue pour l'article avec une nouvelle direction photo.
     */
    public static function illustrate(string $postId, string $style = '', string $model = '', string $size = '', string $scene = ''): array
    {
        $post = Posts::find($postId) ?? throw new \RuntimeException('Article introuvable.');
        $set = self::settings();
        $model = isset(Ai::IMAGE_MODELS[$model]) ? $model : (string) $set['image_model'];
        $size = isset(self::SIZES[$size]) ? $size : (string) $set['image_size'];
        $ai = (array) ($post['ai'] ?? []);
        $scene = trim($scene);
        $look = $scene === '' ? self::pickLook($post['id']) : [];
        $prompt = Ai::fill(Ai::prompt('blog_image'), [
            'titre'        => $post['title'],
            'resume'       => (string) ($post['excerpt'] ?? '') !== '' ? $post['excerpt'] : mb_strimwidth(trim(strip_tags((string) $post['content'])), 0, 300, '…'),
            'points'       => self::headings((string) $post['content']),
            'image_prompt' => $scene !== '' ? $scene : ((string) ($ai['image_prompt'] ?? '') !== '' ? $ai['image_prompt'] : 'A concrete scene that shows what this article is about'),
            'direction'    => $look
                ? 'Framing: ' . self::LOOKS['cadrage'][$look['cadrage']] . '. Light: ' . self::LOOKS['lumiere'][$look['lumiere']] . '. Colours: ' . self::LOOKS['couleurs'][$look['couleurs']] . '.'
                : 'Follow the scene description exactly (framing, light and colours included when given).',
            'style'        => $style !== '' ? $style : (string) $set['image_style'],
        ]);
        $img = Ai::image('blog_image', $prompt, $model, '16:9', $size);
        $alt = $scene !== '' ? mb_strimwidth($scene, 0, 200, '…') : (string) ($ai['image_alt'] ?? $post['title']);
        $entry = Media::fromBinary($img['data'], slugify($post['title']) . '.jpg', $alt);
        $ai['cost_image'] = (float) ($ai['cost_image'] ?? 0) + (float) (Ai::$last['usd'] ?? 0);
        $ai['image_model'] = $model;
        $ai['image_look'] = $look;
        $ai['image_scene'] = $scene;
        $ai['image_alt'] = $alt;
        $ai['at'] ??= date('c');
        // Photos déjà générées pour cet article (la plus récente d'abord) : on peut revenir à l'une d'elles.
        $ai['images'] = array_slice(array_values(array_unique(array_filter([$entry['path'], ...(array) ($ai['images'] ?? []), $post['image']]))), 0, 10);
        return Posts::save(array_merge($post, ['image' => $entry['path'], 'ai' => $ai]));
    }

    /** Scènes des dernières photos du blog, données au rédacteur pour qu'il en imagine une toute différente. */
    private static function recentScenes(): string
    {
        $lines = [];
        foreach (Posts::all() as $p) {
            $ai = (array) ($p['ai'] ?? []);
            $scene = trim((string) (($ai['image_scene'] ?? '') !== '' ? $ai['image_scene'] : ($ai['image_prompt'] ?? '')));
            if ($scene !== '' && !empty($p['image'])) {
                $lines[] = '- ' . mb_strimwidth($scene, 0, 220, '…');
            }
            if (count($lines) >= 8) {
                break;
            }
        }
        return $lines ? implode("\n", $lines) : '(aucune photo pour l’instant)';
    }

    /** Cadrage, lumière et couleurs absents des dernières photos du blog (tirés au sort parmi les autres). */
    private static function pickLook(string $exceptId): array
    {
        $used = ['cadrage' => [], 'lumiere' => [], 'couleurs' => []];
        $recent = array_filter(Posts::all(), static fn($p) => $p['id'] !== $exceptId && !empty($p['ai']['image_look']));
        usort($recent, static fn($a, $b) => strcmp((string) ($b['updated_at'] ?? ''), (string) ($a['updated_at'] ?? '')));
        $current = (array) (Posts::find($exceptId)['ai']['image_look'] ?? []);
        foreach ([$current, ...array_map(static fn($p) => (array) $p['ai']['image_look'], array_slice($recent, 0, 5))] as $look) {
            foreach ($used as $dim => $_) {
                if (isset($look[$dim])) {
                    $used[$dim][] = $look[$dim];
                }
            }
        }
        $out = [];
        foreach (self::LOOKS as $dim => $options) {
            $free = array_values(array_diff(array_keys($options), $used[$dim]));
            // Toutes déjà prises : on évite au moins les deux plus récentes.
            $free = $free ?: array_values(array_diff(array_keys($options), array_slice($used[$dim], 0, 2)));
            $out[$dim] = $free[random_int(0, count($free) - 1)];
        }
        return $out;
    }

    /** Intertitres de l'article : les idées clés que la photo doit évoquer. */
    private static function headings(string $html): string
    {
        preg_match_all('~<h2[^>]*>(.*?)</h2>~is', $html, $m);
        $h = array_slice(array_filter(array_map(static fn($t) => trim(html_entity_decode(strip_tags($t), ENT_QUOTES, 'UTF-8')), $m[1])), 0, 5);
        return $h ? implode(' ; ', $h) : '(voir le titre)';
    }

    /** Sépare les métadonnées (JSON) du corps HTML de la réponse. */
    private static function parse(string $raw): array
    {
        $meta = [];
        $html = $raw;
        if (preg_match('/---META---\s*(\{.*?\})\s*---ARTICLE---\s*(.*)$/s', $raw, $m)) {
            $meta = json_decode($m[1], true) ?: (Ai::parseJson($m[1]) ?? []);
            $html = $m[2];
        } elseif ($j = Ai::parseJson($raw)) {
            $meta = $j;
            $html = (string) ($j['article'] ?? $j['contenu'] ?? '');
        }
        // Code éventuellement entouré de ```html … ``` ; titre <h1> retiré (le titre de l'article est à part).
        $html = trim((string) preg_replace(['/^```(?:html)?\s*/i', '/```\s*$/', '~<h1[^>]*>.*?</h1>~is'], '', trim($html)));
        return [$meta, $html];
    }
}
