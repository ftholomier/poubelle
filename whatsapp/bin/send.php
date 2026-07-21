<?php

declare(strict_types=1);

/**
 * CLI de test pour envoyer un message via la WhatsApp Cloud API.
 *
 * Exemples :
 *   php whatsapp/bin/send.php text     212612345678 "Bonjour depuis PHP natif 🚀"
 *   php whatsapp/bin/send.php template 212612345678 hello_world en_US
 *   php whatsapp/bin/send.php buttons  212612345678
 *   php whatsapp/bin/send.php list     212612345678
 *
 * Astuce : renseigne "default_recipient" dans config.json pour omettre le numéro.
 */

require dirname(__DIR__) . '/bootstrap.php';

use Poubelle\WhatsApp\Config;
use Poubelle\WhatsApp\WhatsAppClient;

$config = Config::load();
$client = new WhatsAppClient($config);

$command = $argv[1] ?? 'text';
$to      = $argv[2] ?? (string) $config->get('default_recipient', '');

if ($to === '') {
    fwrite(STDERR, "❌ Numéro destinataire manquant.\n   Usage : php whatsapp/bin/send.php text 212XXXXXXXXX \"message\"\n");
    exit(1);
}

try {
    switch ($command) {
        case 'text':
            $body   = $argv[3] ?? 'Test depuis le starter WhatsApp Cloud API (PHP natif) 🚀';
            $result = $client->sendText($to, $body);
            break;

        case 'template':
            $name   = $argv[3] ?? 'hello_world';
            $lang   = $argv[4] ?? 'en_US';
            $result = $client->sendTemplate($to, $name, $lang);
            break;

        case 'buttons':
            $result = $client->sendButtons($to, 'Choisis une option :', [
                ['id' => 'opt_1', 'title' => 'Option 1'],
                ['id' => 'opt_2', 'title' => 'Option 2'],
                ['id' => 'opt_3', 'title' => 'Option 3'],
            ]);
            break;

        case 'list':
            $result = $client->sendList($to, 'Nos services :', 'Voir la liste', [
                [
                    'title' => 'Commerce',
                    'rows'  => [
                        ['id' => 'srv_boutique', 'title' => 'Boutique',  'description' => 'Voir le catalogue'],
                        ['id' => 'srv_panier',   'title' => 'Mon panier', 'description' => 'Reprendre ma commande'],
                    ],
                ],
                [
                    'title' => 'Support',
                    'rows'  => [
                        ['id' => 'srv_faq',     'title' => 'FAQ',     'description' => 'Questions fréquentes'],
                        ['id' => 'srv_contact', 'title' => 'Contact', 'description' => 'Parler à un conseiller'],
                    ],
                ],
            ]);
            break;

        default:
            fwrite(STDERR, "❌ Commande inconnue : $command (attendu : text | template | buttons | list)\n");
            exit(1);
    }

    echo "✅ Envoyé\n" . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
} catch (\Throwable $e) {
    fwrite(STDERR, '❌ Échec : ' . $e->getMessage() . "\n");
    exit(1);
}
