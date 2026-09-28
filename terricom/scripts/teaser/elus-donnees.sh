#!/usr/bin/env bash
# Prépare les ressources du film de présentation aux élus dans .teaser/elus (ignoré par git) :
# boucles de l'application image par image (depuis le site commercial), repères des établissements
# (base de démonstration réelle, `npm run db:reset`), contours officiels de la CC et des communes (geo.api.gouv.fr).
# Usage : FFMPEG=/chemin/ffmpeg scripts/teaser/elus-donnees.sh
set -euo pipefail
cd "$(dirname "$0")/../.."
E=.teaser/elus
FFMPEG=${FFMPEG:-ffmpeg}
DB=$(grep ^DATABASE_URL .env | cut -d= -f2-)
mkdir -p "$E/loops"
for f in ../site-terricom/public/assets/video/app/*.mp4; do
  n=$(basename "$f" .mp4); mkdir -p "$E/loops/$n"
  "$FFMPEG" -loglevel error -y -i "$f" -q:v 3 "$E/loops/$n/f%04d.jpg"
done
python3 -c "import os,json;json.dump({n:len(os.listdir('$E/loops/'+n)) for n in os.listdir('$E/loops')},open('$E/loops.json','w'))"
psql "$DB" -Atc "select json_agg(json_build_array(round(e.lng::numeric,5),round(e.lat::numeric,5),c.family)) from establishments e
  join territories t on t.id=e.territory_id left join categories c on c.id=e.category_id
  where t.slug='haut-doubs' and e.lat is not null and e.status<>'ARCHIVED'" > "$E/pins.json"
psql "$DB" -Atc "select string_agg(insee_code, ',') from communes c join commune_memberships m on m.commune_id=c.id
  join territories t on t.id=m.territory_id where t.slug='haut-doubs' and m.valid_to is null" > "$E/insee.txt"
# geo.api.gouv.fr répond parfois vide : on réessaie.
python3 - "$E" <<'PY'
import json, subprocess, sys, time
E = sys.argv[1]
def get(url):
    for _ in range(10):
        r = subprocess.run(['curl', '-s', '--max-time', '30', url], capture_output=True, text=True).stdout
        try: return json.loads(r)
        except Exception: time.sleep(3)
    raise SystemExit(f'injoignable : {url}')
json.dump(get('https://geo.api.gouv.fr/epcis/200069565?format=geojson&geometry=contour'), open(f'{E}/epci.json', 'w'))
feats = [get(f'https://geo.api.gouv.fr/communes/{c}?fields=nom,centre,population&format=geojson&geometry=contour')
         for c in open(f'{E}/insee.txt').read().strip().split(',')]
json.dump({'type': 'FeatureCollection', 'features': feats}, open(f'{E}/communes.json', 'w'))
print(len(feats), 'communes')
PY
