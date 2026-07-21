#!/usr/bin/env bash
# Restauration complète de l'environnement de travail après un reset du conteneur.
# Idempotent : réinstalle les outils, récupère les polices, ré-extrait le texte.
set -e
BOOK="$(cd "$(dirname "$0")/.." && pwd)"
BUILD="$BOOK/build"
FONTS="$BUILD/fonts"
mkdir -p "$BUILD" "$FONTS"

echo "== 1. Outils système (extraction .doc) =="
apt-get install -y antiword catdoc wv >/dev/null 2>&1 && echo "  antiword/catdoc/wv OK" || echo "  (apt indisponible)"

echo "== 2. Python (typst, docx, rendu) =="
pip install --quiet typst python-docx fonttools brotli pymupdf olefile 2>&1 | tail -1 || true

echo "== 3. Polices premium (npm fontsource/expo -> TTF) =="
if [ -z "$(ls -A "$FONTS" 2>/dev/null | grep -i ttf)" ]; then
  TMP="$BUILD/fonts_dl"; mkdir -p "$TMP"; cd "$TMP"
  for pkg in fraunces spectral inter; do npm pack @expo-google-fonts/$pkg >/dev/null 2>&1 || true; done
  for f in *.tgz; do d="${f%.tgz}_x"; mkdir -p "$d"; tar xzf "$f" -C "$d" 2>/dev/null || true; done
  find "$TMP" -iname "*.ttf" -exec cp {} "$FONTS/" \; 2>/dev/null || true
  echo "  polices: $(ls "$FONTS" | grep -ci ttf) fichiers TTF"
else
  echo "  polices déjà présentes ($(ls "$FONTS" | grep -ci ttf) TTF)"
fi

echo "== 4. Extraction du texte (si source présente) =="
SRC="$(ls "$BOOK"/source/*.doc 2>/dev/null | head -1)"
if [ -n "$SRC" ]; then
  python3 "$BOOK/scripts/extract.py" "$SRC" "$BUILD"
else
  echo "  !! Aucune source dans book/source/ — ré-uploader le .doc puis relancer."
fi
echo "== Terminé =="
