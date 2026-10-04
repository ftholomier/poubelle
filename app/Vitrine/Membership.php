<?php
declare(strict_types=1);

namespace App\Vitrine;

use App\Core\JsonStore;
use App\Core\Settings;
use App\Services\Mailer;
use App\Services\Payments;

/**
 * Adhésions à l'association : en ligne (Stripe Checkout ou PayPal, avec les clés réglées pour
 * les dons), par chèque ou en main propre (saisies dans le back-office), via HelloAsso (lien).
 *
 * Données : storage/vitrine/adhesions.json (hors du dépôt et du dossier public). Comme pour les
 * dons, aucun paiement n'est validé sur la foi du navigateur : le statut est relu chez le
 * prestataire (page de retour, webhook signé, synchronisation).
 */
final class Membership
{
    public const FILE = STORAGE_PATH . '/vitrine/adhesions.json';

    public const STATUS = ['pending' => 'Paiement en attente', 'paid' => 'Payée', 'offline' => 'Règlement à recevoir', 'abandoned' => 'Abandonnée', 'failed' => 'Échec du paiement', 'refunded' => 'Remboursée', 'canceled' => 'Annulée'];
    public const PROVIDERS = ['stripe' => 'Carte bancaire', 'paypal' => 'PayPal', 'cheque' => 'Chèque', 'especes' => 'Espèces', 'virement' => 'Virement', 'helloasso' => 'HelloAsso', 'autre' => 'Autre'];

    // ------------------------------------------------------------------ données

    /** @return array<string,array> par identifiant */
    public static function all(): array
    {
        return JsonStore::read(self::FILE, []) ?: [];
    }

    public static function get(string $id): ?array
    {
        return self::all()[$id] ?? null;
    }

    public static function put(array $a): void
    {
        JsonStore::update(self::FILE, function ($all) use ($a) {
            $all = $all ?: [];
            $all[$a['id']] = $a;
            return $all;
        }, []);
    }

    public static function update(string $id, callable $fn): ?array
    {
        $out = null;
        JsonStore::update(self::FILE, function ($all) use ($id, $fn, &$out) {
            $all = $all ?: [];
            if (isset($all[$id])) {
                $all[$id] = $fn($all[$id]);
                $all[$id]['updated'] = date('c');
                $out = $all[$id];
            }
            return $all;
        }, []);
        return $out;
    }

    public static function delete(string $id): bool
    {
        $done = false;
        JsonStore::update(self::FILE, function ($all) use ($id, &$done) {
            $all = $all ?: [];
            $done = isset($all[$id]);
            unset($all[$id]);
            return $all;
        }, []);
        return $done;
    }

    public static function newId(): string
    {
        return 'A' . date('ymd') . '-' . bin2hex(random_bytes(3));
    }

    /** Identifiant d'adhésion valide (« A261004-a1b2c3 »). */
    public static function validId(string $id): bool
    {
        return (bool) preg_match('/^A\d{6}-[a-f0-9]{6}$/', $id);
    }

    /** Adhésions comptées comme acquises (payées ou réglées hors ligne et reçues). */
    public static function active(?int $year = null): array
    {
        $year ??= (int) date('Y');
        return array_filter(self::all(), fn ($a) => $a['status'] === 'paid' && (int) ($a['year'] ?? 0) === $year);
    }

    // ------------------------------------------------------------------ paiement en ligne

    /** Moyens de paiement en ligne utilisables pour l'adhésion (ceux des dons, si activés). */
    public static function methods(): array
    {
        if (!Settings::get('vitrine.membership_online', true) || !Settings::get('donations.enabled', false)) {
            return [];
        }
        $m = [];
        if (Payments::stripeReady()) {
            $m['stripe'] = 'Carte bancaire';
        }
        if (Payments::paypalReady()) {
            $m['paypal'] = 'PayPal';
        }
        return $m;
    }

    /**
     * Crée la session de paiement chez le prestataire.
     * @return array{url?:string,error?:string}
     */
    public static function checkout(array $a): array
    {
        // Retour sur le site (ou dans l'aperçu du back-office, pour un essai avant l'ouverture).
        $base = Site::$preview ? base_url() . Host::PREVIEW : Host::base();
        $back = $base . '/nous-soutenir/adherer/?annule=1';
        $ok = $base . '/nous-soutenir/adherer/merci/?a=' . $a['id'];
        $org = Site::name();
        $label = 'Adhésion ' . $a['year'] . ' · ' . $a['label'];
        $cents = (int) $a['amount'];
        try {
            if ($a['provider'] === 'stripe') {
                $session = Payments::stripe('POST', 'checkout/sessions', [
                    'mode' => 'payment',
                    'line_items' => [['quantity' => 1, 'price_data' => ['currency' => 'eur', 'unit_amount' => $cents, 'product_data' => ['name' => $label . ' — ' . $org]]]],
                    'customer_email' => $a['member']['email'],
                    'client_reference_id' => $a['id'],
                    'metadata' => ['adhesion' => $a['id']],
                    'payment_intent_data' => ['metadata' => ['adhesion' => $a['id']], 'description' => $label],
                    'success_url' => $ok . '&session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => $back,
                    'locale' => 'fr',
                ]);
                if (empty($session['url']) || empty($session['id'])) {
                    throw new \RuntimeException('Stripe : lien de paiement absent');
                }
                self::update($a['id'], fn ($x) => array_replace_recursive($x, ['ext' => ['stripe_session' => $session['id']]]));
                return ['url' => (string) $session['url']];
            }
            $r = Payments::paypal('POST', '/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [['reference_id' => $a['id'], 'custom_id' => $a['id'], 'description' => mb_substr($label, 0, 127), 'amount' => ['currency_code' => 'EUR', 'value' => number_format($cents / 100, 2, '.', '')]]],
                'payment_source' => ['paypal' => ['experience_context' => ['brand_name' => mb_substr($org, 0, 127), 'locale' => 'fr-FR', 'shipping_preference' => 'NO_SHIPPING', 'user_action' => 'PAY_NOW', 'return_url' => $ok . '&pp=order', 'cancel_url' => $back]]],
            ], ['PayPal-Request-Id: ' . $a['id']]);
            self::update($a['id'], fn ($x) => array_replace_recursive($x, ['ext' => ['paypal_order' => $r['id'] ?? null]]));
            $link = Payments::paypalApproveLink($r);
            if (!$link) {
                throw new \RuntimeException('PayPal : lien de paiement absent');
            }
            return ['url' => $link];
        } catch (\Throwable $e) {
            error_log('[adhesions] ' . $e->getMessage());
            self::update($a['id'], fn ($x) => ['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 300)] + $x);
            return ['error' => 'Le service de paiement ne répond pas pour le moment. Réessayez dans quelques minutes, ou choisissez le règlement par chèque.'];
        }
    }

    /** Page de retour : relit le paiement chez le prestataire. */
    public static function syncReturn(array $a, string $sessionId, string $pp, string $token): void
    {
        try {
            if ($a['provider'] === 'stripe' && $sessionId !== '' && $sessionId === ($a['ext']['stripe_session'] ?? '')) {
                self::stripeSession(Payments::stripe('GET', 'checkout/sessions/' . rawurlencode($sessionId)));
            } elseif ($a['provider'] === 'paypal' && $pp === 'order' && $token !== '' && $token === ($a['ext']['paypal_order'] ?? '')) {
                self::capturePaypal($a);
            }
        } catch (\Throwable $e) {
            error_log('[adhesions] retour ' . $a['id'] . ' : ' . $e->getMessage());
        }
    }

    /** Session Stripe Checkout (retour, webhook) : paiement confirmé ou abandon. */
    public static function stripeSession(array $s): void
    {
        $id = (string) ($s['metadata']['adhesion'] ?? $s['client_reference_id'] ?? '');
        $a = self::validId($id) ? self::get($id) : null;
        if (!$a || ($s['id'] ?? '') !== ($a['ext']['stripe_session'] ?? '')) {
            return;
        }
        if (!in_array($s['payment_status'] ?? '', ['paid', 'no_payment_required'], true)) {
            if (($s['status'] ?? '') === 'expired') {
                self::update($id, fn ($x) => $x['status'] === 'pending' ? ['status' => 'abandoned'] + $x : $x);
            }
            return;
        }
        if (!empty($s['payment_intent'])) {
            self::update($id, fn ($x) => array_replace_recursive($x, ['ext' => ['stripe_pi' => (string) $s['payment_intent']]]));
            self::recordPayment($id, (string) $s['payment_intent'], (int) ($s['amount_total'] ?? $a['amount']));
        }
    }

    private static function capturePaypal(array $a): void
    {
        $order = (string) ($a['ext']['paypal_order'] ?? '');
        if ($order === '' || $a['status'] !== 'pending') {
            return;
        }
        try {
            $r = Payments::paypal('POST', '/v2/checkout/orders/' . rawurlencode($order) . '/capture', null, ['PayPal-Request-Id: ' . $a['id'] . '-capture', 'Prefer: return=representation']);
        } catch (\Throwable) {
            $r = Payments::paypal('GET', '/v2/checkout/orders/' . rawurlencode($order));
        }
        $unit = $r['purchase_units'][0] ?? [];
        if (($unit['custom_id'] ?? $unit['reference_id'] ?? $a['id']) !== $a['id']) {
            return;
        }
        foreach ($unit['payments']['captures'] ?? [] as $c) {
            if (($c['status'] ?? '') === 'COMPLETED') {
                self::recordPayment($a['id'], (string) $c['id'], (int) round(((float) $c['amount']['value']) * 100));
            }
        }
    }

    /** Webhook PayPal : capture d'une commande d'adhésion (custom_id « A… »). */
    public static function paypalCaptureEvent(array $o): void
    {
        $id = (string) ($o['custom_id'] ?? '');
        $a = self::validId($id) ? self::get($id) : null;
        if ($a && $a['provider'] === 'paypal' && ($o['status'] ?? 'COMPLETED') === 'COMPLETED') {
            self::recordPayment($id, (string) $o['id'], (int) round(((float) ($o['amount']['value'] ?? 0)) * 100));
        }
    }

    /** Paiement reçu (idempotent : la même référence n'est comptée qu'une fois). */
    public static function recordPayment(string $id, string $ref, int $cents): void
    {
        $first = false;
        $a = self::update($id, function ($x) use ($ref, $cents, &$first) {
            foreach ($x['payments'] ?? [] as $p) {
                if ($p['ref'] === $ref) {
                    return $x;
                }
            }
            $x['payments'][] = ['ref' => $ref, 'amount' => $cents, 'at' => date('c')];
            $first = $x['status'] !== 'paid';
            $x['status'] = 'paid';
            $x['paid_at'] ??= date('c');
            return $x;
        });
        if ($a && $first) {
            self::welcome($a);
            self::notifyTeam($a, 'Nouvelle adhésion payée en ligne');
        }
    }

    /** Remboursement constaté chez le prestataire (webhook Stripe « charge.refunded »). */
    public static function refunded(string $ref): bool
    {
        foreach (self::all() as $a) {
            foreach ($a['payments'] ?? [] as $p) {
                if ($p['ref'] === $ref || ($a['ext']['stripe_pi'] ?? '') === $ref) {
                    self::update($a['id'], fn ($x) => ['status' => 'refunded'] + $x);
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Paiements en ligne jamais confirmés (paiement abandonné, retour interrompu) : relus chez
     * Stripe ou PayPal après une heure, classés « abandonnée » après deux jours (tâche planifiée).
     */
    public static function expire(): int
    {
        $n = 0;
        foreach (self::all() as $a) {
            if ($a['status'] !== 'pending' || !in_array($a['provider'], ['stripe', 'paypal'], true)) {
                continue;
            }
            $age = time() - (int) strtotime((string) $a['created']);
            if ($age > 3600 && $age < 3 * 86400) {
                try {
                    if ($a['provider'] === 'stripe' && !empty($a['ext']['stripe_session']) && Payments::stripeReady()) {
                        self::stripeSession(Payments::stripe('GET', 'checkout/sessions/' . rawurlencode((string) $a['ext']['stripe_session'])));
                    } elseif ($a['provider'] === 'paypal' && !empty($a['ext']['paypal_order']) && Payments::paypalReady()) {
                        self::capturePaypal($a);
                    }
                } catch (\Throwable $e) {
                    error_log('[adhesions] synchronisation ' . $a['id'] . ' : ' . $e->getMessage());
                }
            }
            if ($age > 2 * 86400 && (self::get($a['id'])['status'] ?? '') === 'pending') {
                self::update($a['id'], fn ($x) => ['status' => 'abandoned'] + $x);
                $n++;
            }
        }
        return $n;
    }

    /**
     * Durées de conservation (RGPD, voir la page Confidentialité) : paiement jamais finalisé,
     * effacé après 30 jours ; coordonnées d'une adhésion effacées 3 ans après son année (nom,
     * montant et paiements gardés pour la comptabilité), fiche supprimée après 10 ans.
     */
    public static function purge(): int
    {
        $n = 0;
        $year = (int) date('Y');
        JsonStore::update(self::FILE, function ($all) use (&$n, $year) {
            $all = $all ?: [];
            foreach ($all as $id => $a) {
                $old = (int) ($a['year'] ?? $year);
                if ((in_array($a['status'], ['abandoned', 'failed'], true) && empty($a['payments']) && strtotime((string) $a['created']) < time() - 30 * 86400) || $old < $year - 10) {
                    unset($all[$id]);
                    $n++;
                } elseif ($old < $year - 3 && (($a['member']['email'] ?? '') !== '' || ($a['member']['address'] ?? '') !== '')) {
                    $all[$id]['member'] = ['first' => $a['member']['first'] ?? '', 'last' => $a['member']['last'] ?? '', 'email' => '', 'phone' => '', 'address' => '', 'zip' => '', 'city' => '', 'family' => ''];
                    $all[$id]['source'] = '';
                    $n++;
                }
            }
            return $all;
        }, []);
        return $n;
    }

    // ------------------------------------------------------------------ e-mails

    public static function welcome(array $a): void
    {
        $m = $a['member'];
        $org = Site::name();
        Mailer::send((string) $m['email'], "Bienvenue à $org !",
            '<p>Bonjour ' . e((string) $m['first']) . ',</p>'
            . '<p>Merci ! Votre adhésion <b>' . e((string) $a['year']) . '</b> à l’association ' . e($org) . ' est enregistrée (' . e((string) $a['label']) . ', ' . e(self::money((int) $a['amount'])) . ').</p>'
            . '<p>Vous serez tenu informé de la vie de l’association, de ses projets et de son assemblée générale.</p>'
            . '<p>Référence de votre adhésion : <b>' . e((string) $a['id']) . '</b></p>'
            . '<p>À très vite,<br>L’équipe de ' . e($org) . '</p>');
    }

    public static function notifyTeam(array $a, string $what): void
    {
        $to = Site::email();
        if ($to === '') {
            return;
        }
        $m = $a['member'];
        Mailer::send($to, '[Adhésion] ' . $what . ' · ' . trim($m['first'] . ' ' . $m['last']),
            '<p><b>' . e($what) . '</b></p><p>' . e(trim($m['first'] . ' ' . $m['last'])) . ' · ' . e((string) $m['email']) . '</p>'
            . '<p>' . e((string) $a['label']) . ' · ' . e(self::money((int) $a['amount'])) . ' · ' . e(self::PROVIDERS[$a['provider']] ?? $a['provider']) . '</p>'
            . '<p><a href="' . e(base_url()) . '/admin/association/adhesions/' . e((string) $a['id']) . '">Ouvrir dans le back-office</a></p>', (string) $m['email']);
    }

    public static function money(int $cents): string
    {
        return number_format($cents / 100, $cents % 100 ? 2 : 0, ',', ' ') . ' €';
    }
}
