"""Photos libres du territoire (Wikimedia Commons) pour les captures du teaser, avec leurs crédits.

Elles remplacent, pendant les captures seulement, les photos Unsplash du jeu de démonstration (bloquées hors
production) et servent de photos aux pages des communes. Commons limite fortement le débit : une requête à la fois.
Usage : PYTHONPATH=.teaser/py python3 scripts/teaser/photos.py
"""
import json
import os
import re
import time
import urllib.parse
import urllib.request

RACINE = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
W = os.environ.get('TEASER_DIR', os.path.join(RACINE, '.teaser'))
UA = {'User-Agent': 'terricom-teaser/1.0 (https://terricom.fr)'}

PHOTOS = {
    'territoire': 'Le lac de Saint-Point depuis le belvédère de Montperreux.jpg',
    'metabief': 'Métabief - Panorama depuis Saint-Antoine B.jpg',
    'jougne': 'Jougne Eglise 30.jpg',
    'malbuisson': 'Malbuisson au bord du lac de Saint-Point2.jpg',
    'noel': 'Jougne Place 10.jpg',
    'comte-cave': "Cave d'affinage de Comté en Franche-Comté.JPG",
    'montdor': "Vacherin Mont d'OR1.JPG",
    'pain': 'Pain au lait.jpg',
    'roue': 'Métabief, roue à aubes sur le Bief Rouge.jpg',
    'montdor-vue': "Doubs Blick vom Mont-d'Or 11.jpg",
}


def get(url, tries=8):
    for a in range(tries):
        try:
            return urllib.request.urlopen(urllib.request.Request(url, headers=UA), timeout=60).read()
        except urllib.error.HTTPError as e:
            if e.code != 429 or a == tries - 1:
                raise
            time.sleep(30)


def main():
    from PIL import Image, ImageOps

    os.makedirs(f'{W}/photos', exist_ok=True)
    os.makedirs(f'{W}/photos-web', exist_ok=True)
    titres = '|'.join('File:' + t for t in PHOTOS.values())
    q = {'action': 'query', 'format': 'json', 'titles': titres, 'prop': 'imageinfo', 'iiprop': 'url|extmetadata', 'iiurlwidth': 1920}
    pages = json.loads(get('https://commons.wikimedia.org/w/api.php?' + urllib.parse.urlencode(q)))['query']['pages'].values()
    info = {p['title'][5:]: p['imageinfo'][0] for p in pages if 'imageinfo' in p}
    credits = {}
    for cle, titre in PHOTOS.items():
        ii = info.get(titre) or info.get(titre.replace('_', ' '))
        if not ii:
            print('introuvable :', titre)
            continue
        m = ii.get('extmetadata', {})
        credits[cle] = {
            'titre': titre,
            'auteur': re.sub('<[^>]+>', '', m.get('Artist', {}).get('value', '?')).strip(),
            'licence': m.get('LicenseShortName', {}).get('value', '?'),
            'page': ii.get('descriptionurl'),
        }
        f = f'{W}/photos/{cle}.jpg'
        if not os.path.exists(f):
            open(f, 'wb').write(get(ii.get('thumburl') or ii['url']))
            time.sleep(8)
        im = ImageOps.exif_transpose(Image.open(f)).convert('RGB')
        im.thumbnail((2400, 2400))
        im.save(f'{W}/photos-web/{cle}.jpg', quality=86)
        print('ok', cle, credits[cle]['auteur'], credits[cle]['licence'])
    json.dump(credits, open(f'{W}/photos/credits.json', 'w'), ensure_ascii=False, indent=1)


if __name__ == '__main__':
    main()
