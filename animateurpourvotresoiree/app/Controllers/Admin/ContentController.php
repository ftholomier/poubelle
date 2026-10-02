<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Cache;
use App\Core\Fs;
use App\Core\HttpException;
use App\Core\Image;
use App\Core\Request;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Core\Str;
use App\Core\Url;
use App\Services\Ai;
use App\Services\Blog;
use App\Services\Categories;
use App\Services\Pages;
use App\Services\Seo;
use App\Services\Settings;
use App\Services\Store;

/** Contenus : blog, pages, page d'accueil, métiers, occasions, médias. */
final class ContentController extends AdminController
{
    // ------------------------------------------------------------------- blog

    public function articles(): Response
    {
        $status = self::q('statut', 20);
        $q = Str::norm(self::q('q', 100));
        $items = Store::articles()->find(
            static fn ($a) => ($status === '' || $a['status'] === $status) && ($q === '' || str_contains(Str::norm($a['title'] . ' ' . $a['slug']), $q)),
            static fn ($a, $b) => strcmp((string) $b['published'], (string) $a['published']),
            500
        )['items'];
        return $this->page('articles', self::paginate($items, 40) + ['status' => $status, 'q' => self::q('q'), 'link' => self::pageLink(), 'aiOn' => Settings::aiOn('seo')], 'Blog', 'blog');
    }

    public function article(?int $id = null): Response
    {
        $a = $id ? Store::articles()->get($id) : null;
        if ($id && !$a) {
            throw new HttpException(404);
        }
        if (Request::isPost()) {
            $action = (string) Request::input('action', 'save');
            if ($action === 'delete' && $a) {
                Store::articles()->delete($id);
                Seo::markSitemapDirty();
                Cache::bump();
                $this->audit('Article supprimé', ['article' => $id]);
                return $this->done('Article supprimé.', 'blog');
            }
            if ($action === 'ai-draft') {
                $topic = Sanitizer::line((string) Request::input('topic', ''), 200);
                $d = $topic !== '' ? Ai::articleDraft($topic) : null;
                if (!$d) {
                    return $this->done('Rédaction IA impossible (IA désactivée ou indisponible).', $id ? 'blog/' . $id : 'blog/nouveau', 'error');
                }
                $body = '';
                foreach ((array) ($d['sections'] ?? []) as $s) {
                    $body .= '<h2>' . e((string) ($s['heading'] ?? '')) . '</h2>' . Str::paragraphs((string) ($s['text'] ?? ''));
                }
                $new = Store::articles()->insert([
                    'status' => 'draft', 'title' => Sanitizer::line((string) $d['title'], 160), 'slug' => self::uniqueSlug(Str::slug((string) $d['title'], 80), 0),
                    'excerpt' => Sanitizer::line((string) ($d['excerpt'] ?? ''), 300), 'body' => Sanitizer::html($body, ['headings' => true]),
                    'category' => 'conseils', 'seo' => ['title' => '', 'description' => Sanitizer::line((string) ($d['meta_description'] ?? ''), 170)],
                    'author' => $this->by(), 'views' => 0, 'ai_generated' => true, 'published_at' => date('c'),
                ]);
                $this->audit('Brouillon d\'article rédigé par l\'IA', ['article' => (int) $new['id']]);
                return $this->done('Brouillon rédigé par l\'IA : relisez-le, illustrez-le et vérifiez chaque information avant publication.', 'blog/' . $new['id']);
            }
            $title = Sanitizer::line((string) Request::input('title', ''), 160);
            if (mb_strlen($title) < 3) {
                return $this->done('Le titre est obligatoire.', $id ? 'blog/' . $id : 'blog/nouveau', 'error');
            }
            $slug = self::uniqueSlug(Str::slug((string) Request::input('slug', '') ?: $title, 80), (int) $id);
            $published = (string) Request::input('published_at', '');
            $data = [
                'title' => $title,
                'slug' => $slug,
                'status' => in_array(Request::input('status'), ['draft', 'published'], true) ? (string) Request::input('status') : 'draft',
                'category' => array_key_exists((string) Request::input('category'), Blog::CATEGORIES) ? (string) Request::input('category') : 'conseils',
                'excerpt' => Sanitizer::line((string) Request::input('excerpt', ''), 300),
                'body' => Sanitizer::html((string) Request::raw('body', ''), ['images' => true, 'tables' => true, 'headings' => true, 'link_rel' => 'noopener']),
                'image_alt' => Sanitizer::line((string) Request::input('image_alt', ''), 160) ?: $title,
                'sponsored' => Request::bool('sponsored'),
                'seo' => ['title' => Sanitizer::line((string) Request::input('seo_title', ''), 70), 'description' => Sanitizer::line((string) Request::input('seo_description', ''), 170)],
                'published_at' => $published !== '' && strtotime($published) ? date('c', (int) strtotime($published)) : ($a['published_at'] ?? date('c')),
                'author' => Sanitizer::line((string) Request::input('author', ''), 80) ?: ($a['author'] ?? $this->by()),
            ];
            $text = Str::text($data['body']);
            $data['reading_time'] = max(1, (int) round(str_word_count(Str::ascii($text)) / 220));
            if ($data['excerpt'] === '') {
                $data['excerpt'] = Str::limit($text, 220);
            }
            $file = Request::file('image');
            if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                if (($err = Image::validateUpload($file)) !== null) {
                    return $this->done('Image : ' . $err, $id ? 'blog/' . $id : 'blog/nouveau', 'error');
                }
                $img = Image::store($file['tmp_name'], PUBLIC_PATH . '/media/blog', $slug . '-' . substr(bin2hex(random_bytes(3)), 0, 6), ['sm' => 640, 'lg' => 1400]);
                $data['image'] = '/media/blog/' . $img['id'] . ($img['ext'] === 'webp' ? '' : '-lg.' . $img['ext']);
            }
            if ($a && $a['slug'] !== $slug && $a['status'] === 'published') {
                \App\Services\Redirects::add(Url::blog((string) $a['slug']), Url::blog($slug), 'Article renommé');
            }
            if ($a) {
                Store::articles()->update($id, $data);
            } else {
                $a = Store::articles()->insert($data + ['views' => 0, 'tags' => []]);
                $id = (int) $a['id'];
            }
            Seo::markSitemapDirty();
            Cache::bump();
            $this->audit('Article enregistré', ['article' => $id]);
            return $this->done('Article enregistré.', 'blog/' . $id);
        }
        return $this->page('article', ['a' => $a, 'aiOn' => Settings::aiOn('seo')], $a ? (string) $a['title'] : 'Nouvel article', 'blog');
    }

    private static function uniqueSlug(string $slug, int $id): string
    {
        $slug = $slug !== '' ? $slug : 'article';
        $taken = [];
        foreach (Store::articles()->iterate() as $aid => $row) {
            if ((int) $aid !== $id) {
                $taken[$row['slug']] = true;
            }
        }
        $c = $slug;
        $i = 2;
        while (isset($taken[$c])) {
            $c = $slug . '-' . $i++;
        }
        return $c;
    }

    // ------------------------------------------------------------------ pages

    public function pages(): Response
    {
        $items = Store::pages()->find(null, static fn ($a, $b) => strcmp($a['title'], $b['title']), 200)['items'];
        return $this->page('pages', ['items' => $items], 'Pages', 'pages');
    }

    public function pageEdit(?int $id = null): Response
    {
        $p = $id ? Store::pages()->get($id) : null;
        if ($id && !$p) {
            throw new HttpException(404);
        }
        if (Request::isPost()) {
            if ((string) Request::input('action', '') === 'delete' && $p) {
                if (in_array($p['slug'], Pages::RESERVED, true)) {
                    return $this->done('Cette page est obligatoire (mentions légales, CGU…) : vous pouvez la modifier mais pas la supprimer.', 'pages/' . $id, 'error');
                }
                Store::pages()->delete($id);
                Cache::forget('footer_pages');
                Cache::bump();
                return $this->done('Page supprimée.', 'pages');
            }
            $title = Sanitizer::line((string) Request::input('title', ''), 160);
            $slug = Str::slug((string) Request::input('slug', '') ?: $title, 60);
            if ($title === '' || $slug === '') {
                return $this->done('Titre obligatoire.', $id ? 'pages/' . $id : 'pages/nouvelle', 'error');
            }
            foreach (Store::pages()->iterate() as $pid => $row) {
                if ((int) $pid !== (int) $id && $row['slug'] === $slug) {
                    return $this->done('Une autre page utilise déjà cette adresse.', $id ? 'pages/' . $id : 'pages/nouvelle', 'error');
                }
            }
            if (!$p && (Categories::get($slug) || Categories::occasion($slug) || $slug === \App\Core\Url::ALL)) {
                return $this->done('Cette adresse est réservée à une page de l\'annuaire.', 'pages/nouvelle', 'error');
            }
            $data = [
                'title' => $title,
                'slug' => $p && in_array($p['slug'], Pages::RESERVED, true) ? $p['slug'] : $slug,
                'status' => Request::input('status') === 'draft' ? 'draft' : 'published',
                'body' => Sanitizer::html((string) Request::raw('body', ''), ['images' => true, 'tables' => true, 'headings' => true, 'link_rel' => 'noopener']),
                'seo' => ['title' => Sanitizer::line((string) Request::input('seo_title', ''), 70), 'description' => Sanitizer::line((string) Request::input('seo_description', ''), 170)],
                'in_footer' => Request::bool('in_footer'),
            ];
            if ($p) {
                Store::pages()->update($id, $data);
            } else {
                $p = Store::pages()->insert($data);
                $id = (int) $p['id'];
            }
            Cache::forget('footer_pages');
            Cache::bump();
            Seo::markSitemapDirty();
            $this->audit('Page enregistrée', ['page' => $data['slug']]);
            return $this->done('Page enregistrée.', 'pages/' . $id);
        }
        return $this->page('page-edit', ['p' => $p], $p ? (string) $p['title'] : 'Nouvelle page', 'pages');
    }

    // --------------------------------------------------------- page d'accueil

    public function home(): Response
    {
        if (Request::isPost()) {
            $h = [];
            foreach (['badge', 'title_1', 'title_2', 'title_em', 'title_3', 'subtitle', 'hero_image_1_alt', 'hero_image_2_alt', 'sticker_1', 'sticker_2', 'sticker_3', 'testimonial_text', 'testimonial_author', 'pros_title', 'pros_title_em', 'events_title', 'events_title_em', 'how_title', 'how_title_em', 'hero_request_title', 'hero_request_text', 'request_kicker', 'request_title', 'request_title_em', 'request_text', 'request_note', 'join_kicker', 'join_title', 'join_title_em', 'join_text', 'stat1_value', 'stat1_label', 'stat2_value', 'stat2_label'] as $k) {
                $h[$k] = Sanitizer::line((string) Request::input($k, ''), 400);
            }
            $h['sticker_show'] = Request::bool('sticker_show');
            $h['testimonial_use_reviews'] = Request::bool('testimonial_use_reviews');
            $h['local_links'] = Request::bool('local_links');
            $h['featured_count'] = max(4, min(24, Request::int('featured_count', 8)));
            $h['ticker'] = array_values(array_filter(array_map(static fn ($t) => Sanitizer::line($t, 40), preg_split('/[\n,]+/', (string) Request::input('ticker', '')) ?: [])));
            $popular = [];
            foreach ((array) Request::arr('popular') as $row) {
                $label = Sanitizer::line((string) ($row['label'] ?? ''), 40);
                $url = Sanitizer::line((string) ($row['url'] ?? ''), 200);
                if ($label !== '' && str_starts_with($url, '/')) {
                    $popular[] = ['label' => $label, 'url' => $url];
                }
            }
            $h['popular'] = $popular;
            $steps = [];
            foreach ((array) Request::arr('steps') as $row) {
                if (trim((string) ($row['t'] ?? '')) !== '') {
                    $steps[] = ['t' => Sanitizer::line((string) $row['t'], 60), 'd' => Sanitizer::line((string) ($row['d'] ?? ''), 300)];
                }
            }
            $h['steps'] = array_slice($steps, 0, 3);
            $rsteps = [];
            foreach ((array) Request::arr('request_steps') as $row) {
                if (trim((string) ($row['t'] ?? '')) !== '') {
                    $rsteps[] = ['t' => Sanitizer::line((string) $row['t'], 60), 'd' => Sanitizer::line((string) ($row['d'] ?? ''), 300)];
                }
            }
            $h['request_steps'] = array_slice($rsteps, 0, 3);
            foreach ([1, 2] as $n) {
                $f = Request::file('hero_image_' . $n);
                if ($f && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    if (($err = Image::validateUpload($f)) !== null) {
                        return $this->done('Image ' . $n . ' : ' . $err, 'accueil', 'error');
                    }
                    $img = Image::store($f['tmp_name'], PUBLIC_PATH . '/media/site', 'hero-' . $n . '-' . substr(bin2hex(random_bytes(3)), 0, 6), ['md' => 900, 'lg' => 1400]);
                    $h['hero_image_' . $n] = '/media/site/' . $img['sizes']['lg'];
                }
                if (Request::bool('hero_image_' . $n . '_remove')) {
                    $h['hero_image_' . $n] = '';
                }
            }
            Settings::merge(['home' => $h]);
            Cache::flush('pages');
            Cache::bump();
            $this->audit('Page d\'accueil modifiée');
            return $this->done('Page d\'accueil enregistrée.', 'accueil');
        }
        return $this->page('home', ['h' => Settings::get('home', [])], 'Page d\'accueil', 'home');
    }

    // --------------------------------------------------------------- métiers

    public function categories(): Response
    {
        if (Request::isPost()) {
            $rows = (array) Request::arr('cats');
            $list = [];
            $seen = [];
            foreach ($rows as $i => $c) {
                $slug = Str::slug((string) ($c['slug'] ?? ''), 50);
                $name = Sanitizer::line((string) ($c['name'] ?? ''), 60);
                if ($slug === '' || $name === '' || isset($seen[$slug])) {
                    continue;
                }
                $seen[$slug] = true;
                $list[] = [
                    'slug' => $slug,
                    'name' => $name,
                    'one' => Sanitizer::line((string) ($c['one'] ?? ''), 80) ?: mb_strtolower($name),
                    'many' => Sanitizer::line((string) ($c['many'] ?? ''), 80) ?: mb_strtolower($name),
                    'emoji' => mb_substr(Sanitizer::line((string) ($c['emoji'] ?? ''), 8), 0, 4),
                    'color' => preg_match('/^#[0-9a-f]{6}$/i', (string) ($c['color'] ?? '')) ? strtolower((string) $c['color']) : '#ffd23f',
                    'visible' => !empty($c['visible']),
                    'tagline' => Sanitizer::line((string) ($c['tagline'] ?? ''), 200),
                    'intro' => Sanitizer::text((string) ($c['intro'] ?? ''), 3000),
                    'keywords' => array_values(array_filter(array_map(static fn ($k) => Str::norm(trim($k)), explode(',', (string) ($c['keywords'] ?? ''))))),
                    'order' => (int) ($c['order'] ?? $i),
                ];
            }
            if (!$list) {
                return $this->done('Au moins un métier est nécessaire.', 'categories', 'error');
            }
            usort($list, static fn ($a, $b) => $a['order'] <=> $b['order']);
            Store::doc('categories', Categories::defaults())->save($list);
            Categories::reset();
            \App\Services\Pros::changed();
            Cache::flush();
            $this->audit('Métiers modifiés', ['nombre' => count($list)]);
            return $this->done('Métiers enregistrés.', 'categories');
        }
        $counts = [];
        foreach (Store::pros()->iterate() as $p) {
            foreach ($p['cats'] as $c) {
                $counts[$c] = ($counts[$c] ?? 0) + ($p['status'] === 'active' ? 1 : 0);
            }
        }
        return $this->page('categories', ['cats' => Categories::all(true), 'counts' => $counts], 'Métiers', 'categories');
    }

    public function occasions(): Response
    {
        if (Request::isPost()) {
            $list = [];
            foreach ((array) Request::arr('occ') as $o) {
                $slug = Str::slug((string) ($o['slug'] ?? ''), 60);
                if ($slug === '' || trim((string) ($o['name'] ?? '')) === '') {
                    continue;
                }
                $list[] = [
                    'key' => Str::slug((string) ($o['key'] ?? $slug), 30),
                    'slug' => $slug,
                    'name' => Sanitizer::line((string) $o['name'], 40),
                    'title' => Sanitizer::line((string) ($o['title'] ?? ''), 120),
                    'emoji' => mb_substr(Sanitizer::line((string) ($o['emoji'] ?? ''), 8), 0, 4),
                    'bg' => preg_match('/^#[0-9a-f]{6}$/i', (string) ($o['bg'] ?? '')) ? $o['bg'] : '#fff6e8',
                    'fg' => preg_match('/^#[0-9a-f]{6}$/i', (string) ($o['fg'] ?? '')) ? $o['fg'] : '#1c1233',
                    'tilt' => max(-4, min(4, (float) ($o['tilt'] ?? 0))),
                    'hint' => Sanitizer::line((string) ($o['hint'] ?? ''), 80),
                    'keywords' => array_values(array_filter(array_map(static fn ($k) => Str::norm(trim($k)), explode(',', (string) ($o['keywords'] ?? ''))))),
                    'cats' => array_values(array_filter((array) ($o['cats'] ?? []), static fn ($c) => Categories::get((string) $c) !== null)),
                    'intro' => Sanitizer::text((string) ($o['intro'] ?? ''), 3000),
                ];
            }
            Store::doc('occasions', Categories::occasionDefaults())->save($list);
            Categories::reset();
            Cache::flush();
            Cache::bump();
            $this->audit('Occasions modifiées');
            return $this->done('Occasions enregistrées.', 'occasions');
        }
        return $this->page('occasions', ['items' => array_values(Categories::occasions())], 'Occasions', 'occasions');
    }

    // ----------------------------------------------------------------- médias

    /** Envoi d'image depuis l'éditeur de texte (articles, pages, emails). */
    public function upload(): Response
    {
        $f = Request::file('file');
        if (($err = Image::validateUpload($f)) !== null) {
            return Response::json(['error' => $err], 422);
        }
        $dir = PUBLIC_PATH . '/media/uploads/' . date('Y/m');
        Fs::ensureDir($dir);
        $base = Str::slug(pathinfo((string) ($f['name'] ?? 'image'), PATHINFO_FILENAME), 40) ?: 'image';
        $img = Image::store($f['tmp_name'], $dir, $base . '-' . substr(bin2hex(random_bytes(4)), 0, 8), ['lg' => 1400]);
        $this->audit('Image envoyée', ['fichier' => $img['sizes']['lg']]);
        return Response::json(['ok' => true, 'url' => '/media/uploads/' . date('Y/m') . '/' . $img['sizes']['lg']]);
    }
}
