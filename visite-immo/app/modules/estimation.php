<?php
// Estimation en temps réel à partir des ventes réelles de biens similaires (DVF : open data DGFiP / Etalab).
//
//  - Chaque vente de la commune reçoit une note de ressemblance avec le bien (surface, pièces, terrain,
//    distance, ancienneté) ; les plus proches pèsent le plus dans le prix au m².
//  - Les prix anciens sont actualisés avec la tendance du marché local (médiane par année).
//  - Ajustements selon l'état, le DPE, l'extérieur, le stationnement, l'exposition.
//  - Indice de confiance : nombre de ventes, ressemblance moyenne, dispersion des prix.
//
// Sert à l'outil « Estimer un bien » (réponse pendant la saisie, sans dossier) et au dossier : l'avis de valeur
// est recalculé dès qu'une caractéristique change (fiche, dialogue, pièces lues par l'IA).
// Ce n'est pas une expertise : c'est une aide à la discussion avec le vendeur.

const ESTIMATION_CHAMPS = ['type_bien', 'surface_habitable', 'nb_pieces', 'surface_terrain', 'etat_general', 'dpe', 'exterieur', 'stationnement', 'exposition', 'prix_souhaite'];
const AJUSTEMENTS_ETAT = ['À rénover' => -0.12, 'Travaux à prévoir' => -0.06, 'Bon état' => 0.0, 'Très bon état' => 0.04, 'Refait à neuf' => 0.08];
const AJUSTEMENTS_DPE = ['A' => 0.04, 'B' => 0.03, 'C' => 0.01, 'D' => 0.0, 'E' => -0.03, 'F' => -0.07, 'G' => -0.10];

function nombre_fr(mixed $x): float
{
    return (float) str_replace([',', ' ', "\u{202f}", "\u{a0}"], ['.', '', '', ''], (string) $x);
}

/** Caractéristiques du bien utiles à l'estimation, depuis la fiche d'un dossier. */
function bien_depuis_dossier(array $v): array
{
    $b = [];
    foreach (ESTIMATION_CHAMPS as $k) $b[$k] = champ($v, $k);
    return $b;
}

/** Ressemblance d'une vente avec le bien, entre 0 et 1. */
function similarite_vente(array $vente, array $bien, float $echelle = 1500): float
{
    $surface = nombre_fr($bien['surface_habitable'] ?? 0);
    $s = exp(-((abs($vente['surface'] - $surface) / $surface) / 0.25) ** 2); // 25 % d'écart → 0,37
    $pieces = (int) nombre_fr($bien['nb_pieces'] ?? 0);
    if ($pieces && $vente['pieces']) $s *= [1, 0.8, 0.55, 0.35][min(3, abs($vente['pieces'] - $pieces))];
    $terrain = nombre_fr($bien['surface_terrain'] ?? 0);
    if ($terrain > 0 && $vente['terrain'] > 0) $s *= 0.5 + 0.5 * exp(-(log($vente['terrain'] / $terrain)) ** 2);
    $s *= $vente['distance'] === null ? 0.6 : 1 / (1 + ($vente['distance'] / $echelle) ** 2);
    $mois = max(0, (maintenant() - strtotime($vente['date'])) / (30.4 * 86400));
    $s *= exp(-$mois / 48);
    return $s;
}

function quantile_pondere(array $valeurs, array $poids, float $q): float
{
    array_multisort($valeurs, $poids);
    $total = array_sum($poids);
    if ($total <= 0) return 0;
    $cumul = 0;
    foreach ($valeurs as $i => $v) {
        $cumul += $poids[$i];
        if ($cumul >= $q * $total) return $v;
    }
    return end($valeurs);
}

/** Évolution annuelle des prix au m² (médiane par année, au moins 3 ventes par année), bornée à ±10 %/an. */
function tendance_marche(array $ventes): array
{
    $parAn = [];
    foreach ($ventes as $v) $parAn[substr($v['date'], 0, 4)][] = $v['prix_m2'];
    ksort($parAn);
    $medianes = [];
    foreach ($parAn as $an => $x) if (count($x) >= 3) $medianes[$an] = (int) round(mediane($x));
    $annuelle = 0.0;
    if (count($medianes) >= 2) {
        $ans = array_keys($medianes);
        $n = (int) end($ans) - (int) $ans[0];
        if ($n > 0) $annuelle = max(-0.10, min(0.10, (end($medianes) / reset($medianes)) ** (1 / $n) - 1));
    }
    return ['annuelle' => round($annuelle, 4), 'par_annee' => $medianes];
}

function ajustements_bien(array $bien): array
{
    $ajust = [];
    $etat = (string) ($bien['etat_general'] ?? '');
    if (!empty(AJUSTEMENTS_ETAT[$etat])) $ajust[] = ['État : ' . mb_strtolower($etat), AJUSTEMENTS_ETAT[$etat]];
    $dpe = strtoupper(trim((string) ($bien['dpe'] ?? '')));
    if (!empty(AJUSTEMENTS_DPE[$dpe])) $ajust[] = ["DPE $dpe", AJUSTEMENTS_DPE[$dpe]];
    $ext = mb_strtolower(($bien['exterieur'] ?? '') . ' ' . ($bien['stationnement'] ?? ''));
    if (preg_match('/terrasse|balcon|jardin/u', $ext) && ($bien['type_bien'] ?? '') === 'Appartement') $ajust[] = ['Extérieur (appartement)', 0.04];
    if (preg_match('/garage|parking|box/u', $ext)) $ajust[] = ['Stationnement', 0.02];
    if (preg_match('/\b(sud|sud-ouest)\b/u', mb_strtolower((string) ($bien['exposition'] ?? '')))) $ajust[] = ['Exposition sud', 0.01];
    return $ajust;
}

/**
 * Estimation d'un bien à partir des ventes alentour. $bien : type_bien, surface_habitable, nb_pieces, surface_terrain,
 * etat_general, dpe, exterieur, stationnement, exposition, prix_souhaite. Renvoie null si trop peu de ventes.
 */
function estimer(array $bien, array $ventes, float $echelle = 1500): ?array
{
    $surface = nombre_fr($bien['surface_habitable'] ?? 0);
    if ($surface < 9 || !$ventes) return null;
    $type = in_array($bien['type_bien'] ?? '', ['Maison', 'Appartement'], true) ? $bien['type_bien'] : null;
    $memeType = array_values(array_filter($ventes, fn ($v) => !$type || $v['type'] === $type));
    $tendance = tendance_marche($memeType);

    $notes = [];
    foreach ($memeType as $v) {
        $sim = similarite_vente($v, $bien, $echelle);
        if ($sim < 0.02) continue;
        $ans = max(0, (maintenant() - strtotime($v['date'])) / (365.25 * 86400));
        $v['prix_m2_actualise'] = (int) round($v['prix_m2'] * (1 + $tendance['annuelle']) ** $ans);
        $v['similarite'] = $sim;
        $notes[] = $v;
    }
    usort($notes, fn ($a, $b) => $b['similarite'] <=> $a['similarite']);
    $comp = array_slice($notes, 0, 15);
    if (count($comp) < 3) return null;

    $pm2 = array_column($comp, 'prix_m2_actualise');
    $poids = array_column($comp, 'similarite');
    $central = quantile_pondere($pm2, $poids, 0.5);
    $q1 = quantile_pondere($pm2, $poids, 0.2);
    $q3 = quantile_pondere($pm2, $poids, 0.8);

    $ajust = ajustements_bien($bien);
    $coef = 1 + array_sum(array_column($ajust, 1));
    $arrondi = fn ($x) => (int) (round($x / 1000) * 1000);
    $prix = $central * $surface * $coef;
    $bas = min($q1 * $surface * $coef, $prix * 0.96);
    $haut = max($q3 * $surface * $coef, $prix * 1.04);

    // Confiance : assez de ventes, qui ressemblent au bien, avec des prix cohérents entre eux
    $simMoy = array_sum($poids) / count($poids);
    $dispersion = $central ? ($q3 - $q1) / $central : 1;
    $score = (int) round(100 * min(1, count($comp) / 10) * 0.35 + 100 * min(1, $simMoy / 0.5) * 0.4 + 100 * max(0, 1 - $dispersion / 0.6) * 0.25);
    $niveau = $score >= 70 ? 'élevée' : ($score >= 45 ? 'moyenne' : 'faible');

    $prixVendeur = nombre_fr($bien['prix_souhaite'] ?? 0);
    return [
        'calcule_le' => date('c'),
        'surface' => $surface,
        'prix' => $arrondi($prix), 'bas' => $arrondi($bas), 'haut' => $arrondi($haut),
        'prix_m2' => (int) round($central * $coef),
        'prix_m2_marche' => (int) round($central), 'prix_m2_bas' => (int) round($q1), 'prix_m2_haut' => (int) round($q3),
        'ajustements' => $ajust, 'coefficient' => round($coef, 3),
        'tendance' => $tendance,
        'confiance' => ['score' => $score, 'niveau' => $niveau],
        'prix_vendeur' => $prixVendeur ?: null,
        'ecart_vendeur' => $prixVendeur ? round(($prixVendeur / $prix - 1) * 100, 1) : null,
        'nb_ventes_secteur' => count($memeType),
        'comparables' => array_map(fn ($c) => array_intersect_key($c, array_flip(['date', 'prix', 'type', 'surface', 'pieces', 'terrain', 'adresse', 'lat', 'lon', 'prix_m2', 'prix_m2_actualise', 'distance', 'commune']))
            + ['similarite' => (int) round(100 * min(1, $c['similarite'] / 0.9))], array_slice($comp, 0, 12)),
    ];
}

// ---------- Petites communes : on élargit aux communes voisines ----------

const URL_GEO_COMMUNES = 'https://geo.api.gouv.fr';
const SEUIL_VENTES_COMMUNE = 12;  // en dessous (même type de bien sur 5 ans), on ajoute les communes voisines
const SEUIL_VENTES_SECTEUR = 30;  // on s'arrête dès qu'on en a assez
const MAX_COMMUNES_VOISINES = 6;

/**
 * Communes voisines, de la plus proche à la plus éloignée : on demande à l'API Découpage administratif
 * (geo.api.gouv.fr) dans quelle commune tombent des points placés en cercle autour du bien (4 puis 9 km).
 * Résultat gardé en cache sans limite de durée (les limites communales bougent rarement).
 */
function communes_voisines(string $citycode, float $lat, float $lon): array
{
    $cache = DATA_DIR . "/cache/voisines/$citycode.json";
    if (is_file($cache)) return json_decode((string) file_get_contents($cache), true) ?: [];
    $base = api_base('geo', URL_GEO_COMMUNES);
    $trouvees = [];
    $repondu = false;
    foreach ([4, 9] as $km) {
        foreach (range(0, 315, 45) as $angle) {
            $plat = $lat + $km / 111.0 * cos(deg2rad($angle));
            $plon = $lon + $km / (111.0 * max(0.2, cos(deg2rad($lat)))) * sin(deg2rad($angle));
            $r = http_get("$base/communes?" . http_build_query(['lat' => round($plat, 5), 'lon' => round($plon, 5), 'fields' => 'code,nom', 'format' => 'json']), 6);
            if ($r === null) continue;
            $repondu = true;
            foreach ((array) $r as $c) {
                if (empty($c['code']) || $c['code'] === $citycode || isset($trouvees[$c['code']])) continue;
                $trouvees[$c['code']] = ['code' => (string) $c['code'], 'nom' => (string) ($c['nom'] ?? $c['code']), 'km' => $km];
            }
        }
    }
    $liste = array_values($trouvees);
    if ($repondu) {
        if (!is_dir(dirname($cache))) mkdir(dirname($cache), 0770, true);
        file_put_contents($cache, json_encode($liste, JSON_UNESCAPED_UNICODE));
    }
    return $liste;
}

/**
 * Ventes autour du bien : celles de la commune, plus celles des communes voisines si la commune en compte trop
 * peu pour ce type de bien. Renvoie [ventes, noms des communes ajoutées] ou null si DVF est injoignable.
 */
function ventes_secteur(string $citycode, float $lon, float $lat, ?string $type, ?array $ventes = null): ?array
{
    $ventes ??= ventes_dvf($citycode, $lon, $lat);
    if ($ventes === null) return null;
    $type = in_array($type, ['Maison', 'Appartement'], true) ? $type : null;
    $compte = fn (array $l) => count(array_filter($l, fn ($v) => !$type || $v['type'] === $type));
    $ajoutees = [];
    if ($compte($ventes) >= SEUIL_VENTES_COMMUNE) return [$ventes, $ajoutees];
    foreach (array_slice(communes_voisines($citycode, $lat, $lon), 0, MAX_COMMUNES_VOISINES) as $c) {
        $autres = ventes_dvf($c['code'], $lon, $lat);
        if (!$autres) continue;
        foreach ($autres as &$x) $x['commune'] = $c['nom'];
        unset($x);
        $ventes = array_merge($ventes, $autres);
        $ajoutees[] = $c['nom'];
        if ($compte($ventes) >= SEUIL_VENTES_SECTEUR) break;
    }
    return [$ventes, $ajoutees];
}

/** Estimation avec élargissement : distances jugées à l'échelle du canton quand on sort de la commune. */
function estimer_secteur(array $bien, array $ventes, array $ajoutees): ?array
{
    $e = estimer($bien, $ventes, $ajoutees ? 5000 : 1500);
    if ($e) $e['communes_voisines'] = $ajoutees;
    return $e;
}

/** Estimation du dossier (fiche + ventes déjà récupérées, élargies si besoin), sans rien enregistrer. */
function estimation_dossier(array $v): ?array
{
    $ventes = $v['public']['ventes'] ?? [];
    if (!$ventes) return null;
    $ajoutees = [];
    $geo = $v['public']['geo'] ?? [];
    if (empty($v['public']['simulation']) && !empty($geo['citycode'])) {
        $secteur = ventes_secteur($geo['citycode'], (float) $geo['lon'], (float) $geo['lat'], champ($v, 'type_bien'), $ventes);
        if ($secteur) [$ventes, $ajoutees] = $secteur;
    }
    $e = estimer_secteur(bien_depuis_dossier($v), $ventes, $ajoutees);
    if ($e) $e['simulation'] = !empty($v['public']['simulation']);
    return $e;
}

/**
 * Une caractéristique a changé : l'avis de valeur suit, tout de suite (chiffres seulement ; l'argumentaire rédigé
 * est signalé « à régénérer » pour ne pas appeler l'IA à chaque frappe).
 */
apres_champs(function (array $agent, string $id, array $modifies) {
    if (!array_intersect($modifies, ESTIMATION_CHAMPS)) return;
    $v = load_visit($agent, $id);
    if (empty($v['public']['ventes'])) return;
    $a = calculer_avis_valeur($v);
    if (!$a) return;
    update_visit($agent, $id, function (array $v) use ($a) {
        $ancien = $v['avis_valeur'] ?? [];
        $a['argumentaire'] = $ancien['argumentaire'] ?? '';
        $a['retenu_agent'] = $ancien['retenu_agent'] ?? null;
        if ($a['retenu_agent']) $a['retenu'] = $a['retenu_agent'];
        $a['argumentaire_perime'] = $a['argumentaire'] !== '' && abs(($ancien['retenu'] ?? 0) - $a['retenu']) >= 1000 || !empty($ancien['argumentaire_perime']);
        $a['direct'] = true;
        $v['avis_valeur'] = $a;
        return $v;
    });
});

// ---------- Outil « Estimer un bien » (sans dossier) ----------

/** Suggestions d'adresses pendant la saisie (Base Adresse Nationale). */
route('GET adresse_suggestions', function () {
    require_user();
    $q = trim((string) ($_GET['q'] ?? ''));
    if (mb_strlen($q) < 4) send_json([]);
    $r = http_get(api_base('adresse', URL_ADRESSE) . '/search/?' . http_build_query(['q' => $q, 'limit' => 5, 'autocomplete' => 1]), 6);
    $out = [];
    foreach ($r['features'] ?? [] as $f) {
        $p = $f['properties'];
        $out[] = ['label' => $p['label'] ?? '', 'lon' => (float) $f['geometry']['coordinates'][0], 'lat' => (float) $f['geometry']['coordinates'][1],
            'citycode' => $p['citycode'] ?? '', 'city' => $p['city'] ?? '', 'postcode' => $p['postcode'] ?? '', 'type' => $p['type'] ?? ''];
    }
    send_json($out);
});

/**
 * Estimation instantanée : adresse (ou lat, lon, citycode déjà connus) + caractéristiques.
 * Les ventes de la commune sont en cache : chaque nouvelle saisie répond en quelques millisecondes.
 */
route('GET estimation', function () {
    require_user();
    $g = $_GET;
    $lat = isset($g['lat']) ? (float) $g['lat'] : null;
    $lon = isset($g['lon']) ? (float) $g['lon'] : null;
    $citycode = (string) ($g['citycode'] ?? '');
    $label = (string) ($g['adresse'] ?? '');
    if ($lat === null || $citycode === '') {
        $geo = geocoder($label);
        if (!$geo) fail(404, 'Adresse introuvable : précisez la commune.');
        [$lat, $lon, $citycode, $label] = [$geo['lat'], $geo['lon'], $geo['citycode'], $geo['label']];
    }
    $secteur = ventes_secteur($citycode, $lon, $lat, (string) ($g['type_bien'] ?? ''));
    [$ventes, $ajoutees] = $secteur ?? [null, []];
    $simulation = false;
    if ($ventes === null) {
        $ventes = donnees_simulees($label, ['fiche' => ['champs' => ['surface_habitable' => ['valeur' => (string) ($g['surface_habitable'] ?? '100')], 'type_bien' => ['valeur' => (string) ($g['type_bien'] ?? 'Maison')]]]])['ventes'];
        $simulation = true;
    }
    $bien = array_intersect_key($g, array_flip(ESTIMATION_CHAMPS));
    $e = estimer_secteur($bien, $ventes, $ajoutees);
    send_json(['adresse' => $label, 'lat' => $lat, 'lon' => $lon, 'citycode' => $citycode, 'simulation' => $simulation,
        'nb_ventes_commune' => count(array_filter($ventes, fn ($x) => empty($x['commune']))), 'nb_ventes' => count($ventes),
        'communes_voisines' => $ajoutees, 'estimation' => $e]);
});

/** Estimation du dossier, recalculée à la volée (sans enregistrer). */
route('GET estimation_dossier', fn ($id) => send_json(['estimation' => estimation_dossier(load_visit(require_user(), $id))]));
