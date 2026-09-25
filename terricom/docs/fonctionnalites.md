# Fonctionnalités

Catalogue des fonctions livrées, par espace. Les écrans reprennent la maquette fournie (charte, écrans
P1–P8, E1–E7, C1–C8, S1–S5) ; les fonctions ajoutées ensuite pour couvrir le cahier des charges suivent la
même charte.

## Portail public d’un territoire (P1–P8)

Servi sur `https://<territoire>.terricom.fr`, sur le domaine de la collectivité ou sous `/<territoire>`.

| Rubrique                       | Contenu                                                                                                                                                                                                                                                                                      |
| ------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Accueil                        | Accroche du territoire, recherche en langage naturel, campagne à la une, blocs configurables (nouveautés, agenda, circuits, communes, emploi), inscription à la lettre (avec commune)                                                                                                        |
| Explorateur (carte)            | Résultats et carte Leaflet synchronisés (regroupement au-delà de 80 points), filtres famille, catégorie, commune, « ouvert maintenant », filtres avancés (services, accessibilité, labels, paiement), calques événements, marchés et lieux économiques                                       |
| Fiche d’établissement          | Galerie avec visionneuse, logo, labels, onglets, offre du moment (campagne ou promotion), produits, actualités et événements, recrutement, contact, horaires (exceptions comprises), accès et plan, paiement, accessibilité, tarifs, réseaux sociaux, JSON-LD `LocalBusiness`, revendication |
| Fonctions payantes de la fiche | Pages supplémentaires, formulaires personnalisés (Premium) ; bouton « Suivre » et mini-site aux couleurs de l’établissement (Communication) — voir plus bas                                                                                                                                  |
| Communes                       | Liste des communes, page de commune (fiches par catégorie, opérations commerciales de la commune), page catégorie                                                                                                                                                                            |
| Agenda                         | Événements (filtres, export iCalendar), marchés                                                                                                                                                                                                                                              |
| Campagnes                      | Page de campagne aux couleurs choisies, participants et offres, calendrier de l’Avent                                                                                                                                                                                                        |
| Circuits                       | Parcours, étapes, passeport à tampons (QR code sur place)                                                                                                                                                                                                                                    |
| Emploi                         | Offres d’emploi, candidature avec CV (analyse antivirus), encadré « Vivre ici »                                                                                                                                                                                                              |
| Actualités                     | Publications des commerces et de la collectivité                                                                                                                                                                                                                                             |
| Lettre d’information           | Inscription en double opt-in, désinscription en un clic                                                                                                                                                                                                                                      |
| Légal et SEO                   | Mentions légales, données personnelles, accessibilité, plan du site, `sitemap.xml`, `robots.txt`, URL canoniques                                                                                                                                                                             |

## Espace entreprise (E1–E7)

| Écran                                | Contenu                                                                                                                                                                   |
| ------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Revendication                        | Recherche de sa fiche, vérification (SIRET, justificatif, code par courrier), suivi de la demande                                                                         |
| Inscription                          | Référencer une activité absente de la base (SIRENE) ; ajout d’un établissement de la même entreprise (SIREN prérempli)                                                    |
| Tableau de bord                      | Vues, appels, itinéraires, messages, complétude de la vitrine, campagne en cours                                                                                          |
| Ma fiche                             | Éditeur complet (identité, logo, description avec assistant IA, horaires et exceptions, photos, produits, services, réseaux sociaux, accessibilité, prise de rendez-vous) |
| Publications                         | Rédaction assistée (variantes par canal), programmation, diffusion portail, lettre du territoire, réseaux sociaux                                                         |
| Statistiques                         | Courbe des vues, provenance, heures d’affluence, recherches qui mènent à la fiche, export CSV                                                                             |
| Kit vitrine & QR                     | Affichette, autocollant, carte de visite (PDF), QR code haute définition                                                                                                  |
| Mini-site & formulaires              | Pages supplémentaires, formulaires personnalisés, réglages du mini-site                                                                                                   |
| Clients                              | Abonnés de l’entreprise, lettres aux clients, ajout sur attestation, export CSV                                                                                           |
| Messages                             | Boîte de réception (contact, formulaires avec réponses champ par champ), réponse par email                                                                                |
| Événements, Recrutement, Rendez-vous | Création d’événements, offres d’emploi et candidatures, demandes de rendez-vous (confirmer, décliner, annuler)                                                            |
| Équipe, Mon offre                    | Collaborateurs, changement d’offre                                                                                                                                        |

### Offres des entreprises

| Fonction                                                             | Essentiel (offert) |         Premium         |      Communication      |
| -------------------------------------------------------------------- | :----------------: | :---------------------: | :---------------------: |
| Fiche complète, photos, horaires, QR code, statistiques essentielles |         ✓          |            ✓            |            ✓            |
| Publications                                                         |      3 / mois      | illimitées, programmées | illimitées, programmées |
| Assistant IA de rédaction                                            |      5 / mois      |        illimité         |        illimité         |
| Diffusion dans la lettre du territoire, statistiques avancées        |                    |            ✓            |            ✓            |
| Offres d’emploi, prise de rendez-vous, QR codes personnalisés        |                    |            ✓            |            ✓            |
| Formulaires personnalisés, pages supplémentaires                     |                    |            ✓            |            ✓            |
| Mise en avant enrichie dans les listes et la recherche               |                    |            ✓            |            ✓            |
| Publication sur les réseaux sociaux                                  |                    |                         |            ✓            |
| Clients abonnés et lettres aux clients, export des contacts          |                    |                         |            ✓            |
| Mini-site personnalisable                                            |                    |                         |            ✓            |

Les droits sont définis dans la table `plans` (colonne `limits`, modifiable depuis la console) et contrôlés
**côté serveur** à chaque action et à chaque affichage public ; l’interface présente les fonctions non
incluses avec un lien vers « Mon offre ».

### Clients abonnés (offre Communication)

- Bouton « Suivre » sur la fiche et ses pages : email, consentement explicite, **double opt-in** par email.
- Ajout manuel par le professionnel, sur attestation de l’accord du client ; une personne désinscrite ne
  peut pas être réinscrite par l’entreprise.
- Lettres de l’entreprise : objet, titre, message, bouton facultatif, dernières publications ; envoi par la
  file de tâches, **4 lettres au plus par 30 jours**, expéditeur et adresse en pied, désinscription par
  bouton (page) et en un clic (`List-Unsubscribe-Post`) ; ouvertures, clics et désinscriptions mesurés.
- Export CSV (journalisé, catégorie RGPD). Les agents de la collectivité n’ont pas accès aux clients.

### Formulaires personnalisés (Premium)

Constructeur avec modèles (devis, réservation, inscription à un atelier) : texte court ou long, email,
téléphone, date, nombre, liste de choix, case à cocher ; obligatoire, aide, ordre. Nom, email, téléphone et
consentement sont toujours demandés. Validation serveur champ par champ ; chaque réponse arrive dans la
messagerie (réponses structurées) avec un email de notification.

### Pages supplémentaires (Premium) et mini-site (Communication)

- Jusqu’à 8 pages par fiche (`/<commune>/<catégorie>/<fiche>/<page>`), mise en forme simple (intertitres,
  listes, gras, liens — aucun HTML interprété), photo de couverture choisie parmi les photos de la fiche,
  brouillons, ordre ; présentes dans le plan du site.
- Mini-site : couleur de marque (accents, onglets, boutons avec contraste vérifié), en-tête « photos » ou
  « bandeau de couleur » avec logo, accroche, bouton principal (appeler, itinéraire, contact, rendez-vous,
  formulaire, page ou lien), menu des pages, ordre et visibilité des sections (le contact reste toujours
  proposé). La fiche garde son adresse sur le portail et son référencement.

## Back-office collectivité (C1–C8)

Deux niveaux : **territoire** (intercommunalité) et **commune** (mairie, périmètre restreint à sa commune).

| Rubrique            | Contenu                                                                                                                                                  |
| ------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Tableau de bord     | Adoption (objectif), activité, alertes                                                                                                                   |
| Entreprises         | Liste filtrable, fiche détaillée, création, import CSV ou SIRENE avec correspondance des colonnes, invitations (email ou courrier PDF), export CSV       |
| Revendications      | Validation des revendications, justificatifs, modération des publications                                                                                |
| Campagnes           | Assistant IA, campagnes territoriales ou communales (une mairie ne modifie que les siennes), calendrier de l’Avent, participants, affiche A5 et QR codes |
| Agenda & actualités | Événements, marchés, lieux économiques (zones d’activité, halles, tiers-lieux… placés sur la carte)                                                      |
| Newsletter          | Composition, audiences (manuelles, par zone, par activité, professionnels), programmation, statistiques                                                  |
| Circuits            | Parcours, étapes, tampons imprimables                                                                                                                    |
| Statistiques        | Audience du portail, rapport PDF, export CSV                                                                                                             |
| Personnalisation    | Couleurs, logo, textes, blocs de l’accueil, domaine personnalisé, rôles et invitations, catégories propres au territoire (renommer, masquer, créer)      |
| API & données       | Clés de l’API publique (création, révocation), documentation                                                                                             |

## Console plateforme (S1–S5)

| Rubrique                                                          | Contenu                                                                                                                                                                                                                                                                             |
| ----------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Vue d’ensemble                                                    | ARR, territoires, établissements, conversion et churn des offres, carte des territoires, revenu mensuel, pipeline, santé du service ; **usage et rentabilité par territoire** (panier moyen des offres, entreprises actives, vues des campagnes, coût d’exploitation estimé, marge) |
| Territoires                                                       | Clients et prospects, modules, quotas, statut, **marque blanche**, communes rattachées (ajout, transfert, détachement), accès support temporaire journalisé                                                                                                                         |
| Suivi commercial                                                  | CRM (étapes, contacts, activités, tâches, documents)                                                                                                                                                                                                                                |
| Facturation                                                       | Factures, licences à renouveler, offres des entreprises (prix, droits)                                                                                                                                                                                                              |
| IA, emails, tâches de fond, audit & sécurité, support, simulateur | Consommation IA, boîte d’envoi, file de tâches, journal chaîné, demandes RGPD, tickets, simulateur de devis                                                                                                                                                                         |

## API publique v1

Lecture seule des données publiées d’un territoire, clé par usage : voir [api.md](api.md).

## Application installable et notifications

- **Application installable (PWA)** : manifeste propre à chaque portail (nom, couleurs, icônes générées aux
  initiales du territoire) et à l’espace professionnel (raccourcis Messages et Publications) ; installation sur
  l’écran d’accueil (Android, iPhone, ordinateur).
- **Hors connexion** : les pages publiques déjà consultées restent lisibles ; une page de secours s’affiche
  sinon. Les espaces privés (pro, collectivité, console, compte) ne sont jamais conservés sur l’appareil.
- **Notifications push** (« Mon compte » → Notifications, par appareil) : nouveaux messages et réponses aux
  formulaires, demandes de rendez-vous, candidatures pour les professionnels ; revendications à valider pour
  les agents du territoire et de la commune. Un essai peut être envoyé depuis la page.

## Marque blanche

Option activée par l’exploitant (console → territoire) : le portail et les emails du territoire ne
mentionnent plus terricom. Les mentions légales conservent l’identification du prestataire (obligation légale).

## Site de la marque et démonstration

Présentation, pages collectivités et professionnels, territoires en ligne, tarifs, charte (`/marque`),
démonstration. Avec `DEMO_MODE=true`, la barre de démonstration ouvre chaque espace sans mot de passe
(boulangerie en offre Essentiel, caviste en offre Communication, intercommunalité, mairie, console).
