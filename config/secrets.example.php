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
    'mail_from'         => 'no-reply@intermittent.fr',
    'app_key'           => '',   // 32+ octets aléatoires : signature des jetons
];
