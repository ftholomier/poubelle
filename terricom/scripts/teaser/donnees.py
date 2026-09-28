"""Données du teaser : fond de carte du Haut-Doubs (Overture Maps, relief Terrarium, contours des communes)
et positions des établissements de la démonstration.

Tout est écrit dans le dossier de travail TEASER_DIR (par défaut .teaser/ à la racine, ignoré par git).
Dépendances Python : pip install --target .teaser/py pyarrow shapely pillow  puis PYTHONPATH=.teaser/py

Usage : python3 scripts/teaser/donnees.py overture relief communes geo pins
"""
import concurrent.futures as cf
import json
import math
import os
import subprocess
import sys
import urllib.request
from urllib.parse import urlparse

RACINE = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
W = os.environ.get('TEASER_DIR', os.path.join(RACINE, '.teaser'))
RELEASE = os.environ.get('OVERTURE_RELEASE', '2026-09-23.1')
LARGE = (5.8, 46.4, 6.9, 47.15)  # emprise des données (ouest, sud, est, nord)
COEUR = (5.98, 46.50, 6.54, 46.93)  # détail (bâtiments, petites routes) : la communauté de communes
THEMES = ['base/type=water', 'base/type=land_cover', 'base/type=land_use', 'transportation/type=segment', 'buildings/type=building']


def overture():
    """Extrait l'emprise depuis les fichiers GeoParquet publics d'Overture (S3, accès anonyme), en ne lisant
    que les groupes de lignes dont la boîte englobante recoupe la zone."""
    import pyarrow as pa
    import pyarrow.compute as pc
    import pyarrow.fs as pfs
    import pyarrow.parquet as pq

    proxy = os.environ.get('HTTPS_PROXY')
    opts = {'anonymous': True, 'region': 'us-west-2'}
    if proxy:
        p = urlparse(proxy)
        opts['proxy_options'] = {'scheme': 'http', 'host': p.hostname, 'port': p.port}
    fs = pfs.S3FileSystem(**opts)
    os.makedirs(f'{W}/overture', exist_ok=True)
    for theme in THEMES:
        bb = COEUR if 'building' in theme else LARGE
        files = [f.path for f in fs.get_file_info(pfs.FileSelector(f'overturemaps-us-west-2/release/{RELEASE}/theme={theme}/')) if 'part' in f.path]
        out = []
        for fp in files:
            f = pq.ParquetFile(fs.open_input_file(fp))
            md = f.metadata
            names = [md.schema.column(i).path for i in range(md.num_columns)]
            ix = {n: names.index(n) for n in ['bbox.xmin', 'bbox.ymin', 'bbox.xmax', 'bbox.ymax']}
            groups = []
            for gi in range(md.num_row_groups):
                s = {k: md.row_group(gi).column(i).statistics for k, i in ix.items()}
                if s['bbox.xmin'].min > bb[2] or s['bbox.xmax'].max < bb[0] or s['bbox.ymin'].min > bb[3] or s['bbox.ymax'].max < bb[1]:
                    continue
                groups.append(gi)
            if not groups:
                continue
            t = f.read_row_groups(groups)
            b = t.column('bbox').combine_chunks()
            m = pc.and_(
                pc.and_(pc.less_equal(b.field('xmin'), bb[2]), pc.greater_equal(b.field('xmax'), bb[0])),
                pc.and_(pc.less_equal(b.field('ymin'), bb[3]), pc.greater_equal(b.field('ymax'), bb[1])),
            )
            out.append(t.filter(m))
        name = theme.replace('/type=', '_')
        pq.write_table(pa.concat_tables(out), f'{W}/overture/{name}.parquet')
        print(theme, sum(x.num_rows for x in out), 'objets')


def tuile(lon, lat, z):
    n = 2**z
    return int((lon + 180) / 360 * n), int((1 - math.asinh(math.tan(math.radians(lat))) / math.pi) / 2 * n)


def relief():
    """Tuiles d'altitude Terrarium (AWS Open Data) pour l'ombrage et les courbes de niveau."""
    jobs = []
    for z, b in ((9, (5.3, 47.6, 7.4, 45.9)), (10, (5.4, 47.5, 7.3, 46.0)), (11, (5.5, 47.4, 7.1, 46.1)), (12, (5.6, 47.3, 6.95, 46.25)), (13, (5.75, 47.2, 6.85, 46.35)), (14, (5.95, 47.05, 6.7, 46.5))):
        x0, y0 = tuile(b[0], b[1], z)
        x1, y1 = tuile(b[2], b[3], z)
        jobs += [(z, x, y) for x in range(x0, x1 + 1) for y in range(y0, y1 + 1)]

    def get(j):
        z, x, y = j
        p = f'{W}/dem/{z}/{x}/{y}.png'
        if os.path.exists(p):
            return
        os.makedirs(os.path.dirname(p), exist_ok=True)
        urllib.request.urlretrieve(f'https://elevation-tiles-prod.s3.amazonaws.com/terrarium/{z}/{x}/{y}.png', p)

    with cf.ThreadPoolExecutor(16) as ex:
        list(ex.map(get, jobs))
    print(len(jobs), 'tuiles de relief')


def communes():
    """Contours des communes du Doubs (france-geojson, d'après les données IGN)."""
    url = 'https://raw.githubusercontent.com/gregoiredavid/france-geojson/master/departements/25-doubs/communes-25-doubs.geojson'
    os.makedirs(W, exist_ok=True)
    urllib.request.urlretrieve(url, f'{W}/communes-25.geojson')
    print('contours des communes')


def geo():
    """GeoJSON allégés pour le rendu : jeu « core » détaillé sur la communauté de communes, jeu « wide » (grandes
    routes, eau, occupation du sol) pour les petites échelles, contour du territoire, étiquettes."""
    import pyarrow.parquet as pq
    import shapely
    import shapely.geometry as sg
    from shapely.ops import unary_union

    def rows(name, cols):
        t = pq.read_table(f'{W}/overture/{name}.parquet', columns=cols + ['geometry'])
        d = t.to_pydict()
        for i in range(t.num_rows):
            yield {c: d[c][i] for c in cols}, shapely.from_wkb(d['geometry'][i])

    def feat(g, **p):
        return {'type': 'Feature', 'properties': p, 'geometry': sg.mapping(shapely.set_precision(g, 0.000001))}

    def primary(n):
        return n.get('primary') if isinstance(n, dict) else None

    def save(d, n, f):
        os.makedirs(f'{W}/geo/{d}', exist_ok=True)
        json.dump({'type': 'FeatureCollection', 'features': f}, open(f'{W}/geo/{d}/{n}.json', 'w'), separators=(',', ':'))

    poly = ('Polygon', 'MultiPolygon')
    line = ('LineString', 'MultiLineString')
    coeur = sg.box(*COEUR)
    wp, wl, lab = [], [], []
    for p, g in rows('base_water', ['class', 'names']):
        if g.geom_type in poly:
            wp.append(feat(g, c=p['class']))
            nm = primary(p['names'])
            if nm and p['class'] in ('lake', 'water', 'reservoir') and g.area > 2e-6:
                lab.append({'type': 'Feature', 'properties': {'n': nm, 'k': 'lake', 'a': g.area}, 'geometry': sg.mapping(g.representative_point())})
        elif g.geom_type in line:
            wl.append(feat(g, c=p['class']))
    lc = [feat(g, c=p['subtype']) for p, g in rows('base_land_cover', ['subtype']) if g.geom_type in poly]
    lu = [(feat(g, s=p['subtype'], c=p['class']), g) for p, g in rows('base_land_use', ['subtype', 'class'])]
    rd = [(feat(g, t=p['subtype'], c=p['class']), g) for p, g in rows('transportation_segment', ['subtype', 'class']) if g.geom_type in line]
    bd = [feat(g) for p, g in rows('buildings_building', []) if g.geom_type in poly]
    major = {'motorway', 'trunk', 'primary', 'secondary', 'tertiary'}
    save('core', 'roads', [f for f, g in rd if g.intersects(coeur)])
    save('wide', 'roads', [f for f, g in rd if f['properties']['t'] == 'rail' or f['properties']['c'] in major])
    save('core', 'landuse', [f for f, g in lu if g.geom_type in poly and g.intersects(coeur)])
    save('core', 'landuse_line', [f for f, g in lu if g.geom_type in line and g.intersects(coeur)])
    save('wide', 'landuse', [f for f, g in lu if g.geom_type in poly and f['properties']['c'] in ('residential', 'industrial')])
    save('wide', 'landuse_line', [])
    save('core', 'buildings', bd)
    save('wide', 'buildings', [])
    save('core', 'water_line', [f for f in wl if sg.shape(f['geometry']).intersects(coeur)])
    save('wide', 'water_line', [f for f in wl if f['properties']['c'] == 'river'])
    # communes : contours, territoire, étiquettes (au centre du village pour les communes du territoire)
    seed = {c['insee']: c for c in json.load(open(f'{RACINE}/scripts/seed/haut-doubs-communes.json'))}
    gj = json.load(open(f'{W}/communes-25.geojson'))
    cm, hd = [], []
    for f in gj['features']:
        s = sg.shape(f['geometry'])
        if not s.intersects(sg.box(*LARGE)):
            continue
        code = f['properties']['code']
        cm.append({'type': 'Feature', 'properties': {'n': f['properties']['nom'], 'hd': code in seed}, 'geometry': f['geometry']})
        pt = [seed[code]['lng'], seed[code]['lat']] if code in seed else list(s.representative_point().coords[0])
        lab.append({'type': 'Feature', 'properties': {'n': f['properties']['nom'], 'k': 'commune', 'hd': code in seed}, 'geometry': {'type': 'Point', 'coordinates': pt}})
        if code in seed:
            hd.append(s)
    for d in ('core', 'wide'):
        save(d, 'water_poly', wp)
        save(d, 'landcover', lc)
        save(d, 'communes', cm)
        save(d, 'labels', lab)
    terr = unary_union(hd).buffer(0.0005).buffer(-0.0005)
    os.makedirs(f'{W}/comp', exist_ok=True)
    json.dump({'type': 'FeatureCollection', 'features': [feat(terr)]}, open(f'{W}/comp/territoire.json', 'w'))
    json.dump({'type': 'FeatureCollection', 'features': [f for f in cm if f['properties']['hd']]}, open(f'{W}/comp/communes-hd.json', 'w'))
    print('géodonnées écrites')


def pins():
    """Positions et familles d'activité des établissements du territoire (base de démonstration)."""
    q = (
        "select json_agg(json_build_array(round(e.lng::numeric,5), round(e.lat::numeric,5), c.family)) from establishments e "
        "join territories t on t.id=e.territory_id join categories c on c.id=e.category_id where t.slug='haut-doubs' and e.lat is not null"
    )
    out = subprocess.run(['psql', os.environ['DATABASE_URL'], '-At', '-c', q], capture_output=True, text=True, check=True).stdout
    os.makedirs(f'{W}/comp', exist_ok=True)
    open(f'{W}/comp/pins.json', 'w').write(out)
    print(len(json.loads(out)), 'établissements')


if __name__ == '__main__':
    for step in sys.argv[1:] or ['overture', 'relief', 'communes', 'geo', 'pins']:
        globals()[step]()
