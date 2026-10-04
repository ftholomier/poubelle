<?php
return [
    'slug' => 'faq',
    'title' => 'Comment faire pour… ?',
    'summary' => 'Les réponses courtes aux questions les plus fréquentes, avec le chemin à suivre.',
    'sections' => [
        ['id' => 'corriger-score', 'title' => '… corriger le score d’un match', 'html' => <<<'HTML'
<p>Contenus › Matchs › ouvrez le match (ou <kbd>Ctrl</kbd> + <kbd>K</kbd> et tapez l’adversaire) › onglet <b>Infos</b> › bloc Score › Enregistrer. Les buteurs de la composition doivent correspondre : le contrôle qualité vous le signale. Indiquez la source dans la note de version.</p>
HTML],
        ['id' => 'joueur-compo', 'title' => '… ajouter un joueur oublié dans une composition', 'html' => <<<'HTML'
<p>Onglet <b>Compo &amp; événements</b> › « Ajouter un joueur » en bas de la composition › remplissez la ligne › déplacez-la avec ↑ / ↓ ou l’icône quatre flèches › Enregistrer. Sa fiche et ses statistiques sont mises à jour seules.</p>
HTML],
        ['id' => 'relier-nom', 'title' => '… relier un nom mal orthographié à la bonne fiche', 'html' => <<<'HTML'
<p>Ouvrez la fiche du joueur › onglet Identité › <b>Autres graphies dans les compositions</b> › ajoutez la graphie (« CAMARA Razza ») › Enregistrer. Tous les matchs où le nom est écrit ainsi rejoignent la fiche. Pour un seul match, choisissez le bon joueur dans la ligne de composition.</p>
HTML],
        ['id' => 'creer-joueur', 'title' => '… créer la fiche d’un joueur cité sans fiche', 'html' => <<<'HTML'
<p>Dans la composition, cliquez la pastille rose <b>+</b> de la ligne, ou Qualité › Liens joueurs › « Créer la fiche » : la fiche s’ouvre avec son nom déjà rempli.</p>
HTML],
        ['id' => 'publier-plus-tard', 'title' => '… publier une fiche à une date précise', 'html' => <<<'HTML'
<p>Panneau Publication › statut <b>Planifié</b> › choisissez la date et l’heure › Enregistrer. La fiche paraît seule à l’heure dite ; elle figure dans « Publications programmées » du tableau de bord.</p>
HTML],
        ['id' => 'ancienne-version', 'title' => '… revenir à une ancienne version', 'html' => <<<'HTML'
<p>Onglet <b>Historique</b> de la fiche › ouvrez la version › comparez › un administrateur clique « Restaurer cette version ». La version actuelle reste dans l’historique.</p>
HTML],
        ['id' => 'annuler-suppression', 'title' => '… récupérer une fiche supprimée', 'html' => <<<'HTML'
<p>Sous une liste de fiches › <b>Voir la corbeille</b> › « Sortir de la corbeille ». Elle revient avec son statut précédent… à republier si besoin.</p>
HTML],
        ['id' => 'changer-adresse', 'title' => '… changer l’adresse (URL) d’une page', 'html' => <<<'HTML'
<p>Onglet <b>Classement &amp; SEO</b> › Adresse de la page › Enregistrer. L’ancienne adresse est redirigée automatiquement vers la nouvelle.</p>
HTML],
        ['id' => 'photo-match', 'title' => '… ajouter des photos à un match', 'html' => <<<'HTML'
<p>Onglet <b>Médias</b> › Galerie › ajoutez depuis la médiathèque ou envoyez de nouveaux fichiers (crédit demandé) › légendes › ordre par glisser-déposer › Enregistrer. Choisissez aussi l’image à la une.</p>
HTML],
        ['id' => 'remplacer-photo', 'title' => '… remplacer une photo par un meilleur scan', 'html' => <<<'HTML'
<p>Médiathèque › cliquez la photo › <b>Remplacer le fichier…</b>. Toutes les fiches qui l’utilisent affichent aussitôt le nouveau fichier.</p>
HTML],
        ['id' => 'crediter', 'title' => '… créditer beaucoup de photos d’un coup', 'html' => <<<'HTML'
<p>Médiathèque › filtre « Sans crédit » (ou une recherche) › cochez les photos › saisissez le crédit commun › Enregistrer.</p>
HTML],
        ['id' => 'a-la-une', 'title' => '… mettre une fiche dans le slider de l’accueil', 'html' => <<<'HTML'
<p>Onglet <b>Classement &amp; SEO</b> › cochez <b>À la une</b> et vérifiez qu’elle a une image à la une d’au moins 1 200 × 600 pixels : le tirage au hasard écarte les photos plus petites, floues en plein écran (un message sous la case le signale ; un plus grand scan, par Médiathèque › « Remplacer le fichier… », règle la question). Pour un slider choisi à la main : Éditorial › Accueil &amp; bandeau › Grand slider.</p>
HTML],
        ['id' => 'ordre-mosaique', 'title' => '… changer l’ordre des fiches d’une rubrique', 'html' => <<<'HTML'
<p>Éditorial › Rubriques &amp; menus › ouvrez la rubrique (par exemple « Années 90 ») › « Ordre d’affichage sur le site » : <b>Ordre manuel</b> › déplacez les fiches (↑ / ↓, icône quatre flèches ou clic sur le numéro pour taper la position) › Enregistrer.</p>
HTML],
        ['id' => 'bandeau', 'title' => '… ajouter un message au bandeau défilant', 'html' => <<<'HTML'
<p>Éditorial › Accueil &amp; bandeau › Bandeau « En direct du musée » › « Ajouter un message » (étiquette, message, lien) › « Traduire en anglais » › Enregistrer.</p>
HTML],
        ['id' => 'maintenance', 'title' => '… fermer le site pendant une opération', 'html' => <<<'HTML'
<p>Éditorial › <b>Page d’attente</b> › cochez « Activer la page d’attente » › texte (et compte à rebours si vous voulez) › Enregistrer. Décochez pour rouvrir. Pendant ce temps, l’équipe connectée voit le site normalement (bandeau jaune) et rien n’est indexé par Google.</p>
HTML],
        ['id' => 'contribution', 'title' => '… traiter une contribution d’un visiteur', 'html' => <<<'HTML'
<p>Communauté › Contributions › ouvrez-la › publiez les fichiers (médiathèque, fiche ou objet) › « ✓ Valider », « ? Demander une précision » ou « Refuser ».</p>
HTML],
        ['id' => 'repondre', 'title' => '… répondre à un message reçu par le site', 'html' => <<<'HTML'
<p>Communauté › Messages › ouvrez le message › <b>Répondre par e-mail</b> › puis statut « Traité ».</p>
HTML],
        ['id' => 'inviter', 'title' => '… inviter un nouvel historien', 'html' => <<<'HTML'
<p>(Administrateur) Système › Utilisateurs › <b>Inviter une personne</b> : nom, e-mail, niveau « Utilisateur » › Inviter. Si l’e-mail n’arrive pas, copiez le lien d’invitation et envoyez-le vous-même.</p>
HTML],
        ['id' => 'quiz', 'title' => '… ajouter une question au quiz', 'html' => <<<'HTML'
<p><b>+ Nouveau › Question de quiz</b> (ou Interactif › Quiz) › question, quatre réponses, bonne réponse, « Le saviez-vous ? » › « Affichée dans le quiz » › Enregistrer.</p>
HTML],
        ['id' => 'video', 'title' => '… ajouter une vidéo à une fiche', 'html' => <<<'HTML'
<p>Onglet <b>Médias</b> › Vidéos › « Ajouter une vidéo » › collez le lien YouTube, Dailymotion, Vimeo ou Rutube › titre › Enregistrer.</p>
HTML],
        ['id' => 'adversaire', 'title' => '… corriger le nom ou le logo d’un adversaire', 'html' => <<<'HTML'
<p>Contenus › Saisons, adversaires, lieux › onglet Adversaires › cherchez le club › nom, variantes, ville, logo › Enregistrer. Toutes ses fiches et son face-à-face suivent.</p>
HTML],
        ['id' => 'carte', 'title' => '… placer un joueur sur la carte des origines', 'html' => <<<'HTML'
<p>Fiche du joueur › onglet Identité › bloc Naissance › ville (et pays) › Enregistrer. La position est trouvée automatiquement ; corrigez-la dans Saisons, adversaires, lieux › Lieux de naissance si besoin.</p>
HTML],
        ['id' => 'a-completer', 'title' => '… trouver les fiches à compléter', 'html' => <<<'HTML'
<p>Pilotage › <b>Qualité</b> (onglets) et le bloc <b>À faire</b> du tableau de bord ; dans la médiathèque, les filtres « Sans crédit », « Sans légende », « Droits à préciser » ; dans les personnes, le filtre « Sans lieu de naissance ».</p>
HTML],
        ['id' => 'controler', 'title' => '… vérifier qu’une séance de saisie n’a rien cassé', 'html' => <<<'HTML'
<p>Pilotage › <b>Qualité</b> › <b>Contrôler maintenant</b> : toutes les vérifications sont refaites et les anomalies apparues depuis le contrôle précédent s’affichent, marquées « Nouveau ».</p>
HTML],
        ['id' => 'anglais', 'title' => '… traduire une fiche en anglais', 'html' => <<<'HTML'
<p>Onglet <b>Version EN</b> › « Traduire avec Gemini » › relisez › Enregistrer. Ou en série : Système › Traductions EN › onglet Fiches.</p>
HTML],
        ['id' => 'orthographe', 'title' => '… corriger les fautes d’orthographe d’une fiche', 'html' => <<<'HTML'
<p>Panneau <b>Orthographe</b> de la fiche › <b>Vérifier l’orthographe</b> › « Corriger » sur chaque proposition (ou « Tout corriger ») › <b>Enregistrer</b>. Les fiches à reprendre sont listées dans Qualité › Orthographe. Voir [[aide:fiches#orthographe|Vérifier l’orthographe]].</p>
HTML],
        ['id' => 'dictionnaire', 'title' => '… empêcher le correcteur de corriger un nom', 'html' => <<<'HTML'
<p>Dans le correcteur, bouton <b>+ Dictionnaire</b> sur la proposition ; ou Qualité › Orthographe › <b>Dictionnaire du musée</b> › « Ajouter : mot » › Enregistrer. Pour une seule fiche, « Ignorer » suffit.</p>
HTML],
        ['id' => 'audio', 'title' => '… changer ce que dit le bouton « Écouter » d’une fiche', 'html' => <<<'HTML'
<p>Éditeur de la fiche › carte <b>Écouter la fiche</b> › modifiez le texte › <b>Garder ce texte</b>. Pour une voix naturelle : <b>Voix IA</b>. Tout le musée d’un coup : Système › Fiches audio (administrateurs). Voir [[aide:administration#audio|Fiches audio]].</p>
HTML],
        ['id' => 'couts', 'title' => '… savoir ce que coûte l’IA et me faire rembourser', 'admin' => true, 'html' => <<<'HTML'
<p>Système › <b>Coûts IA</b> (administrateurs) : dépense du jour et du mois en direct. Le mois terminé : <b>Relevé PDF</b> et <b>CSV</b> à remettre à l’association, puis « Noter le remboursement » une fois payé. Budget mensuel : Réglages › Coûts IA. Voir [[aide:administration#couts|Coûts de l’IA et remboursement]].</p>
HTML],
        ['id' => 'verrou', 'title' => '… modifier une fiche qu’une collègue a déjà ouverte', 'html' => <<<'HTML'
<p>Le bandeau en haut de la fiche donne son nom et l’heure. Attendez qu’elle ferme la fiche (le bandeau vous le dira), ou cliquez sur <b>Prendre la main</b> : elle est prévenue et ne peut plus enregistrer. Voir [[aide:fiches#verrou|Deux personnes sur la même fiche]].</p>
HTML],
        ['id' => 'retro-direct', 'title' => '… rejouer un grand match en direct le jour de son anniversaire', 'html' => <<<'HTML'
<p>Interactif › <b>Rétro-Direct</b> › « Anniversaires à venir » › <b>Programmer à 20 h</b>, ou « Programmer un match » pour choisir le match, la date et l’heure. Le jour J, à l’heure dite, le site déroule le match minute par minute. Voir [[aide:interactif#retro-direct|Le Rétro-Direct]].</p>
HTML],
        ['id' => 'temoignage', 'title' => '… publier le souvenir d’un supporter sur la fiche d’un match', 'html' => <<<'HTML'
<p>Communauté › <b>Contributions</b> › le témoignage › choisissez la fiche du match, relisez le texte publié et la signature, « Publier ce souvenir » coché › <b>✓ Valider</b>. Il apparaît dans le bloc « Ils y étaient » de la fiche. Voir [[aide:communaute#contributions|Les contributions]].</p>
HTML],
        ['id' => 'kit', 'title' => '… imprimer le kit souvenirs du mois pour des anciens supporters', 'html' => <<<'HTML'
<p>Sur le site : Interactif › Participer › <b>Kit souvenirs</b> › « Télécharger le kit » (PDF de 4 pages A4). Pour changer le match ou ajouter un mot d’introduction : Interactif › <b>Kit souvenirs</b> du back-office. Voir [[aide:interactif#souvenirs|Le kit souvenirs]].</p>
HTML],
        ['id' => 'chiffres', 'title' => '… comprendre (ou corriger) un chiffre de la page « Les chiffres du FCSM »', 'html' => <<<'HTML'
<p>Chaque chiffre porte un badge qui dit d’où il vient (Carrières, Matchs racontés, Récits des matchs, Fiches des Lions) et mène à la fiche concernée : corrigez cette fiche (tableau de statistiques, composition, temps forts, date de naissance) et la page se recalcule toute seule. Un joueur absent des records de carrière a souvent un tableau de statistiques recopié d’un autre joueur (alerte dans Pilotage › Qualité). Voir [[aide:site-public#chiffres|Les chiffres du FCSM]].</p>
HTML],
        ['id' => 'pdf', 'title' => '… obtenir le PDF d’une fiche', 'html' => <<<'HTML'
<p>Sur le site, bouton <b>Télécharger en PDF</b> sous le titre de la fiche (ou de la saison, du face-à-face, du bilan, des records). Dans le back-office : panneau Publication de la fiche › <b>Télécharger le PDF</b>. Le document reprend la dernière version enregistrée. Voir <a href="/admin/aide/site-public#pdf">Télécharger en PDF</a>.</p>
HTML],
        ['id' => 'web', 'title' => '… vérifier ou compléter une fiche avec des sources sur Internet', 'html' => <<<'HTML'
<p>Panneau <b>Recherche sur le web</b> de la fiche › <b>Chercher sur le web</b> : l’IA propose divergences, compléments et pistes, chacune avec ses pages. Ouvrez la source, vérifiez, reportez vous-même (<b>Copier</b> aide), puis enregistrez avec une note de version qui cite la source. Voir [[aide:fiches#recherche-web|Chercher sur le web]].</p>
HTML],
        ['id' => 'meme-match', 'title' => '… répondre à « Est-ce bien le même match ? »', 'html' => <<<'HTML'
<p>Le back-office pose la question quand l’adversaire ou la date change sur une fiche déjà remplie. Autre match : <b>Annuler</b>, puis <b>+ Nouveau › Fiche match</b>. Erreur de saisie sur ce même match : <b>Même match : enregistrer</b>. Voir [[aide:matchs#creer|Créer la fiche]].</p>
HTML],
        ['id' => 'favori', 'title' => '… avoir mes écrans préférés à un clic', 'html' => <<<'HTML'
<p>Bande du haut › <b>+ Ajouter un favori</b> › <b>☆ Ajouter</b> sur « Cette page » ou sur un écran de la liste. Renommer, déplacer, retirer : dans la même fenêtre. Voir [[aide:prise-en-main#favoris|Vos favoris]].</p>
HTML],
        ['id' => 'mise-a-jour', 'title' => '… installer la dernière version du site', 'admin' => true, 'html' => <<<'HTML'
<p>Système › <b>Mises à jour</b> (administrateurs) › <b>Appliquer la mise à jour</b> : seuls les fichiers du code qui ont changé sont remplacés, jamais les fiches, photos ou réglages, et un retour arrière reste possible. Voir [[aide:administration#majs|Mises à jour du site]].</p>
HTML],
        ['id' => 'perdu', 'title' => '… retrouver un texte non enregistré', 'html' => <<<'HTML'
<p>Rouvrez la fiche sur le même ordinateur et le même navigateur : un bandeau propose « Le récupérer ». Sinon, l’onglet Historique contient la dernière version enregistrée.</p>
HTML],
    ],
];
