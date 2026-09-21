<?php
/**
 * Copier en config/secrets.php (jamais versionné) et renseigner.
 * Toute clé absente désactive proprement la fonctionnalité concernée.
 */
declare(strict_types=1);

return [
    'gemini_api_key'    => '',   // assistant Régie
    'translate_api_key' => '',   // Google Cloud Translation (langues hors FR)
    'places_api_key'    => '',   // avis Google
    'places_id'         => '',   // identifiant de fiche Google de intermittent.fr
    'adsense_client'    => '',   // ca-pub-XXXXXXXXXXXXXXXX
    'adsense_slots'     => [     // identifiant numérique par emplacement
        'home_top' => '', 'home_mid' => '', 'list_side' => '', 'list_infeed' => '',
        'job_below' => '', 'profile_side' => '', 'dir_bottom' => '',
    ],
    // --- offres externes -------------------------------------------------
    // Indeed : l'ancienne API publisher est fermée. Renseignez de préférence
    // `indeed_feed_url` (flux fourni dans le cadre d'un accord partenaire) ;
    // le publisher ID n'est conservé que pour un éventuel accès historique.
    'indeed_feed_url'   => '',
    'indeed_api_base'   => '',
    'indeed_publisher_id' => '4142992219966569',   // repris de l'ancien site

    // France Travail : gratuit et libre-service sur francetravail.io,
    // souscrire à l'API « Offres d'emploi v2 ».
    'francetravail_client_id'     => '',
    'francetravail_client_secret' => '',

    // Agrégateurs de repli, tous deux en libre-service.
    'adzuna_app_id'     => '',
    'adzuna_app_key'    => '',
    'jooble_key'        => '',

    'mail_from'         => 'no-reply@intermittent.fr',
    'app_key'           => '',   // 32+ octets aléatoires : signature des jetons
];
