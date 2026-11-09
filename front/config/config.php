<?php
/*
 * Configuration du site Renov'Est.
 * Les valeurs propres au serveur se mettent dans config/config.local.php
 * (non versionné, jamais remplacé par une mise à jour) qui retourne un tableau partiel.
 */
$config = [
    // URL publique du site, sans slash final (liens des e-mails, sitemap, partage).
    'site_url'    => 'https://renovest.fr',

    // Clé de création du premier compte administrateur (/admin).
    // Vide : une clé est générée dans storage/data/setup-key.txt (à lire par FTP).
    'setup_key'   => '',

    // Expéditeur technique des e-mails (domaine du site pour éviter le spam).
    'mail_from'   => 'no-reply@renovest.fr',

    // Durée d'une session du back-office (secondes).
    'session_ttl' => 7200,

    // Dossier des données (hors de public/).
    'storage'     => __DIR__ . '/../storage',
];

$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    $config = array_replace($config, (array) require $local);
}
return $config;
