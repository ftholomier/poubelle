<?php
return [
    'slug' => 'qualite',
    'title' => 'Qualité des données',
    'summary' => 'Le tableau Qualité, le contrôle complet (bouton « Contrôler maintenant »), les liens joueurs, l’orthographe, les adversaires, stades et lieux : vérifier et corriger.',
    'sections' => [
        ['id' => 'tableau', 'title' => 'Le tableau Qualité', 'html' => <<<'HTML'
<p>Pilotage › <b>Qualité</b> recense ce qui mérite une vérification, recalculé à chaque modification et visible de tous les comptes. Les alertes les plus graves viennent en premier (300 par page) ; filtrez par niveau (hautes, moyennes, basses) ; « Corriger » ouvre la fiche concernée, à l’onglet où se fait la correction. L’onglet <b>À compléter</b> regroupe les informations manquantes : « xx » de l’ancien site, fiches « à venir », arbitre, rubrique de la personne, liens vidéo cassés ; l’onglet <b>Adresses et médias</b>, les fiches inaccessibles ou abîmées (voir plus bas).</p>
[[img:qualite.webp|Le tableau Qualité et ses onglets]]
<table>
<tr><th>Alerte</th><th>Ce qu’elle signifie</th><th>Que faire</th></tr>
<tr><td>Total des buts ≠ buteurs</td><td>le score ne correspond pas aux buts de la composition</td><td>corriger le score ou les buts</td></tr>
<tr><td>Date en toutes lettres ≠ date de la fiche</td><td>la date écrite en tête du match (« Samedi 12 août 1994 ») diffère de la date saisie</td><td>vérifier la vraie date</td></tr>
<tr><td>Jour de la semaine incohérent</td><td>le jour écrit (« Jeudi 30 janvier 1991 ») ne correspond pas à la date : le jour ou la date est faux</td><td>corriger l’un ou l’autre</td></tr>
<tr><td>Tirs au but sans le score de la séance</td><td>« (tab 9-8) » est écrit mais la séance n’est pas saisie</td><td>Score › Prolongation « Tirs au but » et les deux scores</td></tr>
<tr><td>Même tableau de composition que…</td><td>une composition copiée d’un autre match sur l’ancien site</td><td>saisir la bonne composition</td></tr>
<tr><td>Statistiques incohérentes</td><td>le total d’un tableau de statistiques ne correspond pas à la somme des saisons</td><td>corriger le tableau</td></tr>
<tr><td>Tableau de statistiques identique à celui de N autres fiches</td><td>le même tableau recopié sur plusieurs fiches de joueurs (modèle de l’ancien site) : la fiche affiche les chiffres d’un autre joueur</td><td>saisir le vrai tableau du joueur, ou vider l’onglet Statistiques en attendant</td></tr>
<tr><td>Joueur inscrit deux fois dans la composition</td><td>le même joueur figure sur deux lignes de la composition (il n’est compté qu’une fois dans les statistiques)</td><td>supprimer la ligne en trop, en gardant ses buts, remplacements et cartons</td></tr>
<tr><td>Dates à vérifier</td><td>dates de la personne incohérentes : naissance improbable, décès avant la naissance, départ avant l’arrivée, arrivée à un âge impossible, date qui n’existe pas (30 février)</td><td>corriger la date fautive sur la fiche de la personne</td></tr>
<tr><td>Date du match non renseignée, ou impossible</td><td>le match n’apparaît ni dans sa saison, ni dans les bilans, ni dans « Ce jour-là »</td><td>onglet Infos : saisir la date</td></tr>
<tr><td>Match rangé dans une autre saison</td><td>la date ne tombe pas dans la saison indiquée (fichier modifié à la main ou reprise de l’ancien site)</td><td>vérifier la date ; la saison se recalcule en l’enregistrant</td></tr>
<tr><td>Résultat incohérent avec le score</td><td>victoire, nul ou défaite ne correspond pas au score (ou tirs au but saisis sur un score qui n’est pas nul)</td><td>onglet Infos : réenregistrer le score</td></tr>
<tr><td>Score non renseigné, ou saisi pour un match à venir</td><td>match officiel joué sans score ; ou score d’un match daté dans le futur</td><td>saisir le score, ou corriger la date</td></tr>
<tr><td>Joueur entré en cours de jeu noté titulaire</td><td>une minute d’entrée en jeu sur une ligne de titulaire (son temps de jeu compterait 90 minutes) ; aussi : plus de 11 titulaires, deux gardiens, composition incomplète</td><td>Compo &amp; événements : poste « Remplaçant », ou minute de sortie à la bonne place</td></tr>
<tr><td>Même jour et même adversaire que…</td><td>le même match saisi deux fois (compté deux fois dans les bilans)</td><td>garder une seule fiche, mettre l’autre à la corbeille</td></tr>
<tr><td>Fiche marquée « à venir »</td><td>fiche annoncée sur l’ancien site mais pas encore rédigée</td><td>compléter ou laisser en brouillon</td></tr>
<tr><td>Information inconnue notée « xx »</td><td>l’ancien site notait « xx » ce qui n’était pas connu (date, lieu, arbitre, minute…) ; le site public le cache</td><td>compléter l’information, ou retirer le « xx »</td></tr>
<tr><td>Lien vidéo de l’ancien site non reconnu</td><td>le lien de la vidéo est cassé</td><td>onglet Médias : recoller le bon lien YouTube ou Dailymotion</td></tr>
<tr><td>Aucune rubrique cochée</td><td>personne sans rubrique (joueur, entraîneur, dirigeant…)</td><td>onglet Identité : cocher la rubrique</td></tr>
</table>
HTML],
        ['id' => 'controle', 'title' => 'Contrôler maintenant', 'html' => <<<'HTML'
<p>En haut du tableau Qualité, le bouton <b>Contrôler maintenant</b> refait toutes les vérifications sur toutes les fiches, en quelques secondes : scores, dates, compositions, liens joueurs, adresses, rubriques, images, redirections, traductions… Puis il compare avec le contrôle précédent.</p>
[[img:qualite-controle.webp|(1) le bouton « Contrôler maintenant », (2) le résumé du dernier contrôle, (3) les nouvelles anomalies de tous les onglets, (4) chaque nouvelle anomalie est marquée « Nouveau »]]
<ul>
<li>Le message indique combien d’anomalies sont <b>nouvelles</b> depuis le contrôle précédent et combien ont été <b>corrigées</b> ; la liste des nouvelles anomalies s’ouvre aussitôt, tous onglets confondus, avec l’onglet de chacune.</li>
<li>Les nouvelles anomalies gardent leur pastille <b>Nouveau</b> jusqu’au contrôle suivant ; une anomalie qui apparaît entre deux contrôles (fiche enregistrée entre-temps) est marquée tout de suite. Le chiffre de chaque onglet indique combien il en contient.</li>
<li>Une anomalie déjà connue dont seuls les chiffres changent (« Total des buts (3) » devenu « (4) ») n’est pas « nouvelle ».</li>
<li>Le premier contrôle se compare au contrôle complet du site fait en octobre 2026 : seules les anomalies apparues depuis sont « nouvelles ».</li>
<li><b>Contrôles précédents</b> (sous le résumé) garde la trace des derniers contrôles : qui, quand, combien de nouvelles et de corrigées. Chaque contrôle est aussi noté dans le Journal.</li>
<li>Si des fichiers de fiches ont été changés hors du back-office (envoi par FTP, restauration), le contrôle remet aussi à jour la liste des fiches et la recherche.</li>
</ul>
[[astuce|<p>Bon réflexe : un contrôle à la fin d’une séance de saisie. Le tableau de bord rappelle les nouvelles anomalies dans <b>À faire</b>.</p>]]
HTML],
        ['id' => 'liens', 'title' => 'Liens joueurs', 'html' => <<<'HTML'
<p>Onglet <b>Liens joueurs</b> :</p>
<ul>
<li><b>Joueurs cités sans fiche</b> : un nom présent dans des compositions mais sans fiche. « Créer la fiche » l’ouvre déjà remplie ; si la fiche existe sous un autre nom, ajoutez la graphie dans « Autres graphies dans les compositions ».</li>
<li><b>Noms reliés par rapprochement</b> : le site a relié automatiquement un nom écrit autrement (apostrophe, faute de frappe, nom incomplet). « Vérifier » ouvre la fiche : si le lien est faux, choisissez le bon joueur dans la composition du match.</li>
<li><b>Même nom que…</b> : deux fiches de personnes portent le même nom. Fiche en double : gardez la plus complète et mettez l’autre à la corbeille. Homonymes : renseignez les deux dates de naissance, l’alerte disparaît.</li>
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
        ['id' => 'autres', 'title' => 'Adresses, médias, crédits, lieux, traductions', 'html' => <<<'HTML'
<ul>
<li><b>Adresses et médias</b> : deux fiches à la même adresse (une seule s’affiche), adresse vide ou mal formée, fiche rangée dans une rubrique supprimée, image absente de la médiathèque ou de son fichier, fichier de fiche illisible (à remplacer par sa dernière sauvegarde). On y trouve aussi les <b>redirections</b> à revoir (en boucle, en chaîne, vers une page absente ou non publiée, inutiles), les <b>adversaires et stades</b> en double ou dont une graphie désigne deux clubs, et les rubriques rattachées à une rubrique supprimée : « Corriger » ouvre le bon écran.</li>
<li><b>Photos sans crédit</b> : ouvrez la photo dans la médiathèque et complétez le crédit (ou plusieurs photos à la fois).</li>
<li><b>Lieux de naissance inconnus</b> : joueurs absents de la carte des origines ; complétez la naissance dans leur fiche.</li>
<li><b>Traductions à revoir</b> : fiches publiées dont le français a changé depuis la traduction anglaise (niveau moyen si la version anglaise avait été corrigée à la main) ; textes de l’interface dont la traduction n’a pas les mêmes variables (« {n} ») ou les mêmes liens que le français.</li>
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
