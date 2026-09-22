<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Storage\Index;
use App\Storage\Json;

/**
 * Retrouver la bonne page derrière une adresse inconnue.
 *
 * Vingt ans de WordPress laissent des liens partout : annuaires, forums,
 * messages de candidats, index de Google. Les renvoyer tous à l'accueil perd
 * ce qu'ils valaient ; les renvoyer à la fiche qu'ils visaient le garde.
 *
 * Trois prises, de la plus sûre à la plus incertaine.
 *
 *  1. Le numéro d'article WordPress — « ?p=1027 », « /1027/ ». Sans ambiguïté
 *     possible : c'est une clé primaire. Redirection permanente.
 *  2. Le slug exact. L'import a conservé le `post_name` d'origine, donc
 *     l'adresse d'hier et celle d'aujourd'hui finissent par le même segment,
 *     quelle que soit la structure qui le précédait. Permanente aussi.
 *  3. Le rapprochement par mots. Un titre remanié, un slug tronqué par un
 *     annuaire : on compare les mots et on n'accepte qu'une correspondance
 *     large et sans rivale. Temporaire — une supposition ne se grave pas dans
 *     le cache des navigateurs.
 *
 * Rien de convaincant : pas de redirection. La page 404 reprend les mots de
 * l'adresse dans sa recherche, ce qui vaut mieux qu'un renvoi au hasard.
 */
final class Redirects
{
    /** Mots trop courants pour peser dans un rapprochement. */
    private const VIDES = [
        'de', 'du', 'des', 'la', 'le', 'les', 'un', 'une', 'et', 'ou', 'en',
        'au', 'aux', 'pour', 'par', 'sur', 'dans', 'avec', 'chez', 'sans',
        'offre', 'offres', 'emploi', 'emplois', 'annonce', 'annonces', 'job',
        'jobs', 'cv', 'profil', 'profils', 'fiche', 'page', 'index', 'html',
        'php', 'www', 'intermittent', 'fr', 'spectacle',
    ];

    /** Part des mots de l'adresse qu'une fiche doit couvrir pour l'emporter. */
    private const SEUIL = 0.7;

    private static ?array $map = null;

    /**
     * Cible interne d'une adresse inconnue.
     *
     * @return array{path:string, status:int}|null
     */
    public static function match(string $path, array $query = []): ?array
    {
        $map = self::map();
        if ($map['slugs'] === []) {
            return null;
        }

        // 1. Numéro d'article, dans la requête ou dans le chemin.
        foreach (['p', 'page_id', 'post', 'id'] as $key) {
            $id = trim((string) ($query[$key] ?? ''));
            if ($id !== '' && ctype_digit($id) && isset($map['legacy'][$id])) {
                return ['path' => $map['legacy'][$id], 'status' => 301];
            }
        }

        $segments = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));

        // 2. Slug exact. On part de la fin : c'est là que WordPress plaçait le
        //    titre, derrière la date ou le type d'article.
        foreach (array_reverse($segments) as $segment) {
            $segment = self::stripExtension($segment);
            if ($segment === '') {
                continue;
            }
            if (isset($map['slugs'][$segment])) {
                return ['path' => $map['slugs'][$segment], 'status' => 301];
            }
            if (ctype_digit($segment) && isset($map['legacy'][$segment])) {
                return ['path' => $map['legacy'][$segment], 'status' => 301];
            }
        }

        // 3. Rapprochement par mots sur le segment le plus fourni.
        $best = self::nearest(self::words(implode(' ', $segments)), $map['slugs']);
        return $best === null ? null : ['path' => $best, 'status' => 302];
    }

    /**
     * Cible d'un « ?p=1027 » posé sur une adresse par ailleurs valide.
     *
     * Le chemin étant bon — l'accueil, le plus souvent — la requête n'échoue
     * pas, et rien ne viendrait donc rattraper le lien : c'est le noyau qui
     * interroge, avant de router.
     *
     * @return string chemin interne, ou chaîne vide
     */
    public static function legacyQuery(array $query): string
    {
        foreach (['p', 'page_id', 'post'] as $key) {
            $id = trim((string) ($query[$key] ?? ''));
            if ($id !== '' && ctype_digit($id)) {
                return (string) (self::map()['legacy'][$id] ?? '');
            }
        }
        return '';
    }

    /**
     * Mots de l'adresse, à reprendre dans la recherche de la page 404 : le
     * visiteur arrive alors sur des résultats, pas sur un formulaire vide.
     */
    public static function terms(string $path): string
    {
        $words = self::words(str_replace('/', ' ', trim($path, '/')));
        return implode(' ', array_slice($words, 0, 6));
    }

    /* ------------------------------------------------------------ interne */

    /**
     * Fiche la plus proche, ou rien si le doute subsiste.
     *
     * Deux conditions, et les deux comptent. La fiche doit couvrir la plus
     * grande partie des mots de l'adresse, et devancer nettement la suivante :
     * entre deux régisseurs son à Lyon, se tromper vaut moins bien que de
     * laisser chercher.
     *
     * @param string[]              $words
     * @param array<string, string> $slugs
     */
    private static function nearest(array $words, array $slugs): ?string
    {
        if (count($words) < 2) {
            return null;
        }

        $best = null;
        $bestScore = 0.0;
        $runnerUp = 0.0;

        foreach ($slugs as $slug => $target) {
            $common = count(array_intersect($words, self::words($slug)));
            if ($common === 0) {
                continue;
            }
            $score = $common / count($words);
            if ($score > $bestScore) {
                $runnerUp = $bestScore;
                $bestScore = $score;
                $best = $target;
            } elseif ($score > $runnerUp) {
                $runnerUp = $score;
            }
        }

        return $bestScore >= self::SEUIL && $bestScore - $runnerUp >= 0.15 ? $best : null;
    }

    /** @return string[] mots significatifs, sans accent ni ponctuation */
    private static function words(string $text): array
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = strtr($text, [
            'à'=>'a','â'=>'a','ä'=>'a','á'=>'a','ã'=>'a','å'=>'a','ç'=>'c',
            'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','î'=>'i','ï'=>'i','í'=>'i',
            'ô'=>'o','ö'=>'o','ó'=>'o','õ'=>'o','ù'=>'u','û'=>'u','ü'=>'u',
            'ú'=>'u','ÿ'=>'y','ñ'=>'n','œ'=>'oe','æ'=>'ae','ß'=>'ss',
        ]);
        $parts = preg_split('/[^a-z0-9]+/', $text) ?: [];

        $out = [];
        foreach ($parts as $word) {
            if (strlen($word) >= 3 && !in_array($word, self::VIDES, true)) {
                $out[$word] = $word;
            }
        }
        return array_values($out);
    }

    private static function stripExtension(string $segment): string
    {
        return (string) preg_replace('/\.(html?|php|aspx?)$/i', '', $segment);
    }

    /**
     * Répertoire slug → chemin interne, reconstruit dès qu'un index bouge.
     *
     * Il tient dans quelques dizaines de kilo-octets, là où relire les quatre
     * index en coûterait six cents à chaque adresse inconnue — un robot qui
     * ratisse de vieux liens ne doit pas peser sur le site.
     *
     * @return array{slugs: array<string,string>, legacy: array<string,string>}
     */
    private static function map(): array
    {
        if (self::$map !== null) {
            return self::$map;
        }

        $file = Config::path('data') . '/index/redirects.json';
        $sources = ['jobs', 'cv', 'employers', 'pages'];

        $newest = 0;
        foreach ($sources as $name) {
            $newest = max($newest, (int) @filemtime(Index::path($name)));
        }

        $cached = Json::read($file);
        if ($cached !== [] && (int) ($cached['at'] ?? 0) >= $newest) {
            return self::$map = [
                'slugs'  => (array) ($cached['slugs'] ?? []),
                'legacy' => (array) ($cached['legacy'] ?? []),
            ];
        }

        $slugs = [];
        $legacy = [];
        $prefixes = [
            'jobs'      => '/offre/',
            'cv'        => '/cv/',
            'employers' => '/employeur/',
            'pages'     => '/',
        ];

        foreach ($sources as $name) {
            foreach (Index::load($name) as $row) {
                $slug = trim((string) ($row['slug'] ?? ''));
                // Une fiche retirée ne doit pas devenir une destination : on
                // enverrait vers un 404, ou vers une archive en 410.
                if ($slug === '' || !in_array((string) ($row['status'] ?? 'publish'), ['publish', ''], true)) {
                    continue;
                }
                $target = $prefixes[$name] . $slug;
                $slugs[$slug] ??= $target;

                $id = trim((string) ($row['legacy_id'] ?? ''));
                if ($id !== '' && $id !== '0') {
                    $legacy[$id] ??= $target;
                }
            }
        }

        Json::write($file, ['at' => time(), 'slugs' => $slugs, 'legacy' => $legacy]);
        return self::$map = ['slugs' => $slugs, 'legacy' => $legacy];
    }
}
