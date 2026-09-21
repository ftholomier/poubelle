<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Domain\PageRepository;
use App\Services\I18n;
use App\Services\RateLimit;
use App\Services\Regie;
use App\Services\Search;
use App\Storage\Audit;
use App\Storage\Index;

/** API interne JSON : recherche, suggestions, assistant, signalement. */
final class ApiController extends Controller
{
    public function jobs(Request $request, array $params): Response
    {
        $criteria = $this->criteria($request);
        $criteria['per_page'] = min(50, max(1, $request->intval('per_page', 12)));
        $results = Search::jobs($criteria);

        return Response::json([
            'total' => $results['total'],
            'page'  => $results['page'],
            'pages' => $results['pages'],
            'items' => array_map([$this, 'publicJob'], $results['items']),
        ])->withHeader('Cache-Control', 'public, max-age=120');
    }

    public function cv(Request $request, array $params): Response
    {
        $criteria = $this->criteria($request);
        $criteria['per_page'] = min(50, max(1, $request->intval('per_page', 12)));
        $results = Search::cv($criteria);

        return Response::json([
            'total' => $results['total'],
            'page'  => $results['page'],
            'pages' => $results['pages'],
            'items' => array_map([$this, 'publicCv'], $results['items']),
        ])->withHeader('Cache-Control', 'public, max-age=120');
    }

    public function employers(Request $request, array $params): Response
    {
        $results = Search::employers($this->criteria($request) + ['per_page' => 40]);

        return Response::json([
            'total' => $results['total'],
            'items' => array_map(static fn(array $e) => [
                'slug'      => $e['slug'],
                'name'      => $e['name'],
                'city'      => $e['city'],
                'job_count' => $e['job_count'],
                'url'       => I18n::url('/employeur/' . $e['slug']),
            ], $results['items']),
        ])->withHeader('Cache-Control', 'public, max-age=300');
    }

    /** Suggestions pour l'autocomplétion des champs de recherche. */
    public function suggest(Request $request, array $params): Response
    {
        $needle = Index::haystack([(string) $request->get('q', '')]);
        if (mb_strlen($needle) < 2) {
            return Response::json(['items' => []]);
        }

        $seen = [];
        foreach (Index::load('jobs') as $job) {
            foreach (array_merge([(string) $job['title']], (array) $job['tags'], [(string) $job['city']]) as $candidate) {
                $candidate = trim((string) $candidate);
                if ($candidate === '' || mb_strlen($candidate) > 48) {
                    continue;
                }
                if (str_contains(Index::haystack([$candidate]), $needle)) {
                    $seen[mb_strtolower($candidate)] = $candidate;
                }
            }
            if (count($seen) >= 10) {
                break;
            }
        }

        return Response::json(['items' => array_values(array_slice($seen, 0, 10))]);
    }

    /** Assistant « Régie ». Le jeton CSRF évite qu'un tiers fasse consommer le quota. */
    public function regie(Request $request, array $params): Response
    {
        $body = $request->jsonBody();
        $token = (string) ($body['_csrf'] ?? $request->input('_csrf', ''));
        $expected = Csrf::token('regie');

        if ($token === '' || !hash_equals($expected, $token)) {
            return Response::json(['answer' => I18n::t('form.err_csrf')], 419);
        }
        if (RateLimit::hit('regie', $request->ip(), 20, 600) > 0) {
            return Response::json(['answer' => I18n::t('form.err_rate')], 429);
        }

        $question = trim((string) ($body['question'] ?? $request->input('question', '')));
        $answer = Regie::ask($question);

        return Response::json([
            'answer'   => $answer['answer'],
            'source'   => $answer['source'],
            'grounded' => $answer['grounded'],
        ]);
    }

    /** Signalement d'une traduction ou d'un contenu, depuis le bandeau jaune. */
    public function report(Request $request, array $params): Response
    {
        if (RateLimit::hit('report', $request->ip(), 10, 3600) > 0) {
            return Response::json(['ok' => false], 429);
        }

        $body = $request->jsonBody();
        Audit::log('content.reported', [
            'path'    => mb_substr((string) ($body['path'] ?? ''), 0, 200),
            'lang'    => mb_substr((string) ($body['lang'] ?? ''), 0, 5),
            'comment' => mb_substr((string) ($body['comment'] ?? ''), 0, 500),
        ]);

        return Response::json(['ok' => true]);
    }

    /** Projection publique d'une offre : rien de plus que ce qui est déjà affiché. */
    private function publicJob(array $job): array
    {
        return [
            'slug'         => $job['slug'],
            'title'        => $job['title'],
            'company'      => $job['company'],
            'city'         => $job['city'],
            'region'       => $job['region'],
            'remote'       => (bool) $job['remote'],
            'salary'       => $job['salary'],
            'contract'     => $job['contract'],
            'category'     => $job['category'],
            'tags'         => $job['tags'],
            'excerpt'      => $job['excerpt'],
            'published_at' => $job['published_at'],
            'url'          => I18n::url('/offre/' . $job['slug']),
        ];
    }

    /** Projection publique d'un profil : jamais d'adresse e-mail ni de téléphone. */
    private function publicCv(array $cv): array
    {
        return [
            'slug'    => $cv['slug'],
            'name'    => $cv['name'],
            'title'   => $cv['title'],
            'city'    => $cv['city'],
            'region'  => $cv['region'],
            'years'   => $cv['years'],
            'skills'  => $cv['skills'],
            'excerpt' => $cv['excerpt'],
            'url'     => I18n::url('/cv/' . $cv['slug']),
        ];
    }
}
