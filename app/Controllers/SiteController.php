<?php
declare(strict_types=1);

final class SiteController
{
    public static function home(): void
    {
        Analytics::track('page_view', ['page' => '/']);
        echo view('pages/home', [
            'page' => 'home',
            'meta' => [
                'title' => (string) settings('site.meta_title'),
                'description' => (string) settings('site.meta_description'),
            ],
            'bodyClass' => 'page-home',
        ]);
    }

    public static function network(): void
    {
        Analytics::track('page_view', ['page' => '/le-reseau']);
        echo view('pages/network', [
            'page' => 'network',
            'meta' => [
                'title' => 'Le réseau Suisse Immo — agences physiques et agents mandataires',
                'description' => 'Fondé en 2017 à Belfort, Suisse Immo réunit agences physiques et agents mandataires en Bourgogne-Franche-Comté. Découvrez le réseau avant de nous rejoindre.',
            ],
        ]);
    }

    public static function job(): void
    {
        Analytics::track('page_view', ['page' => '/le-metier']);
        echo view('pages/job', [
            'page' => 'job',
            'meta' => [
                'title' => 'Le métier d’agent commercial immobilier — missions et compétences',
                'description' => 'Prospection, estimation, mandat, annonce, visites, négociation, signature : le quotidien d’un agent commercial immobilier indépendant chez Suisse Immo.',
            ],
        ]);
    }

    public static function apply(): void
    {
        Analytics::track('page_view', ['page' => '/candidater']);
        Analytics::track('funnel_start', ['page' => '/candidater']);
        echo view('pages/apply', [
            'page' => 'apply',
            'meta' => [
                'title' => 'Candidater — devenir agent commercial immobilier chez Suisse Immo',
                'description' => 'Candidature en 4 étapes, 2 minutes. Obtenez un rendez-vous stratégique sous 48 h avec l’un de nos collaborateurs.',
            ],
            'bodyClass' => 'page-apply',
        ]);
    }

    public static function thanks(): void
    {
        echo view('pages/thanks', [
            'page' => 'apply',
            'meta' => ['title' => 'Candidature envoyée — Suisse Immo', 'description' => '', 'noindex' => true],
            'bodyClass' => 'page-thanks',
        ]);
    }

    public static function news(): void
    {
        Analytics::track('page_view', ['page' => '/actualites']);
        $posts = self::published();
        echo view('pages/news', [
            'page' => 'news',
            'posts' => $posts,
            'meta' => [
                'title' => 'Actualités du marché immobilier — Suisse Immo',
                'description' => 'Taux, DPE, prix, volumes : l’analyse du marché immobilier par le réseau Suisse Immo.',
            ],
        ]);
    }

    public static function article(array $params): void
    {
        $slug = (string) ($params['slug'] ?? '');
        $post = null;
        foreach (self::published() as $p) {
            if (($p['slug'] ?? '') === $slug) { $post = $p; break; }
        }
        if ($post === null) {
            self::notFound();
            return;
        }
        Analytics::track('page_view', ['page' => '/actualites/' . $slug]);
        echo view('pages/article', [
            'page' => 'news',
            'post' => $post,
            'related' => array_slice(array_values(array_filter(self::published(), static fn ($p) => ($p['slug'] ?? '') !== $slug)), 0, 3),
            'meta' => [
                'title' => (string) $post['title'] . ' — Suisse Immo',
                'description' => (string) ($post['excerpt'] ?? ''),
            ],
        ]);
    }

    public static function contact(): void
    {
        Analytics::track('page_view', ['page' => '/contact']);
        echo view('pages/contact', [
            'page' => 'contact',
            'meta' => [
                'title' => 'Contact — Suisse Immo Recrutement',
                'description' => 'Une question sur le métier, le statut, la rémunération ou votre secteur ? Écrivez-nous, nous répondons sous 48 h.',
            ],
        ]);
    }

    public static function legal(): void
    {
        echo view('pages/legal', [
            'page' => 'legal',
            'meta' => ['title' => 'Mentions légales — Suisse Immo', 'description' => 'Mentions légales du site de recrutement Suisse Immo.'],
        ]);
    }

    public static function privacy(): void
    {
        echo view('pages/privacy', [
            'page' => 'legal',
            'meta' => ['title' => 'Politique de confidentialité — Suisse Immo', 'description' => 'Traitement des données personnelles des candidats et visiteurs.'],
        ]);
    }

    public static function sitemap(): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        $base = rtrim((string) settings('site.url', ''), '/');
        $urls = ['/', '/le-reseau', '/le-metier', '/candidater', '/actualites', '/contact', '/mentions-legales', '/politique-de-confidentialite'];
        foreach (self::published() as $p) {
            $urls[] = '/actualites/' . ($p['slug'] ?? '');
        }
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            echo '  <url><loc>' . e($base . $u) . '</loc></url>' . "\n";
        }
        echo '</urlset>';
    }

    public static function notFound(): void
    {
        http_response_code(404);
        echo view('pages/404', [
            'page' => '404',
            'meta' => ['title' => 'Page introuvable — Suisse Immo', 'description' => '', 'noindex' => true],
        ]);
    }

    /** @return array<int,array> articles publiés, du plus récent au plus ancien */
    public static function published(): array
    {
        $posts = array_values(array_filter(Store::read('posts'), static fn ($p) => ($p['status'] ?? 'published') === 'published'));
        usort($posts, static fn ($a, $b) => strcmp((string) ($b['published_at'] ?? ''), (string) ($a['published_at'] ?? '')));
        return $posts;
    }
}
