<?php
// Signature électronique des documents (mandat, bon de visite, offre d'achat…).
//
// Deux modes, au choix dans les Paramètres :
//   - « interne » : signature au doigt sur le téléphone (sur place) ou depuis l'espace client (lien personnel),
//     avec code de vérification envoyé par e-mail, horodatage, adresse IP et empreinte SHA-256 du document.
//     C'est une signature électronique simple (règlement eIDAS) avec fichier de preuve.
//   - « api » : le document part vers votre service de signature (API générique, contrat décrit dans
//     PASSATION.md) ; le service rappelle public/api/signature.php quand tout le monde a signé.
//
// Chaque demande est rangée dans $visit['signatures'][<clé>] ; <clé> = « mandat », « bon:<id> », « offre:<id> »…

/** Documents signables : clé => [libellé, fonction qui construit le PDF non signé, signataires]. Déclarés par les modules. */
$DOCS_SIGNABLES = [];

function document_signable(string $prefixe, string $label, callable $pdf, callable $signataires, ?callable $apres = null): void
{
    global $DOCS_SIGNABLES;
    $DOCS_SIGNABLES[$prefixe] = ['label' => $label, 'pdf' => $pdf, 'signataires' => $signataires, 'apres' => $apres];
}

function signable(string $cle): array
{
    global $DOCS_SIGNABLES;
    $prefixe = explode(':', $cle)[0];
    return $DOCS_SIGNABLES[$prefixe] ?? fail(400, 'Document non signable.');
}

function mode_signature(): string
{
    global $CONFIG;
    return ($CONFIG['signature_mode'] ?? 'interne') === 'api' && !empty($CONFIG['signature_api_url']) ? 'api' : 'interne';
}

/** Crée la demande de signature (empreinte du document, liste des signataires) et l'envoie si demandé. */
function demander_signature(array $agent, string $id, string $cle, bool $envoyer = true): array
{
    global $CONFIG;
    $doc = signable($cle);
    $v = load_visit($agent, $id);
    if (($v['signatures'][$cle]['statut'] ?? '') === 'signe') fail(409, 'Ce document est déjà signé.');
    [$bin] = ($doc['pdf'])($v, $agent, $cle);
    $signataires = [];
    foreach (($doc['signataires'])($v, $agent, $cle) as $i => $s) $signataires[] = $s + ['id' => 's' . ($i + 1), 'signe_le' => null];

    $demande = [
        'cle' => $cle, 'label' => $doc['label'], 'statut' => 'en_attente', 'mode' => mode_signature(),
        'cree_le' => date('c'), 'hash' => hash('sha256', $bin), 'signataires' => $signataires, 'envois' => [],
    ];
    if ($demande['mode'] === 'api') {
        $retour = http_post_json(rtrim((string) $CONFIG['signature_api_url'], '/') . '/demandes', [
            'reference' => "$id|$cle", 'titre' => $doc['label'] . ' · ' . titre_bien($v),
            'document' => ['nom' => slug($doc['label']) . '.pdf', 'contenu_base64' => base64_encode($bin)],
            'signataires' => array_map(fn ($s) => array_intersect_key($s, array_flip(['id', 'nom', 'email', 'telephone', 'role'])), $signataires),
            'url_retour' => url_publique('api/signature.php'),
        ], ['Authorization: Bearer ' . ($CONFIG['signature_api_cle'] ?? '')]);
        if (!$retour || empty($retour['id'])) fail(502, "Le service de signature n'a pas accepté le document.");
        $demande['api_id'] = $retour['id'];
    }
    $v = update_visit($agent, $id, function (array $v) use ($cle, $demande) {
        $v['signatures'][$cle] = $demande;
        journal_ajout($v, 'signature', "{$demande['label']} : demande de signature créée (" . count($demande['signataires']) . ' signataire(s)).');
        return $v;
    });
    if ($envoyer && $demande['mode'] === 'interne') envoyer_liens_signature($agent, $id, $cle);
    return load_visit($agent, $id);
}

/** Envoie à chaque signataire (hors agent) son lien personnel de signature. */
function envoyer_liens_signature(array $agent, string $id, string $cle): int
{
    $v = load_visit($agent, $id);
    $d = $v['signatures'][$cle] ?? null;
    if (!$d) return 0;
    $n = 0;
    foreach ($d['signataires'] as $s) {
        if ($s['signe_le'] || $s['role'] === 'agent' || !valid_email($s['email'] ?? '')) continue;
        $lien = url_publique('espace/?t=' . lien_pour($agent, $id, $s['role'], ['signataire' => $s['id'], 'doc' => $cle]));
        $texte = "Bonjour {$s['nom']},\n\nVoici votre {$d['label']} concernant le bien " . titre_bien($v) . ".\n\nVous pouvez le relire et le signer en ligne, depuis votre téléphone ou votre ordinateur :\n$lien\n\nUn code de vérification vous sera envoyé par e-mail au moment de signer.\n\nBien cordialement,\n" . signature_agent($agent);
        try {
            if (envoyer_mail_agent($agent, $s['email'], "À signer : {$d['label']} · " . titre_bien($v), $texte)) $n++;
        } catch (Throwable $e) {
            error_log('signature : ' . $e->getMessage());
        }
    }
    if ($n) update_visit($agent, $id, function (array $v) use ($cle, $n) {
        $v['signatures'][$cle]['envois'][] = date('c');
        journal_ajout($v, 'signature', "{$v['signatures'][$cle]['label']} : lien de signature envoyé à $n signataire(s).");
        return $v;
    });
    return $n;
}

/** Code à 6 chiffres envoyé par e-mail au signataire (preuve de son identité). */
function envoyer_code_signature(array $agent, string $id, string $cle, string $signataire): bool
{
    $code = (string) random_int(100000, 999999);
    $v = load_visit($agent, $id);
    $s = signataire($v, $cle, $signataire);
    if (!valid_email($s['email'] ?? '') || !email_configure()) return false;
    update_visit($agent, $id, function (array $v) use ($cle, $signataire, $code) {
        foreach ($v['signatures'][$cle]['signataires'] as &$x) {
            if ($x['id'] === $signataire) $x['code'] = ['hash' => password_hash($code, PASSWORD_DEFAULT), 'expire' => time() + 900, 'essais' => 0];
        }
        return $v;
    });
    envoyer_mail_agent($agent, $s['email'], "Votre code de signature : $code", "Bonjour {$s['nom']},\n\nVotre code pour signer « {$v['signatures'][$cle]['label']} » : $code\n\nIl est valable 15 minutes. Si vous n'êtes pas à l'origine de cette demande, ignorez ce message.\n\n" . signature_agent($agent), [], true);
    return true;
}

function signataire(array $v, string $cle, string $id): array
{
    foreach ($v['signatures'][$cle]['signataires'] ?? [] as $s) if ($s['id'] === $id) return $s;
    fail(404, 'Signataire inconnu.');
}

/**
 * Enregistre une signature : image PNG (dessinée au doigt), méthode (sur place / lien), code vérifié, IP.
 * Quand tous ont signé : document signé envoyé à chacun, étape suivante du dossier.
 */
function enregistrer_signature(array $agent, string $id, string $cle, string $signataire, string $image, string $methode, ?string $code = null): array
{
    if (!preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $image, $m)) fail(400, 'Signature illisible.');
    $png = base64_decode($m[1]);
    if (strlen($png) < 300 || strlen($png) > 600000 || !str_starts_with($png, "\x89PNG")) fail(400, 'Signature vide ou invalide.');
    $v = load_visit($agent, $id);
    $d = $v['signatures'][$cle] ?? fail(404, 'Aucune demande de signature pour ce document.');
    if ($d['statut'] !== 'en_attente') fail(409, 'Ce document n\'est plus en attente de signature.');
    $s = signataire($v, $cle, $signataire);
    if ($s['signe_le']) fail(409, 'Déjà signé.');
    $codeOk = false;
    if ($methode === 'lien' && isset($s['code'])) {
        if ($s['code']['expire'] < time() || $s['code']['essais'] >= 5) fail(400, 'Code expiré : demandez-en un nouveau.');
        if (!password_verify((string) $code, $s['code']['hash'])) {
            update_visit($agent, $id, function (array $v) use ($cle, $signataire) {
                foreach ($v['signatures'][$cle]['signataires'] as &$x) if ($x['id'] === $signataire) $x['code']['essais']++;
                return $v;
            });
            fail(400, 'Code incorrect.');
        }
        $codeOk = true;
    }
    $dir = visit_dir($agent, $id) . '/signatures';
    if (!is_dir($dir)) mkdir($dir, 0770, true);
    $fichier = slug($cle) . "-$signataire.png";
    file_put_contents("$dir/$fichier", $png);

    $v = update_visit($agent, $id, function (array $v) use ($cle, $signataire, $fichier, $methode, $codeOk) {
        $d = &$v['signatures'][$cle];
        foreach ($d['signataires'] as &$x) {
            if ($x['id'] !== $signataire) continue;
            $x['signe_le'] = date('c');
            $x['image'] = $fichier;
            $x['methode'] = $methode === 'lien' ? 'Lien personnel' . ($codeOk ? ' + code e-mail' : '') : 'Sur place, téléphone de l\'agent';
            $x['ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
            $x['appareil'] = mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 160);
            unset($x['code']);
            journal_ajout($v, 'signature', "{$d['label']} signé par {$x['nom']} (" . mb_strtolower($x['methode']) . ').');
        }
        unset($x);
        if (!array_filter($d['signataires'], fn ($x) => !$x['signe_le'])) {
            $d['statut'] = 'signe';
            $d['signe_le'] = date('c');
            journal_ajout($v, 'signature', "{$d['label']} : toutes les signatures sont recueillies.");
        }
        return $v;
    });
    if ($v['signatures'][$cle]['statut'] === 'signe') finaliser_signature($agent, $id, $cle);
    return load_visit($agent, $id);
}

/** Tous ont signé : action propre au document (mandat → en vente) et envoi de l'exemplaire signé. */
function finaliser_signature(array $agent, string $id, string $cle): void
{
    $doc = signable($cle);
    if ($doc['apres']) ($doc['apres'])($agent, $id, $cle);
    $v = load_visit($agent, $id);
    $d = $v['signatures'][$cle];
    [$bin, $nom] = pdf_signe($v, $agent, $cle);
    foreach ($d['signataires'] as $s) {
        $to = $s['role'] === 'agent' ? ($agent['email'] ?? '') : ($s['email'] ?? '');
        if (!valid_email($to)) continue;
        try {
            envoyer_mail_agent($agent, $to, "Signé : {$d['label']} · " . titre_bien($v), "Bonjour {$s['nom']},\n\nVous trouverez ci-joint votre exemplaire signé : {$d['label']}.\n\nConservez-le précieusement.\n\n" . signature_agent($agent), [[$nom, $bin, 'application/pdf']]);
        } catch (Throwable $e) {
            error_log('envoi document signé : ' . $e->getMessage());
        }
    }
}

/** PDF du document avec les signatures (si le service externe a renvoyé son PDF signé, c'est celui-là). */
function pdf_signe(array $v, array $agent, string $cle): array
{
    $doc = signable($cle);
    $fichier = visit_dir($agent, $v['id']) . '/signatures/' . slug($cle) . '-signe.pdf';
    if (is_file($fichier)) return [(string) file_get_contents($fichier), slug($doc['label']) . '-signe.pdf'];
    return ($doc['pdf'])($v, $agent, $cle);
}

/** Images des signatures recueillies, pour les dessiner dans les cadres du document. */
function images_signatures(array $v, array $agent, string $cle): array
{
    $out = [];
    foreach ($v['signatures'][$cle]['signataires'] ?? [] as $s) {
        if (!$s['signe_le'] || empty($s['image'])) continue;
        $chemin = visit_dir($agent, $v['id']) . '/signatures/' . $s['image'];
        if (is_file($chemin)) $out[] = $s + ['chemin' => $chemin];
    }
    return $out;
}

/** Dernière page : certificat de signature (preuve). */
function certificat_signature(VisitePdf $pdf, array $v, string $cle): void
{
    $d = $v['signatures'][$cle] ?? null;
    if (!$d || !array_filter($d['signataires'], fn ($s) => $s['signe_le'])) return;
    $pdf->docLabel = 'Certificat de signature';
    $pdf->AddPage();
    $pdf->SetX(19);
    $pdf->tag('Certificat de signature');
    $pdf->SetY($pdf->GetY() + 12);
    $pdf->font('black', 16, C_ENCRE);
    $pdf->MultiCell(0, 7, $d['label'] . ' · ' . titre_bien($v));
    $pdf->Ln(2);
    $pdf->font('', 9.5, C_ENCRE);
    $pdf->MultiCell(0, 5, "Empreinte SHA-256 du document présenté à la signature :\n" . $d['hash']);
    $pdf->Ln(2);
    $pdf->MultiCell(0, 5, 'Demande créée le ' . date('d/m/Y à H:i:s', strtotime($d['cree_le'])) . ($d['statut'] === 'signe' ? ' · signatures complètes le ' . date('d/m/Y à H:i:s', strtotime($d['signe_le'])) : ' · signatures en cours'));
    $pdf->Ln(4);
    foreach ($d['signataires'] as $s) {
        $pdf->ensureSpace(30);
        $y = $pdf->GetY();
        $pdf->SetDrawColor(...C_LIGNE);
        $pdf->Line(18, $y, 192, $y);
        $pdf->SetXY(18, $y + 3);
        $pdf->font('semi', 10.5, C_ENCRE);
        $pdf->Cell(110, 5, $s['nom'] . ' · ' . (['vendeur' => 'Vendeur', 'agent' => 'Agent immobilier', 'acquereur' => 'Acquéreur'][$s['role']] ?? $s['role']), 0, 2);
        $pdf->font('', 8.5, C_GRIS);
        if ($s['signe_le']) {
            $pdf->MultiCell(110, 4.3, 'Signé le ' . date('d/m/Y à H:i:s', strtotime($s['signe_le'])) . "\nMéthode : " . $s['methode'] . "\nAdresse IP : " . ($s['ip'] ?: '—') . "\nAppareil : " . mb_strimwidth($s['appareil'] ?? '', 0, 90, '…'));
            $img = images_signatures($v, user_by_id($v['agent']) ?? ['id' => $v['agent']], $cle);
            foreach ($img as $i) if ($i['id'] === $s['id']) $pdf->Image($i['chemin'], 140, $y + 3, 46, 0, 'PNG');
        } else {
            $pdf->Cell(110, 5, 'En attente de signature', 0, 2);
        }
        $pdf->SetY(max($pdf->GetY(), $y + 26));
    }
    $pdf->Ln(4);
    $pdf->font('I', 8, C_GRIS);
    $pdf->MultiCell(0, 4.2, "Signature électronique simple au sens du règlement (UE) n° 910/2014 (eIDAS) et des articles 1366 et 1367 du code civil. Le présent certificat, les images des signatures et le journal horodaté sont conservés dans le dossier de l'agence.");
}

// ---------- Mandat ----------

document_signable('mandat', 'Mandat de vente',
    pdf: fn (array $v, array $agent) => build_mandat($v, $agent),
    signataires: function (array $v, array $agent): array {
        $s = [['role' => 'vendeur', 'nom' => trim(champ($v, 'prenom_vendeur') . ' ' . champ($v, 'nom_vendeur')), 'email' => champ($v, 'email_vendeur'), 'telephone' => champ($v, 'telephone_vendeur')]];
        if (champ($v, 'nom_vendeur2') !== '') $s[] = ['role' => 'vendeur', 'nom' => trim(champ($v, 'prenom_vendeur2') . ' ' . champ($v, 'nom_vendeur2')), 'email' => champ($v, 'email_vendeur2') ?: champ($v, 'email_vendeur'), 'telephone' => champ($v, 'telephone_vendeur2')];
        $s[] = ['role' => 'agent', 'nom' => $agent['nom'], 'email' => $agent['email'] ?? '', 'telephone' => $agent['telephone'] ?? ''];
        return $s;
    },
    apres: function (array $agent, string $id) {
        update_visit($agent, $id, function (array $v) {
            $v['mandat']['signe_le'] = date('c');
            journal_ajout($v, 'etape', 'Mandat signé : le bien passe « en vente ».');
            return $v;
        });
    },
);

/** Avant la signature, le mandat reçoit son numéro définitif au registre (il doit figurer sur l'exemplaire signé). */
function preparer_mandat_signature(array $agent, string $id): void
{
    $v = load_visit($agent, $id);
    if (empty($v['mandat']['numero'])) inscrire_registre($agent, $id);
}

// ---------- API (agent connecté) ----------

route('POST signature_demande', function ($id) {
    $me = require_user();
    $in = json_input();
    $cle = (string) ($in['doc'] ?? 'mandat');
    if ($cle === 'mandat') preparer_mandat_signature($me, $id);
    $v = load_visit($me, $id);
    if (($v['signatures'][$cle]['statut'] ?? '') !== 'en_attente') $v = demander_signature($me, $id, $cle, !empty($in['envoyer']));
    elseif (!empty($in['envoyer'])) envoyer_liens_signature($me, $id, $cle);
    send_json(vue_dossier(load_visit($me, $id)));
});

route('POST signer', function ($id) {
    $me = require_user();
    $in = json_input();
    $v = enregistrer_signature($me, $id, (string) ($in['doc'] ?? ''), (string) ($in['signataire'] ?? ''), (string) ($in['image'] ?? ''), 'sur_place');
    send_json(vue_dossier($v));
});

route('POST signature_annuler', function ($id) {
    $me = require_user();
    $cle = (string) (json_input()['doc'] ?? '');
    update_visit($me, $id, function (array $v) use ($cle) {
        if (($v['signatures'][$cle]['statut'] ?? '') === 'en_attente') {
            $v['signatures'][$cle]['statut'] = 'annule';
            journal_ajout($v, 'signature', "{$v['signatures'][$cle]['label']} : demande de signature annulée.");
        }
        return $v;
    });
    send_json(vue_dossier(load_visit($me, $id)));
});

// Relance des signataires qui n'ont pas signé (J+2, J+5)
tache_cron('relances_signature', function (array $agent, array $dossiers): int {
    $n = 0;
    foreach ($dossiers as $v) {
        foreach ($v['signatures'] ?? [] as $cle => $d) {
            if ($d['statut'] !== 'en_attente' || $d['mode'] !== 'interne' || empty($d['envois'])) continue;
            $nb = count($d['envois']);
            if ($nb >= 3 || maintenant() < strtotime($d['envois'][0]) + [0, 2, 5][$nb] * 86400) continue;
            if (date('G', maintenant()) < 9 || date('G', maintenant()) >= 19) continue;
            $n += envoyer_liens_signature($agent, $v['id'], $cle);
        }
    }
    return $n;
});

a_faire('signatures', function (array $agent, array $dossiers): array {
    $items = [];
    foreach ($dossiers as $v) {
        foreach ($v['signatures'] ?? [] as $cle => $d) {
            if ($d['statut'] !== 'en_attente') continue;
            $reste = array_filter($d['signataires'], fn ($s) => !$s['signe_le']);
            $agentSeul = count($reste) === 1 && array_values($reste)[0]['role'] === 'agent';
            $items[] = ['type' => 'signature', 'titre' => $agentSeul ? "À vous de signer : {$d['label']}" : "{$d['label']} : en attente de " . implode(', ', array_column($reste, 'nom')),
                'detail' => titre_bien($v), 'lien' => "#/visite/{$v['id']}/signature/" . rawurlencode($cle), 'priorite' => $agentSeul ? 1 : 2, 'date' => $d['cree_le']];
        }
    }
    return $items;
});
