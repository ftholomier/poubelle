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
                'title' => 'Le réseau Suisse Immo — agences et mandataires',
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
                'title' => 'Le métier d’agent commercial immobilier — Suisse Immo',
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
                'title' => 'Candidater chez Suisse Immo — agent immobilier',
                'description' => 'Candidature en 4 étapes et 2 minutes : décrivez votre projet et obtenez un rendez-vous stratégique sous 48 h avec l’un de nos collaborateurs.',
            ],
            'bodyClass' => 'page-apply',
        ]);
    }

    public static function thanks(): void
    {
        echo view('pages/thanks', [
            'page' => 'apply',
            'meta' => [
                'title' => 'Candidature envoyée — Suisse Immo',
                'description' => 'Votre candidature d’agent commercial immobilier est enregistrée. Un collaborateur Suisse Immo vous rappelle pour fixer votre rendez-vous.',
                'noindex' => true,
            ],
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
                'description' => 'Taux, DPE, prix, volumes de transactions : l’analyse du marché immobilier en Bourgogne-Franche-Comté par les agences du réseau Suisse Immo.',
            ],
        ]);
    }

    public static function article(array $params): void
    {
        $slug = (string) ($params['slug'] ?? '');
        // Un brouillon reste invisible du public, mais un rédacteur connecté
        // doit pouvoir relire sa mise en page avant de publier.
        $apercu = Auth::user() !== null;
        $post = null;
        foreach ($apercu ? Store::read('posts') : self::published() as $p) {
            if (($p['slug'] ?? '') === $slug) { $post = $p; break; }
        }
        if ($post === null) {
            self::notFound();
            return;
        }
        $brouillon = ($post['status'] ?? 'published') !== 'published';
        if (!$brouillon) {
            Analytics::track('page_view', ['page' => '/actualites/' . $slug]);
        }
        echo view('pages/article', [
            'page' => 'news',
            'post' => $post,
            'related' => array_slice(array_values(array_filter(self::published(), static fn ($p) => ($p['slug'] ?? '') !== $slug)), 0, 3),
            'apercu' => $brouillon,
            'meta' => [
                // Le suffixe de marque n'est conservé que s'il tient dans les
                // 60 caractères affichés par les moteurs de recherche.
                'title' => mb_strlen((string) $post['title']) <= 47
                    ? (string) $post['title'] . ' — Suisse Immo'
                    : meta_trim((string) $post['title'], 60),
                'description' => meta_trim((string) ($post['excerpt'] ?? ''), 158),
                // Un brouillon n'a rien à faire dans un index.
                'noindex' => $brouillon,
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
                'description' => 'Une question sur le métier d’agent commercial immobilier, le statut, la rémunération ou votre secteur ? Écrivez-nous, nous répondons sous 48 h.',
            ],
        ]);
    }

    public static function legal(): void
    {
        echo view('pages/legal', [
            'page' => 'legal',
            'meta' => ['title' => 'Mentions légales — Suisse Immo', 'description' => 'Éditeur, hébergeur, propriété intellectuelle et responsabilité : les mentions légales du site de recrutement du réseau immobilier Suisse Immo.'],
        ]);
    }

    public static function privacy(): void
    {
        echo view('pages/privacy', [
            'page' => 'legal',
            'meta' => ['title' => 'Politique de confidentialité — Suisse Immo', 'description' => 'Données collectées, finalités, durées de conservation et exercice de vos droits pour les candidats et visiteurs du site de recrutement Suisse Immo.'],
        ]);
    }

    /**
     * robots.txt.
     *
     * Servi par le routeur : l'adresse du plan de site suit le réglage
     * site.url au lieu d'être figée dans un fichier, et un site déclaré
     * hors ligne (site.indexable) est intégralement désindexé.
     */
    public static function robots(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: public, max-age=86400');
        $base = rtrim((string) settings('site.url', ''), '/');
        if (!settings('site.indexable', true)) {
            echo "User-agent: *\nDisallow: /\n";
            return;
        }
        echo "User-agent: *\n";
        echo "Allow: /\n";
        echo 'Disallow: ' . url('admin') . "\n";
        echo 'Disallow: ' . url('api/') . "\n";
        echo "\n";
        if ($base !== '') {
            echo 'Sitemap: ' . $base . url('sitemap.xml') . "\n";
        }
    }

    /**
     * Manifeste d'application web.
     *
     * Servi par le routeur plutôt que déposé en fichier statique : le
     * nom, les couleurs et le chemin de base proviennent des réglages,
     * et le type MIME est garanti quel que soit l'hébergeur.
     */
    public static function webmanifest(): void
    {
        header('Content-Type: application/manifest+json; charset=utf-8');
        header('Cache-Control: public, max-age=86400');
        echo json_encode([
            'name' => (string) settings('site.name', 'Suisse Immo Recrutement'),
            'short_name' => 'Suisse Immo',
            'description' => (string) settings('site.meta_description', ''),
            'lang' => 'fr-FR',
            'start_url' => url('/'),
            'scope' => url('/'),
            'display' => 'standalone',
            'background_color' => '#07080c',
            'theme_color' => '#07080c',
            'icons' => [
                ['src' => url('assets/img/favicon-192.png'), 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => url('assets/img/favicon-512.png'), 'sizes' => '512x512', 'type' => 'image/png'],
                ['src' => url('assets/img/apple-touch-icon.png'), 'sizes' => '180x180', 'type' => 'image/png'],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    public static function sitemap(): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        $base = rtrim((string) settings('site.url', ''), '/');
        // Date de dernière modification : les pages éditoriales suivent le
        // fichier de contenu, chaque article sa propre date. Sans elle, les
        // moteurs ne savent pas ce qui a bougé depuis leur dernier passage.
        $contenuModifie = date('Y-m-d', (int) (@filemtime(Store::path('content')) ?: time()));
        $urls = [];
        foreach (['/', '/le-reseau', '/le-metier', '/candidater', '/actualites', '/contact'] as $u) {
            $urls[$u] = $contenuModifie;
        }
        foreach (['/mentions-legales', '/politique-de-confidentialite'] as $u) {
            $urls[$u] = date('Y-m-d', (int) (@filemtime(Store::path('settings')) ?: time()));
        }
        foreach (self::published() as $post) {
            $date = (string) ($post['updated_at'] ?? $post['published_at'] ?? '');
            $urls['/actualites/' . ($post['slug'] ?? '')] = $date !== '' ? date('Y-m-d', (int) strtotime($date)) : $contenuModifie;
        }
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u => $lastmod) {
            echo '  <url><loc>' . e($base . $u) . '</loc><lastmod>' . e($lastmod) . '</lastmod></url>' . "\n";
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
