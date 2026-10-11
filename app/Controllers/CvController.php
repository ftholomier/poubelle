<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Domain\CvRepository;
use App\Services\Contact;
use App\Services\ContentTranslator;
use App\Services\I18n;
use App\Services\Search;
use App\Services\StructuredData;
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
            'seo_vars' => [
                '{total}'  => number_format((int) ($facets['total'] ?? 0), 0, ',', ' '),
                '{ville}'  => (string) ($criteria['city'] ?? ''),
                '{motcle}' => (string) ($criteria['q'] ?? ''),
            ],
            'schema'=> StructuredData::breadcrumb([
                [(string) Config::get('site.name'), '/'],
                [I18n::t('nav.cv'), '/cv'],
            ]),
        ]);
    }

    public function show(Request $request, array $params): Response
    {
        return $this->render($request, (string) ($params['slug'] ?? ''));
    }

    /**
     * Message adressé à un candidat. Son adresse n'apparaît jamais dans la
     * page : le site relaie, et la réponse part vers l'employeur.
     */
    public function contact(Request $request, array $params): Response
    {
        $slug = (string) ($params['slug'] ?? '');
        $cv = CvRepository::findBySlug($slug);
        if ($cv === null || !Contact::reachable($cv)) {
            return $this->notFound('/cv');
        }
        if (!Csrf::check($request)) {
            return $this->render($request, $slug, ['_form' => I18n::t('form.err_csrf')]);
        }

        $result = Contact::message($request, $cv);
        if (!$result['ok']) {
            return $this->render($request, $slug, ['_form' => $result['error']]);
        }

        Session::flash('contact_done', $cv['slug']);
        return Response::redirect(I18n::url('/cv/' . $cv['slug']), 303);
    }

    private function render(Request $request, string $slug, array $errors = []): Response
    {
        $cv = CvRepository::findBySlug($slug);
        if ($cv === null || ($cv['status'] ?? '') !== 'publish' || !($cv['listed'] ?? false)) {
            return $this->notFound('/cv');
        }

        // Pas d'appel de traduction facturé pour un robot.
        $cv = ContentTranslator::translateOnDemand(
            $cv, 'cv', I18n::lang(), $request->ip(), $request->isBot(),
        );

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
            'cv'        => $cv,
            'related'   => array_slice($related, 0, 4),
            'reachable' => Contact::reachable($cv),
            // Même principe que la candidature : le jeton n'est rendu que pour
            // qui ouvre le formulaire, jamais pour un simple lecteur.
            'showForm'  => $errors !== [] || (string) $request->get('contacter', '') !== '',
            'errors'    => $errors,
            'sent'      => (string) Session::peekFlash('contact_done') === (string) $cv['slug'],
        ], [
            'title'  => (string) $cv['name'] . ' — ' . (string) $cv['title'],
            'desc'   => str_excerpt((string) $cv['summary'], 155),
            'path'   => '/cv/' . $cv['slug'],
            'schema' => StructuredData::person($cv),
            'seo_vars' => [
                '{nom}'          => (string) $cv['name'],
                '{metier}'       => (string) $cv['title'],
                '{ville}'        => (string) ($cv['location']['city'] ?? ''),
                '{region}'       => (string) ($cv['location']['region'] ?? ''),
                '{annees}'       => (int) ($cv['experience_years'] ?? 0) > 0
                                    ? I18n::t('cv.years', (int) $cv['experience_years']) : '',
                '{competences}'  => implode(', ', array_slice((array) ($cv['skills'] ?? []), 0, 4)),
            ],
        ]);
    }
}
