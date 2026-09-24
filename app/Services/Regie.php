<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Session;
use App\Storage\Audit;
use App\Storage\Index;
use App\Storage\Json;

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

    /** Repli si Google n'a pas répondu : de quoi choisir sans rien inventer. */
    private const MODELES_CONNUS = [
        'gemini-2.5-flash' => 'Gemini 2.5 Flash — rapide et économique',
        'gemini-2.5-pro'   => 'Gemini 2.5 Pro — plus fin, plus lent',
    ];

    /** Une liste vieille d'un jour suffit : Google en publie rarement. */
    private const MODELES_TTL = 86400;

    public static function available(): bool
    {
        return Config::get('regie.enabled', true) && Config::has('gemini_api_key');
    }

    /**
     * Lit le catalogue renvoyé par Google.
     *
     * Il mélange tout : modèles de conversation, d'embedding, d'image, et
     * variantes expérimentales. On ne garde que ceux qui savent répondre à
     * une conversation, sous leur nom court, avec la taille de contexte —
     * c'est elle qui dit combien de pages du site tiennent dans une question.
     *
     * @param  array<int, array<string, mixed>> $catalogue
     * @return array<string, string>
     */
    private static function readModels(array $catalogue): array
    {
        $models = [];
        foreach ($catalogue as $model) {
            if (!in_array('generateContent', (array) ($model['supportedGenerationMethods'] ?? []), true)) {
                continue;
            }
            $name = (string) preg_replace('#^models/#', '', (string) ($model['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $label = trim((string) ($model['displayName'] ?? '')) ?: $name;
            $limit = (int) ($model['inputTokenLimit'] ?? 0);
            $models[$name] = $limit > 0
                ? $label . ' — ' . number_format($limit / 1000, 0, ',', ' ') . 'k de contexte'
                : $label;
        }

        uksort($models, 'strnatcasecmp');
        return $models;
    }

    /** Modèle employé pour répondre : celui choisi au back-office, sinon le défaut. */
    public static function model(): string
    {
        $chosen = trim((string) Config::secret('regie_model', ''));
        return $chosen !== '' ? $chosen : (string) Config::get('regie.model', 'gemini-2.5-flash');
    }

    /**
     * Modèles que le compte peut réellement appeler, demandés à Google.
     *
     * Une liste écrite en dur vieillit mal — Gemini 1.5 a disparu en un an —
     * et surtout elle ment : elle propose des modèles auxquels la clé n'a pas
     * droit. On demande donc au fournisseur, et on ne garde que ceux qui
     * savent répondre à une conversation.
     *
     * @param  bool $refresh vrai pour ignorer le cache (bouton « Actualiser »)
     * @return array{models: array<string,string>, at: int, error: string}
     */
    public static function models(bool $refresh = false): array
    {
        $file = Config::path('data') . '/private/gemini-models.json';
        $cache = Json::read($file);

        $fresh = !$refresh
            && ($cache['models'] ?? []) !== []
            && time() - (int) ($cache['at'] ?? 0) < self::MODELES_TTL;
        if ($fresh) {
            return ['models' => (array) $cache['models'], 'at' => (int) $cache['at'], 'error' => ''];
        }

        $key = trim((string) Config::secret('gemini_api_key', ''));
        if ($key === '') {
            return ['models' => self::MODELES_CONNUS, 'at' => 0,
                    'error' => 'Aucune clé Gemini : liste par défaut.'];
        }

        $call = Http::call('GET',
            'https://generativelanguage.googleapis.com/v1beta/models?pageSize=200&key=' . urlencode($key),
            ['timeout' => 20],
        );
        if (!$call['ok']) {
            // On garde la dernière liste connue plutôt que de vider le menu.
            return [
                'models' => (array) ($cache['models'] ?? self::MODELES_CONNUS),
                'at'     => (int) ($cache['at'] ?? 0),
                'error'  => 'Google n’a pas répondu : ' . $call['error'],
            ];
        }

        $models = self::readModels((array) ($call['data']['models'] ?? []));

        if ($models === []) {
            return ['models' => self::MODELES_CONNUS, 'at' => 0,
                    'error' => 'Google n’a renvoyé aucun modèle conversationnel.'];
        }

        $at = time();
        Json::write($file, ['at' => $at, 'models' => $models]);
        Audit::log('regie.models_listed', ['count' => count($models)]);

        return ['models' => $models, 'at' => $at, 'error' => ''];
    }

    /**
     * @param  string $page chemin de la page d'où part la question, pour l'historique
     * @return array{answer:string, html:string, grounded:bool}
     */
    public static function ask(string $question, string $page = ''): array
    {
        $question = trim(mb_substr($question, 0, 600));
        if ($question === '') {
            return self::reply(I18n::t('regie.placeholder'), [], false);
        }

        $started = microtime(true);
        $context = Knowledge::search(self::searchable($question), 6);
        $exchange = ['question' => $question, 'page' => $page, 'started' => $started];

        if (!self::available()) {
            // Sans clé d'API, on reste utile : on renvoie ce que l'index contient.
            return self::finish($exchange, ...self::fallback($context));
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

        $model = self::model();
        $response = Http::json('POST',
            self::ENDPOINT . rawurlencode($model) . ':generateContent?key='
                . urlencode((string) Config::secret('gemini_api_key')),
            ['json' => $payload, 'timeout' => 30],
        );

        $text = trim((string) ($response['candidates'][0]['content']['parts'][0]['text'] ?? ''));
        if ($text === '') {
            self::journal('regie.empty_response', ['model' => $model]);
            return self::finish($exchange + ['note' => 'gemini_empty'], ...self::fallback($context));
        }

        $text = self::withLink($text, $context);
        self::remember($question, $text);
        self::journal('regie.answered', ['chars' => mb_strlen($text), 'sources' => count($context)]);

        return self::finish($exchange + ['model' => $model], $text, $context, $context !== [], 'ia');
    }

    /**
     * Une ligne de journal par question, seulement quand l'historique est
     * coupé. Le journal porte l'heure à la seconde et l'empreinte de l'adresse
     * du visiteur, qui figure aussi à côté de son compte lorsqu'il se connecte :
     * en croisant les heures, on rattacherait une question anonyme à une
     * personne. L'historique, lui, dit tout sans rien d'identifiant.
     */
    private static function journal(string $event, array $context): void
    {
        if (RegieHistory::retention() === 0) {
            Audit::log($event, $context);
        }
    }

    /**
     * Répond, et garde l'échange dans l'historique du back-office.
     *
     * @param array{question:string, page:string, started:float, note?:string, model?:string} $exchange
     * @param array<int, array<string, mixed>> $context extraits dont la réponse peut citer les pages
     * @return array{answer:string, html:string, grounded:bool}
     */
    private static function finish(array $exchange, string $text, array $context, bool $grounded, string $source): array
    {
        RegieHistory::record([
            'question' => $exchange['question'],
            'answer'   => $text,
            'links'    => self::cited($text, $context),
            'source'   => $source,
            'page'     => $exchange['page'],
            'lang'     => I18n::lang(),
            'ms'       => (int) round((microtime(true) - $exchange['started']) * 1000),
            'model'    => $exchange['model'] ?? '',
            'note'     => $exchange['note'] ?? '',
        ]);
        return self::reply($text, $context, $grounded);
    }

    /**
     * Pages que la réponse cite vraiment, en lien ou en clair : l'historique
     * les garde pour les rendre cliquables, comme le visiteur les a vues.
     *
     * @return array<string, string> chemin interne => libellé
     */
    private static function cited(string $text, array $context): array
    {
        $cited = [];
        foreach (self::allowedPaths($context) as $path => $label) {
            if (preg_match('#(?<![A-Za-z0-9/_-])' . preg_quote($path, '#') . '(?![A-Za-z0-9/_-])#', $text) === 1) {
                $cited[$path] = $label;
            }
        }
        return $cited;
    }

    /**
     * Réponse enregistrée, rendue pour le back-office comme le visiteur l'a
     * lue : texte échappé, liens limités aux pages qu'elle citait, ouverts
     * dans un nouvel onglet pour ne pas quitter l'historique.
     *
     * @param array<string, string> $links chemin interne => libellé
     */
    public static function display(string $text, array $links): string
    {
        return str_replace('<a href="', '<a target="_blank" rel="noopener" href="', self::render($text, $links));
    }

    /** Mots qui annoncent une relance : la question s'appuie sur la précédente. */
    private const RELANCES = ['et', 'mais', 'puis', 'alors', 'aussi', 'il', 'elle', 'ils', 'elles', 'ca',
                              'cela', 'celui', 'celle', 'lui', 'leur', 'y', 'en', 'and', 'it', 'he', 'she', 'they'];

    /**
     * Texte de la recherche. Une relance — « et combien il gagne ? », « quelle
     * formation ? » — ne nomme plus le métier : on la complète de la question
     * précédente pour retrouver la bonne fiche. Une question qui se suffit à
     * elle-même, même courte, est cherchée telle quelle.
     */
    private static function searchable(string $question): string
    {
        $words = array_values(array_filter(explode(' ', Index::haystack([$question])), 'strlen'));
        $meaningful = array_filter($words, static fn(string $w): bool
            => mb_strlen($w) > 2 && !in_array($w, ['quel', 'quelle', 'quels', 'quelles', 'comment',
                'combien', 'pourquoi', 'quand', 'est', 'sont', 'faut', 'peut', 'pour', 'avec', 'une', 'des', 'les'], true));
        $followUp = count($meaningful) <= 1 || array_intersect($words, self::RELANCES) !== [];
        if (!$followUp) {
            return $question;
        }
        foreach (array_reverse(self::history()) as $turn) {
            if (($turn['role'] ?? '') === 'user') {
                return (string) $turn['text'] . ' ' . $question;
            }
        }
        return $question;
    }

    /**
     * Une réponse mène toujours quelque part. Si le modèle n'a cité aucune
     * page du site, on ajoute le lien de l'extrait le plus pertinent, pourvu
     * qu'il réponde vraiment à la question.
     *
     * @param array<int, array<string, mixed>> $context
     */
    private static function withLink(string $text, array $context): string
    {
        $allowed = self::allowedPaths($context);
        if ($allowed === []) {
            return $text;
        }
        // Un chemin du site déjà cité, en lien Markdown ou en clair, suffit.
        foreach (array_keys($allowed) as $path) {
            if (preg_match('#(?<![A-Za-z0-9/_-])' . preg_quote($path, '#') . '(?![A-Za-z0-9/_-])#', $text) === 1) {
                return $text;
            }
        }
        foreach ($context as $chunk) {
            $url = trim((string) ($chunk['url'] ?? ''));
            // Seuil plus haut que pour citer un extrait : un lien ajouté d'office
            // doit répondre à coup sûr, pas seulement toucher un mot de la question.
            if ($url !== '' && (float) ($chunk['score'] ?? 0) >= self::MIN_SCORE
                && (float) ($chunk['coverage'] ?? 0) >= 0.75) {
                return rtrim($text) . "\n→ [" . str_excerpt((string) ($chunk['label'] ?? $chunk['title']), 80)
                     . '](/' . trim($url, '/') . ')';
            }
        }
        return $text;
    }

    /** Instruction système : périmètre, ton, interdits. */
    private static function systemPrompt(): string
    {
        return <<<'TXT'
        Tu es « Régie », l'assistant du site intermittent.fr, qui publie des offres d'emploi et un
        annuaire de CV pour les intermittents du spectacle en France, ainsi que des fiches sur les
        métiers du spectacle, de l'audiovisuel et de l'événementiel : missions, formation, statut,
        salaire indicatif. Le site est gratuit des deux côtés : déposer un CV, déposer une annonce,
        chercher et recruter ne coûtent rien.

        Règles, dans l'ordre de priorité :

        1. Réponds uniquement à partir des extraits du site fournis dans le contexte. Si le contexte
           ne contient pas la réponse, dis-le simplement et propose la page la plus proche.
        1 bis. Ne parle jamais de tes sources, des « extraits », du « contexte » ni de la base de
           connaissance : le visiteur ne doit voir qu'une réponse directe.
        2. N'invente jamais une offre, un profil, un employeur, un chiffre, un prix ou une date.
        3. Tu ne donnes aucun conseil juridique définitif sur le statut d'intermittent, les heures,
           les allocations ou un contrat. Tu peux expliquer les grands principes en termes généraux,
           puis renvoyer vers France Travail spectacle, Audiens ou le GUSO selon le sujet.
        4. Si la question sort du périmètre du site (météo, code, politique, vie privée d'une
           personne, etc.), décline poliment en une phrase et propose de revenir au site.
        5. Ne divulgue jamais de coordonnées personnelles d'un candidat : renvoie vers sa fiche.
        6. Réponds en français, dans la langue de la question si elle est posée dans une autre langue
           parmi : anglais, espagnol, allemand, italien, portugais, néerlandais.
        7. Chaque fois que tu cites une offre, un profil, une entreprise, un métier ou une page du
           site, transforme son nom en lien Markdown : [Nom exact](/chemin). Le chemin est celui
           indiqué dans l'extrait (« lien : /chemin ») ou écrit entre parenthèses dans son texte,
           recopié à l'identique. N'écris jamais un chemin qui n'y figure pas, et ne mets pas de
           lien vers un site externe.
        7 bis. Chaque réponse se termine par un lien vers la page du site la plus utile pour la
           suite : la fiche métier, le formulaire pour déposer un CV ou une annonce, la liste des
           offres ou l'annuaire.
        8. Trois phrases maximum, ton direct et concret, pas de formule d'accueil.
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
                // Le chemin est annoncé explicitement : c'est celui que le
                // modèle doit recopier pour en faire un lien.
                $chunk['url'] !== '' ? ' — lien : ' . $chunk['url'] : ' — aucun lien',
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

    /**
     * @return array{0:string, 1:array, 2:bool, 3:string} texte, extraits cités, réponse fondée, provenance
     */
    private static function fallback(array $context): array
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
            return [I18n::t('regie.no_answer'), [], false, 'none'];
        }

        $answer = str_excerpt((string) $best['text'], 320);
        if ((string) $best['url'] !== '') {
            // Même sans modèle, la réponse pointe la page concernée.
            $answer .= "\n→ [" . str_excerpt((string) ($best['label'] ?? $best['title']), 80) . ']('
                     . '/' . trim((string) $best['url'], '/') . ')';
        }
        return [$answer, $context, true, 'index'];
    }

    /**
     * Le fil affiche du HTML : il est construit ici, jamais par le modèle.
     * Tout est échappé, et seuls les chemins présents dans les extraits retenus
     * peuvent devenir des liens — une adresse inventée perd son lien et ne
     * laisse que son libellé.
     *
     * @param array<int, array<string, mixed>> $context
     * @return array{answer:string, html:string, grounded:bool}
     */
    private static function reply(string $answer, array $context, bool $grounded): array
    {
        return [
            'answer'   => self::plain($answer),
            'html'     => self::render($answer, self::allowedPaths($context)),
            'grounded' => $grounded,
        ];
    }

    /**
     * Chemins que la réponse peut transformer en liens : ceux des extraits
     * retenus, et les pages qu'un extrait cite lui-même — la liste des métiers
     * d'une famille, par exemple.
     *
     * @return array<string, string> chemin interne => libellé par défaut
     */
    private static function allowedPaths(array $context): array
    {
        $allowed = [];
        foreach ($context as $chunk) {
            $url = trim((string) ($chunk['url'] ?? ''));
            if ($url !== '' && str_starts_with($url, '/')) {
                $allowed['/' . trim($url, '/')] ??= str_excerpt((string) ($chunk['label'] ?? $chunk['title'] ?? ''), 80);
            }
            foreach ((array) ($chunk['links'] ?? []) as $path => $label) {
                if (str_starts_with((string) $path, '/')) {
                    $allowed['/' . trim((string) $path, '/')] ??= str_excerpt((string) $label, 80);
                }
            }
        }
        return $allowed;
    }

    /** Version texte, pour l'historique et les clients sans HTML. */
    private static function plain(string $text): string
    {
        return trim((string) preg_replace(
            '#\[([^\]\n]{1,160})\]\((/[^)\s]{0,200})\)#u', '$1', self::tidy($text)));
    }

    /** @param array<string, string> $allowed */
    private static function render(string $text, array $allowed): string
    {
        $pattern = '#\[([^\]\n]{1,160})\]\((/[A-Za-z0-9\-/_%.]{0,200})\)#u';
        $out = '';
        $offset = 0;

        while (preg_match($pattern, $text, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $start = (int) $m[0][1];
            $out .= self::escape(substr($text, $offset, $start - $offset), $allowed);

            $label = (string) $m[1][0];
            $path  = '/' . trim((string) $m[2][0], '/');
            $out  .= isset($allowed[$path]) ? self::anchor($path, $label) : e($label);

            $offset = $start + strlen((string) $m[0][0]);
        }
        $out .= self::escape(substr($text, $offset), $allowed);

        return nl2br($out, false);
    }

    /**
     * Texte brut : on échappe, puis on relie les chemins écrits en clair.
     * Le tri du plus long au plus court évite qu'un chemin court n'entame un
     * chemin plus long qui le contient.
     *
     * @param array<string, string> $allowed
     */
    private static function escape(string $plain, array $allowed): string
    {
        $escaped = e(self::tidy($plain));
        if ($allowed === []) {
            return $escaped;
        }

        $paths = array_keys($allowed);
        usort($paths, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));

        $map = [];
        $i = 0;
        foreach ($paths as $path) {
            if (!str_contains($escaped, $path)) {
                continue;
            }
            $key = "\x00lien" . (++$i) . "\x00";
            $map[$key] = self::anchor($path, $allowed[$path] !== '' ? $allowed[$path] : $path);
            $escaped = str_replace($path, $key, $escaped);
        }
        return strtr($escaped, $map);
    }

    private static function anchor(string $path, string $label): string
    {
        return '<a href="' . e(I18n::url($path)) . '">' . e(trim($label)) . '</a>';
    }

    /** Le modèle glisse parfois du gras ou des puces : le fil reste en texte simple. */
    private static function tidy(string $text): string
    {
        $text = (string) preg_replace('/\*\*(.+?)\*\*/us', '$1', $text);
        return (string) preg_replace('/^\s*[-*•]\s+/mu', '', $text);
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
        RegieHistory::reset();
    }
}
