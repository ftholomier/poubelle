<?php
/**
 * Sources des totaux de référence des joueurs (App\Services\ReferenceTotals) : deux classements publiés,
 * relevés chaque jour par la tâche planifiée « totaux-reference » (et le bouton « Relever maintenant »
 * de Système › Tâches planifiées). Format « histoiredupsg » : pages WordPress lues par l'API wp-json
 * (repli : la page HTML), lignes « 1er – NOM 256 buts » et « 1) Nom 529 ».
 * Les noms qui ne se rapprochent pas tout seuls d'une fiche du musée : totaux-alias.php.
 */

return [
    'label' => 'Histoire du PSG — classements historiques des buteurs et des matchs officiels',
    'format' => 'histoiredupsg',
    'buteurs' => 'https://histoiredupsg.fr/classement-historique-buteurs-psg/',
    'matchs' => 'https://histoiredupsg.fr/classement-matchs-officiels-joues-psg/',
];
