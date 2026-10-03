# Contrôle complet du 3 octobre 2026

Relecture de tout ce qui a été développé (code et données), avant la recette du club.
Six contrôles successifs ; chaque anomalie trouvée a été vérifiée, corrigée et retestée.
Les données des historiens n'ont pas été modifiées : ce qui les concerne est listé en fin de
document, à vérifier fiche par fiche.

## En bref

| Contrôle | Ce qui a été fait | Résultat |
|---|---|---|
| 1. Code | syntaxe de tous les fichiers PHP, appels de fonctions, gabarits, tests automatiques | aucun défaut |
| 2. Site public | 16 314 adresses visitées (FR et EN), PDF, journaux d'erreurs | 3 défauts corrigés |
| 3. Back-office | tous les écrans, enregistrements « à blanc » de 129 fiches de tous types, vraies modifications, accessibilité | 1 défaut grave corrigé (perte de données à l'enregistrement) |
| 4. Sécurité et robustesse | 3 relectures indépendantes : accès et formulaires, sorties HTML et fichiers, logique des fonctionnalités | 3 défauts graves, une dizaine de moyens et une vingtaine de mineurs corrigés |
| 5. Données | cohérence des 2 940 fiches, statistiques, liens joueurs | 2 nouveaux contrôles qualité ; anomalies listées pour les historiens |
| 6. Vérification finale | 10 suites de tests, test de fumée de tous les écrans, accessibilité, 100 chiffres | tout est bon |

## 1. Le défaut le plus important : l'enregistrement d'une fiche pouvait abîmer ses données

Ouvrir une fiche reprise de l'ancien site et cliquer sur « Enregistrer », sans rien toucher,
modifiait la fiche (essai sur 129 fiches : **129 modifiées**). Le formulaire ne savait pas
afficher certaines valeurs de l'ancien site et les remplaçait. Les cas les plus graves :

- **Tirs au but et prolongations effacés** (47 matchs) : « 1-1 (4-5 tab) » devenait « 1-1 »
  et la victoire devenait un nul.
- **Lieu de décès tronqué** (325 personnes) : la ville, le département et les coordonnées
  disparaissaient.
- **Rareté de l'album forcée à « classique »** pour toute personne enregistrée (une légende
  perdait sa carte « légende »).
- **Sous-titres des bilans de saison recollés** : « 14e du championnat de Ligue 1 » et
  « 32e de finale… » devenaient « Ligue 132e de finale ».
- **Saison changée** pour les 14 matchs amicaux de fin juin, rangés par les historiens dans
  la saison qui commence.
- **Enregistrement refusé** pour les fiches dont une date de l'ancien site n'est pas
  reconnue (« juin 1978 ? », « xx »). L'historien ne pouvait plus enregistrer la fiche
  sans corriger d'abord cette date.
- Lignes « intertitre » de la fiche d'identité supprimées, intertitres h5 transformés,
  secondes de la date perdues.
- Une nouvelle version était créée à chaque fois. Le message « Version vN enregistrée »
  s'affichait même quand rien n'avait été enregistré. L'enregistrement suivant pouvait
  ensuite annoncer à tort une « modification simultanée ».

**Correction.** Une valeur que la personne n'a pas touchée est désormais gardée exactement
telle qu'elle est enregistrée. Le formulaire sait aussi afficher les valeurs reprises :
- « Prolongation » affiche « Tirs au but » pour « (4-5 tab) » ;
- la rareté propose « Auto » ;
- le sous-titre accepte plusieurs lignes ;
- les dates incertaines comme « juillet 1970 ? » sont acceptées.

Un enregistrement sans changement répond « Aucune modification » et ne crée ni version ni
modification.

**Vérifié.**
- Nouvel essai sur les 129 fiches : **0 modifiée**.
- Dans le vrai back-office, une modification réelle ne change que le champ modifié.
- 39 contrôles automatiques ont été ajoutés (`tests/fiche-form.php`).

## 2. Autres défauts corrigés

### Écritures simultanées (graves)

- **Publication annulée en silence.** Les tâches planifiées gardaient en mémoire une copie
  ancienne de l'index des fiches. Elles pouvaient la réécrire et annuler une publication
  faite entre-temps : la fiche redevenait « brouillon » pour le site. L'index et la
  recherche sont désormais relus sous verrou à chaque écriture. Testé avec deux processus
  simultanés : les deux modifications sont gardées.
- **Statistiques non mises à jour.** Une correction enregistrée pendant un recalcul des
  statistiques pouvait ne jamais y être prise en compte. Elle relance maintenant un
  nouveau calcul. Le recalcul ne bloque plus les visiteurs et dispose de la mémoire
  nécessaire.
- **Corrections des référentiels écrasées.** Une correction d'un club ou d'un stade faite
  pendant un recalcul était perdue. Les référentiels sont désormais fusionnés, pas écrasés.
- **Modification d'un historien perdue pendant une traduction.** La traduction automatique
  pouvait réenregistrer une fiche modifiée par un historien pendant l'appel à l'IA. Elle
  repart maintenant de la version à jour et ne touche pas aux fiches ouvertes.

### Fonctionnalités

| Où | Défaut | Correction |
|---|---|---|
| Fiche audio | « Array » lu à voix haute pour les 31 matchs avec tirs au but | phrase « et Sochaux l'emporte 5 à 4 aux tirs au but » |
| Fiche audio | nombre de matchs des entraîneurs faux ou absent (13 entraîneurs) | matchs sur le banc ; intertitres lus comme des phrases |
| Rétro-Direct | réactions refusées pendant les dernières minutes du match et toute la prolongation | fin exacte du direct ; minutes « 90'+2 » reconnues |
| Rétro-Direct | « Il y a 1 ans », « 1 spectateurs », « 1 réactions » ; plus de 20 téléphones sur un même Wi-Fi bloqués | accords corrigés, limite relevée |
| Newsletter | envoi en double possible ; textes anglais manquants | un seul envoi à la fois, file enregistrée après chaque e-mail |
| Kit souvenirs | le PDF d'une langue effaçait celui de l'autre ; PDF refait à chaque enregistrement de fiche | un PDF par langue, refait seulement si le kit change |
| Kit souvenirs | « 1e minute », « 21th minute » | « 1re minute », « 21st minute » |
| Fil jaune | page en erreur si un joueur des records est retiré du site | joueur écarté |
| Dons | une réponse incomplète de Stripe n'était pas contrôlée | refusée avec un message clair |
| Sauvegardes | une sauvegarde ratée apparaissait réussie | notée en échec |
| Tâches | « rien à faire » affiché alors que la tâche n'avait pas tourné | message « déjà en cours » |
| Les chiffres | minute « 45+1' » lue comme la 1re ; division par zéro possible | corrigés (aucun des 100 chiffres ne change) |
| Statistiques | 50 apparitions comptées deux fois (joueur inscrit deux fois dans une composition) | comptées une fois, nouvelle alerte qualité |
| Liste des matchs triée A→Z | 5,25 s | 0,06 s |
| Correcteur | réponses encore utiles effacées du cache au bout de 180 jours (refacturées) | date d'utilisation mise à jour |

### Sécurité

Aucune faille critique n'a été trouvée. Corrections apportées :

- **Connexion.**
  - Limite d'essais par compte (en plus des limites par adresse IP), débloquée par « Mot
    de passe oublié ».
  - Les adresses IPv6 comptent par bloc.
  - Même durée de réponse que le compte existe ou non.
  - Nouveau jeton de sécurité à chaque connexion.
- **Liens envoyés par e-mail.** Les liens d'invitation et de mot de passe ne peuvent plus
  pointer vers un autre site. Un compte désactivé ne peut plus être réactivé par
  « Renvoyer l'invitation ».
- **Redirection vers un autre site** possible avec une adresse piégée (`/%09/site.com`) :
  fermée.
- **Page d'attente et mot de passe d'avant-lancement.** L'API publique (recherche,
  assistant IA payant, carte) n'était pas protégée. Elle l'est maintenant.
- **Résultats du « Onze du public »** visibles avant la date de dévoilement : ils sont
  désormais cachés jusqu'à cette date, comme l'annonce le back-office.
- **100 moments.** Un moment marqué « publié » était visible partout (recherche, PDF,
  partage) avant sa semaine. Il reste maintenant planifié jusqu'au jour de sa case. Sa date
  suit aussi le calendrier quand on le réorganise.
- **Personnes non publiées.** Le nom et la photo de fiches en brouillon apparaissaient sur
  les pages saison et dans l'assistant. C'est corrigé.
- **Newsletter.** Confirmation et désinscription se font par un bouton. Les messageries
  ouvrent seules les liens des e-mails et validaient l'inscription à la place des abonnés.
- **Fichiers et contenus.**
  - Exports CSV protégés contre les formules piégées.
  - Fichiers reçus du public plafonnés à 3 Go au total.
  - Chemins d'images et identifiants de vidéos contrôlés.
- **Traductions.** Une traduction anglaise saisie dans le back-office est nettoyée de tout
  code. Le nettoyage du HTML repris de l'ancien site est renforcé.

### Données corrigées directement

- **Ancienne page d'accueil WordPress** (fiche 3) : elle était encore publiée à
  `/accueil-wordpress/` et créait une boucle de redirection. Elle passe en brouillon et son
  adresse redirige vers l'accueil.
- **25 textes anglais manquants** ajoutés au dictionnaire (un autre corrigé).

## 3. À vérifier par les historiens (données non modifiées)

Ces points viennent des fiches reprises. Le site les gère sans erreur, mais ils méritent une
correction. **Tous sont listés dans Pilotage › Qualité**, visible de tous les comptes :
- onglet **Statistiques et dates** : scores, compositions, dates ;
- onglet **À compléter** : « xx », fiches à venir, vidéos.

Les alertes les plus graves viennent en premier, 300 par page. Avant, seules les 400
premières s'affichaient.

Au passage, un défaut a été corrigé : les alertes « fiche à venir » et « statistiques
incohérentes » ouvraient une **mauvaise fiche**, à cause de numéros mélangés lors du calcul.

**Les « xx » de l'ancien site.** L'ancien site notait « xx » quand une information était
inconnue (« Décédé le xx à xx », « xx’ : » pour une minute, « Arbitre : xx »). Ces « xx »
apparaissaient tels quels sur 214 fiches du site public, dans le résumé audio et dans les
données lues par Google. Ils sont maintenant cachés à l'affichage, et la fiche n'est pas
modifiée : « Décédé le xx à xx » devient « Décédé », et « né le xx xx 1940 à Montpellier »
devient « né en 1940 à Montpellier ». Chaque fiche concernée figure dans Qualité › À
compléter avec l'endroit du « xx ».

**Compositions et statistiques**
- **Joueur inscrit deux fois dans une composition officielle (10 matchs)** : 14991
  (Boniface), 15226 (Maraval), 17378 (Ferri), 17812 (Guillou), 17914 (Chedli), 18008
  (Saveljic), 22982 (Melic), 4096 (Laurent), 4438 (Faivre), 6939 (Peybernes). Alerte
  « doublon » dans Qualité.
- **Tableau de statistiques recopié** d'une autre fiche (modèle de l'ancien site) : 594
  fiches de joueurs. Alerte « stats-copie ».
- Compositions à revoir : fiches 23126 et 18075.

**Dates des personnes (alerte « dates »)**
- 10841 Lafranceschina : décès (1924) avant la naissance (1939).
- 11488 Laufenburger : arrivée en 1962, naissance en 1983.
- 11538 Martin Lecolier : naissance en 2023.
- 7844 Gurler : arrivée à 4 ans.
- 9975 Touré : arrivée à 2 ans.
- 6522 Éric Benoit : dates 2020-2021 pour une carrière 1974-1983.
- 7954 Gavanon : naissance en 1943 (1983 ?).
- 9265 Ninot : naissance en 1937.
- 9249 Ogier : naissance en 1976.

**Dates des matchs : date en toutes lettres différente de la date de la fiche**
- **65 matchs** dont le jour, le mois ou l'année diffèrent :
  10184, 12226, 13410, 13432, 14372, 14871, 15229, 1526, 15576, 15578, 15743, 15805, 15942,
  16635, 16642, 16646, 16648, 16660, 17086, 17088, 17101, 17143, 17171, 17362, 17372,
  17894, 17992, 18029, 1861, 18698, 18951, 18998, 19759, 19767, 19829, 21709, 22144,
  22183, 22219, 2233, 2235, 22962, 2305, 23088, 23143, 2383, 2404, 2862, 290, 3715, 3761,
  4345, 4438, 4449, 4611, 491, 5185, 643, 648, 8552, 962, 966, 968, 970, 972.
  - Exemples : 290 « samedi 12 août 1994 » pour une fiche datée du 13 ; 15942 « samedi 1er
    mai 2025 » pour un match de 1999 ; 22962 « vendredi 21 août 2026 » pour le 2 octobre.
- **37 matchs** dont seul le jour de la semaine est faux : 875, 877, 885, 1766, 2317, 2538,
  2746, 3466, 3717, 6349, 6931, 9352, 10448, 12655, 12768, 12885, 13449, 13963, 14408,
  15137, 15499, 15801, 16134, 16871, 17364, 17824, 17832, 17935, 19232, 19578, 19791,
  19800, 21638, 22012, 22027, 22029, 22231.

**Autres points**
- **Tirs au but non détaillés** (score de la séance non lu) : 21638 « (tab 9-8) », 3758
  « (tab 5-4) ».
- **Vidéos dont le lien est cassé** sur l'ancien site : 12949 et 267. Les fiches s'enregistrent
  sans problème ; il suffit de recoller le bon lien YouTube.
- **Informations inconnues notées « xx »** sur l'ancien site : 214 fiches (dates et lieux
  de naissance ou de décès, arbitres, minutes, chiffres clés…). Elles sont cachées sur le
  site public et listées dans Qualité › À compléter.
- **Ligne de jeu « E »** (hors liste) : fiche 5963. Elle est gardée telle quelle.
- **Brouillon d'essai** « Test elfsight » (13259) : à supprimer, avec sa redirection
  `/?p=13259` (signalée dans Qualité › Adresses et médias : elle mène à une page introuvable).

**Trouvé par les vérifications ajoutées avec le bouton « Contrôler maintenant »** (§ 6)
- **Minute d'entrée en jeu sur une ligne de titulaire** (matchs officiels) : le joueur
  compte 90 minutes de jeu au lieu de son vrai temps. 22952 (Goutte, entré à la 46e mais noté
  attaquant : remplaçant mal noté) ; 15349 (Dewilder, 81e), 3747 (Diego Michel, 72e) et 4332
  (Faivre, 75e) : il n'y a que 10 autres titulaires, la minute est sans doute celle de sa
  sortie, mal placée. Onglet Compo & événements de chaque match.

## 4. Droits et points à arbitrer

**Décidé le 3 octobre.** Sont réservés à l'administrateur :
- les écrans Coûts IA, Assistant IA, Fiches audio (traitement groupé), Sauvegardes et
  Tâches planifiées, en plus des Utilisateurs et des Réglages ;
- toute mention de coût (messages après une traduction, une correction, une voix IA) ;
- l'état des sauvegardes en bas du menu ;
- le renvoi d'une newsletter déjà partie.

Tout le reste est ouvert aux historiens, en particulier Pilotage › Qualité et toutes ses
alertes.

**Décidé ensuite.** Les dons hors ligne (chèque, virement, espèces), enregistrés à la main
dans Communauté › Dons, comptent dans la jauge publique et peuvent donner lieu à un reçu
fiscal : leur enregistrement, leur remboursement noté, l'émission et la consultation des
reçus fiscaux sont réservés à l'administrateur. La liste des dons, l'export, le mur des
donateurs et les notes restent ouverts à tous les comptes.

## 5. Refaire les contrôles

Les données : Pilotage › Qualité › **Contrôler maintenant** (§ 6), ou
`php bin/console.php controle`. Le code :

```
for t in tests/*.php; do php $t | tail -1; done          # 11 suites, « Tout est bon. »
SR_BASE=http://127.0.0.1:8080 node tests/smoke.js        # site public
SR_EMAIL=… SR_PASSWORD=… node tests/smoke.js             # + tous les écrans du back-office
```

## 6. Bouton « Contrôler maintenant » (ajouté après le contrôle)

Demande du client : pouvoir refaire soi-même un contrôle complet et voir les anomalies du
contenu saisi depuis le dernier contrôle.

- En haut de Pilotage › Qualité, pour tous les comptes. En 2 secondes environ, toutes les
  vérifications sont refaites sur toutes les fiches, puis comparées au contrôle précédent :
  nombre d'anomalies **nouvelles** et **corrigées**, liste des nouvelles (tous onglets),
  pastille « Nouveau » sur chacune jusqu'au contrôle suivant, rappel dans le tableau de bord,
  historique des derniers contrôles, ligne dans le journal.
- Le premier contrôle se compare à ce contrôle-ci : les anomalies des données du dépôt au
  3 octobre sont livrées avec le code (`app/Resources/controle-reference.json` : 6 477
  alertes, l'onglet Orthographe à part, car il dépend du correcteur de chaque serveur). Seul ce qui a été saisi ou modifié depuis apparaît comme
  « nouveau ».
- Si des fichiers ont été changés hors du back-office (envoi par FTP), l'index des fiches et
  la recherche sont remis à jour au passage.
- Vérifications ajoutées à cette occasion, recalculées aussi à chaque enregistrement :
  - **matchs** : date absente ou impossible, date hors de la saison, résultat incohérent
    avec le score, tirs au but sur un score non nul, match officiel joué sans score, score
    d'un match à venir, même match saisi deux fois, composition (entrant noté titulaire,
    plus de 11 titulaires, deux gardiens) ;
  - **personnes** : date impossible (30 février), aucune rubrique, deux fiches au même nom
    sans dates de naissance qui les distinguent ;
  - **fiches** : titre vide, adresse vide, mal formée ou partagée, rubrique ou image
    supprimée, fichier abîmé ;
  - **site** : redirections (boucle, chaîne, page absente ou non publiée, inutiles),
    adversaires et stades en double, rubriques orphelines, textes de l'interface mal
    traduits, versions anglaises dépassées.
- Nouvel onglet **Adresses et médias** ; l'onglet **Traductions à revoir**, toujours vide
  jusqu'ici (rien ne le remplissait), liste maintenant les fiches et les textes concernés.
- Sur les données actuelles, ces vérifications ne trouvent que les 4 compositions et la
  redirection signalées au § 3. Au passage, un même rapprochement de nom pouvait apparaître
  deux fois dans Liens joueurs (deux graphies affichées pareil) : il n'apparaît plus qu'une fois.
- Essayé de bout en bout avec un compte historien : score modifié et fiche en double saisis,
  contrôle → 4 nouvelles anomalies signalées ; correction, nouveau contrôle → aucune
  nouvelle, 1 corrigée. `tests/controle.php` (54 vérifications).
- Essayé aussi en **installation neuve** (copie vierge de la branche, `storage/` vide, sans
  les photos), comme à la première mise en ligne : premier accès, toutes les pages du site et
  du back-office, tâches planifiées. Deux réglages en sont sortis : les photos pas encore
  copiées donnent une seule alerte (au lieu d'une par fiche) et une tâche dans le tableau de
  bord ; les propositions du correcteur sur des textes anciens, trouvées en tâche de fond,
  ne sont pas « nouvelles ».
