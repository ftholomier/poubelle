<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Domain\EmployerRepository;
use App\Services\I18n;
use App\Services\Search;
use App\Services\StructuredData;
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
            'seo_vars' => ['{total}' => number_format((int) $results['total'], 0, ',', ' ')],
            'schema'=> StructuredData::breadcrumb([
                [(string) \App\Core\Config::get('site.name'), '/'],
                [I18n::t('nav.employers'), '/employeurs'],
            ]),
        ]);
    }

    public function show(Request $request, array $params): Response
    {
        $employer = EmployerRepository::findBySlug((string) ($params['slug'] ?? ''));
        if ($employer === null) {
            return $this->notFound('/employeurs');
        }

        // Brouillons, spams et annonces expirées n'ont rien à faire sur une
        // page publique : la fiche employeur ne liste que ce qui est en ligne.
        $jobs = Search::live(array_values(array_filter(
            Index::load('jobs'),
            static fn(array $row) => $row['company_slug'] === $employer['slug']
                                     && ($row['status'] ?? '') === 'publish',
        )));

        return $this->page('pages/employer', [
            'employer' => $employer,
            'jobs'     => $jobs,
        ], [
            'title' => (string) $employer['name'],
            'desc'  => str_excerpt((string) ($employer['description'] ?: $employer['tagline']), 155)
                        ?: I18n::t('employers.lede'),
            'path'   => '/employeur/' . $employer['slug'],
            'schema' => StructuredData::organization($employer),
            'seo_vars' => [
                '{nom}'    => (string) $employer['name'],
                '{ville}'  => (string) ($employer['location']['city'] ?? ''),
                '{offres}' => (string) count($jobs),
            ],
        ]);
    }
}
