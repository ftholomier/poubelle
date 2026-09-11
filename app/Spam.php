<?php
declare(strict_types=1);

namespace App;

/**
 * Protection anti-spam des formulaires publics, sans service tiers.
 *
 * Trois barrières successives :
 *  1. un jeton signé, posé à l'affichage du formulaire et vérifié à l'envoi ;
 *  2. un pixel que seul un vrai navigateur va chercher ;
 *  3. une note de suspicion calculée sur le contenu.
 *
 * Selon la note, l'envoi passe, demande une question simple, ou part en
 * quarantaine : il est enregistré et visible au back-office, mais n'est pas
 * transmis par email. Aucun visiteur légitime n'est jamais perdu en silence.
 */
final class Spam
{
    /** Champs leurres : invisibles à l'écran, irrésistibles pour un robot. */
    public const TRAPS = ['website', 'company', 'hp'];

    /** Valeurs par défaut, écrasables depuis Réglages → Anti-spam. */
    public const DEFAULTS = [
        'enabled' => true,
        'challengeAt' => 4,      // note à partir de laquelle on pose une question
        'quarantineAt' => 8,     // note à partir de laquelle on met de côté
        'minSeconds' => 3,       // délai minimal entre affichage et envoi
        'maxHours' => 6,         // au-delà, le jeton est périmé
        'perIp' => 5,            // envois autorisés par IP
        'perIpWindow' => 600,    // …sur cette fenêtre, en secondes
        'perEmailPerDay' => 5,   // envois autorisés par adresse et par jour
        'blockHours' => 24,      // durée du blocage d'un récidiviste
        'strikesBeforeBlock' => 5,
        'checkMx' => true,       // le domaine de l'email a-t-il un serveur mail
        'words' => ['viagra', 'casino', 'crypto', 'bitcoin', 'seo', 'backlink', 'porn', 'loan'],
    ];

    /** Domaines d'adresses jetables les plus courants. */
    private const DISPOSABLE = [
        'mailinator.com', 'yopmail.com', 'guerrillamail.com', 'tempmail.com', '10minutemail.com',
        'trashmail.com', 'sharklasers.com', 'getnada.com', 'dispostable.com', 'maildrop.cc',
    ];

    private const MOTS_FR = ['le', 'la', 'les', 'des', 'une', 'et', 'pour', 'vous', 'nous', 'avec',
        'dans', 'sur', 'est', 'sont', 'bureau', 'bureaux', 'bonjour', 'merci', 'je', 'au'];
    private const MOTS_EN = ['the', 'you', 'your', 'and', 'for', 'with', 'this', 'that', 'was',
        'are', 'have', 'from', 'would', 'will', 'hello', 'thanks'];

    // ---------------------------------------------------------------- réglages

    public static function config(): array
    {
        $saved = Content::settings()['antispam'] ?? [];
        return array_replace(self::DEFAULTS, \is_array($saved) ? $saved : []);
    }

    public static function enabled(): bool
    {
        return (bool) self::config()['enabled'];
    }

    // ------------------------------------------------------------ jeton signé

    /**
     * Jeton posé dans le formulaire : horodatage + nom du formulaire + nonce,
     * scellés par HMAC. Impossible à omettre (il devient invalide) ni à
     * antidater (la signature ne suivrait pas).
     */
    public static function mintToken(string $form): string
    {
        $payload = self::b64(json_encode([
            'f' => $form,
            't' => time(),
            'n' => bin2hex(random_bytes(8)),
        ], JSON_THROW_ON_ERROR));

        return $payload . '.' . self::b64(hash_hmac('sha256', $payload, Config::appSecret(), true));
    }

    /**
     * @return array{ok:bool,reason:string,nonce:string,age:int}
     */
    public static function readToken(string $token, string $form): array
    {
        $empty = ['ok' => false, 'reason' => 'token-absent', 'nonce' => '', 'age' => 0];
        if (!str_contains($token, '.')) {
            return $empty;
        }

        [$payload, $signature] = explode('.', $token, 2);
        $expected = self::b64(hash_hmac('sha256', $payload, Config::appSecret(), true));
        if (!hash_equals($expected, $signature)) {
            return ['ok' => false, 'reason' => 'token-signature', 'nonce' => '', 'age' => 0];
        }

        $data = json_decode((string) self::unb64($payload), true);
        if (!\is_array($data) || (string) ($data['f'] ?? '') !== $form) {
            return ['ok' => false, 'reason' => 'token-formulaire', 'nonce' => '', 'age' => 0];
        }

        $age = time() - (int) ($data['t'] ?? 0);
        $config = self::config();
        if ($age < 0 || $age > (int) $config['maxHours'] * 3600) {
            return ['ok' => false, 'reason' => 'token-perime', 'nonce' => (string) ($data['n'] ?? ''), 'age' => $age];
        }

        return ['ok' => true, 'reason' => '', 'nonce' => (string) ($data['n'] ?? ''), 'age' => $age];
    }

    /**
     * Champs invisibles à poser dans chaque formulaire public : le jeton signé,
     * les leurres et le pixel de présence. Un seul appel par formulaire.
     */
    public static function fields(string $form): string
    {
        $token = self::mintToken($form);
        $nonce = self::readToken($token, $form)['nonce'];

        $html = '<input type="hidden" name="ft" value="' . Text::e($token) . '">';
        foreach (self::TRAPS as $trap) {
            $html .= '<input class="honey" type="text" name="' . Text::e($trap) . '" value=""'
                . ' tabindex="-1" autocomplete="off" aria-hidden="true">';
        }
        $html .= '<img class="honey" src="' . Text::e(Config::basePath() . '/api/pixel.php?t=' . rawurlencode($nonce))
            . '" alt="" width="1" height="1" aria-hidden="true">';

        return $html;
    }

    // ------------------------------------------------------------------ pixel

    /** Marque le nonce comme « page réellement affichée par un navigateur ». */
    public static function markPixel(string $nonce): void
    {
        $file = self::pixelFile($nonce);
        if ($file === '') {
            return;
        }
        if (!is_dir(\dirname($file))) {
            @mkdir(\dirname($file), 0775, true);
        }
        @file_put_contents($file, (string) time(), LOCK_EX);
    }

    public static function pixelSeen(string $nonce): bool
    {
        $file = self::pixelFile($nonce);
        return $file !== '' && is_file($file);
    }

    private static function pixelFile(string $nonce): string
    {
        $nonce = preg_replace('/[^a-f0-9]/', '', $nonce) ?? '';
        return $nonce === '' ? '' : Config::storagePath('locks/px-' . substr($nonce, 0, 32) . '.txt');
    }

    // ------------------------------------------------------------------- note

    /**
     * Note de suspicion et motifs, à partir du formulaire reçu.
     *
     * @return array{score:int,reasons:array<int,string>,nonce:string}
     */
    public static function score(array $input, string $form): array
    {
        $config = self::config();
        $score = 0;
        $reasons = [];
        $add = static function (int $points, string $why) use (&$score, &$reasons): void {
            $score += $points;
            $reasons[] = $why;
        };

        // 1. Les leurres : un humain ne les voit pas, donc ne les remplit jamais.
        foreach (self::TRAPS as $trap) {
            if (trim((string) ($input[$trap] ?? '')) !== '') {
                $add(10, 'champ leurre « ' . $trap .' » rempli');
                break;
            }
        }

        // 2. Le jeton signé.
        $token = self::readToken((string) ($input['ft'] ?? ''), $form);
        if (!$token['ok']) {
            $add($token['reason'] === 'token-perime' ? 2 : 5, 'jeton ' . $token['reason']);
        } elseif ($token['age'] < (int) $config['minSeconds']) {
            $add(4, 'formulaire envoyé en ' . $token['age'] . ' s');
        }

        // 3. Le pixel : un script qui poste directement n'affiche jamais la page.
        if ($token['nonce'] === '' || !self::pixelSeen($token['nonce'])) {
            $add(3, 'page jamais affichée par un navigateur');
        }

        // 4. Le contenu.
        $message = (string) ($input['message'] ?? '');
        $name = (string) ($input['name'] ?? '');
        $blob = $name . ' ' . $message . ' ' . (string) ($input['subject'] ?? '');

        $links = preg_match_all('#https?://|www\.|\[url#i', $blob);
        if ($links >= 3) {
            $add(4, $links . ' liens dans le message');
        } elseif ($links >= 1) {
            $add(2, 'lien dans le message');
        }
        if (preg_match('#https?://|www\.#i', $name) === 1) {
            $add(4, 'lien dans le nom');
        }
        if (preg_match('/[\x{0400}-\x{04FF}\x{4E00}-\x{9FFF}\x{0600}-\x{06FF}]/u', $blob) === 1) {
            $add(3, 'alphabet sans rapport avec le site');
        }
        if (mb_strlen($message) > 20 && mb_strtoupper($message) === $message) {
            $add(2, 'message entièrement en majuscules');
        }
        foreach ((array) $config['words'] as $word) {
            $word = trim((string) $word);
            if ($word !== '' && mb_stripos($blob, $word) !== false) {
                $add(4, 'mot signalé « ' . $word . ' »');
                break;
            }
        }
        if (self::wrongLanguage($message, (string) ($input['lang'] ?? Config::DEFAULT_LANG))) {
            $add(3, 'message rédigé dans une autre langue que le formulaire');
        }

        // 5. L'adresse email.
        $email = mb_strtolower(trim((string) ($input['email'] ?? '')));
        $domain = substr(strrchr($email, '@') ?: '', 1);
        if ($domain !== '') {
            if (\in_array($domain, self::DISPOSABLE, true)) {
                $add(3, 'adresse jetable');
            }
            if ($config['checkMx'] && !self::domainAcceptsMail($domain)) {
                $add(4, 'domaine « ' . $domain . ' » sans serveur de messagerie');
            }
        }

        // 6. Le client qui envoie.
        $agent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        if (trim($agent) === '') {
            $add(2, 'aucun agent déclaré');
        } elseif (preg_match('/curl|wget|python|scrapy|libwww|httpclient|bot|spider/i', $agent) === 1) {
            $add(3, 'agent automatisé déclaré');
        }

        // 7. Le même message déjà reçu récemment.
        // Poids volontairement sous le seuil : un visiteur qui renvoie son
        // message ne doit pas être inquiété pour cela seul.
        if (self::seenRecently($message)) {
            $add(3, 'message identique déjà reçu');
        }

        return ['score' => $score, 'reasons' => $reasons, 'nonce' => $token['nonce']];
    }

    /** 'clean' | 'challenge' | 'quarantine' */
    public static function verdict(int $score): string
    {
        $config = self::config();
        if ($score >= (int) $config['quarantineAt']) {
            return 'quarantine';
        }
        return $score >= (int) $config['challengeAt'] ? 'challenge' : 'clean';
    }

    // ------------------------------------------------------------- question

    /** Question arithmétique posée quand la note dépasse le seuil. */
    public static function makeChallenge(): array
    {
        $mots = ['zéro', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf'];
        $a = random_int(2, 6);
        $b = random_int(1, 3);

        Session::start();
        Session::set('_challenge', ['answer' => $a + $b, 'at' => time()]);

        return [
            'question' => I18n::t('spam.challenge', ['a' => $mots[$a], 'b' => $mots[$b]]),
            'hint' => I18n::t('spam.challengeHint'),
        ];
    }

    public static function checkChallenge(string $answer): bool
    {
        Session::start();
        $stored = Session::get('_challenge');
        if (!\is_array($stored) || time() - (int) ($stored['at'] ?? 0) > 900) {
            return false;
        }
        $given = (int) preg_replace('/[^0-9]/', '', $answer);
        if ($given !== (int) $stored['answer']) {
            return false;
        }
        Session::forget('_challenge');
        return true;
    }

    // -------------------------------------------------------------- blocages

    /** true si l'IP est privée d'envoi après trop de tentatives. */
    public static function blocked(): bool
    {
        $file = self::strikeFile();
        if (!is_file($file)) {
            return false;
        }
        $data = json_decode((string) file_get_contents($file), true);
        return \is_array($data) && (int) ($data['until'] ?? 0) > time();
    }

    /** Compte un envoi mis en quarantaine ; bloque l'IP au-delà du seuil. */
    public static function strike(): void
    {
        $config = self::config();
        $file = self::strikeFile();
        if (!is_dir(\dirname($file))) {
            @mkdir(\dirname($file), 0775, true);
        }
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        $data = \is_array($data) ? $data : ['count' => 0, 'until' => 0];

        if ((int) ($data['first'] ?? 0) + 86400 < time()) {
            $data = ['count' => 0, 'until' => 0, 'first' => time()];
        }
        $data['count'] = (int) $data['count'] + 1;
        if ($data['count'] >= (int) $config['strikesBeforeBlock']) {
            $data['until'] = time() + (int) $config['blockHours'] * 3600;
            Log::write('spam', 'IP bloquée ' . $config['blockHours'] . ' h après ' . $data['count'] . ' envois suspects.');
        }
        @file_put_contents($file, (string) json_encode($data), LOCK_EX);
    }

    private static function strikeFile(): string
    {
        return Config::storagePath('locks/strikes-' . substr(sha1(RateLimit::ip()), 0, 16) . '.json');
    }

    // ------------------------------------------------------------ utilitaires

    /** Le domaine accepte-t-il le courrier ? Réponse mise en cache 7 jours. */
    public static function domainAcceptsMail(string $domain): bool
    {
        $domain = strtolower(trim($domain));
        if ($domain === '' || !\function_exists('checkdnsrr')) {
            return true;
        }

        $file = Config::storagePath('locks/mx-' . substr(sha1($domain), 0, 16) . '.json');
        if (is_file($file) && filemtime($file) > time() - 604800) {
            $cached = json_decode((string) file_get_contents($file), true);
            if (\is_array($cached) && isset($cached['ok'])) {
                return (bool) $cached['ok'];
            }
        }

        $ok = @checkdnsrr($domain, 'MX') || @checkdnsrr($domain, 'A');
        if (!is_dir(\dirname($file))) {
            @mkdir(\dirname($file), 0775, true);
        }
        @file_put_contents($file, (string) json_encode(['ok' => $ok]), LOCK_EX);
        return $ok;
    }

    /** Le même message a-t-il déjà été reçu dans les dernières 24 h ? */
    private static function seenRecently(string $message): bool
    {
        $message = trim($message);
        if (mb_strlen($message) < 30) {
            return false;
        }
        $file = Config::storagePath('locks/msg-' . substr(sha1(mb_strtolower($message)), 0, 20) . '.txt');
        $seen = is_file($file) && filemtime($file) > time() - 86400;
        if (!is_dir(\dirname($file))) {
            @mkdir(\dirname($file), 0775, true);
        }
        @file_put_contents($file, (string) time(), LOCK_EX);
        return $seen;
    }

    /**
     * Un message manifestement rédigé dans une autre langue que celle du
     * formulaire. C'est le cas du démarchage automatique, toujours en anglais.
     */
    private static function wrongLanguage(string $message, string $lang): bool
    {
        if (mb_strlen($message) < 120) {
            return false; // trop court pour conclure
        }
        $words = preg_split('/[^a-zà-ÿ]+/ui', mb_strtolower($message), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $fr = \count(array_intersect($words, self::MOTS_FR));
        $en = \count(array_intersect($words, self::MOTS_EN));

        return $lang === 'fr' ? ($en >= 4 && $fr <= 1) : ($fr >= 4 && $en <= 1);
    }

    private static function b64(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function unb64(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/'), true);
    }
}
