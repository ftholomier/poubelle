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
  (1er janvier 2028), dons (Stripe + PayPal, ponctuel/mensuel, paliers, jauge, mur),
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
