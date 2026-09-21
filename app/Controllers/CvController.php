<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Domain\CvRepository;
use App\Services\ContentTranslator;
use App\Services\I18n;
use App\Services\Search;
use App\Storage\Index;

final class CvController extends Controller
{
    public function index(Request $request, array $params): Response
    {
        $criteria = $this->criteria($request);
        $criteria['per_page'] = 12;
        $results = Search::cv($criteria);
        $results['items'] = ContentTranslator::applyToRows($results['items'], 'cv', I18n::lang());
        $facets = Index::meta('cv');

        return $this->page('pages/cv-list', [
            'results'  => $results,
            'facets'   => $facets,
            'criteria' => $criteria,
            'query'    => $this->queryParams($request),
        ], [
            'title' => I18n::t('cv.title', number_format((int) ($facets['total'] ?? 0), 0, ',', ' ')),
            'desc'  => I18n::t('home.profiles_note'),
            'path'  => '/cv',
        ]);
    }

    public function show(Request $request, array $params): Response
    {
        $cv = CvRepository::findBySlug((string) ($params['slug'] ?? ''));
        if ($cv === null || ($cv['status'] ?? '') !== 'publish' || !($cv['listed'] ?? false)) {
            return $this->notFound('/cv');
        }

        $cv = ContentTranslator::translateOnDemand($cv, 'cv', I18n::lang(), $request->ip());

        // Profils voisins : même métier ou même ville.
        $related = [];
        foreach (Index::load('cv') as $row) {
            if ($row['id'] === $cv['id'] || $row['status'] !== 'publish') {
                continue;
            }
            $sameCity = $row['city'] !== '' && $row['city'] === ($cv['location']['city'] ?? '');
            $sameRole = $row['title'] !== '' && $cv['title'] !== ''
                && str_contains(Index::haystack([$row['title']]), Index::haystack([(string) $cv['title']]));
            if ($sameCity || $sameRole) {
                $related[] = $row;
            }
        }

        return $this->page('pages/cv', [
            'cv'      => $cv,
            'related' => array_slice($related, 0, 4),
        ], [
            'title' => (string) $cv['name'] . ' — ' . (string) $cv['title'],
            'desc'  => str_excerpt((string) $cv['summary'], 155),
            'path'  => '/cv/' . $cv['slug'],
        ]);
    }
}
