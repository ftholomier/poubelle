"""Prépare les médias du site à partir de sources/ (non versionné) vers public/assets/ (versionné) :
captures de l'application et photos en WebP, plan topographique, vidéo allégée.

Sources attendues (voir README) :
  sources/captures/*.png        captures (terricom/scripts/teaser/captures-site.mjs)
  sources/photos/*.jpg          photos libres + credits.json (terricom/scripts/teaser/photos.py)
  sources/images/*.jpg          images tirées du teaser
  sources/topo.png              plan topographique sombre (terricom/scripts/teaser/sombre.mjs)
  sources/teaser.mp4            teaser (terricom/docs/teaser/terricom-teaser.mp4)

Usage : python3 outils/medias.py   (Pillow requis ; FFMPEG pour la vidéo)
"""
import json
import os
import subprocess

from PIL import Image

ICI = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC = os.path.join(ICI, 'sources')
OUT = os.path.join(ICI, 'public', 'assets')


def webp(src, dst, largeur, qualite=80, recadre=None):
    im = Image.open(src).convert('RGB')
    if recadre:
        w, h = im.size
        l, t, r, b = recadre
        im = im.crop((int(l * w), int(t * h), int(r * w), int(b * h)))
    if im.width > largeur:
        im = im.resize((largeur, round(im.height * largeur / im.width)), Image.LANCZOS)
    os.makedirs(os.path.dirname(dst), exist_ok=True)
    im.save(dst, 'WEBP', quality=qualite, method=6)
    return im.size


def main():
    tailles = {}
    # captures de l'application : grand format et vignette
    for f in sorted(os.listdir(f'{SRC}/captures')):
        if not f.endswith('.png'):
            continue
        n = f[:-4]
        if n.startswith('mobile-'):
            tailles[n] = webp(f'{SRC}/captures/{f}', f'{OUT}/img/app/{n}.webp', 780, 82)
        else:
            tailles[n] = webp(f'{SRC}/captures/{f}', f'{OUT}/img/app/{n}.webp', 1600, 80)
            webp(f'{SRC}/captures/{f}', f'{OUT}/img/app/{n}-800.webp', 800, 78)
    # photos du territoire
    for f in sorted(os.listdir(f'{SRC}/photos')):
        if f.endswith('.jpg'):
            n = f[:-4]
            tailles[n] = webp(f'{SRC}/photos/{f}', f'{OUT}/img/photos/{n}.webp', 1920, 76)
            webp(f'{SRC}/photos/{f}', f'{OUT}/img/photos/{n}-800.webp', 800, 74)
    # images tirées du teaser et plan topographique
    for f in sorted(os.listdir(f'{SRC}/images')):
        n = f.rsplit('.', 1)[0]
        tailles[n] = webp(f'{SRC}/images/{f}', f'{OUT}/img/{n}.webp', 1920, 80)
    tailles['topo'] = webp(f'{SRC}/topo.png', f'{OUT}/img/topo.webp', 2400, 70)
    webp(f'{SRC}/topo.png', f'{OUT}/img/topo-800.webp', 900, 68)
    # image de partage (1200 × 630)
    im = Image.open(f'{SRC}/images/logo-signature.jpg').convert('RGB')
    w, h = im.size
    im = im.crop((0, int(h * 0.22), w, int(h * 0.22) + int(w * 630 / 1200))).resize((1200, 630), Image.LANCZOS)
    im.save(f'{OUT}/img/partage.jpg', quality=86)
    json.dump({k: list(v) for k, v in tailles.items()}, open(f'{ICI}/outils/tailles.json', 'w'), indent=0)

    # crédits photos
    json.dump(json.load(open(f'{SRC}/photos/credits.json')), open(f'{ICI}/outils/credits.json', 'w'), ensure_ascii=False, indent=1)

    # vidéo : 1280 × 720, légère, lecture progressive
    ff = os.environ.get('FFMPEG', 'ffmpeg')
    if os.path.exists(f'{SRC}/teaser.mp4'):
        os.makedirs(f'{OUT}/video', exist_ok=True)
        subprocess.run([ff, '-y', '-loglevel', 'error', '-i', f'{SRC}/teaser.mp4', '-vf', 'scale=1280:720', '-c:v', 'libx264', '-preset', 'slow', '-crf', '27',
                        '-tune', 'film', '-pix_fmt', 'yuv420p', '-c:a', 'aac', '-b:a', '128k', '-movflags', '+faststart', f'{OUT}/video/teaser.mp4'], check=True)
        subprocess.run([ff, '-y', '-loglevel', 'error', '-ss', '39.5', '-i', f'{SRC}/teaser.mp4', '-frames:v', '1', '-vf', 'scale=1280:720', f'{OUT}/video/affiche.jpg'], check=True)
    print('médias prêts')


if __name__ == '__main__':
    main()
