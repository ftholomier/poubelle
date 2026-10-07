<?php
// En sortant de la visite : tout se prépare automatiquement (dossier technique, avis de valeur, pièces à demander),
// puis un seul geste envoie au vendeur son compte rendu, l'avis de valeur, la demande de pièces et le mandat à signer.

/** Après la génération par l'IA : données publiques, avis de valeur, liste des pièces. */
function preparer_dossier(array $agent, string $id): array
{
    try {
        enrichir_donnees_publiques($agent, $id);
    } catch (Throwable $e) {
        journaliser($agent, $id, 'erreur', 'Dossier technique indisponible : ' . $e->getMessage());
    }
    try {
        mettre_a_jour_avis($agent, $id);
    } catch (Throwable $e) {
        journaliser($agent, $id, 'erreur', 'Avis de valeur impossible : ' . $e->getMessage());
    }
    return update_visit($agent, $id, function (array $v) {
        $v = actualiser_pieces($v);
        $v['prepare_le'] = date('c');
        return $v;
    });
}

route('POST preparer', fn ($id) => send_json(vue_dossier(preparer_dossier(require_user(), $id))));

/** Brouillon de l'e-mail au vendeur : destinataire, objet, message, pièces jointes proposées. */
function brouillon_vendeur(array $v, array $agent): array
{
    $civ = trim(champ($v, 'civilite_vendeur') . ' ' . champ($v, 'nom_vendeur'));
    $mandatPret = !mandat_a_completer($v, $agent);
    $manque = pieces_manquantes(actualiser_pieces($v));
    $date = fmt_date_fr($v['cree_le']);
    $lignes = ["Bonjour" . ($civ ? " $civ" : '') . ",", '', "Je vous remercie pour votre accueil lors de la visite du $date. Comme promis, voici :", '- le compte rendu de notre visite ;'];
    if (!empty($v['avis_valeur'])) $lignes[] = '- mon avis de valeur, établi à partir des ventes réelles de votre secteur ;';
    if ($mandatPret) $lignes[] = '- le mandat de vente, à relire et signer en ligne ;';
    if ($manque) $lignes[] = '- la liste des documents à me transmettre pour préparer la vente.';
    $lignes[] = '';
    $lignes[] = 'Tout est rassemblé dans votre espace personnel (lien ci-dessous) : vous pouvez y consulter les documents' . ($mandatPret ? ', signer le mandat' : '') . ($manque ? ' et déposer vos papiers en photo depuis votre téléphone' : '') . '.';
    $lignes[] = '';
    $lignes[] = 'Je reste à votre disposition pour en parler.';
    $lignes[] = '';
    $lignes[] = 'Bien cordialement,';
    $lignes[] = signature_agent($agent);
    $docs = ['vendeur'];
    if (!empty($v['avis_valeur'])) $docs[] = 'avis';
    return [
        'to' => champ($v, 'email_vendeur'),
        'sujet' => 'Suite à notre visite · ' . titre_bien($v),
        'message' => implode("\n", $lignes),
        'docs' => $docs,
        'mandat_pret' => $mandatPret,
        'pieces' => array_column($manque, 'label'),
    ];
}

route('GET envoi_vendeur', function ($id) {
    $me = require_user();
    send_json(brouillon_vendeur(load_visit($me, $id), $me));
});

route('POST envoi_vendeur', function ($id) {
    $me = require_user();
    $in = json_input();
    $to = trim((string) ($in['to'] ?? ''));
    if (!valid_email($to)) fail(400, 'Adresse e-mail du vendeur invalide.');
    if (!email_configure()) fail(400, "L'envoi d'e-mails n'est pas configuré (Paramètres).");
    $v = load_visit($me, $id);

    // Le vendeur indiqué devient l'adresse du dossier
    if (champ($v, 'email_vendeur') === '') {
        $v = update_visit($me, $id, function (array $v) use ($to) {
            $c = (array) $v['fiche']['champs'];
            $c['email_vendeur'] = ['valeur' => $to, 'citation' => '', 'source' => 'agent'];
            $v['fiche']['champs'] = $c;
            return $v;
        });
    }
    $avecMandat = !empty($in['mandat']) && !mandat_a_completer($v, $me);
    if ($avecMandat) {
        preparer_mandat_signature($me, $id);
        if (($v['signatures']['mandat']['statut'] ?? '') !== 'en_attente') demander_signature($me, $id, 'mandat', false);
        $v = load_visit($me, $id);
    }
    $lien = url_publique('espace/?t=' . lien_pour($me, $id, 'vendeur'));
    $texte = rtrim((string) ($in['message'] ?? '')) . "\n\nVotre espace personnel : $lien";
    $pieces = [];
    foreach (array_intersect(array_keys(pdf_docs()), (array) ($in['docs'] ?? [])) as $doc) {
        [$bin, $nom] = build_pdf($doc, $v, $me);
        $pieces[] = [$nom, $bin, 'application/pdf'];
    }
    try {
        envoyer_mail_agent($me, $to, trim((string) ($in['sujet'] ?? '')) ?: 'Suite à notre visite', $texte, $pieces, true);
    } catch (RuntimeException $e) {
        fail(502, $e->getMessage());
    }
    $v = update_visit($me, $id, function (array $v) use ($to, $in, $avecMandat) {
        $v = actualiser_pieces($v);
        $v['envoi_vendeur'] = ['date' => date('c'), 'a' => $to, 'docs' => (array) ($in['docs'] ?? []), 'mandat' => $avecMandat];
        $v['envois'][] = ['date' => date('c'), 'a' => $to, 'docs' => (array) ($in['docs'] ?? []), 'sujet' => 'Suite à notre visite', 'copie' => false];
        if ($avecMandat) $v['signatures']['mandat']['envois'][] = date('c');
        journal_ajout($v, 'envoi', "Envoyé au vendeur ($to) : " . implode(', ', array_map(fn ($d) => pdf_docs()[$d] ?? $d, (array) ($in['docs'] ?? []))) . ($avecMandat ? ', mandat à signer' : '') . ', lien vers son espace. Relances automatiques activées.');
        return $v;
    });
    send_json(vue_dossier($v));
});
