#!/usr/bin/env bash
# Lance la compilation de production (sortie autonome de Next.js), comme l'image Docker « web ».
# « next start » ne convient pas à cette sortie : il répondrait 404 partout.
set -euo pipefail
cd "$(dirname "$0")/.."
if [ ! -f .next/standalone/server.js ]; then
  echo "Compilation absente : lancez d'abord « npm run build »." >&2
  exit 1
fi
rm -rf .next/standalone/.next/static .next/standalone/public
cp -r .next/static .next/standalone/.next/static
cp -r public .next/standalone/public
export HOSTNAME=0.0.0.0
exec node --env-file-if-exists=.env .next/standalone/server.js
