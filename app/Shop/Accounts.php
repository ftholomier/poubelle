<?php
declare(strict_types=1);

namespace App\Shop;

use App\Core\JsonStore;
use App\Front\PdfExport;
use App\Pdf\Layout;
use App\Services\Payments;

/**
 * Boutique, gestion (lot C) : chiffres du tableau de bord, frais Stripe et rapprochement des
 * paiements, litiges, alertes (au tableau de bord et par e-mail une fois par jour), relevés
 * mensuels de l'imprimeur (PDF et tableur) avec la marge de l'association.
 */
final class Accounts
{
    /** Commandes qui comptent dans les ventes (payées, pas annulées). */
    public const SOLD = ['paid', 'production', 'shipped', 'delivered', 'refunded'];

    private static function stripe(string $method, string $path, array $params = []): array
    {
        return Orders::$stripe ? (Orders::$stripe)($method, $path, $params) : Payments::stripe($method, $path, $params);
    }

    // ------------------------------------------------------------------ chiffres

    /** @return list<array> commandes payées (au moins une fois) */
    public static function sold(): array
    {
        return array_values(array_filter(Orders::all(), fn ($o) => !empty($o['paid_at']) && in_array($o['status'], self::SOLD, true)));
    }

    /** Montants d'une liste de commandes : ventes, remboursements, frais Stripe, coût imprimeur, marge. */
    public static function sums(array $orders): array
    {
        $c = Orders::config();
        $s = ['orders' => count($orders), 'items' => 0, 'sales' => 0, 'refunds' => 0, 'fees' => 0, 'cost' => 0, 'ship_cost' => 0];
        foreach ($orders as $o) {
            $s['sales'] += (int) $o['paid'];
            $s['refunds'] += (int) $o['refunded'];
            $s['fees'] += (int) ($o['fee'] ?? 0);
            if ($o['status'] !== 'refunded') {
                foreach ($o['items'] as $it) {
                    $s['items'] += (int) $it['qty'];
                    $s['cost'] += (int) ($it['cost'] ?? 0) * (int) $it['qty'];
                }
                $s['ship_cost'] += (int) ($o['ship_cost'] ?? $c['ship_cost']);
            }
        }
        $s['net'] = $s['sales'] - $s['refunds'] - $s['fees'];
        $s['margin'] = $s['net'] - $s['cost'] - $s['ship_cost'];
        $s['basket'] = $s['orders'] ? intdiv($s['sales'], $s['orders']) : 0;
        return $s;
    }

    /** Tableau de bord : jour, mois, année, 12 derniers mois, meilleures ventes, à suivre. */
    public static function dashboard(): array
    {
        $sold = self::sold();
        $at = fn ($o) => (string) $o['paid_at'];
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $ym = date('Y-m', strtotime(date('Y-m-01') . " -$i month"));
            $months[$ym] = self::sums(array_filter($sold, fn ($o) => str_starts_with($at($o), $ym)))['sales'];
        }
        $top = [];
        foreach ($sold as $o) {
            if ($o['status'] === 'refunded') {
                continue;
            }
            foreach ($o['items'] as $it) {
                $k = $it['model'];
                $top[$k] ??= ['name' => $it['name'], 'support' => $it['support'], 'qty' => 0, 'sales' => 0];
                $top[$k]['qty'] += (int) $it['qty'];
                $top[$k]['sales'] += (int) $it['total'];
            }
        }
        uasort($top, fn ($a, $b) => $b['qty'] <=> $a['qty'] ?: $b['sales'] <=> $a['sales']);
        $all = Orders::all();
        return [
            'day' => self::sums(array_filter($sold, fn ($o) => str_starts_with($at($o), date('Y-m-d')))),
            'month' => self::sums(array_filter($sold, fn ($o) => str_starts_with($at($o), date('Y-m')))),
            'year' => self::sums(array_filter($sold, fn ($o) => str_starts_with($at($o), date('Y')))),
            'all' => self::sums($sold),
            'months' => $months, 'top' => array_slice($top, 0, 8, true),
            'todo' => ['paid' => count(array_filter($all, fn ($o) => $o['status'] === 'paid')), 'production' => count(array_filter($all, fn ($o) => $o['status'] === 'production')), 'shipped' => count(array_filter($all, fn ($o) => $o['status'] === 'shipped'))],
            'alerts' => self::alerts(),
        ];
    }

    // ------------------------------------------------------------------ Stripe : frais, litiges, rapprochement

    /** Frais et net encaissé d'un paiement (lus chez Stripe) enregistrés sur la commande. */
    public static function fetchFee(string $id): void
    {
        $o = Orders::get($id);
        $pi = (string) ($o['ext']['stripe_pi'] ?? '');
        if (!$o || $pi === '' || (!Orders::$stripe && !Payments::stripeReady())) {
            return;
        }
        try {
            $p = self::stripe('GET', 'payment_intents/' . rawurlencode($pi), ['expand' => ['latest_charge.balance_transaction']]);
            $bt = $p['latest_charge']['balance_transaction'] ?? null;
            if (is_array($bt) && isset($bt['fee'])) {
                Orders::update($id, function ($x) use ($bt, $p) {
                    $x['fee'] = (int) $bt['fee'];
                    $x['net'] = (int) ($bt['net'] ?? 0);
                    $x['ext']['stripe_charge'] = (string) ($p['latest_charge']['id'] ?? '');
                    return $x;
                });
            }
        } catch (\Throwable $e) {
            error_log('[boutique] frais ' . $id . ' : ' . $e->getMessage());
        }
    }

    /** Litige ouvert, mis à jour ou clos chez Stripe (webhook « charge.dispute.* »). */
    public static function dispute(array $d): void
    {
        $pi = (string) ($d['payment_intent'] ?? '');
        foreach (Orders::all() as $o) {
            if ($pi === '' || ($o['ext']['stripe_pi'] ?? '') !== $pi) {
                continue;
            }
            $new = !isset($o['dispute']);
            Orders::update($o['id'], function ($x) use ($d) {
                $x['dispute'] = ['id' => (string) ($d['id'] ?? ''), 'status' => (string) ($d['status'] ?? ''), 'reason' => (string) ($d['reason'] ?? ''), 'amount' => (int) ($d['amount'] ?? 0), 'at' => date('c')];
                $x['history'][] = ['at' => date('c'), 'status' => $x['status'], 'by' => 'Stripe', 'note' => 'Litige : ' . ($d['status'] ?? '') . ' (' . ($d['reason'] ?? '') . ')'];
                return $x;
            });
            $c = Orders::config();
            if ($new && $c['alert_email'] !== '') {
                Orders::notify($c['alert_email'], 'Boutique : litige sur la commande ' . $o['id'], '<p>Le client conteste le paiement de la commande <b>' . e($o['id']) . '</b> (' . e(Orders::money((int) ($d['amount'] ?? 0))) . ', motif : ' . e((string) ($d['reason'] ?? '')) . ').</p><p>Répondez au litige dans le tableau de bord Stripe, avec le suivi d’expédition. <a href="' . e(base_url() . '/admin/boutique/commandes/' . $o['id']) . '">Voir la commande</a></p>');
            }
        }
    }

    /**
     * Rapprochement avec Stripe (30 derniers jours) : paiements reçus sans commande payée
     * (rattrapés), montants différents, commandes payées par carte introuvables chez Stripe.
     * @return array{rows:list<array>,fixed:int,error?:string}
     */
    public static function reconcile(int $days = 30): array
    {
        if (!Orders::$stripe && !Payments::stripeReady()) {
            return ['rows' => [], 'fixed' => 0, 'error' => 'Clés Stripe absentes.'];
        }
        try {
            $list = self::stripe('GET', 'payment_intents', ['limit' => 100, 'created' => ['gte' => time() - $days * 86400]]);
        } catch (\Throwable $e) {
            return ['rows' => [], 'fixed' => 0, 'error' => $e->getMessage()];
        }
        $rows = [];
        $fixed = 0;
        $seen = [];
        foreach ((array) ($list['data'] ?? []) as $p) {
            $oid = (string) ($p['metadata']['commande'] ?? '');
            if ($oid === '' || ($p['status'] ?? '') !== 'succeeded') {
                continue;
            }
            $seen[(string) $p['id']] = true;
            $o = Orders::get($oid);
            $amount = (int) ($p['amount_received'] ?? $p['amount'] ?? 0);
            if (!$o) {
                $rows[] = ['level' => 'error', 'order' => $oid, 'text' => 'Paiement de ' . Orders::money($amount) . ' reçu chez Stripe pour une commande inconnue.'];
            } elseif (empty($o['paid_at'])) {
                Orders::markPaid($oid, (string) $p['id'], $amount, 'rapprochement Stripe');
                $fixed++;
                $rows[] = ['level' => 'warn', 'order' => $oid, 'text' => 'Payée chez Stripe mais pas notée payée : rattrapée (fichiers et e-mails envoyés).'];
            } elseif ((int) $o['paid'] !== $amount) {
                $rows[] = ['level' => 'warn', 'order' => $oid, 'text' => 'Montant différent : ' . Orders::money((int) $o['paid']) . ' sur la commande, ' . Orders::money($amount) . ' chez Stripe.'];
            } else {
                if (!isset($o['fee'])) {
                    self::fetchFee($oid);
                }
                $rows[] = ['level' => 'ok', 'order' => $oid, 'text' => 'Concordant : ' . Orders::money($amount) . '.'];
            }
        }
        foreach (Orders::all() as $o) {
            $pi = (string) ($o['ext']['stripe_pi'] ?? '');
            if ($pi !== '' && !isset($seen[$pi]) && !empty($o['paid_at']) && strtotime((string) $o['paid_at']) > time() - $days * 86400) {
                $rows[] = ['level' => 'error', 'order' => $o['id'], 'text' => 'Notée payée par carte, mais paiement introuvable chez Stripe.'];
            }
        }
        JsonStore::write(STORAGE_PATH . '/shop/reconcile.json', ['at' => date('c'), 'rows' => $rows, 'fixed' => $fixed]);
        return ['rows' => $rows, 'fixed' => $fixed];
    }

    public static function lastReconcile(): ?array
    {
        $r = JsonStore::read(STORAGE_PATH . '/shop/reconcile.json', null);
        return is_array($r) ? $r : null;
    }

    // ------------------------------------------------------------------ alertes

    /** @return list<array{key:string,level:string,text:string,order:string}> */
    public static function alerts(?int $now = null): array
    {
        $now ??= time();
        $out = [];
        foreach (Orders::all() as $o) {
            $id = $o['id'];
            if (!empty($o['dispute']) && !in_array($o['dispute']['status'], ['won', 'lost', 'warning_closed'], true)) {
                $out[] = ['key' => "dispute:$id", 'level' => 'error', 'order' => $id, 'text' => 'Litige ouvert par le client (' . $o['dispute']['reason'] . ') : à traiter dans Stripe.'];
            }
            $since = function (string $st) use ($o): int {
                $t = 0;
                foreach ($o['history'] as $h) {
                    if ($h['status'] === $st) {
                        $t = strtotime((string) $h['at']);
                    }
                }
                return $t;
            };
            if ($o['status'] === 'paid' && ($t = $since('paid')) && $now - $t > 3 * 86400) {
                $out[] = ['key' => "late-prod:$id", 'level' => 'warn', 'order' => $id, 'text' => 'Payée depuis ' . intdiv($now - $t, 86400) . ' jours et pas encore en fabrication.'];
            }
            if ($o['status'] === 'production' && ($t = $since('production')) && $now - $t > 7 * 86400) {
                $out[] = ['key' => "late-ship:$id", 'level' => 'warn', 'order' => $id, 'text' => 'En fabrication depuis ' . intdiv($now - $t, 86400) . ' jours, pas encore expédiée.'];
            }
            $last = $o['messages'] ? end($o['messages']) : null;
            if ($last && $last['from'] === 'client' && $now - strtotime((string) $last['at']) > 2 * 86400 && !in_array($o['status'], ['canceled', 'refunded'], true)) {
                $out[] = ['key' => "msg:$id:" . md5((string) $last['at']), 'level' => 'warn', 'order' => $id, 'text' => 'Question du client sans réponse depuis ' . intdiv($now - (int) strtotime((string) $last['at']), 86400) . ' jours.'];
            }
        }
        foreach (Catalog::models() as $m) {
            if ($m['active'] && $m['sale']['price'] <= 0) {
                $out[] = ['key' => 'price:' . $m['id'], 'level' => 'info', 'order' => '', 'text' => 'Le modèle « ' . $m['name'] . ' » est prêt à la vente mais n’a pas de prix : il n’apparaît pas dans la boutique.'];
            }
        }
        return $out;
    }

    /** Tâche planifiée : envoie à l'association les nouvelles alertes (une fois chacune). */
    public static function mailAlerts(): int
    {
        $c = Orders::config();
        $alerts = array_filter(self::alerts(), fn ($a) => $a['level'] !== 'info');
        $file = STORAGE_PATH . '/shop/alerts-sent.json';
        $sent = (array) JsonStore::read($file, []);
        $new = array_values(array_filter($alerts, fn ($a) => !isset($sent[$a['key']])));
        if (!$new || $c['alert_email'] === '') {
            return 0;
        }
        $h = '<p>À suivre dans la boutique :</p><ul>';
        foreach ($new as $a) {
            $h .= '<li>' . ($a['order'] !== '' ? '<a href="' . e(base_url() . '/admin/boutique/commandes/' . $a['order']) . '">' . e($a['order']) . '</a> : ' : '') . e($a['text']) . '</li>';
            $sent[$a['key']] = date('c');
        }
        Orders::notify($c['alert_email'], 'Boutique : ' . count($new) . ' point(s) à suivre', $h . '</ul>');
        JsonStore::write($file, array_slice($sent, -2000, null, true));
        return count($new);
    }

    // ------------------------------------------------------------------ relevé mensuel de l'imprimeur

    /** Relevé d'un mois (AAAA-MM) : articles fabriqués et montants. */
    public static function statement(string $ym): array
    {
        $ym = preg_match('/^\d{4}-\d{2}$/', $ym) ? $ym : date('Y-m');
        $orders = array_values(array_filter(self::sold(), fn ($o) => str_starts_with((string) $o['paid_at'], $ym)));
        usort($orders, fn ($a, $b) => strcmp((string) $a['paid_at'], (string) $b['paid_at']));
        $c = Orders::config();
        $rows = [];
        foreach ($orders as $o) {
            if ($o['status'] === 'refunded') {
                continue;
            }
            foreach ($o['items'] as $it) {
                $rows[] = ['date' => substr((string) $o['paid_at'], 0, 10), 'order' => $o['id'], 'status' => Orders::STATUSES[$o['status']], 'name' => $it['name'], 'support' => $it['support'], 'size' => $it['size'], 'qty' => (int) $it['qty'], 'unit_cost' => (int) ($it['cost'] ?? 0), 'cost' => (int) ($it['cost'] ?? 0) * (int) $it['qty']];
            }
            $rows[] = ['date' => substr((string) $o['paid_at'], 0, 10), 'order' => $o['id'], 'status' => Orders::STATUSES[$o['status']], 'name' => 'Expédition', 'support' => '', 'size' => '', 'qty' => 1, 'unit_cost' => (int) ($o['ship_cost'] ?? $c['ship_cost']), 'cost' => (int) ($o['ship_cost'] ?? $c['ship_cost'])];
        }
        return ['ym' => $ym, 'rows' => $rows, 'sums' => self::sums($orders), 'printer' => $c['printer_name']];
    }

    public static function statementCsv(array $s, bool $margins = true): string
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Date', 'Commande', 'Étape', 'Article', 'Support', 'Taille', 'Quantité', 'Coût unitaire (€)', 'Coût (€)'], ';');
        $eur = fn (int $c) => number_format($c / 100, 2, ',', '');
        foreach ($s['rows'] as $r) {
            fputcsv($out, [$r['date'], $r['order'], $r['status'], $r['name'], $r['support'], $r['size'], $r['qty'], $eur($r['unit_cost']), $eur($r['cost'])], ';');
        }
        $m = $s['sums'];
        fputcsv($out, [], ';');
        fputcsv($out, ['Total à payer à l’imprimeur', '', '', '', '', '', '', '', $eur($m['cost'] + $m['ship_cost'])], ';');
        if ($margins) {
            foreach (['Ventes encaissées' => $m['sales'], 'Remboursements' => -$m['refunds'], 'Frais Stripe' => -$m['fees'], 'Fabrication et expédition' => -($m['cost'] + $m['ship_cost']), 'Marge de l’association' => $m['margin']] as $k => $v) {
                fputcsv($out, [$k, '', '', '', '', '', '', '', $eur($v)], ';');
            }
        }
        rewind($out);
        return (string) stream_get_contents($out);
    }

    public static function statementPdf(array $s, bool $margins = true): string
    {
        $month = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'][(int) substr($s['ym'], 5, 2) - 1] . ' ' . substr($s['ym'], 0, 4);
        $l = PdfExport::layout('/boutique/', 'Relevé imprimeur · ' . $month, 'Relevé imprimeur ' . $month, 'Boutique Sochaux Rétro');
        $l->newPage();
        $l->masthead('Boutique');
        PdfExport::titleBlock($l, 'Relevé de fabrication', $month, 'Imprimeur : ' . ($s['printer'] !== '' ? $s['printer'] : '—') . ' · articles des commandes payées dans le mois');
        $m = $s['sums'];
        $facts = [['Commandes', (string) $m['orders']], ['Articles', (string) $m['items']], ['À payer à l’imprimeur', Orders::money($m['cost'] + $m['ship_cost'])]];
        if ($margins) {
            $facts = array_merge($facts, [['Ventes encaissées', Orders::money($m['sales'])], ['Remboursements et frais Stripe', Orders::money($m['refunds'] + $m['fees'])], ['Marge de l’association', Orders::money($m['margin'])]]);
        }
        $l->facts($facts, 3);
        $l->gap(10);
        $rows = array_map(fn ($r) => [date('d/m', strtotime($r['date'])), $r['order'], $r['name'] . ($r['support'] !== '' ? ' (' . $r['support'] . ($r['size'] !== '' ? ', ' . $r['size'] : '') . ')' : ''), (string) $r['qty'], Orders::money($r['unit_cost']), Orders::money($r['cost'])], $s['rows']);
        if ($rows) {
            $rows[] = ['', '', 'Total', '', '', Orders::money($m['cost'] + $m['ship_cost']), '_bg' => 'butter'];
            $l->table(['Date', 'Commande', 'Article', 'Qté', 'Unitaire', 'Coût'], $rows, ['align' => [3 => 'right', 4 => 'right', 5 => 'right']]);
        } else {
            $l->para([Layout::run('Aucune commande payée ce mois-ci.', 'serif-i', 11, 'muted')]);
        }
        return $l->finish();
    }
}
