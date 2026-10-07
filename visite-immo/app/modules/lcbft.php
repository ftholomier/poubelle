<?php
// Contrôle anti-blanchiment (LCB-FT, articles L561-1 et suivants du code monétaire et financier) pour le vendeur
// et l'acquéreur : lecture de la pièce d'identité par l'IA, vérification sur le registre national des gels
// des avoirs (DG Trésor), questions (personne politiquement exposée, origine des fonds), niveau de vigilance,
// fiche de vigilance PDF conservée 5 ans.

const URL_GELS = 'https://gels-avoirs.dgtresor.gouv.fr/ApiPublic/api/v1/publication/derniere-publication-flux-json';
const ORIGINES_FONDS = ['epargne' => 'Épargne personnelle', 'pret' => 'Prêt bancaire', 'vente' => "Vente d'un bien", 'donation' => 'Donation / succession', 'autre' => 'Autre'];

function normaliser_nom(string $s): string
{
    return trim(preg_replace('/[^a-z ]+/', ' ', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: '')));
}

/** Registre des gels : téléchargé une fois par jour, réduit à [nom normalisé, prénoms, dates de naissance, nature]. */
function registre_gels(): ?array
{
    $cache = DATA_DIR . '/cache/gels.json';
    if (is_file($cache) && filemtime($cache) > time() - 86400) return read_json($cache, null);
    $r = http_get(api_base('gels', URL_GELS), 60);
    if (!$r) return is_file($cache) ? read_json($cache, null) : null;
    $liste = [];
    foreach ($r['Publications']['PublicationDetail'] ?? [] as $p) {
        if (stripos((string) ($p['Nature'] ?? ''), 'physique') === false) continue;
        $prenoms = [];
        $naissances = [];
        foreach ($p['RegistreDetail'] ?? [] as $d) {
            foreach ($d['Valeur'] ?? [] as $val) {
                if (($d['TypeChamp'] ?? '') === 'PRENOM') $prenoms[] = normaliser_nom((string) ($val['Prenom'] ?? ''));
                if (($d['TypeChamp'] ?? '') === 'DATE_DE_NAISSANCE') $naissances[] = sprintf('%04d-%02d-%02d', (int) ($val['Annee'] ?? 0), (int) ($val['Mois'] ?? 0), (int) ($val['Jour'] ?? 0));
            }
        }
        $liste[] = ['nom' => normaliser_nom((string) ($p['Nom'] ?? '')), 'prenoms' => $prenoms, 'naissances' => $naissances, 'id' => $p['IdRegistre'] ?? null];
    }
    write_json($cache, $liste);
    return $liste;
}

/** Recherche une personne dans le registre : [statut (aucune / homonyme / correspondance / indisponible), détail]. */
function verifier_gels(string $nom, string $prenom, string $naissance): array
{
    $reg = registre_gels();
    if ($reg === null) return ['statut' => 'indisponible', 'detail' => 'Registre des gels injoignable : vérification à refaire.', 'le' => date('c')];
    $n = normaliser_nom($nom);
    $p = normaliser_nom(explode(' ', trim($prenom))[0] ?? '');
    $d = date_iso($naissance);
    foreach ($reg as $x) {
        if ($x['nom'] !== $n) continue;
        $memePrenom = $p !== '' && in_array($p, array_map(fn ($y) => explode(' ', $y)[0], $x['prenoms']), true);
        $memeDate = $d !== '' && in_array($d, $x['naissances'], true);
        if ($memePrenom && ($memeDate || !$x['naissances'])) return ['statut' => 'correspondance', 'detail' => "Personne inscrite au registre des gels (réf. {$x['id']}). Ne pas poursuivre l'opération : déclaration à TRACFIN et à la DG Trésor.", 'le' => date('c')];
        if ($memePrenom || !$p) return ['statut' => 'homonyme', 'detail' => 'Homonyme dans le registre (nom identique, autres éléments différents) : vérifiez la date de naissance.', 'le' => date('c')];
    }
    return ['statut' => 'aucune', 'detail' => 'Aucune correspondance au registre national des gels.', 'le' => date('c')];
}

/** Lecture de la pièce d'identité par l'IA (démo : valeurs simulées). */
function lire_piece_identite(string $chemin, string $mime, string $partie): array
{
    global $CONFIG;
    if (empty($CONFIG['gemini_api_key'])) {
        return $partie === 'vendeur'
            ? ['type' => "Carte nationale d'identité", 'nom' => 'MARTIN', 'prenoms' => 'Claire Anne', 'naissance' => '12/04/1961', 'lieu_naissance' => 'Besançon', 'nationalite' => 'Française', 'numero' => 'X4RT8S2P1', 'expiration' => '15/06/2031']
            : ['type' => "Carte nationale d'identité", 'nom' => 'MOREAU', 'prenoms' => 'Julien', 'naissance' => '03/09/1986', 'lieu_naissance' => 'Lyon', 'nationalite' => 'Française', 'numero' => 'Z9KD2L7Q4', 'expiration' => '22/01/2033'];
    }
    $txt = gemini_generate($CONFIG['modele_analyse'], [
        'contents' => [['role' => 'user', 'parts' => [
            ['text' => "Pièce d'identité (carte d'identité, passeport ou titre de séjour). Recopie exactement les informations, dates au format JJ/MM/AAAA. Si un champ est illisible, laisse-le vide."],
            ['inline_data' => ['mime_type' => $mime, 'data' => base64_encode((string) file_get_contents($chemin))]],
        ]]],
        'generationConfig' => ['responseMimeType' => 'application/json', 'responseSchema' => s_obj(['type' => s_txt(), 'nom' => s_txt(), 'prenoms' => s_txt(), 'naissance' => s_txt(), 'lieu_naissance' => s_txt(), 'nationalite' => s_txt(), 'numero' => s_txt(), 'expiration' => s_txt()], ['nom', 'prenoms'])],
    ], 60);
    return json_decode($txt, true) ?: [];
}

/** Niveau de vigilance selon les éléments recueillis. */
function niveau_vigilance(array $c): array
{
    $raisons = [];
    $niveau = 'standard';
    if (($c['gels']['statut'] ?? '') === 'correspondance') return ['interdit', ['Personne inscrite au registre des gels des avoirs']];
    if (!empty($c['ppe'])) { $niveau = 'renforcée'; $raisons[] = 'Personne politiquement exposée'; }
    if (($c['origine'] ?? '') === 'autre' || ($c['comptant_important'] ?? false)) { $niveau = 'renforcée'; $raisons[] = 'Origine des fonds à justifier'; }
    if (!empty($c['pays']) && !preg_match('/france|union europ|^ue$/i', $c['pays'])) { $niveau = 'renforcée'; $raisons[] = 'Résidence hors Union européenne'; }
    $exp = date_iso($c['identite']['expiration'] ?? '');
    if ($exp && $exp < date('Y-m-d')) $raisons[] = "Pièce d'identité expirée : en demander une valide";
    if (($c['gels']['statut'] ?? '') === 'homonyme') $raisons[] = 'Homonyme au registre des gels à lever';
    if (!$raisons) { $niveau = 'simplifiée'; $raisons[] = 'Aucun facteur de risque identifié'; }
    return [$niveau, $raisons];
}

function rendre_vigilance(VisitePdf $pdf, array $v, array $agent, array $options = []): void
{
    global $CONFIG;
    $partie = ($options['partie'] ?? 'acquereur') === 'vendeur' ? 'vendeur' : 'acquereur';
    $c = $v['lcbft'][$partie] ?? throw new RuntimeException('Contrôle non réalisé.');
    $pdf->docLabel = 'Fiche de vigilance LCB-FT';
    $pdf->AddPage();
    $pdf->titleBlock('Fiche de vigilance', ucfirst($partie) . ' · ' . titre_bien($v), 'Contrôle du ' . fmt_date_fr($c['le']) . ' par ' . $agent['nom'] . ' · ' . (($CONFIG['raison_sociale'] ?? '') ?: $CONFIG['agence']), mb_strtoupper('vigilance ' . $c['niveau']));
    $i = $c['identite'] ?? [];
    $pdf->sectionTitle('Identification');
    $pdf->kvGrid([
        ['label' => 'Nom', 'valeur' => $i['nom'] ?? '—', 'long' => false], ['label' => 'Prénoms', 'valeur' => $i['prenoms'] ?? '—', 'long' => false],
        ['label' => 'Né(e) le', 'valeur' => trim(($i['naissance'] ?? '') . ' à ' . ($i['lieu_naissance'] ?? ''), ' à') ?: '—', 'long' => false], ['label' => 'Nationalité', 'valeur' => $i['nationalite'] ?? '—', 'long' => false],
        ['label' => 'Pièce', 'valeur' => trim(($i['type'] ?? '') . ' n° ' . ($i['numero'] ?? '')), 'long' => false], ['label' => 'Expire le', 'valeur' => $i['expiration'] ?? '—', 'long' => false],
    ]);
    $pdf->sectionTitle('Vérifications');
    $pdf->kvGrid([
        ['label' => 'Registre national des gels', 'valeur' => $c['gels']['detail'] . ' (' . date('d/m/Y H:i', strtotime($c['gels']['le'])) . ')', 'long' => true],
        ['label' => 'Personne politiquement exposée', 'valeur' => !empty($c['ppe']) ? 'Oui' : 'Non (déclaration)', 'long' => false],
        ['label' => 'Pays de résidence', 'valeur' => $c['pays'] ?: 'France', 'long' => false],
        ['label' => 'Origine des fonds', 'valeur' => (ORIGINES_FONDS[$c['origine'] ?? ''] ?? '—') . (($c['origine_detail'] ?? '') ? ' · ' . $c['origine_detail'] : ''), 'long' => true],
    ]);
    $pdf->sectionTitle('Analyse du risque');
    $pdf->richText("Niveau de vigilance : " . mb_strtoupper($c['niveau']) . "\n" . implode("\n", array_map(fn ($r) => "- $r", $c['raisons'])));
    $pdf->Ln(2);
    $pdf->font('I', 8, C_GRIS);
    $pdf->MultiCell(0, 4.2, "Document établi en application des articles L561-5 à L561-14-2 et R561-5 et suivants du code monétaire et financier. À conserver 5 ans après la fin de la relation d'affaires (article L561-12). En cas de soupçon : déclaration à TRACFIN, sans en informer le client.");
}

pdf_module('vigilance', 'Fiche de vigilance', 'rendre_vigilance', null, false);

route('POST lcbft', function ($id) {
    $me = require_user();
    $partie = ($_POST['partie'] ?? '') === 'vendeur' ? 'vendeur' : 'acquereur';
    $v = load_visit($me, $id);
    $c = $v['lcbft'][$partie] ?? [];
    // 1. Pièce d'identité (facultative si déjà lue)
    $f = $_FILES['piece'] ?? null;
    if ($f && ($f['error'] ?? 1) === UPLOAD_ERR_OK) {
        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        if (!isset(PIECES_EXTENSIONS[$mime])) fail(415, 'Envoyez une photo ou un PDF de la pièce.');
        $dir = visit_dir($me, $id) . '/lcbft';
        if (!is_dir($dir)) mkdir($dir, 0770, true);
        $nom = "$partie-" . date('ymd-His') . '.' . PIECES_EXTENSIONS[$mime];
        move_uploaded_file($f['tmp_name'], "$dir/$nom");
        $c['identite'] = lire_piece_identite("$dir/$nom", $mime, $partie) + ['fichier' => $nom];
        flush_usage($me, $id, 'analyse');
    }
    // 2. Réponses aux questions
    foreach (['ppe' => fn ($x) => $x === '1' || $x === 'true', 'pays' => fn ($x) => mb_substr(trim((string) $x), 0, 60), 'origine' => fn ($x) => isset(ORIGINES_FONDS[$x]) ? $x : 'epargne', 'origine_detail' => fn ($x) => mb_substr(trim((string) $x), 0, 300)] as $k => $fn) {
        if (isset($_POST[$k])) $c[$k] = $fn($_POST[$k]);
    }
    if (empty($c['identite'])) fail(400, "Ajoutez d'abord la pièce d'identité.");
    // 3. Registre des gels, niveau de vigilance
    $c['gels'] = verifier_gels($c['identite']['nom'] ?? '', $c['identite']['prenoms'] ?? '', $c['identite']['naissance'] ?? '');
    [$c['niveau'], $c['raisons']] = niveau_vigilance($c);
    $c['le'] = date('c');
    $v = update_visit($me, $id, function (array $v) use ($partie, $c) {
        $v['lcbft'][$partie] = $c;
        journal_ajout($v, 'lcbft', "Contrôle LCB-FT ($partie) : vigilance {$c['niveau']} · {$c['gels']['detail']}");
        return $v;
    });
    send_json(vue_dossier($v));
});

a_faire('lcbft', function (array $agent, array $dossiers): array {
    $items = [];
    foreach ($dossiers as $v) {
        if (empty($v['vente']['debut']) || !empty($v['vente']['acte_le'])) continue;
        foreach (['vendeur', 'acquereur'] as $p) {
            $c = $v['lcbft'][$p] ?? null;
            if (!$c) $items[] = ['type' => 'tache', 'titre' => "Contrôle anti-blanchiment du $p", 'detail' => titre_bien($v) . " · photo de la pièce d'identité, 1 minute", 'lien' => "#/visite/{$v['id']}/vente", 'priorite' => 2];
            elseif ($c['niveau'] === 'interdit') $items[] = ['type' => 'tache', 'titre' => "⛔ Registre des gels : $p", 'detail' => titre_bien($v) . ' · opération à suspendre', 'lien' => "#/visite/{$v['id']}/vente", 'priorite' => 1];
        }
    }
    return $items;
});
