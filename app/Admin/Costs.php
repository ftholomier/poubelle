<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Front\PdfExport;
use App\Pdf\Layout;
use App\Services\AiCosts;
use App\Services\Gemini;

/**
 * Système › Coûts IA : dépense Gemini en temps réel (aujourd'hui, mois, à rembourser),
 * détail des derniers appels, barème des modèles, remboursements par l'association,
 * relevé mensuel PDF et détail CSV.
 */
final class Costs extends Base
{
    private const MONTHS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

    public static function index(Request $req): Response
    {
        $model = Gemini::ready() ? Gemini::model() : null;
        return self::html('admin/system/couts', self::live() + [
            'prices' => AiCosts::prices(),
            'custom' => AiCosts::customPrices(),
            'months' => self::months(),
            'model' => $model,
            'modelPrice' => $model ? AiCosts::price($model) : null,
            'admin' => Auth::can('settings'),
            'payer' => (string) Settings::get('couts.payer', ''),
            'rate' => AiCosts::rate(),
            'free' => (bool) Settings::get('couts.free_tier', false),
        ], ['title' => 'Coûts IA', 'crumb' => 'Système', 'nav' => 'couts', 'scripts' => ['admin/couts.js']]);
    }

    /** GET /admin/api/couts : les chiffres « en direct » (rafraîchis toutes les 10 secondes). */
    public static function api(Request $req): Response
    {
        return self::json(self::live());
    }

    /** Chiffres du moment : aujourd'hui, mois, total, à rembourser, budget, usages, derniers appels. */
    public static function live(): array
    {
        $ym = date('Y-m');
        $m = AiCosts::month($ym);
        $today = $m['days'][date('d')] ?? ['usd' => 0, 'calls' => 0];
        $all = ['usd' => 0.0, 'calls' => 0];
        foreach (AiCosts::totals() as $t) {
            $all['usd'] += (float) ($t['usd'] ?? 0);
            $all['calls'] += (int) ($t['calls'] ?? 0);
        }
        $due = AiCosts::toReimburse();
        $money = fn (float $usd) => ['eur' => AiCosts::fmt(AiCosts::eur($usd)), 'usd' => AiCosts::fmtUsd($usd)];
        $uses = [];
        foreach (AiCosts::USES as $k => $label) {
            $u = $m['uses'][$k] ?? null;
            if ($u) {
                $uses[] = ['label' => $label, 'calls' => self::n($u['calls']), 'in' => self::n($u['in']), 'out' => self::n($u['out'] + $u['th']), 'avg' => AiCosts::fmt(AiCosts::eur((float) $u['usd'] / max(1, (int) $u['calls'])))] + $money((float) $u['usd']);
            }
        }
        $recent = [];
        foreach (array_reverse(AiCosts::lines($ym, 15)) as $l) {
            $ref = (string) ($l['r'] ?? '');
            $t = strtotime($l['at']);
            $recent[] = [
                'at' => date('Y-m-d', $t) === date('Y-m-d') ? date('H:i:s', $t) : date('d/m H:i', $t),
                'use' => AiCosts::USES[$l['f']] ?? $l['f'],
                'model' => $l['m'],
                'who' => $l['u'] ?? '',
                'ref' => self::refLabel($ref),
                'refUrl' => preg_match('/^fiche:(\d+)$/', $ref, $mm) ? '/admin/fiche/' . $mm[1] : null,
                'tokens' => self::n($l['in']) . ' → ' . self::n($l['out'] + $l['th']),
                'eur' => AiCosts::fmt(AiCosts::eur((float) $l['usd'])),
                'flag' => !empty($l['x']) ? 'tarif par défaut' : (!empty($l['e']) ? 'jetons estimés' : (isset($l['g']) ? 'gratuit' : '')),
            ];
        }
        $b = AiCosts::budget();
        $dueMonths = array_map(fn ($ym2) => self::monthLabel($ym2) . ($ym2 === $ym ? ' (en cours)' : ''), $due['months']);
        return [
            'today' => $money((float) $today['usd']) + ['calls' => self::calls((int) $today['calls'])],
            'month' => $money((float) $m['usd']) + ['calls' => self::calls((int) $m['calls']), 'count' => (int) $m['calls'], 'label' => self::monthLabel($ym), 'tokens' => self::n($m['in'] + $m['out'] + $m['th'])],
            'total' => $money($all['usd']) + ['calls' => self::calls($all['calls'])],
            'due' => ['eur' => AiCosts::fmt($due['eur']), 'detail' => $dueMonths ? implode(', ', $dueMonths) : 'rien à rembourser', 'has' => $due['eur'] > 0],
            'budget' => ['set' => $b['budget'] > 0, 'pct' => $b['pct'], 'over' => $b['over'], 'label' => $b['budget'] > 0 ? AiCosts::fmt($b['spent']) . ' sur ' . AiCosts::fmt($b['budget']) : 'aucun plafond fixé'],
            'uses' => $uses,
            'recent' => $recent,
            'at' => date('H:i:s'),
        ];
    }

    /** Mois enregistrés, du plus récent au plus ancien, avec leur remboursement. */
    private static function months(): array
    {
        $paid = AiCosts::reimbursements();
        $out = [];
        foreach (array_reverse(AiCosts::totals(), true) as $ym => $t) {
            $out[] = [
                'ym' => $ym, 'label' => self::monthLabel($ym), 'calls' => self::n((int) $t['calls']),
                'tokens' => self::n(($t['in'] ?? 0) + ($t['out'] ?? 0) + ($t['th'] ?? 0)),
                'usd' => AiCosts::fmtUsd((float) $t['usd']), 'eur' => AiCosts::fmt(AiCosts::eur((float) $t['usd'])),
                'eurRaw' => round(AiCosts::eur((float) $t['usd']), 2), 'current' => $ym === date('Y-m'), 'paid' => $paid[$ym] ?? null,
            ];
        }
        return $out;
    }

    // ------------------------------------------------------------------ actions (administrateurs)

    /** POST /admin/couts-ia/tarifs (JSON) : barème des modèles. */
    public static function savePrices(Request $req): Response
    {
        if (!Auth::can('settings')) {
            return self::json(['error' => 'Réservé aux administrateurs.'], 403);
        }
        $n = AiCosts::savePrices((array) ($req->json()['prices'] ?? []));
        return self::json(['ok' => true, 'message' => "Barème enregistré ($n modèles). Les appels déjà faits gardent leur coût.", 'reload' => true]);
    }

    public static function resetPrices(Request $req): Response
    {
        if ($deny = self::denyUnlessAdmin()) {
            return $deny;
        }
        AiCosts::resetPrices();
        return self::back('/admin/couts-ia#tarifs', 'Tarifs publics de Google rétablis.');
    }

    /** POST /admin/couts-ia/rembourse : marque un mois remboursé par l'association (ou annule). */
    public static function reimburse(Request $req): Response
    {
        if ($deny = self::denyUnlessAdmin()) {
            return $deny;
        }
        $ym = $req->str('mois');
        if (!preg_match('/^\d{4}-\d{2}$/', $ym) || !isset(AiCosts::totals()[$ym])) {
            return self::back('/admin/couts-ia', null, 'Mois inconnu.');
        }
        if ($req->str('annuler') !== '') {
            AiCosts::unmarkReimbursed($ym);
            return self::back('/admin/couts-ia#mois', 'Remboursement de ' . self::monthLabel($ym) . ' annulé.');
        }
        if ($ym === date('Y-m')) {
            return self::back('/admin/couts-ia#mois', null, 'Le mois en cours n’est pas terminé : notez son remboursement à partir du 1er du mois prochain.');
        }
        $eur = (float) str_replace([',', ' ', "\u{a0}", '€'], ['.', '', '', ''], $req->str('montant'));
        if ($eur <= 0) {
            $eur = round(AiCosts::eur((float) AiCosts::totals()[$ym]['usd']), 2);
        }
        AiCosts::markReimbursed($ym, $eur, $req->str('note'), self::actor());
        return self::back('/admin/couts-ia#mois', self::monthLabel($ym) . ' : remboursement de ' . number_format($eur, 2, ',', ' ') . ' € noté.');
    }

    // ------------------------------------------------------------------ relevé PDF et détail CSV

    /** GET /admin/couts-ia/releve/{AAAA-MM}.pdf */
    public static function statement(Request $req, string $ym): ?Response
    {
        if (!preg_match('/^(\d{4}-\d{2})\.pdf$/', $ym, $m) || !isset(AiCosts::totals()[$m[1]])) {
            return null;
        }
        $bytes = self::statementPdf($m[1]);
        return new Response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="releve-frais-ia-' . $m[1] . '.pdf"',
            'Content-Length' => (string) strlen($bytes),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public static function statementPdf(string $ym): string
    {
        $m = AiCosts::month($ym);
        $period = self::monthLabel($ym);
        $rate = AiCosts::rate();
        $eur = fn (float $usd) => number_format(AiCosts::eur($usd), 2, ',', ' ') . ' €';
        $usd = fn (float $v) => number_format($v, 4, ',', ' ') . ' $';
        $paid = AiCosts::reimbursements()[$ym] ?? null;
        $l = PdfExport::layout('/admin/couts-ia', 'Relevé des frais d’IA · ' . $period, 'Relevé des frais d’IA · ' . $period, 'Frais d’intelligence artificielle (API Gemini)');
        $l->url = '';
        $l->exported = 'Relevé édité le ' . date_fr(date('Y-m-d')) . ' à ' . date('H:i');
        $l->newPage();
        $l->masthead('Relevé de frais');
        PdfExport::titleBlock($l, 'Avance de frais pour l’association', 'Frais d’IA · ' . $period, 'Consommation de l’API Gemini (Google) par le site et le back-office du musée');
        $last = (int) date('t', strtotime($ym . '-01'));
        $l->facts([
            ['Association', (string) Settings::get('donations.org_name', 'Sochaux Rétro') ?: 'Sochaux Rétro'],
            ['Frais avancés par', (string) Settings::get('couts.payer', '') ?: '…………………………'],
            ['Période', 'du 1er au ' . $last . ' ' . $period],
            ['Taux de change', '1 $ = ' . number_format($rate, 4, ',', ' ') . ' €'],
            ['Statut', $paid ? 'Remboursé le ' . date('d/m/Y', strtotime($paid['at'])) . ' (' . number_format((float) $paid['eur'], 2, ',', ' ') . ' €' . ($paid['note'] ? ', ' . $paid['note'] : '') . ')' : ($ym === date('Y-m') ? 'Mois en cours (montant provisoire)' : 'À rembourser')],
            ['Appels à Gemini', self::n((int) $m['calls'])],
        ], 3);
        PdfExport::bigNumbers($l, [
            [$eur((float) $m['usd']), $paid ? 'remboursé' : 'à rembourser', true],
            [number_format((float) $m['usd'], 2, ',', ' ') . ' $', 'en dollars'],
            [self::n((int) $m['calls']), 'appels'],
            [number_format(($m['in'] + $m['out'] + $m['th']) / 1e6, 2, ',', ' ') . ' M', 'jetons'],
        ]);
        $num = fn (string $t) => ['t' => $t];
        $l->h2('Par usage');
        $rows = [];
        foreach (AiCosts::USES as $k => $label) {
            $u = $m['uses'][$k] ?? null;
            if ($u) {
                $rows[] = [['t' => $label, 'b' => true], self::n($u['calls']), self::n($u['in']), self::n($u['out'] + $u['th']), self::n($u['th']), $usd((float) $u['usd']), $eur((float) $u['usd'])];
            }
        }
        $rows[] = [['t' => 'Total', 'b' => true], ['t' => self::n($m['calls']), 'b' => true], ['t' => self::n($m['in']), 'b' => true], ['t' => self::n($m['out'] + $m['th']), 'b' => true], ['t' => self::n($m['th']), 'b' => true], ['t' => $usd((float) $m['usd']), 'b' => true], ['t' => $eur((float) $m['usd']), 'b' => true], '_bg' => 'butter'];
        $l->table(['Usage', 'Appels', 'Jetons envoyés', 'Jetons produits', 'dont réflexion', 'Coût ($)', 'Coût (€)'], $rows, ['align' => ['l', 'r', 'r', 'r', 'r', 'r', 'r'], 'size' => 8.4]);
        $l->h2('Par modèle');
        $rows = [];
        foreach ($m['models'] as $model => $u) {
            $p = AiCosts::price((string) $model, $ym . '-15');
            $rows[] = [['t' => (string) $model, 'b' => true], number_format($p['in'], 3, ',', ' ') . ' · ' . number_format($p['out'], 2, ',', ' ') . ($p['known'] ? '' : ' (défaut)'), self::n($u['calls']), $usd((float) $u['usd']), $eur((float) $u['usd'])];
        }
        $l->table(['Modèle', 'Tarif $ / million (entrée · sortie)', 'Appels', 'Coût ($)', 'Coût (€)'], $rows, ['align' => ['l', 'l', 'r', 'r', 'r'], 'size' => 8.4]);
        // Signatures, sous les totaux : la première page suffit pour le remboursement.
        $l->ensure(100);
        $l->y += 8;
        $w = ($l->cw() - 14) / 2;
        foreach (['Frais avancés par (nom, date, signature)', 'Pour l’association : bon pour remboursement'] as $i => $label) {
            $x = $l->ml + $i * ($w + 14);
            $l->rect($x, $l->y, $w, 80, 'paper', 'navy', 1);
            $l->text($x + 9, $l->y + 16, mb_strtoupper($label), 'display-b', 7.6, 'muted', 0.6);
        }
        $l->y += 96;
        $l->h2('Annexe : détail par jour');
        $rows = [];
        ksort($m['days']);
        foreach ($m['days'] as $d => $u) {
            $rows[] = [date_fr($ym . '-' . $d, true), self::n($u['calls']), self::n($u['in'] + $u['out'] + $u['th']), $usd((float) $u['usd']), $eur((float) $u['usd'])];
        }
        $l->table(['Jour', 'Appels', 'Jetons', 'Coût ($)', 'Coût (€)'], $rows, ['align' => ['l', 'r', 'r', 'r', 'r'], 'size' => 8.4]);
        $l->h2('Mode de calcul');
        $l->para([Layout::run('À chaque réponse, Google indique le nombre de jetons envoyés (dont ceux relus dans son cache), produits et de réflexion. Le site les multiplie par le tarif du modèle (dollars par million de jetons, tarifs publics de Google saisis dans le back-office), puis convertit en euros au taux indiqué. Le détail de chaque appel (date, usage, modèle, demandeur, jetons, coût) est joint en CSV. La facture Google (console Google Cloud › Facturation) fait foi : quelques centimes d’écart sont possibles (arrondis, taux de change).', 'serif', 9.6, 'ink')], ['after' => 14]);
        return $l->finish();
    }

    /** GET /admin/couts-ia/detail/{AAAA-MM}.csv : chaque appel du mois (Excel, LibreOffice). */
    public static function csv(Request $req, string $ym): ?Response
    {
        if (!preg_match('/^(\d{4}-\d{2})\.csv$/', $ym, $m)) {
            return null;
        }
        $cell = function ($v): string {
            $s = (string) $v;
            return preg_match('/[;"\r\n]/', $s) ? '"' . str_replace('"', '""', $s) . '"' : $s;
        };
        $dec = fn (float $v, int $d = 6) => number_format($v, $d, ',', '');
        $rows = [['Date', 'Heure', 'Usage', 'Modèle', 'Demandé par', 'Référence', 'Jetons envoyés', 'dont relus en cache', 'Jetons produits', 'dont réflexion', 'Coût ($)', 'Coût (€)', 'Remarque']];
        foreach (AiCosts::lines($m[1]) as $l) {
            $t = strtotime($l['at']);
            $note = !empty($l['x']) ? 'modèle absent du barème : tarif par défaut' : (!empty($l['e']) ? 'jetons estimés' : (isset($l['g']) ? 'niveau gratuit (coût évité : ' . $dec((float) $l['g']) . ' $)' : ''));
            $rows[] = [date('d/m/Y', $t), date('H:i:s', $t), AiCosts::USES[$l['f']] ?? $l['f'], $l['m'], $l['u'] ?? '', self::refLabel((string) ($l['r'] ?? '')), $l['in'], $l['c'], $l['out'] + $l['th'], $l['th'], $dec((float) $l['usd']), $dec(AiCosts::eur((float) $l['usd'])), $note];
        }
        $body = "\xEF\xBB\xBF" . implode("\r\n", array_map(fn ($r) => implode(';', array_map($cell, $r)), $rows)) . "\r\n";
        return new Response($body, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="frais-ia-' . $m[1] . '.csv"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    // ------------------------------------------------------------------ outils

    public static function monthLabel(string $ym): string
    {
        [$y, $mo] = array_map('intval', explode('-', $ym) + [1 => 1]);
        return (self::MONTHS[$mo - 1] ?? '') . ' ' . $y;
    }

    private static function n(int|float $n): string
    {
        return number_format((float) $n, 0, ',', ' ');
    }

    private static function calls(int $n): string
    {
        return self::n($n) . ' appel' . ($n > 1 ? 's' : '');
    }

    private static function refLabel(string $ref): string
    {
        if (preg_match('/^fiche:(\d+)$/', $ref, $m)) {
            $s = \App\Data\Index::get((int) $m[1]);
            return $s ? mb_strimwidth((string) $s['title'], 0, 60, '…') : 'fiche ' . $m[1];
        }
        if (str_starts_with($ref, 'ecran:')) {
            return 'écran ' . substr($ref, 6);
        }
        return $ref;
    }
}
