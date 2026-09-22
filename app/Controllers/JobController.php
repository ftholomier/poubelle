<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Domain\EmployerRepository;
use App\Domain\JobRepository;
use App\Services\Aggregator;
use App\Services\Contact;
use App\Services\ContentTranslator;
use App\Services\I18n;
use App\Services\JobLifecycle;
use App\Services\StructuredData;
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
            // Seuls les partenaires configurés et actifs sont proposés.
            'partners' => Aggregator::activePartners(),
        ], [
            'title' => I18n::t('jobs.title', number_format(
                (int) ($facets['total'] ?? 0) + $blended['external'], 0, ',', ' ')),
            'desc'  => I18n::t('home.lede'),
            'path'  => '/offres',
        ]);
    }

    public function show(Request $request, array $params): Response
    {
        return $this->render($request, (string) ($params['slug'] ?? ''));
    }

    /**
     * Candidature relayée à l'employeur. L'adresse de celui-ci ne figure nulle
     * part dans la page : le site fait passer le message, la réponse revient
     * directement au candidat.
     */
    public function apply(Request $request, array $params): Response
    {
        $slug = (string) ($params['slug'] ?? '');
        $job = JobRepository::findBySlug($slug);
        if ($job === null || ($job['status'] ?? '') !== 'publish' || JobLifecycle::isExpired($job)) {
            return $this->notFound('/offres');
        }
        if (!Csrf::check($request)) {
            return $this->render($request, $slug, ['_form' => I18n::t('form.err_csrf')]);
        }

        $result = Contact::apply($request, $job);
        if (!$result['ok']) {
            return $this->render($request, $slug, ['_form' => $result['error']]);
        }

        Session::flash('apply_done', $job['slug']);
        return Response::redirect(I18n::url('/offre/' . $job['slug']), 303);
    }

    /**
     * Rendu de la fiche, partagé entre l'affichage et le retour du formulaire
     * de candidature.
     *
     * Le statut commande la réponse : un brouillon ou un spam n'existe pas
     * publiquement, une annonce expirée reste lisible mais sort de l'index, et
     * une archive ancienne répond 410 pour que les moteurs la retirent.
     */
    private function render(Request $request, string $slug, array $errors = []): Response
    {
        $job = JobRepository::findBySlug($slug);
        if ($job === null || !in_array((string) $job['status'], ['publish', 'expired'], true)) {
            return $this->notFound('/offres');
        }

        $expired = $job['status'] === 'expired' || JobLifecycle::isExpired($job);
        if ($expired && JobLifecycle::isArchived($job)) {
            return $this->gone();
        }

        // Une annonce consultée dans une autre langue est traduite à la volée
        // puis mise en cache : le visiteur suivant n'attend plus. On ne paie
        // pas cet appel pour un robot.
        $job = ContentTranslator::translateOnDemand(
            $job, 'job', I18n::lang(), $request->ip(), $request->isBot(),
        );

        $employerSlug = (string) ($job['company']['slug'] ?? '');
        $employer = $employerSlug !== '' ? EmployerRepository::find($employerSlug) : null;

        // Autres offres du même employeur, hors celle affichée.
        $siblings = [];
        if ($employerSlug !== '') {
            foreach (Search::live(Index::load('jobs')) as $row) {
                if ($row['company_slug'] === $employerSlug && $row['id'] !== $job['id'] && $row['status'] === 'publish') {
                    $siblings[] = $row;
                }
            }
        }

        return $this->page('pages/job', [
            'job'       => $job,
            'employer'  => $employer,
            'siblings'  => array_slice($siblings, 0, 3),
            'expired'   => $expired,
            'canApply'  => !$expired && trim((string) ($job['apply']['email'] ?? '')) !== '',
            'errors'    => $errors,
            'sent'      => (string) Session::flash('apply_done') === (string) $job['slug'],
        ], [
            'title'        => (string) $job['title'],
            'desc'         => str_excerpt((string) $job['description'], 155),
            'path'         => '/offre/' . $job['slug'],
            'translated'   => !empty($job['translated']),
            'untranslated' => !I18n::isPivot() && empty($job['translated']),
            // Une offre expirée reste lisible pour qui a le lien, mais n'a
            // plus à être proposée en résultat de recherche.
            'robots'       => $expired ? 'noindex, follow' : '',
            'schema'       => StructuredData::jobPosting($job, $employer),
        ]);
    }

    /** Archive trop ancienne : la fiche n'existe plus, et le dit clairement. */
    private function gone(): Response
    {
        return Response::html(View::render('pages/error', [
            'code'  => 410,
            'title' => I18n::t('error.410_title'),
            'body'  => I18n::t('error.410_body'),
            'path'  => '/offres',
        ]), 410);
    }
}
