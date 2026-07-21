<?php

declare(strict_types=1);

namespace Poubelle\WhatsApp;

/**
 * Traitement des webhooks WhatsApp :
 *  - vérification du challenge (GET) lors de la configuration côté Meta
 *  - vérification de la signature (X-Hub-Signature-256) des POST entrants
 *  - extraction des messages et des statuts
 */
final class WebhookHandler
{
    public function __construct(private Config $config)
    {
    }

    /**
     * Répond au challenge de vérification envoyé par Meta (requête GET).
     * Retourne la valeur de hub.challenge si le token correspond, sinon null.
     *
     * @param array<string,mixed> $query typiquement $_GET
     */
    public function verifyChallenge(array $query): ?string
    {
        $mode      = $query['hub_mode']         ?? ($query['hub.mode']         ?? null);
        $token     = $query['hub_verify_token'] ?? ($query['hub.verify_token'] ?? null);
        $challenge = $query['hub_challenge']    ?? ($query['hub.challenge']    ?? null);

        if ($mode === 'subscribe' && $token !== null && hash_equals((string) $this->config->requireKey('verify_token'), (string) $token)) {
            return (string) $challenge;
        }

        return null;
    }

    /**
     * Vérifie la signature HMAC-SHA256 du corps brut avec l'App Secret.
     * Si aucun app_secret n'est configuré, la vérification est ignorée
     * (pratique pour un démarrage rapide, mais À ACTIVER EN PROD).
     */
    public function verifySignature(string $rawBody, ?string $signatureHeader): bool
    {
        $appSecret = (string) $this->config->get('app_secret', '');
        if ($appSecret === '' || str_starts_with($appSecret, 'REMPLACE_')) {
            return true; // non configuré → on ne bloque pas (voir README, section Sécurité)
        }

        if ($signatureHeader === null || !str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $appSecret);

        return hash_equals($expected, $signatureHeader);
    }

    /**
     * Extrait les messages entrants d'un payload webhook.
     *
     * @param array<string,mixed> $payload
     * @return array<int,array<string,mixed>>
     */
    public function parseMessages(array $payload): array
    {
        $messages = [];

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value   = $change['value'] ?? [];
                $contact = $value['contacts'][0] ?? [];

                foreach ($value['messages'] ?? [] as $msg) {
                    $messages[] = [
                        'message_id'   => $msg['id']   ?? null,
                        'from'         => $msg['from'] ?? null,
                        'name'         => $contact['profile']['name'] ?? null,
                        'type'         => $msg['type'] ?? null,
                        'text'         => $msg['text']['body'] ?? null,
                        'button_reply' => $msg['interactive']['button_reply'] ?? null,
                        'list_reply'   => $msg['interactive']['list_reply'] ?? null,
                        'timestamp'    => $msg['timestamp'] ?? null,
                        'raw'          => $msg,
                    ];
                }
            }
        }

        return $messages;
    }

    /**
     * Extrait les mises à jour de statut (sent, delivered, read, failed).
     *
     * @param array<string,mixed> $payload
     * @return array<int,array<string,mixed>>
     */
    public function parseStatuses(array $payload): array
    {
        $statuses = [];

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                foreach ($change['value']['statuses'] ?? [] as $status) {
                    $statuses[] = [
                        'message_id'   => $status['id'] ?? null,
                        'status'       => $status['status'] ?? null,
                        'recipient_id' => $status['recipient_id'] ?? null,
                        'timestamp'    => $status['timestamp'] ?? null,
                        'error'        => $status['errors'][0]['title'] ?? null,
                    ];
                }
            }
        }

        return $statuses;
    }
}
