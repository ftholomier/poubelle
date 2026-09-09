#!/usr/bin/env bash
# Régénère l'ensemble des documents de la formation.
# Prérequis : python3 + python-pptx + pypdf, node + playwright, Chromium.
set -e
cd "$(dirname "$0")"

echo "→ Infographies"
for f in info-*.html; do
  n=${f#info-}; n=${n%.html}
  node render.js png "$f" "../02-infographies/$n.png" ".canvas"
done

echo "→ Planche imprimable"
{ echo '<meta charset="utf-8"><style>@page{size:A4 landscape;margin:0}*{margin:0;padding:0}'
  echo 'body{background:#fff}.p{width:297mm;height:210mm;display:flex;align-items:center;'
  echo 'justify-content:center;break-after:page;overflow:hidden}.p:last-child{break-after:auto}'
  echo '.p img{width:297mm;height:auto;display:block}</style>'
  for f in ../02-infographies/*.png; do echo "<div class=\"p\"><img src=\"$f\"></div>"; done
} > planche.html
node render.js pdfraw planche.html ../02-infographies/00-planche-infographies.pdf
rm planche.html

echo "→ Deck PowerPoint"
python3 deck_slides.py

echo "→ Documents PDF"
python3 - <<'PY'
import sys; sys.path.insert(0, '.')
from build import pdf
pdf('manuel.html',       '03-manuel-participant.pdf', "Manuel du participant  ·  Deux jours pour apprivoiser l'IA")
pdf('exercices.html',    '04-cahier-exercices.pdf',   "Cahier d'exercices  ·  Deux jours pour apprivoiser l'IA", toc=False)
pdf('animation.html',    '06-fiches-animation.pdf',   "Fiches d'animation  ·  Document formateur", toc=False)
pdf('cartographie.html', '07-cartographie-llm.pdf',   "Cartographie des modèles  ·  Module de référence", toc=False)
PY
node render.js pdfraw memo.html ../05-memo-a5.pdf

echo "✓ Terminé"
