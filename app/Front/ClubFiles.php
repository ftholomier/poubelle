<?php

declare(strict_types=1);

namespace App\Front;

use App\Core\Club;
use App\Core\Response;

/**
 * Fichiers fixes de la racine du site (application installable, icônes) déclinés pour les clubs
 * autres que le club historique : public/manifest.webmanifest, sw.js, hors-ligne.html, favicon.ico
 * et apple-touch-icon.png sont ceux de Sochaux ; public/.htaccess envoie ces adresses ici pour les
 * autres domaines, et l'on sert la même chose avec le nom, les couleurs, les icônes et le dossier
 * des médias du club.
 */
final class ClubFiles
{
    public static function manifest(): Response
    {
        $m = json_decode((string) file_get_contents(PUBLIC_PATH . '/manifest.webmanifest'), true) ?: [];
        $icons = Club::s('app_icons');
        $m['name'] = club('{site} · le musée {sigle_de}');
        $m['short_name'] = Club::s('site');
        $m['description'] = club('Le musée en ligne {club_de} : matchs, joueurs, records, Rétro-Direct et quiz.');
        $m['background_color'] = $m['theme_color'] = Club::color('navy');
        $fix = fn (string $src): string => str_replace('/assets/img/app', $icons, $src);
        foreach ($m['icons'] ?? [] as $i => $ic) {
            $m['icons'][$i]['src'] = $fix((string) $ic['src']);
        }
        foreach ($m['shortcuts'] ?? [] as $i => $sc) {
            foreach ($sc['icons'] ?? [] as $j => $ic) {
                $m['shortcuts'][$i]['icons'][$j]['src'] = $fix((string) $ic['src']);
            }
        }
        return new Response(json_encode($m, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 200,
            ['Content-Type' => 'application/manifest+json; charset=utf-8', 'Cache-Control' => 'no-cache']);
    }

    public static function serviceWorker(): Response
    {
        $js = strtr((string) file_get_contents(PUBLIC_PATH . '/sw.js'), [
            "'Sochaux Rétro'" => json_encode(Club::s('site'), JSON_UNESCAPED_UNICODE),
            '/^\/media\/\d+\//' => '/^' . str_replace('/', '\/', MEDIA_URL) . '\/\d+\//',
            '/assets/img/app/' => Club::s('app_icons') . '/',
        ]);
        return new Response($js, 200, ['Content-Type' => 'application/javascript; charset=utf-8', 'Cache-Control' => 'no-cache']);
    }

    public static function offline(): Response
    {
        $site = htmlspecialchars(Club::s('site'), ENT_QUOTES, 'UTF-8');
        $html = strtr((string) file_get_contents(PUBLIC_PATH . '/hors-ligne.html'), [
            'Sochaux Rétro' => $site,
            '#0E1F4D' => Club::color('navy'),
            '#F6C400' => Club::color('yellow'),
            '#F3EDDF' => Club::color('cream'),
            'rgba(246,196,0,' => 'rgba(' . str_replace(' ', '', Club::rgb('yellow')) . ',',
        ]);
        return new Response($html, 200, ['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'no-cache']);
    }

    /** favicon.ico et apple-touch-icon*.png demandés d'office par les navigateurs. */
    public static function icon(string $which): Response
    {
        $file = PUBLIC_PATH . ($which === 'favicon' ? Club::s('favicon') : Club::s('app_icons') . '/180.png');
        return is_file($file)
            ? new Response((string) file_get_contents($file), 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=86400'])
            : new Response('', 404);
    }
}
