<?php
// Génération des documents PDF (vrais PDF, polices intégrées) avec tFPDF.
// Documents : fiche du bien, annonce, rapport interne, compte rendu vendeur, dossier complet.

require_once __DIR__ . '/lib/tfpdf/tfpdf.php';
require_once __DIR__ . '/lib/tfpdf/font/unifont/ttfonts.php';

const PDF_DOCS = [
    'fiche'   => 'Fiche du bien',
    'annonce' => 'Annonce',
    'rapport' => 'Rapport de visite (interne)',
    'vendeur' => 'Compte rendu de visite',
    'dossier' => 'Dossier complet (interne)',
];

// Couleurs officielles des étiquettes DPE / GES
const DPE_COULEURS = ['A' => [0, 150, 64], 'B' => [81, 184, 72], 'C' => [170, 204, 36], 'D' => [255, 222, 0], 'E' => [251, 176, 0], 'F' => [235, 100, 30], 'G' => [215, 33, 27]];

class VisitePdf extends tFPDF
{
    public array $brand = [20, 33, 61];
    public string $agence = '';
    public string $coordonnees = '';
    public ?string $logo = null;
    public string $docLabel = '';
    protected array $ink = [24, 28, 38];
    protected array $muted = [110, 116, 130];
    protected array $line = [222, 225, 232];

    public function __construct()
    {
        parent::__construct('P', 'mm', 'A4');
        $this->AddFont('Archivo', '', 'Archivo-Regular.ttf', true);
        $this->AddFont('Archivo', 'B', 'Archivo-Bold.ttf', true);
        $this->AddFont('Archivo', 'I', 'Archivo-Italic.ttf', true);
        $this->AddFont('ArchivoSemi', '', 'Archivo-SemiBold.ttf', true);
        $this->AddFont('ArchivoBlack', '', 'Archivo-ExtraBold.ttf', true);
        $this->SetMargins(18, 18, 18);
        $this->SetAutoPageBreak(true, 22);
        $this->AliasNbPages();
        $this->SetCreator('Visite Immo', true);
    }

    // ---------- En-tête et pied de page ----------

    public function Header(): void
    {
        if ($this->PageNo() === 1) {
            // Papier à en-tête : logo à gauche, agence à droite
            $logoBas = 14;
            if ($this->logo && is_file($this->logo)) {
                [$w, $h] = @getimagesize($this->logo) ?: [1, 1];
                $hauteur = min(16, 52 * $h / max($w, 1));
                $this->Image($this->logo, 18, 13, 0, $hauteur);
                $logoBas = 13 + $hauteur;
            } else {
                $this->SetXY(18, 14);
                $this->font('black', 15, $this->brand);
                $this->Cell(100, 7, $this->agence);
                $logoBas = 22;
            }
            $this->SetXY(110, 13);
            $this->font('semi', 9.5, $this->ink);
            $this->Cell(82, 5, $this->agence, 0, 2, 'R');
            $this->font('', 8, $this->muted);
            foreach (array_filter(array_map('trim', explode("\n", $this->coordonnees))) as $l) $this->Cell(82, 3.9, $l, 0, 2, 'R');
            $y = max($logoBas, $this->GetY()) + 5;
            $this->SetDrawColor(...$this->brand);
            $this->SetLineWidth(0.7);
            $this->Line(18, $y, 192, $y);
            $this->SetY($y + 8);
        } else {
            $this->SetXY(18, 10);
            $this->font('semi', 8, $this->muted);
            $this->Cell(87, 4, $this->agence);
            $this->Cell(87, 4, mb_strtoupper($this->docLabel), 0, 0, 'R');
            $this->SetDrawColor(...$this->line);
            $this->SetLineWidth(0.3);
            $this->Line(18, 16, 192, 16);
            $this->SetY(24);
        }
    }

    public function Footer(): void
    {
        $this->SetY(-14);
        $this->SetDrawColor(...$this->line);
        $this->SetLineWidth(0.3);
        $this->Line(18, $this->GetY(), 192, $this->GetY());
        $this->Ln(2.5);
        $this->font('', 7.5, $this->muted);
        $this->Cell(140, 4, $this->agence . ($this->docLabel ? ' · ' . $this->docLabel : ''));
        $this->Cell(34, 4, 'Page ' . $this->PageNo() . ' / {nb}', 0, 0, 'R');
    }

    // Texte aligné à gauche par défaut (le justifié étire les mots des titres)
    public function MultiCell($w, $h, $txt, $border = 0, $align = 'L', $fill = false)
    {
        parent::MultiCell($w, $h, $txt, $border, $align, $fill);
    }

    // ---------- Outils de mise en page ----------

    public function font(string $style, float $size, array $color): void
    {
        match ($style) {
            'black' => $this->SetFont('ArchivoBlack', '', $size),
            'semi'  => $this->SetFont('ArchivoSemi', '', $size),
            default => $this->SetFont('Archivo', $style, $size),
        };
        $this->SetTextColor(...$color);
    }

    /** Couleur de marque éclaircie (0 = couleur pure, 1 = blanc). */
    public function tint(float $ratio): array
    {
        return array_map(fn ($c) => (int) round($c + (255 - $c) * $ratio), $this->brand);
    }

    public function ensureSpace(float $h): void
    {
        if ($this->GetY() + $h > $this->PageBreakTrigger) $this->AddPage();
    }

    public function roundedRect(float $x, float $y, float $w, float $h, float $r, string $style = 'F'): void
    {
        $k = $this->k;
        $hp = $this->h;
        $op = match ($style) { 'F' => 'f', 'FD', 'DF' => 'B', default => 'S' };
        $arc = 4 / 3 * (M_SQRT2 - 1);
        $this->_out(sprintf('%.2F %.2F m', ($x + $r) * $k, ($hp - $y) * $k));
        $xc = $x + $w - $r; $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - $y) * $k));
        $this->arc($xc + $r * $arc, $yc - $r, $xc + $r, $yc - $r * $arc, $xc + $r, $yc);
        $xc = $x + $w - $r; $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($hp - $yc) * $k));
        $this->arc($xc + $r, $yc + $r * $arc, $xc + $r * $arc, $yc + $r, $xc, $yc + $r);
        $xc = $x + $r; $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - ($y + $h)) * $k));
        $this->arc($xc - $r * $arc, $yc + $r, $xc - $r, $yc + $r * $arc, $xc - $r, $yc);
        $xc = $x + $r; $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $x * $k, ($hp - $yc) * $k));
        $this->arc($xc - $r, $yc - $r * $arc, $xc - $r * $arc, $yc - $r, $xc, $yc - $r);
        $this->_out($op);
    }

    protected function arc(float $x1, float $y1, float $x2, float $y2, float $x3, float $y3): void
    {
        $h = $this->h;
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c', $x1 * $this->k, ($h - $y1) * $this->k, $x2 * $this->k, ($h - $y2) * $this->k, $x3 * $this->k, ($h - $y3) * $this->k));
    }

    /** Étiquette, grand titre et ligne d'informations en tête de document. */
    public function titleBlock(string $label, string $titre, string $meta, ?string $pastille = null): void
    {
        $this->font('semi', 8.5, $this->brand);
        $this->Cell($this->GetStringWidth(mb_strtoupper($label)) + 2, 5, mb_strtoupper($label));
        if ($pastille) {
            $this->SetFillColor(215, 33, 27);
            $this->font('semi', 7, [255, 255, 255]);
            $w = $this->GetStringWidth($pastille) + 5;
            $this->roundedRect($this->GetX() + 2, $this->GetY() + 0.4, $w, 4.4, 2.2);
            $this->SetX($this->GetX() + 2);
            $this->Cell($w, 5, $pastille, 0, 0, 'C');
        }
        $this->Ln(7);
        $this->font('black', 21, $this->ink);
        $this->MultiCell(0, 9, $titre);
        $this->Ln(1);
        $this->font('', 9.5, $this->muted);
        $this->MultiCell(0, 5, $meta);
        $this->Ln(6);
    }

    public function sectionTitle(string $titre): void
    {
        $this->ensureSpace(18);
        $y = $this->GetY();
        $this->SetFillColor(...$this->brand);
        $this->Rect(18, $y + 0.6, 1.3, 4.6, 'F');
        $this->SetX(22);
        $this->font('semi', 10.5, $this->ink);
        $this->Cell(0, 6, mb_strtoupper($titre));
        $this->Ln(8);
    }

    /** Rangée de chiffres clés dans des cartouches arrondis. */
    public function statBoxes(array $stats): void
    {
        if (!$stats) return;
        $stats = array_slice($stats, 0, 5);
        $gap = 3;
        $w = (174 - $gap * (count($stats) - 1)) / count($stats);
        $h = 19;
        $this->ensureSpace($h + 6);
        $y = $this->GetY();
        foreach ($stats as $i => $s) {
            $x = 18 + $i * ($w + $gap);
            if (isset($s['dpe'])) {
                $this->SetFillColor(...(DPE_COULEURS[$s['dpe']] ?? $this->brand));
                $this->roundedRect($x, $y, $w, $h, 2.5);
                $clair = in_array($s['dpe'], ['C', 'D', 'E'], true);
                $this->SetXY($x, $y + 2.6);
                $this->font('black', 16, $clair ? $this->ink : [255, 255, 255]);
                $this->Cell($w, 8, $s['dpe'], 0, 2, 'C');
                $this->font('semi', 7.5, $clair ? $this->ink : [255, 255, 255]);
                $this->Cell($w, 4.5, $s['label'], 0, 0, 'C');
                continue;
            }
            $this->SetFillColor(...$this->tint(0.92));
            $this->roundedRect($x, $y, $w, $h, 2.5);
            $this->SetXY($x, $y + 3);
            $taille = 14;
            $this->font('black', $taille, $this->brand);
            while ($this->GetStringWidth($s['valeur']) > $w - 4 && $taille > 8) $this->font('black', --$taille, $this->brand);
            $this->Cell($w, 7.5, $s['valeur'], 0, 2, 'C');
            $this->font('', 7.5, $this->muted);
            $this->Cell($w, 4, $s['label'], 0, 0, 'C');
        }
        $this->SetY($y + $h + 8);
    }

    /** Tableau libellé / valeur sur deux colonnes ; les textes longs prennent toute la largeur. */
    public function kvGrid(array $rows): void
    {
        $courts = array_values(array_filter($rows, fn ($r) => !$r['long']));
        $longs = array_values(array_filter($rows, fn ($r) => $r['long']));
        $colW = 84;
        for ($i = 0; $i < count($courts); $i += 2) {
            $this->ensureSpace(11);
            $y = $this->GetY();
            $hauteur = 0;
            foreach ([0, 1] as $c) {
                if (!isset($courts[$i + $c])) continue;
                $r = $courts[$i + $c];
                $x = 18 + $c * ($colW + 6);
                $this->SetXY($x, $y);
                $this->font('', 7.5, $this->muted);
                $this->Cell($colW, 4, mb_strtoupper($r['label']), 0, 2);
                $this->font('semi', 10.5, $this->ink);
                $this->MultiCell($colW, 5, $r['valeur']);
                $hauteur = max($hauteur, $this->GetY() - $y);
            }
            $this->SetY($y + $hauteur + 1.5);
            $this->SetDrawColor(...$this->line);
            $this->SetLineWidth(0.2);
            $this->Line(18, $this->GetY(), 192, $this->GetY());
            $this->Ln(2.5);
        }
        foreach ($longs as $r) {
            $this->ensureSpace(14);
            $this->font('', 7.5, $this->muted);
            $this->Cell(0, 4, mb_strtoupper($r['label']), 0, 2);
            $this->font('', 10.5, $this->ink);
            $this->MultiCell(0, 5.2, $r['valeur']);
            $this->Ln(1.5);
            $this->SetDrawColor(...$this->line);
            $this->Line(18, $this->GetY(), 192, $this->GetY());
            $this->Ln(2.5);
        }
        $this->Ln(4);
    }

    /**
     * Texte rédigé par l'IA : les lignes en MAJUSCULES deviennent des intertitres,
     * les lignes « - » des puces, le reste des paragraphes.
     */
    public function richText(string $texte, float $taille = 10.5): void
    {
        foreach (preg_split('/\R/', trim($texte)) as $ligne) {
            $ligne = rtrim($ligne);
            if ($ligne === '') { $this->Ln(2.6); continue; }
            $lettres = preg_replace('/[^\p{L}]/u', '', $ligne);
            if (mb_strlen($lettres) >= 3 && $lettres === mb_strtoupper($lettres) && !str_starts_with($ligne, '-') && mb_strlen($ligne) < 70) {
                $this->Ln(2);
                $this->sectionTitle(rtrim($ligne, ' :'));
                continue;
            }
            if (preg_match('/^\s*[-•]\s+(.*)$/u', $ligne, $m)) {
                $this->ensureSpace(7);
                $y = $this->GetY();
                $this->SetFillColor(...$this->brand);
                $this->roundedRect(20, $y + 2, 1.6, 1.6, 0.8);
                $this->SetX(25);
                $this->font('', $taille, $this->ink);
                $this->MultiCell(0, $taille * 0.52, $m[1]);
                $this->Ln(1);
                continue;
            }
            $this->ensureSpace(7);
            $this->font('', $taille, $this->ink);
            $this->MultiCell(0, $taille * 0.55, $ligne);
            $this->Ln(1);
        }
    }

    /** Encadré de contact de l'agent. */
    public function contactBox(array $agent): void
    {
        $lignes = array_filter([$agent['telephone'] ?? '', $agent['email'] ?? '']);
        $this->ensureSpace(24);
        $y = $this->GetY() + 3;
        $this->SetFillColor(...$this->tint(0.92));
        $this->roundedRect(18, $y, 174, 18, 2.5);
        $this->SetXY(24, $y + 3.5);
        $this->font('', 7.5, $this->muted);
        $this->Cell(0, 4, 'VOTRE CONTACT', 0, 2);
        $this->font('semi', 11, $this->ink);
        $this->Cell(0, 5.5, $agent['nom'] . ' · ' . $this->agence, 0, 2);
        $this->font('', 9.5, $this->brand);
        $this->Cell(0, 4.5, implode('   ·   ', $lignes), 0, 2);
        $this->SetY($y + 24);
    }
}

// ---------- Données ----------

function fmt_nombre(string $v): string
{
    if (!is_numeric($v)) return $v;
    if (abs((float) $v) < 10000) return str_replace('.', ',', (string) (float) $v); // 1978, 125, 1450 : pas de séparateur
    return number_format((float) $v, fmod((float) $v, 1) ? 1 : 0, ',', "\u{00A0}");
}

function fmt_date_fr(?string $iso): string
{
    $mois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $t = $iso ? strtotime($iso) : time();
    return date('j', $t) . ' ' . $mois[(int) date('n', $t) - 1] . ' ' . date('Y', $t);
}

function fiche_valeur(array $champs, string $cle): string
{
    return trim((string) ($champs[$cle]['valeur'] ?? ''));
}

function chiffres_cles(array $champs): array
{
    $v = fn ($k) => fiche_valeur($champs, $k);
    $stats = [];
    if ($v('prix_souhaite') !== '') {
        $stats[] = ['valeur' => fmt_nombre($v('prix_souhaite')) . "\u{00A0}€", 'label' => is_numeric($v('surface_habitable')) && (float) $v('surface_habitable') > 0 && is_numeric($v('prix_souhaite'))
            ? 'Prix · ' . fmt_nombre((string) round((float) $v('prix_souhaite') / (float) $v('surface_habitable'))) . "\u{00A0}€/m²" : 'Prix'];
    }
    if ($v('surface_habitable') !== '') $stats[] = ['valeur' => fmt_nombre($v('surface_habitable')) . "\u{00A0}m²", 'label' => 'Surface habitable'];
    $pieces = array_filter([$v('nb_pieces') !== '' ? $v('nb_pieces') . ' p.' : '', $v('nb_chambres') !== '' ? $v('nb_chambres') . ' ch.' : '']);
    if ($pieces) $stats[] = ['valeur' => implode(' · ', $pieces), 'label' => $v('nb_pieces') !== '' ? 'Pièces · chambres' : 'Chambres'];
    if ($v('surface_terrain') !== '') $stats[] = ['valeur' => fmt_nombre($v('surface_terrain')) . "\u{00A0}m²", 'label' => 'Terrain'];
    if (preg_match('/^[A-G]$/', $v('dpe'))) $stats[] = ['dpe' => $v('dpe'), 'label' => 'DPE'];
    return $stats;
}

function lignes_fiche(array $champs, bool $avecVendeur): array
{
    $sections = [];
    foreach (SECTIONS as $s) {
        if (!$avecVendeur && $s['titre'] === 'Vendeur & projet') continue;
        $rows = [];
        foreach ($s['champs'] as $c) {
            $val = fiche_valeur($champs, $c['cle']);
            if ($val === '') continue;
            if ($c['type'] === 'number') $val = fmt_nombre($val) . (isset($c['unite']) ? "\u{00A0}{$c['unite']}" : '');
            if ($c['type'] === 'bool') $val = ucfirst($val);
            $rows[] = ['label' => $c['label'], 'valeur' => $val, 'long' => $c['type'] === 'textarea' || mb_strlen($val) > 45];
        }
        if ($rows) $sections[] = [$s['titre'], $rows];
    }
    return $sections;
}

// ---------- Documents ----------

function pdf_nouveau(string $label): VisitePdf
{
    global $CONFIG;
    $pdf = new VisitePdf();
    $pdf->agence = (string) $CONFIG['agence'];
    $pdf->coordonnees = (string) ($CONFIG['agence_coordonnees'] ?? '');
    $pdf->brand = hex_rgb((string) ($CONFIG['couleur'] ?? '')) ?? [20, 33, 61];
    $pdf->logo = logo_path();
    $pdf->docLabel = $label;
    $pdf->SetAuthor($pdf->agence, true);
    return $pdf;
}

function hex_rgb(string $hex): ?array
{
    return preg_match('/^#?([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', trim($hex), $m) ? [hexdec($m[1]), hexdec($m[2]), hexdec($m[3])] : null;
}

function titre_bien(array $visit): string
{
    $c = (array) $visit['fiche']['champs'];
    $adresse = trim(fiche_valeur($c, 'adresse') . ' ' . fiche_valeur($c, 'ville'));
    return $visit['titre'] ?: ($adresse ?: 'Visite du ' . fmt_date_fr($visit['cree_le']));
}

function rendre_fiche(VisitePdf $pdf, array $visit, array $agent, bool $avecVendeur): void
{
    $champs = (array) $visit['fiche']['champs'];
    $pdf->docLabel = 'Fiche du bien';
    $pdf->AddPage();
    $type = fiche_valeur($champs, 'type_bien');
    $pdf->titleBlock('Fiche du bien' . ($type ? " · $type" : ''), titre_bien($visit), 'Visite du ' . fmt_date_fr($visit['cree_le']) . ' · ' . $agent['nom']);
    $pdf->statBoxes(chiffres_cles($champs));
    foreach (lignes_fiche($champs, $avecVendeur) as [$titre, $rows]) {
        $pdf->sectionTitle($titre);
        $pdf->kvGrid($rows);
    }
}

function rendre_annonce(VisitePdf $pdf, array $visit, array $agent): void
{
    $champs = (array) $visit['fiche']['champs'];
    $pdf->docLabel = 'Annonce';
    $pdf->AddPage();
    $lieu = implode(' · ', array_filter([fiche_valeur($champs, 'type_bien'), fiche_valeur($champs, 'ville')]));
    $pdf->titleBlock('À vendre', $visit['titre_annonce'] ?: titre_bien($visit), $lieu ?: titre_bien($visit));
    $pdf->statBoxes(chiffres_cles($champs));
    $pdf->richText($visit['annonce'], 11);
    $pdf->Ln(4);
    $pdf->contactBox($agent);
}

function rendre_rapport(VisitePdf $pdf, array $visit, array $agent): void
{
    $pdf->docLabel = 'Rapport de visite (interne)';
    $pdf->AddPage();
    $pdf->titleBlock('Rapport de visite', titre_bien($visit), 'Visite du ' . fmt_date_fr($visit['cree_le']) . ' · ' . $agent['nom'], 'CONFIDENTIEL');
    $pdf->statBoxes(chiffres_cles((array) $visit['fiche']['champs']));
    $pdf->richText($visit['rapport_agent'], 10.5);
}

function rendre_vendeur(VisitePdf $pdf, array $visit, array $agent): void
{
    $champs = (array) $visit['fiche']['champs'];
    $pdf->docLabel = 'Compte rendu de visite';
    $pdf->AddPage();
    // Bloc destinataire, à droite comme un courrier
    $vendeur = fiche_valeur($champs, 'nom_vendeur');
    $pdf->SetX(118);
    $pdf->font('semi', 10, [24, 28, 38]);
    if ($vendeur) $pdf->MultiCell(74, 5, $vendeur);
    $pdf->SetX(118);
    $pdf->font('', 9.5, [110, 116, 130]);
    $pdf->MultiCell(74, 4.8, trim(fiche_valeur($champs, 'adresse') . "\n" . fiche_valeur($champs, 'ville')));
    $pdf->Ln(6);
    $pdf->SetX(118);
    $pdf->Cell(74, 5, 'Le ' . fmt_date_fr(null));
    $pdf->Ln(12);
    $pdf->font('semi', 10.5, $pdf->brand);
    $pdf->MultiCell(0, 5.5, 'Objet : compte rendu de la visite du ' . fmt_date_fr($visit['cree_le']) . ' · ' . titre_bien($visit));
    $pdf->Ln(5);
    $pdf->richText($visit['rapport_vendeur'], 10.5);
}

/** Construit le PDF demandé et renvoie [contenu binaire, nom de fichier]. */
function build_pdf(string $doc, array $visit, array $agent): array
{
    if (!isset(PDF_DOCS[$doc])) fail(400, 'Document inconnu.');
    $pdf = pdf_nouveau(PDF_DOCS[$doc]);
    $pdf->SetTitle(PDF_DOCS[$doc] . ' · ' . titre_bien($visit), true);
    match ($doc) {
        'fiche'   => rendre_fiche($pdf, $visit, $agent, false),
        'annonce' => rendre_annonce($pdf, $visit, $agent),
        'rapport' => rendre_rapport($pdf, $visit, $agent),
        'vendeur' => rendre_vendeur($pdf, $visit, $agent),
        'dossier' => (function () use ($pdf, $visit, $agent) {
            rendre_fiche($pdf, $visit, $agent, true);
            if (trim($visit['annonce']) !== '') rendre_annonce($pdf, $visit, $agent);
            if (trim($visit['rapport_agent']) !== '') rendre_rapport($pdf, $visit, $agent);
            if (trim($visit['rapport_vendeur']) !== '') rendre_vendeur($pdf, $visit, $agent);
        })(),
    };
    $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', titre_bien($visit)) ?: 'visite')), '-');
    $nom = ['fiche' => 'Fiche', 'annonce' => 'Annonce', 'rapport' => 'Rapport-interne', 'vendeur' => 'Compte-rendu', 'dossier' => 'Dossier'][$doc];
    return [$pdf->Output('S'), "$nom-" . substr($slug ?: 'visite', 0, 60) . '.pdf'];
}
