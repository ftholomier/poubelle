<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Data\Activity;
use App\Data\Collections;
use App\Data\Index;
use App\Services\Souvenirs;

/**
 * Interactif › Kit souvenirs (Raconte-moi Bonal) : le match de chaque mois (choisi
 * automatiquement ou par les historiens), un mot d'introduction, le PDF ; les témoignages
 * publiés dans « Ils y étaient ».
 */
final class Kit extends Base
{
    public static function index(Request $req): Response
    {
        $months = [];
        $choices = (array) Collections::get('souvenirs', []);
        for ($i = 0; $i < 3; $i++) {
            $ym = date('Y-m', strtotime("first day of +$i month"));
            $months[] = ['ym' => $ym, 'label' => Souvenirs::monthLabel($ym), 'match' => Souvenirs::match($ym), 'candidates' => Souvenirs::candidates($ym, 6), 'choice' => $choices[$ym] ?? []];
        }
        $published = [];
        foreach (Souvenirs::allTestimonies() as $mid => $list) {
            foreach ($list as $t) {
                $published[] = $t + ['s' => Index::get((int) $mid)];
            }
        }
        usort($published, fn ($a, $b) => strcmp($b['at'], $a['at']));
        return self::html('admin/collections/souvenirs', ['months' => $months, 'published' => array_slice($published, 0, 12), 'total' => count($published)],
            ['title' => 'Kit souvenirs', 'crumb' => 'Interactif', 'nav' => 'souvenirs']);
    }

    /** POST /admin/souvenirs : match et mot d'introduction d'un mois (0 = automatique). */
    public static function save(Request $req): Response
    {
        $ym = $req->str('mois');
        if (!Souvenirs::validMonth($ym)) {
            return self::back('/admin/souvenirs', null, 'Mois invalide.');
        }
        $id = (int) ($req->str('autre') !== '' ? $req->str('autre') : $req->str('match'));
        $s = $id ? Index::get($id) : null;
        if ($id && (!$s || $s['type'] !== 'match' || !Index::visible($s))) {
            return self::back('/admin/souvenirs', null, 'Choisissez un match publié.');
        }
        Souvenirs::choose($ym, $id, $req->str('intro'), self::actor());
        Activity::log(self::actor(), 'a préparé le kit souvenirs de ' . Souvenirs::monthLabel($ym), ['title' => $s['title'] ?? 'choix automatique', 'path' => '/interactif/souvenirs/']);
        return self::back('/admin/souvenirs#m-' . $ym, 'Kit de ' . Souvenirs::monthLabel($ym) . ' enregistré' . ($s ? ' : ' . ($s['m']['home'] ?? '') . ' – ' . ($s['m']['away'] ?? '') . ' (' . substr((string) ($s['m']['date'] ?? ''), 0, 4) . ').' : ' : choix automatique.'));
    }
}
