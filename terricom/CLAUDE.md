@AGENTS.md

# Mémoire du projet terricom

- Lire d’abord `docs/developpement.md` (conventions, pièges connus), `docs/journal.md` (lots livrés,
  décisions) et `TODO.md` (en cours, reste à faire). Les tenir à jour à chaque lot.
- Fonctionnalités par espace et par offre : `docs/fonctionnalites.md` ; API publique : `docs/api.md`.
- Branche de travail : `claude/determined-planck-fvpvuh` ; la marque s’écrit toujours « terricom » en minuscules.
- Dépôt GitLab `https://gitlab.com/sty255/terricom.git` (jeton dans `GITLAB_TOKEN`, jamais écrit ni affiché) :
  à chaque livraison, pousser sur la branche `dev` (après avoir intégré `main` et `dev` distants, sans réécrire
  l’historique). `main` est protégée. **Ne jamais ouvrir de merge request sans demande explicite.**
- Avant chaque commit : `npm run check` (formatage, types, lint, tests unitaires), tests Playwright concernés
  (serveur `npm run dev` lancé, base chargée avec `DEMO_DATASET=fictif npm run db:reset`), puis
  `npm run db:reset` pour remettre la démonstration réelle (Haut-Doubs seul, aucun contenu inventé).
