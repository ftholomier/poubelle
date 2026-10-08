<?php
return [
    'slug' => 'administration',
    'title' => 'Administration',
    'summary' => 'Pour les administrateurs : utilisateurs, réglages, assistant IA, fiches audio, coûts de l’IA, dons hors ligne et reçus fiscaux, sauvegardes, tâches, journal.',
    'sections' => [
        ['id' => 'statistiques', 'title' => 'Statistiques du musée', 'html' => <<<'HTML'
<p>Pilotage › <b>Statistiques</b> (administrateurs) : l’audience du musée. En haut, le <b>direct</b> : visiteurs en ligne (une page vue dans les 5 dernières minutes), pages vues minute par minute et ce qu’ils regardent, mis à jour toutes les 15 secondes. Puis, sur la période choisie (aujourd’hui, 7 ou 30 jours, mois, année, depuis le début, ou deux dates) : visiteurs, visites, pages vues, pages par visite, visites d’une seule page, part en anglais, chacun comparé à la période précédente ; un encadré <b>À retenir</b> ; la courbe de fréquentation, les heures et jours de visite, les appareils, d’où viennent les visiteurs, les décennies les plus consultées et les classements (joueurs, matchs, récits, rubriques, pages, jeux, recherches). <b>Rapport PDF</b> télécharge le tout pour la période. <b>Remise à zéro</b> (avant l’ouverture) : tapez REMETTRE A ZERO ; les anciennes données sont gardées de côté sur le serveur.</p>
<p>La mesure se fait sans cookie et sans garder d’adresse IP : un visiteur est reconnu par une empreinte anonyme qui change chaque jour (pas de bandeau de consentement nécessaire).</p>
HTML],
        ['id' => 'utilisateurs', 'title' => 'Utilisateurs et invitations', 'html' => <<<'HTML'
<p>Système › <b>Utilisateurs</b> (administrateurs) : <b>Inviter une personne</b> (nom, e-mail, niveau) ; elle reçoit un lien pour choisir son mot de passe, que l’on peut aussi copier et transmettre autrement. On peut renvoyer une invitation, changer le niveau ou désactiver un compte.</p>
[[img:utilisateurs.webp|L’équipe du back-office et les invitations]]
[[attention|<p>Le site garde toujours au moins un administrateur actif.</p>]]
HTML],
        ['id' => 'reglages', 'title' => 'Réglages', 'html' => <<<'HTML'
<p>Système › <b>Réglages</b> (administrateurs), en onglets : Général (nom, adresse du site, e-mail de contact, mot de passe d’accès avant lancement, « Masquer le site aux moteurs de recherche » même ouvert au public), Page d’attente, Accueil, Réseaux sociaux (et lien discret vers le site officiel du FCSM, sous le titre de l’en-tête), Pied de page (titre, phrase, deux boutons et leurs liens, accroche, ligne du bas, en français et en anglais), Assistant IA (clé Gemini et modèle), Traduction, Correcteur (vérification de fond, plafond d’appels à Gemini, typographie), Fiches audio (durée maximale, voix, ton, explications IA), Coûts IA (qui avance les frais, taux de change, budget mensuel), Dons (Stripe, PayPal, objectifs), E-mail (serveur d’envoi), Newsletter, Carte, Centenaire, Mentions légales, Sauvegardes, Cookies et RGPD.</p>
[[img:reglages.webp|Les réglages : les clés secrètes ne sont jamais réaffichées]]
[[astuce|<p>Les clés secrètes (API, mots de passe) sont chiffrées : laissez le champ vide pour garder la valeur enregistrée.</p>]]
HTML],
        ['id' => 'assistant', 'title' => 'L’assistant IA', 'html' => <<<'HTML'
<p>La bulle « Le guide du musée » répond aux visiteurs à partir des données du site (fiches, statistiques), grâce à Gemini. Réglages › Assistant IA : clé, modèle (liste chargée depuis la clé), nom, message d’accueil, limites quotidiennes. La même clé sert aux traductions et au correcteur d’orthographe.</p>
<p>Système › <b>Assistant IA</b> : questions posées par mois, avis des visiteurs (utile / pas utile), export, réindexation après de grosses modifications.</p>
[[img:assistant.webp|Le journal de l’assistant IA]]
HTML],
        ['id' => 'audio', 'title' => 'Fiches audio (écouter une fiche)', 'html' => <<<'HTML'
<p>Chaque fiche publiée est expliquée à voix haute sur le site (bouton « Écouter »), en entier, aussi longuement que son contenu le demande, sans dépasser la <b>durée maximale</b> réglée (3 minutes par défaut, Réglages › Fiches audio). <b>Gratuit par défaut</b> : le texte est tiré de la fiche et lu par la voix de l’appareil du visiteur. Rédigé par l’IA, il raconte la fiche comme un historien : une accroche, le décor, le récit en paragraphes (coulisses, anecdotes, hommes), une conclusion, avec uniquement les faits de la fiche.</p>
<p><b>Réécrire tous les textes</b> : Système › Fiches audio › carte <b>Réécrire les textes avec l’IA</b> (essai sur 20 fiches, puis toutes) : environ 1 € pour tout le musée avec un modèle Flash-Lite, sans voix IA (lus par la voix du navigateur, gratuite). Les textes écrits à la main ne sont jamais remplacés. Réglages › Fiches audio › <b>Modèle qui rédige les textes audio</b> : un modèle Flash (sans « Lite ») raconte mieux, pour quelques euros.</p>
<p><b>Fiche par fiche</b>, dans l’éditeur, la carte <b>Écouter la fiche</b> montre le texte lu :</p>
<ul>
<li>modifiez-le puis <b>Garder ce texte</b> (il ne sera plus jamais remplacé automatiquement), ou <b>Rédiger avec l’IA</b> (environ 0,05 centime) ;</li>
<li><b>Voix IA</b> enregistre une voix naturelle (environ 3 centimes pour 3 minutes) : les visiteurs l’entendent aussitôt ; <b>▶ Écouter</b> pour vérifier ;</li>
<li><b>Télécharger le MP3</b> : la voix IA de la fiche, au nom de la fiche (« titre-fr.mp3 ») ;</li>
<li><b>Revenir au résumé automatique</b>, <b>Supprimer la voix IA</b> : retour au gratuit.</li>
</ul>
[[img:fiche-audio.webp|La carte « Écouter » de l’éditeur : (1) le texte lu, (2) rédiger avec l’IA, (3) voix IA, (4) écouter]]
<p><b>Toutes les fiches</b> : Système › <b>Fiches audio</b>, carte <b>Toutes les fiches en voix IA</b>, estime le coût puis confie les fiches au <b>traitement groupé</b> de Google, à moitié prix (environ 40 € pour les 2 940 fiches en 3 minutes au plus ; le tarif de la voix double au 1er janvier 2027). Commencez par <b>Essayer d’abord sur 20 fiches</b> pour écouter le résultat. Google répond en quelques heures ; la tâche planifiée range les voix et chaque fiche bascule toute seule sur sa voix IA. Les pages de synthèse ne sont pas des fiches : elles ne sont pas comprises, elles se lancent à part (ci-dessous).</p>
[[img:audio.webp|Système › Fiches audio : voix enregistrées, estimation, traitements groupés]]
<p><b>Pages de synthèse</b> (face-à-face, saisons, bilans, records, chiffres) : l’IA les raconte aussi, en français et en anglais, à partir de leurs chiffres et des fiches de leurs grands matchs, puis la <b>voix IA</b> enregistre chaque récit. Chaque nuit, les récits manquants ou dont les chiffres ont changé sont rédigés puis enregistrés en traitement groupé (moins d’un euro pour les récits avec Flash-Lite, environ 15 à 20 € pour toutes les voix ; le tarif des voix double au 1er janvier 2027). En attendant, le récit automatique, gratuit et toujours exact, est lu par la voix de l’appareil. Carte <b>Pages de synthèse racontées par l’IA</b> : <b>essayez d’abord sur une page</b> (collez son adresse, par exemple <code>/face-a-face/nancy/</code> : le récit et sa voix sont faits tout de suite, pour quelques centimes ; écoutez-les sur la page), puis <b>Lancer pour toutes les pages</b>. Rien n’est dépensé avant ce lancement ; ensuite, la rédaction de nuit prend le relais, et <b>Rédiger et enregistrer maintenant</b> (ou <b>Tout refaire</b>) n’attend pas la nuit. Réglages › Fiches audio : la voix IA des pages se coupe à part.</p>
[[img:audio-pages.webp|Carte « Pages de synthèse racontées par l’IA » : essayer sur une page, puis lancer pour toutes les pages]]
<p><b>Télécharger les voix</b> (administrateurs) : Système › Fiches audio, carte <b>Télécharger les voix IA</b> : toutes les voix des fiches ou des pages de synthèse dans un ZIP, en français, en anglais ou les deux. Chaque MP3 porte le titre de la fiche ou de la page et sa langue ; « sommaire.csv » (s’ouvre dans un tableur) donne le titre, la durée et l’adresse sur le site. Seules les voix à jour sont fournies. Une voix seule : bouton ⤓ dans la liste « Dernières voix IA ».</p>
[[astuce|<p>Réglages › <b>Fiches audio</b> : durée maximale, choix de la voix (Charon, Gacrux, Sulafat…), rédaction des explications par l’IA lors du traitement groupé, et mise à jour de nuit des voix des fiches modifiées. Chaque dépense apparaît dans Coûts IA (usage « Fiches audio »).</p>]]
HTML],
        ['id' => 'reprise', 'title' => 'Reprise des années 1928-1969', 'admin' => true, 'html' => <<<'HTML'
<p>Système › <b>Reprise 1928-1969</b> fait entrer au musée les saisons, matchs, tournois, articles et portraits racontés sur fcsmstory.com (années 20 à 60). Trois boutons, dans l’ordre :</p>
<ol>
<li><b>Analyser</b> : le site est lu ; chaque saison est découpée en matchs (date, lieu, adversaire, compétition, score, composition, buteurs). Le plan s’affiche : rien n’est encore créé. Un match déjà au musée à la même date, ou un joueur déjà présent (même nom et un prénom commun), est écarté et n’est jamais modifié.</li>
<li><b>Essai sur 3 matchs</b> : trois fiches sont créées pour juger le style ; ouvrez-les depuis le tableau.</li>
<li><b>Lancer tout l’import</b> : la tâche planifiée traite un lot toutes les 5 minutes, on peut fermer la page ; « Mettre en pause » l’arrête.</li>
</ol>
<p>Chaque texte est <b>entièrement réécrit par Gemini</b> (ordre, tournures, style du musée), puis comparé à l’original : s’il reprend trop de suites de six mots, il est réécrit, puis écarté (« Trop proche »). Les faits sont repris tels quels, aucune image. Les fiches sont publiées dans les rubriques Années 20 à 60 (une saison absente est créée), avec une ligne « Sources ». Le texte d’une saison va dans sa rubrique, seulement si elle n’en a pas déjà un. En bas de page, les joueurs des compositions sans fiche au musée : créez-les si besoin, les matchs s’y relient d’eux-mêmes. Coût dans Coûts IA (« Reprise des années 1928-1969 »).</p>
<p><b>4. Importer les photos</b> : une fois les fiches créées, les coupures de presse et photos d’agence de fcsmstory.com sont ajoutées à la galerie de la bonne fiche (le match dont le récit les montre, ou celui de la veille du journal ; l’article, le tournoi ou le portrait), légendées « Journal, date » et créditées « Presse de l’époque, via FCSM Story ». Seuls les originaux du domaine public sont repris : journal ou agence identifié, publié en 1955 au plus tard ; jamais d’image retouchée ou colorisée, de dessin signé ni de photo sans source. Une photo déjà au musée n’est pas doublée ; les portraits déjà au musée reçoivent seulement la photo dans leur galerie.</p>
HTML],
        ['id' => 'feuilles', 'title' => 'Import des feuilles de match', 'admin' => true, 'html' => <<<'HTML'
<p>Système › <b>Feuilles de match</b> fait entrer au musée les feuilles de match des archives de l’association : environ 3 600 matchs officiels et 330 amicaux, de 1928 à 2024 (score, mi-temps, stade, affluence, arbitre, buteurs des deux équipes, compositions avec remplacements, entraîneur). Trois boutons, dans l’ordre :</p>
<ol>
<li><b>Analyser les feuilles</b> : chaque feuille est rapprochée d’une fiche du musée (même date à trois jours près, même adversaire). Le tableau « Saison par saison » montre ce qui sera créé et ce qui sera comparé ; rien n’est encore écrit.</li>
<li><b>Essai sur 10 matchs</b> : ouvrez les fiches créées dans « Derniers matchs traités » pour juger le résultat.</li>
<li><b>Lancer tout l’import</b> : l’import avance tant que la page reste ouverte (quelques minutes en tout), et par la tâche planifiée sinon ; « Mettre en pause » l’arrête.</li>
</ol>
<p>Un match <b>absent</b> du musée est créé, publié et rangé dans sa saison (une saison manquante est créée), avec une ligne « Sources ». Un match <b>déjà au musée n’est jamais modifié</b> : chaque écart (score, date, domicile ou extérieur, affluence) et chaque information qui lui manque (arbitre, stade, buteurs, composition) devient une proposition dans <b>Trouvailles</b>, filtre « Feuilles de match », à envoyer dans la fiche ou à écarter. Relancer est sans risque : une feuille n’est traitée qu’une fois. Aucun coût d’IA.</p>
<p><b>Bilans des joueurs</b> : les bilans de l’association (1932-2024 : matchs, titularisations, minutes, buts et cartons de chaque joueur, saison par saison et compétition par compétition) s’affichent d’eux-mêmes, sans bouton : tableau « Saison par saison » et « minutes jouées » sur la fiche du joueur, onglet <b>Temps de jeu</b> du livre des records, <b>classement final</b> en haut des pages de saison. Un nom du bilan (« REVELLI P. ») n’est relié qu’à une fiche sans ambiguïté : même nom, même initiale, même époque. En bas de l’écran Feuilles de match, la liste des joueurs des bilans sans fiche : créez-la (ou complétez prénom et années au club) et le lien se fait tout seul.</p>
[[astuce|<p>Commencez par les <b>scores</b> en désaccord dans Trouvailles : ce sont les plus importants. Le rapport complet, saison par saison, est dans <code>docs/rapport-comparaison-matchs.md</code>.</p>]]
HTML],
        ['id' => 'couts', 'title' => 'Coûts de l’IA et remboursement', 'admin' => true, 'html' => <<<'HTML'
<p>Chaque appel à Gemini (assistant du site, traductions, correcteur d’orthographe, index de l’assistant) est facturé par Google à la personne qui a fourni la clé. Système › <b>Coûts IA</b> calcule ce coût au moment où Google répond : jetons consommés × tarif du modèle, converti en euros. L’écran se met à jour tout seul toutes les 10 secondes : aujourd’hui, ce mois-ci, à rembourser, budget, et les derniers appels (usage, qui l’a demandé, quelle fiche, combien).</p>
[[img:couts-ia.webp|Les coûts de l’IA : (1) aujourd’hui, (2) à rembourser, (3) budget du mois, (4) derniers appels en direct, (5) noter le remboursement, (6) relevé PDF et CSV]]
<p><b>Se faire rembourser.</b> Le mois terminé, téléchargez son <b>relevé PDF</b> (totaux par usage, par modèle et par jour, cases de signature) et le <b>détail CSV</b> (chaque appel, à ouvrir dans un tableur), remettez-les au trésorier de l’association puis, le virement reçu, cliquez sur <b>Noter le remboursement</b>. Le tableau de bord rappelle les mois qui restent à rembourser.</p>
<p><b>Garder la main sur la dépense.</b> Réglages › <b>Coûts IA</b> : nom de la personne qui avance les frais (imprimé sur le relevé), taux de change de votre banque, budget mensuel. Budget atteint : les tâches automatiques (et, si vous le souhaitez, l’assistant du site) se mettent en pause jusqu’au 1er du mois suivant ; les boutons du back-office restent utilisables. Après une traduction ou une vérification d’orthographe, son coût s’affiche aussi dans le message de confirmation.</p>
[[astuce|<p>Ordre de grandeur avec Gemini 3.1 Flash-Lite : une question à l’assistant coûte un à deux dixièmes de centime, la relecture ou la traduction d’une fiche de match quelques dixièmes de centime. Un texte déjà relu n’est jamais renvoyé à Gemini.</p>]]
[[attention|<p>La facture Google (console Google Cloud › Facturation) fait foi : quelques centimes d’écart sont possibles (arrondis, taux de change). Si Google change ses prix, mettez à jour le <b>barème</b> en bas de l’écran ; une hausse annoncée peut être saisie à l’avance, avec sa date. Les appels déjà faits gardent leur coût.</p>]]
HTML],
        ['id' => 'dons', 'title' => 'Dons hors ligne et reçus fiscaux', 'html' => <<<'HTML'
<p>Communauté › <b>Dons</b> : seuls les administrateurs enregistrent un don reçu hors ligne (chèque, virement, espèces), notent son remboursement, émettent les reçus fiscaux et les consultent. Les autres comptes voient la jauge, la liste et le détail des dons, exportent le fichier CSV, modèrent le mur des donateurs et ajoutent des notes.</p>
HTML],
        ['id' => 'sauvegardes', 'title' => 'Sauvegardes et restauration', 'html' => <<<'HTML'
<p>Voir [[aide:fonctionnement#sauvegardes|Les sauvegardes]]. Réglages › Sauvegardes : heure de la sauvegarde quotidienne, nombre d’archives gardées, photos ajoutées le dimanche. Restaurer une sauvegarde complète (remplacer les dossiers <code>data/</code> et <code>storage/</code> sur le serveur) est une opération de webmestre, à réserver aux incidents graves.</p>
HTML],
        ['id' => 'majs', 'title' => 'Mises à jour du site', 'admin' => true, 'html' => <<<'HTML'
<p>Système › <b>Mises à jour</b> (administrateurs) : la version installée, la dernière version publiée sur GitHub et la liste des changements. <b>Appliquer la mise à jour</b> remplace en quelques secondes les seuls fichiers du code qui ont changé ; le site affiche « Mise à jour en cours » pendant la copie.</p>
[[img:mises-a-jour.webp|Système › Mises à jour : version installée, version disponible, changements et historique]]
<ul>
<li><b>Jamais touchés :</b> les fiches, la médiathèque et leurs traductions, les réglages, les comptes, les journaux, les coûts IA, les photos et les voix.</li>
<li><b>Avec précaution :</b> les nouveaux libellés anglais de l’interface sont ajoutés sans modifier les traductions existantes ; un fichier <code>.htaccess</code> réglé à la main sur le serveur est gardé.</li>
<li><b>Retour arrière :</b> avant chaque mise à jour, les fichiers remplacés sont sauvegardés ; « Revenir à cette version » les remet en place.</li>
<li><b>Synchronisation :</b> chaque vérification compare aussi le code du serveur à GitHub, fichier par fichier. Après un envoi par FTP, la version est reconnue si tout est identique ; sinon, les fichiers oubliés ou différents sont listés et <b>Synchroniser avec GitHub</b> les remplace en un clic.</li>
</ul>
<p>Le site vérifie tout seul toutes les 3 heures ; une nouvelle version s’annonce au tableau de bord et par une pastille dans le menu. Dépôt GitHub et branche suivie : Réglages › <b>Mises à jour</b>.</p>
HTML],
        ['id' => 'taches', 'title' => 'Tâches planifiées', 'html' => <<<'HTML'
<p>Voir [[aide:fonctionnement#taches|Les tâches automatiques]]. Le tableau donne, pour chaque tâche, sa fréquence, son dernier passage et son résultat ; « Lancer » en exécute une tout de suite.</p>
<p>La carte <b>Serveur</b>, en bas de l’écran, vérifie l’hébergement : version de PHP (8.3), extensions, dossiers inscriptibles ; et liste les dernières erreurs du journal PHP. Un réglage manquant s’affiche aussi en tête du tableau de bord (« Régler le serveur ») et, pour un administrateur connecté, sur la page « Arrêt de jeu » en cas d’erreur.</p>
[[attention|<p>Un bandeau rouge « La tâche planifiée n’est jamais passée » (ou un dernier passage ancien) signifie que la tâche cron du serveur n’est pas installée ou s’est arrêtée : prévenez le webmestre, qui vérifiera la ligne indiquée dans le bandeau chez l’hébergeur.</p>]]
HTML],
        ['id' => 'journal', 'title' => 'Journal d’activité', 'html' => <<<'HTML'
<p>Pilotage › <b>Journal</b> : toutes les actions de l’équipe (création, modification, publication, connexion…), filtrables par personne.</p>
[[img:journal.webp|Le journal d’activité]]
HTML],
    ],
];
