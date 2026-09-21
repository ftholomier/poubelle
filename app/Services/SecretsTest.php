<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Services\Sources\AdzunaSource;
use App\Services\Sources\FranceTravailSource;
use App\Services\Sources\IndeedSource;
use App\Services\Sources\JoobleSource;

/**
 * Vérifie qu'une clé enregistrée fonctionne réellement, en interrogeant le
 * service concerné. Évite de découvrir une clé fausse le jour où un visiteur
 * en a besoin.
 */
final class SecretsTest
{
    /** @return array{ok:bool, message:string} */
    public static function run(string $group): array
    {
        return match ($group) {
            'regie'     => self::gemini(),
            'translate' => self::translate(),
            'reviews'   => self::reviews(),
            'adsense'   => self::adsense(),
            'sources'   => self::sources(),
            'mail'      => self::mail(),
            default     => self::fail('Test indisponible pour ce réglage.'),
        };
    }

    private static function ok(string $message): array
    {
        return ['ok' => true, 'message' => $message];
    }

    private static function fail(string $message): array
    {
        return ['ok' => false, 'message' => $message];
    }

    private static function gemini(): array
    {
        if (!Config::has('gemini_api_key')) {
            return self::fail('Aucune clé enregistrée. L’assistant fonctionne en mode dégradé.');
        }
        $model = (string) Config::get('regie.model', 'gemini-2.5-flash');
        $response = Http::json('POST',
            'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model)
            . ':generateContent?key=' . urlencode((string) Config::secret('gemini_api_key')),
            [
                'json' => ['contents' => [['role' => 'user', 'parts' => [['text' => 'Réponds « ok ».']]]],
                           'generationConfig' => ['maxOutputTokens' => 10]],
                'timeout' => 20,
            ],
        );
        $text = trim((string) ($response['candidates'][0]['content']['parts'][0]['text'] ?? ''));
        return $text !== ''
            ? self::ok('Gemini répond. Modèle : ' . $model . '.')
            : self::fail('Gemini n’a pas répondu : clé invalide, quota épuisé, ou modèle indisponible.');
    }

    private static function translate(): array
    {
        if (!Translator::available()) {
            return self::fail('Aucune clé enregistrée. Les pages non traduites restent en français.');
        }
        $result = Translator::translate(['bonjour'], 'en');
        return $result !== []
            ? self::ok('Traduction active. « bonjour » → « ' . $result[0] . ' ».')
            : self::fail('L’API a refusé la requête : clé invalide, API non activée, ou facturation absente.');
    }

    private static function reviews(): array
    {
        if (!Reviews::available()) {
            return self::fail('Clé ou identifiant de fiche manquant. Le bloc d’avis reste masqué.');
        }
        $data = Reviews::get();
        return $data !== null && $data['count'] > 0
            ? self::ok(sprintf('Fiche trouvée : %s/5 sur %d avis.', $data['rating'], $data['count']))
            : self::fail('Aucun avis remonté : vérifiez l’identifiant de fiche et l’activation de Places API.');
    }

    private static function adsense(): array
    {
        $client = (string) Config::get('ads.client', '');
        if ($client === '') {
            return self::fail('Aucun identifiant éditeur. Les emplacements montrent le cadre de la maquette.');
        }
        if (!preg_match('/^ca-pub-\d{10,20}$/', $client)) {
            return self::fail('Format inattendu : un identifiant éditeur ressemble à ca-pub-0000000000000000.');
        }
        $slots = Ads::slots();
        $own = count(array_filter($slots, static fn(array $s) => $s['own'] !== ''));
        $inherited = count(array_filter($slots, static fn(array $s) => $s['inherited']));
        $empty = count($slots) - $own - $inherited;

        if ($empty === count($slots)) {
            return self::fail(
                'Identifiant éditeur valide, mais aucune unité : renseignez au moins '
                . 'l’unité par défaut, sans quoi les emplacements restent au cadre de la maquette.',
            );
        }
        return self::ok(sprintf(
            'Identifiant valide. %d emplacement(s) avec une unité propre, %d reprenant '
            . 'l’unité par défaut, %d sans unité sur %d.',
            $own, $inherited, $empty, count($slots),
        ));
    }

    private static function sources(): array
    {
        $lines = [];
        foreach ([new FranceTravailSource(), new IndeedSource(), new AdzunaSource(), new JoobleSource()] as $source) {
            if (!$source->isConfigured()) {
                continue;
            }
            $found = $source->search(['q' => '', 'city' => '', 'page' => 1, 'limit' => 3]);
            $lines[] = sprintf('%s : %s', $source->name(),
                $found !== [] ? count($found) . ' offre(s) remontée(s)' : 'aucune réponse exploitable');
        }
        if ($lines === []) {
            return self::fail('Aucune source configurée. Seules les annonces du site sont affichées.');
        }
        $good = count(array_filter($lines, static fn(string $l) => !str_contains($l, 'aucune')));
        return ['ok' => $good > 0, 'message' => implode(' · ', $lines)];
    }

    private static function mail(): array
    {
        $from = (string) Config::secret('mail_from', '');
        if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return self::fail('Adresse d’expédition absente ou invalide.');
        }
        if (!function_exists('mail')) {
            return self::fail('La fonction mail() est désactivée : les messages seront archivés dans data/logs/mail/.');
        }
        $host = parse_url((string) Config::get('site.url'), PHP_URL_HOST) ?: '';
        $domain = substr(strrchr($from, '@') ?: '', 1);
        return $domain !== '' && str_contains($host, $domain)
            ? self::ok('Adresse cohérente avec le domaine du site.')
            : self::ok('Adresse valide, mais son domaine diffère de celui du site : '
                     . 'risque de classement en indésirable.');
    }
}
