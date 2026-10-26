<?php
declare(strict_types=1);

namespace LD\Controllers;

use LD\Ai;
use LD\AiException;
use LD\Auth;
use LD\Cron;
use LD\Http;
use LD\Mailer;
use LD\PageCache;
use LD\Router;
use LD\Secrets;
use LD\Settings;
use LD\Sms;
use LD\Store;

/**
 * Back-office : IA & clés API (liste des modèles en direct), paramètres du site, compte et sauvegarde.
 */
final class AdminSettings
{
    public static function routes(Router $r): void
    {
        $g = [Admin::class, 'guard'];
        $r->get('/admin/ia', $g([self::class, 'ai']));
        $r->post('/admin/ia', $g([self::class, 'saveAi'], true));
        $r->post('/admin/api/ia/cle', $g([self::class, 'saveKey'], true));
        $r->post('/admin/api/ia/modeles', $g([self::class, 'models'], true));
        $r->post('/admin/api/ia/test', $g([self::class, 'test'], true));

        $r->get('/admin/parametres', $g([self::class, 'settings']));
        $r->post('/admin/parametres', $g([self::class, 'saveSettings'], true));
        $r->post('/admin/api/test-email', $g([self::class, 'testEmail'], true));
        $r->post('/admin/api/test-sms', $g([self::class, 'testSms'], true));
        $r->post('/admin/cache/vider', $g([self::class, 'clearCache'], true));
        $r->get('/admin/sauvegarde', $g([self::class, 'backup']));

        $r->get('/admin/compte', $g([self::class, 'account']));
        $r->post('/admin/compte', $g([self::class, 'saveAccount'], true));
    }

    // ------------------------------------------------------------------ IA

    public static function ai(): never
    {
        $providers = [];
        foreach (Ai::PROVIDERS as $key => $p) {
            $cache = Store::read('cache/models/' . $key . '.json', []);
            $providers[$key] = $p + [
                'has_key'    => Ai::hasKey($key),
                'masked'     => Secrets::masked(Ai::keyName($key)),
                'updated'    => Secrets::updatedAt(Ai::keyName($key)),
                'models'     => $cache['models'] ?? [],
                'fetched_at' => $cache['fetched_at'] ?? null,
            ];
        }
        $usage = Store::read('data/ai-usage.json', []);
        Admin::view('ai', ['providers' => $providers, 'cfg' => Ai::config(), 'usage' => array_slice($usage, -14, 14, true), 'features' => Ai::FEATURES], 'IA & clés API');
    }

    public static function saveAi(): never
    {
        $c = Ai::config();
        $provider = (string) ($_POST['default_provider'] ?? '');
        $c['enabled'] = !empty($_POST['enabled']);
        $c['default_provider'] = isset(Ai::PROVIDERS[$provider]) ? $provider : '';
        $c['default_model'] = clean_text((string) ($_POST['default_model'] ?? ''), 160);
        $c['anthropic_effort'] = in_array($_POST['anthropic_effort'] ?? '', ['low', 'medium', 'high', 'none'], true) ? $_POST['anthropic_effort'] : 'low';
        $c['daily_cap'] = max(0, min(100000, (int) ($_POST['daily_cap'] ?? 300)));
        foreach (Ai::FEATURES as $f => $def) {
            $in = (array) ($_POST['features'][$f] ?? []);
            $fp = (string) ($in['provider'] ?? '');
            $c['features'][$f] = [
                'enabled'    => !empty($in['enabled']),
                'provider'   => isset(Ai::PROVIDERS[$fp]) ? $fp : '',
                'model'      => isset(Ai::PROVIDERS[$fp]) ? clean_text((string) ($in['model'] ?? ''), 160) : '',
                'max_tokens' => max(100, min(64000, (int) ($in['max_tokens'] ?? $def['max_tokens']))),
            ];
        }
        $c['transcription'] = ['enabled' => !empty($_POST['transcription_enabled']), 'model' => clean_text((string) ($_POST['transcription_model'] ?? 'whisper-1'), 80) ?: 'whisper-1'];
        foreach (array_keys($c['prompts']) as $k) {
            if (isset($_POST['prompts'][$k])) {
                $c['prompts'][$k] = mb_substr(str_replace("\r\n", "\n", (string) $_POST['prompts'][$k]), 0, 12000);
            }
        }
        if (!empty($_POST['reset_prompts'])) {
            $c['prompts'] = \LD\Content::defaults('ai')['prompts'];
        }
        Ai::saveConfig($c);
        Admin::back('/admin/ia', 'ok', 'Réglages IA enregistrés.');
    }

    /** Enregistre (ou supprime) une clé puis charge la liste des modèles disponibles. */
    public static function saveKey(): never
    {
        $provider = (string) Http::input('provider', '');
        if (!isset(Ai::PROVIDERS[$provider])) {
            Http::fail('Fournisseur inconnu.');
        }
        $key = trim((string) Http::input('key', ''));
        if ($key === '') {
            Secrets::delete(Ai::keyName($provider));
            Store::delete('cache/models/' . $provider . '.json');
            Http::sendJson(['ok' => true, 'has_key' => false, 'masked' => '', 'models' => []]);
        }
        if (strlen($key) > 400 || preg_match('/\s/', $key)) {
            Http::fail('Cette clé ne semble pas valide.');
        }
        $previous = Secrets::get(Ai::keyName($provider));
        Secrets::set(Ai::keyName($provider), $key);
        try {
            $models = Ai::models($provider, true);
        } catch (AiException $e) {
            // Clé refusée : on restaure l'ancienne pour ne rien casser.
            if ($previous) {
                Secrets::set(Ai::keyName($provider), $previous);
            } else {
                Secrets::delete(Ai::keyName($provider));
            }
            Http::fail('Clé refusée par ' . Ai::PROVIDERS[$provider]['label'] . ' : ' . $e->getMessage(), 422);
        }
        // Premier fournisseur configuré : on le choisit par défaut avec un modèle cohérent.
        $c = Ai::config();
        if ($c['default_provider'] === '' || !Ai::hasKey($c['default_provider']) || ($c['default_provider'] === $provider && !in_array($c['default_model'], array_column($models, 'id'), true))) {
            $c['default_provider'] = $provider;
            $ids = array_column($models, 'id');
            if (!in_array($c['default_model'], $ids, true)) {
                $c['default_model'] = in_array('claude-opus-5-5', $ids, true) ? 'claude-opus-5-5' : ($ids[0] ?? '');
            }
            Ai::saveConfig($c);
        }
        Http::sendJson(['ok' => true, 'has_key' => true, 'masked' => Secrets::masked(Ai::keyName($provider)), 'models' => $models, 'default_provider' => $c['default_provider'], 'default_model' => $c['default_model']]);
    }

    public static function models(): never
    {
        $provider = (string) Http::input('provider', '');
        try {
            Http::sendJson(['ok' => true, 'models' => Ai::models($provider, true)]);
        } catch (AiException $e) {
            Http::fail($e->getMessage(), 422);
        }
    }

    public static function test(): never
    {
        $provider = (string) Http::input('provider', '');
        $model = (string) Http::input('model', '');
        $prompt = clean_text((string) Http::input('prompt', ''), 2000) ?: 'Présente-toi en une phrase, en tutoyant.';
        if (!isset(Ai::PROVIDERS[$provider]) || $model === '') {
            Http::fail('Choisis un fournisseur et un modèle.');
        }
        $t0 = microtime(true);
        try {
            $r = Ai::call($provider, $model, Ai::prompt('chat_system'), [['role' => 'user', 'content' => $prompt]], 1500);
            Http::sendJson(['ok' => true, 'text' => $r['text'], 'secs' => round(microtime(true) - $t0, 2), 'in' => $r['in'], 'out' => $r['out']]);
        } catch (AiException $e) {
            Http::fail($e->getMessage(), 422);
        }
    }

    // ------------------------------------------------------------------ paramètres

    public static function settings(): never
    {
        Admin::view('settings', [
            's'       => Settings::all(),
            'secrets' => [
                'smtp_password'     => Secrets::masked('smtp_password'),
                'brevo_api_key'     => Secrets::masked('brevo_api_key'),
                'twilio_auth_token' => Secrets::masked('twilio_auth_token'),
            ],
            'cronUrl' => Cron::url(),
            'cronCmd' => 'php ' . LD_APP . '/cron.php',
            'cron'    => Store::read('cache/cron.json', [])['last_run'] ?? null,
        ], 'Paramètres');
    }

    public static function saveSettings(): never
    {
        $s = Store::read('content/settings.json', []);
        $p = $_POST;
        $str = static fn(string $k, int $max = 200) => clean_text((string) array_get($p, $k, ''), $max);
        $url = static function (string $v): string {
            $v = trim($v);
            return $v === '' || filter_var($v, FILTER_VALIDATE_URL) ? $v : '';
        };
        $s['site'] = [
            'name'       => $str('site.name', 80) ?: 'LE-DIGITAL.com',
            'url'        => rtrim($url((string) array_get($p, 'site.url', '')), '/'),
            'owner'      => $str('site.owner', 80),
            'logo'       => $str('site.logo', 300) ?: '/assets/img/logo.svg',
            'logo_white' => $str('site.logo_white', 300) ?: '/assets/img/logo-blanc.svg',
            'avatar'     => $str('site.avatar', 300) ?: '/assets/img/fred-avatar.webp',
            'og_image'   => $str('site.og_image', 300),
        ];
        $s['contact'] = [
            'phone' => $str('contact.phone', 30), 'email' => filter_var($str('contact.email', 160), FILTER_VALIDATE_EMAIL) ?: '',
            'linkedin' => $url((string) array_get($p, 'contact.linkedin', '')), 'address' => $str('contact.address', 200),
            'postal_code' => $str('contact.postal_code', 10), 'city' => $str('contact.city', 80), 'siret' => $str('contact.siret', 30),
        ];
        $s['features'] = ['animations' => !empty($p['features']['animations'])];
        $s['mail'] = [
            'mode'            => in_array(array_get($p, 'mail.mode'), ['mail', 'smtp', 'off'], true) ? array_get($p, 'mail.mode') : 'mail',
            'from_email'      => filter_var($str('mail.from_email', 160), FILTER_VALIDATE_EMAIL) ?: '',
            'from_name'       => $str('mail.from_name', 80),
            'notify_email'    => filter_var($str('mail.notify_email', 160), FILTER_VALIDATE_EMAIL) ?: '',
            'notify'          => !empty($p['mail']['notify']),
            'confirm_visitor' => !empty($p['mail']['confirm_visitor']),
            'smtp_host'       => $str('mail.smtp_host', 160),
            'smtp_port'       => max(1, min(65535, (int) array_get($p, 'mail.smtp_port', 587))),
            'smtp_secure'     => in_array(array_get($p, 'mail.smtp_secure'), ['tls', 'ssl', 'none'], true) ? array_get($p, 'mail.smtp_secure') : 'tls',
            'smtp_user'       => $str('mail.smtp_user', 160),
        ];
        $s['sms'] = [
            'enabled'       => !empty($p['sms']['enabled']),
            'provider'      => in_array(array_get($p, 'sms.provider'), ['none', 'brevo', 'twilio'], true) ? array_get($p, 'sms.provider') : 'none',
            'sender'        => $str('sms.sender', 11),
            'twilio_sid'    => $str('sms.twilio_sid', 60),
            'twilio_from'   => $str('sms.twilio_from', 30),
            'reminder_text' => $str('sms.reminder_text', 300),
        ];
        $s['seo'] = [
            'title_suffix'        => (string) mb_substr((string) array_get($p, 'seo.title_suffix', ''), 0, 60),
            'analytics_head'      => mb_substr((string) array_get($p, 'seo.analytics_head', ''), 0, 5000),
            'google_verification' => $str('seo.google_verification', 120),
            'noindex'             => !empty($p['seo']['noindex']),
        ];
        $s['performance'] = ['page_cache' => !empty($p['performance']['page_cache']), 'cache_ttl' => max(60, min(604800, (int) array_get($p, 'performance.cache_ttl', 21600)))];
        $redirects = [];
        foreach (preg_split('/\R/', (string) ($p['redirects'] ?? '')) ?: [] as $line) {
            if (preg_match('~^\s*(/\S*)\s*(?:=>|->|\s)\s*(\S+)\s*$~', $line, $m)) {
                $redirects[] = ['from' => $m[1], 'to' => $m[2]];
            }
        }
        $s['redirects'] = $redirects;
        $s['debug'] = !empty($p['debug']);
        foreach (['smtp_password', 'brevo_api_key', 'twilio_auth_token'] as $secret) {
            if (!empty($p['secret_delete'][$secret])) {
                Secrets::delete($secret);
            } elseif (trim((string) ($p['secret'][$secret] ?? '')) !== '') {
                Secrets::set($secret, (string) $p['secret'][$secret]);
            }
        }
        Settings::save($s);
        Admin::back('/admin/parametres', 'ok', 'Paramètres enregistrés.');
    }

    public static function testEmail(): never
    {
        $to = (string) (Settings::get('mail.notify_email') ?: (Auth::user()['email'] ?? ''));
        $ok = Mailer::send($to, 'Test d’envoi · LE-DIGITAL.com', Mailer::layout('Ça marche ! ☕', '<p>Les e-mails de ton site partent correctement. Tu recevras ici les demandes, rendez-vous et vocaux.</p>'));
        $ok ? Http::sendJson(['ok' => true, 'message' => 'E-mail de test envoyé à ' . $to . '.'])
            : Http::fail('Échec de l’envoi à ' . ($to ?: '(aucune adresse)') . '. Consulte storage/logs/mail.log ou vérifie les réglages SMTP.', 422);
    }

    public static function testSms(): never
    {
        $phone = (string) Http::input('phone', '');
        Sms::send($phone, 'Test SMS LE-DIGITAL.com : les rappels de rendez-vous fonctionnent ☕')
            ? Http::sendJson(['ok' => true, 'message' => 'SMS envoyé.'])
            : Http::fail('Échec de l’envoi. Vérifie le fournisseur, la clé et le numéro (format 06… ou +33…). Détails dans storage/logs/sms.log.', 422);
    }

    public static function clearCache(): never
    {
        PageCache::clear();
        foreach (glob(Store::path('cache/ai/*.json')) ?: [] as $f) {
            @unlink($f);
        }
        Admin::back($_POST['back'] ?? '/admin/parametres', 'ok', 'Cache vidé.');
    }

    /** Sauvegarde complète (contenus, données, médias envoyés) au format ZIP. */
    public static function backup(): never
    {
        if (!class_exists('ZipArchive')) {
            Admin::back('/admin/parametres', 'err', 'L’extension ZIP de PHP n’est pas disponible sur cet hébergement.');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'ldbk');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        $add = static function (string $dir, string $prefix) use ($zip): void {
            if (!is_dir($dir)) {
                return;
            }
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f->isFile() && !str_ends_with($f->getFilename(), '.lock') && !str_ends_with($f->getFilename(), '.tmp')) {
                    $zip->addFile($f->getPathname(), $prefix . substr($f->getPathname(), strlen($dir)));
                }
            }
        };
        $add(LD_STORAGE . '/content', 'storage/content');
        $add(LD_STORAGE . '/data', 'storage/data');
        $add(LD_STORAGE . '/secrets', 'storage/secrets');
        $add(LD_PUBLIC . '/uploads', 'public/uploads');
        $zip->close();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="sauvegarde-le-digital-' . date('Y-m-d-His') . '.zip"');
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    // ------------------------------------------------------------------ compte

    public static function account(): never
    {
        Admin::view('account', [], 'Mon compte');
    }

    public static function saveAccount(): never
    {
        $u = Auth::user();
        $email = trim((string) ($_POST['email'] ?? ''));
        $new = (string) ($_POST['password'] ?? '');
        if (!password_verify((string) ($_POST['current'] ?? ''), $u['hash'])) {
            Admin::back('/admin/compte', 'err', 'Mot de passe actuel incorrect.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Admin::back('/admin/compte', 'err', 'Adresse e-mail invalide.');
        }
        if ($new !== '' && (mb_strlen($new) < 10 || $new !== (string) ($_POST['password2'] ?? ''))) {
            Admin::back('/admin/compte', 'err', 'Nouveau mot de passe : 10 caractères minimum, et identique dans les deux champs.');
        }
        Auth::updateUser($u['id'], ['email' => $email, 'name' => clean_text((string) ($_POST['name'] ?? ''), 80), 'password' => $new]);
        Admin::back('/admin/compte', 'ok', 'Compte mis à jour.');
    }
}
