<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\Config;
use App\Storage\Json;
use App\Storage\Repository;
use App\Storage\Schema;

/**
 * Fiches métiers : une par fichier dans data/trades/.
 *
 * Deux sources, qu'il ne faut pas confondre.
 *
 *  • Les fiches d'origine, écrites avec le code dans bin/content/metiers/.
 *    Elles voyagent avec chaque déploiement.
 *  • Les fiches en service, dans data/trades/, que l'exploitant modifie depuis
 *    le back-office. Elles ne quittent jamais le serveur.
 *
 * Un déploiement n'écrase donc jamais une correction faite en ligne : il ne
 * fait qu'ajouter les métiers nouveaux. Et une fiche supprimée au back-office
 * ne ressuscite pas au déploiement suivant — le registre des amorçages garde
 * la trace de ce qui a déjà été proposé.
 */
final class TradeRepository extends Repository
{
    protected static function dir(): string
    {
        return self::dataPath('trades');
    }

    protected static function type(): string
    {
        return 'trade';
    }

    /** Dossier des fiches d'origine, versionnées avec le code. */
    public static function seedDir(): string
    {
        return Config::path('root') . '/bin/content/metiers';
    }

    /**
     * Fiches d'origine, indexées par slug.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function seeds(): array
    {
        static $seeds = null;
        if ($seeds !== null) {
            return $seeds;
        }

        $seeds = [];
        foreach (glob(self::seedDir() . '/[0-9]*.json') ?: [] as $file) {
            $data = Json::read($file);
            $family = (string) ($data['family'] ?? '');
            foreach ((array) ($data['trades'] ?? []) as $trade) {
                $slug = slugify((string) ($trade['slug'] ?? $trade['name'] ?? ''));
                if ($slug === '') {
                    continue;
                }
                $trade['slug'] = $slug;
                $trade['family'] = (string) ($trade['family'] ?? $family);
                $seeds[$slug] = $trade;
            }
        }
        return $seeds;
    }

    /** Fiche d'origine d'un métier, pour la restaurer après une fausse manœuvre. */
    public static function seed(string $id): ?array
    {
        return self::seeds()[$id] ?? null;
    }

    /**
     * Crée les fiches d'origine qui n'ont encore jamais été proposées.
     *
     * @return int nombre de fiches créées
     */
    public static function seedMissing(): int
    {
        $registry = self::dataPath('private') . '/trades-seeded.json';
        $state = Json::read($registry, ['slugs' => []]);
        $seeded = (array) ($state['slugs'] ?? []);
        $before = count($seeded);
        $created = 0;

        foreach (self::seeds() as $slug => $seed) {
            if (isset($seeded[$slug])) {
                continue;
            }
            if (self::find($slug) === null) {
                $record = $seed;
                $record['id'] = $slug;
                $record['status'] = (string) ($seed['status'] ?? 'publish');
                $record['published_at'] = date('c');
                $record['seed_hash'] = self::hash($seed);
                self::save($record);
                $created++;
            }
            $seeded[$slug] = date('c');
        }

        if (count($seeded) !== $before) {
            Json::write($registry, ['slugs' => $seeded]);
        }
        return $created;
    }

    /**
     * Remet le texte d'origine sur une fiche, sans toucher à ce qui n'est pas
     * du texte : son adresse, son état de publication, ses anciennes adresses.
     */
    public static function restore(string $id): ?array
    {
        $record = self::find($id);
        $seed = self::seed($id);
        if ($record === null || $seed === null) {
            return null;
        }

        $keep = array_intersect_key($record, array_flip([
            'id', 'slug', 'status', 'former_slugs', 'created_at', 'published_at',
        ]));
        $restored = $keep + $seed;
        $restored['seo'] = ['title' => '', 'description' => ''];
        $restored['seed_hash'] = self::hash($seed);

        return self::save($restored) ? self::find($id) : null;
    }

    /** La fiche en service diffère-t-elle encore de son texte d'origine ? */
    public static function isModified(array $record): bool
    {
        $seed = self::seed((string) ($record['id'] ?? ''));
        if ($seed === null) {
            return true;   // métier ajouté au back-office
        }
        // Mêmes valeurs par défaut des deux côtés : un champ absent de la
        // fiche d'origine ne doit pas passer pour une modification.
        $seed = Schema::upgrade($seed, 'trade');
        foreach (self::CONTENT_FIELDS as $field) {
            if (json_encode($record[$field] ?? null) !== json_encode($seed[$field] ?? null)) {
                return true;
            }
        }
        return false;
    }

    /** Champs de texte, ceux que la restauration remet d'origine. */
    public const CONTENT_FIELDS = [
        'name', 'name_f', 'family', 'rome', 'summary', 'intro', 'missions', 'day', 'skills',
        'training', 'schools', 'statut', 'pay', 'career', 'faq', 'brief', 'related',
        'keywords', 'search',
    ];

    private static function hash(array $seed): string
    {
        return substr(sha1((string) json_encode($seed)), 0, 12);
    }
}
