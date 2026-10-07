<?php
// Services extérieurs simulés pour les tests (php -S 127.0.0.1:8098 tests/services-simules.php).
// Réponses au format des vrais services : adresse (BAN/Géoplateforme), cadastre (API Carto IGN),
// Géorisques, DPE (ADEME, data-fair), ventes DVF (fichiers CSV Etalab), gel des avoirs (DG Trésor),
// signature électronique (API générique, firma.dev, BoldSign) et Gemini (generateContent).

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$q = $_GET;
file_put_contents('php://stderr', $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI'] . "\n");

function json(mixed $d, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

const LON = 6.3561;
const LAT = 47.0942;

// --- Adresse ---
if (str_starts_with($path, '/adresse/search') && ($q['type'] ?? '') !== 'municipality') {
    json(['type' => 'FeatureCollection', 'features' => [[
        'type' => 'Feature',
        'geometry' => ['type' => 'Point', 'coordinates' => [LON, LAT]],
        'properties' => ['label' => '11 Rue du Chênois 25260 Lougres', 'score' => 0.93, 'housenumber' => '11', 'street' => 'Rue du Chênois',
            'postcode' => '25260', 'citycode' => '25349', 'city' => 'Lougres', 'context' => '25, Doubs, Bourgogne-Franche-Comté'],
    ]]]);
}

// --- Cadastre ---
if (str_starts_with($path, '/cadastre/cadastre/parcelle')) {
    json(['type' => 'FeatureCollection', 'features' => [[
        'type' => 'Feature', 'geometry' => null,
        'properties' => ['numero' => '0142', 'section' => 'AB', 'code_insee' => '25349', 'nom_com' => 'Lougres', 'contenance' => 812, 'idu' => '25349000AB0142'],
    ]]]);
}

// --- Géorisques ---
if (str_starts_with($path, '/georisques/gaspar/risques')) {
    json(['results' => 1, 'data' => [['code_insee' => '25349', 'libelle_commune' => 'Lougres', 'risques_detail' => [
        ['num_risque' => '11', 'libelle_risque_long' => 'Inondation'],
        ['num_risque' => '134', 'libelle_risque_long' => 'Mouvement de terrain - Tassements différentiels'],
        ['num_risque' => '158', 'libelle_risque_long' => 'Séisme'],
    ]]]]);
}
if (str_starts_with($path, '/georisques/zonage_sismique')) json(['data' => [['code_zone' => '3', 'zone_sismicite' => '3 - Modérée']]]);
if (str_starts_with($path, '/georisques/radon')) json(['data' => [['classe_potentiel' => '1']]]);

// --- DPE (ADEME) ---
if (str_starts_with($path, '/dpe/lines') && str_contains((string) ($q['qs'] ?? ''), 'etiquette_dpe')) {
    // Prospection : logements classés F ou G d'une commune
    $rues = ['Rue des Tilleuls', 'Rue du Moulin', 'Grande Rue', 'Chemin des Vignes', 'Rue de la Gare', 'Impasse des Lilas', 'Rue Pasteur', 'Route de Besançon'];
    $res = [];
    foreach ($rues as $i => $r) {
        $res[] = ['adresse_ban' => (3 + $i * 4) . " $r 25260 Lougres", 'etiquette_dpe' => $i % 3 ? 'F' : 'G', 'etiquette_ges' => 'E', 'date_etablissement_dpe' => (2022 + $i % 3) . '-0' . (1 + $i % 9) . '-10',
            'type_batiment' => $i % 4 ? 'maison' : 'appartement', 'surface_habitable_logement' => 70 + $i * 9, 'annee_construction' => 1950 + $i * 3, '_geopoint' => (LAT + ($i - 4) * 0.002) . ',' . (LON + ($i % 3 - 1) * 0.003)];
    }
    json(['total' => count($res), 'results' => $res]);
}
if (str_starts_with($path, '/adresse/search') && ($q['type'] ?? '') === 'municipality') {
    json(['features' => [['geometry' => ['coordinates' => [LON, LAT]], 'properties' => ['label' => 'Lougres', 'city' => 'Lougres', 'citycode' => '25349', 'postcode' => '25260', 'score' => 0.97, 'type' => 'municipality', 'context' => '25, Doubs']]]]);
}
if (str_starts_with($path, '/dpe/lines')) {
    json(['total' => 1, 'results' => [[
        'numero_dpe' => '2325E0123456X', 'date_etablissement_dpe' => '2024-03-12', 'etiquette_dpe' => 'C', 'etiquette_ges' => 'A',
        'adresse_ban' => '11 Rue du Chênois 25260 Lougres', 'surface_habitable_logement' => 124.6, 'annee_construction' => 1978,
        'conso_5_usages_par_m2_ep' => 118, 'emission_ges_5_usages_par_m2' => 4, 'type_batiment' => 'maison',
    ]]]);
}

// --- DVF (CSV Etalab par commune et par année) ---
if (preg_match('#^/dvf/(\d{4})/communes/(\w+)/(\w+)\.csv$#', $path, $m)) {
    header('Content-Type: text/csv');
    $cols = 'id_mutation,date_mutation,numero_disposition,nature_mutation,valeur_fonciere,adresse_numero,adresse_suffixe,adresse_nom_voie,adresse_code_voie,code_postal,code_commune,nom_commune,code_departement,ancien_code_commune,ancien_nom_commune,id_parcelle,ancien_id_parcelle,numero_volume,lot1_numero,lot1_surface_carrez,lot2_numero,lot2_surface_carrez,lot3_numero,lot3_surface_carrez,lot4_numero,lot4_surface_carrez,lot5_numero,lot5_surface_carrez,nombre_lots,code_type_local,type_local,surface_reelle_bati,nombre_pieces_principales,code_nature_culture,nature_culture,code_nature_culture_speciale,nature_culture_speciale,surface_terrain,longitude,latitude';
    echo $cols . "\n";
    $an = (int) $m[1];
    $ventes = [[118, 5, 900, 352000, 0.004], [132, 6, 1200, 395000, -0.006], [96, 4, 650, 289000, 0.009], [140, 6, 1500, 431000, 0.012], [110, 5, 700, 338000, -0.011], [75, 3, 0, 185000, 0.002]];
    foreach ($ventes as $i => [$surf, $p, $terrain, $prix, $d]) {
        if (($i + $an) % 3 === 0 && $an < 2024) continue; // des années plus ou moins fournies
        $type = $terrain ? 'Maison' : 'Appartement';
        $code = $terrain ? 1 : 2;
        printf("%d-%d,%d-%02d-15,1,Vente,%d,%d,,RUE DES TILLEULS,B001,25260,25349,Lougres,25,,,25349000AB%04d,,,,,,,,,,,,,0,%d,%s,%d,%d,S,sols,,,%d,%.6f,%.6f\n",
            $an, $i, $an, ($i % 12) + 1, $prix + ($an - 2022) * 4000, 2 + $i, 100 + $i, $code, $type, $surf, $p, $terrain, LON + $d, LAT - $d / 2);
        if ($terrain) printf("%d-%d,%d-%02d-15,2,Vente,%d,%d,,RUE DES TILLEULS,B001,25260,25349,Lougres,25,,,25349000AB%04d,,,,,,,,,,,,,0,3,Dépendance,,,S,sols,,,0,%.6f,%.6f\n",
            $an, $i, $an, ($i % 12) + 1, $prix + ($an - 2022) * 4000, 2 + $i, 100 + $i, LON + $d, LAT - $d / 2);
    }
    exit;
}

// --- Gel des avoirs ---
if (str_starts_with($path, '/gels')) {
    json(['Publications' => ['DatePublication' => date('c'), 'PublicationDetail' => [
        ['IdRegistre' => 1, 'Nature' => 'Personne physique', 'Nom' => 'TESTGEL', 'RegistreDetail' => [
            ['TypeChamp' => 'PRENOM', 'Valeur' => [['Prenom' => 'Ivan']]],
            ['TypeChamp' => 'DATE_DE_NAISSANCE', 'Valeur' => [['Jour' => '01', 'Mois' => '02', 'Annee' => '1970']]],
        ]],
    ]]]);
}

// --- Signature électronique (contrat générique : voir PASSATION.md) ---
if (str_starts_with($path, '/signature/demandes')) {
    $in = json_decode((string) file_get_contents('php://input'), true) ?: [];
    json(['id' => 'sig_' . substr(md5((string) microtime(true)), 0, 10), 'statut' => 'envoye', 'signataires' => count($in['signataires'] ?? [])]);
}

// --- firma.dev (format de https://docs.firma.dev) : la dernière demande est enregistrée pour vérification ---
$T = sys_get_temp_dir() . '/visite-immo-test';
function pdf_signe_simule(): string
{
    return "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj 2 0 obj<</Type/Pages/Kids[]/Count 0>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF SIGNE-PAR-LE-SERVICE\n";
}
if (str_starts_with($path, '/firma/')) {
    if (($_SERVER['HTTP_AUTHORIZATION'] ?? '') !== 'Bearer cle-firma-test' && $path !== '/firma/final.pdf') json(['error' => 'Unauthorized'], 401);
    if ($path === '/firma/signing-requests/create-and-send') {
        $in = json_decode((string) file_get_contents('php://input'), true) ?: [];
        if (empty($in['document']) || empty($in['recipients'])) json(['error' => 'document and recipients required'], 400);
        file_put_contents("$T/firma-derniere.json", json_encode($in));
        $id = 'sr_' . substr(md5((string) microtime(true)), 0, 10);
        json(['id' => $id, 'name' => $in['name'], 'status' => 'draft', 'recipients' => array_map(fn ($r) => ['id' => 'rcp_' . $r['id'], 'email' => $r['email']], $in['recipients']), 'fields' => $in['fields'] ?? []], 201);
    }
    if (preg_match('#^/firma/signing-requests/([\w-]+)$#', $path, $m)) {
        $fini = is_file("$T/firma-fini");
        json(['id' => $m[1], 'status' => ['sent' => true, 'finished' => $fini, 'cancelled' => false, 'declined' => false, 'expired' => false],
            'final_document_download_url' => $fini ? 'http://127.0.0.1:8098/firma/final.pdf' : null]);
    }
    if ($path === '/firma/final.pdf') { header('Content-Type: application/pdf'); exit(pdf_signe_simule()); }
    if ($path === '/firma/webhooks') json(['id' => 'wh_1', 'url' => json_decode((string) file_get_contents('php://input'), true)['url'] ?? '', 'signing_secret' => 'secret-webhook-test'], 201);
}

// --- BoldSign (comme le projet Qualiopi) ---
if (str_starts_with($path, '/boldsign/')) {
    if (($_SERVER['HTTP_X_API_KEY'] ?? '') !== 'cle-boldsign-test') json(['error' => 'Unauthorized'], 401);
    if ($path === '/boldsign/v1/document/send') {
        // Lecture du multipart brut (le serveur simulé tourne sans analyse automatique : les clés « Signers[0].Name » restent intactes)
        $brut = (string) file_get_contents('php://input');
        preg_match_all('/name="([^"]+)"(?:; filename="[^"]*")?\r\n(?:[^\r\n]+\r\n)*\r\n(.*?)\r\n--/s', $brut, $m, PREG_SET_ORDER);
        $champs = [];
        foreach ($m as $c) $champs[$c[1]] = $c[1] === 'Files' ? substr($c[2], 0, 4) : $c[2];
        file_put_contents("$T/boldsign-derniere.json", json_encode(['champs' => $champs, 'fichier' => ($champs['Files'] ?? '') === '%PDF']));
        json(['documentId' => 'bs_' . substr(md5((string) microtime(true)), 0, 10)], 201);
    }
    if ($path === '/boldsign/v1/document/download') { header('Content-Type: application/pdf'); exit(pdf_signe_simule()); }
    if ($path === '/boldsign/v1/document/properties') json(['documentId' => $q['documentId'] ?? '', 'status' => is_file("$T/boldsign-fini") ? 'Completed' : 'InProgress']);
}

// --- Service de notifications push (enregistre le message chiffré pour vérification) ---
if (str_starts_with($path, '/push/')) {
    $dir = sys_get_temp_dir() . '/visite-immo-test/push';
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $n = count(glob("$dir/*.bin"));
    file_put_contents("$dir/$n.bin", file_get_contents('php://input'));
    file_put_contents("$dir/$n.json", json_encode(['authorization' => $_SERVER['HTTP_AUTHORIZATION'] ?? '', 'encoding' => $_SERVER['HTTP_CONTENT_ENCODING'] ?? '', 'ttl' => $_SERVER['HTTP_TTL'] ?? '']));
    http_response_code(201);
    exit;
}

// --- Gemini generateContent (réponses fixes, pour vérifier les appels réels) ---
if (preg_match('#^/gemini/models/([^:]+):generateContent$#', $path, $m)) {
    $in = json_decode((string) file_get_contents('php://input'), true) ?: [];
    $schema = $in['generationConfig']['responseSchema']['properties'] ?? [];
    $texte = 'Réponse simulée.';
    if ($schema) {
        $out = [];
        foreach ($schema as $k => $def) $out[$k] = match ($def['type'] ?? 'STRING') { 'ARRAY' => [], 'OBJECT' => new stdClass(), 'NUMBER', 'INTEGER' => 0, 'BOOLEAN' => false, default => "($k simulé)" };
        $texte = json_encode($out, JSON_UNESCAPED_UNICODE);
    }
    json(['candidates' => [['content' => ['parts' => [['text' => $texte]]], 'finishReason' => 'STOP']],
        'usageMetadata' => ['promptTokenCount' => 1200, 'candidatesTokenCount' => 300]]);
}

json(['error' => "Service simulé inconnu : $path"], 404);
