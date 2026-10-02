<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Controller;
use App\Core\Response;
use App\Services\Categories;
use App\Services\Pros;
use App\Services\Seo;
use App\Services\Settings;
use App\Services\Stats;
use App\Services\Store;

final class HomeController extends Controller
{
    public function index(): Response
    {
        $numbers = Stats::publicNumbers();
        $cats = Categories::all();
        // Les 7 métiers les plus représentés pour les filtres (comme sur la maquette).
        $byCat = $numbers['by_cat'];
        $chipCats = $cats;
        uksort($chipCats, static fn ($a, $b) => ($byCat[$b] ?? 0) <=> ($byCat[$a] ?? 0));
        $chipCats = array_slice($chipCats, 0, 7, true);
        $featured = self::featured(null, (int) Settings::get('home.featured_count', 9));
        $meta = Seo::meta('home', ['nb' => $numbers['pros']]);
        \App\Services\Stats::hit('pv');
        return $this->view('front/home', [
            'numbers' => $numbers,
            'cats' => $cats,
            'chipCats' => $chipCats,
            'featured' => $featured,
            'testimonial' => self::testimonial(),
            'meta' => $meta + [
                'canonical' => \App\Core\Url::abs('/'),
                'jsonld' => [Seo::itemList($featured)],
                'local_links' => (bool) Settings::get('home.local_links', true),
                'body_class' => 'page-home',
            ],
        ]);
    }

    /** Sélection des pros mis en avant (rotation quotidienne, fiches illustrées en priorité). */
    public static function featured(?string $cat, int $n): array
    {
        $seed = (int) date('Ymd');
        $rows = [];
        foreach (Pros::publicIndex() as $id => $p) {
            if ($cat !== null && $cat !== '' && !in_array($cat, $p['cats'], true)) {
                continue;
            }
            $score = ($p['photo'] ? 50 : 0) + $p['score'] / 4 + $p['rating'] * 3 + ($p['hot'] ? 8 : 0) + (!empty($p['featured']) ? 120 : 0) + (crc32($seed . '-' . $id) % 1000) / 40;
            $rows[$id] = $score;
        }
        arsort($rows);
        $all = Pros::publicIndex();
        $out = [];
        foreach (array_slice(array_keys($rows), 0, $n) as $id) {
            $out[] = $all[$id];
        }
        return $out;
    }

    /** Témoignage : un vrai avis 5 étoiles récent, sinon le texte du back-office. */
    public static function testimonial(): array
    {
        if (Settings::get('home.testimonial_use_reviews', true)) {
            $found = Store::reviews()->find(static fn ($r) => $r['status'] === 'approved' && $r['rating'] === 5 && mb_strlen($r['excerpt']) <= 110 && mb_strlen($r['excerpt']) >= 25, null, 1);
            if ($found['items']) {
                $r = Store::reviews()->get((int) $found['items'][0]['id']);
                $pro = $r ? Store::pros()->get((int) $r['pro_id']) : null;
                if ($r && $pro) {
                    return ['stars' => true, 'text' => '« ' . $r['body'] . ' »', 'author' => \App\Services\Reviews::author($r) . ($pro['city'] ?? '' ? ', ' . $pro['city'] : '')];
                }
            }
        }
        return ['stars' => false, 'text' => '« ' . Settings::get('home.testimonial_text') . ' »', 'author' => (string) Settings::get('home.testimonial_author')];
    }
}
