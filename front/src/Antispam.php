<?php

declare(strict_types=1);

/**
 * Anti-spam des formulaires du site (devis, contact, rappel, message vocal, assistant).
 * Invisible pour les visiteurs : pas de case « je ne suis pas un robot ».
 *
 * 1. Barrages (robot certain) : champ piège rempli, envoi depuis un autre site, preuve JavaScript
 *    absente sur un formulaire qui n'existe qu'en JavaScript. Le robot reçoit un faux « merci »,
 *    rien n'est enregistré ; la tentative est comptée dans le journal.
 * 2. Score de suspicion (robot probable) : rapidité de remplissage, absence de geste humain, liens,
 *    mots de spam, alphabet étranger, message en anglais, e-mail jetable, numéro fantaisiste,
 *    doublon, logiciel d'envoi automatique… Au-delà du seuil réglé, la demande est gardée dans le
 *    statut « Spam » des Demandes, sans e-mail de notification : rien n'est perdu.
 */
final class Antispam
{
    /** Seuil de score par niveau de sévérité (réglage « antispam_level »). */
    public const LEVELS = ['souple' => 8, 'normal' => 5, 'strict' => 3];

    public const DEFAULT_WORDS = "viagra\ncialis\ncasino\npoker\nbitcoin\ncrypto\ncryptomonnaie\nforex\nbacklink\nbacklinks\nnetlinking\nseo\nréférencement\npremière page de google\naugmenter votre trafic\nrédacteur web\nprêt entre particuliers\ngagner de l'argent\nrendement garanti\ninvestissement rentable\nsearch engine\nfirst page\nguest post\nguestpost\nlink insertion\nsponsored post\narticle sponsorisé\ndofollow\nlink building\nweb design\nwebsite design\nlead generation\nmarketing services\ndear sir\ndear business owner\nbusiness loan\nunsubscribe\nporn\nescort\ndating";

    private const DISPOSABLE = [
        'mailinator.com', 'yopmail.com', 'yopmail.fr', 'yopmail.net', 'guerrillamail.com', 'guerrillamail.net', 'sharklasers.com',
        '10minutemail.com', 'temp-mail.org', 'tempmail.com', 'tempmail.net', 'tempail.com', 'trashmail.com', 'trashmail.fr',
        'getnada.com', 'dispostable.com', 'maildrop.cc', 'throwawaymail.com', 'fakeinbox.com', 'mohmal.com', 'mailnesia.com',
        'mintemail.com', 'emailondeck.com', 'spamgourmet.com', 'jetable.org', 'mail-temporaire.fr', 'moakt.com', 'burnermail.io',
        'minuteinbox.com', 'mailcatch.com', 'tempinbox.com', 'spambox.us', 'grr.la', 'mvrht.com', 'byom.de', 'wegwerfmail.de',
    ];

    public static function enabled(): bool
    {
        return (bool) Settings::get('antispam_enabled', true);
    }

    // ------------------------------------------------------------------ barrages

    /** Envoi venu d'un autre site (en-tête Origin d'une autre adresse) : refusé. */
    public static function originOk(): bool
    {
        $o = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
        if ($o === '' || $o === 'null') {
            return true;
        }
        $host = strtolower((string) parse_url($o, PHP_URL_HOST));
        $own = array_filter([strtolower((string) ($_SERVER['HTTP_HOST'] ?? '')), strtolower((string) parse_url((string) cfg('site_url', ''), PHP_URL_HOST))]);
        foreach ($own as $h) {
            if ($host === preg_replace('/:\d+$/', '', $h)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Preuve JavaScript : le navigateur renvoie le début du jeton à l'envers, suivi du nombre de gestes
     * réels (clic, touche, toucher) faits sur la page. Un robot qui poste sans exécuter la page ne l'a pas.
     */
    public static function proofOk(string $pf, string $token): bool
    {
        return (bool) preg_match('/^(\d{10})\.(\d{1,3})$/', $pf, $m)
            && hash_equals(strrev(substr($token, 0, 10)), $m[1])
            && (int) $m[2] > 0;
    }

    // ------------------------------------------------------------------ score

    /**
     * Score de suspicion d'un envoi.
     * @param array $in      données brutes reçues (token, pf…)
     * @param array $fields  ['nom' => …, 'email' => …, 'tel' => …, 'msg' => …]
     * @param int   $minSeconds  temps minimal plausible pour remplir ce formulaire
     * @return array{spam: bool, score: int, reasons: string[]}
     */
    public static function check(array $in, string $form, array $fields, int $minSeconds = 3, bool $contentOnly = false): array
    {
        if (!self::enabled() && !$contentOnly) {
            return ['spam' => false, 'score' => 0, 'reasons' => []];
        }
        $score = 0;
        $why = [];
        $add = function (int $pts, string $reason) use (&$score, &$why): void {
            $score += $pts;
            $why[] = $reason;
        };
        $token = Http::str($in, 'token', 80);
        $nom = trim((string) ($fields['nom'] ?? ''));
        $email = mb_strtolower(trim((string) ($fields['email'] ?? '')));
        $tel = trim((string) ($fields['tel'] ?? ''));
        $msg = trim((string) ($fields['msg'] ?? ''));
        $all = $nom . "\n" . $msg;

        // Comportement (ignoré par l'outil de test du back-office)
        $age = $contentOnly ? null : Http::tokenAge($token);
        if ($age !== null && $age < $minSeconds) {
            $add(4, 'Rempli en ' . $age . ' s (trop rapide pour un humain)');
        }
        if (!$contentOnly && !self::proofOk(Http::str($in, 'pf', 20), $token)) {
            $add(3, 'Aucun geste humain détecté sur la page (clic, frappe, toucher)');
        }
        $ua = $contentOnly ? 'test' : (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        if ($ua === '' || preg_match('/curl|wget|python|httpclient|okhttp|go-http|java\/|libwww|scrapy|headless|phantom|\bbot\b|spider|crawler/i', $ua)) {
            $add(4, 'Envoyé par un logiciel automatique (' . ($ua === '' ? 'navigateur inconnu' : mb_substr($ua, 0, 40)) . ')');
        }
        if (!$contentOnly && trim((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')) === '') {
            $add(1, 'Navigateur sans langue déclarée');
        }

        // Contenu
        $links = preg_match_all('~(?:https?://|www\.)[^\s<>"\'\])]+|\b[a-z0-9-]{2,}\.(?:com|net|org|ru|info|biz|xyz|top|online|site|shop|io|co|me|click|link)\b~i', $all);
        if ($links >= 3) {
            $add(6, $links . ' liens dans le message');
        } elseif ($links === 2) {
            $add(4, '2 liens dans le message');
        } elseif ($links === 1) {
            $add(2, 'Un lien dans le message');
        }
        if (preg_match('~<a\s|\[url|\[link|href=~i', $all)) {
            $add(5, 'Code de lien (HTML ou BBCode) dans le message');
        }
        $hits = self::wordHits($all);
        if ($hits) {
            $add(min(9, 3 * count($hits)), 'Mots de spam : ' . implode(', ', array_slice($hits, 0, 4)));
        }
        if (Settings::get('antispam_scripts', true) && preg_match('/[\p{Cyrillic}\p{Han}\p{Hiragana}\p{Katakana}\p{Arabic}\p{Hebrew}\p{Thai}\p{Hangul}\p{Devanagari}\p{Greek}]/u', $all)) {
            $add(6, 'Alphabet étranger (cyrillique, chinois, arabe…)');
        }
        if (Settings::get('antispam_english', true) && self::looksEnglish($msg)) {
            $add(4, 'Message rédigé en anglais');
        }
        if (preg_match('~https?://|www\.~i', $nom)) {
            $add(5, 'Lien dans le champ « nom »');
        } elseif (preg_match_all('/\d/', $nom) >= 3) {
            $add(2, 'Chiffres dans le nom');
        }
        if (mb_strlen($nom) > 60) {
            $add(2, 'Nom anormalement long');
        }
        if ($email !== '' && Settings::get('antispam_disposable', true)) {
            $dom = substr((string) strrchr($email, '@'), 1);
            if (in_array($dom, self::DISPOSABLE, true)) {
                $add(4, 'Adresse e-mail jetable (' . $dom . ')');
            }
        }
        if ($tel !== '') {
            $d = preg_replace('/\D/', '', $tel);
            if (preg_match('/^(\d)\1{7,}$/', substr($d, -10)) || in_array(substr($d, -10), ['0123456789', '1234567890', '9876543210', '0612345678'], true)) {
                $add(4, 'Numéro de téléphone fantaisiste');
            } elseif (str_starts_with($tel, '+') || str_starts_with($d, '00')) {
                if (!preg_match('/^(?:\+|00)(33|32|41|49|352|262|590|594|596|687|689)/', preg_replace('/[^\d+]/', '', $tel))) {
                    $add(2, 'Numéro de téléphone étranger');
                }
            } elseif (!str_starts_with($d, '0') || strlen($d) !== 10) {
                $add(2, 'Numéro qui n\'a pas le format français (10 chiffres commençant par 0)');
            }
        }
        $letters = preg_replace('/[^\p{L}]/u', '', $msg);
        if (mb_strlen($letters) > 25 && $letters === mb_strtoupper($letters)) {
            $add(1, 'Message tout en majuscules');
        }
        if ($msg !== '' && mb_strlen($msg) > 20 && self::isDuplicate($msg)) {
            $add(4, 'Message identique à une demande des dernières 24 h');
        }

        $limit = self::LEVELS[(string) Settings::get('antispam_level', 'normal')] ?? 5;
        $spam = $score >= $limit;
        if ($spam && !$contentOnly) {
            self::log($form, 'spam', $score);
        }
        return ['spam' => $spam, 'score' => $score, 'reasons' => $why, 'limit' => $limit];
    }

    /** Mots ou expressions de la liste interdite présents dans le texte (accents et majuscules ignorés). */
    public static function wordHits(string $text): array
    {
        $norm = self::norm($text);
        $list = Settings::get('antispam_words', self::DEFAULT_WORDS);
        $list = is_array($list) ? implode("\n", $list) : (string) $list;
        $hits = [];
        foreach (preg_split('/\R/', $list) ?: [] as $w) {
            $w = trim($w);
            if ($w === '') {
                continue;
            }
            // Le mot seul ou au pluriel (« guest post » attrape aussi « guest posts »)
            if (preg_match('/(?<![\p{L}\p{N}])' . preg_quote(self::norm($w), '/') . '(?:s|x)?(?![\p{L}\p{N}])/u', $norm)) {
                $hits[] = $w;
            }
        }
        return $hits;
    }

    private static function norm(string $s): string
    {
        $s = mb_strtolower($s);
        $t = class_exists('Normalizer') ? (string) Normalizer::normalize($s, Normalizer::FORM_D) : $s;
        return (string) preg_replace('/\p{Mn}/u', '', $t);
    }

    /** Message nettement en anglais (beaucoup de mots anglais courants, presque aucun mot français). */
    public static function looksEnglish(string $msg): bool
    {
        if (mb_strlen($msg) < 30) {
            return false;
        }
        $words = preg_split('/[^\p{L}\']+/u', mb_strtolower($msg), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        static $en = ['the', 'and', 'you', 'your', 'we', 'our', 'are', 'is', 'for', 'with', 'this', 'that', 'have', 'will', 'can', 'please', 'website', 'business', 'would', 'could', 'hello', 'hi', 'dear', 'of', 'to', 'in', 'my', 'it', 'be', 'get', 'more', 'services', 'offer'];
        static $fr = ['le', 'la', 'les', 'de', 'des', 'du', 'et', 'est', 'pour', 'vous', 'une', 'un', 'je', 'nous', 'bonjour', 'merci', 'avec', 'sur', 'pas', 'mon', 'ma', 'mes', 'votre', 'vos', 'à', 'au', 'aux', 'en', 'il', 'qui', 'que', 'ce', 'cette', 'devis', 'fenêtre', 'fenêtres', 'porte', 'volet', 'volets'];
        $nEn = count(array_filter($words, fn($w) => in_array($w, $en, true)));
        $nFr = count(array_filter($words, fn($w) => in_array($w, $fr, true)));
        return $nEn >= 4 && $nFr <= 1;
    }

    private static function isDuplicate(string $msg): bool
    {
        $key = self::norm(preg_replace('/\s+/', ' ', $msg));
        $since = date('c', time() - 86400);
        foreach (Store::get('leads', []) as $l) {
            if (($l['at'] ?? '') >= $since && self::norm(preg_replace('/\s+/', ' ', (string) ($l['msg'] ?? ''))) === $key) {
                return true;
            }
        }
        return false;
    }

    // ------------------------------------------------------------------ journal

    /** Garde une trace des envois bloqués ou mis en spam (les 1 000 derniers). */
    public static function log(string $form, string $reason, int $score = 0): void
    {
        $e = ['at' => date('c'), 'form' => $form, 'reason' => $reason];
        if ($score) {
            $e['score'] = $score;
        }
        Store::update('antispam', function (array $d) use ($e) {
            $d['log'] = array_slice(array_merge([$e], (array) ($d['log'] ?? [])), 0, 1000);
            return $d;
        });
    }

    public const REASONS = [
        'honeypot' => 'Champ piège rempli (robot)',
        'origin' => 'Envoi depuis un autre site',
        'proof' => 'Formulaire envoyé sans la page (robot)',
        'spam' => 'Mise en spam (score élevé)',
        'quota' => 'Assistant : quota du jour atteint',
    ];

    /** Bilan des N derniers jours : total, par raison, par formulaire, par jour. */
    public static function stats(int $days = 30): array
    {
        $since = date('c', time() - $days * 86400);
        $log = array_values(array_filter((array) (Store::get('antispam', [])['log'] ?? []), fn($e) => ($e['at'] ?? '') >= $since));
        $by = $forms = [];
        foreach ($log as $e) {
            $by[$e['reason']] = ($by[$e['reason']] ?? 0) + 1;
            $forms[$e['form']] = ($forms[$e['form']] ?? 0) + 1;
        }
        arsort($by);
        arsort($forms);
        return ['total' => count($log), 'reasons' => $by, 'forms' => $forms, 'last' => array_slice($log, 0, 15)];
    }

    // ------------------------------------------------------------------ assistant

    /** Plafond global de questions à l'assistant par jour (protège la facture Gemini contre les abus). */
    public static function botQuotaOk(): bool
    {
        $max = (int) Settings::get('bot_daily_max', 400);
        if ($max <= 0) {
            return true;
        }
        $ok = true;
        Store::update('botquota', function (array $q) use ($max, &$ok) {
            $day = date('Y-m-d');
            if (($q['day'] ?? '') !== $day) {
                $q = ['day' => $day, 'n' => 0];
            }
            if ($q['n'] >= $max) {
                $ok = false;
                return $q;
            }
            $q['n']++;
            return $q;
        });
        return $ok;
    }
}
