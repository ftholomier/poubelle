<?php
// Offres d'achat : dictées par l'agent, signées par l'acquéreur (sur place ou par lien), transmises
// automatiquement au vendeur qui accepte (signature), refuse ou fait une contre-proposition depuis son espace.

const STATUTS_OFFRE = ['redigee' => 'À signer par l\'acquéreur', 'transmise' => 'Transmise au vendeur', 'acceptee' => 'Acceptée', 'refusee' => 'Refusée', 'contre_offre' => 'Contre-proposition', 'non_retenue' => 'Non retenue'];

function offre(array $v, string $oid): array
{
    foreach ($v['offres'] ?? [] as $o) if ($o['id'] === $oid) return $o;
    fail(404, 'Offre introuvable.');
}

function maj_offre(array $agent, string $id, string $oid, callable $fn): array
{
    return update_visit($agent, $id, function (array $v) use ($oid, $fn) {
        foreach ($v['offres'] as $i => $o) if ($o['id'] === $oid) $v['offres'][$i] = $fn($o, $v);
        return $v;
    });
}

type_dictee('offre',
    s_obj([
        'acquereur_nom' => s_txt(), 'montant' => s_num(), 'financement' => s_enum(['pret', 'comptant', 'mixte']), 'apport' => s_num(),
        'pret_montant' => s_num(), 'pret_duree_ans' => s_num(), 'pret_taux_max' => s_num(), 'validite_jours' => s_num(),
        'date_compromis_souhaitee' => s_txt(), 'autres_conditions' => s_txt(), 'notes' => s_txt(),
    ], ['montant', 'financement']),
    "L'agent dicte une offre d'achat qu'un acquéreur fait pour le bien. Extrais le montant proposé (net vendeur ou honoraires inclus tel que dit), le mode de financement, l'apport, les caractéristiques du prêt (condition suspensive : montant, durée en années, taux maximum), la durée de validité de l'offre en jours (10 par défaut), la date souhaitée pour le compromis, les autres conditions.",
    fn (string $t) => ['acquereur_nom' => 'Julien Moreau', 'montant' => 398000, 'financement' => 'pret', 'apport' => 90000, 'pret_montant' => 320000, 'pret_duree_ans' => 25, 'pret_taux_max' => 3.9, 'validite_jours' => 10, 'date_compromis_souhaitee' => '', 'autres_conditions' => '', 'notes' => 'Offre ferme, prêt déjà validé en principe.'],
    fn (array $agent, array $in) => !empty($in['dossier']) ? 'Bien : ' . titre_bien(load_visit($agent, (string) $in['dossier'])) . ' · prix affiché ' . (champ(load_visit($agent, (string) $in['dossier']), 'mandat_prix') ?: '?') . ' €' : '',
);

apres_dictee('offre', function (array $agent, array $d, array $in) {
    $id = (string) ($in['dossier'] ?? '');
    $acq = !empty($in['acquereur']) ? collection_trouver($agent, 'acquereurs', (string) $in['acquereur']) : null;
    if (!$acq && !empty($d['acquereur_nom'])) {
        foreach (acquereurs($agent) as $a) if (mb_strtolower(nom_acquereur($a)) === mb_strtolower(trim($d['acquereur_nom']))) $acq = $a;
    }
    $o = [
        'id' => nouvel_id('of'), 'date' => date('c'), 'statut' => 'redigee',
        'acquereur' => $acq['id'] ?? null, 'nom' => $acq ? nom_acquereur($acq) : (trim((string) ($d['acquereur_nom'] ?? '')) ?: 'Acquéreur'),
        'email' => $acq['email'] ?? '', 'telephone' => $acq['telephone'] ?? '',
        'montant' => (int) $d['montant'], 'financement' => $d['financement'], 'apport' => (int) ($d['apport'] ?? 0),
        'pret' => ['montant' => (int) ($d['pret_montant'] ?? 0), 'duree' => (int) ($d['pret_duree_ans'] ?? 0), 'taux' => (float) ($d['pret_taux_max'] ?? 0)],
        'validite' => date('c', strtotime('+' . max(1, (int) ($d['validite_jours'] ?? 10)) . ' days')),
        'date_compromis' => $d['date_compromis_souhaitee'] ?? '', 'conditions' => $d['autres_conditions'] ?? '', 'notes' => $d['notes'] ?? '',
    ];
    $v = update_visit($agent, $id, function (array $v) use ($o) {
        $v['offres'][] = $o;
        journal_ajout($v, 'offre', "Offre d'achat de {$o['nom']} : " . number_format($o['montant'], 0, ',', ' ') . ' €.');
        return $v;
    });
    if ($acq) collection_enregistrer($agent, 'acquereurs', ['statut' => 'offre'] + $acq);
    return ['offre' => $o];
});

// ---------- PDF de l'offre ----------

function rendre_offre(VisitePdf $pdf, array $v, array $agent, array $options = []): void
{
    global $CONFIG;
    $cle = (string) ($options['cle'] ?? '');
    $oid = explode(':', $cle)[1] ?? '';
    $o = offre($v, $oid);
    $f = fn ($n) => fmt_nombre((string) $n) . "\u{00A0}€";
    $pdf->docLabel = "Offre d'achat";
    $pdf->AddPage();
    $pdf->titleBlock("Offre d'achat", titre_bien($v), 'Faite le ' . fmt_date_fr($o['date']) . ' · valable jusqu\'au ' . fmt_date_fr($o['validite']));
    $pdf->statBoxes(array_filter([
        ['label' => 'Prix proposé', 'valeur' => $f($o['montant']), 'sombre' => true, 'detail' => champ($v, 'mandat_honoraires_charge') === "L'acquéreur" ? 'honoraires inclus' : ''],
        ['label' => 'Financement', 'valeur' => ['pret' => 'Prêt', 'comptant' => 'Comptant', 'mixte' => 'Apport + prêt'][$o['financement']] ?? $o['financement']],
        $o['apport'] ? ['label' => 'Apport', 'valeur' => $f($o['apport'])] : null,
    ]));
    $vendeurs = trim(champ($v, 'civilite_vendeur') . ' ' . champ($v, 'prenom_vendeur') . ' ' . champ($v, 'nom_vendeur'));
    $pdf->richText("Je soussigné(e) {$o['nom']}, après avoir visité le bien désigné ci-dessus par l'intermédiaire de l'agence {$CONFIG['agence']} (mandat n° " . ($v['mandat']['numero'] ?? '—') . "), déclare faire une offre ferme d'acquisition au prix de " . number_format($o['montant'], 0, ',', ' ') . " euros (" . euros_en_lettres($o['montant']) . ")" . (champ($v, 'mandat_honoraires_charge') === "L'acquéreur" ? ', honoraires d\'agence inclus' : '') . ", à l'attention de $vendeurs.", 10.5);
    $cond = [];
    if ($o['financement'] !== 'comptant' && $o['pret']['montant']) $cond[] = "- Obtention d'un ou plusieurs prêts d'un montant total maximum de " . number_format($o['pret']['montant'], 0, ',', ' ') . " €" . ($o['pret']['duree'] ? " sur {$o['pret']['duree']} ans" : '') . ($o['pret']['taux'] ? ', au taux maximum de ' . str_replace('.', ',', (string) $o['pret']['taux']) . ' % hors assurance' : '') . ' (articles L313-40 et suivants du code de la consommation).';
    if ($o['financement'] === 'comptant') $cond[] = "- Je déclare ne pas recourir à un prêt pour financer cette acquisition.";
    if ($o['conditions']) $cond[] = '- ' . $o['conditions'];
    if ($cond) { $pdf->sectionTitle('Conditions'); $pdf->richText(implode("\n", $cond), 10.5); }
    $pdf->richText("Cette offre est valable jusqu'au " . fmt_date_fr($o['validite']) . ". En cas d'acceptation, un avant-contrat (compromis ou promesse de vente) sera signé chez le notaire" . ($o['date_compromis'] ? ", si possible le {$o['date_compromis']}" : '') . ". Conformément à l'article 1589-1 du code civil, aucun versement ne peut être exigé ni accepté à l'occasion de cette offre. Le délai de rétractation de dix jours (article L271-1 du code de la construction et de l'habitation) courra à compter de la notification de l'avant-contrat.", 10);
    // Cadres de signature : acquéreur, puis acceptation du vendeur
    $pdf->Ln(3);
    $pdf->ensureSpace(44);
    $y = $pdf->GetY();
    $imgOffre = images_signatures($v, $agent, "offre:$oid");
    $imgAcc = images_signatures($v, $agent, "acceptation:$oid");
    foreach ([[18, "L'ACQUÉREUR", 'Bon pour offre', $imgOffre, 'acquereur'], [108, 'LE VENDEUR', 'Bon pour acceptation', $imgAcc, 'vendeur']] as [$x, $titre, $aide, $imgs, $role]) {
        $pdf->SetDrawColor(...C_ENCRE);
        $pdf->SetFillColor(...C_PAPIER);
        $pdf->roundedRect($x, $y, 84, 38, 2.5, 'FD');
        $pdf->SetXY($x + 5, $y + 4);
        $pdf->font('mono', 7, C_VERT);
        $pdf->Cell(74, 4, $titre, 0, 2);
        $pdf->font('I', 8.5, C_GRIS);
        $pdf->Cell(74, 4.5, $aide, 0, 2);
        $nbRole = $role === 'vendeur' && champ($v, 'nom_vendeur2') !== '' ? 2 : 1;
        for ($r = 0; $r < $nbRole; $r++) $pdf->zoneSignature($role, $r, $x + 4 + $r * 39, $y + 13, $nbRole > 1 ? 37 : 74, 20);
        $k = 0;
        foreach ($imgs as $i) if ($i['role'] === $role) {
            [$iw, $ih] = @getimagesize($i['chemin']) ?: [3, 1];
            $pdf->Image($i['chemin'], $x + 5 + $k * 39, $y + 14, min(36, 15 * $iw / max(1, $ih)), 0, 'PNG');
            $k++;
        }
    }
    $pdf->SetY($y + 44);
    if (($o['statut'] ?? '') === 'acceptee') {
        $pdf->font('semi', 10, C_VERT);
        $pdf->Cell(0, 6, 'Offre acceptée le ' . fmt_date_fr($o['acceptee_le'] ?? null) . '.', 0, 1);
    }
    certificat_signature($pdf, $v, "offre:$oid");
    if (isset($v['signatures']["acceptation:$oid"])) certificat_signature($pdf, $v, "acceptation:$oid");
}

pdf_module('offre', "Offre d'achat", 'rendre_offre', null, false);

function pdf_offre(array $v, array $agent, string $cle): array
{
    $pdf = pdf_nouveau("Offre d'achat");
    rendre_offre($pdf, $v, $agent, ['cle' => 'offre:' . explode(':', $cle)[1]]);
    return [$pdf->Output('S'), 'Offre-achat-' . slug(titre_bien($v), 40) . '.pdf'];
}

document_signable('offre', "Offre d'achat", 'pdf_offre',
    signataires: function (array $v, array $agent, string $cle): array {
        $o = offre($v, explode(':', $cle)[1]);
        return [['role' => 'acquereur', 'nom' => $o['nom'], 'email' => $o['email'], 'telephone' => $o['telephone']]];
    },
    apres: function (array $agent, string $id, string $cle) {
        // Signée par l'acquéreur : transmise automatiquement au vendeur, qui accepte depuis son espace
        $oid = explode(':', $cle)[1];
        maj_offre($agent, $id, $oid, fn ($o) => ['statut' => 'transmise', 'transmise_le' => date('c')] + $o);
        demander_signature($agent, $id, "acceptation:$oid", false);
        $v = load_visit($agent, $id);
        $o = offre($v, $oid);
        $email = champ($v, 'email_vendeur');
        if (valid_email($email)) {
            $lien = url_publique('espace/?t=' . lien_pour($agent, $id, 'vendeur'));
            try {
                envoyer_mail_agent($agent, $email, "Offre d'achat reçue · " . titre_bien($v),
                    "Bonjour " . trim(champ($v, 'civilite_vendeur') . ' ' . champ($v, 'nom_vendeur')) . ",\n\nJ'ai le plaisir de vous transmettre une offre d'achat pour votre bien : " . number_format($o['montant'], 0, ',', ' ') . " €"
                    . ($o['financement'] === 'comptant' ? ', sans recours au crédit' : ', avec un prêt de ' . number_format($o['pret']['montant'], 0, ',', ' ') . ' €') . ".\nElle est valable jusqu'au " . fmt_date_fr($o['validite']) . ".\n\nVous pouvez la consulter, l'accepter (signature en ligne) ou me faire part d'une contre-proposition depuis votre espace :\n$lien\n\nJe vous appelle pour en parler.\n\n" . signature_agent($agent),
                    [array_reverse(pdf_offre($v, $agent, $cle)) + [2 => 'application/pdf']]);
            } catch (Throwable $e) {
                error_log('offre : ' . $e->getMessage());
            }
        }
        journaliser($agent, $id, 'offre', "Offre de {$o['nom']} signée et transmise au vendeur.");
    },
);

document_signable('acceptation', "Acceptation de l'offre", 'pdf_offre',
    signataires: function (array $v, array $agent, string $cle): array {
        $s = [['role' => 'vendeur', 'nom' => trim(champ($v, 'prenom_vendeur') . ' ' . champ($v, 'nom_vendeur')), 'email' => champ($v, 'email_vendeur')]];
        if (champ($v, 'nom_vendeur2') !== '') $s[] = ['role' => 'vendeur', 'nom' => trim(champ($v, 'prenom_vendeur2') . ' ' . champ($v, 'nom_vendeur2')), 'email' => champ($v, 'email_vendeur2') ?: champ($v, 'email_vendeur')];
        return $s;
    },
    apres: function (array $agent, string $id, string $cle) {
        $oid = explode(':', $cle)[1];
        $v = update_visit($agent, $id, function (array $v) use ($oid) {
            foreach ($v['offres'] as &$o) {
                if ($o['id'] === $oid) { $o['statut'] = 'acceptee'; $o['acceptee_le'] = date('c'); $acceptee = $o; }
                elseif (in_array($o['statut'], ['redigee', 'transmise', 'contre_offre'], true)) $o['statut'] = 'non_retenue';
            }
            unset($o);
            $v['vente'] = ($v['vente'] ?? []) + ['offre' => $oid, 'acquereur' => $acceptee['acquereur'], 'acquereur_nom' => $acceptee['nom'], 'prix' => $acceptee['montant'], 'debut' => date('c')];
            journal_ajout($v, 'etape', "Offre de {$acceptee['nom']} acceptée par le vendeur : " . number_format($acceptee['montant'], 0, ',', ' ') . ' €. Le dossier passe en préparation du compromis.');
            return $v;
        });
        $o = offre($v, $oid);
        if ($o['email'] && valid_email($o['email'])) {
            try {
                envoyer_mail_agent($agent, $o['email'], 'Votre offre est acceptée · ' . titre_bien($v), "Bonjour {$o['nom']},\n\nBonne nouvelle : le vendeur a accepté votre offre de " . number_format($o['montant'], 0, ',', ' ') . " €.\nJe vous recontacte pour organiser la signature du compromis chez le notaire. Pensez à me transmettre les coordonnées de votre notaire et de votre courtier.\n\n" . signature_agent($agent));
            } catch (Throwable) {
            }
        }
        if (function_exists('notifier')) notifier($agent, 'Offre acceptée 🎉', $o['nom'] . ' · ' . titre_bien($v), "#/visite/$id/vente");
    },
);

// ---------- Espace vendeur : les offres ----------

espace_section('vendeur', 12, function (array $ctx): string {
    $h = '';
    foreach (array_reverse($ctx['v']['offres'] ?? []) as $o) {
        if (!in_array($o['statut'], ['transmise', 'contre_offre', 'acceptee', 'refusee'], true)) continue;
        $etat = ['transmise' => 'À étudier', 'contre_offre' => 'Contre-proposition envoyée', 'acceptee' => 'Acceptée', 'refusee' => 'Refusée'][$o['statut']];
        $h .= '<section class="es-card' . ($o['statut'] === 'transmise' ? ' es-signer-offre' : '') . '"><span class="es-tag">Offre d\'achat · ' . e($etat) . '</span>'
            . '<h2 style="font:800 1.6rem Archivo,sans-serif;color:var(--encre);text-transform:none;margin:14px 0 4px">' . number_format($o['montant'], 0, ',', ' ') . ' €</h2>'
            . '<p>' . e($o['financement'] === 'comptant' ? 'Sans recours au crédit' : 'Avec un prêt de ' . number_format($o['pret']['montant'], 0, ',', ' ') . ' €') . ' · valable jusqu\'au ' . e(date('d/m/Y', strtotime($o['validite']))) . '</p>'
            . '<a class="es-btn" target="_blank" href="?t=' . e($ctx['token']) . '&pdf=' . e('offre:' . $o['id']) . '">📄 Lire l\'offre</a>';
        if ($o['statut'] === 'transmise') {
            $h .= '<p class="es-aide" style="margin-top:12px">Pour accepter, signez ci-dessous. Vous pouvez aussi refuser ou proposer un autre prix.</p>'
                . '<div class="es-choix"><button type="button" class="es-btn" data-offre-refus="' . e($o['id']) . '">Refuser</button><button type="button" class="es-btn" data-offre-contre="' . e($o['id']) . '">Contre-proposer</button></div>';
        }
        $h .= '</section>';
    }
    return $h;
});

espace_action_module('offre', function (array $agent, array $v, array $lien, array $in): never {
    if ($lien['role'] !== 'vendeur') fail(403, 'Non autorisé.');
    $oid = (string) ($in['offre'] ?? '');
    $o = offre($v, $oid);
    if ($o['statut'] !== 'transmise') fail(409, 'Cette offre a déjà reçu une réponse.');
    $choix = (string) ($in['choix'] ?? '');
    $prix = (int) preg_replace('/\D/', '', (string) ($in['prix'] ?? ''));
    if (!in_array($choix, ['refus', 'contre'], true) || ($choix === 'contre' && !$prix)) fail(400, 'Réponse incomplète.');
    maj_offre($agent, $v['id'], $oid, fn ($o) => ['statut' => $choix === 'refus' ? 'refusee' : 'contre_offre', 'reponse' => ['date' => date('c'), 'prix' => $prix ?: null, 'message' => mb_substr((string) ($in['message'] ?? ''), 0, 500)]] + $o);
    update_visit($agent, $v['id'], function (array $x) use ($oid) {
        if (($x['signatures']["acceptation:$oid"]['statut'] ?? '') === 'en_attente') $x['signatures']["acceptation:$oid"]['statut'] = 'annule';
        return $x;
    });
    $txt = $choix === 'refus' ? "Le vendeur a refusé l'offre de {$o['nom']}." : "Le vendeur propose " . number_format($prix, 0, ',', ' ') . " € à {$o['nom']}.";
    journaliser($agent, $v['id'], 'offre', $txt);
    try {
        if (valid_email($agent['email'] ?? '')) envoyer_mail_agent($agent, $agent['email'], "Réponse du vendeur · " . titre_bien($v), $txt . (($in['message'] ?? '') ? "\nMessage : {$in['message']}" : ''));
    } catch (Throwable) {
    }
    if (function_exists('notifier')) notifier($agent, 'Réponse du vendeur', $txt, "#/visite/{$v['id']}/vente");
    send_json(['ok' => true]);
});

route('POST offre', function ($id) {
    $me = require_user();
    $in = json_input();
    $oid = (string) ($in['offre'] ?? '');
    if (($in['action'] ?? '') === 'supprimer') {
        update_visit($me, $id, function (array $v) use ($oid) {
            $v['offres'] = array_values(array_filter($v['offres'] ?? [], fn ($o) => $o['id'] !== $oid || $o['statut'] === 'acceptee'));
            return $v;
        });
    } elseif (($in['action'] ?? '') === 'acceptee_hors_ligne') {
        // Acceptation signée sur papier : l'agent l'enregistre
        $v = load_visit($me, $id);
        if (!isset($v['signatures']["acceptation:$oid"])) demander_signature($me, $id, "acceptation:$oid", false);
        update_visit($me, $id, function (array $v) use ($oid) {
            foreach ($v['signatures']["acceptation:$oid"]['signataires'] as &$s) if (!$s['signe_le']) { $s['signe_le'] = date('c'); $s['methode'] = 'Acceptation sur papier, enregistrée par l\'agent'; }
            $v['signatures']["acceptation:$oid"]['statut'] = 'signe';
            return $v;
        });
        finaliser_signature($me, $id, "acceptation:$oid");
    }
    send_json(vue_dossier(load_visit($me, $id)));
});

a_faire('offres', function (array $agent, array $dossiers): array {
    $items = [];
    foreach ($dossiers as $v) foreach ($v['offres'] ?? [] as $o) {
        if ($o['statut'] === 'redigee') $items[] = ['type' => 'signature', 'titre' => "Faire signer l'offre de {$o['nom']}", 'detail' => titre_bien($v), 'lien' => "#/visite/{$v['id']}/vente", 'priorite' => 1, 'date' => $o['date']];
        if (in_array($o['statut'], ['refusee', 'contre_offre'], true) && strtotime($o['reponse']['date'] ?? '') > maintenant() - 3 * 86400) $items[] = ['type' => 'contact', 'titre' => ($o['statut'] === 'refusee' ? 'Offre refusée : ' : 'Contre-proposition à transmettre : ') . $o['nom'], 'detail' => titre_bien($v) . ($o['reponse']['prix'] ? ' · ' . number_format($o['reponse']['prix'], 0, ',', ' ') . ' €' : ''), 'lien' => "#/visite/{$v['id']}/vente", 'priorite' => 1, 'date' => $o['reponse']['date']];
    }
    return $items;
});
