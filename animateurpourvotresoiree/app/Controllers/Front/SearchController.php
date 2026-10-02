<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Controllers\Api\SearchCriteria;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Url;
use App\Services\Categories;
use App\Services\Geo;
use App\Services\Search;
use App\Services\Seo;
use App\Services\Stats;

/** Explorateur libre (/recherche/, /carte/) et page des favoris. */
final class SearchController extends Controller
{
    public function index(): Response
    {
        $c = SearchCriteria::fromRequest();
        unset($c['offset']);
        $page = max(1, (int) Request::query('page', 1));
        $res = Search::run($c + ['page' => $page, 'per' => 24]);
        $filtered = SearchCriteria::filtered($c);
        $title = 'Trouver un pro';
        if (!empty($c['cat'])) {
            $title = Categories::name($c['cat']);
        }
        if (!empty($c['insee'])) {
            $title .= ' ' . Geo::inCity((string) Geo::commune($c['insee'])['n']);
        }
        if (!empty($c['q'])) {
            Stats::hit('search', 0, (string) $c['q']);
        }
        Stats::hit('pv');
        $meta = Seo::meta('search', []);
        return (new ListingController())->explorer([
            'h1' => $filtered ? $title : 'Trouvez le pro qui fera vibrer votre fête',
            'kicker' => '🔎 Recherche',
            'intro' => $filtered ? '' : 'Filtrez par métier, ville ou occasion, survolez la liste : la carte vous montre où ils se trouvent.',
            'crumbs' => [['Accueil', '/'], ['Recherche', '/recherche/']],
            'criteria' => $c,
            'state' => $c + ['ou' => !empty($c['insee']) ? Geo::label(Geo::commune($c['insee'])) : (string) Request::query('ou', '')],
            'result' => $res,
            'page' => $page,
            'self' => '/recherche/',
            'children' => [],
            'related' => [],
            'cat' => !empty($c['cat']) ? Categories::get($c['cat']) : null,
            'search' => true,
            'mapFirst' => str_starts_with(Request::path(), '/carte'),
            'seo_content' => '',
            'meta' => $meta + [
                'canonical' => Url::abs('/recherche/'),
                'robots' => $filtered || $page > 1 ? 'noindex, follow' : 'index, follow',
            ],
        ]);
    }

    public function map(): Response
    {
        return $this->index();
    }

    public function favoris(): Response
    {
        return $this->view('front/favoris', ['meta' => ['title' => 'Mes favoris', 'robots' => 'noindex, follow', 'description' => 'Vos pros préférés, enregistrés sur cet appareil.', 'scripts' => [asset('js/favoris.js')]]]);
    }
}
