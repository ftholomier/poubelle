<?php
// Mots propres au musée, appliqués à tous les textes (t()) et aux menus : texte du modèle commun => texte de Paris.
// (Une clé recopiée à l'identique protège un texte déjà juste : strtr essaie d'abord la clé la plus longue.)
return [
    'Fil jaune' => 'Fil bleu', 'fil jaune' => 'fil bleu', 'FIL JAUNE' => 'FIL BLEU',
    'Yellow Thread' => 'Blue Thread', 'Yellow thread' => 'Blue thread', 'yellow thread' => 'blue thread',

    // Page « Les chiffres » : « {team} » vaut « PSG », qui s'écrit avec l'article en français.
    'pour PSG.' => 'pour le PSG.',
    'Où PSG a disputé' => 'Où le PSG a disputé',
    'où PSG a disputé' => 'où le PSG a disputé',
    'passés par PSG' => 'passés par le PSG',
    'Quand PSG marque' => 'Quand le PSG marque',
    'Quand PSG encaisse' => 'Quand le PSG encaisse',
    ', PSG s’impose' => ', le PSG s’impose',
    'le PSG menait' => 'le PSG menait', 'PSG menait' => 'Le PSG menait',
    'Made in PSG' => 'Formés au club',
    'raconté, Parc compris.' => 'raconté, Parc des Princes compris.',
    'Le Parc et les tribunes' => 'Le Parc et les tribunes', 'Parc et les tribunes' => 'Le Parc et les tribunes',
    'bêtes noires et voisins de l’Est.' => 'bêtes noires et grands rivaux.',
];
