<?php
// Services de signature électronique externes. Choix dans Paramètres → Signature électronique :
//   - firma.dev  : API REST (create-and-send, document PDF en base64, champs de signature placés en % de la page),
//                  webhook signé « X-Firma-Signature: t=…,v1=… » (HMAC-SHA256 hexadécimal de « t.corps »).
//   - BoldSign   : même intégration que le projet Qualiopi (X-API-KEY, /v1/document/send en multipart, champs en
//                  pixels à 96 dpi, webhook « X-BoldSign-Signature », téléchargement /v1/document/download).
//   - api        : contrat générique décrit dans PASSATION.md.
// Les champs sont placés exactement dans les cadres dessinés par nos PDF (VisitePdf::zoneSignature).

const URL_FIRMA = 'https://api.firma.dev/functions/v1/signing-request-api';
const URL_BOLDSIGN = 'https://api-eu.boldsign.com';
const FICHIER_SIG_EXTERNES = '/signatures_externes.json';
const NOMS_SIGNATURE = ['firma' => 'firma.dev', 'boldsign' => 'BoldSign', 'api' => 'le service de signature'];

function cle_api_signature(): string
{
    global $CONFIG;
    return (string) preg_replace('/\s+/', '', (string) ($CONFIG['signature_api_cle'] ?? ''));
}

function base_signature(string $mode): string
{
    global $CONFIG;
    $perso = trim((string) ($CONFIG['signature_api_url'] ?? ''));
    $defaut = $perso !== '' ? $perso : (['firma' => URL_FIRMA, 'boldsign' => URL_BOLDSIGN][$mode] ?? '');
    return rtrim(api_base($mode === 'api' ? 'signature' : $mode, $defaut), '/');
}

/** Cadre de signature de chaque signataire (k-ième signataire d'un rôle → k-ième cadre de ce rôle). */
function zones_par_signataire(array $signataires, array $zones): array
{
    $vus = [];
    $out = [];
    foreach ($signataires as $s) {
        $rang = $vus[$s['role']] = isset($vus[$s['role']]) ? $vus[$s['role']] + 1 : 0;
        $trouvees = array_values(array_filter($zones, fn ($z) => $z['role'] === $s['role'] && $z['rang'] === $rang));
        $out[$s['id']] = $trouvees ? end($trouvees) : null;
    }
    return $out;
}

function separer_nom(string $nom): array
{
    $p = preg_split('/\s+/', trim($nom), 2);
    return [$p[0] ?? '', $p[1] ?? ''];
}

/** Requête JSON vers un service de signature ; renvoie [code HTTP, corps décodé]. */
function requete_signature(string $methode, string $url, array $entetes, ?string $corps = null, array $multipart = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $methode, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_TIMEOUT => 60, CURLOPT_HTTPHEADER => $entetes]);
    if ($multipart) curl_setopt($ch, CURLOPT_POSTFIELDS, $multipart);
    elseif ($corps !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $corps);
    $brut = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($brut === false) {
        journal_signature("Erreur réseau $methode $url : $err");
        return [0, null, ''];
    }
    if ($code >= 400) journal_signature("HTTP $code $methode $url : " . substr((string) $brut, 0, 600));
    return [$code, json_decode((string) $brut, true), (string) $brut];
}

/** Journal des échanges avec le service (data/logs/signature.log), sans secret. */
function journal_signature(string $m): void
{
    $f = DATA_DIR . '/logs/signature.log';
    if (!is_dir(dirname($f))) @mkdir(dirname($f), 0770, true);
    @file_put_contents($f, '[' . date('Y-m-d H:i:s') . "] $m\n", FILE_APPEND);
}

/**
 * Envoie le document au service choisi. Renvoie ['id' => identifiant de la demande, 'destinataires' => [id signataire => id chez le service]].
 * $zones : cadres repérés pendant le rendu du PDF.
 */
function fournisseur_envoyer(string $mode, string $pdf, array $signataires, array $zones, string $titre, string $reference): array
{
    global $CONFIG;
    $cle = cle_api_signature();
    if ($cle === '') fail(400, 'Clé API du service de signature non renseignée (Paramètres).');
    foreach ($signataires as $s) if (!valid_email($s['email'] ?? '')) fail(400, "E-mail manquant pour le signataire {$s['nom']} : le service de signature l'envoie par e-mail.");
    $parSignataire = zones_par_signataire($signataires, $zones);

    if ($mode === 'firma') {
        $dest = [];
        $champs = [];
        foreach (array_values($signataires) as $i => $s) {
            [$prenom, $nom] = separer_nom($s['nom']);
            $tmp = 'temp_' . ($i + 1);
            $dest[] = ['id' => $tmp, 'first_name' => $prenom, 'last_name' => $nom, 'email' => $s['email'], 'designation' => 'Signer', 'order' => $i + 1] + (!empty($s['telephone']) ? ['phone_number' => $s['telephone']] : []);
            $z = $parSignataire[$s['id']] ?? null;
            if ($z) $champs[] = ['type' => 'signature', 'required' => true, 'recipient_id' => $tmp, 'page_number' => $z['page'],
                'position' => ['x' => round($z['x'] / $z['page_w'] * 100, 2), 'y' => round($z['y'] / $z['page_h'] * 100, 2), 'width' => round($z['w'] / $z['page_w'] * 100, 2), 'height' => round($z['h'] / $z['page_h'] * 100, 2)]];
        }
        $corps = ['name' => mb_substr($titre, 0, 120), 'document' => base64_encode($pdf), 'language' => 'fr', 'expiration_hours' => 24 * 30,
            'recipients' => $dest, 'fields' => $champs,
            'settings' => ['attach_pdf_on_finish' => true, 'allow_download' => true, 'require_otp_verification' => !empty($CONFIG['signature_otp'])]];
        [$code, $r, $brut] = requete_signature('POST', base_signature('firma') . '/signing-requests/create-and-send', ['Authorization: Bearer ' . $cle, 'Content-Type: application/json', 'Accept: application/json'], json_encode($corps, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        if ($code < 200 || $code >= 300 || empty($r['id'])) fail(502, 'firma.dev a refusé la demande' . ($code ? " ($code)" : ' (service injoignable)') . ' : ' . mb_substr((string) ($r['error'] ?? $r['message'] ?? $brut), 0, 200));
        $ids = [];
        foreach (array_values($signataires) as $i => $s) $ids[$s['id']] = $r['recipients'][$i]['id'] ?? null;
        journal_signature("firma : demande {$r['id']} créée ($reference, " . count($dest) . ' signataire(s), ' . count($champs) . ' champ(s)).');
        return ['id' => (string) $r['id'], 'destinataires' => $ids];
    }

    if ($mode === 'boldsign') {
        $tmp = tempnam(sys_get_temp_dir(), 'sig') . '.pdf';
        file_put_contents($tmp, $pdf);
        $champs = ['Title' => mb_substr($titre, 0, 120), 'Message' => 'Merci de signer ce document.', 'Files' => new CURLFile($tmp, 'application/pdf', slug($titre) . '.pdf'),
            'DocumentInfo[0].Locale' => 'FR', 'DocumentInfo[0].Title' => mb_substr($titre, 0, 120)];
        foreach (array_values($signataires) as $i => $s) {
            $z = $parSignataire[$s['id']] ?? ['page' => 1, 'x' => 120, 'y' => 240, 'w' => 60, 'h' => 18];
            $px = fn ($mm) => (int) round($mm * 96 / 25.4); // BoldSign : pixels à 96 dpi, origine en haut à gauche
            $champs += ["Signers[$i].Name" => $s['nom'], "Signers[$i].EmailAddress" => $s['email'], "Signers[$i].SignerType" => 'Signer', "Signers[$i].SignerOrder" => $i + 1, "Signers[$i].Locale" => 'FR',
                "Signers[$i].formFields[0].fieldType" => 'Signature', "Signers[$i].formFields[0].pageNumber" => $z['page'], "Signers[$i].formFields[0].isRequired" => 'true',
                "Signers[$i].formFields[0].bounds.x" => $px($z['x']), "Signers[$i].formFields[0].bounds.y" => $px($z['y']), "Signers[$i].formFields[0].bounds.width" => $px($z['w']), "Signers[$i].formFields[0].bounds.height" => $px($z['h'])];
        }
        [$code, $r, $brut] = requete_signature('POST', base_signature('boldsign') . '/v1/document/send', ['X-API-KEY: ' . $cle, 'Accept: application/json'], null, $champs);
        @unlink($tmp);
        $id = $r['documentId'] ?? ($r['DocumentId'] ?? null);
        if ($code < 200 || $code >= 300 || !$id) fail(502, 'BoldSign a refusé la demande' . ($code ? " ($code)" : '') . ($code === 401 ? ' : clé refusée (vérifiez le centre de données EU/US).' : '.'));
        journal_signature("boldsign : document $id créé ($reference).");
        return ['id' => (string) $id, 'destinataires' => []];
    }

    // Contrat générique
    $r = http_post_json(base_signature('api') . '/demandes', [
        'reference' => $reference, 'titre' => $titre, 'document' => ['nom' => slug($titre) . '.pdf', 'contenu_base64' => base64_encode($pdf)],
        'signataires' => array_map(fn ($s) => array_intersect_key($s, array_flip(['id', 'nom', 'email', 'telephone', 'role'])) + ['zone' => $parSignataire[$s['id']] ?? null], $signataires),
        'url_retour' => url_publique('api/signature.php'),
    ], ['Authorization: Bearer ' . $cle]);
    if (!$r || empty($r['id'])) fail(502, "Le service de signature n'a pas accepté le document.");
    return ['id' => (string) $r['id'], 'destinataires' => []];
}

/** Index demande externe → dossier (pour les webhooks et la synchronisation). */
function indexer_signature_externe(string $mode, string $apiId, array $agent, string $dossier, string $cle): void
{
    update_json(DATA_DIR . FICHIER_SIG_EXTERNES, function (array $m) use ($mode, $apiId, $agent, $dossier, $cle) {
        $m["$mode:$apiId"] = ['agent' => $agent['id'], 'dossier' => $dossier, 'cle' => $cle, 'le' => date('c')];
        return $m;
    });
}

function trouver_signature_externe(string $mode, string $apiId): ?array
{
    $e = read_json(DATA_DIR . FICHIER_SIG_EXTERNES, [])["$mode:$apiId"] ?? null;
    if (!$e) return null;
    $agent = user_by_id($e['agent']);
    return $agent ? [$agent, $e['dossier'], $e['cle']] : null;
}

/** Récupère le PDF signé auprès du service (firma : lien de téléchargement ; BoldSign : /v1/document/download). */
function telecharger_signe(string $mode, string $apiId): ?string
{
    $cle = cle_api_signature();
    if ($mode === 'firma') {
        [$code, $r] = requete_signature('GET', base_signature('firma') . '/signing-requests/' . rawurlencode($apiId), ['Authorization: Bearer ' . $cle, 'Accept: application/json']);
        $url = $r['final_document_download_url'] ?? null;
        if (!$url) return null;
        $pdf = http_get($url, 60, false, ['Accept: application/pdf']);
        return is_string($pdf) && str_starts_with($pdf, '%PDF') ? $pdf : null;
    }
    if ($mode === 'boldsign') {
        [$code, , $brut] = requete_signature('GET', base_signature('boldsign') . '/v1/document/download?documentId=' . rawurlencode($apiId), ['X-API-KEY: ' . $cle]);
        return $code === 200 && str_starts_with($brut, '%PDF') ? $brut : null;
    }
    return null;
}

/** État chez le service : 'signe', 'en_cours', 'annule' ou null (inconnu). */
function etat_externe(string $mode, string $apiId): ?string
{
    $cle = cle_api_signature();
    if ($mode === 'firma') {
        [$code, $r] = requete_signature('GET', base_signature('firma') . '/signing-requests/' . rawurlencode($apiId), ['Authorization: Bearer ' . $cle, 'Accept: application/json']);
        if ($code !== 200 || !is_array($r)) return null;
        $s = $r['status'] ?? [];
        return !empty($s['finished']) ? 'signe' : (!empty($s['cancelled']) || !empty($s['declined']) || !empty($s['expired']) ? 'annule' : 'en_cours');
    }
    if ($mode === 'boldsign') {
        [$code, $r] = requete_signature('GET', base_signature('boldsign') . '/v1/document/properties?documentId=' . rawurlencode($apiId), ['X-API-KEY: ' . $cle, 'Accept: application/json']);
        if ($code !== 200) return null;
        $st = strtolower((string) ($r['status'] ?? ''));
        return $st === 'completed' ? 'signe' : (in_array($st, ['declined', 'revoked', 'expired'], true) ? 'annule' : 'en_cours');
    }
    return null;
}

/** Le service annonce la fin : on enregistre le PDF signé, toutes les signatures, puis la suite (mandat en vente…). */
function cloturer_signature_externe(array $agent, string $dossier, string $cle, string $mode, string $apiId): bool
{
    $v = load_visit($agent, $dossier);
    if (($v['signatures'][$cle]['statut'] ?? '') === 'signe') return false;
    $pdf = telecharger_signe($mode, $apiId);
    if ($pdf) {
        $dir = visit_dir($agent, $dossier) . '/signatures';
        if (!is_dir($dir)) mkdir($dir, 0770, true);
        file_put_contents("$dir/" . slug($cle) . '-signe.pdf', $pdf);
    }
    update_visit($agent, $dossier, function (array $v) use ($cle, $mode, $pdf) {
        $d = &$v['signatures'][$cle];
        foreach ($d['signataires'] as &$s) if (!$s['signe_le']) { $s['signe_le'] = date('c'); $s['methode'] = NOMS_SIGNATURE[$mode] ?? 'Service de signature'; }
        unset($s);
        $d['statut'] = 'signe';
        $d['signe_le'] = date('c');
        journal_ajout($v, 'signature', "{$d['label']} : signé par tous via " . NOMS_SIGNATURE[$mode] . ($pdf ? ', exemplaire signé récupéré.' : ' (exemplaire signé à récupérer).'));
        return $v;
    });
    finaliser_signature($agent, $dossier, $cle);
    return true;
}

/** Un signataire a signé (événement intermédiaire) : on le coche dans le dossier. */
function signataire_externe_signe(array $agent, string $dossier, string $cle, string $email, ?string $quand): void
{
    update_visit($agent, $dossier, function (array $v) use ($cle, $email, $quand) {
        foreach ($v['signatures'][$cle]['signataires'] ?? [] as $i => $s) {
            if (!$s['signe_le'] && strcasecmp($s['email'] ?? '', $email) === 0) {
                $v['signatures'][$cle]['signataires'][$i]['signe_le'] = date('c', strtotime((string) $quand) ?: time());
                $v['signatures'][$cle]['signataires'][$i]['methode'] = NOMS_SIGNATURE[$v['signatures'][$cle]['mode']] ?? 'Service de signature';
                journal_ajout($v, 'signature', "{$v['signatures'][$cle]['label']} signé par {$s['nom']}.");
            }
        }
        return $v;
    });
}

/** Interroge le service pour une demande et applique le résultat. Renvoie l'état ('signe', 'en_cours', 'annule' ou null). */
function synchroniser_signature(array $agent, string $id, string $cle): ?string
{
    $d = load_visit($agent, $id)['signatures'][$cle] ?? null;
    if (!$d || $d['statut'] !== 'en_attente' || !in_array($d['mode'], ['firma', 'boldsign'], true) || empty($d['api_id'])) return $d['statut'] ?? null;
    update_visit($agent, $id, function (array $x) use ($cle) {
        $x['signatures'][$cle]['synchro_le'] = date('c', maintenant());
        return $x;
    });
    $etat = etat_externe($d['mode'], $d['api_id']);
    if ($etat === 'signe') cloturer_signature_externe($agent, $id, $cle, $d['mode'], $d['api_id']);
    if ($etat === 'annule') update_visit($agent, $id, function (array $x) use ($cle) {
        $x['signatures'][$cle]['statut'] = 'annule';
        journal_ajout($x, 'signature', "{$x['signatures'][$cle]['label']} : refusée, expirée ou annulée chez le service de signature.");
        return $x;
    });
    return $etat;
}

route('POST signature_synchro', function ($id) {
    $me = require_user();
    $etat = synchroniser_signature($me, $id, (string) (json_input()['doc'] ?? ''));
    send_json(['etat' => $etat === 'en_attente' ? 'en_cours' : $etat]);
});

// Filet de sécurité : si un webhook n'arrive pas, on interroge le service (au plus toutes les 30 minutes par demande)
tache_cron('synchro_signatures', function (array $agent, array $dossiers): int {
    $n = 0;
    foreach ($dossiers as $v) {
        foreach ($v['signatures'] ?? [] as $cle => $d) {
            if ($d['statut'] !== 'en_attente' || !in_array($d['mode'], ['firma', 'boldsign'], true) || empty($d['api_id'])) continue;
            if (!empty($d['synchro_le']) && maintenant() - strtotime($d['synchro_le']) < 1800) continue;
            if (synchroniser_signature($agent, $v['id'], $cle) === 'signe') $n++;
        }
    }
    return $n;
});

/** Enregistre chez firma.dev l'adresse de retour (webhook) ; mémorise le secret de signature s'il est renvoyé. */
route('POST signature_webhook', function () {
    require_admin();
    $url = url_publique('api/signature.php');
    [$code, $r, $brut] = requete_signature('POST', base_signature('firma') . '/webhooks', ['Authorization: Bearer ' . cle_api_signature(), 'Content-Type: application/json'],
        json_encode(['url' => $url, 'events' => ['signing_request.completed', 'signing_request.recipient.signed', 'signing_request.cancelled', 'signing_request.expired'], 'description' => 'Visite Immo · Synapse']));
    if ($code < 200 || $code >= 300) fail(502, "firma.dev n'a pas accepté le webhook ($code) : " . mb_substr((string) ($r['error'] ?? $brut), 0, 200));
    $secret = $r['signing_secret'] ?? $r['secret'] ?? ($r['webhook']['signing_secret'] ?? null);
    if ($secret) {
        $s = read_json(SETTINGS_FILE, []);
        $s['signature_webhook_secret'] = $secret;
        write_json(SETTINGS_FILE, $s);
    }
    send_json(['ok' => true, 'url' => $url, 'secret_enregistre' => (bool) $secret]);
});
