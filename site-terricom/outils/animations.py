"""Animations du site : l'application filmée en train d'être utilisée, en boucles vidéo courtes et muettes.

Les images viennent des scènes filmées pour le site et le teaser (terricom/scripts/teaser/scenes-site.mjs et
scenes.mjs) : l'application réelle, pilotée image
par image, avec un curseur. Chaque scène devient une boucle sans à-coup (fondu enchaîné de la fin vers le début),
en MP4 H.264 léger (et WebM VP9 en secours), avec une image d'attente en WebP.

Usage : TEASER_DIR=… FFMPEG=… python3 outils/animations.py [scène …]   (Pillow requis)
"""
import json
import os
import shutil
import subprocess
import sys
import tempfile

from PIL import Image

ICI = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
CLIPS = os.path.join(os.environ.get('TEASER_DIR', os.path.join(ICI, '..', 'terricom', '.teaser')), 'clips')
VID = os.path.join(ICI, 'public', 'assets', 'video', 'app')
IMG = os.path.join(ICI, 'public', 'assets', 'img', 'app')
FF = os.environ.get('FFMPEG', 'ffmpeg')
FONDU = 15  # images (0,5 s)

# scène : (largeur de sortie, image d'attente en part de la durée, première image, dernière image)
SCENES = {
    'site-recherche': (1280, 0.55, 0, None),
    'site-tableau': (1280, 0.1, 0, None),
    'site-stats': (1280, 0.1, 0, None),
    'site-sirene': (1280, 0.45, 0, None),
    'site-newsletter': (1280, 0.1, 0, None),
    'site-mairie': (1280, 0.1, 0, None),
    'site-commune': (1280, 0.05, 0, None),
    'site-fiche': (1280, 0.4, 0, None),
    'site-pro': (1280, 0.6, 0, None),
    'bo-ia': (1280, 0.62, 0, None),
    'mobile': (540, 0.1, 0, 216),
}


def boucle(nom, largeur, attente, debut, fin):
    src = os.path.join(CLIPS, nom)
    imgs = sorted(f for f in os.listdir(src) if f.endswith('.jpg'))[debut:fin]
    n = len(imgs)
    tmp = tempfile.mkdtemp()
    try:
        # sortie = images[FONDU:], les FONDU dernières fondues vers les FONDU premières : la boucle se referme
        k = 0
        for i in range(FONDU, n):
            dst = os.path.join(tmp, f'f{k:05d}.jpg')
            j = i - (n - FONDU)
            if j >= 0:
                a = Image.open(os.path.join(src, imgs[i])).convert('RGB')
                b = Image.open(os.path.join(src, imgs[j])).convert('RGB')
                Image.blend(a, b, (j + 1) / (FONDU + 1)).save(dst, quality=92)
            else:
                os.symlink(os.path.join(src, imgs[i]), dst)
            k += 1
        os.makedirs(VID, exist_ok=True)
        os.makedirs(IMG, exist_ok=True)
        out = os.path.join(VID, f'{nom}.mp4')
        subprocess.run([FF, '-y', '-loglevel', 'error', '-framerate', '30', '-i', os.path.join(tmp, 'f%05d.jpg'),
                        '-vf', f'scale={largeur}:-2:flags=lanczos', '-c:v', 'libx264', '-preset', 'slow', '-crf', '27',
                        '-profile:v', 'high', '-pix_fmt', 'yuv420p', '-movflags', '+faststart', '-an', out], check=True)
        # WebM (VP9) en secours, pour les navigateurs sans H.264 (certains Chromium libres)
        subprocess.run([FF, '-y', '-loglevel', 'error', '-framerate', '30', '-i', os.path.join(tmp, 'f%05d.jpg'),
                        '-vf', f'scale={largeur}:-2:flags=lanczos', '-c:v', 'libvpx-vp9', '-b:v', '0', '-crf', '40',
                        '-row-mt', '1', '-deadline', 'good', '-cpu-used', '2', '-pix_fmt', 'yuv420p', '-an',
                        out.replace('.mp4', '.webm')], check=True)
        im = Image.open(os.path.join(tmp, f'f{int(k * attente):05d}.jpg')).convert('RGB')
        im = im.resize((largeur, round(im.height * largeur / im.width)), Image.LANCZOS)
        im.save(os.path.join(IMG, f'anim-{nom}.webp'), quality=80, method=6)
        print(f'{nom} : {k / 30:.1f} s, {os.path.getsize(out) / 1e6:.1f} Mo, {im.width}×{im.height}')
        return {'w': im.width, 'h': im.height, 'duree': round(k / 30, 1)}
    finally:
        shutil.rmtree(tmp)


def main():
    voulu = sys.argv[1:] or list(SCENES)
    f = os.path.join(ICI, 'outils', 'animations.json')
    tailles = json.load(open(f)) if os.path.exists(f) else {}
    for nom in voulu:
        tailles[nom] = boucle(nom, *SCENES[nom])
    json.dump(tailles, open(f, 'w'), indent=1, sort_keys=True)


if __name__ == '__main__':
    main()
