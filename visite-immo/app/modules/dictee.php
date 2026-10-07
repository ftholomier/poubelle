<?php
// Dictée courte et universelle : l'agent parle 30 secondes, l'IA transcrit et range l'information dans le bon
// format (fiche acquéreur, retour de visite, bilan d'appel, offre d'achat, commande vocale…).
// Chaque module déclare son type de dictée avec type_dictee().

$TYPES_DICTEE = [];

/**
 * @param array    $schema  schéma de sortie (format Gemini, OBJECT)
 * @param string   $consigne ce que l'IA doit extraire
 * @param callable $demo    fn (string $texte): array, réponse simulée sans clé Gemini
 * @param ?callable $contexte fn (array $agent, array $in): string, informations utiles (dossier, acquéreurs…)
 */
function type_dictee(string $type, array $schema, string $consigne, callable $demo, ?callable $contexte = null): void
{
    global $TYPES_DICTEE;
    $TYPES_DICTEE[$type] = compact('schema', 'consigne', 'demo', 'contexte');
}

/** Transcrit (si audio) puis extrait les données structurées. Renvoie [transcription, données]. */
function traiter_dictee(array $agent, string $type, ?array $fichier, string $texte, array $in = []): array
{
    global $TYPES_DICTEE, $CONFIG;
    $t = $TYPES_DICTEE[$type] ?? fail(400, 'Type de dictée inconnu.');
    if ($fichier && ($fichier['error'] ?? 1) === UPLOAD_ERR_OK) {
        if ($fichier['size'] > 15 * 1024 * 1024) fail(413, 'Enregistrement trop long.');
        $mime = strtolower(trim(explode(';', (string) ($_POST['type_audio'] ?? $fichier['type']))[0]));
        if (!isset(AUDIO_TYPES[$mime])) $mime = 'audio/webm';
        if (empty($CONFIG['gemini_api_key'])) $texte = $texte ?: ($in['demo_texte'] ?? '');
        else {
            $texte = transcribe_audio($fichier['tmp_name'], $mime, 0);
            flush_usage($agent, null, 'transcription');
        }
    }
    $texte = trim($texte);
    $contexte = $t['contexte'] ? ($t['contexte'])($agent, $in) : '';
    if (empty($CONFIG['gemini_api_key'])) return [$texte ?: '(dictée simulée en mode démo)', ($t['demo'])($texte, $in)];
    if ($texte === '') fail(400, "Rien n'a été entendu. Réessayez en parlant près du téléphone.");
    $json = gemini_generate($CONFIG['modele_analyse'], [
        'systemInstruction' => ['parts' => [['text' => "Tu assistes {$agent['nom']}, agent immobilier chez {$CONFIG['agence']}. Aujourd'hui : " . date('l d/m/Y H:i') . ". " . $t['consigne']
            . "\nN'invente rien : laisse vide ce qui n'est pas dit. Dates au format AAAA-MM-JJ, heures HH:MM, montants en euros sans espace."]]],
        'contents' => [['role' => 'user', 'parts' => [['text' => ($contexte ? "Contexte :\n$contexte\n\n" : '') . "Dictée de l'agent :\n$texte"]]]],
        'generationConfig' => ['responseMimeType' => 'application/json', 'responseSchema' => $t['schema']],
    ], 60);
    flush_usage($agent, null, 'analyse');
    $d = json_decode($json, true);
    if (!is_array($d)) throw new RuntimeException('Dictée non comprise, réessayez.');
    return [$texte, $d];
}

/** Schéma Gemini : raccourcis */
function s_txt(): array { return ['type' => 'STRING']; }
function s_num(): array { return ['type' => 'NUMBER']; }
function s_obj(array $props, array $requis = []): array { return ['type' => 'OBJECT', 'properties' => $props] + ($requis ? ['required' => $requis] : []); }
function s_liste(array $items): array { return ['type' => 'ARRAY', 'items' => $items]; }
function s_enum(array $valeurs): array { return ['type' => 'STRING', 'enum' => $valeurs]; }

route('POST dictee', function () {
    $me = require_user();
    $type = (string) ($_GET['type'] ?? '');
    $in = $_POST ?: json_input();
    [$transcription, $donnees] = traiter_dictee($me, $type, $_FILES['audio'] ?? null, (string) ($in['texte'] ?? ''), $in);
    global $APRES_DICTEE;
    $resultat = isset($APRES_DICTEE[$type]) ? ($APRES_DICTEE[$type])($me, $donnees, $in, $transcription) : null;
    send_json(['transcription' => $transcription, 'donnees' => $donnees, 'resultat' => $resultat]);
});

$APRES_DICTEE = [];
/** Action automatique après une dictée (créer l'acquéreur, enregistrer le retour…) : fn ($agent, $donnees, $in, $transcription) */
function apres_dictee(string $type, callable $fn): void
{
    global $APRES_DICTEE;
    $APRES_DICTEE[$type] = $fn;
}
