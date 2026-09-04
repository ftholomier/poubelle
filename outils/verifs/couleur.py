"""Le site aux quatre coins de la roue chromatique.

Pourquoi un sixième auditeur : la mairie choisit elle-même la couleur de la
commune (Apparence → Couleur de la commune), et cette couleur repeint tout —
les aplats, les filets, les fonds sombres, les voiles posés sur les photos de
bandeau. Les cinq autres auditeurs mesurent le site tel qu'il est réglé le
jour où on les lance, c'est-à-dire une teinte sur trois cent soixante. Un
défaut qui n'apparaît qu'en rouge ou en jaune leur est invisible, et c'est la
mairie qui le découvrirait, en ligne.

Le réglage n'est pourtant pas dangereux, et ce script est là pour le prouver
plutôt que pour l'affirmer. `App\\Core\\Charte` ne pose jamais la couleur
choisie telle quelle : elle en garde la teinte, borne la saturation, et
RÉSOUT la luminosité de chaque ton jusqu'à ce qu'il tienne le contraste exigé
sur le fond où il sert. La propriété à vérifier est donc : quelle que soit la
teinte, aucun texte du site ne passe sous son seuil.

C'est ce que ce script mesure — vraiment, sur des pixels peints, en
réutilisant l'auditeur de contraste plutôt qu'en refaisant son travail. Il
force une douzaine de teintes réparties sur la roue, plus les cas limites
(gris sans saturation, couleur criarde, couleur presque noire, couleur
presque blanche), et repasse le contraste sur un échantillon de pages qui
couvre toutes les familles de fonds du site : bandeau photographique, section
claire, section teintée, section sombre, bande d'appel, fiche à encart sombre.

Il rend la main sur le réglage d'origine, même s'il échoue.

Usage :
    php -S 127.0.0.1:8081 -t public public/index.php &
    python3 outils/verifs/couleur.py
    python3 outils/verifs/couleur.py --base http://127.0.0.1:8081
    python3 outils/verifs/couleur.py --toutes    # 36 teintes au lieu de 12

Sort en code 1 s'il trouve quelque chose.
"""
import argparse
import colorsys
import json
import os
import sys

from playwright.sync_api import sync_playwright

RACINE = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
PARAMETRES = os.path.join(RACINE, 'data', 'admin', 'parametres.json')
NAVIGATEUR = os.environ.get('CHROMIUM', '/opt/pw-browsers/chromium')

# Les pages retenues ne sont pas les plus visitées mais les plus variées : il
# faut qu'entre elles se trouvent tous les fonds sur lesquels un ton de marque
# se pose. Les mesurer toutes coûterait vingt minutes par teinte sans rien
# apprendre de plus.
PAGES = (
    '/',                                  # bandeau photo, bande d'indicateurs sombre
    '/conseil-municipal',                 # tuiles d'élus sur ardoise
    '/histoire-et-patrimoine',            # citation sur fond sombre
    '/demarches/permis-de-construire',    # encart sombre, liens sortants
    '/salle-camille',                     # tableau de tarifs, sections teintées
    '/numeros-utiles',                    # urgences en grand sur fond sombre
)

# Douze teintes régulièrement réparties, plus quatre cas limites. Les cas
# limites comptent au moins autant que la roue : c'est le gris sans saturation
# et le jaune très clair qui mettent une dérivation en défaut, pas le bleu.
TEINTES = tuple(f'{h}' for h in range(0, 360, 30))
LIMITES = (
    ('#808080', 'gris parfait, sans saturation'),
    ('#ff0000', 'rouge pur, saturation maximale'),
    ('#0a0a14', 'presque noir'),
    ('#f2f0d8', 'presque blanc'),
)


def hex_depuis_teinte(h: int, s: float = 0.55, l: float = 0.42) -> str:
    r, v, b = colorsys.hls_to_rgb(h / 360, l, s)
    return '#%02x%02x%02x' % (round(r * 255), round(v * 255), round(b * 255))


def reglages() -> dict:
    if os.path.isfile(PARAMETRES):
        with open(PARAMETRES, encoding='utf-8') as f:
            try:
                return json.load(f)
            except json.JSONDecodeError:
                return {}
    return {}


def regler(couleur: str) -> None:
    donnees = reglages()
    apparence = dict(donnees.get('apparence', {}))
    apparence['couleur'] = couleur
    donnees['apparence'] = apparence
    os.makedirs(os.path.dirname(PARAMETRES), exist_ok=True)
    with open(PARAMETRES, 'w', encoding='utf-8') as f:
        json.dump(donnees, f, ensure_ascii=False, indent=2)


def charger_contraste():
    """Réutilise l'auditeur de contraste plutôt que de le réécrire.

    Deux auditeurs qui mesureraient le contraste de deux façons différentes
    seraient pires qu'un seul : le jour où l'un trouve ce que l'autre rate, on
    ne sait plus lequel croire. Celui-ci ne fait que régler la couleur et
    appeler l'autre.
    """
    chemin = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'contraste.py')
    espace: dict = {'__name__': 'contraste_importe'}
    with open(chemin, encoding='utf-8') as f:
        exec(compile(f.read(), chemin, 'exec'), espace)
    return espace


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    ap.add_argument('--base', default='http://127.0.0.1:8081')
    ap.add_argument('--toutes', action='store_true',
                    help='36 teintes au lieu de 12 — plus long, même conclusion')
    args = ap.parse_args()
    base = args.base.rstrip('/')

    contraste = charger_contraste()
    auditer = contraste['auditer']

    pas = 10 if args.toutes else 30
    essais = [(hex_depuis_teinte(h), f'teinte {h}°') for h in range(0, 360, pas)]
    essais += list(LIMITES)

    origine = reglages()
    couleur_origine = str(origine.get('apparence', {}).get('couleur', ''))
    total = 0

    try:
        with sync_playwright() as p:
            navigateur = p.chromium.launch(executable_path=NAVIGATEUR, args=['--no-sandbox'])
            consentement = json.dumps({'v': 1, 'd': '2026-01-01',
                                       'mesure': False, 'externes': False})
            for couleur, etiquette in essais:
                regler(couleur)
                ecarts_couleur = 0
                for largeur in (390, 1440):
                    ctx = navigateur.new_context(viewport={'width': largeur, 'height': 900})
                    ctx.add_cookies([{'name': 'cv_consentement',
                                      'value': consentement, 'url': base}])
                    pg = ctx.new_page()
                    for chemin in PAGES:
                        pg.goto(base + chemin, wait_until='domcontentloaded')
                        pg.wait_for_timeout(400)
                        for sel, pire, seuil, texte in auditer(pg, 900):
                            ecarts_couleur += 1
                            print('       %-26s %5.2f:1  (seuil %s)  %-22s « %s »'
                                  % (sel, pire, seuil, chemin, texte))
                    ctx.close()
                total += ecarts_couleur
                print('  %-6s %-32s %s' % ('ok' if not ecarts_couleur else 'ECART',
                                           couleur + '  ' + etiquette,
                                           'aucun écart' if not ecarts_couleur
                                           else '%d écart(s)' % ecarts_couleur))
            navigateur.close()
    finally:
        # Le réglage d'origine revient quoi qu'il arrive : un auditeur qui
        # laisse le site repeint en violet parce qu'il a échoué au milieu est
        # un auditeur qu'on cesse de lancer.
        if couleur_origine:
            regler(couleur_origine)
        else:
            donnees = reglages()
            donnees.get('apparence', {}).pop('couleur', None)
            with open(PARAMETRES, 'w', encoding='utf-8') as f:
                json.dump(donnees, f, ensure_ascii=False, indent=2)

    print('---')
    print('%d teintes × %d pages × 2 largeurs — %d écart(s).'
          % (len(essais), len(PAGES), total))
    if total:
        print('Corrigez les cibles de contraste dans App\\Core\\Charte, jamais la '
              'couleur choisie : c’est la dérivation qui doit tenir, pas le choix '
              'de la mairie.')
    return 1 if total else 0


if __name__ == '__main__':
    sys.exit(main())
