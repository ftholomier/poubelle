<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Domain\TradeRepository;
use App\Services\Sources\Sector;
use App\Storage\Index;
use App\Storage\Json;

/**
 * Les métiers : familles, fiches, et ce qui les relie au reste du site.
 *
 * Une fiche métier ne vaut que par ce qu'elle ouvre : les annonces du moment,
 * les profils disponibles, les métiers voisins. Le rapprochement se fait sur
 * les mots-clés de la fiche, cherchés dans l'intitulé seul — le texte d'une
 * annonce parle de tout, son intitulé dit ce qu'elle est.
 *
 * Un intitulé revient au métier le plus précis qu'il nomme : « monteur de
 * stands » n'est pas une offre de monteur vidéo, ni « monteur son » une offre
 * de monteur tout court. Voir owners().
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
        'creation'   => 'par création',
        'heure'      => 'de l’heure',
    ];

    /** Durée ISO 8601 de chaque unité, pour les données structurées. */
    private const DURATIONS = ['jour' => 'P1D', 'semaine' => 'P1W', 'mois' => 'P1M', 'heure' => 'PT1H'];

    /** Lus une fois par requête : une fiche s'en sert pour cinq blocs. */
    private static ?array $rows = null;
    private static ?array $liveJobs = null;
    /** @var array<string, string>|null motif de chaque fiche publiée, par slug */
    private static ?array $patterns = null;
    /** @var array<string, string[]> premier mot d'un mot-clé => fiches qui l'emploient */
    private static array $heads = [];
    /** @var array<string, string[]> intitulé normalisé => métiers qu'il désigne */
    private static array $owners = [];

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
        self::$patterns = null;
        self::$heads = [];
        self::$owners = [];
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
     * L'écriture inclusive passe aussi : « chargé(e) de production ».
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
            // La terminaison inclusive ne compte qu'entre deux mots : après le
            // dernier, elle ne change rien à la trouvaille.
            $alternatives[] = implode(Sector::INCLUSIF . ' ', $words);
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

    /** @return array<string, string> motif de chaque fiche publiée, par slug */
    private static function patterns(): array
    {
        if (self::$patterns === null) {
            self::$patterns = [];
            self::$heads = [];
            foreach (self::published() as $row) {
                $slug = (string) $row['slug'];
                $keywords = self::keywords($row);
                $pattern = self::pattern($keywords);
                if ($pattern === '') {
                    continue;
                }
                self::$patterns[$slug] = $pattern;
                foreach ($keywords as $keyword) {
                    $head = self::head(strtok(Index::haystack([(string) $keyword]), ' ') ?: '');
                    if ($head !== '') {
                        self::$heads[$head][$slug] = $slug;
                    }
                }
            }
        }
        return self::$patterns;
    }

    /** Un mot sans sa marque de pluriel : « régisseurs » et « régisseur » se rangent ensemble. */
    private static function head(string $word): string
    {
        return rtrim($word, 's');
    }

    /**
     * Métiers que désigne un intitulé.
     *
     * Chaque fiche y cherche ses mots-clés ; une trouvaille que chevauche celle,
     * plus longue, d'une autre fiche ne compte pas. « Chef monteur de stands »
     * revient au monteur de stands, pas au monteur vidéo, quand « réalisateur
     * monteur » nomme deux métiers et revient aux deux. À longueur égale, les
     * deux fiches gardent l'offre : « maquilleuse coiffeuse ».
     *
     * @return string[] slugs
     */
    public static function owners(string $title): array
    {
        $haystack = Index::haystack([$title]);
        if ($haystack === '') {
            return [];
        }
        if (!isset(self::$owners[$haystack])) {
            if (count(self::$owners) > 5000) {
                self::$owners = [];
            }
            // Un mot-clé ne peut se trouver que là où figure son premier mot :
            // seules ces fiches passent l'intitulé au crible.
            $patterns = self::patterns();
            $candidates = [];
            foreach (explode(' ', $haystack) as $word) {
                foreach (self::$heads[self::head($word)] ?? [] as $slug) {
                    $candidates[$slug] = $patterns[$slug];
                }
            }
            self::$owners[$haystack] = self::ownersAmong($candidates, $haystack);
        }
        return self::$owners[$haystack];
    }

    /**
     * @param  array<string, string> $patterns slug => motif
     * @return string[]
     */
    private static function ownersAmong(array $patterns, string $haystack): array
    {
        $spans = [];
        foreach ($patterns as $slug => $pattern) {
            if (preg_match_all($pattern, $haystack, $found, PREG_OFFSET_CAPTURE) > 0) {
                foreach ($found[0] as [$text, $start]) {
                    $spans[(string) $slug][] = [$start, $start + strlen($text)];
                }
            }
        }

        $owners = [];
        foreach ($spans as $slug => $mine) {
            foreach ($mine as [$start, $end]) {
                if (!self::outmatched((string) $slug, $start, $end, $spans)) {
                    $owners[] = (string) $slug;
                    break;
                }
            }
        }
        return $owners;
    }

    /** Une autre fiche a-t-elle trouvé plus long à cet endroit ? */
    private static function outmatched(string $slug, int $start, int $end, array $spans): bool
    {
        foreach ($spans as $other => $theirs) {
            if ((string) $other === $slug) {
                continue;
            }
            foreach ($theirs as [$s, $e]) {
                if ($s < $end && $start < $e && ($e - $s) > ($end - $start)) {
                    return true;
                }
            }
        }
        return false;
    }

    /** L'intitulé relève-t-il de ce métier, et non d'un métier plus précis ? */
    private static function belongs(array $trade, string $pattern, string $title): bool
    {
        if (!self::titleMatches($pattern, $title)) {
            return false;
        }
        $slug = (string) $trade['slug'];
        $patterns = self::patterns();
        if (($patterns[$slug] ?? null) === $pattern) {
            return in_array($slug, self::owners($title), true);
        }
        // Brouillon, ou fiche tout juste retouchée : on la mesure aux autres
        // telle qu'elle est, sans toucher au cache des fiches publiées.
        $patterns[$slug] = $pattern;
        return in_array($slug, self::ownersAmong($patterns, Index::haystack([$title])), true);
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
            static fn(array $job) => self::belongs($trade, $pattern, (string) ($job['title'] ?? '')),
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
     * attendant. L'intitulé doit porter un mot-clé du métier, et passer seul
     * le filtre sectoriel : le résumé d'une offre d'usine peut parler de
     * « production » et de « son » équipe, son intitulé ne ment pas. Les
     * termes bannis au back-office s'appliquent ici aussi.
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

        $exclude = Aggregator::exclude();
        $out = [];
        $seen = [];
        foreach ($pool as $job) {
            $id = (string) ($job['id'] ?? '');
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $title = (string) ($job['title'] ?? '');
            if (self::belongs($trade, $pattern, $title)
                && Sector::matches($title)
                && !Sector::excluded($exclude, $title)) {
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
            if (($cv['status'] ?? '') === 'publish' && self::belongs($trade, $pattern, (string) ($cv['title'] ?? ''))) {
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
        $counts = array_fill_keys(array_map(static fn(array $row) => (string) $row['slug'], self::published()), 0);
        foreach (self::liveJobs() as $job) {
            foreach (self::owners((string) ($job['title'] ?? '')) as $slug) {
                $counts[$slug]++;
            }
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
