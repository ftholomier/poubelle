<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Data\Activity;
use App\Front\AlbumBook;
use App\Services\Vignettes;

/**
 * Interactif › Mon album : la collection personnelle de vignettes de l'Album Sochini / Parini.
 * Statistiques (collectionneurs, pochettes, échanges) et réglages (pochettes par jour, rareté, bonus).
 */
final class StickerAlbum extends Base
{
    public static function index(Request $req): Response
    {
        $cat = AlbumBook::catalog();
        return self::html('admin/collections/mon-album', [
            'stats' => Vignettes::stats(), 'set' => Vignettes::settings(), 'name' => AlbumBook::name(),
            'albums' => array_map('count', $cat),
        ], ['title' => 'Mon album (vignettes)', 'crumb' => 'Interactif', 'nav' => 'mon-album']);
    }

    public static function save(Request $req): Response
    {
        if ((int) ($req->post['gold'] ?? 0) + (int) ($req->post['shiny'] ?? 0) > 70) {
            return self::back('/admin/mon-album', null, 'Brillantes + dorées : 70 % au plus, sinon les vignettes normales deviennent rares.');
        }
        $s = Vignettes::saveSettings($req->post);
        Activity::log(self::actor(), 'a réglé « Mon album » (vignettes)', ['title' => AlbumBook::name(), 'path' => AlbumBook::PATH]);
        return self::back('/admin/mon-album', 'Réglages enregistrés' . ($s['enabled'] ? '.' : ' : « Mon album » est désactivé sur le site.'));
    }
}
