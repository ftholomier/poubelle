@AGENTS.md

# Mémoire du projet terricom

- Lire d’abord `docs/developpement.md` (conventions, pièges connus), `docs/journal.md` (lots livrés,
  décisions) et `TODO.md` (en cours, reste à faire). Les tenir à jour à chaque lot.
- Fonctionnalités par espace et par offre : `docs/fonctionnalites.md` ; API publique : `docs/api.md`.
- La marque s’écrit toujours « terricom » en minuscules ; textes en français.
- Git : dépôt GitLab `https://gitlab.com/sty255/terricom.git` (remote `origin`), branche de travail `dev`.
  `main` est protégée et n’évolue que par merge request.
  - Commits **en local** sur `dev`, un par lot, message en français.
  - Pousser (`git push origin dev`) **seulement quand c’est demandé**, ou pour une merge request demandée ;
    avant, `git pull origin dev` (fusion, jamais de rebase ni de push forcé).
  - **Merge request uniquement sur demande explicite** : `glab mr create --source-branch dev --target-branch main`.
  - Aucun jeton ni mot de passe dans un fichier ou une commande ; `.env` n’est jamais commité.
- Poste de travail Windows (cmd) : le dépôt est cloné dans `C:\Users\fthol\terricom`, l’application est dans
  son sous-dossier `terricom`. En local, l’application tourne dans Docker (voir `README.md`, « Avec Docker »).
- Avant chaque commit : `npm run check` (formatage, types, lint, tests unitaires), tests Playwright concernés
  (serveur `npm run dev` lancé, base chargée avec `DEMO_DATASET=fictif npm run db:reset`), puis
  `npm run db:reset` pour remettre la démonstration réelle (Haut-Doubs seul, aucun contenu inventé).
