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
        'communes_voisines' => $e['communes_voisines'] ?? [],
        'simulation' => $e['simulation'],
        'argumentaire' => '',
    ];
}

/** Argumentaire rédigé sans IA (pas de clé Gemini, ou rapport demandé avant le premier calcul). */
function argumentaire_simple(array $v, array $a): string
{
    $f = fn ($n) => number_format($n, 0, ',', ' ') . ' €';
    $ecart = $a['ecart_vendeur'];
    $type = mb_strtolower(champ($v, 'type_bien') ?: 'bien');
    $base = "Nous avons étudié " . count($a['comparables']) . " ventes réelles de biens comparables ({$type}s les plus ressemblants : surface, pièces, terrain, proximité) enregistrées par l'administration fiscale" . (!empty($a['communes_voisines']) ? ', dans la commune et, faute de ventes suffisantes, dans les communes voisines (' . implode(', ', $a['communes_voisines']) . ')' : '') . '. '
        . "Une fois actualisés selon l'évolution du marché local, ces prix donnent une valeur de référence de {$f($a['prix_m2_median'])} par m², la plupart des ventes se situant entre {$f($a['prix_m2_bas'])} et {$f($a['prix_m2_haut'])} par m².";
    $txt = $base . "\n\n";
    if ($a['ajustements']) $txt .= 'Les caractéristiques propres à votre bien ont été prises en compte : ' . implode(', ', array_map(fn ($x) => mb_strtolower($x[0]) . ' (' . ($x[1] > 0 ? '+' : '') . round($x[1] * 100) . ' %)', $a['ajustements'])) . ".\n\n";
    $txt .= "Nous estimons la valeur de votre bien entre {$f($a['bas'])} et {$f($a['haut'])}, avec un prix de présentation conseillé de {$f($a['retenu'])}.";
    if ($ecart !== null && abs($ecart) >= 3) {
        $txt .= $ecart > 0 ? "\n\nLe prix que vous envisagiez ({$f($a['prix_vendeur'])}) est supérieur d'environ " . round($ecart) . " % à cette estimation : un prix ajusté dès la mise en vente attire davantage de visites et évite que l'annonce ne s'use." : "\n\nLe prix que vous envisagiez ({$f($a['prix_vendeur'])}) est en dessous du marché : votre bien peut être présenté plus haut.";
    }
    return $txt;
}

function argumentaire_avis(array $v, array $a, array $agent): string
{
    global $CONFIG;
    $f = fn ($n) => number_format($n, 0, ',', ' ') . ' €';
    $ecart = $a['ecart_vendeur'];
    $type = mb_strtolower(champ($v, 'type_bien') ?: 'bien');
    $base = "Nous avons étudié " . count($a['comparables']) . " ventes réelles de biens comparables ({$type}s les plus ressemblants : surface, pièces, terrain, proximité) enregistrées par l'administration fiscale" . (!empty($a['communes_voisines']) ? ', dans la commune et, faute de ventes suffisantes, dans les communes voisines (' . implode(', ', $a['communes_voisines']) . ')' : '') . '. '
        . "Une fois actualisés selon l'évolution du marché local, ces prix donnent une valeur de référence de {$f($a['prix_m2_median'])} par m², la plupart des ventes se situant entre {$f($a['prix_m2_bas'])} et {$f($a['prix_m2_haut'])} par m².";
    if (empty($CONFIG['gemini_api_key'])) return argumentaire_simple($v, $a);
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

/**
 * Rapport d'estimation remis au vendeur : le prix conseillé et tout ce qui le justifie (carte des ventes autour du
 * bien, calcul pas à pas, ventes retenues, évolution du marché, méthode). Même nom de fichier qu'avant (« Avis de
 * valeur ») : c'est la pièce jointe envoyée au vendeur et le document de son espace.
 */
function rendre_avis_valeur(VisitePdf $pdf, array $v, array $agent): void
{
    $a = $v['avis_valeur'] ?? null;
    if (!$a && !empty($v['public']['ventes'])) {
        $a = calculer_avis_valeur($v); // pas encore calculé : chiffres du jour, texte sans IA
        if ($a) $a['argumentaire'] = argumentaire_simple($v, $a);
    }
    $pdf->docLabel = 'Avis de valeur';
    $pdf->AddPage();
    $f = fn ($n) => fmt_nombre((string) round($n)) . "\u{00A0}€";
    $m2 = fn ($n) => fmt_nombre((string) round($n)) . "\u{00A0}€/m²";
    $pdf->titleBlock('Avis de valeur', titre_bien($v), 'Établi le ' . fmt_date_fr(null) . ' par ' . $agent['nom']);
    if (!$a) {
        $pdf->richText("Les ventes comparables n'ont pas encore pu être analysées pour ce bien.");
        return;
    }
    $comp = $a['comparables'];
    $pdf->statBoxes([
        ['label' => 'Prix conseillé', 'valeur' => $f($a['retenu']), 'sombre' => true, 'detail' => $m2($a['retenu'] / $a['surface'])],
        ['label' => 'Fourchette basse', 'valeur' => $f($a['bas'])],
        ['label' => 'Fourchette haute', 'valeur' => $f($a['haut'])],
        ['label' => 'Ventes étudiées', 'valeur' => (string) count($comp), 'detail' => 'confiance ' . ($a['confiance']['niveau'] ?? '')],
    ]);

    // Le bien tel qu'il a été estimé
    $pdf->sectionTitle('Votre bien');
    $c = fn ($k) => champ($v, $k);
    $lignes = array_filter([
        ['label' => 'Type', 'valeur' => $c('type_bien'), 'long' => false],
        ['label' => 'Surface habitable', 'valeur' => $c('surface_habitable') !== '' ? fmt_nombre($c('surface_habitable')) . ' m²' : '', 'long' => false],
        ['label' => 'Pièces · chambres', 'valeur' => trim(($c('nb_pieces') !== '' ? $c('nb_pieces') . ' pièces' : '') . ($c('nb_chambres') !== '' ? ' · ' . $c('nb_chambres') . ' chambres' : ''), ' ·'), 'long' => false],
        ['label' => 'Terrain', 'valeur' => $c('surface_terrain') !== '' ? fmt_nombre($c('surface_terrain')) . ' m²' : '', 'long' => false],
        ['label' => 'État général', 'valeur' => $c('etat_general'), 'long' => false],
        ['label' => 'DPE', 'valeur' => $c('dpe'), 'long' => false],
        ['label' => 'Extérieur', 'valeur' => $c('exterieur'), 'long' => false],
        ['label' => 'Stationnement', 'valeur' => $c('stationnement'), 'long' => false],
    ], fn ($l) => trim((string) $l['valeur']) !== '');
    $pdf->kvGrid(array_values($lignes));

    $pdf->sectionTitle('Notre analyse');
    $pdf->richText($a['argumentaire'] ?: argumentaire_simple($v, $a));
    $pdf->Ln(2);

    // La carte : le bien, les ventes retenues numérotées (comme dans le tableau) et leur prix, les autres ventes
    $geo = $v['public']['geo'] ?? [];
    $carte = !empty($geo['lat']) ? carte_ventes_png($geo, (int) $a['retenu'], $comp, autres_ventes($v, $comp)) : null;
    if ($carte) {
        $h = 174 * $carte['ratio'];
        $pdf->ensureSpace($h + 26);
        $pdf->sectionTitle('Les ventes autour de votre bien');
        $y = $pdf->GetY();
        $pdf->Image($carte['fichier'], 18, $y, 174, $h);
        @unlink($carte['fichier']);
        $pdf->SetY($y + $h + 2.5);
        $legende = [[C_CITRON, 'Votre bien'], [C_VERT, 'Les 4 ventes les plus ressemblantes'], [[95, 127, 36], 'Les suivantes'], [[236, 232, 220], 'Les autres ventes retenues'], [[138, 135, 127], 'Autres ventes du secteur']];
        $x = 18;
        $pdf->font('', 7.6, C_GRIS);
        foreach ($legende as [$rgb, $txt]) {
            $pdf->SetFillColor(...$rgb);
            $pdf->SetDrawColor(...C_ENCRE);
            $pdf->SetLineWidth(0.2);
            $pdf->Rect($x, $pdf->GetY() + 1, 3, 3, 'FD');
            $pdf->SetX($x + 4.2);
            $pdf->Cell($pdf->GetStringWidth($txt) + 5, 5, $txt);
            $x += 4.2 + $pdf->GetStringWidth($txt) + 5;
        }
        $pdf->Ln(6);
        $pdf->font('I', 8, C_GRIS);
        $pdf->MultiCell(0, 4, 'Chaque numéro renvoie au tableau des ventes retenues ; les prix sont ceux réellement payés, enregistrés par l\'administration fiscale.');
        $pdf->Ln(3);
    }

    // Le calcul, pas à pas
    $pdf->sectionTitle('Comment nous avons calculé votre prix');
    $etapes = [
        ['Prix au m² des ventes qui ressemblent le plus à votre bien', 'médiane pondérée par la ressemblance (surface, pièces, terrain, distance, date), prix actualisés au marché d\'aujourd\'hui', $m2($a['prix_m2_median'])],
        ['× surface habitable de votre bien', '', fmt_nombre((string) $a['surface']) . " m² = " . $f(round($a['prix_m2_median'] * $a['surface'] / 1000) * 1000)],
    ];
    if ($a['ajustements']) {
        $etapes[] = ['Ajustements propres à votre bien', implode(' · ', array_map(fn ($x) => $x[0] . ' ' . ($x[1] > 0 ? '+' : '') . round($x[1] * 100) . ' %', $a['ajustements'])), ($a['coefficient'] >= 1 ? '+' : '') . round(($a['coefficient'] - 1) * 100) . ' %'];
    }
    $calcule = (int) (round($a['prix_m2_median'] * $a['surface'] * $a['coefficient'] / 1000) * 1000);
    $etapes[] = ['Valeur estimée', '', $f($calcule)];
    if (!empty($a['retenu_agent']) && (int) $a['retenu_agent'] !== $calcule) $etapes[] = ['Prix de présentation conseillé', 'fixé par votre conseiller à partir de cette valeur et de sa connaissance du secteur', $f($a['retenu'])];
    foreach ($etapes as $i => [$titre, $detail, $valeur]) {
        $pdf->ensureSpace(12);
        $fin = $i === count($etapes) - 1;
        $y = $pdf->GetY();
        $pdf->SetDrawColor(...($fin ? C_ENCRE : C_LIGNE));
        $pdf->SetLineWidth($fin ? 0.35 : 0.2);
        $pdf->Line(18, $y, 192, $y);
        $pdf->SetXY(18, $y + 1.6);
        $pdf->font($fin ? 'black' : 'semi', $fin ? 11 : 9.5, C_ENCRE);
        $pdf->MultiCell(122, 4.8, $titre);
        if ($detail !== '') {
            $pdf->font('', 7.8, C_GRIS);
            $pdf->MultiCell(122, 3.8, $detail);
        }
        $bas = $pdf->GetY();
        $pdf->SetXY(140, $y + 1.6);
        $pdf->font($fin ? 'black' : 'semi', $fin ? 12.5 : 10, C_ENCRE);
        $pdf->Cell(52, 5.5, $valeur, 0, 0, 'R');
        $pdf->SetY(max($bas, $y + 8) + 1.2);
    }
    $pdf->Ln(1.5);
    $pdf->font('', 8.5, C_GRIS);
    $pdf->MultiCell(0, 4.4, "Fourchette : de {$f($a['bas'])} à {$f($a['haut'])}, soit de {$m2($a['prix_m2_bas'])} à {$m2($a['prix_m2_haut'])} selon les ventes retenues (les 20 % les moins chères et les 20 % les plus chères sont écartées)."
        . (!empty($a['communes_voisines']) ? ' Peu de ventes dans la commune : des ventes de ' . implode(', ', $a['communes_voisines']) . ' ont été ajoutées, les plus proches pesant le plus.' : ''));
    if ($a['ecart_vendeur'] !== null && abs($a['ecart_vendeur']) >= 3) {
        $pdf->Ln(1.5);
        $pdf->font('semi', 9, $a['ecart_vendeur'] > 0 ? [154, 50, 8] : C_VERT);
        $pdf->MultiCell(0, 4.6, 'Le prix que vous envisagiez (' . $f($a['prix_vendeur']) . ') est ' . ($a['ecart_vendeur'] > 0 ? 'supérieur' : 'inférieur') . ' d\'environ ' . abs(round($a['ecart_vendeur'])) . ' % à cette estimation.');
    }
    $pdf->Ln(3);

    // Graphique : prix au m² des ventes retenues, le prix conseillé en citron
    $pdf->ensureSpace(62); // titre et graphique sur la même page
    $pdf->sectionTitle('Prix au m² des ventes retenues');
    $max = max(array_merge(array_column($comp, 'prix_m2'), [$a['retenu'] / $a['surface']])) * 1.1;
    $y0 = $pdf->GetY() + 34;
    $n = count($comp) + 1;
    $larg = min(12, 160 / $n);
    $x = 22;
    $barres = array_merge(array_map(fn ($c, $i) => [$c['prix_m2'], 'N° ' . ($i + 1), false], $comp, array_keys($comp)), [[(int) round($a['retenu'] / $a['surface']), 'Votre bien', true]]);
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

    // Les ventes retenues, numérotées comme sur la carte
    $pdf->sectionTitle('Les ventes retenues (source : DVF, DGFiP)');
    $cols = [['N°', 8, 'L'], ['Vendu', 15, 'L'], ['Adresse', 49, 'L'], ['Distance', 15, 'R'], ['Surface', 16, 'R'], ['P.', 8, 'R'], ['Terrain', 17, 'R'], ['Prix', 23, 'R'], ['€/m²', 14, 'R'], ['Ress.', 9, 'R']];
    $pdf->font('mono', 6, C_GRIS);
    foreach ($cols as [$t, $w, $al]) $pdf->Cell($w, 5, mb_strtoupper($t), 0, 0, $al);
    $pdf->Ln(6);
    foreach ($comp as $i => $x) {
        $pdf->ensureSpace(6);
        $pdf->font($i < 4 ? 'semi' : '', 8, C_ENCRE);
        $dist = $x['distance'] ?? null;
        $vals = [(string) ($i + 1), date('m/Y', strtotime($x['date'])), mb_strimwidth($x['adresse'] . (!empty($x['commune']) ? ', ' . $x['commune'] : ''), 0, 30, '…'),
            $dist === null ? '—' : ($dist < 1000 ? $dist . ' m' : number_format($dist / 1000, 1, ',', '') . ' km'),
            fmt_nombre((string) round($x['surface'])) . ' m²', (string) ($x['pieces'] ?: '—'), $x['terrain'] ? fmt_nombre((string) round($x['terrain'])) . ' m²' : '—',
            fmt_nombre((string) $x['prix']) . ' €', fmt_nombre((string) $x['prix_m2']), ($x['similarite'] ?? '') . ' %'];
        foreach ($cols as $k => [$t, $w, $al]) $pdf->Cell($w, 5, $vals[$k], 0, 0, $al);
        $pdf->Ln(5);
    }
    $pdf->font('I', 7.6, C_GRIS);
    $pdf->MultiCell(0, 4, '« Ress. » : ressemblance avec votre bien. Les prix au m² sont ceux de la vente ; ils sont actualisés selon l\'évolution du marché local avant le calcul.');
    $pdf->Ln(3);

    // Le marché local
    $annees = $a['tendance']['par_annee'] ?? [];
    if (count($annees) >= 2) {
        $pdf->ensureSpace(54);
        $pdf->sectionTitle('L\'évolution du marché local');
        $max = max($annees) * 1.15;
        $y0 = $pdf->GetY() + 26;
        $x = 22;
        foreach ($annees as $an => $val) {
            $h = 22 * $val / $max;
            $pdf->SetFillColor(...C_VERT);
            $pdf->Rect($x, $y0 - $h, 14, $h, 'F');
            $pdf->SetXY($x - 4, $y0 - $h - 4);
            $pdf->font('mono', 6, C_ENCRE);
            $pdf->Cell(22, 3, fmt_nombre((string) round($val)), 0, 0, 'C');
            $pdf->SetXY($x - 4, $y0 + 1);
            $pdf->font('mono', 6, C_GRIS);
            $pdf->Cell(22, 3, (string) $an, 0, 0, 'C');
            $x += 24;
        }
        $pdf->SetXY(22 + 24 * count($annees) + 4, $y0 - 22);
        $pdf->font('', 9, C_ENCRE);
        $t = round(($a['tendance']['annuelle'] ?? 0) * 100, 1);
        $pdf->MultiCell(192 - $pdf->GetX(), 4.8, 'Prix médian au m² par année de vente. Tendance retenue : ' . ($t >= 0 ? '+' : '') . str_replace('.', ',', (string) $t) . ' % par an, appliquée aux ventes les plus anciennes pour les ramener au prix d\'aujourd\'hui.');
        $pdf->SetY($y0 + 8);
    }

    $pdf->Ln(2);
    $pdf->font('I', 8, C_GRIS);
    $pdf->MultiCell(0, 4.2, "Cet avis de valeur est établi à partir des ventes enregistrées par l'administration fiscale (DVF) et des informations recueillies lors de la visite. Il ne constitue pas une expertise immobilière au sens de la Charte de l'expertise en évaluation immobilière." . (!empty($a['simulation']) ? ' (Données de ventes simulées : service public injoignable lors du calcul.)' : ''));
    $pdf->contactBox($agent);
}

document_module(fn (array $v) => [['avis', 'Avis de valeur', !empty($v['avis_valeur']) || !empty($v['public']['ventes']), false, 'avis']]);
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
