#!/bin/sh
# Refait public/assets/3d/*.glb (modèles 3D de la boutique) : téléchargement Sketchfab et allègement,
# voir app/Resources/shop/models3d.mjs. Il faut Node et un jeton d'API Sketchfab dans SKETCHFAB_TOKEN.
set -e
cd "$(dirname "$0")/.."
[ -n "$SKETCHFAB_TOKEN" ] || { echo "SKETCHFAB_TOKEN manquant" >&2; exit 1; }
TMP=$(mktemp -d)
cp app/Resources/shop/models3d.mjs "$TMP/models3d.mjs"
(cd "$TMP" && npm init -y >/dev/null && npm i -s @gltf-transform/core@4.5.1 @gltf-transform/extensions@4.5.1 @gltf-transform/functions@4.5.1 meshoptimizer gl-matrix >/dev/null)
mkdir -p public/assets/3d
node "$TMP/models3d.mjs" "$PWD/public/assets/3d"
rm -rf "$TMP"
ls -l public/assets/3d
