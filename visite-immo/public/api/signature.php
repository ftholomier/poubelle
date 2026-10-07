<?php
declare(strict_types=1);

// Retour du service de signature électronique externe (mode « api », voir Paramètres et PASSATION.md).
// Le service appelle cette adresse quand un signataire a signé ou quand le document est complet :
//   POST /api/signature.php    En-tête : Authorization: Bearer <clé du service>
//   { "id": "<id de la demande>", "reference": "<dossier>|<document>", "statut": "signe" | "en_cours" | "refuse",
//     "signataires": [{ "id": "s1", "signe_le": "2026-10-07T10:12:00+02:00" }],
//     "document_signe_base64": "<PDF signé, quand statut = signe>" }

require __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('{"error":"POST attendu"}');
}
$attendu = (string) ($CONFIG['signature_api_cle'] ?? '');
$recu = preg_replace('/^Bearer\s+/i', '', (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
if ($attendu === '' || !hash_equals($attendu, $recu)) {
    http_response_code(401);
    exit('{"error":"Clé invalide"}');
}

$in = json_decode((string) file_get_contents('php://input'), true) ?: [];
[$dossierId, $cle] = array_pad(explode('|', (string) ($in['reference'] ?? ''), 2), 2, '');
$trouve = chercher_dossier($dossierId);
if (!$trouve || empty($trouve[1]['signatures'][$cle]) || ($trouve[1]['signatures'][$cle]['api_id'] ?? '') !== ($in['id'] ?? null)) {
    http_response_code(404);
    exit('{"error":"Demande inconnue"}');
}
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
echo '{"ok":true}';
