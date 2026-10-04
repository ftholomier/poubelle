<?php
return [
    'slug' => 'administration',
    'title' => 'Administration',
    'summary' => 'Pour les administrateurs : utilisateurs, réglages, assistant IA, fiches audio, coûts de l’IA, dons hors ligne et reçus fiscaux, sauvegardes, tâches, journal.',
    'sections' => [
        ['id' => 'utilisateurs', 'title' => 'Utilisateurs et invitations', 'html' => <<<'HTML'
<p>Système › <b>Utilisateurs</b> (administrateurs) : <b>Inviter une personne</b> (nom, e-mail, niveau) ; elle reçoit un lien pour choisir son mot de passe, que l’on peut aussi copier et transmettre autrement. On peut renvoyer une invitation, changer le niveau ou désactiver un compte.</p>
[[img:utilisateurs.webp|L’équipe du back-office et les invitations]]
[[attention|<p>Le site garde toujours au moins un administrateur actif.</p>]]
HTML],
        ['id' => 'reglages', 'title' => 'Réglages', 'html' => <<<'HTML'
<p>Système › <b>Réglages</b> (administrateurs), en onglets : Général (nom, adresse du site, e-mail de contact, mot de passe d’accès avant lancement, « Masquer le site aux moteurs de recherche » même ouvert au public), Page d’attente, Accueil, Réseaux sociaux, Pied de page (titre, phrase, deux boutons et leurs liens, accroche, ligne du bas, en français et en anglais), Assistant IA (clé Gemini et modèle), Traduction, Correcteur (vérification de fond, plafond d’appels à Gemini, typographie), Fiches audio (durée maximale, voix, ton, explications IA), Coûts IA (qui avance les frais, taux de change, budget mensuel), Dons (Stripe, PayPal, objectifs), E-mail (serveur d’envoi), Newsletter, Carte, Centenaire, Mentions légales, Sauvegardes, Cookies et RGPD.</p>
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
<li><b>Revenir au résumé automatique</b>, <b>Supprimer la voix IA</b> : retour au gratuit.</li>
</ul>
[[img:fiche-audio.webp|La carte « Écouter » de l’éditeur : (1) le texte lu, (2) rédiger avec l’IA, (3) voix IA, (4) écouter]]
<p><b>Tout le musée</b> : Système › <b>Fiches audio</b> estime le coût puis confie les fiches au <b>traitement groupé</b> de Google, à moitié prix (environ 40 € pour les 2 940 fiches en 3 minutes au plus ; le tarif de la voix double au 1er janvier 2027). Commencez par <b>Essayer d’abord sur 20 fiches</b> pour écouter le résultat. Google répond en quelques heures ; la tâche planifiée range les voix et chaque fiche bascule toute seule sur sa voix IA.</p>
[[img:audio.webp|Système › Fiches audio : voix enregistrées, estimation, traitements groupés]]
[[astuce|<p>Réglages › <b>Fiches audio</b> : durée maximale, choix de la voix (Charon, Gacrux, Sulafat…), du ton, rédaction des explications par l’IA lors du traitement groupé, et mise à jour de nuit des voix des fiches modifiées. Chaque dépense apparaît dans Coûts IA (usage « Fiches audio »).</p>]]
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
