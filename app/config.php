<?php
/**
 * Configuration applicative.
 * Toutes les valeurs sensibles proviennent du fichier .env (non versionné)
 * ou de data/settings.json (éditable depuis le back-office).
 */

declare(strict_types=1);

use App\Core\Env;

return [
    'app' => [
        'name'      => 'Le Comptable à Lunettes',
        'env'       => Env::get('APP_ENV', 'production'),
        'debug'     => Env::bool('APP_DEBUG', false),
        'url'       => rtrim(Env::get('APP_URL', ''), '/'),
        'timezone'  => Env::get('APP_TIMEZONE', 'Europe/Paris'),
        // Clé utilisée pour signer les jetons (CSRF longue durée, reset password).
        'key'       => Env::get('APP_KEY', ''),
    ],

    'paths' => [
        'data'     => DATA_PATH,
        'pages'    => DATA_PATH . '/pages',
        'i18n'     => DATA_PATH . '/i18n',
        'backups'  => DATA_PATH . '/backups',
        'runtime'  => DATA_PATH . '/runtime',
        'uploads'  => DATA_PATH . '/uploads',
        'logs'     => STORAGE_PATH . '/logs',
    ],

    'security' => [
        'session_name'        => 'lcal_session',
        'session_lifetime'    => 60 * 60 * 4,      // 4 h
        'idle_timeout'        => 60 * 45,          // 45 min d'inactivité
        'login_max_attempts'  => 5,
        'login_lockout'       => 60 * 15,          // 15 min
        'reset_token_ttl'     => 60 * 60,          // 1 h
        'password_min_length' => 10,
        'hash_algo'           => PASSWORD_ARGON2ID,
        'force_https'         => Env::bool('FORCE_HTTPS', false),
        'trusted_proxies'     => array_filter(array_map('trim', explode(',', Env::get('TRUSTED_PROXIES', '')))),
    ],

    'storage' => [
        // Nombre de versions conservées par fichier JSON (reprise de contenu).
        'revisions'      => 30,
        'lock_timeout'   => 5,     // secondes d'attente max sur un flock
        'atomic_writes'  => true,
    ],

    'uploads' => [
        'max_size'       => 12 * 1024 * 1024, // 12 Mo
        'media_mimes'    => [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
            'image/svg+xml' => 'svg',
        ],
        'doc_mimes'      => [
            'application/pdf' => 'pdf',
            'text/plain'      => 'txt',
            'text/markdown'   => 'md',
            'text/csv'        => 'csv',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        ],
    ],

    'mail' => [
        'transport'  => Env::get('MAIL_TRANSPORT', 'mail'), // mail | smtp | log
        'from'       => Env::get('MAIL_FROM', 'no-reply@lecomptablealunettes.fr'),
        'from_name'  => Env::get('MAIL_FROM_NAME', 'Le Comptable à Lunettes'),
        'smtp' => [
            'host'       => Env::get('SMTP_HOST', ''),
            'port'       => Env::int('SMTP_PORT', 587),
            'user'       => Env::get('SMTP_USER', ''),
            'pass'       => Env::get('SMTP_PASS', ''),
            'encryption' => Env::get('SMTP_ENCRYPTION', 'tls'), // tls | ssl | none
        ],
    ],

    'ai' => [
        'provider'    => 'gemini',
        'api_key'     => Env::get('GEMINI_API_KEY', ''),
        'model'       => Env::get('GEMINI_MODEL', 'gemini-2.0-flash'),
        'endpoint'    => 'https://generativelanguage.googleapis.com/v1beta/models',
        'timeout'     => 25,
        'max_tokens'  => 900,
        'temperature' => 0.35,
        'rate_limit'  => ['hits' => 20, 'window' => 600], // 20 messages / 10 min / IP
    ],

    'reviews' => [
        'provider'   => 'google',
        'api_key'    => Env::get('GOOGLE_PLACES_API_KEY', ''),
        'place_id'   => Env::get('GOOGLE_PLACE_ID', ''),
        'cache_ttl'  => 60 * 60 * 12, // 12 h
        'endpoint'   => 'https://places.googleapis.com/v1/places/',
    ],

    'i18n' => [
        'default'   => 'fr',
        'available' => ['fr', 'en', 'es', 'de'],
        // Widget Google Traduction : déclaration de langue + traduction à la volée
        'google_translate' => [
            'enabled'  => true,
            'script'   => 'https://translate.google.com/translate_a/element.js',
        ],
    ],

    'forms' => [
        'contact_rate_limit' => ['hits' => 5, 'window' => 3600],
        'lead_rate_limit'    => ['hits' => 8, 'window' => 3600],
        'min_fill_seconds'   => 3, // anti-bot : temps minimum de remplissage
    ],
];
