<?php
// Génération des documents PDF (vrais PDF, polices intégrées) avec tFPDF, à la charte Synapse :
// fond crème, encre noire, surlignage citron, étiquette orange, cartes à contour noir, bandeau noir.
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

// Charte Synapse
const C_CREME = [244, 241, 234];
const C_PAPIER = [255, 253, 248];
const C_ENCRE = [17, 17, 20];
const C_GRIS = [109, 107, 100];
const C_LIGNE = [214, 208, 195];
const C_CITRON = [212, 242, 46];
const C_ORANGE = [255, 107, 53];
const C_VERT = [46, 125, 58];
const C_ROUGE = [232, 85, 43];

class VisitePdf extends tFPDF
{
    public array $brand = C_VERT;
    public string $agence = '';
    public string $coordonnees = '';
    public ?string $logo = null;
    public string $docLabel = '';

    public function __construct()
    {
        parent::__construct('P', 'mm', 'A4');
        $this->AddFont('Archivo', '', 'Archivo-Regular.ttf', true);
        $this->AddFont('Archivo', 'B', 'Archivo-Bold.ttf', true);
        $this->AddFont('Archivo', 'I', 'Archivo-Italic.ttf', true);
        $this->AddFont('ArchivoSemi', '', 'Archivo-SemiBold.ttf', true);
        $this->AddFont('ArchivoBlack', '', 'Archivo-ExtraBold.ttf', true);
        $this->AddFont('Mono', '', 'JetBrainsMono-Medium.ttf', true);
        $this->SetMargins(18, 18, 18);
        $this->SetAutoPageBreak(true, 26);
        $this->AliasNbPages();
        $this->SetCreator('Visite Immo · Synapse', true);
    }

    // Texte aligné à gauche par défaut (le justifié étire les mots des titres)
    public function MultiCell($w, $h, $txt, $border = 0, $align = 'L', $fill = false)
    {
        parent::MultiCell($w, $h, $txt, $border, $align, $fill);
    }

    // ---------- En-tête et pied de page ----------

    public function Header(): void
    {
        // Fond crème sur toute la page
        $this->SetFillColor(...C_CREME);
        $this->Rect(0, 0, $this->w, $this->h, 'F');

        if ($this->PageNo() === 1) {
            $bas = 30;
            if ($this->logo && is_file($this->logo)) {
                [$w, $h] = @getimagesize($this->logo) ?: [1, 1];
                $hauteur = min(17, 70 * $h / max($w, 1));
                $this->Image($this->logo, 18, 13, 0, $hauteur);
                $bas = 13 + $hauteur;
            } else {
                $this->SetXY(18, 14);
                $this->font('black', 20, C_ENCRE);
                $this->Cell(100, 9, $this->agence);
                $bas = 24;
            }
            $this->SetXY(108, 14);
            $this->font('mono', 6.8, C_ENCRE);
            $this->Cell(84, 4, mb_strtoupper($this->agence), 0, 2, 'R');
            $this->font('mono', 6.6, C_GRIS);
            foreach (array_filter(array_map('trim', explode("\n", $this->coordonnees))) as $l) $this->Cell(84, 3.7, mb_strtoupper($l), 0, 2, 'R');
            $this->SetY(max($bas, $this->GetY()) + 9);
        } else {
            $this->SetXY(18, 10);
            $this->font('mono', 6.8, C_GRIS);
            $this->Cell(87, 4, mb_strtoupper($this->agence));
            $this->Cell(87, 4, mb_strtoupper($this->docLabel), 0, 0, 'R');
            $this->SetDrawColor(...C_ENCRE);
            $this->SetLineWidth(0.35);
            $this->Line(18, 16, 192, 16);
            $this->SetY(24);
        }
    }

    /** Bandeau noir en bas de page, façon « ticker », avec étoiles citron. */
    public function Footer(): void
    {
        $y = $this->h - 17;
        $this->SetFillColor(...C_ENCRE);
        $this->roundedRect(18, $y, 174, 8, 2);
        $morceaux = [mb_strtoupper($this->agence), mb_strtoupper($this->docLabel), 'PAGE ' . $this->PageNo() . ' / {nb}'];
        $this->font('mono', 6.6, C_CREME);
        $x = 23;
        foreach ($morceaux as $i => $m) {
            if ($i > 0) {
                $this->star($x + 2.2, $y + 4, 1.5, C_CITRON);
                $x += 6;
            }
            $this->SetXY($x, $y + 2);
            $w = $this->GetStringWidth(str_replace('{nb}', '99', $m)) + 1;
            $this->Cell($w, 4, $m);
            $x += $w + 1.5;
        }
    }

    // ---------- Outils de mise en page ----------

    public function font(string $style, float $size, array $color): void
    {
        match ($style) {
            'black' => $this->SetFont('ArchivoBlack', '', $size),
            'semi'  => $this->SetFont('ArchivoSemi', '', $size),
            'mono'  => $this->SetFont('Mono', '', $size),
            default => $this->SetFont('Archivo', $style, $size),
        };
        $this->SetTextColor(...$color);
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

    /** Étoile à cinq branches (la police n'a pas le caractère ★). */
    public function star(float $cx, float $cy, float $r, array $color): void
    {
        $this->SetFillColor(...$color);
        $pts = [];
        for ($i = 0; $i < 10; $i++) {
            $a = -M_PI / 2 + $i * M_PI / 5;
            $rr = $i % 2 ? $r * 0.45 : $r;
            $pts[] = sprintf('%.2F %.2F', ($cx + $rr * cos($a)) * $this->k, ($this->h - ($cy + $rr * sin($a))) * $this->k);
        }
        $this->_out($pts[0] . ' m ' . implode(' l ', array_slice($pts, 1)) . ' l h f');
    }

    /** Étiquette orange légèrement inclinée, en police machine à écrire. */
    public function tag(string $texte, array $fond = C_ORANGE, array $encre = C_ENCRE, float $angle = 2): float
    {
        $texte = mb_strtoupper($texte);
        $this->font('mono', 7.2, $encre);
        $w = $this->GetStringWidth($texte) + 7;
        $x = $this->GetX(); $y = $this->GetY();
        // rotation autour du coin gauche de l'étiquette
        $a = deg2rad($angle); $cx = $x * $this->k; $cy = ($this->h - $y - 3.2) * $this->k;
        $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm', cos($a), sin($a), -sin($a), cos($a), $cx - cos($a) * $cx + sin($a) * $cy, $cy - sin($a) * $cx - cos($a) * $cy));
        $this->SetFillColor(...$fond);
        $this->roundedRect($x, $y, $w, 6.4, 1.4);
        $this->SetXY($x, $y + 1.2);
        $this->Cell($w, 4, $texte, 0, 0, 'C');
        $this->_out('Q');
        return $w;
    }

    /** Découpe un texte en lignes qui tiennent dans la largeur donnée (police courante). */
    public function wrap(string $texte, float $largeur): array
    {
        $lignes = [];
        $courante = '';
        foreach (preg_split('/\s+/u', trim($texte)) as $mot) {
            $essai = $courante === '' ? $mot : "$courante $mot";
            if ($courante !== '' && $this->GetStringWidth($essai) > $largeur) {
                $lignes[] = $courante;
                $courante = $mot;
            } else {
                $courante = $essai;
            }
        }
        if ($courante !== '') $lignes[] = $courante;
        return $lignes;
    }

    /** Étiquette, grand titre (dernière ligne surlignée citron) et ligne d'informations. */
    public function titleBlock(string $label, string $titre, string $meta, ?string $pastille = null): void
    {
        $y = $this->GetY();
        $this->SetX(19);
        $w = $this->tag($label);
        if ($pastille) {
            $this->SetXY(19 + $w + 4, $y - 0.6);
            $this->tag($pastille, C_ENCRE, C_CITRON, 0);
        }
        $this->SetY($y + 12);

        $taille = 25;
        $this->font('black', $taille, C_ENCRE);
        $lignes = $this->wrap($titre, 174);
        if (count($lignes) > 3) {
            $taille = 19;
            $this->font('black', $taille, C_ENCRE);
            $lignes = $this->wrap($titre, 174);
        }
        $hauteur = $taille * 0.42;
        foreach ($lignes as $i => $l) {
            if ($i === count($lignes) - 1) {
                $this->SetFillColor(...C_CITRON);
                $this->Rect(17, $this->GetY() + 0.6, $this->GetStringWidth($l) + 3, $hauteur - 0.4, 'F');
            }
            $this->SetX(18.5);
            $this->Cell(0, $hauteur, $l, 0, 1);
        }
        $this->Ln(3);
        $this->font('', 10, [59, 59, 64]);
        $this->MultiCell(0, 5, $meta);
        $this->Ln(7);
    }

    public function sectionTitle(string $titre): void
    {
        $this->ensureSpace(20);
        $this->font('mono', 8, C_VERT);
        $this->Cell(0, 5, mb_strtoupper($titre), 0, 1);
        $this->SetDrawColor(...C_ENCRE);
        $this->SetLineWidth(0.35);
        $this->Line(18, $this->GetY() + 0.5, 192, $this->GetY() + 0.5);
        $this->Ln(4.5);
    }

    /** Rangée de chiffres clés : cartes à contour noir, le prix en carte noire. */
    public function statBoxes(array $stats): void
    {
        if (!$stats) return;
        $stats = array_slice($stats, 0, 5);
        $gap = 3;
        $w = (174 - $gap * (count($stats) - 1)) / count($stats);
        $h = 21;
        $this->ensureSpace($h + 6);
        $y = $this->GetY();
        $couleursLabel = [C_ORANGE, C_VERT, C_ORANGE, C_VERT];
        foreach ($stats as $i => $s) {
            $x = 18 + $i * ($w + $gap);
            $this->SetDrawColor(...C_ENCRE);
            $this->SetLineWidth(0.35);
            if (isset($s['dpe'])) {
                $this->SetFillColor(...(DPE_COULEURS[$s['dpe']] ?? C_VERT));
                $this->roundedRect($x, $y, $w, $h, 2.5, 'FD');
                $clair = in_array($s['dpe'], ['C', 'D', 'E'], true);
                $this->SetXY($x, $y + 3.2);
                $this->font('mono', 6.4, $clair ? C_ENCRE : [255, 255, 255]);
                $this->Cell($w, 3.5, mb_strtoupper($s['label']), 0, 2, 'C');
                $this->font('black', 17, $clair ? C_ENCRE : [255, 255, 255]);
                $this->Cell($w, 9, $s['dpe'], 0, 0, 'C');
                continue;
            }
            $sombre = !empty($s['sombre']);
            $this->SetFillColor(...($sombre ? C_ENCRE : C_PAPIER));
            $this->roundedRect($x, $y, $w, $h, 2.5, 'FD');
            $this->SetXY($x + 3.5, $y + 3.4);
            $this->font('mono', 6.2, $sombre ? C_CITRON : $couleursLabel[$i % 4]);
            $this->Cell($w - 7, 3.5, mb_strtoupper($s['label']), 0, 2);
            $taille = 14.5;
            $this->font('black', $taille, $sombre ? C_CREME : C_ENCRE);
            while ($this->GetStringWidth($s['valeur']) > $w - 7 && $taille > 8) $this->font('black', --$taille, $sombre ? C_CREME : C_ENCRE);
            $this->SetX($x + 3.5);
            $this->Cell($w - 7, 9, $s['valeur'], 0, 2);
            if (!empty($s['detail'])) {
                $this->SetX($x + 3.5);
                $this->font('mono', 5.8, $sombre ? [200, 198, 190] : C_GRIS);
                $this->Cell($w - 7, 3, mb_strtoupper($s['detail']), 0, 0);
            }
        }
        $this->SetY($y + $h + 9);
    }

    /** Tableau libellé / valeur sur deux colonnes ; les textes longs prennent toute la largeur. */
    public function kvGrid(array $rows): void
    {
        $courts = array_values(array_filter($rows, fn ($r) => !$r['long']));
        $longs = array_values(array_filter($rows, fn ($r) => $r['long']));
        $colW = 84;
        for ($i = 0; $i < count($courts); $i += 2) {
            $this->ensureSpace(12);
            $y = $this->GetY();
            $hauteur = 0;
            foreach ([0, 1] as $c) {
                if (!isset($courts[$i + $c])) continue;
                $r = $courts[$i + $c];
                $x = 18 + $c * ($colW + 6);
                $this->SetXY($x, $y);
                $this->font('mono', 6.4, C_GRIS);
                $this->Cell($colW, 4, mb_strtoupper($r['label']), 0, 2);
                $this->font('semi', 10.5, C_ENCRE);
                $this->MultiCell($colW, 5, $r['valeur']);
                $hauteur = max($hauteur, $this->GetY() - $y);
            }
            $this->SetY($y + $hauteur + 1.8);
            $this->SetDrawColor(...C_LIGNE);
            $this->SetLineWidth(0.2);
            $this->Line(18, $this->GetY(), 192, $this->GetY());
            $this->Ln(2.8);
        }
        foreach ($longs as $r) {
            $this->ensureSpace(14);
            $this->font('mono', 6.4, C_GRIS);
            $this->Cell(0, 4, mb_strtoupper($r['label']), 0, 2);
            $this->font('', 10.5, C_ENCRE);
            $this->MultiCell(0, 5.2, $r['valeur']);
            $this->Ln(1.8);
            $this->SetDrawColor(...C_LIGNE);
            $this->Line(18, $this->GetY(), 192, $this->GetY());
            $this->Ln(2.8);
        }
        $this->Ln(5);
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
                $this->Ln(2.5);
                $this->sectionTitle(rtrim($ligne, ' :'));
                continue;
            }
            if (preg_match('/^\s*[-•]\s+(.*)$/u', $ligne, $m)) {
                $this->ensureSpace(7);
                $y = $this->GetY();
                $this->SetFillColor(...C_CITRON);
                $this->SetDrawColor(...C_ENCRE);
                $this->SetLineWidth(0.25);
                $this->roundedRect(19.5, $y + 1.6, 2.4, 2.4, 0.5, 'FD');
                $this->SetX(25);
                $this->font('', $taille, C_ENCRE);
                $this->MultiCell(0, $taille * 0.52, $m[1]);
                $this->Ln(1.2);
                continue;
            }
            $this->ensureSpace(7);
            $this->font('', $taille, C_ENCRE);
            $this->MultiCell(0, $taille * 0.56, $ligne);
            $this->Ln(1.2);
        }
    }

    /** Carte noire de contact de l'agent (comme la carte « La priorité » de la charte). */
    public function contactBox(array $agent): void
    {
        $lignes = array_filter([$agent['telephone'] ?? '', $agent['email'] ?? '']);
        $this->ensureSpace(28);
        $y = $this->GetY() + 3;
        $this->SetFillColor(...C_ENCRE);
        $this->roundedRect(18, $y, 174, 22, 3);
        $this->SetXY(25, $y + 4.5);
        $this->font('mono', 6.8, C_CITRON);
        $this->Cell(0, 4, 'VOTRE CONTACT', 0, 2);
        $this->font('black', 13, C_CREME);
        $this->Cell(0, 7, $agent['nom'] . ' · ' . $this->agence, 0, 2);
        $this->font('', 9.5, C_CREME);
        $this->Cell(0, 4.5, implode('   ·   ', $lignes), 0, 2);
        $this->SetY($y + 28);
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
        $stats[] = ['valeur' => fmt_nombre($v('prix_souhaite')) . "\u{00A0}€", 'label' => 'Prix', 'sombre' => true,
            'detail' => is_numeric($v('surface_habitable')) && (float) $v('surface_habitable') > 0 && is_numeric($v('prix_souhaite'))
                ? fmt_nombre((string) round((float) $v('prix_souhaite') / (float) $v('surface_habitable'))) . "\u{00A0}€/m²" : ''];
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
        if (!$avecVendeur && !empty($s['interne'])) continue; // vendeurs, juridique, mandat : dossier interne seulement
        $rows = [];
        foreach ($s['champs'] as $c) {
            $val = fiche_valeur($champs, $c['cle']);
            if ($val === '') continue;
            if ($c['type'] === 'number' && is_numeric($val)) $val = fmt_nombre($val) . (isset($c['unite']) ? "\u{00A0}{$c['unite']}" : '');
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
    $vendeur = trim(fiche_valeur($champs, 'civilite_vendeur') . ' ' . fiche_valeur($champs, 'prenom_vendeur') . ' ' . fiche_valeur($champs, 'nom_vendeur'));
    $pdf->SetX(118);
    $pdf->font('semi', 10, C_ENCRE);
    if ($vendeur) $pdf->MultiCell(74, 5, $vendeur);
    $pdf->SetX(118);
    $pdf->font('', 9.5, C_GRIS);
    $pdf->MultiCell(74, 4.8, trim(fiche_valeur($champs, 'adresse') . "\n" . fiche_valeur($champs, 'ville')));
    $pdf->Ln(6);
    $pdf->SetX(118);
    $pdf->Cell(74, 5, 'Le ' . fmt_date_fr(null));
    $pdf->Ln(12);
    $pdf->font('mono', 7.5, C_VERT);
    $pdf->Cell(0, 4, 'OBJET', 0, 1);
    $pdf->font('black', 12, C_ENCRE);
    $pdf->MultiCell(0, 5.8, 'Compte rendu de la visite du ' . fmt_date_fr($visit['cree_le']) . ' · ' . titre_bien($visit));
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
