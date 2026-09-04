"""Contraste du texte du bandeau d'accueil, photo par photo.

Pourquoi un quatrième auditeur : le diaporama du bandeau tire ses vues au
hasard à chaque affichage. L'auditeur de contraste n'en voit donc qu'une sur
six par passage, et un écart peut traverser plusieurs audits sans sortir —
puis sortir un jour, chez un visiteur, sur la photo qu'on n'avait jamais
mesurée. Ce script force chaque vue à son tour.

Il a trouvé, sur ce site, deux photos à 4,41 et 4,43:1 à 390 px là où l'audit
général rendait « ok » : le voile du bandeau était réglé à 82, il est passé
à 92.

Usage :
    php -S 127.0.0.1:8081 -t public public/index.php &
    python3 outils/verifs/bandeau.py
    python3 outils/verifs/bandeau.py --base http://127.0.0.1:8081

Sort en code 1 s'il reste un écart.
"""
import argparse
import io
import json
import os
import sys

from PIL import Image
from playwright.sync_api import sync_playwright

LARGEURS = (390, 768, 1440)
SEUIL = 4.5
NAVIGATEUR = os.environ.get('CHROMIUM', '/opt/pw-browsers/chromium')

RACINE = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))


def photos() -> list[str]:
    """Les vues du diaporama, prises au contenu vivant s'il existe."""
    for dossier in ('data', 'data-modele'):
        chemin = os.path.join(RACINE, dossier, 'pages', 'accueil.json')
        if os.path.isfile(chemin):
            with open(chemin, encoding='utf-8') as f:
                hero = json.load(f)['hero']
            vues = [v['image'] for v in hero.get('diaporama', {}).get('vues', [])
                    if v.get('actif', True) and v.get('image')]
            return vues or [hero['image']]
    return []


def luminance(couleur) -> float:
    def canal(v: float) -> float:
        v /= 255
        return v / 12.92 if v <= 0.03928 else ((v + 0.055) / 1.055) ** 2.4
    r, g, b = (canal(x) for x in couleur[:3])
    return .2126 * r + .7152 * g + .0722 * b


def contraste(a, b) -> float:
    la, lb = luminance(a), luminance(b)
    haut, bas = max(la, lb), min(la, lb)
    return (haut + .05) / (bas + .05)


def mesurer(pg, largeur: int, photo: str) -> float:
    pg.goto(BASE + '/', wait_until='networkidle')
    # une seule vue montrée, la nôtre ; barre et bandeau cookies effacés, ils
    # recouvriraient la zone mesurée
    pg.evaluate("""(src) => {
        document.querySelectorAll('[data-vue]').forEach((v, i) => {
            v.style.backgroundImage = "url('/" + src + "')";
            v.classList.toggle('est-visible', i === 0);
            v.style.opacity = i === 0 ? '1' : '0';
        });
        // La bulle de l'assistant rejoint la liste pour la même raison que la
        // barre et le bandeau cookies : c'est un survol fixe, et le bas du
        // texte de bandeau passe derrière elle sur un écran court.
        document.querySelectorAll('.cookies, .entete, .assistant').forEach(e => e.style.display = 'none');
    }""", photo)
    pg.wait_for_timeout(1200)

    boite = pg.locator('.heros__texte').bounding_box()
    if boite is None:
        return 99.0

    # Le texte est effacé, puis on laisse la peinture se faire : capturer trop
    # tôt échantillonne les lettres elles-mêmes, et un texte blanc et dense
    # ressort faussement illisible.
    pg.evaluate("() => { document.querySelector('.heros__texte').style.color = 'transparent'; }")
    pg.wait_for_timeout(350)

    image = Image.open(io.BytesIO(pg.screenshot(clip=boite))).convert('RGB')
    # le pire pixel, jamais la moyenne : un mot qui passe sur une éclaircie
    # est illisible sur ce mot-là
    return min(contraste((255, 255, 255), pixel) for pixel in image.getdata())


if __name__ == '__main__':
    ap = argparse.ArgumentParser()
    ap.add_argument('--base', default='http://127.0.0.1:8081')
    args = ap.parse_args()
    BASE = args.base.rstrip('/')

    vues = photos()
    if not vues:
        print('Aucune photo de bandeau déclarée.')
        sys.exit(0)

    ecarts = 0
    pire = 99.0
    with sync_playwright() as p:
        b = p.chromium.launch(executable_path=NAVIGATEUR, args=['--no-sandbox'])
        for largeur in LARGEURS:
            pg = b.new_page(viewport={'width': largeur, 'height': 844})
            for photo in vues:
                r = mesurer(pg, largeur, photo)
                pire = min(pire, r)
                if r < SEUIL:
                    ecarts += 1
                    print(f'  ÉCART {largeur:5d} px  {r:5.2f}:1  {os.path.basename(photo)}')
                else:
                    print(f'     ok {largeur:5d} px  {r:5.2f}:1  {os.path.basename(photo)}')
            pg.close()
        b.close()

    print('---')
    print(f'Pire cas : {pire:.2f}:1 — seuil {SEUIL}. {ecarts} écart(s).')
    if ecarts:
        print('Relevez le voile du bandeau dans le back-office (Page d’accueil → '
              'Assombrissement), ou retirez la photo en cause du diaporama.')
    sys.exit(1 if ecarts else 0)
