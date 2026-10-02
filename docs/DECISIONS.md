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
- Rôles : administrateur, historien, modérateur.
- Utilisable sur tablette.
