<?php
// Dossier technique automatique à partir de l'adresse, avec des données publiques gratuites :
//   - adresse normalisée et coordonnées : Base Adresse Nationale (Géoplateforme IGN)
//   - parcelle cadastrale et contenance : API Carto IGN (cadastre)
//   - risques naturels et technologiques, sismicité, radon : Géorisques (BRGM)
//   - DPE déjà enregistré pour ce logement : ADEME (DPE logements existants depuis 2021)
//   - ventes réelles alentour : DVF géolocalisées (Etalab, fichiers par commune)
// Si un service ne répond pas, des valeurs simulées (marquées comme telles) permettent de tester le parcours.

const URL_ADRESSE = 'https://data.geopf.fr/geocodage';
const URL_CADASTRE = 'https://apicarto.ign.fr/api';
const URL_GEORISQUES = 'https://georisques.gouv.fr/api/v1';
const URL_DPE = 'https://data.ademe.fr/data-fair/api/v1/datasets/dpe03existant';
const URL_DVF = 'https://files.data.gouv.fr/geo-dvf/latest/csv';

function geocoder(string $adresse): ?array
{
    if (trim($adresse) === '') return null;
    $r = http_get(api_base('adresse', URL_ADRESSE) . '/search/?' . http_build_query(['q' => $adresse, 'limit' => 1]));
    $f = $r['features'][0] ?? null;
    if (!$f || ($f['properties']['score'] ?? 0) < 0.4) return null;
    $p = $f['properties'];
    return [
        'lon' => (float) $f['geometry']['coordinates'][0], 'lat' => (float) $f['geometry']['coordinates'][1],
        'label' => $p['label'] ?? $adresse, 'citycode' => $p['citycode'] ?? '', 'postcode' => $p['postcode'] ?? '',
        'city' => $p['city'] ?? '', 'score' => round((float) ($p['score'] ?? 0), 2),
    ];
}

function cadastre_parcelle(float $lon, float $lat): ?array
{
    $geom = json_encode(['type' => 'Point', 'coordinates' => [$lon, $lat]]);
    $r = http_get(api_base('cadastre', URL_CADASTRE) . '/cadastre/parcelle?' . http_build_query(['geom' => $geom]));
    $p = $r['features'][0]['properties'] ?? null;
    if (!$p) return null;
    return [
        'section' => ltrim((string) ($p['section'] ?? ''), '0'), 'numero' => ltrim((string) ($p['numero'] ?? ''), '0'),
        'idu' => $p['idu'] ?? '', 'contenance' => (int) ($p['contenance'] ?? 0), 'commune' => $p['nom_com'] ?? '',
    ];
}

function georisques(float $lon, float $lat, string $citycode): ?array
{
    $base = api_base('georisques', URL_GEORISQUES);
    $ll = "$lon,$lat";
    $r = http_get("$base/gaspar/risques?" . http_build_query(['latlon' => $ll]));
    if ($r === null) return null;
    $liste = [];
    foreach ($r['data'] ?? [] as $commune) foreach ($commune['risques_detail'] ?? [] as $d) $liste[] = $d['libelle_risque_long'] ?? '';
    $sis = http_get("$base/zonage_sismique?" . http_build_query(['latlon' => $ll]));
    $radon = $citycode ? http_get("$base/radon?" . http_build_query(['code_insee' => $citycode])) : null;
    return [
        'liste'     => array_values(array_unique(array_filter($liste))),
        'sismicite' => $sis['data'][0]['zone_sismicite'] ?? '',
        'radon'     => isset($radon['data'][0]['classe_potentiel']) ? 'Catégorie ' . $radon['data'][0]['classe_potentiel'] : '',
        'lien'      => 'https://www.georisques.gouv.fr/mes-risques/connaitre-les-risques-pres-de-chez-moi',
    ];
}

/** DPE enregistré à l'ADEME pour ce logement (le plus récent à cette adresse). */
function dpe_enregistre(float $lon, float $lat, string $label): ?array
{
    $base = api_base('dpe', URL_DPE);
    $select = 'numero_dpe,date_etablissement_dpe,etiquette_dpe,etiquette_ges,adresse_ban,surface_habitable_logement,annee_construction,conso_5_usages_par_m2_ep,emission_ges_5_usages_par_m2,type_batiment';
    $r = http_get("$base/lines?" . http_build_query(['size' => 5, 'select' => $select, 'geo_distance' => "$lon:$lat:30", 'sort' => '-date_etablissement_dpe']));
    if (empty($r['results'])) {
        $r = http_get("$base/lines?" . http_build_query(['size' => 5, 'select' => $select, 'q' => $label, 'q_fields' => 'adresse_ban', 'sort' => '-date_etablissement_dpe']));
    }
    if ($r === null) return null;
    $d = $r['results'][0] ?? null;
    if (!$d) return ['trouve' => false];
    return [
        'trouve' => true, 'numero' => $d['numero_dpe'] ?? '', 'date' => $d['date_etablissement_dpe'] ?? '',
        'dpe' => $d['etiquette_dpe'] ?? '', 'ges' => $d['etiquette_ges'] ?? '', 'adresse' => $d['adresse_ban'] ?? '',
        'surface' => $d['surface_habitable_logement'] ?? null, 'annee' => $d['annee_construction'] ?? null,
        'conso' => $d['conso_5_usages_par_m2_ep'] ?? null, 'emission' => $d['emission_ges_5_usages_par_m2'] ?? null,
    ];
}

function departement(string $citycode): string
{
    return str_starts_with($citycode, '97') ? substr($citycode, 0, 3) : substr($citycode, 0, 2);
}

function distance_m(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $r = 6371000;
    $p1 = deg2rad($lat1); $p2 = deg2rad($lat2);
    $dp = $p2 - $p1; $dl = deg2rad($lon2 - $lon1);
    $a = sin($dp / 2) ** 2 + cos($p1) * cos($p2) * sin($dl / 2) ** 2;
    return 2 * $r * asin(min(1, sqrt($a)));
}

/**
 * Ventes réelles (DVF) de la commune sur les dernières années, une ligne par mutation : prix, surface bâtie,
 * pièces, terrain, distance. Les fichiers par commune sont mis en cache 30 jours, la lecture du CSV 1 jour.
 */
function ventes_dvf(string $citycode, float $lon, float $lat, int $annees = 5): ?array
{
    $ventes = ventes_dvf_commune($citycode, $annees);
    if ($ventes === null) return null;
    foreach ($ventes as &$m) $m['distance'] = $m['lat'] ? (int) round(distance_m($lat, $lon, $m['lat'], $m['lon'])) : null;
    return $ventes;
}

/** Ventes de la commune (sans distance), lues une fois puis gardées en cache : l'estimation en direct reste instantanée. */
function ventes_dvf_commune(string $citycode, int $annees = 5): ?array
{
    if ($citycode === '' || !preg_match('/^\w{5}$/', $citycode)) return null;
    $lu = DATA_DIR . "/cache/dvf/ventes-$citycode-$annees.json";
    if (is_file($lu) && filemtime($lu) > time() - 86400) return json_decode((string) file_get_contents($lu), true);
    $dep = departement($citycode);
    $base = api_base('dvf', URL_DVF);
    $ventes = [];
    $ok = false;
    $derniere = (int) date('Y', maintenant()) - 1;
    for ($an = $derniere; $an > $derniere - $annees; $an--) {
        $cache = DATA_DIR . "/cache/dvf/$an-$citycode.csv";
        if (is_file($cache) && filemtime($cache) > time() - 30 * 86400) {
            $csv = (string) file_get_contents($cache);
        } else {
            $csv = http_get("$base/$an/communes/$dep/$citycode.csv", 20, false);
            if ($csv === null) continue;
            if (!is_dir(dirname($cache))) mkdir(dirname($cache), 0770, true);
            file_put_contents($cache, $csv);
        }
        $ok = true;
        $lignes = preg_split('/\R/', trim($csv));
        $entetes = str_getcsv((string) array_shift($lignes));
        $mutations = [];
        foreach ($lignes as $l) {
            $c = @array_combine($entetes, str_getcsv($l));
            if (!$c || ($c['nature_mutation'] ?? '') !== 'Vente') continue;
            $idm = $c['id_mutation'];
            $m = $mutations[$idm] ?? ['date' => $c['date_mutation'], 'prix' => (float) $c['valeur_fonciere'], 'type' => '', 'surface' => 0.0, 'pieces' => 0, 'terrain' => 0.0, 'locaux' => 0,
                'adresse' => trim($c['adresse_numero'] . ' ' . ucwords(strtolower($c['adresse_nom_voie']))), 'lat' => (float) $c['latitude'], 'lon' => (float) $c['longitude']];
            if (in_array($c['type_local'], ['Maison', 'Appartement'], true)) {
                $m['locaux']++;
                $m['type'] = $c['type_local'];
                $m['surface'] += (float) $c['surface_reelle_bati'];
                $m['pieces'] += (int) $c['nombre_pieces_principales'];
            }
            $m['terrain'] = max($m['terrain'], (float) $c['surface_terrain']);
            if (!$m['lat'] && $c['latitude'] !== '') { $m['lat'] = (float) $c['latitude']; $m['lon'] = (float) $c['longitude']; }
            $mutations[$idm] = $m;
        }
        foreach ($mutations as $m) {
            if ($m['locaux'] !== 1 || $m['surface'] < 9 || $m['prix'] < 10000) continue; // ventes groupées ou atypiques écartées
            $m['prix_m2'] = round($m['prix'] / $m['surface']);
            unset($m['locaux']);
            $ventes[] = $m;
        }
    }
    if (!$ok) return null;
    usort($ventes, fn ($a, $b) => strcmp($b['date'], $a['date']));
    if (!is_dir(dirname($lu))) mkdir(dirname($lu), 0770, true);
    file_put_contents($lu, json_encode($ventes));
    return $ventes;
}

// ---------- Simulation (service indisponible) ----------

function graine(string $texte): int
{
    return hexdec(substr(md5($texte), 0, 7));
}

function donnees_simulees(string $adresse, array $visit): array
{
    $g = graine($adresse ?: 'x');
    $lat = 46.2 + ($g % 1000) / 1000;
    $lon = 2.2 + ($g % 777) / 500;
    $surf = (float) (champ($visit, 'surface_habitable') ?: 100);
    $type = champ($visit, 'type_bien') === 'Appartement' ? 'Appartement' : 'Maison';
    $prixM2 = 2200 + $g % 1400;
    $ventes = [];
    for ($i = 0; $i < 9; $i++) {
        $s = round($surf * (0.75 + (($g >> $i) % 50) / 100));
        $pm2 = $prixM2 * (0.85 + (($g >> ($i + 3)) % 30) / 100);
        $ventes[] = ['date' => date('Y-m-d', strtotime("-" . (2 + $i * 4) . " months")), 'prix' => round($s * $pm2, -3), 'type' => $type, 'surface' => $s,
            'pieces' => max(2, (int) round($s / 24)), 'terrain' => $type === 'Maison' ? 300 + ($g >> $i) % 900 : 0,
            'adresse' => ($i + 3) . ' rue ' . ['des Tilleuls', 'du Moulin', 'de la Gare', 'des Écoles', 'Pasteur'][$i % 5], 'lat' => $lat, 'lon' => $lon,
            'prix_m2' => round($pm2), 'distance' => 150 + 120 * $i];
    }
    return [
        'simulation' => true,
        'geo' => ['lat' => $lat, 'lon' => $lon, 'label' => $adresse, 'citycode' => '', 'postcode' => '', 'city' => champ($visit, 'ville'), 'score' => 0],
        'cadastre' => ['section' => 'AB', 'numero' => (string) (100 + $g % 400), 'idu' => '', 'contenance' => (int) (champ($visit, 'surface_terrain') ?: 600), 'commune' => champ($visit, 'ville')],
        'risques' => ['liste' => ['Inondation', 'Mouvement de terrain - Tassements différentiels'], 'sismicite' => '2 - Faible', 'radon' => 'Catégorie 1', 'lien' => 'https://www.georisques.gouv.fr'],
        'dpe' => ['trouve' => false],
        'ventes' => $ventes,
    ];
}

// ---------- Enrichissement du dossier ----------

/** Récupère toutes les données publiques du bien et complète les champs vides de la fiche. */
function enrichir_donnees_publiques(array $user, string $id): array
{
    $v = load_visit($user, $id);
    $adresse = trim(champ($v, 'adresse') . ' ' . champ($v, 'ville')) ?: (string) $v['titre'];
    $pub = ['maj_le' => date('c'), 'simulation' => false, 'sources' => []];

    $geo = geocoder($adresse);
    if ($geo) {
        $pub['geo'] = $geo;
        $pub['sources'][] = 'Base Adresse Nationale';
        $pub['cadastre'] = cadastre_parcelle($geo['lon'], $geo['lat']);
        if ($pub['cadastre']) $pub['sources'][] = 'Cadastre (IGN)';
        $pub['risques'] = georisques($geo['lon'], $geo['lat'], $geo['citycode']);
        if ($pub['risques']) $pub['sources'][] = 'Géorisques';
        $pub['dpe'] = dpe_enregistre($geo['lon'], $geo['lat'], $geo['label']);
        if ($pub['dpe']) $pub['sources'][] = 'ADEME (DPE)';
        $pub['ventes'] = ventes_dvf($geo['citycode'], $geo['lon'], $geo['lat']);
        if ($pub['ventes'] !== null) $pub['sources'][] = 'DVF (DGFiP / Etalab)';
    }
    if (!$geo || ($pub['ventes'] ?? null) === null) {
        // Service injoignable (ou adresse introuvable) : on complète avec des valeurs simulées, signalées comme telles
        $sim = donnees_simulees($adresse, $v);
        foreach (['geo', 'cadastre', 'risques', 'dpe', 'ventes'] as $k) if (empty($pub[$k])) $pub[$k] = $sim[$k];
        $pub['simulation'] = true;
    }

    return update_visit($user, $id, function (array $v) use ($pub) {
        $v['public'] = $pub;
        $champs = (array) $v['fiche']['champs'];
        $remplir = function (string $cle, string $valeur, string $citation) use (&$champs) {
            if ($valeur === '' || trim((string) ($champs[$cle]['valeur'] ?? '')) !== '') return;
            $champs[$cle] = ['valeur' => $valeur, 'citation' => $citation, 'source' => 'public'];
        };
        $c = $pub['cadastre'] ?? null;
        if ($c && $c['numero'] !== '') $remplir('cadastre', "Section {$c['section']} n° {$c['numero']}" . ($c['contenance'] ? " ({$c['contenance']} m²)" : ''), 'Cadastre (IGN)' . ($pub['simulation'] ? ' · simulé' : ''));
        if ($c && $c['contenance'] && champ($v, 'type_bien') !== 'Appartement') $remplir('surface_terrain', (string) $c['contenance'], 'Contenance cadastrale');
        $d = $pub['dpe'] ?? [];
        if (!empty($d['trouve'])) {
            $remplir('dpe', (string) $d['dpe'], "DPE n° {$d['numero']} du " . date_jj($d['date']) . ' (ADEME)');
            $remplir('ges', (string) $d['ges'], "DPE n° {$d['numero']} (ADEME)");
            if (!empty($d['annee'])) $remplir('annee_construction', (string) $d['annee'], 'DPE (ADEME)');
        }
        if (!empty($pub['geo']['city'])) $remplir('ville', trim(($pub['geo']['postcode'] ?? '') . ' ' . $pub['geo']['city']), 'Base Adresse Nationale');
        $v['fiche']['champs'] = $champs ?: new stdClass();
        journal_ajout($v, 'auto', 'Dossier technique récupéré : ' . ($pub['sources'] ? implode(', ', $pub['sources']) : 'aucune source joignable') . ($pub['simulation'] ? ' (en partie simulé : service indisponible)' : '') . '.');
        return $v;
    });
}

// ---------- Synthèse PDF du dossier technique ----------

function rendre_technique(VisitePdf $pdf, array $v, array $agent): void
{
    $p = $v['public'] ?? null;
    if (!$p) throw new RuntimeException('Dossier technique pas encore récupéré.');
    $pdf->docLabel = 'Dossier technique';
    $pdf->AddPage();
    $pdf->titleBlock('Dossier technique', titre_bien($v), 'Données publiques récupérées le ' . fmt_date_fr($p['maj_le']) . ($p['simulation'] ? ' · en partie simulées (service indisponible)' : ''));
    $g = $p['geo'] ?? [];
    $c = $p['cadastre'] ?? [];
    $rows = [];
    if (!empty($g['label'])) $rows[] = ['label' => 'Adresse normalisée', 'valeur' => $g['label'], 'long' => false];
    if (!empty($g['lat'])) $rows[] = ['label' => 'Coordonnées', 'valeur' => sprintf('%.5f, %.5f', $g['lat'], $g['lon']), 'long' => false];
    if (!empty($c['numero'])) $rows[] = ['label' => 'Parcelle cadastrale', 'valeur' => "Section {$c['section']} n° {$c['numero']}", 'long' => false];
    if (!empty($c['contenance'])) $rows[] = ['label' => 'Contenance', 'valeur' => fmt_nombre((string) $c['contenance']) . ' m²', 'long' => false];
    if ($rows) { $pdf->sectionTitle('Localisation et cadastre'); $pdf->kvGrid($rows); }
    $r = $p['risques'] ?? [];
    $pdf->sectionTitle('Risques naturels et technologiques (Géorisques)');
    $pdf->richText(($r['liste'] ?? []) ? implode("\n", array_map(fn ($x) => "- $x", $r['liste'])) : 'Aucun risque recensé pour la commune.');
    $rows = [];
    if (!empty($r['sismicite'])) $rows[] = ['label' => 'Zone de sismicité', 'valeur' => $r['sismicite'], 'long' => false];
    if (!empty($r['radon'])) $rows[] = ['label' => 'Potentiel radon', 'valeur' => $r['radon'], 'long' => false];
    if ($rows) $pdf->kvGrid($rows);
    $d = $p['dpe'] ?? [];
    $pdf->sectionTitle('Diagnostic de performance énergétique (ADEME)');
    if (!empty($d['trouve'])) {
        $pdf->statBoxes(array_filter([
            ['dpe' => $d['dpe'], 'label' => 'Énergie'],
            $d['ges'] ? ['label' => 'Climat (GES)', 'valeur' => $d['ges']] : null,
            $d['conso'] ? ['label' => 'Consommation', 'valeur' => round((float) $d['conso']) . ' kWh/m²/an'] : null,
            ['label' => 'Établi le', 'valeur' => date_jj($d['date']), 'detail' => 'N° ' . $d['numero']],
        ]));
    } else {
        $pdf->richText("Aucun DPE enregistré à l'ADEME pour cette adresse : un diagnostic devra être commandé avant la mise en vente.");
    }
    $pdf->Ln(2);
    $pdf->font('I', 8, C_GRIS);
    $pdf->MultiCell(0, 4.2, "Sources : " . implode(', ', $p['sources'] ?: ['—']) . ". Cette synthèse ne remplace pas l'état des risques (ERP) ni le dossier de diagnostic technique, qui doivent être annexés à la promesse de vente. Informations détaillées : " . ($r['lien'] ?? 'www.georisques.gouv.fr'));
}

document_module(fn (array $v) => [['technique', 'Dossier technique (cadastre, risques, DPE)', !empty($v['public']), true, 'technique']]);
pdf_module('technique', 'Dossier technique', 'rendre_technique');
