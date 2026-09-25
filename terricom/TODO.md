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

## En cours

- [ ] Portail multilingue (module `MULTILINGUAL`) : interface en anglais, traductions des fiches par l’IA,
      sélecteur de langue, `hreflang`

## À faire ensuite

- [ ] Test e2e de la marque blanche
- [ ] Vérifier le hors-ligne sur une compilation de production (`npm run build && npm start`)
- [ ] Contrôles finaux : `next build`, tests complets, parcours de tous les espaces, `db:reset`
- [ ] Documentation finale (README, architecture) et rapport de livraison

## Limites connues

- Dans le bac à sable de développement, cartes, photos, API publiques et IA sont bloquées (fonctionnent en production).
- Les photos de démonstration proviennent d’Unsplash (URL externes) ; en production, les photos sont téléversées.
