#!/bin/sh
# Refait public/assets/js/babyfoot.js (le babyfoot 3D du musée) depuis app/Resources/babyfoot/*.js.
# Il faut Node : Three.js est épinglé ici, esbuild ne garde que ce qui sert et minifie le tout.
# Les modules physics.js, table.js, ai.js et rules.js se testent sans navigateur : node tests/babyfoot.mjs
set -e
cd "$(dirname "$0")/.."
TMP=$(mktemp -d)
cp app/Resources/babyfoot/*.js "$TMP/"
(cd "$TMP" && npm init -y >/dev/null && npm i -s three@0.186.1 esbuild@0.25 >/dev/null)
"$TMP/node_modules/.bin/esbuild" "$TMP/babyfoot.src.js" --bundle --minify --format=iife --target=es2020 \
  --legal-comments=eof --outfile=public/assets/js/babyfoot.js
rm -rf "$TMP"
ls -l public/assets/js/babyfoot.js
