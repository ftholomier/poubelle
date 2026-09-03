"""Aucune requête tierce tant que le visiteur n'a pas accepté.

Le contrôle qui décide de la conformité du site : on refuse tout dans le
bandeau cookies, on parcourt les pages, et on compte les requêtes sortantes
vers un autre domaine que celui du site. La bonne valeur est zéro.

On vérifie aussi le pendant : une fois les contenus externes acceptés, le plan
d'accès doit réellement se charger — un consentement qui ne débloque rien est
un bandeau décoratif.

Usage :
    php -S 127.0.0.1:8081 -t public &
    python3 outils/verifs/traceurs.py

Sort en code 1 si une requête tierce part sans accord.
"""
import argparse
import json
import os
import re
import sys
from urllib.parse import urlparse

from playwright.sync_api import sync_playwright


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


def parcourir(b, base, pages, choix):
    """Parcourt le site avec un consentement donné, et rend les hôtes tiers
    contactés."""
    ctx = b.new_context(viewport={'width': 1440, 'height': 1000})
    ctx.add_cookies([{'name': 'cv_consentement', 'value': json.dumps(choix), 'url': base}])
    pg = ctx.new_page()

    interne = urlparse(base).hostname
    tiers = {}
    pg.on('request', lambda r: tiers.setdefault(urlparse(r.url).hostname, 0))

    for chemin in pages:
        pg.goto(base + chemin, wait_until='networkidle')
        # laisser le temps aux scripts différés de se manifester
        pg.wait_for_timeout(1200)
        pg.evaluate('window.scrollTo(0, document.body.scrollHeight)')
        pg.wait_for_timeout(800)

    ctx.close()
    return {h for h in tiers if h and h != interne}


def main():
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    ap.add_argument('pages', nargs='*')
    ap.add_argument('--base', default='http://127.0.0.1:8081')
    args = ap.parse_args()
    pages = args.pages or pages_du_site(args.base.rstrip("/"))

    with sync_playwright() as p:
        b = p.chromium.launch(executable_path=NAVIGATEUR, args=['--no-sandbox'])

        refus = {'v': 1, 'd': '2026-01-01', 'mesure': False, 'externes': False}
        hotes = parcourir(b, args.base, pages, refus)
        print('Consentement refusé — hôtes tiers contactés : %s' % (sorted(hotes) or 'aucun'))

        accord = {'v': 1, 'd': '2026-01-01', 'mesure': False, 'externes': True}
        apres = parcourir(b, args.base, ['/contact'], accord)
        print('Contenus externes acceptés sur /contact — hôtes : %s' % (sorted(apres) or 'aucun'))

        b.close()

    print('---')
    if hotes:
        print('%d hôte(s) contacté(s) sans accord. À corriger : tout contenu tiers '
              'doit dormir dans un <template> jusqu’au consentement.' % len(hotes))
        return 1

    print('Aucune requête tierce sans accord.')
    if not apres:
        print('En revanche, rien ne se charge non plus après accord : vérifier que '
              'le bloc porte bien data-cookies-contenu et son <template>.')
    return 0


if __name__ == '__main__':
    sys.exit(main())
