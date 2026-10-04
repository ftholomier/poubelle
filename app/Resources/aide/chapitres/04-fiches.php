<?php
return [
    'slug' => 'fiches',
    'title' => 'Travailler avec les fiches',
    'summary' => 'Listes, création, onglets, correcteur d’orthographe, recherche sur le web, statuts, aperçu, historique des versions et corbeille.',
    'sections' => [
        ['id' => 'listes', 'title' => 'Les listes de fiches', 'html' => <<<'HTML'
<p>Menu <b>Contenus</b> : Matchs, Personnes, Articles &amp; pages, Objets. Chaque liste se filtre (saison, compétition, rubrique, statut…), se trie et se cherche.</p>
[[img:liste-matchs.webp|La liste des matchs : (1) recherche, (2) filtres, (3) cases à cocher, (4) actions groupées]]
<p><b>Actions groupées</b> : cochez plusieurs fiches, puis Publier, À relire, Brouillon, Traduire (EN) ou Corbeille.</p>
HTML],
        ['id' => 'creer', 'title' => 'Créer une fiche', 'html' => <<<'HTML'
<ol>
<li>Bouton <b>+ Nouveau</b> en haut, ou bouton « Nouveau » de la liste.</li>
<li>Remplissez au moins les champs marqués d’une étoile, puis enregistrez : la fiche est créée en <b>brouillon</b>.</li>
<li>Complétez les onglets à votre rythme, puis publiez.</li>
</ol>
[[astuce|<p>Pour une personne citée dans une composition sans fiche, le bouton <b>+</b> de la ligne de composition (ou « Créer la fiche » dans Qualité) ouvre une nouvelle fiche déjà remplie avec son nom.</p>]]
HTML],
        ['id' => 'editeur', 'title' => 'L’éditeur et ses onglets', 'html' => <<<'HTML'
<p>Une fiche s’édite en onglets, différents selon le type. À droite, des panneaux restent visibles :</p>
<ul>
<li><b>Publication</b> : statut, enregistrement, aperçu, note de version, corbeille ;</li>
<li><b>Orthographe</b> : le correcteur d’orthographe et de syntaxe (voir ci-dessous) ;</li>
<li><b>Recherche sur le web</b> : l’IA cherche sur Internet ce qui pourrait corriger ou compléter la fiche (voir ci-dessous) ;</li>
<li><b>Mis à jour automatiquement</b> : ce que l’enregistrement recalcule ;</li>
<li><b>Contrôle qualité</b> : ce qui reste à vérifier sur cette fiche ;</li>
<li><b>Version EN</b> : l’état de la traduction anglaise.</li>
</ul>
[[img:editeur-publication.webp|Le panneau Publication : (1) statut, (2) enregistrer, (3) aperçu, (4) voir sur le site, (5) note de version]]
HTML],
        ['id' => 'orthographe', 'title' => 'Vérifier l’orthographe', 'html' => <<<'HTML'
<p>Le bouton <b>Vérifier l’orthographe</b> (panneau « Orthographe », à droite) relit tous les textes de la fiche, version anglaise comprise : titres, introduction, blocs de texte, temps forts, réactions, brèves, légendes, référencement…</p>
[[img:correcteur.webp|Le correcteur : (1) vérifier, (2) la faute barrée et sa correction, (3) corriger, (4) ignorer, (5) ajouter au dictionnaire, (6) tout corriger]]
<ol>
<li>Cliquez sur <b>Vérifier l’orthographe</b> : le panneau du correcteur s’ouvre, en quelques secondes (jusqu’à une minute pour une très longue fiche).</li>
<li>Chaque proposition montre le passage, la faute <s>barrée</s>, la correction en vert et une explication. Cliquez sur le passage : l’onglet du champ s’ouvre et le passage y est sélectionné.</li>
<li><b>Corriger</b> reporte la correction dans le champ (<b>Annuler</b> la retire) ; <b>Tout corriger</b> applique toutes les propositions restantes.</li>
<li><b>Ignorer</b> écarte une proposition fausse : elle ne reviendra plus pour cette fiche. <b>+ Dictionnaire</b> protège un nom propre ou un mot du club sur toutes les fiches.</li>
<li><b>Enregistrez</b> la fiche : la note de version « Corrections d’orthographe (correcteur) » est remplie pour vous.</li>
</ol>
<table>
<tr><th>Qui relit</th><th>Ce qui est vérifié</th></tr>
<tr><td>Gemini (quand la clé est réglée)</td><td>orthographe, accords, conjugaison, homophones (a/à, et/est, ces/ses…), mot manquant ou en trop, construction fautive, ponctuation, majuscules</td></tr>
<tr><td>Règles du musée (toujours)</td><td>mot répété (« de de »), espace avant une virgule ou un point, espace oubliée après la ponctuation, « l’ équipe », ordinaux (« 2e », « 1re » et non « 2ème », « 1ère »), « À » en début de phrase</td></tr>
</table>
[[attention|<p>Le correcteur propose, vous décidez : relisez chaque proposition, surtout dans les citations, les noms propres et les termes d’époque. Rien n’est modifié sans votre clic, et rien n’est publié avant « Enregistrer ».</p>]]
[[astuce|<p>Pendant la saisie, le navigateur souligne déjà en rouge les mots inconnus (clic droit pour une suggestion). Le correcteur va plus loin : accords, conjugaison et syntaxe. Le même bouton existe dans Accueil &amp; bandeau, Rubriques &amp; menus et les outils interactifs (quiz, frise, maillots…).</p>]]
[[auto|<p>Le correcteur vérifie aussi chaque fiche en tâche de fond, quelques minutes après son enregistrement : le panneau « Orthographe » annonce alors le nombre de corrections proposées, et Qualité › Orthographe liste les fiches concernées.</p>]]
HTML],
        ['id' => 'recherche-web', 'title' => 'Chercher sur le web (aide à l’historien)', 'html' => <<<'HTML'
<p>Le bouton <b>Chercher sur le web</b> (panneau « Recherche sur le web », à droite) demande à l’IA de chercher avec Google ce qui concerne exactement cette fiche, puis de le comparer à ce qui est saisi. Un panneau s’ouvre en 10 à 60 secondes ; vous pouvez continuer à travailler pendant ce temps.</p>
<table>
<tr><th>Rubrique du panneau</th><th>Ce que l’IA propose</th></tr>
<tr><td>Divergences avec la fiche</td><td>ce que les sources disent autrement que la fiche : date, score, buteurs, affluence, arbitre, composition, naissance, parcours…</td></tr>
<tr><td>Compléments</td><td>ce qui manque à la fiche, en particulier les champs vides (arbitre, spectateurs…)</td></tr>
<tr><td>Pistes à consulter</td><td>pages, archives, photos ou vidéos qui méritent un coup d’œil</td></tr>
</table>
<ol>
<li>Chaque proposition indique ce que dit la fiche, ce que disent les sources, un niveau de confiance et les <b>pages</b> qui l’appuient : ouvrez-les (nouvel onglet) et vérifiez.</li>
<li>Si c’est juste, reportez l’information vous-même dans la fiche (<b>Copier</b> met le texte de la proposition dans le presse-papiers), puis enregistrez, avec une note de version qui cite la source.</li>
<li>En bas du panneau : toutes les pages consultées et les recherches Google faites par l’IA (affichées comme Google le demande).</li>
</ol>
[[img:recherche-web.webp|Le panneau « Recherche sur le web » (exemple) : (1) ce que dit la fiche, (2) ce que disent les sources, (3) les pages qui l’appuient, (4) copier la proposition, (5) le niveau de confiance]]
[[img:recherche-web-sources.webp|En bas du panneau : les pages consultées et les recherches Google de l’IA]]
[[attention|<p>L’IA peut se tromper ou confondre deux matchs, deux homonymes : ne reportez jamais une information sans avoir lu la source. Rien n’est modifié dans la fiche sans vous.</p>]]
[[auto|<p>Le dernier résultat de chaque fiche reste consultable 30 jours (« Voir les propositions ») sans nouvelle recherche. La recherche n’a lieu que quand on clique, jamais en tâche de fond ; un plafond mensuel se règle dans Réglages › Recherche sur le web.</p>]]
HTML],
        ['id' => 'statuts', 'title' => 'Statuts et publication programmée', 'html' => <<<'HTML'
<table>
<tr><th>Statut</th><th>Sur le site</th><th>Quand l’utiliser</th></tr>
<tr><td>Brouillon</td><td>invisible</td><td>fiche en cours de saisie</td></tr>
<tr><td>À relire</td><td>invisible</td><td>fiche terminée, à faire vérifier par un collègue</td></tr>
<tr><td>Planifié</td><td>publiée seule à la date et l’heure choisies</td><td>anniversaire d’un match, moment du centenaire</td></tr>
<tr><td>Publié</td><td>visible</td><td>fiche prête</td></tr>
<tr><td>Corbeille</td><td>retirée</td><td>fiche à supprimer (récupérable)</td></tr>
</table>
[[auto|<p>Une fiche planifiée est publiée par la tâche automatique qui passe toutes les cinq minutes. Le tableau de bord liste les publications programmées.</p>]]
HTML],
        ['id' => 'apercu', 'title' => 'Aperçu avant publication', 'html' => <<<'HTML'
<p>Le bouton <b>Aperçu</b> ouvre la fiche telle qu’elle sera sur le site, avec ce que vous venez de saisir, même sans avoir enregistré. Un bandeau jaune rappelle qu’il ne s’agit pas de la page publique.</p>
HTML],
        ['id' => 'brouillon', 'title' => 'Brouillon de secours et modifications simultanées', 'html' => <<<'HTML'
<p>Ce que vous tapez est gardé au fur et à mesure dans le navigateur. Si l’onglet se ferme avant l’enregistrement (coupure, fausse manœuvre), la fiche le signale à la réouverture : <b>Le récupérer</b> enregistre aussitôt ce brouillon (une nouvelle version, visible dans l’Historique) ; <b>L’ignorer</b> l’efface et garde la fiche telle qu’elle était.</p>
[[img:editeur-brouillon.webp|Un brouillon non enregistré retrouvé : « Le récupérer » ou « L’ignorer »]]
[[attention|<p>Le brouillon de secours est propre à l’ordinateur et au navigateur utilisés : il ne remplace pas l’enregistrement.</p>]]
HTML],
        ['id' => 'verrou', 'title' => 'Deux personnes sur la même fiche', 'html' => <<<'HTML'
<p>Quand une collègue a déjà ouvert une fiche, vous le voyez tout de suite : un bandeau indique <b>qui la modifie et depuis quand</b>, et la fiche s’ouvre en <b>lecture seule</b>. Les onglets se consultent et l’aperçu fonctionne, mais rien ne peut être enregistré. Dans les listes de fiches, la mention <b>✎ Prénom</b> signale les fiches ouvertes en ce moment.</p>
[[img:verrou.webp|Une fiche déjà ouverte : (1) qui la modifie et depuis quand, (2) prendre la main, (3) enregistrement impossible en lecture seule]]
<ul>
<li>Dès que la personne ferme la fiche, le bandeau vous le dit : <b>Modifier maintenant</b>, ou <b>Recharger pour modifier</b> si elle a enregistré une nouvelle version entre-temps.</li>
<li><b>Prendre la main</b> (urgence, fiche oubliée ouverte) : vous pouvez modifier ; la personne est prévenue aussitôt et ne peut plus enregistrer. Le journal d’activité garde la trace de la prise de main.</li>
<li>Une fiche oubliée se libère toute seule : après 30 minutes sans activité, ou 2 minutes après la fermeture de l’onglet (ordinateur éteint, coupure de réseau).</li>
</ul>
[[astuce|<p>Le même verrou protège les quiz, la frise et les autres contenus interactifs, l’accueil, les rubriques et l’album. Et si deux personnes enregistrent malgré tout la même fiche, la seconde est prévenue au lieu d’écraser le travail de la première.</p>]]
HTML],
        ['id' => 'historique', 'title' => 'Historique des versions', 'html' => <<<'HTML'
<p>L’onglet <b>Historique</b> liste chaque enregistrement : qui, quand, ce qui a changé, et la note de version. Pour une fiche reprise de l’ancien site, la première version est son état d’origine.</p>
[[img:editeur-historique.webp|L’historique : chaque version peut être consultée ; un administrateur peut la restaurer]]
<p>Restaurer une version crée une nouvelle version : rien n’est jamais perdu. La restauration est réservée aux administrateurs.</p>
HTML],
        ['id' => 'corbeille', 'title' => 'La corbeille', 'html' => <<<'HTML'
<p>« Mettre à la corbeille » retire la fiche du site. Lien <b>Voir la corbeille</b> sous les listes de fiches : on peut l’en sortir à tout moment. La suppression définitive est réservée aux administrateurs (une copie reste dans l’historique).</p>
[[img:corbeille.webp|La corbeille]]
HTML],
    ],
];
