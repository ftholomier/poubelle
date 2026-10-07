<?php
// Avis de valeur automatique : ventes comparables du quartier (DVF), ajustements selon l'état, le DPE et les
// extérieurs, fourchette et prix conseillé, argumentaire rédigé. PDF remis au vendeur.
// Mention obligatoire : un avis de valeur n'est pas une expertise.

// Le calcul lui-même (ressemblance des ventes, tendance, confiance) est dans estimation.php.

function mediane(array $x): float
{
    sort($x);
    $n = count($x);
    if (!$n) return 0;
    return $n % 2 ? $x[intdiv($n, 2)] : ($x[$n / 2 - 1] + $x[$n / 2]) / 2;
}

function quantile(array $x, float $q): float
{
    sort($x);
    $n = count($x);
    if (!$n) return 0;
    $pos = ($n - 1) * $q;
    $b = (int) floor($pos);
    return $x[$b] + ($x[min($b + 1, $n - 1)] - $x[$b]) * ($pos - $b);
}

/** Choisit les ventes comparables : même type, surface proche, récentes, proches ; élargit si trop peu. */
function comparables(array $ventes, string $type, float $surface): array
{
    $type = in_array($type, ['Maison', 'Appartement'], true) ? $type : null;
    foreach ([[0.3, 1500, 3], [0.45, 3000, 4], [0.7, 8000, 5], [5, 1e9, 6]] as [$ecart, $dist, $ans]) {
        $limite = date('Y-m-d', strtotime("-$ans years", maintenant()));
        $c = array_values(array_filter($ventes, fn ($v) => (!$type || $v['type'] === $type)
            && (!$surface || abs($v['surface'] - $surface) / $surface <= $ecart)
            && ($v['distance'] === null || $v['distance'] <= $dist) && $v['date'] >= $limite));
        if (count($c) >= 5) break;
    }
    usort($c, fn ($a, $b) => ($a['distance'] ?? 0) <=> ($b['distance'] ?? 0));
    return array_slice($c, 0, 12);
}

function calculer_avis_valeur(array $v): ?array
{
    $e = estimation_dossier($v);
    if (!$e) return null;
    return [
        'calcule_le' => $e['calcule_le'],
        'surface' => $e['surface'],
        'prix_m2_median' => $e['prix_m2_marche'],
        'prix_m2_bas' => $e['prix_m2_bas'],
        'prix_m2_haut' => $e['prix_m2_haut'],
        'ajustements' => $e['ajustements'],
        'coefficient' => $e['coefficient'],
        'bas' => $e['bas'], 'haut' => $e['haut'], 'retenu' => $e['prix'],
        'prix_vendeur' => $e['prix_vendeur'],
        'ecart_vendeur' => $e['ecart_vendeur'],
        'confiance' => $e['confiance'],
        'tendance' => $e['tendance'],
        'comparables' => $e['comparables'],
        'simulation' => $e['simulation'],
        'argumentaire' => '',
    ];
}

function argumentaire_avis(array $v, array $a, array $agent): string
{
    global $CONFIG;
    $f = fn ($n) => number_format($n, 0, ',', ' ') . ' €';
    $ecart = $a['ecart_vendeur'];
    $type = mb_strtolower(champ($v, 'type_bien') ?: 'bien');
    $base = "Nous avons étudié " . count($a['comparables']) . " ventes réelles de biens comparables ({$type}s les plus ressemblants : surface, pièces, terrain, proximité) enregistrées par l'administration fiscale. "
        . "Une fois actualisés selon l'évolution du marché local, ces prix donnent une valeur de référence de {$f($a['prix_m2_median'])} par m², la plupart des ventes se situant entre {$f($a['prix_m2_bas'])} et {$f($a['prix_m2_haut'])} par m².";
    if (empty($CONFIG['gemini_api_key'])) {
        $txt = $base . "\n\n";
        if ($a['ajustements']) $txt .= 'Les caractéristiques propres à votre bien ont été prises en compte : ' . implode(', ', array_map(fn ($x) => mb_strtolower($x[0]) . ' (' . ($x[1] > 0 ? '+' : '') . round($x[1] * 100) . ' %)', $a['ajustements'])) . ".\n\n";
        $txt .= "Nous estimons la valeur de votre bien entre {$f($a['bas'])} et {$f($a['haut'])}, avec un prix de présentation conseillé de {$f($a['retenu'])}.";
        if ($ecart !== null && abs($ecart) >= 3) {
            $txt .= $ecart > 0 ? "\n\nLe prix que vous envisagiez ({$f($a['prix_vendeur'])}) est supérieur d'environ " . round($ecart) . " % à cette estimation : un prix ajusté dès la mise en vente attire davantage de visites et évite que l'annonce ne s'use." : "\n\nLe prix que vous envisagiez ({$f($a['prix_vendeur'])}) est en dessous du marché : votre bien peut être présenté plus haut.";
        }
        return $txt;
    }
    $donnees = json_encode(['bien' => array_map(fn ($c) => $c['valeur'], (array) $v['fiche']['champs']), 'avis' => array_diff_key($a, ['comparables' => 1])], JSON_UNESCAPED_UNICODE);
    try {
        $txt = gemini_generate($CONFIG['modele_analyse'], [
            'systemInstruction' => ['parts' => [['text' => "Tu rédiges l'argumentaire d'un avis de valeur immobilier destiné au vendeur, pour {$agent['nom']} ({$CONFIG['agence']}). 3 courts paragraphes, ton professionnel et pédagogique, vouvoiement, texte brut sans markdown. Appuie-toi uniquement sur les données fournies (ventes DVF, ajustements). Mentionne les atouts et points qui pèsent sur le prix. Si le prix souhaité par le vendeur s'écarte de l'estimation, explique-le avec tact."]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $base . "\n\nDonnées : " . $donnees]]]],
        ], 90);
        return trim($txt) ?: $base;
    } catch (Throwable) {
        return $base;
    }
}

function mettre_a_jour_avis(array $user, string $id): array
{
    $v = load_visit($user, $id);
    $a = calculer_avis_valeur($v);
    if ($a) $a['argumentaire'] = argumentaire_avis($v, $a, $user);
    flush_usage($user, $id, 'analyse');
    return update_visit($user, $id, function (array $v) use ($a) {
        if (!$a) return $v;
        $ancien = $v['avis_valeur'] ?? [];
        if (!empty($ancien['retenu_agent'])) $a['retenu'] = $ancien['retenu_agent']; // l'agent a fixé le prix conseillé
        $a['retenu_agent'] = $ancien['retenu_agent'] ?? null;
        $a['argumentaire_perime'] = false;
        $v['avis_valeur'] = $a;
        journal_ajout($v, 'auto', 'Avis de valeur calculé sur ' . count($a['comparables']) . ' ventes comparables : ' . number_format($a['retenu'], 0, ',', ' ') . ' €.');
        return $v;
    });
}

// ---------- PDF ----------

function rendre_avis_valeur(VisitePdf $pdf, array $v, array $agent): void
{
    $a = $v['avis_valeur'] ?? null;
    $pdf->docLabel = 'Avis de valeur';
    $pdf->AddPage();
    $f = fn ($n) => fmt_nombre((string) $n) . "\u{00A0}€";
    $pdf->titleBlock('Avis de valeur', titre_bien($v), 'Établi le ' . fmt_date_fr(null) . ' par ' . $agent['nom']);
    if (!$a) {
        $pdf->richText("Les ventes comparables n'ont pas encore pu être analysées pour ce bien.");
        return;
    }
    $pdf->statBoxes([
        ['label' => 'Prix conseillé', 'valeur' => $f($a['retenu']), 'sombre' => true, 'detail' => fmt_nombre((string) round($a['retenu'] / $a['surface'])) . "\u{00A0}€/m²"],
        ['label' => 'Fourchette basse', 'valeur' => $f($a['bas'])],
        ['label' => 'Fourchette haute', 'valeur' => $f($a['haut'])],
        ['label' => 'Médiane du secteur', 'valeur' => fmt_nombre((string) $a['prix_m2_median']) . "\u{00A0}€/m²"],
    ]);
    $pdf->sectionTitle('Notre analyse');
    $pdf->richText($a['argumentaire']);
    $pdf->Ln(2);

    // Graphique : prix au m² des ventes comparables, le prix conseillé en citron
    $pdf->sectionTitle('Prix au m² des ventes comparables');
    $comp = $a['comparables'];
    $max = max(array_merge(array_column($comp, 'prix_m2'), [$a['retenu'] / $a['surface']])) * 1.1;
    $pdf->ensureSpace(48);
    $y0 = $pdf->GetY() + 34;
    $n = count($comp) + 1;
    $larg = min(12, 160 / $n);
    $x = 22;
    $barres = array_merge(array_map(fn ($c) => [$c['prix_m2'], date('m/y', strtotime($c['date'])), false], $comp), [[(int) round($a['retenu'] / $a['surface']), 'Votre bien', true]]);
    foreach ($barres as [$val, $lib, $nous]) {
        $h = 30 * $val / $max;
        $pdf->SetFillColor(...($nous ? C_CITRON : [205, 199, 186]));
        $pdf->SetDrawColor(...C_ENCRE);
        $pdf->SetLineWidth($nous ? 0.35 : 0);
        $pdf->Rect($x, $y0 - $h, $larg - 2, $h, $nous ? 'FD' : 'F');
        $pdf->SetXY($x - 2, $y0 - $h - 4);
        $pdf->font('mono', 5.2, C_ENCRE);
        $pdf->Cell($larg + 2, 3, fmt_nombre((string) $val), 0, 0, 'C');
        $pdf->SetXY($x - 2, $y0 + 1);
        $pdf->font('mono', 5, $nous ? C_ENCRE : C_GRIS);
        $pdf->Cell($larg + 2, 3, $nous ? 'VOUS' : $lib, 0, 0, 'C');
        $x += $larg;
    }
    $pdf->SetY($y0 + 9);

    $pdf->sectionTitle('Ventes retenues (source : DVF, DGFiP)');
    $pdf->font('mono', 6.2, C_GRIS);
    foreach ([['Date', 18], ['Adresse', 62], ['Surface', 20], ['Pièces', 14], ['Terrain', 20], ['Prix', 24], ['€/m²', 16]] as [$t, $w]) $pdf->Cell($w, 5, mb_strtoupper($t));
    $pdf->Ln(6);
    foreach ($comp as $c) {
        $pdf->ensureSpace(6);
        $pdf->font('', 8.5, C_ENCRE);
        $pdf->Cell(18, 5, date('m/Y', strtotime($c['date'])));
        $pdf->Cell(62, 5, mb_strimwidth($c['adresse'], 0, 36, '…'));
        $pdf->Cell(20, 5, fmt_nombre((string) round($c['surface'])) . ' m²');
        $pdf->Cell(14, 5, (string) ($c['pieces'] ?: '—'));
        $pdf->Cell(20, 5, $c['terrain'] ? fmt_nombre((string) round($c['terrain'])) . ' m²' : '—');
        $pdf->Cell(24, 5, fmt_nombre((string) $c['prix']) . ' €');
        $pdf->Cell(16, 5, fmt_nombre((string) $c['prix_m2']), 0, 1);
    }
    if ($a['ajustements']) {
        $pdf->Ln(3);
        $pdf->font('I', 8.5, C_GRIS);
        $pdf->MultiCell(0, 4.5, 'Ajustements appliqués : ' . implode(' · ', array_map(fn ($x) => $x[0] . ' ' . ($x[1] > 0 ? '+' : '') . round($x[1] * 100) . ' %', $a['ajustements'])) . '.');
    }
    $pdf->Ln(3);
    $pdf->font('I', 8, C_GRIS);
    $pdf->MultiCell(0, 4.2, "Cet avis de valeur est établi à partir des ventes enregistrées par l'administration fiscale (DVF) et des informations recueillies lors de la visite. Il ne constitue pas une expertise immobilière au sens de la Charte de l'expertise en évaluation immobilière." . (!empty($a['simulation']) ? ' (Données de ventes simulées : service public injoignable lors du calcul.)' : ''));
    $pdf->contactBox($agent);
}

document_module(fn (array $v) => [['avis', 'Avis de valeur', !empty($v['avis_valeur']), false, 'avis']]);
pdf_module('avis', 'Avis de valeur', 'rendre_avis_valeur');

route('POST avis', function ($id) {
    $me = require_user();
    $in = json_input();
    if (isset($in['retenu'])) {
        $prix = (int) preg_replace('/\D/', '', (string) $in['retenu']);
        $v = update_visit($me, $id, function (array $v) use ($prix) {
            $v['avis_valeur']['retenu_agent'] = $prix ?: null;
            if ($prix) $v['avis_valeur']['retenu'] = $prix;
            return $v;
        });
        send_json(vue_dossier($v));
    }
    send_json(vue_dossier(mettre_a_jour_avis($me, $id)));
});
