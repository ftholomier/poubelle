<?php
// Définition de la fiche bien : sert à la fois au formulaire (envoyé à l'appli) et au prompt de l'IA.
// Pour ajouter un champ, il suffit de l'ajouter ici.

const SECTIONS = [
    ['titre' => 'Le bien', 'champs' => [
        ['cle' => 'type_bien', 'label' => 'Type de bien', 'type' => 'select', 'options' => ['Appartement', 'Maison', 'Terrain', 'Local commercial', 'Immeuble', 'Autre']],
        ['cle' => 'adresse', 'label' => 'Adresse', 'type' => 'text'],
        ['cle' => 'ville', 'label' => 'Ville', 'type' => 'text'],
        ['cle' => 'surface_habitable', 'label' => 'Surface habitable', 'type' => 'number', 'unite' => 'm²'],
        ['cle' => 'surface_terrain', 'label' => 'Surface du terrain', 'type' => 'number', 'unite' => 'm²'],
        ['cle' => 'nb_pieces', 'label' => 'Pièces', 'type' => 'number'],
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
        ['cle' => 'prix_souhaite', 'label' => 'Prix souhaité', 'type' => 'number', 'unite' => '€'],
        ['cle' => 'charges_mensuelles', 'label' => 'Charges de copro / mois', 'type' => 'number', 'unite' => '€'],
        ['cle' => 'taxe_fonciere', 'label' => 'Taxe foncière / an', 'type' => 'number', 'unite' => '€'],
    ]],
    ['titre' => 'Vendeur & projet', 'champs' => [
        ['cle' => 'nom_vendeur', 'label' => 'Nom du vendeur', 'type' => 'text'],
        ['cle' => 'telephone_vendeur', 'label' => 'Téléphone', 'type' => 'text'],
        ['cle' => 'email_vendeur', 'label' => 'E-mail', 'type' => 'text'],
        ['cle' => 'motif_vente', 'label' => 'Motif de la vente', 'type' => 'text'],
        ['cle' => 'disponibilite', 'label' => 'Disponibilité', 'type' => 'text'],
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
    $hints = ['number' => 'nombre', 'bool' => 'oui/non', 'text' => 'texte', 'textarea' => 'texte'];
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
