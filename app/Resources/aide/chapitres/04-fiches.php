<?php
return [
    'slug' => 'fiches',
    'title' => 'Travailler avec les fiches',
    'summary' => 'Listes, création, onglets, statuts, aperçu, historique des versions et corbeille.',
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
<p>Une fiche s’édite en onglets, différents selon le type. À droite, quatre panneaux restent visibles :</p>
<ul>
<li><b>Publication</b> : statut, enregistrement, aperçu, note de version, corbeille ;</li>
<li><b>Mis à jour automatiquement</b> : ce que l’enregistrement recalcule ;</li>
<li><b>Contrôle qualité</b> : ce qui reste à vérifier sur cette fiche ;</li>
<li><b>Version EN</b> : l’état de la traduction anglaise.</li>
</ul>
[[img:editeur-publication.webp|Le panneau Publication : (1) statut, (2) enregistrer, (3) aperçu, (4) voir sur le site, (5) note de version]]
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
<p>Si le navigateur se ferme avant l’enregistrement, la fiche propose à la réouverture de récupérer le brouillon trouvé sur l’ordinateur.</p>
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
