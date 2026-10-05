#!/bin/sh
# Refait public/assets/js/shop3d.js (aperçu 3D de la boutique) depuis app/Resources/shop/shop3d.src.js.
# Il faut Node : Three.js est épinglé ici, esbuild ne garde que ce qui sert et minifie le tout.
set -e
cd "$(dirname "$0")/.."
TMP=$(mktemp -d)
cp app/Resources/shop/shop3d.src.js "$TMP/entry.js"
(cd "$TMP" && npm init -y >/dev/null && npm i -s three@0.186.1 esbuild@0.25 >/dev/null)
"$TMP/node_modules/.bin/esbuild" "$TMP/entry.js" --bundle --minify --format=esm --target=es2020 \
  --legal-comments=eof --outfile=public/assets/js/shop3d.js
rm -rf "$TMP"
ls -l public/assets/js/shop3d.js
