<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Session;
use App\Storage\Audit;

/**
 * Assistant « Régie ».
 * Gemini est appelé côté serveur uniquement : la clé ne quitte jamais le serveur.
 * Les réponses s'appuient sur la base de connaissance locale ; hors périmètre,
 * l'assistant décline, et il ne donne jamais de conseil juridique définitif.
 */
final class Regie
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/';
    private const HISTORY_KEY = 'regie_history';

    public static function available(): bool
    {
        return Config::get('regie.enabled', true) && Config::has('gemini_api_key');
    }

    /**
     * @return array{answer:string, source:string, grounded:bool}
     */
    public static function ask(string $question): array
    {
        $question = trim(mb_substr($question, 0, 600));
        if ($question === '') {
            return self::reply(I18n::t('regie.placeholder'), '', false);
        }

        $context = Knowledge::search($question, 5);

        if (!self::available()) {
            // Sans clé d'API, on reste utile : on renvoie ce que l'index contient.
            return self::fallback($question, $context);
        }

        $payload = [
            'systemInstruction' => ['parts' => [['text' => self::systemPrompt()]]],
            'contents'          => self::buildContents($question, $context),
            'generationConfig'  => [
                'temperature'     => 0.3,
                'maxOutputTokens' => 600,
                'topP'            => 0.9,
            ],
            // Les garde-fous du modèle restent au niveau par défaut ; les nôtres
            // sont dans l'instruction système et dans le filtrage ci-dessous.
        ];

        $model = (string) Config::get('regie.model', 'gemini-2.5-flash');
        $response = Http::json('POST',
            self::ENDPOINT . rawurlencode($model) . ':generateContent?key='
                . urlencode((string) Config::secret('gemini_api_key')),
            ['json' => $payload, 'timeout' => 30],
        );

        $text = trim((string) ($response['candidates'][0]['content']['parts'][0]['text'] ?? ''));
        if ($text === '') {
            Audit::log('regie.empty_response', ['question' => mb_substr($question, 0, 120)]);
            return self::fallback($question, $context);
        }

        self::remember($question, $text);
        Audit::log('regie.answered', ['chars' => mb_strlen($text), 'sources' => count($context)]);

        return self::reply($text, self::sourceLabel($context), $context !== []);
    }

    /** Instruction système : périmètre, ton, interdits. */
    private static function systemPrompt(): string
    {
        return <<<'TXT'
        Tu es « Régie », l'assistant du site intermittent.fr, qui publie des offres d'emploi et un
        annuaire de CV pour les intermittents du spectacle en France. Le site est gratuit des deux
        côtés : déposer un CV, déposer une annonce, chercher et recruter ne coûtent rien.

        Règles, dans l'ordre de priorité :

        1. Réponds uniquement à partir des extraits du site fournis dans le contexte. Si le contexte
           ne contient pas la réponse, dis-le simplement et propose la page la plus proche.
        2. N'invente jamais une offre, un profil, un employeur, un chiffre, un prix ou une date.
        3. Tu ne donnes aucun conseil juridique définitif sur le statut d'intermittent, les heures,
           les allocations ou un contrat. Tu peux expliquer les grands principes en termes généraux,
           puis renvoyer vers France Travail spectacle, Audiens ou le GUSO selon le sujet.
        4. Si la question sort du périmètre du site (météo, code, politique, vie privée d'une
           personne, etc.), décline poliment en une phrase et propose de revenir au site.
        5. Ne divulgue jamais de coordonnées personnelles d'un candidat : renvoie vers sa fiche.
        6. Réponds en français, dans la langue de la question si elle est posée dans une autre langue
           parmi : anglais, espagnol, allemand, italien, portugais, néerlandais.
        7. Trois phrases maximum, ton direct et concret, pas de formule d'accueil.
        TXT;
    }

    /** @return array<int, array<string, mixed>> */
    private static function buildContents(string $question, array $context): array
    {
        $contents = [];

        // Historique court, pour que l'assistant suive le fil.
        foreach (array_slice(self::history(), -(int) Config::get('regie.max_context', 12)) as $turn) {
            $contents[] = ['role' => $turn['role'], 'parts' => [['text' => $turn['text']]]];
        }

        $extracts = '';
        foreach ($context as $i => $chunk) {
            $extracts .= sprintf(
                "\n[Extrait %d — %s « %s »%s]\n%s\n",
                $i + 1,
                $chunk['kind'],
                $chunk['title'],
                $chunk['url'] !== '' ? ' (' . $chunk['url'] . ')' : '',
                $chunk['text'],
            );
        }
        if ($extracts === '') {
            $extracts = "\n(Aucun extrait du site ne correspond à cette question.)\n";
        }

        $contents[] = ['role' => 'user', 'parts' => [['text' =>
            "Extraits du site :\n" . $extracts . "\nQuestion du visiteur : " . $question]]];

        return $contents;
    }

    /**
     * Réponse sans API : on cite l'index local plutôt que de ne rien dire.
     * En dessous du seuil de pertinence, on décline — mieux vaut un refus poli
     * qu'un extrait qui n'a rien à voir avec la question.
     */
    private const MIN_SCORE = 3.0;
    private const MIN_COVERAGE = 0.5;

    private static function fallback(string $question, array $context): array
    {
        $best = null;
        foreach ($context as $chunk) {
            if ((float) $chunk['score'] >= self::MIN_SCORE
                && (float) ($chunk['coverage'] ?? 0) >= self::MIN_COVERAGE) {
                $best = $chunk;
                break;
            }
        }
        if ($best === null) {
            return self::reply(I18n::t('regie.no_scope'), '', false);
        }

        $answer = str_excerpt((string) $best['text'], 320);
        if ((string) $best['url'] !== '') {
            $answer .= "\n→ " . I18n::url((string) $best['url']);
        }
        return self::reply($answer, self::sourceLabel($context), true);
    }

    private static function sourceLabel(array $context): string
    {
        if ($context === []) {
            return '';
        }
        $titles = [];
        foreach (array_slice($context, 0, 2) as $chunk) {
            $titles[] = str_excerpt((string) $chunk['title'], 40);
        }
        return 'Source : ' . implode(' · ', $titles);
    }

    /** @return array{answer:string, source:string, grounded:bool} */
    private static function reply(string $answer, string $source, bool $grounded): array
    {
        return ['answer' => $answer, 'source' => $source, 'grounded' => $grounded];
    }

    /** @return array<int, array{role:string,text:string}> */
    public static function history(): array
    {
        $history = Session::get(self::HISTORY_KEY, []);
        return is_array($history) ? $history : [];
    }

    private static function remember(string $question, string $answer): void
    {
        $history = self::history();
        $history[] = ['role' => 'user',  'text' => $question];
        $history[] = ['role' => 'model', 'text' => $answer];
        // On borne l'historique : la session ne doit pas enfler indéfiniment.
        Session::set(self::HISTORY_KEY, array_slice($history, -24));
    }

    public static function forget(): void
    {
        Session::forget(self::HISTORY_KEY);
    }
}
