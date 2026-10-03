<?php
return [
    'slug' => 'administration',
    'title' => 'Administration',
    'summary' => 'Pour les administrateurs : utilisateurs, réglages, assistant IA, sauvegardes, tâches, journal.',
    'sections' => [
        ['id' => 'utilisateurs', 'title' => 'Utilisateurs et invitations', 'html' => <<<'HTML'
<p>Système › <b>Utilisateurs</b> (administrateurs) : <b>Inviter une personne</b> (nom, e-mail, niveau) ; elle reçoit un lien pour choisir son mot de passe, que l’on peut aussi copier et transmettre autrement. On peut renvoyer une invitation, changer le niveau ou désactiver un compte.</p>
[[img:utilisateurs.webp|L’équipe du back-office et les invitations]]
[[attention|<p>Le site garde toujours au moins un administrateur actif.</p>]]
HTML],
        ['id' => 'reglages', 'title' => 'Réglages', 'html' => <<<'HTML'
<p>Système › <b>Réglages</b> (administrateurs), en onglets : Général (nom, adresse du site, e-mail de contact, mot de passe d’accès avant lancement), Page d’attente, Accueil, Réseaux sociaux, Assistant IA (clé Gemini et modèle), Traduction, Correcteur (vérification de fond, plafond d’appels à Gemini, typographie), Dons (Stripe, PayPal, objectifs), E-mail (serveur d’envoi), Newsletter, Carte, Centenaire, Mentions légales, Sauvegardes, Cookies et RGPD.</p>
[[img:reglages.webp|Les réglages : les clés secrètes ne sont jamais réaffichées]]
[[astuce|<p>Les clés secrètes (API, mots de passe) sont chiffrées : laissez le champ vide pour garder la valeur enregistrée.</p>]]
HTML],
        ['id' => 'assistant', 'title' => 'L’assistant IA', 'html' => <<<'HTML'
<p>La bulle « Le guide du musée » répond aux visiteurs à partir des données du site (fiches, statistiques), grâce à Gemini. Réglages › Assistant IA : clé, modèle (liste chargée depuis la clé), nom, message d’accueil, limites quotidiennes. La même clé sert aux traductions et au correcteur d’orthographe.</p>
<p>Système › <b>Assistant IA</b> : questions posées par mois, avis des visiteurs (utile / pas utile), export, réindexation après de grosses modifications.</p>
[[img:assistant.webp|Le journal de l’assistant IA]]
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
