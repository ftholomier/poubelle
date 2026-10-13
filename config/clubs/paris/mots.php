<?php
// Mots propres au musée, appliqués à tous les textes (t()) et aux menus : texte du modèle commun => texte de Paris.
// (Une clé recopiée à l'identique protège un texte déjà juste : strtr essaie d'abord la clé la plus longue.)
return [
    'Fil jaune' => 'Fil bleu', 'fil jaune' => 'fil bleu', 'FIL JAUNE' => 'FIL BLEU',
    'Yellow Thread' => 'Blue Thread', 'Yellow thread' => 'Blue thread', 'yellow thread' => 'blue thread',
    // Libellés hérités du modèle sochalien (source des chiffres, rubrique des joueurs).
    'Fiches des Lions' => 'Fiches des joueurs', 'Nos Lions' => 'Nos joueurs',
    // Couleurs du modèle sochalien restées en dur (textes d'interface et leurs traductions anglaises).
    'jaune et bleu' => 'rouge et bleu', 'le jaune et le bleu' => 'le rouge et le bleu',
    'yellow and blue' => 'red and blue', 'yellow-and-blue' => 'red-and-blue',
    'Ma vie en jaune et bleu' => 'Ma vie en bleu & rouge',
    'Our Lions' => 'Our players',
    // Album Parini : les formés au club sont les « Titis ».
    'Les Lionceaux' => 'Les Titis', 'The Lion cubs' => 'The Titis',
    // var(--yellow) vaut le rouge de Paris : la poignée des maillots, les étoiles du Fil bleu.
    'Glissez la poignée jaune' => 'Glissez la poignée rouge', 'Drag the yellow handle' => 'Drag the red handle',
    'En jaune : les dix plus fidèles' => 'En rouge : les dix plus fidèles', 'In yellow: the ten most loyal' => 'In red: the ten most loyal',

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
