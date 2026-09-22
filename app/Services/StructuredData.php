<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

/**
 * Données structurées schema.org, en JSON-LD.
 *
 * Pour un site d'emploi, `JobPosting` est le premier levier de visibilité :
 * c'est lui qui ouvre Google for Jobs, où les offres s'affichent avant les
 * résultats classiques. Google exige une date de fin, d'où la durée de vie
 * posée sur chaque annonce.
 *
 * Aucune donnée personnelle n'entre ici : ni adresse e-mail d'employeur, ni
 * coordonnées de candidat. Le balisage décrit ce qui est déjà visible.
 */
final class StructuredData
{
    /** Correspondance entre les contrats du site et le vocabulaire schema.org. */
    private const EMPLOYMENT_TYPES = [
        'cdi'           => 'FULL_TIME',
        'cdd'           => 'TEMPORARY',
        'cdd d’usage'   => 'TEMPORARY',
        "cdd d'usage"   => 'TEMPORARY',
        'intermittent'  => 'TEMPORARY',
        'cachet'        => 'PER_DIEM',
        'stage'         => 'INTERN',
        'alternance'    => 'INTERN',
        'temps partiel' => 'PART_TIME',
        'temps plein'   => 'FULL_TIME',
        'bénévolat'     => 'VOLUNTEER',
        'freelance'     => 'CONTRACTOR',
    ];

    /** Fiche d'offre : le balisage qui ouvre Google for Jobs. */
    public static function jobPosting(array $job, ?array $employer = null): array
    {
        $base = rtrim((string) Config::get('site.url'), '/');
        $posted = (string) ($job['published_at'] ?: $job['created_at']);
        $expires = (string) ($job['expires_at'] ?? '');
        if ($expires === '') {
            $expires = JobLifecycle::expiresAt($job);
        }

        $data = [
            '@context'    => 'https://schema.org',
            '@type'       => 'JobPosting',
            'title'       => (string) $job['title'],
            'description' => self::htmlDescription($job),
            'identifier'  => [
                '@type' => 'PropertyValue',
                'name'  => (string) Config::get('site.name'),
                'value' => (string) $job['id'],
            ],
            'datePosted'  => self::date($posted),
            'validThrough'=> self::date($expires),
            'url'         => $base . I18n::url('/offre/' . $job['slug'], 'fr'),
            'directApply' => trim((string) ($job['apply']['email'] ?? '')) !== '',
            'hiringOrganization' => [
                '@type' => 'Organization',
                'name'  => (string) ($job['company']['name'] ?: Config::get('site.name')),
            ],
        ];

        $website = (string) ($employer['website'] ?? $job['company']['website'] ?? '');
        if ($website !== '') {
            $data['hiringOrganization']['sameAs'] = $website;
        }
        if ($employer !== null && ($employer['slug'] ?? '') !== '') {
            $data['hiringOrganization']['url'] = $base . I18n::url('/employeur/' . $employer['slug'], 'fr');
        }

        $city = trim((string) ($job['location']['city'] ?? ''));
        $region = trim((string) ($job['location']['region'] ?? ''));
        if ($city !== '' || $region !== '') {
            $data['jobLocation'] = [
                '@type'   => 'Place',
                'address' => array_filter([
                    '@type'           => 'PostalAddress',
                    'addressLocality' => $city,
                    'addressRegion'   => $region,
                    'addressCountry'  => 'FR',
                ], static fn($v) => $v !== ''),
            ];
        }
        if (!empty($job['location']['remote'])) {
            $data['jobLocationType'] = 'TELECOMMUTE';
            $data['applicantLocationRequirements'] = ['@type' => 'Country', 'name' => 'France'];
        }

        $types = [];
        foreach ((array) ($job['contract'] ?? []) as $contract) {
            $needle = mb_strtolower(trim((string) $contract));
            foreach (self::EMPLOYMENT_TYPES as $word => $type) {
                if ($needle !== '' && str_starts_with($needle, $word)) {
                    $types[$type] = true;
                    break;
                }
            }
        }
        if ($types !== []) {
            $data['employmentType'] = array_keys($types);
        }

        // La rémunération n'est balisée que si elle est chiffrée : annoncer
        // « à négocier » en baseSalary ferait rejeter la fiche.
        $salary = trim((string) ($job['salary'] ?? ''));
        if ($salary !== '' && preg_match('/\d/', $salary) === 1) {
            $data['baseSalary'] = [
                '@type' => 'MonetaryAmount',
                'currency' => 'EUR',
                'value' => ['@type' => 'QuantitativeValue', 'unitText' => 'MONTH', 'name' => $salary],
            ];
        }

        return $data;
    }

    /** Fiche d'annuaire : une personne et son métier, sans coordonnées. */
    public static function person(array $cv): array
    {
        $base = rtrim((string) Config::get('site.url'), '/');
        $data = [
            '@context'  => 'https://schema.org',
            '@type'     => 'ProfilePage',
            'url'       => $base . I18n::url('/cv/' . $cv['slug'], 'fr'),
            'mainEntity'=> array_filter([
                '@type'      => 'Person',
                'name'       => (string) $cv['name'],
                'jobTitle'   => (string) $cv['title'],
                'description'=> str_excerpt((string) $cv['summary'], 200),
            ], static fn($v) => $v !== ''),
        ];

        $city = trim((string) ($cv['location']['city'] ?? ''));
        if ($city !== '') {
            $data['mainEntity']['address'] = [
                '@type' => 'PostalAddress',
                'addressLocality' => $city,
                'addressCountry'  => 'FR',
            ];
        }
        return $data;
    }

    public static function organization(array $employer): array
    {
        $base = rtrim((string) Config::get('site.url'), '/');
        return array_filter([
            '@context' => 'https://schema.org',
            '@type'    => 'Organization',
            'name'     => (string) $employer['name'],
            'url'      => $base . I18n::url('/employeur/' . $employer['slug'], 'fr'),
            'sameAs'   => (string) ($employer['website'] ?? ''),
            'description' => str_excerpt((string) ($employer['description'] ?: $employer['tagline']), 200),
        ], static fn($v) => $v !== '' && $v !== []);
    }

    /** Identité du site + recherche interne, posées sur l'accueil. */
    public static function site(): array
    {
        $base = rtrim((string) Config::get('site.url'), '/');
        $name = (string) Config::get('site.name');

        return [
            '@context' => 'https://schema.org',
            '@graph'   => [
                [
                    '@type'       => 'Organization',
                    '@id'         => $base . '/#organization',
                    'name'        => $name,
                    'url'         => $base . '/',
                    'description' => (string) Config::get('site.baseline'),
                    'logo'        => $base . '/assets/img/og-default.png',
                    'foundingDate'=> (string) Config::get('site.since', '2005'),
                ],
                [
                    '@type'     => 'WebSite',
                    '@id'       => $base . '/#website',
                    'name'      => $name,
                    'url'       => $base . '/',
                    'inLanguage'=> 'fr-FR',
                    'publisher' => ['@id' => $base . '/#organization'],
                    'potentialAction' => [
                        '@type'  => 'SearchAction',
                        'target' => [
                            '@type'       => 'EntryPoint',
                            'urlTemplate' => $base . '/fr/offres?q={search_term_string}',
                        ],
                        'query-input' => 'required name=search_term_string',
                    ],
                ],
            ],
        ];
    }

    /**
     * Fil d'Ariane. Les libellés sont ceux que le visiteur lit, pas des
     * identifiants internes.
     *
     * @param array<int, array{0:string,1:string}> $trail  [libellé, chemin]
     */
    public static function breadcrumb(array $trail): array
    {
        $base = rtrim((string) Config::get('site.url'), '/');
        $items = [];
        foreach (array_values($trail) as $position => [$label, $path]) {
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $position + 1,
                'name'     => $label,
                'item'     => $base . (str_starts_with($path, 'http') ? '' : I18n::url($path)),
            ];
        }
        return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items];
    }

    /**
     * Google attend une description en HTML simple : on reconstruit des
     * paragraphes à partir du texte brut stocké.
     */
    private static function htmlDescription(array $job): string
    {
        $parts = [(string) $job['description']];
        if (!empty($job['requirements'])) {
            $parts[] = '<h3>Profil recherché</h3><ul><li>'
                     . implode('</li><li>', array_map(
                         static fn($line) => e((string) $line),
                         (array) $job['requirements'],
                     ))
                     . '</li></ul>';
        }
        if (trim((string) ($job['conditions'] ?? '')) !== '') {
            $parts[] = '<h3>Conditions</h3>' . self::paragraphs((string) $job['conditions']);
        }

        return self::paragraphs($parts[0]) . implode('', array_slice($parts, 1));
    }

    private static function paragraphs(string $text): string
    {
        $out = '';
        foreach (preg_split('/\n\s*\n/', $text) ?: [] as $block) {
            $block = trim($block);
            if ($block !== '') {
                $out .= '<p>' . nl2br(e($block)) . '</p>';
            }
        }
        return $out;
    }

    private static function date(string $value): string
    {
        $timestamp = strtotime($value);
        return $timestamp === false ? date('c') : date('c', $timestamp);
    }

    /** Bloc <script> prêt à poser dans la page. */
    public static function script(array $data, string $nonce = ''): string
    {
        if ($data === []) {
            return '';
        }
        $json = json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE,
        );
        if ($json === false) {
            return '';
        }
        // Une balise fermante dans une chaîne casserait le script : on la neutralise.
        $json = str_replace('</', '<\/', $json);

        return '<script type="application/ld+json"'
             . ($nonce !== '' ? ' nonce="' . e($nonce) . '"' : '')
             . '>' . $json . '</script>';
    }
}
