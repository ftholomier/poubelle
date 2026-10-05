# Sochaux Rétro — guide du back-office

Pour les historiens et les administrateurs du musée en ligne. Le back-office s'ouvre à
l'adresse `/admin` du site (`https://musee.fcsochauxretro.com/admin`).

## 1. Accès

- **Invitation** : un administrateur vous invite (Système › Utilisateurs) ; vous recevez
  un lien pour choisir votre mot de passe. Mot de passe oublié : lien sur l'écran de
  connexion.
- **Déconnexion automatique** après 30 minutes sans activité (clavier, souris, dans
  n'importe quel onglet du back-office) ; « Toujours là ? » deux minutes avant, avec
  « Rester connecté ». Une saisie sans enregistrement compte comme une activité. Après
  reconnexion, retour à la même page ; une fiche en cours garde son brouillon sur l'ordinateur.
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

### Recherche sur le web (aide à l'historien)

Le panneau **Recherche sur le web** (bouton **Chercher sur le web**) demande à l'IA de
chercher avec Google ce qui concerne exactement la fiche, puis de le comparer à la saisie.
En 10 à 60 secondes, un panneau liste :

- les **divergences avec la fiche** (date, score, buteurs, affluence, arbitre, composition,
  naissance, parcours…), avec ce que dit la fiche et ce que disent les sources ;
- les **compléments** (surtout les champs vides) ;
- les **pistes à consulter** (pages, archives, photos, vidéos).

Chaque proposition porte un niveau de confiance et les pages qui l'appuient (ouvertes dans
un nouvel onglet). Rien n'est modifié : on lit la source, puis on reporte soi-même
(**Copier** met le texte dans le presse-papiers) et on enregistre avec une note de version
qui cite la source. En bas du panneau : les pages consultées et les recherches Google de
l'IA. Le dernier résultat reste consultable 30 jours (**Voir les propositions**). La
recherche n'a lieu que sur clic, jamais en tâche de fond ; activation, modèle et plafond
mensuel (300 par défaut) dans Réglages › Recherche sur le web. Coût : environ 1 centime
la recherche (les recherches Google sont gratuites jusqu'à 5 000 par mois avec Gemini 3).

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

**Une fiche = un match.** Pour un nouveau match, créez une nouvelle fiche (+ Nouveau ›
Fiche match) plutôt que de reprendre celle du match précédent. Si l'on change l'adversaire
ou la date (de plus de deux jours) d'une fiche qui a déjà des textes, une composition ou
des photos, l'enregistrement s'arrête sur « Est-ce bien le même match ? » : « Annuler » pour
un autre match (puis nouvelle fiche), « Même match : enregistrer » pour corriger une erreur
de saisie (la correction est notée dans l'historique).

Sur les fiches reprises de l'ancien site, la date et le tour écrits en toutes lettres
(« Vendredi 21 août 2026 », « 3e journée de Ligue 2 ») ne s'affichent que s'ils concordent
avec la date et la journée saisies ; sinon la page affiche les champs saisis, un encadré
jaune le signale dans l'onglet Infos et l'ancien texte est remplacé à l'enregistrement. La
page affiche la journée saisie (« J15 » → « 15e journée »).

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
- **100 moments** (Éditorial) : titre, année, date de l'événement, récit, image, fiches
  liées. Le moment se valide en choisissant sa date de parution (Planifié et sa date, puis
  Enregistrer ; « Planifier à la date anniversaire » la propose). Le numéro suit l'ordre des
  dates et ne bouge plus une fois le moment en ligne.

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
- **Murs de photos** : la fiche d'une photo dit si elle peut être tirée au hasard sur les
  murs de photos (Interactif) ou pourquoi elle ne l'est pas ; la case **Jamais sur les murs
  de photos** l'en retire, même bien créditée.

Bonne pratique : une photo sans crédit ni légende est signalée dans Qualité. Le champ Crédit
ne contient que le photographe, le journal ou la collection : une date ou une légende va dans
« Date ou époque » ou « Légende » (sinon la photo est écartée des murs de photos).

## 5. Éditorial

- **Accueil & bandeau** : slider « À la une » (tirage au hasard parmi les fiches cochées
  « À la une » dont la vraie photo — la silhouette « ? » est écartée — fait au moins
  1 200 × 600 pixels, pour rester nette en plein écran, ou liste choisie à la main),
  messages du bandeau « En direct du musée » (4 messages automatiques : ce jour-là, compte à
  rebours du centenaire, dernier match fiché, Rétro-Direct en cours ou dans les 7 jours ;
  plus les messages libres de l'équipe), introduction, palmarès, grandes époques, réserves
  mises en avant, **chiffre du jour** (un des 100 chiffres du FCSM sous « Ce jour-là », un
  nouveau chaque jour : la carte montre celui du jour, une case le masque), **teaser vidéo**
  (1 min 55, sous les compteurs ; le public le voit à l'ouverture du site). Les grandes
  époques, les réserves et les encarts (quiz, maillots, frise, contribuer) ont une **photo
  choisie au départ** dans la médiathèque, comme les dates de la frise et les époques du
  comparateur de maillots : bouton « Choisir… » pour la remplacer. **Photos trop petites
  pour le slider** : la carte du slider compte les fiches « À la une » écartées du tirage
  pour cette raison ; en sélection manuelle, l'étiquette « photo trop petite » signale une
  photo qui paraîtra floue ; dans la fiche, la case « À la une » le dit aussi. Un plus grand
  scan (Médiathèque › la photo › « Remplacer le fichier… ») fait entrer la fiche dans le
  tirage.
- **Rubriques & menus** : **ordre d'affichage sur le site** (manuel, chronologique ou A–Z),
  **ordre des fiches** de la rubrique (toutes, sous-rubriques comprises : ↑ / ↓, icône
  quatre flèches à glisser avec trait jaune de dépôt, clic sur le numéro pour taper une
  position, tris rapides Date ↑ / Date ↓ / A → Z, recherche dans la liste), **ordre des
  sous-rubriques**, libellés (français et anglais), descriptions.
- **100 moments** : calendrier de parution (date modifiable tant que le moment n'est pas en
  ligne), moments à dater avec leur date anniversaire, points à surveiller (même jour, long
  trou, sans image) et rythme à tenir jusqu'au centenaire. Sur le site, les moments à venir
  restent « À venir », sans date.
- **Boîte à idées** (100 moments) : l'IA propose des idées de moments appuyées sur les
  fiches publiées (sommaire par époque, ou piste précise) ; vous retenez, modifiez, écartez
  (la raison lui est rappelée) ou ajoutez les vôtres ; « Premier jet (IA) » crée la fiche
  « À relire » avec ses sources et les points à vérifier, « Écrire moi-même » une fiche
  préremplie. L'IA ne date ni ne publie rien ; rien ne l'indique sur le site.
- **Redirections** : anciennes adresses redirigées (301) ; onglet « Adresses
  introuvables » : adresses demandées par des visiteurs qui n'existent pas, à rediriger en
  un clic vers la bonne fiche.
- **Page d'attente** : **active dès l'installation**, à décocher le jour de l'ouverture (puis à
  réactiver pendant une opération) ; logo, texte, compte à rebours facultatif, teaser vidéo
  (décoché par défaut : la vidéo reste secrète). Tant qu'elle est active, rien n'est indexé
  par les moteurs de recherche et la page n'a aucun lien vers le back-office. Les membres
  **connectés** (par `/admin`) voient le vrai site, avec un bandeau jaune « Site fermé au
  public » ; déconnectés, la page d'attente. Boutons d'aperçu : la page telle que la voient
  les visiteurs, et avec le teaser. Elle ne concerne que le musée : le site de l'association
  a la sienne (§ 12).

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
- **Kit souvenirs** (Raconte-moi Bonal) : chaque mois, un PDF en gros
  caractères à imprimer pour les anciens supporters (le grand match d'il y a N ans,
  « Vous les reconnaissez ? », le quiz des anciens, « Racontez-nous » avec QR code). Pour le
  mois en cours et les deux suivants : match choisi automatiquement ou par vous (autres
  propositions, ou n'importe quel match), mot d'introduction, PDF à vérifier ; liste des
  souvenirs publiés.
- **Murs de photos** : quatre pages du site (planche-contact, journal « Le Lion illustré »,
  mur du vestiaire, grande mosaïque) tirent des photos de la médiathèque au hasard à chaque
  visite, avec un filtre par décennie et par photographe. Seules les photos sûres y vont :
  crédit renseigné, ni « DR » ni crédit exclu (agences, presse nationale, télévision, sites
  web), photo d'une fiche publiée, assez grande. L'écran montre les photos écartées par
  raison (un clic ouvre la médiathèque sur elles : « crédit sans auteur » = une date ou une
  légende à la place du crédit, à corriger), la liste des crédits exclus (modifiable, une
  ligne par crédit), tous les crédits montrés et les vignettes préparées d'avance.
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
- **Notifications** (administrateurs) : appareils abonnés à l'appli du musée (total et par
  sujet), envoi d'une notification de l'équipe (titre, texte, page ouverte au clic, sujet,
  version anglaise facultative, aperçu), envois automatiques prêts à partir (coup d'envoi des
  Rétro-Direct, parution des 100 moments, kit du mois, « Ce jour-là »), historique avec les
  ouvertures. Réglages › Application du musée : appli installable, notifications, envois
  automatiques, heure de « Ce jour-là ».
- **L'appli du musée** (page publique /appli/) : installation sur l'écran d'accueil, lecture
  hors connexion des pages déjà vues, abonnement aux notifications. Sur téléphone, un bandeau
  discret invite à l'installer à partir de la 2e page vue (« Plus tard » : 30 jours).
- **Annonce de l'ouverture** : la page d'attente propose « Prévenez-moi de l'ouverture » ;
  Communauté › Notifications compte les inscrits et prépare l'annonce (français et anglais).
  Le jour J : ouvrir le musée d'abord, puis envoyer ; elle ne part qu'une fois.
- **Dons** : jauge, liste filtrable, export CSV, ajout d'un don reçu hors ligne (chèque,
  virement, espèces ; administrateurs). Les reçus fiscaux existent mais sont désactivés ;
  activés, ils sont émis et consultés par les administrateurs.

## 8. Qualité

Le tableau **Qualité** liste ce qui mérite une vérification, par onglet :

- **Statistiques et dates** : score différent de la somme des buteurs, textes d'un autre
  match sous l'en-tête de celui-ci (adversaire jamais nommé, réaction de l'entraîneur d'un
  autre club : l'IA ne raconte pas la fiche avant correction), date ou tour en toutes
  lettres de l'ancien site qui contredit la fiche (« 3e journée de Ligue 2 » pour un amical,
  « 38e journée » pour la J3, « de D2 » pour un match de Division 1), match de coupe rangé en
  championnat, tableau de composition identique à celui d'un autre match
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
  20 fiches puis passer toutes les fiches en voix IA (carte « Toutes les fiches en voix IA ») en
  **traitement groupé** (moitié prix, environ 40 € pour toutes les fiches en 3 minutes au plus),
  suivi des envois. Les pages de synthèse n'y sont pas comprises : elles se lancent à part.
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
  « Lancer pour toutes les pages ». Rien n'est dépensé avant ce lancement ; ensuite, la rédaction de
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

## 11 bis. Boutique (administrateurs)

- **Boutique › Tableau de bord** : ventes, marge, alertes, rapprochement avec Stripe.
- **Boutique › Relevés imprimeur** : relevé mensuel (PDF, tableur) de ce que l'imprimeur facture, et marge.
- **Boutique › Commandes** : commandes par étape ; dans une commande : PDF, messages, étape, remboursement, paiement hors ligne.
- **Boutique › Réglages** : ouverture, port, délai, imprimeur (e-mail qui ouvre son espace /imprimeur/), alertes, conditions de vente.
- **Boutique › Supports** : dimensions de la zone imprimable, fonds perdus, couleurs proposées et consignes de l'imprimeur pour chaque produit.
- **Boutique › Banque de textes** : listes de phrases (slogans, anecdotes…). Seules les phrases « Validée » sont proposées au client ; « Proposer des phrases avec l’IA » en ajoute, à valider.
- **Boutique › Modèles** : créer, dupliquer, activer ou supprimer un modèle. Dans l'éditeur, ajoutez le logo, des textes, des formes et des « champs du client » (prénom, numéro…) ; l'aperçu sur le produit et le fichier d'impression se mettent à jour. « PDF imprimeur » télécharge le fichier vectoriel prêt à imprimer.
  L'ordre de la boutique se règle en glissant les modèles par leur poignée ⠿.
- **Vente** (dans l'éditeur) : prix, supplément par taille, part de l'imprimeur (% ou € par
  article), couleurs du produit ou du fond, couleurs des textes (seules les lisibles sont
  proposées), taille et position du texte. Posters : un **prix par format** (A4, A3, A2).
- **Pièces uniques — anecdote tirée par le client** : champ « + Anecdote tirée par le client ».
  Le client peut choisir un sujet (match, joueur, entraîneur) dans les propositions du musée ;
  l'IA rédige d'après la fiche (records, chiffre clé, histoire, coulisses d'abord), chaque
  phrase est vérifiée (aucun nom ni nombre hors de la fiche), signée, et jamais revendue.
  Sujet sans matière : le client est prévenu et reçoit une anecdote générique.
- **Poster souvenir d'un match** : support « Poster (A4, A3, A2) », calque « + Poster souvenir
  du match ». Le client choisit son match (fiches complètes seulement), donne prénom, nom et
  format ; le poster est dédicacé et numéroté à la commande. Coût IA : ligne « Boutique
  (posters souvenirs) » de Coûts IA.
- Fiche produit : aperçu en direct, **zoom** en grand d'un clic, **vue 3D**.
- **Réglages** : budget IA du jour (anecdotes et posters) ; conditions de vente vides = texte proposé.

## 12. Site de l'association (administrateurs)

Le pavé **Site de l'association** du menu pilote le site www.fcsochauxretro.com (présentation,
actions, actualités, agenda, adhésion, bénévolat, contact). Il n'apparaît que pour les
administrateurs ; les autres comptes ne le voient pas et n'y ont pas accès.

- **Tableau de bord** : site ouvert ou fermé (page d'attente au départ), bouton « Aperçu
  complet » (le site entier, même fermé, avec les contenus « à vérifier » signalés en rouge),
  **Ouvrir le site au public** / « Fermer le site », liste **À vérifier** (points rouges
  d'abord : tarifs d'exemple, e-mail de réception, mentions légales), adhérents de l'année,
  chèques attendus, propositions de bénévolat, messages reçus, audience.
- **Page d'attente** : celle du site de l'association, montrée tant qu'il est fermé, distincte
  de celle du musée (page claire en deux colonnes, photo de la tribune de Bonal « qui attend
  son public », créditée « Photo : FC Sochaux-Montbéliard », et pastille « Bientôt »). Texte
  et photo (légende, crédit : vide, celui de la médiathèque), liste « Ce qui vous attend »,
  inscription à la lettre « Ce jour-là » (elle fonctionne site fermé), encart du musée en
  ligne (bouton « Visiter le musée » une fois celui-ci ouvert, teaser vidéo facultatif),
  e-mail et réseaux sociaux, compte à rebours facultatif. « Aperçu de la page d'attente »
  la montre telle que la voient les visiteurs ; « Revenir à la page de départ » la rétablit.
- **Contenus**, par onglets : textes des **pages**, **actions** (une page chacune, dans l'ordre
  du menu), **actualités** et **agenda** (datés ; brouillon possible ; les Rétro-Direct du musée
  et le centenaire s'ajoutent seuls à l'agenda), **équipe** (un membre n'est affiché que si son
  nom est saisi ; présentée en cartes à collectionner : photo, nom, rôle, mission, quelques mots, anecdote ; mêmes cartes sur le musée, Supporters › L'équipe de Sochaux Rétro) et pôles de bénévoles, **partenaires**, **presse** (revue de presse),
  **documents** (statuts, comptes rendus : « Déposer un fichier » puis Enregistrer), **tarifs**
  d'adhésion. Liens des boutons : « /page/ » (ce site), « musee:/page/ » (le musée),
  « https://… » (autre site), « social:youtube » (réseau social réglé).
- **À vérifier** : ce qui n'a pas pu être connu en préparant le site est coché « à vérifier ».
  Une actualité ou un événement à vérifier reste invisible du public tant que la case n'est
  pas décochée. Aucun nom de personne, de partenaire ni article de presse n'a été inventé.
- Textes de départ : mis à jour avec le code tant qu'ils n'ont pas été modifiés ; dès le
  premier enregistrement, ils sont à vous (« Revenir au contenu de départ » les rétablit,
  votre version reste dans l'historique).
- **Adhésions** : en ligne (carte bancaire ou PayPal, avec les clés des dons), par chèque
  (« Règlement attendu » jusqu'à « Marquer comme payée »), sur papier (« + Adhésion papier ») ;
  filtres par année et statut, e-mail de bienvenue, export CSV.
- **Bénévoles** : propositions reçues, suivi (contacté, actif, classé), note interne, export.
- Les **messages** du formulaire de contact du site arrivent dans Communauté › Messages, avec
  l'étiquette « Association ».
- **Réglages du site** : adresse et noms redirigés, nom et signature, e-mail de réception,
  téléphone et adresse postale, adhésion en ligne, lien HelloAsso, LinkedIn, titre et
  description pour Google.

