<?php
// Croquis de plan 2D : les pièces citées pendant la visite (et leurs surfaces) sont disposées par niveau.
// C'est un croquis indicatif, non coté : le vrai plan mesuré (scan LiDAR) demandera une appli native.

const SURFACES_TYPES = ['séjour' => 28, 'salon' => 22, 'salle à manger' => 15, 'cuisine' => 11, 'chambre' => 11, 'bureau' => 9, 'salle de bain' => 6,
    "salle d'eau" => 4, 'wc' => 2, 'entrée' => 5, 'couloir' => 5, 'dégagement' => 4, 'palier' => 4, 'buanderie' => 5, 'cellier' => 4, 'dressing' => 4,
    'garage' => 30, 'cave' => 10, 'grenier' => 15, 'véranda' => 14, 'mezzanine' => 12];

function surface_piece(array $p): float
{
    $s = (float) ($p['surface'] ?? 0);
    if ($s > 0) return $s;
    $nom = mb_strtolower((string) $p['nom']);
    foreach (SURFACES_TYPES as $k => $v) if (str_contains($nom, $k)) return $v;
    return 8;
}

/** Disposition « en carrés » (squarified) de pièces dans un rectangle : renvoie [x, y, l, h, pièce] en unités du rectangle. */
function disposer(array $pieces, float $x, float $y, float $l, float $h): array
{
    $total = array_sum(array_map('surface_piece', $pieces));
    if (!$pieces || $total <= 0) return [];
    usort($pieces, fn ($a, $b) => surface_piece($b) <=> surface_piece($a));
    $echelle = $l * $h / $total;
    $rects = [];
    $reste = $pieces;
    while ($reste) {
        $court = min($l, $h);
        $rangee = [];
        $pire = INF;
        foreach ($reste as $p) {
            $essai = array_merge($rangee, [$p]);
            $aires = array_map(fn ($q) => surface_piece($q) * $echelle, $essai);
            $s = array_sum($aires);
            $epaisseur = $s / $court;
            $ratio = max(array_map(fn ($a) => max($epaisseur / ($a / $epaisseur), ($a / $epaisseur) / $epaisseur), $aires));
            if ($ratio > $pire) break;
            $pire = $ratio;
            $rangee = $essai;
        }
        $aire = array_sum(array_map(fn ($q) => surface_piece($q) * $echelle, $rangee));
        $epaisseur = $aire / $court;
        $pos = 0;
        foreach ($rangee as $p) {
            $long = surface_piece($p) * $echelle / $epaisseur;
            $rects[] = $l >= $h ? [$x, $y + $pos, $epaisseur, $long, $p] : [$x + $pos, $y, $long, $epaisseur, $p];
            $pos += $long;
        }
        if ($l >= $h) { $x += $epaisseur; $l -= $epaisseur; } else { $y += $epaisseur; $h -= $epaisseur; }
        $reste = array_slice($reste, count($rangee));
    }
    return $rects;
}

/** Pièces regroupées par niveau, chaque niveau disposé dans un rectangle de proportions 4:3. */
function plan_niveaux(array $v): array
{
    $niveaux = [];
    foreach ($v['plan']['pieces'] ?? [] as $p) $niveaux[trim((string) ($p['niveau'] ?? '')) ?: 'RDC'][] = $p;
    $out = [];
    foreach ($niveaux as $nom => $pieces) {
        $total = array_sum(array_map('surface_piece', $pieces));
        $out[] = ['nom' => $nom, 'surface' => $total, 'rects' => disposer($pieces, 0, 0, 4, 3)];
    }
    return $out;
}

function plan_svg(array $v): string
{
    $niv = plan_niveaux($v);
    if (!$niv) return '';
    $u = 100; // 1 unité = 100 px
    $svg = '';
    $y0 = 0;
    foreach ($niv as $n) {
        $svg .= sprintf('<text x="0" y="%d" class="pl-niv">%s · %s m²</text>', $y0 + 22, htmlspecialchars(mb_strtoupper($n['nom'])), fmt_nombre((string) round($n['surface'])));
        $svg .= sprintf('<rect x="0" y="%d" width="%d" height="%d" class="pl-mur"/>', $y0 + 34, 4 * $u, 3 * $u);
        foreach ($n['rects'] as [$x, $y, $l, $h, $p]) {
            $cx = $x * $u + $l * $u / 2;
            $cy = $y0 + 34 + $y * $u + $h * $u / 2;
            $taille = max(9, min(15, $l * $u / 8));
            $svg .= sprintf('<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" class="pl-piece"/>', $x * $u, $y0 + 34 + $y * $u, $l * $u, $h * $u);
            $svg .= sprintf('<text x="%.1f" y="%.1f" class="pl-nom" font-size="%.1f">%s</text>', $cx, $cy - 2, $taille, htmlspecialchars((string) $p['nom']));
            $svg .= sprintf('<text x="%.1f" y="%.1f" class="pl-surf" font-size="%.1f">%s m²%s</text>', $cx, $cy + $taille, $taille * 0.8, fmt_nombre((string) round(surface_piece($p), 1)), ((float) ($p['surface'] ?? 0)) > 0 ? '' : ' ≈');
        }
        $y0 += 3 * $u + 60;
    }
    return sprintf('<svg xmlns="http://www.w3.org/2000/svg" viewBox="-4 -4 %d %d" class="plan-svg"><style>.pl-mur{fill:none;stroke:#111114;stroke-width:6}.pl-piece{fill:#fffdf8;stroke:#111114;stroke-width:2}.pl-nom{font:700 13px Archivo,sans-serif;text-anchor:middle;fill:#111114}.pl-surf{font:500 11px "JetBrains Mono",monospace;text-anchor:middle;fill:#6d6b64}.pl-niv{font:700 13px "JetBrains Mono",monospace;fill:#2e7d3a;letter-spacing:1px}</style>%s</svg>', 4 * $u + 8, $y0 - 52, $svg);
}

function rendre_plan(VisitePdf $pdf, array $v, array $agent): void
{
    $niv = plan_niveaux($v);
    if (!$niv) throw new RuntimeException('Pas de pièces pour le plan.');
    $pdf->docLabel = 'Croquis de plan';
    $pdf->AddPage();
    $pdf->titleBlock('Croquis de plan', titre_bien($v), 'Disposition indicative des pièces citées lors de la visite · non coté, non contractuel');
    $u = 34; // mm par unité
    foreach ($niv as $n) {
        $pdf->ensureSpace(3 * $u + 14);
        $pdf->font('mono', 8, C_VERT);
        $pdf->Cell(0, 5, mb_strtoupper($n['nom']) . ' · ' . fmt_nombre((string) round($n['surface'])) . ' M²', 0, 1);
        $x0 = 18 + (174 - 4 * $u) / 2;
        $y0 = $pdf->GetY() + 2;
        $pdf->SetLineWidth(0.4);
        $pdf->SetDrawColor(...C_ENCRE);
        $pdf->SetFillColor(...C_PAPIER);
        foreach ($n['rects'] as [$x, $y, $l, $h, $p]) {
            $pdf->Rect($x0 + $x * $u, $y0 + $y * $u, $l * $u, $h * $u, 'FD');
            $pdf->font('semi', max(6, min(9.5, $l * $u / 5)), C_ENCRE);
            $pdf->SetXY($x0 + $x * $u, $y0 + $y * $u + $h * $u / 2 - 4);
            $pdf->Cell($l * $u, 4, (string) $p['nom'], 0, 2, 'C');
            $pdf->font('mono', 6.5, C_GRIS);
            $pdf->Cell($l * $u, 4, fmt_nombre((string) round(surface_piece($p), 1)) . ' m²' . (((float) ($p['surface'] ?? 0)) > 0 ? '' : ' (estimé)'), 0, 2, 'C');
        }
        $pdf->SetLineWidth(1.2);
        $pdf->Rect($x0, $y0, 4 * $u, 3 * $u, 'D');
        $pdf->SetY($y0 + 3 * $u + 8);
    }
    $pdf->font('I', 8, C_GRIS);
    $pdf->MultiCell(0, 4.2, "Croquis généré automatiquement d'après les pièces et surfaces citées pendant la visite. Les proportions sont indicatives ; seules les surfaces mesurées (loi Carrez / Boutin) font foi.");
}

document_module(fn (array $v) => [['plan', 'Croquis de plan', !empty($v['plan']['pieces']), false, 'plan'], ['social', 'Publications réseaux sociaux', !empty($v['posts']), false, 'social']]);
pdf_module('plan', 'Croquis de plan', 'rendre_plan');

route('GET plan', fn ($id) => send_json(['svg' => plan_svg(load_visit(require_user(), $id))]));
route('POST plan', function ($id) {
    $me = require_user();
    $pieces = [];
    foreach ((array) (json_input()['pieces'] ?? []) as $p) {
        $nom = mb_substr(trim((string) ($p['nom'] ?? '')), 0, 40);
        if ($nom === '') continue;
        $pieces[] = ['nom' => $nom, 'surface' => max(0, (float) str_replace(',', '.', (string) ($p['surface'] ?? 0))), 'niveau' => mb_substr(trim((string) ($p['niveau'] ?? 'RDC')), 0, 20) ?: 'RDC'];
    }
    $v = update_visit($me, $id, function (array $v) use ($pieces) {
        $v['plan'] = ['pieces' => $pieces, 'modifie_par_agent' => true];
        return $v;
    });
    send_json(['svg' => plan_svg($v), 'pieces' => $pieces]);
});
