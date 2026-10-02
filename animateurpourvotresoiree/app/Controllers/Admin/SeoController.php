<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Cache;
use App\Core\Fs;
use App\Core\Image;
use App\Core\Request;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Core\Url;
use App\Services\Ai;
use App\Services\Categories;
use App\Services\Geo;
use App\Services\Pros;
use App\Services\Redirects;
use App\Services\Seo;
use App\Services\Settings;
use App\Services\Store;

/** Référencement : gabarits de titres, pages locales, redirections et 404, robots.txt. */
final class SeoController extends AdminController
{
    public const TYPES = [
        'home' => ['Accueil', ''],
        'category' => ['Page métier (France)', '/dj/'],
        'category_region' => ['Métier × région', '/dj/bretagne/'],
        'category_dep' => ['Métier × département', '/dj/rhone-69/'],
        'category_city' => ['Métier × ville', '/dj/rhone-69/lyon/'],
        'all' => ['Tous les métiers (France)', '/animateurs/'],
        'all_region' => ['Tous les métiers × région', '/animateurs/bretagne/'],
        'all_dep' => ['Tous les métiers × département', '/animateurs/rhone-69/'],
        'all_city' => ['Tous les métiers × ville', '/animateurs/rhone-69/lyon/'],
        'occasion' => ['Occasion', '/animation-mariage/'],
        'occasion_dep' => ['Occasion × département', '/animation-mariage/rhone-69/'],
        'pro' => ['Fiche d\'un pro', '/dj/rhone-69/lyon/nom-du-pro/'],
        'search' => ['Recherche / carte', '/recherche/'],
        'blog' => ['Blog', '/blog/'],
    ];

    public function index(): Response
    {
        if (Request::isPost()) {
            $seo = [];
            foreach (array_keys(self::TYPES) as $type) {
                $seo[$type] = [
                    'title' => Sanitizer::line((string) (Request::arr($type)['title'] ?? ''), 200),
                    'description' => Sanitizer::line((string) (Request::arr($type)['description'] ?? ''), 400),
                ];
            }
            $seo['suffix'] = Sanitizer::line((string) Request::input('suffix', ''), 60);
            $seo['noindex_empty'] = Request::bool('noindex_empty');
            $f = Request::file('og_image');
            if ($f && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                if (($err = Image::validateUpload($f)) !== null) {
                    return $this->done('Image de partage : ' . $err, 'seo', 'error');
                }
                $img = Image::store($f['tmp_name'], PUBLIC_PATH . '/media/site', 'partage-' . substr(bin2hex(random_bytes(3)), 0, 6), ['lg' => 1200]);
                $seo['og_image'] = '/media/site/' . $img['sizes']['lg'];
            }
            Settings::merge(['seo' => $seo]);
            Cache::flush('pages');
            $this->audit('Gabarits SEO modifiés');
            return $this->done('Réglages SEO enregistrés.', 'seo');
        }
        $examples = [];
        $cat = Categories::get('dj');
        $dep = Geo::dep('69');
        $vars = Seo::catVars($cat) + ['in' => $dep['in'] ?? 'dans le Rhône', 'code' => '69', 'in_city' => 'à Lyon', 'occasion' => 'Animation de mariage', 'name' => 'DJ Paillettes', 'Cat' => 'DJ', 'tagline' => 'DJ mariage et soirées depuis 2008.'];
        foreach (array_keys(self::TYPES) as $t) {
            $examples[$t] = Seo::meta($t, $vars);
        }
        return $this->page('seo', ['seo' => Settings::get('seo', []), 'types' => self::TYPES, 'examples' => $examples], 'Référencement', 'seo');
    }

    public function redirects(): Response
    {
        if (Request::isPost()) {
            $action = (string) Request::input('action', '');
            if ($action === 'add') {
                $from = '/' . ltrim(Sanitizer::line((string) Request::input('from', ''), 300), '/');
                $to = Sanitizer::line((string) Request::input('to', ''), 500);
                if ($from === '/' || $to === '' || (!str_starts_with($to, '/') && !preg_match('#^https?://#', $to))) {
                    return $this->done('Indiquez une ancienne adresse et une destination valides (/chemin/ ou https://…).', 'seo/redirections', 'error');
                }
                if (rtrim($from, '/') === rtrim($to, '/')) {
                    return $this->done('La destination doit être différente de l\'origine.', 'seo/redirections', 'error');
                }
                Redirects::add($from, $to, Sanitizer::line((string) Request::input('note', ''), 120));
                $this->audit('Redirection ajoutée', ['de' => $from, 'vers' => $to]);
                return $this->done('Redirection ajoutée.', 'seo/redirections');
            }
            if ($action === 'remove') {
                Redirects::remove((string) Request::input('from', ''));
                return $this->done('Redirection supprimée.', 'seo/redirections');
            }
            if ($action === 'clear404') {
                Fs::writeJson(STORAGE_PATH . '/data/404.json', []);
                return $this->done('Journal des pages introuvables vidé.', 'seo/redirections');
            }
            if ($action === 'ignore404') {
                $path = (string) Request::input('from', '');
                Fs::withLock(STORAGE_PATH . '/data/404.json.lock', static function () use ($path): void {
                    $d = Fs::readJson(STORAGE_PATH . '/data/404.json', []);
                    unset($d[$path]);
                    Fs::writeJson(STORAGE_PATH . '/data/404.json', $d);
                });
                return $this->done('Entrée retirée.', 'seo/redirections#e404');
            }
        }
        $manual = Redirects::manual();
        $hits = Redirects::hits();
        uksort($manual, 'strcmp');
        return $this->page('redirects', ['manual' => $manual, 'hits' => $hits, 'log' => array_slice(Redirects::notFoundLog(), 0, 300, true)], 'Redirections', 'redirects');
    }

    public function landings(): Response
    {
        if (Request::isPost()) {
            $path = '/' . trim(Sanitizer::line((string) Request::input('path', ''), 200), '/') . '/';
            $action = (string) Request::input('action', 'save');
            if ($path === '//') {
                return $this->done('Adresse de page invalide.', 'seo/pages-locales', 'error');
            }
            if ($action === 'delete') {
                Store::doc('landings')->update(static function (array $d) use ($path): array {
                    unset($d[str_replace(['.', '/'], '_', $path)]);
                    return $d;
                });
                Cache::flush('pages');
                return $this->done('Texte supprimé : la page revient au texte automatique.', 'seo/pages-locales');
            }
            $data = [
                'path' => $path,
                'title' => Sanitizer::line((string) Request::input('title', ''), 80),
                'description' => Sanitizer::line((string) Request::input('description', ''), 170),
                'intro' => Sanitizer::text((string) Request::input('intro', ''), 3000),
                'by' => $this->by(),
            ];
            if ($action === 'ai') {
                $label = Sanitizer::line((string) Request::input('label', ''), 120) ?: $path;
                $count = (int) Request::input('count', 0);
                $intro = Ai::landingIntro($label, $count, []);
                $meta = Ai::metaFor($label, $count . ' professionnels référencés');
                if (!$intro && !$meta) {
                    return $this->done('IA indisponible (désactivée ou quota atteint).', 'seo/pages-locales?path=' . rawurlencode($path), 'error');
                }
                $data['intro'] = $intro ?? $data['intro'];
                $data['title'] = $meta['title'] ?? $data['title'];
                $data['description'] = $meta['description'] ?? $data['description'];
                $data['ai'] = true;
            }
            Seo::saveLanding($path, array_filter($data, static fn ($v) => $v !== '' && $v !== null));
            Cache::flush('pages');
            $this->audit('Texte de page locale enregistré', ['page' => $path]);
            return $this->done($action === 'ai' ? 'Proposition de l\'IA enregistrée : relisez-la.' : 'Texte enregistré.', 'seo/pages-locales?path=' . rawurlencode($path));
        }
        $all = Store::doc('landings')->all();
        // suggestions : combinaisons métier × département les plus fournies sans texte personnalisé
        $combo = [];
        foreach (Pros::publicIndex() as $p) {
            foreach (array_slice($p['cats'], 0, 2) as $c) {
                if ($p['dep'] !== '') {
                    $combo[$c . '|' . $p['dep']] = ($combo[$c . '|' . $p['dep']] ?? 0) + 1;
                }
            }
        }
        arsort($combo);
        $suggest = [];
        foreach ($combo as $k => $n) {
            [$c, $d] = explode('|', $k);
            $path = Url::dep($c, $d);
            if (!isset($all[str_replace(['.', '/'], '_', $path)]) && Categories::get($c) && Geo::dep($d)) {
                $suggest[] = ['path' => $path, 'label' => Categories::name($c) . ' ' . (Geo::dep($d)['in'] ?? '') . ' (' . $d . ')', 'count' => $n];
            }
            if (count($suggest) >= 25) {
                break;
            }
        }
        $current = null;
        $path = (string) Request::query('path', '');
        if ($path !== '') {
            $current = Seo::landingText($path) ?? ['path' => $path];
            $current['path'] ??= $path;
        }
        return $this->page('landings', ['all' => $all, 'suggest' => $suggest, 'current' => $current, 'aiOn' => Settings::aiOn('seo'), 'label' => (string) Request::query('label', ''), 'count' => (int) Request::query('count', 0)], 'Pages locales', 'landings');
    }

    public function robots(): Response
    {
        if (Request::isPost()) {
            Settings::set('seo.robots', Sanitizer::text((string) Request::input('robots', ''), 5000));
            $this->audit('robots.txt modifié');
            return $this->done('robots.txt enregistré.', 'seo/robots');
        }
        return $this->page('robots', ['robots' => (string) Settings::get('seo.robots', ''), 'env' => (string) \App\Core\Env::get('APP_ENV', 'production')], 'robots.txt', 'robots');
    }
}
