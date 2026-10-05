<?php
declare(strict_types=1);

namespace App\Vitrine;

use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Core\View;

/** Pages du site de l'association (hors formulaires : voir Forms). */
final class Pages
{
    // ------------------------------------------------------------------ accueil

    public static function home(Request $req): Response
    {
        $p = Content::page('accueil');
        $teaser = is_file(APP_DIR . '/Resources/video/teaser.mp4');
        return Site::render('home', [
            'p' => $p,
            'figures' => Content::figures(),
            'actions' => Content::actions(),
            'news' => array_slice(Content::news(), 0, 3),
            'events' => array_slice(Content::events(), 0, 3),
            'partners' => Content::partners(),
            'teaser' => $teaser,
            'days' => \App\Front\Site::daysToCentenary(),
            'centenary' => (string) Settings::get('home.centenary_date', '2028-05-20'),
        ], [
            'full_title' => (string) Settings::get('vitrine.seo_title', '') ?: Site::name(),
            'description' => (string) Settings::get('vitrine.seo_description', ''),
            'active' => 'accueil',
            'body_class' => 'vt-home',
            'jsonld' => Site::organizationLd(),
        ]);
    }

    // ------------------------------------------------------------------ l'association

    public static function association(Request $req): Response
    {
        $p = Content::page('association');
        return Site::render('association', ['p' => $p, 'figures' => Content::figures()], [
            'title' => $p['title'], 'description' => $p['lead'], 'image' => self::share($p['image'] ?? null), 'active' => 'association',
            'jsonld' => Site::organizationLd(),
        ]);
    }

    public static function team(Request $req): Response
    {
        $p = Content::page('equipe');
        return Site::render('equipe', ['p' => $p, 'team' => Content::team(), 'poles' => Content::poles()], [
            'title' => $p['title'], 'description' => $p['lead'], 'active' => 'association',
            'styles' => ['css/team.css'], 'scripts' => ['js/team.js'],
        ]);
    }

    public static function documents(Request $req): Response
    {
        $p = Content::page('documents');
        return Site::render('documents', ['p' => $p, 'docs' => Content::documents()], [
            'title' => $p['title'], 'description' => $p['lead'], 'active' => 'association',
        ]);
    }

    // ------------------------------------------------------------------ actions

    public static function actions(Request $req): Response
    {
        $p = Content::page('actions');
        return Site::render('actions', ['p' => $p, 'actions' => Content::actions()], [
            'title' => $p['title'], 'description' => $p['lead'], 'active' => 'actions',
        ]);
    }

    public static function action(Request $req, string $slug): ?Response
    {
        $a = Content::action($slug);
        if (!$a) {
            return null;
        }
        return Site::render('action', [
            'a' => $a,
            'others' => array_values(array_filter(Content::actions(), fn ($x) => $x['slug'] !== $slug)),
            'figures' => !empty($a['stats']) ? Content::figures() : [],
            'videos' => !empty($a['videos']) ? Videos::latest(6) : [],
            'channel' => Videos::channelUrl(),
            'centenary' => (string) Settings::get('home.centenary_date', '2028-05-20'),
        ], [
            'title' => $a['title'], 'description' => $a['excerpt'] ?? $a['lead'] ?? '', 'image' => self::share($a['image'] ?? null), 'active' => 'actions',
        ]);
    }

    // ------------------------------------------------------------------ actualités

    private const PER_PAGE = 9;

    public static function news(Request $req): Response
    {
        $p = Content::page('actualites');
        $all = Content::news();
        $pages = max(1, (int) ceil(count($all) / self::PER_PAGE));
        $n = max(1, min($pages, (int) $req->str('page') ?: 1));
        return Site::render('actualites', ['p' => $p, 'items' => array_slice($all, ($n - 1) * self::PER_PAGE, self::PER_PAGE), 'n' => $n, 'pages' => $pages], [
            'title' => $p['title'] . ($n > 1 ? " (page $n)" : ''), 'description' => $p['lead'], 'active' => 'actualites',
            'canonical' => '/actualites/' . ($n > 1 ? '?page=' . $n : ''),
        ]);
    }

    public static function newsItem(Request $req, string $slug): ?Response
    {
        $n = Content::newsItem($slug);
        if (!$n) {
            return null;
        }
        $others = array_values(array_filter(Content::news(), fn ($x) => $x['slug'] !== $slug));
        return Site::render('actualite', ['n' => $n, 'others' => array_slice($others, 0, 3)], [
            'title' => $n['title'], 'description' => $n['excerpt'] ?? '', 'image' => self::share($n['image'] ?? null), 'active' => 'actualites', 'type' => 'article',
            'jsonld' => ['@context' => 'https://schema.org', '@type' => 'NewsArticle', 'headline' => $n['title'], 'datePublished' => $n['date'] ?? null,
                'image' => !empty($n['image']) ? [Host::abs(img((string) $n['image'], 1200))] : null, 'publisher' => ['@type' => 'Organization', 'name' => Site::name()]],
        ]);
    }

    // ------------------------------------------------------------------ agenda

    public static function agenda(Request $req): Response
    {
        $p = Content::page('agenda');
        return Site::render('agenda', ['p' => $p, 'next' => Content::events(), 'past' => array_slice(Content::events(true), 0, 6)], [
            'title' => $p['title'], 'description' => $p['lead'], 'active' => 'agenda',
        ]);
    }

    public static function event(Request $req, string $slug): ?Response
    {
        $e = Content::event($slug);
        if (!$e) {
            return null;
        }
        return Site::render('evenement', ['e' => $e, 'next' => array_slice(array_values(array_filter(Content::events(), fn ($x) => $x['slug'] !== $slug)), 0, 3)], [
            'title' => $e['title'], 'description' => $e['excerpt'] ?? '', 'image' => self::share($e['image'] ?? null), 'active' => 'agenda',
            'jsonld' => array_filter(['@context' => 'https://schema.org', '@type' => 'Event', 'name' => $e['title'], 'startDate' => str_replace(' ', 'T', $e['start']),
                'endDate' => $e['end'] !== '' ? str_replace(' ', 'T', $e['end']) : null, 'location' => ['@type' => 'Place', 'name' => $e['place'] ?? '', 'address' => $e['address'] ?? ($e['place'] ?? '')],
                'organizer' => ['@type' => 'Organization', 'name' => Site::name(), 'url' => Host::abs('/')], 'description' => $e['excerpt'] ?? '']),
        ]);
    }

    /** Agenda au format iCalendar (abonnement depuis un agenda personnel). */
    public static function ics(Request $req): Response
    {
        $esc = fn (string $s) => str_replace(["\\", "\n", ',', ';'], ['\\\\', '\\n', '\\,', '\\;'], $s);
        $fold = fn (string $line) => rtrim(chunk_split($line, 73, "\r\n "), "\r\n ");
        $out = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Sochaux Retro//Agenda//FR', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH', $fold('X-WR-CALNAME:' . $esc(Site::name() . ' · Agenda')), 'X-WR-TIMEZONE:Europe/Paris'];
        foreach (Content::events() as $e) {
            $start = strtotime($e['start']);
            $end = $e['end'] !== '' ? strtotime($e['end']) : $start + 2 * 3600;
            $url = $e['kind'] === 'asso' ? Host::abs('/agenda/' . $e['slug'] . '/') : (preg_match('#^https?://#', (string) ($e['href'] ?? '')) ? $e['href'] : Host::abs((string) ($e['href'] ?? '/agenda/')));
            $out[] = 'BEGIN:VEVENT';
            $out[] = 'UID:' . $e['slug'] . '@' . Host::host();
            $out[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');
            if (!empty($e['allday'])) {
                $out[] = 'DTSTART;VALUE=DATE:' . date('Ymd', $start);
            } else {
                $out[] = 'DTSTART:' . gmdate('Ymd\THis\Z', $start);
                $out[] = 'DTEND:' . gmdate('Ymd\THis\Z', $end);
            }
            $out[] = $fold('SUMMARY:' . $esc((string) $e['title']));
            if (!empty($e['place'])) {
                $out[] = $fold('LOCATION:' . $esc((string) $e['place']));
            }
            $out[] = $fold('DESCRIPTION:' . $esc(trim((string) ($e['excerpt'] ?? '')) . "\n" . $url));
            $out[] = $fold('URL:' . $url);
            $out[] = 'END:VEVENT';
        }
        $out[] = 'END:VCALENDAR';
        return new Response(implode("\r\n", $out) . "\r\n", 200, ['Content-Type' => 'text/calendar; charset=UTF-8', 'Content-Disposition' => 'inline; filename="agenda-sochaux-retro.ics"', 'Cache-Control' => 'public, max-age=3600']);
    }

    // ------------------------------------------------------------------ nous soutenir, partenaires, presse

    public static function support(Request $req): Response
    {
        $p = Content::page('soutenir');
        return Site::render('soutenir', ['p' => $p, 'donations' => (bool) Settings::get('donations.enabled', false)], [
            'title' => $p['title'], 'description' => $p['lead'], 'image' => self::share($p['image'] ?? null), 'active' => 'soutenir',
        ]);
    }

    public static function partners(Request $req): Response
    {
        $p = Content::page('partenaires');
        return Site::render('partenaires', ['p' => $p, 'partners' => Content::partners()], [
            'title' => $p['title'], 'description' => $p['lead'], 'active' => 'association',
        ]);
    }

    public static function press(Request $req): Response
    {
        $p = Content::page('presse');
        return Site::render('presse', ['p' => $p, 'articles' => Content::press(), 'kit' => is_file(self::PRESS_KIT), 'kitSize' => is_file(self::PRESS_KIT) ? (int) filesize(self::PRESS_KIT) : 0, 'figures' => Content::figures()], [
            'title' => $p['title'], 'description' => $p['lead'], 'active' => 'association',
        ]);
    }

    /** Présentation du projet (PDF livré avec le site), pour la presse. */
    private const PRESS_KIT = APP_DIR . '/Resources/vitrine/fichiers/presentation-sochaux-retro.pdf';

    public static function pressKit(Request $req): ?Response
    {
        if (!is_file(self::PRESS_KIT)) {
            return null;
        }
        $res = new Response('', 200, ['Content-Type' => 'application/pdf', 'Content-Length' => (string) filesize(self::PRESS_KIT), 'Content-Disposition' => 'inline; filename="presentation-sochaux-retro.pdf"', 'Cache-Control' => 'public, max-age=86400', 'X-Content-Type-Options' => 'nosniff']);
        $res->file = self::PRESS_KIT;
        return $res;
    }

    // ------------------------------------------------------------------ pages légales, plan du site

    public static function legal(Request $req, string $key): Response
    {
        $g = fn (string $k, string $d = '') => trim((string) Settings::get('legal.' . $k, $d));
        $email = $g('email') ?: Site::email();
        $titles = ['mentions' => 'Mentions légales', 'confidentialite' => 'Politique de confidentialité', 'cookies' => 'Cookies et traceurs'];
        return Site::render('legal/' . $key, [
            'title' => $titles[$key],
            'publisher' => $g('publisher', 'Sochaux Rétro'),
            'status' => $g('status'),
            'address' => safe_html($g('address')),
            'registration' => $g('registration'),
            'director' => $g('director'),
            'email' => $email,
            'phone' => $g('phone'),
            'host' => safe_html($g('host')),
            'privacy' => $g('privacy_contact') ?: $email,
            'extra' => safe_html($g('extra')),
            'online' => Forms::onlineMembership(),
            'updated' => '2026-10-04',
        ], ['title' => $titles[$key], 'description' => $titles[$key] . ' du site de l’association ' . Site::name() . '.', 'body_class' => 'vt-legal', 'styles' => ['css/legal.css']]);
    }

    public static function siteMap(Request $req): Response
    {
        return Site::render('plan', ['actions' => Content::actions(), 'news' => Content::news(), 'events' => Content::events()], [
            'title' => 'Plan du site', 'description' => 'Toutes les pages du site de l’association ' . Site::name() . '.',
        ]);
    }

    // ------------------------------------------------------------------ attente, teaser, erreurs

    /** Page d'attente (site fermé au public). */
    /**
     * Page d'attente (site fermé ; aperçu depuis le back-office). Sa propre page, distincte de
     * celle du musée : réglée dans Site de l'association › Page d'attente.
     */
    public static function waiting(): Response
    {
        $w = Content::waiting();
        $html = View::render('vitrine/waiting', [
            'w' => $w,
            'museumOpen' => !\App\Front\Seo::closed(),
            'teaser' => !empty($w['museum']) && !empty($w['teaser']) && is_file(APP_DIR . '/Resources/video/teaser.mp4'),
            'email' => !empty($w['contact']) ? Site::email() : '',
            'social' => !empty($w['social']) ? Site::social() : [],
            'preview' => Site::$preview,
        ]);
        $headers = ['Content-Type' => 'text/html; charset=UTF-8', 'Cache-Control' => 'no-store', 'X-Robots-Tag' => 'noindex, nofollow'];
        return Site::$preview ? new Response($html, 200, $headers) : new Response($html, 503, $headers + ['Retry-After' => '3600']);
    }

    /** Teaser du musée (accueil du site de l'association). */
    public static function teaser(Request $req, string $ext): ?Response
    {
        $file = APP_DIR . '/Resources/video/teaser.' . ($ext === 'jpg' ? 'jpg' : 'mp4');
        if (!is_file($file)) {
            return null;
        }
        $res = Response::media($file, $ext === 'jpg' ? 'image/jpeg' : 'video/mp4', $req->server['HTTP_RANGE'] ?? null);
        $res->headers['Cache-Control'] = Site::$preview ? 'private, no-store' : 'public, max-age=86400';
        $res->headers['X-Content-Type-Options'] = 'nosniff';
        return $res;
    }

    public static function notFound(): Response
    {
        return Site::render('404', [], ['title' => 'Page introuvable', 'noindex' => true], 404);
    }

    // ------------------------------------------------------------------ aides

    /**
     * Lien saisi dans les contenus : « musee:/chemin/ » (musée en ligne), « social:youtube »
     * (réseau social réglé), « /chemin/ » (page du site), « https://… » (autre site).
     * @return array{0:string,1:bool} [adresse, autre site ?]
     */
    public static function link(?string $u): array
    {
        $u = trim((string) $u);
        if ($u === '') {
            return ['', false];
        }
        if (str_starts_with($u, 'musee:')) {
            return [Host::museum('/' . ltrim(substr($u, 6), '/')), true];
        }
        if (str_starts_with($u, 'social:')) {
            $s = Site::social()[substr($u, 7)][1] ?? '';
            return [$s, $s !== ''];
        }
        if (preg_match('#^https?://#i', $u)) {
            return [$u, true];
        }
        if (str_starts_with($u, '/') && !str_starts_with($u, '//')) {
            return [Host::url($u), false];
        }
        return ['', false];
    }

    /** Image de partage d'une page (médiathèque), sinon celle du site. */
    private static function share(?string $rel): string
    {
        return Content::hasImage($rel) ? img((string) $rel, 1200) : '';
    }

    /** Texte riche saisi dans le back-office, prêt à afficher (liens internes gardés dans l'aperçu). */
    public static function rich(?string $html): string
    {
        $h = safe_html((string) $html);
        if (Host::$prefix !== '') {
            $h = (string) preg_replace('#href="/(?!/|media/|assets/|apercu-association/)#', 'href="' . Host::$prefix . '/', $h);
        }
        return $h;
    }
}
