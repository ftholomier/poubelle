<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\JsonStore;
use App\Core\Settings;

/**
 * Coût de l'intelligence artificielle (API Gemini), calculé en temps réel.
 *
 * Chaque réponse de Gemini indique les jetons consommés (usageMetadata) : jetons envoyés,
 * dont ceux servis par le cache de Google, jetons produits et jetons de réflexion (facturés
 * comme des jetons produits). Multipliés par le tarif du modèle, ils donnent le coût de
 * l'appel, enregistré avec son usage (assistant, traductions, correcteur, index), la
 * personne qui l'a demandé et la fiche concernée : storage/ia/AAAA-MM.jsonl (détail) et
 * storage/ia/totaux.json (cumuls par mois, jour, usage et modèle).
 *
 * La facture Google fait foi : ce suivi sert à la surveiller au jour le jour, à plafonner
 * la dépense et à se faire rembourser par l'association (relevé mensuel PDF et CSV).
 */
final class AiCosts
{
    public const USES = [
        'assistant' => 'Assistant du site',
        'traduction' => 'Traductions',
        'correcteur' => 'Correcteur d’orthographe',
        'index' => 'Index de l’assistant',
        'audio' => 'Fiches audio',
        'recherche' => 'Recherche sur le web (fiches)',
        'moments' => '100 moments (idées et premiers jets)',
        'boutique' => 'Boutique (banque de textes)',
        'boutique-poster' => 'Boutique (posters souvenirs)',
        'radio' => 'Rétro-Direct commenté (radio)',
        'import' => 'Reprise des années 1928-1969 (FCSM Story)',
        'trouvailles' => 'Trouvailles (archives et presse)',
        'autre' => 'Autre',
    ];

    /**
     * Recherche Google pendant une réponse (« ancrage », recherche sur le web des fiches), dollars,
     * octobre 2026 : Gemini 3 et suivants facturent chaque recherche lancée, 5 000 gratuites par
     * mois ; les modèles plus anciens, chaque demande qui cherche, 1 500 gratuites par jour.
     */
    public const SEARCH = ['query' => 0.014, 'free_month' => 5000, 'prompt' => 0.035, 'free_day' => 1500];

    /**
     * Tarifs publics de Google (dollars US par million de jetons, octobre 2026) :
     * [début de l'identifiant du modèle, entrée, sortie (réponse et réflexion), entrée lue
     * dans le cache, en vigueur à partir du]. Modifiables dans l'écran Coûts IA.
     */
    public const DEFAULT_PRICES = [
        // Voix (synthèse vocale) : la sortie est de l'audio, 25 jetons par seconde.
        ['gemini-3.8-flash-tts', 0.50, 9.00, 0.50, ''],
        ['gemini-3.8-flash-tts', 1.00, 18.00, 1.00, '2027-01-01'],
        ['gemini-3.8-flash-preview-tts', 0.50, 9.00, 0.50, ''],
        ['gemini-3.8-flash-preview-tts', 1.00, 18.00, 1.00, '2027-01-01'],
        ['gemini-3.1-flash-tts', 1.00, 20.00, 1.00, ''],
        ['gemini-3.1-flash-preview-tts', 1.00, 20.00, 1.00, ''],
        ['gemini-2.5-flash-preview-tts', 0.50, 10.00, 0.50, ''],
        ['gemini-2.5-pro-preview-tts', 1.00, 20.00, 1.00, ''],
        ['gemini-3.8-flash', 0.75, 3.75, 0.075, ''],
        ['gemini-3.8-flash', 1.50, 7.50, 0.15, '2027-01-01'],
        ['gemini-3.7-flash', 0.75, 3.75, 0.075, ''],
        ['gemini-3.7-flash', 1.50, 7.50, 0.15, '2027-01-01'],
        ['gemini-3.6-flash', 0.75, 3.75, 0.075, ''],
        ['gemini-3.6-flash', 1.50, 7.50, 0.15, '2027-01-01'],
        ['gemini-3.5-flash-lite', 0.30, 2.50, 0.03, ''],
        ['gemini-3.5-flash', 1.50, 9.00, 0.15, ''],
        ['gemini-3.1-flash-lite', 0.25, 1.50, 0.025, ''],
        ['gemini-3.1-pro', 2.00, 12.00, 0.20, ''],
        ['gemini-3-flash', 0.50, 3.00, 0.05, ''],
        ['gemini-3-pro', 2.00, 12.00, 0.20, ''],
        ['gemini-2.5-pro', 1.25, 10.00, 0.125, ''],
        ['gemini-2.5-flash-lite', 0.10, 0.40, 0.01, ''],
        ['gemini-2.5-flash', 0.30, 2.50, 0.03, ''],
        ['gemini-2.0-flash-lite', 0.075, 0.30, 0.075, ''],
        ['gemini-2.0-flash', 0.10, 0.40, 0.025, ''],
        ['gemini-embedding', 0.15, 0.0, 0.15, ''],
        ['text-embedding', 0.0, 0.0, 0.0, ''],
    ];
    /** Modèle inconnu du barème : tarif prudent, signalé dans l'écran Coûts IA. */
    private const FALLBACK = ['prefix' => '', 'in' => 0.50, 'out' => 3.00, 'cached' => 0.05, 'from' => ''];
    /** Modèle de voix inconnu du barème : tarif de voix prudent (jamais celui d'un modèle de texte). */
    private const FALLBACK_TTS = ['prefix' => '', 'in' => 1.00, 'out' => 20.00, 'cached' => 1.00, 'from' => ''];

    public static string $dir = STORAGE_PATH . '/ia';
    /** Fiche ou écran concerné par les appels en cours (« fiche:123 »), posé par l'appelant. */
    public static string $ref = '';
    private static array $request = ['calls' => 0, 'usd' => 0.0, 'tokens' => 0];
    private static ?array $prices = null;

    // ------------------------------------------------------------------ tarifs

    /** @return list<array{prefix:string,in:float,out:float,cached:float,from:string}> */
    public static function prices(): array
    {
        if (self::$prices === null) {
            $custom = JsonStore::read(self::$dir . '/tarifs.json', null);
            self::$prices = is_array($custom) && $custom ? array_values($custom) : self::defaultPrices();
        }
        return self::$prices;
    }

    public static function defaultPrices(): array
    {
        return array_map(fn ($r) => ['prefix' => $r[0], 'in' => (float) $r[1], 'out' => (float) $r[2], 'cached' => (float) $r[3], 'from' => $r[4]], self::DEFAULT_PRICES);
    }

    public static function customPrices(): bool
    {
        return is_file(self::$dir . '/tarifs.json');
    }

    /** Enregistre le barème (lignes incomplètes ignorées). */
    public static function savePrices(array $rows): int
    {
        $out = [];
        foreach ($rows as $r) {
            $prefix = strtolower(trim((string) ($r['prefix'] ?? '')));
            if (!preg_match('/^[a-z0-9][a-z0-9._-]{1,60}$/', $prefix)) {
                continue;
            }
            $num = fn ($v) => max(0.0, round((float) str_replace(',', '.', (string) $v), 6));
            $from = trim((string) ($r['from'] ?? ''));
            $out[] = ['prefix' => $prefix, 'in' => $num($r['in'] ?? 0), 'out' => $num($r['out'] ?? 0), 'cached' => $num($r['cached'] ?? 0), 'from' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : ''];
        }
        JsonStore::write(self::$dir . '/tarifs.json', $out);
        self::$prices = null;
        return count($out);
    }

    public static function resetPrices(): void
    {
        JsonStore::delete(self::$dir . '/tarifs.json');
        self::$prices = null;
    }

    /**
     * Tarif applicable à un modèle à une date : le début d'identifiant le plus long, et parmi
     * ses lignes la plus récente déjà en vigueur.
     * @return array{prefix:string,in:float,out:float,cached:float,from:string,known:bool}
     */
    public static function price(string $model, ?string $date = null): array
    {
        $date ??= date('Y-m-d');
        $m = strtolower((string) preg_replace('#^models/#', '', $model));
        $best = null;
        foreach (self::prices() as $r) {
            $p = strtolower((string) $r['prefix']);
            if ($p === '' || !str_starts_with($m, $p) || ($r['from'] !== '' && $r['from'] > $date)) {
                continue;
            }
            if (!$best || strlen($p) > strlen($best['prefix']) || (strlen($p) === strlen($best['prefix']) && $r['from'] > $best['from'])) {
                $best = $r;
            }
        }
        if (str_contains($m, 'tts') && $best && !str_contains(strtolower((string) $best['prefix']), 'tts')) {
            $best = null; // « gemini-x-flash » ne doit pas tarifer « gemini-x-flash-…-tts »
        }
        return ($best ?? (str_contains($m, 'tts') ? self::FALLBACK_TTS : self::FALLBACK)) + ['known' => $best !== null];
    }

    /**
     * Coût en dollars : jetons envoyés (hors cache), lus dans le cache, produits et de réflexion.
     * Traitement groupé (API Batch de Google, $u['batch']) : moitié prix.
     */
    public static function cost(array $u, array $price): float
    {
        $u += ['in' => 0, 'cached' => 0, 'out' => 0, 'think' => 0, 'batch' => false];
        $fresh = max(0, $u['in'] - $u['cached']);
        $usd = ($fresh * $price['in'] + $u['cached'] * $price['cached'] + ($u['out'] + $u['think']) * $price['out']) / 1e6;
        return $u['batch'] ? $usd / 2 : $usd;
    }

    // ------------------------------------------------------------------ enregistrement

    /** Jetons d'une réponse de Gemini (champ usageMetadata). */
    public static function usage(array $response): array
    {
        $m = $response['usageMetadata'] ?? [];
        return [
            'in' => (int) ($m['promptTokenCount'] ?? 0) + (int) ($m['toolUsePromptTokenCount'] ?? 0),
            'cached' => (int) ($m['cachedContentTokenCount'] ?? 0),
            'out' => (int) ($m['candidatesTokenCount'] ?? 0),
            'think' => (int) ($m['thoughtsTokenCount'] ?? 0),
            'est' => false,
            // Recherches Google lancées par le modèle (facturées à part, voir SEARCH).
            'search' => count((array) ($response['candidates'][0]['groundingMetadata']['webSearchQueries'] ?? [])),
        ];
    }

    /**
     * Enregistre un appel facturé. Ne fait jamais échouer l'appel à Gemini : une erreur
     * d'écriture est seulement journalisée.
     */
    public static function record(string $for, string $model, array $u, ?string $ref = null): void
    {
        try {
            $for = isset(self::USES[$for]) ? $for : 'autre';
            $u += ['in' => 0, 'cached' => 0, 'out' => 0, 'think' => 0, 'est' => false];
            if ($u['in'] + $u['out'] + $u['think'] <= 0) {
                return;
            }
            $price = self::price($model);
            $usd = self::cost($u, $price);
            $search = max(0, (int) ($u['search'] ?? 0));
            if ($search > 0) {
                $usd += self::searchCost($model, $search, date('Y-m'), date('d'));
            }
            $free = (bool) Settings::get('couts.free_tier', false);
            $billed = $free ? 0.0 : $usd;
            $line = [
                'at' => date('c'), 'f' => $for, 'm' => $model,
                'in' => $u['in'], 'c' => $u['cached'], 'out' => $u['out'], 'th' => $u['think'],
                'usd' => round($billed, 7), 'u' => self::who(), 'r' => mb_substr($ref ?? self::$ref, 0, 80),
            ];
            if ($free) {
                $line['g'] = round($usd, 7); // coût évité grâce au niveau gratuit
            }
            if (!empty($u['est'])) {
                $line['e'] = 1; // jetons estimés (Google ne les indique pas pour ce service)
            }
            if (!empty($u['batch'])) {
                $line['b'] = 1; // traitement groupé : moitié prix
            }
            if (!$price['known']) {
                $line['x'] = 1; // modèle absent du barème : tarif par défaut
            }
            if ($search > 0) {
                $line['q'] = $search; // recherches Google lancées
            }
            JsonStore::append(self::$dir . '/' . substr($line['at'], 0, 7) . '.jsonl', $line);
            JsonStore::update(self::$dir . '/totaux.json', function ($t) use ($line) {
                $t = is_array($t) ? $t : [];
                $ym = substr($line['at'], 0, 7);
                $add = function (array &$a) use ($line): void {
                    $a['calls'] = ($a['calls'] ?? 0) + 1;
                    $a['usd'] = round(($a['usd'] ?? 0) + $line['usd'], 7);
                    $a['in'] = ($a['in'] ?? 0) + $line['in'];
                    $a['out'] = ($a['out'] ?? 0) + $line['out'];
                    $a['th'] = ($a['th'] ?? 0) + $line['th'];
                };
                $mo = $t[$ym] ?? [];
                $add($mo);
                $day = substr($line['at'], 8, 2);
                $d = $mo['days'][$day] ?? [];
                $add($d);
                // Recherches Google : par mois (Gemini 3) et demandes qui cherchent, par jour (modèles plus anciens).
                if (!empty($line['q'])) {
                    $mo['search'] = ($mo['search'] ?? 0) + $line['q'];
                    $d['sp'] = ($d['sp'] ?? 0) + 1;
                }
                $mo['days'][$day] = $d;
                $f = $mo['uses'][$line['f']] ?? [];
                $add($f);
                $mo['uses'][$line['f']] = $f;
                $m = $mo['models'][$line['m']] ?? [];
                $add($m);
                $mo['models'][$line['m']] = $m;
                $t[$ym] = $mo;
                ksort($t);
                return $t;
            }, []);
            self::$request['calls']++;
            self::$request['usd'] += $billed;
            self::$request['tokens'] += $u['in'] + $u['out'] + $u['think'];
        } catch (\Throwable $e) {
            error_log('[coûts IA] ' . $e->getMessage());
        }
    }

    /**
     * Coût des recherches Google d'un appel, compte tenu de la part gratuite déjà consommée :
     * recherches du mois (Gemini 3 et suivants) ou demandes du jour qui ont cherché (avant).
     */
    public static function searchCost(string $model, int $queries, string $ym, string $day): float
    {
        if ($queries <= 0) {
            return 0.0;
        }
        $mo = self::totals()[$ym] ?? [];
        if (preg_match('/gemini-(?:[3-9]|\d{2,})/', $model)) {
            $used = (int) ($mo['search'] ?? 0);
            $free = self::SEARCH['free_month'];
            return (max(0, $used + $queries - $free) - max(0, $used - $free)) * self::SEARCH['query'];
        }
        return (int) ($mo['days'][$day]['sp'] ?? 0) >= self::SEARCH['free_day'] ? self::SEARCH['prompt'] : 0.0;
    }

    /** Qui a déclenché l'appel : membre connecté, tâche automatique ou visiteur (assistant). */
    private static function who(): string
    {
        if (PHP_SAPI === 'cli') {
            return 'Tâche automatique';
        }
        $u = Auth::user();
        return $u ? (string) $u['name'] : 'Visiteur du site';
    }

    /** Coût des appels faits pendant la requête en cours (affiché après une action). */
    public static function request(): array
    {
        $usd = self::$request['usd'];
        return ['calls' => self::$request['calls'], 'tokens' => self::$request['tokens'], 'usd' => round($usd, 6), 'eur' => round(self::eur($usd), 6), 'label' => self::$request['calls'] ? self::fmt(self::eur($usd)) : ''];
    }

    // ------------------------------------------------------------------ lecture

    /** Cumuls par mois (« AAAA-MM » => totaux, jours, usages, modèles). */
    public static function totals(): array
    {
        return JsonStore::read(self::$dir . '/totaux.json', []) ?: [];
    }

    public static function month(string $ym): array
    {
        return (self::totals()[$ym] ?? []) + ['calls' => 0, 'usd' => 0.0, 'in' => 0, 'out' => 0, 'th' => 0, 'days' => [], 'uses' => [], 'models' => []];
    }

    /** Appels d'un mois (détail), les plus récents en dernier ; $limit : seulement les derniers. */
    public static function lines(string $ym, int $limit = 0): array
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
            return [];
        }
        $file = self::$dir . "/$ym.jsonl";
        if (!is_file($file)) {
            return [];
        }
        $raw = $limit > 0 ? self::tail($file, $limit) : (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []);
        $out = [];
        foreach ($raw as $l) {
            $d = json_decode((string) $l, true);
            if (is_array($d) && isset($d['at'])) {
                $out[] = $d;
            }
        }
        return $out;
    }

    /** Dernières lignes d'un fichier sans le lire en entier. */
    private static function tail(string $file, int $n): array
    {
        $fp = fopen($file, 'rb');
        if (!$fp) {
            return [];
        }
        $size = (int) fstat($fp)['size'];
        $chunk = min($size, max(8192, $n * 400));
        fseek($fp, $size - $chunk);
        $data = (string) fread($fp, $chunk);
        fclose($fp);
        $lines = array_values(array_filter(explode("\n", $data), fn ($l) => trim($l) !== ''));
        if ($chunk < $size) {
            array_shift($lines); // première ligne peut-être coupée
        }
        return array_slice($lines, -$n);
    }

    // ------------------------------------------------------------------ euros, budget

    public static function rate(): float
    {
        $r = (float) Settings::get('couts.eur_rate', 0.86);
        return $r > 0 ? $r : 0.86;
    }

    public static function eur(float $usd): float
    {
        return $usd * self::rate();
    }

    /** Montant lisible : « 3,42 € », « 0,123 € », « 0,05 centime » (espace insécable avant l'unité). */
    public static function fmt(float $eur): string
    {
        if ($eur <= 0) {
            return "0\u{a0}€";
        }
        if ($eur < 0.01) {
            $c = $eur * 100;
            return number_format($c, $c < 0.1 ? 3 : 2, ',', "\u{a0}") . "\u{a0}centime";
        }
        return number_format($eur, $eur < 1 ? 3 : 2, ',', "\u{a0}") . "\u{a0}€";
    }

    public static function fmtUsd(float $usd): string
    {
        return '$' . number_format($usd, $usd < 1 ? 4 : 2, '.', ' ');
    }

    /** Budget du mois en cours : ['budget' => €, 'spent' => €, 'pct' => %, 'over' => bool] (budget 0 : aucun). */
    public static function budget(): array
    {
        $budget = max(0.0, (float) Settings::get('couts.monthly_budget', 0));
        $spent = self::eur((float) self::month(date('Y-m'))['usd']);
        return ['budget' => $budget, 'spent' => $spent, 'pct' => $budget > 0 ? min(999, (int) round($spent / $budget * 100)) : 0, 'over' => $budget > 0 && $spent >= $budget];
    }

    /** Usage suspendu parce que le budget du mois est atteint ? */
    public static function paused(string $for): bool
    {
        if (!self::budget()['over']) {
            return false;
        }
        return $for === 'assistant' ? (bool) Settings::get('couts.pause_assistant', false) : (bool) Settings::get('couts.pause_tasks', true);
    }

    // ------------------------------------------------------------------ remboursements

    public static function reimbursements(): array
    {
        return JsonStore::read(self::$dir . '/remboursements.json', []) ?: [];
    }

    public static function markReimbursed(string $ym, float $eur, string $note, ?array $user): void
    {
        JsonStore::update(self::$dir . '/remboursements.json', function ($r) use ($ym, $eur, $note, $user) {
            $r = is_array($r) ? $r : [];
            $r[$ym] = ['at' => date('c'), 'by' => $user['name'] ?? '', 'eur' => round($eur, 2), 'note' => mb_substr($note, 0, 200)];
            ksort($r);
            return $r;
        }, []);
    }

    public static function unmarkReimbursed(string $ym): void
    {
        JsonStore::update(self::$dir . '/remboursements.json', function ($r) use ($ym) {
            $r = is_array($r) ? $r : [];
            unset($r[$ym]);
            return $r;
        }, []);
    }

    /** Montant à rembourser (mois non remboursés, mois en cours compris) et mois concernés. */
    public static function toReimburse(bool $withCurrent = true): array
    {
        $paid = self::reimbursements();
        $eur = 0.0;
        $months = [];
        foreach (self::totals() as $ym => $t) {
            if (isset($paid[$ym]) || (!$withCurrent && $ym === date('Y-m')) || ($t['usd'] ?? 0) <= 0) {
                continue;
            }
            $eur += self::eur((float) $t['usd']);
            $months[] = $ym;
        }
        return ['eur' => $eur, 'months' => $months];
    }
}
