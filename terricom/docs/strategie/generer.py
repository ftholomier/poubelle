"""Génère docs/strategie/strategie.html (document interne des fondateurs) à partir du modèle économique.

Usage : python3 docs/strategie/generer.py && node scripts/strategie.mjs
Les chiffres des simulations viennent de modele.py : changer une hypothèse là-bas, puis régénérer.
"""
from pathlib import Path

import modele as M

ICI = Path(__file__).parent
CSS = (ICI / 'charte.css').read_text()
R = M.resultats()
C = R['central']


def eur(v, dec=False):
    """12 345 € (espaces fines insécables)."""
    s = f"{v:,.0f}".replace(',', ' ')
    return f"{s} €"


def keur(v):
    if abs(v) >= 1_000_000:
        return f"{v / 1e6:.2f}".replace('.', ',') + ' M€'
    return f"{round(v / 1000):,}".replace(',', ' ') + ' k€'


def nb(v):
    return f"{round(v):,}".replace(',', ' ')


def pct(v, d=0):
    return f"{v * 100:.{d}f}".replace('.', ',') + ' %'


# ─── Graphiques SVG ────────────────────────────────────────────────────────
def barres(valeurs, etiquettes, w=520, h=260, couleurs=None, fmt=nb, max_v=None):
    n = len(valeurs)
    mx = max_v or max(valeurs) * 1.12
    bw = w / n * 0.62
    out = [f'<svg viewBox="0 0 {w} {h + 50}" width="{w}" height="{h + 50}" style="overflow:visible">']
    for i, (v, e) in enumerate(zip(valeurs, etiquettes)):
        x = i * w / n + (w / n - bw) / 2
        bh = v / mx * h
        c = (couleurs or ['#1F6B52'])[i % len(couleurs or ['#1F6B52'])]
        out.append(f'<rect x="{x:.1f}" y="{h - bh:.1f}" width="{bw:.1f}" height="{bh:.1f}" rx="8" fill="{c}"/>')
        out.append(f'<text x="{x + bw / 2:.1f}" y="{h - bh - 10:.1f}" text-anchor="middle" font-family="Bricolage" font-weight="800" font-size="22" fill="#14201B">{fmt(v)}</text>')
        out.append(f'<text x="{x + bw / 2:.1f}" y="{h + 26}" text-anchor="middle" font-family="Instrument" font-size="15" fill="#4A514C">{e}</text>')
    out.append('</svg>')
    return ''.join(out)


def empile(series, etiquettes, couleurs, w=640, h=300):
    """Barres empilées : series = [(nom, [valeurs par année])]."""
    n = len(etiquettes)
    tot = [sum(s[1][i] for s in series) for i in range(n)]
    mx = max(tot) * 1.12
    bw = w / n * 0.58
    out = [f'<svg viewBox="0 0 {w} {h + 40}" width="{w}" height="{h + 40}" style="overflow:visible">']
    for i in range(n):
        x = i * w / n + (w / n - bw) / 2
        y = h
        for (nom, vals), c in zip(series, couleurs):
            bh = vals[i] / mx * h
            y -= bh
            out.append(f'<rect x="{x:.1f}" y="{y:.1f}" width="{bw:.1f}" height="{bh:.1f}" fill="{c}"/>')
        out.append(f'<text x="{x + bw / 2:.1f}" y="{y - 10:.1f}" text-anchor="middle" font-family="Bricolage" font-weight="800" font-size="19" fill="#14201B">{keur(tot[i])}</text>')
        out.append(f'<text x="{x + bw / 2:.1f}" y="{h + 26}" text-anchor="middle" font-family="Instrument" font-size="15" fill="#4A514C">{etiquettes[i]}</text>')
    out.append('</svg>')
    return ''.join(out)


def courbes(series, etiquettes, couleurs, w=620, h=280):
    """Courbes de trésorerie cumulée (peut être négative)."""
    allv = [v for _, vals in series for v in vals] + [0]
    lo, hi = min(allv), max(allv)
    pad = (hi - lo) * 0.1
    lo, hi = lo - pad, hi + pad
    X = lambda i: 40 + i * (w - 80) / (len(etiquettes) - 1)
    Y = lambda v: h - (v - lo) / (hi - lo) * h
    out = [f'<svg viewBox="0 0 {w} {h + 40}" width="{w}" height="{h + 40}" style="overflow:visible">']
    out.append(f'<line x1="20" x2="{w - 20}" y1="{Y(0):.1f}" y2="{Y(0):.1f}" stroke="#AEBDB5" stroke-dasharray="5 5"/>')
    out.append(f'<text x="0" y="{Y(0) + 5:.1f}" text-anchor="start" font-family="Instrument" font-size="13" fill="#6B726D">0</text>')
    for (nom, vals), c in zip(series, couleurs):
        pts = ' '.join(f'{X(i):.1f},{Y(v):.1f}' for i, v in enumerate(vals))
        out.append(f'<polyline points="{pts}" fill="none" stroke="{c}" stroke-width="4" stroke-linejoin="round"/>')
        for i, v in enumerate(vals):
            out.append(f'<circle cx="{X(i):.1f}" cy="{Y(v):.1f}" r="5" fill="{c}"/>')
        out.append(f'<text x="{X(len(vals) - 1) + 10:.1f}" y="{Y(vals[-1]) + 5:.1f}" font-family="Bricolage" font-weight="800" font-size="16" fill="{c}">{nom} {keur(vals[-1])}</text>')
    for i, e in enumerate(etiquettes):
        out.append(f'<text x="{X(i):.1f}" y="{h + 28}" text-anchor="middle" font-family="Instrument" font-size="15" fill="#4A514C">{e}</text>')
    out.append('</svg>')
    return ''.join(out)


def entonnoir(etapes, w=560):
    out = []
    mx = etapes[0][1]
    for i, (lib, v, note) in enumerate(etapes):
        largeur = max(16, v / mx * 100)
        fond = ['#14201B', '#1F6B52', '#2E8466', '#C8702A', '#F4B266'][i % 5]
        coul = '#14201B' if i == 4 else '#fff'
        out.append(
            f'<div style="display:grid;grid-template-columns:{w}px 1fr;gap:18px;align-items:center;margin-bottom:9px">'
            f'<div style="height:52px;display:flex;justify-content:center"><div style="width:{largeur:.0f}%;background:{fond};color:{coul};border-radius:12px;display:flex;align-items:center;justify-content:center;gap:10px;font-family:Bricolage;font-weight:800;font-size:22px">{nb(v)}</div></div>'
            f'<div style="font-size:17px;color:var(--text2);line-height:1.3"><b style="font-family:Bricolage;font-size:19px;color:var(--ink)">{lib}</b><br>{note}</div></div>'
        )
    return ''.join(out)


# ─── Diapositives ─────────────────────────────────────────────────────────
S = []


def slide(corps, cls='', rubrique=''):
    S.append((cls, corps, rubrique))


def tete(kicker, titre, sous=None):
    h = f'<div class="kicker">{kicker}</div><h2 class="t">{titre}</h2>'
    if sous:
        h += f'<p class="sub">{sous}</p>'
    return h


def tableau(entetes, lignes, largeurs=None, style=''):
    cols = ''.join(f'<col style="width:{l}">' for l in (largeurs or []))
    th = ''.join(f'<th>{e}</th>' for e in entetes)
    tr = ''.join('<tr>' + ''.join(f'<td>{c}</td>' for c in l) + '</tr>' for l in lignes)
    return f'<table class="tb" style="{style}"><colgroup>{cols}</colgroup><thead><tr>{th}</tr></thead><tbody>{tr}</tbody></table>'


def points(items, start=1):
    lis = ''.join(f'<li><span class="nb">{i}</span><span><b>{t}</b>{d}</span></li>' for i, (t, d) in enumerate(items, start))
    return f'<ol class="points">{lis}</ol>'


def intercalaire(num, titre, sous, cls='green'):
    slide(f'<div class="num">{num}</div><h2 class="t" style="font-size:62px;margin-top:22px">{titre}</h2><p class="sub">{sous}</p>', f'{cls} divider', '')


T = M.TRANCHES
HD_LIC = 7900
HD_MES = 1900 + 50 * 32
HD_3ANS = HD_LIC * (1 + 1.02 + 1.02 ** 2)
TOP_3ANS = 16900 * (1 + 1.02 + 1.02 ** 2) + 1900 + 50 * 37
cen = {l['annee']: l for l in C}
pru = {l['annee']: l for l in R['prudent']}
amb = {l['annee']: l for l in R['ambitieux']}
sans_pros = M.simuler('central', pros_actifs=False)

# 0 — Couverture
slide(f'''
<div class="blob" style="width:560px;height:560px;right:-120px;top:-160px;background:var(--green)"></div>
<div class="blob" style="width:260px;height:260px;left:-80px;bottom:-110px;background:#23332C"></div>
<div style="position:relative;display:flex;justify-content:space-between;font-size:14px;color:var(--sage)"><span>terricom.fr</span><span>Document interne · fondateurs · septembre 2026</span></div>
<div style="position:relative;margin-top:92px;display:flex;align-items:center;gap:18px"><span class="mark" style="width:70px;height:70px"><span style="font-size:44px">t</span></span><span class="wm" style="font-size:86px;color:var(--cream)">terricom<i>.</i></span></div>
<h1 class="t" style="position:relative;font-size:58px;color:var(--cream);margin-top:28px;max-width:900px">Aller chercher <em>toutes les communautés de communes.</em></h1>
<p class="sub" style="position:relative;max-width:840px">Marché, offre, tarifs, stratégie commerciale et web, modèle économique et simulations sur cinq ans : ce que nous proposons, chiffres à l’appui, et pourquoi.</p>
<div style="position:relative;margin-top:auto;display:flex;gap:10px;flex-wrap:wrap">{''.join(f'<span style="border:1px solid rgba(255,255,255,.25);border-radius:999px;padding:6px 14px;font-size:14px;color:var(--sage2)">{x}</span>' for x in ['1 · Le marché', '2 · Notre solution', '3 · Nos tarifs', '4 · Vendre partout', '5 · Webmarketing', '6 · Modèle et simulations', '7 · Plan d’action'])}</div>
''', 'dark', '')

# 1 — Synthèse
slide(tete('Synthèse', 'Ce que nous proposons, <em>en une page.</em>') + f'''
<div class="grid3" style="margin-top:26px">
<div class="card green"><h3>Une grille nationale unique</h3><p>La même pour tous, publique, par tranche de population : de {eur(4900)} à {eur(16900)} HT par an pour une communauté de communes, tout compris. Environ 0,43 € par habitant en moyenne.</p></div>
<div class="card ink"><h3>Toujours sous le seuil</h3><p>Trois ans de licence et la mise en service restent sous {eur(60000)} HT pour toutes les CC : signature sur simple devis, sans appel d’offres.</p></div>
<div class="card amber"><h3>Rentable sans les commerçants</h3><p>Les licences suffisent à équilibrer le modèle. Les abonnements des commerçants sont un bonus, pas un pari : c’est là qu’ont échoué les places de marché locales.</p></div>
<div class="card"><h3>Une démo sur leur territoire</h3><p>Chaque CC voit son portail rempli de ses vraies entreprises avant le premier rendez-vous. C’est notre arme pour couvrir les 989 CC depuis la visio.</p></div>
<div class="card"><h3>Objectif 2031</h3><p>Scénario central : {nb(cen[2031]['actifs'])} CC clientes ({pct(cen[2031]['part_marche'])} du marché), {keur(cen[2031]['ca'])} de chiffre d’affaires, {keur(cen[2031]['resultat'])} de résultat.</p></div>
<div class="card"><h3>Ce qu’il faut décider</h3><p>Valider la grille, lancer l’offre 2027, lever {keur(300000)} environ, recruter un premier commercial en 2028.</p></div>
</div>''', '', 'Synthèse')

# ═══ 1 LE MARCHÉ ═══
intercalaire('1', 'Le marché : <em>989 portes à ouvrir.</em>', 'Qui achète, combien de clients possibles, avec quel budget et à quel moment.')

slide(tete('1 · Le marché national', 'Un marché <em>clair et dénombrable.</em>', 'Le découpage officiel des intercommunalités (Etalab, millésime 2025) : pas d’estimation, un comptage.') + f'''
<div class="grid4" style="margin-top:28px">
<div class="card green"><div class="big" style="font-size:68px;color:var(--amber)">989</div><p style="margin-top:8px">communautés de communes, notre cœur de cible</p></div>
<div class="card"><div class="big" style="font-size:68px;color:var(--green)">21,4 M</div><p style="margin-top:8px">d’habitants vivent dans une communauté de communes</p></div>
<div class="card"><div class="big" style="font-size:68px;color:var(--green)">19 000</div><p style="margin-top:8px">habitants et 21 communes dans une CC médiane</p></div>
<div class="card"><div class="big" style="font-size:68px;color:var(--green)">{keur(M.MARCHE_LICENCES)}</div><p style="margin-top:8px">de licences par an si toutes les CC étaient clientes (notre grille)</p></div>
</div>
<div class="grid3" style="margin-top:20px">
<div class="card mint"><h3>Et au-delà</h3><p>230 communautés d’agglomération, 14 communautés urbaines, 21 métropoles, et 34 969 communes. Notre grille s’applique aussi à elles (voir tarifs).</p></div>
<div class="card mint"><h3>Un besoin prioritaire</h3><p>La vacance commerciale en centre-ville atteint 11,7 % en 2025 (10,8 % en 2024), en hausse depuis trois ans (Codata).</p></div>
<div class="card mint"><h3>Un tissu de très petites entreprises</h3><p>97 % des entreprises sont des microentreprises (Insee) : peu de moyens pour être visibles seules.</p></div>
</div>''', '', '1 · Le marché')

slide(tete('1 · Le marché national', 'Les CC par taille : <em>deux tiers entre 10 000 et 35 000 habitants.</em>') + f'''
<div class="split" style="grid-template-columns:600px 1fr;margin-top:18px">
<div>{barres([t[3] for t in T], ['< 10 000', '10–20 000', '20–35 000', '35–50 000', '50 000 +'], w=580, h=280, couleurs=['#AEBDB5', '#1F6B52', '#1F6B52', '#AEBDB5', '#AEBDB5'])}<p class="small" style="font-size:14px;color:var(--muted);margin-top:4px">Nombre de communautés de communes par tranche de population.</p></div>
{points([
    ('632 CC entre 10 000 et 35 000 habitants', 'Notre cible prioritaire : assez grandes pour avoir un service économique, assez petites pour ne pas avoir d’outil maison.'),
    ('217 petites CC', 'Moins de 10 000 habitants, budgets serrés : une entrée de gamme à 4 900 € HT par an, sans mise en service lourde.'),
    ('140 CC de plus de 35 000 habitants', 'Souvent périurbaines, avec des zones commerciales : la licence la plus élevée et des besoins de campagnes.'),
])}
</div>''', '', '1 · Le marché')

slide(tete('1 · Qui achète, et quand', 'Vendre au bon interlocuteur, <em>au bon mois.</em>') + '''
<div class="grid3" style="margin-top:26px">
<div class="card"><h3>Qui décide</h3><p>Le président et le vice-président au développement économique portent le projet ; le DGS et le chargé de développement économique ou le manager de commerce l’instruisent. C’est à ce dernier que nous parlons d’abord : l’outil lui fait gagner du temps.</p></div>
<div class="card"><h3>Sur quel budget</h3><p>Budget de fonctionnement du développement économique, de l’attractivité ou du tourisme. Un poste de manager de commerce coûte 50 à 60 k€ chargés par an, cofinancé 20 k€ par an par la Banque des Territoires (500 postes financés depuis février 2026) : notre licence en est l’outil de travail.</p></div>
<div class="card"><h3>Quand</h3><p>Débat d’orientation budgétaire puis budget voté au plus tard le 15 avril. On prospecte de septembre à novembre pour être inscrit au budget suivant ; on signe de janvier à juin. 2027 est le premier budget complet des équipes élues en 2026 : la fenêtre est maintenant.</p></div>
</div>
<div class="card green" style="margin-top:20px;display:flex;align-items:center;gap:26px"><span class="big" style="font-size:52px;color:var(--amber);white-space:nowrap">60 000 €</span><p style="font-size:20px">HT : seuil sous lequel un achat de services se fait sans publicité ni mise en concurrence, depuis le 1er avril 2026 (décret 2025-1386). Notre grille est construite pour rester dessous sur trois ans, pour toutes les CC.</p></div>''', '', '1 · Le marché')

slide(tete('1 · Ce qui a échoué', 'Les places de marché locales : <em>la leçon à retenir.</em>') + '''
<div class="split" style="grid-template-columns:1fr 1fr;margin-top:20px;align-items:start">
<div class="card ink" style="padding:28px"><h3>Ce que dit la Cour des comptes (2023)</h3><p style="font-size:19px">Le soutien aux places de marché locales a été « un échec » : dans plus de la moitié des villes Action cœur de ville étudiées, moins de 25 % des commerçants ont adhéré ; 54 % des collectivités ne voient aucun effet sur la fréquentation. Ma Ville Mon Shopping (La Poste), facturée 0,22 à 0,50 € par habitant, ferme en 2025.</p></div>
''' + points([
    ('Ne pas vendre de l’e-commerce', 'Les habitants n’achètent pas leur pain en ligne. Nous vendons de la visibilité, de l’animation et du pilotage.'),
    ('Ne pas dépendre de l’adhésion', 'Nos fiches existent pour toutes les entreprises dès le premier jour, sans que le commerçant fasse quoi que ce soit.'),
    ('Ne pas prélever de commission', 'Rien sur les ventes : le commerçant n’a aucune raison de se méfier.'),
    ('Montrer des résultats aux élus', 'Statistiques, rapport pour le conseil, météo du commerce : la collectivité voit ce qu’elle paie.'),
]) + '</div>', '', '1 · Le marché')

slide(tete('1 · La concurrence', 'Personne ne fait <em>exactement cela.</em>') + tableau(
    ['Solution', 'Pour qui', 'Prix constaté', 'Ce qui manque'],
    [
        ['Fiche Google', 'Chaque commerçant, seul', 'Gratuit', 'Une entreprise sur deux seulement l’utilise ; aucune vue d’ensemble ni animation pour la collectivité'],
        ['PagesJaunes (Solocal)', 'Commerçant', '25 à 78 € HT / mois', 'Payant pour chacun, rien pour le territoire'],
        ['Applications d’information (PanneauPocket, IntraMuros, Illiwap)', 'Mairie', '130 € TTC à ~720 € HT / an par commune', 'Informent les habitants, ne valorisent pas les entreprises'],
        ['Places de marché locales', 'Collectivité et commerçants', '0,22 à 0,50 € / hab. + commission', 'Modèle en échec, fermetures'],
        ['Cartes cadeaux territoriales', 'Collectivité', '5 à 20 % de commission', 'Un outil de relance ponctuel, complémentaire'],
        ['Site ou annuaire de la CC', 'Collectivité', 'Développement ponctuel', 'Vite périmé, jamais exhaustif'],
        ['<b>terricom</b>', '<b>Collectivité, communes, commerçants, habitants</b>', '<b>0,27 à 0,67 € / hab. selon la taille</b>', '<b>Exhaustif (SIRENE), gratuit pour le commerçant, animé et piloté</b>'],
    ], ['23%', '20%', '19%', '38%'], 'margin-top:22px'), '', '1 · Le marché')

# ═══ 2 NOTRE SOLUTION ═══
intercalaire('2', 'Notre solution : <em>forces et angles morts.</em>', 'Ce que le produit fait déjà, ce qu’il nous manque, et la promesse à tenir.', 'dark')

slide(tete('2 · Notre solution', 'Un produit complet, <em>déjà démontrable.</em>') + '''
<div class="grid4" style="margin-top:26px">
<div class="card"><h3>Portail public</h3><p>Toutes les entreprises du territoire, carte, recherche en langage naturel, agenda, circuits, emploi, trois langues.</p></div>
<div class="card"><h3>Back-office</h3><p>Communauté de communes et mairies : campagnes, lettre aux habitants, statistiques, rapport pour le conseil.</p></div>
<div class="card"><h3>Espace entreprise</h3><p>Fiche gratuite ; options avec assistant de rédaction, mini-site, lettres aux clients.</p></div>
<div class="card"><h3>Socle</h3><p>Hébergé en France, haute disponibilité, RGPD, double authentification, journal inaltérable.</p></div>
</div>
<div class="card green" style="margin-top:20px"><h3>La preuve : le Haut-Doubs en un jour</h3><p style="font-size:19px">3 753 établissements SIRENE des 32 communes des Lacs et Montagnes du Haut-Doubs, contrôlés un par un : 1 968 fiches en ligne, 219 cas douteux proposés à la validation, 1 566 exclusions certaines (SCI, meublés de particuliers, services publics). Puis une synchronisation mensuelle : environ 270 créations par an sur ce territoire.</p></div>''', '', '2 · Notre solution')

slide(tete('2 · Lucidité', 'Nos forces, <em>nos angles morts.</em>') + '''
<div class="grid2" style="margin-top:24px">
<div class="card green"><h3>Forces</h3><p>✓ Démonstration sur le vrai territoire du prospect, avant le rendez-vous<br>✓ 100 % des entreprises visibles le premier jour, sans effort du commerçant<br>✓ Gratuit pour le commerçant : pas de résistance<br>✓ Un seul outil pour la CC et toutes ses communes<br>✓ Coût marginal faible : une licence de plus coûte peu à servir</p></div>
<div class="card ink"><h3>Angles morts</h3><p>✗ Aucune référence client signée à ce jour<br>✗ Marque inconnue des élus et des services<br>✗ Adoption par les commerçants non encore prouvée sur le terrain<br>✗ Équipe de deux : la mise en service ne passera pas à l’échelle sans outillage<br>✗ Dépendance à la visio : il faudra aussi être présent sur le terrain</p></div>
</div>
<p class="sub" style="font-size:19px">Conséquence : la première année sert à obtenir 5 à 10 références solides, bien accompagnées, dont on publie les résultats.</p>''', '', '2 · Notre solution')

slide(tete('2 · Notre promesse', 'Une phrase, <em>trois bénéfices.</em>') + '''
<div class="card ink" style="margin-top:22px;padding:30px"><p style="font-family:Bricolage;font-weight:800;font-size:34px;line-height:1.15;color:var(--cream)">« Toutes les entreprises de votre territoire en vitrine dès le premier jour, et les outils pour les faire vivre, pour moins de 50 centimes par habitant. »</p></div>
<div class="grid3" style="margin-top:20px">
<div class="card"><h3>Pour les élus</h3><p>Une action visible pour le commerce local, des chiffres pour le conseil, une offre pour chaque commune.</p></div>
<div class="card"><h3>Pour les services</h3><p>Moins de tableaux et d’annuaires à tenir : la base se met à jour seule, les campagnes se préparent en une phrase.</p></div>
<div class="card"><h3>Pour les commerçants</h3><p>Une fiche offerte, juste et bien placée sur Google ; des options s’ils veulent aller plus loin, sans engagement.</p></div>
</div>''', '', '2 · Notre solution')

# ═══ 3 TARIFS ═══
intercalaire('3', 'Nos tarifs : <em>une grille, pour tout le monde.</em>', 'Les mêmes règles pour les 989 communautés de communes, publiques et sans négociation. Et pourquoi ces montants.', 'amber')

slide(tete('3 · Nos principes de prix', 'Six règles, <em>et leur raison.</em>') + points([
    ('La collectivité paie, le commerçant jamais obligé', 'La fiche reste gratuite pour chaque entreprise : c’est ce qui garantit l’exhaustivité et évite l’échec des places de marché.'),
    ('Une grille nationale, publique, identique', 'Même prix pour deux CC de même taille : pas de négociation, pas de soupçon, un argument de transparence pour des acheteurs publics.'),
    ('La population comme seul critère', 'Donnée officielle, connue de tous, liée à la valeur (habitants touchés, nombre d’entreprises). Pas de compteur d’usage à surveiller.'),
    ('Tout compris', 'Aucun module payant côté collectivité : on ne vend pas « la newsletter en plus ». Plus simple à acheter, plus simple à faire adopter.'),
    ('Sous 60 000 € HT sur trois ans', 'Signature sur devis pour toutes les CC, sans appel d’offres : le cycle de vente passe de 12 mois à 3 à 6 mois.'),
    ('Dégressif par habitant', 'Une petite CC paie plus par habitant (0,67 €) qu’une grande (0,27 €) : le coût de mise en route ne dépend pas de la taille, la capacité à payer si.'),
]), '', '3 · Nos tarifs')

grille = []
POP_MOY = [7260, 15093, 26238, 41590, 62721]  # population moyenne réelle des CC de chaque tranche
for (lib, lo, hi, n_, prix), pm in zip(T, POP_MOY):
    grille.append([lib, f'<b>{eur(prix)}</b>', f'{eur(prix / 12)}', f'≈ {prix / pm:.2f} €'.replace('.', ','), nb(n_), eur(prix * (1 + 1.02 + 1.0404) + 1900 + 50 * 30)])
slide(tete('3 · La grille nationale', 'Licence annuelle, <em>tout compris.</em>', 'Portail, back-office de la CC et de toutes ses communes, fiches gratuites pour toutes les entreprises, synchronisation SIRENE, assistant IA, trois langues, hébergement en France, support et mises à jour.') + tableau(
    ['Population de la CC', 'Licence HT / an', 'Soit par mois', 'Par habitant', 'CC concernées', '3 ans + mise en service'],
    grille, ['24%', '16%', '14%', '14%', '14%', '18%'], 'margin-top:20px') + f'<p style="font-size:15px;color:var(--muted);margin-top:10px">Indexation de 2 % par an au contrat. Colonne de droite : trois ans de licence et une mise en service de 30 communes, toujours sous {eur(60000)} HT (au plus {eur(TOP_3ANS)} pour les plus grandes CC).</p>', '', '3 · Nos tarifs')

slide(tete('3 · Pourquoi ces montants', 'Des prix <em>faciles à défendre.</em>') + '''
<div class="grid2" style="margin-top:22px">''' + points([
    ('0,43 € par habitant en moyenne', 'Au milieu de ce que facturait La Poste (0,22 à 0,50 €), mais sans commission et avec bien plus de services. Un élu raisonne en euros par habitant : c’est le chiffre à donner.'),
    ('Moins d’un sixième d’un manager de commerce', '7 900 € pour une CC de 15 000 habitants, contre 50 à 60 k€ par an pour un poste : l’outil qui démultiplie ce poste, ou qui en tient lieu dans les petites CC.'),
    ('Pas plus cher qu’une application d’information', 'Rapporté à la commune (21 en moyenne), la licence représente 230 à 520 € par commune et par an, dans la fourchette des applications d’information municipale.'),
]) + points([
    ('Assez haut pour être rentable', f'Servir une CC nous coûte environ 1 900 € par an (hébergement, emails, IA, part de support) : marge brute d’environ 80 %.', ),
    ('Assez bas pour éviter le marché public', 'Même la plus grande CC reste sous 60 000 € HT sur trois ans, mise en service comprise.'),
    ('Des tranches rondes', '4 900, 7 900, 10 900, 13 900, 16 900 : faciles à retenir et à inscrire au budget, en hausse de 3 000 € par tranche.'),
], 4) + '</div>', '', '3 · Nos tarifs')

slide(tete('3 · Mise en service et offre de lancement', 'Payer le travail réel, <em>une seule fois.</em>') + f'''
<div class="grid3" style="margin-top:24px">
<div class="card green"><h3>Mise en service</h3><div class="big" style="font-size:34px;color:var(--amber);margin:6px 0 10px;white-space:nowrap">1 900 € + 50 € / commune</div><p>Import et contrôle SIRENE, paramétrage, formation de la CC et des mairies en visio, courriers d’invitation aux entreprises. CC médiane (21 communes) : 2 950 € HT.</p></div>
<div class="card amber"><h3>Offre de lancement 2027</h3><div class="big" style="font-size:44px;margin:6px 0 10px">Offerte</div><p style="color:var(--ink)">Pour toute CC qui signe en 2027, quelle que soit sa taille. Même règle pour tous, limitée dans le temps : elle crée l’urgence sans casser la grille.</p></div>
<div class="card ink"><h3>Engagement trois ans</h3><div class="big" style="font-size:44px;color:var(--amber);margin:6px 0 10px">Prix bloqué</div><p>Pas d’indexation pendant trois ans pour qui s’engage : simple à expliquer, bon pour la visibilité budgétaire, et nous gagnons en rétention.</p></div>
</div>
<p class="sub" style="font-size:19px">Pourquoi pas une remise sur la licence ? Parce qu’une remise se négocie ensuite à chaque renouvellement et se compare entre voisins. Offrir la mise en service ne coûte qu’une fois.</p>''', '', '3 · Nos tarifs')

slide(tete('3 · Pour tout le monde', 'Communes seules, agglomérations : <em>la même logique.</em>') + tableau(
    ['Collectivité', 'Tarif HT / an', 'Règle'],
    [
        ['Commune membre d’une CC cliente', '<b>Incluse</b>', 'Accès mairie offert, aucune licence en plus'],
        ['Commune seule, moins de 2 000 hab.', '<b>790 €</b>', 'Sa commune seulement ; déduite si la CC signe dans les 12 mois'],
        ['Commune seule, 2 000 à 5 000 hab.', '<b>1 490 €</b>', 'Idem'],
        ['Commune seule, 5 000 à 10 000 hab.', '<b>2 490 €</b>', 'Idem'],
        ['Commune seule, 10 000 hab. et plus', '<b>3 900 €</b>', 'Idem'],
        ['Communauté d’agglomération, urbaine, métropole', '<b>16 900 € + 0,10 € / hab. au-delà de 50 000</b>', 'CA médiane (84 000 hab.) : 20 300 € ; sur devis au-delà de 250 000 hab.'],
    ], ['36%', '30%', '34%'], 'margin-top:22px') + '<p style="font-size:16px;color:var(--text2);margin-top:12px">La commune seule est une porte d’entrée : une mairie convaincue devient notre meilleur ambassadeur auprès de sa CC, et sa licence est déduite si la CC signe.</p>', '', '3 · Nos tarifs')

slide(tete('3 · Les commerçants', 'Gratuit pour exister, <em>options pour gagner des clients.</em>') + '''
<div class="grid3" style="margin-top:22px">
<div class="card"><h3>Essentiel · 0 €</h3><p>Payé par la collectivité. Fiche complète, photos, horaires, carte, QR code, 3 publications par mois, statistiques essentielles.</p></div>
<div class="card green"><h3>Premium · 24 € HT / mois</h3><p>ou 240 € HT par an (deux mois offerts). Assistant de rédaction, publications illimitées, lettre du territoire, statistiques avancées, emploi, rendez-vous, formulaires.</p></div>
<div class="card ink"><h3>Communication · 49 € HT / mois</h3><p>ou 490 € HT par an. Tout Premium, plus lettres à ses propres clients, réseaux sociaux, mini-site.</p></div>
</div>
<div class="grid2" style="margin-top:18px">
<div class="card mint"><h3>Pourquoi 24 € ?</h3><p>Juste sous PagesJaunes+ (à partir de 25 €, 33 à 78 € pour les offres complètes) avec plus de services, et sans engagement. Sous 30 € par mois, un commerçant décide seul, sans comptable.</p></div>
<div class="card amber"><h3>« Premium offert par la collectivité »</h3><p style="color:var(--ink)">La CC peut offrir le Premium à ses commerçants pour une campagne : 120 € HT par commerce et par an (moitié prix), à partir de 20 commerces. Elle transforme son budget en adoption ; nous gagnons des utilisateurs actifs.</p></div>
</div>''', '', '3 · Nos tarifs')

slide(tete('3 · Services et limites', 'Ce que nous vendons en plus, <em>ce que nous refusons.</em>') + '''
<div class="grid2" style="margin-top:22px">
<div class="card"><h3>Services à la demande</h3><p>• Formation sur site : 790 € HT la journée, hors frais de déplacement<br>• Campagne clé en main (conception, textes, visuels, lettre) : 1 490 € HT<br>• Kits vitrine imprimés pour tous les commerçants : au coût d’impression + 15 %<br>• Rapport annuel pour le conseil communautaire : inclus</p></div>
<div class="card ink"><h3>Ce que nous ne faisons pas</h3><p>• Aucune commission sur les ventes des commerçants<br>• Aucun module payant côté collectivité<br>• Aucune remise hors grille, sauf via une centrale d’achat publique<br>• Aucun engagement imposé au commerçant<br>• Aucune revente de données</p></div>
</div>
<p class="sub" style="font-size:19px">Chaque refus est un argument de vente : les élus ont tous en tête une plateforme qui a coûté cher pour peu de résultats.</p>''', '', '3 · Nos tarifs')

slide(tete('3 · Exemple', 'Les Lacs et Montagnes du Haut-Doubs <em>sur la grille.</em>') + f'''
<div class="grid4" style="margin-top:26px">
<div class="card green"><div class="big" style="font-size:54px;color:var(--amber)">7 900 €</div><p style="margin-top:8px">HT par an : tranche 10 000–20 000 habitants (16 920 hab.)</p></div>
<div class="card"><div class="big" style="font-size:54px;color:var(--green)">3 500 €</div><p style="margin-top:8px">mise en service (1 900 € + 32 communes × 50 €), <b>offerte en 2027</b></p></div>
<div class="card"><div class="big" style="font-size:54px;color:var(--green)">0,47 €</div><p style="margin-top:8px">par habitant et par an ; 247 € par commune</p></div>
<div class="card"><div class="big" style="font-size:54px;color:var(--green)">{eur(HD_3ANS)}</div><p style="margin-top:8px">HT sur 3 ans, bien sous le seuil de 60 000 €</p></div>
</div>
<div class="card ink" style="margin-top:20px"><p style="font-size:19px">Ce chiffre remplace les montants évoqués oralement (9 600 € affichés, 7 500 € remisés) : avec une grille nationale, le Haut-Doubs paie exactement ce que paiera toute CC de sa taille. S’il s’engage sur trois ans, son prix reste à 7 900 € par an, et la mise en service est offerte s’il signe en 2027. Côté commerçants : 3 % d’abonnés parmi ses 1 968 fiches représenteraient environ 16 500 € HT par an.</p></div>''', '', '3 · Nos tarifs')

# ═══ 4 VENDRE PARTOUT ═══
intercalaire('4', 'Vendre partout : <em>989 CC depuis un bureau.</em>', 'Une démonstration automatique, la visio, des relais nationaux et un calendrier calé sur les budgets.')

obj = [(y, cen[y]['actifs'], amb[y]['actifs'], pru[y]['actifs']) for y in M.ANNEES]
slide(tete('4 · L’ambition', 'Toutes les CC, <em>par étapes.</em>') + f'''
<div class="split" style="grid-template-columns:600px 1fr;margin-top:14px">
<div>{barres([cen[y]['actifs'] for y in M.ANNEES], [str(y) for y in M.ANNEES], w=580, h=270, couleurs=['#1F6B52'])}<p style="font-size:14px;color:var(--muted)">CC clientes en fin d’année, scénario central.</p></div>
''' + points([
    ('2027 : prouver', f'{cen[2027]["nouveaux"]} CC, dont le Haut-Doubs, suivies de près. Des résultats publiés.'),
    ('2028-2029 : répéter', f'{cen[2028]["nouveaux"]} puis {cen[2029]["nouveaux"]} nouvelles CC par an grâce aux références, aux relais et à la visio.'),
    ('2030-2031 : s’imposer', f'{nb(cen[2031]["actifs"])} CC en 2031 ({pct(cen[2031]["part_marche"])} du marché), {nb(amb[2031]["actifs"])} dans le scénario ambitieux ({pct(amb[2031]["part_marche"])}).'),
]) + '</div>', '', '4 · Vendre partout')

slide(tete('4 · Le moteur', 'La démonstration <em>sur leur propre territoire.</em>', 'Ce que nous avons fait pour le Haut-Doubs, automatisé pour les 989 CC : leurs communes et leurs vraies entreprises dans un portail prêt à visiter.') + '''
<div class="grid4" style="margin-top:26px">
<div class="card"><h3>1 · Générer</h3><p>Un script crée la démo de n’importe quelle CC depuis SIRENE et le découpage officiel, en quelques minutes.</p></div>
<div class="card"><h3>2 · Envoyer</h3><p>« Voici les 1 247 entreprises de votre territoire, déjà en vitrine » : un lien personnel, visible 30 jours.</p></div>
<div class="card"><h3>3 · Montrer</h3><p>30 minutes de visio sur leur portail, pas sur un territoire inconnu : l’élu reconnaît sa boulangerie.</p></div>
<div class="card"><h3>4 · Transformer</h3><p>Le portail est déjà prêt : signer, c’est simplement l’ouvrir au public.</p></div>
</div>
<div class="card green" style="margin-top:20px"><p style="font-size:20px">C’est ce qui rend la cible nationale atteignable : le coût d’une démo tombe presque à zéro, et chaque démo est unique. Aucun concurrent ne peut montrer à un élu son propre territoire avant même le premier appel.</p></div>''', '', '4 · Vendre partout')

f = 20
slide(tete('4 · L’entonnoir', f'Pour signer {f} CC en 2028, <em>ce qu’il faut.</em>') + '<div style="margin-top:22px">' + entonnoir([
    ('CC contactées', 600, 'Emailing ciblé, LinkedIn, relais, salons : 60 % du marché touché dans l’année'),
    ('Démos ouvertes', 180, '30 % ouvrent le lien vers leur propre territoire'),
    ('Rendez-vous en visio', 90, 'La moitié accepte 30 minutes'),
    ('Présentations aux élus', 40, 'Bureau ou commission économique'),
    ('Signatures', 20, 'Une sur deux, étalées sur le cycle budgétaire'),
], 520) + '</div><p style="font-size:15px;color:var(--muted)">Taux de conversion prudents, à mesurer dès les 50 premières démos et à corriger.</p>', '', '4 · Vendre partout')

slide(tete('4 · Les canaux', 'Démultiplier sans <em>multiplier les commerciaux.</em>') + '''
<div class="grid3" style="margin-top:22px">
<div class="card green"><h3>Direct, en visio</h3><p>Les fondateurs signent 6 CC par an, puis un commercial sédentaire en signe environ 14 : démo, visio, déplacement seulement pour la présentation aux élus.</p></div>
<div class="card"><h3>Relais institutionnels</h3><p>Intercommunalités de France, associations départementales des maires, chefs de projet Petites villes de demain (1 646 communes), managers de commerce financés par la Banque des Territoires, CCI et chambres de métiers.</p></div>
<div class="card"><h3>Partenaires rémunérés</h3><p>Agences d’attractivité, cabinets de conseil aux collectivités, intégrateurs : 15 % de la première année de licence pour chaque CC apportée.</p></div>
<div class="card"><h3>Centrales d’achat</h3><p>Référencement UGAP puis centrales régionales dès 2028 : l’acheteur est dispensé de mise en concurrence, y compris pour les agglomérations au-delà du seuil.</p></div>
<div class="card"><h3>Événements</h3><p>Salon des maires et des collectivités locales (novembre, Paris), congrès d’Intercommunalités de France, rencontres Action cœur de ville (acte III en 2027, commerce prioritaire).</p></div>
<div class="card ink"><h3>Bouche-à-oreille</h3><p>Chaque portail porte « propulsé par terricom » ; les CC voisines se regardent. Une référence par département vaut une campagne.</p></div>
</div>''', '', '4 · Vendre partout')

slide(tete('4 · Le calendrier commercial', 'Caler nos efforts <em>sur les budgets.</em>') + tableau(
    ['Période', 'Côté collectivité', 'Ce que nous faisons'],
    [
        ['Septembre – novembre', 'Préparation du budget, débat d’orientation', '<b>Temps fort de prospection</b> : démos, visios, présentations ; objectif « inscrit au budget »'],
        ['Décembre – avril', 'Vote du budget (au plus tard le 15 avril)', 'Relances, devis, délibérations, <b>signatures</b>'],
        ['Mai – juin', 'Exécution', '<b>Mises en service</b>, formations, lancements presse'],
        ['Juillet – août', 'Ralenti', 'Études de cas, contenus, préparation de la saison, démos générées en masse'],
        ['Toute l’année', 'Achats sous le seuil sur crédits votés', 'Signatures d’opportunité, communes seules'],
    ], ['20%', '32%', '48%'], 'margin-top:22px') + '<p style="font-size:16px;color:var(--text2);margin-top:12px">Fenêtre immédiate : les exécutifs élus en 2026 construisent leur premier budget complet pour 2027. Prospecter dès octobre 2026.</p>', '', '4 · Vendre partout')

slide(tete('4 · Les objections', 'Ce qu’on nous dira, <em>ce qu’on répond.</em>') + tableau(
    ['Objection', 'Réponse'],
    [
        ['« On n’a pas le budget. »', 'Moins de 50 centimes par habitant, sans appel d’offres, mise en service offerte en 2027 ; finançable dans le cadre d’un poste de manager de commerce cofinancé.'],
        ['« Les commerçants ont déjà Google. »', 'Une entreprise sur deux seulement ; et Google ne fait ni campagnes, ni lettre du territoire, ni statistiques pour la collectivité.'],
        ['« Les commerçants n’adhèrent jamais. »', 'Ils n’ont pas à adhérer : leur fiche existe et reste gratuite. Ceux qui s’impliquent vont plus loin.'],
        ['« On a eu une place de marché, c’était un fiasco. »', 'Nous ne vendons rien en ligne et ne prenons aucune commission : c’est l’inverse de ce modèle.'],
        ['« Et nos données ? »', 'Hébergement en France, RGPD, export complet à tout moment, aucune revente.'],
        ['« Vous êtes une petite structure. »', 'Grille publique, contrat annuel, données exportables : aucun risque d’enfermement ; et références publiées.'],
    ], ['32%', '68%'], 'margin-top:20px'), '', '4 · Vendre partout')

# ═══ 5 WEBMARKETING ═══
intercalaire('5', 'Webmarketing : <em>être trouvé, être crédible.</em>', 'Un site qui génère des démos, des contenus pour les services et les élus, et une mécanique de conversion côté commerçants.', 'dark')

slide(tete('5 · Acquisition des collectivités', 'Faire venir <em>les services économiques.</em>') + points([
    ('« Votre territoire en vitrine » sur terricom.fr', 'Le visiteur choisit sa CC dans une liste : il découvre les chiffres de ses entreprises (établissements, créations, fermetures, secteurs) et demande sa démo. Chaque demande est un contact qualifié.'),
    ('Le baromètre SIRENE de chaque CC', 'Un PDF gratuit par intercommunalité, généré automatiquement : « l’économie de votre territoire en 2 pages ». Un prétexte utile pour écrire au service économique, et un contenu repris dans les bulletins.'),
    ('Référencement sur les recherches métier', 'Guides pratiques : « animer le commerce de centre-bourg », « financer un manager de commerce », « réussir une campagne de Noël » ; des pages qui répondent aux questions que se posent les agents.'),
    ('LinkedIn, webinaires, lettre trimestrielle', 'Élus et agents y sont actifs. Un webinaire mensuel de 30 minutes, une lettre trimestrielle aux collectivités, des études de cas chiffrées.'),
]), '', '5 · Webmarketing')

slide(tete('5 · Contenus et prospection', 'Un plan <em>simple et régulier.</em>') + tableau(
    ['Action', 'Fréquence', 'Objectif'],
    [
        ['Emailing aux services économiques des CC (données publiques, désinscription en un clic)', '1 campagne / mois, par tranche de taille', 'Ouvertures de démos'],
        ['Publication LinkedIn des fondateurs (cas concrets, chiffres SIRENE)', '2 par semaine', 'Notoriété auprès des élus et DGS'],
        ['Webinaire « 30 minutes pour animer le commerce local »', '1 par mois', '20 à 40 inscrits, démos'],
        ['Étude de cas publiée (Haut-Doubs puis chaque référence)', 'Tous les 2 mois', 'Crédibilité, relais presse locale'],
        ['Baromètres SIRENE par CC', 'Mise à jour trimestrielle', 'Prétexte de contact, référencement'],
        ['Présence aux salons nationaux et régionaux', '2 à 4 par an', 'Rendez-vous qualifiés'],
    ], ['52%', '24%', '24%'], 'margin-top:22px'), '', '5 · Webmarketing')

slide(tete('5 · Côté commerçants', 'Transformer une fiche offerte <em>en client Premium.</em>') + '''
<div class="grid2" style="margin-top:22px">''' + points([
    ('Le lancement fait par la collectivité', 'Courrier et email d’invitation, affichette avec QR code : la fiche est déjà là, il suffit de la réclamer.'),
    ('Des séquences automatiques', 'J+2 « complétez vos horaires », J+7 « voici vos premières vues », J+30 « l’assistant peut écrire pour vous » : l’envie de Premium vient des résultats.'),
    ('Des essais au bon moment', 'Premium offert un mois pendant les campagnes (Noël, rentrée) : c’est là que la valeur se voit.'),
]) + points([
    ('Mesurer par territoire', 'Taux de revendication, de complétude et de conversion suivis chaque mois, partagés avec la CC.'),
    ('Objectif réaliste', '1 % des fiches abonnées la première année, 3 à 4 % au bout de trois ans : prudent au regard des places de marché (moins de 25 % d’adhésion, mais gratuite).'),
    ('Ne jamais forcer', 'Aucune fonction essentielle derrière le paiement : la confiance des commerçants est aussi celle des élus.'),
], 4) + '</div>', '', '5 · Webmarketing')

bud = [cen[y]['couts']['marketing'] for y in M.ANNEES]
slide(tete('5 · Budget et indicateurs', 'Ce qu’on dépense, <em>ce qu’on mesure.</em>') + f'''
<div class="split" style="grid-template-columns:560px 1fr;margin-top:10px">
<div>{barres(bud, [str(y) for y in M.ANNEES], w=540, h=240, couleurs=['#C8702A'], fmt=keur)}<p style="font-size:14px;color:var(--muted)">Budget marketing, scénario central : 30 k€ fixes (salons, site, contenus) + 5 % du chiffre d’affaires.</p></div>
''' + tableau(['Indicateur', 'Cible 2027'], [
    ['Démos générées puis ouvertes', '> 30 %'],
    ['Démo ouverte → rendez-vous', '> 40 %'],
    ['Rendez-vous → signature', '> 20 %'],
    ['Coût d’acquisition par CC', '< 6 000 €'],
    ['Fiches revendiquées à 12 mois', '> 20 %'],
    ['Renouvellement des licences', '> 92 %'],
], ['64%', '36%']) + '</div>', '', '5 · Webmarketing')

# ═══ 6 MODÈLE ET SIMULATIONS ═══
intercalaire('6', 'Modèle économique : <em>les chiffres.</em>', 'Quatre sources de revenus, des coûts maîtrisés, trois scénarios sur cinq ans et le besoin de financement.', 'green')

slide(tete('6 · Le modèle', 'Quatre sources de revenus, <em>une seule indispensable.</em>') + f'''
<div class="grid4" style="margin-top:24px">
<div class="card green"><h3>Licences</h3><p>Récurrent, prévisible, payé par la collectivité. ≈ {eur(M.LICENCE_MOYENNE)} HT par CC en moyenne. <b>Le socle du modèle.</b></p></div>
<div class="card"><h3>Mises en service</h3><p>≈ {eur(M.MISE_EN_SERVICE)} HT par nouvelle CC, une fois (offerte en 2027).</p></div>
<div class="card"><h3>Services</h3><p>Formation sur site, campagnes clés en main, impressions : ≈ 6 % des licences.</p></div>
<div class="card amber"><h3>Abonnements commerçants</h3><p style="color:var(--ink)">≈ {M.ARPU_PRO} € HT par abonné et par an. Un bonus qui grandit avec l’ancienneté des territoires.</p></div>
</div>
<div class="grid2" style="margin-top:20px">
<div class="card mint"><h3>Ce que coûte une CC servie</h3><p>≈ {eur(M.INFRA_PAR_TERRITOIRE)} d’hébergement, emails et IA, et une part de support et de mise en service : ≈ 1 900 € par an, soit une marge brute d’environ 80 % sur la licence.</p></div>
<div class="card mint"><h3>Ce que coûte l’entreprise</h3><p>Deux fondateurs rémunérés (hypothèse : 90 k€ chargés à deux), puis commerciaux, accompagnement et développement recrutés au rythme des signatures. Hébergement en France : {eur(M.INFRA_FIXE)} par an de socle.</p></div>
</div>''', '', '6 · Modèle et simulations')

hyp = [[SCEN['label'], ' · '.join(str(x) for x in SCEN['nouveaux']), pct(SCEN['attrition']), ' → '.join(pct(c, 1) for c in SCEN['conversion'][::2]), ' → '.join(pct(p) for p in SCEN['partenaires'][1::2])] for SCEN in M.SCENARIOS.values()]
slide(tete('6 · Les hypothèses', 'Trois scénarios, <em>des hypothèses explicites.</em>') + tableau(
    ['Scénario', 'Nouvelles CC par an (2027 → 2031)', 'Départs / an', 'Fiches abonnées (an 1 → 3 → 5)', 'Part apportée par partenaires'],
    hyp, ['14%', '30%', '13%', '25%', '18%'], 'margin-top:22px') + f'''
<div class="grid3" style="margin-top:18px">
<div class="card"><h3>Prix</h3><p>Licence moyenne {eur(M.LICENCE_MOYENNE)} (grille nationale pondérée par le nombre de CC par tranche), +2 %/an ; mise en service {eur(M.MISE_EN_SERVICE)}, offerte en 2027.</p></div>
<div class="card"><h3>Territoires</h3><p>{nb(M.ENT_PAR_TERRITOIRE)} fiches par CC en moyenne après tri (Haut-Doubs : 1 968) ; signature en milieu d’année la première fois.</p></div>
<div class="card"><h3>Équipe</h3><p>1 commercial par tranche de 14 signatures au-delà de 6 ; 1 chargé d’accompagnement pour 35 CC ; 1 développeur dès 2028, +1 par 50 CC.</p></div>
</div><p style="font-size:14px;color:var(--muted);margin-top:10px">Toutes les hypothèses sont dans docs/strategie/modele.py : les changer régénère ce document.</p>''', '', '6 · Modèle et simulations')

slide(tete('6 · Chiffre d’affaires', f'Scénario central : <em>{keur(cen[2031]["ca"])} en 2031.</em>') + f'''
<div class="split" style="grid-template-columns:680px 1fr;margin-top:6px">
<div>{empile([('Licences', [cen[y]['licences'] for y in M.ANNEES]), ('Mises en service et services', [cen[y]['mes'] + cen[y]['services'] for y in M.ANNEES]), ('Commerçants', [cen[y]['pros'] for y in M.ANNEES])], [str(y) for y in M.ANNEES], ['#1F6B52', '#AEBDB5', '#F4B266'], w=660, h=300)}
<div style="display:flex;gap:18px;font-size:15px;color:var(--text2);margin-top:4px"><span><b style="color:#1F6B52">■</b> Licences</span><span><b style="color:#AEBDB5">■</b> Mises en service et services</span><span><b style="color:#F4B266">■</b> Abonnements commerçants</span></div></div>
''' + tableau(['', '2027', '2029', '2031'], [
    ['CC clientes', nb(cen[2027]['actifs']), nb(cen[2029]['actifs']), nb(cen[2031]['actifs'])],
    ['Licences', keur(cen[2027]['licences']), keur(cen[2029]['licences']), keur(cen[2031]['licences'])],
    ['Commerçants abonnés', nb(cen[2027]['payants']), nb(cen[2029]['payants']), nb(cen[2031]['payants'])],
    ['Chiffre d’affaires', f"<b>{keur(cen[2027]['ca'])}</b>", f"<b>{keur(cen[2029]['ca'])}</b>", f"<b>{keur(cen[2031]['ca'])}</b>"],
    ['Résultat', keur(cen[2027]['resultat']), keur(cen[2029]['resultat']), keur(cen[2031]['resultat'])],
    ['Équipe', nb(cen[2027]['equipe_n']), nb(cen[2029]['equipe_n']), nb(cen[2031]['equipe_n'])],
], ['40%', '20%', '20%', '20%']) + '</div>', '', '6 · Modèle et simulations')

lig = []
for k, sc in (('prudent', pru), ('central', cen), ('ambitieux', amb)):
    lig.append([f"<b>{M.SCENARIOS[k]['label']}</b>"] + [keur(sc[y]['ca']) for y in M.ANNEES] + [nb(sc[2031]['actifs'])])
    lig.append(['<span style="color:var(--muted)">résultat</span>'] + [f'<span style="color:{"#9C3328" if sc[y]["resultat"] < 0 else "#1F6B52"}">{keur(sc[y]["resultat"])}</span>' for y in M.ANNEES] + [pct(sc[2031]['part_marche'])])
slide(tete('6 · Trois scénarios', 'Chiffre d’affaires et résultat, <em>année par année.</em>') + tableau(['Scénario'] + [str(y) for y in M.ANNEES] + ['CC 2031 · part'], lig, ['16%'] + ['13%'] * 5 + ['19%'], 'margin-top:22px') + f'''
<div class="grid3" style="margin-top:18px">
<div class="card"><h3>Prudent</h3><p>Équilibre en 2030, {nb(pru[2031]['actifs'])} CC en 2031 : une belle PME, sans financement extérieur important au-delà de l’amorçage.</p></div>
<div class="card green"><h3>Central</h3><p>Équilibre en 2029, {nb(cen[2031]['actifs'])} CC et {keur(cen[2031]['ca'])} en 2031 ; {nb(cen[2031]['equipe_n'])} personnes.</p></div>
<div class="card ink"><h3>Ambitieux</h3><p>Leader national : {nb(amb[2031]['actifs'])} CC, {keur(amb[2031]['ca'])} en 2031 ; suppose des relais et une centrale d’achat efficaces dès 2028.</p></div>
</div>''', '', '6 · Modèle et simulations')

slide(tete('6 · Trésorerie', 'Le besoin de financement : <em>250 à 400 k€.</em>') + f'''
<div class="split" style="grid-template-columns:660px 1fr;margin-top:8px">
<div>{courbes([('Prudent', [pru[y]['tresorerie'] for y in M.ANNEES]), ('Central', [cen[y]['tresorerie'] for y in M.ANNEES]), ('Ambitieux', [amb[y]['tresorerie'] for y in M.ANNEES])], [str(y) for y in M.ANNEES], ['#AEBDB5', '#1F6B52', '#C8702A'], w=560, h=280)}<p style="font-size:14px;color:var(--muted)">Trésorerie cumulée générée par l’activité, avant financement.</p></div>
''' + points([
    ('Creux de trésorerie', f'{keur(-min(l["tresorerie"] for l in R["prudent"]))} (prudent), {keur(-min(l["tresorerie"] for l in C))} (central), {keur(-min(l["tresorerie"] for l in R["ambitieux"]))} (ambitieux), atteint en 2028-2029.'),
    ('Sans aucun commerçant payant', f'Le creux central passe à {keur(-min(l["tresorerie"] for l in sans_pros))} et l’entreprise reste rentable en 2031 ({keur(sans_pros[-1]["resultat"])} de résultat).'),
    ('Comment financer', 'Prêts d’honneur et Bpifrance (bourse French Tech, prêt d’amorçage), puis un tour d’amorçage de 300 à 400 k€ avec une marge de sécurité de six mois.'),
]) + '</div>', '', '6 · Modèle et simulations')

sens = []
for fconv in (0, 0.5, 1, 1.5):
    row = [f'{("aucun" if fconv == 0 else pct(0.04 * fconv, 0))} de fiches abonnées à maturité']
    for fsig in (0.7, 1, 1.3):
        L = M.simuler('central', conversion=fconv, signatures=fsig)
        row.append(f"{keur(L[-1]['ca'])} · <span style='color:{'#9C3328' if L[-1]['resultat'] < 0 else '#1F6B52'}'>{keur(L[-1]['resultat'])}</span>")
    sens.append(row)
slide(tete('6 · Sensibilité', 'Ce qui fait vraiment <em>bouger le résultat.</em>', 'Chiffre d’affaires et résultat 2031 du scénario central, selon le rythme de signatures et l’adoption par les commerçants.') + tableau(
    ['Adoption commerçants', 'Signatures −30 %', 'Signatures prévues', 'Signatures +30 %'], sens, ['31%', '23%', '23%', '23%'], 'margin-top:22px') + '''
<div class="card ink" style="margin-top:18px"><p style="font-size:19px">Le rythme de signatures des CC compte plus que l’adoption par les commerçants : c’est la preuve qu’il faut concentrer l’énergie commerciale sur les collectivités, et traiter l’abonnement des commerçants comme un accélérateur.</p></div>''', '', '6 · Modèle et simulations')

# ═══ 7 PLAN D'ACTION ═══
intercalaire('7', 'Plan d’action : <em>les douze prochains mois.</em>', 'Ce que nous faisons dès maintenant, les risques à surveiller et les décisions à prendre.', 'dark')

slide(tete('7 · Feuille de route', 'Les douze prochains mois, <em>trimestre par trimestre.</em>') + '''
<div class="grid4" style="margin-top:24px">
<div class="card green"><h3>T4 2026</h3><p>Valider la grille, publier la page Tarifs · Signer le Haut-Doubs · Industrialiser la démo automatique · 300 démos générées pour les CC de Bourgogne-Franche-Comté et des régions voisines · Salon des maires</p></div>
<div class="card"><h3>T1 2027</h3><p>Campagne « offre de lancement 2027 » · 2 à 3 signatures sur les budgets votés · Premier webinaire · Dossier de financement Bpifrance, prêts d’honneur</p></div>
<div class="card"><h3>T2 2027</h3><p>Mises en service et lancements presse · Étude de cas Haut-Doubs publiée · Premiers partenariats (associations de maires, CCI) · Préparer le référencement en centrale d’achat</p></div>
<div class="card"><h3>T3 2027</h3><p>Baromètres SIRENE pour toutes les CC · Démos générées pour les 989 CC · Recrutement du premier commercial sédentaire · Préparer la saison budgétaire 2028</p></div>
</div>
<div class="card ink" style="margin-top:18px"><p style="font-size:19px">Objectif fin 2027 : 6 CC clientes, 50 démos ouvertes par mois, 3 relais actifs, 1 étude de cas chiffrée.</p></div>''', '', '7 · Plan d’action')

slide(tete('7 · Risques', 'Ce qui peut mal tourner, <em>et la parade.</em>') + tableau(
    ['Risque', 'Parade'],
    [
        ['Cycle de vente plus long que prévu', 'Grille sous le seuil, offre datée, prospection calée sur les budgets, communes seules comme porte d’entrée'],
        ['Faible adoption par les commerçants', 'Modèle rentable sans eux ; fiches déjà remplies ; campagnes et Premium offert par la collectivité'],
        ['Un grand acteur copie l’idée', 'Avance produit, démos sur mesure, références, prix public bas et clair'],
        ['Qualité des données SIRENE', 'Tri contrôlé (forme juridique + activité + nom), file « à vérifier », synchronisation mensuelle'],
        ['Mise en service qui ne passe pas à l’échelle', 'Outillage (import, invitations, formation en visio enregistrée), un chargé d’accompagnement pour 35 CC'],
        ['Dépendance à deux fondateurs', 'Documentation, recrutements anticipés, procédures d’exploitation écrites'],
    ], ['36%', '64%'], 'margin-top:22px'), '', '7 · Plan d’action')

slide(tete('7 · À décider', 'Les décisions <em>des fondateurs.</em>') + points([
    ('Valider la grille nationale', '4 900 à 16 900 € HT par CC, communes seules, agglomérations, commerçants à 24 et 49 €.'),
    ('Lancer l’offre 2027', 'Mise en service offerte pour toute signature en 2027, prix bloqué pour trois ans d’engagement.'),
    ('Choisir le scénario de pilotage', 'Central recommandé : 6 CC en 2027, 20 en 2028, un commercial dès 2028.'),
    ('Financer', 'Lever 300 à 400 k€ (prêts d’honneur, Bpifrance, amorçage) avant mi-2027.'),
    ('Fixer la rémunération des fondateurs', 'Hypothèse du modèle : 90 k€ chargés à deux ; à ajuster.'),
    ('Industrialiser la démo', 'Priorité produit numéro un : une démo par CC, générée en quelques minutes.'),
]), '', '7 · Plan d’action')

slide(tete('Sources', 'Les chiffres cités <em>et leur origine.</em>') + '''<div style="columns:2;column-gap:40px;margin-top:20px;font-size:14.5px;line-height:1.5;color:var(--text2)">
<p style="margin:0 0 8px"><b>Intercommunalités</b> : découpage administratif officiel (Etalab, @etalab/decoupage-administratif 6.0, populations Insee) : 989 CC, 230 CA, 14 CU, 21 métropoles, 34 969 communes ; DGCL, BIS 195 et Bilan statistique 2025.</p>
<p style="margin:0 0 8px"><b>Vacance commerciale</b> : Codata Digest 2026 (millésime 2025), 11,7 % en pied d’immeuble.</p>
<p style="margin:0 0 8px"><b>Entreprises</b> : Insee, 2023, 5,2 M d’entreprises, 97 % de microentreprises.</p>
<p style="margin:0 0 8px"><b>Commande publique</b> : décret n° 2025-1386, seuil de dispense de procédure porté à 60 000 € HT au 1er avril 2026.</p>
<p style="margin:0 0 8px"><b>Places de marché locales</b> : Cour des comptes, synthèse « commerce de proximité », septembre 2023 ; Ma Ville Mon Shopping (La Poste), tarifs 0,22 à 0,50 € / hab. et fermeture en 2025.</p>
<p style="margin:0 0 8px"><b>Managers de commerce</b> : Banque des Territoires, cofinancement jusqu’à 20 k€ / an pendant 2 ans, 20 M€ pour environ 500 postes (février 2026) ; coût chargé 50–60 k€ estimé.</p>
<p style="margin:0 0 8px"><b>Programmes</b> : ANCT, Petites villes de demain (1 646 communes, pérennisé jusqu’en 2030) ; Action cœur de ville (244 villes, acte III en 2027).</p>
<p style="margin:0 0 8px"><b>Prix constatés</b> : PagesJaunes+ à partir de 25 € / mois ; PanneauPocket ~130 € TTC / an ; IntraMuros ~720 € HT / an pour 5 000 hab. (barème 2020) ; cartes cadeaux 5–20 % de commission.</p>
<p style="margin:0 0 8px"><b>Usage des fiches Google</b> : Baromètre France Num 2025, environ une entreprise sur deux.</p>
<p style="margin:0 0 8px"><b>Budget des collectivités</b> : collectivites-locales.gouv.fr, cycle budgétaire (vote au plus tard le 15 avril).</p>
<p style="margin:0 0 8px"><b>Haut-Doubs</b> : base SIRENE (API Recherche d’entreprises, septembre 2026), rapport docs/demo/haut-doubs-controle.csv.</p>
<p style="margin:0"><b>Simulations</b> : docs/strategie/modele.py (hypothèses modifiables). Chiffres à revérifier avant toute diffusion externe.</p></div>''', '', 'Sources')

# ─── Assemblage ────────────────────────────────────────────────────────────
EXTRA = '''
.tb { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 16.5px; background: var(--paper); border: 1px solid var(--line); border-radius: 18px; overflow: hidden; }
.tb th { text-align: left; font-size: 13px; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); font-weight: 800; padding: 12px 16px; background: var(--sand); }
.tb td { padding: 10px 16px; border-top: 1px solid var(--line); color: var(--text2); line-height: 1.32; vertical-align: top; }
.tb td b { color: var(--ink); }
.tb tr:last-child td { }
.points li { font-size: 17px; }
h2.t + .points, p.sub + .points { margin-top: 26px; }
.points li b { font-size: 21px; }
.card p { font-size: 17px; }
.card h3 { font-size: 23px; }
'''

html = ['<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>terricom · Stratégie commerciale et modèle économique</title>',
        f'<style>{CSS}{EXTRA}</style></head><body>']
for i, (cls, corps, rub) in enumerate(S):
    pied = '' if i == 0 else f'<div class="foot"><span class="brand">terricom<i>.</i></span><span>{rub or "Document interne · fondateurs"}</span><span class="n">{i}</span></div>'
    html.append(f'<section class="slide {cls}">{corps}{pied}</section>')
html.append('</body></html>')
(ICI / 'strategie.html').write_text('\n'.join(html))
print(f'{len(S)} diapositives → docs/strategie/strategie.html')
