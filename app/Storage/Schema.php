<?php
declare(strict_types=1);

namespace App\Storage;

use App\Core\Config;

/**
 * Schéma versionné. Deux niveaux, complémentaires :
 *
 *  1. migrate()  — au démarrage, une seule fois par version, idempotent et non destructif.
 *  2. upgrade()  — à chaque lecture, comble les champs manquants en mémoire.
 *
 * Conséquence : un fichier écrit par une version antérieure du code reste lisible,
 * et un champ inconnu écrit par une version postérieure est simplement conservé.
 */
final class Schema
{
    /** Champs par défaut, par type d'entité. Un champ absent prend cette valeur. */
    private const DEFAULTS = [
        'job' => [
            'schema' => 3, 'id' => '', 'slug' => '', 'title' => '', 'status' => 'draft',
            'description' => '', 'requirements' => [], 'conditions' => '',
            'company' => ['name' => '', 'slug' => '', 'website' => '', 'tagline' => '', 'description' => '', 'logo' => ''],
            'location' => ['city' => '', 'region' => '', 'country' => '', 'remote' => false],
            'salary' => '', 'contract' => [], 'category' => [], 'tags' => [],
            'starts_at' => '', 'expires_at' => '', 'filled' => false, 'featured' => false,
            'apply' => ['email' => '', 'url' => ''],
            'author_id' => 0, 'views' => 0,
            'created_at' => '', 'updated_at' => '', 'published_at' => '',
            'legacy_id' => 0,
        ],
        'cv' => [
            'schema' => 3, 'id' => '', 'slug' => '', 'status' => 'draft',
            'name' => '', 'title' => '', 'summary' => '',
            'location' => ['city' => '', 'region' => '', 'country' => ''],
            'experience_years' => 0, 'mobility' => '',
            'skills' => [], 'experiences' => [], 'education' => [], 'links' => [],
            'file' => ['path' => '', 'name' => '', 'size' => 0, 'legacy_url' => ''],
            'photo' => ['path' => '', 'legacy_url' => ''],
            'contact' => ['email' => '', 'phone' => '', 'public' => false],
            'available' => true, 'listed' => true, 'featured' => false,
            'author_id' => 0, 'views' => 0,
            'created_at' => '', 'updated_at' => '', 'published_at' => '',
            'legacy_id' => 0,
        ],
        'employer' => [
            'schema' => 3, 'id' => '', 'slug' => '', 'name' => '', 'kind' => '',
            'tagline' => '', 'description' => '', 'website' => '', 'logo' => ['path' => '', 'legacy_url' => ''],
            'location' => ['city' => '', 'region' => ''],
            'social' => ['facebook' => '', 'twitter' => '', 'linkedin' => '', 'google' => ''],
            'job_count' => 0, 'user_id' => 0,
            'created_at' => '', 'updated_at' => '', 'legacy_id' => 0,
        ],
        'page' => [
            'schema' => 3, 'id' => '', 'slug' => '', 'title' => '', 'status' => 'draft',
            'excerpt' => '', 'body' => '', 'lang' => 'fr', 'source_hash' => '',
            'translated' => false, 'menu' => false,
            'seo' => ['title' => '', 'description' => ''],
            'created_at' => '', 'updated_at' => '', 'published_at' => '',
            'revision' => 1, 'legacy_id' => 0,
        ],
        'user' => [
            'schema' => 3, 'id' => 0, 'email' => '', 'login' => '', 'display_name' => '',
            'first_name' => '', 'last_name' => '', 'role' => 'candidate',
            'password' => '', 'password_legacy' => '', 'must_reset' => false,
            'company' => ['name' => '', 'slug' => '', 'website' => '', 'tagline' => '', 'description' => '', 'logo' => ''],
            'locale' => 'fr', 'active' => true, 'two_factor' => ['enabled' => false, 'secret' => ''],
            'created_at' => '', 'last_login_at' => '', 'legacy_id' => 0,
        ],
    ];

    public static function version(): int
    {
        return (int) Config::get('storage.schema', 3);
    }

    public static function defaults(string $type): array
    {
        return self::DEFAULTS[$type] ?? [];
    }

    /**
     * Complète un enregistrement lu sur disque. Ne jette jamais.
     * Les clés inconnues sont conservées telles quelles.
     */
    public static function upgrade(array $record, string $type): array
    {
        $defaults = self::DEFAULTS[$type] ?? [];
        $out = $record;

        foreach ($defaults as $key => $default) {
            if (!array_key_exists($key, $out) || $out[$key] === null) {
                $out[$key] = $default;
                continue;
            }
            // Sous-objet : on comble champ par champ sans écraser l'existant.
            if (is_array($default) && self::isAssoc($default)) {
                $out[$key] = is_array($out[$key]) ? $out[$key] + $default : $default;
            } elseif (is_array($default) && !is_array($out[$key])) {
                $out[$key] = $out[$key] === '' ? [] : [$out[$key]];
            }
        }

        $out['schema'] = self::version();
        return $out;
    }

    /** Migrations globales, exécutées au plus une fois par version. */
    public static function migrate(): void
    {
        $stateFile = Config::path('data') . '/private/schema.json';
        $state = Json::read($stateFile, ['version' => 0, 'applied' => []]);
        $from = (int) ($state['version'] ?? 0);
        $to = self::version();

        if ($from >= $to) {
            return;
        }

        // Chaque migration est idempotente : la relancer ne casse rien.
        for ($v = $from + 1; $v <= $to; $v++) {
            $method = 'toV' . $v;
            if (method_exists(self::class, $method)) {
                self::$method();
            }
            $state['applied'][] = ['version' => $v, 'at' => date('c')];
        }

        $state['version'] = $to;
        Json::write($stateFile, $state);
        Audit::log('schema.migrated', ['from' => $from, 'to' => $to]);
    }

    /** v1 : création de l'arborescence de données. */
    private static function toV1(): void
    {
        $base = Config::path('data');
        // Ces dossiers ne sont pas versionnés (données personnelles) :
        // ils sont recréés au premier démarrage après un clone.
        foreach (['content/fr', 'jobs', 'cv', 'employers', 'users', 'index', 'i18n',
                  'uploads/cv', 'uploads/photo', 'uploads/logo', 'backups',
                  'logs', 'logs/mail', 'private/auth', 'private/locks', 'private/ai'] as $dir) {
            if (!is_dir($base . '/' . $dir)) {
                @mkdir($base . '/' . $dir, 0775, true);
            }
        }
    }

    /** v2 : un dossier de contenu par langue servie. */
    private static function toV2(): void
    {
        $base = Config::path('data') . '/content';
        foreach (array_keys((array) Config::get('i18n.languages', [])) as $lang) {
            if (!is_dir($base . '/' . $lang)) {
                @mkdir($base . '/' . $lang, 0775, true);
            }
        }
    }

    /** v3 : index de recherche séparés par entité. */
    private static function toV3(): void
    {
        $dir = Config::path('data') . '/index';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }

    private static function isAssoc(array $array): bool
    {
        return $array !== [] && array_keys($array) !== range(0, count($array) - 1);
    }
}
