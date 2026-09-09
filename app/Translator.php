<?php
declare(strict_types=1);

namespace App;

/**
 * Traduction assistée (Google Cloud Translation v2) déclenchée au back-office.
 * Le résultat est écrit en brouillon : rien n'est publié sans relecture.
 */
final class Translator
{
    public static function configured(): bool
    {
        return Config::has('GOOGLE_TRANSLATE_KEY');
    }

    /**
     * @param array<int,string> $texts
     * @return array{ok:bool,texts?:array<int,string>,error?:string}
     */
    public static function translate(array $texts, string $target, string $source = Config::DEFAULT_LANG): array
    {
        $texts = array_values(array_map('strval', $texts));
        if ($texts === []) {
            return ['ok' => true, 'texts' => []];
        }
        if (!self::configured()) {
            return ['ok' => false, 'error' => 'Clé Google Translate absente : renseignez-la dans Réglages → Clés API.'];
        }

        $out = [];
        // L'API limite la taille des requêtes : on envoie par paquets de 40 segments.
        foreach (array_chunk($texts, 40) as $chunk) {
            $response = Http::postJson(
                'https://translation.googleapis.com/language/translate/v2?key=' . rawurlencode((string) Config::get('GOOGLE_TRANSLATE_KEY')),
                ['q' => $chunk, 'source' => $source, 'target' => $target, 'format' => 'html'],
                ['timeout' => 15, 'headers' => ['Referer' => Config::apiReferer()]]
            );
            if (!$response['ok'] || $response['json'] === null) {
                Log::write('translate', 'Échec (' . $response['status'] . ') ' . substr($response['body'], 0, 300));
                // Le message de Google dit précisément ce qui bloque (clé, quota,
                // référent) : on le remonte au back-office plutôt qu'un code nu.
                $detail = trim((string) ($response['json']['error']['message'] ?? ''));
                return [
                    'ok' => false,
                    'error' => $detail !== ''
                        ? 'Google : ' . mb_substr($detail, 0, 220)
                        : 'Google Translate a renvoyé une erreur (' . $response['status'] . ').',
                ];
            }
            foreach ((array) ($response['json']['data']['translations'] ?? []) as $item) {
                $out[] = html_entity_decode((string) ($item['translatedText'] ?? ''), ENT_QUOTES, 'UTF-8');
            }
        }

        if (\count($out) !== \count($texts)) {
            return ['ok' => false, 'error' => 'Réponse incomplète de Google Translate.'];
        }
        return ['ok' => true, 'texts' => $out];
    }

    /**
     * Traduit récursivement les chaînes d'une structure, en laissant intactes
     * les clés techniques (identifiants, chemins, couleurs…).
     */
    public static function translateTree(array $tree, string $target, array $skipKeys = []): array
    {
        $paths = [];
        $texts = [];
        $collect = static function (array $node, string $prefix) use (&$collect, &$paths, &$texts, $skipKeys): void {
            foreach ($node as $key => $value) {
                $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
                if (\in_array((string) $key, $skipKeys, true)) {
                    continue;
                }
                if (\is_array($value)) {
                    $collect($value, $path);
                } elseif (\is_string($value) && trim($value) !== '' && !preg_match('#^(/|https?://|#[0-9a-f]{3,8}$)#i', trim($value))) {
                    $paths[] = $path;
                    $texts[] = $value;
                }
            }
        };
        $collect($tree, '');

        $result = self::translate($texts, $target);
        if (!($result['ok'] ?? false)) {
            return ['ok' => false, 'error' => $result['error'] ?? 'Traduction impossible.'];
        }

        $out = $tree;
        foreach ($paths as $i => $path) {
            $ref = &$out;
            foreach (explode('.', $path) as $segment) {
                $ref = &$ref[$segment];
            }
            $ref = $result['texts'][$i] ?? $ref;
            unset($ref);
        }
        return ['ok' => true, 'tree' => $out];
    }
}
