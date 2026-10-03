<?php
return [
    'slug' => 'bienvenue',
    'title' => 'Bienvenue',
    'summary' => 'Ce qu’est le nouveau back-office, ce qui change par rapport à WordPress et le vocabulaire à connaître.',
    'sections' => [
        ['id' => 'role', 'title' => 'À quoi sert le back-office', 'html' => <<<'HTML'
<p>Le back-office est l’atelier du musée en ligne : c’est ici que l’équipe des historiens saisit, illustre et publie tout ce que les visiteurs voient sur le site — matchs, joueurs, articles, photos, pages d’accueil, quiz… Il remplace l’administration de l’ancien site WordPress.</p>
<p>Il s’ouvre à l’adresse <code>/admin</code> du site (par exemple <code>https://www.fcsochauxretro.com/admin</code>) avec votre adresse e-mail et votre mot de passe. Tout se fait depuis un navigateur, sur ordinateur, tablette ou téléphone.</p>
[[auto|<p>Le nouveau site calcule beaucoup de choses tout seul à partir de ce que vous saisissez : les statistiques de chaque joueur, les pages saison, les face-à-face avec chaque adversaire, les records, la carte des origines… Vous saisissez une composition une fois : tout le reste suit.</p>]]
HTML],
        ['id' => 'wordpress', 'title' => 'Ce qui change par rapport à WordPress', 'html' => <<<'HTML'
<table>
<tr><th>Dans WordPress</th><th>Dans le nouveau back-office</th></tr>
<tr><td>Articles (un article par match, par joueur…)</td><td><b>Fiches</b> d’un type précis : match, personne, article, page, objet, moment. Chaque type a son propre formulaire, avec les bons champs.</td></tr>
<tr><td>Catégories</td><td><b>Rubriques</b> : les mêmes, avec les mêmes menus. On coche les rubriques d’une fiche dans l’onglet « Classement &amp; SEO ».</td></tr>
<tr><td>Constructeur de page (blocs BeTheme)</td><td>Des onglets clairs : Infos, Composition, Récit, Médias… La mise en page est automatique et identique pour toutes les fiches.</td></tr>
<tr><td>Tableaux wpDataTables</td><td>Composition saisie en grille (une ligne par joueur) et tableaux modifiables comme une feuille de calcul.</td></tr>
<tr><td>Médias</td><td><b>Médiathèque</b> : légende, crédit et droits pour chaque photo, retouche sans abîmer l’original, « utilisée dans ».</td></tr>
<tr><td>Extensions (SEO, cookies, formulaires…)</td><td>Tout est intégré : référencement, cookies, contact, contributions, newsletter, dons.</td></tr>
<tr><td>Statistiques recopiées à la main</td><td>Calculées automatiquement à partir des compositions.</td></tr>
</table>
[[wp|<p>Tout le contenu de l’ancien site a été repris : 2 940 fiches, 12 735 photos, 884 vidéos, 2 593 tableaux. Les anciennes adresses des pages renvoient automatiquement vers les nouvelles : les liens partagés sur les réseaux sociaux ou dans Google continuent de fonctionner.</p>]]
HTML],
        ['id' => 'vocabulaire', 'title' => 'Le vocabulaire du musée', 'html' => <<<'HTML'
<table>
<tr><th>Mot</th><th>Ce que c’est</th></tr>
<tr><td><b>Fiche</b></td><td>Une page du musée : un match, une personne (joueur, entraîneur, dirigeant, personnage), un article, une page, un objet des réserves ou un moment du centenaire.</td></tr>
<tr><td><b>Rubrique</b></td><td>Un classement du site (Matchs › Saison 1987-1988, Nos Lions › Joueurs…), visible dans les menus.</td></tr>
<tr><td><b>Mosaïque</b></td><td>La page d’une rubrique : toutes ses fiches en vignettes, avec filtres et tri.</td></tr>
<tr><td><b>Image à la une</b></td><td>La photo principale d’une fiche : vignette des mosaïques, image de partage.</td></tr>
<tr><td><b>Composition</b></td><td>La liste des joueurs d’un match, ligne par ligne.</td></tr>
<tr><td><b>Statut</b></td><td>Brouillon, À relire, Planifié, Publié ou Corbeille : ce qui est visible ou non.</td></tr>
<tr><td><b>Version</b></td><td>Chaque enregistrement est gardé : on peut revoir et restaurer une version précédente.</td></tr>
</table>
HTML],
        ['id' => 'niveaux', 'title' => 'Deux niveaux d’accès', 'html' => <<<'HTML'
<ul>
<li><b>Administrateur</b> : tout, y compris les utilisateurs, les réglages, la suppression définitive, la restauration de versions, les sauvegardes (lancer, télécharger) et le lancement manuel des tâches planifiées.</li>
<li><b>Utilisateur</b> : tout le travail éditorial (fiches, médiathèque, rubriques, accueil, outils interactifs, contributions, dons…), sauf ces opérations. Il peut mettre une fiche à la corbeille, consulter l’historique, la liste des sauvegardes et l’état des tâches.</li>
</ul>
[[astuce|<p>Ce guide est toujours accessible par le menu <b>Aide</b> et par le bouton <b>? Aide</b> en haut de chaque écran, qui ouvre directement la partie qui le concerne. Les petites icônes <b>?</b> à côté des titres et des champs donnent une explication rapide au survol.</p>]]
HTML],
        ['id' => 'commencer', 'title' => 'Par où commencer', 'html' => <<<'HTML'
<ol>
<li>Lisez [[aide:prise-en-main|Prise en main]] (dix minutes) : connexion, menu, recherche, enregistrement.</li>
<li>Ouvrez une fiche match existante et parcourez ses onglets avec [[aide:matchs|Saisir un match]].</li>
<li>Gardez le <b>mémo</b> à portée de main (PDF de deux pages, sur la page d’accueil de l’aide).</li>
<li>En cas de doute, cherchez dans l’aide (« crédit », « composition », « publier plus tard »…) ou consultez [[aide:faq|Comment faire pour… ?]].</li>
</ol>
HTML],
    ],
];
