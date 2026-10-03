<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\JsonStore;
use App\Core\Settings;

/**
 * Prestataires de paiement : Stripe (Checkout, abonnements) et PayPal (Orders v2,
 * Subscriptions). Appels HTTP directs, sans SDK. Les clés restent chiffrées dans
 * les réglages et ne quittent jamais le serveur.
 */
final class Payments
{
    // ------------------------------------------------------------------ Stripe

    public static function stripeReady(): bool
    {
        $sk = (string) Settings::get('donations.stripe_secret_key', '');
        if ($sk === '') {
            return false;
        }
        // En mode test, seule une clé de test est acceptée (aucun prélèvement réel).
        return Settings::get('donations.mode', 'test') === 'live' ? str_starts_with($sk, 'sk_live_') : str_starts_with($sk, 'sk_test_');
    }

    public static function stripe(string $method, string $path, array $params = []): array
    {
        $sk = (string) Settings::get('donations.stripe_secret_key', '');
        $ch = curl_init('https://api.stripe.com/v1/' . ltrim($path, '/') . ($method === 'GET' && $params ? '?' . http_build_query($params) : ''));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERPWD => $sk . ':',
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => ['Stripe-Version: 2024-06-20'],
        ]);
        if ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($body === false || $code === 0) {
            throw new \RuntimeException('Stripe injoignable : ' . $err);
        }
        $data = json_decode((string) $body, true);
        if (!is_array($data)) {
            throw new \RuntimeException("Stripe : réponse illisible (HTTP $code)");
        }
        if ($code >= 400 || isset($data['error'])) {
            throw new \RuntimeException('Stripe : ' . ($data['error']['message'] ?? "erreur HTTP $code"));
        }
        return $data;
    }

    /** Vérifie la signature d'un webhook Stripe (en-tête Stripe-Signature, tolérance 5 min). */
    public static function stripeVerify(string $payload, string $header): bool
    {
        $secret = (string) Settings::get('donations.stripe_webhook_secret', '');
        if ($secret === '' || $header === '') {
            return false;
        }
        $t = null;
        $sigs = [];
        foreach (explode(',', $header) as $part) {
            [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($k === 't') {
                $t = (int) $v;
            } elseif ($k === 'v1') {
                $sigs[] = $v;
            }
        }
        if (!$t || abs(time() - $t) > 300) {
            return false;
        }
        $expected = hash_hmac('sha256', $t . '.' . $payload, $secret);
        foreach ($sigs as $s) {
            if (hash_equals($expected, $s)) {
                return true;
            }
        }
        return false;
    }

    // ------------------------------------------------------------------ PayPal

    public static function paypalReady(): bool
    {
        return (string) Settings::get('donations.paypal_client_id', '') !== '' && (string) Settings::get('donations.paypal_secret', '') !== '';
    }

    private static function paypalBase(): string
    {
        return Settings::get('donations.mode', 'test') === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
    }

    private static function paypalToken(): string
    {
        $cache = JsonStore::read(STORAGE_PATH . '/dons/paypal-token.json', []);
        if (($cache['exp'] ?? 0) > time() + 60 && ($cache['mode'] ?? '') === Settings::get('donations.mode', 'test')) {
            return (string) $cache['token'];
        }
        $ch = curl_init(self::paypalBase() . '/v1/oauth2/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERPWD => Settings::get('donations.paypal_client_id', '') . ':' . Settings::get('donations.paypal_secret', ''),
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false || $code === 0) {
            throw new \RuntimeException('PayPal injoignable');
        }
        $data = json_decode((string) $raw, true) ?: [];
        if (empty($data['access_token'])) {
            throw new \RuntimeException('PayPal : authentification refusée');
        }
        JsonStore::write(STORAGE_PATH . '/dons/paypal-token.json', ['token' => $data['access_token'], 'exp' => time() + (int) ($data['expires_in'] ?? 3000), 'mode' => Settings::get('donations.mode', 'test')]);
        return (string) $data['access_token'];
    }

    public static function paypal(string $method, string $path, ?array $body = null, array $headers = []): array
    {
        $ch = curl_init(self::paypalBase() . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json', 'Authorization: Bearer ' . self::paypalToken()], $headers),
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false || $code === 0) {
            throw new \RuntimeException('PayPal injoignable');
        }
        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            $data = [];
            if ($code >= 300 || trim((string) $raw) !== '') {
                throw new \RuntimeException("PayPal : réponse illisible (HTTP $code)");
            }
        }
        if ($code >= 400) {
            throw new \RuntimeException('PayPal : ' . ($data['message'] ?? $data['error_description'] ?? "erreur HTTP $code"));
        }
        return $data;
    }

    /** Lien d'approbation d'une réponse PayPal. */
    public static function paypalApproveLink(array $r): ?string
    {
        foreach ($r['links'] ?? [] as $l) {
            if (in_array($l['rel'] ?? '', ['approve', 'payer-action'], true)) {
                return (string) $l['href'];
            }
        }
        return null;
    }

    /** Plan d'abonnement mensuel PayPal pour un montant (produit et plans créés une fois puis mémorisés). */
    public static function paypalPlan(int $cents): string
    {
        $file = STORAGE_PATH . '/dons/paypal-plans.json';
        $mode = (string) Settings::get('donations.mode', 'test');
        $plans = JsonStore::read($file, []);
        if (!empty($plans[$mode]['plans'][$cents])) {
            return (string) $plans[$mode]['plans'][$cents];
        }
        $product = $plans[$mode]['product'] ?? null;
        if (!$product) {
            $p = self::paypal('POST', '/v1/catalogs/products', ['name' => 'Don mensuel ' . Settings::get('donations.org_name', 'Sochaux Rétro'), 'type' => 'SERVICE', 'category' => 'NONPROFIT']);
            $product = $p['id'];
        }
        $value = number_format($cents / 100, 2, '.', '');
        $plan = self::paypal('POST', '/v1/billing/plans', [
            'product_id' => $product,
            'name' => "Don mensuel de $value €",
            'billing_cycles' => [[
                'frequency' => ['interval_unit' => 'MONTH', 'interval_count' => 1],
                'tenure_type' => 'REGULAR', 'sequence' => 1, 'total_cycles' => 0,
                'pricing_scheme' => ['fixed_price' => ['value' => $value, 'currency_code' => 'EUR']],
            ]],
            'payment_preferences' => ['auto_bill_outstanding' => true, 'payment_failure_threshold' => 2],
        ]);
        JsonStore::update($file, function ($all) use ($mode, $product, $cents, $plan) {
            $all = $all ?: [];
            $all[$mode]['product'] = $product;
            $all[$mode]['plans'][$cents] = $plan['id'];
            return $all;
        }, []);
        return (string) $plan['id'];
    }

    /** Vérifie un webhook PayPal auprès de PayPal (signature de l'en-tête de transmission). */
    public static function paypalVerify(array $headers, array $event): bool
    {
        $id = (string) Settings::get('donations.paypal_webhook_id', '');
        if ($id === '') {
            return false;
        }
        try {
            $r = self::paypal('POST', '/v1/notifications/verify-webhook-signature', [
                'auth_algo' => $headers['PAYPAL-AUTH-ALGO'] ?? '',
                'cert_url' => $headers['PAYPAL-CERT-URL'] ?? '',
                'transmission_id' => $headers['PAYPAL-TRANSMISSION-ID'] ?? '',
                'transmission_sig' => $headers['PAYPAL-TRANSMISSION-SIG'] ?? '',
                'transmission_time' => $headers['PAYPAL-TRANSMISSION-TIME'] ?? '',
                'webhook_id' => $id,
                'webhook_event' => $event,
            ]);
            return ($r['verification_status'] ?? '') === 'SUCCESS';
        } catch (\Throwable $e) {
            error_log((string) $e);
            return false;
        }
    }
}
