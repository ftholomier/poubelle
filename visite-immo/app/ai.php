<?php
// Appels à l'API Gemini (Google) en cURL natif :
//   - liste des modèles disponibles pour la clé
//   - transcription d'un morceau audio
//   - génération de la fiche, de l'annonce et des deux rapports
// Sans clé API configurée, des données de démonstration sont renvoyées.

require_once __DIR__ . '/demo.php';


function gemini_request(string $method, string $path, string $key, ?array $body = null, int $timeout = 60): array
{
    $ch = curl_init(api_base('gemini', 'https://generativelanguage.googleapis.com/v1beta') . $path);
    $headers = ['x-goog-api-key: ' . $key];
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT        => $timeout,
    ]);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($raw === false) throw new RuntimeException("Connexion à Gemini impossible : $error");
    $data = json_decode($raw, true);
    if ($status >= 400) {
        $msg = $data['error']['message'] ?? substr($raw, 0, 300);
        if ($status === 400 && str_contains($msg, 'API key')) $msg = 'Clé API Gemini invalide.';
        throw new RuntimeException("Gemini ($status) : $msg");
    }
    return $data ?? [];
}

// ---------- Coûts (estimation à partir des jetons renvoyés par Gemini) ----------

$AI_USAGE = []; // appels de la requête en cours : [modèle, coût en €, jetons]

/** Tarifs indicatifs en $ par million de jetons [entrée, sortie] selon le type de modèle et la modalité. */
function tarif(string $model, bool $live = false): array
{
    if ($live) return ['TEXT' => [0.50, 2.00], 'AUDIO' => [3.00, 12.00]];
    if (str_contains($model, 'pro')) return ['TEXT' => [1.25, 10.00], 'AUDIO' => [1.25, 10.00]];
    if (str_contains($model, 'lite')) return ['TEXT' => [0.10, 0.40], 'AUDIO' => [0.30, 0.40]];
    return ['TEXT' => [0.30, 2.50], 'AUDIO' => [1.00, 2.50]];
}

/** Coût estimé en euros d'un usageMetadata Gemini. */
function cout_usage(string $model, array $u, bool $live = false): float
{
    $t = tarif($model, $live);
    $usd = 0.0;
    $detailsIn = $u['promptTokensDetails'] ?? [['modality' => 'TEXT', 'tokenCount' => $u['promptTokenCount'] ?? 0]];
    foreach ($detailsIn as $d) $usd += ($d['tokenCount'] ?? 0) * ($t[$d['modality'] ?? 'TEXT'][0] ?? $t['TEXT'][0]);
    $detailsOut = $u['responseTokensDetails'] ?? $u['candidatesTokensDetails'] ?? [['modality' => 'TEXT', 'tokenCount' => ($u['responseTokenCount'] ?? $u['candidatesTokenCount'] ?? 0)]];
    foreach ($detailsOut as $d) $usd += ($d['tokenCount'] ?? 0) * ($t[$d['modality'] ?? 'TEXT'][1] ?? $t['TEXT'][1]);
    $usd += ($u['thoughtsTokenCount'] ?? 0) * $t['TEXT'][1]; // le raisonnement est facturé comme de la sortie
    return round($usd / 1e6 * 0.9, 5); // ≈ conversion $ → €
}

/**
 * Enregistre les coûts : total du mois (data/couts/AAAA-MM.json, par type et par agent) et total de la visite.
 * $type : transcription, analyse, conversation.
 */
function log_cout(array $user, ?string $visitId, string $type, float $euros): void
{
    if ($euros <= 0) return;
    update_json(DATA_DIR . '/couts/' . date('Y-m') . '.json', function (array $c) use ($user, $type, $euros) {
        $c['total'] = round(($c['total'] ?? 0) + $euros, 5);
        $c['par_type'][$type] = round(($c['par_type'][$type] ?? 0) + $euros, 5);
        $c['par_agent'][$user['id']] = round(($c['par_agent'][$user['id']] ?? 0) + $euros, 5);
        return $c;
    });
    if ($visitId) {
        try {
            update_visit($user, $visitId, function (array $v) use ($type, $euros) {
                $v['couts'][$type] = round(($v['couts'][$type] ?? 0) + $euros, 5);
                return $v;
            });
        } catch (Throwable) {
            // visite supprimée entre-temps : seul le total du mois compte
        }
    }
}

/** Reporte les coûts des appels Gemini faits pendant la requête. */
function flush_usage(array $user, ?string $visitId, string $type): void
{
    global $AI_USAGE;
    $total = array_sum(array_column($AI_USAGE, 1));
    $AI_USAGE = [];
    log_cout($user, $visitId, $type, $total);
}

/** Jeton temporaire à usage unique pour ouvrir une conversation Live depuis le téléphone (la clé reste sur le serveur). */
function gemini_live_token(): string
{
    global $CONFIG;
    $ch = curl_init(api_base('gemini_live', 'https://generativelanguage.googleapis.com/v1alpha') . '/auth_tokens');
    $body = [
        'uses'                 => 1,
        'expireTime'           => gmdate('Y-m-d\TH:i:s\Z', time() + 30 * 60), // durée maximale de la conversation
        'newSessionExpireTime' => gmdate('Y-m-d\TH:i:s\Z', time() + 120),     // délai pour l'ouvrir
    ];
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'x-goog-api-key: ' . $CONFIG['gemini_api_key']],
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 20,
    ]);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode((string) $raw, true);
    if ($status >= 400 || empty($data['name'])) {
        throw new RuntimeException('Impossible d\'ouvrir la conversation Gemini : ' . ($data['error']['message'] ?? "erreur $status"));
    }
    return $data['name'];
}

/** Appelle generateContent et renvoie le texte de la réponse. */
function gemini_generate(string $model, array $body, int $timeout): string
{
    global $CONFIG;
    $data = gemini_request('POST', '/models/' . rawurlencode($model) . ':generateContent', $CONFIG['gemini_api_key'], $body, $timeout);

    if (!empty($data['promptFeedback']['blockReason'])) {
        throw new RuntimeException('Gemini a bloqué la demande (' . $data['promptFeedback']['blockReason'] . ').');
    }
    global $AI_USAGE;
    if (!empty($data['usageMetadata'])) $AI_USAGE[] = [$model, cout_usage($model, $data['usageMetadata']), $data['usageMetadata']];
    $candidate = $data['candidates'][0] ?? null;
    if (!$candidate) throw new RuntimeException('Gemini n\'a renvoyé aucune réponse.');

    $text = '';
    foreach ($candidate['content']['parts'] ?? [] as $part) {
        if (empty($part['thought']) && isset($part['text'])) $text .= $part['text']; // on ignore le raisonnement
    }
    $reason = $candidate['finishReason'] ?? 'STOP';
    if ($reason === 'MAX_TOKENS') throw new RuntimeException('Réponse de Gemini tronquée (trop longue), réessayez.');
    if ($reason !== 'STOP' && trim($text) === '') throw new RuntimeException("Gemini n'a pas pu répondre ($reason).");
    return $text;
}

/** Modèles utilisables avec cette clé : génération (generateContent) et conversation en direct (bidiGenerateContent). */
function gemini_models(string $key): array
{
    $models = [];
    $page = '';
    do {
        $data = gemini_request('GET', '/models?pageSize=1000' . ($page ? '&pageToken=' . urlencode($page) : ''), $key);
        foreach ($data['models'] ?? [] as $m) {
            $id = preg_replace('#^models/#', '', $m['name']);
            $methodes = $m['supportedGenerationMethods'] ?? [];
            $generation = in_array('generateContent', $methodes, true);
            $live = in_array('bidiGenerateContent', $methodes, true);
            if (!$generation && !$live) continue;
            if (preg_match('/embedding|imagen|image|tts|aqa|veo|learnlm/i', $id)) continue; // modèles hors sujet
            $models[] = [
                'id'          => $id,
                'nom'         => $m['displayName'] ?? $id,
                'description' => $m['description'] ?? '',
                'entree'      => $m['inputTokenLimit'] ?? null,
                'generation'  => $generation,
                'live'        => $live,
                // les modèles « native audio » ne savent répondre qu'en voix : plus chers
                'audio_natif' => (bool) preg_match('/native-audio|native_audio/i', $id),
            ];
        }
        $page = $data['nextPageToken'] ?? '';
    } while ($page);

    // Les « gemini » d'abord, versions les plus récentes en premier
    usort($models, fn ($a, $b) => (str_starts_with($b['id'], 'gemini') <=> str_starts_with($a['id'], 'gemini'))
        ?: strnatcasecmp($b['id'], $a['id']));
    return $models;
}

// ---------- Transcription ----------

function transcribe_audio(string $path, string $mime, int $index = 0): string
{
    global $CONFIG;
    if (empty($CONFIG['gemini_api_key'])) {
        usleep(400_000);
        return demo_transcript_chunk($index);
    }

    $prompt = "Transcris intégralement et fidèlement cet enregistrement d'une visite immobilière, en français. "
        . "Écris uniquement les paroles prononcées, sans commentaire, sans horodatage et sans nom de locuteur. "
        . "Vocabulaire possible : DPE, GES, taxe foncière, copropriété, charges, double vitrage, pompe à chaleur, séjour, mezzanine, m². "
        . "Si l'audio est vide ou inaudible, ne réponds rien.";

    $text = gemini_generate($CONFIG['modele_transcription'], [
        'contents' => [[
            'role'  => 'user',
            'parts' => [
                ['text' => $prompt],
                ['inline_data' => ['mime_type' => $mime, 'data' => base64_encode((string) file_get_contents($path))]],
            ],
        ]],
    ], 180);
    return trim($text);
}

// ---------- Génération ----------

/** Schéma de la réponse attendue (format OpenAPI utilisé par Gemini). */
function generation_schema(): array
{
    $texte = ['type' => 'STRING'];
    return [
        'type' => 'OBJECT',
        'properties' => [
            'champs' => [
                'type'  => 'ARRAY',
                'items' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'cle'      => ['type' => 'STRING', 'enum' => field_keys()],
                        'valeur'   => $texte,
                        'citation' => $texte,
                    ],
                    'required' => ['cle', 'valeur', 'citation'],
                    'propertyOrdering' => ['cle', 'valeur', 'citation'],
                ],
            ],
            'titre_annonce'   => $texte,
            'annonce'         => $texte,
            'rapport_agent'   => $texte,
            'rapport_vendeur' => $texte,
            'points_forts'    => ['type' => 'ARRAY', 'items' => $texte],
            'pieces_plan'     => [
                'type'  => 'ARRAY',
                'items' => [
                    'type' => 'OBJECT',
                    'properties' => ['nom' => $texte, 'surface' => ['type' => 'NUMBER'], 'niveau' => $texte],
                    'required' => ['nom', 'niveau'],
                ],
            ],
            'posts' => [
                'type' => 'OBJECT',
                'properties' => ['instagram' => $texte, 'facebook' => $texte, 'linkedin' => $texte],
                'required' => ['instagram', 'facebook', 'linkedin'],
            ],
        ],
        'required' => ['champs', 'titre_annonce', 'annonce', 'rapport_agent', 'rapport_vendeur', 'points_forts', 'pieces_plan', 'posts'],
        'propertyOrdering' => ['champs', 'titre_annonce', 'annonce', 'rapport_agent', 'rapport_vendeur', 'points_forts', 'pieces_plan', 'posts'],
    ];
}

function generation_prompt(array $agent): string
{
    global $CONFIG;
    $champs = fields_prompt();
    $agence = $CONFIG['agence'];
    return <<<PROMPT
Tu assistes un agent immobilier ({$agent['nom']}, agence « {$agence} »). On te donne la transcription brute d'une visite de bien chez un vendeur : agent et vendeur mélangés, sans distinction des voix, avec d'éventuelles erreurs de reconnaissance vocale.

Produis les éléments suivants.

1. « champs » : la fiche du bien.
- Ne remplis un champ que si l'information est dite ou se déduit sans ambiguïté. Dans le doute, n'inclus pas le champ.
- Si une valeur est corrigée plus loin ("non, en fait 3 chambres"), garde la dernière.
- Nombres : chiffres uniquement, sans unité ni espace (ex. "4", "120", "350000"). Montants en euros.
- Oui/non : "oui" ou "non". Champs à choix : exactement une des valeurs proposées.
- « citation » : la courte phrase de la transcription qui justifie la valeur, recopiée telle quelle.
Champs disponibles :
{$champs}

2. « titre_annonce » : titre d'annonce accrocheur, 60 caractères maximum (ex. "Maison familiale 4 chambres avec jardin exposé sud").

3. « annonce » : description pour les portails (SeLoger, Leboncoin…), environ 1 500 caractères, ton professionnel et vendeur, en paragraphes. N'invente rien : uniquement ce qui a été dit. Ne mentionne ni le motif de vente, ni les défauts, ni le nom du vendeur. Termine par les mentions disponibles (DPE, taxe foncière, charges).

4. « rapport_agent » : rapport interne, franc et opérationnel, pour l'agent. Sections avec titres en majuscules suivis de puces "- " :
SYNTHÈSE / POINTS FORTS / POINTS FAIBLES & RISQUES / TRAVAUX / VENDEUR (motivation, délai, souplesse sur le prix perçue) / PRIX (avis sur le prix demandé au vu de ce qui a été dit) / À VÉRIFIER (documents à demander, informations manquantes ou contradictoires) / PROCHAINES ÉTAPES.

5. « rapport_vendeur » : compte rendu de visite à envoyer au vendeur, sous forme de courrier. Commence par "Bonjour" + nom du vendeur s'il est connu. Ton chaleureux, professionnel et valorisant : remercie pour l'accueil, résume les caractéristiques et atouts du bien relevés, mentionne avec tact les points à préparer ou documents à fournir, propose la suite. Aucune remarque interne (pas d'avis sur la motivation du vendeur, pas de critique du prix). Signe "{$agent['nom']}, {$agence}".

6. « points_forts » : 3 à 5 atouts du bien, 6 mots maximum chacun (ex. "Jardin plein sud de 800 m²").

7. « pieces_plan » : la liste des pièces citées pendant la visite, pour dessiner un croquis de plan : nom (ex. "Séjour", "Chambre 1", "Cuisine"), surface en m² si elle a été dite (sinon 0), niveau ("RDC", "Étage 1", "Sous-sol"…). N'invente pas de pièce ; si rien n'est dit, liste vide.

8. « posts » : publications pour les réseaux sociaux de l'agence, avec émojis sobres et hashtags locaux : « instagram » (accroche + 4 lignes + 5 hashtags), « facebook » (5 à 6 lignes, invitation à contacter l'agence), « linkedin » (ton professionnel, 4 lignes). Sans prix si non connu, sans adresse exacte.

Texte brut uniquement (pas de markdown, pas d'astérisques). Rédige en français.
PROMPT;
}

function generate_documents(string $transcript, array $agent, string $titre = '', array $connus = []): array
{
    global $CONFIG;
    if (empty($CONFIG['gemini_api_key'])) {
        sleep(2);
        return demo_generation($agent);
    }

    $text = gemini_generate($CONFIG['modele_analyse'], [
        'systemInstruction' => ['parts' => [['text' => generation_prompt($agent)]]],
        'contents' => [[
            'role'  => 'user',
            'parts' => [['text' => ($titre !== '' ? "Bien visité (saisi par l'agent) : $titre\n\n" : '')
                . ($connus ? "Informations validées par l'agent (prioritaires sur la transcription, à reprendre telles quelles) :\n" . implode("\n", array_map(fn ($k, $v) => "- $k : $v", array_keys($connus), $connus)) . "\n\n" : '')
                . "Transcription de la visite :\n\n" . $transcript]],
        ]],
        'generationConfig' => [
            'responseMimeType' => 'application/json',
            'responseSchema'   => generation_schema(),
        ],
    ], 300);

    $result = json_decode($text, true);
    if (!is_array($result) || !isset($result['champs'])) throw new RuntimeException('Réponse de Gemini illisible, réessayez.');
    return $result;
}
