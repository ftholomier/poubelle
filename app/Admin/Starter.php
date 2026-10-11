<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Data\Activity;
use App\Services\StarterPack;

/**
 * Système › Contenus de départ : création des fiches préparées pour un nouveau musée
 * (StarterPack), au statut « À relire ». Réservé aux administrateurs.
 */
final class Starter extends Base
{
    public static function index(Request $req): Response
    {
        if ($r = self::denyUnlessAdmin()) {
            return $r;
        }
        return self::html('admin/system/depart', ['summary' => StarterPack::summary(), 'state' => StarterPack::state()],
            ['title' => 'Contenus de départ', 'crumb' => 'Système', 'nav' => 'depart', 'scripts' => ['admin/depart.js']]);
    }

    /** POST : lancer, pause, lot (JSON, appelé par la page). */
    public static function action(Request $req): Response
    {
        if ($r = self::denyUnlessAdmin()) {
            return $r;
        }
        $back = '/admin/contenus-de-depart';
        @set_time_limit(300);
        @ini_set('memory_limit', '512M');
        try {
            switch ($req->str('action')) {
                case 'lancer':
                    StarterPack::start(true);
                    Activity::log(self::actor(), 'a lancé la création des contenus de départ', null);
                    return self::back($back, 'Création lancée : elle avance tant que cette page est ouverte.');
                case 'pause':
                    StarterPack::start(false);
                    return self::back($back, 'Création mise en pause.');
                case 'lot':
                    if (!StarterPack::state()['running']) {
                        return self::json(['running' => false]);
                    }
                    $r = StarterPack::run(120);
                    if ($r['left'] === 0) {
                        StarterPack::start(false);
                    }
                    return self::json($r + ['running' => $r['left'] !== 0, 'busy' => $r['left'] === -1]);
            }
        } catch (\Throwable $e) {
            return $req->str('action') === 'lot' ? self::json(['error' => $e->getMessage()], 500) : self::back($back, null, $e->getMessage());
        }
        return self::back($back);
    }
}
