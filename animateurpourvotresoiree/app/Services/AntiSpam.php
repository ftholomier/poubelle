<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;
use App\Core\Crypto;
use App\Core\Env;
use App\Core\Fs;
use App\Core\Http;
use App\Core\Logger;
use App\Core\Net;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Str;

/**
 * Anti-spam multicouche, sans captcha visuel :
 *  1. champ piège invisible (honeypot)        5. listes noires (IP, emails, domaines, mots)
 *  2. jeton horodaté signé (trop rapide = robot) 6. analyse du contenu (liens, alphabet, mots)
 *  3. preuve de travail calculée par le navigateur 7. email : syntaxe, domaine jetable, MX
 *  4. limitation de débit par IP et par email   8. Cloudflare Turnstile et score IA (facultatifs)
 * Le score final décide : envoi automatique, modération manuelle ou rejet silencieux.
 */
final class AntiSpam
{
    private const DISPOSABLE = ['yopmail.com', 'yopmail.fr', 'mailinator.com', 'guerrillamail.com', 'guerrillamail.info', 'sharklasers.com', '10minutemail.com', 'tempmail.com', 'temp-mail.org', 'trashmail.com', 'trashmail.fr', 'jetable.org', 'getnada.com', 'dispostable.com', 'maildrop.cc', 'mailnesia.com', 'mytrashmail.com', 'throwawaymail.com', 'fakeinbox.com', 'emailondeck.com', 'mohmal.com', 'tempail.com', 'mintemail.com', 'spamgourmet.com', 'mailcatch.com', 'moakt.com', 'tmpmail.org', 'tmail.ws', 'burnermail.io', 'mail.tm', 'emailfake.com', 'crazymailing.com', 'inboxkitten.com', 'mailpoof.com', 'spambox.us', 'discard.email', 'meltmail.com', 'anonbox.net', 'nada.email', 'cuvox.de', 'armyspy.com', 'dayrep.com', 'einrot.com', 'fleckens.hu', 'gustr.com', 'jourrapide.com', 'rhyta.com', 'superrito.com', 'teleworm.us'];
    private const SPAM_WORDS = ['viagra', 'cialis', 'casino en ligne', 'online casino', 'crypto', 'bitcoin', 'forex', 'binary option', 'seo service', 'backlinks', 'guest post', 'rank your website', 'first page of google', 'web design services', 'increase your traffic', 'loan', 'prêt rapide', 'porn', 'sex', 'xxx', 'escort', 'onlyfans', 'click here', 'buy now', 'whatsapp me', 'telegram', 'free trial', 'ai-powered', 'growth service', 'lead generation', 'unsubscribe', 'dear sir', 'dear owner', 'investment', 'make money', 'work from home', 'cheap', 'replica', 'pharmacy'];

    public static function honeypotName(string $form): string
    {
        return 'site_web_' . substr(hash('crc32b', $form . Crypto::key()), 0, 4);
    }

    /** Jeton horodaté à placer dans chaque formulaire public. */
    public static function formToken(string $form): string
    {
        return Crypto::sign(['f' => $form, 't' => time()], 'form');
    }

    /** Champs cachés anti-spam à insérer dans les formulaires publics. */
    public static function fields(string $form): string
    {
        $hp = self::honeypotName($form);
        $html = '<input type="hidden" name="_ft" value="' . e(self::formToken($form)) . '">';
        $html .= '<input type="hidden" name="_pow" value="">';
        $html .= '<div class="hp" aria-hidden="true"><label>Ne pas remplir <input type="text" name="' . e($hp) . '" tabindex="-1" autocomplete="off"></label></div>';
        if (Settings::get('antispam.turnstile') && Env::get('TURNSTILE_SITE_KEY')) {
            $html .= '<div class="cf-turnstile" data-turnstile data-sitekey="' . e((string) Env::get('TURNSTILE_SITE_KEY')) . '" data-language="fr"></div>';
        }
        return $html;
    }

    /** Défi de preuve de travail (le navigateur doit trouver n tel que sha256(jeton+n) commence par d bits nuls). */
    public static function challenge(string $form): array
    {
        $d = max(8, min(22, (int) Settings::get('antispam.pow_difficulty', 15)));
        $token = Crypto::sign(['f' => mb_substr($form, 0, 30), 'r' => bin2hex(random_bytes(6)), 'd' => $d], 'pow', 3600);
        return ['token' => $token, 'difficulty' => $d];
    }

    public static function verifyPow(string $value, string $form): bool
    {
        if (!str_contains($value, '|')) {
            return false;
        }
        [$token, $n] = explode('|', $value, 2);
        $p = Crypto::verify($token, 'pow');
        if (!$p || ($p['f'] ?? '') !== mb_substr($form, 0, 30) || !ctype_digit($n)) {
            return false;
        }
        $hash = hash('sha256', $token . $n);
        $bits = 0;
        foreach (str_split($hash) as $c) {
            $v = hexdec($c);
            if ($v === 0) {
                $bits += 4;
                continue;
            }
            $bits += (int) (3 - floor(log($v, 2)));
            break;
        }
        if ($bits < (int) $p['d']) {
            return false;
        }
        // usage unique
        $used = 'pow-used:' . sha1($token);
        if (!RateLimiter::attempt($used, 1, 7200)) {
            return false;
        }
        return true;
    }

    /**
     * Évalue une soumission de formulaire.
     * @param array $data champs utiles (name, email, phone, message, …)
     * @return array{score:int, decision:string, reasons:string[], blocked:bool, message:?string}
     */
    public static function evaluate(string $form, array $data): array
    {
        $cfg = Settings::get('antispam', []);
        $score = 0;
        $reasons = [];
        $add = static function (int $pts, string $why) use (&$score, &$reasons): void {
            $score += $pts;
            $reasons[] = $why . ' (+' . $pts . ')';
        };
        $ip = Request::ip();
        $email = Str::email((string) ($data['email'] ?? ''));
        $text = trim(implode("\n", array_filter([(string) ($data['message'] ?? ''), (string) ($data['name'] ?? ''), (string) ($data['subject'] ?? '')])));

        // 1. limitation de débit (message explicite à l'utilisateur)
        [$max, $window] = $cfg['rates'][$form] ?? [6, 3600];
        if (!RateLimiter::attempt('form:' . $form . ':' . $ip, (int) $max, (int) $window)) {
            return self::result(100, ['Trop d\'envois depuis cette adresse IP'], true, 'Vous avez envoyé beaucoup de messages en peu de temps. Réessayez un peu plus tard.', $form, $data);
        }
        if ($email !== '' && !RateLimiter::attempt('form-mail:' . $form . ':' . $email, max(3, (int) $max), 86400)) {
            return self::result(100, ['Trop d\'envois pour cet email'], true, 'Cette adresse email a déjà envoyé plusieurs demandes aujourd\'hui.', $form, $data);
        }
        // 2. champ piège
        if (!empty($cfg['honeypot']) && trim((string) Request::input(self::honeypotName($form), '')) !== '') {
            $add(100, 'Champ piège rempli');
        }
        // 3. jeton horodaté
        $ft = Crypto::verify((string) Request::input('_ft', ''), 'form');
        if (!$ft || ($ft['f'] ?? '') !== $form) {
            $add(40, 'Jeton de formulaire absent ou invalide');
        } else {
            $age = time() - (int) $ft['t'];
            if ($age < (int) ($cfg['min_seconds'] ?? 4)) {
                $add(60, 'Formulaire rempli en ' . $age . ' s');
            } elseif ($age > 86400 * 2) {
                $add(15, 'Page ouverte depuis plus de 2 jours');
            }
        }
        // 4. preuve de travail
        if (!empty($cfg['pow'])) {
            $pow = (string) Request::input('_pow', '');
            if ($pow === '') {
                $add(35, 'Preuve de travail absente (JavaScript désactivé ?)');
            } elseif (!self::verifyPow($pow, $form)) {
                $add(70, 'Preuve de travail invalide ou rejouée');
            }
        }
        // 5. Turnstile
        if (!empty($cfg['turnstile']) && Env::get('TURNSTILE_SECRET_KEY')) {
            $tok = (string) Request::input('cf-turnstile-response', '');
            $res = Http::postForm('https://challenges.cloudflare.com/turnstile/v0/siteverify', ['secret' => (string) Env::get('TURNSTILE_SECRET_KEY'), 'response' => $tok, 'remoteip' => $ip], [], 8);
            $ok = (json_decode($res['body'], true)['success'] ?? false) === true;
            if (!$ok) {
                $add(80, 'Vérification Turnstile échouée');
            }
        }
        // 6. listes noires
        $block = $cfg['block'] ?? [];
        if (!empty($block['ips']) && Net::ipInList($ip, (array) $block['ips'])) {
            $add(100, 'IP bloquée');
        }
        if ($email !== '') {
            $domain = substr(strrchr($email, '@') ?: '', 1);
            if (in_array($email, array_map('strtolower', (array) ($block['emails'] ?? [])), true)) {
                $add(100, 'Email bloqué');
            }
            if ($domain !== '' && in_array($domain, array_map('strtolower', (array) ($block['domains'] ?? [])), true)) {
                $add(100, 'Domaine bloqué');
            }
            if (!empty($cfg['block_disposable']) && in_array($domain, self::DISPOSABLE, true)) {
                $add(45, 'Adresse email jetable');
            }
            if (!empty($cfg['check_mx']) && $domain !== '' && !self::hasMx($domain)) {
                $add(40, 'Domaine email sans serveur de messagerie');
            }
        }
        $low = mb_strtolower($text);
        foreach ((array) ($block['words'] ?? []) as $w) {
            $w = mb_strtolower(trim((string) $w));
            if ($w !== '' && str_contains($low, $w)) {
                $add(60, 'Mot interdit : ' . $w);
            }
        }
        // 7. contenu
        if ($text !== '') {
            $links = preg_match_all('#(https?://|www\.)#i', $text);
            $maxLinks = (int) ($cfg['max_links'] ?? 2);
            if ($links > $maxLinks) {
                $add(min(60, ($links - $maxLinks) * 20), $links . ' liens dans le message');
            }
            if (preg_match('#\[url=|\[/url\]|<a\s+href#i', $text)) {
                $add(50, 'Liens BBCode/HTML');
            }
            if (preg_match('#\b(bit\.ly|tinyurl|ow\.ly|t\.co|goo\.gl|is\.gd|cutt\.ly|rebrand\.ly)/#i', $text)) {
                $add(35, 'Lien raccourci');
            }
            $letters = preg_match_all('/\p{L}/u', $text) ?: 1;
            $foreign = preg_match_all('/[\p{Cyrillic}\p{Han}\p{Hangul}\p{Arabic}\p{Thai}\p{Hebrew}]/u', $text);
            if ($foreign / $letters > 0.25) {
                $add(50, 'Alphabet inhabituel');
            }
            $hits = 0;
            foreach (self::SPAM_WORDS as $w) {
                if (str_contains($low, $w)) {
                    $hits++;
                }
            }
            if ($hits) {
                $add(min(60, $hits * 15), $hits . ' expression(s) typique(s) du spam');
            }
            if (self::englishRatio($low) > 0.35 && mb_strlen($low) > 80) {
                $add(25, 'Message rédigé en anglais');
            }
            $msgLen = mb_strlen(trim((string) ($data['message'] ?? '')));
            if (isset($data['message']) && $msgLen < 15) {
                $add(15, 'Message très court');
            }
            if (preg_match('/(.)\1{7,}/u', $text) || preg_match('/\b[bcdfghjklmnpqrstvwxz]{7,}\b/i', $text)) {
                $add(20, 'Texte incohérent');
            }
        }
        $name = (string) ($data['name'] ?? '');
        if ($name !== '' && (preg_match('#https?://|www\.#i', $name) || preg_match('/\d{4,}/', $name))) {
            $add(40, 'Nom suspect');
        }
        if (!empty($data['phone']) && !Str::phoneValid((string) $data['phone'])) {
            $add(10, 'Téléphone invalide');
        }
        // 8. score IA (facultatif, uniquement si le doute subsiste)
        if (!empty($cfg['ai_scoring']) && Settings::aiOn('moderation') && $score < 70 && $text !== '') {
            $ai = Ai::moderate($form, $data);
            if ($ai !== null) {
                $pts = (int) round(((float) ($ai['spam'] ?? 0)) * 60);
                if ($pts > 0) {
                    $add($pts, 'IA : ' . ($ai['reason'] ?? 'probabilité de spam'));
                }
                $data['_ai'] = $ai;
            }
        }
        return self::result($score, $reasons, false, null, $form, $data);
    }

    private static function result(int $score, array $reasons, bool $blocked, ?string $message, string $form, array $data): array
    {
        $auto = (int) Settings::get('moderation.auto_threshold', 30);
        $spam = (int) Settings::get('moderation.spam_threshold', 70);
        $decision = $blocked ? 'blocked' : ($score >= $spam ? 'spam' : ($score >= $auto ? 'review' : 'clean'));
        if ($decision !== 'clean') {
            Logger::log('spam', 'Formulaire ' . $form . ' : ' . $decision, ['form' => $form, 'decision' => $decision, 'score' => $score, 'reasons' => $reasons, 'email' => $data['email'] ?? '', 'ip' => Request::ip()]);
        }
        if ($decision === 'spam' || $decision === 'blocked') {
            self::countSpam();
        }
        return ['score' => min(100, $score), 'decision' => $decision, 'reasons' => $reasons, 'blocked' => $blocked, 'message' => $message, 'ai' => $data['_ai'] ?? null];
    }

    /** Alerte si une vague de spam est détectée (plus de 30 rejets par heure). */
    private static function countSpam(): void
    {
        $n = RateLimiter::hit('spam-wave', 3600);
        if ($n === 30) {
            Notify::admin('spam', 'Vague de spam en cours', '30 envois rejetés en moins d\'une heure. Pensez à activer Turnstile ou à durcir les réglages.', \App\Core\Url::admin('antispam'), 'warning');
        }
    }

    public static function hasMx(string $domain): bool
    {
        if (!function_exists('checkdnsrr')) {
            return true;
        }
        return (bool) Cache::remember('mx:' . $domain, 86400 * 7, static fn () => @checkdnsrr($domain, 'MX') || @checkdnsrr($domain, 'A'));
    }

    private static function englishRatio(string $t): float
    {
        $words = preg_split('/\W+/u', $t, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($words) < 10) {
            return 0.0;
        }
        $en = ['the', 'and', 'you', 'your', 'for', 'with', 'this', 'that', 'are', 'have', 'our', 'we', 'will', 'can', 'website', 'business', 'get', 'more', 'from', 'is', 'to', 'of', 'in'];
        $n = 0;
        foreach ($words as $w) {
            if (in_array($w, $en, true)) {
                $n++;
            }
        }
        return $n / count($words);
    }

    /** Ajoute une IP, un email ou un domaine aux listes noires. */
    public static function block(string $type, string $value): void
    {
        $value = mb_strtolower(trim($value));
        if ($value === '' || !in_array($type, ['ips', 'emails', 'domains', 'words'], true)) {
            return;
        }
        $list = (array) Settings::get('antispam.block.' . $type, []);
        if (!in_array($value, $list, true)) {
            $list[] = $value;
            Settings::set('antispam.block.' . $type, array_values($list));
        }
    }

    public static function recentLog(int $limit = 200): array
    {
        return \App\Core\Logger::tail('spam', $limit);
    }
}
