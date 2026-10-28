#!/bin/sh
# Refait public/assets/js/flipper.js (le flipper 3D du musée) depuis app/Resources/flipper/*.js.
# Il faut Node : Three.js est épinglé ici, esbuild ne garde que ce qui sert et minifie le tout.
# Les modules physics.js, table.js et rules.js se testent sans navigateur : node tests/flipper.mjs
set -e
cd "$(dirname "$0")/.."
TMP=$(mktemp -d)
cp app/Resources/flipper/*.js "$TMP/"
(cd "$TMP" && npm init -y >/dev/null && npm i -s three@0.186.1 esbuild@0.25 >/dev/null)
"$TMP/node_modules/.bin/esbuild" "$TMP/flipper.src.js" --bundle --minify --format=iife --target=es2020 \
  --legal-comments=eof --outfile=public/assets/js/flipper.js
rm -rf "$TMP"
ls -l public/assets/js/flipper.js
