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
- [x] Teaser vidéo (`docs/teaser`, MP4 calé sur la musique fournie)
- [x] Synchronisation SIRENE : passage mensuel automatique (API Sirene de l’INSEE avec clé gratuite, sinon API
      Recherche d’entreprises), nouveautés à valider puis invitation par courrier, fermetures signalées à archiver
      ou garder, fichier stock des départements pour les grands territoires, activités exclues par territoire

## En cours

- [ ] Territoire de démonstration réel « Lacs et Montagnes du Haut-Doubs » : territoire, 32 communes officielles
      (codes INSEE, codes postaux, populations, positions), comptes et accès démo en place ; reste à figer les
      entreprises réelles avec `npm run demo:sirene` (accès réseau à `recherche-entreprises.api.gouv.fr`
      nécessaire), puis `npm run db:reset` et contrôle visuel.

## Pistes pour la suite (hors cahier des charges initial)

- [ ] Multilingue, pistes suivantes : traduction des publications, événements et offres d’emploi ; autres langues
      (espagnol, italien, néerlandais : l’IA les gère déjà) ; lettre du territoire par langue d’abonné
- [ ] Connecteurs natifs (fiche Google, Meta) en complément du connecteur générique signé
- [ ] Revenus « à terme » du cahier des charges (§ 24) : cartes cadeaux territoriales ; place de marché seulement
      si le pilote l’exprime (§ 29, e-commerce volontairement non prioritaire)

## Limites connues

- Dans le bac à sable de développement, cartes, photos, API publiques et IA sont bloquées (fonctionnent en production).
- Synchronisation SIRENE : API Sirene de l’INSEE et fichiers stock de data.gouv.fr non joignables depuis le bac
  à sable ; logique testée (tests unitaires, jeu de démonstration, e2e), appels réels à vérifier en préproduction.
- Les photos de démonstration proviennent d’Unsplash (URL externes) ; en production, les photos sont téléversées.
