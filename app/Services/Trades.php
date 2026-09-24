<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Domain\TradeRepository;
use App\Storage\Index;
use App\Storage\Json;

/**
 * Les métiers : familles, fiches, et ce qui les relie au reste du site.
 *
 * Une fiche métier ne vaut que par ce qu'elle ouvre : les annonces du moment,
 * les profils disponibles, les métiers voisins. Le rapprochement se fait sur
 * les mots-clés de la fiche, cherchés dans l'intitulé seul — le texte d'une
 * annonce parle de tout, son intitulé dit ce qu'elle est.
 */
final class Trades
{
    /** Taille du flux partenaire demandé par métier, par la tâche planifiée comme par la page. */
    public const PARTNER_POOL = 20;

    /** Unités de rémunération, telles qu'on les écrit. */
    public const UNITS = [
        'jour'       => 'par jour',
        'semaine'    => 'par semaine',
        'cachet'     => 'par cachet',
        'mois'       => 'par mois',
        'prestation' => 'par prestation',
        'heure'      => 'de l’heure',
    ];

    /** Durée ISO 8601 de chaque unité, pour les données structurées. */
    private const DURATIONS = ['jour' => 'P1D', 'semaine' => 'P1W', 'mois' => 'P1M', 'heure' => 'PT1H'];

    /** Lus une fois par requête : une fiche s'en sert pour cinq blocs. */
    private static ?array $rows = null;
    private static ?array $liveJobs = null;

    /** @return array<int, array<string, mixed>> */
    private static function rows(): array
    {
        return self::$rows ??= Index::load('trades');
    }

    /** @return array<int, array<string, mixed>> annonces publiées et non expirées */
    private static function liveJobs(): array
    {
        return self::$liveJobs ??= array_values(array_filter(
            Search::live(Index::load('jobs')),
            static fn(array $job) => ($job['status'] ?? '') === 'publish',
        ));
    }

    /** À appeler après une modification, dans la même requête. */
    public static function forget(): void
    {
        self::$rows = null;
        self::$liveJobs = null;
    }

    /* ----------------------------------------------------------- familles */

    /**
     * Familles de métiers, dans l'ordre de la mosaïque.
     *
     * @return array<string, array{key:string, name:string, intro:string, tone:string, icon:string}>
     */
    public static function families(): array
    {
        static $families = null;
        if ($families !== null) {
            return $families;
        }

        $families = [];
        $rows = (array) (Json::read(TradeRepository::seedDir() . '/familles.json')['families'] ?? []);
        foreach ($rows as $row) {
            $key = (string) ($row['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $families[$key] = [
                'key'   => $key,
                'name'  => (string) ($row['name'] ?? $key),
                'intro' => (string) ($row['intro'] ?? ''),
                'tone'  => (string) ($row['tone'] ?? 'coral'),
                'icon'  => (string) ($row['icon'] ?? 'star'),
            ];
        }
        return $families;
    }

    public static function family(string $key): array
    {
        return self::families()[$key]
            ?? ['key' => $key, 'name' => ucfirst($key), 'intro' => '', 'tone' => 'coral', 'icon' => 'star'];
    }

    /* ------------------------------------------------------------- fiches */

    /** @return array<int, array<string, mixed>> fiches publiées, ligne d'index */
    public static function published(): array
    {
        return array_values(array_filter(
            self::rows(),
            static fn(array $row) => ($row['status'] ?? '') === 'publish',
        ));
    }

    /**
     * Fiches publiées rangées par famille, dans l'ordre des familles.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function byFamily(): array
    {
        $out = array_fill_keys(array_keys(self::families()), []);
        foreach (self::published() as $row) {
            $out[(string) $row['family']][] = $row;
        }
        return array_filter($out);
    }

    /** Fiche complète d'après son adresse publique. */
    public static function findBySlug(string $slug): ?array
    {
        foreach (self::rows() as $row) {
            if ($row['slug'] === $slug) {
                return TradeRepository::find((string) $row['id']);
            }
        }
        return null;
    }

    /**
     * Adresse actuelle d'une fiche renommée. Changer le slug d'un métier ne
     * doit casser aucun lien, et surtout pas ceux que Google a indexés.
     */
    public static function currentSlug(string $former): string
    {
        foreach (self::rows() as $row) {
            if (in_array($former, (array) ($row['former_slugs'] ?? []), true)
                && ($row['status'] ?? '') === 'publish') {
                return (string) $row['slug'];
            }
        }
        return '';
    }

    /* ------------------------------------------------------- rapprochement */

    /** @return string[] mots-clés de rapprochement, nom et féminin en repli */
    public static function keywords(array $trade): array
    {
        $keywords = array_values(array_filter(array_map('trim', (array) ($trade['keywords'] ?? [])), 'strlen'));
        if ($keywords === []) {
            $keywords = array_values(array_filter([(string) ($trade['name'] ?? ''), (string) ($trade['name_f'] ?? '')]));
        }
        return $keywords;
    }

    /**
     * Expression régulière d'un jeu de mots-clés, à appliquer sur un texte
     * normalisé par Index::haystack().
     *
     * Chaque mot accepte son pluriel : « régisseurs son » est une offre de
     * régisseur son. Les féminins, eux, sont trop irréguliers pour une règle —
     * régisseuse, costumière, technicienne : ils figurent parmi les mots-clés.
     */
    public static function pattern(array $keywords): string
    {
        $alternatives = [];
        foreach ($keywords as $keyword) {
            $norm = Index::haystack([(string) $keyword]);
            if ($norm === '') {
                continue;
            }
            $words = array_map(
                static fn(string $w): string => preg_quote($w, '/') . (preg_match('/[sx]$/', $w) ? '' : 's?'),
                explode(' ', $norm),
            );
            $alternatives[] = implode(' ', $words);
        }
        if ($alternatives === []) {
            return '';
        }
        // Les plus longs d'abord : « chef électricien » avant « électricien ».
        usort($alternatives, static fn(string $a, string $b) => strlen($b) <=> strlen($a));
        return '/(?<![a-z0-9])(?:' . implode('|', array_unique($alternatives)) . ')(?![a-z0-9])/';
    }

    public static function titleMatches(string $pattern, string $title): bool
    {
        return $pattern !== '' && preg_match($pattern, Index::haystack([$title])) === 1;
    }

    /**
     * Annonces du site encore en ligne qui relèvent du métier.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function matchingJobs(array $trade, int $limit): array
    {
        $pattern = self::pattern(self::keywords($trade));
        if ($pattern === '') {
            return [];
        }
        $live = array_filter(
            self::liveJobs(),
            static fn(array $job) => self::titleMatches($pattern, (string) ($job['title'] ?? '')),
        );
        usort($live, static fn(array $a, array $b)
            => strcmp((string) ($b['published_at'] ?? ''), (string) ($a['published_at'] ?? '')));
        return array_slice(array_values($live), 0, $limit);
    }

    /**
     * Offres des partenaires pour ce métier, sans jamais appeler leur API.
     *
     * Deux viviers, lus en cache seulement : le flux propre au métier, que la
     * tâche planifiée rafraîchit à tour de rôle, et le flux général du site en
     * attendant. L'intitulé doit porter un mot-clé du métier : c'est un tri
     * plus sévère que le filtre sectoriel, qui laisse encore passer des
     * offres hors secteur.
     *
     * @param  array<int, array<string, mixed>> $local annonces du site déjà affichées
     * @return array<int, array<string, mixed>>
     */
    public static function partnerJobs(array $trade, int $limit, array $local = []): array
    {
        if ($limit <= 0 || !Aggregator::isEnabled()) {
            return [];
        }
        $pattern = self::pattern(self::keywords($trade));
        if ($pattern === '') {
            return [];
        }

        $pool = array_merge(
            Aggregator::fetch(self::partnerCriteria($trade), self::PARTNER_POOL, false, true),
            Aggregator::fetch([], 40, false, true),
        );

        $out = [];
        $seen = [];
        foreach ($pool as $job) {
            $id = (string) ($job['id'] ?? '');
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            if (self::titleMatches($pattern, (string) ($job['title'] ?? ''))) {
                $out[] = $job;
            }
        }

        $out = Aggregator::withoutLocalDuplicates($out, $local);
        usort($out, static fn(array $a, array $b)
            => strcmp((string) ($b['published_at'] ?? ''), (string) ($a['published_at'] ?? '')));
        return array_slice($out, 0, $limit);
    }

    /** Critères du flux partenaire d'un métier : identiques côté tâche et côté page, sinon le cache ne se retrouve pas. */
    public static function partnerCriteria(array $trade): array
    {
        $query = trim((string) ($trade['search'] ?? ''));
        if ($query === '') {
            $query = (string) (self::keywords($trade)[0] ?? '');
        }
        return ['q' => $query, 'city' => '', 'page' => 1];
    }

    /**
     * Profils de l'annuaire qui exercent ce métier.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function matchingProfiles(array $trade, int $limit): array
    {
        $pattern = self::pattern(self::keywords($trade));
        if ($pattern === '') {
            return [];
        }
        $out = [];
        foreach (Index::load('cv') as $cv) {
            if (($cv['status'] ?? '') === 'publish' && self::titleMatches($pattern, (string) ($cv['title'] ?? ''))) {
                $out[] = $cv;
                if (count($out) >= $limit) {
                    break;
                }
            }
        }
        return $out;
    }

    /**
     * Métiers voisins : ceux que la fiche désigne, complétés par sa famille.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function related(array $trade, int $limit): array
    {
        $bySlug = [];
        foreach (self::published() as $row) {
            $bySlug[(string) $row['slug']] = $row;
        }
        unset($bySlug[(string) $trade['slug']]);

        $out = [];
        foreach ((array) ($trade['related'] ?? []) as $slug) {
            if (isset($bySlug[$slug])) {
                $out[$slug] = $bySlug[$slug];
            }
        }
        foreach ($bySlug as $slug => $row) {
            if (count($out) >= $limit) {
                break;
            }
            if ($row['family'] === $trade['family'] && !isset($out[$slug])) {
                $out[$slug] = $row;
            }
        }
        return array_slice(array_values($out), 0, $limit);
    }

    /**
     * Annonces en ligne par métier, pour les pastilles de la mosaïque.
     *
     * @return array<string, int> slug => nombre
     */
    public static function jobCounts(): array
    {
        $titles = [];
        foreach (self::liveJobs() as $job) {
            $titles[] = Index::haystack([(string) ($job['title'] ?? '')]);
        }

        $counts = [];
        foreach (self::published() as $row) {
            $pattern = self::pattern(self::keywords($row));
            $n = 0;
            foreach ($titles as $title) {
                if ($pattern !== '' && preg_match($pattern, $title) === 1) {
                    $n++;
                }
            }
            $counts[(string) $row['slug']] = $n;
        }
        return $counts;
    }

    /* --------------------------------------------------------- rédaction */

    /** « 180 à 300 € brut par jour », ou rien si la fiche ne donne pas de chiffre. */
    public static function payLabel(array $pay): string
    {
        $min = (int) ($pay['min'] ?? 0);
        $max = (int) ($pay['max'] ?? 0);
        if ($min <= 0 && $max <= 0) {
            return '';
        }
        $money = static fn(int $n): string => number_format($n, 0, ',', "\u{202F}") . "\u{00A0}€";
        $range = $min > 0 && $max > $min
            ? $money($min) . ' à ' . $money($max)
            : $money(max($min, $max));
        $unit = self::UNITS[(string) ($pay['unit'] ?? '')] ?? '';
        return trim($range . ' brut ' . $unit);
    }

    /** Durée ISO de l'unité, vide si elle n'en a pas (cachet, prestation). */
    public static function payDuration(array $pay): string
    {
        return self::DURATIONS[(string) ($pay['unit'] ?? '')] ?? '';
    }

    /** « de » ou « d’ » devant le nom du métier : « offres d’accessoiriste ». */
    public static function de(string $name): string
    {
        $first = substr(Index::haystack([$name]), 0, 1);
        return in_array($first, ['a', 'e', 'i', 'o', 'u', 'y', 'h'], true) ? 'd’' : 'de ';
    }

    /**
     * Minuscule initiale dans le fil d'une phrase, sauf pour un sigle :
     * « un régisseur son », mais « un DJ ».
     */
    public static function inline(string $name): string
    {
        $first = strtok($name, ' ') ?: $name;
        if (mb_strtoupper($first) === $first) {
            return $name;
        }
        return mb_strtolower(mb_substr($name, 0, 1)) . mb_substr($name, 1);
    }

    /* ---------------------------------------------------- tâche planifiée */

    /**
     * Rafraîchit le flux partenaire de quelques métiers, à tour de rôle.
     *
     * Soixante métiers fois quatre partenaires, c'est trop pour un seul
     * passage et pour les quotas gratuits : un ou deux métiers par demi-heure
     * suffisent à tenir chaque fiche à jour d'un jour sur l'autre.
     */
    public static function refreshPartnerFeeds(int $count): string
    {
        if ($count <= 0 || !Aggregator::isEnabled()) {
            return '';
        }
        $rows = self::published();
        if ($rows === []) {
            return '';
        }

        $file = Config::path('data') . '/private/trades-sources.json';
        $cursor = (int) (Json::read($file)['cursor'] ?? 0);
        $names = [];
        $total = count($rows);

        for ($i = 0; $i < min($count, $total); $i++) {
            $row = $rows[($cursor + $i) % $total];
            $trade = TradeRepository::find((string) $row['id']) ?? $row;
            Aggregator::fetch(self::partnerCriteria($trade), self::PARTNER_POOL, true);
            $names[] = (string) $row['name'];
        }

        Json::write($file, ['cursor' => ($cursor + count($names)) % $total, 'at' => date('c')]);
        return 'flux partenaires rafraîchis : ' . implode(', ', $names);
    }
}
