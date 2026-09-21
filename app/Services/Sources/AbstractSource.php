<?php
declare(strict_types=1);

namespace App\Services\Sources;

use App\Core\Config;
use App\Storage\Audit;

/** Outils communs aux adaptateurs : normalisation et garde-fous. */
abstract class AbstractSource implements JobSource
{
    public function key(): string
    {
        return strtolower(str_replace(' ', '-', $this->name()));
    }

    /**
     * Met une offre externe à la forme attendue par les gabarits.
     *
     * @param array{id:string,title:string,company?:string,city?:string,region?:string,
     *              salary?:string,contract?:array,tags?:array,excerpt?:string,
     *              published_at?:string,url:string,remote?:bool} $fields
     */
    protected function normalize(array $fields): array
    {
        $title = trim((string) $fields['title']);
        $url = trim((string) $fields['url']);
        if ($title === '' || !preg_match('#^https?://#i', $url)) {
            return [];
        }

        return [
            'id'           => 'ext:' . $this->key() . ':' . substr(sha1($url), 0, 12),
            'slug'         => '',                 // pas de page locale
            'title'        => mb_substr($title, 0, 180),
            'status'       => 'publish',
            'company'      => mb_substr(trim((string) ($fields['company'] ?? '')), 0, 120),
            'company_slug' => '',
            'city'         => mb_substr(trim((string) ($fields['city'] ?? '')), 0, 80),
            'region'       => mb_substr(trim((string) ($fields['region'] ?? '')), 0, 80),
            'remote'       => (bool) ($fields['remote'] ?? false),
            'salary'       => mb_substr(trim((string) ($fields['salary'] ?? '')), 0, 90),
            'contract'     => array_slice(array_values((array) ($fields['contract'] ?? [])), 0, 3),
            'category'     => [],
            'tags'         => array_slice(array_values((array) ($fields['tags'] ?? [])), 0, 4),
            'excerpt'      => str_excerpt((string) ($fields['excerpt'] ?? ''), 180),
            'published_at' => (string) ($fields['published_at'] ?? ''),
            'expires_at'   => '',
            'filled'       => false,
            'featured'     => false,

            // Ce qui distingue une offre externe d'une annonce déposée ici.
            'external'     => true,
            'source'       => $this->name(),
            'url'          => $url,
        ];
    }

    /** Date ISO à partir d'un format quelconque ; chaîne vide si illisible. */
    protected function date(mixed $value): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }
        $timestamp = is_numeric($raw) ? (int) $raw : strtotime($raw);
        return $timestamp === false || $timestamp <= 0 ? '' : date('c', $timestamp);
    }

    /** Nettoie un extrait fourni en HTML par une API tierce. */
    protected function text(mixed $value): string
    {
        $text = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    protected function setting(string $path, mixed $default = null): mixed
    {
        return Config::get('sources.' . $path, $default);
    }

    protected function fail(string $reason, array $context = []): array
    {
        Audit::log('source.failed', ['source' => $this->key(), 'reason' => $reason] + $context);
        return [];
    }
}
