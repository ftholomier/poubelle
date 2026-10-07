<?php
// Prospection : trouver les vendeurs avant les autres, avec des données publiques.
//   - logements classés F ou G (DPE ADEME) : les propriétaires bailleurs devront rénover ou vendre
//     (location interdite : G depuis 2025, F en 2028, E en 2034) ;
//   - rues où les ventes bougent (DVF) : boîtage « votre quartier se vend » (statistiques anonymes) ;
//   - courrier personnalisé « Au propriétaire » prêt à imprimer, suivi jusqu'au mandat.
// Le courrier remplace le démarchage téléphonique, soumis au consentement préalable de la personne.

const STATUTS_CIBLE = ['nouveau' => 'À contacter', 'courrier' => 'Courrier envoyé', 'contacte' => 'A répondu', 'rdv' => 'Estimation prévue', 'mandat' => 'Mandat signé', 'ecarte' => 'Écarté'];

function chercher_commune(string $nom): array
{
    $r = http_get(api_base('adresse', URL_ADRESSE) . '/search/?' . http_build_query(['q' => $nom, 'type' => 'municipality', 'limit' => 5]));
    return array_map(fn ($f) => ['citycode' => $f['properties']['citycode'], 'nom' => $f['properties']['city'] ?? $f['properties']['label'], 'cp' => $f['properties']['postcode'] ?? '',
        'lon' => $f['geometry']['coordinates'][0], 'lat' => $f['geometry']['coordinates'][1], 'contexte' => $f['properties']['context'] ?? ''], $r['features'] ?? []);
}

/** Logements F/G d'une commune (DPE ADEME), les plus anciens DPE d'abord. */
function logements_passoires(string $citycode): ?array
{
    $r = http_get(api_base('dpe', URL_DPE) . '/lines?' . http_build_query([
        'size' => 200, 'qs' => "code_insee_ban:\"$citycode\" AND etiquette_dpe:(F OR G)",
        'select' => 'adresse_ban,etiquette_dpe,etiquette_ges,date_etablissement_dpe,type_batiment,surface_habitable_logement,annee_construction,_geopoint',
        'sort' => 'date_etablissement_dpe',
    ]), 30);
    if ($r === null) return null;
    $out = [];
    foreach ($r['results'] ?? [] as $d) {
        [$lat, $lon] = array_map('floatval', explode(',', (string) ($d['_geopoint'] ?? '0,0')) + [0, 0]);
        $out[] = ['adresse' => $d['adresse_ban'] ?? '', 'dpe' => $d['etiquette_dpe'] ?? '', 'ges' => $d['etiquette_ges'] ?? '', 'date_dpe' => $d['date_etablissement_dpe'] ?? '',
            'type' => $d['type_batiment'] ?? '', 'surface' => $d['surface_habitable_logement'] ?? null, 'annee' => $d['annee_construction'] ?? null, 'lat' => $lat, 'lon' => $lon];
    }
    return $out;
}

/** Statistiques de marché d'une commune et rues les plus actives (DVF, 3 dernières années). */
function marche_commune(string $citycode, float $lon, float $lat): array
{
    $ventes = ventes_dvf($citycode, $lon, $lat, 3) ?? [];
    $parRue = [];
    foreach ($ventes as $v) {
        $rue = trim(preg_replace('/^\d+\s*/', '', $v['adresse']));
        if ($rue === '') continue;
        $parRue[$rue][] = $v;
    }
    $rues = [];
    foreach ($parRue as $rue => $l) {
        if (count($l) < 2) continue;
        $rues[] = ['rue' => $rue, 'ventes' => count($l), 'prix_m2' => (int) round(mediane(array_column($l, 'prix_m2'))), 'derniere' => max(array_column($l, 'date')), 'lat' => $l[0]['lat'], 'lon' => $l[0]['lon']];
    }
    usort($rues, fn ($a, $b) => $b['ventes'] <=> $a['ventes']);
    $maisons = array_filter($ventes, fn ($v) => $v['type'] === 'Maison');
    $apparts = array_filter($ventes, fn ($v) => $v['type'] === 'Appartement');
    return [
        'ventes' => count($ventes),
        'prix_m2_maison' => $maisons ? (int) round(mediane(array_column($maisons, 'prix_m2'))) : null,
        'prix_m2_appartement' => $apparts ? (int) round(mediane(array_column($apparts, 'prix_m2'))) : null,
        'rues' => array_slice($rues, 0, 15),
    ];
}

function score_cible(array $c): int
{
    $s = $c['dpe'] === 'G' ? 60 : 45;
    if ($c['type'] === 'maison') $s += 10;
    if ($c['date_dpe'] && $c['date_dpe'] < date('Y-m-d', strtotime('-2 years'))) $s += 10; // DPE ancien : décision proche
    if (($c['annee'] ?? 2000) < 1975) $s += 10;
    if (($c['surface'] ?? 0) > 90) $s += 5;
    return min(100, $s);
}

/** Analyse un secteur : nouvelles cibles ajoutées (sans doublon), statistiques de marché mémorisées. */
function analyser_secteur(array $agent, array $commune): array
{
    $passoires = logements_passoires($commune['citycode']);
    $marche = marche_commune($commune['citycode'], (float) $commune['lon'], (float) $commune['lat']);
    $ajouts = 0;
    collection_maj($agent, 'prospection', function (array $p) use ($commune, $passoires, $marche, &$ajouts) {
        $p['secteurs'][$commune['citycode']] = $commune + ['analyse_le' => date('c'), 'marche' => $marche, 'disponible' => $passoires !== null];
        $connues = array_column($p['cibles'] ?? [], 'adresse');
        foreach ($passoires ?? [] as $c) {
            if ($c['adresse'] === '' || in_array($c['adresse'], $connues, true)) continue;
            $p['cibles'][] = $c + ['id' => nouvel_id('c'), 'citycode' => $commune['citycode'], 'raison' => 'dpe', 'score' => score_cible($c), 'statut' => 'nouveau', 'historique' => [], 'cree_le' => date('c')];
            $connues[] = $c['adresse'];
            $ajouts++;
        }
        return $p;
    });
    return ['ajouts' => $ajouts, 'marche' => $marche, 'service' => $passoires !== null];
}

// ---------- Courriers ----------

function rendre_courrier(VisitePdf $pdf, array $agent, array $c, array $marche): void
{
    global $CONFIG;
    $pdf->docLabel = 'Courrier';
    $pdf->AddPage();
    $pdf->SetX(118);
    $pdf->font('semi', 10, C_ENCRE);
    $pdf->MultiCell(74, 5, "Au propriétaire\n" . preg_replace('/\s+(\d{5})\s+/', "\n$1 ", $c['adresse']));
    $pdf->Ln(6);
    $pdf->SetX(118);
    $pdf->font('', 9.5, C_GRIS);
    $pdf->Cell(74, 5, 'Le ' . fmt_date_fr(null), 0, 1);
    $pdf->Ln(10);
    $pm2 = $c['type'] === 'appartement' ? ($marche['prix_m2_appartement'] ?? null) : ($marche['prix_m2_maison'] ?? null);
    if ($c['raison'] === 'dpe') {
        $pdf->titleBlock('Votre logement', $c['dpe'] === 'G' ? 'Logement classé G : vos options en 2026' : 'Logement classé F : anticipez 2028', "Un courrier de {$agent['nom']}, conseiller {$CONFIG['agence']} près de chez vous");
        $pdf->richText("Madame, Monsieur,\n\nLe diagnostic de performance énergétique de votre logement, publié par l'ADEME, le classe en " . $c['dpe'] . ". Depuis la loi Climat et résilience, les logements classés G ne peuvent plus être proposés à la location depuis le 1er janvier 2025 ; ce sera le cas des logements classés F en 2028, puis E en 2034.\n\nTrois options s'offrent aux propriétaires :\n- rénover, avec l'aide de MaPrimeRénov' et des certificats d'économies d'énergie ;\n- vendre en l'état, en valorisant le potentiel de rénovation auprès d'acquéreurs qui le recherchent ;\n- vendre après des travaux ciblés, pour gagner une ou deux classes.", 10.5);
    } else {
        $pdf->titleBlock('Votre quartier', 'Votre quartier se vend bien', "Un courrier de {$agent['nom']}, conseiller {$CONFIG['agence']} près de chez vous");
        $pdf->richText("Madame, Monsieur,\n\nPlusieurs biens ont été vendus récemment près de chez vous. Si vous vous êtes déjà demandé combien vaut votre bien aujourd'hui, c'est le bon moment pour le savoir.", 10.5);
    }
    if ($pm2 || $marche['ventes']) {
        $pdf->Ln(2);
        $pdf->statBoxes(array_filter([
            $pm2 ? ['label' => 'Prix médian au m²', 'valeur' => fmt_nombre((string) $pm2) . "\u{00A0}€", 'sombre' => true, 'detail' => $c['type'] === 'appartement' ? 'appartements' : 'maisons'] : null,
            ['label' => 'Ventes sur 3 ans', 'valeur' => (string) $marche['ventes'], 'detail' => 'dans votre commune'],
            !empty($c['surface']) && $pm2 ? ['label' => 'Ordre de grandeur', 'valeur' => fmt_nombre((string) (round($c['surface'] * $pm2 * ($c['raison'] === 'dpe' ? 0.88 : 1) / 5000) * 5000)) . "\u{00A0}€", 'detail' => 'à affiner par une visite'] : null,
        ]));
    }
    $pdf->richText("Je vous propose un avis de valeur gratuit et sans engagement, appuyé sur les ventes réelles de votre secteur" . ($c['raison'] === 'dpe' ? ", avec le chiffrage des options (vente en l'état ou après travaux)" : '') . ". Il suffit de m'appeler ou de m'écrire.\n\nBien cordialement,", 10.5);
    $pdf->font('semi', 11, C_ENCRE);
    $pdf->Cell(0, 6, $agent['nom'] . ' · ' . $CONFIG['agence'], 0, 1);
    $pdf->font('', 10, C_ENCRE);
    $pdf->Cell(0, 5, implode('  ·  ', array_filter([$agent['telephone'] ?? '', $agent['email'] ?? ''])), 0, 1);
    $pdf->Ln(4);
    $pdf->font('I', 7.5, C_GRIS);
    $pdf->MultiCell(0, 4, "Courrier adressé à partir des données publiques de l'ADEME et des valeurs foncières (DVF). Pour ne plus recevoir de courrier de notre part, il suffit de nous le signaler.");
}

function rendre_boitage(VisitePdf $pdf, array $agent, array $secteur, array $rue): void
{
    global $CONFIG;
    $pdf->docLabel = 'Boîtage';
    $pdf->AddPage();
    $pdf->titleBlock('Votre rue', $rue['rue'] . ' : ' . $rue['ventes'] . ' ventes récentes', $secteur['nom'] . ' · ventes enregistrées par l\'administration fiscale (DVF) sur 3 ans');
    $pdf->statBoxes([['label' => 'Prix médian au m²', 'valeur' => fmt_nombre((string) $rue['prix_m2']) . "\u{00A0}€", 'sombre' => true], ['label' => 'Dernière vente', 'valeur' => date('m/Y', strtotime($rue['derniere']))], ['label' => 'Ventes dans la commune', 'valeur' => (string) $secteur['marche']['ventes']]]);
    $pdf->richText("Bonjour,\n\nVotre rue attire les acquéreurs : " . $rue['ventes'] . " biens y ont été vendus ces trois dernières années. Si vous envisagez de vendre, ou si vous voulez simplement connaître la valeur de votre bien, je vous offre un avis de valeur gratuit, sans engagement.\n\n{$agent['nom']}, {$CONFIG['agence']}", 11);
    $pdf->contactBox($agent);
}

// ---------- API ----------

route('GET prospection', function () {
    $me = require_user();
    $p = collection($me, 'prospection');
    $cibles = $p['cibles'] ?? [];
    usort($cibles, fn ($a, $b) => [$a['statut'] === 'ecarte', -$a['score']] <=> [$b['statut'] === 'ecarte', -$b['score']]);
    $stats = array_count_values(array_column($cibles, 'statut'));
    send_json(['secteurs' => array_values($p['secteurs'] ?? []), 'cibles' => $cibles, 'stats' => $stats, 'statuts' => STATUTS_CIBLE]);
});

route('GET communes', function () {
    require_user();
    send_json(chercher_commune((string) ($_GET['q'] ?? '')));
});

route('POST secteur', function () {
    $me = require_user();
    $c = json_input();
    if (!preg_match('/^\w{5}$/', (string) ($c['citycode'] ?? ''))) fail(400, 'Commune invalide.');
    $c = array_intersect_key($c, array_flip(['citycode', 'nom', 'cp', 'lat', 'lon', 'contexte']));
    send_json(analyser_secteur($me, $c));
});

route('POST cible', function ($id) {
    $me = require_user();
    $in = json_input();
    $p = collection_maj($me, 'prospection', function (array $p) use ($id, $in) {
        foreach ($p['cibles'] ?? [] as $i => $c) {
            if ($c['id'] !== $id) continue;
            if (isset($in['statut']) && isset(STATUTS_CIBLE[$in['statut']])) {
                $p['cibles'][$i]['statut'] = $in['statut'];
                $p['cibles'][$i]['historique'][] = ['date' => date('c'), 'texte' => STATUTS_CIBLE[$in['statut']]];
            }
            if (isset($in['note'])) $p['cibles'][$i]['historique'][] = ['date' => date('c'), 'texte' => mb_substr((string) $in['note'], 0, 300)];
        }
        return $p;
    });
    send_json(['ok' => true]);
});

/** Courriers (une page par adresse) pour les cibles choisies ; elles passent au statut « courrier envoyé ». */
route('GET courriers', function () {
    $me = require_user();
    $ids = array_filter(explode(',', (string) ($_GET['ids'] ?? '')));
    $p = collection($me, 'prospection');
    $pdf = pdf_nouveau('Courrier');
    $n = 0;
    foreach ($p['cibles'] ?? [] as $c) {
        if (!in_array($c['id'], $ids, true)) continue;
        rendre_courrier($pdf, $me, $c, $p['secteurs'][$c['citycode']]['marche'] ?? ['ventes' => 0]);
        $n++;
    }
    if (!$n) fail(400, 'Aucune adresse choisie.');
    if (empty($_GET['apercu'])) collection_maj($me, 'prospection', function (array $p) use ($ids) {
        foreach ($p['cibles'] as &$c) if (in_array($c['id'], $ids, true) && $c['statut'] === 'nouveau') { $c['statut'] = 'courrier'; $c['historique'][] = ['date' => date('c'), 'texte' => 'Courrier généré']; }
        return $p;
    });
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="Courriers-prospection-' . date('Y-m-d') . '.pdf"');
    echo $pdf->Output('S');
    exit;
});

route('GET boitage', function () {
    $me = require_user();
    $p = collection($me, 'prospection');
    $s = $p['secteurs'][(string) ($_GET['commune'] ?? '')] ?? fail(404, 'Secteur introuvable.');
    $rue = null;
    foreach ($s['marche']['rues'] as $r) if ($r['rue'] === ($_GET['rue'] ?? '')) $rue = $r;
    if (!$rue) fail(404, 'Rue introuvable.');
    $pdf = pdf_nouveau('Boîtage');
    rendre_boitage($pdf, $me, $s, $rue);
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="Boitage-' . slug($rue['rue']) . '.pdf"');
    echo $pdf->Output('S');
    exit;
});
