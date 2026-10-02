#!/usr/bin/env python3
"""
Génère le référentiel géographique (app/data/geo/*.php) à partir de :
  - @etalab/decoupage-administratif (communes, départements, régions — données officielles INSEE)
  - cities.json (GeoNames, coordonnées GPS des communes principales)

Usage : python3 bin/build/build_geo.py <dossier_decoupage> <cities.json> <dossier_sortie> [centroids.json]

centroids.json (facultatif) : {code_insee: [lat, lng]} calculé à partir des contours communaux
(france-geojson, données IGN) — prioritaire sur GeoNames.

Les communes sans coordonnées GeoNames reçoivent une position approchée (barycentre du code postal
ou du département) marquée "a" => 1. Le back-office peut ensuite récupérer les centres exacts via
geo.api.gouv.fr (Maintenance > Référentiel géographique).
"""
import json
import os
import re
import sys
import unicodedata
from collections import defaultdict

DEPTS = {
    '01': ("Ain", "dans l'Ain", "de l'Ain"), '02': ("Aisne", "dans l'Aisne", "de l'Aisne"),
    '03': ("Allier", "dans l'Allier", "de l'Allier"),
    '04': ("Alpes-de-Haute-Provence", "dans les Alpes-de-Haute-Provence", "des Alpes-de-Haute-Provence"),
    '05': ("Hautes-Alpes", "dans les Hautes-Alpes", "des Hautes-Alpes"),
    '06': ("Alpes-Maritimes", "dans les Alpes-Maritimes", "des Alpes-Maritimes"),
    '07': ("Ardèche", "en Ardèche", "de l'Ardèche"), '08': ("Ardennes", "dans les Ardennes", "des Ardennes"),
    '09': ("Ariège", "en Ariège", "de l'Ariège"), '10': ("Aube", "dans l'Aube", "de l'Aube"),
    '11': ("Aude", "dans l'Aude", "de l'Aude"), '12': ("Aveyron", "dans l'Aveyron", "de l'Aveyron"),
    '13': ("Bouches-du-Rhône", "dans les Bouches-du-Rhône", "des Bouches-du-Rhône"),
    '14': ("Calvados", "dans le Calvados", "du Calvados"), '15': ("Cantal", "dans le Cantal", "du Cantal"),
    '16': ("Charente", "en Charente", "de la Charente"),
    '17': ("Charente-Maritime", "en Charente-Maritime", "de la Charente-Maritime"),
    '18': ("Cher", "dans le Cher", "du Cher"), '19': ("Corrèze", "en Corrèze", "de la Corrèze"),
    '2A': ("Corse-du-Sud", "en Corse-du-Sud", "de la Corse-du-Sud"),
    '2B': ("Haute-Corse", "en Haute-Corse", "de la Haute-Corse"),
    '21': ("Côte-d'Or", "en Côte-d'Or", "de la Côte-d'Or"),
    '22': ("Côtes-d'Armor", "dans les Côtes-d'Armor", "des Côtes-d'Armor"),
    '23': ("Creuse", "en Creuse", "de la Creuse"), '24': ("Dordogne", "en Dordogne", "de la Dordogne"),
    '25': ("Doubs", "dans le Doubs", "du Doubs"), '26': ("Drôme", "dans la Drôme", "de la Drôme"),
    '27': ("Eure", "dans l'Eure", "de l'Eure"), '28': ("Eure-et-Loir", "en Eure-et-Loir", "d'Eure-et-Loir"),
    '29': ("Finistère", "dans le Finistère", "du Finistère"), '30': ("Gard", "dans le Gard", "du Gard"),
    '31': ("Haute-Garonne", "en Haute-Garonne", "de la Haute-Garonne"), '32': ("Gers", "dans le Gers", "du Gers"),
    '33': ("Gironde", "en Gironde", "de la Gironde"), '34': ("Hérault", "dans l'Hérault", "de l'Hérault"),
    '35': ("Ille-et-Vilaine", "en Ille-et-Vilaine", "d'Ille-et-Vilaine"), '36': ("Indre", "dans l'Indre", "de l'Indre"),
    '37': ("Indre-et-Loire", "en Indre-et-Loire", "d'Indre-et-Loire"), '38': ("Isère", "en Isère", "de l'Isère"),
    '39': ("Jura", "dans le Jura", "du Jura"), '40': ("Landes", "dans les Landes", "des Landes"),
    '41': ("Loir-et-Cher", "dans le Loir-et-Cher", "du Loir-et-Cher"), '42': ("Loire", "dans la Loire", "de la Loire"),
    '43': ("Haute-Loire", "en Haute-Loire", "de la Haute-Loire"),
    '44': ("Loire-Atlantique", "en Loire-Atlantique", "de la Loire-Atlantique"),
    '45': ("Loiret", "dans le Loiret", "du Loiret"), '46': ("Lot", "dans le Lot", "du Lot"),
    '47': ("Lot-et-Garonne", "dans le Lot-et-Garonne", "du Lot-et-Garonne"), '48': ("Lozère", "en Lozère", "de la Lozère"),
    '49': ("Maine-et-Loire", "en Maine-et-Loire", "du Maine-et-Loire"), '50': ("Manche", "dans la Manche", "de la Manche"),
    '51': ("Marne", "dans la Marne", "de la Marne"), '52': ("Haute-Marne", "en Haute-Marne", "de la Haute-Marne"),
    '53': ("Mayenne", "en Mayenne", "de la Mayenne"),
    '54': ("Meurthe-et-Moselle", "en Meurthe-et-Moselle", "de Meurthe-et-Moselle"),
    '55': ("Meuse", "dans la Meuse", "de la Meuse"), '56': ("Morbihan", "dans le Morbihan", "du Morbihan"),
    '57': ("Moselle", "en Moselle", "de la Moselle"), '58': ("Nièvre", "dans la Nièvre", "de la Nièvre"),
    '59': ("Nord", "dans le Nord", "du Nord"), '60': ("Oise", "dans l'Oise", "de l'Oise"),
    '61': ("Orne", "dans l'Orne", "de l'Orne"), '62': ("Pas-de-Calais", "dans le Pas-de-Calais", "du Pas-de-Calais"),
    '63': ("Puy-de-Dôme", "dans le Puy-de-Dôme", "du Puy-de-Dôme"),
    '64': ("Pyrénées-Atlantiques", "dans les Pyrénées-Atlantiques", "des Pyrénées-Atlantiques"),
    '65': ("Hautes-Pyrénées", "dans les Hautes-Pyrénées", "des Hautes-Pyrénées"),
    '66': ("Pyrénées-Orientales", "dans les Pyrénées-Orientales", "des Pyrénées-Orientales"),
    '67': ("Bas-Rhin", "dans le Bas-Rhin", "du Bas-Rhin"), '68': ("Haut-Rhin", "dans le Haut-Rhin", "du Haut-Rhin"),
    '69': ("Rhône", "dans le Rhône", "du Rhône"), '70': ("Haute-Saône", "en Haute-Saône", "de la Haute-Saône"),
    '71': ("Saône-et-Loire", "en Saône-et-Loire", "de Saône-et-Loire"), '72': ("Sarthe", "dans la Sarthe", "de la Sarthe"),
    '73': ("Savoie", "en Savoie", "de la Savoie"), '74': ("Haute-Savoie", "en Haute-Savoie", "de la Haute-Savoie"),
    '75': ("Paris", "à Paris", "de Paris"), '76': ("Seine-Maritime", "en Seine-Maritime", "de la Seine-Maritime"),
    '77': ("Seine-et-Marne", "en Seine-et-Marne", "de Seine-et-Marne"), '78': ("Yvelines", "dans les Yvelines", "des Yvelines"),
    '79': ("Deux-Sèvres", "dans les Deux-Sèvres", "des Deux-Sèvres"), '80': ("Somme", "dans la Somme", "de la Somme"),
    '81': ("Tarn", "dans le Tarn", "du Tarn"), '82': ("Tarn-et-Garonne", "dans le Tarn-et-Garonne", "du Tarn-et-Garonne"),
    '83': ("Var", "dans le Var", "du Var"), '84': ("Vaucluse", "dans le Vaucluse", "du Vaucluse"),
    '85': ("Vendée", "en Vendée", "de la Vendée"), '86': ("Vienne", "dans la Vienne", "de la Vienne"),
    '87': ("Haute-Vienne", "en Haute-Vienne", "de la Haute-Vienne"), '88': ("Vosges", "dans les Vosges", "des Vosges"),
    '89': ("Yonne", "dans l'Yonne", "de l'Yonne"),
    '90': ("Territoire de Belfort", "dans le Territoire de Belfort", "du Territoire de Belfort"),
    '91': ("Essonne", "en Essonne", "de l'Essonne"), '92': ("Hauts-de-Seine", "dans les Hauts-de-Seine", "des Hauts-de-Seine"),
    '93': ("Seine-Saint-Denis", "en Seine-Saint-Denis", "de la Seine-Saint-Denis"),
    '94': ("Val-de-Marne", "dans le Val-de-Marne", "du Val-de-Marne"), '95': ("Val-d'Oise", "dans le Val-d'Oise", "du Val-d'Oise"),
    '971': ("Guadeloupe", "en Guadeloupe", "de la Guadeloupe"), '972': ("Martinique", "en Martinique", "de la Martinique"),
    '973': ("Guyane", "en Guyane", "de la Guyane"), '974': ("La Réunion", "à La Réunion", "de La Réunion"),
    '976': ("Mayotte", "à Mayotte", "de Mayotte"),
}

REGIONS = {
    '84': ("Auvergne-Rhône-Alpes", "en Auvergne-Rhône-Alpes"), '27': ("Bourgogne-Franche-Comté", "en Bourgogne-Franche-Comté"),
    '53': ("Bretagne", "en Bretagne"), '24': ("Centre-Val de Loire", "en Centre-Val de Loire"), '94': ("Corse", "en Corse"),
    '44': ("Grand Est", "dans le Grand Est"), '32': ("Hauts-de-France", "dans les Hauts-de-France"),
    '11': ("Île-de-France", "en Île-de-France"), '28': ("Normandie", "en Normandie"),
    '75': ("Nouvelle-Aquitaine", "en Nouvelle-Aquitaine"), '76': ("Occitanie", "en Occitanie"),
    '52': ("Pays de la Loire", "dans les Pays de la Loire"),
    '93': ("Provence-Alpes-Côte d'Azur", "en Provence-Alpes-Côte d'Azur"),
    '01': ("Guadeloupe", "en Guadeloupe"), '02': ("Martinique", "en Martinique"), '03': ("Guyane", "en Guyane"),
    '04': ("La Réunion", "à La Réunion"), '06': ("Mayotte", "à Mayotte"),
}

# Anciennes régions (avant 2016, codes de l'ancien site) -> nouvelles régions
OLD_REGIONS = {'42': '44', '72': '75', '83': '84', '25': '28', '26': '27', '53': '53', '24': '24', '21': '44', '94': '94',
               '43': '27', '1': '01', '3': '03', '23': '28', '11': '11', '91': '76', '74': '75', '41': '44', '2': '02',
               '73': '76', '31': '32', '52': '52', '22': '32', '54': '75', '93': '93', '4': '04', '82': '84'}


def norm(s):
    s = unicodedata.normalize('NFKD', s).encode('ascii', 'ignore').decode().lower()
    s = re.sub(r"\bst\b", 'saint', s)
    s = re.sub(r"\bste\b", 'sainte', s)
    return re.sub(r'[^a-z0-9]+', ' ', s).strip()


def slug(s):
    s = unicodedata.normalize('NFKD', s).encode('ascii', 'ignore').decode().lower()
    s = s.replace("'", '-').replace('&', '-et-')
    return re.sub(r'[^a-z0-9]+', '-', s).strip('-')


def php(value, indent=0):
    pad = '  ' * indent
    if isinstance(value, dict):
        items = []
        for k, v in value.items():
            key = repr(k) if isinstance(k, str) else str(k)
            items.append(f"{pad}  {php_str(k) if isinstance(k, str) else k} => {php(v, indent + 1)}")
        return '[\n' + ',\n'.join(items) + f'\n{pad}]'
    if isinstance(value, list):
        return '[' + ', '.join(php(v, indent + 1) for v in value) + ']'
    if isinstance(value, bool):
        return 'true' if value else 'false'
    if value is None:
        return 'null'
    if isinstance(value, (int, float)):
        return repr(value)
    return php_str(value)


def php_str(s):
    return "'" + str(s).replace('\\', '\\\\').replace("'", "\\'") + "'"


def write_php(path, data, compact=False):
    os.makedirs(os.path.dirname(path), exist_ok=True)
    with open(path, 'w', encoding='utf-8') as f:
        f.write('<?php\n// Fichier généré par bin/build/build_geo.py — ne pas modifier à la main.\nreturn ')
        f.write(php_compact(data) if compact else php(data))
        f.write(';\n')


def php_compact(value):
    if isinstance(value, dict):
        return '[' + ','.join(f"{php_str(k) if isinstance(k, str) else k}=>{php_compact(v)}" for k, v in value.items()) + ']'
    if isinstance(value, list):
        return '[' + ','.join(php_compact(v) for v in value) + ']'
    return php(value)


def main():
    src, cities_path, out = sys.argv[1], sys.argv[2], sys.argv[3]
    centroids = json.load(open(sys.argv[4], encoding='utf-8')) if len(sys.argv) > 4 else {}
    communes = json.load(open(os.path.join(src, 'communes.json'), encoding='utf-8'))
    geonames = json.load(open(cities_path, encoding='utf-8'))
    countries = {'FR', 'GP', 'MQ', 'GF', 'RE', 'YT'}
    gn = defaultdict(list)
    for g in geonames:
        if g['country'] in countries and g.get('admin2'):
            gn[(norm(g['name']), g['admin2'].upper())].append((float(g['lat']), float(g['lng'])))

    actual = [c for c in communes if c['type'] == 'commune-actuelle' and c.get('departement') in DEPTS]
    res = {}
    by_cp = defaultdict(list)
    for c in actual:
        dep = c['departement']
        key = (norm(c['nom']), dep)
        pt = gn.get(key)
        e = {'n': c['nom'], 'd': dep, 'r': c.get('region', ''), 'cp': c.get('codesPostaux', []), 'p': int(c.get('population') or 0)}
        if c['code'] in centroids:
            e['la'], e['lo'] = centroids[c['code']]
        elif pt:
            e['la'], e['lo'] = round(pt[0][0], 5), round(pt[0][1], 5)
        res[c['code']] = e
        for cp in e['cp']:
            by_cp[cp].append(c['code'])

    # Arrondissements municipaux : coordonnées utiles pour Paris/Lyon/Marseille.
    for c in communes:
        if c['type'] == 'arrondissement-municipal' and c.get('commune') in res:
            parent = res[c['commune']]
            if 'la' not in parent:
                pt = gn.get((norm(c['nom']), c['departement']))
                if pt:
                    parent['la'], parent['lo'] = round(pt[0][0], 5), round(pt[0][1], 5)

    # Barycentres de département (pondérés par la population) pour les positions approchées.
    dep_pts = defaultdict(list)
    for code, e in res.items():
        if 'la' in e:
            dep_pts[e['d']].append((e['la'], e['lo'], max(e['p'], 1)))
    dep_center = {}
    for d, pts in dep_pts.items():
        w = sum(p[2] for p in pts)
        dep_center[d] = (round(sum(p[0] * p[2] for p in pts) / w, 5), round(sum(p[1] * p[2] for p in pts) / w, 5))

    approx = 0
    for code, e in res.items():
        if 'la' in e:
            continue
        approx += 1
        sib = [res[s] for cp in e['cp'] for s in by_cp[cp] if s != code and 'la' in res[s] and not res[s].get('a')]
        if sib:
            w = sum(max(s['p'], 1) for s in sib)
            e['la'] = round(sum(s['la'] * max(s['p'], 1) for s in sib) / w, 5)
            e['lo'] = round(sum(s['lo'] * max(s['p'], 1) for s in sib) / w, 5)
        elif e['d'] in dep_center:
            e['la'], e['lo'] = dep_center[e['d']]
        else:
            e['la'], e['lo'] = 46.6, 2.4
        e['a'] = 1

    # Slugs uniques par département
    seen = defaultdict(set)
    for code in sorted(res, key=lambda k: -res[k]['p']):
        e = res[code]
        s = slug(e['n'])
        if s in seen[e['d']]:
            s = s + '-' + code.lower()
        seen[e['d']].add(s)
        e['s'] = s

    # Alias (communes déléguées / associées, anciens noms) -> commune actuelle
    alias = {}
    for c in communes:
        if c['type'] in ('commune-deleguee', 'commune-associee') and c.get('chefLieu') in res:
            alias[norm(c['nom']) + '|' + c['departement']] = c['chefLieu']

    # Fichiers par département + index de recherche + codes postaux
    per_dep = defaultdict(dict)
    for code, e in res.items():
        per_dep[e['d']][code] = e
    for d, items in per_dep.items():
        write_php(os.path.join(out, 'communes', d.lower() + '.php'), dict(sorted(items.items())), compact=True)

    search = defaultdict(list)
    for code, e in res.items():
        nm = norm(e['n'])
        search[nm[:2] if len(nm) >= 2 else nm].append([nm, code, e['p']])
    for k, rows in search.items():
        rows.sort(key=lambda r: -r[2])
        write_php(os.path.join(out, 'search', k.replace(' ', '_') + '.php'), [[r[0], r[1]] for r in rows], compact=True)

    write_php(os.path.join(out, 'cp.php'), {cp: codes for cp, codes in sorted(by_cp.items())}, compact=True)
    write_php(os.path.join(out, 'alias.php'), alias, compact=True)

    deps = {}
    for code, (name, din, dde) in DEPTS.items():
        reg = next((c.get('region') for c in actual if c['departement'] == code), '')
        center = dep_center.get(code, (46.6, 2.4))
        # Préfecture : commune la plus peuplée du département (approximation suffisante pour le centrage)
        top = max(per_dep[code].items(), key=lambda kv: kv[1]['p'])[0] if per_dep.get(code) else ''
        deps[code] = {'code': code, 'name': name, 'slug': slug(name) + '-' + code.lower(), 'region': reg, 'in': din, 'of': dde,
                      'la': center[0], 'lo': center[1], 'top': top, 'n': len(per_dep.get(code, {}))}
    write_php(os.path.join(out, 'departements.php'), deps)

    regs = {}
    for code, (name, rin) in REGIONS.items():
        dlist = [d for d, v in deps.items() if v['region'] == code]
        regs[code] = {'code': code, 'name': name, 'slug': slug(name), 'in': rin, 'deps': dlist}
    write_php(os.path.join(out, 'regions.php'), regs)
    write_php(os.path.join(out, 'old_regions.php'), OLD_REGIONS)

    print(f"{len(res)} communes, {approx} positions approchées, {len(deps)} départements, {len(regs)} régions, {len(alias)} alias")


if __name__ == '__main__':
    main()
