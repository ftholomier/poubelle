<?php
// Appels à l'API Gemini (Google) en cURL natif :
//   - liste des modèles disponibles pour la clé
//   - transcription d'un morceau audio
//   - génération de la fiche, de l'annonce et des deux rapports
// Sans clé API configurée, des données de démonstration sont renvoyées.

require_once __DIR__ . '/demo.php';

const GEMINI_API = 'https://generativelanguage.googleapis.com/v1beta';

function gemini_request(string $method, string $path, string $key, ?array $body = null, int $timeout = 60): array
{
    $ch = curl_init(GEMINI_API . $path);
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

/** Appelle generateContent et renvoie le texte de la réponse. */
function gemini_generate(string $model, array $body, int $timeout): string
{
    global $CONFIG;
    $data = gemini_request('POST', '/models/' . rawurlencode($model) . ':generateContent', $CONFIG['gemini_api_key'], $body, $timeout);

    if (!empty($data['promptFeedback']['blockReason'])) {
        throw new RuntimeException('Gemini a bloqué la demande (' . $data['promptFeedback']['blockReason'] . ').');
    }
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

/** Modèles utilisables avec cette clé (ceux qui acceptent generateContent). */
function gemini_models(string $key): array
{
    $models = [];
    $page = '';
    do {
        $data = gemini_request('GET', '/models?pageSize=1000' . ($page ? '&pageToken=' . urlencode($page) : ''), $key);
        foreach ($data['models'] ?? [] as $m) {
            $id = preg_replace('#^models/#', '', $m['name']);
            if (!in_array('generateContent', $m['supportedGenerationMethods'] ?? [], true)) continue;
            if (preg_match('/embedding|imagen|image|tts|aqa|veo|learnlm/i', $id)) continue; // modèles hors sujet
            $models[] = [
                'id'          => $id,
                'nom'         => $m['displayName'] ?? $id,
                'description' => $m['description'] ?? '',
                'entree'      => $m['inputTokenLimit'] ?? null,
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
        ],
        'required' => ['champs', 'titre_annonce', 'annonce', 'rapport_agent', 'rapport_vendeur'],
        'propertyOrdering' => ['champs', 'titre_annonce', 'annonce', 'rapport_agent', 'rapport_vendeur'],
    ];
}

function generation_prompt(array $agent): string
{
    global $CONFIG;
    $champs = fields_prompt();
    $agence = $CONFIG['agence'];
    return <<<PROMPT
Tu assistes un agent immobilier ({$agent['nom']}, agence « {$agence} »). On te donne la transcription brute d'une visite de bien chez un vendeur : agent et vendeur mélangés, sans distinction des voix, avec d'éventuelles erreurs de reconnaissance vocale.

Produis les cinq éléments suivants.

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

Texte brut uniquement (pas de markdown, pas d'astérisques). Rédige en français.
PROMPT;
}

function generate_documents(string $transcript, array $agent, string $titre = ''): array
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
            'parts' => [['text' => ($titre !== '' ? "Bien visité (saisi par l'agent) : $titre\n\n" : '') . "Transcription de la visite :\n\n" . $transcript]],
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
