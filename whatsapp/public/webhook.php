<?php

declare(strict_types=1);

/**
 * Point d'entrée du webhook WhatsApp Cloud API.
 *
 *  - GET  : vérification (Meta envoie hub.challenge lors de la config)
 *  - POST : réception des messages et statuts (signés en X-Hub-Signature-256)
 *
 * Sert ce dossier en dev :  php -S localhost:8080 -t whatsapp/public
 * URL du webhook :          https://<ton-tunnel-ngrok>/webhook.php
 */

require dirname(__DIR__) . '/bootstrap.php';

use Poubelle\WhatsApp\Config;
use Poubelle\WhatsApp\Logger;
use Poubelle\WhatsApp\WebhookHandler;
use Poubelle\WhatsApp\WhatsAppClient;

$config  = Config::load();
$logger  = new Logger(dirname(__DIR__) . '/logs/webhook.log');
$handler = new WebhookHandler($config);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/* ------------------------------------------------------------------ *
 * 1) Vérification du webhook (GET)
 * ------------------------------------------------------------------ */
if ($method === 'GET') {
    $challenge = $handler->verifyChallenge($_GET);
    if ($challenge !== null) {
        header('Content-Type: text/plain; charset=utf-8');
        echo $challenge;
        exit;
    }
    http_response_code(403);
    echo 'Vérification échouée';
    exit;
}

/* ------------------------------------------------------------------ *
 * 2) Réception des événements (POST)
 * ------------------------------------------------------------------ */
if ($method === 'POST') {
    $rawBody   = file_get_contents('php://input') ?: '';
    $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? null;

    if (!$handler->verifySignature($rawBody, $signature)) {
        $logger->error('Signature webhook invalide', ['signature' => $signature]);
        http_response_code(401);
        exit;
    }

    // On accuse réception tout de suite (Meta réémet si pas de 200 rapide),
    // puis on traite en arrière-plan quand le SAPI le permet (php-fpm).
    http_response_code(200);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'OK';
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }

    /** @var array<string,mixed> $payload */
    $payload  = json_decode($rawBody, true) ?? [];
    $messages = $handler->parseMessages($payload);
    $statuses = $handler->parseStatuses($payload);

    if ($statuses !== []) {
        $logger->info('Statuts reçus', ['statuses' => $statuses]);
    }

    if ($messages !== []) {
        $client = new WhatsAppClient($config);
        foreach ($messages as $message) {
            $logger->info('Message reçu', [
                'from' => $message['from'],
                'type' => $message['type'],
                'text' => $message['text'],
            ]);

            try {
                if ($message['message_id'] !== null) {
                    $client->markAsRead((string) $message['message_id']);
                }
                autoReply($client, $message);
            } catch (\Throwable $e) {
                $logger->error('Erreur de traitement', ['error' => $e->getMessage()]);
            }
        }
    }

    exit;
}

http_response_code(405);
echo 'Méthode non autorisée';

/**
 * Logique métier d'EXEMPLE. Remplace-la par la tienne (FAQ, prise de commande,
 * suivi de colis, prise de RDV…). Les réponses texte ci-dessous fonctionnent
 * car on est dans la fenêtre de service 24 h (le client vient d'écrire).
 *
 * @param array<string,mixed> $message
 */
function autoReply(WhatsAppClient $client, array $message): void
{
    $to = $message['from'] ?? null;
    if ($to === null) {
        return;
    }
    $to = (string) $to;

    // a) Réponse à un clic sur un bouton interactif
    if (is_array($message['button_reply'])) {
        $title = $message['button_reply']['title'] ?? '';
        $id    = $message['button_reply']['id'] ?? '';
        $client->sendText($to, "Tu as choisi : « $title » (id: $id) ✅");
        return;
    }

    // b) Réponse à une sélection dans une liste
    if (is_array($message['list_reply'])) {
        $title = $message['list_reply']['title'] ?? '';
        $client->sendText($to, "Sélection : « $title » ✅");
        return;
    }

    $text = trim((string) ($message['text'] ?? ''));
    $name = $message['name'] ?? null;

    if ($text === '') {
        $client->sendText($to, 'Message bien reçu ✅');
        return;
    }

    // c) Petit menu de démonstration (FR + salutations darija/arabe courantes)
    $normalized = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
    if (in_array($normalized, ['bonjour', 'salut', 'salam', 'salamou alaykoum', 'hello', 'hi', 'ahlan'], true)) {
        $client->sendButtons(
            $to,
            'Bonjour' . ($name ? " $name" : '') . " 👋\nComment puis-je t'aider ?",
            [
                ['id' => 'menu_infos',   'title' => 'Infos'],
                ['id' => 'menu_contact', 'title' => 'Contact'],
                ['id' => 'menu_aide',    'title' => 'Aide'],
            ]
        );
        return;
    }

    // d) Écho par défaut
    $client->sendText($to, "Tu as écrit : « $text »");
}
