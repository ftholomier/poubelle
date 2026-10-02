<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Services\Categories;
use App\Services\Geo;

/** Lecture et validation des critères de recherche depuis l'URL (?q=&cat=&dep=&insee=…). */
final class SearchCriteria
{
    public static function fromRequest(): array
    {
        $c = [];
        $q = Request::str('q', 120);
        if ($q !== '') {
            $c['q'] = $q;
        }
        $cat = Request::str('cat', 60);
        if ($cat !== '' && Categories::get($cat)) {
            $c['cat'] = $cat;
        }
        $occ = Request::str('occasion', 60);
        if ($occ !== '' && Categories::occasion($occ)) {
            $c['occasion'] = $occ;
        }
        $region = Request::str('region', 3);
        if ($region !== '' && Geo::region($region)) {
            $c['region'] = $region;
        }
        $dep = Request::str('dep', 3);
        if ($dep !== '' && Geo::dep($dep)) {
            $c['dep'] = Geo::depCode($dep);
        }
        $insee = Request::str('insee', 5);
        if ($insee !== '' && Geo::commune($insee)) {
            $c['insee'] = strtoupper($insee);
        } elseif (($ou = Request::str('ou', 80)) !== '') {
            $found = Geo::search($ou, 1);
            if ($found) {
                $c['insee'] = $found[0]['insee'];
            }
        }
        $lat = Request::query('lat');
        $lng = Request::query('lng');
        if (is_numeric($lat) && is_numeric($lng) && abs((float) $lat) <= 90 && abs((float) $lng) <= 180 && (float) $lat !== 0.0) {
            $c['lat'] = (float) $lat;
            $c['lng'] = (float) $lng;
        }
        $radius = (int) Request::query('rayon', 0);
        if ($radius > 0) {
            $c['radius'] = max(5, min(200, $radius));
        }
        if (Request::query('photo') === '1') {
            $c['photo'] = true;
        }
        if (Request::query('avis') === '1') {
            $c['reviews'] = true;
        }
        $sort = Request::str('tri', 12);
        if (in_array($sort, ['distance', 'rating', 'recent', 'price'], true)) {
            $c['sort'] = $sort;
        }
        $c['offset'] = max(0, min(5000, (int) Request::query('offset', 0)));
        $c['per'] = max(6, min(60, (int) Request::query('per', 24)));
        return $c;
    }

    /** Une recherche est-elle filtrée (sinon : tous les repères sur la carte) ? */
    public static function filtered(array $c): bool
    {
        return !empty($c['q']) || !empty($c['cat']) || !empty($c['occasion']) || !empty($c['region']) || !empty($c['dep']) || !empty($c['insee']) || !empty($c['photo']) || !empty($c['reviews']) || isset($c['lat']);
    }
}
