<?php
declare(strict_types=1);

namespace LD;

/**
 * Coût des appels IA du site, d'après les tarifs officiels des fournisseurs.
 *
 * - Tarifs intégrés (USD par million de jetons) relevés sur les pages officielles d'Anthropic et de Google,
 *   avec les changements de prix annoncés à leur date d'effet ;
 * - pour les autres fournisseurs : catalogue public d'OpenRouter (prix publics des fournisseurs), actualisé chaque semaine ;
 * - tarif saisi à la main dans le back-office : prioritaire sur tout le reste.
 * Les jetons consommés sont gardés par jour et par modèle (data/ai-usage.json) : le coût est recalculé à l'affichage,
 * donc une correction de tarif s'applique aussi à l'historique.
 */
final class AiCosts
{
    private const FILE = 'data/ai-prices.json';
    private const CATALOG = 'cache/ai-catalog.json';
    public const CATALOG_URL = 'https://openrouter.ai/api/v1/models';

    /** Relevé des tarifs intégrés (pages officielles). */
    public const PRICES_DATE = '27/10/2026';

    /**
     * Tarifs officiels : identifiant normalisé => [entrée, sortie] en USD / million de jetons,
     * ou liste de périodes [['until' => 'AAAA-MM-JJ', 'in' => …, 'out' => …], …, ['in' => …, 'out' => …]].
     * Modèles d'image : 'image' => [taille => USD par image].
     */
    private const OFFICIAL = [
        // Anthropic — platform.claude.com (tarifs API standard)
        'claude-fable-5-1'   => ['in' => 10, 'out' => 50, 'src' => 'anthropic'],
        'claude-mythos-5-1'  => ['in' => 10, 'out' => 50, 'src' => 'anthropic'],
        'claude-fable-5'     => ['in' => 10, 'out' => 50, 'src' => 'anthropic'],
        'claude-opus-5-5'    => ['in' => 4, 'out' => 20, 'src' => 'anthropic'],
        'claude-opus-5'      => ['in' => 5, 'out' => 25, 'src' => 'anthropic'],
        'claude-opus-4-8'    => ['in' => 5, 'out' => 25, 'src' => 'anthropic'],
        'claude-opus-4-7'    => ['in' => 5, 'out' => 25, 'src' => 'anthropic'],
        'claude-opus-4-6'    => ['in' => 5, 'out' => 25, 'src' => 'anthropic'],
        'claude-opus-4-5'    => ['in' => 5, 'out' => 25, 'src' => 'anthropic'],
        'claude-opus-4-1'    => ['in' => 15, 'out' => 75, 'src' => 'anthropic'],
        'claude-opus-4'      => ['in' => 15, 'out' => 75, 'src' => 'anthropic'],
        'claude-sonnet-5-5'  => ['in' => 2, 'out' => 10, 'src' => 'anthropic'],
        'claude-sonnet-5'    => ['in' => 2, 'out' => 10, 'src' => 'anthropic'],
        'claude-sonnet-4-6'  => ['in' => 3, 'out' => 15, 'src' => 'anthropic'],
        'claude-sonnet-4-5'  => ['in' => 3, 'out' => 15, 'src' => 'anthropic'],
        'claude-sonnet-4'    => ['in' => 3, 'out' => 15, 'src' => 'anthropic'],
        'claude-haiku-5-5'   => ['in' => 0.10, 'out' => 0.50, 'src' => 'anthropic'],
        'claude-haiku-4-5'   => ['in' => 1, 'out' => 5, 'src' => 'anthropic'],
        'claude-3-5-haiku'   => ['in' => 0.80, 'out' => 4, 'src' => 'anthropic'],
        'claude-3-haiku'     => ['in' => 0.25, 'out' => 1.25, 'src' => 'anthropic'],
        // Google — ai.google.dev/gemini-api/docs/pricing (niveau payant standard, ≤ 200 k jetons)
        'gemini-4-argon'        => ['periods' => [['until' => '2026-11-30', 'in' => 2, 'out' => 10], ['in' => 4, 'out' => 20]], 'src' => 'google'],
        'gemini-3-8-flash'      => ['periods' => [['until' => '2026-12-31', 'in' => 0.75, 'out' => 3.75], ['in' => 1.50, 'out' => 7.50]], 'src' => 'google'],
        'gemini-3-6-flash'      => ['periods' => [['until' => '2026-12-31', 'in' => 0.75, 'out' => 3.75], ['in' => 1.50, 'out' => 7.50]], 'src' => 'google'],
        'gemini-3-5-flash-lite' => ['in' => 0.30, 'out' => 2.50, 'src' => 'google'],
        'gemini-3-1-pro-preview' => ['in' => 2, 'out' => 12, 'src' => 'google'],
        'gemini-3-1-flash-lite' => ['in' => 0.25, 'out' => 1.50, 'src' => 'google'],
        'gemini-3-flash-preview' => ['in' => 0.50, 'out' => 3, 'src' => 'google'],
        'gemini-2-5-pro'        => ['in' => 1.25, 'out' => 10, 'src' => 'google'],
        'gemini-2-5-flash'      => ['in' => 0.30, 'out' => 2.50, 'src' => 'google'],
        'gemini-2-5-flash-lite' => ['in' => 0.10, 'out' => 0.40, 'src' => 'google'],
        // Google — modèles d'image « Nano Banana » (prix par image générée + jetons d'entrée)
        'gemini-nano-banana-2-1'      => ['in' => 1.50, 'out' => 7.50, 'image' => ['1K' => 0.0336, '2K' => 0.0504, '4K' => 0.113], 'src' => 'google'],
        'gemini-3-1-flash-image'      => ['in' => 0.50, 'out' => 3, 'image' => ['0.5K' => 0.045, '1K' => 0.067, '2K' => 0.101, '4K' => 0.151], 'src' => 'google'],
        'gemini-3-1-flash-lite-image' => ['in' => 0.25, 'out' => 1.50, 'image' => ['1K' => 0.0336], 'src' => 'google'],
        'gemini-3-pro-image'          => ['in' => 2, 'out' => 12, 'image' => ['1K' => 0.134, '2K' => 0.134, '4K' => 0.24], 'src' => 'google'],
        'gemini-2-5-flash-image'      => ['in' => 0.30, 'out' => 0, 'image' => ['1K' => 0.039], 'src' => 'google'],
        // OpenAI — transcription des vocaux (USD par minute d'audio)
        'whisper-1'              => ['in' => 0, 'out' => 0, 'minute' => 0.006, 'src' => 'openai'],
        'gpt-4o-transcribe'      => ['in' => 0, 'out' => 0, 'minute' => 0.006, 'src' => 'openai'],
        'gpt-4o-mini-transcribe' => ['in' => 0, 'out' => 0, 'minute' => 0.003, 'src' => 'openai'],
    ];

    /** Préfixe du catalogue OpenRouter pour chaque fournisseur. */
    private const CATALOG_PREFIX = ['anthropic' => 'anthropic', 'openai' => 'openai', 'gemini' => 'google', 'mistral' => 'mistralai', 'deepseek' => 'deepseek', 'xai' => 'x-ai'];

    public const SOURCES = [
        'manual'    => 'Saisi par toi',
        'anthropic' => 'Officiel Anthropic',
        'google'    => 'Officiel Google',
        'openai'    => 'Officiel OpenAI',
        'catalog'   => 'Catalogue public (OpenRouter)',
    ];

    // ------------------------------------------------------------------ réglages

    private static ?array $settings = null;
    private static ?array $catalog = null;

    public static function settings(): array
    {
        return self::$settings ??= Store::read(self::FILE, []) + ['rate' => 0.86, 'budget' => 0, 'overrides' => [], 'alerted' => ''];
    }

    public static function saveSettings(array $s): void
    {
        Store::write(self::FILE, $s);
        self::$settings = null;
    }

    /** Taux de conversion USD → EUR (réglable). */
    public static function rate(): float
    {
        $r = (float) self::settings()['rate'];
        return $r > 0 ? $r : 0.86;
    }

    // ------------------------------------------------------------------ tarifs

    private static function norm(string $id): string
    {
        $id = strtolower(trim($id));
        $id = (string) preg_replace(['~^models/~', '~^[a-z0-9_-]+/~', '~@[0-9a-z]+$~', '~-(\d{8}|\d{4}-\d{2}-\d{2})$~'], '', $id);
        return str_replace(['.', '_'], '-', $id);
    }

    /** Entrée la plus précise d'une table dont la clé correspond à l'identifiant (exact, sinon plus long préfixe). */
    private static function match(string $norm, array $table): ?array
    {
        if (isset($table[$norm])) {
            return $table[$norm] + ['key' => $norm];
        }
        $best = null;
        foreach ($table as $k => $v) {
            if (str_starts_with($norm, $k . '-') && ($best === null || strlen($k) > strlen($best))) {
                $best = $k;
            }
        }
        return $best !== null ? $table[$best] + ['key' => $best] : null;
    }

    /**
     * Tarif d'un modèle à une date : ['in', 'out' (USD / M jetons), 'image' => [taille => USD]|null, 'minute' => USD|null,
     * 'src' => clé de SOURCES, 'approx' => bool] ou null si inconnu.
     */
    public static function price(string $provider, string $model, ?string $date = null): ?array
    {
        $date ??= date('Y-m-d');
        $o = self::settings()['overrides'][$provider . ':' . $model] ?? null;
        if (is_array($o) && (isset($o['in']) || isset($o['out']) || isset($o['image']))) {
            return ['in' => (float) ($o['in'] ?? 0), 'out' => (float) ($o['out'] ?? 0), 'image' => isset($o['image']) && $o['image'] !== '' ? ['*' => (float) $o['image']] : null, 'minute' => null, 'src' => 'manual', 'approx' => false];
        }
        $norm = self::norm($model);
        if ($provider !== 'openrouter' && ($p = self::match($norm, self::OFFICIAL))) {
            $in = $p['in'] ?? 0;
            $out = $p['out'] ?? 0;
            foreach ($p['periods'] ?? [] as $per) {
                if (!isset($per['until']) || $date <= $per['until']) {
                    [$in, $out] = [$per['in'], $per['out']];
                    break;
                }
            }
            return ['in' => (float) $in, 'out' => (float) $out, 'image' => $p['image'] ?? null, 'minute' => $p['minute'] ?? null, 'src' => $p['src'], 'approx' => false];
        }
        $cat = self::catalog();
        if ($provider === 'openrouter' && isset($cat[$model])) {
            return ['in' => $cat[$model][0], 'out' => $cat[$model][1], 'image' => null, 'minute' => null, 'src' => 'catalog', 'approx' => false];
        }
        $prefix = self::CATALOG_PREFIX[$provider] ?? null;
        $table = [];
        foreach ($cat as $id => $pr) {
            if ($prefix === null || str_starts_with($id, $prefix . '/')) {
                $table[self::norm($id)] ??= $pr;
            }
        }
        if ($p = self::match($norm, $table)) {
            // Groq & co. hébergent des modèles ouverts à leurs propres prix : le catalogue donne alors un ordre de grandeur.
            return ['in' => $p[0], 'out' => $p[1], 'image' => null, 'minute' => null, 'src' => 'catalog', 'approx' => $prefix === null || $p['key'] !== $norm];
        }
        return null;
    }

    /**
     * Prix d'une recherche web faite par l'IA (USD) : Anthropic 10 $ / 1 000 recherches ; Google 14 $ / 1 000 requêtes
     * (Gemini 3 et suivants, 5 000 gratuites par mois non déduites) ou 35 $ / 1 000 (Gemini 2.5).
     */
    public static function searchPrice(string $provider, string $model): float
    {
        return match ($provider) {
            'anthropic' => 0.010,
            'gemini'    => str_contains(self::norm($model), 'gemini-2-') ? 0.035 : 0.014,
            default     => 0.0,
        };
    }

    /** Coût en USD d'un appel (jetons, images générées par taille, minutes d'audio, recherches web). */
    public static function cost(string $provider, string $model, int $in, int $out, array $images = [], float $minutes = 0, ?string $date = null, int $searches = 0): ?float
    {
        $p = self::price($provider, $model, $date);
        if (!$p) {
            return null;
        }
        $usd = $in / 1e6 * $p['in'] + $out / 1e6 * $p['out'] + $searches * self::searchPrice($provider, $model);
        foreach ($images as $size => $n) {
            $per = $p['image'] ? ($p['image'][$size] ?? $p['image']['*'] ?? reset($p['image'])) : 0;
            $usd += $n * (float) $per;
        }
        if ($minutes > 0 && $p['minute']) {
            $usd += $minutes * $p['minute'];
        }
        return $usd;
    }

    // ------------------------------------------------------------------ catalogue public (OpenRouter)

    /** @return array<string, array{0: float, 1: float}> identifiant => [entrée, sortie] USD / M jetons */
    public static function catalog(): array
    {
        return self::$catalog ??= Store::read(self::CATALOG, [])['models'] ?? [];
    }

    public static function catalogDate(): ?int
    {
        return Store::read(self::CATALOG, [])['fetched_at'] ?? null;
    }

    /** Actualise le catalogue (bouton du back-office et tâche planifiée hebdomadaire). */
    public static function refreshCatalog(): int
    {
        $r = Http::request('GET', self::CATALOG_URL, [], null, 30);
        if ($r['error'] || $r['status'] !== 200 || !is_array($r['json']['data'] ?? null)) {
            throw new \RuntimeException('Catalogue des tarifs injoignable' . ($r['error'] ? ' : ' . $r['error'] : ' (' . $r['status'] . ')'));
        }
        $out = [];
        foreach ($r['json']['data'] as $m) {
            $id = (string) ($m['id'] ?? '');
            if ($id === '' || str_contains($id, ':') || !isset($m['pricing']['prompt'], $m['pricing']['completion'])) {
                continue;
            }
            $in = (float) $m['pricing']['prompt'] * 1e6;
            $outp = (float) $m['pricing']['completion'] * 1e6;
            if ($in < 0 || $outp < 0) {
                continue;
            }
            $out[$id] = [round($in, 4), round($outp, 4)];
        }
        Store::write(self::CATALOG, ['fetched_at' => time(), 'models' => $out]);
        self::$catalog = null;
        return count($out);
    }

    public static function refreshCatalogIfDue(): ?string
    {
        if ((self::catalogDate() ?? 0) > time() - 7 * 86400) {
            return null;
        }
        try {
            return 'Tarifs IA actualisés (' . self::refreshCatalog() . ' modèles)';
        } catch (\Throwable $e) {
            return 'Tarifs IA : ' . $e->getMessage();
        }
    }

    // ------------------------------------------------------------------ consommation

    /**
     * Enregistre un appel dans data/ai-usage.json (par jour : total, par fonctionnalité, par modèle).
     * $images : [taille => nombre] ; $minutes : durée d'audio transcrite.
     */
    public static function record(string $feature, string $provider, string $model, bool $ok, int $in, int $out, array $images = [], float $minutes = 0, int $searches = 0): void
    {
        $day = date('Y-m-d');
        $key = $provider . ':' . $model;
        Store::update('data/ai-usage.json', static function (array &$u) use ($day, $feature, $key, $ok, $in, $out, $images, $minutes, $searches): void {
            $d = $u[$day] ?? [];
            $d += ['calls' => 0, 'errors' => 0, 'in' => 0, 'out' => 0, 'features' => [], 'models' => []];
            $d['calls']++;
            $d['errors'] += $ok ? 0 : 1;
            $d['in'] += $in;
            $d['out'] += $out;
            $d['features'][$feature] = ($d['features'][$feature] ?? 0) + 1;
            $m = $d['models'][$key] ?? [];
            $m += ['calls' => 0, 'in' => 0, 'out' => 0, 'images' => [], 'minutes' => 0, 'searches' => 0, 'features' => []];
            $m['calls']++;
            $m['searches'] += $searches;
            $m['in'] += $in;
            $m['out'] += $out;
            foreach ($images as $size => $n) {
                $m['images'][$size] = ($m['images'][$size] ?? 0) + $n;
            }
            $m['minutes'] = round($m['minutes'] + $minutes, 2);
            $f = $m['features'][$feature] ?? ['calls' => 0, 'in' => 0, 'out' => 0, 'images' => 0];
            $f['calls']++;
            $f['in'] += $in;
            $f['out'] += $out;
            $f['images'] += array_sum($images);
            $f['searches'] = ($f['searches'] ?? 0) + $searches;
            $m['features'][$feature] = $f;
            $d['models'][$key] = $m;
            $u[$day] = $d;
            ksort($u);
            $u = array_slice($u, -400, null, true);
        });
        self::checkBudget();
    }

    /**
     * Rapport sur une période : total, par jour, par fonctionnalité, par modèle (coûts en USD).
     * Les jours enregistrés avant le suivi par modèle sont reconstitués depuis le journal des appels (logs/ai.log).
     */
    public static function report(string $from, string $to): array
    {
        $usage = self::backfill(Store::read('data/ai-usage.json', []));
        $rep = ['usd' => 0.0, 'calls' => 0, 'errors' => 0, 'in' => 0, 'out' => 0, 'images' => 0, 'searches' => 0, 'unknown' => [], 'days' => [], 'features' => [], 'models' => []];
        for ($t = strtotime($from); $t <= strtotime($to); $t += 86400) {
            $rep['days'][date('Y-m-d', $t)] = 0.0;
        }
        foreach ($usage as $day => $d) {
            if ($day < $from || $day > $to) {
                continue;
            }
            $rep['calls'] += (int) ($d['calls'] ?? 0);
            $rep['errors'] += (int) ($d['errors'] ?? 0);
            foreach ($d['models'] ?? [] as $key => $m) {
                [$provider, $model] = array_pad(explode(':', (string) $key, 2), 2, '');
                $usd = self::cost($provider, $model, (int) $m['in'], (int) $m['out'], (array) ($m['images'] ?? []), (float) ($m['minutes'] ?? 0), $day, (int) ($m['searches'] ?? 0));
                $priced = $usd !== null;
                if (!$priced) {
                    $rep['unknown'][$key] = true;
                    $usd = 0.0;
                }
                $rep['usd'] += $usd;
                $rep['in'] += (int) $m['in'];
                $rep['out'] += (int) $m['out'];
                $rep['images'] += (int) array_sum((array) ($m['images'] ?? []));
                $rep['searches'] += (int) ($m['searches'] ?? 0);
                $rep['days'][$day] = ($rep['days'][$day] ?? 0) + $usd;
                $rm = $rep['models'][$key] ?? ['provider' => $provider, 'model' => $model, 'calls' => 0, 'in' => 0, 'out' => 0, 'images' => 0, 'usd' => 0.0];
                $rm['calls'] += (int) $m['calls'];
                $rm['in'] += (int) $m['in'];
                $rm['out'] += (int) $m['out'];
                $rm['images'] += (int) array_sum((array) ($m['images'] ?? []));
                $rm['usd'] += $usd;
                $rep['models'][$key] = $rm;
                // Répartition par fonctionnalité au prorata du prix des jetons de chaque fonctionnalité pour ce modèle ;
                // les recherches web sont attribuées exactement à la fonctionnalité qui les a faites (quand c'est connu).
                $pr = $priced ? self::price($provider, $model, $day) : null;
                $weight = static fn(array $x): float => $pr ? (int) $x['in'] * $pr['in'] + (int) $x['out'] * $pr['out'] : (int) $x['in'] + (int) $x['out'];
                $tokens = max(1e-9, $weight($m));
                $mSearch = (int) ($m['searches'] ?? 0);
                $exact = $priced && $mSearch > 0 && array_sum(array_map(static fn($fs) => (int) ($fs['searches'] ?? 0), (array) ($m['features'] ?? []))) === $mSearch;
                $sp = $exact ? self::searchPrice($provider, $model) : 0.0;
                $base = $usd - $mSearch * $sp;
                foreach ((array) ($m['features'] ?? []) as $f => $fs) {
                    $rf = $rep['features'][$f] ?? ['calls' => 0, 'in' => 0, 'out' => 0, 'images' => 0, 'searches' => 0, 'usd' => 0.0];
                    $rf['calls'] += (int) $fs['calls'];
                    $rf['in'] += (int) $fs['in'];
                    $rf['out'] += (int) $fs['out'];
                    $rf['images'] += (int) ($fs['images'] ?? 0);
                    $rf['searches'] += (int) ($fs['searches'] ?? 0);
                    $share = count((array) $m['features']) === 1 ? 1 : $weight($fs) / $tokens;
                    $rf['usd'] += $base * $share + (int) ($fs['searches'] ?? 0) * $sp;
                    $rep['features'][$f] = $rf;
                }
            }
        }
        uasort($rep['models'], static fn($a, $b) => $b['usd'] <=> $a['usd']);
        uasort($rep['features'], static fn($a, $b) => $b['usd'] <=> $a['usd']);
        return $rep;
    }

    /** Coût du mois en cours (USD). */
    public static function monthUsd(?string $month = null): float
    {
        $month ??= date('Y-m');
        return self::report($month . '-01', date('Y-m-t', strtotime($month . '-01')))['usd'];
    }

    /** Budget mensuel dépassé : un e-mail à Fred, une fois par mois. */
    private static function checkBudget(): void
    {
        $s = self::settings();
        $budget = (float) $s['budget'];
        if ($budget <= 0 || ($s['alerted'] ?? '') === date('Y-m')) {
            return;
        }
        $eur = self::monthUsd() * self::rate();
        if ($eur < $budget) {
            return;
        }
        $s['alerted'] = date('Y-m');
        self::saveSettings($s);
        $html = '<p>Les dépenses IA du site atteignent <b>' . e(number_format($eur, 2, ',', ' ')) . ' €</b> ce mois-ci, pour un budget de ' . e(number_format($budget, 2, ',', ' ')) . ' €.</p>'
            . '<p>Tu peux baisser le plafond d’appels par jour ou choisir un modèle moins cher dans le back-office.</p>';
        Mailer::send(Mailer::notifyAddress(), '⚠ Budget IA dépassé', Mailer::layout('Budget IA dépassé', $html, 'Voir les coûts IA', abs_url('/admin/ia/couts')));
    }

    /** Jours sans détail par modèle (avant ce suivi) : reconstitués depuis logs/ai.log, une seule fois. */
    private static function backfill(array $usage): array
    {
        $missing = array_filter($usage, static fn($d) => !isset($d['models']) && ($d['calls'] ?? 0) > 0);
        $file = Store::path('logs/ai.log');
        if (!$missing || !is_file($file)) {
            return $usage;
        }
        $fh = fopen($file, 'r');
        while ($fh && ($line = fgets($fh)) !== false) {
            $l = json_decode($line, true);
            $day = is_array($l) ? substr((string) ($l['at'] ?? ''), 0, 10) : '';
            if (!isset($missing[$day]) || empty($l['provider']) || empty($l['model'])) {
                continue;
            }
            $key = $l['provider'] . ':' . $l['model'];
            $f = (string) ($l['feature'] ?? 'autre');
            $m = $usage[$day]['models'][$key] ?? ['calls' => 0, 'in' => 0, 'out' => 0, 'images' => [], 'minutes' => 0, 'features' => []];
            $m['calls']++;
            $m['in'] += (int) ($l['in'] ?? 0);
            $m['out'] += (int) ($l['out'] ?? 0);
            $fs = $m['features'][$f] ?? ['calls' => 0, 'in' => 0, 'out' => 0, 'images' => 0];
            $fs['calls']++;
            $fs['in'] += (int) ($l['in'] ?? 0);
            $fs['out'] += (int) ($l['out'] ?? 0);
            $m['features'][$f] = $fs;
            $usage[$day]['models'][$key] = $m;
        }
        if ($fh) {
            fclose($fh);
        }
        foreach ($missing as $day => $d) {
            $usage[$day]['models'] ??= [];
        }
        Store::update('data/ai-usage.json', static function (array &$u) use ($usage, $missing): void {
            foreach ($missing as $day => $d) {
                if (isset($u[$day]) && !isset($u[$day]['models'])) {
                    $u[$day]['models'] = $usage[$day]['models'];
                }
            }
        });
        return $usage;
    }
}
