<?php

declare(strict_types=1);

namespace Poubelle\WhatsApp;

/**
 * Client de la WhatsApp Cloud API (Meta Graph API).
 * 100% PHP natif (cURL), aucune dépendance externe.
 *
 * Doc : https://developers.facebook.com/docs/whatsapp/cloud-api
 */
final class WhatsAppClient
{
    private string $token;
    private string $phoneNumberId;
    private string $apiVersion;
    private string $baseUrl;

    public function __construct(Config $config)
    {
        $this->token         = (string) $config->requireKey('access_token');
        $this->phoneNumberId = (string) $config->requireKey('phone_number_id');
        $this->apiVersion    = (string) $config->get('graph_api_version', 'v22.0');
        $this->baseUrl       = rtrim((string) $config->get('graph_base_url', 'https://graph.facebook.com'), '/');
    }

    /**
     * Message texte simple.
     * ⚠️ Nécessite une fenêtre de service ouverte (le client t'a écrit dans les dernières 24 h).
     * Hors de cette fenêtre, utilise sendTemplate().
     *
     * @return array<string,mixed>
     */
    public function sendText(string $to, string $body, bool $previewUrl = false): array
    {
        return $this->sendMessage($to, [
            'type' => 'text',
            'text' => ['preview_url' => $previewUrl, 'body' => $body],
        ]);
    }

    /**
     * Message template pré-approuvé — seul moyen d'INITIER une conversation
     * (hors fenêtre 24 h). Le template doit être validé côté Meta au préalable.
     *
     * @param array<int,array<string,mixed>> $components composants (body, boutons, en-tête…)
     * @return array<string,mixed>
     */
    public function sendTemplate(string $to, string $templateName, string $languageCode = 'fr', array $components = []): array
    {
        $template = [
            'name'     => $templateName,
            'language' => ['code' => $languageCode],
        ];
        if ($components !== []) {
            $template['components'] = $components;
        }

        return $this->sendMessage($to, ['type' => 'template', 'template' => $template]);
    }

    /**
     * Boutons de réponse rapide (max 3).
     *
     * @param array<int,array{id:string,title:string}> $buttons
     * @return array<string,mixed>
     */
    public function sendButtons(string $to, string $bodyText, array $buttons, ?string $header = null): array
    {
        $replyButtons = [];
        foreach ($buttons as $btn) {
            $replyButtons[] = [
                'type'  => 'reply',
                'reply' => ['id' => $btn['id'], 'title' => $btn['title']],
            ];
        }

        $interactive = [
            'type'   => 'button',
            'body'   => ['text' => $bodyText],
            'action' => ['buttons' => $replyButtons],
        ];
        if ($header !== null) {
            $interactive['header'] = ['type' => 'text', 'text' => $header];
        }

        return $this->sendMessage($to, ['type' => 'interactive', 'interactive' => $interactive]);
    }

    /**
     * Liste d'options (menu déroulant, max 10 lignes réparties en sections).
     *
     * @param array<int,array<string,mixed>> $sections
     * @return array<string,mixed>
     */
    public function sendList(string $to, string $bodyText, string $buttonLabel, array $sections, ?string $header = null): array
    {
        $interactive = [
            'type'   => 'list',
            'body'   => ['text' => $bodyText],
            'action' => ['button' => $buttonLabel, 'sections' => $sections],
        ];
        if ($header !== null) {
            $interactive['header'] = ['type' => 'text', 'text' => $header];
        }

        return $this->sendMessage($to, ['type' => 'interactive', 'interactive' => $interactive]);
    }

    /**
     * Image par URL publique (ou passe un media_id déjà uploadé via $link=null + $mediaId).
     *
     * @return array<string,mixed>
     */
    public function sendImage(string $to, string $link, ?string $caption = null): array
    {
        $image = ['link' => $link];
        if ($caption !== null) {
            $image['caption'] = $caption;
        }
        return $this->sendMessage($to, ['type' => 'image', 'image' => $image]);
    }

    /**
     * Marque un message entrant comme « lu » (double coche bleue côté client).
     *
     * @return array<string,mixed>
     */
    public function markAsRead(string $messageId): array
    {
        return $this->post($this->phoneNumberId . '/messages', [
            'messaging_product' => 'whatsapp',
            'status'            => 'read',
            'message_id'        => $messageId,
        ]);
    }

    /**
     * Normalise un numéro au format E.164 sans « + » (ex : 212612345678).
     */
    public function normalizeNumber(string $number): string
    {
        return preg_replace('/\D+/', '', $number) ?? '';
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function sendMessage(string $to, array $payload): array
    {
        $body = array_merge([
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $this->normalizeNumber($to),
        ], $payload);

        return $this->post($this->phoneNumberId . '/messages', $body);
    }

    /**
     * Requête POST JSON générique vers la Graph API.
     *
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    private function post(string $path, array $body): array
    {
        $url  = sprintf('%s/%s/%s', $this->baseUrl, $this->apiVersion, ltrim($path, '/'));
        $json = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->token,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response  = curl_exec($ch);
        $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException("Erreur réseau cURL : $curlError");
        }

        /** @var array<string,mixed> $decoded */
        $decoded = json_decode((string) $response, true) ?? [];

        if ($httpCode >= 400) {
            $apiError = $decoded['error']['message'] ?? 'erreur inconnue';
            throw new \RuntimeException("API WhatsApp (HTTP $httpCode) : $apiError\nRéponse brute : $response");
        }

        return $decoded;
    }
}
