# Sochaux Rétro — décisions validées

Récapitulatif des choix pris avec le client, à respecter pendant le développement.

## Périmètre
- Refonte complète en PHP natif + JSON + HTML/CSS/JS, **sans base de données**.
- Seul `/public` est exposé ; code, données et réglages hors de `/public`.
- **Tout le contenu actuel doit être repris** (textes, chiffres, tableaux, médias, vidéos) ;
  contrôle automatique de complétude avant mise en ligne.
- **Aucune initiative de réorganisation** : on reste sur l'arborescence actuelle
  (articles + catégories, méga-menus, mosaïques avec image à la une, galeries).
- Développement **uniquement après le feu vert du client** et réception de la maquette.

## Contenus
- Fiches : joueur (dont « à l'essai »), entraîneur, dirigeant, personnage emblématique,
  match, articles thématiques (infrastructures, symboles, supporters).
- Tableaux de composition et de statistiques conservés **en tableaux** ; ils proviennent
  de wpDataTables (stockage séparé) et doivent être repris explicitement.
- Vidéos : intégrations YouTube (aucun fichier vidéo hébergé).
- Slider d'accueil : 5 articles tirés au hasard dans « À la une » à chaque chargement.

## Fonctionnalités demandées
- Back-office pour les historiens (masques de saisie par type).
- Réglages (équivalent `.env`) dans le back-office, secrets chiffrés.
- Moteur de recherche + assistant IA **Gemini** (RAG sur toutes les données) :
  public, bulle **en bas à droite** sur tout le site, liste des modèles chargée
  dynamiquement depuis la clé API, questions conservées et consultables (RGPD).
- Traduction : **FR et EN uniquement**.
- Maquette : recherche plein écran, « Ce jour-là », compte à rebours centenaire
  (**20 mai 2028**, date exacte de création du club), dons (Stripe + PayPal, ponctuel/mensuel, paliers, jauge, mur),
  frise 1928→2028, quiz, comparateur de maillots, contact dynamique + partenaires.
- Cartographie : **OpenStreetMap** (Leaflet), cartes dynamiques avec listes et filtres.
- Sécurité, antispam, cookies (consentement), SEO.

## URL — option B (validée, schéma validé)
Adresses porteuses de mots-clés + **redirection 301 de chaque ancienne adresse** :
- `/joueurs/{slug}/`, `/entraineurs/{slug}/`, `/dirigeants/{slug}/`,
  `/personnages-emblematiques/{slug}/`
- `/matchs/{saison}/{domicile}-{exterieur}-{competition}-{jj-mm-aaaa}/` (sans le score)
- `/infrastructures/{slug}/`, `/symboles/{slug}/`, `/supporters/{slug}/`
- Rubriques : `/matchs/coupe-de-france/`, `/nos-lions/joueurs/formes-au-club/`, …
- Anglais : préfixe `/en/`.
- Suffixes parasites supprimés ; homonymes distingués par les années.
- Personne à plusieurs rôles : une seule adresse (premier rôle), présente dans toutes ses rubriques.
- Adresse modifiable dans le back-office → redirection automatique depuis l'ancienne.

## Fonctionnalités complémentaires (toutes validées)
1. **Historique des versions** de chaque fiche (qui, quand, quoi, restauration) +
   sauvegarde automatique quotidienne des données hors serveur.
2. **Liens automatiques joueurs ↔ matchs** : noms des compositions reliés aux fiches ;
   chaque fiche joueur liste tous ses matchs (buts, cartons).
3. **Face-à-face** par adversaire, généré automatiquement :
   - encadré sur chaque fiche match : bilan global, **bilan à la date du match**,
     5 précédentes confrontations, lien vers la page complète ;
   - page « Face-à-face » avec **choix de l'équipe** (suggestions + liste des adversaires),
     filtres compétition / période / domicile-extérieur, bilan, graphique, records,
     buteurs sochaliens, liste triable ; une URL par adversaire (`/face-a-face/{club}/`) ;
   - même moteur de bilans décliné **par compétition** (ex. « Sochaux en Coupe de France » :
     bilan, parcours par saison, meilleur parcours, buteurs, records) et **par stade**
     (ex. « Sochaux à Bonal » : bilan à domicile, séries, affluences, records) ;
     URLs `/bilans/coupe-de-france/`, `/bilans/stade-auguste-bonal/` ;
   - **référentiel des clubs** (regroupement des variantes de noms, pré-rempli puis validé
     dans le back-office ; logo, ville, stade).
4. **Records** calculés automatiquement (buteurs, joueurs les plus utilisés, affluences,
   plus larges victoires, séries), filtres décennie / compétition.
5. **« Contribuer au musée »** : propositions de corrections, photos, documents
   (cession de droits), file de validation dans le back-office.
6. **« 100 ans, 100 moments »** : série éditoriale liée à la frise et au compte à rebours.
7. **Vote du « Onze de légende du centenaire »** (composition sur terrain, résultat global).
8. **Album de cartes à collectionner** (cartes joueurs débloquées en visitant / via le quiz,
   progression mémorisée dans le navigateur, sans compte).
9. **Tableau de bord qualité** pour les historiens (fiches « à venir », photos sans crédit,
   tableaux en double, statistiques incohérentes).
10. **Pages saison enrichies** (effectif, résultats, buteurs calculés).
11. **Images de partage générées** automatiquement pour chaque fiche.
12. **Newsletter « Ce jour-là »** hebdomadaire automatique.
13. **Accessibilité** (RGAA : lecteurs d'écran, contrastes, navigation clavier).

## Back-office (aussi important que le front)
Simple, ergonomique, intuitif **et** complet, pensé pour des historiens non techniciens.
- Masque de saisie dédié par type de fiche ; aides sous chaque champ ; vocabulaire métier.
- Brouillon enregistré automatiquement, historique des versions + restauration,
  alerte en cas de modification simultanée.
- Recherche globale au clavier (Ctrl+K), compositions et statistiques saisies en grille,
  joueurs choisis parmi les fiches existantes (création à la volée).
- Aperçu « comme sur le site » ; statuts brouillon / à relire / publié + programmation.
- Médiathèque : glisser-déposer, recadrage, légende et crédit obligatoires,
  « où cette photo est utilisée ».
- Écrans : tableau de bord, fiches, médiathèque, rubriques/menus/ordre des mosaïques,
  accueil, frise, quiz, maillots, partenaires, cartes, contributions, messages, dons,
  assistant IA, qualité, utilisateurs, réglages.
- **Deux niveaux d'accès** (remplace la proposition à 3 rôles) :
  - **Administrateur** : tout.
  - **Utilisateur** : tout **sauf** gestion des utilisateurs et invitations,
    réglages (clés API, paiements, e-mail…), suppression définitive et restauration
    d'anciennes versions (il peut mettre à la corbeille et consulter l'historique).
    Il a donc accès aux rubriques, menus, ordre des mosaïques et aux dons.
- Utilisable sur tablette.

## Rubrique « INTERACTIF » (validée)
Nouvelle entrée du menu principal regroupant les contenus où le visiteur agit,
méga-menu en 3 colonnes :
- **Explorer l'histoire** : face-à-face, bilans par compétition, bilans par stade,
  records, cartes, frise 1928→2028, comparateur de maillots.
- **Jouer** : quiz, album de cartes à collectionner.
- **Participer** : Onze de légende du centenaire, contribuer au musée,
  Ce jour-là + newsletter, guide IA.
- « Faire un don » reste un bouton permanent de l'en-tête.
- Page d'arrivée `/interactif/` : mosaïque de grandes cartes illustrées.
- URLs `/interactif/{outil}/` ; pages générées gardent leurs URLs (`/face-a-face/{club}/`…).
- Menu principal : ACCUEIL (conservé), MATCHS, NOS LIONS, SUPPORTERS, INFRASTRUCTURES,
  SYMBOLES, INTERACTIF.

## Réponses du client (questionnaire)
- **Hébergement** : o2switch (actuel).
- **Saisies WordPress** : **gel dès maintenant** — l'aspiration en cours est la version
  définitive du contenu (pas de resynchronisation prévue avant la bascule).
- **Reçus fiscaux** : statut inconnu → fonction développée **désactivée**, activable
  dans les réglages.
- **Quiz, frise, maillots** : pré-remplis par Claude à partir des fiches existantes,
  à valider par les historiens dans le back-office.
- **Gemini** : le client a déjà une clé (saisie dans les réglages du back-office).
- **Stripe / PayPal** : aucun compte → développement et tests en mode test ;
  création des comptes avant la mise en ligne.
- **Mentions légales / confidentialité** : base rédigée par Claude, à faire relire.
- **Utilisateurs du back-office** : plus de 10 → invitations par e-mail,
  suivi d'activité par personne, deux niveaux (administrateur / utilisateur).

## Maquette (reçue, validée) — `docs/maquette/`
Design **à respecter strictement**, réécrit en HTML/CSS/JS natif (pas le runtime React de l'outil).
- Couleurs : marine `#0E1F4D`, jaune `#F6C400`, crème `#F3EDDF`, bleu `#1F3FA8`,
  papier `#FFFDF6`, sable `#E8DFC9`, texte secondaire `#3A4A75`.
- Polices : Big Shoulders Display (titres/chiffres/boutons) + Newsreader (texte),
  **auto-hébergées** (pas de Google Fonts côté visiteur, RGPD).
- Style : bordures 2 px marine, ombres portées décalées, pas d'arrondis ; animations
  (ticker, révélations, score, cartes) avec respect de `prefers-reduced-motion`.
- Éléments ajoutés par la maquette : bandeau « En direct du musée », bouton taille du texte,
  bande palmarès, « Grandes époques », « Réserves du musée », « Ils ont porté le lion »,
  page Saison, album 120 cartes (Légende / Classique / Actuel), 100 moments hebdomadaires,
  carto plein écran (stades, origines, épopées, lieux + curseur du temps), mosaïques avec
  recherche / tri / mosaïque-liste / « Afficher plus », images de partage + newsletter,
  back-office (versions + restauration, contributions, qualité, sauvegardes).
- À dessiner dans le même style (absent de la maquette) : bulle assistant IA, fiches
  entraîneur / dirigeant / personnage / article thématique, vidéo des matchs, autres écrans BO.
- Frise : « 1928 → aujourd'hui » comme la maquette.

### Arbitrages maquette ↔ site actuel
- **Méga-menus pour toutes les rubriques** (Nos Lions, Supporters, Infrastructures,
  Symboles créés dans le style du méga-menu MATCHS, avec toutes les sous-rubriques actuelles).
- **Filtres Nos Lions** : toutes les sous-rubriques actuelles + filtre par poste.
- **Composition** : terrain (titulaires) **+ tableau complet** (remplaçants, entraîneur,
  buts, remplacements, cartons).
- **Accueil** : bande palmarès **et** compteurs du musée.
- **Fiches** : tout le contenu existant (identité complète, récits, chiffre clé, galerie,
  statistiques) affiché dans le style de la maquette.
- **Réserves du musée** : nouveau type de contenu **« Objet »** (photo, catégorie, date,
  description, crédit, fiches liées), alimenté par les historiens et les contributions
  validées ; pré-classement des médias existants à valider.
- **Album** : sélection par les historiens dans le BO (dans l'album, rareté, numéro) ;
  première sélection proposée par Claude.
- **Grandes époques + légendes** : rédigées / proposées par Claude, à valider dans le BO.

## Back-office — maquette complète (v2, `docs/maquette/Back-office.dc.html`)
**Maquette graphique** : le style est à respecter, le contenu est à adapter à ce qui est
réellement développé.
- Menu par groupes : Pilotage (tableau de bord, qualité, journal), Contenus (matchs,
  personnes, saisons/adversaires/lieux, médiathèque), Éditorial (accueil, bandeau, 100 moments),
  Interactif (quiz, onze, album), Communauté (contributions, messages, newsletter, dons),
  Système (traductions EN, utilisateurs, sauvegardes).
- Fiche match en onglets : Infos, Compo & événements, Récit, Médias, Partage & SEO,
  Historique ; panneaux Publication (brouillon / à relire / planifié / publié),
  « Mis à jour automatiquement », Contrôle qualité, Version EN.
- Fiche personne : identité, naissance (géolocalisée pour la carto), au club, statuts,
  matchs reliés automatiquement, carte de l'album (rareté).
- Médiathèque : légende, crédit, droits, texte alternatif, « utilisée dans », alertes
  (sans crédit, droits ?, doublon ?).
- **Ajouts nécessaires** (absents de la maquette) : Réglages (clés API Gemini, Stripe,
  PayPal, SMTP…), rubriques & menus & ordre des mosaïques, frise, maillots, objets
  (réserves), partenaires, lieux de la carto, articles thématiques et pages (mentions
  légales…), redirections, assistant IA (questions posées, réindexation), et tous les
  champs existants des fiches (taille, poids, pied, récit, statistiques, galerie…).
- **Rôles** : la maquette montre 5 rôles ; **confirmé par le client : 2 niveaux**
  (administrateur / utilisateur), présentés dans le style de l'écran « Utilisateurs & rôles ».
