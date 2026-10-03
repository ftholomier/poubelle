<?php
return [
    'slug' => 'fiches',
    'title' => 'Travailler avec les fiches',
    'summary' => 'Listes, création, onglets, correcteur d’orthographe, statuts, aperçu, historique des versions et corbeille.',
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
<p>Une fiche s’édite en onglets, différents selon le type. À droite, cinq panneaux restent visibles :</p>
<ul>
<li><b>Publication</b> : statut, enregistrement, aperçu, note de version, corbeille ;</li>
<li><b>Orthographe</b> : le correcteur d’orthographe et de syntaxe (voir ci-dessous) ;</li>
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
