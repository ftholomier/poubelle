"""L'en-tête aux extrêmes du réglage « Taille du logo ».

Pourquoi un cinquième auditeur : la mairie règle elle-même la hauteur du logo
(Apparence → Taille du logo), et choisit si la barre suit cette hauteur ou si
le logo la dépasse. Les quatre autres auditeurs mesurent le site tel qu'il est
réglé le jour où on les lance — c'est-à-dire une combinaison sur six. Un
défaut qui n'apparaît qu'à une autre valeur du curseur leur est invisible, et
c'est la mairie qui le découvrirait, en ligne.

Ce script force les deux bornes et la valeur livrée, dans les deux modes, à
quatre largeurs, en haut de page et une fois défilé. C'est ce qui autorise à
laisser le réglage ouvert : les bornes de ApparenceController ne sont pas une
estimation, elles sont mesurées ici.

Ce qu'il a trouvé : au-delà d'une centaine de pixels, sur un écran de 320, la
règle globale img{max-width:100%} rognait la largeur du logo sans toucher à sa
hauteur — il partait écrasé, à 1,94 de rapport au lieu de 2,34. La correction
est object-fit:contain sur .entete__marque : il se réduit désormais au lieu de
se déformer.

Il rend la main sur le réglage d'origine, même s'il échoue.

Usage :
    php -S 127.0.0.1:8081 -t public public/index.php &
    python3 outils/verifs/entete.py
    python3 outils/verifs/entete.py --base http://127.0.0.1:8081

Sort en code 1 s'il trouve quelque chose.
"""
import argparse
import json
import os
import re
import sys

from playwright.sync_api import sync_playwright

LARGEURS = (320, 390, 768, 1440)
# Les deux dispositions ne donnent pas la même taille de logo sous 1080 px :
# les mesurer toutes les deux, sinon la moitié des réglages possibles n'est
# jamais vue.
MENUS = ('lateral', 'horizontal')
# Même convention que mise-en-page.py : la règle des 44 px s'applique là où
# l'on tape, c'est-à-dire sous 780 px. Deux auditeurs qui ne mesureraient pas
# la même chose seraient pires qu'un seul.
CIBLE_MINI = 44
CIBLE_JUSQUA = 780
NAVIGATEUR = os.environ.get('CHROMIUM', '/opt/pw-browsers/chromium')

RACINE = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
PARAMETRES = os.path.join(RACINE, 'data', 'admin', 'parametres.json')


def bornes() -> tuple[int, int, int]:
    """Les bornes lues dans le contrôleur, pour qu'elles ne divergent jamais.

    Les recopier ici reviendrait à mesurer autre chose que ce que le
    back-office autorise : le jour où quelqu'un relève le plafond, c'est
    précisément ce jour-là qu'il faut que l'auditeur suive.
    """
    source = os.path.join(RACINE, 'app', 'Admin', 'ApparenceController.php')
    with open(source, encoding='utf-8') as f:
        php = f.read()
    lu = {}
    for cle in ('LOGO_MIN', 'LOGO_MAX', 'LOGO_DEFAUT'):
        m = re.search(rf'const\s+{cle}\s*=\s*(\d+)\s*;', php)
        if not m:
            print(f'Borne {cle} introuvable dans ApparenceController.', file=sys.stderr)
            sys.exit(2)
        lu[cle] = int(m.group(1))
    return lu['LOGO_MIN'], lu['LOGO_DEFAUT'], lu['LOGO_MAX']


def reglages() -> dict:
    if os.path.isfile(PARAMETRES):
        with open(PARAMETRES, encoding='utf-8') as f:
            return json.load(f)
    return {}


def regler(hauteur: int, deborde: bool, menu: str) -> None:
    donnees = reglages()
    apparence = dict(donnees.get('apparence', {}))
    apparence.update({'menu': menu, 'logo': hauteur, 'logo_deborde': deborde})
    donnees['apparence'] = apparence
    os.makedirs(os.path.dirname(PARAMETRES), exist_ok=True)
    with open(PARAMETRES, 'w', encoding='utf-8') as f:
        json.dump(donnees, f, ensure_ascii=False, indent=2)


MESURE = """() => {
  const barre  = document.querySelector('.entete');
  const logo   = document.querySelector('.entete__marque--clair');
  const burger = document.querySelector('.burger');
  if (!barre || !logo) return null;
  const b = barre.getBoundingClientRect();
  const l = logo.getBoundingClientRect();
  const g = burger ? burger.getBoundingClientRect() : null;
  const doc = document.documentElement;

  // Proportions réellement peintes. La boîte peut être rognée en largeur par
  // img{max-width:100%} sans que la hauteur demandée bouge : c'est là qu'un
  // logo part écrasé. object-fit décide si le dessin suit la boîte ou garde
  // son rapport, donc on reconstitue le dessin plutôt que de lire la boîte.
  const ajust = getComputedStyle(logo).objectFit;
  let peintL = l.width, peintH = l.height;
  if ((ajust === 'contain' || ajust === 'scale-down')
      && logo.naturalWidth && logo.naturalHeight) {
    const e = Math.min(l.width / logo.naturalWidth, l.height / logo.naturalHeight);
    peintL = logo.naturalWidth * e;
    peintH = logo.naturalHeight * e;
  }

  // La cible tactile est le lien, pas l'image : c'est lui qu'on tape.
  const lien = logo.closest('a');
  const cible = lien ? lien.getBoundingClientRect().height : l.height;

  return {
    barre: b.height, logo: l.height, largeurLogo: l.width, cible: cible,
    haut: l.top - b.top, bas: l.bottom - b.top,
    pleine: barre.classList.contains('entete--pleine'),
    surBurger: g ? !(l.right <= g.left || l.left >= g.right) : false,
    debordPage: doc.scrollWidth - doc.clientWidth,
    rapportPeint: peintH ? peintL / peintH : 0,
    rapportNaturel: (logo.naturalWidth && logo.naturalHeight)
      ? logo.naturalWidth / logo.naturalHeight : 0,
  };
}"""


def mesurer(page, base: str, largeur: int, defile: bool) -> dict:
    page.set_viewport_size({'width': largeur, 'height': 800})
    page.goto(base + '/', wait_until='load')
    # les transitions de hauteur de barre font mesurer un état intermédiaire
    page.evaluate("document.querySelectorAll('*').forEach(e => {"
                  "e.style.transition = 'none'; e.style.animation = 'none'; })")
    if defile:
        page.evaluate('window.scrollTo(0, 600)')
        page.wait_for_timeout(250)
    return page.evaluate(MESURE)


def souci(m: dict, deborde: bool, largeur: int) -> list[str]:
    """Ce qui compte comme défaut, et pourquoi.

    Le débordement voulu n'en est pas un : c'est le réglage. Ce qu'on refuse,
    c'est ce qu'aucun réglage ne peut vouloir — un logo hors de l'écran par le
    haut, posé sur le burger, une page qui défile latéralement, un débordement
    là où la mairie a demandé que la barre suive, ou des proportions fausses.

    Un logo servi plus petit que demandé sur un écran étroit n'est pas un
    défaut : c'est la place qui manque, et il vaut mieux le réduire que le
    déformer. Un logo déformé, lui, en est un — c'est ce que ce script a
    trouvé au-delà de 100 px avant qu'object-fit ne soit posé.
    """
    faits = []
    if m['debordPage'] > 0:
        faits.append(f"la page déborde de {m['debordPage']:.0f} px en largeur")
    if m['surBurger']:
        faits.append('le logo touche le burger')
    if m['haut'] < -1:
        faits.append(f"le logo sort de la barre par le haut ({m['haut']:.0f} px)")
    if not deborde and m['bas'] > m['barre'] + 1:
        faits.append(f"le logo dépasse alors que la barre devait le suivre "
                     f"({m['bas']:.0f} > {m['barre']:.0f} px)")
    if deborde and m['pleine'] and m['bas'] > m['barre'] + 1:
        faits.append(f"le logo dépasse la barre réduite, donc sur le contenu "
                     f"({m['bas']:.0f} > {m['barre']:.0f} px)")
    if largeur <= CIBLE_JUSQUA and round(m['cible']) < CIBLE_MINI:
        faits.append(f"cible tactile du logo à {m['cible']:.0f} px "
                     f"(minimum {CIBLE_MINI})")
    if m['rapportNaturel'] and abs(m['rapportPeint'] - m['rapportNaturel']) \
            / m['rapportNaturel'] > 0.02:
        faits.append(f"le logo est déformé ({m['rapportPeint']:.2f} au lieu de "
                     f"{m['rapportNaturel']:.2f})")
    return faits


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument('--base', default='http://127.0.0.1:8081')
    args = ap.parse_args()

    mini, defaut, maxi = bornes()
    tailles = sorted({mini, defaut, maxi})
    origine = reglages()

    soucis = 0
    try:
        with sync_playwright() as p:
            navigateur = p.chromium.launch(executable_path=NAVIGATEUR, args=['--no-sandbox'])
            page = navigateur.new_page()
            for menu in MENUS:
                for hauteur in tailles:
                    for deborde in (False, True):
                        mode = 'déborde' if deborde else 'la barre suit'
                        regler(hauteur, deborde, menu)
                        for largeur in LARGEURS:
                            for defile in (False, True):
                                m = mesurer(page, args.base, largeur, defile)
                                if m is None:
                                    print('  ÉCHEC  en-tête introuvable', file=sys.stderr)
                                    sys.exit(2)
                                faits = souci(m, deborde, largeur)
                                soucis += len(faits)
                                etat = 'défilée' if m['pleine'] else 'haut   '
                                ligne = (f"{menu:<10} {hauteur:>4} px  {mode:<13} "
                                         f"{largeur:>5} px  {etat}  "
                                         f"barre {m['barre']:>3.0f}  logo {m['logo']:>3.0f}"
                                         f"×{m['largeurLogo']:>3.0f}  haut {m['haut']:>3.0f}  "
                                         f"cible {m['cible']:>3.0f}")
                                if faits:
                                    print(f'  SOUCI {ligne} — ' + ' ; '.join(faits))
                                else:
                                    print(f'     ok {ligne}')
            navigateur.close()
    finally:
        # le réglage de la mairie n'appartient pas à l'auditeur
        if origine:
            with open(PARAMETRES, 'w', encoding='utf-8') as f:
                json.dump(origine, f, ensure_ascii=False, indent=2)
        elif os.path.isfile(PARAMETRES):
            os.remove(PARAMETRES)

    print('---')
    print(f'{len(MENUS)} dispositions × {len(tailles)} tailles × 2 modes × '
          f'{len(LARGEURS)} largeurs × 2 états — {soucis} souci(s).')
    if soucis:
        print('Corrigez la feuille de style si le défaut vient d’elle — c’est le cas '
              'd’une déformation ou d’un chevauchement. Si la mise en page ne peut '
              'pas tenir la valeur, resserrez LOGO_MIN / LOGO_MAX dans '
              'app/Admin/ApparenceController.php : ce sont eux qui bornent ce que la '
              'mairie peut demander.')
    sys.exit(1 if soucis else 0)


if __name__ == '__main__':
    main()
