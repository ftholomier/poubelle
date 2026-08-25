<?php
declare(strict_types=1);

/**
 * Mesure maison du tunnel de conversion (sans cookie tiers, sans
 * service externe) : chaque étape franchie est journalisée puis
 * agrégée dans le tableau de bord du back-office.
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

    public static function track(string $event, array $meta = []): void
    {
        if (!array_key_exists($event, self::STEPS)) {
            return;
        }
        Store::mutate('events', static function (array $rows) use ($event, $meta): array {
            $rows[] = [
                'event' => $event,
                'page' => substr((string) ($meta['page'] ?? ''), 0, 120),
                'source' => substr((string) ($meta['source'] ?? ''), 0, 60),
                'visitor' => visitor_hash(),
                'at' => date('c'),
            ];
            // Rétention glissante : 20 000 derniers évènements.
            return count($rows) > 20000 ? array_slice($rows, -20000) : $rows;
        });
    }

    /** Agrégats pour le tableau de bord. */
    public static function summary(int $days = 30): array
    {
        $since = strtotime('-' . $days . ' days');
        $events = Store::read('events');
        $counts = array_fill_keys(array_keys(self::STEPS), 0);
        $uniques = [];
        $daily = [];
        $sources = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $daily[date('Y-m-d', strtotime('-' . $i . ' days'))] = ['views' => 0, 'applications' => 0];
        }

        foreach ($events as $ev) {
            $ts = strtotime((string) ($ev['at'] ?? ''));
            if ($ts === false || $ts < $since) { continue; }
            $name = (string) ($ev['event'] ?? '');
            if (isset($counts[$name])) { $counts[$name]++; }
            if ($name === 'page_view') {
                $uniques[(string) ($ev['visitor'] ?? '')] = true;
            }
            $day = date('Y-m-d', $ts);
            if (isset($daily[$day])) {
                if ($name === 'page_view') { $daily[$day]['views']++; }
                if ($name === 'application') { $daily[$day]['applications']++; }
            }
            if ($name === 'application' && ($ev['source'] ?? '') !== '') {
                $sources[(string) $ev['source']] = ($sources[(string) $ev['source']] ?? 0) + 1;
            }
        }

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
}
