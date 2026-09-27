"""Fichier des intercommunalités de France pour la prospection, avec les coordonnées publiées dans l'Annuaire de
l'administration de Service-public.fr. Onglets : toutes les intercommunalités, puis les communautés de communes,
puis les communautés d'agglomération, communautés urbaines et métropoles.

Sources ouvertes (Licence Ouverte Etalab 2.0) :
  - geo.api.gouv.fr : liste officielle des EPCI, population, départements, régions, communes membres ;
  - api-lannuaire.service-public.fr : Annuaire de l'administration (Dila) : adresse, téléphone, courriel, site ;
  - recherche-entreprises.api.gouv.fr : adresse du siège (SIRENE) quand l'annuaire n'a pas de fiche.

Usage : python3 prospection/intercommunalites.py   (openpyxl requis ; réseau vers les trois adresses ci-dessus)
Résultat : prospection/intercommunalites-france.xlsx
"""
import collections
import json
import os
import time
import urllib.parse
import urllib.request
from datetime import date

from openpyxl import Workbook
from openpyxl.styles import Alignment, Font, PatternFill
from openpyxl.utils import get_column_letter

ICI = os.path.dirname(os.path.abspath(__file__))
CACHE = os.path.join(ICI, '.cache')
SORTIE = os.path.join(ICI, 'intercommunalites-france.xlsx')
GEO = 'https://geo.api.gouv.fr'
ANNUAIRE = 'https://api-lannuaire.service-public.fr/api/explore/v2.1/catalog/datasets/api-lannuaire-administration'
SIRENE = 'https://recherche-entreprises.api.gouv.fr/search'
UA = {'User-Agent': 'terricom-prospection/1.0 (https://terricom.fr)'}

TYPES = {'CC': 'Communauté de communes', 'CA': 'Communauté d’agglomération', 'CU': 'Communauté urbaine', 'METRO': 'Métropole', 'MET69': 'Métropole de Lyon'}


def get(url, tries=6):
    for a in range(tries):
        try:
            return urllib.request.urlopen(urllib.request.Request(url, headers=UA), timeout=300).read()
        except Exception:
            if a == tries - 1:
                raise
            time.sleep(3 * (a + 1))


def cached(name, url):
    """Télécharge une fois, puis relit le fichier en cache (supprimer .cache/ pour tout rafraîchir)."""
    os.makedirs(CACHE, exist_ok=True)
    f = os.path.join(CACHE, name)
    if not os.path.exists(f):
        open(f, 'wb').write(get(url))
    return json.load(open(f, encoding='utf-8'))


def champs(r, cle):
    v = r.get(cle)
    if not v:
        return []
    try:
        return json.loads(v) if isinstance(v, str) else v
    except json.JSONDecodeError:
        return []


def adresse(r):
    """Adresse physique de préférence, sinon la première adresse publiée."""
    adrs = champs(r, 'adresse')
    if not adrs:
        return '', '', ''
    a = next((x for x in adrs if x.get('type_adresse') == 'Adresse'), adrs[0])
    rue = ', '.join(x for x in (a.get('numero_voie'), a.get('complement1'), a.get('complement2'), a.get('service_distribution')) if x)
    return rue, a.get('code_postal', ''), a.get('nom_commune', '')


def par_nom(nom):
    """Fiche de l'annuaire retrouvée par le nom (pour les quelques EPCI dont le SIREN n'y figure pas)."""
    court = nom.split(' ', 1)[1] if nom.startswith(('CC ', 'CA ', 'CU ')) else nom
    court = court.removeprefix('du ').removeprefix('de la ').removeprefix('des ').removeprefix('de ').removeprefix("d'")
    where = f'startswith(ancien_code_pivot,"epci") and search(nom,"{court}")'
    try:
        res = json.loads(get(f'{ANNUAIRE}/records?limit=5&where={urllib.parse.quote(where)}'))['results']
    except Exception:
        return None
    return res[0] if len(res) == 1 else None


def siege(siren):
    """Adresse du siège au registre SIRENE."""
    try:
        res = json.loads(get(f'{SIRENE}?q={siren}&per_page=1'))['results']
    except Exception:
        return '', '', ''
    if not res or res[0]['siren'] != siren:
        return '', '', ''
    s = res[0]['siege']
    rue = ' '.join(x for x in (s.get('numero_voie'), s.get('type_voie'), s.get('libelle_voie')) if x)
    return rue.title() if rue else '', s.get('code_postal') or '', (s.get('libelle_commune') or '').title()


def onglet_ca_modele_b(wb, cc):
    """Chiffre d'affaires du modèle B (abonnement territorial) pour chaque communauté de communes.

    Paramètres modifiables en tête d'onglet ; tous les montants sont des formules Excel qui en dépendent.
    """
    ws = wb.create_sheet('CA modèle B', 1)
    vert = PatternFill('solid', fgColor='1F6B52')
    creme = PatternFill('solid', fgColor='F7F4EC')
    ambre = PatternFill('solid', fgColor='FBE6C9')
    gras = Font(bold=True)
    euros = '# ##0 €'

    ws['A1'] = 'Modèle B : abonnement territorial, chiffre d’affaires par communauté de communes'
    ws['A1'].font = Font(bold=True, size=14, color='1F6B52')
    ws['A2'] = 'Abonnement annuel HT = socle + prix par habitant × population, plafonné. Modifiez les cases jaunes : tout se recalcule.'
    ws['A2'].font = Font(italic=True, color='5B625D')
    params = [('Socle annuel HT', 10000, euros), ('Prix par habitant HT', 0.20, '0.00 €'), ('Plafond annuel HT', 59000, euros), ('Mise en service HT (1re année)', 5000, euros), ('Seuil de dispense de procédure HT', 60000, euros)]
    for i, (lib, val, fmt) in enumerate(params):
        r = 4 + i
        ws.cell(r, 1, lib).font = gras
        c = ws.cell(r, 2, val)
        c.number_format = fmt
        c.fill = PatternFill('solid', fgColor='FFF2B3')
    socle, hab, plafond, mes, seuil = '$B$4', '$B$5', '$B$6', '$B$7', '$B$8'

    debut = 20  # première ligne de données
    fin = debut + len(cc) - 1
    # synthèse
    ws['D4'] = 'Si toutes les CC signaient'
    ws['D4'].font = gras
    synthese = [
        ('Communautés de communes', f'=COUNTA(A{debut}:A{fin})', '# ##0'),
        ('Abonnements annuels récurrents', f'=SUM(E{debut}:E{fin})', euros),
        ('Mises en service', f'=SUM(F{debut}:F{fin})', euros),
        ('Chiffre d’affaires de la 1re année', f'=SUM(G{debut}:G{fin})', euros),
        ('Chiffre d’affaires sur 3 ans', f'=SUM(H{debut}:H{fin})', euros),
        ('Abonnement moyen par CC', f'=AVERAGE(E{debut}:E{fin})', euros),
        ('CC au-dessus du seuil sur 3 ans', f'=COUNTIF(I{debut}:I{fin},"Oui")', '# ##0'),
    ]
    for i, (lib, f, fmt) in enumerate(synthese):
        ws.cell(5 + i, 4, lib)
        c = ws.cell(5 + i, 5, f)
        c.number_format = fmt
        c.font = gras
    ws['G4'] = 'Selon la part des CC clientes'
    ws['G4'].font = gras
    ws['G5'], ws['H5'], ws['I5'] = 'Part des CC', 'CC clientes', 'Abonnements / an'
    for c in ('G5', 'H5', 'I5'):
        ws[c].font = gras
    for i, part in enumerate((0.01, 0.05, 0.10, 0.20, 0.30, 0.50)):
        r = 6 + i
        ws.cell(r, 7, part).number_format = '0 %'
        ws.cell(r, 8, f'=ROUND(G{r}*$E$5,0)').number_format = '# ##0'
        ws.cell(r, 9, f'=G{r}*$E$6').number_format = euros
    ws.cell(13, 7, 'Moyenne : chaque CC cliente rapporte en moyenne l’abonnement moyen ci-contre.').font = Font(italic=True, color='5B625D')

    entetes = ['Communauté de communes', 'Département', 'Code', 'Population', 'Abonnement annuel HT', 'Mise en service HT', 'Total 1re année HT', 'Total 3 ans HT', 'Au-dessus du seuil sur 3 ans', 'Coût par habitant / an']
    for c, e in enumerate(entetes, start=1):
        cell = ws.cell(debut - 1, c, e)
        cell.font = Font(bold=True, color='FFFFFF')
        cell.fill = vert
        cell.alignment = Alignment(vertical='center', wrap_text=True)
    ws.row_dimensions[debut - 1].height = 32
    for i, l in enumerate(cc):
        r = debut + i
        ws.cell(r, 1, l[0])
        ws.cell(r, 2, l[4])
        ws.cell(r, 3, l[3])
        ws.cell(r, 4, l[6]).number_format = '# ##0'
        ws.cell(r, 5, f'=MIN({socle}+{hab}*D{r},{plafond})').number_format = euros
        ws.cell(r, 6, f'={mes}').number_format = euros
        ws.cell(r, 7, f'=E{r}+F{r}').number_format = euros
        ws.cell(r, 8, f'=3*E{r}+F{r}').number_format = euros
        ws.cell(r, 9, f'=IF(H{r}>{seuil},"Oui","Non")')
        ws.cell(r, 10, f'=IF(D{r}>0,E{r}/D{r},"")').number_format = '0.00 €'
        if i % 2:
            for c in range(1, 11):
                ws.cell(r, c).fill = creme
    total = fin + 1
    ws.cell(total, 1, 'TOTAL').font = gras
    ws.cell(total, 4, f'=SUM(D{debut}:D{fin})').number_format = '# ##0'
    for col in 'EFGH':
        c = ws[f'{col}{total}']
        c.value = f'=SUM({col}{debut}:{col}{fin})'
        c.number_format = euros
    for c in range(1, 11):
        ws.cell(total, c).fill = ambre
        ws.cell(total, c).font = gras
    for col, w in zip('ABCDEFGHIJ', (46, 22, 8, 12, 20, 18, 18, 16, 16, 14)):
        ws.column_dimensions[col].width = w
    ws.column_dimensions['D'].width = 34
    ws.freeze_panes = f'B{debut}'
    ws.auto_filter.ref = f'A{debut - 1}:J{fin}'
    wb.calculation.fullCalcOnLoad = True


def main():
    epcis = cached('epcis.json', f'{GEO}/epcis?fields=nom,code,type,population,codesDepartements,codesRegions&format=json')
    deps = {d['code']: d['nom'] for d in cached('departements.json', f'{GEO}/departements?fields=nom,code')}
    regs = {r['code']: r['nom'] for r in cached('regions.json', f'{GEO}/regions?fields=nom,code')}
    communes = cached('communes.json', f'{GEO}/communes?fields=code,codeEpci&format=json')
    nb = collections.Counter(c.get('codeEpci') for c in communes)
    where = urllib.parse.quote('startswith(ancien_code_pivot,"epci")')
    annuaire = cached('annuaire-epci.json', f'{ANNUAIRE}/exports/json?where={where}')
    fiches = {}
    for r in annuaire:
        if r.get('siren'):
            fiches.setdefault(r['siren'], r)

    lignes = collections.defaultdict(list)
    sans_fiche = []
    for e in sorted(epcis, key=lambda x: (x['codesDepartements'][0] if x.get('codesDepartements') else '', x['nom'])):
        r = fiches.get(e['code']) or par_nom(e['nom'])
        source = 'Annuaire Service-public.fr'
        if r:
            rue, cp, ville = adresse(r)
        else:
            rue, cp, ville = siege(e['code'])
            source = 'SIRENE (siège) : pas de fiche dans l’annuaire'
            sans_fiche.append(e['nom'])
        tel = ' / '.join(t['valeur'] for t in champs(r, 'telephone') if t.get('valeur')) if r else ''
        site = next((s['valeur'] for s in champs(r, 'site_internet') if s.get('valeur')), '') if r else ''
        codes = e.get('codesDepartements') or []
        lignes['CC' if e['type'] == 'CC' else 'AUTRES'].append(
            [
                e['nom'],
                TYPES.get(e['type'], e['type']),
                e['code'],
                ', '.join(codes),
                ', '.join(deps.get(c, c) for c in codes),
                ', '.join(regs.get(c, c) for c in e.get('codesRegions') or []),
                e.get('population'),
                nb.get(e['code']) or None,
                rue,
                cp,
                ville,
                tel,
                (r or {}).get('adresse_courriel') or '',
                site,
                (r or {}).get('formulaire_contact') or '',
                (r or {}).get('url_service_public') or '',
                source,
                '',
                '',
                '',
            ]
        )

    entetes = [
        'Nom',
        'Type',
        'SIREN',
        'Code département',
        'Département',
        'Région',
        'Population',
        'Communes',
        'Adresse',
        'Code postal',
        'Commune',
        'Téléphone',
        'Courriel',
        'Site web',
        'Formulaire de contact',
        'Fiche Service-public.fr',
        'Source des coordonnées',
        'Statut prospection',
        'Interlocuteur',
        'Notes',
    ]
    largeurs = [46, 24, 12, 11, 22, 26, 11, 10, 38, 11, 26, 18, 34, 34, 30, 30, 26, 18, 22, 40]
    liens = {13: 'mailto:', 14: '', 15: '', 16: ''}

    wb = Workbook()
    lignes['TOUTES'] = sorted(lignes['CC'] + lignes['AUTRES'], key=lambda l: (l[3], l[0]))
    onglets = (('TOUTES', 'Toutes les intercommunalités'), ('CC', 'Communautés de communes'), ('AUTRES', 'Agglos, CU, métropoles'))
    for i, (cle, titre) in enumerate(onglets):
        ws = wb.active if i == 0 else wb.create_sheet()
        ws.title = titre
        ws.append(entetes)
        for ligne in lignes[cle]:
            ws.append(ligne)
        vert, ambre = PatternFill('solid', fgColor='1F6B52'), PatternFill('solid', fgColor='F4B266')
        for c, cell in enumerate(ws[1], start=1):
            cell.font = Font(bold=True, color='FFFFFF' if c <= 17 else '14201B')
            cell.fill = vert if c <= 17 else ambre
            cell.alignment = Alignment(vertical='center', wrap_text=True)
        ws.row_dimensions[1].height = 32
        for c, w in enumerate(largeurs, start=1):
            ws.column_dimensions[get_column_letter(c)].width = w
        for row in ws.iter_rows(min_row=2):
            row[6].number_format = '# ##0'
            for col, prefixe in liens.items():
                cell = row[col - 1]
                if cell.value:
                    cell.hyperlink = prefixe + cell.value
                    cell.font = Font(color='1F6B52', underline='single')
        ws.freeze_panes = 'B2'
        ws.auto_filter.ref = ws.dimensions

    onglet_ca_modele_b(wb, lignes['CC'])

    src = wb.create_sheet('Sources')
    for ligne in [
        ['Intercommunalités de France : coordonnées publiques'],
        [f'Extraction du {date.today().strftime("%d/%m/%Y")}'],
        [],
        ['Liste, population, départements, régions, communes', 'geo.api.gouv.fr (découpage administratif, Insee / Etalab)'],
        ['Adresse, téléphone, courriel, site, formulaire', 'Annuaire de l’administration, Service-public.fr (Dila)'],
        ['Adresse du siège (EPCI sans fiche dans l’annuaire)', 'Registre SIRENE, recherche-entreprises.api.gouv.fr'],
        ['Licence', 'Licence Ouverte Etalab 2.0 : réutilisation libre, source à mentionner'],
        [],
        ['Toutes les intercommunalités (premier onglet)', len(lignes['TOUTES'])],
        ['Communautés de communes', len(lignes['CC'])],
        ['Autres intercommunalités', len(lignes['AUTRES'])],
        ['Sans fiche dans l’annuaire', ', '.join(sans_fiche) or 'aucune'],
        [],
        ['Usage', 'Coordonnées institutionnelles publiées par les collectivités. Prospection B2B vers les collectivités :'],
        ['', 'proposer une désinscription et respecter les demandes d’opposition (RGPD).'],
    ]:
        src.append(ligne)
    src['A1'].font = Font(bold=True, size=14, color='1F6B52')
    src.column_dimensions['A'].width = 48
    src.column_dimensions['B'].width = 90
    wb.save(SORTIE)
    print(f'{len(lignes["CC"])} communautés de communes, {len(lignes["AUTRES"])} autres EPCI ; sans fiche : {sans_fiche}')
    print(SORTIE)


if __name__ == '__main__':
    main()
