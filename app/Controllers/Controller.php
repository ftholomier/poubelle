<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\I18n;
use App\Services\Seo;

/** Base commune : rendu d'une page publique avec sa mise en page. */
abstract class Controller
{
    /**
     * @param array{title?:string,desc?:string,path?:string,translated?:bool} $meta
     */
    protected function page(string $template, array $data = [], array $meta = []): Response
    {
        // Titre, description et directive robots réglés au back-office
        // l'emportent sur ceux calculés par le contrôleur.
        $meta = Seo::apply($meta, (array) ($meta['seo_vars'] ?? []));

        return Response::html(View::render($template, $data + [
            'title'      => $meta['title'] ?? '',
            'desc'       => $meta['desc'] ?? '',
            'path'       => $meta['path'] ?? '/',
            'robots'     => $meta['robots'] ?? '',
            'schema'     => $meta['schema'] ?? [],
            'ogType'     => $meta['ogType'] ?? 'website',
            'ogImage'    => $meta['ogImage'] ?? '',
            // Trois états : page pivot, page réellement traduite, ou page
            // servie en français faute de traduction disponible.
            'translated'   => $meta['translated'] ?? (!I18n::isPivot() && I18n::hasTranslations()),
            'untranslated' => $meta['untranslated'] ?? (!I18n::isPivot() && !I18n::hasTranslations()),
        ]));
    }

    protected function notFound(string $path = '/'): Response
    {
        return Response::html(View::render('pages/error', [
            'code'  => 404,
            'title' => I18n::t('error.404_title'),
            'body'  => I18n::t('error.404_body'),
            'path'  => $path,
        ]), 404);
    }

    /** Critères de recherche lus depuis la query string (donc sans JS). */
    protected function criteria(Request $request): array
    {
        return [
            'q'        => (string) $request->get('q', ''),
            'city'     => (string) $request->get('city', ''),
            'category' => $request->all('category'),
            'contract' => $request->all('contract'),
            'region'   => $request->all('region'),
            // Provenance : « site » pour les annonces déposées ici, sinon la
            // clé d'un partenaire.
            'source'   => $request->all('source'),
            'skill'    => $request->all('skill'),
            // Une recherche par mot-clé se classe par pertinence, sinon la
            // meilleure réponse se retrouve en page trois derrière des
            // annonces plus récentes mais hors sujet.
            'sort'     => in_array($request->get('sort', ''), ['relevance', 'recent', 'oldest', 'title'], true)
                            ? (string) $request->get('sort')
                            : ((string) $request->get('q', '') !== '' ? 'relevance' : 'recent'),
            'page'     => max(1, $request->intval('page', 1)),
        ];
    }

    /** Paramètres à reporter dans les liens de pagination et de filtre. */
    protected function queryParams(Request $request): array
    {
        $keep = ['q', 'city', 'category', 'contract', 'region', 'skill', 'sort', 'source'];
        $out = [];
        foreach ($keep as $key) {
            $value = $request->query[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $out[$key] = trim($value);
            } elseif (is_array($value) && $value !== []) {
                $out[$key] = array_values(array_filter(array_map('strval', $value), 'strlen'));
            }
        }
        return $out;
    }
}
