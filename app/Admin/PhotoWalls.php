<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Data\Activity;
use App\Front\Walls;
use App\Services\PhotoWall;

/**
 * Interactif › Murs de photos : combien de photos vont sur les quatre murs et pourquoi les
 * autres en sont écartées (lien vers la médiathèque pour chaque raison), liste des crédits
 * exclus (agences, presse nationale…) modifiable, crédits montrés, vignettes préparées d'avance.
 */
final class PhotoWalls extends Base
{
    public static function index(Request $req): Response
    {
        $photos = PhotoWall::photos();
        $credits = [];
        foreach ($photos as $p) {
            $credits[$p['c']] ??= ['name' => $p['c'], 'who' => $p['who'], 'n' => 0];
            $credits[$p['c']]['n']++;
        }
        usort($credits, fn ($a, $b) => $b['n'] <=> $a['n'] ?: strcoll($a['name'], $b['name']));
        $ready = [];
        foreach (PhotoWall::WIDTHS as $i => $w) {
            $ready[$w] = count(array_filter($photos, fn ($p) => ($p['t'] >> $i) & 1));
        }
        return self::html('admin/collections/murs', [
            'stats' => PhotoWall::stats(),
            'walls' => Walls::WALLS,
            'excluded' => PhotoWall::excluded(),
            'isDefault' => PhotoWall::excluded() === PhotoWall::RISKY,
            'hits' => PhotoWall::excludedHits(),
            'credits' => $credits,
            'photographers' => PhotoWall::photographers(),
            'decades' => PhotoWall::decades(),
            'ready' => $ready,
            'total' => count($photos),
        ], ['title' => 'Murs de photos', 'crumb' => 'Interactif', 'nav' => 'murs']);
    }

    /** POST /admin/murs-photos : liste des crédits exclus, liste de départ, ou vignettes à préparer. */
    public static function save(Request $req): Response
    {
        $before = count(PhotoWall::photos());
        switch ($req->str('action')) {
            case 'exclus':
                PhotoWall::saveExcluded(preg_split('/\R/u', $req->str('exclus')) ?: []);
                break;
            case 'defaut':
                PhotoWall::saveExcluded(PhotoWall::RISKY);
                break;
            case 'vignettes':
                @set_time_limit(60);
                $r = PhotoWall::prepare(20.0);
                PhotoWall::forget();
                return self::back('/admin/murs-photos#vignettes', $r['done'] . ' vignette(s) préparée(s)' . ($r['left'] ? ' ; ' . $r['left'] . ' restent, préparées par la tâche planifiée (ou cliquez à nouveau).' : ' : tout est prêt.'));
            default:
                return self::back('/admin/murs-photos', null, 'Action inconnue.');
        }
        $after = count(PhotoWall::photos());
        Activity::log(self::actor(), 'a modifié les crédits exclus des murs de photos', ['title' => $after . ' photos montrées', 'path' => '/interactif/planche-contact/']);
        $diff = $after - $before;
        return self::back('/admin/murs-photos#exclus', 'Liste enregistrée : ' . number_format($after, 0, ',', ' ') . ' photos sur les murs' . ($diff ? ' (' . ($diff > 0 ? '+' : '') . $diff . ').' : '.'));
    }
}
