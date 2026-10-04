<?php
/**
 * Tarifs d'adhésion de départ (Site de l'association › Adhésions) : montants d'EXEMPLE, à
 * valider par le bureau avant l'ouverture du site.
 */

return [
    'a_verifier' => true,
    'items' => [
        ['key' => 'individuel', 'label' => 'Adhésion individuelle', 'amount' => 15, 'text' => 'Pour soutenir l’association et participer à sa vie.', 'free' => false],
        ['key' => 'reduit', 'label' => 'Tarif réduit', 'amount' => 10, 'text' => 'Moins de 18 ans, étudiants, demandeurs d’emploi.', 'free' => false],
        ['key' => 'famille', 'label' => 'Adhésion famille', 'amount' => 25, 'text' => 'Pour toute la famille vivant sous le même toit.', 'free' => false],
        ['key' => 'bienfaiteur', 'label' => 'Membre bienfaiteur', 'amount' => 50, 'text' => 'À partir de 50 € : un soutien renforcé, du montant de votre choix.', 'free' => true],
    ],
];
