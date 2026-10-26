<?php
declare(strict_types=1);

namespace LD\Controllers;

use LD\Auth;
use LD\Booking;
use LD\Content;
use LD\Http;
use LD\Mailer;
use LD\PageCache;
use LD\Posts;
use LD\Seo;
use LD\Settings;
use LD\View;

/**
 * Pages publiques du site.
 */
final class Front
{
    /** Clé de cache : chemin + requête. */
    public static function cacheKey(): string
    {
        return Http::path() . '?' . ($_SERVER['QUERY_STRING'] ?? '');
    }

    public static function output(string $html, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-cache');
        echo PageCache::hydrate($html);
        exit;
    }

    private static function render(string $template, string $contentKey, array $meta, array $vars = [], bool $cacheable = true, int $status = 200): never
    {
        $c = $contentKey !== '' ? Content::get($contentKey) : [];
        $meta += ['title' => $c['seo']['title'] ?? '', 'description' => $c['seo']['description'] ?? ''];
        $meta['jsonld'] = array_merge([Seo::business()], $meta['jsonld'] ?? []);
        $html = View::page($template, ['c' => $c, 'meta' => $meta] + $vars);
        if ($cacheable && $status === 200 && PageCache::enabled() && !Auth::peek()) {
            PageCache::put(self::cacheKey(), $html);
        }
        self::output($html, $status);
    }

    public static function home(): never
    {
        self::render('home', 'home', ['active' => '', 'bodyClass' => 'is-home']);
    }

    public static function surMesure(): never
    {
        self::render('sur-mesure', 'sur-mesure', ['active' => 'sur-mesure']);
    }

    public static function formation(): never
    {
        self::render('formation', 'formation', ['active' => 'formation']);
    }

    public static function accompagnement(): never
    {
        self::render('accompagnement', 'accompagnement', ['active' => 'accompagnement']);
    }

    public static function solutions(): never
    {
        self::render('solutions', 'solutions', ['active' => 'solutions']);
    }

    public static function qui(): never
    {
        self::render('qui', 'qui', ['active' => 'qui']);
    }

    public static function avis(): never
    {
        self::render('avis', 'avis', ['active' => '']);
    }

    public static function blog(): never
    {
        $theme = isset($_GET['theme']) ? clean_text((string) $_GET['theme'], 60) : '';
        self::render('blog', 'blog', ['active' => ''], ['theme' => $theme]);
    }

    public static function article(string $slug): never
    {
        $post = Posts::bySlug($slug);
        $preview = $post && empty($post['published']) && Auth::peek();
        if (!$post || (empty($post['published']) && !$preview) || (!$preview && substr((string) $post['date'], 0, 10) > date('Y-m-d'))) {
            self::notFound();
        }
        $meta = [
            'title'       => $post['seo_title'] ?: $post['title'],
            'description' => $post['seo_description'] ?: ($post['excerpt'] ?? ''),
            'image'       => $post['image'] ?: null,
            'type'        => 'article',
            'active'      => '',
            'noindex'     => $preview,
            'jsonld'      => [Seo::article($post)],
        ];
        self::render('article', 'blog', $meta, ['post' => $post], !$preview);
    }

    public static function rdv(): never
    {
        // Pas de cache : les créneaux disponibles changent en continu.
        self::render('rdv', 'rdv', ['active' => '', 'bodyClass' => 'is-rdv'], [], false);
    }

    public static function zone(string $slug): never
    {
        $z = Content::get('zones');
        $city = null;
        foreach ($z['cities'] as $ct) {
            if ($ct['slug'] === $slug) {
                $city = $ct;
            }
        }
        if (!$city) {
            self::notFound();
        }
        $fill = static fn(string $s) => strtr($s, ['{prep}' => $city['prep'], '{of}' => $city['of'], '{city}' => $city['name']]);
        self::render('zone', 'zones', ['active' => '', 'title' => $fill($z['seo']['title']), 'description' => $fill($z['seo']['description'])], ['city' => $city]);
    }

    public static function mentions(): never
    {
        self::render('mentions', 'mentions', ['active' => '']);
    }

    public static function notFound(): never
    {
        self::render('simple', '', ['title' => 'Page introuvable', 'noindex' => true], [
            'tag'    => 'Erreur 404',
            'title'  => 'Oups, cette page a pris un café sans moi ☕',
            'text'   => 'La page que tu cherches n’existe pas (ou plus). Pas de panique : on repart de l’accueil, ou on prend directement RdV.',
            'action' => ['href' => '/', 'label' => 'Retour à l’accueil'],
        ], false, 404);
    }

    /** Annulation d'un rendez-vous depuis le lien reçu par e-mail. */
    public static function cancelBooking(string $id, string $token): never
    {
        $b = Booking::find($id);
        if (!$b || !hash_equals($b['token'], $token)) {
            self::notFound();
        }
        $done = false;
        if ($b['status'] === 'confirmed' && Http::method() === 'POST') {
            $b = Booking::setStatus($id, 'cancelled') ?? $b;
            $done = true;
            $to = Mailer::notifyAddress();
            if ($to !== '') {
                Mailer::send($to, 'Rendez-vous annulé · ' . $b['name'], Mailer::layout('Rendez-vous annulé', '<p>' . e($b['name']) . ' a annulé son rendez-vous du ' . e(Booking::label($b)) . '.</p>'), $b['email'] ?: null, [
                    ['name' => 'rdv-annule.ics', 'type' => 'text/calendar; method=CANCEL', 'content' => Booking::ics([$b], true)],
                ]);
            }
        }
        if ($b['status'] !== 'confirmed') {
            self::render('simple', '', ['title' => 'Rendez-vous annulé', 'noindex' => true], [
                'tag' => 'C’est noté', 'title' => $done ? 'Ton rendez-vous est annulé.' : 'Ce rendez-vous est déjà annulé.',
                'text' => 'Pas de souci. Quand tu veux, on recale un café.', 'action' => ['href' => '/on-prend-rdv', 'label' => 'Choisir un autre créneau'],
            ], false);
        }
        $form = '<form method="post" style="display:flex;flex-wrap:wrap;gap:12px;justify-content:center"><button class="btn btn-dark btn-xl" type="submit">Oui, j’annule</button><a class="btn btn-outline btn-xl2" href="/">Non, je garde mon créneau</a></form>';
        self::render('simple', '', ['title' => 'Annuler mon rendez-vous', 'noindex' => true], [
            'tag' => 'Ton rendez-vous', 'title' => 'Tu veux annuler ton café ?', 'text' => Booking::label($b) . '.', 'extra' => $form,
        ], false);
    }

    /** Flux iCalendar privé des rendez-vous (à ajouter dans Google Agenda, Apple Calendrier…). */
    public static function icsFeed(): never
    {
        if (!hash_equals(Booking::icsKey(), (string) ($_GET['k'] ?? ''))) {
            http_response_code(403);
            exit('Accès refusé');
        }
        $from = date('Y-m-d', strtotime('-60 days'));
        $list = array_values(array_filter(Booking::all(), static fn($b) => $b['date'] >= $from));
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: inline; filename="rdv-le-digital.ics"');
        echo Booking::ics($list, true);
        exit;
    }

    public static function sitemap(): never
    {
        header('Content-Type: application/xml; charset=utf-8');
        echo Seo::sitemap();
        exit;
    }

    public static function robots(): never
    {
        header('Content-Type: text/plain; charset=utf-8');
        echo Seo::robots();
        exit;
    }

    /** Lien « tâche planifiée » pour les hébergements sans cron en ligne de commande. */
    public static function cron(string $key): never
    {
        if (!hash_equals(substr(hash_hmac('sha256', 'cron', Settings::appSecret()), 0, 24), $key)) {
            http_response_code(403);
            exit('Accès refusé');
        }
        header('Content-Type: text/plain; charset=utf-8');
        echo \LD\Cron::run();
        exit;
    }
}
