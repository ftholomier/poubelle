<?php
return [
    'slug' => 'prise-en-main',
    'title' => 'Prise en main',
    'summary' => 'Se connecter, se repérer dans le menu, rechercher, créer, enregistrer : les gestes de base.',
    'sections' => [
        ['id' => 'connexion', 'title' => 'Se connecter', 'html' => <<<'HTML'
<p>Un administrateur vous invite depuis l’écran Utilisateurs : vous recevez un e-mail avec un lien pour choisir votre mot de passe (au moins 10 caractères). Ensuite, connectez-vous à l’adresse <code>/admin</code> avec votre e-mail et ce mot de passe.</p>
[[img:connexion.webp|L’écran de connexion, avec le lien « Mot de passe oublié »]]
<ul>
<li><b>Mot de passe oublié</b> : lien sous le formulaire ; un e-mail vous permet d’en choisir un nouveau.</li>
<li>Après plusieurs essais erronés, la connexion est bloquée quelques minutes (protection contre les intrusions).</li>
<li><b>Se déconnecter</b> : menu en haut à droite (vos initiales).</li>
</ul>
HTML],
        ['id' => 'tableau-de-bord', 'title' => 'Le tableau de bord', 'html' => <<<'HTML'
<p>C’est la page d’arrivée. Elle résume l’état du musée et ce qui vous attend.</p>
[[img:tableau-de-bord.webp|Le tableau de bord : (1) le menu, (2) la recherche globale, (3) « + Nouveau », (4) l’aide de l’écran, (5) votre compte, (6) la liste « À faire »]]
<ol>
<li><b>Le menu</b>, à gauche, regroupe les écrans par thème : Pilotage, Contenus, Éditorial, Interactif, Communauté, Système et Aide.</li>
<li><b>La recherche globale</b> trouve une fiche, une photo ou un écran.</li>
<li><b>+ Nouveau</b> crée un match, une personne, un article, un objet, une question de quiz…</li>
<li><b>? Aide</b> ouvre la partie du guide qui explique l’écran affiché.</li>
<li><b>Votre compte</b> : profil, mot de passe, déconnexion.</li>
<li><b>À faire</b> : contributions à traiter, photos à créditer, joueurs sans fiche… Chaque ligne mène à l’écran concerné.</li>
</ol>
<p>Sous le titre, la ligne <b>★ Favoris</b> garde vos écrans les plus utilisés à un clic (voir ci-dessous).</p>
HTML],
        ['id' => 'favoris', 'title' => 'Vos favoris, en haut de chaque écran', 'html' => <<<'HTML'
<p>Pour ne plus chercher dans le menu de gauche, mettez vos écrans les plus utilisés dans la bande du haut, sous le titre : un clic sur un favori ouvre l’écran.</p>
<ol>
<li>Cliquez sur <b>+ Ajouter un favori</b>.</li>
<li>La fenêtre propose d’abord <b>Cette page</b> (l’écran affiché, par exemple une fiche que vous reprenez souvent, ou une liste filtrée), puis tous les écrans du menu, les créations (« Fiche match », « Personne »…) et, pour les administrateurs, chaque groupe des Réglages. Tapez quelques lettres dans « Chercher un écran à ajouter » pour filtrer.</li>
<li><b>☆ Ajouter</b> le met dans la bande ; <b>★ Ajouté</b> l’en retire.</li>
</ol>
<p>En haut de la même fenêtre, vos favoris se <b>renomment</b> (cliquez dans le nom, tapez, <kbd>Entrée</kbd>), se <b>déplacent</b> (↑ ↓) et se <b>retirent</b> (✕). Douze au plus. Chacun a les siens : vos favoris ne changent rien pour vos collègues.</p>
HTML],
        ['id' => 'recherche', 'title' => 'Trouver n’importe quoi : la recherche globale', 'html' => <<<'HTML'
<p>Cliquez dans la barre « Rechercher partout » ou tapez <kbd>Ctrl</kbd> + <kbd>K</kbd> (<kbd>⌘</kbd> + <kbd>K</kbd> sur Mac) depuis n’importe quel écran. Tapez quelques lettres : un nom de joueur, un adversaire, une saison, un nom de fichier photo, un écran (« redirections »).</p>
[[img:recherche-globale.webp|La recherche globale : fiches, médias et écrans en une seule liste]]
<p>Flèches <kbd>↑</kbd> <kbd>↓</kbd> pour choisir, <kbd>Entrée</kbd> pour ouvrir, <kbd>Échap</kbd> pour fermer.</p>
HTML],
        ['id' => 'aide-rapide', 'title' => 'Les bulles d’aide « ? »', 'html' => <<<'HTML'
<p>À côté du titre de chaque écran et de nombreux blocs et champs, une petite icône <b>?</b> affiche une explication courte quand on la survole (ou quand on la touche sur tablette). Celle du titre de l’écran propose aussi un lien vers le guide complet.</p>
[[img:bulle-aide.webp|Une bulle d’aide ouverte sur la composition d’un match]]
HTML],
        ['id' => 'enregistrer', 'title' => 'Enregistrer, sans jamais perdre son travail', 'html' => <<<'HTML'
<ul>
<li><b>Enregistrer</b> : bouton du panneau Publication, ou <kbd>Ctrl</kbd> + <kbd>S</kbd>. Un message confirme l’enregistrement.</li>
<li><b>Brouillon de secours</b> : ce que vous tapez est conservé dans votre navigateur tant que ce n’est pas enregistré. En cas de coupure ou de fermeture d’onglet, la fiche propose « Le récupérer » à la réouverture.</li>
<li><b>Fiche déjà ouverte</b> : un bandeau dit qui la modifie ; vous la consultez en lecture seule (« Prendre la main » si besoin). Et si un collègue a enregistré la même fiche pendant que vous la modifiiez, vous êtes prévenu au lieu d’écraser son travail.</li>
<li><b>Historique</b> : chaque enregistrement est une version, consultable dans l’onglet Historique.</li>
</ul>
[[astuce|<p>Les champs obligatoires portent une étoile <b>*</b>. Si un champ est mal rempli (une date illisible, par exemple), l’enregistrement est refusé et le champ est signalé en rouge.</p>]]
HTML],
        ['id' => 'ordre', 'title' => 'Changer l’ordre d’une liste (glisser-déposer)', 'html' => <<<'HTML'
<p>Partout où l’ordre compte — fiches d’une décennie ou d’une saison, sous-rubriques, composition d’un match, galerie, slider, messages du bandeau, cartes de l’album, questions du quiz, calendrier des 100 moments… — chaque élément porte la même commande, à droite :</p>
<ul>
<li><b>↑</b> : monter d’un cran ; <b>↓</b> : descendre d’un cran (grisées en début et en fin de liste) ;</li>
<li>au milieu, l’icône jaune <b>quatre flèches</b> : attrapez-la à la souris (ou au doigt sur tablette) et glissez l’élément aussi loin que vous voulez. Un <b>trait jaune</b> montre exactement où il sera déposé, avec sa future position ; la liste défile toute seule quand vous approchez du bord. <kbd>Échap</kbd> annule.</li>
<li>dans les listes numérotées, <b>cliquez sur le numéro</b> et tapez directement la position voulue (« 12 » + <kbd>Entrée</kbd>) : pratique pour envoyer un match tout en haut d’une liste de 500.</li>
</ul>
[[img:glisser-deposer.webp|Glisser-déposer en cours : la fiche suit la souris, le trait jaune et « Position 2 » montrent où elle sera déposée]]
[[astuce|<p>Au clavier : placez-vous sur l’icône quatre flèches (touche <kbd>Tab</kbd>) puis <kbd>↑</kbd> / <kbd>↓</kbd> pour déplacer, <kbd>Début</kbd> / <kbd>Fin</kbd> pour aller en tête ou en queue, <kbd>Entrée</kbd> pour taper une position.</p>]]
[[attention|<p>Un nouvel ordre n’est pris en compte qu’après <b>Enregistrer</b>.</p>]]
HTML],
        ['id' => 'profil', 'title' => 'Votre profil', 'html' => <<<'HTML'
<p>Menu en haut à droite › <b>Mon profil</b> : votre nom (affiché dans le journal et l’historique), votre mot de passe et vos dernières actions.</p>
[[ecran:/admin/profil|Ouvrir mon profil]]
HTML],
    ],
];
