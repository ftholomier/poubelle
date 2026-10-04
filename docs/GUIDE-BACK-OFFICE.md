# Sochaux Rétro — guide du back-office

Pour les historiens et les administrateurs du musée en ligne. Le back-office s'ouvre à
l'adresse `/admin` du site (`https://musee.fcsochauxretro.com/admin`).

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
| Système | Traductions EN, Assistant IA, Utilisateurs, Réglages, Sauvegardes, Tâches planifiées, Mises à jour |

- **Recherche globale** : `Ctrl + K` (ou la barre en haut) trouve une fiche, une photo,
  un écran.
- **Tableau de bord** : chiffres du musée, dernières modifications, choses à faire
  (photos à créditer, joueurs sans fiche, contributions à traiter…).
- **« + Nouveau »** en haut : créer un match, une personne, un article, un objet…
- **Favoris** (ligne ★ sous le titre) : « + Ajouter un favori » ouvre la liste de tout ce
  qu'on peut y mettre (la page affichée, les écrans du menu, les créations, les groupes des
  Réglages pour les administrateurs, avec une recherche) ; vos favoris s'y renomment, se
  déplacent et se retirent. Douze au plus, propres à chaque compte.

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
  à la main), messages du bandeau « En direct du musée » (4 messages automatiques : ce
  jour-là, compte à rebours du centenaire, dernier match fiché, Rétro-Direct en cours ou dans
  les 7 jours ; plus les messages libres de l'équipe), introduction, palmarès, grandes
  époques, réserves mises en avant, **chiffre du jour** (un des 100 chiffres du FCSM sous
  « Ce jour-là », un nouveau chaque jour : la carte montre celui du jour, une case le masque),
  **teaser vidéo** (1 min 55, sous les compteurs ; le public le voit à l'ouverture du site).
  Les grandes époques, les réserves et les encarts (quiz, maillots, frise, contribuer) ont
  une **photo choisie au départ** dans la médiathèque, comme les dates de la frise et les
  époques du comparateur de maillots : bouton « Choisir… » pour la remplacer.
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
- **Page d'attente** : **active dès l'installation**, à décocher le jour de l'ouverture (puis à
  réactiver pendant une opération) ; logo, texte, compte à rebours facultatif, teaser vidéo
  (décoché par défaut : la vidéo reste secrète). Tant qu'elle est active, rien n'est indexé
  par les moteurs de recherche et la page n'a aucun lien vers le back-office. Les membres
  **connectés** (par `/admin`) voient le vrai site, avec un bandeau jaune « Site fermé au
  public » ; déconnectés, la page d'attente. Boutons d'aperçu : la page telle que la voient
  les visiteurs, et avec le teaser.

## 6. Interactif

- **Quiz, frise, carte, maillots, partenaires, page « Faire un don »** : chaque outil est
  une liste d'éléments (question, date, étape, lieu, époque…) à compléter, réordonner ou
  traduire. Les contenus de départ ont été préparés à partir des fiches : **à valider**.
- **Onze & album** : candidats au vote du « Onze de légende » (résultats, date de
  révélation, résultats cachés au public jusque-là) et sélection des cartes de l'album
  (rareté : légende, classique, actuel, ou « Auto » selon la carrière).
- **Rétro-Direct** : un match rejoué en direct sur le site le jour et à l'heure choisis
  (temps forts à leur minute, score qui change à la minute des buts, remplacements,
  mi-temps de 15 minutes, prolongation et tirs au but ; compteur de spectateurs connectés,
  réactions, « J'y étais ! »). « Anniversaires à venir » propose les anniversaires ronds
  (10, 20, 25, 30, 40, 50 ans…) des 30 à 365 prochains jours, les plus marquants d'abord
  retenus : **Programmer à 20 h** en un clic. « Programmer un match » : n'importe quel match
  (au moins 4 temps forts avec leur minute), date (le prochain anniversaire est proposé),
  heure du coup d'envoi, présentation facultative (français, anglais). « Au programme » :
  voir, modifier, retirer ; public des directs passés (pic de spectateurs, réactions). Aucun
  coût : tout vient de la fiche du match.
- **Kit souvenirs** (les Après-midi Bonal) : chaque mois, un PDF de 4 pages en gros
  caractères à imprimer pour les anciens supporters (le grand match d'il y a N ans,
  « Vous les reconnaissez ? », le quiz des anciens, « Racontez-nous » avec QR code). Pour le
  mois en cours et les deux suivants : match choisi automatiquement ou par vous (autres
  propositions, ou n'importe quel match), mot d'introduction, PDF à vérifier ; liste des
  souvenirs publiés.
- **Les chiffres du FCSM** (site, Matchs › Explorer et Interactif › Explorer l'histoire,
  adresse `/chiffres/`) : 100 statistiques en 11 chapitres, rien à saisir. Elles viennent des
  tableaux de statistiques des fiches joueurs (records de carrière depuis 1929), des fiches
  match (compositions, buteurs, affluences), des temps forts (passes décisives, remontadas) et
  des fiches des personnes (âges, tailles, origines). Un chiffre surprenant signale souvent une
  donnée à corriger (date de naissance, composition recopiée) : corrigez la fiche, la page se
  recalcule.
- **Fil jaune** (site, Interactif › Jouer) : rien à saisir, le site relie les joueurs par les
  compositions des matchs (joueurs reliés à leur fiche). Chaque composition complétée,
  surtout d'avant 1980, ajoute des liens ; une chaîne étonnante signale parfois un homonyme
  relié à la mauvaise fiche.

## 7. Communauté

- **Contributions** (proposées par les visiteurs : corrections, photos, documents) : à
  traiter, demander une information, publier (les fichiers peuvent être versés dans la
  médiathèque ou rattachés à une fiche), refuser. Un **témoignage** rattaché à une fiche de
  match se publie à la validation dans le bloc « Ils y étaient » de la fiche (texte et
  signature relus, case « Publier ce souvenir »).
- **Messages** (formulaire de contact) : lire, répondre, attribuer, marquer comme traité.
- **Newsletter « Ce jour-là »** : abonnés, aperçu, envoi de test, envoi.
- **Dons** : jauge, liste filtrable, export CSV, ajout d'un don reçu hors ligne (chèque,
  virement, espèces ; administrateurs). Les reçus fiscaux existent mais sont désactivés ;
  activés, ils sont émis et consultés par les administrateurs.

## 8. Qualité

Le tableau **Qualité** liste ce qui mérite une vérification, par onglet :

- **Statistiques et dates** : score différent de la somme des buteurs, date du titre
  différente de la date du match, tableau de composition identique à celui d'un autre match
  (copié par erreur sur l'ancien site), statistiques personnelles incohérentes, tableau de
  statistiques identique sur plusieurs fiches de joueurs (modèle recopié : la fiche affiche
  les chiffres d'un autre joueur), dates d'une personne impossibles ; matchs sans date,
  rangés dans une autre saison, au résultat incohérent avec le score, officiels sans score,
  saisis deux fois ; compositions avec un entrant noté titulaire, plus de 11 titulaires ou
  deux gardiens.
- **À compléter** : « xx » de l'ancien site, fiches « à venir », arbitre, liens vidéo
  cassés, personne sans rubrique.
- **Liens joueurs** : joueurs cités dans des compositions sans fiche (bouton « Créer la
  fiche »), noms reliés automatiquement à une fiche par rapprochement (autre graphie,
  faute de frappe, nom incomplet) : à vérifier ; deux fiches de personnes au même nom
  (doublon, ou homonymes à distinguer par la date de naissance).
- **Adresses et médias** : deux fiches à la même adresse, adresse mal formée, rubrique ou
  image supprimée, fichier de fiche abîmé ou retouché à la main dans un format inattendu
  (l'ouvrir et l'enregistrer suffit à le réparer) ; redirections à revoir (chacune suivie
  comme par un visiteur), adversaires ou stades en double, rubriques orphelines.
- **Orthographe & syntaxe** : les fiches pour lesquelles le correcteur propose des
  corrections (haute : au moins trois fautes de langue ; basse : ponctuation ou typographie
  seulement). « Corriger » ouvre la fiche avec le correcteur. Le **dictionnaire du musée**
  (lien au-dessus de la liste) contient les mots à ne jamais corriger ; les noms des joueurs,
  clubs et stades du musée sont déjà reconnus.
- **Photos sans crédit**, **lieux de naissance inconnus** (carte), **traductions à revoir**
  (fiches dont le français a changé depuis la version anglaise, textes de l'interface mal
  traduits).

Chaque alerte disparaît d'elle-même une fois la fiche corrigée.

**Contrôler maintenant** (en haut de l'écran, pour tous les comptes) refait toutes les
vérifications sur toutes les fiches, en quelques secondes, et les compare au contrôle
précédent : le message donne le nombre d'anomalies **nouvelles** et **corrigées**, la liste
des nouvelles s'ouvre (tous onglets), chacune marquée « Nouveau » jusqu'au contrôle suivant.
Une anomalie apparue entre deux contrôles est marquée tout de suite, et le tableau de bord
la rappelle dans « À faire ». Le premier contrôle se compare au contrôle complet du
4 octobre 2026. Les propositions du correcteur ne sont « nouvelles » que si le texte de la
fiche a changé. Quatre contrôles au plus toutes les deux minutes par compte. Bon réflexe :
un contrôle à la fin de chaque séance de saisie.

## 9. Anglais

- Le site existe en français et en anglais (`/en/…`). Le français fait foi.
- **Traductions EN** : libellés de l'interface, et traduction des fiches par l'IA Gemini
  (bouton « Traduire » sur une fiche, ou traduction automatique par la tâche planifiée),
  à relire dans l'onglet **Version EN** de chaque fiche. Une fiche modifiée en français
  signale que sa version anglaise est à revoir.

## 10. Assistant IA

La bulle en bas à droite du site répond aux visiteurs à partir des données du musée.
Système › Assistant IA (administrateurs) : questions posées, avis des visiteurs, export,
réindexation après de grosses modifications. Les questions sont conservées pour une durée limitée (RGPD).

## 11. Administration

Réservés aux administrateurs : Utilisateurs, Réglages, Assistant IA, Fiches audio (écran du
traitement groupé), Coûts IA, Sauvegardes et Tâches planifiées ; dans Communauté › Dons,
l'enregistrement des dons hors ligne (et de leurs remboursements) et les reçus fiscaux. Les
montants dépensés en IA ne s'affichent que pour eux. Tout le reste, y compris les alertes de
Pilotage › Qualité et la liste des dons, est ouvert à tous les comptes.

- **Utilisateurs** (administrateur) : inviter, changer le niveau, désactiver un compte.
- **Mises à jour** (administrateur) : nouvelle version du site sur GitHub, liste des
  changements, « Appliquer la mise à jour » en un clic (le code seulement, jamais les fiches,
  médias, réglages ni comptes), sauvegarde et « Revenir à cette version ». Vérification
  automatique toutes les 3 heures, signalée au tableau de bord. Chaque vérification compare
  aussi le code du serveur à GitHub, fichier par fichier : version reconnue après un envoi
  par FTP, fichier oublié ou retouché signalé (« Synchroniser avec GitHub »).
- **Réglages** (administrateur) : identité du site, e-mail, **pied de page** (titre, phrase,
  deux boutons et leurs liens, accroche, ligne du bas et mention « Propulsé par », en
  français et en anglais ; un texte vide masque l'élément), clés Gemini, correcteur
  (vérification de fond, plafond quotidien d'appels à Gemini, typographie), coûts de l'IA
  (qui avance les frais, taux de change, budget mensuel), Stripe et PayPal, carte,
  centenaire, mentions légales, cookies, sauvegardes.
- **Fiches audio** : chaque fiche est expliquée à voix haute sur le site (bouton
  « Écouter »), en entier, dans la durée maximale réglée (3 minutes par défaut), gratuitement
  avec la voix de l'appareil du visiteur. Rédigé par l'IA, le texte raconte la fiche comme un
  historien (accroche, décor, récit en paragraphes, conclusion, uniquement les faits de la
  fiche) ; Système › Fiches audio › « Réécrire les textes avec l'IA » les refait tous, sans voix
  IA (environ 1 € avec Flash-Lite ; un modèle Flash, réglable à part, raconte mieux). Dans l'éditeur, la carte
  « Écouter » permet de modifier le texte lu, de le faire rédiger par l'IA ou de lui donner
  une **voix IA** naturelle (environ 3 centimes pour 3 minutes). Système › Fiches audio (administrateurs) : essayer sur
  20 fiches puis passer tout le musée en voix IA en **traitement groupé** (moitié prix,
  environ 40 € pour toutes les fiches en 3 minutes au plus), suivi des envois.
  Les **pages de synthèse** ont aussi leur bouton « Écouter » : face-à-face, saisons, bilans
  (Coupe de France, championnat, Europe, stade Bonal…), livre des records et chiffres du FCSM.
  L'IA les raconte comme un historien, en français et en anglais, à partir de leurs chiffres et
  des fiches de leurs grands matchs (premier et dernier match, plus belles victoires, finales,
  buteurs, séries, bilan de la saison). Chaque nuit, les récits manquants ou dont les chiffres ont
  changé sont rédigés en traitement groupé (moins d'un euro pour tout le musée avec Flash-Lite),
  puis enregistrés par la **voix IA** (la voix des fiches, en français et en anglais : environ 15 à
  20 € pour tout le musée, le tarif des voix doublant au 1er janvier 2027) ; en attendant, un
  récit automatique, gratuit, est lu par la voix de l'appareil. Système › Fiches audio › carte
  « Pages de synthèse racontées par l'IA » : **essayer d'abord sur une page** (coller son adresse,
  par exemple `/face-a-face/nancy/` : récit et voix tout de suite, quelques centimes), puis
  « Lancer pour tout le musée ». Rien n'est dépensé avant ce lancement ; ensuite, la rédaction de
  nuit prend le relais. Réglages › Fiches audio : rédaction de nuit et voix IA des pages.
  Pour **une fiche**, l'essai se fait dans l'éditeur, carte « Écouter la fiche » : « Rédiger avec
  l'IA » puis « Voix IA ».
- **Coûts IA** (administrateurs) : ce que coûte Gemini, calculé à chaque appel et mis à jour à l'écran toutes
  les 10 secondes (aujourd'hui, ce mois-ci, à rembourser, budget, derniers appels avec la
  personne et la fiche concernées). Le mois terminé : relevé PDF à signer et détail CSV à
  remettre à l'association, puis « Noter le remboursement » (administrateur). Le tableau de
  bord rappelle les mois non remboursés. Budget atteint : les tâches automatiques se
  mettent en pause jusqu'au mois suivant. La facture Google fait foi.
- **Sauvegardes** (administrateurs) : une sauvegarde complète est faite chaque jour ; on peut en lancer une
  et la télécharger. Gardez-en régulièrement une copie hors du serveur.
- **Tâches planifiées** (administrateurs) : état des tâches automatiques (publication programmée,
  statistiques, traductions, correcteur d'orthographe, newsletter, carte…), avec un bouton
  pour en lancer une tout de suite ; carte **Serveur** : version de PHP, extensions, dossiers
  inscriptibles et dernières erreurs du journal PHP (un réglage manquant est aussi rappelé en
  tête du tableau de bord).
- **Corbeille** (lien « Voir la corbeille » sous les listes de fiches) : fiches mises à la
  corbeille, à restaurer ; la suppression définitive est réservée aux administrateurs (une
  copie reste dans l'historique des versions).
