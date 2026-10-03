<?php
return [
    'slug' => 'qualite',
    'title' => 'Qualité des données',
    'summary' => 'Le tableau Qualité, les liens joueurs, l’orthographe, les adversaires, stades et lieux : vérifier et corriger.',
    'sections' => [
        ['id' => 'tableau', 'title' => 'Le tableau Qualité', 'html' => <<<'HTML'
<p>Pilotage › <b>Qualité</b> recense ce qui mérite une vérification, recalculé à chaque modification. Filtrez par niveau (hautes, moyennes, basses) ; « Corriger » ouvre la fiche concernée.</p>
[[img:qualite.webp|Le tableau Qualité et ses onglets]]
<table>
<tr><th>Alerte</th><th>Ce qu’elle signifie</th><th>Que faire</th></tr>
<tr><td>Total des buts ≠ buteurs</td><td>le score ne correspond pas aux buts de la composition</td><td>corriger le score ou les buts</td></tr>
<tr><td>Date du titre ≠ date de la fiche</td><td>la date du titre d’origine diffère de la date saisie</td><td>vérifier la vraie date</td></tr>
<tr><td>Même tableau de composition que…</td><td>une composition copiée d’un autre match sur l’ancien site</td><td>saisir la bonne composition</td></tr>
<tr><td>Statistiques incohérentes</td><td>le total d’un tableau de statistiques ne correspond pas à la somme des saisons</td><td>corriger le tableau</td></tr>
<tr><td>Tableau de statistiques identique à celui de N autres fiches</td><td>le même tableau recopié sur plusieurs fiches de joueurs (modèle de l’ancien site) : la fiche affiche les chiffres d’un autre joueur</td><td>saisir le vrai tableau du joueur, ou vider l’onglet Statistiques en attendant</td></tr>
<tr><td>Joueur inscrit deux fois dans la composition</td><td>le même joueur figure sur deux lignes de la composition (il n’est compté qu’une fois dans les statistiques)</td><td>supprimer la ligne en trop, en gardant ses buts, remplacements et cartons</td></tr>
<tr><td>Dates à vérifier</td><td>dates de la personne incohérentes : naissance improbable, décès avant la naissance, départ avant l’arrivée, arrivée à un âge impossible</td><td>corriger la date fautive sur la fiche de la personne</td></tr>
<tr><td>Fiche marquée « à venir »</td><td>fiche annoncée sur l’ancien site mais pas encore rédigée</td><td>compléter ou laisser en brouillon</td></tr>
</table>
HTML],
        ['id' => 'liens', 'title' => 'Liens joueurs', 'html' => <<<'HTML'
<p>Onglet <b>Liens joueurs</b> :</p>
<ul>
<li><b>Joueurs cités sans fiche</b> : un nom présent dans des compositions mais sans fiche. « Créer la fiche » l’ouvre déjà remplie ; si la fiche existe sous un autre nom, ajoutez la graphie dans « Autres graphies dans les compositions ».</li>
<li><b>Noms reliés par rapprochement</b> : le site a relié automatiquement un nom écrit autrement (apostrophe, faute de frappe, nom incomplet). « Vérifier » ouvre la fiche : si le lien est faux, choisissez le bon joueur dans la composition du match.</li>
</ul>
[[img:qualite-liens.webp|Les liens joueurs à créer ou à vérifier]]
HTML],
        ['id' => 'orthographe', 'title' => 'Orthographe et syntaxe', 'html' => <<<'HTML'
<p>Onglet <b>Orthographe &amp; syntaxe</b> : les fiches pour lesquelles le correcteur propose des corrections, avec un exemple. Il vérifie en tâche de fond chaque fiche nouvelle ou modifiée, puis toutes les autres.</p>
[[img:qualite-orthographe.webp|Qualité › Orthographe : (1) l’avancement de la vérification, (2) les corrections proposées et un exemple, (3) « Corriger » ouvre la fiche avec le correcteur]]
<ul>
<li>Niveau <b>haut</b> : au moins trois fautes de langue (orthographe, accord, conjugaison, syntaxe) ; <b>moyen</b> : une ou deux ; <b>bas</b> : ponctuation ou typographie seulement.</li>
<li><b>Corriger</b> ouvre la fiche et lance le correcteur : acceptez ou ignorez chaque proposition, puis enregistrez. La fiche quitte la liste à la vérification suivante.</li>
<li>Le <b>Dictionnaire du musée</b> (lien au-dessus de la liste) contient les mots que le correcteur ne doit jamais corriger. Les noms des joueurs, des clubs et des stades du musée sont déjà reconnus ; ajoutez-y surnoms, mots du club ou du patois, ou utilisez « + Dictionnaire » dans le correcteur.</li>
</ul>
[[attention|<p>Sans clé Gemini, seules les règles de base du musée sont appliquées (ponctuation, typographie, mots répétés) : les accords et la syntaxe ne sont pas vérifiés.</p>]]
HTML],
        ['id' => 'autres', 'title' => 'Photos sans crédit, lieux, traductions', 'html' => <<<'HTML'
<ul>
<li><b>Photos sans crédit</b> : ouvrez la photo dans la médiathèque et complétez le crédit (ou plusieurs photos à la fois).</li>
<li><b>Lieux de naissance inconnus</b> : joueurs absents de la carte des origines ; complétez la naissance dans leur fiche.</li>
<li><b>Traductions à revoir</b> : fiches dont le français a changé depuis la traduction anglaise.</li>
</ul>
HTML],
        ['id' => 'referentiels', 'title' => 'Saisons, adversaires, lieux', 'html' => <<<'HTML'
<p>Contenus › <b>Saisons, adversaires, lieux</b> : les listes communes à toutes les fiches, en onglets.</p>
<ul>
<li><b>Adversaires</b> : un même club écrit de plusieurs façons dans les fiches est regroupé (variantes de noms) ; ville, logo.</li>
<li><b>Stades</b> et <b>Lieux de naissance</b> : position sur la carte, trouvée automatiquement, à corriger si besoin.</li>
<li><b>Compétitions</b> et <b>Saisons</b> : la liste calculée depuis les matchs.</li>
</ul>
[[img:referentiels.webp|Les adversaires et leurs variantes de noms]]
[[auto|<p>Une correction faite ici s’applique à toutes les fiches, aux face-à-face, aux bilans et à la carte.</p>]]
HTML],
    ],
];
