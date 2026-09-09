# Formation IA débutants — kit complet, 2 jours

Ensemble des supports d'une formation IA intra-entreprise pour débutants,
sur deux journées (~13 h 30 de face-à-face, environ 60 % de pratique,
9 ateliers, 4 livrables participants).

## Les documents

| | Document | Pour qui | Format |
|---|---|---|---|
| 00 | **Programme minuté** — déroulé séquence par séquence | Formateur, client | HTML — [page publiée](https://claude.ai/code/artifact/39ad5e4b-e607-4919-a7c6-41abaf306028) |
| 01 | **Deck de projection** — 73 slides, notes du formateur sous chaque slide | Projection | PPTX 16:9 |
| 02 | **8 infographies** + planche imprimable | Projection, impression, insertion | PNG 3200×1800 · PDF A4 paysage |
| 03 | **Manuel du participant** — explications, exercices, liens, 20 prompts, glossaire, FAQ | Participants | PDF A4, 31 p. |
| 04 | **Cahier d'exercices** — les 9 fiches à remplir | Participants | PDF A4, 10 p. |
| 05 | **Mémo de poche** — COPAIN, 3 feux, prompts, plan 30 jours | Participants | PDF A5, 8 p. |
| 06 | **Fiches d'animation** — minutage, consignes mot à mot, débriefs, pièges, plans B | Formateur uniquement | PDF A4, 21 p. |
| 07 | **Cartographie des modèles** — module de référence par critères | Formateur, client | PDF A4, 8 p. |

## Les infographies

1. Six mots qu'on emploie l'un pour l'autre — IA, ML, deep learning, génératif, LLM, agents
2. Ce qui se passe quand vous appuyez sur Entrée — tokens, probabilités, boucle
3. Soixante-dix ans d'histoire en trois actes — 1950-2011, 2012-2021, 2022-aujourd'hui
4. La méthode C.O.P.A.I.N. — les six lettres, puis le prompt assemblé
5. La règle des trois feux — quelles données dans quel outil
6. Cartographie des modèles — quatre questions, quatre familles
7. Quatre marches, de l'assistant au système d'agents
8. La grille d'automatisation — fréquence × temps × répétitivité

## Structure de la formation

| | Jour 1 — Comprendre & dialoguer | Jour 2 — Produire & automatiser |
|---|---|---|
| Matin | Baromètre · L'IA sans le brouillard · 70 ans en 45 min · Premier contact et chasse aux hallucinations | Retours du soir · Laboratoire d'outils · Fabriquer son assistant |
| Après-midi | Méthode C.O.P.A.I.N. · Cartographie des LLM · Données, droit et AI Act | Agents et automatisation · Le futur et plan à 30 jours · Quiz et clôture |

## Régénérer les documents

```bash
_build/make.sh
```

Prérequis : `python3` avec `python-pptx` et `pypdf`, `node` avec `playwright`,
et un Chromium. Le chemin du binaire est en tête de `_build/render.js`.

### Comment c'est fabriqué

- **PDF** — les documents sont écrits en HTML, mis en page par `_build/base.css`
  (système de design partagé, A4), puis rendus par Chromium via Playwright.
  Le sommaire du manuel est paginé en deux passes : des marqueurs invisibles
  semés dans le HTML sont relus dans le PDF de la première passe pour connaître
  la page réelle de chaque chapitre.
- **Polices** — Bricolage Grotesque, Public Sans et IBM Plex Mono sont intégrées
  en base64 dans `_build/polices/polices.css`, pour un rendu identique hors ligne.
- **PPTX** — construit avec `python-pptx`. Il utilise volontairement des polices
  présentes par défaut sur Windows et macOS (Trebuchet MS, Calibri, Consolas) :
  le fichier s'ouvre correctement sur le poste du client sans installation.
  L'identité visuelle passe par la couleur, la mise en page, et les infographies
  qui sont des images.

## Sources de départ

Construit sur les 7 modules existants (Les LLM, Les couches de l'IA, Démystifier
l'IA, L'art de parler à l'IA, C.O.P.A.I.N., Les grands modèles de langage, IA
agentique) et la liste d'outils du kit Notion.

Trois choses ont été ajoutées parce qu'elles manquaient : l'histoire et
l'évolution de l'IA, le cadre juridique (confidentialité, droit d'auteur,
obligation de littératie IA de l'article 4 du règlement européen, applicable
depuis le 2 février 2025), et des livrables tangibles pour les participants.

La cartographie des modèles a été reconstruite sur des critères de choix plutôt
que sur des noms de version, qui périment en trois mois.
