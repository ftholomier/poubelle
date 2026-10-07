<?php
// Acte signé : facture d'honoraires numérotée, note de commission de l'agent (paliers de l'offre Synapse :
// 80 % dès le premier euro, 90 % à partir de 40 000 €, 95 % à partir de 80 000 € d'honoraires HT sur 12 mois
// glissants, réglables dans les Paramètres), fin de diffusion, puis demande d'avis Google au client.

function paliers(): array
{
    global $CONFIG;
    return [
        [0, (float) ($CONFIG['taux_palier1'] ?? 80)],
        [(float) ($CONFIG['seuil_palier2'] ?? 40000), (float) ($CONFIG['taux_palier2'] ?? 90)],
        [(float) ($CONFIG['seuil_palier3'] ?? 80000), (float) ($CONFIG['taux_palier3'] ?? 95)],
    ];
}

/** Honoraires HT encaissés par l'agent sur les 12 mois précédant une date (registre des factures). */
function ca_12_mois(string $agentId, ?int $avant = null): float
{
    $avant ??= maintenant();
    $total = 0.0;
    foreach (read_json(DATA_DIR . '/factures.json', [])['factures'] ?? [] as $f) {
        $t = strtotime($f['date']);
        if ($f['agent'] === $agentId && $t < $avant && $t >= strtotime('-12 months', $avant)) $total += $f['ht'];
    }
    return $total;
}

function taux_agent(float $ca): array
{
    $taux = 0;
    $palier = 1;
    foreach (paliers() as $i => [$seuil, $t]) if ($ca >= $seuil) { $taux = $t; $palier = $i + 1; }
    $suivant = paliers()[$palier] ?? null;
    return ['taux' => $taux, 'palier' => $palier, 'prochain_seuil' => $suivant[0] ?? null, 'prochain_taux' => $suivant[1] ?? null];
}

function montant_honoraires_ttc(array $v): float
{
    $h = champ($v, 'mandat_honoraires');
    $prix = (float) ($v['vente']['prix'] ?? 0) ?: (float) champ($v, 'mandat_prix');
    if (str_contains($h, '%')) {
        $pct = (float) str_replace(',', '.', preg_replace('/[^\d,.]/', '', $h));
        return round(champ($v, 'mandat_honoraires_charge') === 'Le vendeur' ? $prix * $pct / 100 : $prix - $prix / (1 + $pct / 100), 2);
    }
    return (float) preg_replace('/[^\d.]/', '', str_replace(',', '.', $h));
}

/** Enregistre l'acte : numéro de facture définitif, commission, fin de diffusion. */
function enregistrer_acte(array $agent, string $id, string $date): array
{
    $v = load_visit($agent, $id);
    if (!empty($v['vente']['acte_le'])) fail(409, 'Acte déjà enregistré.');
    if (empty($v['vente']['debut'])) fail(400, 'Aucune offre acceptée sur ce dossier.');
    $ttc = montant_honoraires_ttc($v);
    if ($ttc <= 0) fail(400, 'Honoraires du mandat introuvables.');
    $ht = round($ttc / 1.2, 2);
    $quand = strtotime($date) ?: maintenant();
    $commission = taux_agent(ca_12_mois($agent['id'], $quand));
    $numero = null;
    update_json(DATA_DIR . '/factures.json', function (array $r) use ($agent, $id, $ht, $ttc, $quand, &$numero) {
        $an = date('Y', $quand);
        $r['compteurs'][$an] = ($r['compteurs'][$an] ?? 0) + 1;
        $numero = sprintf('F-%s-%04d', $an, $r['compteurs'][$an]);
        $r['factures'][] = ['numero' => $numero, 'date' => date('c', $quand), 'agent' => $agent['id'], 'dossier' => $id, 'ht' => $ht, 'ttc' => $ttc];
        return $r;
    });
    $v = update_visit($agent, $id, function (array $v) use ($date, $numero, $ht, $ttc, $commission) {
        $v['vente']['acte_le'] = date_iso($date) ?: date('Y-m-d');
        $v['vente']['facture'] = ['numero' => $numero, 'date' => date('c'), 'ht' => $ht, 'tva' => round($ttc - $ht, 2), 'ttc' => $ttc];
        $v['vente']['commission'] = $commission + ['base_ht' => $ht, 'montant' => round($ht * $commission['taux'] / 100, 2)];
        if (!empty($v['vitrine']['publiee'])) $v['vitrine']['publiee'] = false;
        journal_ajout($v, 'etape', "Acte signé 🎉 Facture $numero (" . number_format($ttc, 2, ',', ' ') . " € TTC), commission agent " . $commission['taux'] . ' %. Annonce retirée.');
        return $v;
    });
    if (!empty($v['vente']['acquereur']) && ($a = collection_trouver($agent, 'acquereurs', $v['vente']['acquereur']))) collection_enregistrer($agent, 'acquereurs', ['statut' => 'achete'] + $a);
    return $v;
}

function rendre_facture(VisitePdf $pdf, array $v, array $agent): void
{
    global $CONFIG;
    $f = $v['vente']['facture'] ?? throw new RuntimeException('Pas encore de facture.');
    $eur = fn ($n) => number_format((float) $n, 2, ',', "\u{00A0}") . "\u{00A0}€";
    $pdf->docLabel = 'Facture ' . $f['numero'];
    $pdf->AddPage();
    $parVendeur = champ($v, 'mandat_honoraires_charge') === 'Le vendeur';
    $acq = !empty($v['vente']['acquereur']) ? collection_trouver($agent, 'acquereurs', $v['vente']['acquereur']) : null;
    $client = $parVendeur ? trim(champ($v, 'civilite_vendeur') . ' ' . champ($v, 'prenom_vendeur') . ' ' . champ($v, 'nom_vendeur')) : ($acq ? nom_acquereur($acq) : ($v['vente']['acquereur_nom'] ?? ''));
    $pdf->titleBlock('Facture', 'Facture n° ' . $f['numero'], 'Émise le ' . fmt_date_fr($f['date']) . ' · ' . (($CONFIG['raison_sociale'] ?? '') ?: $CONFIG['agence']) . (($CONFIG['siret'] ?? '') ? ' · SIRET ' . $CONFIG['siret'] : ''));
    $pdf->kvGrid([
        ['label' => 'Client', 'valeur' => $client ?: '—', 'long' => false],
        ['label' => 'Objet', 'valeur' => 'Honoraires de transaction · ' . titre_bien($v), 'long' => false],
        ['label' => 'Mandat', 'valeur' => 'N° ' . ($v['mandat']['numero'] ?? '—'), 'long' => false],
        ['label' => 'Acte authentique', 'valeur' => 'Signé le ' . date_jj($v['vente']['acte_le']), 'long' => false],
    ]);
    $pdf->statBoxes([
        ['label' => 'Montant HT', 'valeur' => $eur($f['ht'])],
        ['label' => 'TVA 20 %', 'valeur' => $eur($f['tva'])],
        ['label' => 'Total TTC', 'valeur' => $eur($f['ttc']), 'sombre' => true],
    ]);
    $pdf->font('I', 8.5, C_GRIS);
    $pdf->MultiCell(0, 4.5, "Honoraires dus à la signature de l'acte authentique (loi n° 70-9 du 2 janvier 1970, article 6). Paiement à réception, par virement ou par le notaire chargé de la vente. "
        . "En cas de retard : pénalités au taux légal majoré de 10 points et indemnité forfaitaire de 40 € pour frais de recouvrement (articles L441-10 et D441-5 du code de commerce)."
        . (($CONFIG['carte_numero'] ?? '') ? "\nCarte professionnelle n° {$CONFIG['carte_numero']}" . (($CONFIG['carte_delivree_par'] ?? '') ? " délivrée par {$CONFIG['carte_delivree_par']}" : '') . '.' : '')
        . (($CONFIG['garant'] ?? '') ? " Garantie financière : {$CONFIG['garant']}." : ''));
}

function rendre_commission(VisitePdf $pdf, array $v, array $agent): void
{
    $c = $v['vente']['commission'] ?? throw new RuntimeException('Pas encore de commission.');
    $eur = fn ($n) => number_format((float) $n, 2, ',', "\u{00A0}") . "\u{00A0}€";
    $pdf->docLabel = 'Note de commission';
    $pdf->AddPage();
    $pdf->titleBlock('Note de commission', $agent['nom'], 'Vente de ' . titre_bien($v) . ' · facture ' . $v['vente']['facture']['numero'] . ' · acte du ' . date_jj($v['vente']['acte_le']));
    $pdf->statBoxes([
        ['label' => 'Honoraires HT', 'valeur' => $eur($c['base_ht'])],
        ['label' => 'Votre part', 'valeur' => $c['taux'] . ' %', 'detail' => 'palier ' . $c['palier']],
        ['label' => 'À facturer', 'valeur' => $eur($c['montant']), 'sombre' => true, 'detail' => 'HT'],
    ]);
    $ca = ca_12_mois($agent['id']);
    $pdf->richText("Calcul : votre chiffre d'affaires sur les 12 mois glissants précédant cette vente détermine votre palier, qui s'applique à toute la vente.\n"
        . implode("\n", array_map(fn ($p, $i) => '- Palier ' . ($i + 1) . ' : ' . $p[1] . ' % ' . ($p[0] ? 'à partir de ' . number_format($p[0], 0, ',', ' ') . ' € HT' : 'dès le premier euro'), paliers(), array_keys(paliers())))
        . "\n\nChiffre d'affaires sur 12 mois, cette vente comprise : " . number_format($ca, 0, ',', ' ') . " € HT."
        . ($c['prochain_seuil'] ? "\nProchain palier (" . $c['prochain_taux'] . ' %) à partir de ' . number_format($c['prochain_seuil'], 0, ',', ' ') . ' € HT.' : "\nVous êtes au palier maximum.")
        . "\n\nÀ facturer par l'agent commercial (statut RSAC) au réseau, à réception du règlement des honoraires par le notaire.");
}

pdf_module('facture', "Facture d'honoraires", 'rendre_facture', null, false);
pdf_module('commission', 'Note de commission', 'rendre_commission', null, false);
document_module(fn (array $v) => !empty($v['vente']['facture']) ? [['facture', "Facture d'honoraires", true, true, 'vente'], ['commission', 'Note de commission', true, true, 'vente']] : []);
document_module(fn (array $v) => !empty($v['vente']['debut']) ? [['notaire', 'Fiche notaire', true, true, 'vente']] : []);

route('POST acte', function ($id) {
    $me = require_user();
    $v = enregistrer_acte($me, $id, (string) (json_input()['date'] ?? date('Y-m-d')));
    send_json(vue_dossier($v));
});

// ---------- Demande d'avis Google ----------

/** Lien suivi : on sait si le client a cliqué (pas de relance inutile), puis redirection vers la fiche Google. */
function lien_avis(array $agent, string $id, string $qui): string
{
    return url_publique('avis.php?t=' . lien_pour($agent, $id, 'avis', ['qui' => $qui]));
}

tache_cron('avis_google', function (array $agent, array $dossiers): int {
    global $CONFIG;
    if (empty($CONFIG['lien_avis_google']) || date('G', maintenant()) < 10 || date('G', maintenant()) >= 19) return 0;
    $n = 0;
    foreach ($dossiers as $v) {
        $acte = $v['vente']['acte_le'] ?? null;
        if (!$acte) continue;
        $jours = (maintenant() - strtotime($acte)) / 86400;
        $acq = !empty($v['vente']['acquereur']) ? collection_trouver($agent, 'acquereurs', $v['vente']['acquereur']) : null;
        foreach (['vendeur' => [champ($v, 'email_vendeur'), trim(champ($v, 'civilite_vendeur') . ' ' . champ($v, 'nom_vendeur'))], 'acquereur' => [$acq['email'] ?? '', $acq ? nom_acquereur($acq) : '']] as $qui => [$email, $nom]) {
            $etat = $v['avis'][$qui] ?? [];
            if (!valid_email($email) || !empty($etat['clic'])) continue;
            $envois = count($etat['envois'] ?? []);
            if ($envois >= 2 || $jours < [2, 9][$envois]) continue;
            $txt = "Bonjour $nom,\n\n" . ($envois ? "Je me permets de revenir vers vous : " : "Merci encore pour votre confiance dans cette " . ($qui === 'vendeur' ? 'vente' : 'acquisition') . ". ")
                . "Si vous avez été satisfait(e) de notre accompagnement, votre avis nous aiderait beaucoup. Cela prend une minute :\n" . lien_avis($agent, $v['id'], $qui) . "\n\nMerci et belle continuation,\n" . signature_agent($agent);
            try {
                if (!envoyer_mail_agent($agent, $email, 'Votre avis compte · ' . $CONFIG['agence'], $txt)) continue;
            } catch (Throwable) {
                continue;
            }
            update_visit($agent, $v['id'], function (array $x) use ($qui) {
                $x['avis'][$qui]['envois'][] = date('c', maintenant());
                journal_ajout($x, 'relance', "Demande d'avis Google envoyée au $qui.");
                return $x;
            });
            $n++;
        }
    }
    return $n;
});
