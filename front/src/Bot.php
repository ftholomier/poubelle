<?php

declare(strict_types=1);

/**
 * Assistant du site (bot en bas à droite).
 *
 * Avec une clé API Gemini (Google) réglée dans le back-office, les réponses viennent du
 * modèle Gemini choisi dans la liste dynamique des modèles, avec des consignes construites
 * à partir du contenu du site : produits, agences, coordonnées, engagements. Sans clé, ou si
 * Gemini ne répond pas, l'assistant répond avec les réponses rapides réglées dans le
 * back-office. Il propose toujours de basculer vers le devis ou l'appel.
 */
final class Bot
{
    /** @param array $history [['role' => 'user'|'assistant', 'text' => '…'], …] */
    public static function reply(array $history, string $session = ''): array
    {
        $msgs = [];
        foreach (array_slice($history, -12) as $m) {
            $role = ($m['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $text = trim(mb_substr((string) ($m['text'] ?? ''), 0, 1200));
            if ($text === '') {
                continue;
            }
            // Messages alternés, en commençant par l'utilisateur
            if ($msgs && end($msgs)['role'] === $role) {
                $msgs[count($msgs) - 1]['content'] .= "\n" . $text;
            } elseif (!$msgs && $role === 'assistant') {
                continue;
            } else {
                $msgs[] = ['role' => $role, 'content' => $text];
            }
        }
        if (!$msgs || end($msgs)['role'] !== 'user') {
            return ['text' => (string) (Content::ui()['bot']['fallback'] ?? ''), 'source' => 'vide'];
        }
        $question = (string) end($msgs)['content'];
        $answer = null;
        $source = 'regles';
        $error = null;
        if (self::aiReady()) {
            try {
                $answer = Gemini::generate(Settings::secret('gemini_key'), self::model(), self::systemPrompt(), $msgs);
                $source = 'ia';
            } catch (Throwable $e) {
                $error = $e->getMessage();
                error_log('[renovest] assistant IA : ' . $error);
            }
        }
        if ($answer === null || trim($answer) === '') {
            $answer = self::rules($question);
            $source = 'regles';
        }
        self::log($session, $question, $answer, $source);
        return ['text' => $answer, 'source' => $source, 'error' => $error];
    }

    /** IA utilisable : clé Gemini enregistrée. */
    public static function aiReady(): bool
    {
        return Settings::hasSecret('gemini_key');
    }

    /** Modèle choisi dans le back-office, sinon le modèle recommandé parmi ceux de la clé. */
    public static function model(): string
    {
        $m = trim((string) Settings::get('bot_model', ''));
        if ($m !== '' && Gemini::validId($m)) {
            return $m;
        }
        $rec = Gemini::recommended(Gemini::cachedModels());
        if ($rec === '') {
            try {
                $rec = Gemini::recommended(Gemini::models(Settings::secret('gemini_key')));
            } catch (Throwable) {
            }
        }
        return $rec !== '' ? $rec : 'gemini-2.5-flash';
    }

    /** Prompt système : uniquement des informations issues du site. */
    public static function systemPrompt(): string
    {
        $s = Content::site();
        $agences = [];
        foreach ($s['agencies'] ?? [] as $a) {
            $agences[] = '- ' . $a['name'] . ' : ' . $a['street'] . ', ' . $a['zip'] . ' ' . $a['town'];
        }
        $produits = [];
        foreach (Content::products() as $p) {
            $produits[] = '- ' . $p['title'] . ' (' . Http::abs($p['path']) . ') : ' . $p['summary'];
        }
        $why = plain((string) (Content::page('produits')['why'] ?? ''));
        $presentation = plain((string) (Content::page('presentation')['content'] ?? ''));
        $philo = [];
        foreach (Content::page('home')['philosophy']['items'] ?? [] as $f) {
            $philo[] = '- ' . $f['title'] . ' : ' . $f['text'];
        }
        $extra = trim((string) Settings::get('bot_extra', ''));
        $devis = Http::abs(Content::path('devis'));
        $contact = Http::abs(Content::path('contact'));

        $txt = <<<TXT
Tu es l'assistant du site de {$s['name']} ({$s['legal_name']}), entreprise de fourniture et pose de fermetures de l'habitation et de protections solaires. Tu réponds aux visiteurs du site, en français, avec un ton chaleureux et professionnel.

Règles :
- Réponds en 2 à 4 phrases courtes, sans titres ni mise en forme Markdown.
- Utilise uniquement les informations ci-dessous. N'invente jamais de prix, de délai, de remise, de marque ou de garantie : si l'information n'y figure pas, dis qu'un commercial la précisera dans le devis détaillé et gratuit.
- Propose à chaque fois une suite concrète : le formulaire de devis ({$devis}), l'appel au {$s['phone']}, ou le formulaire de contact ({$contact}).
- Pour une panne ou une réparation, oriente vers le service technique : {$s['sav_phone']}.
- Ne demande pas de données personnelles dans la discussion : renvoie vers le formulaire de devis.
- Si la question n'a aucun rapport avec l'habitat et l'entreprise, réponds poliment que tu ne peux aider que pour les projets de fermetures et de protection solaire.

Coordonnées :
- Téléphone : {$s['phone']} ({$s['hours']})
- Service technique / SAV : {$s['sav_phone']}
- Fax : {$s['fax']}
- E-mail : {$s['email']}
Agences :
{AGENCES}

Présentation :
{$presentation}

Notre philosophie :
{PHILO}

Produits :
{PRODUITS}

Pourquoi faire appel à {$s['name']} :
{$why}

Zone d'intervention : {ZONES}.
{EXTRA}
TXT;
        return strtr($txt, [
            '{AGENCES}' => implode("\n", $agences),
            '{PHILO}' => implode("\n", $philo),
            '{PRODUITS}' => implode("\n", $produits),
            '{ZONES}' => implode(', ', (array) ($s['zones'] ?? [])),
            '{EXTRA}' => $extra !== '' ? "\nInformations complémentaires validées par l'entreprise :\n" . $extra : '',
        ]);
    }

    /** Réponse sans IA : réponses rapides réglées dans le back-office, par mots-clés. */
    public static function rules(string $q): string
    {
        $ui = Content::ui();
        $site = Content::site();
        $qn = mb_strtolower(plain($q));
        foreach ($ui['bot']['quick'] ?? [] as $r) {
            if (mb_strtolower(trim($r['q'])) === trim($qn)) {
                return (string) $r['a'];
            }
        }
        $map = [
            ['/\\b(prix|co[uû]ts?|tarifs?|combien|cher|ch[eè]re|budget)\\b/u', 0],
            ['/\\b(o[uù]|zones?|intervenez|secteurs?|villes?|d[ée]placez)\\b/u', 1],
            ['/\\b(aides?|primes?|subventions?|rge|cee|tva|maprimer[ée]nov)\\b/u', 2],
            ['/\\b(rdv|rendez|rappel|rappeler|visite)\\b/u', 3],
        ];
        foreach ($map as [$re, $i]) {
            if (preg_match($re, $qn) && isset($ui['bot']['quick'][$i])) {
                return (string) $ui['bot']['quick'][$i]['a'];
            }
        }
        if (preg_match('/sav|panne|r[ée]par|bloqu|cass|d[ée]pann/u', $qn)) {
            return 'Notre service technique est joignable au ' . $site['sav_phone'] . ' : un service réactif et proche de ses clients.';
        }
        // Produit le plus proche : le plus de mots du nom retrouvés dans la question
        $best = null;
        $bestScore = 0;
        foreach (Content::products() as $p) {
            $words = array_unique(array_filter(preg_split('/[^\p{L}]+/u', mb_strtolower($p['title'] . ' ' . ($p['offer_title'] ?? ''))) ?: [], fn($w) => mb_strlen($w) > 3));
            $score = 0;
            foreach ($words as $w) {
                if (str_contains($qn, rtrim($w, 's'))) {
                    $score += mb_strlen($w);
                }
            }
            if ($score > $bestScore) {
                $best = $p;
                $bestScore = $score;
            }
        }
        if ($best) {
            return $best['summary'] . ' Plus de détails : ' . Http::abs($best['path']) . ' — ou demandez directement votre devis gratuit.';
        }
        if (preg_match('/horaire|ouvert|heure/u', $qn)) {
            return 'Nous sommes joignables au ' . $site['phone'] . ' : ' . $site['hours'] . '.';
        }
        return (string) ($ui['bot']['fallback'] ?? '');
    }

    private static function log(string $session, string $q, string $a, string $source): void
    {
        if (!Settings::get('bot_log', true)) {
            return;
        }
        $sid = preg_match('/^[a-z0-9]{8,40}$/', $session) ? $session : 'anon-' . substr(hash('sha256', Http::ip() . date('Ymd')), 0, 10);
        Store::update('botlog', function (array $log) use ($sid, $q, $a, $source) {
            $now = date('c');
            if (!isset($log[$sid])) {
                $log[$sid] = ['id' => $sid, 'start' => $now, 'msgs' => []];
            }
            $log[$sid]['last'] = $now;
            $log[$sid]['msgs'][] = ['q' => mb_substr($q, 0, 1200), 'a' => mb_substr($a, 0, 2000), 'src' => $source, 'at' => $now];
            $log[$sid]['msgs'] = array_slice($log[$sid]['msgs'], -40);
            uasort($log, fn($x, $y) => strcmp((string) $y['last'], (string) $x['last']));
            return array_slice($log, 0, 400, true);
        });
    }
}
