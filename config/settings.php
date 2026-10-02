<?php
/**
 * Schéma des réglages modifiables dans le back-office (Réglages).
 *
 * Types : text, textarea, email, url, number, bool, select, secret, date, color.
 * Un champ « secret » est chiffré au repos et n'est jamais réaffiché en clair.
 * « options_from » indique une liste remplie dynamiquement (ex. modèles Gemini).
 */

return [
    'general' => [
        'label' => 'Général',
        'fields' => [
            'site_name' => ['label' => 'Nom du site', 'type' => 'text', 'default' => 'Sochaux Rétro'],
            'site_tagline' => ['label' => 'Signature', 'type' => 'text', 'default' => 'Le musée en ligne du FCSM'],
            'base_url' => ['label' => 'Adresse du site', 'type' => 'url', 'default' => 'https://www.fcsochauxretro.com', 'help' => 'Sans barre oblique finale.'],
            'contact_email' => ['label' => 'E-mail de réception des messages', 'type' => 'email', 'default' => ''],
            'maintenance' => ['label' => 'Mode maintenance', 'type' => 'bool', 'default' => false, 'help' => 'Le site public affiche une page d’attente ; le back-office reste accessible.'],
            'front_password' => ['label' => 'Mot de passe d’accès au site public (pré-lancement)', 'type' => 'secret', 'default' => '', 'help' => 'Laisser vide pour un site ouvert à tous.'],
            'debug' => ['label' => 'Afficher les erreurs (développement)', 'type' => 'bool', 'default' => false],
        ],
    ],
    'home' => [
        'label' => 'Accueil',
        'fields' => [
            'intro_title' => ['label' => 'Titre d’introduction', 'type' => 'text', 'default' => 'Musée numérique FCSM - Sochaux Rétro'],
            'intro_text' => ['label' => 'Texte d’introduction', 'type' => 'textarea', 'default' => "Sochaux Rétro : l'histoire de notre club s'écrit maintenant ! Bienvenue au musée numérique de notre club, le Football Club Sochaux Montbéliard - FCSM.\nUn projet de Sochaux Rétro !"],
            'slider_count' => ['label' => 'Nombre de slides tirées au hasard dans « À la une »', 'type' => 'number', 'default' => 5],
            'counter_community' => ['label' => 'Compteur : membres de la communauté', 'type' => 'text', 'default' => '11000+'],
            'counter_videos' => ['label' => 'Compteur : vidéos YouTube', 'type' => 'text', 'default' => '1400+'],
            'counter_players_db' => ['label' => 'Compteur : joueurs présents dans la base', 'type' => 'text', 'default' => '1000+'],
            'centenary_date' => ['label' => 'Date du centenaire (compte à rebours)', 'type' => 'date', 'default' => '2028-05-20'],
            'centenary_text' => ['label' => 'Texte du compte à rebours', 'type' => 'textarea', 'default' => 'Le FCSM fêtera ses 100 ans. Aidez-nous à écrire son histoire avant le centenaire !'],
        ],
    ],
    'social' => [
        'label' => 'Réseaux sociaux',
        'fields' => [
            'facebook' => ['label' => 'Facebook', 'type' => 'url', 'default' => 'https://www.facebook.com/groups/fcsmvintage'],
            'instagram' => ['label' => 'Instagram', 'type' => 'url', 'default' => 'https://instagram.com/sochauxretro'],
            'x' => ['label' => 'X (Twitter)', 'type' => 'url', 'default' => 'https://x.com/SochauxRetro'],
            'youtube' => ['label' => 'YouTube', 'type' => 'url', 'default' => 'https://youtube.com/@SochauxRetro'],
        ],
    ],
    'ai' => [
        'label' => 'Assistant IA',
        'fields' => [
            'enabled' => ['label' => 'Activer l’assistant (bulle en bas à droite)', 'type' => 'bool', 'default' => false],
            'gemini_api_key' => ['label' => 'Clé API Gemini', 'type' => 'secret', 'default' => '', 'help' => 'Clé Google AI Studio. Elle reste sur le serveur et n’est jamais envoyée au navigateur.'],
            'model' => ['label' => 'Modèle de réponse', 'type' => 'select', 'options_from' => 'gemini_generate_models', 'default' => ''],
            'embedding_model' => ['label' => 'Modèle d’embedding (recherche dans les données)', 'type' => 'select', 'options_from' => 'gemini_embedding_models', 'default' => ''],
            'assistant_name' => ['label' => 'Nom de l’assistant', 'type' => 'text', 'default' => 'Le guide du musée'],
            'welcome' => ['label' => 'Message d’accueil', 'type' => 'textarea', 'default' => 'Bonjour ! Je connais tous les matchs, joueurs et personnages du musée Sochaux Rétro. Posez-moi votre question.'],
            'system_prompt' => ['label' => 'Consignes de l’assistant', 'type' => 'textarea', 'default' => "Tu es le guide du musée numérique Sochaux Rétro, consacré à l'histoire du FC Sochaux-Montbéliard (FCSM).\nRéponds uniquement à partir des extraits du musée fournis. Si l'information n'y figure pas, dis-le simplement et propose une recherche proche.\nCite les fiches utilisées. Réponds dans la langue de la question, avec un ton chaleureux et précis."],
            'temperature' => ['label' => 'Créativité (0 = factuel, 1 = libre)', 'type' => 'number', 'default' => 0.3, 'step' => 0.1, 'min' => 0, 'max' => 1],
            'max_output_tokens' => ['label' => 'Longueur maximale des réponses (jetons)', 'type' => 'number', 'default' => 800],
            'context_chunks' => ['label' => 'Nombre d’extraits envoyés à Gemini par question', 'type' => 'number', 'default' => 8],
            'daily_limit' => ['label' => 'Questions par visiteur et par jour', 'type' => 'number', 'default' => 20],
            'log_questions' => ['label' => 'Conserver les questions posées (consultables dans le back-office)', 'type' => 'bool', 'default' => true],
            'log_retention_days' => ['label' => 'Durée de conservation des questions (jours)', 'type' => 'number', 'default' => 365],
        ],
    ],
    'translation' => [
        'label' => 'Traduction',
        'fields' => [
            'languages' => ['label' => 'Langues proposées (codes séparés par des virgules)', 'type' => 'text', 'default' => 'fr,en'],
            'auto_translate' => ['label' => 'Traduire automatiquement les contenus avec Gemini', 'type' => 'bool', 'default' => true, 'help' => 'Les traductions sont stockées et corrigeables ; le français reste la référence.'],
        ],
    ],
    'donations' => [
        'label' => 'Dons',
        'fields' => [
            'enabled' => ['label' => 'Activer les dons', 'type' => 'bool', 'default' => false],
            'mode' => ['label' => 'Mode', 'type' => 'select', 'options' => ['test' => 'Test (aucun prélèvement réel)', 'live' => 'Production'], 'default' => 'test'],
            'currency' => ['label' => 'Devise', 'type' => 'select', 'options' => ['eur' => 'Euro (€)'], 'default' => 'eur'],
            'goal_amount' => ['label' => 'Objectif de la collecte (€)', 'type' => 'number', 'default' => 10000],
            'goal_label' => ['label' => 'Intitulé de la collecte', 'type' => 'text', 'default' => 'Objectif centenaire 2028'],
            'goal_start' => ['label' => 'Début de la collecte (pour la jauge)', 'type' => 'date', 'default' => '2026-01-01'],
            'min_amount' => ['label' => 'Montant libre minimum (€)', 'type' => 'number', 'default' => 2],
            'stripe_public_key' => ['label' => 'Stripe : clé publique (pk_…)', 'type' => 'text', 'default' => ''],
            'stripe_secret_key' => ['label' => 'Stripe : clé secrète (sk_…)', 'type' => 'secret', 'default' => ''],
            'stripe_webhook_secret' => ['label' => 'Stripe : secret du webhook (whsec_…)', 'type' => 'secret', 'default' => '', 'help' => 'Adresse du webhook à déclarer chez Stripe : /api/dons/stripe/webhook'],
            'paypal_client_id' => ['label' => 'PayPal : Client ID', 'type' => 'text', 'default' => ''],
            'paypal_secret' => ['label' => 'PayPal : Secret', 'type' => 'secret', 'default' => ''],
            'paypal_webhook_id' => ['label' => 'PayPal : identifiant du webhook', 'type' => 'text', 'default' => '', 'help' => 'Adresse du webhook à déclarer chez PayPal : /api/dons/paypal/webhook'],
            'tax_receipts' => ['label' => 'Émettre des reçus fiscaux (association d’intérêt général)', 'type' => 'bool', 'default' => false],
            'org_name' => ['label' => 'Nom de l’association', 'type' => 'text', 'default' => 'Sochaux Rétro'],
            'org_address' => ['label' => 'Adresse de l’association', 'type' => 'textarea', 'default' => ''],
            'org_rna' => ['label' => 'N° RNA ou SIREN', 'type' => 'text', 'default' => ''],
            'org_signatory' => ['label' => 'Signataire des reçus (nom, qualité)', 'type' => 'text', 'default' => ''],
            'thanks_email' => ['label' => 'E-mail de remerciement', 'type' => 'textarea', 'default' => "Merci {prenom} !\n\nVotre don de {montant} aide Sochaux Rétro à préserver l'histoire du FCSM.\n\nL'équipe de Sochaux Rétro"],
        ],
    ],
    'mail' => [
        'label' => 'E-mail (SMTP)',
        'fields' => [
            'from_email' => ['label' => 'Adresse d’expédition', 'type' => 'email', 'default' => ''],
            'from_name' => ['label' => 'Nom d’expéditeur', 'type' => 'text', 'default' => 'Sochaux Rétro'],
            'smtp_host' => ['label' => 'Serveur SMTP', 'type' => 'text', 'default' => '', 'help' => 'Vide = fonction mail() de PHP.'],
            'smtp_port' => ['label' => 'Port', 'type' => 'number', 'default' => 587],
            'smtp_secure' => ['label' => 'Sécurité', 'type' => 'select', 'options' => ['tls' => 'STARTTLS (587)', 'ssl' => 'SSL (465)', '' => 'Aucune'], 'default' => 'tls'],
            'smtp_user' => ['label' => 'Identifiant', 'type' => 'text', 'default' => ''],
            'smtp_password' => ['label' => 'Mot de passe', 'type' => 'secret', 'default' => ''],
        ],
    ],
    'privacy' => [
        'label' => 'Cookies et RGPD',
        'fields' => [
            'cookie_text' => ['label' => 'Texte du bandeau cookies', 'type' => 'textarea', 'default' => 'Nous utilisons des cookies pour lire les vidéos YouTube et traiter les dons en ligne. Vous pouvez accepter ou refuser ces services.'],
            'analytics_id' => ['label' => 'Mesure d’audience (ID, facultatif)', 'type' => 'text', 'default' => ''],
            'legal_page' => ['label' => 'Adresse de la page Mentions légales', 'type' => 'text', 'default' => '/mentions-legales/'],
        ],
    ],
];
