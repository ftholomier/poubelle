<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\Config;
use App\Storage\Json;
use App\Storage\Lock;
use App\Storage\Schema;

/**
 * Pages éditoriales, une par langue : /data/content/{lang}/{slug}.json
 * Le français est la source ; les autres langues sont des traductions en cache.
 */
final class PageRepository
{
    private static function dir(string $lang): string
    {
        return Config::path('data') . '/content/' . preg_replace('/[^a-z]/', '', $lang);
    }

    private static function path(string $lang, string $slug): string
    {
        return self::dir($lang) . '/' . slugify($slug) . '.json';
    }

    public static function find(string $slug, string $lang = 'fr'): ?array
    {
        $data = Json::read(self::path($lang, $slug));

        // Traduction absente : on sert le français, signalé comme non traduit.
        if ($data === [] && $lang !== 'fr') {
            $data = Json::read(self::path('fr', $slug));
            if ($data !== []) {
                $data['lang'] = $lang;
                $data['translated'] = false;
                $data['fallback'] = true;
            }
        }

        return $data === [] ? null : Schema::upgrade($data, 'page');
    }

    public static function all(string $lang = 'fr'): array
    {
        $out = [];
        foreach (Json::listFiles(self::dir($lang)) as $file) {
            $data = Json::read($file);
            if ($data !== []) {
                $out[] = Schema::upgrade($data, 'page');
            }
        }
        usort($out, static fn(array $a, array $b) => strcmp((string) $a['title'], (string) $b['title']));
        return $out;
    }

    public static function published(string $lang = 'fr'): array
    {
        return array_values(array_filter(self::all($lang), static fn(array $p) => ($p['status'] ?? '') === 'publish'));
    }

    public static function save(array $page, string $lang = 'fr'): bool
    {
        $page = Schema::upgrade($page, 'page');
        $page['lang'] = $lang;
        $page['updated_at'] = date('c');
        if (($page['created_at'] ?? '') === '') {
            $page['created_at'] = $page['updated_at'];
        }
        if (($page['id'] ?? '') === '') {
            $page['id'] = $page['slug'];
        }

        // Empreinte de la source FR : sert à marquer les traductions obsolètes.
        if ($lang === 'fr') {
            $page['source_hash'] = substr(sha1((string) $page['title'] . '|' . (string) $page['body']), 0, 16);
            $page['translated'] = false;
        }

        $slug = (string) $page['slug'];
        return Lock::transaction('page:' . $lang . ':' . $slug,
            static fn(): bool => Json::write(self::path($lang, $slug), $page));
    }

    public static function delete(string $slug, string $lang): bool
    {
        return Json::delete(self::path($lang, $slug));
    }

    /**
     * État de traduction d'une page, pour la carte « Traductions » du back-office :
     * source / à jour / obsolète / manquant.
     */
    public static function translationStates(string $slug): array
    {
        $source = self::find($slug, 'fr');
        $hash = (string) ($source['source_hash'] ?? '');
        $states = [];

        foreach ((array) Config::get('i18n.languages', []) as $code => $meta) {
            if ($code === 'fr') {
                $states[$code] = ['code' => $code, 'name' => $meta['name'], 'state' => 'source'];
                continue;
            }
            $translated = Json::read(self::path($code, $slug));
            $states[$code] = [
                'code'  => $code,
                'name'  => $meta['name'],
                'state' => match (true) {
                    $translated === [] => 'missing',
                    ($translated['source_hash'] ?? '') === $hash && $hash !== '' => 'fresh',
                    default => 'stale',
                },
            ];
        }
        return $states;
    }
}
