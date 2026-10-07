<?php
// Le dossier d'un bien, de la visite à l'acte : étape en cours, prochaine action, documents disponibles.
// Principe : presque tout part de la visite. Après « Créer la fiche », tout est préparé automatiquement ;
// l'agent n'a plus qu'une action à la fois, affichée en grand.

const ETAPES = [
    'visite'      => ['Visite', 'Enregistrée'],
    'preparation' => ['Dossier', 'À compléter et envoyer'],
    'signature'   => ['Mandat', 'Signature en cours'],
    'en_vente'    => ['En vente', 'Diffusé'],
    'offre'       => ['Offre', 'Offre acceptée'],
    'compromis'   => ['Compromis', 'Signé, en attente de l\'acte'],
    'vendu'       => ['Vendu', 'Acte signé'],
];

function etape_dossier(array $v): string
{
    if (!empty($v['vente']['acte_le'])) return 'vendu';
    if (!empty($v['vente']['compromis_le'])) return 'compromis';
    foreach ($v['offres'] ?? [] as $o) if (($o['statut'] ?? '') === 'acceptee') return 'offre';
    if (!empty($v['mandat']['signe_le'])) return 'en_vente';
    if (!empty($v['mandat']['numero'])) return 'signature';
    if (empty($v['genere_le'])) return 'visite';
    return 'preparation';
}

/** Ce qui reste à faire sur le dossier, le plus important d'abord : [titre, detail, action (clé), bouton]. */
function prochaines_actions(array $v): array
{
    $id = $v['id'];
    $etape = etape_dossier($v);
    $a = [];
    $manquants = champs_manquants((array) $v['fiche']['champs']);
    if ($etape === 'visite') {
        $a[] = ['Créer les documents', "L'IA prépare tout à partir de l'enregistrement.", 'generer', '✨ Créer la fiche'];
        return $a;
    }
    if ($etape === 'preparation') {
        if ($manquants) $a[] = [count($manquants) . ' information' . (count($manquants) > 1 ? 's' : '') . ' à compléter', "L'assistant vous pose uniquement les questions qui manquent pour le mandat.", 'dialogue', '🎙️ Compléter à la voix'];
        if (empty($v['envoi_vendeur'])) $a[] = ['Envoyer au vendeur', 'Compte rendu, avis de valeur, demande de pièces' . (!$manquants ? ' et mandat à signer' : '') . ', en un seul e-mail.', 'envoyer_vendeur', '✉️ Tout envoyer au vendeur'];
        elseif (!$manquants) $a[] = ['Faire signer le mandat', 'Signature au doigt sur votre téléphone, ou lien envoyé au vendeur.', 'signer_mandat', '✍️ Faire signer le mandat'];
    }
    if ($etape === 'signature') {
        $a[] = ['Mandat en attente de signature', 'Le vendeur a reçu son lien ; relances automatiques en cours.', 'signer_mandat', '✍️ Faire signer sur place'];
    }
    if ($etape === 'en_vente' && empty($v['vitrine']['publiee'])) {
        $a[] = ['Mettre le bien en vente', "Page du bien, diffusion et réseaux sociaux sont prêts.", 'publier', '🚀 Publier'];
    }
    $pieces = array_filter($v['pieces'] ?? [], fn ($p) => ($p['statut'] ?? '') !== 'recue' && empty($p['facultative']));
    if ($pieces && in_array($etape, ['preparation', 'signature', 'en_vente', 'offre'], true)) {
        $a[] = [count($pieces) . ' pièce' . (count($pieces) > 1 ? 's' : '') . ' du vendeur à recevoir', 'Relances automatiques par e-mail ; le vendeur dépose ses papiers dans son espace.', 'pieces', '📎 Voir les pièces'];
    }
    return $a;
}

/** Documents du dossier, tels que listés dans l'onglet « Documents » : [clé, libellé, prêt ?, interne ?, route de l'écran]. */
function documents_dossier(array $v): array
{
    $docs = [
        ['vendeur', 'Compte rendu au vendeur', trim($v['rapport_vendeur'] ?? '') !== '', false, 'vendeur'],
        ['fiche', 'Fiche du bien', !empty($v['genere_le']), false, 'fiche'],
        ['annonce', 'Annonce', trim($v['annonce'] ?? '') !== '', false, 'annonce'],
        ['mandat', 'Mandat de vente', !empty($v['genere_le']), false, 'mandat'],
        ['rapport', 'Rapport interne', trim($v['rapport_agent'] ?? '') !== '', true, 'rapport'],
        ['dossier', 'Dossier complet', !empty($v['genere_le']), true, 'fiche'],
    ];
    global $DOCS_MODULES;
    foreach ($DOCS_MODULES ?? [] as $fn) foreach ($fn($v) as $d) $docs[] = $d;
    return $docs;
}

/** Un module déclare des documents supplémentaires (avis de valeur, plan, offre…) : fn (array $visit): array */
function document_module(callable $fn): void
{
    global $DOCS_MODULES;
    $DOCS_MODULES[] = $fn;
}

/** Ajoute à la visite le taux de complétude du dossier et la liste des champs obligatoires manquants. */
function avec_completude(array $visit): array
{
    $champs = (array) $visit['fiche']['champs'];
    $visit['completude'] = completude($champs);
    $visit['manquants'] = array_map(fn ($c) => ['cle' => $c['cle'], 'label' => $c['label']], champs_manquants($champs));
    $visit['mandat_manques'] = mandat_a_completer($visit, user_by_id($visit['agent'] ?? '') ?? current_user() ?? []);
    return $visit;
}

/** Vue complète d'un dossier pour l'appli : complétude, étape, actions, documents. */
function vue_dossier(array $visit): array
{
    $visit = avec_completude($visit);
    $visit['etape'] = etape_dossier($visit);
    $visit['etapes'] = array_map(fn ($e) => ['label' => $e[0], 'detail' => $e[1]], ETAPES);
    $visit['actions'] = prochaines_actions($visit);
    $pdfs = pdf_docs();
    $visit['documents'] = array_map(fn ($d) => ['cle' => $d[0], 'label' => $d[1], 'pret' => $d[2], 'interne' => $d[3], 'ecran' => $d[4], 'pdf' => isset($pdfs[$d[0]])], documents_dossier($visit));
    return $visit;
}

route('GET dossier', fn ($id) => send_json(vue_dossier(load_visit(require_user(), $id))));
