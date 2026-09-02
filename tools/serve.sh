#!/usr/bin/env bash
# Démarre le serveur de développement intégré à PHP.
# Usage : ./tools/serve.sh [port]

set -euo pipefail
PORT="${1:-8000}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

echo "→ http://localhost:${PORT}"
echo "→ http://localhost:${PORT}/labo   (laboratoire de formes)"
echo "→ http://localhost:${PORT}/api    (documentation de l'API)"
exec php -S "localhost:${PORT}" -t "${ROOT}/public" "${ROOT}/public/router.php"
