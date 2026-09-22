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
    /** Les groupes qui savent se vérifier ; les autres n'affichent pas de bouton. */
    public const TESTABLE = ['regie', 'translate', 'reviews', 'adsense', 'sources', 'mail'];

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
        $call = Http::call('POST',
            'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model)
            . ':generateContent?key=' . urlencode((string) Config::secret('gemini_api_key')),
            [
                'json' => ['contents' => [['role' => 'user', 'parts' => [['text' => 'Réponds « ok ».']]]],
                           'generationConfig' => ['maxOutputTokens' => 10]],
                'timeout' => 20,
            ],
        );
        if (!$call['ok']) {
            return self::fail('Refus de l’API Gemini : ' . $call['error']);
        }
        $text = trim((string) ($call['data']['candidates'][0]['content']['parts'][0]['text'] ?? ''));
        return $text !== ''
            ? self::ok('Gemini répond. Modèle : ' . $model . '.')
            : self::fail('Gemini a répondu sans texte : modèle « ' . $model . '  » indisponible '
                       . 'pour ce compte, ou réponse filtrée.');
    }

    private static function translate(): array
    {
        if (!Translator::available()) {
            return self::fail('Aucune clé enregistrée. Les pages non traduites restent en français.');
        }
        $result = Translator::translate(['bonjour'], 'en');
        if ($result !== []) {
            return self::ok('Traduction active. « bonjour » → « ' . $result[0] . ' ».');
        }
        $reason = Translator::lastError();
        return self::fail($reason !== ''
            ? 'Refus de l’API Google : ' . $reason
            : 'L’API a refusé la requête, sans message.');
    }

    private static function reviews(): array
    {
        if (!Reviews::available()) {
            return self::fail('Clé ou identifiant de fiche manquant. Le bloc d’avis reste masqué.');
        }
        $data = Reviews::get();
        if ($data !== null && $data['count'] > 0) {
            return self::ok(sprintf('Fiche trouvée : %s/5 sur %d avis.', $data['rating'], $data['count']));
        }
        $reason = Reviews::lastError();
        return self::fail($reason !== ''
            ? 'Refus de l’API Places : ' . $reason
            : 'Aucun avis remonté : vérifiez l’identifiant de fiche et l’activation de Places API.');
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

    /**
     * Le test envoie un vrai message à l'adresse d'alerte : c'est la seule
     * preuve qui vaille, transport compris.
     */
    private static function mail(): array
    {
        $from = (string) Config::secret('mail_from', '');
        if ($from !== '' && !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return self::fail('Adresse d’expédition invalide.');
        }
        if (!Smtp::configured() && !function_exists('mail')) {
            return self::fail('Aucun serveur SMTP renseigné et la fonction mail() est '
                            . 'désactivée sur cet hébergement : aucun e-mail ne peut partir.');
        }

        $result = Notifier::test();
        if (!$result['ok']) {
            return self::fail($result['message']);
        }

        $host = parse_url((string) Config::get('site.url'), PHP_URL_HOST) ?: '';
        $domain = $from === '' ? '' : substr(strrchr($from, '@') ?: '', 1);
        $warning = $domain !== '' && !str_contains($host, $domain)
            ? ' Attention : le domaine de l’adresse d’expédition diffère de celui du site, '
              . 'ce qui augmente le risque de classement en indésirable.'
            : '';

        return self::ok($result['message'] . $warning);
    }
}
