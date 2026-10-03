<?php
return [
    'slug' => 'administration',
    'title' => 'Administration',
    'summary' => 'Pour les administrateurs : utilisateurs, réglages, assistant IA, coûts de l’IA, sauvegardes, tâches, journal.',
    'sections' => [
        ['id' => 'utilisateurs', 'title' => 'Utilisateurs et invitations', 'html' => <<<'HTML'
<p>Système › <b>Utilisateurs</b> (administrateurs) : <b>Inviter une personne</b> (nom, e-mail, niveau) ; elle reçoit un lien pour choisir son mot de passe, que l’on peut aussi copier et transmettre autrement. On peut renvoyer une invitation, changer le niveau ou désactiver un compte.</p>
[[img:utilisateurs.webp|L’équipe du back-office et les invitations]]
[[attention|<p>Le site garde toujours au moins un administrateur actif.</p>]]
HTML],
        ['id' => 'reglages', 'title' => 'Réglages', 'html' => <<<'HTML'
<p>Système › <b>Réglages</b> (administrateurs), en onglets : Général (nom, adresse du site, e-mail de contact, mot de passe d’accès avant lancement), Page d’attente, Accueil, Réseaux sociaux, Assistant IA (clé Gemini et modèle), Traduction, Correcteur (vérification de fond, plafond d’appels à Gemini, typographie), Coûts IA (qui avance les frais, taux de change, budget mensuel), Dons (Stripe, PayPal, objectifs), E-mail (serveur d’envoi), Newsletter, Carte, Centenaire, Mentions légales, Sauvegardes, Cookies et RGPD.</p>
[[img:reglages.webp|Les réglages : les clés secrètes ne sont jamais réaffichées]]
[[astuce|<p>Les clés secrètes (API, mots de passe) sont chiffrées : laissez le champ vide pour garder la valeur enregistrée.</p>]]
HTML],
        ['id' => 'assistant', 'title' => 'L’assistant IA', 'html' => <<<'HTML'
<p>La bulle « Le guide du musée » répond aux visiteurs à partir des données du site (fiches, statistiques), grâce à Gemini. Réglages › Assistant IA : clé, modèle (liste chargée depuis la clé), nom, message d’accueil, limites quotidiennes. La même clé sert aux traductions et au correcteur d’orthographe.</p>
<p>Système › <b>Assistant IA</b> : questions posées par mois, avis des visiteurs (utile / pas utile), export, réindexation après de grosses modifications.</p>
[[img:assistant.webp|Le journal de l’assistant IA]]
HTML],
        ['id' => 'couts', 'title' => 'Coûts de l’IA et remboursement', 'html' => <<<'HTML'
<p>Chaque appel à Gemini (assistant du site, traductions, correcteur d’orthographe, index de l’assistant) est facturé par Google à la personne qui a fourni la clé. Système › <b>Coûts IA</b> calcule ce coût au moment où Google répond : jetons consommés × tarif du modèle, converti en euros. L’écran se met à jour tout seul toutes les 10 secondes : aujourd’hui, ce mois-ci, à rembourser, budget, et les derniers appels (usage, qui l’a demandé, quelle fiche, combien).</p>
[[img:couts-ia.webp|Les coûts de l’IA : (1) aujourd’hui, (2) à rembourser, (3) budget du mois, (4) derniers appels en direct, (5) noter le remboursement, (6) relevé PDF et CSV]]
<p><b>Se faire rembourser.</b> Le mois terminé, téléchargez son <b>relevé PDF</b> (totaux par usage, par modèle et par jour, cases de signature) et le <b>détail CSV</b> (chaque appel, à ouvrir dans un tableur), remettez-les au trésorier de l’association puis, le virement reçu, cliquez sur <b>Noter le remboursement</b>. Le tableau de bord rappelle les mois qui restent à rembourser.</p>
<p><b>Garder la main sur la dépense.</b> Réglages › <b>Coûts IA</b> : nom de la personne qui avance les frais (imprimé sur le relevé), taux de change de votre banque, budget mensuel. Budget atteint : les tâches automatiques (et, si vous le souhaitez, l’assistant du site) se mettent en pause jusqu’au 1er du mois suivant ; les boutons du back-office restent utilisables. Après une traduction ou une vérification d’orthographe, son coût s’affiche aussi dans le message de confirmation.</p>
[[astuce|<p>Ordre de grandeur avec Gemini 3.1 Flash-Lite : une question à l’assistant coûte un à deux dixièmes de centime, la relecture ou la traduction d’une fiche de match quelques dixièmes de centime. Un texte déjà relu n’est jamais renvoyé à Gemini.</p>]]
[[attention|<p>La facture Google (console Google Cloud › Facturation) fait foi : quelques centimes d’écart sont possibles (arrondis, taux de change). Si Google change ses prix, mettez à jour le <b>barème</b> en bas de l’écran ; une hausse annoncée peut être saisie à l’avance, avec sa date. Les appels déjà faits gardent leur coût.</p>]]
HTML],
        ['id' => 'sauvegardes', 'title' => 'Sauvegardes et restauration', 'html' => <<<'HTML'
<p>Voir [[aide:fonctionnement#sauvegardes|Les sauvegardes]]. Réglages › Sauvegardes : heure de la sauvegarde quotidienne, nombre d’archives gardées, photos ajoutées le dimanche. Restaurer une sauvegarde complète (remplacer les dossiers <code>data/</code> et <code>storage/</code> sur le serveur) est une opération de webmestre, à réserver aux incidents graves.</p>
HTML],
        ['id' => 'taches', 'title' => 'Tâches planifiées', 'html' => <<<'HTML'
<p>Voir [[aide:fonctionnement#taches|Les tâches automatiques]]. Le tableau donne, pour chaque tâche, sa fréquence, son dernier passage et son résultat ; « Lancer » en exécute une tout de suite.</p>
[[attention|<p>Un bandeau rouge « La tâche planifiée n’est jamais passée » (ou un dernier passage ancien) signifie que la tâche cron du serveur n’est pas installée ou s’est arrêtée : prévenez le webmestre, qui vérifiera la ligne indiquée dans le bandeau chez l’hébergeur.</p>]]
HTML],
        ['id' => 'journal', 'title' => 'Journal d’activité', 'html' => <<<'HTML'
<p>Pilotage › <b>Journal</b> : toutes les actions de l’équipe (création, modification, publication, connexion…), filtrables par personne.</p>
[[img:journal.webp|Le journal d’activité]]
HTML],
    ],
];
