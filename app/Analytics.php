<?php
declare(strict_types=1);

/**
 * Mesure maison du tunnel de conversion (sans cookie tiers, sans
 * service externe).
 *
 * Le journal est un fichier JSONL par mois, écrit en ajout pur : une vue
 * de page coûte un append de quelques centaines d'octets, jamais la
 * réécriture d'un tableau complet. La lecture se fait en flux, ce qui
 * permet d'agréger des dizaines de milliers d'évènements sans les charger
 * tous en mémoire.
 */
final class Analytics
{
    public const STEPS = [
        'page_view' => 'Visite',
        'cta_click' => 'Clic CTA',
        'simulator_used' => 'Simulateur utilisé',
        'funnel_start' => 'Tunnel démarré',
        'funnel_step_1' => 'Étape 1 — situation',
        'funnel_step_2' => 'Étape 2 — projet',
        'funnel_step_3' => 'Étape 3 — coordonnées',
        'application' => 'Candidature envoyée',
        'lead' => 'Message de contact',
    ];

    /**
     * Évènements que le navigateur a le droit de déclarer.
     * Tous les autres sont émis par le serveur au moment où l'action a
     * réellement eu lieu : les accepter depuis l'extérieur permettrait de
     * fabriquer de fausses candidatures dans les statistiques.
     */
    public const CLIENT_EVENTS = ['cta_click', 'simulator_used'];

    /** Rétention des fichiers mensuels, en mois. */
    public const RETENTION_MONTHS = 13;

    public static function dir(): string
    {
        $dir = DATA_DIR . '/events';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    public static function file(?int $timestamp = null): string
    {
        return self::dir() . '/events-' . date('Y-m', $timestamp ?? time()) . '.jsonl';
    }

    public static function track(string $event, array $meta = []): void
    {
        if (!array_key_exists($event, self::STEPS)) {
            return;
        }
        $line = json_encode([
            'e' => $event,
            'p' => mb_substr((string) ($meta['page'] ?? ''), 0, 120),
            's' => mb_substr((string) ($meta['source'] ?? ''), 0, 60),
            'v' => visitor_hash(),
            't' => time(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($line === false) {
            return;
        }
        @file_put_contents(self::file(), $line . "\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * Parcourt les évènements des N derniers jours, fichier mensuel par
     * fichier mensuel, ligne par ligne.
     */
    public static function each(int $days, callable $fn): void
    {
        $since = time() - $days * 86400;
        $months = [];
        for ($t = $since; $t <= time() + 86400; $t += 86400 * 15) {
            $months[date('Y-m', $t)] = true;
        }
        $months[date('Y-m')] = true;

        foreach (array_keys($months) as $month) {
            $file = self::dir() . '/events-' . $month . '.jsonl';
            if (!is_file($file)) {
                continue;
            }
            $fh = @fopen($file, 'r');
            if ($fh === false) {
                continue;
            }
            while (($line = fgets($fh)) !== false) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $row = json_decode($line, true);
                if (!is_array($row) || (int) ($row['t'] ?? 0) < $since) {
                    continue;
                }
                $fn($row);
            }
            fclose($fh);
        }
    }

    /** Agrégats pour le tableau de bord. */
    public static function summary(int $days = 30): array
    {
        $counts = array_fill_keys(array_keys(self::STEPS), 0);
        $uniques = [];
        $daily = [];
        $sources = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $daily[date('Y-m-d', strtotime('-' . $i . ' days'))] = ['views' => 0, 'applications' => 0];
        }

        self::each($days, static function (array $ev) use (&$counts, &$uniques, &$daily, &$sources): void {
            $name = (string) ($ev['e'] ?? '');
            if (isset($counts[$name])) {
                $counts[$name]++;
            }
            if ($name === 'page_view') {
                $uniques[(string) ($ev['v'] ?? '')] = true;
            }
            $day = date('Y-m-d', (int) ($ev['t'] ?? 0));
            if (isset($daily[$day])) {
                if ($name === 'page_view') { $daily[$day]['views']++; }
                if ($name === 'application') { $daily[$day]['applications']++; }
            }
            if ($name === 'application' && ($ev['s'] ?? '') !== '') {
                $sources[(string) $ev['s']] = ($sources[(string) $ev['s']] ?? 0) + 1;
            }
        });

        $views = max(1, $counts['page_view']);
        return [
            'counts' => $counts,
            'visitors' => count($uniques),
            'daily' => $daily,
            'sources' => $sources,
            'conversion' => round(($counts['application'] / $views) * 100, 2),
            'funnel' => [
                ['label' => 'Visites', 'value' => $counts['page_view']],
                ['label' => 'Clics CTA', 'value' => $counts['cta_click']],
                ['label' => 'Tunnel démarré', 'value' => $counts['funnel_start']],
                ['label' => 'Coordonnées saisies', 'value' => $counts['funnel_step_3']],
                ['label' => 'Candidatures', 'value' => $counts['application']],
            ],
        ];
    }

    /**
     * Reprend un ancien data/events.json (format tableau) et le réécrit
     * dans les fichiers mensuels. Appelé une seule fois par l'installeur.
     */
    public static function migrateLegacy(): int
    {
        $legacy = DATA_DIR . '/events.json';
        if (!is_file($legacy)) {
            return 0;
        }
        $rows = json_decode((string) @file_get_contents($legacy), true);
        $moved = 0;
        if (is_array($rows)) {
            $buckets = [];
            foreach ($rows as $row) {
                $ts = strtotime((string) ($row['at'] ?? '')) ?: time();
                $line = json_encode([
                    'e' => (string) ($row['event'] ?? ''),
                    'p' => (string) ($row['page'] ?? ''),
                    's' => (string) ($row['source'] ?? ''),
                    'v' => (string) ($row['visitor'] ?? ''),
                    't' => $ts,
                ], JSON_UNESCAPED_UNICODE);
                if ($line === false) { continue; }
                $buckets[date('Y-m', $ts)][] = $line;
                $moved++;
            }
            foreach ($buckets as $month => $lines) {
                @file_put_contents(self::dir() . '/events-' . $month . '.jsonl', implode("\n", $lines) . "\n", FILE_APPEND | LOCK_EX);
            }
        }
        @unlink($legacy);
        @unlink($legacy . '.lock');
        return $moved;
    }
}
