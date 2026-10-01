<?php
// Appels aux IA en cURL natif :
//   - transcription d'un morceau audio (OpenAI)
//   - génération de la fiche, de l'annonce et des deux rapports (Claude)
// Sans clé API configurée, des données de démonstration sont renvoyées.

require_once __DIR__ . '/demo.php';

function http_post(string $url, array $headers, mixed $body, int $timeout): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT        => $timeout,
    ]);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($raw === false) throw new RuntimeException("Connexion impossible : $error");
    $data = json_decode($raw, true);
    if ($status >= 400) {
        $msg = $data['error']['message'] ?? substr($raw, 0, 300);
        throw new RuntimeException("Erreur API ($status) : $msg");
    }
    return $data ?? [];
}

// ---------- Transcription ----------

function transcribe_audio(string $path, string $mime, int $index = 0): string
{
    global $CONFIG;
    if (empty($CONFIG['openai_api_key'])) {
        usleep(400_000);
        return demo_transcript_chunk($index);
    }

    $ext = AUDIO_TYPES[$mime] ?? 'webm';
    $data = http_post(
        'https://api.openai.com/v1/audio/transcriptions',
        ['Authorization: Bearer ' . $CONFIG['openai_api_key']],
        [
            'file'     => new CURLFile($path, $mime, "morceau.$ext"),
            'model'    => $CONFIG['transcribe_model'],
            'language' => 'fr',
            // Vocabulaire métier pour aider la reconnaissance
            'prompt'   => 'Visite immobilière : DPE, GES, taxe foncière, copropriété, charges, double vitrage, pompe à chaleur, séjour, mezzanine, m².',
        ],
        180
    );
    return trim($data['text'] ?? '');
}

// ---------- Génération (Claude) ----------

function generation_schema(): array
{
    return [
        'type' => 'object',
        'additionalProperties' => false,
        'required' => ['champs', 'titre_annonce', 'annonce', 'rapport_agent', 'rapport_vendeur'],
        'properties' => [
            'champs' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['cle', 'valeur', 'citation'],
                    'properties' => [
                        'cle'      => ['type' => 'string', 'enum' => field_keys()],
                        'valeur'   => ['type' => 'string'],
                        'citation' => ['type' => 'string'],
                    ],
                ],
            ],
            'titre_annonce'   => ['type' => 'string'],
            'annonce'         => ['type' => 'string'],
            'rapport_agent'   => ['type' => 'string'],
            'rapport_vendeur' => ['type' => 'string'],
        ],
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
    if (empty($CONFIG['anthropic_api_key'])) {
        sleep(2);
        return demo_generation($agent);
    }

    $body = [
        'model'         => $CONFIG['claude_model'],
        'max_tokens'    => 16000,
        'fallbacks'     => 'default', // si le modèle décline, l'API bascule sur un autre modèle
        'output_config' => [
            'effort' => 'medium',
            'format' => ['type' => 'json_schema', 'schema' => generation_schema()],
        ],
        'system'   => generation_prompt($agent),
        'messages' => [['role' => 'user', 'content' => ($titre !== '' ? "Bien visité (saisi par l'agent) : $titre\n\n" : '') . "Transcription de la visite :\n\n" . $transcript]],
    ];

    $data = http_post(
        'https://api.anthropic.com/v1/messages',
        [
            'Content-Type: application/json',
            'x-api-key: ' . $CONFIG['anthropic_api_key'],
            'anthropic-version: 2023-06-01',
            'anthropic-beta: server-side-fallback-2026-07-01',
        ],
        json_encode($body, JSON_UNESCAPED_UNICODE),
        300
    );

    if (($data['stop_reason'] ?? '') === 'refusal') throw new RuntimeException("L'IA a refusé d'analyser cette visite.");
    if (($data['stop_reason'] ?? '') === 'max_tokens') throw new RuntimeException('Réponse de l\'IA tronquée, réessayez.');

    foreach ($data['content'] ?? [] as $block) {
        if (($block['type'] ?? '') === 'text') {
            $result = json_decode($block['text'], true);
            if (is_array($result)) return $result;
        }
    }
    throw new RuntimeException("Réponse de l'IA illisible.");
}
