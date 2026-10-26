<?php
declare(strict_types=1);

namespace LD;

/**
 * Envoi de SMS (rappel de rendez-vous la veille) via Brevo ou Twilio.
 * Désactivable dans Paramètres › SMS : la case « rappel par SMS » disparaît alors du site et aucun SMS ne part.
 */
final class Sms
{
    /** SMS activés dans le back-office et fournisseur configuré. */
    public static function enabled(): bool
    {
        if (!Settings::get('sms.enabled', false)) {
            return false;
        }
        $p = (string) Settings::get('sms.provider', 'none');
        return match ($p) {
            'brevo'  => Secrets::has('brevo_api_key'),
            'twilio' => Secrets::has('twilio_auth_token') && Settings::get('sms.twilio_sid'),
            default  => false,
        };
    }

    /** Numéro au format international (+33…) à partir d'une saisie française. */
    public static function normalize(string $phone): ?string
    {
        $d = preg_replace('/[^\d+]/', '', $phone) ?? '';
        if (str_starts_with($d, '00')) {
            $d = '+' . substr($d, 2);
        }
        if (preg_match('/^0[1-9]\d{8}$/', $d)) {
            $d = '+33' . substr($d, 1);
        }
        return preg_match('/^\+\d{8,15}$/', $d) ? $d : null;
    }

    public static function send(string $phone, string $message): bool
    {
        $to = self::normalize($phone);
        if (!$to || !self::enabled()) {
            return false;
        }
        $sender = substr(preg_replace('/[^A-Za-z0-9]/', '', (string) Settings::get('sms.sender', 'LEDIGITAL')) ?: 'LEDIGITAL', 0, 11);
        $provider = (string) Settings::get('sms.provider');
        if ($provider === 'brevo') {
            $r = Http::request('POST', 'https://api.brevo.com/v3/transactionalSMS/sms', [
                'api-key' => (string) Secrets::get('brevo_api_key'), 'Accept' => 'application/json',
            ], ['sender' => $sender, 'recipient' => ltrim($to, '+'), 'content' => $message, 'type' => 'transactional'], 20);
        } else {
            $sid = (string) Settings::get('sms.twilio_sid');
            $from = (string) Settings::get('sms.twilio_from') ?: $sender;
            $r = Http::request('POST', 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($sid) . '/Messages.json', [
                'Authorization' => 'Basic ' . base64_encode($sid . ':' . Secrets::get('twilio_auth_token')),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ], http_build_query(['To' => $to, 'From' => $from, 'Body' => $message]), 20);
        }
        $ok = $r['status'] >= 200 && $r['status'] < 300;
        Store::appendLog('sms', ['to' => substr($to, 0, -4) . '****', 'ok' => $ok, 'status' => $r['status'], 'error' => $ok ? null : mb_substr($r['body'], 0, 300)]);
        return $ok;
    }
}
