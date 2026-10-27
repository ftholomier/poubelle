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

    /** Génère (ou régénère) la photo de l'article avec Nano Banana et l'enregistre dans la médiathèque. */
    public static function illustrate(string $postId, string $style = '', string $model = '', string $size = ''): array
    {
        $post = Posts::find($postId) ?? throw new \RuntimeException('Article introuvable.');
        $set = self::settings();
        $model = isset(Ai::IMAGE_MODELS[$model]) ? $model : (string) $set['image_model'];
        $size = isset(self::SIZES[$size]) ? $size : (string) $set['image_size'];
        $ai = (array) ($post['ai'] ?? []);
        $prompt = Ai::fill(Ai::prompt('blog_image'), [
            'titre' => $post['title'],
            'image_prompt' => (string) ($ai['image_prompt'] ?? '') !== '' ? $ai['image_prompt'] : 'A scene that illustrates: ' . $post['title'],
            'style' => $style !== '' ? $style : (string) $set['image_style'],
        ]);
        $img = Ai::image('blog_image', $prompt, $model, '16:9', $size);
        $entry = Media::fromBinary($img['data'], slugify($post['title']) . '.jpg', (string) ($ai['image_alt'] ?? $post['title']));
        $ai['cost_image'] = (float) ($ai['cost_image'] ?? 0) + (float) (Ai::$last['usd'] ?? 0);
        $ai['image_model'] = $model;
        return Posts::save(array_merge($post, ['image' => $entry['path'], 'ai' => $ai]));
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
