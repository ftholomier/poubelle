<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Session;

/**
 * Historique des échanges avec l'assistant Régie, lu au back-office.
 *
 * Anonyme par construction : ni adresse IP, ni empreinte, ni compte, ni
 * cookie. Un fil de conversation est un numéro tiré au hasard à la première
 * question d'une session : il regroupe les relances et ne renvoie à personne.
 * Les adresses e-mail et les longs numéros qu'un visiteur aurait tapés sont
 * masqués avant l'écriture. Un fichier par mois, hors des sauvegardes, effacé
 * au-delà de la durée de conservation par la tâche planifiée.
 */
final class RegieHistory
{
    private const SESSION_KEY = 'regie_conv';

    /** Chemin interne tel que le site l'écrit : jamais d'adresse externe. */
    private const PATH = '#^/(?!/)[A-Za-z0-9\-/_%.]{0,200}$#';

    /** Durée de conservation, en mois ; 0 coupe l'historique. */
    public static function retention(): int
    {
        return max(0, (int) Config::get('regie.history_months', 12));
    }

    private static function dir(): string
    {
        return Config::path('data') . '/logs';
    }

    private static function file(string $month): string
    {
        return self::dir() . '/regie-' . $month . '.jsonl';
    }

    /**
     * @param array{question:string, answer:string, links:array<string,string>, source:string,
     *              page:string, lang:string, ms:int, model:string, note:string} $exchange
     */
    public static function record(array $exchange): void
    {
        if (self::retention() === 0) {
            return;
        }
        if (!is_dir(self::dir())) {
            @mkdir(self::dir(), 0775, true);
        }

        $line = json_encode([
            'at'     => date('c'),
            'conv'   => self::conversation(),
            'lang'   => I18n::isSupported($exchange['lang']) ? $exchange['lang'] : '',
            'page'   => self::page($exchange['page']),
            'q'      => self::scrub($exchange['question']),
            'a'      => self::scrub($exchange['answer']),
            'links'  => self::links($exchange['links']),
            'source' => $exchange['source'],
            'ms'     => $exchange['ms'],
            'model'  => $exchange['model'],
            'note'   => $exchange['note'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

        if ($line !== false) {
            @file_put_contents(self::file(date('Y-m')), $line . "\n", FILE_APPEND | LOCK_EX);
        }
    }

    /**
     * Numéro du fil en cours, tiré au hasard : il ne dérive ni de la session,
     * ni de l'adresse, et disparaît avec la session.
     */
    private static function conversation(): string
    {
        $conv = Session::get(self::SESSION_KEY);
        if (!is_string($conv) || preg_match('/^[a-f0-9]{12}$/', $conv) !== 1) {
            $conv = bin2hex(random_bytes(6));
            Session::set(self::SESSION_KEY, $conv);
        }
        return $conv;
    }

    /** L'assistant a oublié le fil : la question suivante en ouvre un nouveau. */
    public static function reset(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    /** Page d'où la question est partie, sans paramètres : ils peuvent porter un jeton. */
    private static function page(string $page): string
    {
        $path = (string) parse_url($page, PHP_URL_PATH);
        return preg_match(self::PATH, $path) === 1 ? $path : '';
    }

    /**
     * @param  array<mixed, mixed> $links
     * @return array<string, string> chemin interne => libellé
     */
    private static function links(array $links): array
    {
        $out = [];
        foreach ($links as $path => $label) {
            if (is_string($path) && preg_match(self::PATH, $path) === 1) {
                $out[$path] = mb_substr((string) $label, 0, 120);
            }
        }
        return $out;
    }

    /**
     * Ce qu'un visiteur aurait tapé de trop : une adresse e-mail, un numéro de
     * téléphone, de sécurité sociale ou de compte — toute suite d'au moins neuf
     * chiffres, séparateurs compris. Montants, années et codes postaux, plus
     * courts, restent lisibles ; un chiffre collé à une lettre ou à un chemin
     * (« j1027 », « /offres/… ») n'est pas un numéro.
     */
    public static function scrub(string $text): string
    {
        $text = (string) preg_replace('/[\p{L}\p{N}._%+\-]+@[\p{L}\p{N}\-]+(?:\.[\p{L}\p{N}\-]+)*\.\p{L}{2,}/u',
            '[e-mail]', $text);
        return (string) preg_replace('/(?<![\p{L}\p{N}\/_\-])\+?\d(?:[ .\-]?\d){8,}(?![\p{L}\p{N}\/_\-])/u',
            '[numéro]', $text);
    }

    /** @return array<int, string> mois disponibles, du plus récent au plus ancien */
    public static function months(): array
    {
        $months = [];
        foreach (glob(self::dir() . '/regie-*.jsonl') ?: [] as $file) {
            if (preg_match('/regie-(\d{4}-\d{2})\.jsonl$/', $file, $m) === 1 && (int) @filesize($file) > 0) {
                $months[] = $m[1];
            }
        }
        rsort($months);
        return $months;
    }

    /**
     * Échanges des mois demandés, dans l'ordre où ils ont eu lieu.
     *
     * @param  array<int, string> $months
     * @return array<int, array{at:string, conv:string, lang:string, page:string, q:string, a:string,
     *                          links:array<string,string>, source:string, ms:int, model:string, note:string,
     *                          ts:int}>
     */
    public static function exchanges(array $months): array
    {
        $months = array_values(array_unique(array_filter($months,
            static fn($m): bool => is_string($m) && preg_match('/^\d{4}-\d{2}$/', $m) === 1)));
        sort($months);

        $out = [];
        foreach ($months as $month) {
            $file = self::file($month);
            if (!is_file($file)) {
                continue;
            }
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $row = json_decode($line, true);
                if (!is_array($row) || !is_string($row['at'] ?? null) || !is_string($row['conv'] ?? null)) {
                    continue;
                }
                $out[] = [
                    'at'     => $row['at'],
                    // L'heure d'été change le décalage écrit dans « at » : on trie sur l'instant.
                    'ts'     => (int) strtotime($row['at']),
                    'conv'   => $row['conv'],
                    'lang'   => (string) ($row['lang'] ?? ''),
                    'page'   => self::page((string) ($row['page'] ?? '')),
                    'q'      => (string) ($row['q'] ?? ''),
                    'a'      => (string) ($row['a'] ?? ''),
                    'links'  => self::links((array) ($row['links'] ?? [])),
                    'source' => (string) ($row['source'] ?? ''),
                    'ms'     => (int) ($row['ms'] ?? 0),
                    'model'  => (string) ($row['model'] ?? ''),
                    'note'   => (string) ($row['note'] ?? ''),
                ];
            }
        }
        // Tri stable : deux échanges de la même seconde gardent l'ordre du fichier.
        usort($out, static fn(array $a, array $b): int => $a['ts'] <=> $b['ts']);
        return $out;
    }

    /**
     * Regroupe les échanges par fil, le plus récent d'abord.
     *
     * @param  array<int, array<string, mixed>> $exchanges dans l'ordre chronologique
     * @return array<int, array{conv:string, started:string, ended:string, lang:string, page:string, exchanges:array, last:int}>
     */
    public static function conversations(array $exchanges): array
    {
        $threads = [];
        foreach (array_values($exchanges) as $i => $exchange) {
            $conv = (string) $exchange['conv'];
            $threads[$conv] ??= ['conv' => $conv, 'started' => $exchange['at'], 'ended' => $exchange['at'],
                                 'lang' => $exchange['lang'], 'page' => $exchange['page'], 'exchanges' => []];
            $threads[$conv]['exchanges'][] = $exchange;
            $threads[$conv]['ended'] = $exchange['at'];
            // Rang du dernier échange : départage deux fils terminés dans la même seconde.
            $threads[$conv]['last'] = $i;
        }
        usort($threads, static fn(array $a, array $b): int => $b['last'] <=> $a['last']);
        return $threads;
    }

    /**
     * Fréquentation récente : questions du jour, des 7 et des 30 derniers
     * jours, fils de conversation, et réponses qui ne mènent à aucune page du
     * site — question hors sujet, ou page qui manque.
     *
     * @return array{today:int, week:int, month:int, conversations:int, unlinked:int}
     */
    public static function stats(): array
    {
        $since = strtotime('-30 days');
        $months = [];
        for ($t = strtotime(date('Y-m-01', $since)); $t <= time(); $t = strtotime('+1 month', $t)) {
            $months[] = date('Y-m', $t);
        }

        $stats = ['today' => 0, 'week' => 0, 'month' => 0, 'conversations' => 0, 'unlinked' => 0];
        $threads = [];
        $today = date('Y-m-d');
        $week = strtotime('-7 days');
        foreach (self::exchanges($months) as $exchange) {
            $at = $exchange['ts'];
            if ($at < $since) {
                continue;
            }
            $stats['month']++;
            $threads[$exchange['conv']] = true;
            $stats['unlinked'] += $exchange['links'] === [] ? 1 : 0;
            $stats['week'] += $at >= $week ? 1 : 0;
            $stats['today'] += date('Y-m-d', $at) === $today ? 1 : 0;
        }
        $stats['conversations'] = count($threads);
        return $stats;
    }

    /** Efface un fil de conversation, dans tous les mois où il figure. */
    public static function remove(string $conv): int
    {
        if (preg_match('/^[a-f0-9]{12}$/', $conv) !== 1) {
            return 0;
        }

        $removed = 0;
        foreach (self::months() as $month) {
            $handle = @fopen(self::file($month), 'c+');
            if ($handle === false) {
                continue;
            }
            // Même verrou que l'écriture : une question posée pendant
            // l'effacement n'est pas perdue.
            try {
                flock($handle, LOCK_EX);
                $kept = [];
                $dropped = 0;
                foreach (preg_split('/\R/', (string) stream_get_contents($handle)) ?: [] as $line) {
                    if ($line === '') {
                        continue;
                    }
                    $row = json_decode($line, true);
                    if (is_array($row) && ($row['conv'] ?? '') === $conv) {
                        $dropped++;
                        continue;
                    }
                    $kept[] = $line;
                }
                if ($dropped > 0) {
                    ftruncate($handle, 0);
                    rewind($handle);
                    fwrite($handle, $kept === [] ? '' : implode("\n", $kept) . "\n");
                    fflush($handle);
                    $removed += $dropped;
                }
            } finally {
                flock($handle, LOCK_UN);
                fclose($handle);
            }
        }
        return $removed;
    }

    /**
     * Efface les mois au-delà de la durée de conservation ; tout, si
     * l'historique est coupé. Appelé chaque jour par la tâche planifiée.
     *
     * @return int nombre de fichiers supprimés
     */
    public static function purge(): int
    {
        $keep = [];
        // Depuis le 1er du mois : « -1 month » un 31 retombe dans le même mois.
        $first = date('Y-m-01');
        for ($i = 0; $i < self::retention(); $i++) {
            $keep[] = date('Y-m', (int) strtotime($first . ' -' . $i . ' month'));
        }

        $removed = 0;
        foreach (glob(self::dir() . '/regie-*.jsonl') ?: [] as $file) {
            if (preg_match('/regie-(\d{4}-\d{2})\.jsonl$/', $file, $m) === 1 && !in_array($m[1], $keep, true)) {
                $removed += @unlink($file) ? 1 : 0;
            }
        }
        return $removed;
    }
}
