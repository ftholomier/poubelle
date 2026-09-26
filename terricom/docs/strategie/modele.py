"""Modèle économique terricom : trois scénarios sur cinq ans (2027-2031).

Toutes les hypothèses sont en tête de fichier, commentées ; `python3 docs/strategie/modele.py` affiche les
résultats et `modele.resultats()` les fournit au générateur du document.
"""

ANNEES = [2027, 2028, 2029, 2030, 2031]

# ─── Grille nationale unique (voir le document) ─────────────────────────────
# Tranches de population des 989 communautés de communes (découpage officiel, @etalab/decoupage-administratif) :
# nombre de CC, licence annuelle € HT.
TRANCHES = [
    ('moins de 10 000 hab.', 0, 10000, 217, 4900),
    ('10 000 à 20 000', 10000, 20000, 310, 7900),
    ('20 000 à 35 000', 20000, 35000, 322, 10900),
    ('35 000 à 50 000', 35000, 50000, 100, 13900),
    ('50 000 et plus', 50000, 10**9, 40, 16900),
]
NB_CC = sum(t[3] for t in TRANCHES)
MARCHE_LICENCES = sum(t[3] * t[4] for t in TRANCHES)     # € HT/an si toutes les CC étaient clientes
LICENCE_MOYENNE = MARCHE_LICENCES / NB_CC               # ≈ 9 200 € HT/an
HAUSSE_LICENCE = 0.02       # indexation annuelle prévue au contrat
MISE_EN_SERVICE = 1900 + 50 * 26   # forfait + 50 €/commune (26 communes en moyenne) ≈ 3 200 € HT
OFFRE_LANCEMENT_ANNEES = [2027]    # mise en service offerte aux territoires signant en 2027
SERVICES_PART = 0.06        # formation sur site, campagnes clés en main, impressions (part de la licence)
ENT_PAR_TERRITOIRE = 1200   # fiches par CC après tri (≈ 0,055 par habitant ; Haut-Doubs, touristique : 1 968)
ARPU_PRO = 280              # € HT/an par commerçant payant (mix Premium mensuel/annuel et Communication)
FRAIS_PAIEMENT = 0.02

SCENARIOS = {
    'prudent': {
        'label': 'Prudent',
        'nouveaux': [4, 10, 18, 26, 32],
        'attrition': 0.08,
        'partenaires': [0, 0.1, 0.2, 0.25, 0.3],
        # part des fiches d'un territoire qui paient une option, selon l'ancienneté du territoire (1re, 2e… année)
        'conversion': [0.005, 0.01, 0.015, 0.02, 0.02],
    },
    'central': {
        'label': 'Central',
        'nouveaux': [6, 20, 35, 48, 60],
        'attrition': 0.06,
        'partenaires': [0, 0.15, 0.25, 0.3, 0.35],
        'conversion': [0.01, 0.02, 0.03, 0.035, 0.04],
    },
    'ambitieux': {
        'label': 'Ambitieux',
        'nouveaux': [10, 35, 60, 85, 100],
        'attrition': 0.05,
        'partenaires': [0, 0.2, 0.3, 0.35, 0.4],
        'conversion': [0.015, 0.03, 0.045, 0.055, 0.06],
    },
}

# ─── Coûts ─────────────────────────────────────────────────────────────────
INFRA_FIXE = 650 * 12            # hébergement haute disponibilité en France (socle)
INFRA_PAR_TERRITOIRE = 45 * 12   # emails, IA, stockage, sauvegardes par territoire actif
FRAIS_GENERAUX = 18000           # comptabilité, juridique, assurances, outils
FONDATEURS = 2 * 45000           # rémunération chargée des deux fondateurs (hypothèse à arbitrer)
COMMERCIAL = 62000               # chargé, fixe + variable ; signe ~14 territoires/an (démos en visio), au-delà des 6 des fondateurs
CAPACITE_COMMERCIAL = 14
CSM = 46000                      # mise en service, formation, support : 1 par tranche de 35 territoires actifs
DEV = 60000                      # développeur : 1 dès 2028, puis 1 par tranche de 50 territoires actifs
COMMISSION_PARTENAIRE = 0.15     # sur la licence des territoires apportés par un partenaire (1re année seulement)
MARKETING_FIXE = 30000           # salons nationaux, contenus, site, webinaires ; + 5 % du chiffre d'affaires
MARKETING_PART = 0.05


def simuler(cle, conversion=1.0, signatures=1.0, pros_actifs=True):
    s = dict(SCENARIOS[cle])
    s['nouveaux'] = [round(n * signatures) for n in s['nouveaux']]
    s['conversion'] = [c * conversion if pros_actifs else 0 for c in s['conversion']]
    cohortes = []  # [annee_entree, nombre_restant, licence_unitaire]
    signes = 0
    tresorerie = 0
    lignes = []
    for i, annee in enumerate(ANNEES):
        # attrition sur la base existante
        for c in cohortes:
            c[1] *= 1 - s['attrition']
        n = s['nouveaux'][i]
        for k in range(n):
            cohortes.append([i, 1.0, LICENCE_MOYENNE])
        signes += n
        actifs = sum(c[1] for c in cohortes)
        licences = sum(c[1] * c[2] * (1 + HAUSSE_LICENCE) ** (i - c[0]) for c in cohortes)
        # les nouveaux territoires signent en moyenne en milieu d'année : demi-licence la 1re année
        licences -= sum(0.5 * c[1] * c[2] for c in cohortes if c[0] == i)
        mes = 0 if annee in OFFRE_LANCEMENT_ANNEES else n * MISE_EN_SERVICE
        services = SERVICES_PART * licences
        pros = 0.0
        for c in cohortes:
            age = min(i - c[0], len(s['conversion']) - 1)
            part = s['conversion'][age] * (0.5 if c[0] == i else 1)
            pros += c[1] * ENT_PAR_TERRITOIRE * part * ARPU_PRO
        ca = licences + mes + services + pros
        directs = n * (1 - s['partenaires'][i])
        commerciaux = max(0, -(-int(round(directs - 6)) // CAPACITE_COMMERCIAL))
        csm = -(-int(actifs) // 35) if actifs >= 8 else 0
        devs = (1 if annee >= 2028 else 0) + int(actifs) // 50
        commission = COMMISSION_PARTENAIRE * n * s['partenaires'][i] * LICENCE_MOYENNE
        couts = {
            'infra': INFRA_FIXE + INFRA_PAR_TERRITOIRE * actifs,
            'paiement': FRAIS_PAIEMENT * pros,
            'generaux': FRAIS_GENERAUX,
            'fondateurs': FONDATEURS,
            'equipe': commerciaux * COMMERCIAL + csm * CSM + devs * DEV,
            'partenaires': commission,
            'marketing': MARKETING_FIXE + MARKETING_PART * ca,
        }
        total = sum(couts.values())
        resultat = ca - total
        tresorerie += resultat
        lignes.append({
            'annee': annee, 'nouveaux': n, 'actifs': actifs, 'licences': licences, 'mes': mes, 'services': services,
            'pros': pros, 'ca': ca, 'couts': couts, 'charges': total, 'resultat': resultat, 'tresorerie': tresorerie,
            'arr': sum(c[1] * c[2] * (1 + HAUSSE_LICENCE) ** (i - c[0]) for c in cohortes) * (1 + SERVICES_PART) + pros * (1 if i == 0 else 1),
            'equipe_n': 2 + commerciaux + csm + devs, 'payants': pros / ARPU_PRO,
            'commerciaux': commerciaux, 'csm': csm, 'devs': devs, 'part_marche': actifs / 990,
        })
    return lignes


def resultats():
    return {k: simuler(k) for k in SCENARIOS}


if __name__ == '__main__':
    k = lambda v: f"{v / 1000:>7.0f} k€"
    for cle, lignes in resultats().items():
        print(f"\n== {SCENARIOS[cle]['label']}")
        print('année  nouv actifs   licences     MES  services     pros        CA   charges  résultat  trésorerie équipe payants')
        for l in lignes:
            print(f"{l['annee']}  {l['nouveaux']:>4} {l['actifs']:>6.1f} {k(l['licences'])} {k(l['mes'])} {k(l['services'])} {k(l['pros'])} {k(l['ca'])} {k(l['charges'])} {k(l['resultat'])} {k(l['tresorerie'])} {l['equipe_n']:>5} {l['payants']:>7.0f}")
