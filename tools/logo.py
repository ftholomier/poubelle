#!/usr/bin/env python3
"""Produit les quatre déclinaisons du logo « le|digital.com ».

Les lettres sont converties en contours à partir de Montserrat ExtraBold, la
graisse déjà embarquée sur le site : le logo ne dépend d'aucun chargement de
police et reste net à toutes les tailles.

    pip install fonttools brotli
    python3 tools/logo.py

Quatre fichiers en sortie :

  public/assets/img/logo-mono.svg  l'en-tête — encre en « currentColor », barre
                                   et carré en « --logo-accent », pour que le
                                   logo suive le fond et la couleur dominante ;
  public/assets/img/logo.svg       partage et impression — les couleurs réelles,
                                   aucune variable ne serait résolue là-bas ;
  public/assets/img/favicon.svg    le « d », la barre et le carré : à seize
                                   pixels, le mot entier est illisible ;
  content/shapes/logo.svg          la version empilée, pour le nuage de
                                   particules — voir plus bas.
"""

from __future__ import annotations

import pathlib
import sys

try:
    from fontTools.pens.svgPathPen import SVGPathPen
    from fontTools.ttLib import TTFont
except ImportError:  # pragma: no cover - dépendance d'outillage
    sys.exit("fontTools est requis : pip install fonttools brotli")

RACINE = pathlib.Path(__file__).resolve().parent.parent
POLICE = RACINE / 'public/assets/fonts/montserrat-800.woff2'
ENCRE = '#232323'
ROUGE = '#d51317'

# Proportions relevées sur le logo d'origine.
GRAND, PETIT = 200, 76
ECART_BARRE = 0.085      # blanc de part et d'autre de la barre
BARRE_LARGEUR = 0.072
BARRE_HAUT, BARRE_BAS = 0.815, 0.205   # débords au-dessus et au-dessous de la ligne
CARRE = 0.46             # côté du point carré, en part de PETIT

police = TTFont(POLICE)
upm = police['head'].unitsPerEm
glyphes = police.getGlyphSet()
table = police.getBestCmap()
try:
    crenage = police['kern'].kernTables[0].kernTable
except (KeyError, IndexError):
    crenage = {}


def mot(texte: str, corps: float, x: float, y: float, approche: float = -0.035):
    """Dessine un mot ; renvoie (chemins, largeur). « y » est la ligne de base."""
    echelle = corps / upm
    chemins: list[str] = []
    plume, precedent = x, None
    for caractere in texte:
        nom = table[ord(caractere)]
        if precedent is not None:
            plume += crenage.get((precedent, nom), 0) * echelle
        stylo = SVGPathPen(glyphes)
        glyphes[nom].draw(stylo)
        trace = stylo.getCommands()
        if trace:
            chemins.append(
                f'<path transform="translate({plume:.2f} {y:.2f}) '
                f'scale({echelle:.6f} {-echelle:.6f})" d="{trace}"/>'
            )
        plume += glyphes[nom].width * echelle + approche * corps
        precedent = nom
    return chemins, plume - x - approche * corps


def rect(x: float, y: float, largeur: float, hauteur: float) -> str:
    return f'<rect x="{x:.2f}" y="{y:.2f}" width="{largeur:.2f}" height="{hauteur:.2f}"/>'


def enveloppe(largeur: float, hauteur: float, corps: str, etiquette: str) -> str:
    return (
        f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {largeur} {hauteur}" '
        f'role="img" aria-label="le|digital.com">\n'
        f'  <title>{etiquette}</title>\n{corps}\n</svg>\n'
    )


def ecrire(chemin: pathlib.Path, contenu: str) -> None:
    chemin.parent.mkdir(parents=True, exist_ok=True)
    chemin.write_text(contenu, encoding='utf-8')
    print(f'{chemin.relative_to(RACINE)} — {len(contenu)} octets')


# ------------------------------------------------------------- Logo en ligne

MARGE, BASE = 6, 200
x = MARGE
le, largeur_le = mot('le', GRAND, x, BASE)
x += largeur_le + ECART_BARRE * GRAND

barre_l = BARRE_LARGEUR * GRAND
barre = rect(x, BASE - BARRE_HAUT * GRAND, barre_l, (BARRE_HAUT + BARRE_BAS) * GRAND)
x += barre_l + ECART_BARRE * GRAND

digital, largeur_digital = mot('digital', GRAND, x, BASE)
x += largeur_digital + 0.035 * GRAND

cote = CARRE * PETIT
point = rect(x, BASE - cote, cote, cote)
x += cote + 0.09 * PETIT

com, largeur_com = mot('com', PETIT, x, BASE)
LARGEUR = round(x + largeur_com + MARGE, 1)
HAUTEUR = round(BASE + 0.30 * GRAND, 1)

sombre = '\n'.join('    ' + c for c in le + digital)
accent = '\n'.join('    ' + c for c in com)


def en_ligne(encre: str, rouge: str) -> str:
    return enveloppe(
        LARGEUR, HAUTEUR,
        f'  <g fill="{encre}">\n{sombre}\n  </g>\n'
        f'  <g fill="{rouge}">\n    {barre}\n    {point}\n{accent}\n  </g>',
        'le|digital.com',
    )


ecrire(RACINE / 'public/assets/img/logo.svg', en_ligne(ENCRE, ROUGE))
ecrire(
    RACINE / 'public/assets/img/logo-mono.svg',
    en_ligne('currentColor', 'var(--logo-accent, currentColor)'),
)

# ---------------------------------------------------------------- Favicone

d, largeur_d = mot('d', 68, 26, 78)
favicone = (
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" '
    'role="img" aria-label="le|digital.com">\n'
    '  <title>le|digital.com</title>\n'
    f'  <rect width="100" height="100" rx="22" fill="var(--logo-ink, {ENCRE})"/>\n'
    f'  <rect x="12" y="18" width="7" height="62" fill="var(--logo-accent, {ROUGE})"/>\n'
    '  <g fill="#fff">\n' + '\n'.join('    ' + c for c in d) + '\n  </g>\n'
    f'  <rect x="{26 + largeur_d + 5:.1f}" y="64" width="14" height="14" '
    f'fill="var(--logo-accent, {ROUGE})"/>\n</svg>\n'
)
ecrire(RACINE / 'public/assets/img/favicon.svg', favicone)

# ------------------------------------------------- Version pour les particules
#
# Le logo en ligne fait 4,25 fois plus large que haut : cadré dans la fenêtre,
# il devient une traînée de points illisible. Empilé sur trois lignes, il tient
# dans un carré et chaque lettre se lit une fois éclatée.

MARGE_P, PETIT_P = 14, 92
lignes: list[str] = []

y1 = MARGE_P + 0.8 * GRAND
le, largeur_le = mot('le', GRAND, MARGE_P, y1)
x = MARGE_P + largeur_le + ECART_BARRE * GRAND
lignes += le + [rect(x, y1 - BARRE_HAUT * GRAND, barre_l, (BARRE_HAUT + BARRE_BAS) * GRAND)]
largeur_max = x + barre_l

y2 = y1 + 1.12 * GRAND
digital, largeur_digital = mot('digital', GRAND, MARGE_P, y2)
lignes += digital
largeur_max = max(largeur_max, MARGE_P + largeur_digital)

# Le carré et « com » se calent à droite, sous la fin de « digital ».
y3 = y2 + 0.72 * GRAND
cote = CARRE * PETIT_P
_, largeur_com = mot('com', PETIT_P, 0, y3)
x3 = MARGE_P + largeur_digital - (cote + 0.09 * PETIT_P + largeur_com)
com, _ = mot('com', PETIT_P, x3 + cote + 0.09 * PETIT_P, y3)
lignes += [rect(x3, y3 - cote, cote, cote)] + com

largeur = round(largeur_max + MARGE_P, 1)
hauteur = round(y3 + 0.24 * GRAND + MARGE_P, 1)
ecrire(
    RACINE / 'content/shapes/logo.svg',
    f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {largeur} {hauteur}" fill-rule="evenodd">\n'
    '  <title>Logo le|digital.com, empilé</title>\n'
    + '\n'.join('  ' + c for c in lignes)
    + '\n</svg>\n'
)
print(f'\nEn ligne : rapport {LARGEUR / HAUTEUR:.2f} — empilé : rapport {largeur / hauteur:.2f}')
