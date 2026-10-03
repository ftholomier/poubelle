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
<li><b>Modification simultanée</b> : si un collègue a enregistré la même fiche pendant que vous la modifiiez, vous êtes prévenu au lieu d’écraser son travail.</li>
<li><b>Historique</b> : chaque enregistrement est une version, consultable dans l’onglet Historique.</li>
</ul>
[[astuce|<p>Les champs obligatoires portent une étoile <b>*</b>. Si un champ est mal rempli (une date illisible, par exemple), l’enregistrement est refusé et le champ est signalé en rouge.</p>]]
HTML],
        ['id' => 'profil', 'title' => 'Votre profil', 'html' => <<<'HTML'
<p>Menu en haut à droite › <b>Mon profil</b> : votre nom (affiché dans le journal et l’historique), votre mot de passe et vos dernières actions.</p>
[[ecran:/admin/profil|Ouvrir mon profil]]
HTML],
    ],
];
