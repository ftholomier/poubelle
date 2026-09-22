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

    /**
     * Autocomplétion du champ « Ville ou région ».
     *
     * Les propositions viennent des données du site — les villes et régions où
     * il y a réellement des offres ou des profils — complétées par les
     * 18 régions françaises. Le rapprochement ignore les accents, la casse et
     * les traits d'union : « st etienne » trouve « Saint-Étienne ».
     */
    public function places(Request $request, array $params): Response
    {
        $needle = self::expand(Index::haystack([(string) $request->get('q', '')]));
        if (mb_strlen($needle) < 1) {
            return Response::json(['items' => []]);
        }

        $counts = [];
        foreach (['jobs', 'cv'] as $collection) {
            foreach (Index::load($collection) as $row) {
                foreach (['city', 'region'] as $field) {
                    $value = self::cleanPlace((string) ($row[$field] ?? ''));
                    if ($value === '') {
                        continue;
                    }
                    $key = mb_strtolower($value);
                    $counts[$key] = [
                        'label' => $counts[$key]['label'] ?? $value,
                        'kind'  => $field === 'region' ? 'region' : 'city',
                        'n'     => ($counts[$key]['n'] ?? 0) + 1,
                    ];
                }
            }
        }

        // Régions et grandes villes sont toujours proposables, même sans offre.
        foreach (self::REGIONS as $region) {
            $counts[mb_strtolower($region)] ??= ['label' => $region, 'kind' => 'region', 'n' => 0];
        }
        foreach (self::CITIES as $city) {
            $counts[mb_strtolower($city)] ??= ['label' => $city, 'kind' => 'city', 'n' => 0];
        }

        $matches = [];
        foreach ($counts as $entry) {
            $hay = self::expand(Index::haystack([$entry['label']]));
            if ($hay === '') {
                continue;
            }
            // Un début de mot vaut mieux qu'une occurrence au milieu.
            $position = strpos($hay, $needle);
            if ($position === false) {
                continue;
            }
            $startsWord = $position === 0 || $hay[$position - 1] === ' ';
            $entry['score'] = ($position === 0 ? 1000 : ($startsWord ? 500 : 100)) + (int) $entry['n'];
            $matches[] = $entry;
        }

        usort($matches, static fn(array $a, array $b) => [$b['score'], $a['label']] <=> [$a['score'], $b['label']]);

        return Response::json([
            'items' => array_map(
                static fn(array $m) => ['label' => $m['label'], 'kind' => $m['kind'], 'count' => $m['n']],
                array_slice($matches, 0, 8),
            ),
        ])->withHeader('Cache-Control', 'public, max-age=600');
    }

    /**
     * Écarte les valeurs de lieu inexploitables héritées de l'ancien site :
     * champs libres cumulant plusieurs villes (« Marseille, Lyon, Paris »),
     * ou tronqués par l'ancien nettoyage (« Ile de », « Paris + Hauts-de »).
     * Elles restent dans les fiches ; elles ne sont simplement pas proposées.
     */
    private static function cleanPlace(string $raw): string
    {
        $value = trim($raw);
        if ($value === '' || mb_strlen($value) < 3 || mb_strlen($value) > 40) {
            return '';
        }
        if (preg_match('#[/+,;()]|\bet\b#iu', $value)) {
            return '';
        }
        // Se termine par un mot de liaison : le nom a été coupé.
        if (preg_match('/\b(de|du|des|le|la|les|sur|en|aux)\s*$/iu', $value)) {
            return '';
        }
        return $value;
    }

    /** « st etienne » doit trouver « Saint-Étienne », et inversement. */
    private static function expand(string $normalized): string
    {
        return trim((string) preg_replace(
            ['/\bst\b/', '/\bste\b/', '/\bmt\b/'],
            ['saint', 'sainte', 'mont'],
            $normalized,
        ));
    }

    /** Grandes villes françaises, pour proposer un lieu sans offre en cours. */
    private const CITIES = [
        'Paris', 'Marseille', 'Lyon', 'Toulouse', 'Nice', 'Nantes', 'Montpellier', 'Strasbourg',
        'Bordeaux', 'Lille', 'Rennes', 'Reims', 'Saint-Étienne', 'Le Havre', 'Toulon', 'Grenoble',
        'Dijon', 'Angers', 'Nîmes', 'Villeurbanne', 'Clermont-Ferrand', 'Le Mans', 'Aix-en-Provence',
        'Brest', 'Tours', 'Amiens', 'Limoges', 'Annecy', 'Perpignan', 'Besançon', 'Metz', 'Orléans',
        'Rouen', 'Argenteuil', 'Mulhouse', 'Montreuil', 'Caen', 'Nancy', 'Saint-Denis', 'Roubaix',
        'Tourcoing', 'Nanterre', 'Avignon', 'Vitry-sur-Seine', 'Créteil', 'Poitiers', 'Dunkerque',
        'Versailles', 'Aubervilliers', 'Aulnay-sous-Bois', 'Asnières-sur-Seine', 'Colombes',
        'Saint-Paul', 'Rueil-Malmaison', 'Pau', 'Le Tampon', 'Antibes', 'Saint-Maur-des-Fossés',
        'Champigny-sur-Marne', 'La Rochelle', 'Cannes', 'Calais', 'Béziers', 'Colmar', 'Bourges',
        'Drancy', 'Mérignac', 'Ajaccio', 'Saint-Nazaire', 'Valence', 'Quimper', 'Troyes', 'Lorient',
        'Chambéry', 'Niort', 'Sarcelles', 'Villejuif', 'Hyères', 'Beauvais', 'Cholet', 'Vannes',
        'La Roche-sur-Yon', 'Arles', 'Bayonne', 'Bastia', 'Narbonne', 'Albi', 'Biarritz', 'Sète',
    ];

    /** Les 18 régions, proposées même sans offre associée. */
    private const REGIONS = [
        'Île-de-France', 'Auvergne-Rhône-Alpes', "Provence-Alpes-Côte d'Azur", 'Occitanie',
        'Nouvelle-Aquitaine', 'Hauts-de-France', 'Grand Est', 'Pays de la Loire', 'Bretagne',
        'Normandie', 'Bourgogne-Franche-Comté', 'Centre-Val de Loire', 'Corse',
        'Guadeloupe', 'Martinique', 'Guyane', 'La Réunion', 'Mayotte',
    ];

    /** Assistant « Régie ». Le jeton CSRF évite qu'un tiers fasse consommer le quota. */
    /**
     * Jeton anti-CSRF d'un formulaire public, remis à la demande.
     *
     * Les formulaires rares — assistant, candidature, message à un candidat —
     * ne portent plus leur jeton dans la page : il est réclamé au moment où le
     * visiteur s'en sert. Une session n'est donc ouverte que pour celui qui
     * agit, et les pages restent cachables pour tous les autres.
     */
    public function token(Request $request, array $params): Response
    {
        $form = (string) $request->get('form', '');
        if (!in_array($form, self::PUBLIC_FORMS, true)) {
            return Response::json(['error' => 'unknown-form'], 400);
        }

        return Response::json(['form' => $form, 'token' => Csrf::token($form)])
            ->withHeader('Cache-Control', 'private, no-store');
    }

    /** Formulaires publics autorisés à réclamer un jeton. */
    private const PUBLIC_FORMS = ['regie', 'apply', 'contact'];

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

        // La route n'est pas montée par langue : la page nous dit la sienne, pour
        // que la réponse et ses liens restent dans la langue consultée.
        $lang = (string) ($body['lang'] ?? $request->input('lang', ''));
        if (I18n::isSupported($lang)) {
            I18n::boot($lang);
        }

        $question = trim((string) ($body['question'] ?? $request->input('question', '')));
        $answer = Regie::ask($question);

        return Response::json([
            'answer'   => $answer['answer'],
            // HTML construit côté serveur : liens internes échappés et filtrés.
            'html'     => $answer['html'],
            'grounded' => $answer['grounded'],
        ]);
    }

    /** Signalement d'une traduction ou d'un contenu, depuis le bandeau jaune. */
    public function report(Request $request, array $params): Response
    {
        // Signalement sans jeton — il n'écrit qu'une ligne de journal — mais
        // l'origine doit être le site : un formulaire hébergé ailleurs ne doit
        // pas pouvoir remplir le journal à distance.
        if (!$this->sameOrigin($request)) {
            return Response::json(['ok' => false], 403);
        }
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

    /** L'en-tête Origin (ou Referer) désigne-t-il bien ce site ? */
    private function sameOrigin(Request $request): bool
    {
        $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
        if ($origin === '') {
            $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
            if ($referer === '') {
                return false;
            }
            $origin = (string) parse_url($referer, PHP_URL_SCHEME) . '://'
                    . (string) parse_url($referer, PHP_URL_HOST)
                    . (($port = parse_url($referer, PHP_URL_PORT)) ? ':' . $port : '');
        }

        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        return $host !== '' && str_ends_with($origin, '://' . $host);
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
