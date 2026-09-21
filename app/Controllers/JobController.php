<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Domain\EmployerRepository;
use App\Domain\JobRepository;
use App\Services\I18n;
use App\Services\Search;
use App\Storage\Index;

final class JobController extends Controller
{
    public function index(Request $request, array $params): Response
    {
        $criteria = $this->criteria($request);
        $criteria['per_page'] = (int) Config::get('search.per_page', 6);
        $results = Search::jobs($criteria);
        $facets = Index::meta('jobs');

        $newest = Index::load('jobs')[0]['published_at'] ?? '';

        return $this->page('pages/jobs', [
            'results'  => $results,
            'facets'   => $facets,
            'criteria' => $criteria,
            'query'    => $this->queryParams($request),
            'newest'   => $newest,
        ], [
            'title' => I18n::t('jobs.title', number_format((int) ($facets['total'] ?? 0), 0, ',', ' ')),
            'desc'  => I18n::t('home.lede'),
            'path'  => '/offres',
        ]);
    }

    public function show(Request $request, array $params): Response
    {
        $job = JobRepository::findBySlug((string) ($params['slug'] ?? ''));
        if ($job === null) {
            return $this->notFound('/offres');
        }

        $employerSlug = (string) ($job['company']['slug'] ?? '');
        $employer = $employerSlug !== '' ? EmployerRepository::find($employerSlug) : null;

        // Autres offres du même employeur, hors celle affichée.
        $siblings = [];
        if ($employerSlug !== '') {
            foreach (Index::load('jobs') as $row) {
                if ($row['company_slug'] === $employerSlug && $row['id'] !== $job['id'] && $row['status'] === 'publish') {
                    $siblings[] = $row;
                }
            }
        }

        return $this->page('pages/job', [
            'job'       => $job,
            'employer'  => $employer,
            'siblings'  => array_slice($siblings, 0, 3),
        ], [
            'title' => (string) $job['title'],
            'desc'  => str_excerpt((string) $job['description'], 155),
            'path'  => '/offre/' . $job['slug'],
        ]);
    }
}
