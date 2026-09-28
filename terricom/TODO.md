# TODO

Suivi du développement : ce qui est fait, en cours et restant. Mis à jour à chaque lot
(détails dans [docs/journal.md](docs/journal.md)).

## Fait

- [x] Socle technique, modèle de données multi-territorial, jeu de démonstration Val de Loue
- [x] Portail public (P1–P8) : explorateur, fiches, communes, agenda, campagnes, circuits, emploi, actualités, SEO
- [x] Espace entreprise (E1–E7) : revendication, tableau de bord, fiche, publications IA, statistiques, kit vitrine, offres
- [x] Back-office collectivité (C1–C8) : entreprises, import, revendications, campagnes, newsletter, circuits, statistiques, personnalisation
- [x] Console (S1–S5) : vue d’ensemble, territoires, CRM, facturation, audit, IA, emails, tâches, support
- [x] Site de la marque, pages légales, mode démonstration
- [x] Worker, Kubernetes HA, CI, sauvegardes, supervision
- [x] Vérification visuelle et accessibilité de tous les espaces (bureau et mobile)
- [x] Routage multi-domaines fiable en production
- [x] Campagnes communales, filtres avancés, calques de carte, catégories par territoire, communes rattachées
- [x] Audiences de newsletter, QR codes de campagne, rendez-vous contrôlés, multi-établissements
- [x] Offres Premium et Communication : clients abonnés, lettres aux clients, formulaires, pages supplémentaires, mini-site
- [x] Marque blanche (option par territoire)
- [x] Indicateurs de la console : panier moyen, entreprises actives, vues des campagnes, coût d’exploitation, marge
- [x] API publique v1 (clés, limitation, OpenAPI, page « API & données ») et tests e2e
- [x] Notifications push : abonnements par appareil, déclencheurs (messages, formulaires, rendez-vous,
      candidatures, revendications), page « Mon compte », invitation dans la messagerie pro
- [x] PWA : manifeste par portail et pour l’espace pro, icônes générées, service worker (hors-ligne des pages
      publiques, jamais des espaces privés), page hors-ligne, installation
- [x] Portail multilingue (module `MULTILINGUAL`) : interface complète en anglais et en allemand, sélecteur de
      langue mémorisé, `hreflang`, canoniques et plan du site par langue, emails de double validation traduits,
      traductions IA des fiches (tâche `i18n.translate`) et des textes du portail (« Langues du portail »),
      version rédigée par le professionnel, repli sur le français, pages légales en français
- [x] Accès à la connexion depuis le portail : « Se connecter » dans l’en-tête, « Espace élus et agents » en pied
      de page
- [x] Synchronisation des contenus : flux iCal et RSS (territoire, fiches), connecteur signé des entreprises
      (Premium : publications, fiche, événements, offres d’emploi), agendas externes iCal importés chaque heure
- [x] Marque blanche testée de bout en bout ; hors-ligne vérifié sur la compilation de production
- [x] Contrôles finaux : compilation de production, parcours de tous les espaces (bureau et mobile), tests
      unitaires et e2e complets, worker, base de démonstration réinitialisée, documentation à jour
- [x] Dossier de réalisation en PDF à la charte (`docs/dossier`, `npm run dossier`) : design, ergonomie,
      fonctionnalités, IA, technique, hébergement, sécurité, RGPD, qualité, démonstration ; `npm start` lance le
      serveur autonome ; plus de bande vide en bas des espaces privés hors mode démonstration
- [x] Présentation aux élus (`docs/presentation`, `npm run presentation`) : de la communauté de communes au
      commerce, sans vocabulaire technique ; PDF paysage avec étapes de construction, version HTML animée
- [x] Stratégie des fondateurs (`docs/strategie`, `npm run strategie`) : grille nationale unique par population,
      mise en service, offre de lancement 2027, vente à distance, webmarketing, modèle et trois scénarios 2027–2031
- [x] Teaser vidéo (`docs/teaser`, MP4 calé sur la musique fournie)
- [x] Teaser refait sur « The Mountain » (1 min 53) : naissance de la marque sans le nom jusqu’à la révélation,
      plan topographique réel du Haut-Doubs, captures animées de l’application avec curseur (portail, carte, Métabief,
      assistant de campagne, SIRENE, espace commerçant, téléphones), fond de carte réel et photos libres du territoire
- [x] Note de cadrage « Fonctionnalités à développer » mise à la charte (`docs/cadrage`, `npm run cadrage`) ;
      les 24 fonctionnalités restent à arbitrer et à planifier
- [x] Stratégie des fondateurs : modèle B (abonnement territorial 10 000 € + 0,20 €/hab., plafond 59 000 €,
      mise en service 5 000 €) ajouté à côté de la grille A, comparaison et simulations A/B ; décision à prendre
- [x] Prospection : fichier Excel des 1 255 intercommunalités avec coordonnées et chiffre d’affaires du modèle B
      par communauté de communes (`../prospection`)
- [x] Site commercial terricom.fr (`../site-terricom`) : 15 pages statiques pour les élus (accueil, élus, solution,
      communes, entreprises, différence, démonstration, accompagnement, confiance, questions, contact, légal),
      captures et film du Haut-Doubs, formulaires par courriel, sans tarif public ; version nationale : photos de
      toute la France, application en action en boucles vidéo, démonstration par onglets ; formulaires branchés
      sur la plateforme (`/api/site/demonstration`, `/api/site/contact`) : affaire dans le suivi commercial sans
      doublon, courriel à l’équipe, accusé de réception, messagerie en secours
- [x] Synchronisation SIRENE : passage mensuel automatique (API Sirene de l’INSEE avec clé gratuite, sinon API
      Recherche d’entreprises), nouveautés à valider puis invitation par courrier, fermetures signalées à archiver
      ou garder, fichier stock des départements pour les grands territoires, activités exclues par territoire
- [x] Territoire réel de démonstration « Lacs et Montagnes du Haut-Doubs » : 32 communes officielles, 3 753
      établissements SIRENE actifs contrôlés un par un (forme juridique, code NAF, nom) : 1 968 fiches
      précréées, 219 cas « à vérifier » dans la file SIRENE, 1 566 exclusions certaines (SCI, particuliers
      loueurs, copropriétés, services publics…), rapport `docs/demo/haut-doubs-controle.csv`
- [x] Démonstration 100 % Haut-Doubs pour le premier prospect : jeu fictif retiré de la démo (gardé pour les
      tests, `DEMO_DATASET=fictif`), commerce de démonstration signalé, exemples signalés, libellé « territoire de
      démonstration » (et non « pilote ») sur le site
- [x] Dossier de réalisation, présentation aux élus et teaser refaits sur le Haut-Doubs : 45 captures du
      territoire réel, textes et chiffres réels (1 968 entreprises, 32 communes, 3 753 établissements contrôlés)
- [x] Adhésion directe des entreprises sans collectivité partenaire : parcours `/pro/adhesion` (SIRET, code
      postal, formule, paiement Stripe ou virement), vitrine nationale `/france`, levier commercial par
      intercommunalité (SIREN de l’EPCI), console « Adhésions directes », bascule automatique à l’adhésion de la
      commune ou de la CC, site commercial et stratégie des fondateurs mis à jour (sans tarif sur le site public)
- [x] Film de présentation aux élus (`docs/teaser/terricom-elus.mp4`) : même musique que le teaser, logo et
      adresse seulement à la fin, aucun tarif

## En cours

Rien en cours.

## Pistes pour la suite (hors cahier des charges initial)

- [ ] Assistant de campagne : n’y retenir que les fiches réclamées ou validées laisse une sélection d’un seul
      commerce sur un territoire qui vient d’ouvrir (démo Haut-Doubs) ; y inclure les fiches précréées publiées,
      l’étape « Inviter la sélection à participer » étant faite pour elles
- [ ] Site commercial : compléter les mentions légales (raison sociale, SIREN, adresse, directeur de publication,
      hébergeur), choisir une mesure d’audience sans cookie, décider de l’hébergement (si le site statique et la
      plateforme partagent terricom.fr, transmettre `/api/` à la plateforme)

- [ ] Multilingue, pistes suivantes : traduction des publications, événements et offres d’emploi ; autres langues
      (espagnol, italien, néerlandais : l’IA les gère déjà) ; lettre du territoire par langue d’abonné
- [ ] Adresses web des clients (mis de côté) : sous-domaine en terricom.fr demandé depuis le back-office (CC,
      communes, entreprises) et domaine propre branché sur la fiche ou le mini-site. Proposition et décisions à
      prendre : [docs/cadrage/adresses-web.md](docs/cadrage/adresses-web.md)
- [ ] Compte habitant facultatif (mis de côté) : connexion sans mot de passe par lien sécurisé envoyé par email ;
      passeport des circuits sur tous les appareils, abonnements, favoris, alertes de sa commune. Proposition :
      [docs/cadrage/compte-habitant.md](docs/cadrage/compte-habitant.md)
- [ ] Connecteurs natifs (fiche Google, Meta) en complément du connecteur générique signé
- [ ] Revenus « à terme » du cahier des charges (§ 24) : cartes cadeaux territoriales ; place de marché seulement
      si le pilote l’exprime (§ 29, e-commerce volontairement non prioritaire)

## Limites connues

- Adhésion directe : la mise à jour du prix Stripe à la bascule demande les clés Stripe (sinon journalisée) ;
  les adhésions directes ne sont pas encore comptées dans le modèle financier des fondateurs.

- Dans le bac à sable de développement, cartes, photos, API publiques et IA sont bloquées (fonctionnent en production).
- Synchronisation SIRENE : API Sirene de l’INSEE et fichiers stock de data.gouv.fr non joignables depuis le bac
  à sable ; logique testée (tests unitaires, jeu de démonstration, e2e), appels réels à vérifier en préproduction.
- Les photos de démonstration proviennent d’Unsplash (URL externes) ; en production, les photos sont téléversées.
