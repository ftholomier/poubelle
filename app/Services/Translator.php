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

    /** Raison du dernier refus de l'API, pour l'afficher au back-office. */
    private static string $lastError = '';

    /**
     * Un refus de configuration ne se répare pas en réessayant : clé invalide,
     * API non activée, facturation absente, quota épuisé. Sans ce coupe-circuit,
     * un lot de traduction enchaîne des centaines d'appels tous voués à
     * échouer, et l'écran d'administration attend pour rien.
     */
    private static bool $blocked = false;

    /** Codes pour lesquels réessayer ne sert à rien dans la même requête. */
    private const FATAL = [400, 401, 403, 429];

    public static function available(): bool
    {
        return Config::has('translate_api_key');
    }

    /** Vide tant qu'aucun appel n'a échoué. */
    public static function lastError(): string
    {
        return self::$lastError;
    }

    /** Vrai après un refus de configuration : les appels suivants sont sautés. */
    public static function blocked(): bool
    {
        return self::$blocked;
    }

    /** Après correction de la clé depuis le back-office. */
    public static function reset(): void
    {
        self::$lastError = '';
        self::$blocked = false;
    }

    /**
     * Un appel qui échoue ne doit pas disparaître en silence : sans le message
     * de Google, « 0 fiche traduite » est indiscernable de « tout est à jour ».
     *
     * @return array la charge utile, vide en cas d'échec
     */
    private static function post(array $payload, int $timeout): array
    {
        if (self::$blocked) {
            return [];
        }

        $call = Http::call('POST',
            self::ENDPOINT . '?key=' . urlencode((string) Config::secret('translate_api_key')),
            ['json' => $payload, 'timeout' => $timeout],
        );

        if ($call['ok']) {
            self::$lastError = '';
            return $call['data'];
        }

        self::$lastError = $call['error'];
        if (in_array($call['status'], self::FATAL, true)) {
            self::$blocked = true;
        }
        // Le journal garde la raison, jamais la clé ni le texte envoyé.
        Audit::log('translate.failed', [
            'target' => $payload['target'] ?? '',
            'count'  => count((array) ($payload['q'] ?? [])),
            'status' => $call['status'],
            'error'  => mb_substr($call['error'], 0, 300),
        ]);
        return [];
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
            $response = self::post([
                'q'      => array_values($chunk),
                'source' => 'fr',
                'target' => $target,
                'format' => 'text',
            ], 30);

            $translations = $response['data']['translations'] ?? null;
            if (!is_array($translations)) {
                if (self::$lastError === '') {
                    self::$lastError = 'réponse inattendue de l’API';
                }
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

        $response = self::post([
            'q'      => [$html],
            'source' => 'fr',
            'target' => $target,
            'format' => 'html',
        ], 45);

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
