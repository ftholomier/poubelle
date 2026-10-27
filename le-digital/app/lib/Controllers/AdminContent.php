<?php
declare(strict_types=1);

namespace LD\Controllers;

use LD\Content;
use LD\Http;
use LD\Media;
use LD\Posts;
use LD\Reviews;
use LD\Router;
use LD\Settings;
use LD\Store;

/**
 * Back-office : contenus des pages (éditeur générique), avis, blog et médiathèque.
 */
final class AdminContent
{
    public static function routes(Router $r): void
    {
        $g = [Admin::class, 'guard'];
        $r->get('/admin/contenus', $g([self::class, 'pages']));
        $r->get('/admin/contenus/{page}', $g([self::class, 'edit']));
        $r->post('/admin/contenus/{page}', $g([self::class, 'save'], true));
        $r->post('/admin/contenus/{page}/reinitialiser', $g([self::class, 'reset'], true));

        $r->get('/admin/avis', $g([self::class, 'reviews']));
        $r->get('/admin/avis/{id}', $g([self::class, 'review']));
        $r->post('/admin/avis/{id}', $g([self::class, 'saveReview'], true));
        $r->post('/admin/avis/{id}/supprimer', $g([self::class, 'deleteReview'], true));

        $r->get('/admin/blog', $g([self::class, 'posts']));
        $r->get('/admin/blog/ia', $g([self::class, 'writer']));
        $r->post('/admin/api/blog/ia/texte', $g([self::class, 'writerText'], true));
        $r->post('/admin/api/blog/ia/image', $g([self::class, 'writerImage'], true));
        $r->post('/admin/api/blog/ia/idees', $g([self::class, 'ideas'], true));
        $r->post('/admin/api/blog/ia/idee', $g([self::class, 'ideaWrite'], true));
        $r->post('/admin/api/blog/ia/idee/ecarter', $g([self::class, 'ideaDismiss'], true));
        $r->post('/admin/api/blog/ia/planifier', $g([self::class, 'schedulePost'], true));
        $r->post('/admin/blog/{id}/publier', $g([self::class, 'publishPost'], true));
        $r->get('/admin/blog/linkedin', $g([self::class, 'linkedin']));
        $r->post('/admin/blog/linkedin', $g([self::class, 'saveLinkedin'], true));
        $r->get('/admin/blog/{id}', $g([self::class, 'post']));
        $r->post('/admin/blog/{id}', $g([self::class, 'savePost'], true));
        $r->post('/admin/blog/{id}/supprimer', $g([self::class, 'deletePost'], true));

        $r->get('/admin/medias', $g([self::class, 'media']));
        $r->get('/admin/api/medias', $g([self::class, 'mediaList']));
        $r->post('/admin/api/medias', $g([self::class, 'mediaUpload'], true));
        $r->post('/admin/api/medias/{id}/alt', $g([self::class, 'mediaAlt'], true));
        $r->post('/admin/medias/{id}/supprimer', $g([self::class, 'mediaDelete'], true));
    }

    /** URL publique d'une page de contenu (lien « Voir la page »). */
    private static function publicUrl(string $page): string
    {
        return match ($page) {
            'home', 'global' => '/', 'sur-mesure' => '/sur-mesure-ia', 'formation' => '/formation', 'accompagnement' => '/accompagnement',
            'solutions' => '/mes-solutions', 'qui' => '/qui-suis-je', 'avis' => '/avis', 'blog' => '/blog', 'rdv' => '/on-prend-rdv',
            'zones' => '/zones/' . (Content::get('zones')['cities'][0]['slug'] ?? 'besancon'), 'mentions' => '/mentions-legales', 'distance' => '/partout-en-france', default => '/',
        };
    }

    // ------------------------------------------------------------------ contenus

    public static function pages(): never
    {
        $pages = [];
        foreach (Content::PAGES as $key => $label) {
            $file = \LD\Store::path('content/' . $key . '.json');
            $pages[] = ['key' => $key, 'label' => $label, 'url' => self::publicUrl($key), 'updated' => is_file($file) ? filemtime($file) : null];
        }
        Admin::view('content-list', ['pages' => $pages], 'Contenus des pages');
    }

    public static function edit(string $page): never
    {
        if (!isset(Content::PAGES[$page])) {
            Admin::back('/admin/contenus', 'err', 'Page inconnue.');
        }
        Admin::view('content-edit', ['page' => $page, 'label' => Content::PAGES[$page], 'data' => Content::get($page), 'url' => self::publicUrl($page)], 'Contenus · ' . Content::PAGES[$page]);
    }

    /**
     * Valide le JSON envoyé par l'éditeur : même forme que le contenu par défaut, textes nettoyés.
     */
    private static function sanitize(mixed $value, mixed $shape): mixed
    {
        if (is_array($value)) {
            $out = [];
            $isList = array_is_list($value);
            $itemShape = is_array($shape) && array_is_list($shape) ? ($shape[0] ?? null) : null;
            foreach ($value as $k => $v) {
                if (!$isList && !preg_match('/^[a-z0-9_]{1,40}$/i', (string) $k)) {
                    continue;
                }
                $out[$k] = self::sanitize($v, $isList ? $itemShape : (is_array($shape) ? ($shape[$k] ?? null) : null));
            }
            return $out;
        }
        if (is_bool($shape)) {
            return (bool) $value;
        }
        if (is_int($shape) || is_float($shape)) {
            return is_numeric($value) ? $value + 0 : $shape;
        }
        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            return $value;
        }
        // Texte : on retire les balises (le rendu échappe de toute façon) et les caractères de contrôle.
        $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', strip_tags((string) $value)) ?? '';
        return mb_substr($v, 0, 20000);
    }

    public static function save(string $page): never
    {
        if (!isset(Content::PAGES[$page])) {
            Admin::back('/admin/contenus', 'err', 'Page inconnue.');
        }
        $data = json_decode((string) ($_POST['content_json'] ?? ''), true);
        if (!is_array($data)) {
            Admin::back('/admin/contenus/' . $page, 'err', 'Contenu illisible, rien n’a été modifié.');
        }
        Content::save($page, self::sanitize($data, Content::defaults($page)));
        Admin::back('/admin/contenus/' . $page, 'ok', 'Page enregistrée. Le site est à jour.');
    }

    public static function reset(string $page): never
    {
        if (isset(Content::PAGES[$page])) {
            Content::reset($page);
        }
        Admin::back('/admin/contenus/' . $page, 'ok', 'Contenu réinitialisé avec les textes de la maquette.');
    }

    // ------------------------------------------------------------------ avis

    public static function reviews(): never
    {
        Admin::view('reviews', ['reviews' => Reviews::all()], 'Avis clients');
    }

    public static function review(string $id): never
    {
        $r = $id === 'nouveau' ? ['id' => '', 'rating' => 5, 'text' => '', 'name' => '', 'activity' => '', 'tag' => Reviews::TAGS[0], 'photo' => '', 'home' => false, 'published' => true, 'order' => 0, 'source' => '']
            : (Reviews::find($id) ?? Admin::back('/admin/avis', 'err', 'Avis introuvable.'));
        Admin::view('review-edit', ['r' => $r], $id === 'nouveau' ? 'Nouvel avis' : 'Modifier un avis');
    }

    public static function saveReview(string $id): never
    {
        $existing = $id !== 'nouveau' ? (Reviews::find($id) ?? []) : [];
        $tag = in_array($_POST['tag'] ?? '', Reviews::TAGS, true) ? $_POST['tag'] : '';
        if ($existing && Reviews::isGoogle($existing)) {
            // Avis Google : texte, note et auteur restent ceux de Google ; seuls les réglages d'affichage changent.
            Reviews::save(array_merge($existing, [
                'activity'  => clean_text((string) ($_POST['activity'] ?? ''), 120),
                'tag'       => $tag,
                'home'      => !empty($_POST['home']),
                'published' => !empty($_POST['published']),
                'order'     => (int) ($_POST['order'] ?? 0),
            ]));
            Admin::back('/admin/avis/' . $id, 'ok', 'Avis enregistré.');
        }
        $text = trim(clean_text((string) ($_POST['text'] ?? ''), 3000));
        $name = clean_text((string) ($_POST['name'] ?? ''), 80);
        if ($text === '' || $name === '') {
            Admin::back('/admin/avis/' . $id, 'err', 'Le texte et le prénom sont obligatoires.');
        }
        $r = Reviews::save(array_merge($existing, [
            'id'        => $id === 'nouveau' ? '' : $id,
            'rating'    => max(1, min(5, (int) ($_POST['rating'] ?? 5))),
            'text'      => $text,
            'name'      => $name,
            'activity'  => clean_text((string) ($_POST['activity'] ?? ''), 120),
            'tag'       => $tag,
            'photo'     => clean_text((string) ($_POST['photo'] ?? ''), 300),
            'home'      => !empty($_POST['home']),
            'published' => !empty($_POST['published']),
            'order'     => (int) ($_POST['order'] ?? 0),
            'source'    => clean_text((string) ($_POST['source'] ?? ''), 200),
        ]));
        Admin::back('/admin/avis/' . $r['id'], 'ok', 'Avis enregistré.');
    }

    public static function deleteReview(string $id): never
    {
        Reviews::delete($id);
        Admin::back('/admin/avis', 'ok', 'Avis supprimé.');
    }

    // ------------------------------------------------------------------ blog

    public static function posts(): never
    {
        Admin::view('posts', ['posts' => Posts::all(), 'themes' => Content::get('blog')['themes']], 'Blog');
    }

    public static function post(string $id): never
    {
        $themes = Content::get('blog')['themes'];
        $p = $id === 'nouveau'
            ? ['id' => '', 'title' => '', 'slug' => '', 'theme' => $themes[0] ?? '', 'excerpt' => '', 'image' => '', 'content' => '', 'date' => date('Y-m-d'), 'published' => false, 'seo_title' => '', 'seo_description' => '', 'read' => 1]
            : (Posts::find($id) ?? Admin::back('/admin/blog', 'err', 'Article introuvable.'));
        Admin::view('post-edit', ['p' => $p, 'themes' => $themes, 'canImage' => \LD\BlogWriter::canImage()], $id === 'nouveau' ? 'Nouvel article' : 'Modifier l’article');
    }

    public static function savePost(string $id): never
    {
        $title = clean_text((string) ($_POST['title'] ?? ''), 200);
        if ($title === '') {
            Admin::back('/admin/blog/' . $id, 'err', 'Le titre est obligatoire.');
        }
        $date = (string) ($_POST['date'] ?? date('Y-m-d'));
        $existing = $id !== 'nouveau' ? (Posts::find($id) ?? []) : [];
        $p = Posts::save(array_merge($existing, [
            'id'              => $id === 'nouveau' ? '' : $id,
            'title'           => $title,
            'slug'            => clean_text((string) ($_POST['slug'] ?? ''), 120),
            'theme'           => clean_text((string) ($_POST['theme'] ?? ''), 60),
            'excerpt'         => clean_text((string) ($_POST['excerpt'] ?? ''), 400),
            'image'           => clean_text((string) ($_POST['image'] ?? ''), 300),
            'content'         => (string) ($_POST['content'] ?? ''),
            'date'            => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : date('Y-m-d'),
            'published'       => !empty($_POST['published']),
            'seo_title'       => clean_text((string) ($_POST['seo_title'] ?? ''), 160),
            'seo_description' => clean_text((string) ($_POST['seo_description'] ?? ''), 320),
        ]));
        Admin::back('/admin/blog/' . $p['id'], 'ok', $p['published'] ? 'Article enregistré et publié.' : 'Brouillon enregistré.');
    }

    public static function deletePost(string $id): never
    {
        Posts::delete($id);
        Admin::back('/admin/blog', 'ok', 'Article supprimé.');
    }

    // ------------------------------------------------------------------ rédaction d'articles par l'IA

    public static function writer(): never
    {
        $drafts = array_values(array_filter(Posts::all(), static fn($p) => !empty($p['ai'])));
        $batches = \LD\BlogIdeas::batches();
        $lot = (string) ($_GET['lot'] ?? '');
        $batch = $batches[0] ?? null;
        foreach ($batches as $b) {
            if ($b['id'] === $lot) {
                $batch = $b;
            }
        }
        Admin::view('blog-ai', [
            'themes' => Content::get('blog')['themes'], 'set' => \LD\BlogWriter::settings(), 'drafts' => array_slice($drafts, 0, 8),
            'canWrite' => \LD\BlogWriter::canWrite(), 'canImage' => \LD\BlogWriter::canImage(), 'writer' => \LD\Ai::resolve('blog'),
            'batches' => $batches, 'batch' => $batch, 'ideasAi' => \LD\Ai::resolve('blog_ideas'),
        ], 'Blog · Écrire avec l’IA');
    }

    /** Idées d'articles : l'IA fait sa veille (actualité, tendances, recherche web) et propose une mosaïque de sujets. */
    public static function ideas(): never
    {
        @set_time_limit(360);
        ignore_user_abort(true);
        try {
            $batch = \LD\BlogIdeas::propose(clean_text((string) Http::input('focus', ''), 200), (int) Http::input('n', 6));
        } catch (\LD\AiException $e) {
            Http::fail($e->getMessage(), 502);
        }
        Http::sendJson(['ok' => true, 'batch' => $batch['id'], 'html' => \LD\View::render('admin/blog-ideas-grid', ['batch' => $batch, 'canImage' => \LD\BlogWriter::canImage()])]);
    }

    /** Une idée cochée : l'IA rédige l'article (brouillon), en vérifiant l'actualité du sujet sur le web si demandé. */
    public static function ideaWrite(): never
    {
        @set_time_limit(360);
        ignore_user_abort(true);
        $batchId = clean_text((string) Http::input('batch', ''), 40);
        $ideaId = clean_text((string) Http::input('idea', ''), 20);
        $idea = \LD\BlogIdeas::find($batchId, $ideaId) ?? Http::fail('Idée introuvable : relance une proposition.', 404);
        if ($idea['post_id'] !== '' && ($p = Posts::find($idea['post_id']))) {
            Http::sendJson(['ok' => true, 'post' => self::writerPayload($p)]);
        }
        $angle = $idea['angle'];
        if ($idea['pourquoi'] !== '') {
            $angle .= "\nPourquoi en parler maintenant : " . $idea['pourquoi'];
        }
        if ($idea['sources']) {
            $angle .= "\nSources repérées (pour le contexte, sans lien dans l’article) :\n" . implode("\n", array_map(static fn($s) => '  • ' . $s['titre'] . ' (' . $s['url'] . ')', $idea['sources']));
        }
        $brief = ['theme' => $idea['theme'], 'sujet' => $idea['sujet'] !== '' ? $idea['sujet'] : $idea['titre'], 'angle' => $angle,
                  'mots_cles' => $idea['mots_cles'], 'public' => $idea['public'], 'longueur' => $idea['longueur']];
        try {
            $p = \LD\BlogWriter::write($brief, Http::input('web', '1') ? 3 : false, ['idea' => ['batch' => $batchId, 'id' => $ideaId, 'titre' => $idea['titre']], 'sources' => $idea['sources']]);
        } catch (\LD\AiException $e) {
            Http::fail($e->getMessage(), 502);
        }
        \LD\BlogIdeas::mark($batchId, $ideaId, ['status' => 'written', 'post_id' => $p['id']]);
        Http::sendJson(['ok' => true, 'post' => self::writerPayload($p)]);
    }

    public static function ideaDismiss(): never
    {
        \LD\BlogIdeas::mark(clean_text((string) Http::input('batch', ''), 40), clean_text((string) Http::input('idea', ''), 20), ['status' => 'dismissed']);
        Http::sendJson(['ok' => true]);
    }

    /** Date de parution d'un article : tout de suite, programmé à une date, ou laissé en brouillon. */
    public static function schedulePost(): never
    {
        $p = Posts::find(clean_text((string) Http::input('id', ''), 40)) ?? Http::fail('Article introuvable.', 404);
        $mode = (string) Http::input('mode', 'draft');
        $date = (string) Http::input('date', '');
        $today = date('Y-m-d');
        if ($mode === 'date' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date))) {
            Http::fail('Choisis une date de parution valide.', 422);
        }
        $p = match (true) {
            $mode === 'date' && $date > $today => Posts::save(array_merge($p, ['published' => true, 'date' => $date])),
            $mode === 'now' || $mode === 'date' => Posts::save(array_merge($p, ['published' => true, 'date' => $today])),
            default => Posts::save(array_merge($p, ['published' => false, 'date' => $today])),
        };
        Http::sendJson(['ok' => true, 'state' => self::postState($p), 'post' => self::writerPayload($p)]);
    }

    /** État d'un article pour la mosaïque d'idées : en ligne, programmé (date), brouillon. */
    public static function postState(array $p): array
    {
        $date = substr((string) $p['date'], 0, 10);
        if (empty($p['published'])) {
            return ['key' => 'draft', 'label' => 'Brouillon à relire'];
        }
        return $date > date('Y-m-d')
            ? ['key' => 'scheduled', 'label' => 'Programmé le ' . date('d/m/Y', (int) strtotime($date))]
            : ['key' => 'live', 'label' => 'En ligne', 'url' => '/blog/' . $p['slug']];
    }

    /** Étape 1 : l'IA écrit l'article (brouillon). */
    public static function writerText(): never
    {
        @set_time_limit(300);
        ignore_user_abort(true);
        $themes = Content::get('blog')['themes'];
        $theme = clean_text((string) Http::input('theme', ''), 60);
        $brief = [
            'theme'     => in_array($theme, $themes, true) ? $theme : ($themes[0] ?? ''),
            'sujet'     => clean_text((string) Http::input('sujet', ''), 300),
            'angle'     => clean_text((string) Http::input('angle', ''), 3000),
            'mots_cles' => clean_text((string) Http::input('mots_cles', ''), 300),
            'public'    => clean_text((string) Http::input('public', ''), 200),
            'longueur'  => isset(\LD\BlogWriter::LENGTHS[(string) Http::input('longueur', '')]) ? (string) Http::input('longueur') : '1000',
        ];
        if ($brief['sujet'] === '') {
            Http::fail('Donne au moins le sujet de l’article (une phrase suffit).', 422);
        }
        try {
            $p = \LD\BlogWriter::write($brief);
        } catch (\LD\AiException $e) {
            Http::fail($e->getMessage(), 502);
        }
        Http::sendJson(['ok' => true, 'post' => self::writerPayload($p)]);
    }

    /** Étape 2 : Nano Banana génère la photo de l'article (ou une autre, éventuellement décrite par Fred). */
    public static function writerImage(): never
    {
        @set_time_limit(240);
        ignore_user_abort(true);
        try {
            $p = \LD\BlogWriter::illustrate(clean_text((string) Http::input('id', ''), 40), clean_text((string) Http::input('style', ''), 200), (string) Http::input('model', ''), (string) Http::input('size', ''),
                clean_text((string) Http::input('prompt', ''), 1500));
        } catch (\LD\AiException | \RuntimeException $e) {
            Http::fail($e->getMessage(), 502);
        }
        Http::sendJson(['ok' => true, 'post' => self::writerPayload($p)]);
    }

    /** Validation : l'article part en ligne (date du jour). */
    public static function publishPost(string $id): never
    {
        $p = Posts::find($id) ?? Admin::back('/admin/blog', 'err', 'Article introuvable.');
        $p = Posts::save(array_merge($p, ['published' => true, 'date' => min((string) $p['date'], date('Y-m-d'))]));
        Admin::back('/admin/blog', 'ok', 'Article « ' . $p['title'] . ' » publié sur le blog.');
    }

    private static function writerPayload(array $p): array
    {
        $ai = (array) ($p['ai'] ?? []);
        $rate = \LD\AiCosts::rate();
        $fmt = static fn($usd) => $usd === null ? null : number_format((float) $usd * $rate, 3, ',', ' ') . ' €';
        return [
            'id' => $p['id'], 'title' => $p['title'], 'excerpt' => $p['excerpt'], 'theme' => $p['theme'], 'read' => (int) $p['read'],
            'content' => $p['content'], 'image' => $p['image'] !== '' ? \LD\Media::best($p['image']) : '', 'image_alt' => $ai['image_alt'] ?? '',
            'image_path' => $p['image'], 'images' => array_values((array) ($ai['images'] ?? [])), 'image_scene' => (string) ($ai['image_scene'] ?? ''),
            'seo_title' => $p['seo_title'], 'seo_description' => $p['seo_description'], 'slug' => $p['slug'],
            'cost_text' => $fmt($ai['cost_text'] ?? null), 'cost_image' => isset($ai['cost_image']) ? $fmt($ai['cost_image']) : null,
            'edit' => '/admin/blog/' . $p['id'], 'publish' => '/admin/blog/' . $p['id'] . '/publier',
        ];
    }

    /** Fil de posts LinkedIn : code d'une application externe affiché sur la page Blog. */
    public static function linkedin(): never
    {
        Admin::view('blog-linkedin', ['li' => (array) Settings::get('linkedin_feed', [])], 'Blog · Posts LinkedIn');
    }

    public static function saveLinkedin(): never
    {
        $code = trim(str_replace("\r\n", "\n", (string) ($_POST['code'] ?? '')));
        if (mb_strlen($code) > 20000) {
            Admin::back('/admin/blog/linkedin', 'err', 'Code trop long (20 000 caractères maximum).');
        }
        if (preg_match('#<\?(php|=)?#i', $code)) {
            Admin::back('/admin/blog/linkedin', 'err', 'Le code ne peut contenir que du HTML / JavaScript.');
        }
        $s = Store::read('content/settings.json', []);
        $s['linkedin_feed'] = [
            'code'  => $code,
            'place' => in_array($_POST['place'] ?? '', Posts::LINKEDIN_PLACES, true) ? $_POST['place'] : 'split',
            'title' => clean_text((string) ($_POST['title'] ?? ''), 120),
            'v2'    => true,
        ];
        Settings::save($s);
        Admin::back('/admin/blog/linkedin', 'ok', $code === '' ? 'Fil LinkedIn retiré de la page Blog.' : ($s['linkedin_feed']['place'] === 'off' ? 'Code enregistré (masqué sur la page Blog).' : 'Code enregistré : tes posts LinkedIn sont en ligne sur la page Blog.'));
    }

    // ------------------------------------------------------------------ médias

    public static function media(): never
    {
        Admin::view('media', ['items' => Media::all()], 'Médiathèque');
    }

    public static function mediaList(): never
    {
        Http::sendJson(['ok' => true, 'items' => array_map(static fn($m) => ['id' => $m['id'], 'path' => $m['path'], 'thumb' => $m['variants'][480] ?? $m['path'], 'w' => $m['w'] ?? 0, 'h' => $m['h'] ?? 0, 'alt' => $m['alt'] ?? '', 'name' => $m['name'] ?? ''], Media::all())]);
    }

    public static function mediaUpload(): never
    {
        $files = $_FILES['files'] ?? $_FILES['file'] ?? null;
        if (!$files) {
            Http::fail('Aucun fichier reçu (vérifie la taille maximale autorisée par ton hébergement).', 422);
        }
        $list = [];
        if (is_array($files['name'])) {
            foreach ($files['name'] as $i => $n) {
                $list[] = ['name' => $n, 'type' => $files['type'][$i], 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]];
            }
        } else {
            $list[] = $files;
        }
        $done = [];
        $errors = [];
        foreach ($list as $f) {
            try {
                $done[] = Media::upload($f, clean_text((string) ($_POST['alt'] ?? ''), 200));
            } catch (\RuntimeException $e) {
                $errors[] = $f['name'] . ' : ' . $e->getMessage();
            }
        }
        if (!$done && $errors) {
            Http::fail(implode("\n", $errors), 422);
        }
        Http::sendJson(['ok' => true, 'items' => $done, 'errors' => $errors]);
    }

    public static function mediaAlt(string $id): never
    {
        Media::updateAlt($id, clean_text((string) Http::input('alt', ''), 200));
        Http::sendJson(['ok' => true]);
    }

    public static function mediaDelete(string $id): never
    {
        Media::delete($id);
        Admin::back('/admin/medias', 'ok', 'Image supprimée.');
    }
}
