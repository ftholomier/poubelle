<?php

declare(strict_types=1);

/**
 * Assistant du site (bot en bas à droite).
 *
 * Avec une clé API Anthropic réglée dans le back-office, les réponses viennent de Claude
 * (API Messages, appel HTTP natif en cURL) avec un prompt système construit à partir du
 * contenu du site : produits, agences, coordonnées, engagements. Sans clé, l'assistant
 * répond avec les réponses rapides réglées dans le back-office.
 * Il propose toujours de basculer vers le devis ou l'appel.
 */
final class Bot
{
    private const API = 'https://api.anthropic.com/v1/messages';
    private const VERSION = '2026-10-21';

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
        $key = Settings::secret('anthropic_key');
        $answer = null;
        $source = 'regles';
        if ($key !== '') {
            try {
                $answer = self::claude($key, $msgs);
                $source = 'ia';
            } catch (Throwable $e) {
                error_log('[renovest] assistant IA : ' . $e->getMessage());
            }
        }
        if ($answer === null || trim($answer) === '') {
            $answer = self::rules($question);
            $source = $source === 'ia' ? 'regles' : $source;
        }
        self::log($session, $question, $answer, $source);
        return ['text' => $answer, 'source' => $source];
    }

    /** Appel de l'API Messages de Claude. */
    private static function claude(string $key, array $msgs): string
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Extension PHP cURL absente');
        }
        $model = (string) Settings::get('bot_model', 'claude-opus-5-5');
        if (!isset(Settings::BOT_MODELS[$model])) {
            $model = 'claude-opus-5-5';
        }
        $body = [
            'model' => $model,
            'max_tokens' => 4096,
            'system' => self::systemPrompt(),
            'messages' => $msgs,
            // Réponses de discussion courtes : peu de réflexion suffit
            'output_config' => ['effort' => 'low'],
        ];
        $headers = [
            'content-type: application/json',
            'x-api-key: ' . $key,
            'anthropic-version: ' . self::VERSION,
        ];
        // Repli automatique côté serveur si un filtre de sécurité refuse la demande
        if ($model !== 'claude-haiku-5-5') {
            $body['fallbacks'] = 'default';
            $headers[] = 'anthropic-beta: server-side-fallback-2026-07-01';
        }
        $ch = curl_init(self::API);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 45,
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            throw new RuntimeException('API injoignable : ' . $err);
        }
        $j = json_decode((string) $raw, true);
        if ($code !== 200 || !is_array($j)) {
            $msg = is_array($j) ? (string) ($j['error']['message'] ?? '') : '';
            throw new RuntimeException('API code ' . $code . ($msg !== '' ? ' : ' . $msg : ''));
        }
        if (($j['stop_reason'] ?? '') === 'refusal') {
            return '';
        }
        $text = '';
        foreach ((array) ($j['content'] ?? []) as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= (string) $block['text'];
            }
        }
        return trim($text);
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
