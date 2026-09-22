<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Request;

/**
 * Filtrage des dépôts publics, sans captcha ni service tiers.
 *
 * L'ancien site a reçu 434 annonces de spam en une nuit d'octobre 2025 parce
 * que le formulaire publiait sans rien vérifier. Trois signaux suffisent à
 * arrêter l'essentiel : un champ piège qu'un humain ne voit pas, le temps de
 * saisie, et la forme du contenu. Rien n'est refusé en silence à un humain :
 * au pire le dépôt part en file de modération.
 */
final class SpamGuard
{
    /** Nom du champ piège. Volontairement crédible pour un robot. */
    public const HONEYPOT = 'company_url_confirm';

    /** Champ caché portant l'instant d'ouverture du formulaire, signé. */
    public const STAMP = '_opened';

    /** Marque à poser dans le formulaire : horodatage signé + champ piège. */
    public static function fields(): string
    {
        $stamp = self::sign(time());
        return '<input type="hidden" name="' . self::STAMP . '" value="' . e($stamp) . '">'
             . '<div class="hp-field" aria-hidden="true">'
             . '<label for="' . self::HONEYPOT . '">Ne remplissez pas ce champ</label>'
             . '<input type="text" id="' . self::HONEYPOT . '" name="' . self::HONEYPOT . '"'
             . ' value="" tabindex="-1" autocomplete="off">'
             . '</div>';
    }

    /**
     * Verdict sur un dépôt.
     *
     * @return array{action:string, reasons:string[]}
     *         action : « publish », « pending » ou « reject »
     */
    public static function inspect(Request $request, array $record, string $type): array
    {
        $reasons = [];

        // 1. Champ piège rempli : seul un automate a pu le voir.
        if (trim((string) ($request->post[self::HONEYPOT] ?? '')) !== '') {
            return ['action' => 'reject', 'reasons' => ['champ piège rempli']];
        }

        // 2. Formulaire renvoyé trop vite, ou horodatage absent / falsifié.
        $elapsed = self::elapsed((string) ($request->post[self::STAMP] ?? ''));
        $min = (int) Config::get('moderation.min_secs', 4);
        if ($elapsed === null) {
            $reasons[] = 'horodatage du formulaire absent';
        } elseif ($elapsed < $min) {
            return ['action' => 'reject', 'reasons' => ['formulaire renvoyé en ' . $elapsed . ' s']];
        }

        // 3. Titre de dix lettres minuscules : signature exacte du spam de 2025.
        $title = trim((string) ($record['title'] ?? $record['name'] ?? ''));
        if (preg_match('/^[a-z]{10}$/', $title) === 1) {
            return ['action' => 'reject', 'reasons' => ['titre caractéristique du spam']];
        }

        $text = (string) ($record['description'] ?? $record['summary'] ?? '');

        // 4. Trop de liens dans un texte libre : à relire avant publication.
        $links = preg_match_all('#https?://|www\.#i', $text . ' ' . $title);
        if ($links > (int) Config::get('moderation.max_links', 2)) {
            $reasons[] = $links . ' liens dans le texte';
        }

        // 5. Alphabet sans rapport avec un site francophone.
        if (preg_match('/[\x{0400}-\x{04FF}\x{4E00}-\x{9FFF}\x{0600}-\x{06FF}]/u', $title . ' ' . $text) === 1) {
            $reasons[] = 'texte hors alphabet latin';
        }

        // 6. Mots-clés des vagues habituelles.
        $needle = mb_strtolower($title . ' ' . $text);
        foreach (['casino', 'viagra', 'crypto', 'bitcoin', 'porn', 'escort', 'loan', 'seo backlink'] as $word) {
            if (str_contains($needle, $word)) {
                $reasons[] = 'mot signalé : ' . $word;
                break;
            }
        }

        $mode = (string) Config::get('moderation.mode', 'auto');
        if ($mode === 'never') {
            return ['action' => 'publish', 'reasons' => $reasons];
        }
        if ($mode === 'always' || $reasons !== []) {
            return ['action' => 'pending', 'reasons' => $reasons ?: ['modération systématique']];
        }
        return ['action' => 'publish', 'reasons' => []];
    }

    /** Secondes écoulées depuis l'ouverture du formulaire, ou null si invalide. */
    private static function elapsed(string $value): ?int
    {
        if (!str_contains($value, '.')) {
            return null;
        }
        [$time, $mac] = explode('.', $value, 2);
        if (!ctype_digit($time) || !hash_equals(self::mac((int) $time), $mac)) {
            return null;
        }
        $elapsed = time() - (int) $time;
        // Un horodatage venu du futur ou vieux d'un jour est suspect.
        return $elapsed < 0 || $elapsed > 86400 ? null : $elapsed;
    }

    private static function sign(int $time): string
    {
        return $time . '.' . self::mac($time);
    }

    private static function mac(int $time): string
    {
        return substr(hash_hmac('sha256', 'form:' . $time, Secrets::appKey()), 0, 24);
    }
}
