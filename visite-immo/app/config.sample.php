<?php
// Copier ce fichier en config.php et renseigner les clés.
// Sans clé, l'appli fonctionne en mode démo (transcription et analyse simulées).

return [
    // Analyse (fiche, annonce, rapports) : https://console.anthropic.com
    'anthropic_api_key' => getenv('ANTHROPIC_API_KEY') ?: '',
    'claude_model'      => 'claude-opus-5-5',

    // Transcription audio : https://platform.openai.com
    'openai_api_key'    => getenv('OPENAI_API_KEY') ?: '',
    'transcribe_model'  => 'whisper-1',

    // Dossier de stockage (hors du dossier public)
    'data_dir'          => __DIR__ . '/../data',

    // Utilisé pour signer le rapport envoyé au vendeur
    'agence'            => 'Mon Agence Immobilière',
];
