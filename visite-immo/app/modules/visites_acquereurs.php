<?php
// Visites des acquéreurs : bon de visite signé sur le téléphone, retour dicté en 30 secondes après la visite,
// et chaque vendredi un compte rendu de commercialisation envoyé automatiquement au vendeur.

function visite_acq(array $v, string $vaId): array
{
    foreach ($v['visites_acq'] ?? [] as $va) if ($va['id'] === $vaId) return $va;
    fail(404, 'Visite introuvable.');
}

// ---------- Bon de visite ----------

function rendre_bon_visite(VisitePdf $pdf, array $v, array $agent, array $options = []): void
{
    global $CONFIG;
    $cle = (string) ($options['cle'] ?? '');
    $va = visite_acq($v, explode(':', $cle)[1] ?? '');
    $a = !empty($va['acquereur']) ? collection_trouver($agent, 'acquereurs', $va['acquereur']) : null;
    $pdf->docLabel = 'Bon de visite';
    $pdf->AddPage();
    $pdf->titleBlock('Bon de visite', titre_bien($v), 'Visite du ' . fmt_date_fr($va['date']) . ' à ' . date('H\hi', strtotime($va['date'])));
    $prix = champ($v, 'mandat_prix') ?: champ($v, 'prix_souhaite');
    $pdf->kvGrid(array_values(array_filter([
        ['label' => 'Visiteur', 'valeur' => $a ? nom_acquereur($a) : $va['nom'], 'long' => false],
        $a && !empty($a['telephone']) ? ['label' => 'Téléphone', 'valeur' => $a['telephone'], 'long' => false] : null,
        ['label' => 'Bien visité', 'valeur' => trim(champ($v, 'type_bien') . ' · ' . titre_bien($v), ' ·'), 'long' => false],
        $prix ? ['label' => 'Prix de présentation', 'valeur' => fmt_nombre($prix) . " € honoraires inclus", 'long' => false] : null,
        !empty($v['mandat']['numero']) ? ['label' => 'Mandat', 'valeur' => 'N° ' . $v['mandat']['numero'] . ' · ' . mb_strtolower(champ($v, 'mandat_type')), 'long' => false] : null,
        ['label' => 'Agence', 'valeur' => ($CONFIG['raison_sociale'] ?? '') ?: $CONFIG['agence'], 'long' => false],
    ])));
    $pdf->sectionTitle('Engagement du visiteur');
    $pdf->richText("Je soussigné(e) reconnais avoir visité ce jour le bien désigné ci-dessus par l'intermédiaire de l'agence {$CONFIG['agence']}, représentée par {$agent['nom']}, qui me l'a présenté.\n\nJe m'engage, si je souhaite acquérir ce bien, directement ou par personne interposée, à ne traiter que par l'intermédiaire de l'agence, pendant la durée du mandat et de ses éventuels renouvellements, et au plus tard pendant les douze mois suivant son expiration.\n\nLes informations recueillies servent uniquement au suivi de ma recherche ; je peux y accéder et les faire rectifier ou supprimer auprès de l'agence.", 10);
    $pdf->Ln(3);
    $images = images_signatures($v, $agent, $cle);
    $y = $pdf->GetY();
    $pdf->ensureSpace(42);
    $y = $pdf->GetY();
    foreach ([[18, 'LE VISITEUR', 'acquereur'], [108, "L'AGENT", 'agent']] as [$x, $titre, $role]) {
        $pdf->SetDrawColor(...C_ENCRE);
        $pdf->SetFillColor(...C_PAPIER);
        $pdf->roundedRect($x, $y, 84, 34, 2.5, 'FD');
        $pdf->SetXY($x + 5, $y + 4);
        $pdf->font('mono', 7, C_VERT);
        $pdf->Cell(74, 4, $titre);
        $pdf->zoneSignature($role, 0, $x + 4, $y + 9, 74, 20);
        foreach ($images as $i) if ($i['role'] === $role) {
            [$iw, $ih] = @getimagesize($i['chemin']) ?: [3, 1];
            $pdf->Image($i['chemin'], $x + 5, $y + 10, min(70, 16 * $iw / max(1, $ih)), 0, 'PNG');
            $pdf->SetXY($x + 5, $y + 28);
            $pdf->font('mono', 5.6, C_GRIS);
            $pdf->Cell(74, 3, 'SIGNÉ LE ' . date('d/m/Y H:i', strtotime($i['signe_le'])));
        }
    }
    $pdf->SetY($y + 40);
    certificat_signature($pdf, $v, $cle);
}

pdf_module('bon', 'Bon de visite', 'rendre_bon_visite', null, false);
document_signable('bon', 'Bon de visite',
    pdf: function (array $v, array $agent, string $cle) {
        $pdf = pdf_nouveau('Bon de visite');
        rendre_bon_visite($pdf, $v, $agent, ['cle' => $cle]);
        return [$pdf->Output('S'), 'Bon-de-visite-' . slug(titre_bien($v), 40) . '.pdf'];
    },
    signataires: function (array $v, array $agent, string $cle): array {
        $va = visite_acq($v, explode(':', $cle)[1]);
        $a = !empty($va['acquereur']) ? collection_trouver($agent, 'acquereurs', $va['acquereur']) : null;
        return [['role' => 'acquereur', 'nom' => $a ? nom_acquereur($a) : $va['nom'], 'email' => $a['email'] ?? '', 'telephone' => $a['telephone'] ?? ''],
            ['role' => 'agent', 'nom' => $agent['nom'], 'email' => $agent['email'] ?? '']];
    },
    apres: function (array $agent, string $id, string $cle) {
        update_visit($agent, $id, function (array $v) use ($cle) {
            foreach ($v['visites_acq'] as &$va) if ('bon:' . $va['id'] === $cle) $va['bon_signe'] = true;
            return $v;
        });
    },
);

// ---------- Retour de visite dicté ----------

type_dictee('retour_visite',
    s_obj(['interet' => s_num(), 'positifs' => s_liste(s_txt()), 'negatifs' => s_liste(s_txt()), 'prix_percu' => s_txt(), 'suite' => s_enum(['offre_probable', 'deuxieme_visite', 'reflexion', 'pas_interesse']), 'resume_vendeur' => s_txt(), 'notes_internes' => s_txt()],
        ['interet', 'positifs', 'negatifs', 'suite', 'resume_vendeur']),
    "L'agent dicte le retour d'une visite qu'il vient de faire avec un acquéreur. Extrais l'intérêt de 1 (aucun) à 5 (très fort), les points positifs et négatifs relevés par l'acquéreur, son avis sur le prix, la suite probable, un résumé de 2 phrases destiné au vendeur (ton factuel et bienveillant, sans nom ni information personnelle sur l'acquéreur) et des notes internes (budget, objections réelles, stratégie).",
    fn (string $t) => ['interet' => 4, 'positifs' => ['Jardin et exposition', 'Chambre au rez-de-chaussée', 'Garage double'], 'negatifs' => ['Cuisine à refaire'], 'prix_percu' => 'Un peu élevé vu la cuisine',
        'suite' => 'deuxieme_visite', 'resume_vendeur' => "Visiteurs séduits par le jardin et les volumes ; la cuisine à rafraîchir est le principal frein. Ils souhaitent revenir avec un artisan pour chiffrer les travaux.", 'notes_internes' => 'Budget 430 k€, prêt accordé. Viser une offre autour de 400 k€.'],
    fn (array $agent, array $in) => !empty($in['dossier']) ? 'Bien : ' . titre_bien(load_visit($agent, (string) $in['dossier'])) : '',
);

apres_dictee('retour_visite', function (array $agent, array $d, array $in, string $transcription) {
    $id = (string) ($in['dossier'] ?? '');
    $vaId = (string) ($in['visite'] ?? '');
    $v = update_visit($agent, $id, function (array $v) use ($vaId, $d, $transcription) {
        foreach ($v['visites_acq'] as &$va) {
            if ($va['id'] !== $vaId) continue;
            $va['retour'] = $d + ['dicte_le' => date('c'), 'transcription' => $transcription];
            $va['statut'] = 'faite';
            journal_ajout($v, 'visite', "Retour de visite ({$va['nom']}) : intérêt {$d['interet']}/5 · " . $d['resume_vendeur']);
        }
        return $v;
    });
    $va = visite_acq($v, $vaId);
    if (!empty($va['acquereur']) && ($a = collection_trouver($agent, 'acquereurs', $va['acquereur']))) {
        $a['historique'][] = ['date' => date('c'), 'texte' => 'Visite de ' . titre_bien($v) . " : intérêt {$d['interet']}/5. " . ($d['notes_internes'] ?? '')];
        if ($d['suite'] === 'pas_interesse') $a['historique'][] = ['date' => date('c'), 'texte' => 'Pas intéressé : ' . implode(', ', $d['negatifs'])];
        collection_enregistrer($agent, 'acquereurs', $a);
    }
    return ['visite' => $va];
});

// ---------- Compte rendu de commercialisation hebdomadaire ----------

function stats_semaine(array $v, int $depuis): array
{
    $visites = array_values(array_filter($v['visites_acq'] ?? [], fn ($va) => strtotime($va['date']) >= $depuis && strtotime($va['date']) <= maintenant()));
    $contacts = array_values(array_filter($v['contacts'] ?? [], fn ($c) => strtotime($c['date']) >= $depuis));
    $vues = 0;
    foreach ($v['vitrine']['vues'] ?? [] as $jour => $n) if (strtotime($jour) >= $depuis - 86400) $vues += $n;
    return ['visites' => $visites, 'contacts' => $contacts, 'vues' => $vues];
}

function rediger_cr_hebdo(array $v, array $agent, int $depuis): string
{
    global $CONFIG;
    $s = stats_semaine($v, $depuis);
    $civ = trim(champ($v, 'civilite_vendeur') . ' ' . champ($v, 'nom_vendeur'));
    $retours = array_filter(array_map(fn ($va) => $va['retour']['resume_vendeur'] ?? null, $s['visites']));
    $donnees = "Bien : " . titre_bien($v) . "\nSemaine : " . date('d/m', $depuis) . ' au ' . date('d/m', maintenant())
        . "\nConsultations de l'annonce : {$s['vues']}\nDemandes de contact : " . count($s['contacts']) . "\nVisites : " . count($s['visites'])
        . "\nRetours des visiteurs :\n" . ($retours ? implode("\n", array_map(fn ($r) => "- $r", $retours)) : '(aucun retour cette semaine)')
        . "\nPrix affiché : " . (champ($v, 'mandat_prix') ?: champ($v, 'prix_souhaite')) . " €\nEn vente depuis : " . (!empty($v['mandat']['signe_le']) ? fmt_date_fr($v['mandat']['signe_le']) : '—');
    if (empty($CONFIG['gemini_api_key'])) {
        $t = "Bonjour" . ($civ ? " $civ" : '') . ",\n\nVoici le point de la semaine sur la vente de votre bien.\n\n"
            . "CETTE SEMAINE\n- {$s['vues']} consultation(s) de l'annonce\n- " . count($s['contacts']) . " demande(s) de contact\n- " . count($s['visites']) . " visite(s)\n\n";
        if ($retours) $t .= "RETOURS DES VISITEURS\n" . implode("\n", array_map(fn ($r) => "- $r", $retours)) . "\n\n";
        $t .= "PROCHAINE ÉTAPE\n- " . (count($s['visites']) ? 'Nous relançons les visiteurs intéressés et organisons les contre-visites.' : "Nous renforçons la diffusion de l'annonce et relançons les acquéreurs de notre fichier.") . "\n\nJe reste à votre disposition pour en parler.\n\n" . signature_agent($agent);
        return $t;
    }
    try {
        return trim(gemini_generate($CONFIG['modele_analyse'], [
            'systemInstruction' => ['parts' => [['text' => "Rédige le compte rendu hebdomadaire de commercialisation envoyé au vendeur par {$agent['nom']} ({$CONFIG['agence']}). Commence par « Bonjour" . ($civ ? " $civ" : '') . " », vouvoiement, ton transparent, positif et professionnel. Sections en majuscules : CETTE SEMAINE (chiffres), RETOURS DES VISITEURS (anonymes), NOTRE ANALYSE (si les retours convergent sur le prix ou un défaut, dis-le avec tact et propose une piste), PROCHAINE ÉTAPE. Texte brut sans markdown. Termine par la signature : " . str_replace("\n", ', ', signature_agent($agent))]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $donnees]]]],
        ], 60));
    } catch (Throwable) {
        return $donnees;
    }
}

function rendre_cr_hebdo(VisitePdf $pdf, array $v, array $agent, array $options = []): void
{
    $liste = $v['cr_hebdo'] ?? [];
    if (!$liste) throw new RuntimeException('Aucun compte rendu.');
    $cr = end($liste);
    if (isset($options['n']) && isset($v['cr_hebdo'][(int) $options['n']])) $cr = $v['cr_hebdo'][(int) $options['n']];
    $pdf->docLabel = 'Compte rendu de commercialisation';
    $pdf->AddPage();
    $pdf->titleBlock('Point de la semaine', titre_bien($v), 'Semaine du ' . fmt_date_fr($cr['du']) . ' au ' . fmt_date_fr($cr['au']));
    $pdf->statBoxes([
        ['label' => 'Consultations', 'valeur' => (string) $cr['vues'], 'sombre' => true],
        ['label' => 'Contacts', 'valeur' => (string) $cr['contacts']],
        ['label' => 'Visites', 'valeur' => (string) $cr['visites']],
    ]);
    $pdf->richText($cr['texte']);
}

pdf_module('cr_hebdo', 'Compte rendu de commercialisation', 'rendre_cr_hebdo', null, false);

/** Prépare (et envoie si l'option est active) le compte rendu de la semaine pour un dossier en vente. */
function compte_rendu_hebdo(array $agent, string $id, bool $envoyer): array
{
    $v = load_visit($agent, $id);
    $depuis = maintenant() - 7 * 86400;
    $s = stats_semaine($v, $depuis);
    $cr = ['du' => date('c', $depuis), 'au' => date('c', maintenant()), 'texte' => rediger_cr_hebdo($v, $agent, $depuis), 'vues' => $s['vues'], 'contacts' => count($s['contacts']), 'visites' => count($s['visites']), 'envoye_le' => null, 'cree_le' => date('c', maintenant())];
    flush_usage($agent, $id, 'analyse');
    $v = update_visit($agent, $id, function (array $v) use ($cr) {
        $v['cr_hebdo'][] = $cr;
        return $v;
    });
    if ($envoyer) envoyer_cr_hebdo($agent, $id, count($v['cr_hebdo']) - 1);
    return load_visit($agent, $id);
}

function envoyer_cr_hebdo(array $agent, string $id, int $n): void
{
    $v = load_visit($agent, $id);
    $email = champ($v, 'email_vendeur');
    $cr = $v['cr_hebdo'][$n] ?? fail(404, 'Compte rendu introuvable.');
    if (!valid_email($email)) fail(400, "L'e-mail du vendeur n'est pas renseigné.");
    $pdf = pdf_nouveau('Compte rendu de commercialisation');
    rendre_cr_hebdo($pdf, $v, $agent, ['n' => $n]);
    $lien = url_publique('espace/?t=' . lien_pour($agent, $id, 'vendeur'));
    envoyer_mail_agent($agent, $email, 'Le point de la semaine · ' . titre_bien($v), $cr['texte'] . "\n\nVotre espace : $lien", [['Point-de-la-semaine.pdf', $pdf->Output('S'), 'application/pdf']], true);
    update_visit($agent, $id, function (array $v) use ($n) {
        $v['cr_hebdo'][$n]['envoye_le'] = date('c');
        journal_ajout($v, 'envoi', 'Compte rendu de la semaine envoyé au vendeur.');
        return $v;
    });
}

// Chaque vendredi à partir de 17 h : compte rendu pour chaque bien en vente (envoyé ou à relire selon le réglage de l'agent)
tache_cron('cr_hebdo', function (array $agent, array $dossiers): int {
    if (date('N', maintenant()) != 5 || date('G', maintenant()) < 17) return 0;
    $n = 0;
    foreach ($dossiers as $v) {
        if (!in_array(etape_dossier($v), ['en_vente', 'offre'], true)) continue;
        $liste = $v['cr_hebdo'] ?? [];
        $dernier = $liste ? end($liste) : null;
        if ($dernier && maintenant() - strtotime($dernier['cree_le']) < 5 * 86400) continue;
        try {
            compte_rendu_hebdo($agent, $v['id'], ($agent['cr_auto'] ?? true) && valid_email(champ($v, 'email_vendeur')) && email_configure());
            $n++;
        } catch (Throwable $e) {
            error_log('cr hebdo : ' . $e->getMessage());
        }
    }
    return $n;
});

a_faire('visites_acquereurs', function (array $agent, array $dossiers): array {
    $items = [];
    foreach ($dossiers as $v) {
        foreach ($v['visites_acq'] ?? [] as $va) {
            if (($va['statut'] ?? '') === 'prevue' && strtotime($va['date']) < maintenant() - 1800) {
                $items[] = ['type' => 'tache', 'titre' => "Dicter le retour de visite de {$va['nom']}", 'detail' => titre_bien($v) . ' · 30 secondes, le vendeur sera informé vendredi', 'lien' => "#/visite/{$v['id']}/vente", 'priorite' => 1, 'date' => $va['date']];
            }
        }
        foreach ($v['cr_hebdo'] ?? [] as $cr) {
            if (empty($cr['envoye_le']) && strtotime($cr['cree_le']) > maintenant() - 7 * 86400) $items[] = ['type' => 'relance', 'titre' => 'Compte rendu de la semaine à relire et envoyer', 'detail' => titre_bien($v), 'lien' => "#/visite/{$v['id']}/vente", 'priorite' => 2, 'date' => $cr['cree_le']];
        }
    }
    return $items;
});

// ---------- Espace vendeur : les visites et leurs retours ----------

espace_section('vendeur', 25, function (array $ctx): string {
    $v = $ctx['v'];
    $faites = array_filter($v['visites_acq'] ?? [], fn ($va) => !empty($va['retour']));
    $prevues = array_filter($v['visites_acq'] ?? [], fn ($va) => ($va['statut'] ?? '') === 'prevue' && strtotime($va['date']) > time());
    if (!$faites && !$prevues && empty($v['cr_hebdo'])) return '';
    $h = '<section class="es-card"><h2>Les visites de votre bien</h2>';
    foreach ($prevues as $va) $h .= '<div class="es-ligne"><strong>📅 ' . e(libelle_creneau($va['date'])) . '</strong><span class="es-aide">Visite prévue</span></div>';
    foreach (array_reverse($faites) as $va) $h .= '<div class="es-ligne"><strong>' . e(date('d/m', strtotime($va['date']))) . ' · intérêt ' . str_repeat('●', (int) $va['retour']['interet']) . str_repeat('○', 5 - (int) $va['retour']['interet']) . '</strong><span class="es-aide">' . e($va['retour']['resume_vendeur']) . '</span></div>';
    foreach (array_reverse($v['cr_hebdo'] ?? [], true) as $n => $cr) if (!empty($cr['envoye_le'])) $h .= '<a class="es-doc" target="_blank" href="?t=' . e($ctx['token']) . '&pdf=cr_hebdo&n=' . $n . '"><span>📄</span>Point de la semaine du ' . e(date('d/m', strtotime($cr['au']))) . '<span class="es-fl">→</span></a>';
    return $h . '</section>';
});

// ---------- API ----------

route('POST cr_hebdo', function ($id) {
    $me = require_user();
    $in = json_input();
    if (isset($in['envoyer'])) envoyer_cr_hebdo($me, $id, (int) $in['envoyer']);
    else compte_rendu_hebdo($me, $id, false);
    send_json(vue_dossier(load_visit($me, $id)));
});

route('POST visite_acq', function ($id) {
    // Visite non planifiée (acquéreur de passage) : création immédiate pour signer le bon
    $me = require_user();
    $in = json_input();
    $r = rdv_enregistrer($me, ['type' => 'visite', 'dossier' => $id, 'acquereur' => $in['acquereur'] ?? null, 'titre' => mb_substr((string) ($in['nom'] ?? 'Visiteur'), 0, 80), 'debut' => date('c'), 'duree' => 45]);
    send_json(vue_dossier(load_visit($me, $id)) + ['rdv' => $r]);
});
