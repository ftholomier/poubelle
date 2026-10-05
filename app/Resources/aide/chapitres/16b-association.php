<?php
return [
    'slug' => 'association',
    'title' => 'Le site de l’association (www)',
    'summary' => 'Le site vitrine de Sochaux Rétro sur www : page d’attente, contenus, actualités, agenda, adhésions, bénévoles, ouverture au public. Réservé aux administrateurs.',
    'admin' => true,
    'sections' => [
        ['id' => 'principe', 'title' => 'Deux sites, un seul back-office', 'admin' => true, 'html' => <<<'HTML'
<p>La même installation sert deux adresses : le <b>musée</b> (musee.fcsochauxretro.com) et le <b>site de l’association</b> (www.fcsochauxretro.com). Le site de l’association présente Sochaux Rétro, ses actions, son agenda, et permet d’<b>adhérer</b>, de <b>devenir bénévole</b>, de <b>faire un don</b> (au musée) ou de nous écrire.</p>
<ul>
<li>Tout se gère ici, dans le pavé <b>Site de l’association</b> du menu, visible des seuls administrateurs.</li>
<li>Les anciennes adresses de www (l’ancien site WordPress) mènent automatiquement à la bonne page du musée.</li>
<li>L’adresse sans « www » (fcsochauxretro.com) redirige vers www.</li>
<li>Les mises à jour depuis GitHub (Système › Mises à jour) concernent les deux sites à la fois.</li>
</ul>
[[attention|<p>Pour que www affiche ce site, le domaine doit mener au <b>même dossier</b> que le musée (cPanel › Domaines). Tant que ce n’est pas fait, utilisez l’<b>aperçu</b> (bouton « Aperçu complet » du tableau de bord).</p>]]
HTML],
        ['id' => 'ouverture', 'title' => 'Aperçu et ouverture au public', 'admin' => true, 'html' => <<<'HTML'
<p>Le site est <b>fermé au départ</b> : les visiteurs voient une page d’attente et rien n’est indexé par Google. Vous le voyez en entier dans l’<b>aperçu</b> (adresse /apercu-association/ du musée), y compris les contenus « à vérifier », signalés par une étiquette rouge.</p>
[[img:association-tableau.webp|Le tableau de bord du site : (1) site fermé ou ouvert, (2) aperçu complet, (3) ouvrir le site au public, (4) adhésions, bénévolat, messages et audience, (5) la liste « À vérifier », (6) le pavé dans le menu]]
<ol>
<li>Parcourez la liste <b>À vérifier</b> du tableau de bord : points rouges d’abord (tarifs, e-mail de réception, mentions légales), puis les jaunes.</li>
<li>Vérifiez le rendu dans l’aperçu, sur ordinateur et sur téléphone.</li>
<li>Cliquez <b>Ouvrir le site au public</b>. Le bouton devient « Fermer le site » pour revenir à la page d’attente à tout moment.</li>
</ol>
<p>Ce que montre la page d’attente se règle dans <b>Page d’attente</b> (ci-dessous) ; l’adresse du site, l’e-mail de réception, HelloAsso et le référencement, dans <b>Réglages du site</b>.</p>
HTML],
        ['id' => 'attente', 'title' => 'La page d’attente du site', 'admin' => true, 'html' => <<<'HTML'
<p>Tant que le site est fermé, les visiteurs de www.fcsochauxretro.com ne voient qu’une <b>page d’attente</b> : celle du site de l’association, différente de celle du musée (Éditorial › Page d’attente), et réglée à part dans <b>Site de l’association › Page d’attente</b>.</p>
[[img:association-attente.webp|L’écran Page d’attente : (1) état et ouverture du site, (2) aperçu de la page d’attente, (3) texte et photo, (4) la liste « Ce qui vous attend », (5) les blocs affichés, (6) le compte à rebours]]
<ul>
<li><b>Texte et photo</b> : surtitre, titre, texte, photo (à gauche ; en haut sur téléphone), sa légende, son <b>crédit</b> (affiché sous la légende, « Photo : … » ; laissé vide, c’est celui de la photo dans la médiathèque) et la pastille « Bientôt » posée dessus.</li>
<li><b>Ce qui vous attend</b> : ce que le site proposera, quatre éléments au plus ; liste vide, bloc masqué.</li>
<li><b>Blocs de la page</b> : inscription à la lettre « Ce jour-là » (elle fonctionne site fermé), encart du musée en ligne (avec le bouton « Visiter le musée » dès qu’il est ouvert, et le teaser vidéo si vous le cochez), e-mail de l’association, réseaux sociaux.</li>
<li><b>Compte à rebours</b> : jusqu’à l’ouverture prévue ; il disparaît de lui-même une fois la date passée.</li>
</ul>
<p>Enregistrez, puis contrôlez le résultat avec <b>Aperçu de la page d’attente</b>, sur ordinateur et sur téléphone. Elle n’est jamais indexée par les moteurs de recherche, et les pages légales restent accessibles. Elle disparaît dès que vous cliquez <b>Ouvrir le site au public</b>, et revient si vous refermez le site.</p>
[[astuce|<p>« Revenir à la page de départ » rétablit la page livrée avec le site ; votre version reste dans l’historique en bas de l’écran.</p>]]
HTML],
        ['id' => 'contenus', 'title' => 'Modifier les contenus', 'admin' => true, 'html' => <<<'HTML'
<p><b>Contenus</b> regroupe, par onglets, tout ce qui s’affiche sur le site :</p>
<ul>
<li><b>Pages</b> : les textes de chaque page (accueil, qui sommes-nous, adhérer…).</li>
<li><b>Actions</b> : une page par action, dans l’ordre du menu « Nos actions ».</li>
<li><b>Actualités</b> et <b>Agenda</b> : datés ; un brouillon reste invisible. Les Rétro-Direct programmés au musée et le centenaire s’ajoutent seuls à l’agenda, qui s’exporte aussi au format iCal.</li>
<li><b>Équipe</b> : l’équipe de Sochaux Rétro, présentée en <b>cartes à collectionner</b> façon Panini. Pour chaque membre : photo (avec son accord), nom, rôle, pôle, <b>sa mission</b>, <b>son anecdote</b> et quelques mots. Recto : photo, nom, rôle ; verso (au survol, ou au toucher sur mobile) : mission, quelques mots et anecdote. Les mêmes cartes s’affichent sur la page « L’équipe » de l’association et sur le musée (Supporters › L’équipe de Sochaux Rétro), chacune aux couleurs de son site. Un membre n’apparaît que si son nom est saisi ; les pôles décrivent les missions des bénévoles.</li>
<li><b>Partenaires</b>, <b>Presse</b> (revue de presse) et <b>Documents</b> (statuts, comptes rendus : « Déposer un fichier », puis Enregistrer).</li>
<li><b>Tarifs d’adhésion</b> : les formules, leurs montants, un montant libre « à partir de ».</li>
</ul>
[[img:association-equipe.webp|L’équipe en cartes à collectionner : sur le musée (en haut, une carte retournée) et sur le site de l’association (en bas)]]
[[img:association-contenus.webp|Les contenus, ici les actualités : (1) les onglets, (2) l’aperçu de la page, (3) enregistrer, (4) l’adresse de la page, (5) la case « À vérifier », (6) déplacer, dupliquer ou supprimer]]
<p>Liens dans les boutons : « /page/ » pour ce site, « musee:/page/ » pour le musée, « https://… » pour un autre site, « social:youtube » pour un réseau social réglé.</p>
[[astuce|<p>Les textes livrés au départ sont mis à jour avec le code tant que vous ne les avez pas modifiés. Dès votre premier enregistrement, ils sont à vous ; « Revenir au contenu de départ » les rétablit (votre version reste dans l’historique).</p>]]
<p><b>À vérifier</b> : ce qui n’a pas pu être connu en préparant le site (dates d’événements, histoire de l’association, tarifs…) est marqué « à vérifier ». Une actualité ou un événement à vérifier reste <b>invisible du public</b> tant que la case n’est pas décochée ; aucun nom de personne, de partenaire ni article de presse n’a été inventé.</p>
HTML],
        ['id' => 'adhesions', 'title' => 'Les adhésions', 'admin' => true, 'html' => <<<'HTML'
<p>Sur la page <b>Adhérer</b>, le visiteur choisit sa formule et règle :</p>
<ul>
<li><b>en ligne</b>, par carte bancaire ou PayPal, avec les clés réglées pour les dons (Réglages › Dons) ; l’adhésion passe « Payée » quand le prestataire confirme le paiement, et un e-mail de bienvenue part ;</li>
<li><b>par chèque</b> ou en main propre : l’adhésion attend son règlement (« Règlement attendu ») ; à réception, ouvrez-la et cliquez <b>Marquer comme payée</b> ;</li>
<li><b>sur papier</b> : le bulletin à imprimer est sur le site ; saisissez ces adhésions avec « + Adhésion papier ».</li>
</ul>
<p>La liste se filtre par année et par statut ; <b>Exporter (CSV)</b> donne le fichier des adhérents (tableur). Si l’association utilise HelloAsso, indiquez sa page dans les réglages : un bouton s’ajoute à la page Adhérer.</p>
[[attention|<p>Les coordonnées des adhérents sont effacées automatiquement trois ans après leur année d’adhésion (nom, montant et paiements restent pour la comptabilité dix ans), comme l’annonce la page Confidentialité du site.</p>]]
HTML],
        ['id' => 'benevoles', 'title' => 'Bénévoles et messages', 'admin' => true, 'html' => <<<'HTML'
<p><b>Bénévoles</b> : chaque proposition reçue par le site (pôles choisis, compétences, disponibilité, message). Passez-la à « Contacté », puis « Bénévole actif » ou « Classée » ; une note interne garde le suivi. Les propositions restées sans suite sont effacées après deux ans.</p>
<p>Les <b>messages</b> du formulaire de contact du site arrivent dans Communauté › Messages, avec l’étiquette « Association » : on y répond comme aux messages du musée. Un e-mail prévient aussi l’adresse de réception réglée.</p>
<p>La <b>newsletter</b> proposée sur le site est celle du musée, « Ce jour-là ».</p>
HTML],
    ],
];
