<?php
declare(strict_types=1);

namespace App;

use App\Ai\Gemini;

/**
 * Tests des intégrations depuis le back-office.
 *
 * Chaque test fait un vrai appel — le plus petit possible — avec la clé
 * réellement enregistrée, et renvoie une réponse lisible : ce qui a marché,
 * ce qui a échoué et pourquoi. Aucun test n'écrit dans le contenu du site.
 */
final class Diagnostics
{
    /** Intégrations testables, dans l'ordre d'affichage. */
    public const TARGETS = ['gemini', 'places', 'translate', 'mail'];

    public static function label(string $target): string
    {
        return match ($target) {
            'gemini' => 'Assistant Gemini',
            'places' => 'Avis Google (Places)',
            'translate' => 'Traduction Google',
            'mail' => 'Envoi d’emails',
            default => $target,
        };
    }

    /** Clés lues par ce test, pour l'affichage au back-office. */
    public static function keysOf(string $target): array
    {
        return match ($target) {
            'gemini' => ['GEMINI_API_KEY', 'GEMINI_MODEL'],
            'places' => ['GOOGLE_PLACES_KEY', 'GOOGLE_PLACE_ID'],
            'translate' => ['GOOGLE_TRANSLATE_KEY'],
            'mail' => ['MAIL_FROM', 'MAIL_TO', 'SMTP_HOST', 'SMTP_PORT', 'SMTP_SECURE', 'SMTP_USER', 'SMTP_PASS'],
            default => [],
        };
    }

    /** true si l'intégration a de quoi être testée. */
    public static function configured(string $target): bool
    {
        return match ($target) {
            'gemini' => Config::has('GEMINI_API_KEY'),
            'places' => Config::has('GOOGLE_PLACES_KEY'),
            'translate' => Config::has('GOOGLE_TRANSLATE_KEY'),
            'mail' => true, // mail() de l'hébergeur par défaut, SMTP si configuré
            default => false,
        };
    }

    /**
     * @return array{target:string,ok:bool,detail:string,ms:int,at:string}
     */
    public static function run(string $target): array
    {
        $started = microtime(true);
        $result = match ($target) {
            'gemini' => self::gemini(),
            'places' => self::places(),
            'translate' => self::translate(),
            'mail' => self::mail(),
            default => ['ok' => false, 'detail' => 'Test inconnu.'],
        };

        $out = [
            'target' => $target,
            'ok' => (bool) $result['ok'],
            'detail' => (string) $result['detail'],
            'ms' => (int) round((microtime(true) - $started) * 1000),
            'at' => (new \DateTimeImmutable())->format(\DATE_ATOM),
        ];
        Log::write('diagnostics', $target . ' : ' . ($out['ok'] ? 'OK' : 'ÉCHEC') . ' (' . $out['ms'] . ' ms) ' . $out['detail']);
        return $out;
    }

    /** Une vraie génération, la plus courte possible, sur le modèle sélectionné. */
    private static function gemini(): array
    {
        if (!Config::has('GEMINI_API_KEY')) {
            return ['ok' => false, 'detail' => 'Aucune clé API Gemini enregistrée.'];
        }

        $model = Gemini::model();
        $response = Http::postJson(
            'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent',
            [
                'contents' => [['role' => 'user', 'parts' => [['text' => 'Réponds exactement : OK']]]],
                'generationConfig' => ['temperature' => 0, 'maxOutputTokens' => 16],
            ],
            ['timeout' => 10, 'headers' => ['x-goog-api-key' => (string) Config::get('GEMINI_API_KEY')]]
        );

        if (!$response['ok'] || $response['json'] === null) {
            return ['ok' => false, 'detail' => self::googleError($response, 'Modèle « ' . $model . ' »')];
        }

        $text = '';
        foreach ((array) ($response['json']['candidates'][0]['content']['parts'] ?? []) as $part) {
            $text .= (string) ($part['text'] ?? '');
        }
        $text = trim($text);

        return $text === ''
            ? ['ok' => false, 'detail' => 'Le modèle « ' . $model . " » a répondu sans texte. Essayez un autre modèle dans la liste."]
            : ['ok' => true, 'detail' => 'Le modèle « ' . $model . ' » répond : « ' . mb_substr($text, 0, 60) . ' ».'];
    }

    /** Fiche configurée si elle existe, sinon simple validation de la clé. */
    private static function places(): array
    {
        if (!Config::has('GOOGLE_PLACES_KEY')) {
            return ['ok' => false, 'detail' => 'Aucune clé API Google Places enregistrée.'];
        }

        if (!Config::has('GOOGLE_PLACE_ID')) {
            $search = Reviews::searchPlaces('coworking Besançon', 1);
            return $search['ok']
                ? ['ok' => true, 'detail' => "La clé fonctionne. Il reste à choisir l'identifiant de la fiche ci-dessus."]
                : ['ok' => false, 'detail' => $search['error'] . self::remedy((string) $search['error'])];
        }

        $placeId = (string) Config::get('GOOGLE_PLACE_ID');
        $response = Http::getJson(
            'https://places.googleapis.com/v1/places/' . rawurlencode($placeId) . '?languageCode=' . Config::DEFAULT_LANG,
            ['timeout' => 10, 'headers' => [
                'X-Goog-Api-Key' => (string) Config::get('GOOGLE_PLACES_KEY'),
                'X-Goog-FieldMask' => 'id,displayName,formattedAddress,rating,userRatingCount',
            ]]
        );

        if (!$response['ok'] || $response['json'] === null) {
            return ['ok' => false, 'detail' => self::googleError($response, 'Fiche « ' . $placeId . ' »')];
        }

        $name = (string) ($response['json']['displayName']['text'] ?? $placeId);
        $rating = (float) ($response['json']['rating'] ?? 0);
        $count = (int) ($response['json']['userRatingCount'] ?? 0);
        return ['ok' => true, 'detail' => 'Fiche « ' . $name . ' »'
            . ($count > 0 ? ' — ' . number_format($rating, 1, ',', ' ') . '/5 sur ' . $count . ' avis.' : '.')];
    }

    /** Une traduction d'un mot suffit à valider la clé et le quota. */
    private static function translate(): array
    {
        if (!Config::has('GOOGLE_TRANSLATE_KEY')) {
            return ['ok' => false, 'detail' => 'Aucune clé API Google Translate enregistrée.'];
        }

        $source = 'Bonjour, bienvenue au iOiO.';
        $result = Translator::translate([$source], 'en');
        if (($result['ok'] ?? false) !== true) {
            $error = (string) ($result['error'] ?? 'Traduction refusée.');
            return ['ok' => false, 'detail' => $error . self::remedy($error)];
        }

        $first = trim((string) (($result['texts'] ?? [])[0] ?? ''));
        return $first === '' || $first === $source
            ? ['ok' => false, 'detail' => 'La clé n\'a rien traduit. Voir storage/logs/translate.log.']
            : ['ok' => true, 'detail' => '« ' . $source . ' » → « ' . mb_substr($first, 0, 70) . ' ».'];
    }

    /** Envoi réel d'un email de test vers la boîte configurée. */
    private static function mail(): array
    {
        $to = Mailer::inbox();
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'detail' => 'Aucune adresse de réception valide (MAIL_TO ou email de contact).'];
        }

        $via = Config::has('SMTP_HOST')
            ? 'SMTP ' . Config::get('SMTP_HOST') . ':' . (Config::get('SMTP_PORT') ?? '587')
              . ' (' . (Config::get('SMTP_SECURE') ?? 'tls') . ')'
            : 'fonction mail() de l\'hébergeur';

        $sent = Mailer::send(
            $to,
            'Test d\'envoi — back-office du iOiO',
            '<p>Cet email confirme que l\'envoi fonctionne depuis le back-office du site.</p>'
            . '<p class="muted">Voie utilisée : ' . Text::e($via) . '.<br>'
            . 'Expéditeur : ' . Text::e(Mailer::fromAddress()) . '.</p>'
        );

        return $sent
            ? ['ok' => true, 'detail' => 'Email de test envoyé à ' . $to . ' via ' . $via . '. Vérifiez la réception (et les indésirables).']
            : ['ok' => false, 'detail' => 'Envoi refusé via ' . $via . '. Détail dans storage/logs/mail.log.'];
    }

    /** Message d'erreur Google lisible, sinon le statut HTTP. */
    private static function googleError(array $response, string $context): string
    {
        $message = trim((string) ($response['json']['error']['message'] ?? ''));
        if ($message !== '') {
            return $context . ' — Google : ' . mb_substr($message, 0, 220) . self::remedy($message);
        }
        if ((string) $response['error'] !== '') {
            return $context . ' — réseau : ' . mb_substr((string) $response['error'], 0, 160);
        }
        return $context . ' — statut HTTP ' . $response['status'] . '.';
    }

    /**
     * Traduit les refus les plus fréquents en geste à faire. La restriction
     * « Sites Web » d'une clé Google est le piège classique : elle vérifie le
     * référent du navigateur, alors que le site appelle depuis le serveur.
     */
    public static function remedy(string $message): string
    {
        $low = mb_strtolower($message);

        if (str_contains($low, 'referer') || str_contains($low, 'referrer')) {
            return ' → Cette clé est restreinte « Sites Web » dans Google Cloud, or le site'
                . ' l\'appelle depuis le serveur. Le référent ' . Config::apiReferer() . ' est désormais envoyé :'
                . ' vérifiez qu\'il figure dans les référents autorisés. Le réglage recommandé reste'
                . ' une clé distincte restreinte « Adresses IP » sur l\'IP du serveur.';
        }
        if (str_contains($low, 'api key not valid') || str_contains($low, 'api_key_invalid')) {
            return ' → La clé est refusée telle quelle : recopiez-la depuis Google Cloud (sans espace).';
        }
        if (str_contains($low, 'has not been used') || str_contains($low, 'is disabled')) {
            return ' → L\'API correspondante n\'est pas activée sur ce projet Google Cloud : activez-la puis réessayez.';
        }
        if (str_contains($low, 'billing')) {
            return ' → La facturation n\'est pas active sur le projet Google Cloud.';
        }
        if (str_contains($low, 'quota') || str_contains($low, 'exhausted')) {
            return ' → Quota atteint côté Google : réessayez plus tard ou relevez la limite du projet.';
        }
        if (str_contains($low, 'ip address') || str_contains($low, 'blocked')) {
            return ' → La restriction de la clé refuse cet appel : vérifiez les restrictions dans Google Cloud.';
        }
        return '';
    }
}
