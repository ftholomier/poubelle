<?php
return [
    'slug' => 'prise-en-main',
    'title' => 'Prise en main',
    'summary' => 'Se connecter, se repérer dans le menu, rechercher, créer, enregistrer : les gestes de base.',
    'sections' => [
        ['id' => 'connexion', 'title' => 'Se connecter', 'html' => <<<'HTML'
<p>Un administrateur vous invite depuis l’écran Utilisateurs : vous recevez un e-mail avec un lien pour choisir votre mot de passe (au moins 10 caractères). Ensuite, connectez-vous à l’adresse <code>/admin</code> avec votre e-mail et ce mot de passe.</p>
[[img:connexion.webp|L’écran de connexion, avec le lien « Mot de passe oublié » et, en fond, le petit match]]
<ul>
<li><b>En fond, un petit match se joue</b>, pour le plaisir : le FCSM fait tourner, marque, et le tableau d’affichage suit. Il ne gêne pas la saisie ; il reste immobile si votre appareil demande moins d’animations.</li>
<li><b>Mot de passe oublié</b> : lien sous le formulaire ; un e-mail vous permet d’en choisir un nouveau.</li>
<li>Après plusieurs essais erronés, la connexion est bloquée quelques minutes (protection contre les intrusions).</li>
<li><b>Se déconnecter</b> : menu en haut à droite (vos initiales).</li>
<li><b>Déconnexion automatique</b> : après <b>30 minutes sans activité</b> (ni clavier, ni souris, dans aucun onglet du back-office), vous êtes déconnecté. Deux minutes avant, une fenêtre « Toujours là ? » propose <b>Rester connecté</b>. Écrire un long texte sans enregistrer compte bien comme une activité. Après reconnexion, vous revenez sur la même page ; une fiche en cours de modification a gardé son brouillon sur l’ordinateur, proposé à sa réouverture.</li>
</ul>
HTML],
        ['id' => 'tableau-de-bord', 'title' => 'Le tableau de bord', 'html' => <<<'HTML'
<p>C’est la page d’arrivée. Elle résume l’état du musée et ce qui vous attend.</p>
[[img:tableau-de-bord.webp|Le tableau de bord : (1) le menu, (2) la recherche globale, (3) « + Nouveau », (4) l’aide de l’écran, (5) votre compte, (6) la liste « À faire », (7) vos favoris]]
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
<p>Le menu de gauche est rangé en grandes parties (Pilotage, Contenus, Éditorial…). L’interrupteur <b>Menu compact</b>, en haut du menu, n’affiche que ces parties : leurs rubriques s’ouvrent au survol de la souris. Votre choix est retenu sur cet ordinateur ; sur téléphone, le menu reste complet.</p>
<p>Pour ne plus chercher dans le menu de gauche, mettez vos écrans les plus utilisés dans la bande du haut, sous le titre : un clic sur un favori ouvre l’écran.</p>
<ol>
<li>Cliquez sur <b>+ Ajouter un favori</b>.</li>
<li>La fenêtre propose d’abord <b>Cette page</b> (l’écran affiché, par exemple une fiche que vous reprenez souvent, ou une liste filtrée), puis tous les écrans du menu, les créations (« Fiche match », « Personne »…) et, pour les administrateurs, chaque groupe des Réglages. Tapez quelques lettres dans « Chercher un écran à ajouter » pour filtrer.</li>
<li><b>☆ Ajouter</b> le met dans la bande ; <b>★ Ajouté</b> l’en retire.</li>
</ol>
<p>En haut de la même fenêtre, vos favoris se <b>renomment</b> (cliquez dans le nom, tapez, <kbd>Entrée</kbd>), se <b>déplacent</b> (↑ ↓) et se <b>retirent</b> (✕). Douze au plus. Chacun a les siens : vos favoris ne changent rien pour vos collègues.</p>
[[img:favoris.webp|La fenêtre « + Ajouter un favori » : (1) vos favoris, renommables, (2) monter, descendre, retirer, (3) chercher un écran, (4) ajouter la page affichée ou un autre écran]]
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
        ['id' => 'tickets', 'title' => 'Tickets : signaler un bug, demander une amélioration', 'html' => <<<'HTML'
<p>Le bouton <b>🎫 Ticket</b>, tout en haut du menu de gauche (avant Pilotage), sert à prévenir l’administrateur : <b>un bug</b>, <b>une idée d’amélioration</b> ou <b>une question</b> sur le musée. L’écran que vous regardiez est noté automatiquement dans le ticket. L’écran Tickets est en deux colonnes : <b>à gauche, la liste et le suivi</b> des tickets, <b>à droite, le formulaire « Nouveau ticket »</b>, toujours visible.</p>
[[img:tickets.webp|L’écran Tickets : à gauche la liste et le suivi, à droite le formulaire « Nouveau ticket »]]
<ul>
<li><b>Titre</b> : en quelques mots, ce qui ne va pas ou ce que vous voulez.</li>
<li><b>Type</b> : 🐞 Correction de bug, ✨ Demande d’amélioration ou ❓ Question. <b>Priorité</b> : normale, urgente ou bloquante (l’administrateur peut l’ajuster).</li>
<li><b>Votre demande</b> : ce qui se passe, où, et ce que vous attendiez. Pour un bug, ce que vous avez fait juste avant.</li>
<li><b>Prompt pour l’IA</b> (facultatif) : la consigne que vous donneriez vous-même à l’IA qui développe le musée, comme si vous lui parliez (« Sur la fiche joueur, ajoute sous la photo… »). C’est vous qui savez exactement ce que vous voulez : plus c’est précis, mieux c’est. L’administrateur la copie d’un clic.</li>
<li><b>Pièces jointes</b> (facultatif) : cliquez sur la zone 📎 pour choisir des fichiers, glissez-les dessus, ou <b>collez une capture d’écran</b> (Ctrl+V, ou ⌘+V sur Mac) n’importe où dans le formulaire, même dans le texte de la demande. Chaque fichier s’affiche avant l’envoi, avec une croix pour le retirer. Images, PDF, textes, Word, Excel, LibreOffice, ZIP et vidéos ; 10 fichiers au plus par envoi. Une capture trop lourde pour le serveur est recompressée toute seule. Les pièces jointes ne sont visibles que par l’équipe connectée ; vous pouvez en ajouter (ou en retirer) en modifiant le ticket, et en joindre à une réponse, même sans texte.</li>
</ul>
<p><b>Statuts</b> : <b>Nouveau</b> → <b>Pris en compte</b> → <b>En cours</b> → <b>À vérifier</b> (c’est en ligne : contrôlez et répondez « c’est bon ») → <b>Terminé</b>, ou <b>Refusé / sans suite</b> avec une explication. Seul l’administrateur change le statut et la priorité.</p>
<ul>
<li>Toute l’équipe voit tous les tickets du musée : regardez s’il n’existe pas déjà avant d’en ouvrir un. Filtres : ouverts, mes tickets, par statut, type, personne, ou recherche.</li>
<li>Sous chaque ticket, un <b>fil d’échanges</b> pour poser une question ou répondre, et l’<b>historique</b> de ses changements.</li>
<li>Sur le <b>tableau de bord</b>, le bloc <b>« Mes tickets en cours »</b> liste vos seuls tickets encore ouverts, avec leur statut (point jaune : du nouveau).</li>
<li>Vous pouvez modifier votre ticket tant qu’il est « Nouveau » ; ensuite, ajoutez une réponse.</li>
<li><b>E-mails</b> : l’administrateur est prévenu de chaque nouveau ticket et de chaque réponse ; vous êtes prévenu à chaque réponse ou changement de statut de votre ticket. Une pastille sur le bouton Ticket compte les tickets qui ont bougé depuis votre dernière visite.</li>
</ul>
[[img:ticket-detail.webp|Un ticket : la demande, le prompt pour l’IA, les échanges et, pour l’administrateur, le statut à mettre à jour]]
[[ecran:/admin/tickets|Ouvrir les tickets]]
HTML],
        ['id' => 'profil', 'title' => 'Votre profil', 'html' => <<<'HTML'
<p>Menu en haut à droite › <b>Mon profil</b> : votre nom (affiché dans le journal et l’historique), votre mot de passe et vos dernières actions.</p>
[[ecran:/admin/profil|Ouvrir mon profil]]
HTML],
    ],
];
