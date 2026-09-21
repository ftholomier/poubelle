<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\I18n;
use App\Services\Search;
use App\Storage\Index;

final class HomeController extends Controller
{
    public function index(Request $request, array $params): Response
    {
        $facets = Index::meta('jobs');

        $employers = Index::load('employers');

        return $this->page('pages/home', [
            'facets'   => $facets,
            // Les trois compteurs qu'affichait le site d'origine, calculés
            // sur les données réelles plutôt que saisis en dur.
            'stats'    => [
                'jobs'      => (int) ($facets['total'] ?? 0),
                'cv'        => (int) (Index::meta('cv')['total'] ?? 0),
                'employers' => count(array_filter($employers,
                                 static fn(array $e) => (int) $e['job_count'] > 0)),
            ],
            'families' => Search::jobFamilies(6),
            'latest'   => Search::latestJobs(4),
            'profiles' => Search::latestCv(4),
            'trades'   => $this->trades(),
        ], [
            'title' => I18n::t('home.h1_a') . ' ' . I18n::t('home.h1_b'),
            'desc'  => I18n::t('home.lede'),
            'path'  => '/',
        ]);
    }

    /**
     * Bandeau défilant : les métiers réellement présents dans les annonces,
     * complétés par une liste de repli quand la base est encore pauvre.
     */
    private function trades(): array
    {
        $counts = [];
        foreach (Index::load('jobs') as $job) {
            foreach ((array) ($job['tags'] ?? []) as $tag) {
                $tag = trim((string) $tag);
                if ($tag !== '' && mb_strlen($tag) <= 24) {
                    $counts[mb_strtoupper($tag)] = ($counts[mb_strtoupper($tag)] ?? 0) + 1;
                }
            }
        }
        arsort($counts);
        $trades = array_slice(array_keys($counts), 0, 10);

        $fallback = ['RÉGIE SON', 'CADREUR', 'COSTUMIÈRE', 'MACHINISTE', 'CHEF ÉLECTRO',
                     'MONTEUSE', 'HMC', 'RÉGIE GÉNÉRALE', 'SCRIPTE', 'PERCHMAN'];
        foreach ($fallback as $trade) {
            if (count($trades) >= 10) {
                break;
            }
            if (!in_array($trade, $trades, true)) {
                $trades[] = $trade;
            }
        }
        return $trades;
    }
}
