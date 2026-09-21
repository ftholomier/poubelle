#!/usr/bin/env php
<?php
/**
 * Reprise de l'ancien site WordPress vers le stockage JSON.
 *
 *   php bin/import-wordpress.php --dump=/chemin/export.sql [options]
 *
 *   --uploads=/chemin/wp-content/uploads   rattache les fichiers présents
 *   --with-applications                    importe aussi les candidatures (RGPD : exclu par défaut)
 *   --keep-spam                            n'écarte pas le spam d'octobre 2025
 *   --dry-run                              compte sans écrire
 *
 * Le script est réexécutable : il réécrit les fiches à l'identique.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/lib/SqlDump.php';
require __DIR__ . '/lib/Wp.php';

use App\Core\Config;
use App\Domain\CvRepository;
use App\Domain\EmployerRepository;
use App\Domain\JobRepository;
use App\Domain\PageRepository;
use App\Domain\UserRepository;
use App\Storage\Index;
use App\Storage\Json;
use Bin\SqlDump;
use Bin\Wp;

$options = getopt('', ['dump:', 'uploads::', 'with-applications', 'keep-spam', 'dry-run']);
if (!isset($options['dump'])) {
    fwrite(STDERR, "Usage : php bin/import-wordpress.php --dump=/chemin/export.sql [--uploads=…] [--dry-run]\n");
    exit(1);
}

$dumpPath   = (string) $options['dump'];
$uploadsDir = isset($options['uploads']) ? rtrim((string) $options['uploads'], '/') : '';
$withApps   = isset($options['with-applications']);
$keepSpam   = isset($options['keep-spam']);
$dryRun     = isset($options['dry-run']);

$dump = new SqlDump($dumpPath);
$t0 = microtime(true);

echo "→ Passe 1/2 : articles, taxonomies, comptes\n";

$posts = $terms = $taxonomies = $users = [];
$wantedTypes = ['job_listing' => 1, 'resume' => 1, 'page' => 1, 'attachment' => 1];
if ($withApps) {
    $wantedTypes['job_application'] = 1;
}

foreach ($dump->rows(['wp_posts', 'wp_terms', 'wp_term_taxonomy', 'wp_users']) as [$table, $row]) {
    switch ($table) {
        case 'wp_posts':
            if (isset($wantedTypes[(string) $row['post_type']])) {
                $posts[(int) $row['ID']] = $row;
            }
            break;
        case 'wp_terms':
            $terms[(int) $row['term_id']] = ['name' => (string) $row['name'], 'slug' => (string) $row['slug']];
            break;
        case 'wp_term_taxonomy':
            $taxonomies[(int) $row['term_taxonomy_id']] = [
                'term_id'  => (int) $row['term_id'],
                'taxonomy' => (string) $row['taxonomy'],
            ];
            break;
        case 'wp_users':
            $users[(int) $row['ID']] = $row;
            break;
    }
}
printf("   %d articles · %d termes · %d comptes\n", count($posts), count($terms), count($users));

echo "→ Passe 2/2 : métadonnées et rattachements\n";

$postMeta = $userMeta = $postTerms = [];
foreach ($dump->rows(['wp_postmeta', 'wp_usermeta', 'wp_term_relationships']) as [$table, $row]) {
    switch ($table) {
        case 'wp_postmeta':
            $id = (int) $row['post_id'];
            if (isset($posts[$id])) {
                $postMeta[$id][(string) $row['meta_key']] = $row['meta_value'];
            }
            break;
        case 'wp_usermeta':
            $id = (int) $row['user_id'];
            if (isset($users[$id])) {
                $userMeta[$id][(string) $row['meta_key']] = $row['meta_value'];
            }
            break;
        case 'wp_term_relationships':
            $id = (int) $row['object_id'];
            $tt = $taxonomies[(int) $row['term_taxonomy_id']] ?? null;
            if ($tt !== null && isset($posts[$id]) && isset($terms[$tt['term_id']])) {
                $postTerms[$id][$tt['taxonomy']][] = $terms[$tt['term_id']]['name'];
            }
            break;
    }
}
printf("   métadonnées sur %d articles et %d comptes\n", count($postMeta), count($userMeta));

/* ------------------------------------------------------------------ outils */

$meta = static fn(int $id, string $key, string $default = ''): string
    => trim((string) ($postMeta[$id][$key] ?? $default));

$umeta = static fn(int $id, string $key, string $default = ''): string
    => trim((string) ($userMeta[$id][$key] ?? $default));

$taxOf = static fn(int $id, string $taxonomy): array
    => array_values(array_unique($postTerms[$id][$taxonomy] ?? []));

/** Retrouve un fichier de l'ancien site dans l'archive uploads fournie. */
$localFile = static function (string $legacyUrl) use ($uploadsDir): string {
    if ($uploadsDir === '' || $legacyUrl === '') {
        return '';
    }
    $path = parse_url($legacyUrl, PHP_URL_PATH) ?: $legacyUrl;
    $pos = strpos($path, '/uploads/');
    $relative = $pos === false ? ltrim($path, '/') : substr($path, $pos + 9);
    foreach ([$uploadsDir . '/' . $relative, $uploadsDir . '/' . basename($relative)] as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }
    return '';
};

$copied = 0;
/** Copie un fichier d'origine dans data/uploads et renvoie son chemin relatif. */
$adoptFile = static function (string $legacyUrl, string $kind, string $id)
        use ($localFile, $dryRun, &$copied): string {
    $source = $localFile($legacyUrl);
    if ($source === '') {
        return '';
    }
    $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION)) ?: 'bin';
    $relative = $kind . '/' . $id . '.' . $extension;
    $target = Config::path('data') . '/uploads/' . $relative;

    if (!$dryRun) {
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0775, true);
        }
        if (!is_file($target) || filesize($target) !== filesize($source)) {
            copy($source, $target);
        }
    }
    $copied++;
    return $relative;
};

/* ------------------------------------------------------- 1. les employeurs */

echo "→ Employeurs\n";

$employers = [];   // slug => fiche
$employerSlugOf = static function (string $name) use (&$employers): string {
    $slug = slugify($name);
    return $slug !== '' && isset($employers[$slug]) ? $slug : $slug;
};

/** Fusionne une source d'information sur un employeur dans la fiche existante. */
$mergeEmployer = static function (string $name, array $fields) use (&$employers): string {
    $name = trim($name);
    if ($name === '') {
        return '';
    }
    $slug = slugify($name);
    if ($slug === '') {
        return '';
    }
    if (!isset($employers[$slug])) {
        $employers[$slug] = [
            'schema' => 3, 'id' => $slug, 'slug' => $slug, 'name' => $name,
            'kind' => '', 'tagline' => '', 'description' => '', 'website' => '',
            'logo' => ['path' => '', 'legacy_url' => ''],
            'location' => ['city' => '', 'region' => ''],
            'social' => ['facebook' => '', 'twitter' => '', 'linkedin' => '', 'google' => ''],
            'job_count' => 0, 'user_id' => 0, 'legacy_id' => 0,
            'created_at' => '', 'updated_at' => '',
        ];
    }
    // On ne remplace que ce qui est vide : la première source renseignée gagne.
    foreach ($fields as $key => $value) {
        if (is_array($value)) {
            foreach ($value as $sub => $subValue) {
                if (($employers[$slug][$key][$sub] ?? '') === '' && $subValue !== '') {
                    $employers[$slug][$key][$sub] = $subValue;
                }
            }
        } elseif (($employers[$slug][$key] ?? '') === '' && $value !== '') {
            $employers[$slug][$key] = $value;
        }
    }
    return $slug;
};

/* ------------------------------------------------------------ 2. les offres */

echo "→ Offres d'emploi\n";

$jobs = [];
$skippedSpam = 0;

foreach ($posts as $id => $post) {
    if ((string) $post['post_type'] !== 'job_listing') {
        continue;
    }
    $title = trim((string) $post['post_title']);
    $status = (string) $post['post_status'];

    if (!$keepSpam && $status === 'draft' && Wp::isSpamTitle($title)) {
        $skippedSpam++;
        continue;
    }
    if ($title === '') {
        continue;
    }

    $companyName = $meta($id, '_company_name');
    $companySlug = $mergeEmployer($companyName, [
        'tagline'     => $meta($id, '_company_tagline'),
        'description' => Wp::toText($meta($id, '_company_description')),
        'website'     => Wp::url($meta($id, '_company_website')),
        'social'      => [
            'facebook' => Wp::url($meta($id, '_company_facebook')),
            'twitter'  => Wp::url($meta($id, '_company_twitter')),
            'linkedin' => Wp::url($meta($id, '_company_linkedin')),
            'google'   => Wp::url($meta($id, '_company_google')),
        ],
        'location'    => Wp::parseLocation($meta($id, '_job_location')),
        'legacy_id'   => 0,
    ]);

    $location = Wp::parseLocation($meta($id, '_job_location'));
    $regions = $taxOf($id, 'job_listing_region');
    if ($regions !== []) {
        $location['region'] = (string) $regions[0];
    }

    $body = Wp::cleanHtml((string) $post['post_content']);
    $sections = splitJobBody($body);

    $jobs[] = [
        'schema'      => 3,
        'id'          => 'j' . $id,
        'slug'        => (string) ($post['post_name'] ?: slugify($title)),
        'title'       => $title,
        'status'      => match ($status) {
            'publish' => 'publish',
            'expired' => 'expired',
            default   => 'draft',
        },
        'description' => $sections['description'],
        'requirements'=> $sections['requirements'],
        'conditions'  => $sections['conditions'],
        'company'     => [
            'name'        => $companyName,
            'slug'        => $companySlug,
            'website'     => Wp::url($meta($id, '_company_website')),
            'tagline'     => $meta($id, '_company_tagline'),
            'description' => Wp::toText($meta($id, '_company_description')),
            'logo'        => '',
        ],
        'location'    => $location + ['remote' => Wp::bool($meta($id, '_remote_position'))],
        'salary'      => '',
        'contract'    => $taxOf($id, 'job_listing_type'),
        'category'    => $taxOf($id, 'job_listing_category'),
        'tags'        => $taxOf($id, 'job_listing_tag'),
        'starts_at'   => '',
        'expires_at'  => Wp::date($meta($id, '_job_expires')),
        'filled'      => Wp::bool($meta($id, '_filled')),
        'featured'    => Wp::bool($meta($id, '_featured')),
        'apply'       => [
            'email' => Wp::email($meta($id, '_application')),
            'url'   => str_contains($meta($id, '_application'), 'http') ? Wp::url($meta($id, '_application')) : '',
        ],
        'author_id'   => (int) $post['post_author'],
        'views'       => (int) $meta($id, 'iawp_total_views', '0'),
        'created_at'  => Wp::date($post['post_date']),
        'updated_at'  => Wp::date($post['post_modified']),
        'published_at'=> $status === 'publish' || $status === 'expired' ? Wp::date($post['post_date']) : '',
        'legacy_id'   => $id,
    ];
}
printf("   %d offres retenues (%d pourriels écartés)\n", count($jobs), $skippedSpam);

/* --------------------------------------------------------------- 3. les CV */

echo "→ CV\n";

$cvs = [];
foreach ($posts as $id => $post) {
    if ((string) $post['post_type'] !== 'resume') {
        continue;
    }
    $name = $meta($id, '_candidate_name') ?: trim((string) $post['post_title']);
    if ($name === '') {
        continue;
    }

    $summaryHtml = $meta($id, '_resume_content') ?: (string) $post['post_content'];
    $summary = Wp::toText($summaryHtml);

    // La taxonomie resume_region du site actuel est inexploitable (un seul terme
    // pour les 137 CV) : on reconstruit la géo depuis le champ libre.
    $location = Wp::parseLocation($meta($id, '_candidate_location'));

    // Compétences : taxonomie WordPress + champ libre, dédoublonnés sans tenir
    // compte de la casse (« Montage » et « montage » sont la même compétence).
    $skills = [];
    $rawSkills = array_merge($taxOf($id, 'resume_skill'), [$meta($id, '_resume_skills')]);
    foreach (Wp::splitSkills(implode(', ', array_filter($rawSkills))) as $skill) {
        $skills[mb_strtolower($skill)] ??= $skill;
    }
    $skills = array_values($skills);

    $experiences = [];
    foreach (Wp::unserialize($postMeta[$id]['_candidate_experience'] ?? '') as $row) {
        if (!is_array($row)) {
            continue;
        }
        $experiences[] = [
            'employer' => trim((string) ($row['employer'] ?? '')),
            'role'     => trim((string) ($row['job_title'] ?? '')),
            'period'   => trim((string) ($row['date'] ?? '')),
            'notes'    => Wp::toText((string) ($row['notes'] ?? '')),
        ];
    }

    $education = [];
    foreach (Wp::unserialize($postMeta[$id]['_candidate_education'] ?? '') as $row) {
        if (!is_array($row)) {
            continue;
        }
        $education[] = [
            'school'  => trim((string) ($row['location'] ?? '')),
            'degree'  => trim((string) ($row['qualification'] ?? '')),
            'period'  => trim((string) ($row['date'] ?? '')),
            'notes'   => Wp::toText((string) ($row['notes'] ?? '')),
        ];
    }

    $links = [];
    foreach (Wp::unserialize($postMeta[$id]['_links'] ?? '') as $row) {
        if (is_array($row) && Wp::url((string) ($row['url'] ?? '')) !== '') {
            $links[] = ['name' => trim((string) ($row['name'] ?? 'Lien')), 'url' => Wp::url((string) $row['url'])];
        }
    }

    $status = (string) $post['post_status'] === 'publish' ? 'publish' : 'draft';
    $fileUrl = $meta($id, '_resume_file');
    $photoUrl = $meta($id, '_candidate_photo');
    $cvId = 'c' . $id;

    $cvs[] = [
        'schema'   => 3,
        'id'       => $cvId,
        'slug'     => (string) ($post['post_name'] ?: slugify($name)),
        'status'   => $status,
        'name'     => $name,
        'title'    => $meta($id, '_candidate_title'),
        'summary'  => $summary,
        'location' => $location,
        'experience_years' => Wp::guessYears($summary . ' ' . implode(' ', array_column($experiences, 'period'))),
        'mobility' => '',
        'skills'   => $skills,
        'experiences' => $experiences,
        'education'   => $education,
        'links'       => $links,
        'file'  => [
            'path' => $adoptFile($fileUrl, 'cv', $cvId),
            'name' => $fileUrl !== '' ? basename((string) parse_url($fileUrl, PHP_URL_PATH)) : '',
            'size' => 0,
            'legacy_url' => $fileUrl,
        ],
        'photo' => [
            'path' => $adoptFile($photoUrl, 'photo', $cvId),
            'legacy_url' => $photoUrl,
        ],
        // Les coordonnées restent masquées tant que la personne ne les a pas rendues publiques.
        'contact' => ['email' => Wp::email($meta($id, '_candidate_email')), 'phone' => '', 'public' => false],
        'available' => true,
        'listed'    => $status === 'publish',
        'featured'  => Wp::bool($meta($id, '_featured')),
        'author_id' => (int) $post['post_author'],
        'views'     => (int) $meta($id, 'iawp_total_views', '0'),
        'created_at'   => Wp::date($post['post_date']),
        'updated_at'   => Wp::date($post['post_modified']),
        'published_at' => $status === 'publish' ? Wp::date($post['post_date']) : '',
        'legacy_id' => $id,
    ];
}
printf("   %d CV retenus\n", count($cvs));

/* ----------------------------------------------------------- 4. les comptes */

echo "→ Comptes\n";

$importedUsers = [];
$skippedUsers = 0;
foreach ($users as $id => $user) {
    $login = (string) $user['user_login'];
    if (!$keepSpam && Wp::isSpamTitle($login)) {
        $skippedUsers++;
        continue;
    }
    $email = Wp::email($user['user_email']);
    if ($email === '') {
        $skippedUsers++;
        continue;
    }

    $capabilities = (string) ($userMeta[$id]['wp_capabilities'] ?? '');
    $role = match (true) {
        str_contains($capabilities, 'administrator') => 'admin',
        str_contains($capabilities, 'employer')      => 'employer',
        str_contains($capabilities, 'candidate')     => 'candidate',
        default                                      => 'candidate',
    };

    $companyName = $umeta($id, '_company_name');
    if ($companyName !== '' && $role === 'employer') {
        $mergeEmployer($companyName, [
            'tagline'     => $umeta($id, '_company_tagline'),
            'description' => Wp::toText($umeta($id, '_company_description')),
            'website'     => Wp::url($umeta($id, '_company_website')),
            'social'      => [
                'facebook' => Wp::url($umeta($id, '_company_facebook')),
                'twitter'  => Wp::url($umeta($id, '_company_twitter')),
                'linkedin' => Wp::url($umeta($id, '_company_linkedin')),
            ],
            'user_id'     => $id,
        ]);
    }

    $importedUsers[] = [
        'schema' => 3,
        'id'     => $id,
        'email'  => $email,
        'login'  => $login,
        'display_name' => (string) ($user['display_name'] ?: $login),
        'first_name'   => $umeta($id, 'first_name'),
        'last_name'    => $umeta($id, 'last_name'),
        'role'   => $role,
        // Le hash WordPress est conservé tel quel ; il sera converti en Argon2id
        // à la première connexion réussie (voir App\Services\Auth).
        'password'        => '',
        'password_legacy' => (string) $user['user_pass'],
        'must_reset'      => false,
        'company' => [
            'name'        => $companyName,
            'slug'        => $companyName !== '' ? slugify($companyName) : '',
            'website'     => Wp::url($umeta($id, '_company_website')),
            'tagline'     => $umeta($id, '_company_tagline'),
            'description' => Wp::toText($umeta($id, '_company_description')),
            'logo'        => '',
        ],
        'locale' => 'fr',
        'active' => true,
        'two_factor' => ['enabled' => false, 'secret' => ''],
        'created_at'    => Wp::date($user['user_registered']),
        'last_login_at' => '',
        'legacy_id'     => $id,
    ];
}
printf("   %d comptes retenus (%d écartés)\n", count($importedUsers), $skippedUsers);

/* ------------------------------------------------------------- 5. les pages */

echo "→ Pages éditoriales\n";

$pages = [];
foreach ($posts as $id => $post) {
    if ((string) $post['post_type'] !== 'page' || (string) $post['post_status'] !== 'publish') {
        continue;
    }
    $body = Wp::cleanHtml((string) $post['post_content']);
    // Les pages WP Job Manager ne contiennent qu'un shortcode : rien à reprendre.
    if (mb_strlen(Wp::toText($body)) < 200) {
        continue;
    }
    $title = trim((string) $post['post_title']);
    $pages[] = [
        'schema' => 3,
        'id'     => (string) ($post['post_name'] ?: slugify($title)),
        'slug'   => (string) ($post['post_name'] ?: slugify($title)),
        'title'  => $title,
        'status' => 'publish',
        'excerpt'=> str_excerpt(Wp::toText($body), 220),
        'body'   => $body,
        'lang'   => 'fr',
        'menu'   => true,
        'seo'    => ['title' => $title, 'description' => str_excerpt(Wp::toText($body), 155)],
        'created_at'   => Wp::date($post['post_date']),
        'updated_at'   => Wp::date($post['post_modified']),
        'published_at' => Wp::date($post['post_date']),
        'revision'     => 1,
        'legacy_id'    => $id,
    ];
}
printf("   %d pages retenues\n", count($pages));

/* --------------------------------------------------- 6. écriture sur disque */

if ($dryRun) {
    printf("\n[simulation] rien n'a été écrit. %.1f s\n", microtime(true) - $t0);
    exit(0);
}

echo "→ Écriture\n";

foreach ($jobs as $job) {
    JobRepository::save($job);
}
foreach ($cvs as $cv) {
    CvRepository::save($cv);
}
foreach ($importedUsers as $user) {
    UserRepository::save($user);
}
foreach ($pages as $page) {
    PageRepository::save($page, 'fr');
}
foreach ($employers as $employer) {
    EmployerRepository::save($employer);
}

UserRepository::reindex();
$report = Index::rebuildAll();

printf("   %d offres · %d CV · %d employeurs · %d comptes · %d pages\n",
    count($jobs), count($cvs), count($employers), count($importedUsers), count($pages));
printf("   %d fichier(s) repris depuis l'archive uploads\n", $copied);
printf("   index : %s\n", json_encode($report, JSON_UNESCAPED_UNICODE));
printf("\nTerminé en %.1f s.\n", microtime(true) - $t0);

/**
 * Découpe le corps d'une annonce en « Le poste » / « Profil recherché » / « Conditions ».
 * Les anciennes annonces n'ont pas de structure : on s'appuie sur les intertitres,
 * et à défaut tout part dans la description.
 *
 * @return array{description:string,requirements:array<int,string>,conditions:string}
 */
function splitJobBody(string $html): array
{
    $text = Wp::toText($html);
    $out = ['description' => $text, 'requirements' => [], 'conditions' => ''];

    $profilePattern = '/\n\s*(profil\s+(?:recherch[ée]|souhait[ée])?|comp[ée]tences?\s+requises?|'
                    . 'nous\s+recherchons|vous\s+[êe]tes)\s*:?\s*\n/iu';
    $conditionsPattern = '/\n\s*(conditions?|r[ée]mun[ée]ration|contrat|modalit[ée]s)\s*:?\s*\n/iu';

    if (preg_match($profilePattern, $text, $m, PREG_OFFSET_CAPTURE)) {
        $cut = (int) $m[0][1];
        $out['description'] = trim(substr($text, 0, $cut));
        $rest = substr($text, $cut + strlen($m[0][0]));

        if (preg_match($conditionsPattern, $rest, $m2, PREG_OFFSET_CAPTURE)) {
            $cut2 = (int) $m2[0][1];
            $out['requirements'] = Wp::toBullets(substr($rest, 0, $cut2));
            $out['conditions'] = trim(substr($rest, $cut2 + strlen($m2[0][0])));
        } else {
            $out['requirements'] = Wp::toBullets($rest);
        }
    } elseif (preg_match($conditionsPattern, $text, $m, PREG_OFFSET_CAPTURE)) {
        $cut = (int) $m[0][1];
        $out['description'] = trim(substr($text, 0, $cut));
        $out['conditions'] = trim(substr($text, $cut + strlen($m[0][0])));
    }

    return $out;
}
