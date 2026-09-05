"""Aucune requête tierce tant que le visiteur n'a pas accepté.

Le contrôle qui décide de la conformité du site : on refuse tout dans le
bandeau cookies, on parcourt les pages, et on compte les requêtes sortantes
vers un autre domaine que celui du site. La bonne valeur est zéro.

On vérifie aussi le pendant : une fois les contenus externes acceptés, le plan
d'accès doit réellement se charger — un consentement qui ne débloque rien est
un bandeau décoratif.

**Turnstile est allumé de force le temps de la mesure**, et c'est la raison
d'être de la moitié de ce script. La protection anti-robot des formulaires
charge un script de Cloudflare SANS passer par la barrière de consentement :
c'est permis, elle ne dépose pas de cookie et relève de la sécurité du service
demandé. Mais le réglage est vide dans data-modele/, donc le script n'était
jamais dans la page mesurée — l'auditeur ne l'avait jamais vu. Le jour où la
mairie renseigne sa clé, un hôte tiers apparaît sur deux pages, et personne ne
relance l'audit pour s'en apercevoir.

C'est le piège général que ce dépôt connaît déjà : un réglage qui décide de la
PRÉSENCE d'un élément cache cet élément aux auditeurs. Il faut donc l'allumer
pour le mesurer, exactement comme bulle.py allume l'assistant.

Ce que le script exige alors :
  · challenges.cloudflare.com est toléré, et lui seul ;
  · uniquement sur les pages qui portent un formulaire protégé ;
  · nulle part ailleurs — un Turnstile qui fuirait sur toutes les pages serait
    un traceur de fait, chargé sur chaque visite sans motif.

Usage :
    php -S 127.0.0.1:8081 -t public &
    python3 outils/verifs/traceurs.py

Sort en code 1 si une requête tierce part sans accord.
"""
import argparse
import copy
import json
import os
import re
import sys
from urllib.parse import urlparse

from playwright.sync_api import sync_playwright

RACINE = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
PARAMETRES = os.path.join(RACINE, 'data', 'admin', 'parametres.json')

# Le seul hôte tiers qu'une page ait le droit de contacter sans accord, et les
# seules pages où ce droit s'applique. Toute autre combinaison est un écart.
PROTECTEUR = 'challenges.cloudflare.com'
PAGES_A_FORMULAIRE = ('/contact', '/demande-en-ligne')

# Clé de site factice : Cloudflare la refusera, et c'est sans importance —
# ce qui est mesuré est la REQUÊTE, pas sa réponse. Aucune donnée de visiteur
# ne part, puisqu'il n'y a pas de visiteur.
CLE_FACTICE = '0x4AAAAAAA_audit_traceurs'


def pages_du_site(base):
    """Les adresses à mesurer, lues dans le plan du site.

    Une liste écrite en dur ici se périme dès qu'une page est ajoutée ou
    qu'un slug est changé depuis le back-office — et une page non mesurée
    est exactement celle où l'écart passera. Le sitemap, lui, est produit
    par Seo::PAGES : il ne peut pas diverger de ce que le site sert.
    """
    from urllib.request import urlopen
    xml = urlopen(base + '/sitemap.xml', timeout=20).read().decode('utf-8')
    vues = []
    for loc in re.findall(r'<loc>([^<]+)</loc>', xml):
        chemin = loc.split(base, 1)[-1] if loc.startswith(base) else loc
        chemin = re.sub(r'^https?://[^/]+', '', chemin) or '/'
        if chemin not in vues:
            vues.append(chemin)
    return vues


NAVIGATEUR = os.environ.get('CHROMIUM', '/opt/pw-browsers/chromium')


def reglages() -> dict:
    if os.path.isfile(PARAMETRES):
        with open(PARAMETRES, encoding='utf-8') as f:
            try:
                return json.load(f)
            except json.JSONDecodeError:
                return {}
    return {}


def ecrire(donnees: dict) -> None:
    os.makedirs(os.path.dirname(PARAMETRES), exist_ok=True)
    with open(PARAMETRES, 'w', encoding='utf-8') as f:
        json.dump(donnees, f, ensure_ascii=False, indent=2)


def allumer_le_protecteur(origine: dict) -> None:
    """Renseigne une clé Turnstile factice, le temps de la mesure."""
    donnees = copy.deepcopy(origine)
    antispam = dict(donnees.get('antispam', {}))
    antispam['cle_site'] = CLE_FACTICE
    antispam['cle_secrete'] = antispam.get('cle_secrete') or CLE_FACTICE
    donnees['antispam'] = antispam
    ecrire(donnees)


def parcourir(b, base, pages, choix):
    """Parcourt le site avec un consentement donné.

    Rend un dictionnaire page -> hôtes tiers contactés depuis cette page. Le
    détail par page est nécessaire : le protecteur de formulaire n'est toléré
    que sur les pages à formulaire, et un hôte toléré partout ne serait plus
    un hôte toléré.
    """
    ctx = b.new_context(viewport={'width': 1440, 'height': 1000})
    ctx.add_cookies([{'name': 'cv_consentement', 'value': json.dumps(choix), 'url': base}])
    pg = ctx.new_page()

    interne = urlparse(base).hostname
    courants = set()
    pg.on('request', lambda r: courants.add(urlparse(r.url).hostname))

    releve = {}
    for chemin in pages:
        courants.clear()
        pg.goto(base + chemin, wait_until='networkidle')
        # laisser le temps aux scripts différés de se manifester
        pg.wait_for_timeout(1200)
        pg.evaluate('window.scrollTo(0, document.body.scrollHeight)')
        pg.wait_for_timeout(800)
        releve[chemin] = {h for h in courants if h and h != interne}

    ctx.close()
    return releve


def main():
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    ap.add_argument('pages', nargs='*')
    ap.add_argument('--base', default='http://127.0.0.1:8081')
    args = ap.parse_args()
    pages = args.pages or pages_du_site(args.base.rstrip("/"))

    origine = reglages()
    allumer_le_protecteur(origine)

    try:
        with sync_playwright() as p:
            b = p.chromium.launch(executable_path=NAVIGATEUR, args=['--no-sandbox'])

            refus = {'v': 1, 'd': '2026-01-01', 'mesure': False, 'externes': False}
            releve = parcourir(b, args.base, pages, refus)

            accord = {'v': 1, 'd': '2026-01-01', 'mesure': False, 'externes': True}
            apres = parcourir(b, args.base, ['/contact'], accord).get('/contact', set())

            b.close()
    finally:
        # Le réglage d'origine revient quoi qu'il arrive : un auditeur qui
        # laisse une clé factice dans les paramètres parce qu'il a échoué au
        # milieu est un auditeur qu'on cesse de lancer.
        ecrire(origine)

    ecarts = []
    protecteur_vu = []
    for chemin, hotes in sorted(releve.items()):
        for hote in sorted(hotes):
            if hote == PROTECTEUR and chemin in PAGES_A_FORMULAIRE:
                protecteur_vu.append(chemin)
                continue
            ecarts.append((chemin, hote))

    print('Consentement refusé, protection des formulaires allumée :')
    print('  · hôtes tiers hors protecteur : %s'
          % (sorted({h for _, h in ecarts}) or 'aucun'))
    print('  · %s attendu sur %s — vu sur %s'
          % (PROTECTEUR, list(PAGES_A_FORMULAIRE), protecteur_vu or 'aucune page'))
    print('Contenus externes acceptés sur /contact — hôtes : %s' % (sorted(apres) or 'aucun'))

    print('---')
    if ecarts:
        for chemin, hote in ecarts:
            print('  %s contacte %s sans accord' % (chemin, hote))
        print('%d écart(s). À corriger : tout contenu tiers doit dormir dans un '
              '<template> jusqu’au consentement.' % len(ecarts))
        return 1

    if not protecteur_vu:
        print('La protection des formulaires a été allumée mais aucune requête n’est '
              'partie vers %s : le widget ne se rend plus. Vérifier '
              'Antispam::widget() et son appel dans les gabarits de formulaire.' % PROTECTEUR)
        return 1

    print('Aucune requête tierce sans accord. Le protecteur de formulaire ne se '
          'charge que sur les %d page(s) à formulaire.' % len(set(protecteur_vu)))
    if not apres:
        print('En revanche, rien ne se charge non plus après accord : vérifier que '
              'le bloc porte bien data-cookies-contenu et son <template>.')
    return 0


if __name__ == '__main__':
    sys.exit(main())
