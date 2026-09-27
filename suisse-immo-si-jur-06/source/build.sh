#!/bin/sh
# Régénère les quatre livrables de la fiche SI-JUR-06 à partir de content.py.
#
# Prérequis : Python 3 avec pymupdf, fonttools et brotli ; Node 18+ avec
# `npm install` dans ce dossier (docx, jszip) et Playwright + Chromium installés.
set -e
cd "$(dirname "$0")"

OUT=../livrables
NAME=SI-JUR-06_Prospection-telephonique_Suisse-Immo
export NODE_PATH="${NODE_PATH:-$(npm root -g)}"
mkdir -p "$OUT"

python3 content.py content.json
python3 prep_assets.py

# Fiche complète : PDF A4 et page web autonome
python3 build_html.py fiche.html
node render.cjs fiche.html "$OUT/$NAME.pdf"
python3 pdf_meta.py "$OUT/$NAME.pdf"
python3 build_html.py "$OUT/$NAME.html" --inline

# Formulaire seul, à remplir sur tablette
python3 build_html.py annexe.html --fillable
node render.cjs annexe.html annexe-base.pdf --boxes champs.json
python3 build_fillable.py annexe-base.pdf champs.json "$OUT/SI-JUR-06_Formulaire-consentement_a-remplir.pdf"

# Word éditable, polices intégrées
node build_docx.js "$OUT/$NAME.docx"

rm -f fiche.html annexe.html annexe-base.pdf champs.json
