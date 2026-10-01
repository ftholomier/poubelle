<?php
// Valeurs par défaut. Inutile d'éditer ce fichier : tout se règle dans l'appli
// (menu ☰ → Réglages, réservé aux administrateurs). Les réglages faits dans l'appli
// sont enregistrés dans app/settings.json et remplacent ces valeurs.
// Sans clé Gemini, l'appli fonctionne en mode démo (transcription et analyse simulées).

return [
    // Clé API Gemini : https://aistudio.google.com/apikey
    'gemini_api_key'       => getenv('GEMINI_API_KEY') ?: '',

    // Modèle qui rédige la fiche, l'annonce et les rapports
    'modele_analyse'       => 'gemini-2.5-flash',

    // Modèle qui transcrit l'audio de la visite
    'modele_transcription' => 'gemini-2.5-flash',

    // Dossier de stockage : chemin absolu, ou relatif au dossier de l'appli. Jamais dans public/.
    'data_dir'             => 'data',

    // Nom de l'agence, utilisé pour signer le compte rendu vendeur : « Nom de l'agent, <agence> »
    'agence'               => 'Mon Agence Immobilière',
];
