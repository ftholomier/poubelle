# Sochaux Rétro — avancement du développement

Fichier de reprise : à relire en premier après une pause (quota, réveil automatique).
Règle : cocher au fur et à mesure, pousser après chaque étape.

## Réveils automatiques
- Routine horaire de secours : `trig_01As8VxpxYtxnuZiiJrm2bC7` (à supprimer à la fin).
- Réveils ponctuels 25 min (send_later) armés le 02/10 22:26 UTC jusqu'à ~01:47 UTC :
  trig_01WiQsrdfvkWqUJXShXiK69y, trig_01Xzk5LSdtrqKTVAKxG6AaMo, trig_01RwszBhjZgJeLrh17D1X35f,
  trig_01B8ML7YTU2zzytNedRCowSq, trig_014rUAPRt5WbcJhsVVWkpAHm, trig_01EzNHDLVrgE4NUqJpMCzFV5,
  trig_017eY6oM9GECAhruDaeuGWaD, trig_01GXaHpArXVKv68GEXEStZpy.
  → Réarmer une nouvelle série quand elle arrive à échéance.

## Données locales (non versionnées, régénérables)
- `storage/import/` : aspiration brute (HTML, REST, ordres des mosaïques).
- `storage/media/originals/` : photos originales (5,2 Go).
- Aspiration : `WP_PASSWORD=… php scripts/wp/fetch.php all` (reprenable). Journal : `storage/logs/fetch.log`.
- Maquette décompressée : `storage/maquette2/` (copie versionnée dans `docs/maquette/`).

## Phase 1 — Données
- [ ] Aspiration HTML des 2 941 pages (en cours)
- [ ] Ordre des mosaïques par catégorie
- [ ] Téléchargement des originaux des médias
- [ ] Extraction → `data/` (fiches, catégories, médias, redirections)
- [ ] Référentiels : saisons, clubs (alias), stades, compétitions
- [ ] Liens joueurs ↔ matchs, statistiques dérivées
- [ ] Rapport de complétude (texte ancien vs nouveau, images, tableaux, vidéos)

## Phase 2 — Noyau et front
- [ ] Noyau (routeur, stockage JSON, index, réglages, sessions, sécurité)
- [ ] Charte : polices auto-hébergées, CSS de la maquette, JS natif
- [ ] En-tête (bandeau, méga-menus, recherche plein écran, taille du texte, FR/EN, burger) + pied de page
- [ ] Accueil
- [ ] Mosaïques (filtres, tri, vue liste, « Afficher plus »)
- [ ] Fiche match (terrain + tableau, temps forts, vidéo, galerie, face-à-face)
- [ ] Fiche personne (carte à collectionner, identité complète, récits, stats, tous ses matchs)
- [ ] Fiches dirigeant / personnage / article thématique / pages
- [ ] Images à la volée (redimensionnement + cache)
- [ ] URL option B + redirections 301 + plan du site XML + SEO

## Phase 3 — Back-office
- [ ] Connexion, 2 niveaux, invitations, journal d'activité
- [ ] Tableau de bord, qualité, journal
- [ ] Matchs, personnes, articles (onglets, versions, restauration, statuts, planification)
- [ ] Référentiels, médiathèque, rubriques & menus & ordre des mosaïques
- [ ] Éditorial : accueil, bandeau, slider, 100 moments
- [ ] Réglages (secrets chiffrés), sauvegardes

## Phase 4 — Interactif
- [ ] Saisons, face-à-face, bilans compétition / stade, records
- [ ] Carto (stades, origines, épopées, lieux)
- [ ] Frise, maillots, quiz, album, centenaire (moments, Onze), réserves (objets)

## Phase 5 — Communauté
- [ ] Contribuer, contact, messages, newsletter, partenaires
- [ ] Dons Stripe + PayPal (ponctuel / mensuel, webhooks, jauge, mur, reçus)

## Phase 6 — IA et traduction
- [ ] Recherche plein texte
- [ ] Assistant Gemini (RAG, bulle, limites, journal)
- [ ] Traduction EN (Gemini + correction BO)

## Phase 7 — Finitions
- [ ] Sécurité (CSP, CSRF, antispam), cookies, accessibilité
- [ ] Images de partage, newsletter « Ce jour-là »
- [ ] Contrôle de complétude final, documentation de déploiement o2switch

## Journal
- 02/10 22:26 UTC — feu vert du client, démarrage du développement.
