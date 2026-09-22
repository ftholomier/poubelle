<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Domain\EmployerRepository;
use App\Domain\JobRepository;
use App\Services\Aggregator;
use App\Services\ContentTranslator;
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

        // Les cartes affichent le titre et l'extrait traduits quand ils existent.
        $results['items'] = ContentTranslator::applyToRows($results['items'], 'job', I18n::lang());

        // Les offres partenaires comptent dans ce que voit le visiteur : la
        // pagination reste guidée par les annonces du site — seules elles se
        // parcourent page par page — mais les compteurs annoncent les deux,
        // avec leur détail, plutôt qu'un total qui en cacherait la moitié.
        $blended = Aggregator::blend($results['items'], $criteria);
        $results['items'] = $blended['items'];
        $results['local_total'] = (int) $results['total'];
        $results['total'] = $results['local_total'] + $blended['external'];

        $newest = Index::load('jobs')[0]['published_at'] ?? '';

        return $this->page('pages/jobs', [
            'results'  => $results,
            'external' => $blended['external'],
            'facets'   => $facets,
            'criteria' => $criteria,
            'query'    => $this->queryParams($request),
            'newest'   => $newest,
        ], [
            'title' => I18n::t('jobs.title', number_format(
                (int) ($facets['total'] ?? 0) + $blended['external'], 0, ',', ' ')),
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

        // Une annonce consultée dans une autre langue est traduite à la volée
        // puis mise en cache : le visiteur suivant n'attend plus.
        $job = ContentTranslator::translateOnDemand($job, 'job', I18n::lang(), $request->ip());

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
            'title'        => (string) $job['title'],
            'desc'         => str_excerpt((string) $job['description'], 155),
            'path'         => '/offre/' . $job['slug'],
            'translated'   => !empty($job['translated']),
            'untranslated' => !I18n::isPivot() && empty($job['translated']),
        ]);
    }
}
