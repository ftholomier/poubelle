@AGENTS.md

# Mémoire du projet terricom

- Lire d’abord `docs/developpement.md` (conventions, pièges connus), `docs/journal.md` (lots livrés,
  décisions) et `TODO.md` (en cours, reste à faire). Les tenir à jour à chaque lot.
- Fonctionnalités par espace et par offre : `docs/fonctionnalites.md` ; API publique : `docs/api.md`.
- Branche de travail : `claude/determined-planck-fvpvuh` ; la marque s’écrit toujours « terricom » en minuscules.
- Avant chaque commit : `npx tsc --noEmit -p .`, `npx eslint`, `npx prettier --check`, `npx vitest run`,
  tests Playwright concernés (serveur `npm run dev` lancé), `npm run db:reset` si le schéma ou le jeu change.
