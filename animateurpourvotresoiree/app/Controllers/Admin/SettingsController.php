<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Cache;
use App\Core\Crypto;
use App\Core\Env;
use App\Core\Logger;
use App\Core\Mailer;
use App\Core\Request;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Core\Str;
use App\Core\Url;
use App\Services\Ads;
use App\Services\Ai;
use App\Services\AntiSpam;
use App\Services\Notify;
use App\Services\Settings;

/** Réglages : configuration (.env), fonctionnement, alertes, publicité, anti-spam. */
final class SettingsController extends AdminController
{
    /** Clés du fichier .env : groupe => [clé => [libellé, type, aide, options]] */
    public static function envSchema(): array
    {
        return [
            'Site' => [
                'APP_NAME' => ['Nom du site', 'text', ''],
                'APP_URL' => ['Adresse du site', 'url', 'Avec https://, sans / final (ex. https://www.animateurpourvotresoiree.com)'],
                'APP_ENV' => ['Environnement', 'select', '« production » pour le site en ligne (robots autorisés, erreurs masquées)', ['production' => 'Production', 'development' => 'Développement / test']],
                'APP_DEBUG' => ['Mode debug', 'bool', 'Affiche le détail des erreurs : à désactiver en production'],
                'ADMIN_PATH' => ['Chemin du back-office', 'slug', 'Changer ce chemin masque le back-office aux robots (ex. gestion-7f3k). Vous serez redirigé vers la nouvelle adresse.'],
                'CONTACT_EMAIL' => ['Email de contact', 'email', 'Expéditeur par défaut et destinataire du formulaire de contact'],
                'ADMIN_ALERT_EMAILS' => ['Emails d\'alerte', 'text', 'Adresses supplémentaires qui reçoivent les alertes (séparées par des virgules)'],
                'OLD_SITE_URL' => ['Ancien site', 'url', 'Utilisé pour rapatrier les photos lors de la migration'],
                'FORCE_HTTPS' => ['Forcer HTTPS', 'bool', ''],
                'TRUSTED_PROXIES' => ['Proxys de confiance', 'text', '« cloudflare » ou plages IP (CIDR), pour obtenir la vraie IP des visiteurs'],
                'MAINTENANCE_MODE' => ['Mode maintenance', 'bool', 'Le site public affiche une page d\'attente (le back-office reste accessible)'],
                'MAINTENANCE_ALLOWED_IPS' => ['IP autorisées pendant la maintenance', 'text', ''],
            ],
            'Emails' => [
                'MAIL_DRIVER' => ['Mode d\'envoi', 'select', '« log » enregistre les emails sans les envoyer (tests)', ['smtp' => 'SMTP (recommandé)', 'mail' => 'Fonction mail() du serveur', 'log' => 'Test : enregistrer sans envoyer']],
                'MAIL_HOST' => ['Serveur SMTP', 'text', 'ex. smtp.gmail.com, ssl0.ovh.net, smtp-relay.brevo.com'],
                'MAIL_PORT' => ['Port', 'int', '587 (TLS) ou 465 (SSL)'],
                'MAIL_ENCRYPTION' => ['Chiffrement', 'select', '', ['tls' => 'STARTTLS (587)', 'ssl' => 'SSL (465)', 'none' => 'Aucun']],
                'MAIL_USERNAME' => ['Identifiant SMTP', 'text', ''],
                'MAIL_PASSWORD' => ['Mot de passe SMTP', 'secret', ''],
                'MAIL_FROM_ADDRESS' => ['Adresse d\'expédition', 'email', 'Doit appartenir à votre domaine (SPF/DKIM)'],
                'MAIL_FROM_NAME' => ['Nom d\'expéditeur', 'text', ''],
                'MAIL_REPLY_TO' => ['Adresse de réponse', 'email', 'Facultatif'],
            ],
            'Intelligence artificielle' => [
                'GEMINI_API_KEY' => ['Clé API Google Gemini', 'secret', 'À créer sur aistudio.google.com (Get API key)'],
                'GEMINI_MODEL' => ['Modèle principal', 'text', 'ex. gemini-2.5-flash'],
                'GEMINI_MODEL_FAST' => ['Modèle rapide', 'text', 'ex. gemini-2.5-flash-lite (modération, classement)'],
            ],
            'Anti-spam' => [
                'TURNSTILE_SITE_KEY' => ['Cloudflare Turnstile — clé du site', 'text', 'Facultatif : captcha invisible en complément'],
                'TURNSTILE_SECRET_KEY' => ['Cloudflare Turnstile — clé secrète', 'secret', ''],
            ],
            'Cartes' => [
                'MAP_TILE_URL' => ['Fond de carte (URL des tuiles)', 'text', 'OpenStreetMap / CARTO par défaut'],
                'MAP_TILE_ATTRIBUTION' => ['Attribution de la carte', 'text', ''],
                'GEO_API_URL' => ['API géographique', 'url', 'geo.api.gouv.fr (mise à jour des communes)'],
            ],
            'Notifications push' => [
                'VAPID_PUBLIC_KEY' => ['Clé publique VAPID', 'readonly', 'Générée automatiquement'],
                'VAPID_PRIVATE_KEY' => ['Clé privée VAPID', 'locked', 'Générée automatiquement'],
                'VAPID_SUBJECT' => ['Contact VAPID', 'text', 'mailto:votre@email'],
            ],
            'Sécurité' => [
                'SESSION_LIFETIME_ADMIN' => ['Déconnexion admin après inactivité (minutes)', 'int', ''],
                'ADMIN_2FA_REQUIRED' => ['Double authentification obligatoire', 'bool', 'Tous les administrateurs devront l\'activer'],
                'ADMIN_IP_ALLOWLIST' => ['IP autorisées pour le back-office', 'text', 'Vide = toutes. Attention à ne pas vous bloquer !'],
                'LOGIN_MAX_ATTEMPTS' => ['Échecs de connexion avant blocage', 'int', ''],
                'LOGIN_LOCK_MINUTES' => ['Durée du blocage (minutes)', 'int', ''],
                'PASSWORD_MIN_LENGTH' => ['Longueur minimale des mots de passe', 'int', ''],
                'CRON_TOKEN' => ['Jeton du cron web', 'locked', 'Utilisé par l\'adresse /cron/{jeton}'],
            ],
            'Performances' => [
                'PAGE_CACHE' => ['Cache des pages', 'bool', 'Pages publiques servies depuis le cache pour les visiteurs'],
                'PAGE_CACHE_TTL' => ['Durée du cache (secondes)', 'int', ''],
            ],
        ];
    }

    public function env(): Response
    {
        $schema = self::envSchema();
        if (Request::isPost()) {
            $me = $this->current();
            $action = (string) Request::input('action', 'save');
            if ($action === 'test-mail') {
                $res = Mailer::send(['to' => $me['email'], 'subject' => 'Test d\'envoi — ' . Settings::siteName(), 'html' => '<p>Bravo, la configuration des emails fonctionne 🎉</p><p>Envoyé le ' . e(date_fr(date('c'), 'datetime')) . ' (pilote : ' . e((string) Env::get('MAIL_DRIVER')) . ').</p>']);
                return $this->done($res['ok'] ? 'Email de test envoyé à ' . $me['email'] . (Env::get('MAIL_DRIVER') === 'log' ? ' (mode test : enregistré dans storage/logs/mail)' : '') . '.' : 'Échec : ' . $res['error'], 'reglages', $res['ok'] ? 'success' : 'error');
            }
            if ($action === 'test-ai') {
                $r = Ai::generate([['role' => 'user', 'parts' => [['text' => 'Réponds simplement « OK » suivi du nom de ton modèle.']]]], ['role' => 'test', 'max_tokens' => 50, 'timeout' => 20]);
                return $this->done($r['ok'] ? 'Gemini répond : ' . Str::limit($r['text'], 120) : 'Échec : ' . $r['error'], 'reglages', $r['ok'] ? 'success' : 'error');
            }
            if (!Crypto::verifyPassword((string) Request::raw('confirm_password', ''), (string) $me['password_hash'])) {
                Logger::security('Modification du .env refusée (mot de passe incorrect)', ['admin' => $me['email']]);
                return $this->done('Mot de passe incorrect : la configuration n\'a pas été modifiée.', 'reglages', 'error');
            }
            if ($action === 'rotate-cron') {
                Env::write(['CRON_TOKEN' => Crypto::token(24)]);
                $this->audit('Jeton du cron régénéré');
                return $this->done('Nouveau jeton de cron généré : mettez à jour l\'adresse de votre tâche planifiée web si vous en utilisez une.', 'reglages#cron');
            }
            $in = (array) Request::arr('env');
            $changes = [];
            $errors = [];
            foreach ($schema as $group => $keys) {
                foreach ($keys as $key => [$label, $type]) {
                    if (in_array($type, ['readonly', 'locked'], true)) {
                        continue;
                    }
                    if ($type === 'bool') {
                        $val = !empty($in[$key]) ? 'true' : 'false';
                    } elseif ($type === 'secret') {
                        if (!empty($in[$key . '__clear'])) {
                            $val = '';
                        } elseif (trim((string) ($in[$key] ?? '')) === '') {
                            continue; // inchangé
                        } else {
                            $val = trim((string) $in[$key]);
                        }
                    } else {
                        $val = trim(str_replace(["\r", "\n"], '', (string) ($in[$key] ?? '')));
                    }
                    $ok = match ($type) {
                        'url' => $val === '' || (bool) filter_var($val, FILTER_VALIDATE_URL) && preg_match('#^https?://#', $val),
                        'email' => $val === '' || Str::emailValid($val),
                        'int' => $val === '' || ctype_digit($val),
                        'slug' => (bool) preg_match('/^[a-z0-9][a-z0-9\-]{2,39}$/', $val) && !in_array($val, ['api', 'espace-pro', 'blog', 'assets', 'media', 'recherche', 'devis', 'connexion', 'animateurs'], true),
                        'select' => array_key_exists($val, $keys[$key][3] ?? []),
                        default => mb_strlen($val) <= 500,
                    };
                    if (!$ok) {
                        $errors[] = $label;
                        continue;
                    }
                    if ((string) Env::get($key, '') !== $val) {
                        $changes[$key] = $val;
                    }
                }
            }
            if ($errors) {
                return $this->done('Valeurs invalides : ' . implode(', ', $errors) . '. Rien n\'a été enregistré.', 'reglages', 'error');
            }
            if (isset($changes['ADMIN_IP_ALLOWLIST']) && $changes['ADMIN_IP_ALLOWLIST'] !== '' && !\App\Core\Net::ipInList(Request::ip(), $changes['ADMIN_IP_ALLOWLIST'])) {
                return $this->done('Votre adresse IP actuelle (' . Request::ip() . ') ne fait pas partie de la liste : vous seriez bloqué. Ajoutez-la d\'abord.', 'reglages', 'error');
            }
            if (!$changes) {
                return $this->done('Aucune modification.', 'reglages', 'info');
            }
            Env::write($changes);
            $this->audit('Configuration .env modifiée', ['clés' => implode(', ', array_keys($changes))]);
            Notify::admin('security', 'Configuration modifiée', $this->by() . ' a modifié : ' . implode(', ', array_keys($changes)), Url::admin('journal?canal=audit'), 'warning');
            Cache::flush('pages');
            if (isset($changes['ADMIN_PATH'])) {
                \App\Core\Session::flash('success', 'Configuration enregistrée. Le back-office est désormais à cette adresse : mettez à jour vos favoris.');
                return $this->redirect('/' . $changes['ADMIN_PATH'] . '/reglages');
            }
            return $this->done('Configuration enregistrée (' . count($changes) . ' modification(s)). Une copie de l\'ancien fichier a été conservée.', 'reglages');
        }
        $known = [];
        foreach ($schema as $keys) {
            $known += $keys;
        }
        $others = array_diff_key(Env::all(), $known, ['APP_KEY' => 1, 'SETUP_TOKEN' => 1]);
        return $this->page('env', [
            'schema' => $schema,
            'others' => $others,
            'cronUrl' => Url::abs('/cron/' . Env::get('CRON_TOKEN', '')),
            'cronCmd' => '* * * * * php ' . BASE_PATH . '/bin/cron.php > /dev/null 2>&1',
            'cron' => \App\Services\Cron::status(),
            'envPath' => str_replace(BASE_PATH . '/', '', Env::path()),
        ], 'Configuration', 'env');
    }

    // ------------------------------------------------------------ fonctionnement

    public function features(): Response
    {
        if (Request::isPost()) {
            $site = [];
            foreach (['name' => 80, 'short_name' => 20, 'baseline' => 160, 'company' => 120, 'siret' => 30, 'rcs' => 80, 'address' => 200, 'director' => 80, 'host' => 300, 'phone' => 30] as $k => $max) {
                $site[$k] = Sanitizer::line((string) Request::input('site_' . $k, ''), $max);
            }
            foreach (['facebook', 'instagram', 'tiktok', 'youtube', 'linkedin'] as $net) {
                $site['socials'][$net] = Str::url(Sanitizer::line((string) Request::input('social_' . $net, ''), 255));
            }
            $mode = static fn (string $k, array $allowed, string $def) => in_array(Request::input($k), $allowed, true) ? (string) Request::input($k) : $def;
            Settings::merge([
                'site' => $site,
                'registration' => [
                    'enabled' => Request::bool('reg_enabled'),
                    'auto_approve' => Request::bool('reg_auto'),
                    'require_siren' => Request::bool('reg_siren'),
                    'max_zones' => max(1, min(101, Request::int('reg_zones', 10))),
                    'max_photos' => max(1, min(40, Request::int('reg_photos', 12))),
                ],
                'moderation' => [
                    'requests_mode' => $mode('mod_requests', ['auto', 'hybrid', 'manual'], 'hybrid'),
                    'messages_mode' => $mode('mod_messages', ['auto', 'hybrid', 'manual'], 'hybrid'),
                    'reviews_mode' => $mode('mod_reviews', ['auto', 'manual'], 'manual'),
                    'auto_threshold' => max(0, min(100, Request::int('mod_auto', 30))),
                    'spam_threshold' => max(1, min(150, Request::int('mod_spam', 70))),
                    'max_recipients' => max(1, min(300, Request::int('mod_max', 40))),
                    'radius_km' => max(5, min(300, Request::int('mod_radius', 50))),
                ],
                'reviews' => ['enabled' => Request::bool('feat_reviews'), 'min_length' => max(10, min(500, Request::int('rev_min', 30)))],
                'listing' => ['per_page' => max(12, min(60, Request::int('list_per', 24))), 'radius_km' => max(5, min(200, Request::int('list_radius', 40))), 'map' => Request::bool('feat_map')],
                'features' => [
                    'favorites' => Request::bool('feat_favorites'), 'reviews' => Request::bool('feat_reviews'), 'pwa' => Request::bool('feat_pwa'),
                    'push' => Request::bool('feat_push'), 'phone_reveal' => Request::bool('feat_phone'), 'map' => Request::bool('feat_map'),
                ],
                'mailing' => [
                    'rate_per_minute' => max(1, min(600, Request::int('mail_rate', 60))),
                    'track_opens' => Request::bool('mail_opens'),
                    'track_clicks' => Request::bool('mail_clicks'),
                    'signature' => Sanitizer::text((string) Request::input('mail_signature', ''), 500),
                ],
                'links' => ['pro_website_rel' => $mode('links_rel', ['noopener', 'nofollow noopener', 'ugc nofollow noopener'], 'noopener')],
                'maintenance' => ['message' => Sanitizer::line((string) Request::input('maintenance_message', ''), 300)],
                'backup' => ['keep' => max(1, min(90, Request::int('backup_keep', 7))), 'include_media' => Request::bool('backup_media')],
            ]);
            Cache::flush('pages');
            Cache::bump();
            $this->audit('Réglages de fonctionnement modifiés');
            return $this->done('Réglages enregistrés.', 'reglages/fonctionnement');
        }
        return $this->page('features', ['s' => Settings::all()], 'Fonctionnement', 'features');
    }

    // ------------------------------------------------------------ alertes admin

    public function notifications(): Response
    {
        if (Request::isPost()) {
            $types = [];
            foreach (Notify::TYPES as $k => $_) {
                $row = (array) (Request::arr('types')[$k] ?? []);
                $types[$k] = ['email' => in_array($row['email'] ?? '', ['instant', 'digest', 'off'], true) ? $row['email'] : 'digest', 'push' => !empty($row['push'])];
            }
            Settings::merge(['notifications' => [
                'digest_hour' => max(0, min(23, Request::int('digest_hour', 8))),
                'emails' => Sanitizer::line((string) Request::input('emails', ''), 500),
                'types' => $types,
            ]]);
            $this->audit('Préférences d\'alertes modifiées');
            return $this->done('Préférences d\'alertes enregistrées.', 'reglages/notifications');
        }
        return $this->page('notif-settings', ['n' => Settings::get('notifications', []), 'recipients' => \App\Services\Mail::adminEmails()], 'Alertes', 'notif-settings');
    }

    // ------------------------------------------------------------ publicité

    public function ads(): Response
    {
        if (Request::isPost()) {
            $client = trim((string) Request::input('client', ''));
            if ($client !== '' && !preg_match('/^ca-pub-\d{10,20}$/', $client)) {
                return $this->done('Identifiant éditeur invalide (format ca-pub-1234567890123456).', 'publicite', 'error');
            }
            $slots = [];
            foreach (Ads::SLOTS as $key => $_) {
                $row = (array) (Request::arr('slots')[$key] ?? []);
                $slots[$key] = [
                    'on' => !empty($row['on']),
                    'id' => preg_replace('/\D/', '', (string) ($row['id'] ?? '')) ?? '',
                    'format' => in_array($row['format'] ?? 'auto', ['auto', 'fluid', 'rect'], true) ? $row['format'] : 'auto',
                ];
                if ($key === 'listing') {
                    $slots[$key]['every'] = max(3, min(30, (int) ($row['every'] ?? 8)));
                }
            }
            Settings::merge(['ads' => [
                'enabled' => Request::bool('enabled'),
                'client' => $client,
                'auto_ads' => Request::bool('auto_ads'),
                'cmp' => in_array(Request::input('cmp'), ['google', 'npa', 'own'], true) ? (string) Request::input('cmp') : 'google',
                'test_mode' => Request::bool('test_mode'),
                'label' => Sanitizer::line((string) Request::input('label', 'Publicité'), 40) ?: 'Publicité',
                'slots' => $slots,
                'ads_txt' => Sanitizer::text((string) Request::input('ads_txt', ''), 5000),
            ], 'analytics' => [
                'ga4' => preg_match('/^G-[A-Z0-9]{4,20}$/', (string) Request::input('ga4', '')) ? (string) Request::input('ga4') : '',
                'meta_pixel' => preg_replace('/\D/', '', (string) Request::input('meta_pixel', '')) ?? '',
                // code HTML/JS brut : réservé au super-administrateur (il s'exécute sur tout le site)
                'head_html' => \App\Core\Auth::adminCan('env') ? (string) Request::raw('head_html', '') : (string) Settings::get('analytics.head_html', ''),
                'body_html' => \App\Core\Auth::adminCan('env') ? (string) Request::raw('body_html', '') : (string) Settings::get('analytics.body_html', ''),
            ]]);
            Cache::flush('pages');
            Cache::bump();
            $this->audit('Réglages publicité modifiés');
            return $this->done('Réglages de publicité enregistrés.', 'publicite');
        }
        return $this->page('ads', ['ads' => Settings::get('ads', []), 'analytics' => Settings::get('analytics', []), 'client' => Ads::client(), 'envClient' => (string) Env::get('ADSENSE_CLIENT', ''), 'adsTxt' => Ads::adsTxt()], 'Publicité', 'ads');
    }

    // ------------------------------------------------------------ anti-spam

    public function antispam(): Response
    {
        if (Request::isPost()) {
            $rates = [];
            foreach ((array) Settings::get('antispam.rates', []) as $form => [$max, $window]) {
                $row = (array) (Request::arr('rates')[$form] ?? []);
                $rates[$form] = [max(1, min(1000, (int) ($row[0] ?? $max))), max(60, min(86400 * 7, (int) ($row[1] ?? $window)))];
            }
            $list = static fn (string $k): array => array_values(array_unique(array_filter(array_map(static fn ($v) => mb_strtolower(trim($v)), preg_split('/[\n,;]+/', (string) Request::input($k, '')) ?: []))));
            Settings::merge(['antispam' => [
                'honeypot' => Request::bool('honeypot'),
                'min_seconds' => max(0, min(60, Request::int('min_seconds', 4))),
                'pow' => Request::bool('pow'),
                'pow_difficulty' => max(8, min(22, Request::int('pow_difficulty', 15))),
                'turnstile' => Request::bool('turnstile'),
                'max_links' => max(0, min(20, Request::int('max_links', 2))),
                'check_mx' => Request::bool('check_mx'),
                'block_disposable' => Request::bool('block_disposable'),
                'ai_scoring' => Request::bool('ai_scoring'),
                'rates' => $rates,
                'block' => ['ips' => $list('block_ips'), 'emails' => $list('block_emails'), 'domains' => $list('block_domains'), 'words' => $list('block_words')],
            ]]);
            $this->audit('Réglages anti-spam modifiés');
            return $this->done('Réglages anti-spam enregistrés.', 'antispam');
        }
        return $this->page('antispam', ['a' => Settings::get('antispam', []), 'log' => AntiSpam::recentLog(150), 'turnstileKeys' => Env::get('TURNSTILE_SITE_KEY', '') !== '' && Env::get('TURNSTILE_SECRET_KEY', '') !== ''], 'Anti-spam', 'antispam');
    }
}
