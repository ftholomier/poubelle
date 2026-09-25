#!/usr/bin/env bash
# Contrôles avant validation (npm run check) : formate les fichiers modifiés, puis types, lint, tests unitaires. Échoue au premier problème.
set -euo pipefail
cd "$(dirname "$0")/.."
# Fichiers modifiés ou nouveaux, chemins relatifs au projet (qu'il soit ou non à la racine du dépôt).
FILES=$({ git diff --name-only --relative HEAD; git ls-files --others --exclude-standard; } | sort -u | grep -E '\.(ts|tsx|css|mjs|md|json)$' | grep -v '^drizzle/' | grep -v 'package' | while read -r f; do [ -f "$f" ] && echo "$f"; done || true)
if [ -n "$FILES" ]; then npx prettier --write $FILES >/dev/null; npx prettier --check $FILES; fi
npx tsc --noEmit -p .
npx eslint src tests scripts/seed.ts
npx vitest run --reporter=dot
echo "✓ prêt à valider"
