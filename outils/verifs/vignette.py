"""L'image fabriquée pour Instagram, sous chaque couleur de commune.

Pourquoi un huitième auditeur : quand une publication n'a pas de photo, le
site en fabrique une — carrée, aux couleurs de la commune, avec le blason et
le titre. C'est du texte posé sur un aplat, donc du contraste ; et cet aplat
suit la couleur réglée dans l'écran Apparence, donc trois cent soixante
teintes possibles.

Aucun des sept autres auditeurs ne peut la voir. Ce n'est pas une page : c'est
un fichier JPEG produit par GD, qui part chez Meta et s'affiche dans un fil
Instagram, sur un téléphone, souvent en plein soleil. `couleur.py` mesure le
site sous chaque teinte ; celui-ci mesure l'image sous chaque teinte.

Il a déjà servi avant même d'exister : le sur-titre était écrit en
`--bleu-clair`, qui paraît le ton naturel mais n'est résolu que contre
l'ardoise et les fonds sombres du site. Sur le fond de la vignette il tombait
à 3,18:1. Il est écrit en `--bleu-barre`, que `Charte` résout explicitement
contre ce fond-là : 4,98:1.

La mesure se fait sur les pixels du JPEG, pas sur les couleurs déduites. Le
fond est l'aplat le plus fréquent ; les encres sont les autres couleurs assez
présentes pour former un caractère. On compare chaque encre au fond, et l'on
garde la pire — l'anticrénelage, lui, est écarté par le seuil de population :
les pixels de bord d'une lettre sont trop peu nombreux et trop dispersés pour
former un groupe.

Le blason est exclu de la mesure : c'est une image, pas du texte. Il porte son
propre noir et son propre blanc, et les traiter comme un libellé n'aurait pas
de sens.

Usage :
    python3 outils/verifs/vignette.py
    python3 outils/verifs/vignette.py --toutes    # 36 teintes au lieu de 12

Sort en code 1 s'il trouve quelque chose.
"""
import argparse
import colorsys
import os
import subprocess
import sys
from collections import Counter

from PIL import Image

RACINE = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

# Le seuil des textes courants. Le titre est assez grand pour n'en demander que
# trois, mais on ne relâche pas : une image qui part dans un fil est réduite,
# recompressée, et regardée sur un écran de téléphone.
CONTRASTE_MINI = 4.5

# Ce qu'on considère comme une encre : une couleur portée par au moins tant de
# pixels de la zone mesurée. En dessous, c'est du bord de lettre.
PART_MINI = 0.0015

# Le coin du blason, écarté de la mesure. Voir l'en-tête.
BLASON = (0, 0, 330, 275)

TITRES = (
    ('Collecte des encombrants le samedi 14 mars', 'Info pratique'),
    ('Coupure d’eau', 'Urgent'),
    ('Compte rendu du conseil municipal du 30 juin 2026 : budget, '
     'forêt communale et travaux de la salle Camille', 'Conseil municipal'),
)

LIMITES = (
    ('#808080', 'gris parfait, sans saturation'),
    ('#ff0000', 'rouge pur, saturation maximale'),
    ('#0a0a14', 'presque noir'),
    ('#f2f0d8', 'presque blanc'),
)

# Le générateur est appelé en PHP, hors serveur web : c'est lui qu'on mesure,
# et il ne dépend ni d'une session ni d'une route. Le script tient en quatre
# lignes plutôt que dans un fichier à part, pour qu'on le lise ici.
PHP = r'''
require getenv("RACINE") . "/app/Core/Charte.php";
require getenv("RACINE") . "/app/Core/Vignette.php";
$v = new App\Core\Vignette(getenv("RACINE") . "/public",
                           new App\Core\Charte($argv[1]), "Mairie d’Angeot");
echo $v->fabriquer($argv[2], $argv[3]);
'''


def hex_depuis_teinte(h: int, s: float = 0.55, l: float = 0.42) -> str:
    r, v, b = colorsys.hls_to_rgb(h / 360, l, s)
    return '#%02x%02x%02x' % (round(r * 255), round(v * 255), round(b * 255))


def luminance(c) -> float:
    v = []
    for x in c:
        x /= 255
        v.append(x / 12.92 if x <= .03928 else ((x + .055) / 1.055) ** 2.4)
    return .2126 * v[0] + .7152 * v[1] + .0722 * v[2]


def rapport(a, b) -> float:
    la, lb = luminance(a), luminance(b)
    return (max(la, lb) + .05) / (min(la, lb) + .05)


def fabriquer(couleur: str, titre: str, surtitre: str) -> str:
    env = dict(os.environ, RACINE=RACINE)
    sortie = subprocess.run(['php', '-r', PHP, '--', couleur, titre, surtitre],
                            capture_output=True, text=True, env=env, cwd=RACINE)
    if sortie.returncode != 0 or not sortie.stdout.strip():
        raise RuntimeError((sortie.stderr or sortie.stdout).strip()[:300])
    return sortie.stdout.strip()


def mesurer(chemin: str):
    """Rend (pire rapport, encre fautive, fond) pour une image."""
    image = Image.open(chemin).convert('RGB')
    if image.size != (1080, 1080):
        raise RuntimeError('image %dx%d, 1080x1080 attendu' % image.size)

    # On quantifie légèrement : un JPEG à 88 fait osciller un aplat de un ou
    # deux niveaux, et sans cela le « fond » se disperserait en cent nuances.
    pixels = []
    x0, y0, x1, y1 = BLASON
    for y in range(0, 1080, 2):
        for x in range(0, 1080, 2):
            if x0 <= x < x1 and y0 <= y < y1:
                continue
            r, v, b = image.getpixel((x, y))
            pixels.append((r >> 2 << 2, v >> 2 << 2, b >> 2 << 2))

    compte = Counter(pixels)
    total = len(pixels)
    fond = compte.most_common(1)[0][0]

    pire, fautive = 99.0, None
    for couleur, n in compte.items():
        if n / total < PART_MINI:
            continue
        # Une couleur trop proche du fond est le fond lui-même, à un niveau de
        # compression près : ce n'est pas une encre.
        if rapport(couleur, fond) < 1.25:
            continue
        r = rapport(couleur, fond)
        if r < pire:
            pire, fautive = r, couleur

    if fautive is None:
        raise RuntimeError('aucune encre trouvée — l’image est-elle vide ?')

    return pire, fautive, fond


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    ap.add_argument('--toutes', action='store_true',
                    help='36 teintes au lieu de 12 — plus long, même conclusion')
    args = ap.parse_args()

    pas = 10 if args.toutes else 30
    essais = [(hex_depuis_teinte(h), 'teinte %d°' % h) for h in range(0, 360, pas)]
    essais += list(LIMITES)

    total = 0
    for couleur, etiquette in essais:
        ecarts = 0
        pire_teinte = 99.0
        for titre, surtitre in TITRES:
            try:
                chemin = fabriquer(couleur, titre, surtitre)
                pire, encre, fond = mesurer(os.path.join(RACINE, 'public', chemin))
            except RuntimeError as e:
                total += 1
                ecarts += 1
                print('       %-40s %s' % (titre[:38], e))
                continue
            pire_teinte = min(pire_teinte, pire)
            if pire < CONTRASTE_MINI:
                total += 1
                ecarts += 1
                print('       %5.2f:1  encre rgb%s sur fond rgb%s  « %s »'
                      % (pire, encre, fond, titre[:44]))
        print('  %-6s %-32s pire cas %5.2f:1'
              % ('ok' if not ecarts else 'ECART', couleur + '  ' + etiquette, pire_teinte))

    print('---')
    print('%d couleurs × %d titres — %d écart(s).' % (len(essais), len(TITRES), total))
    if total:
        print('Corrigez le ton retenu dans App\\Core\\Vignette, jamais le seuil : '
              'chaque ton de la charte est résolu contre un fond précis, et il '
              'faut prendre celui qui l’est contre CE fond-là.')
    return 1 if total else 0


if __name__ == '__main__':
    sys.exit(main())
