<?php
// Définition de la fiche bien : sert à la fois au formulaire (envoyé à l'appli) et au prompt de l'IA.
// Pour ajouter un champ, il suffit de l'ajouter ici.

const SECTIONS = [
    ['titre' => 'Le bien', 'champs' => [
        ['cle' => 'type_bien', 'label' => 'Type de bien', 'type' => 'select', 'options' => ['Appartement', 'Maison', 'Terrain', 'Local commercial', 'Immeuble', 'Autre'], 'requis' => true],
        ['cle' => 'adresse', 'label' => 'Adresse', 'type' => 'text', 'requis' => true],
        ['cle' => 'ville', 'label' => 'Ville', 'type' => 'text', 'requis' => true],
        ['cle' => 'surface_habitable', 'label' => 'Surface habitable', 'type' => 'number', 'unite' => 'm²', 'requis' => true],
        ['cle' => 'surface_terrain', 'label' => 'Surface du terrain', 'type' => 'number', 'unite' => 'm²'],
        ['cle' => 'nb_pieces', 'label' => 'Pièces', 'type' => 'number', 'requis' => true],
        ['cle' => 'nb_chambres', 'label' => 'Chambres', 'type' => 'number'],
        ['cle' => 'nb_salles_de_bain', 'label' => "Salles de bain / d'eau", 'type' => 'number'],
        ['cle' => 'nb_wc', 'label' => 'WC', 'type' => 'number'],
        ['cle' => 'etage', 'label' => 'Étage', 'type' => 'number'],
        ['cle' => 'ascenseur', 'label' => 'Ascenseur', 'type' => 'bool'],
        ['cle' => 'annee_construction', 'label' => 'Année de construction', 'type' => 'number'],
    ]],
    ['titre' => 'Équipements & extérieurs', 'champs' => [
        ['cle' => 'chauffage', 'label' => 'Chauffage', 'type' => 'text'],
        ['cle' => 'exposition', 'label' => 'Exposition', 'type' => 'text'],
        ['cle' => 'exterieur', 'label' => 'Balcon / terrasse / jardin', 'type' => 'text'],
        ['cle' => 'stationnement', 'label' => 'Parking / garage', 'type' => 'text'],
        ['cle' => 'cave', 'label' => 'Cave', 'type' => 'bool'],
    ]],
    ['titre' => 'État & diagnostics', 'champs' => [
        ['cle' => 'etat_general', 'label' => 'État général', 'type' => 'select', 'options' => ['À rénover', 'Travaux à prévoir', 'Bon état', 'Très bon état', 'Refait à neuf']],
        ['cle' => 'travaux_realises', 'label' => 'Travaux réalisés', 'type' => 'textarea'],
        ['cle' => 'travaux_a_prevoir', 'label' => 'Travaux à prévoir', 'type' => 'textarea'],
        ['cle' => 'dpe', 'label' => 'DPE', 'type' => 'select', 'options' => ['A', 'B', 'C', 'D', 'E', 'F', 'G']],
        ['cle' => 'ges', 'label' => 'GES', 'type' => 'select', 'options' => ['A', 'B', 'C', 'D', 'E', 'F', 'G']],
    ]],
    ['titre' => 'Financier', 'champs' => [
        ['cle' => 'prix_souhaite', 'label' => 'Prix souhaité par le vendeur', 'type' => 'number', 'unite' => '€', 'requis' => true],
        ['cle' => 'charges_mensuelles', 'label' => 'Charges de copro / mois', 'type' => 'number', 'unite' => '€'],
        ['cle' => 'taxe_fonciere', 'label' => 'Taxe foncière / an', 'type' => 'number', 'unite' => '€'],
    ]],
    // Les sections « interne » ne figurent pas sur la fiche envoyée aux clients (seulement dans le dossier complet)
    ['titre' => 'Vendeur(s)', 'interne' => true, 'champs' => [
        ['cle' => 'civilite_vendeur', 'label' => 'Vendeur 1 · civilité', 'type' => 'select', 'options' => ['Madame', 'Monsieur'], 'requis' => true],
        ['cle' => 'prenom_vendeur', 'label' => 'Vendeur 1 · prénom(s)', 'type' => 'text', 'requis' => true],
        ['cle' => 'nom_vendeur', 'label' => 'Vendeur 1 · nom', 'type' => 'text', 'requis' => true],
        ['cle' => 'naissance_date_vendeur', 'label' => 'Vendeur 1 · date de naissance', 'type' => 'date', 'requis' => true],
        ['cle' => 'naissance_lieu_vendeur', 'label' => 'Vendeur 1 · lieu de naissance', 'type' => 'text', 'requis' => true],
        ['cle' => 'adresse_vendeur', 'label' => 'Vendeur 1 · adresse (si différente du bien)', 'type' => 'text'],
        ['cle' => 'telephone_vendeur', 'label' => 'Vendeur 1 · téléphone', 'type' => 'text', 'requis' => true],
        ['cle' => 'email_vendeur', 'label' => 'Vendeur 1 · e-mail', 'type' => 'text', 'requis' => true],
        ['cle' => 'situation_vendeur', 'label' => 'Situation familiale', 'type' => 'select', 'options' => ['Célibataire', 'Marié(e)', 'Pacsé(e)', 'Divorcé(e)', 'Veuf / veuve', 'Concubinage'], 'requis' => true],
        ['cle' => 'regime_vendeur', 'label' => 'Régime matrimonial', 'type' => 'select', 'options' => ['Communauté réduite aux acquêts', 'Séparation de biens', 'Participation aux acquêts', 'Communauté universelle'], 'requis_si' => ['situation_vendeur', 'Marié(e)']],
        ['cle' => 'civilite_vendeur2', 'label' => 'Vendeur 2 · civilité', 'type' => 'select', 'options' => ['Madame', 'Monsieur']],
        ['cle' => 'prenom_vendeur2', 'label' => 'Vendeur 2 · prénom(s)', 'type' => 'text'],
        ['cle' => 'nom_vendeur2', 'label' => 'Vendeur 2 · nom', 'type' => 'text'],
        ['cle' => 'naissance_date_vendeur2', 'label' => 'Vendeur 2 · date de naissance', 'type' => 'date'],
        ['cle' => 'naissance_lieu_vendeur2', 'label' => 'Vendeur 2 · lieu de naissance', 'type' => 'text'],
        ['cle' => 'telephone_vendeur2', 'label' => 'Vendeur 2 · téléphone', 'type' => 'text'],
        ['cle' => 'email_vendeur2', 'label' => 'Vendeur 2 · e-mail', 'type' => 'text'],
        ['cle' => 'autres_proprietaires', 'label' => 'Autres propriétaires / indivision', 'type' => 'textarea'],
        ['cle' => 'motif_vente', 'label' => 'Motif de la vente', 'type' => 'text'],
        ['cle' => 'disponibilite', 'label' => 'Disponibilité', 'type' => 'text'],
    ]],
    ['titre' => 'Situation juridique du bien', 'interne' => true, 'champs' => [
        ['cle' => 'origine_propriete', 'label' => 'Origine de propriété', 'type' => 'text', 'requis' => true],
        ['cle' => 'cadastre', 'label' => 'Références cadastrales', 'type' => 'text', 'requis' => true],
        ['cle' => 'copropriete', 'label' => 'En copropriété', 'type' => 'bool', 'requis' => true],
        ['cle' => 'lots_copropriete', 'label' => 'Numéros de lots', 'type' => 'text', 'requis_si' => ['copropriete', 'oui']],
        ['cle' => 'surface_carrez', 'label' => 'Surface loi Carrez', 'type' => 'number', 'unite' => 'm²', 'requis_si' => ['copropriete', 'oui']],
        ['cle' => 'occupation', 'label' => 'Occupation', 'type' => 'select', 'options' => ['Libre', 'Occupé par le propriétaire', 'Loué'], 'requis' => true],
        ['cle' => 'servitudes', 'label' => 'Servitudes, litiges, procédures', 'type' => 'textarea'],
        ['cle' => 'diagnostics', 'label' => 'Diagnostics réalisés / à commander', 'type' => 'textarea'],
    ]],
    ['titre' => 'Mandat', 'interne' => true, 'champs' => [
        ['cle' => 'mandat_type', 'label' => 'Type de mandat', 'type' => 'select', 'options' => ['Simple', 'Semi-exclusif', 'Exclusif'], 'requis' => true],
        ['cle' => 'mandat_lieu', 'label' => 'Lieu de signature', 'type' => 'select', 'options' => ['En agence', 'Au domicile du vendeur', 'À distance'], 'requis' => true],
        ['cle' => 'mandat_prix', 'label' => 'Prix de présentation (honoraires inclus)', 'type' => 'number', 'unite' => '€', 'requis' => true],
        ['cle' => 'mandat_honoraires', 'label' => 'Honoraires TTC', 'type' => 'text', 'requis' => true],
        ['cle' => 'mandat_honoraires_charge', 'label' => 'Honoraires à la charge de', 'type' => 'select', 'options' => ["L'acquéreur", 'Le vendeur'], 'requis' => true],
        ['cle' => 'mandat_duree', 'label' => 'Durée (mois)', 'type' => 'number', 'requis' => true],
        ['cle' => 'mandat_date', 'label' => 'Date de signature prévue', 'type' => 'date', 'requis' => true],
        ['cle' => 'mandat_notes', 'label' => 'Conditions particulières', 'type' => 'textarea'],
    ]],
];

function field_keys(): array
{
    $keys = [];
    foreach (SECTIONS as $s) foreach ($s['champs'] as $c) $keys[] = $c['cle'];
    return $keys;
}

function fields_prompt(): string
{
    $hints = ['number' => 'nombre', 'bool' => 'oui/non', 'text' => 'texte', 'textarea' => 'texte', 'date' => 'date JJ/MM/AAAA'];
    $lines = [];
    foreach (SECTIONS as $s) {
        foreach ($s['champs'] as $c) {
            $hint = $c['type'] === 'select' ? 'une valeur parmi : ' . implode(', ', $c['options']) : $hints[$c['type']];
            $unit = isset($c['unite']) ? ", en {$c['unite']}" : '';
            $lines[] = "- {$c['cle']} ({$c['label']}{$unit}) : {$hint}";
        }
    }
    return implode("\n", $lines);
}

/** Champs obligatoires encore vides (en tenant compte des conditions : régime si marié, lots si copropriété…). */
function champs_manquants(array $champs): array
{
    $val = fn ($k) => trim((string) ($champs[$k]['valeur'] ?? ''));
    $manquants = [];
    foreach (SECTIONS as $s) {
        foreach ($s['champs'] as $c) {
            $requis = !empty($c['requis']) || (isset($c['requis_si']) && mb_strtolower($val($c['requis_si'][0])) === mb_strtolower($c['requis_si'][1]));
            if ($requis && $val($c['cle']) === '') $manquants[] = $c;
        }
    }
    return $manquants;
}

/** Taux de complétude du dossier (champs obligatoires remplis), de 0 à 100. */
function completude(array $champs): int
{
    $total = 0;
    foreach (SECTIONS as $s) foreach ($s['champs'] as $c) if (!empty($c['requis'])) $total++;
    $manque = count(array_filter(champs_manquants($champs), fn ($c) => !empty($c['requis'])));
    return $total ? (int) round(100 * ($total - $manque) / $total) : 100;
}
