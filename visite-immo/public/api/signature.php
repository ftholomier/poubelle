<?php
declare(strict_types=1);

// Retour (webhook) des services de signature électronique. Trois formats acceptés :
//
// 1. firma.dev — en-tête « X-Firma-Signature: t=<horodatage>,v1=<hex> » = HMAC-SHA256 de « <t>.<corps brut> »
//    avec le secret du webhook (Paramètres). Événements traités : signing_request.recipient.signed,
//    signing_request.completed (→ PDF signé téléchargé, dossier mis à jour), .cancelled / .expired / .declined.
//
// 2. BoldSign — en-tête « X-BoldSign-Signature: t=<horodatage>, s0=<hex> » (même logique que le projet Qualiopi) :
//    tout événement autre que « Completed » est acquitté (200) ; « Completed » exige une signature valide.
//
// 3. Contrat générique (mode « api », voir PASSATION.md) :
//   POST /api/signature.php    En-tête : Authorization: Bearer <clé du service>
//   { "id": "<id de la demande>", "reference": "<dossier>|<document>", "statut": "signe" | "en_cours" | "refuse",
//     "signataires": [{ "id": "s1", "signe_le": "2026-10-07T10:12:00+02:00" }],
//     "document_signe_base64": "<PDF signé, quand statut = signe>" }

require __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json');
$brut = (string) file_get_contents('php://input');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || trim($brut) === '') {
    // Test de joignabilité depuis un navigateur ou vérification de l'adresse par le service
    exit('{"ok":true,"message":"Adresse de retour de signature active."}');
}

function repondre(int $code, string $message): never
{
    http_response_code($code);
    if ($code >= 400) journal_signature("webhook refusé ($code) : $message");
    exit(json_encode(['ok' => $code < 400, 'message' => $message], JSON_UNESCAPED_UNICODE));
}

/** Vérifie un en-tête « t=…,v1=… » ou « t=…, s0=… » : HMAC-SHA256 hexadécimal, comparaison à temps constant. */
function signature_webhook_valide(string $entete, string $brut, array $formats): bool
{
    global $CONFIG;
    $secret = (string) ($CONFIG['signature_webhook_secret'] ?? '');
    if ($secret === '' || $entete === '') return false;
    $t = '';
    $recues = [];
    foreach (explode(',', $entete) as $morceau) {
        [$k, $val] = array_pad(explode('=', trim($morceau), 2), 2, '');
        if ($k === 't') $t = $val;
        elseif ($k !== '' && ($k[0] === 'v' || $k[0] === 's')) $recues[] = strtolower($val);
    }
    if ($t !== '' && ctype_digit($t) && abs(time() - (int) $t) > 3600) return false; // rejeu
    foreach ($formats as $f) {
        $calc = hash_hmac('sha256', str_replace(['{t}', '{corps}'], [$t, $brut], $f), $secret);
        foreach ($recues as $r) if ($r !== '' && hash_equals($calc, $r)) return true;
    }
    return false;
}

$in = json_decode($brut, true);
if (!is_array($in)) repondre(400, 'JSON invalide.');

// ---------- firma.dev ----------
if (isset($_SERVER['HTTP_X_FIRMA_SIGNATURE'])) {
    $ok = signature_webhook_valide((string) $_SERVER['HTTP_X_FIRMA_SIGNATURE'], $brut, ['{t}.{corps}'])
        || signature_webhook_valide((string) ($_SERVER['HTTP_X_FIRMA_SIGNATURE_OLD'] ?? ''), $brut, ['{t}.{corps}']);
    if (!$ok) repondre(401, 'Signature firma.dev invalide.');
    $type = (string) ($in['type'] ?? ($_SERVER['HTTP_X_FIRMA_EVENT'] ?? ''));
    $apiId = (string) ($in['data']['signing_request']['id'] ?? ($in['data']['signing_request_id'] ?? ''));
    $trouve = $apiId !== '' ? trouver_signature_externe('firma', $apiId) : null;
    if (!$trouve) repondre(200, 'Demande inconnue ici : ignorée.');
    [$agent, $dossier, $cle] = $trouve;
    if ($type === 'signing_request.completed') {
        cloturer_signature_externe($agent, $dossier, $cle, 'firma', $apiId);
    } elseif ($type === 'signing_request.recipient.signed') {
        foreach ((array) ($in['data']['recipients'] ?? []) as $r) {
            if (!empty($r['signed_at']) && !empty($r['email'])) signataire_externe_signe($agent, $dossier, $cle, (string) $r['email'], (string) $r['signed_at']);
        }
        if (!empty($in['data']['recipient']['email'])) signataire_externe_signe($agent, $dossier, $cle, (string) $in['data']['recipient']['email'], (string) ($in['data']['recipient']['signed_at'] ?? ''));
    } elseif (in_array($type, ['signing_request.cancelled', 'signing_request.expired', 'signing_request.declined', 'signing_request.recipient.declined'], true)) {
        update_visit($agent, $dossier, function (array $v) use ($cle, $type) {
            if (($v['signatures'][$cle]['statut'] ?? '') !== 'en_attente') return $v;
            $v['signatures'][$cle]['statut'] = 'annule';
            journal_ajout($v, 'signature', "{$v['signatures'][$cle]['label']} : " . (str_contains($type, 'expired') ? 'demande expirée' : (str_contains($type, 'declined') ? 'signature refusée' : 'demande annulée')) . ' chez firma.dev.');
            return $v;
        });
    }
    journal_signature("firma : $type reçu pour $apiId.");
    repondre(200, 'Traité.');
}

// ---------- BoldSign (comme le projet Qualiopi) ----------
if (isset($_SERVER['HTTP_X_BOLDSIGN_SIGNATURE']) || isset($in['event']['eventType'])) {
    $type = (string) ($in['event']['eventType'] ?? ($in['eventType'] ?? ''));
    if (strcasecmp($type, 'Completed') !== 0) repondre(200, 'Acquittement.');
    if (!signature_webhook_valide((string) ($_SERVER['HTTP_X_BOLDSIGN_SIGNATURE'] ?? ''), $brut, ['{t}.{corps}', '{t}{corps}', '{corps}'])) repondre(401, 'Signature BoldSign invalide.');
    $chercher = function (array $a) use (&$chercher): ?string {
        foreach ($a as $k => $val) {
            if (is_string($k) && strtolower($k) === 'documentid' && is_scalar($val)) return (string) $val;
            if (is_array($val) && ($r = $chercher($val)) !== null) return $r;
        }
        return null;
    };
    $apiId = $chercher($in) ?? '';
    $trouve = $apiId !== '' ? trouver_signature_externe('boldsign', $apiId) : null;
    if (!$trouve) repondre(200, 'Document inconnu ici : ignoré.');
    cloturer_signature_externe($trouve[0], $trouve[1], $trouve[2], 'boldsign', $apiId);
    journal_signature("boldsign : Completed reçu pour $apiId.");
    repondre(200, 'Traité.');
}

// ---------- Contrat générique ----------
$attendu = cle_api_signature();
$recu = preg_replace('/^Bearer\s+/i', '', (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
if ($attendu === '' || !hash_equals($attendu, (string) $recu)) repondre(401, 'Clé invalide.');

[$dossierId, $cle] = array_pad(explode('|', (string) ($in['reference'] ?? ''), 2), 2, '');
$trouve = chercher_dossier($dossierId);
if (!$trouve || empty($trouve[1]['signatures'][$cle]) || ($trouve[1]['signatures'][$cle]['api_id'] ?? '') !== ($in['id'] ?? null)) repondre(404, 'Demande inconnue.');
[$agent, $v] = $trouve;

$statut = (string) ($in['statut'] ?? '');
if ($statut === 'signe' && !empty($in['document_signe_base64'])) {
    $pdf = base64_decode((string) $in['document_signe_base64'], true);
    if ($pdf && str_starts_with($pdf, '%PDF')) {
        $dir = visit_dir($agent, $dossierId) . '/signatures';
        if (!is_dir($dir)) mkdir($dir, 0770, true);
        file_put_contents("$dir/" . slug($cle) . '-signe.pdf', $pdf);
    }
}
update_visit($agent, $dossierId, function (array $v) use ($cle, $in, $statut) {
    $d = &$v['signatures'][$cle];
    foreach ((array) ($in['signataires'] ?? []) as $r) {
        foreach ($d['signataires'] as &$s) {
            if ($s['id'] === ($r['id'] ?? '') && !empty($r['signe_le']) && !$s['signe_le']) {
                $s['signe_le'] = date('c', strtotime((string) $r['signe_le']) ?: time());
                $s['methode'] = 'Service de signature électronique';
                journal_ajout($v, 'signature', "{$d['label']} signé par {$s['nom']} (service de signature).");
            }
        }
        unset($s);
    }
    if ($statut === 'signe') {
        $d['statut'] = 'signe';
        $d['signe_le'] = date('c');
    } elseif ($statut === 'refuse') {
        $d['statut'] = 'annule';
        journal_ajout($v, 'signature', "{$d['label']} : signature refusée par un signataire.");
    }
    return $v;
});
if ($statut === 'signe') finaliser_signature($agent, $dossierId, $cle);
repondre(200, 'Traité.');
