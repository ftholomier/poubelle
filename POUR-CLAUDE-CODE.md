# À lire en premier — reprise de ce projet pour un autre site

Vous tenez un **site web complet et livrable**, et en même temps un **socle
réutilisable**. Les deux sont le même code : ce dépôt sert le site officiel de
la mairie de Lachapelle-sous-Chaux (Territoire de Belfort), et il a été écrit
pour qu'on en produise d'autres sans repartir de zéro.

Ce fichier ne décrit pas le code — les autres le font, et mieux. Il dit
seulement **quoi lire, dans quel ordre, et ce qu'il ne faut pas casser**.

---

## Ce que c'est, en cinq lignes

PHP 8.1+ natif, **aucune dépendance** : ni Composer, ni `node_modules`, ni base
de données, ni étape de build. Le contenu vit dans des fichiers JSON qu'un
back-office complet écrit. Trente-deux pages publiques, une vingtaine d'écrans
d'administration, un éditeur de texte, une médiathèque, un assistant IA
optionnel, un module d'avis Google et un module de traduction — les trois
derniers éteints tant qu'aucune clé n'est saisie.

C'est conçu pour l'hébergement mutualisé français : un FTP, du PHP, rien
d'autre.

---

## L'ordre de lecture

| Ordre | Fichier | Ce qu'il vous donne |
|---|---|---|
| 1 | **`CLAUDE.md`** | Les règles de travail. Court. Chargé automatiquement par Claude Code. |
| 2 | **`NOUVEAU-SITE.md`** | **La recette pour produire un autre site.** Le système de design en entier, l'itinéraire en dix étapes, les auditeurs, et les pièges déjà payés. C'est le document central si votre tâche est « fais-moi un site pour la commune X ». |
| 3 | `KIT.md` | La carte du code : où vit quoi, les conventions, et § 10 les pièges rencontrés pendant le développement du socle. |
| 4 | `SITE.md` | Les arbitrages de **ce** site-ci : pourquoi telle rubrique, telle couleur, tel réglage. Utile pour comprendre les intentions ; à réécrire pour un autre site. |
| 5 | `DEPLOIEMENT.md` | Mise en ligne, droits de fichiers, FTP, mises à jour, dépannage. |

`README.md` est la porte d'entrée pour un humain ; ces cinq-là sont pour vous.

---

## Démarrer, vérifier que tout marche

```bash
php -S localhost:8080 -t public public/index.php
```

`http://localhost:8080` pour le site, `/admin` pour le back-office — il n'y a
**aucun identifiant par défaut**, le premier passage crée le compte.

Le contenu de `data-modele/` se recopie tout seul dans `data/` à la première
lecture : le site s'affiche complet, sans manipulation.

---

## Les cinq auditeurs — c'est le cœur de la méthode

La qualité de ce projet ne vient pas du goût mais de la mesure. Cinq scripts
Python pilotent un navigateur et sortent en code 1 s'ils trouvent quelque
chose :

```bash
php -S 127.0.0.1:8081 -t public public/index.php &

python3 outils/verifs/contraste.py       # contraste réel de chaque texte peint
python3 outils/verifs/mise-en-page.py    # débordement, cibles tactiles, titres, alt
python3 outils/verifs/traceurs.py        # aucune requête tierce sans consentement
python3 outils/verifs/bandeau.py         # chaque photo du diaporama, une par une
python3 outils/verifs/entete.py          # l'en-tête aux bornes de ses réglages
```

Ils demandent Python 3 et Playwright (`pip install playwright pillow` puis
`playwright install chromium`). Si Chromium est déjà présent ailleurs :
`export CHROMIUM=/chemin/vers/chromium`.

**Zéro écart, zéro souci, zéro hôte tiers : c'est la définition de « fini »
ici.** Ce n'est pas de la coquetterie — ces scripts ont attrapé, sur ce projet,
des choses qu'aucun œil n'avait vues : un texte à 4,36:1 sur le fond crème, la
couleur de marque à 2,16:1 sur l'anthracite, des cibles tactiles à 26 px, un
saut de titre h1 → h3, un logo servi écrasé, une cible à 40 px sur la barre
défilée, deux photos de bandeau à 4,4:1.

**N'affaiblissez jamais un seuil pour faire passer une page.** Si un résultat
vous semble faux, c'est probablement un vrai faux positif : les sept pièges de
mesure connus sont documentés dans `NOUVEAU-SITE.md`, § « Les auditeurs ».
Corrigez le script en expliquant pourquoi, ne relevez pas le seuil.

---

## Ce qu'il ne faut jamais casser

- **Aucune dépendance.** Si une bibliothèque semble nécessaire, il manque
  presque toujours cinquante lignes de PHP.
- **`public/` est le seul dossier exposé.**
- **`e()` sur toute sortie.** L'unique dérogation est `riche()`, qui passe par
  une liste blanche de balises et de classes (`App\Core\TexteRiche`).
- **`Csrf::champ()` dans chaque formulaire d'administration**, `Csrf::verifier()`
  en tête de chaque traitement.
- **Écritures atomiques** : fichier temporaire puis `rename()`.
- **Tout est nommé en français** : classes, méthodes, variables, routes, clés
  JSON.
- **Les commentaires disent pourquoi, jamais quoi.** Un commentaire qui
  explique qu'on a mesuré 2,16:1 et qu'il a fallu une variante assombrie vaut
  cher : c'est ce qui empêche quelqu'un de « simplifier » la correction six
  mois plus tard.

---

## Ce qui est propre à Lachapelle et devra être remplacé

Pour un autre site, tout ceci change — `NOUVEAU-SITE.md` détaille comment :

- **La charte** : les jetons de couleur et de police, en tête de
  `public/assets/css/site.css`, dans le bloc `:root` et nulle part ailleurs.
- **Les logos** : `public/assets/img/logo/` — version pour fond clair, version
  pour fond sombre, emblème, favicon.
- **Les photos** : `public/assets/img/site/`.
- **Le contenu** : `data-modele/` en entier (pages, démarches, actualités,
  agenda, documents, élus, associations, numéros).
- **L'arborescence** : `Seo::PAGES` dans `app/Core/Seo.php`, et les routes dans
  `app/routes.php`.
- **Les redirections** depuis l'ancien site : le tableau en tête de
  `app/routes.php`.
- **`SITE.md`**, à réécrire pour la nouvelle commune.

Le reste — `app/`, `views/`, `outils/`, la mécanique du back-office — se reprend
tel quel.

---

## Si c'est un site de service public

Quatre règles qui ne s'appliquaient pas au site commercial dont vient ce
socle, et qui s'appliquent ici :

1. **Ne publiez jamais une règle administrative que vous n'avez pas vérifiée.**
   Les pièces, les seuils et les délais changent d'une année à l'autre ; une
   fiche périmée envoie un administré au guichet avec le mauvais dossier. Le
   site précédent décrivait l'urbanisme d'avant 2012 et les procurations
   d'avant 2022.
2. **N'affirmez jamais un service que la commune ne rend pas** — ni une
   coordonnée que vous n'avez pas vérifiée. Une adresse de préfecture inventée
   fait perdre une matinée à quelqu'un.
3. **Les urgences passent avant tout le reste**, y compris avant un renvoi vers
   une page du site.
4. **Aucune requête tierce sans consentement.** `traceurs.py` le mesure.

---

## Ce que ce paquet ne contient pas

- **`.git`** : c'est une copie de travail, pas un dépôt.
- **`data/`** (le contenu vivant) et **`data/admin/`** (le compte
  d'administration, le mot de passe SMTP) : ils se recréent tout seuls depuis
  `data-modele/` au premier lancement.
- **Sept bulletins municipaux en PDF**, retirés pour alléger l'archive de
  dix-huit mégaoctets. Les dix autres documents sont là, le composant de
  téléchargement est donc démontrable. Les fiches correspondantes restent dans
  `data-modele/documents.json` : leurs liens pointeront vers des fichiers
  absents tant qu'on ne les remplace pas, ce qui n'émet aucune erreur — la vue
  vérifie l'existence du fichier avant d'en afficher le poids.
- **Les clés d'API** : Gemini pour l'assistant, Google pour les avis, DeepL
  pour la traduction. Chaque fonction concernée se retire d'elle-même tant
  qu'elle n'est pas configurée.

---

## Une invite pour démarrer

À coller telle quelle dans une nouvelle session, en remplaçant ce qui est entre
crochets :

> Tu trouves en pièce jointe un fichier zip : un site de mairie complet, en PHP
> natif sans aucune dépendance, qui sert aussi de socle réutilisable. Commence
> par lire `POUR-CLAUDE-CODE.md`, puis `CLAUDE.md` et `NOUVEAU-SITE.md` : la
> recette y est écrite pas à pas.
>
> Je veux que tu produises sur cette base le site de [la commune / la structure
> X], dont le site actuel est [adresse]. Reprends-en les photos et le contenu,
> que tu as le droit d'adapter, d'améliorer et d'étoffer du moment que tu
> gardes l'esprit global. Je te donne les droits d'accès à [adresse].
>
> Les cinq auditeurs de `outils/verifs/` doivent être à zéro avant que tu
> déclares quoi que ce soit fini. Pose-moi tes questions avant de développer si
> tu en as. Développe dans une nouvelle branche.

---

## En une phrase

Le socle ne bouge pas ; ce qui change tient dans vingt lignes de jetons, un
dossier de contenu et une table de pages — et la qualité vient des cinq
auditeurs, pas du goût.
