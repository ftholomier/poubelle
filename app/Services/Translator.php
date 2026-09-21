<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Storage\Audit;

/**
 * Google Cloud Translation, appelé côté serveur uniquement.
 * Sans clé d'API, toutes les méthodes renvoient une valeur vide et l'appelant
 * retombe sur le français : le site reste entièrement fonctionnel.
 */
final class Translator
{
    private const ENDPOINT = 'https://translation.googleapis.com/language/translate/v2';
    private const BATCH = 100;   // limite de segments par appel

    public static function available(): bool
    {
        return Config::has('translate_api_key');
    }

    /**
     * Traduit une liste de chaînes depuis le français.
     *
     * @param  string[] $strings
     * @return string[] même ordre, vide si l'API est indisponible
     */
    public static function translate(array $strings, string $target): array
    {
        if (!self::available() || $strings === [] || $target === 'fr') {
            return [];
        }

        $out = [];
        foreach (array_chunk($strings, self::BATCH) as $chunk) {
            $response = Http::json('POST', self::ENDPOINT . '?key=' . urlencode((string) Config::secret('translate_api_key')), [
                'json' => [
                    'q'      => array_values($chunk),
                    'source' => 'fr',
                    'target' => $target,
                    'format' => 'text',
                ],
                'timeout' => 30,
            ]);

            $translations = $response['data']['translations'] ?? null;
            if (!is_array($translations)) {
                Audit::log('translate.failed', ['target' => $target, 'count' => count($chunk)]);
                return [];
            }
            foreach ($translations as $entry) {
                $out[] = html_entity_decode((string) ($entry['translatedText'] ?? ''), ENT_QUOTES, 'UTF-8');
            }
        }
        return $out;
    }

    /** Traduit un fragment HTML en préservant les balises. */
    public static function translateHtml(string $html, string $target): string
    {
        if (!self::available() || trim($html) === '' || $target === 'fr') {
            return '';
        }

        $response = Http::json('POST', self::ENDPOINT . '?key=' . urlencode((string) Config::secret('translate_api_key')), [
            'json' => [
                'q'      => [$html],
                'source' => 'fr',
                'target' => $target,
                'format' => 'html',
            ],
            'timeout' => 45,
        ]);

        $text = $response['data']['translations'][0]['translatedText'] ?? '';
        return is_string($text) ? html_entity_decode($text, ENT_QUOTES, 'UTF-8') : '';
    }

    /**
     * Traduit une page complète et renvoie la version cible, prête à être stockée.
     * Renvoie null si la traduction échoue — la page FR reste servie.
     */
    public static function translatePage(array $page, string $target): ?array
    {
        if (!self::available()) {
            return null;
        }

        $body = self::translateHtml((string) ($page['body'] ?? ''), $target);
        $short = self::translate([
            (string) ($page['title'] ?? ''),
            (string) ($page['excerpt'] ?? ''),
            (string) ($page['seo']['title'] ?? ''),
            (string) ($page['seo']['description'] ?? ''),
        ], $target);

        if ($body === '' && $short === []) {
            return null;
        }

        $translated = $page;
        $translated['lang']       = $target;
        $translated['body']       = $body !== '' ? $body : $page['body'];
        $translated['title']      = $short[0] ?? $page['title'];
        $translated['excerpt']    = $short[1] ?? $page['excerpt'];
        $translated['seo']        = [
            'title'       => $short[2] ?? ($page['seo']['title'] ?? ''),
            'description' => $short[3] ?? ($page['seo']['description'] ?? ''),
        ];
        $translated['translated'] = true;
        // L'empreinte de la source permet de détecter l'obsolescence au prochain enregistrement FR.
        $translated['source_hash'] = (string) ($page['source_hash'] ?? '');

        return $translated;
    }
}
