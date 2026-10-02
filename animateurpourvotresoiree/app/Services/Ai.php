<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Core\Fs;
use App\Core\Http;
use App\Core\Logger;
use App\Core\Str;
use App\Core\Url;

/**
 * Intelligence artificielle Google Gemini (API REST, sans SDK).
 * Rôles activables séparément dans le back-office :
 *  - assistant : chat visiteurs avec appels d'outils (recherche de pros, préparation de devis)
 *  - moderation : score de spam et de qualité des demandes
 *  - seo : titres, descriptions et textes des pages locales, aide à la rédaction des fiches
 *  - classification : catégories des pros déduites de leurs textes
 */
final class Ai
{
    private const API = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public static function configured(): bool
    {
        return (string) Env::get('GEMINI_API_KEY', '') !== '';
    }

    public static function model(bool $fast = false): string
    {
        $m = $fast ? (string) Env::get('GEMINI_MODEL_FAST', '') : '';
        return $m !== '' ? $m : (string) Env::get('GEMINI_MODEL', 'gemini-2.5-flash');
    }

    /** Quota quotidien (protection du budget). */
    private static function usagePath(): string
    {
        return STORAGE_PATH . '/data/ai/usage-' . date('Y-m-d') . '.json';
    }

    public static function usage(?string $date = null): array
    {
        return Fs::readJson(STORAGE_PATH . '/data/ai/usage-' . ($date ?? date('Y-m-d')) . '.json', ['calls' => 0, 'in' => 0, 'out' => 0, 'errors' => 0, 'roles' => []]);
    }

    private static function count(string $role, array $meta, bool $error = false): void
    {
        $p = self::usagePath();
        try {
            Fs::withLock($p . '.lock', static function () use ($p, $role, $meta, $error): void {
                $u = Fs::readJson($p, ['calls' => 0, 'in' => 0, 'out' => 0, 'errors' => 0, 'roles' => []]);
                $u['calls']++;
                $u['in'] += (int) ($meta['promptTokenCount'] ?? 0);
                $u['out'] += (int) ($meta['candidatesTokenCount'] ?? 0) + (int) ($meta['thoughtsTokenCount'] ?? 0);
                $u['roles'][$role] = ($u['roles'][$role] ?? 0) + 1;
                if ($error) {
                    $u['errors']++;
                }
                Fs::writeJson($p, $u);
            }, 3);
        } catch (\Throwable) {
        }
    }

    private static function overBudget(): bool
    {
        return self::usage()['calls'] >= (int) Settings::get('ai.daily_limit', 1500);
    }

    /**
     * Appel brut à generateContent.
     * @return array{ok:bool, text:string, calls:array, raw:array, error:?string}
     */
    public static function generate(array $contents, array $opts = []): array
    {
        $role = (string) ($opts['role'] ?? 'divers');
        if (!self::configured()) {
            return ['ok' => false, 'text' => '', 'calls' => [], 'raw' => [], 'error' => 'Clé Gemini absente'];
        }
        if (self::overBudget()) {
            if (\App\Core\RateLimiter::attempt('ai-quota-alert', 1, 3600)) {
                Notify::admin('ai', 'Quota IA quotidien atteint', 'Les appels à Gemini sont suspendus jusqu\'à minuit.', Url::admin('ia'), 'warning');
            }
            return ['ok' => false, 'text' => '', 'calls' => [], 'raw' => [], 'error' => 'Quota quotidien atteint'];
        }
        $body = ['contents' => $contents, 'generationConfig' => array_filter([
            'temperature' => (float) ($opts['temperature'] ?? Settings::get('ai.temperature', 0.5)),
            'maxOutputTokens' => (int) ($opts['max_tokens'] ?? 1024),
            'responseMimeType' => $opts['json'] ?? false ? 'application/json' : null,
            'responseSchema' => $opts['schema'] ?? null,
        ], static fn ($v) => $v !== null)];
        if (!empty($opts['system'])) {
            $body['systemInstruction'] = ['parts' => [['text' => (string) $opts['system']]]];
        }
        if (!empty($opts['tools'])) {
            $body['tools'] = [['functionDeclarations' => $opts['tools']]];
        }
        $model = (string) ($opts['model'] ?? self::model((bool) ($opts['fast'] ?? false)));
        $t0 = microtime(true);
        $res = Http::postJson(self::API . rawurlencode($model) . ':generateContent', $body, ['x-goog-api-key' => (string) Env::get('GEMINI_API_KEY')], (int) ($opts['timeout'] ?? 40));
        $ms = (int) ((microtime(true) - $t0) * 1000);
        $data = json_decode($res['body'], true) ?: [];
        if ($res['status'] !== 200) {
            $err = (string) ($data['error']['message'] ?? $res['error'] ?? ('HTTP ' . $res['status']));
            self::count($role, [], true);
            Logger::log('ai', 'Erreur Gemini', ['role' => $role, 'model' => $model, 'status' => $res['status'], 'error' => $err, 'ms' => $ms], 'error');
            return ['ok' => false, 'text' => '', 'calls' => [], 'raw' => $data, 'error' => $err];
        }
        $parts = $data['candidates'][0]['content']['parts'] ?? [];
        $text = '';
        $calls = [];
        foreach ($parts as $p) {
            if (!empty($p['thought'])) {
                continue;
            }
            if (isset($p['text'])) {
                $text .= $p['text'];
            }
            if (isset($p['functionCall'])) {
                $calls[] = $p['functionCall'];
            }
        }
        self::count($role, $data['usageMetadata'] ?? []);
        Logger::log('ai', 'Appel Gemini', ['role' => $role, 'model' => $model, 'ms' => $ms, 'tokens' => $data['usageMetadata']['totalTokenCount'] ?? null]);
        return ['ok' => true, 'text' => trim($text), 'calls' => $calls, 'raw' => $data, 'parts' => $parts, 'error' => null];
    }

    public static function json(string $prompt, array $schema, array $opts = []): ?array
    {
        $r = self::generate([['role' => 'user', 'parts' => [['text' => $prompt]]]], $opts + ['json' => true, 'schema' => $schema]);
        if (!$r['ok']) {
            return null;
        }
        $txt = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $r['text']) ?? $r['text'];
        $d = json_decode($txt, true);
        return is_array($d) ? $d : null;
    }

    public static function text(string $prompt, array $opts = []): ?string
    {
        $r = self::generate([['role' => 'user', 'parts' => [['text' => $prompt]]]], $opts);
        return $r['ok'] ? $r['text'] : null;
    }

    // ------------------------------------------------------------ modération

    /** @return array{spam:float, quality:float, reason:string, categories:string[]}|null */
    public static function moderate(string $form, array $data): ?array
    {
        if (!Settings::aiOn('moderation')) {
            return null;
        }
        $cats = implode(', ', array_keys(Categories::all()));
        $fields = '';
        foreach (['name' => 'Nom', 'email' => 'Email', 'phone' => 'Téléphone', 'city' => 'Ville', 'event' => 'Événement', 'message' => 'Message'] as $k => $label) {
            if (!empty($data[$k])) {
                $fields .= $label . ' : ' . Str::limit((string) $data[$k], 1500) . "\n";
            }
        }
        $prompt = "Tu modères les formulaires d'un annuaire français de prestataires d'animation (DJ, magiciens, groupes, animateurs). "
            . "Formulaire : $form. Évalue si la soumission est du spam (publicité, SEO, arnaque, robot, texte sans rapport avec un événement) "
            . "et sa qualité (demande claire et exploitable par un professionnel). Catégories possibles : $cats.\n\n" . $fields;
        $schema = ['type' => 'OBJECT', 'properties' => [
            'spam' => ['type' => 'NUMBER', 'description' => 'Probabilité de spam entre 0 et 1'],
            'quality' => ['type' => 'NUMBER', 'description' => 'Qualité de la demande entre 0 et 1'],
            'reason' => ['type' => 'STRING', 'description' => 'Justification courte en français'],
            'categories' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
        ], 'required' => ['spam', 'quality', 'reason']];
        $d = self::json($prompt, $schema, ['role' => 'moderation', 'fast' => true, 'temperature' => 0.1, 'max_tokens' => 400, 'timeout' => 12]);
        if (!$d) {
            return null;
        }
        return [
            'spam' => max(0.0, min(1.0, (float) ($d['spam'] ?? 0))),
            'quality' => max(0.0, min(1.0, (float) ($d['quality'] ?? 0))),
            'reason' => Str::limit((string) ($d['reason'] ?? ''), 200),
            'categories' => array_values(array_filter((array) ($d['categories'] ?? []), static fn ($c) => Categories::get((string) $c) !== null)),
        ];
    }

    // -------------------------------------------------------- classification

    /** @return string[]|null */
    public static function classify(array $pro): ?array
    {
        if (!Settings::aiOn('classification')) {
            return null;
        }
        $list = [];
        foreach (Categories::all(true) as $slug => $c) {
            $list[] = $slug . ' (' . $c['name'] . ')';
        }
        $prompt = "Classe ce professionnel de l'événementiel dans 1 à 3 catégories, de la plus pertinente à la moins pertinente, parmi : " . implode(', ', $list) . ".\n\n"
            . 'Nom : ' . Pros::displayName($pro) . "\nMots-clés : " . implode(', ', (array) ($pro['tags'] ?? [])) . "\nAccroche : " . ($pro['tagline'] ?? '') . "\nDescription : " . Str::limit(Str::text((string) ($pro['description'] ?? '')), 2500);
        $d = self::json($prompt, ['type' => 'OBJECT', 'properties' => ['categories' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']]], 'required' => ['categories']], ['role' => 'classification', 'fast' => true, 'temperature' => 0.1, 'max_tokens' => 200]);
        if (!$d) {
            return null;
        }
        $out = [];
        foreach ((array) $d['categories'] as $c) {
            $slug = trim(explode(' ', (string) $c)[0]);
            if (Categories::get($slug)) {
                $out[] = $slug;
            }
        }
        return array_values(array_unique(array_slice($out, 0, 3))) ?: null;
    }

    // ---------------------------------------------------------------- SEO

    /** Texte d'introduction d'une page locale (catégorie × lieu), à partir de données réelles. */
    public static function landingIntro(string $label, int $count, array $facts): ?string
    {
        if (!Settings::aiOn('seo')) {
            return null;
        }
        $prompt = "Rédige en français un paragraphe d'introduction de 90 à 140 mots pour la page « $label » d'un annuaire de prestataires d'animation. "
            . "Données réelles : $count professionnels référencés. " . implode(' ', $facts) . "\n"
            . "Ton chaleureux et concret, utile au lecteur (conseils pour bien choisir, types d'événements), sans superlatif mensonger, sans inventer de chiffres ni de noms, sans liste à puces, sans titre.";
        return self::text($prompt, ['role' => 'seo', 'temperature' => 0.7, 'max_tokens' => 600]);
    }

    /** @return array{title:string, description:string}|null */
    public static function metaFor(string $label, string $context): ?array
    {
        if (!Settings::aiOn('seo')) {
            return null;
        }
        $schema = ['type' => 'OBJECT', 'properties' => ['title' => ['type' => 'STRING'], 'description' => ['type' => 'STRING']], 'required' => ['title', 'description']];
        $d = self::json("Propose une balise title (55 caractères maximum, mot-clé principal au début) et une meta description (150 caractères maximum, incitative, avec un appel à l'action) pour la page « $label » d'un annuaire de prestataires d'animation en France. Contexte : $context", $schema, ['role' => 'seo', 'temperature' => 0.6, 'max_tokens' => 300]);
        return $d ? ['title' => Str::limit((string) $d['title'], 70, ''), 'description' => Str::limit((string) $d['description'], 170, '')] : null;
    }

    /** Amélioration de la description d'une fiche (espace pro). */
    public static function improveDescription(array $pro, string $draft): ?string
    {
        if (!Settings::aiOn('seo')) {
            return null;
        }
        $cat = Pros::primaryCategory($pro)['name'] ?? 'prestataire d\'animation';
        $prompt = "Tu aides un professionnel ($cat, basé à " . ($pro['city'] ?? 'en France') . ") à présenter sa prestation sur un annuaire. "
            . "Réécris et structure sa description en français (250 à 450 mots), à la première personne du pluriel ou du singulier selon le texte d'origine, "
            . "en 3 à 5 paragraphes courts : présentation, prestations et formules, matériel ou spécialités, zone d'intervention, appel à demander un devis. "
            . "N'invente aucun fait, prix, chiffre ou référence absents du texte. Texte brut, paragraphes séparés par une ligne vide, pas de titre ni de markdown.\n\nTexte d'origine :\n" . Str::limit($draft, 4000);
        return self::text($prompt, ['role' => 'seo', 'temperature' => 0.6, 'max_tokens' => 1200]);
    }

    /** Brouillon d'article de blog (back-office). */
    public static function articleDraft(string $topic): ?array
    {
        if (!Settings::aiOn('seo')) {
            return null;
        }
        $schema = ['type' => 'OBJECT', 'properties' => [
            'title' => ['type' => 'STRING'], 'excerpt' => ['type' => 'STRING'],
            'sections' => ['type' => 'ARRAY', 'items' => ['type' => 'OBJECT', 'properties' => ['heading' => ['type' => 'STRING'], 'text' => ['type' => 'STRING']], 'required' => ['heading', 'text']]],
            'meta_description' => ['type' => 'STRING'],
        ], 'required' => ['title', 'excerpt', 'sections', 'meta_description']];
        return self::json("Rédige un article de blog en français pour un annuaire de prestataires d'animation (DJ, magiciens, animateurs) sur le sujet : « $topic ». 5 à 7 sections avec intertitres, 900 à 1300 mots au total, conseils concrets, ton expert et convivial, sans inventer de statistiques.", $schema, ['role' => 'seo', 'temperature' => 0.7, 'max_tokens' => 4000, 'timeout' => 90]);
    }

    // ------------------------------------------------------------ assistant

    public static function assistantPrompt(): string
    {
        $custom = trim((string) Settings::get('ai.assistant_prompt', ''));
        $name = (string) Settings::get('ai.assistant_name', 'Confetti');
        $cats = [];
        foreach (Categories::all() as $slug => $c) {
            $cats[] = $c['name'] . ' (' . $slug . ')';
        }
        $base = "Tu es $name, l'assistant du site « " . Settings::siteName() . " », annuaire gratuit de professionnels de l'animation et de l'événementiel en France. "
            . "Tu aides les visiteurs à trouver le bon prestataire pour leur fête (mariage, anniversaire, soirée d'entreprise, soirée privée…) et à préparer leur demande de devis. "
            . "Règles : réponds toujours en français, de façon chaleureuse, concise (3 à 6 phrases) et concrète. "
            . "Pour proposer des pros, utilise TOUJOURS l'outil search_pros (n'invente jamais de professionnel, de prix ni de disponibilité). "
            . "Si la ville ou le type de prestation manque, pose une seule question à la fois. "
            . "Quand le besoin est clair, propose de préparer la demande de devis avec l'outil prepare_quote. "
            . "Ne demande jamais de données sensibles. Si la question est hors sujet, ramène poliment la conversation sur l'organisation de l'événement. "
            . 'Métiers disponibles : ' . implode(', ', $cats) . '. Nous sommes le ' . date_fr(date('c'), 'long') . '.';
        return $custom !== '' ? $base . "\n\nConsignes complémentaires de l'administrateur :\n" . $custom : $base;
    }

    public static function tools(): array
    {
        $catEnum = array_keys(Categories::all());
        return [
            [
                'name' => 'search_pros',
                'description' => "Recherche des professionnels publiés sur l'annuaire. Renvoie au plus 6 fiches.",
                'parameters' => ['type' => 'OBJECT', 'properties' => [
                    'categorie' => ['type' => 'STRING', 'description' => 'Identifiant de métier', 'enum' => $catEnum],
                    'ville' => ['type' => 'STRING', 'description' => 'Ville ou code postal en France'],
                    'occasion' => ['type' => 'STRING', 'description' => 'mariage, anniversaire, entreprise ou soirée privée'],
                    'mots_cles' => ['type' => 'STRING', 'description' => 'Précisions libres (style musical, karaoké, enfants…)'],
                ]],
            ],
            [
                'name' => 'prepare_quote',
                'description' => 'Prépare un lien vers le formulaire de demande de devis pré-rempli.',
                'parameters' => ['type' => 'OBJECT', 'properties' => [
                    'categorie' => ['type' => 'STRING', 'enum' => $catEnum],
                    'ville' => ['type' => 'STRING'],
                    'date' => ['type' => 'STRING', 'description' => 'Date au format AAAA-MM-JJ si connue'],
                    'invites' => ['type' => 'STRING'],
                    'occasion' => ['type' => 'STRING'],
                    'details' => ['type' => 'STRING', 'description' => 'Résumé du besoin rédigé pour les professionnels'],
                ]],
            ],
        ];
    }

    /**
     * Tour de conversation de l'assistant.
     * @param array<int,array{role:string,text:string}> $history
     * @return array{text:string, cards:array, quote:?string, error:?string}
     */
    public static function chat(array $history): array
    {
        $contents = [];
        foreach (array_slice($history, -12) as $m) {
            $contents[] = ['role' => $m['role'] === 'user' ? 'user' : 'model', 'parts' => [['text' => Str::limit((string) $m['text'], 1500)]]];
        }
        $cards = [];
        $quote = null;
        for ($i = 0; $i < 4; $i++) {
            $r = self::generate($contents, ['role' => 'assistant', 'system' => self::assistantPrompt(), 'tools' => self::tools(), 'temperature' => (float) Settings::get('ai.temperature', 0.5), 'max_tokens' => 900]);
            if (!$r['ok']) {
                return ['text' => "Je suis momentanément indisponible 😕 Vous pouvez chercher directement un pro ou déposer une demande de devis gratuite.", 'cards' => [], 'quote' => '/devis/', 'error' => $r['error']];
            }
            if (!$r['calls']) {
                return ['text' => $r['text'] !== '' ? $r['text'] : "Pouvez-vous m'en dire un peu plus sur votre événement ?", 'cards' => $cards, 'quote' => $quote, 'error' => null];
            }
            $contents[] = ['role' => 'model', 'parts' => $r['parts']];
            $responses = [];
            foreach ($r['calls'] as $call) {
                $args = (array) ($call['args'] ?? []);
                if ($call['name'] === 'search_pros') {
                    $found = self::toolSearch($args);
                    $cards = array_slice($found, 0, 6);
                    $responses[] = ['functionResponse' => ['name' => 'search_pros', 'response' => ['resultats' => array_map(static fn ($c) => ['nom' => $c['name'], 'metier' => $c['cat'], 'ville' => $c['city'], 'note' => $c['rating'], 'prix_des' => $c['price'], 'accroche' => $c['tagline']], $cards), 'total' => count($found)]]];
                } elseif ($call['name'] === 'prepare_quote') {
                    $quote = Url::devis(array_filter([
                        'cat' => (string) ($args['categorie'] ?? ''),
                        'ville' => (string) ($args['ville'] ?? ''),
                        'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($args['date'] ?? '')) ? $args['date'] : '',
                        'invites' => (string) ($args['invites'] ?? ''),
                        'occasion' => (string) ($args['occasion'] ?? ''),
                        'details' => Str::limit((string) ($args['details'] ?? ''), 800, ''),
                        'src' => 'assistant',
                    ]));
                    $responses[] = ['functionResponse' => ['name' => 'prepare_quote', 'response' => ['lien' => $quote, 'consigne' => 'Invite le visiteur à cliquer sur le bouton « Finaliser ma demande » affiché sous ta réponse.']]];
                } else {
                    $responses[] = ['functionResponse' => ['name' => (string) $call['name'], 'response' => ['erreur' => 'outil inconnu']]];
                }
            }
            $contents[] = ['role' => 'user', 'parts' => $responses];
        }
        return ['text' => 'Voici ce que j\'ai trouvé pour vous !', 'cards' => $cards, 'quote' => $quote, 'error' => null];
    }

    private static function toolSearch(array $args): array
    {
        $criteria = ['per' => 6, 'q' => trim((string) ($args['mots_cles'] ?? ''))];
        $cat = (string) ($args['categorie'] ?? '');
        if (Categories::get($cat)) {
            $criteria['cat'] = $cat;
        }
        $ville = trim((string) ($args['ville'] ?? ''));
        if ($ville !== '') {
            $c = Geo::search($ville, 1);
            if ($c) {
                $criteria['insee'] = $c[0]['insee'];
                $criteria['radius'] = 60;
            }
        }
        $occ = Str::norm((string) ($args['occasion'] ?? ''));
        foreach (Categories::occasions() as $slug => $o) {
            if ($occ !== '' && (str_contains(Str::norm($o['name']), $occ) || in_array($occ, array_map([Str::class, 'norm'], $o['keywords']), true))) {
                $criteria['occasion'] = $slug;
            }
        }
        $res = Search::run($criteria);
        if ($res['total'] === 0 && $criteria['q'] !== '') {
            unset($criteria['q']);
            $res = Search::run($criteria);
        }
        return array_map([Search::class, 'card'], $res['items']);
    }
}
