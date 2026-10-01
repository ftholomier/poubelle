<?php
// Envoi d'e-mails avec pièces jointes, en PHP natif :
//   - par un serveur SMTP (recommandé : Gmail, OVH, o2switch, Brevo…), en SSL ou STARTTLS
//   - ou par la fonction mail() de l'hébergeur
// Réglages dans l'appli : Paramètres → Envoi des e-mails.

function email_configure(): bool
{
    global $CONFIG;
    $methode = $CONFIG['email_methode'] ?? '';
    return ($methode === 'smtp' && !empty($CONFIG['smtp_host']) && !empty($CONFIG['email_expediteur']))
        || ($methode === 'mail' && !empty($CONFIG['email_expediteur']));
}

function valid_email(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

/** Encode un en-tête (nom, objet) en UTF-8, sans retour à la ligne possible. */
function mime_header(string $texte): string
{
    $texte = str_replace(["\r", "\n"], ' ', $texte);
    return preg_match('/[^\x20-\x7E]/', $texte) ? '=?UTF-8?B?' . base64_encode($texte) . '?=' : $texte;
}

function mime_adresse(string $email, string $nom = ''): string
{
    return $nom !== '' ? mime_header($nom) . " <$email>" : $email;
}

/**
 * Envoie un e-mail.
 * $m = [to, cc (liste), bcc (liste), reply_to, sujet, texte, html, pieces => [[nom, contenu, type]], images => [cid => [chemin, type]]]
 */
function send_email(array $m): void
{
    global $CONFIG;
    if (!email_configure()) throw new RuntimeException("L'envoi d'e-mails n'est pas configuré (Paramètres → Envoi des e-mails).");

    $from = (string) $CONFIG['email_expediteur'];
    $fromNom = (string) ($CONFIG['email_expediteur_nom'] ?? $CONFIG['agence']);
    foreach (array_merge([$m['to']], $m['cc'] ?? [], $m['bcc'] ?? []) as $a) {
        if (!valid_email($a)) throw new RuntimeException("Adresse e-mail invalide : $a");
    }

    $domaine = substr(strrchr($from, '@'), 1) ?: 'localhost';
    $headers = [
        'Date: ' . date('r'),
        'From: ' . mime_adresse($from, $fromNom),
        'Message-ID: <' . bin2hex(random_bytes(12)) . "@$domaine>",
        'MIME-Version: 1.0',
    ];
    if (!empty($m['reply_to']) && valid_email($m['reply_to'])) $headers[] = 'Reply-To: ' . $m['reply_to'];
    if (!empty($m['cc'])) $headers[] = 'Cc: ' . implode(', ', $m['cc']);

    [$typeCorps, $corps] = mime_body($m);
    $headers[] = $typeCorps;

    if (($CONFIG['email_methode'] ?? '') === 'mail') {
        $ok = mail($m['to'], mime_header($m['sujet']), $corps, implode("\r\n", $headers), '-f' . $from);
        if (!$ok) throw new RuntimeException("La fonction mail() de l'hébergeur a refusé l'envoi.");
        return;
    }

    $message = implode("\r\n", array_merge($headers, ['To: ' . $m['to'], 'Subject: ' . mime_header($m['sujet'])])) . "\r\n\r\n" . $corps;
    smtp_send($from, array_merge([$m['to']], $m['cc'] ?? [], $m['bcc'] ?? []), $message);
}

/** Corps MIME : texte + HTML (avec images intégrées) + pièces jointes. Renvoie [en-tête Content-Type, corps]. */
function mime_body(array $m): array
{
    $b = fn () => '=_' . bin2hex(random_bytes(10));
    $part = fn (string $type, string $contenu, array $extra = []) =>
        implode("\r\n", array_merge(["Content-Type: $type", 'Content-Transfer-Encoding: base64'], $extra))
        . "\r\n\r\n" . rtrim(chunk_split(base64_encode($contenu), 76, "\r\n")) . "\r\n";
    $multipart = function (string $sousType, array $parts) use ($b): array {
        $bound = $b();
        $corps = '';
        foreach ($parts as $p) $corps .= "--$bound\r\n$p\r\n";
        return ["Content-Type: multipart/$sousType; boundary=\"$bound\"", $corps . "--$bound--\r\n"];
    };

    // texte + HTML
    [$tAlt, $cAlt] = $multipart('alternative', [
        $part('text/plain; charset=UTF-8', $m['texte']),
        $part('text/html; charset=UTF-8', $m['html'] ?? nl2br(htmlspecialchars($m['texte']))),
    ]);
    $bloc = "$tAlt\r\n\r\n$cAlt";

    // images intégrées au HTML (logo)
    if (!empty($m['images'])) {
        $parts = [$bloc];
        foreach ($m['images'] as $cid => [$chemin, $type]) {
            $parts[] = $part($type, (string) file_get_contents($chemin), ["Content-ID: <$cid>", 'Content-Disposition: inline']);
        }
        [$tRel, $cRel] = $multipart('related', $parts);
        $bloc = "$tRel\r\n\r\n$cRel";
    }

    if (empty($m['pieces'])) {
        [$type, $corps] = explode("\r\n\r\n", $bloc, 2);
        return [$type, $corps];
    }
    $parts = [$bloc];
    foreach ($m['pieces'] as [$nom, $contenu, $type]) {
        $nomEnc = mime_header($nom);
        $parts[] = $part("$type; name=\"$nomEnc\"", $contenu, ["Content-Disposition: attachment; filename=\"$nomEnc\""]);
    }
    return $multipart('mixed', $parts);
}

// ---------- Client SMTP ----------

function smtp_send(string $from, array $destinataires, string $message): void
{
    global $CONFIG;
    $host = trim((string) $CONFIG['smtp_host']);
    $port = (int) ($CONFIG['smtp_port'] ?: 587);
    $securite = $CONFIG['smtp_securite'] ?? 'tls';

    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
    $fp = @stream_socket_client(($securite === 'ssl' ? 'ssl://' : 'tcp://') . "$host:$port", $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) throw new RuntimeException("Connexion au serveur SMTP $host:$port impossible ($errstr).");
    stream_set_timeout($fp, 30);

    $lire = function () use ($fp): array {
        $texte = '';
        while (($l = fgets($fp, 1024)) !== false) {
            $texte .= $l;
            if (strlen($l) < 4 || $l[3] !== '-') break; // dernière ligne d'une réponse multi-lignes
        }
        return [(int) substr($texte, 0, 3), trim($texte)];
    };
    $cmd = function (string $c, array $attendus, string $masque = '') use ($fp, $lire): string {
        fwrite($fp, $c . "\r\n");
        [$code, $rep] = $lire();
        if (!in_array($code, $attendus, true)) {
            if ($masque !== '' || $code === 535) throw new RuntimeException('Identifiant ou mot de passe SMTP refusé par le serveur.');
            throw new RuntimeException('Le serveur SMTP a refusé « ' . strtok($c, ' ') . ' » : ' . ($rep ?: 'pas de réponse'));
        }
        return $rep;
    };

    try {
        [$code, $rep] = $lire();
        if ($code !== 220) throw new RuntimeException("Serveur SMTP indisponible : $rep");
        $ehlo = 'EHLO ' . (gethostname() ?: 'localhost');
        $capacites = $cmd($ehlo, [250]);
        if ($securite === 'tls') {
            $cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                throw new RuntimeException('Échec du chiffrement TLS avec le serveur SMTP.');
            }
            $capacites = $cmd($ehlo, [250]);
        }
        if (!empty($CONFIG['smtp_user'])) {
            if (stripos($capacites, 'PLAIN') !== false) {
                $cmd('AUTH PLAIN ' . base64_encode("\0" . $CONFIG['smtp_user'] . "\0" . $CONFIG['smtp_pass']), [235], 'identifiants');
            } else {
                $cmd('AUTH LOGIN', [334]);
                $cmd(base64_encode($CONFIG['smtp_user']), [334], 'identifiant');
                $cmd(base64_encode((string) $CONFIG['smtp_pass']), [235], 'mot de passe');
            }
        }
        $cmd("MAIL FROM:<$from>", [250]);
        foreach (array_unique($destinataires) as $d) $cmd("RCPT TO:<$d>", [250, 251]);
        $cmd('DATA', [354]);
        // Une ligne commençant par « . » doit être doublée (dot-stuffing)
        $data = preg_replace('/^\./m', '..', str_replace(["\r\n", "\r"], "\n", $message));
        fwrite($fp, str_replace("\n", "\r\n", $data) . "\r\n.\r\n");
        [$code, $rep] = $lire();
        if ($code !== 250) throw new RuntimeException("Le serveur SMTP a refusé le message : $rep");
        fwrite($fp, "QUIT\r\n");
    } finally {
        fclose($fp);
    }
}

// ---------- Mise en forme ----------

/** E-mail HTML à la charte Synapse (fond crème, carte à contour noir, bandeau noir), avec le logo intégré. */
function email_html(string $texte, array $agent): array
{
    global $CONFIG;
    $agence = htmlspecialchars((string) $CONFIG['agence']);
    $logo = logo_path();
    $images = [];
    $entete = "<div style=\"font:900 26px Arial,Helvetica,sans-serif;letter-spacing:-1px;color:#111114\">$agence</div>";
    if ($logo && is_file($logo)) {
        $images['logo@visite-immo'] = [$logo, str_ends_with($logo, '.png') ? 'image/png' : 'image/jpeg'];
        $entete = "<img src=\"cid:logo@visite-immo\" alt=\"$agence\" height=\"52\" style=\"height:52px;max-width:240px;display:block\">";
    }
    $corps = nl2br(htmlspecialchars($texte));
    $coord = nl2br(htmlspecialchars(trim((string) ($CONFIG['agence_coordonnees'] ?? ''))));
    $mono = "font-family:'JetBrains Mono',Menlo,Consolas,'Courier New',monospace;letter-spacing:1px;text-transform:uppercase";
    $html = <<<HTML
<!doctype html><html lang="fr"><body style="margin:0;background:#f4f1ea;padding:28px 12px;font-family:Arial,Helvetica,sans-serif">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td align="center">
<table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;width:100%">
<tr><td style="padding:0 4px 18px">$entete</td></tr>
<tr><td style="background:#fffdf8;border:1.5px solid #111114;border-radius:12px;padding:28px;font-size:15px;line-height:1.65;color:#111114">$corps</td></tr>
<tr><td style="padding:18px 4px 6px;font-size:11px;line-height:1.6;color:#6d6b64;$mono"><strong style="color:#111114">$agence</strong><br>$coord</td></tr>
<tr><td style="padding-top:10px"><div style="background:#111114;color:#f4f1ea;border-radius:10px;padding:10px 14px;font-size:10px;$mono;text-align:center">
$agence <span style="color:#d4f22e">&#9733;</span> On connecte l'immo <span style="color:#d4f22e">&#9733;</span> On ne raconte pas de salades</div></td></tr>
</table></td></tr></table></body></html>
HTML;
    return [$html, $images];
}
