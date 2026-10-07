<?php
// Du compromis à l'acte : intervenants (notaires, courtier), dates clés et échéancier calculé automatiquement
// (rétractation SRU de 10 jours, condition suspensive de prêt, acte), relances automatiques, dossier notaire
// assemblé et transmis, espace notaire.

const JOURS_FERIES_FIXES = ['01-01', '05-01', '05-08', '07-14', '08-15', '11-01', '11-11', '12-25'];

/** Jours fériés (fixes + Pâques, Ascension, Pentecôte) pour reporter une échéance au jour ouvrable suivant. */
function est_ferie(int $t): bool
{
    if (in_array(date('m-d', $t), JOURS_FERIES_FIXES, true)) return true;
    if (!function_exists('easter_date')) return false; // extension calendar absente : seuls les jours fixes comptent
    $paques = easter_date((int) date('Y', $t));
    foreach ([1, 39, 50] as $d) if (date('Y-m-d', $t) === date('Y-m-d', strtotime("+$d days", $paques))) return true;
    return false;
}

/** Délai SRU : 10 jours à compter du lendemain de la première présentation ; reporté au premier jour ouvrable. */
function fin_retractation(string $notification): ?string
{
    $t = strtotime($notification);
    if (!$t) return null;
    $fin = strtotime('+10 days', strtotime(date('Y-m-d', $t)));
    while (in_array((int) date('N', $fin), [6, 7], true) || est_ferie($fin)) $fin = strtotime('+1 day', $fin);
    return date('Y-m-d', $fin);
}

/** Échéancier de la vente : [clé, libellé, date, état (fait / a_venir / depasse / a_saisir), détail]. */
function echeancier(array $v): array
{
    $s = $v['vente'] ?? [];
    $auj = date('Y-m-d', maintenant());
    $e = [];
    $ajout = function (string $cle, string $label, ?string $date, bool $fait, string $detail = '') use (&$e, $auj) {
        $etat = $fait ? 'fait' : (!$date ? 'a_saisir' : ($date < $auj ? 'depasse' : 'a_venir'));
        $e[] = compact('cle', 'label', 'date', 'etat', 'detail');
    };
    $ajout('offre', 'Offre acceptée', isset($s['debut']) ? substr($s['debut'], 0, 10) : null, !empty($s['debut']));
    $ajout('compromis', 'Signature du compromis', $s['compromis_prevu'] ?? $s['compromis_le'] ?? null, !empty($s['compromis_le']), 'Chez le notaire');
    $sru = !empty($s['notification_sru']) ? fin_retractation($s['notification_sru']) : null;
    $ajout('sru', "Fin du délai de rétractation de l'acquéreur", $sru, $sru !== null && $sru < $auj, $sru ? '10 jours après la notification du ' . date_jj($s['notification_sru']) : 'Date de notification à saisir');
    if (($s['financement'] ?? 'pret') !== 'comptant') {
        $ajout('pret_depot', 'Dépôt des demandes de prêt', $s['pret_depot_limite'] ?? null, !empty($s['pret_depose_le']), 'Justificatif à fournir au notaire');
        $ajout('pret', "Obtention de l'offre de prêt (condition suspensive)", $s['pret_limite'] ?? null, !empty($s['pret_obtenu_le']));
    }
    $ajout('acte', "Signature de l'acte authentique", $s['acte_prevu'] ?? $s['acte_le'] ?? null, !empty($s['acte_le']));
    return $e;
}

/** Relances automatiques selon l'échéancier : chaque relance n'est envoyée qu'une fois (mémorisée dans vente.relances). */
function relances_vente(array $agent, array $v): int
{
    $s = $v['vente'] ?? [];
    if (empty($s['debut']) || !empty($s['acte_le'])) return 0;
    $auj = maintenant();
    $faites = $s['relances'] ?? [];
    $envois = [];
    $jours = fn (?string $d) => $d ? (int) floor((strtotime($d) - strtotime(date('Y-m-d', $auj))) / 86400) : null;
    $acq = !empty($s['acquereur']) ? collection_trouver($agent, 'acquereurs', $s['acquereur']) : null;
    $mailAcq = $acq['email'] ?? '';
    $bien = titre_bien($v);

    if (($s['financement'] ?? 'pret') !== 'comptant' && empty($s['pret_obtenu_le']) && ($j = $jours($s['pret_limite'] ?? null)) !== null) {
        foreach ([15 => 'pret_j15', 5 => 'pret_j5'] as $seuil => $cle) {
            if ($j <= $seuil && $j >= 0 && empty($faites[$cle])) {
                $txt = "Bonjour,\n\nLa date limite d'obtention de votre prêt pour l'achat de $bien est le " . date_jj($s['pret_limite']) . " (dans $j jours).\nOù en est votre dossier ? Merci de me transmettre l'offre de prêt dès réception, ou de me prévenir en cas de difficulté.\n\n" . signature_agent($agent);
                foreach (array_filter([$mailAcq, $s['courtier']['email'] ?? '']) as $to) $envois[] = [$to, "Votre prêt · échéance du " . date_jj($s['pret_limite']), $txt, $cle];
                break;
            }
        }
    }
    if (($j = $jours($s['acte_prevu'] ?? null)) !== null) {
        if ($j <= 10 && $j >= 3 && empty($faites['acte_notaires'])) {
            $txt = "Maître,\n\nLa signature de l'acte de vente de $bien est prévue le " . date_jj($s['acte_prevu']) . ".\nPouvez-vous me confirmer que le dossier est complet, ou m'indiquer les pièces qui manquent ? Le dossier complet est disponible ici : " . url_publique('espace/?t=' . lien_pour($agent, $v['id'], 'notaire')) . "\n\nBien cordialement,\n" . signature_agent($agent);
            foreach (array_filter([$s['notaire_vendeur']['email'] ?? '', $s['notaire_acquereur']['email'] ?? '']) as $to) $envois[] = [$to, "Acte du " . date_jj($s['acte_prevu']) . ' · ' . $bien, $txt, 'acte_notaires'];
        }
        if ($j <= 2 && $j >= 0 && empty($faites['acte_parties'])) {
            $txt = "Bonjour,\n\nPetit rappel : la signature de l'acte de vente de $bien a lieu le " . date_jj($s['acte_prevu']) . (($s['acte_lieu'] ?? '') ? " à {$s['acte_lieu']}" : ' chez le notaire') . ".\nPensez à votre pièce d'identité. Je serai présent(e) à vos côtés.\n\n" . signature_agent($agent);
            foreach (array_filter([$mailAcq, champ($v, 'email_vendeur')]) as $to) $envois[] = [$to, 'Rappel : signature de l\'acte le ' . date_jj($s['acte_prevu']), $txt, 'acte_parties'];
        }
    }
    $n = 0;
    $marquees = [];
    foreach ($envois as [$to, $sujet, $txt, $cle]) {
        try {
            if (envoyer_mail_agent($agent, $to, $sujet, $txt)) { $n++; $marquees[$cle] = date('c', $auj); }
        } catch (Throwable $e) {
            error_log('relance vente : ' . $e->getMessage());
        }
    }
    if ($marquees) update_visit($agent, $v['id'], function (array $x) use ($marquees) {
        foreach ($marquees as $k => $d) $x['vente']['relances'][$k] = $d;
        journal_ajout($x, 'relance', 'Relances automatiques envoyées : ' . implode(', ', array_map(fn ($k) => ['pret_j15' => 'prêt (J-15)', 'pret_j5' => 'prêt (J-5)', 'acte_notaires' => 'notaires avant l\'acte', 'acte_parties' => 'rappel de l\'acte aux parties'][$k] ?? $k, array_keys($marquees))) . '.');
        return $x;
    });
    return $n;
}

tache_cron('relances_vente', function (array $agent, array $dossiers): int {
    if (date('G', maintenant()) < 9 || date('G', maintenant()) >= 19) return 0;
    $n = 0;
    foreach ($dossiers as $v) $n += relances_vente($agent, $v);
    return $n;
});

a_faire('echeances', function (array $agent, array $dossiers): array {
    $items = [];
    foreach ($dossiers as $v) {
        if (empty($v['vente']['debut']) || !empty($v['vente']['acte_le'])) continue;
        foreach (echeancier($v) as $e) {
            if ($e['etat'] === 'fait') continue;
            $j = $e['date'] ? (int) floor((strtotime($e['date']) - strtotime(date('Y-m-d', maintenant()))) / 86400) : null;
            if ($e['etat'] === 'a_saisir' && in_array($e['cle'], ['compromis', 'acte'], true)) $items[] = ['type' => 'echeance', 'titre' => "Date à fixer : {$e['label']}", 'detail' => titre_bien($v), 'lien' => "#/visite/{$v['id']}/vente", 'priorite' => 2];
            elseif ($j !== null && $j <= 7) $items[] = ['type' => 'echeance', 'titre' => $e['label'] . ($j < 0 ? ' : dépassée' : ($j === 0 ? " aujourd'hui" : " dans $j j")), 'detail' => titre_bien($v) . ' · ' . date_jj($e['date']), 'lien' => "#/visite/{$v['id']}/vente", 'priorite' => $j <= 2 ? 1 : 2, 'date' => $e['date']];
        }
    }
    return $items;
});

// ---------- Fiche de renseignements pour le notaire ----------

function rendre_notaire(VisitePdf $pdf, array $v, array $agent): void
{
    global $CONFIG;
    $s = $v['vente'] ?? [];
    if (empty($s['debut'])) throw new RuntimeException('Pas de vente en cours.');
    $f = fn ($n) => fmt_nombre((string) $n) . "\u{00A0}€";
    $pdf->docLabel = 'Fiche de renseignements notaire';
    $pdf->AddPage();
    $pdf->titleBlock('Dossier notaire', titre_bien($v), 'Transmis par ' . $agent['nom'] . ' · ' . $CONFIG['agence'] . ' · le ' . fmt_date_fr(null));
    $pdf->statBoxes(array_filter([
        ['label' => 'Prix de vente', 'valeur' => $f($s['prix'] ?? 0), 'sombre' => true],
        ['label' => 'Honoraires TTC', 'valeur' => champ($v, 'mandat_honoraires'), 'detail' => 'charge ' . (champ($v, 'mandat_honoraires_charge') === 'Le vendeur' ? 'vendeur' : 'acquéreur')],
        ['label' => 'Mandat', 'valeur' => $v['mandat']['numero'] ?? '—', 'detail' => champ($v, 'mandat_type')],
    ]));
    $personne = fn (string $suf) => trim(implode("\n", array_filter([
        trim(champ($v, "civilite_vendeur$suf") . ' ' . champ($v, "prenom_vendeur$suf") . ' ' . mb_strtoupper(champ($v, "nom_vendeur$suf"))),
        champ($v, "naissance_date_vendeur$suf") ? 'Né(e) le ' . champ($v, "naissance_date_vendeur$suf") . ' à ' . champ($v, "naissance_lieu_vendeur$suf") : '',
        implode(' · ', array_filter([champ($v, "telephone_vendeur$suf"), champ($v, "email_vendeur$suf")])),
    ])));
    $pdf->sectionTitle('Vendeur(s)');
    $rows = [['label' => 'Vendeur 1', 'valeur' => $personne(''), 'long' => true]];
    if (champ($v, 'nom_vendeur2')) $rows[] = ['label' => 'Vendeur 2', 'valeur' => $personne('2'), 'long' => true];
    $rows[] = ['label' => 'Situation familiale', 'valeur' => trim(champ($v, 'situation_vendeur') . ' ' . champ($v, 'regime_vendeur')) ?: '—', 'long' => false];
    $rows[] = ['label' => 'Adresse', 'valeur' => champ($v, 'adresse_vendeur') ?: 'Le bien vendu', 'long' => false];
    $pdf->kvGrid($rows);
    $acq = !empty($s['acquereur']) ? collection_trouver($agent, 'acquereurs', $s['acquereur']) : null;
    $pdf->sectionTitle('Acquéreur(s)');
    $pdf->kvGrid(array_values(array_filter([
        ['label' => 'Acquéreur', 'valeur' => $acq ? nom_acquereur($acq) : ($s['acquereur_nom'] ?? '—'), 'long' => false],
        $acq ? ['label' => 'Contact', 'valeur' => implode(' · ', array_filter([$acq['telephone'] ?? '', $acq['email'] ?? ''])) ?: '—', 'long' => false] : null,
        ['label' => 'Financement', 'valeur' => ($s['financement'] ?? 'pret') === 'comptant' ? 'Comptant' : 'Prêt de ' . $f($s['pret_montant'] ?? 0) . (($s['pret_duree'] ?? 0) ? " sur {$s['pret_duree']} ans" : '') . (($s['pret_taux'] ?? 0) ? ', taux max ' . $s['pret_taux'] . ' %' : ''), 'long' => false],
        !empty($s['courtier']['nom']) ? ['label' => 'Courtier', 'valeur' => trim($s['courtier']['nom'] . ' · ' . ($s['courtier']['email'] ?? '')), 'long' => false] : null,
    ])));
    $pdf->sectionTitle('Le bien');
    $pdf->kvGrid(array_values(array_filter([
        ['label' => 'Adresse', 'valeur' => trim(champ($v, 'adresse') . ' ' . champ($v, 'ville')) ?: titre_bien($v), 'long' => false],
        ['label' => 'Cadastre', 'valeur' => champ($v, 'cadastre') ?: '—', 'long' => false],
        champ($v, 'copropriete') === 'oui' ? ['label' => 'Lots de copropriété', 'valeur' => champ($v, 'lots_copropriete') . (champ($v, 'surface_carrez') ? ' · Carrez ' . champ($v, 'surface_carrez') . ' m²' : ''), 'long' => false] : null,
        ['label' => 'Origine de propriété', 'valeur' => champ($v, 'origine_propriete') ?: '—', 'long' => true],
        ['label' => 'Occupation', 'valeur' => champ($v, 'occupation') ?: '—', 'long' => false],
        champ($v, 'servitudes') ? ['label' => 'Servitudes, procédures', 'valeur' => champ($v, 'servitudes'), 'long' => true] : null,
    ])));
    $pdf->sectionTitle('Calendrier');
    $pdf->kvGrid(array_map(fn ($e) => ['label' => $e['label'], 'valeur' => ($e['date'] ? date_jj($e['date']) : 'à fixer') . ($e['etat'] === 'fait' ? ' ✓' : ''), 'long' => false], echeancier($v)));
    $pdf->sectionTitle('Pièces jointes au dossier');
    $pieces = array_filter($v['pieces'] ?? [], fn ($p) => ($p['statut'] ?? '') === 'recue');
    $pdf->richText($pieces ? implode("\n", array_map(fn ($p) => '- ' . $p['label'], $pieces)) : 'Aucune pièce reçue pour le moment.');
    $manque = pieces_manquantes($v);
    if ($manque) $pdf->richText("Pièces encore attendues du vendeur :\n" . implode("\n", array_map(fn ($p) => '- ' . $p['label'], $manque)));
    $l = $v['lcbft'] ?? [];
    $pdf->sectionTitle('Vigilance (LCB-FT)');
    $pdf->richText(implode("\n", array_map(fn ($r) => '- ' . ucfirst($r) . ' : ' . (isset($l[$r]['niveau']) ? 'contrôle effectué, vigilance ' . $l[$r]['niveau'] : 'contrôle à réaliser'), ['vendeur', 'acquereur'])));
    $pdf->contactBox($agent);
}

pdf_module('notaire', 'Fiche notaire', 'rendre_notaire', null, false);

// ---------- Espace notaire ----------

espace_section('notaire', 10, function (array $ctx): string {
    $v = $ctx['v'];
    $t = e($ctx['token']);
    $h = '<section class="es-card"><h2>Dossier de vente</h2>'
        . '<a class="es-doc" target="_blank" href="?t=' . $t . '&pdf=notaire"><span>📄</span>Fiche de renseignements<span class="es-fl">→</span></a>'
        . (!empty($v['mandat']['numero']) ? '<a class="es-doc" target="_blank" href="?t=' . $t . '&pdf=mandat"><span>📄</span>Mandat n° ' . e($v['mandat']['numero']) . '<span class="es-fl">→</span></a>' : '')
        . (!empty($v['vente']['offre']) ? '<a class="es-doc" target="_blank" href="?t=' . $t . '&pdf=' . e('offre:' . $v['vente']['offre']) . '"><span>📄</span>Offre d\'achat acceptée<span class="es-fl">→</span></a>' : '')
        . '<a class="es-doc" target="_blank" href="?t=' . $t . '&pdf=fiche"><span>📄</span>Fiche du bien<span class="es-fl">→</span></a>';
    foreach (['vendeur', 'acquereur'] as $r) if (!empty($v['lcbft'][$r]['niveau'])) $h .= '<a class="es-doc" target="_blank" href="?t=' . $t . '&pdf=vigilance&partie=' . $r . '"><span>📄</span>Fiche de vigilance ' . $r . '<span class="es-fl">→</span></a>';
    $h .= '</section><section class="es-card"><h2>Pièces du vendeur</h2>';
    foreach ($v['pieces'] ?? [] as $p) {
        foreach ($p['fichiers'] ?? [] as $f) $h .= '<a class="es-doc" target="_blank" href="?t=' . $t . '&piece=' . e($f['nom']) . '"><span>📎</span>' . e($p['label']) . '<span class="es-fl">→</span></a>';
        if (($p['statut'] ?? '') !== 'recue' && empty($p['facultative'])) $h .= '<div class="es-ligne"><strong>' . e($p['label']) . '</strong><span class="es-aide">En attente du vendeur (relances automatiques)</span></div>';
    }
    $h .= '</section><section class="es-card"><h2>Calendrier</h2>';
    foreach (echeancier($v) as $e) $h .= '<div class="es-ligne"><strong>' . ($e['etat'] === 'fait' ? '✓ ' : '') . e($e['label']) . '</strong><span class="es-aide">' . e($e['date'] ? date_jj($e['date']) : 'à fixer') . '</span></div>';
    return $h . '</section>';
});

// Le vendeur et l'acquéreur voient aussi le calendrier
foreach (['vendeur', 'acquereur'] as $role) {
    espace_section($role, 15, function (array $ctx): string {
        if (empty($ctx['v']['vente']['debut'])) return '';
        $h = '<section class="es-card"><h2>Les étapes de votre vente</h2>';
        foreach (echeancier($ctx['v']) as $e) $h .= '<div class="es-ligne"><strong>' . ($e['etat'] === 'fait' ? '✓ ' : '') . e($e['label']) . '</strong><span class="es-aide">' . e($e['date'] ? date_jj($e['date']) : 'date à fixer') . '</span></div>';
        return $h . '</section>';
    });
}

// ---------- API ----------

route('POST vente', function ($id) {
    $me = require_user();
    $in = json_input();
    $dates = ['compromis_prevu', 'compromis_le', 'notification_sru', 'pret_depot_limite', 'pret_depose_le', 'pret_limite', 'pret_obtenu_le', 'acte_prevu'];
    $v = update_visit($me, $id, function (array $v) use ($in, $dates) {
        $s = $v['vente'] ?? [];
        foreach ($dates as $k) if (array_key_exists($k, $in)) $s[$k] = date_iso((string) $in[$k]) ?: null;
        foreach (['notaire_vendeur', 'notaire_acquereur', 'courtier'] as $k) {
            if (isset($in[$k]) && is_array($in[$k])) $s[$k] = array_map(fn ($x) => mb_substr(trim((string) $x), 0, 160), array_intersect_key($in[$k], array_flip(['nom', 'email', 'telephone'])));
        }
        if (isset($in['financement'])) $s['financement'] = in_array($in['financement'], ['pret', 'comptant', 'mixte'], true) ? $in['financement'] : 'pret';
        foreach (['pret_montant', 'prix'] as $k) if (isset($in[$k])) $s[$k] = (int) preg_replace('/\D/', '', (string) $in[$k]);
        if (isset($in['acte_lieu'])) $s['acte_lieu'] = mb_substr(trim((string) $in['acte_lieu']), 0, 160);
        // Compromis signé : délais par défaut si non saisis (prêt 45 jours, acte 3 mois)
        if (!empty($s['compromis_le']) && empty($v['vente']['compromis_le'])) {
            $s['pret_limite'] ??= date('Y-m-d', strtotime($s['compromis_le'] . ' +45 days'));
            $s['pret_depot_limite'] ??= date('Y-m-d', strtotime($s['compromis_le'] . ' +10 days'));
            $s['acte_prevu'] ??= date('Y-m-d', strtotime($s['compromis_le'] . ' +3 months'));
            journal_ajout($v, 'etape', 'Compromis signé le ' . date_jj($s['compromis_le']) . '. Échéancier calculé, relances automatiques activées.');
        }
        $v['vente'] = $s;
        return $v;
    });
    send_json(vue_dossier($v) + ['echeancier' => echeancier($v)]);
});

route('GET echeancier', fn ($id) => send_json(echeancier(load_visit(require_user(), $id))));

route('POST envoyer_notaires', function ($id) {
    $me = require_user();
    $v = load_visit($me, $id);
    $s = $v['vente'] ?? fail(400, 'Pas de vente en cours.');
    $lien = url_publique('espace/?t=' . lien_pour($me, $id, 'notaire'));
    [$bin, $nom] = build_pdf('notaire', $v, $me);
    $n = 0;
    foreach (['notaire_vendeur', 'notaire_acquereur'] as $k) {
        $to = $s[$k]['email'] ?? '';
        if (!valid_email($to)) continue;
        envoyer_mail_agent($me, $to, 'Dossier de vente · ' . titre_bien($v), "Maître,\n\nVeuillez trouver ci-joint la fiche de renseignements de la vente de " . titre_bien($v) . " au prix de " . number_format((float) ($s['prix'] ?? 0), 0, ',', ' ') . " €.\n\nToutes les pièces (mandat, offre acceptée, documents du vendeur, fiches de vigilance) sont disponibles et tenues à jour ici :\n$lien\n\nBien cordialement,\n" . signature_agent($me), [[$nom, $bin, 'application/pdf']], true);
        $n++;
    }
    if (!$n) fail(400, 'Renseignez l\'e-mail d\'au moins un notaire.');
    $v = update_visit($me, $id, function (array $v) use ($n) {
        $v['vente']['notaires_envoye_le'] = date('c');
        journal_ajout($v, 'envoi', "Dossier transmis à $n notaire(s), avec l'accès à l'espace notaire.");
        return $v;
    });
    send_json(vue_dossier($v));
});
