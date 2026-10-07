<?php
// Suivi du projet en temps réel : une case par chose à faire, de la visite à la diffusion, avec son état
// (fait, à faire, en attente d'un tiers, pas commencé, prévu dans le vrai logiciel). Objectif : à la sortie de la
// visite, si tout a été capté (audio, photos, documents, dialogue avec l'IA), le dossier est quasiment opérationnel.
//
// Aussi : le « coach de captation » pendant l'enregistrement (sujets déjà abordés / à aborder, d'après la
// transcription de chaque morceau) et l'export de la fiche pour le CRM.

/** Sujets à aborder pendant la visite : clé => [libellé, question à poser, mots repérés dans la transcription, champs de la fiche]. */
const SUJETS_VISITE = [
    'surface'     => ['Surface', 'la surface habitable', '/\b\d{2,3}\s*(m2|m²|mètres? carrés?)|surface/iu', ['surface_habitable']],
    'pieces'      => ['Pièces et chambres', 'le nombre de chambres', '/chambres?|pièces?\b/iu', ['nb_pieces', 'nb_chambres']],
    'etat'        => ['État et travaux', 'les travaux réalisés ou à prévoir', '/travaux|refait|rénov|toiture|fenêtres|isolation/iu', ['etat_general', 'travaux_realises', 'travaux_a_prevoir']],
    'chauffage'   => ['Chauffage', 'le mode de chauffage', '/chauffage|chaudière|pompe à chaleur|radiateurs?|poêle|fioul|plancher chauffant/iu', ['chauffage']],
    'dpe'         => ['DPE', 'le diagnostic de performance énergétique', '/\bdpe\b|diagnostic|classe [a-g]\b|étiquette/iu', ['dpe']],
    'annee'       => ['Année de construction', "l'année de construction", '/construit|construction|(19|20)\d\d/iu', ['annee_construction']],
    'exterieur'   => ['Extérieur', 'le jardin, la terrasse ou le terrain', '/jardin|terrasse|balcon|terrain/iu', ['exterieur', 'surface_terrain']],
    'parking'     => ['Stationnement', 'le garage ou le stationnement', '/garage|parking|stationnement|\bbox\b/iu', ['stationnement']],
    'taxe'        => ['Taxe foncière', 'le montant de la taxe foncière', '/taxe foncière|foncière/iu', ['taxe_fonciere']],
    'prix'        => ['Prix souhaité', 'le prix souhaité par le vendeur', '/prix|\d+\s*000\s*(€|euros)|euros/iu', ['prix_souhaite']],
    'projet'      => ['Projet et délai', 'la raison et le délai de la vente', '/déménag|mutation|succession|divorce|délai|retraite|pourquoi vous vendez|achat d.un autre/iu', ['motif_vente', 'disponibilite']],
    'proprietaires' => ['Propriétaires', 'qui est propriétaire (situation, indivision)', '/marié|pacs|veu[fv]|divorc|indivision|héritiers?|propriétaires?/iu', ['situation_vendeur', 'nom_vendeur']],
];

const ETATS_SUIVI = ['fait' => 1.0, 'en_attente' => 0.6, 'a_faire' => 0.0, 'pas_commence' => 0.0];

/** Sujets abordés pendant la visite, d'après la transcription et les champs déjà remplis. */
function couverture_captation(array $v): array
{
    $texte = implode("\n", array_map(fn ($m) => (string) ($m['transcription'] ?? ''), $v['morceaux'] ?? []));
    $champs = (array) ($v['fiche']['champs'] ?? []);
    $out = [];
    foreach (SUJETS_VISITE as $cle => [$label, $question, $motif, $cles]) {
        $parChamp = (bool) array_filter($cles, fn ($k) => trim((string) ($champs[$k]['valeur'] ?? '')) !== '');
        $out[] = ['cle' => $cle, 'label' => $label, 'question' => $question, 'couvert' => $parChamp || preg_match($motif, $texte) === 1];
    }
    return $out;
}

/** Contrôles essentiels avant diffusion sur les portails (le détail complet est dans l'aperçu Leboncoin). */
function problemes_annonce(array $v): array
{
    $p = [];
    $titre = trim((string) ($v['titre_annonce'] ?? ''));
    $desc = trim((string) ($v['annonce'] ?? ''));
    if ($titre === '') $p[] = 'titre manquant';
    elseif (mb_strlen($titre) > 70) $p[] = 'titre trop long';
    if ($desc === '') $p[] = 'description manquante';
    elseif (mb_strlen($desc) > 4000) $p[] = 'description trop longue';
    if (count(array_filter($v['photos'] ?? [], fn ($x) => empty($x['floue']))) < 3) $p[] = 'moins de 3 photos';
    if (!champ($v, 'mandat_prix') && !champ($v, 'prix_souhaite')) $p[] = 'prix';
    if (!champ($v, 'mandat_honoraires_charge')) $p[] = 'charge des honoraires';
    if (!champ($v, 'dpe')) $p[] = 'DPE';
    if (!preg_match('/\d{5}/', champ($v, 'ville'))) $p[] = 'code postal';
    return $p;
}

/** Le tableau de suivi : groupes de cases, pourcentage « opérationnel » à la sortie de visite. */
function suivi_dossier(array $v): array
{
    $champs = (array) ($v['fiche']['champs'] ?? []);
    $etape = etape_dossier($v);
    $genere = !empty($v['genere_le']);
    $id = $v['id'];
    $lien = fn ($onglet) => "#/visite/$id/$onglet";
    $c = fn ($cle, $label, $statut, $detail = '', $lienOnglet = '', $optionnel = false) => compact('cle', 'label', 'statut', 'detail') + ['lien' => $lienOnglet ? $lien($lienOnglet) : '', 'optionnel' => $optionnel];

    // --- La visite : ce qui a été capté ---
    $morceaux = $v['morceaux'] ?? [];
    $duree = (int) array_sum(array_column($morceaux, 'duree'));
    $erreurs = count(array_filter($morceaux, fn ($m) => ($m['statut'] ?? '') === 'erreur'));
    $sujets = couverture_captation($v);
    $couverts = count(array_filter($sujets, fn ($s) => $s['couvert']));
    $manquesSujets = array_column(array_filter($sujets, fn ($s) => !$s['couvert']), 'label');
    $remplissables = [];
    foreach (SECTIONS as $s) if (empty($s['interne'])) foreach ($s['champs'] as $f) $remplissables[] = $f['cle'];
    $remplis = count(array_filter($remplissables, fn ($k) => trim((string) ($champs[$k]['valeur'] ?? '')) !== ''));
    $manquants = champs_manquants($champs);
    $dictes = count(array_filter($champs, fn ($x) => ($x['source'] ?? '') === 'dialogue'));
    $photos = array_filter($v['photos'] ?? [], fn ($p) => empty($p['floue']));
    $pieces = array_filter($v['pieces'] ?? [], fn ($p) => empty($p['facultative']));
    $recues = array_filter($pieces, fn ($p) => ($p['statut'] ?? '') === 'recue');
    $envoye = !empty($v['envoi_vendeur']);

    $visite = [
        $c('audio', 'Visite enregistrée', $morceaux ? 'fait' : 'pas_commence', $morceaux ? ($duree < 60 ? "$duree s d'audio" : (int) round($duree / 60) . " min d'audio") . ' · ' . count($morceaux) . ' morceau(x)' : 'Appuyez sur le bouton rouge pendant la visite', 'audio'),
        $c('transcription', 'Transcription', !$morceaux ? 'pas_commence' : ($erreurs ? 'a_faire' : 'fait'), $erreurs ? "$erreurs morceau(x) à retranscrire (relancez « Créer la fiche »)" : ($morceaux ? 'Tout est retranscrit' : ''), 'audio'),
        $c('captation', 'Sujets abordés', !$morceaux && !$genere ? 'pas_commence' : ($couverts >= count($sujets) - 1 ? 'fait' : 'a_faire'), "$couverts/" . count($sujets) . ($manquesSujets ? ' · pas abordé : ' . mb_strtolower(implode(', ', array_slice($manquesSujets, 0, 4))) : ''), 'dialogue'),
        $c('infos', 'Informations du bien', !$genere ? 'pas_commence' : ($remplis >= 0.8 * count($remplissables) ? 'fait' : 'a_faire'), "$remplis/" . count($remplissables) . ' champs remplis', 'fiche'),
        $c('dialogue', "Questions de l'assistant IA", !$genere ? 'pas_commence' : (!$manquants ? 'fait' : 'a_faire'), !$manquants ? ($dictes ? "$dictes réponse(s) dictée(s), rien ne manque" : 'Rien ne manque') : count($manquants) . ' information(s) à dicter : ' . mb_strtolower(implode(', ', array_slice(array_column($manquants, 'label'), 0, 3))), 'dialogue'),
        $c('photos', 'Photos du bien', count($photos) >= 8 ? 'fait' : (count($photos) ? 'a_faire' : 'pas_commence'), count($photos) . ' photo(s) nette(s)' . (count($photos) < 8 ? ' · 8 conseillées' : ''), 'photos'),
        $c('documents', 'Documents du vendeur', !$pieces ? ($genere ? 'fait' : 'pas_commence') : (count($recues) === count($pieces) ? 'fait' : ($envoye ? 'en_attente' : 'a_faire')), $pieces ? count($recues) . '/' . count($pieces) . ' reçus' . (count($recues) < count($pieces) ? ($envoye ? ' · le vendeur est relancé automatiquement' : ' · photographiez-les sur place ou envoyez la demande') : '') : '', 'pieces'),
    ];

    // --- Rédigé automatiquement ---
    $av = $v['avis_valeur'] ?? null;
    $redige = [
        $c('fiche', 'Fiche du bien', $genere ? 'fait' : 'pas_commence', '', 'fiche'),
        $c('annonce', 'Annonce', trim($v['annonce'] ?? '') !== '' ? 'fait' : 'pas_commence', trim($v['titre_annonce'] ?? ''), 'annonce'),
        $c('cr_vendeur', 'Compte rendu au vendeur', trim($v['rapport_vendeur'] ?? '') !== '' ? 'fait' : 'pas_commence', '', 'vendeur'),
        $c('rapport', 'Rapport interne', trim($v['rapport_agent'] ?? '') !== '' ? 'fait' : 'pas_commence', '', 'rapport'),
        $c('technique', 'Dossier technique', !empty($v['public']['maj_le']) ? 'fait' : 'pas_commence', !empty($v['public']['maj_le']) ? implode(', ', $v['public']['sources'] ?? []) . (!empty($v['public']['simulation']) ? ' (en partie simulé)' : '') : 'Cadastre, risques, DPE, ventes', 'technique'),
        $c('avis', 'Avis de valeur', !$av ? 'pas_commence' : (!empty($av['argumentaire_perime']) ? 'a_faire' : 'fait'), $av ? number_format((float) $av['retenu'], 0, ',', ' ') . ' €' . (!empty($av['argumentaire_perime']) ? ' · argumentaire à réécrire' : '') : '', 'avis'),
        $c('plan', 'Croquis de plan', !empty($v['plan']['pieces']) ? 'fait' : 'pas_commence', '', 'plan'),
        $c('social', 'Publications réseaux sociaux', !empty($v['posts']) ? 'fait' : 'pas_commence', '', 'social'),
    ];

    // --- Mandat ---
    $mandatManques = $genere ? mandat_a_completer($v, user_by_id($v['agent'] ?? '') ?? []) : ['…'];
    $sig = $v['signatures']['mandat'] ?? null;
    $signes = $sig ? count(array_filter($sig['signataires'], fn ($s) => !empty($s['signe_le']))) : 0;
    $mandat = [
        $c('mandat_infos', 'Mandat complet', !$genere ? 'pas_commence' : ($mandatManques ? 'a_faire' : 'fait'), $mandatManques && $genere ? 'Il manque : ' . implode(', ', array_slice($mandatManques, 0, 3)) : 'Toutes les mentions obligatoires', 'mandat'),
        $c('registre', 'Numéro au registre des mandats', !empty($v['mandat']['numero']) ? 'fait' : ($genere ? 'a_faire' : 'pas_commence'), !empty($v['mandat']['numero']) ? 'n° ' . $v['mandat']['numero'] : 'Attribué en préparant la signature', 'mandat'),
        $c('envoi', 'Envoyé au vendeur', $envoye ? 'fait' : ($genere ? 'a_faire' : 'pas_commence'), $envoye ? 'Compte rendu, avis de valeur, pièces et mandat' : 'Un seul geste depuis le résumé', 'resume'),
        $c('espace', 'Espace vendeur consulté', !empty($v['espace_vu']['vendeur']) ? 'fait' : ($envoye ? 'en_attente' : 'pas_commence'), !empty($v['espace_vu']['vendeur']) ? 'Ouvert le ' . date('d/m à H:i', strtotime($v['espace_vu']['vendeur'])) : ($envoye ? 'Le vendeur n\'a pas encore ouvert son lien' : '')),
        $c('signature', 'Mandat signé', !empty($v['mandat']['signe_le']) ? 'fait' : ($sig && $sig['statut'] === 'en_attente' ? 'en_attente' : ($genere ? 'a_faire' : 'pas_commence')), $sig ? "$signes/" . count($sig['signataires']) . ' signature(s)' . (($sig['mode'] ?? 'interne') !== 'interne' ? ' · ' . (NOMS_SIGNATURE[$sig['mode']] ?? '') : '') : '', 'signature/mandat'),
    ];

    // --- Diffusion ---
    $pbAnnonce = $genere ? problemes_annonce($v) : ['…'];
    $signe = !empty($v['mandat']['signe_le']);
    $publiee = !empty($v['vitrine']['publiee']);
    $diffusion = [
        $c('conforme', 'Annonce prête pour les portails', !$genere ? 'pas_commence' : ($pbAnnonce ? 'a_faire' : 'fait'), $pbAnnonce && $genere ? 'À corriger : ' . implode(', ', $pbAnnonce) : 'Contrôle de conformité au vert', 'apercu'),
        $c('vitrine', 'Page du bien en ligne', $publiee ? 'fait' : ($signe ? 'a_faire' : 'en_attente'), $publiee ? 'Publiée' : ($signe ? 'Prête : un clic sur « Publier »' : 'Après la signature du mandat'), 'vente'),
        $c('flux', 'Flux de multidiffusion (XML)', $publiee ? 'fait' : 'en_attente', $publiee ? "L'annonce est dans le flux des portails" : 'Ajoutée au flux dès la publication', 'vente'),
        $c('portails', 'Leboncoin, SeLoger… (diffusion réelle)', 'prevu', 'Compte pro ou multidiffuseur : branchement dans le vrai logiciel', 'apercu'),
        $c('crm_fiche', 'Fiche prête pour le CRM', $genere ? 'fait' : 'pas_commence', $genere ? 'Export au format d\'échange (JSON)' : ''),
        $c('crm_envoi', 'Envoi au CRM Synapse', 'prevu', 'Automatique dans le vrai logiciel'),
    ];

    $groupes = [
        ['cle' => 'visite', 'titre' => 'La visite', 'items' => $visite],
        ['cle' => 'redige', 'titre' => 'Rédigé automatiquement', 'items' => $redige],
        ['cle' => 'mandat', 'titre' => 'Mandat', 'items' => $mandat],
        ['cle' => 'diffusion', 'titre' => 'Diffusion et CRM', 'items' => $diffusion],
    ];

    // --- Vente (à partir de la mise en vente) ---
    if (in_array($etape, ['en_vente', 'offre', 'compromis', 'vendu'], true)) {
        $s = $v['vente'] ?? [];
        $acceptee = array_filter($v['offres'] ?? [], fn ($o) => ($o['statut'] ?? '') === 'acceptee');
        $groupes[] = ['cle' => 'vente', 'titre' => 'La vente', 'vente' => true, 'items' => [
            $c('visites', 'Visites d\'acquéreurs', !empty($v['visites_acq']) ? 'fait' : 'en_attente', count($v['visites_acq'] ?? []) . ' visite(s)', 'vente'),
            $c('cr_hebdo', 'Point du vendredi au vendeur', !empty($v['cr_hebdo']) ? 'fait' : 'en_attente', count($v['cr_hebdo'] ?? []) . ' envoyé(s) automatiquement', 'vente'),
            $c('offre', 'Offre acceptée', $acceptee ? 'fait' : (!empty($v['offres']) ? 'en_attente' : 'pas_commence'), count($v['offres'] ?? []) . ' offre(s)', 'vente'),
            $c('lcbft', 'Contrôle anti-blanchiment', !empty($v['lcbft']['vendeur']) && !empty($v['lcbft']['acquereur']) ? 'fait' : ($acceptee ? 'a_faire' : 'pas_commence'), '', 'vente'),
            $c('notaires', 'Dossier transmis aux notaires', !empty($s['notaires_envoye_le']) ? 'fait' : ($acceptee ? 'a_faire' : 'pas_commence'), '', 'vente'),
            $c('compromis', 'Compromis signé', !empty($s['compromis_le']) ? 'fait' : 'pas_commence', !empty($s['compromis_le']) ? date('d/m/Y', strtotime($s['compromis_le'])) : '', 'vente'),
            $c('acte', 'Acte authentique', !empty($s['acte_le']) ? 'fait' : (!empty($s['compromis_le']) ? 'en_attente' : 'pas_commence'), !empty($s['acte_le']) ? date('d/m/Y', strtotime($s['acte_le'])) : '', 'vente'),
        ]];
    }

    // Pourcentage « opérationnel » : tout ce qui doit être prêt en sortant de la visite (hors vente et hors « prévu »)
    $total = 0;
    $points = 0.0;
    $compte = ['fait' => 0, 'a_faire' => 0, 'en_attente' => 0, 'pas_commence' => 0, 'prevu' => 0];
    foreach ($groupes as &$g) {
        $gt = 0;
        $gp = 0.0;
        foreach ($g['items'] as $it) {
            $compte[$it['statut']] = ($compte[$it['statut']] ?? 0) + 1;
            if (!isset(ETATS_SUIVI[$it['statut']]) || $it['optionnel']) continue;
            $gt++;
            $gp += ETATS_SUIVI[$it['statut']];
        }
        $g['pourcentage'] = $gt ? (int) round(100 * $gp / $gt) : 100;
        if (empty($g['vente'])) { $total += $gt; $points += $gp; }
    }
    unset($g);
    $prochaine = null;
    foreach ($groupes as $g) foreach ($g['items'] as $it) if (!$prochaine && $it['statut'] === 'a_faire') $prochaine = $it;
    return [
        'pourcentage' => $total ? (int) round(100 * $points / $total) : 0,
        'compte' => $compte,
        'groupes' => $groupes,
        'prochaine' => $prochaine,
        'sujets' => $sujets,
        'maj_le' => date('c'),
    ];
}

/** Résumé compact pour les listes (cartes « Biens ») : pourcentage et nombre de cases à faire. */
function suivi_resume(array $v): array
{
    $s = suivi_dossier($v);
    return ['pourcentage' => $s['pourcentage'], 'a_faire' => $s['compte']['a_faire'], 'en_attente' => $s['compte']['en_attente']];
}

route('GET suivi', fn ($id) => send_json(suivi_dossier(load_visit(require_user(), $id))));

/** Pendant l'enregistrement : sujets abordés, durée, photos et documents déjà pris (interrogé toutes les 20 s). */
route('GET captation', function ($id) {
    $v = load_visit(require_user(), $id);
    $sujets = couverture_captation($v);
    send_json([
        'sujets' => $sujets,
        'couverts' => count(array_filter($sujets, fn ($s) => $s['couvert'])),
        'minutes' => (int) round(array_sum(array_column($v['morceaux'] ?? [], 'duree')) / 60),
        'morceaux' => count($v['morceaux'] ?? []),
        'photos' => count($v['photos'] ?? []),
        'documents' => count(array_filter($v['pieces'] ?? [], fn ($p) => ($p['statut'] ?? '') === 'recue')),
    ]);
});

// ---------- Export CRM ----------

/**
 * Fiche du dossier au format d'échange, prête pour le CRM du réseau (envoi automatique prévu dans le vrai logiciel).
 * Regroupement : mandant (vendeurs), bien, mandat, estimation, annonce, diagnostics, documents.
 */
function export_crm(array $v, array $agent): array
{
    $champs = (array) ($v['fiche']['champs'] ?? []);
    $val = fn ($k) => trim((string) ($champs[$k]['valeur'] ?? ''));
    $num = fn ($k) => $val($k) === '' ? null : nombre_fr($val($k));
    $vendeurs = [];
    foreach (['', '2'] as $n) {
        if ($val("nom_vendeur$n") === '') continue;
        $vendeurs[] = array_filter(['civilite' => $val("civilite_vendeur$n"), 'prenom' => $val("prenom_vendeur$n"), 'nom' => $val("nom_vendeur$n"),
            'email' => $val("email_vendeur$n"), 'telephone' => $val("telephone_vendeur$n"), 'date_naissance' => $val("naissance_date_vendeur$n"),
            'lieu_naissance' => $val("naissance_lieu_vendeur$n"), 'situation' => $val("situation_vendeur$n")], fn ($x) => $x !== '');
    }
    $sig = $v['signatures']['mandat'] ?? null;
    return [
        'format' => 'synapse-visite-immo/1',
        'exporte_le' => date('c'),
        'reference' => $v['id'],
        'agent' => ['id' => $agent['id'], 'nom' => $agent['nom'], 'email' => $agent['email'] ?? ''],
        'etape' => etape_dossier($v),
        'mandant' => ['vendeurs' => $vendeurs, 'adresse' => $val('adresse_vendeur') ?: null],
        'bien' => array_filter([
            'type' => $val('type_bien'), 'adresse' => $val('adresse'), 'ville' => $val('ville'),
            'surface_habitable' => $num('surface_habitable'), 'surface_terrain' => $num('surface_terrain'),
            'pieces' => $num('nb_pieces'), 'chambres' => $num('nb_chambres'), 'salles_de_bain' => $num('nb_salles_de_bain'),
            'annee_construction' => $num('annee_construction'), 'etat' => $val('etat_general'), 'chauffage' => $val('chauffage'),
            'exterieur' => $val('exterieur'), 'stationnement' => $val('stationnement'), 'exposition' => $val('exposition'),
            'cadastre' => $val('cadastre'), 'copropriete' => $val('copropriete'), 'taxe_fonciere' => $num('taxe_fonciere'),
            'coordonnees' => isset($v['public']['geo']) ? ['lat' => $v['public']['geo']['lat'], 'lon' => $v['public']['geo']['lon']] : null,
        ], fn ($x) => $x !== '' && $x !== null),
        'diagnostics' => array_filter(['dpe' => $val('dpe'), 'ges' => $val('ges'), 'depenses_energie_min' => $num('depenses_energie_min'), 'depenses_energie_max' => $num('depenses_energie_max'),
            'risques' => $v['public']['risques']['liste'] ?? null], fn ($x) => $x !== '' && $x !== null),
        'mandat' => array_filter(['numero' => $v['mandat']['numero'] ?? null, 'type' => $val('mandat_type'), 'prix' => $num('mandat_prix'), 'honoraires' => $val('mandat_honoraires'),
            'honoraires_charge' => $val('mandat_honoraires_charge'), 'duree_mois' => $num('mandat_duree'), 'date' => $val('mandat_date'),
            'signe_le' => $v['mandat']['signe_le'] ?? null, 'signature' => $sig ? ['statut' => $sig['statut'], 'mode' => $sig['mode'] ?? 'interne'] : null], fn ($x) => $x !== '' && $x !== null),
        'estimation' => isset($v['avis_valeur']['retenu']) ? ['prix_conseille' => $v['avis_valeur']['retenu'], 'bas' => $v['avis_valeur']['bas'], 'haut' => $v['avis_valeur']['haut'],
            'prix_vendeur' => $num('prix_souhaite'), 'comparables' => count($v['avis_valeur']['comparables'] ?? [])] : null,
        'annonce' => ['titre' => $v['titre_annonce'] ?? '', 'texte' => $v['annonce'] ?? '', 'points_forts' => $v['points_forts'] ?? [],
            'photos' => array_values(array_map(fn ($p) => ['fichier' => $p['staging']['fichier'] ?? $p['fichier'], 'piece' => $p['piece'] ?? '', 'amenagement_virtuel' => !empty($p['staging'])], array_filter($v['photos'] ?? [], fn ($p) => empty($p['floue']))))],
        'documents' => array_values(array_map(fn ($p) => ['type' => $p['cle'], 'libelle' => $p['label'], 'statut' => $p['statut'] ?? 'a_demander'], $v['pieces'] ?? [])),
        'suivi' => suivi_resume($v),
    ];
}

route('GET crm_export', function ($id) {
    $me = require_user();
    $v = load_visit($me, $id);
    header('Content-Disposition: attachment; filename="crm-' . slug(titre_bien($v)) . '.json"');
    send_json(export_crm($v, $me));
});
