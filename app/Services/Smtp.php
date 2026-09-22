<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

/**
 * Client SMTP minimal, écrit sur les sockets de PHP — aucune dépendance.
 *
 * Il sert de second transport au Mailer : `mail()` suffit sur un hébergement
 * mutualisé qui porte son propre MTA, mais beaucoup de boîtes classent ces
 * messages en indésirables. Renseigner un serveur SMTP authentifié (celui du
 * domaine, ou un service d'envoi) fait passer les alertes par un expéditeur
 * reconnu.
 *
 * Volontairement limité : un destinataire par enveloppe, corps texte, pas de
 * pipelining. C'est tout ce dont le site a besoin.
 */
final class Smtp
{
    private const TIMEOUT = 15;

    private static string $lastError = '';

    public static function lastError(): string
    {
        return self::$lastError;
    }

    /** Un serveur est-il configuré ? Sinon le Mailer reste sur mail(). */
    public static function configured(): bool
    {
        return trim((string) Config::secret('smtp_host', '')) !== '';
    }

    /**
     * @param string[] $recipients
     * @param string[] $headers    lignes d'en-tête déjà formatées
     */
    public static function send(array $recipients, string $headers, string $body, string $from): bool
    {
        self::$lastError = '';

        $host = trim((string) Config::secret('smtp_host', ''));
        $port = (int) Config::secret('smtp_port', '587');
        $user = trim((string) Config::secret('smtp_user', ''));
        $pass = (string) Config::secret('smtp_pass', '');
        $secure = strtolower(trim((string) Config::secret('smtp_secure', 'tls')));

        if ($host === '') {
            self::$lastError = 'Aucun serveur SMTP configuré.';
            return false;
        }
        $port = $port > 0 ? $port : ($secure === 'ssl' ? 465 : 587);

        $context = stream_context_create(['ssl' => [
            'verify_peer'       => true,
            'verify_peer_name'  => true,
            'SNI_enabled'       => true,
        ]]);

        $endpoint = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $socket = @stream_socket_client(
            $endpoint,
            $errno,
            $errstr,
            self::TIMEOUT,
            STREAM_CLIENT_CONNECT,
            $context,
        );

        if (!is_resource($socket)) {
            self::$lastError = sprintf('Connexion à %s impossible : %s', $endpoint, $errstr ?: 'délai dépassé');
            return false;
        }
        stream_set_timeout($socket, self::TIMEOUT);

        try {
            if (!self::expect($socket, 220)) {
                return false;
            }

            $ehlo = self::helo();
            if (!self::command($socket, 'EHLO ' . $ehlo, 250)) {
                return false;
            }

            if ($secure === 'tls') {
                if (!self::command($socket, 'STARTTLS', 220)) {
                    return false;
                }
                $crypto = @stream_socket_enable_crypto(
                    $socket,
                    true,
                    STREAM_CRYPTO_METHOD_TLS_CLIENT,
                );
                if ($crypto !== true) {
                    self::$lastError = 'Le serveur a refusé le chiffrement TLS.';
                    return false;
                }
                if (!self::command($socket, 'EHLO ' . $ehlo, 250)) {
                    return false;
                }
            }

            if ($user !== '') {
                if (!self::command($socket, 'AUTH LOGIN', 334)
                    || !self::command($socket, base64_encode($user), 334)
                    || !self::command($socket, base64_encode($pass), 235)) {
                    // Ne jamais recopier le mot de passe dans le message d'erreur.
                    self::$lastError = self::$lastError !== ''
                        ? 'Authentification refusée : ' . self::$lastError
                        : 'Authentification refusée.';
                    return false;
                }
            }

            if (!self::command($socket, 'MAIL FROM:<' . $from . '>', 250)) {
                return false;
            }
            foreach ($recipients as $recipient) {
                if (!self::command($socket, 'RCPT TO:<' . $recipient . '>', 250)) {
                    return false;
                }
            }
            if (!self::command($socket, 'DATA', 354)) {
                return false;
            }

            $payload = $headers . "\r\n\r\n" . self::stuff($body) . "\r\n.";
            if (!self::command($socket, $payload, 250)) {
                return false;
            }

            self::command($socket, 'QUIT', 221);
            return true;
        } finally {
            @fclose($socket);
        }
    }

    /** Nom annoncé au serveur : le domaine du site, jamais « localhost ». */
    private static function helo(): string
    {
        $host = (string) parse_url((string) Config::get('site.url'), PHP_URL_HOST);
        return $host !== '' ? $host : 'intermittent.fr';
    }

    /** Une ligne commençant par un point serait prise pour la fin du message. */
    private static function stuff(string $body): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", $body);
        $body = str_replace("\n", "\r\n", $body);
        return (string) preg_replace('/^\./m', '..', $body);
    }

    /** @param resource $socket */
    private static function command($socket, string $line, int $expected): bool
    {
        if (@fwrite($socket, $line . "\r\n") === false) {
            self::$lastError = 'Écriture impossible sur la connexion SMTP.';
            return false;
        }
        return self::expect($socket, $expected);
    }

    /** @param resource $socket */
    private static function expect($socket, int $expected): bool
    {
        $reply = '';
        while (($line = @fgets($socket, 1024)) !== false) {
            $reply .= $line;
            // Réponse multiligne : « 250-… » puis « 250 … » sur la dernière.
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }

        if ($reply === '') {
            self::$lastError = 'Le serveur SMTP n’a pas répondu.';
            return false;
        }
        if ((int) substr($reply, 0, 3) !== $expected) {
            self::$lastError = trim($reply);
            return false;
        }
        return true;
    }
}
