<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Url;
use App\Services\Blog;
use App\Services\Seo;
use App\Services\Settings;
use App\Services\Stats;
use App\Services\Store;

final class BlogController extends Controller
{
    public function index(?string $cat = null): Response
    {
        if ($cat !== null && !isset(Blog::CATEGORIES[$cat])) {
            throw new HttpException(404);
        }
        $page = max(1, (int) Request::query('page', 1));
        $per = 12;
        $res = Blog::published($per, ($page - 1) * $per, $cat);
        $pages = max(1, (int) ceil($res['total'] / $per));
        if ($page > $pages) {
            throw new HttpException(404);
        }
        $base = $cat ? '/blog/categorie/' . $cat . '/' : '/blog/';
        $meta = Seo::meta('blog', []);
        if ($cat) {
            $meta['title'] = Blog::CATEGORIES[$cat] . ' : nos conseils et idées';
        }
        Stats::hit('pv');
        return $this->view('front/blog', [
            'items' => $res['items'],
            'page' => $page,
            'pages' => $pages,
            'cat' => $cat,
            'base' => $base,
            'meta' => $meta + [
                'canonical' => Url::abs($base) . ($page > 1 ? '?page=' . $page : ''),
                'jsonld' => [Seo::breadcrumbs(array_filter([['Accueil', '/'], ['Blog', '/blog/'], $cat ? [Blog::CATEGORIES[$cat], $base] : null]))],
                'prev' => $page > 1 ? $base . ($page > 2 ? '?page=' . ($page - 1) : '') : null,
                'next' => $page < $pages ? $base . '?page=' . ($page + 1) : null,
            ],
        ]);
    }

    public function show(string $slug): Response
    {
        $a = Blog::bySlug($slug);
        if (!$a || $a['status'] !== 'published' || ($a['published_at'] ?? '') > date('c')) {
            throw new HttpException(404);
        }
        Store::articles()->update((int) $a['id'], static function (array $x): array {
            $x['views'] = (int) ($x['views'] ?? 0) + 1;
            return $x;
        }, false);
        $related = Blog::published(4, 0, $a['category'] ?? null)['items'];
        $related = array_values(array_filter($related, static fn ($r) => (int) $r['id'] !== (int) $a['id']));
        $crumbs = [['Accueil', '/'], ['Blog', '/blog/'], [$a['title'], Url::blog($a['slug'])]];
        Stats::hit('pv');
        return $this->view('front/article', [
            'a' => $a,
            'related' => array_slice($related, 0, 3),
            'crumbs' => $crumbs,
            'meta' => [
                'title' => $a['seo']['title'] ?: $a['title'],
                'description' => $a['seo']['description'] ?: (string) $a['excerpt'],
                'canonical' => Url::abs(Url::blog($a['slug'])),
                'type' => 'article',
                'og_image' => Blog::imageUrl($a['image'] ?? null, 'lg'),
                'jsonld' => [Seo::article($a), Seo::breadcrumbs($crumbs)],
            ],
        ]);
    }
}
