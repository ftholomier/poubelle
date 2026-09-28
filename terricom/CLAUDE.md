@AGENTS.md

# Mémoire du projet terricom

- Lire d’abord `docs/developpement.md` (conventions, pièges connus), `docs/journal.md` (lots livrés,
  décisions) et `TODO.md` (en cours, reste à faire). Les tenir à jour à chaque lot.
- Fonctionnalités par espace et par offre : `docs/fonctionnalites.md` ; API publique : `docs/api.md`.
- Branche de travail : `claude/determined-planck-fvpvuh` ; la marque s’écrit toujours « terricom » en minuscules.
- Avant chaque commit : `npm run check` (formatage, types, lint, tests unitaires), tests Playwright concernés
  (serveur `npm run dev` lancé, base chargée avec `DEMO_DATASET=fictif npm run db:reset`), puis
  `npm run db:reset` pour remettre la démonstration réelle (Haut-Doubs seul, aucun contenu inventé).
