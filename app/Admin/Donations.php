<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Data\Activity;
use App\Front\Donations as Front;

/**
 * Dons : suivi de la collecte (jauge, liste filtrable, export CSV), détail d'un
 * don (paiements, reçus fiscaux, arrêt d'un don mensuel), dons reçus hors ligne
 * (chèque, virement, espèces) et modération du mur des donateurs.
 */
final class Donations extends Base
{
    public static function index(Request $req): Response
    {
        $all = Front::all();
        $status = $req->str('statut');
        $mode = $req->str('mode', Front::testMode() ? 'test' : 'live');
        $q = mb_strtolower($req->str('q'));
        $rows = array_values(array_filter($all, function ($d) use ($status, $mode, $q) {
            if ($mode !== 'tous' && ($d['mode'] ?? 'live') !== $mode && $d['provider'] !== 'manuel') {
                return false;
            }
            if ($status === 'mur') {
                return !empty($d['wall']) && ($d['wall_name'] ?? '') !== '';
            }
            if ($status === 'mensuels') {
                return $d['frequency'] === 'month' && in_array($d['status'], ['active', 'canceled'], true);
            }
            if ($status !== '' && $d['status'] !== $status) {
                return false;
            }
            if ($status === '' && in_array($d['status'], ['abandoned'], true)) {
                return false;
            }
            return $q === '' || str_contains(mb_strtolower(implode(' ', [$d['id'], $d['donor']['first'] ?? '', $d['donor']['last'] ?? '', $d['donor']['email'] ?? '', $d['wall_name'] ?? ''])), $q);
        }));
        usort($rows, fn ($a, $b) => strcmp((string) ($b['last_paid'] ?? $b['created']), (string) ($a['last_paid'] ?? $a['created'])));
        $kpi = ['month' => 0, 'year' => 0, 'active' => 0, 'monthly' => 0, 'receipts' => 0];
        foreach ($all as $d) {
            if (($d['mode'] ?? 'live') === 'test' && !Front::testMode() && $d['provider'] !== 'manuel') {
                continue;
            }
            foreach ($d['payments'] ?? [] as $p) {
                if ($p['status'] !== 'paid') {
                    continue;
                }
                if (substr((string) $p['at'], 0, 7) === date('Y-m')) {
                    $kpi['month'] += (int) $p['amount'];
                }
                if (substr((string) $p['at'], 0, 4) === date('Y')) {
                    $kpi['year'] += (int) $p['amount'];
                }
                if (!empty($p['receipt'])) {
                    $kpi['receipts']++;
                }
            }
            if ($d['frequency'] === 'month' && $d['status'] === 'active') {
                $kpi['active']++;
                $kpi['monthly'] += (int) $d['amount'];
            }
        }
        $total = count($rows);
        $pages = max(1, (int) ceil($total / 50));
        $page = min($pages, max(1, (int) $req->str('page', '1')));
        return self::html('admin/donations/index', [
            'rows' => array_slice($rows, ($page - 1) * 50, 50), 'total' => $total, 'page' => $page, 'pages' => $pages,
            'status' => $status, 'mode' => $mode, 'q' => $req->str('q'), 'kpi' => $kpi, 'gauge' => Front::gauge(), 'wall' => Front::wall(12),
            'enabled' => (bool) Settings::get('donations.enabled', false), 'test' => Front::testMode(), 'methods' => Front::methods(),
            'receipts' => (bool) Settings::get('donations.tax_receipts', false),
        ], ['title' => 'Dons', 'crumb' => 'Communauté', 'nav' => 'dons']);
    }

    public static function show(Request $req, string $id): Response
    {
        $d = Front::get($id);
        if (!$d) {
            return self::html('admin/message', ['title' => 'Don introuvable', 'text' => "Aucun don $id.", 'back' => '/admin/dons'], ['title' => 'Introuvable'], 404);
        }
        return self::html('admin/donations/show', ['d' => $d, 'receipts' => (bool) Settings::get('donations.tax_receipts', false), 'numbers' => Front::receiptNumbers($d)], [
            'title' => 'Don ' . $d['id'], 'crumb_html' => 'Communauté › <a href="/admin/dons">Dons</a>', 'nav' => 'dons',
        ]);
    }

    /** Actions générales : don hors ligne, recalcul de la jauge, synchronisation. */
    public static function action(Request $req): Response
    {
        $action = (string) ($req->post['action'] ?? '');
        $user = self::actor();
        if ($action === 'manuel') {
            $amount = (float) str_replace([',', ' ', "\u{a0}"], ['.', '', ''], (string) ($req->post['amount'] ?? '0'));
            $cents = (int) round($amount * 100);
            $first = Html::line($req->post['first'] ?? '', 80);
            if ($cents <= 0 || $first === '') {
                return self::back('/admin/dons', null, 'Indiquez au moins le montant et le prénom (ou le nom de l’organisme).');
            }
            $email = trim((string) ($req->post['email'] ?? ''));
            $date = (string) ($req->post['date'] ?? '') !== '' && strtotime((string) $req->post['date']) ? date('c', strtotime($req->post['date'] . ' 12:00')) : date('c');
            $wallName = Front::cleanWallName((string) ($req->post['wall_name'] ?? ''));
            $id = 'D' . date('ymd') . '-' . bin2hex(random_bytes(3));
            Front::put([
                'id' => $id, 'created' => date('c'), 'updated' => date('c'), 'mode' => 'live', 'provider' => 'manuel',
                'frequency' => 'once', 'amount' => $cents, 'currency' => 'eur', 'status' => 'pending',
                'donor' => [
                    'first' => $first, 'last' => Html::line($req->post['last'] ?? '', 80), 'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '',
                    'address' => Html::line($req->post['address'] ?? '', 200), 'zip' => Html::line($req->post['zip'] ?? '', 12), 'city' => Html::line($req->post['city'] ?? '', 80), 'country' => Html::line($req->post['country'] ?? 'France', 60),
                ],
                'wall' => $wallName !== '', 'wall_name' => $wallName, 'wall_hidden' => false,
                'receipt' => !empty($req->post['receipt']), 'lang' => 'fr', 'manage' => bin2hex(random_bytes(16)), 'ext' => [], 'payments' => [],
                'method_detail' => Html::line($req->post['method'] ?? 'chèque', 40), 'note' => Html::clean((string) ($req->post['note'] ?? '')), 'by' => $user['name'],
            ]);
            if (empty($req->post['thank'])) {
                // Pas de remerciement automatique : on ne transmet pas l'adresse au moment du paiement.
                $keep = Front::get($id)['donor']['email'];
                Front::update($id, fn ($x) => array_replace_recursive($x, ['donor' => ['email' => '']]));
                Front::recordPayment($id, 'manuel-' . bin2hex(random_bytes(4)), $cents, $date);
                Front::update($id, fn ($x) => array_replace_recursive($x, ['donor' => ['email' => $keep]]));
            } else {
                Front::recordPayment($id, 'manuel-' . bin2hex(random_bytes(4)), $cents, $date);
            }
            Activity::log($user, 'a enregistré un don hors ligne', ['title' => Front::money($cents) . ' · ' . $first]);
            return self::back('/admin/dons/' . $id, 'Don de ' . Front::money($cents) . ' enregistré : la jauge est à jour.');
        }
        if ($action === 'recalculer') {
            Front::rebuildStats();
            return self::back('/admin/dons', 'Jauge et mur des donateurs recalculés.');
        }
        if ($action === 'synchroniser') {
            @set_time_limit(120);
            try {
                $r = Front::sync();
                return self::back('/admin/dons', 'Synchronisation terminée : ' . json_encode($r, JSON_UNESCAPED_UNICODE));
            } catch (\Throwable $e) {
                return self::back('/admin/dons', null, 'Synchronisation impossible : ' . $e->getMessage());
            }
        }
        return self::back('/admin/dons', null, 'Action inconnue.');
    }

    /** Actions sur un don : mur, note, arrêt du mensuel, reçu fiscal, remboursement noté. */
    public static function update(Request $req, string $id): Response
    {
        $d = Front::get($id);
        if (!$d) {
            return self::back('/admin/dons', null, 'Don introuvable.');
        }
        $action = (string) ($req->post['action'] ?? '');
        $user = self::actor();
        $back = '/admin/dons/' . $id;
        switch ($action) {
            case 'mur':
                $name = Front::cleanWallName((string) ($req->post['wall_name'] ?? ''));
                Front::update($id, fn ($x) => ['wall_name' => $name, 'wall' => $name !== '', 'wall_hidden' => !empty($req->post['wall_hidden'])] + $x);
                Front::rebuildStats();
                Activity::log($user, 'a modéré le mur des donateurs', ['title' => $id]);
                return self::back($back, 'Mur des donateurs mis à jour.');
            case 'note':
                Front::update($id, fn ($x) => ['note' => Html::clean((string) ($req->post['note'] ?? ''))] + $x);
                return self::back($back, 'Note enregistrée.');
            case 'arreter':
                if ($d['frequency'] !== 'month' || $d['status'] !== 'active') {
                    return self::back($back, null, 'Ce don n’est pas un don mensuel actif.');
                }
                $ok = Front::cancelSubscription($d, 'back-office (' . $user['name'] . ')');
                Activity::log($user, 'a arrêté un don mensuel', ['title' => $id]);
                return self::back($back, $ok ? 'Don mensuel arrêté chez le prestataire ; le donateur est prévenu par e-mail.' : null, $ok ? null : 'Le prestataire n’a pas confirmé l’arrêt : réessayez ou arrêtez-le depuis son tableau de bord.');
            case 'recu':
                if (!Settings::get('donations.tax_receipts', false)) {
                    return self::back($back, null, 'Les reçus fiscaux sont désactivés (Réglages › Dons).');
                }
                $refs = array_values(array_filter((array) ($req->post['refs'] ?? []), 'is_string'));
                if (!$refs) {
                    $refs = array_column(array_filter($d['payments'] ?? [], fn ($p) => $p['status'] === 'paid' && empty($p['receipt'])), 'ref');
                }
                $num = $refs ? Front::issueReceipt($id, $refs) : null;
                if ($num && !empty($req->post['send']) && ($d['donor']['email'] ?? '') !== '') {
                    $file = Front::RECEIPTS . '/' . substr($num, 3, 4) . "/$num.pdf";
                    \App\Services\Mailer::send((string) $d['donor']['email'], 'Votre reçu fiscal · Sochaux Rétro', '<p>Bonjour ' . e($d['donor']['first'] ?? '') . ',</p><p>Veuillez trouver ci-joint le reçu fiscal correspondant à votre don. Merci pour votre soutien !</p>', null, [['name' => "recu-fiscal-$num.pdf", 'type' => 'application/pdf', 'data' => (string) file_get_contents($file)]]);
                }
                Activity::log($user, 'a émis un reçu fiscal', ['title' => (string) $num]);
                return self::back($back, $num ? "Reçu $num émis." : null, $num ? null : 'Aucun paiement sans reçu.');
            case 'rembourse':
                $ref = (string) ($req->post['ref'] ?? '');
                Front::update($id, function ($x) use ($ref) {
                    foreach ($x['payments'] as $i => $p) {
                        if ($p['ref'] === $ref) {
                            $x['payments'][$i]['status'] = 'refunded';
                            $x['payments'][$i]['refunded'] = date('c');
                        }
                    }
                    if ($x['frequency'] === 'once') {
                        $x['status'] = 'refunded';
                    }
                    return $x;
                });
                Front::rebuildStats();
                Activity::log($user, 'a noté un remboursement', ['title' => $id]);
                return self::back($back, 'Paiement marqué comme remboursé (effectuez le remboursement chez le prestataire si ce n’est pas déjà fait).');
            case 'supprimer':
                if (!Auth::can('destroy')) {
                    return self::back($back, null, 'Réservé aux administrateurs.');
                }
                if (array_filter($d['payments'] ?? [], fn ($p) => $p['status'] === 'paid')) {
                    return self::back($back, null, 'Un don payé ne se supprime pas (obligation comptable) : notez plutôt un remboursement.');
                }
                \App\Core\JsonStore::update(Front::FILE, function ($all) use ($id) {
                    unset($all[$id]);
                    return $all ?: [];
                }, []);
                Activity::log($user, 'a supprimé un don non abouti', ['title' => $id]);
                return self::back('/admin/dons', 'Don supprimé.');
        }
        return self::back($back, null, 'Action inconnue.');
    }

    public static function export(Request $req): Response
    {
        $year = $req->str('annee');
        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Don', 'Date du paiement', 'Montant (€)', 'Fréquence', 'Moyen', 'Mode', 'Statut', 'Prénom', 'Nom', 'E-mail', 'Adresse', 'Code postal', 'Ville', 'Pays', 'Reçu fiscal', 'Mur', 'Référence'], ';');
        foreach (Front::all() as $d) {
            foreach ($d['payments'] ?? [] as $p) {
                if ($year !== '' && substr((string) $p['at'], 0, 4) !== $year) {
                    continue;
                }
                fputcsv($out, csv_safe([
                    $d['id'], date('d/m/Y', strtotime((string) $p['at'])), number_format($p['amount'] / 100, 2, ',', ''), $d['frequency'] === 'month' ? 'mensuel' : 'ponctuel',
                    Front::PROVIDERS[$d['provider']] ?? $d['provider'], $d['mode'], $p['status'], $d['donor']['first'] ?? '', $d['donor']['last'] ?? '', $d['donor']['email'] ?? '',
                    $d['donor']['address'] ?? '', $d['donor']['zip'] ?? '', $d['donor']['city'] ?? '', $d['donor']['country'] ?? '', $p['receipt'] ?? '', !empty($d['wall']) && empty($d['wall_hidden']) ? ($d['wall_name'] ?? '') : '', $p['ref'],
                ]), ';');
            }
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        Activity::log(self::actor(), 'a exporté les dons', ['title' => $year ?: 'toutes années']);
        return new Response($csv, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="dons-sochaux-retro' . ($year ? "-$year" : '') . '-' . date('Ymd') . '.csv"']);
    }

    public static function receipt(Request $req, string $num): Response
    {
        if (!preg_match('/^RF-(\d{4})-\d{4,6}$/', $num, $m)) {
            return Response::notFound();
        }
        $file = Front::RECEIPTS . '/' . $m[1] . "/$num.pdf";
        if (!is_file($file)) {
            return Response::notFound();
        }
        return new Response((string) file_get_contents($file), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="' . $num . '.pdf"']);
    }
}
