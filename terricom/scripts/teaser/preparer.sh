#!/bin/bash
# Base de démonstration réelle (Haut-Doubs) + photos des pages de Métabief, Jougne et Malbuisson, comme les
# ajouterait la collectivité. À relancer avant chaque série de captures ; `npm run db:reset` remet la démo d'origine.
set -e
cd "$(dirname "$0")/../.."
npm run db:reset > /dev/null
set -a; . ./.env; set +a
psql "$DATABASE_URL" -qc "update communes set hero_image_url = 'https://photos.terricom.test/' || slug || '.jpg' where insee_code in ('25380','25318','25361')"
echo "base prête pour les captures"
