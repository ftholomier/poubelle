<?php
// Mandat de vente en PDF, rempli automatiquement avec le dossier.
//
// Le texte reprend le gabarit de la plateforme Synapse.immo (config/contracts.php du dépôt suisse-immo) :
// mentions obligatoires de la loi Hoguet et du décret 72-678, clause de dénonciation pour l'exclusif,
// clause de rétractation et bordereau détachable pour un mandat signé hors de l'agence.
// C'est un point de départ conforme, pas un conseil juridique : à faire relire, puis remplacer par le modèle du réseau.
//
// Registre des mandats : tant que le mandat n'est pas inscrit, le PDF est un PROJET (filigrane). L'inscription attribue
// un numéro chronologique définitif (AAAA-0001…), comme l'exige le décret 72-678 (art. 72) : pas de trou, pas de réemploi.

const MANDAT_GABARIT = <<<'TEXTE'
# MANDAT DE VENTE {{mandat.type_majuscules}}

Inscrit au registre des mandats sous le numéro {{mandat.registre}}, le {{mandat.registre_le}}.

## Entre les soussignés

**Le mandant** : {{mandant.identite}}, demeurant {{mandant.adresse}}.

**Le mandataire** : {{agence.denomination}}, dont le siège est situé {{agence.adresse}}, titulaire de la carte professionnelle n° {{agence.carte_numero}} délivrée par {{agence.carte_prefecture}}, garantie par {{agence.garant}}, assurée en responsabilité civile professionnelle auprès de {{agence.rcp}}.

## Article 1 · Objet du mandat

Le mandant confie au mandataire, qui l'accepte, le mandat de vendre le bien ci-après désigné : {{bien.designation}}, situé {{bien.adresse}}.

{{bien.origine}}

{{bien.diagnostics}}

## Article 2 · Prix

Le prix de vente demandé est fixé à {{mandat.prix}}, honoraires inclus.

## Article 3 · Honoraires

Les honoraires du mandataire s'élèvent à {{mandat.honoraires}} TTC, à la charge {{mandat.honoraires_charge}}.

Ils ne sont dus qu'à compter du jour où l'opération a été effectivement conclue et constatée dans un acte écrit contenant l'engagement des parties, conformément à l'article 6 de la loi n°70-9 du 2 janvier 1970.

## Article 4 · Durée

Le présent mandat est conclu pour une durée de {{mandat.duree}}.

{{mandat.denonciation}}

## Article 5 · Obligations du mandataire

Le mandataire s'engage à mettre en œuvre les moyens de commercialisation nécessaires, à rendre compte au mandant de ses diligences, et à lui communiquer toute offre reçue.

## Article 6 · Obligations du mandant

Le mandant garantit avoir la pleine capacité de disposer du bien et s'engage à fournir l'ensemble des diagnostics et documents obligatoires.

{{mandat.conditions}}

## Article 7 · Données personnelles

Les données recueillies sont traitées par {{agence.denomination}} pour l'exécution du présent mandat. Le mandant dispose d'un droit d'accès, de rectification et d'effacement, qu'il peut exercer auprès de {{agence.email}}.

{{contrat.retractation}}

Fait à {{contrat.lieu}}, le {{contrat.signe_le}}, en deux exemplaires originaux, dont un remis au mandant qui le reconnaît.
TEXTE;

const MANDAT_DENONCIATION = 'Le présent mandat étant conclu à titre {{mandat.type_minuscules}}, le mandant peut y renoncer à tout moment passé un délai de trois mois à compter de sa signature, par lettre recommandée avec demande d\'avis de réception, la dénonciation prenant effet quinze jours après sa réception.';

const MANDAT_RETRACTATION = 'Le présent contrat étant conclu {{contrat.hors_etablissement}}, le mandant dispose d\'un délai de quatorze jours pour exercer son droit de rétractation, sans avoir à motiver sa décision. Le délai court à compter du lendemain de la signature et expire le {{contrat.retractation_fin}}. Le formulaire de rétractation figure en dernière page et peut en être détaché.';

const MANDAT_BORDEREAU = "À l'attention de {{agence.denomination}}, {{agence.adresse}} ({{agence.email}})\n\nJe vous notifie par la présente ma rétractation du contrat portant sur la prestation de service ci-dessous :\n\nMandat n° {{mandat.registre}} : {{bien.designation}}, {{bien.adresse}}\n\nCommandé le : {{contrat.signe_le}}\nNom du consommateur : {{mandant.identite_courte}}\nAdresse du consommateur : {{mandant.adresse}}\n\nDate : ……………………………………\n\nSignature du consommateur (uniquement en cas de notification sur papier) :";

// ---------- Nombres et dates ----------

function nombre_en_lettres(int $n): string
{
    if ($n === 0) return 'zéro';
    $unites = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf', 'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize'];
    $dizaines = [2 => 'vingt', 3 => 'trente', 4 => 'quarante', 5 => 'cinquante', 6 => 'soixante'];
    $moinsDeCent = function (int $n) use ($unites, $dizaines): string {
        if ($n <= 16) return $unites[$n];
        if ($n < 20) return 'dix-' . $unites[$n - 10];
        if ($n < 70) {
            $d = intdiv($n, 10); $u = $n % 10;
            return $dizaines[$d] . ($u === 1 ? '-et-un' : ($u ? '-' . $unites[$u] : ''));
        }
        if ($n < 80) return 'soixante' . ($n === 71 ? '-et-onze' : '-' . ($n - 60 <= 16 ? $unites[$n - 60] : 'dix-' . $unites[$n - 70]));
        $r = $n - 80;
        if ($r === 0) return 'quatre-vingts';
        return 'quatre-vingt-' . ($r <= 16 ? $unites[$r] : 'dix-' . $unites[$r - 10]);
    };
    $moinsDeMille = function (int $n) use ($moinsDeCent, $unites): string {
        $c = intdiv($n, 100); $r = $n % 100;
        $txt = $c === 0 ? '' : ($c === 1 ? 'cent' : $unites[$c] . '-cent' . ($r === 0 ? 's' : ''));
        return trim($txt . ($r ? ($txt ? '-' : '') . $moinsDeCent($r) : ''), '-');
    };
    $parties = [];
    foreach ([[1000000000, 'milliard'], [1000000, 'million'], [1000, 'mille']] as [$val, $nom]) {
        $q = intdiv($n, $val);
        if ($q) {
            $n %= $val;
            if ($nom === 'mille') $parties[] = $q === 1 ? 'mille' : str_replace('cents', 'cent', $moinsDeMille($q)) . '-mille';
            else $parties[] = $moinsDeMille($q) . '-' . $nom . ($q > 1 ? 's' : '');
        }
    }
    if ($n) $parties[] = $moinsDeMille($n);
    return implode('-', $parties);
}

function euros_en_lettres(float $v): string
{
    return fmt_nombre((string) round($v)) . "\u{00A0}€ (" . nombre_en_lettres((int) round($v)) . ' euros)';
}

/** « 12/03/1958 » → « 12 mars 1958 » (laisse le texte tel quel s'il n'est pas au format JJ/MM/AAAA). */
function date_longue(string $jjmmaaaa): string
{
    $t = date_fr_depuis($jjmmaaaa);
    return $t ? fmt_date_fr(date('c', $t)) : $jjmmaaaa;
}

function date_fr_depuis(string $jjmmaaaa): ?int
{
    return preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', trim($jjmmaaaa), $m) ? mktime(12, 0, 0, (int) $m[2], (int) $m[1], (int) $m[3]) : null;
}

// ---------- Jetons ----------

/**
 * Valeurs des jetons du gabarit. Une valeur manquante est remplacée par « [à compléter : …] »,
 * affichée en surligné dans le PDF : le projet reste lisible et l'agent voit tout de suite ce qui manque.
 */
function mandat_jetons(array $visit, array $agent): array
{
    global $CONFIG;
    $c = (array) $visit['fiche']['champs'];
    $v = fn ($k) => trim((string) ($c[$k]['valeur'] ?? ''));
    $manque = fn ($libelle) => "[à compléter : $libelle]";
    $ou = fn ($val, $libelle) => $val !== '' ? $val : $manque($libelle);
    $cfg = fn ($k) => trim((string) ($CONFIG[$k] ?? ''));

    // Mandant(s)
    $personne = function (string $suffixe) use ($v, $ou): string {
        $nom = trim($v("civilite_vendeur$suffixe") . ' ' . $v("prenom_vendeur$suffixe") . ' ' . mb_strtoupper($v("nom_vendeur$suffixe")));
        $nee = $v("civilite_vendeur$suffixe") === 'Monsieur' ? 'né' : 'née';
        return $nom . ", $nee le " . $ou(date_longue($v("naissance_date_vendeur$suffixe")), 'date de naissance') . ' à ' . $ou($v("naissance_lieu_vendeur$suffixe"), 'lieu de naissance');
    };
    $identite = $v('nom_vendeur') !== '' ? $personne('') : $manque('identité du vendeur');
    if ($v('nom_vendeur2') !== '') $identite .= ', et ' . $personne('2');
    $situation = $v('situation_vendeur');
    if ($situation !== '') {
        $deux = $v('nom_vendeur2') !== '';
        $femme = $v('civilite_vendeur') === 'Madame';
        $accord = ['Marié(e)' => $deux ? 'mariés' : ($femme ? 'mariée' : 'marié'), 'Pacsé(e)' => $deux ? 'pacsés' : ($femme ? 'pacsée' : 'pacsé'),
            'Divorcé(e)' => $femme ? 'divorcée' : 'divorcé', 'Veuf / veuve' => $femme ? 'veuve' : 'veuf', 'Célibataire' => 'célibataire', 'Concubinage' => 'vivant en concubinage'];
        $identite .= ', ' . ($accord[$situation] ?? mb_strtolower($situation));
        if ($situation === 'Marié(e)') $identite .= ' sous le régime de la ' . $ou(mb_strtolower($v('regime_vendeur')), 'régime matrimonial');
    } else {
        $identite .= ', ' . $manque('situation familiale');
    }
    if ($v('autres_proprietaires') !== '') $identite .= ' ; autres propriétaires : ' . $v('autres_proprietaires');
    $identiteCourte = trim($v('prenom_vendeur') . ' ' . mb_strtoupper($v('nom_vendeur')) . ($v('nom_vendeur2') ? ' et ' . $v('prenom_vendeur2') . ' ' . mb_strtoupper($v('nom_vendeur2')) : ''));

    $adresseBien = trim($v('adresse') . ', ' . $v('ville'), ', ');

    // Bien
    $designation = array_filter([
        $v('type_bien') ? ($v('type_bien') === 'Maison' ? 'une maison' : ($v('type_bien') === 'Appartement' ? 'un appartement' : mb_strtolower($v('type_bien')))) : $manque('type de bien'),
        $v('nb_pieces') ? $v('nb_pieces') . ' pièces' : '',
        $v('surface_habitable') ? 'd\'une surface habitable de ' . $v('surface_habitable') . ' m²' : '',
        $v('surface_terrain') ? 'sur un terrain de ' . fmt_nombre($v('surface_terrain')) . ' m²' : '',
    ]);
    $designation = implode(', ', $designation);
    $designation .= ', cadastré ' . $ou($v('cadastre'), 'références cadastrales');
    if ($v('copropriete') === 'oui') {
        $designation .= ', soumis au statut de la copropriété (lots ' . $ou($v('lots_copropriete'), 'numéros de lots') . ', superficie privative loi Carrez de ' . $ou($v('surface_carrez'), 'surface Carrez') . ' m²)';
    }
    $designation .= ', ' . mb_strtolower($ou($v('occupation'), 'occupation')) . ' au jour de la signature';

    // Prix et honoraires
    $prix = (float) str_replace([' ', ','], ['', '.'], $v('mandat_prix'));
    $net = (float) str_replace([' ', ','], ['', '.'], $v('prix_souhaite'));
    $charge = $v('mandat_honoraires_charge');
    $hono = $v('mandat_honoraires');
    $montantHono = 0.0;
    if ($prix && $net && $prix > $net && $charge === "L'acquéreur") $montantHono = $prix - $net;
    elseif (preg_match('/([\d.,]+)\s*%/', $hono, $m) && $prix) {
        $pct = (float) str_replace(',', '.', $m[1]);
        $montantHono = $charge === "L'acquéreur" ? $prix - $prix / (1 + $pct / 100) : $prix * $pct / 100;
    } elseif (is_numeric(str_replace([' ', '€'], '', $hono))) {
        $montantHono = (float) str_replace([' ', '€'], '', $hono);
    }
    $honoTxt = $hono === '' ? $manque('honoraires') : ($montantHono ? trim((str_contains($hono, '%') ? "$hono du prix net vendeur, soit " : '') . euros_en_lettres($montantHono)) : $hono);
    $prixTxt = $prix ? euros_en_lettres($prix) . ($montantHono && $charge === "L'acquéreur" ? ', soit un prix net vendeur de ' . euros_en_lettres($prix - $montantHono) : '') : $manque('prix de présentation');

    // Durée et dates
    $signe = date_fr_depuis($v('mandat_date'));
    $mois = (int) $v('mandat_duree');
    $duree = $mois ? "$mois mois" . ($signe ? ' à compter du ' . fmt_date_fr(date('c', $signe)) . ", soit jusqu'au " . fmt_date_fr(date('c', strtotime("+$mois months -1 day", $signe))) . ' inclus' : '') : $manque('durée');
    $type = $v('mandat_type');
    $lieu = $v('mandat_lieu');
    $horsEtablissement = in_array($lieu, ['Au domicile du vendeur', 'À distance'], true);

    $registre = $visit['mandat']['numero'] ?? null;
    return [
        'mandat.type_majuscules'   => $type ? mb_strtoupper($type) : '',
        'mandat.type_minuscules'   => mb_strtolower($type),
        'mandat.registre'          => $registre ?: '[attribué lors de l\'inscription au registre]',
        'mandat.registre_le'       => $registre ? fmt_date_fr($visit['mandat']['inscrit_le']) : '[date d\'inscription]',
        'mandant.identite'         => $identite,
        'mandant.identite_courte'  => $identiteCourte ?: $manque('nom du vendeur'),
        'mandant.adresse'          => $v('adresse_vendeur') ?: ($adresseBien ?: $manque('adresse du vendeur')),
        'agence.denomination'      => $cfg('raison_sociale') ?: $cfg('agence'),
        'agence.adresse'           => $ou($cfg('siege'), 'adresse du siège (Paramètres)'),
        'agence.carte_numero'      => $ou($cfg('carte_numero'), 'n° de carte professionnelle (Paramètres)'),
        'agence.carte_prefecture'  => $ou($cfg('carte_delivree_par'), 'CCI de délivrance (Paramètres)'),
        'agence.garant'            => $ou($cfg('garant'), 'garant financier (Paramètres)'),
        'agence.rcp'               => $ou($cfg('rcp'), 'assureur RCP (Paramètres)'),
        'agence.email'             => $cfg('email_expediteur') ?: ($agent['email'] ?? '') ?: $manque('e-mail de l\'agence'),
        'bien.designation'         => $designation,
        'bien.adresse'             => $adresseBien ?: $manque('adresse du bien'),
        'bien.origine'             => $v('origine_propriete') ? 'Origine de propriété : ' . $v('origine_propriete') . '.' : 'Origine de propriété : ' . $manque('origine de propriété') . '.',
        'bien.diagnostics'         => 'Diagnostics : ' . ($v('diagnostics') ?: ($v('dpe') ? 'diagnostic de performance énergétique classe ' . $v('dpe') . ($v('ges') ? ', GES classe ' . $v('ges') : '') . ' ; autres diagnostics obligatoires à fournir par le mandant' : 'à fournir par le mandant')) . '.' . ($v('servitudes') ? ' Servitudes, litiges ou procédures déclarés : ' . $v('servitudes') . '.' : ''),
        'mandat.prix'              => $prixTxt,
        'mandat.honoraires'        => $honoTxt,
        'mandat.honoraires_charge' => $charge === "L'acquéreur" ? "de l'acquéreur" : ($charge === 'Le vendeur' ? 'du vendeur' : $manque('honoraires à la charge de')),
        'mandat.duree'             => $duree,
        'mandat.denonciation'      => in_array($type, ['Exclusif', 'Semi-exclusif'], true) ? '!!' . str_replace('{{mandat.type_minuscules}}', mb_strtolower($type), MANDAT_DENONCIATION) : '',
        'mandat.conditions'        => $v('mandat_notes') ? 'Conditions particulières : ' . $v('mandat_notes') : '',
        'contrat.retractation'     => $horsEtablissement ? str_replace(
            ['{{contrat.hors_etablissement}}', '{{contrat.retractation_fin}}'],
            [$lieu === 'À distance' ? 'à distance' : 'hors établissement, au domicile du mandant', $signe ? fmt_date_fr(date('c', strtotime('+14 days', $signe))) : '[date de signature + 14 jours]'],
            MANDAT_RETRACTATION
        ) : '',
        'contrat.hors_etablissement' => $horsEtablissement,
        'contrat.lieu'             => $lieu === 'En agence' ? ($ou(trim(preg_replace('/^.*\d{5}\s*/', '', $cfg('siege'))), 'ville de l\'agence')) : (trim(preg_replace('/\b\d{5}\b/', '', $v('ville'))) ?: $manque('lieu de signature')),
        'contrat.signe_le'         => $signe ? fmt_date_fr(date('c', $signe)) : $manque('date de signature'),
        '_type'                    => $type,
        '_lieu'                    => $lieu,
    ];
}

/** Remplace les jetons ; renvoie le texte et la liste des informations encore à compléter. */
function mandat_remplir(string $texte, array $jetons): string
{
    return preg_replace_callback('/\{\{([a-z_.]+)\}\}/', fn ($m) => (string) ($jetons[$m[1]] ?? '[à compléter : ' . $m[1] . ']'), $texte);
}

function mandat_a_completer(array $visit, array $agent): array
{
    $j = mandat_jetons($visit, $agent);
    $texte = mandat_remplir(MANDAT_GABARIT, $j) . ($j['contrat.hors_etablissement'] ? mandat_remplir(MANDAT_BORDEREAU, $j) : '');
    preg_match_all('/\[à compléter : ([^\]]+)\]/u', $texte, $m);
    $manques = array_values(array_unique($m[1]));
    if ($j['_type'] === '') array_unshift($manques, 'type de mandat');
    if ($j['_lieu'] === '') $manques[] = 'lieu de signature';
    return array_values(array_unique($manques));
}

// ---------- PDF ----------

class MandatPdf extends VisitePdf
{
    public bool $projet = true;
    public ?string $labelSuivant = null; // libellé de la page suivante (le pied de la page en cours garde l'ancien)

    public function Header(): void
    {
        if ($this->labelSuivant !== null) {
            $this->docLabel = $this->labelSuivant;
            $this->labelSuivant = null;
        }
        parent::Header();
        if ($this->projet) {
            // Filigrane « PROJET » tant que le mandat n'est pas inscrit au registre
            $this->font('black', 90, [232, 226, 212]);
            $a = deg2rad(35); $x = 38; $y = 215;
            $cx = $x * $this->k; $cy = ($this->h - $y) * $this->k;
            $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm', cos($a), sin($a), -sin($a), cos($a), $cx - cos($a) * $cx + sin($a) * $cy, $cy - sin($a) * $cx - cos($a) * $cy));
            $this->Text($x, $y, 'PROJET');
            $this->_out('Q');
            $this->SetY($this->PageNo() === 1 ? $this->GetY() : 24);
        }
    }

    /** Paragraphe avec **gras** en ligne et « [à compléter : …] » surligné. */
    public function paragraphe(string $texte, float $taille = 10): void
    {
        $h = $taille * 0.5;
        $this->ensureSpace($h * 2);
        foreach (preg_split('/(\*\*[^*]+\*\*|\[à compléter : [^\]]+\])/u', $texte, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $morceau) {
            if (str_starts_with($morceau, '**')) {
                $this->font('B', $taille, C_ENCRE);
                $this->Write($h, trim($morceau, '*'));
            } elseif (str_starts_with($morceau, '[à compléter')) {
                $this->font('B', $taille, C_ROUGE);
                $this->Write($h, $morceau);
            } else {
                $this->font('', $taille, C_ENCRE);
                $this->Write($h, $morceau);
            }
        }
        $this->Ln($h + 2.2);
    }

    /** Clause « en caractères très apparents » : gras, encadrée (décret 72-678, art. 78). */
    public function clauseApparente(string $texte): void
    {
        $this->font('B', 10.5, C_ENCRE);
        $lignes = $this->wrap($texte, 160);
        $hauteur = count($lignes) * 5.4 + 8;
        $this->ensureSpace($hauteur + 4);
        $y = $this->GetY();
        $this->SetDrawColor(...C_ENCRE);
        $this->SetLineWidth(0.6);
        $this->SetFillColor(...C_CITRON);
        $this->roundedRect(18, $y, 174, $hauteur, 2, 'FD');
        $this->SetXY(25, $y + 4);
        foreach ($lignes as $l) {
            $this->SetX(25);
            $this->Cell(160, 5.4, $l, 0, 1);
        }
        $this->SetY($y + $hauteur + 5);
    }

    public function signatures(): void
    {
        $this->ensureSpace(48);
        $this->Ln(4);
        $y = $this->GetY();
        foreach ([[18, 'LE MANDANT', '« Lu et approuvé, bon pour mandat »'], [108, 'LE MANDATAIRE', 'Signature et cachet']] as [$x, $titre, $aide]) {
            $this->SetDrawColor(...C_ENCRE);
            $this->SetLineWidth(0.35);
            $this->SetFillColor(...C_PAPIER);
            $this->roundedRect($x, $y, 84, 38, 2.5, 'FD');
            $this->SetXY($x + 5, $y + 4);
            $this->font('mono', 7, C_VERT);
            $this->Cell(74, 4, $titre, 0, 2);
            $this->font('I', 8.5, C_GRIS);
            $this->Cell(74, 4.5, $aide, 0, 2);
        }
        $this->SetY($y + 44);
    }
}

/** Construit le PDF du mandat : [contenu binaire, nom de fichier]. */
function build_mandat(array $visit, array $agent): array
{
    global $CONFIG;
    $j = mandat_jetons($visit, $agent);
    $pdf = new MandatPdf();
    $pdf->agence = (string) $CONFIG['agence'];
    $pdf->coordonnees = (string) ($CONFIG['agence_coordonnees'] ?? '');
    $pdf->logo = logo_path();
    $pdf->projet = empty($visit['mandat']['numero']);
    $pdf->docLabel = 'Mandat ' . ($visit['mandat']['numero'] ?? 'projet');
    $pdf->SetTitle('Mandat de vente · ' . titre_bien($visit), true);
    $pdf->AddPage();

    $texte = mandat_remplir(MANDAT_GABARIT, $j);
    foreach (preg_split('/\n{2,}/', trim($texte)) as $bloc) {
        $bloc = trim(preg_replace('/\s*\n\s*/', ' ', $bloc));
        if ($bloc === '') continue;
        if (str_starts_with($bloc, '# ')) {
            $pdf->SetX(19);
            $pdf->tag($pdf->projet ? 'Projet de mandat' : 'Mandat n° ' . $visit['mandat']['numero']);
            $pdf->SetY($pdf->GetY() + 11);
            $pdf->font('black', 21, C_ENCRE);
            $pdf->MultiCell(0, 9, rtrim(substr($bloc, 2)) ?: 'MANDAT DE VENTE');
            $pdf->Ln(3);
        } elseif (str_starts_with($bloc, '## ')) {
            $pdf->Ln(1.5);
            $pdf->sectionTitle(substr($bloc, 3));
        } elseif (str_starts_with($bloc, '!!')) {
            $pdf->clauseApparente(substr($bloc, 2));
        } else {
            $pdf->paragraphe($bloc);
        }
    }
    $pdf->signatures();

    // Bordereau de rétractation détachable, obligatoire hors établissement (art. L221-5 et R221-1 du code de la consommation)
    if ($j['contrat.hors_etablissement']) {
        $pdf->labelSuivant = 'Formulaire de rétractation';
        $pdf->AddPage();
        $pdf->SetDrawColor(...C_ENCRE);
        $pdf->SetLineWidth(0.3);
        for ($x = 18; $x < 192; $x += 4) $pdf->Line($x, $pdf->GetY(), $x + 2, $pdf->GetY()); // pointillés de découpe
        $pdf->Ln(8);
        $pdf->SetX(19);
        $pdf->tag('Formulaire détachable');
        $pdf->SetY($pdf->GetY() + 11);
        $pdf->font('black', 18, C_ENCRE);
        $pdf->Cell(0, 9, 'FORMULAIRE DE RÉTRACTATION', 0, 1);
        $pdf->font('I', 9.5, C_GRIS);
        $pdf->MultiCell(0, 5, 'À compléter et renvoyer uniquement si vous souhaitez vous rétracter du contrat (annexe à l\'article R221-1 du code de la consommation).');
        $pdf->Ln(5);
        foreach (explode("\n", mandat_remplir(MANDAT_BORDEREAU, $j)) as $ligne) {
            if (trim($ligne) === '') { $pdf->Ln(3); continue; }
            $pdf->paragraphe($ligne);
        }
    }

    $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', titre_bien($visit)) ?: 'visite')), '-');
    return [$pdf->Output('S'), ($pdf->projet ? 'Projet-mandat-' : 'Mandat-' . $visit['mandat']['numero'] . '-') . substr($slug ?: 'visite', 0, 50) . '.pdf'];
}

/** Inscription au registre : numéro chronologique définitif, jamais réattribué. */
function inscrire_registre(array $user, string $id): array
{
    $visit = load_visit($user, $id);
    if (!empty($visit['mandat']['numero'])) return $visit;
    $manques = mandat_a_completer($visit, $user);
    if ($manques) fail(400, 'Mandat incomplet : ' . implode(', ', array_slice($manques, 0, 6)) . (count($manques) > 6 ? '…' : '') . '.');

    $c = (array) $visit['fiche']['champs'];
    $numero = null;
    update_json(DATA_DIR . '/registre.json', function (array $r) use ($user, $id, $c, &$numero) {
        $annee = date('Y');
        $r['compteurs'][$annee] = ($r['compteurs'][$annee] ?? 0) + 1;
        $numero = sprintf('%s-%04d', $annee, $r['compteurs'][$annee]);
        $r['entrees'][] = [
            'numero'  => $numero,
            'date'    => date('c'),
            'agent'   => $user['nom'],
            'visite'  => $id,
            'type'    => $c['mandat_type']['valeur'] ?? '',
            'mandant' => trim(($c['prenom_vendeur']['valeur'] ?? '') . ' ' . ($c['nom_vendeur']['valeur'] ?? '')),
            'bien'    => trim(($c['adresse']['valeur'] ?? '') . ' ' . ($c['ville']['valeur'] ?? '')),
        ];
        return $r;
    });
    return update_visit($user, $id, function (array $v) use ($numero) {
        $v['mandat'] = ['numero' => $numero, 'inscrit_le' => date('c')];
        return $v;
    });
}
