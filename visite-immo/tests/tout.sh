#!/usr/bin/env bash
# Lance tous les tests de bout en bout, chacun sur des données neuves. Usage : ./tests/tout.sh
cd "$(dirname "$0")"
ok=0; ko=0
for t in e2e-base e2e-sortie e2e-photos e2e-acquereurs e2e-commercialisation e2e-video e2e-transaction e2e-quotidien e2e-prospection e2e-signature e2e-estimation e2e-leboncoin e2e-suivi e2e-acces e2e-accessibilite e2e-securite e2e-suppressions e2e-secteur e2e-modeles e2e-aide e2e-demo; do
  ../tests/lancer.sh > /dev/null
  echo "── $t"
  if timeout 400 node "$t.mjs"; then ok=$((ok+1)); else ko=$((ko+1)); echo "   ✗ $t en échec"; fi
done
./lancer.sh stop
echo "Résultat : $ok réussi(s), $ko en échec."
[ "$ko" -eq 0 ]
