<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Domain\CvRepository;
use App\Domain\EmployerRepository;
use App\Domain\JobRepository;
use App\Services\I18n;
use App\Services\JobReview;
use App\Services\RateLimit;
use App\Services\Sanitizer;
use App\Services\Upload;
use App\Services\Validator;
use App\Storage\Audit;
use App\Storage\Index;

/** Dépôt d'un CV et dépôt d'une annonce, côté public. */
final class SubmitController extends Controller
{
    private const MAX_PER_HOUR = 5;

    /* ------------------------------------------------------------ dépôt CV */

    public function cvForm(Request $request, array $params): Response
    {
        return $this->renderCvForm([], [], (string) Session::flash('cv_done'));
    }

    public function cvSubmit(Request $request, array $params): Response
    {
        if (!Csrf::check($request)) {
            return $this->renderCvForm($request->post, ['_form' => I18n::t('form.err_csrf')]);
        }
        if (RateLimit::hit('submit-cv', $request->ip(), self::MAX_PER_HOUR, 3600) > 0) {
            return $this->renderCvForm($request->post, ['_form' => I18n::t('form.err_rate')]);
        }

        $v = (new Validator($request->post))
            ->required('name')
            ->required('title')          // métier principal
            ->required('city')
            ->email('email')
            ->optional('mobility', 120)
            ->optional('summary', 6000)
            ->integer('experience', 0, 60)
            ->listOf('skills', 24)
            ->listOf('skills_free', 24)
            ->checkbox('listed')
            ->checkbox('contact_public')
            ->accepted('gdpr');

        $isDraft = $request->input('action') === 'draft';
        $values = $v->values();

        // Le fichier n'est traité qu'une fois le reste validé.
        $id = CvRepository::nextId();
        $file = ['path' => '', 'name' => '', 'size' => 0, 'legacy_url' => ''];
        if (!empty($request->files['cv_file']['name'])) {
            $stored = Upload::store($request->files['cv_file'], 'cv', $id, 'cv');
            if (!$stored['ok'] && $stored['error'] !== '') {
                $v->addError('cv_file', $stored['error']);
            } elseif ($stored['ok']) {
                $file = ['path' => $stored['path'], 'name' => $stored['name'], 'size' => $stored['size'], 'legacy_url' => ''];
            }
        }

        if ($v->fails()) {
            Upload::delete($file['path']);
            return $this->renderCvForm($request->post, $v->errors());
        }

        $location = $this->parsePlace((string) $values['city']);
        $skills = array_values(array_unique(array_merge((array) $values['skills'], (array) $values['skills_free'])));

        $cv = [
            'id'      => $id,
            'slug'    => CvRepository::uniqueSlug(($values['name'] ?: 'profil') . '-' . substr($id, -5)),
            'status'  => $isDraft ? 'draft' : 'publish',
            'name'    => $values['name'],
            'title'   => $values['title'],
            'summary' => Sanitizer::text((string) $values['summary']),
            'location'=> $location,
            'experience_years' => (int) $values['experience'],
            'mobility'=> $values['mobility'],
            'skills'  => $skills,
            'file'    => $file,
            'contact' => [
                'email'  => $values['email'],
                'phone'  => '',
                'public' => (bool) $values['contact_public'],
            ],
            'available' => true,
            'listed'    => !$isDraft && (bool) $values['listed'],
            'published_at' => $isDraft ? '' : date('c'),
        ];

        CvRepository::save($cv);
        Index::rebuild('cv');
        Audit::log('cv.submitted', ['id' => $id, 'draft' => $isDraft]);

        Session::flash('cv_done', $isDraft ? 'draft' : $cv['slug']);
        return Response::redirect(I18n::url('/deposer-un-cv'), 303);
    }

    /* -------------------------------------------------------- dépôt annonce */

    public function jobForm(Request $request, array $params): Response
    {
        return $this->renderJobForm([], [], (string) Session::flash('job_done'));
    }

    public function jobSubmit(Request $request, array $params): Response
    {
        if (!Csrf::check($request)) {
            return $this->renderJobForm($request->post, ['_form' => I18n::t('form.err_csrf')]);
        }
        if (RateLimit::hit('submit-job', $request->ip(), self::MAX_PER_HOUR, 3600) > 0) {
            return $this->renderJobForm($request->post, ['_form' => I18n::t('form.err_rate')]);
        }

        $v = (new Validator($request->post))
            ->required('title')
            ->required('company')
            ->required('city')
            ->optional('salary', 120)
            ->date('starts_at')
            ->required('description')
            ->email('apply_email')
            ->url('company_website')
            ->listOf('contract', 4)
            ->listOf('category', 3)
            ->listOf('tags', 10)
            ->checkbox('remote')
            ->accepted('gdpr');

        $values = $v->values();
        $isPreview = $request->input('action') === 'preview';

        $id = JobRepository::nextId();
        $job = [
            'id'      => $id,
            'slug'    => JobRepository::uniqueSlug(($values['title'] ?: 'offre') . '-' . substr($id, -5)),
            'title'   => $values['title'],
            'status'  => 'publish',
            'description' => Sanitizer::text((string) $values['description']),
            'company' => [
                'name'    => $values['company'],
                'slug'    => slugify((string) $values['company']),
                'website' => $values['company_website'],
            ],
            'location' => $this->parsePlace((string) $values['city']) + ['remote' => (bool) $values['remote']],
            'salary'   => $values['salary'],
            'contract' => $values['contract'],
            'category' => $values['category'],
            'tags'     => $values['tags'],
            'starts_at'=> $values['starts_at'],
            'apply'    => ['email' => $values['apply_email'], 'url' => ''],
            'published_at' => date('c'),
        ];

        $review = JobReview::check($job);

        // La prévisualisation n'écrit rien : elle renvoie le formulaire avec la relecture.
        if ($isPreview || $v->fails()) {
            return $this->renderJobForm($request->post, $v->errors(), '', $review, $v->fails() ? null : $job);
        }

        JobRepository::save($job);
        $this->touchEmployer($job);
        Index::rebuild('jobs');
        Index::rebuild('employers');
        Audit::log('job.submitted', ['id' => $id]);

        Session::flash('job_done', $job['slug']);
        return Response::redirect(I18n::url('/deposer-une-annonce'), 303);
    }

    /* ------------------------------------------------------------- privé */

    private function renderCvForm(array $old, array $errors = [], string $done = ''): Response
    {
        $facets = Index::meta('cv');
        return $this->page('pages/post-cv', [
            'old'       => $old,
            'errors'    => $errors,
            'done'      => $done,
            'suggested' => array_slice(array_keys((array) ($facets['skills'] ?? [])), 0, 18),
        ], [
            'title' => I18n::t('post_cv.title'),
            'desc'  => I18n::t('post_cv.lede'),
            'path'  => '/deposer-un-cv',
        ]);
    }

    private function renderJobForm(
        array $old,
        array $errors = [],
        string $done = '',
        array $review = [],
        ?array $preview = null,
    ): Response {
        $facets = Index::meta('jobs');
        return $this->page('pages/post-job', [
            'old'        => $old,
            'errors'     => $errors,
            'done'       => $done,
            'review'     => $review,
            'preview'    => $preview,
            'categories' => array_keys((array) ($facets['categories'] ?? [])),
            'contracts'  => array_keys((array) ($facets['contracts'] ?? [])),
        ], [
            'title' => I18n::t('post_job.title'),
            'desc'  => I18n::t('post_job.lede'),
            'path'  => '/deposer-une-annonce',
        ]);
    }

    /** Ville saisie librement -> ville + région, avec la même logique qu'à l'import. */
    private function parsePlace(string $raw): array
    {
        require_once Config::path('root') . '/bin/lib/Wp.php';
        $parsed = \Bin\Wp::parseLocation($raw);
        return ['city' => $parsed['city'], 'region' => $parsed['region'], 'country' => $parsed['country']];
    }

    /** Crée ou complète la fiche employeur rattachée à une annonce. */
    private function touchEmployer(array $job): void
    {
        $slug = (string) $job['company']['slug'];
        if ($slug === '') {
            return;
        }
        $employer = EmployerRepository::find($slug) ?? [
            'id'   => $slug,
            'slug' => $slug,
            'name' => $job['company']['name'],
        ];
        if (($employer['website'] ?? '') === '' && ($job['company']['website'] ?? '') !== '') {
            $employer['website'] = $job['company']['website'];
        }
        if (($employer['location']['city'] ?? '') === '') {
            $employer['location'] = ['city' => $job['location']['city'], 'region' => $job['location']['region']];
        }
        EmployerRepository::save($employer);
    }
}
