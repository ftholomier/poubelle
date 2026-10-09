<?php
// Pièces à demander au vendeur : liste calculée d'après le bien (copropriété, année, situation familiale,
// location…), dépôt par le vendeur dans son espace ou par l'agent (photo ou PDF), lecture par l'IA qui
// remplit la fiche (origine de propriété, lots, charges…), et relances automatiques par e-mail.

const PIECES_EXTENSIONS = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/heic' => 'heic'];

/** Pièces nécessaires pour ce dossier : [clé, libellé, aide, facultative]. */
function pieces_requises(array $v): array
{
    $annee = (int) champ($v, 'annee_construction');
    $copro = champ($v, 'copropriete') === 'oui' || champ($v, 'type_bien') === 'Appartement';
    $l = [
        ['identite', "Pièce d'identité du ou des vendeurs", 'Carte d\'identité ou passeport en cours de validité', false],
        ['titre', 'Titre de propriété', "L'acte notarié d'achat (ou attestation de propriété)", false],
        ['taxe_fonciere', 'Dernier avis de taxe foncière', '', false],
        ['diagnostics', 'Diagnostics immobiliers (DDT)', 'DPE, électricité et gaz (installations de plus de 15 ans)' . ($annee && $annee < 1997 ? ', amiante' : '') . ($annee && $annee < 1949 ? ', plomb' : '') . ', termites selon zone', false],
    ];
    if ($copro) {
        $l[] = ['reglement_copro', 'Règlement de copropriété et état descriptif de division', '', false];
        $l[] = ['pv_ag', "Procès-verbaux des 3 dernières assemblées générales", '', false];
        $l[] = ['fiche_synthetique', 'Fiche synthétique de la copropriété', 'Fournie par le syndic', false];
        $l[] = ['charges', 'Relevé des charges des 2 dernières années', '', false];
        $l[] = ['carnet', "Carnet d'entretien de l'immeuble", '', true];
    }
    $sit = champ($v, 'situation_vendeur');
    if ($sit === 'Marié(e)' || $sit === 'Pacsé(e)') $l[] = ['etat_civil', $sit === 'Marié(e)' ? 'Contrat de mariage ou livret de famille' : 'Convention de PACS', '', false];
    if (champ($v, 'occupation') === 'Loué') $l[] = ['bail', 'Bail en cours et dernières quittances', '', false];
    if (preg_match('/\b(20[12]\d)\b/u', champ($v, 'travaux_realises'))) $l[] = ['factures_travaux', 'Factures des travaux et assurance décennale', 'Pour les travaux de moins de 10 ans', false];
    if (champ($v, 'type_bien') === 'Maison') $l[] = ['assainissement', "Contrôle de l'assainissement", 'Si la maison n\'est pas raccordée au tout-à-l\'égout', true];
    return $l;
}

/** Met à jour la liste des pièces du dossier sans perdre celles déjà reçues. */
function actualiser_pieces(array $v): array
{
    $actuelles = [];
    foreach ($v['pieces'] ?? [] as $p) $actuelles[$p['cle']] = $p;
    $liste = [];
    foreach (pieces_requises($v) as [$cle, $label, $aide, $fac]) {
        $liste[] = ($actuelles[$cle] ?? ['cle' => $cle, 'statut' => 'manquante', 'fichiers' => []]) + ['label' => $label, 'aide' => $aide, 'facultative' => $fac];
        $liste[count($liste) - 1]['label'] = $label;
        $liste[count($liste) - 1]['aide'] = $aide;
        unset($actuelles[$cle]);
    }
    foreach ($actuelles as $p) if (!empty($p['fichiers'])) $liste[] = $p; // pièces reçues hors liste : on les garde
    $v['pieces'] = $liste;
    return $v;
}

function pieces_manquantes(array $v): array
{
    return array_values(array_filter($v['pieces'] ?? [], fn ($p) => ($p['statut'] ?? '') !== 'recue' && empty($p['facultative'])));
}

// ---------- Lecture d'un document par l'IA ----------

function lecture_schema(): array
{
    $t = ['type' => 'STRING'];
    return [
        'type' => 'OBJECT',
        'properties' => [
            'type_document' => ['type' => 'STRING', 'enum' => array_merge(array_map(fn ($p) => $p[0], PIECES_TOUTES), ['autre'])],
            'resume' => $t,
            'champs' => ['type' => 'ARRAY', 'items' => ['type' => 'OBJECT', 'properties' => ['cle' => ['type' => 'STRING', 'enum' => field_keys()], 'valeur' => $t, 'citation' => $t], 'required' => ['cle', 'valeur', 'citation']]],
            'alertes' => ['type' => 'ARRAY', 'items' => $t],
        ],
        'required' => ['type_document', 'resume', 'champs', 'alertes'],
    ];
}

const PIECES_TOUTES = [['identite'], ['titre'], ['taxe_fonciere'], ['diagnostics'], ['reglement_copro'], ['pv_ag'], ['fiche_synthetique'], ['charges'], ['carnet'], ['etat_civil'], ['bail'], ['factures_travaux'], ['assainissement']];

/** Fait lire un document par Gemini (image ou PDF) : type de pièce, résumé, champs de la fiche, points d'alerte. */
function lire_document(string $chemin, string $mime, array $v): array
{
    global $CONFIG;
    if (empty($CONFIG['gemini_api_key'])) return lecture_demo($chemin, $v);
    $prompt = "Tu aides un agent immobilier. Voici un document fourni par le vendeur du bien. Identifie de quelle pièce il s'agit, résume-la en une phrase, "
        . "et extrais uniquement les informations utiles au dossier de vente (champs ci-dessous), avec la citation exacte du document. "
        . "Signale dans « alertes » tout point de vigilance (procédure en cours, travaux votés en AG, servitude, diagnostic défavorable, pièce périmée, incohérence avec le dossier).\n\n"
        . "Champs possibles :\n" . fields_prompt() . "\n\nDossier actuel : " . titre_bien($v);
    $txt = gemini_generate($CONFIG['modele_analyse'], [
        'contents' => [['role' => 'user', 'parts' => [['text' => $prompt], ['inline_data' => ['mime_type' => $mime, 'data' => base64_encode((string) file_get_contents($chemin))]]]]],
        'generationConfig' => ['responseMimeType' => 'application/json', 'responseSchema' => lecture_schema()],
    ], 120);
    $r = json_decode($txt, true);
    if (!is_array($r)) throw new RuntimeException('Lecture du document impossible.');
    return $r;
}

/** Mode démo : on reconnaît la pièce d'après le nom du fichier et on simule quelques informations lues. */
function lecture_demo(string $chemin, array $v): array
{
    $nom = mb_strtolower(basename($chemin));
    $r = ['type_document' => 'autre', 'resume' => 'Document reçu (lecture simulée en mode démo).', 'champs' => [], 'alertes' => []];
    if (preg_match('/titre|acte|propri/u', $nom)) {
        $r = ['type_document' => 'titre', 'resume' => "Acte de vente reçu par Maître Lefèvre, notaire, le 14 mars 2005.", 'alertes' => [],
            'champs' => [['cle' => 'origine_propriete', 'valeur' => 'Acquisition par acte du 14/03/2005 reçu par Me Lefèvre, notaire à Besançon', 'citation' => 'Acte reçu le 14 mars 2005 par Maître Lefèvre']]];
    } elseif (preg_match('/tax|fonci/u', $nom)) {
        $r = ['type_document' => 'taxe_fonciere', 'resume' => 'Avis de taxe foncière 2025.', 'alertes' => [], 'champs' => [['cle' => 'taxe_fonciere', 'valeur' => '1452', 'citation' => 'Montant de votre impôt : 1 452 €']]];
    } elseif (preg_match('/dpe|diag/u', $nom)) {
        $r = ['type_document' => 'diagnostics', 'resume' => 'Dossier de diagnostic technique (DPE, électricité, amiante).', 'alertes' => ["Électricité : 2 anomalies à signaler à l'acquéreur"],
            'champs' => [['cle' => 'diagnostics', 'valeur' => 'DPE, électricité (2 anomalies), amiante (absence)', 'citation' => 'Constat de risque : 2 anomalies']]];
    } elseif (preg_match('/ag|assembl|pv/u', $nom)) {
        $r = ['type_document' => 'pv_ag', 'resume' => "Procès-verbal d'assemblée générale 2025.", 'alertes' => ['Ravalement de façade voté : 4 800 € à la charge du lot, appel en 2026'], 'champs' => []];
    } elseif (preg_match('/cni|identit|passeport/u', $nom)) {
        $r = ['type_document' => 'identite', 'resume' => "Carte nationale d'identité.", 'alertes' => [], 'champs' => []];
    }
    return $r;
}

/** Enregistre un fichier déposé pour une pièce, le fait lire par l'IA et met à jour la fiche. */
function deposer_piece(array $agent, string $id, array $fichier, string $cle, string $par): array
{
    if (($fichier['error'] ?? 1) !== UPLOAD_ERR_OK) fail(400, 'Envoi du fichier incomplet.');
    if ($fichier['size'] > 20 * 1024 * 1024) fail(413, 'Fichier trop lourd (20 Mo maximum).');
    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($fichier['tmp_name']);
    if (!isset(PIECES_EXTENSIONS[$mime])) fail(415, 'Format non accepté : envoyez un PDF ou une photo.');
    $dir = visit_dir($agent, $id) . '/pieces';
    if (!is_dir($dir)) mkdir($dir, 0770, true);
    $base = slug(pathinfo((string) $fichier['name'], PATHINFO_FILENAME), 40);
    $nom = date('ymd-His') . "-$base." . PIECES_EXTENSIONS[$mime];
    if (is_uploaded_file($fichier['tmp_name'])) move_uploaded_file($fichier['tmp_name'], "$dir/$nom");
    else rename($fichier['tmp_name'], "$dir/$nom");

    $v = load_visit($agent, $id);
    $lecture = null;
    try {
        $lecture = lire_document("$dir/$nom", $mime, $v);
        flush_usage($agent, $id, 'analyse');
    } catch (Throwable $e) {
        $lecture = ['type_document' => 'autre', 'resume' => 'Lecture automatique impossible : ' . $e->getMessage(), 'champs' => [], 'alertes' => []];
    }
    if ($cle === '' || $cle === 'auto') $cle = $lecture['type_document'] !== 'autre' ? $lecture['type_document'] : 'autre';

    $modifies = [];
    $v = update_visit($agent, $id, function (array $v) use ($cle, $nom, $lecture, $par, $base, &$modifies) {
        $v = actualiser_pieces($v);
        $trouve = false;
        foreach ($v['pieces'] as &$p) {
            if ($p['cle'] !== $cle) continue;
            $p['fichiers'][] = ['nom' => $nom, 'depose_le' => date('c'), 'par' => $par, 'resume' => $lecture['resume'], 'alertes' => $lecture['alertes']];
            $p['statut'] = 'recue';
            $trouve = true;
        }
        unset($p);
        if (!$trouve) $v['pieces'][] = ['cle' => $cle === 'autre' ? 'autre-' . substr(md5($nom), 0, 6) : $cle, 'label' => $cle === 'autre' ? 'Autre document : ' . $base : $cle, 'aide' => '', 'facultative' => true, 'statut' => 'recue',
            'fichiers' => [['nom' => $nom, 'depose_le' => date('c'), 'par' => $par, 'resume' => $lecture['resume'], 'alertes' => $lecture['alertes']]]];
        // Les informations lues complètent la fiche (sans écraser une valeur validée par l'agent)
        $champs = (array) $v['fiche']['champs'];
        $n = 0;
        foreach ($lecture['champs'] as $c) {
            if (!in_array($c['cle'], field_keys(), true) || trim((string) $c['valeur']) === '') continue;
            if (in_array($champs[$c['cle']]['source'] ?? '', ['agent', 'dialogue'], true)) continue;
            $champs[$c['cle']] = ['valeur' => trim((string) $c['valeur']), 'citation' => (string) $c['citation'], 'source' => 'document'];
            $modifies[] = $c['cle'];
            $n++;
        }
        $v['fiche']['champs'] = $champs ?: new stdClass();
        foreach ($lecture['alertes'] as $al) $v['alertes'][] = ['date' => date('c'), 'texte' => $al, 'source' => $nom];
        journal_ajout($v, 'piece', ($par === 'vendeur' ? 'Le vendeur a déposé' : 'Pièce ajoutée') . " : {$lecture['resume']}" . ($n ? " · $n information(s) reportée(s) dans la fiche." : '') . ($lecture['alertes'] ? ' · ⚠ ' . implode(' ; ', $lecture['alertes']) : ''));
        return $v;
    });
    if ($modifies) {
        champs_modifies($agent, $id, $modifies); // ex. : surface lue dans un document → estimation recalculée
        $v = load_visit($agent, $id);
    }
    return $v;
}

// ---------- Relances automatiques ----------

/** Relance le vendeur à J+2, J+5, J+10 puis toutes les semaines (5 fois au plus) tant qu'il manque des pièces. */
tache_cron('relances_pieces', function (array $agent, array $dossiers): int {
    $n = 0;
    foreach ($dossiers as $v) {
        if (empty($v['envoi_vendeur']['date']) || in_array(etape_dossier($v), ['vendu', 'compromis'], true)) continue;
        $manque = pieces_manquantes($v);
        $email = champ($v, 'email_vendeur');
        if (!$manque || !valid_email($email)) continue;
        $relances = $v['relances_pieces'] ?? [];
        if (count($relances) >= 5) continue;
        $jours = [2, 5, 10, 17, 24][count($relances)];
        if (maintenant() < strtotime($v['envoi_vendeur']['date']) + $jours * 86400) continue;
        if (date('G', maintenant()) < 9 || date('G', maintenant()) >= 19 || date('N', maintenant()) == 7) continue; // heures ouvrables

        $lien = url_publique('espace/?t=' . lien_pour($agent, $v['id'], 'vendeur'));
        $civ = trim(champ($v, 'civilite_vendeur') . ' ' . champ($v, 'nom_vendeur'));
        $texte = "Bonjour" . ($civ ? " $civ" : '') . ",\n\nPour avancer sur la vente de votre bien, il me manque encore " . (count($manque) > 1 ? 'les documents suivants' : 'le document suivant') . " :\n"
            . implode("\n", array_map(fn ($p) => '- ' . $p['label'], $manque))
            . "\n\nVous pouvez les déposer en quelques secondes depuis votre espace, en photo depuis votre téléphone ou en PDF :\n$lien\n\nMerci beaucoup,\n" . signature_agent($agent);
        try {
            if (!envoyer_mail_agent($agent, $email, 'Documents pour la vente de votre bien · ' . titre_bien($v), $texte)) continue;
        } catch (Throwable $e) {
            error_log('relance pièces : ' . $e->getMessage());
            continue;
        }
        update_visit($agent, $v['id'], function (array $x) use ($manque) {
            $x['relances_pieces'][] = date('c', maintenant());
            journal_ajout($x, 'relance', 'Relance automatique du vendeur : ' . count($manque) . ' pièce(s) manquante(s).');
            return $x;
        });
        $n++;
    }
    return $n;
});

a_faire('pieces', function (array $agent, array $dossiers): array {
    $items = [];
    foreach ($dossiers as $v) {
        foreach ($v['alertes'] ?? [] as $al) {
            if (!empty($al['vu'])) continue;
            $items[] = ['type' => 'piece', 'titre' => '⚠ ' . $al['texte'], 'detail' => titre_bien($v) . ' · lu dans un document du vendeur', 'lien' => "#/visite/{$v['id']}/pieces", 'priorite' => 1, 'date' => $al['date']];
        }
        foreach (array_slice($v['journal'] ?? [], -5) as $j) {
            if ($j['type'] === 'piece' && strtotime($j['date']) > time() - 86400 * 2) $items[] = ['type' => 'info', 'titre' => $j['texte'], 'detail' => titre_bien($v), 'lien' => "#/visite/{$v['id']}/pieces", 'priorite' => 3, 'date' => $j['date']];
        }
    }
    return $items;
});

// ---------- API ----------

route('GET pieces', function ($id) {
    $me = require_user();
    $v = actualiser_pieces(load_visit($me, $id));
    send_json(['pieces' => $v['pieces'], 'alertes' => $v['alertes'] ?? [], 'relances' => $v['relances_pieces'] ?? []]);
});

route('POST pieces', function ($id) {
    $me = require_user();
    $v = deposer_piece($me, $id, $_FILES['fichier'] ?? [], (string) ($_POST['cle'] ?? 'auto'), 'agent');
    $v = actualiser_pieces($v);
    send_json(['pieces' => $v['pieces'], 'alertes' => $v['alertes'] ?? []]);
});

route('POST alertes_vues', function ($id) {
    $me = require_user();
    update_visit($me, $id, function (array $v) {
        foreach ($v['alertes'] ?? [] as $i => $a) $v['alertes'][$i]['vu'] = true;
        return $v;
    });
    send_json(['ok' => true]);
});

route('GET piece', function ($id) {
    $me = require_user();
    servir_piece(visit_dir($me, $id), (string) ($_GET['f'] ?? ''));
});

function servir_piece(string $dossier, string $nom): never
{
    if (!preg_match('/^[a-z0-9._-]{5,90}$/', $nom) || !is_file("$dossier/pieces/$nom")) fail(404, 'Pièce introuvable.');
    $chemin = "$dossier/pieces/$nom";
    header('Content-Type: ' . (new finfo(FILEINFO_MIME_TYPE))->file($chemin));
    header('Content-Disposition: inline; filename="' . $nom . '"');
    header('Cache-Control: private, max-age=600');
    readfile($chemin);
    exit;
}
