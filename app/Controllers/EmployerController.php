<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Domain\EmployerRepository;
use App\Services\I18n;
use App\Services\Search;
use App\Storage\Index;

final class EmployerController extends Controller
{
    public function index(Request $request, array $params): Response
    {
        $criteria = $this->criteria($request);
        $criteria['per_page'] = 24;
        $criteria['hiring'] = $request->get('hiring') === '1';
        $results = Search::employers($criteria);

        return $this->page('pages/employers', [
            'results'  => $results,
            'criteria' => $criteria,
            'query'    => $this->queryParams($request),
        ], [
            'title' => I18n::t('employers.title'),
            'desc'  => I18n::t('employers.lede'),
            'path'  => '/employeurs',
        ]);
    }

    public function show(Request $request, array $params): Response
    {
        $employer = EmployerRepository::findBySlug((string) ($params['slug'] ?? ''));
        if ($employer === null) {
            return $this->notFound('/employeurs');
        }

        $jobs = array_values(array_filter(
            Index::load('jobs'),
            static fn(array $row) => $row['company_slug'] === $employer['slug'],
        ));

        return $this->page('pages/employer', [
            'employer' => $employer,
            'jobs'     => $jobs,
        ], [
            'title' => (string) $employer['name'],
            'desc'  => str_excerpt((string) ($employer['description'] ?: $employer['tagline']), 155)
                        ?: I18n::t('employers.lede'),
            'path'  => '/employeur/' . $employer['slug'],
        ]);
    }
}
