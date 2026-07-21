# Restructuration du livre « La magie des sociétés / Le triangle magique »

Projet : restructurer un manuscrit de ~68 000 mots (fichier Word `.doc`, 119 pages) en
**sommaire + chapitres + titres**, **corps du texte identique au mot près**, puis produire
un **.docx** (sommaire cliquable) et un **PDF haut de gamme** (moteur Typst, DA « agence »).

## Décisions validées avec l'utilisateur
- Format : **Word .docx** (sommaire cliquable auto) **+ PDF** à mise en page pro.
- Structure : **ordre du texte conservé**, redécoupage des parties longues, sommaire complet.
- Titres : **inventés/clairs**, notes internes de l'auteur nettoyées.
- Le .docx doit être livré **en lien téléchargeable dans le chat**.

## Architecture du livre (ordre linéaire, déjà dans le texte)
- **Introduction** (idx corps 13–82) — histoire perso, carrés magiques, Triangle Magique. 2 images `[pic]` (idx 42, 51) = carrés magiques, reconstruits en figures vectorielles.
- **I. Le monde de la comptabilité** (titre idx 83 ; corps 84–415)
- **II. Le monde des particuliers et entrepreneurs individuels** (titre idx 416 ; corps 417–1137)
- **III. Le monde des sociétés** (titre idx 1138 ; corps 1139–2074)
- **IV. La holding** (titre idx 2075 ; corps 2076–2398) — finit par « Remerciements » (idx 2368).

## Chaîne technique (validée)
- LibreOffice est CASSÉ ici. Extraction via **antiword -x db** (DocBook) → `extract.py` → `build/paras.json` (2399 paragraphes, 1625 non vides, ~71 900 mots ; encodage propre).
- Moteur PDF : **Typst 0.15** (pip `typst`), polices **Fraunces / Spectral / Inter** (TTF via npm `@expo-google-fonts/*`).
- **.docx** : `python-docx`.

## ⚠️ Résilience aux resets du conteneur
Le conteneur est réinitialisé entre certains tours : **seuls les fichiers commités dans git survivent**
(scratchpad, upload `.doc`, outils installés = tous effacés). Donc :
1. La **source .doc** doit être commitée dans `book/source/` (à re-uploader une dernière fois).
2. Après tout reset : `git pull` puis `bash book/scripts/setup.sh` restaure outils + polices + `build/`.

## État d'avancement
- [x] Extraction + analyse de structure
- [x] Structure **Partie I** → `struct/struct_part1.json` (3 ch, 13 s-s) — COMPLET
- [x] Structure **Partie IV** → `struct/struct_part4.json` (5 ch, 5 s-s) — COMPLET
- [~] Structure **Partie II** → `struct/struct_part2_partial.json` — chapitres OK, **sous-sections à re-dériver**
- [ ] Structure **Partie III** → **à refaire** (agent mort au reset)
- [x] DA Typst → `scripts/theme.typ`
- [x] Assembleur → `scripts/assemble.py` (émetteur Typst ; **émetteur .docx à écrire**)
- [ ] Rendu PDF + QA ; génération .docx ; vérif fidélité mot-à-mot ; livraison

## Reprise
```bash
git pull
# placer le .doc dans book/source/ (une fois)
bash book/scripts/setup.sh
# re-dériver struct part2 (sous-sections) + part3 (2 sous-agents sur build/part2_*.txt, part3_*.txt)
python3 book/scripts/assemble.py          # -> build/livre.typ
# rendu (à écrire) : assemble.py doit aussi émettre le .docx ; compiler Typst -> out/livre.pdf
```
