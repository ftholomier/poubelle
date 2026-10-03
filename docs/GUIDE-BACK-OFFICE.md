# Sochaux Rétro — guide du back-office

Pour les historiens et les administrateurs du musée en ligne. Le back-office s'ouvre à
l'adresse `/admin` du site (par exemple `https://www.fcsochauxretro.com/admin`).

## 1. Accès

- **Invitation** : un administrateur vous invite (Système › Utilisateurs) ; vous recevez
  un lien pour choisir votre mot de passe. Mot de passe oublié : lien sur l'écran de
  connexion.
- **Deux niveaux** :
  - *Administrateur* : tout.
  - *Utilisateur* : tout, sauf la gestion des utilisateurs, les réglages (clés API,
    paiements, e-mail…), la suppression définitive, la restauration d'anciennes versions,
    les sauvegardes (lancer, télécharger : une archive contient les clés et les comptes) et
    le lancement manuel des tâches planifiées. Il peut mettre à la corbeille et consulter
    l'historique.
- Votre profil (nom, mot de passe) : menu en haut à droite › « Mon profil ».

## 2. Se repérer

Le menu de gauche regroupe les écrans :

| Groupe | Écrans |
|---|---|
| Pilotage | Tableau de bord, Qualité, Journal (qui a fait quoi) |
| Contenus | Matchs, Personnes, Articles & pages, Objets (réserves), Saisons/adversaires/lieux, Médiathèque |
| Éditorial | Accueil & bandeau, 100 moments, Rubriques & menus, Redirections, Page d'attente |
| Interactif | Quiz, frise, carte, maillots, partenaires… ; Onze & album |
| Communauté | Contributions, Messages, Newsletter, Dons |
| Système | Traductions EN, Assistant IA, Utilisateurs, Réglages, Sauvegardes, Tâches planifiées |

- **Recherche globale** : `Ctrl + K` (ou la barre en haut) trouve une fiche, une photo,
  un écran.
- **Tableau de bord** : chiffres du musée, dernières modifications, choses à faire
  (photos à créditer, joueurs sans fiche, contributions à traiter…).
- **« + Nouveau »** en haut : créer un match, une personne, un article, un objet…

## 3. Les fiches

### Listes

Matchs, Personnes, Articles & pages, Objets : recherche, filtres (saison, compétition,
rubrique…), tri, et actions groupées en cochant plusieurs fiches (publier, repasser en
brouillon, « à relire », traduire, mettre à la corbeille).

### Éditeur

Une fiche s'édite en onglets ; à droite, le panneau **Publication** :

- **Statut** : *Brouillon* (invisible), *À relire*, *Planifié* (publication automatique à la
  date choisie), *Publié*.
- **Enregistrer** (ou `Ctrl + S`), avec une note de version facultative
  (« score corrigé d'après L'Est républicain »).
- **Aperçu** : la fiche telle qu'elle apparaîtra, avant publication.
- **Voir sur le site**, **Mettre à la corbeille** (récupérable dans la corbeille).

Garde-fous :

- Ce que vous tapez est gardé dans votre navigateur tant que ce n'est pas enregistré
  (coupure, fermeture d'onglet) : il est proposé à la réouverture de la fiche.
- **Fiche déjà ouverte par quelqu'un d'autre** : un bandeau indique qui la modifie et
  depuis quand ; vous la consultez en lecture seule (onglets et aperçu utilisables). Dès
  qu'elle est refermée, le bandeau le dit (« Modifier maintenant » ou « Recharger pour
  modifier »). **Prendre la main** permet de modifier quand même : la personne est prévenue
  et ne peut plus enregistrer. Une fiche oubliée se libère après 30 minutes sans activité.
  Dans les listes, « ✎ Prénom » signale les fiches ouvertes. Même protection pour les
  contenus interactifs, l'accueil, les rubriques et l'album.
- Si quelqu'un d'autre a enregistré la même fiche entre-temps, vous êtes prévenu au lieu
  d'écraser son travail.
- Onglet **Historique** : chaque enregistrement est une version (qui, quand, quoi) ; la
  première version d'une fiche reprise est son état d'origine sur l'ancien site. Un
  administrateur peut restaurer une ancienne version.

Les panneaux **Mis à jour automatiquement** et **Contrôle qualité** indiquent ce que
l'enregistrement recalcule (saison, fiches des joueurs, carte, records…) et les points à
vérifier sur la fiche.

### Orthographe

Le panneau **Orthographe** (bouton **Vérifier l'orthographe**) relit tous les textes de la
fiche, version anglaise comprise :

- **Gemini** (quand la clé est réglée) : orthographe, accords, conjugaison, homophones
  (a/à, et/est…), mots manquants ou en trop, constructions fautives, ponctuation, majuscules ;
- **règles du musée** (toujours) : mot répété, espace avant une virgule ou un point, espace
  oubliée après la ponctuation, « l' équipe », ordinaux (« 2e », « 1re » et non « 2ème »,
  « 1ère »), « À » en tête de phrase.

Chaque proposition montre le passage, la faute barrée, la correction et une explication.
**Corriger** la reporte dans le champ (**Annuler** la retire), **Tout corriger** les applique
toutes, **Ignorer** l'écarte pour cette fiche, **+ Dictionnaire** protège un nom propre ou
un mot du club partout. Rien n'est modifié sans clic, ni publié avant **Enregistrer** (la
note de version est remplie d'office). Le même bouton existe dans Accueil & bandeau,
Rubriques & menus et les outils interactifs.

Le correcteur vérifie aussi chaque fiche en tâche de fond quelques minutes après son
enregistrement : le panneau annonce alors le nombre de corrections proposées.

### Fiche match

| Onglet | Contenu |
|---|---|
| Infos | date, compétition, journée, équipes, score (prolongation, tirs au but), stade, spectateurs, arbitre, buteurs par équipe |
| Compo & événements | composition, temps forts minute par minute, réactions, brèves |
| Récit | textes (avant-match, résumé…), dans l'éditeur de texte |
| Médias | image à la une, galerie, vidéos, publications de réseaux sociaux |
| Tableaux | autres tableaux de la fiche (grille modifiable) |
| Classement & SEO | rubriques, « À la une », adresse de la page, description pour Google |
| Version EN | traduction anglaise |

**Composition** : une ligne par joueur.

- **Poste** : G gardien, D défenseur, M milieu, A attaquant (titulaires), R remplaçant,
  E entraîneur. **N°** : numéro de maillot (facultatif).
- **Joueur** : en tapant le nom, choisissez la fiche proposée ; la pastille ✓ indique une
  fiche reliée, + permet de créer la fiche manquante.
- **Buts** : minutes séparées par des virgules (« 33', 78' ») ; « s.p. » pour un penalty,
  « csc » pour un but contre son camp (compté pour l'adversaire).
- **Remplacement** : « Entrée 75' », « Sortie 81' » (ou ↑ 75' / ↓ 81').
- **Cartons** : « J 50' » (jaune), « R 80' » (rouge), « J 35' R 80' ».
- **Importer depuis un tableau** : collez un tableau copié d'Excel, de Word ou d'une page
  web ; avec une ligne d'en-tête, les colonnes sont reconnues dans n'importe quel ordre.
- Les lignes se déplacent avec ↑ / ↓ (un cran) ou en glissant l’icône quatre flèches (plus loin ; un trait jaune montre la position de dépôt).

Les statistiques des joueurs (matchs, buts, minutes, cartons), les pages saison, les
face-à-face et les records se recalculent seuls à partir des compositions.

### Fiche personne

| Onglet | Contenu |
|---|---|
| Identité | prénom, nom, nom affiché, surnom, **autres graphies dans les compositions**, rubriques (joueur, entraîneur, dirigeant, personnage), poste, nationalité ; naissance (le lieu place la personne sur la carte des origines) et décès ; statuts (formé au club, international, à l'essai, légende) ; carte de l'album du centenaire |
| Carrière | au club (arrivée, départ, premiers et derniers matchs), palmarès, après Sochaux, fiche d'identité d'origine, matchs marquants |
| Récit | textes |
| Statistiques | tableau de statistiques par saison |
| Médias, Classement & SEO, Version EN | comme pour un match |

La liste **Matchs reliés automatiquement** montre tous les matchs où la personne figure
dans une composition. Si un nom est écrit autrement dans certaines compositions
(« CAMARA Razza »), ajoutez cette graphie dans « Autres graphies » : ces matchs seront
reliés à la fiche.

### Articles, pages, objets, moments

- **Articles & pages** : texte, image à la une, galerie, tableaux ; les pages légales
  sont aussi modifiables.
- **Objets (réserves du musée)** : photo, collection, date, provenance, crédit, fiches
  liées.
- **100 moments** (Éditorial) : un moment par semaine jusqu'au centenaire, numéro,
  année, récit, fiches liées.

## 4. Médiathèque

- **Envoi** : glisser-déposer des photos ou PDF (25 Mo au plus) ; le **crédit est
  demandé** à l'envoi, avec la légende et les droits.
- **Retouche** : rotation et recadrage sans abîmer l'original (toujours récupérable).
- **Remplacer le fichier** en gardant la même photo partout où elle est utilisée.
- **« Utilisée dans »** : liste des fiches où la photo apparaît.
- **Filtres** : sans crédit, sans légende, droits à préciser, doublons, inutilisées, PDF,
  issues des contributions, récentes.
- Modification groupée : cocher plusieurs photos pour leur donner le même crédit ou les
  mêmes droits.

Bonne pratique : une photo sans crédit ni légende est signalée dans Qualité.

## 5. Éditorial

- **Accueil & bandeau** : slider « À la une » (tirage au hasard parmi les fiches cochées
  « À la une » qui ont une vraie photo — la silhouette « ? » est écartée —, ou liste choisie
  à la main), messages du bandeau « En direct du musée » (3 messages automatiques : ce
  jour-là, compte à rebours du centenaire, dernier match fiché ; plus les messages libres de
  l'équipe), introduction, palmarès, grandes époques, réserves mises en avant.
- **Rubriques & menus** : **ordre d'affichage sur le site** (manuel, chronologique ou A–Z),
  **ordre des fiches** de la rubrique (toutes, sous-rubriques comprises : ↑ / ↓, icône
  quatre flèches à glisser avec trait jaune de dépôt, clic sur le numéro pour taper une
  position, tris rapides Date ↑ / Date ↓ / A → Z, recherche dans la liste), **ordre des
  sous-rubriques**, libellés (français et anglais), descriptions.
- **100 moments** : calendrier ; les moments pas encore révélés changent de semaine par
  glisser-déposer, puis « Enregistrer le calendrier ».
- **Redirections** : anciennes adresses redirigées (301) ; onglet « Adresses
  introuvables » : adresses demandées par des visiteurs qui n'existent pas, à rediriger en
  un clic vers la bonne fiche.
- **Page d'attente** : à activer pendant une opération ; logo, texte, compte à rebours
  facultatif. Les membres connectés du back-office voient toujours le site normal ; le
  bouton d'aperçu montre la page d'attente.

## 6. Interactif

- **Quiz, frise, carte, maillots, partenaires, page « Faire un don »** : chaque outil est
  une liste d'éléments (question, date, étape, lieu, époque…) à compléter, réordonner ou
  traduire. Les contenus de départ ont été préparés à partir des fiches : **à valider**.
- **Onze & album** : candidats au vote du « Onze de légende » (résultats, date de
  révélation) et sélection des cartes de l'album (rareté : légende, classique, actuel).

## 7. Communauté

- **Contributions** (proposées par les visiteurs : corrections, photos, documents) : à
  traiter, demander une information, publier (les fichiers peuvent être versés dans la
  médiathèque ou rattachés à une fiche), refuser.
- **Messages** (formulaire de contact) : lire, répondre, attribuer, marquer comme traité.
- **Newsletter « Ce jour-là »** : abonnés, aperçu, envoi de test, envoi.
- **Dons** : jauge, liste filtrable, export CSV, ajout d'un don reçu hors ligne (chèque,
  espèces). Les reçus fiscaux existent mais sont désactivés.

## 8. Qualité

Le tableau **Qualité** liste ce qui mérite une vérification, par onglet :

- **Statistiques incohérentes** : score différent de la somme des buteurs, date du titre
  différente de la date du match, tableau de composition identique à celui d'un autre match
  (copié par erreur sur l'ancien site), statistiques personnelles incohérentes, fiches
  « à venir ».
- **Liens joueurs** : joueurs cités dans des compositions sans fiche (bouton « Créer la
  fiche »), et noms reliés automatiquement à une fiche par rapprochement (autre graphie,
  faute de frappe, nom incomplet) : à vérifier.
- **Orthographe & syntaxe** : les fiches pour lesquelles le correcteur propose des
  corrections (haute : au moins trois fautes de langue ; basse : ponctuation ou typographie
  seulement). « Corriger » ouvre la fiche avec le correcteur. Le **dictionnaire du musée**
  (lien au-dessus de la liste) contient les mots à ne jamais corriger ; les noms des joueurs,
  clubs et stades du musée sont déjà reconnus.
- **Photos sans crédit**, **lieux de naissance inconnus** (carte), **traductions à revoir**.

Chaque alerte disparaît d'elle-même une fois la fiche corrigée.

## 9. Anglais

- Le site existe en français et en anglais (`/en/…`). Le français fait foi.
- **Traductions EN** : libellés de l'interface, et traduction des fiches par l'IA Gemini
  (bouton « Traduire » sur une fiche, ou traduction automatique par la tâche planifiée),
  à relire dans l'onglet **Version EN** de chaque fiche. Une fiche modifiée en français
  signale que sa version anglaise est à revoir.

## 10. Assistant IA

La bulle en bas à droite du site répond aux visiteurs à partir des données du musée.
Système › Assistant IA : questions posées, avis des visiteurs, export, réindexation après
de grosses modifications. Les questions sont conservées pour une durée limitée (RGPD).

## 11. Administration

- **Utilisateurs** (administrateur) : inviter, changer le niveau, désactiver un compte.
- **Réglages** (administrateur) : identité du site, e-mail, clés Gemini, correcteur
  (vérification de fond, plafond quotidien d'appels à Gemini, typographie), coûts de l'IA
  (qui avance les frais, taux de change, budget mensuel), Stripe et PayPal, carte,
  centenaire, mentions légales, cookies, sauvegardes.
- **Fiches audio** : chaque fiche se raconte en 30 secondes sur le site (bouton
  « Écouter »), gratuitement avec la voix de l'appareil du visiteur. Dans l'éditeur, la carte
  « Écouter » permet de modifier le texte lu, de le faire rédiger par l'IA ou de lui donner
  une **voix IA** naturelle (environ 0,6 centime). Système › Fiches audio : essayer sur
  20 fiches puis passer tout le musée en voix IA en **traitement groupé** (moitié prix,
  environ 5 € pour toutes les fiches), suivi des envois.
- **Coûts IA** : ce que coûte Gemini, calculé à chaque appel et mis à jour à l'écran toutes
  les 10 secondes (aujourd'hui, ce mois-ci, à rembourser, budget, derniers appels avec la
  personne et la fiche concernées). Le mois terminé : relevé PDF à signer et détail CSV à
  remettre à l'association, puis « Noter le remboursement » (administrateur). Le tableau de
  bord rappelle les mois non remboursés. Budget atteint : les tâches automatiques se
  mettent en pause jusqu'au mois suivant. La facture Google fait foi.
- **Sauvegardes** (administrateurs) : une sauvegarde complète est faite chaque jour ; on peut en lancer une
  et la télécharger. Gardez-en régulièrement une copie hors du serveur.
- **Tâches planifiées** : état des tâches automatiques (publication programmée,
  statistiques, traductions, correcteur d'orthographe, newsletter, carte…), avec un bouton
  pour en lancer une tout de suite.
- **Corbeille** (lien « Voir la corbeille » sous les listes de fiches) : fiches mises à la
  corbeille, à restaurer ; la suppression définitive est réservée aux administrateurs (une
  copie reste dans l'historique des versions).
