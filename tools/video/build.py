# -*- coding: utf-8 -*-
"""Assemble le film iOiO : un clip muet par plan, puis concatenation et musique."""
import os, subprocess, sys, json
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from storyboard import SHOTS, FPS, DUREE, MEDIA

ICI = os.environ.get('SORTIE') or os.path.join(
    os.path.dirname(os.path.abspath(__file__)), 'build')
FF = os.environ.get('FFMPEG', 'ffmpeg')
CLIPS = os.path.join(ICI, 'clips')
CARDS = os.path.join(ICI, 'cards')
os.makedirs(CLIPS, exist_ok=True)
# resolution de travail du Ken Burns : 2x la sortie, pour un mouvement sans saccade
TW, TH = 3840, 2160


def lance(args, etape):
    r = subprocess.run(args, capture_output=True, text=True)
    if r.returncode != 0:
        print('ECHEC', etape, file=sys.stderr)
        print(r.stderr[-3000:], file=sys.stderr)
        sys.exit(1)


def mouvement(ken, n):
    """Expressions zoompan. n = nombre d'images du plan."""
    prog = 'on/%d' % max(n - 1, 1)
    cx, cy = '(iw-iw/zoom)/2', '(ih-ih/zoom)/2'
    if ken == 'in':
        return "1.0+0.12*(%s)" % prog, cx, cy
    if ken == 'out':
        return "1.12-0.12*(%s)" % prog, cx, cy
    if ken == 'left':
        return "1.08", "(iw-iw/zoom)*(0.82-0.64*(%s))" % prog, cy
    if ken == 'right':
        return "1.08", "(iw-iw/zoom)*(0.18+0.64*(%s))" % prog, cy
    return "1.0", cx, cy


def clip_photo(s, n, dur, sortie):
    src = MEDIA + s['src']
    z, x, y = mouvement(s.get('ken', 'in'), n)
    chaine = (
        "[0:v]scale=%d:%d:force_original_aspect_ratio=increase:flags=lanczos,"
        "crop=%d:%d,setsar=1,"
        "zoompan=z='%s':x='%s':y='%s':d=1:fps=%d:s=1920x1080,"
        "eq=contrast=1.04:saturation=1.09:brightness=0.016,"
        "vignette=PI/11[bg]" % (TW, TH, TW, TH, z, x, y, FPS)
    )
    entrees = ['-loop', '1', '-framerate', str(FPS), '-t', '%.4f' % dur, '-i', src]
    if s.get('overlay'):
        entrees += ['-loop', '1', '-framerate', str(FPS), '-t', '%.4f' % dur,
                    '-i', os.path.join(CARDS, s['overlay'] + '.png')]
        chaine += (";[1:v]format=rgba,fade=t=in:st=0.30:d=0.55:alpha=1[ov];"
                   "[bg][ov]overlay=0:0:format=auto[v]")
    else:
        chaine += ";[bg]null[v]"
    lance([FF, '-y', '-hide_banner', '-loglevel', 'error'] + entrees +
          ['-filter_complex', chaine, '-map', '[v]', '-frames:v', str(n),
           '-c:v', 'libx264', '-preset', 'medium', '-crf', '14',
           '-pix_fmt', 'yuv420p', '-r', str(FPS), '-x264-params', 'keyint=30:scenecut=0',
           sortie], s['id'])


def clip_carton(s, n, dur, sortie):
    png = os.path.join(CARDS, s['card'] + '.png')
    nom = s['card']
    prog = 'on/%d' % max(n - 1, 1)
    if nom.startswith('mot-'):            # entree sechee, sur le temps
        snap = max(int(0.22 * FPS), 1)
        z = ("if(lt(on,%d),1.075-0.075*on/%d,1.0+0.018*(on-%d)/%d)"
             % (snap, snap, snap, max(n - snap - 1, 1)))
    elif nom == 'intro':
        z = "1.06-0.06*(%s)" % prog
    else:
        z = "1.0+0.030*(%s)" % prog
    chaine = ("[0:v]scale=%d:%d:flags=lanczos,setsar=1,"
              "zoompan=z='%s':x='(iw-iw/zoom)/2':y='(ih-ih/zoom)/2':"
              "d=1:fps=%d:s=1920x1080" % (TW, TH, z, FPS))
    if nom == 'intro':
        chaine += ",fade=t=in:st=0:d=1.30:color=black"
    if s.get('dip'):
        chaine += (",fade=t=in:st=0:d=0.18:color=black"
                   ",fade=t=out:st=%.3f:d=0.22:color=black" % (dur - 0.22))
    if nom == 'fin':
        chaine += ",fade=t=out:st=%.3f:d=1.70:color=black" % (dur - 1.70)
    chaine += "[v]"
    lance([FF, '-y', '-hide_banner', '-loglevel', 'error',
           '-loop', '1', '-framerate', str(FPS), '-t', '%.4f' % dur, '-i', png,
           '-filter_complex', chaine, '-map', '[v]', '-frames:v', str(n),
           '-c:v', 'libx264', '-preset', 'medium', '-crf', '14',
           '-pix_fmt', 'yuv420p', '-r', str(FPS), '-x264-params', 'keyint=30:scenecut=0',
           sortie], s['id'])


def main():
    total = 0
    liste = []
    for s in SHOTS:
        f0, f1 = round(s['t0'] * FPS), round(s['t1'] * FPS)
        n = f1 - f0
        dur = n / FPS
        total += n
        sortie = os.path.join(CLIPS, s['id'] + '.mp4')
        choix = os.environ.get('PLANS')
        saute = (os.environ.get('SEULES_PHOTOS') and s['kind'] != 'photo') \
            or (choix and s['id'] not in choix.split(','))
        if not saute:
            (clip_photo if s['kind'] == 'photo' else clip_carton)(s, n, dur, sortie)
        liste.append(sortie)
        print('  %s  %6.2f s  %4d img  %s' % (
            s['id'], dur, n, s.get('src') or s.get('card')), flush=True)

    with open(os.path.join(ICI, 'liste.txt'), 'w') as f:
        for c in liste:
            f.write("file '%s'\n" % c)
    print('total : %d images = %.3f s' % (total, total / FPS))
    json.dump({'frames': total, 'secondes': total / FPS},
              open(os.path.join(ICI, 'montage.json'), 'w'))


if __name__ == '__main__':
    main()
