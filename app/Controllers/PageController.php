<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Domain\PageRepository;
use App\Services\I18n;
use App\Services\Seo;
use App\Storage\Index;

final class PageController extends Controller
{
    /** Page éditoriale servie depuis /data/content/{lang}/{slug}.json */
    public function show(Request $request, array $params): Response
    {
        $slug = (string) ($params['slug'] ?? '');

        // Page renommée depuis la rubrique SEO : l'ancienne adresse redirige
        // au lieu de disparaître, et le référencement acquis se reporte.
        $moved = Seo::pageRedirect($slug);
        if ($moved !== '') {
            return Response::redirect(I18n::url('/' . $moved), 301);
        }

        $page = PageRepository::find($slug, I18n::lang());

        if ($page === null || ($page['status'] ?? '') !== 'publish') {
            return $this->notFound('/');
        }

        return $this->page('pages/page', ['page' => $page], [
            'title'      => (string) ($page['seo']['title'] ?: $page['title']),
            'desc'       => (string) ($page['seo']['description'] ?: $page['excerpt']),
            'path'       => '/' . $page['slug'],
            'robots'     => ($page['seo']['robots'] ?? '') === 'noindex' ? 'noindex, follow' : '',
            'ogType'     => 'article',
            // Le bandeau « traduit automatiquement » n'apparaît que sur une vraie traduction.
            'translated' => !I18n::isPivot() && (bool) ($page['translated'] ?? false),
        ]);
    }

    /** « Ressources » : index des pages éditoriales publiées. */
    public function resources(Request $request, array $params): Response
    {
        return $this->page('pages/resources', [
            'pages' => Index::load('pages'),
        ], [
            'title' => I18n::t('nav.resources'),
            'desc'  => I18n::t('home.lede'),
            'path'  => '/ressources',
        ]);
    }
}
