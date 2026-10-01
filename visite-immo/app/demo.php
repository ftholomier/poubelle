<?php
// Données simulées utilisées quand les clés API ne sont pas configurées (mode démo).

const DEMO_TRANSCRIPT = [
    "Bonjour madame Martin, merci de nous recevoir. Alors c'est une maison de 1978 c'est bien ça ? Oui, on l'a achetée en 2005. Elle fait 125 mètres carrés habitables sur un terrain de 800 mètres carrés. D'accord. Et au niveau des chambres ? Il y a quatre chambres, trois à l'étage et une au rez-de-chaussée. Deux salles de bain, et deux WC.",
    "Le chauffage c'est quoi ? Pompe à chaleur, on l'a fait installer il y a trois ans, et on a refait la toiture en 2019. Le DPE ? On est en C. Par contre la cuisine serait à rafraîchir. Le jardin est exposé sud, il y a une terrasse de 30 mètres carrés et un garage double.",
    "La taxe foncière tourne autour de 1 450 euros par an. On part en Bretagne pour la retraite, donc on aimerait vendre avant l'été. On pensait à 420 000 euros. Vous pouvez me joindre au 06 12 34 56 78.",
];

function demo_transcript_chunk(int $index): string
{
    return DEMO_TRANSCRIPT[$index] ?? '(suite de la visite, mode démo)';
}

function demo_generation(array $agent): array
{
    global $CONFIG;
    $signature = "{$agent['nom']}, {$CONFIG['agence']}";
    return [
        'champs' => [
            ['cle' => 'type_bien', 'valeur' => 'Maison', 'citation' => "c'est une maison de 1978"],
            ['cle' => 'annee_construction', 'valeur' => '1978', 'citation' => "c'est une maison de 1978"],
            ['cle' => 'nom_vendeur', 'valeur' => 'Mme Martin', 'citation' => 'Bonjour madame Martin'],
            ['cle' => 'telephone_vendeur', 'valeur' => '06 12 34 56 78', 'citation' => 'me joindre au 06 12 34 56 78'],
            ['cle' => 'surface_habitable', 'valeur' => '125', 'citation' => 'Elle fait 125 mètres carrés habitables'],
            ['cle' => 'surface_terrain', 'valeur' => '800', 'citation' => 'sur un terrain de 800 mètres carrés'],
            ['cle' => 'nb_chambres', 'valeur' => '4', 'citation' => 'Il y a quatre chambres'],
            ['cle' => 'nb_salles_de_bain', 'valeur' => '2', 'citation' => 'Deux salles de bain'],
            ['cle' => 'nb_wc', 'valeur' => '2', 'citation' => 'et deux WC'],
            ['cle' => 'chauffage', 'valeur' => 'Pompe à chaleur (installée il y a 3 ans)', 'citation' => "Pompe à chaleur, on l'a fait installer il y a trois ans"],
            ['cle' => 'travaux_realises', 'valeur' => 'Pompe à chaleur (~3 ans), toiture refaite en 2019', 'citation' => 'on a refait la toiture en 2019'],
            ['cle' => 'travaux_a_prevoir', 'valeur' => 'Cuisine à rafraîchir', 'citation' => 'la cuisine serait à rafraîchir'],
            ['cle' => 'etat_general', 'valeur' => 'Bon état', 'citation' => 'Par contre la cuisine serait à rafraîchir'],
            ['cle' => 'dpe', 'valeur' => 'C', 'citation' => 'On est en C.'],
            ['cle' => 'exposition', 'valeur' => 'Sud', 'citation' => 'Le jardin est exposé sud'],
            ['cle' => 'exterieur', 'valeur' => 'Jardin de 800 m², terrasse de 30 m²', 'citation' => 'il y a une terrasse de 30 mètres carrés'],
            ['cle' => 'stationnement', 'valeur' => 'Garage double', 'citation' => 'et un garage double'],
            ['cle' => 'taxe_fonciere', 'valeur' => '1450', 'citation' => 'autour de 1 450 euros par an'],
            ['cle' => 'motif_vente', 'valeur' => 'Départ à la retraite en Bretagne', 'citation' => 'On part en Bretagne pour la retraite'],
            ['cle' => 'disponibilite', 'valeur' => "Vente souhaitée avant l'été", 'citation' => "on aimerait vendre avant l'été"],
            ['cle' => 'prix_souhaite', 'valeur' => '420000', 'citation' => 'On pensait à 420 000 euros'],
        ],
        'titre_annonce' => 'Maison 4 chambres, jardin sud et garage double',
        'annonce' => "Coup de cœur assuré pour cette maison familiale de 125 m² habitables, édifiée en 1978 sur un beau terrain de 800 m² exposé plein sud.\n\nElle offre quatre chambres, dont une au rez-de-chaussée idéale pour recevoir ou pour un parent, ainsi que trois chambres à l'étage. Deux salles de bain et deux WC assurent le confort de toute la famille.\n\nLa maison a été entretenue avec soin : toiture refaite en 2019 et pompe à chaleur récente garantissent sérénité et maîtrise des dépenses énergétiques. Seule la cuisine, à rafraîchir, vous permettra d'y apporter votre touche personnelle.\n\nÀ l'extérieur, profitez d'une terrasse de 30 m² ouverte sur le jardin ensoleillé. Un garage double complète ce bien.\n\nDPE : C. Taxe foncière : 1 450 €/an.",
        'rapport_agent' => "SYNTHÈSE\n- Maison 1978, 125 m² sur 800 m², 4 ch., DPE C, bon état général. Bien facile à commercialiser auprès des familles.\n\nPOINTS FORTS\n- Jardin plein sud + terrasse 30 m²\n- Chambre en RDC\n- PAC récente et toiture 2019 : peu de travaux lourds\n- Garage double\n\nPOINTS FAIBLES & RISQUES\n- Cuisine datée à rafraîchir\n- Nombre de pièces total non précisé\n\nTRAVAUX\n- Réalisés : PAC (~3 ans), toiture (2019)\n- À prévoir : cuisine\n\nVENDEUR\n- Mme Martin, départ en retraite en Bretagne\n- Délai : vendre avant l'été → motivation forte, probable souplesse\n\nPRIX\n- Demandé : 420 000 €, soit 3 360 €/m². À confronter aux ventes DVF du secteur avant de valider.\n\nÀ VÉRIFIER\n- Diagnostics complets (DPE officiel, électricité, amiante vu l'année)\n- Factures PAC et toiture, garanties décennales\n- Dernier avis de taxe foncière\n- Surface du séjour et nombre de pièces\n\nPROCHAINES ÉTAPES\n- Envoyer le compte rendu à la vendeuse\n- Préparer l'estimation comparative\n- Proposer un mandat sous 48 h",
        'rapport_vendeur' => "Bonjour Madame Martin,\n\nJe vous remercie encore pour votre accueil lors de la visite de votre maison.\n\nVoici un récapitulatif des éléments relevés :\n- Maison de 1978, 125 m² habitables sur un terrain de 800 m²\n- 4 chambres dont une au rez-de-chaussée, 2 salles de bain, 2 WC\n- Pompe à chaleur récente et toiture refaite en 2019\n- Jardin exposé plein sud, terrasse de 30 m², garage double\n- DPE : C\n\nVotre maison présente de vrais atouts, en particulier son jardin ensoleillé et les travaux récents qui rassureront les acquéreurs.\n\nAfin de préparer au mieux la mise en vente, pourriez-vous me transmettre les documents suivants :\n- les diagnostics immobiliers dont vous disposez\n- les factures de la pompe à chaleur et de la toiture\n- votre dernier avis de taxe foncière\n\nJe reviens vers vous très rapidement avec mon estimation détaillée, afin que nous puissions avancer selon votre calendrier.\n\nBien cordialement,\n{$signature}",
    ];
}
