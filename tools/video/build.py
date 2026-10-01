# -*- coding: utf-8 -*-
"""Assemble le film iOiO : un clip muet par plan, puis concatenation et musique.

   Le mouvement est amorti : il part vite sur la coupe et ralentit ensuite.
   C'est ce qui fait entendre le temps a l'image, plus que la coupe elle-meme.
"""
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
ECLAIR = 0.09       # duree de l'eclair sur la coupe
VOLET = 0.20        # duree du volet de couleur


def lance(args, etape):
    r = subprocess.run(args, capture_output=True, text=True)
    if r.returncode != 0:
        print('ECHEC', etape, file=sys.stderr)
        print(r.stderr[-3000:], file=sys.stderr)
        sys.exit(1)


def mouvement(ken, n, biais):
    """Expressions zoompan amorties. n = nombre d'images, biais = cadrage vertical."""
    p = 'on/%d' % max(n - 1, 1)
    amorti = '(1-exp(-2.8*(%s)))' % p
    cx = '(iw-iw/zoom)/2'
    cy = '(ih-ih/zoom)*%.2f' % biais
    if ken == 'push':
        return '1.00+0.17*%s' % amorti, cx, cy
    if ken == 'pull':
        return '1.17-0.17*%s' % amorti, cx, cy
    if ken == 'kick':
        return '1.02+0.15*exp(-6.0*(%s))+0.05*(%s)' % (p, p), cx, cy
    lat = '(1-exp(-2.6*(%s)))' % p
    if ken == 'swipe-l':
        return '1.12', '(iw-iw/zoom)*(0.88-0.76*%s)' % lat, cy
    if ken == 'swipe-r':
        return '1.12', '(iw-iw/zoom)*(0.12+0.76*%s)' % lat, cy
    return '1.0', cx, cy


def habillage(s, dur, chaine, src, prochaine):
    """Pose l'eclair et le volet de couleur. src = etiquette du flux courant."""
    i = prochaine
    if s.get('wipe'):
        chaine += (";[%d:v]format=rgba[vol];[%s][vol]overlay="
                   "x='min(1920,1920*pow(max(t,0)/%.3f,0.62))':y=0"
                   ":enable='lt(t,%.3f)'[vw]" % (i, src, VOLET, VOLET + 0.02))
        src, i = 'vw', i + 1
    if s.get('flash'):
        chaine += (";[%s]eq=brightness='if(lt(t,%.3f),0.42*(1-t/%.3f),0)':eval=frame[vf]"
                   % (src, ECLAIR, ECLAIR))
        src = 'vf'
    return chaine + ';[%s]null[v]' % src, i


def entrees_habillage(s, dur):
    if not s.get('wipe'):
        return []
    return ['-f', 'lavfi', '-t', '%.4f' % dur,
            '-i', 'color=c=%s:s=1920x1080:r=%d' % (s['wipe'].replace('#', '0x'), FPS)]


def clip_photo(s, n, dur, sortie):
    z, x, y = mouvement(s.get('ken', 'push'), n, s.get('biais', 0.5))
    chaine = (
        "[0:v]scale=%d:%d:force_original_aspect_ratio=increase:flags=lanczos,"
        "crop=%d:%d,setsar=1,"
        "zoompan=z='%s':x='%s':y='%s':d=1:fps=%d:s=1920x1080,"
        "eq=contrast=1.04:saturation=1.09:brightness=0.016,"
        "vignette=PI/11[bg]" % (TW, TH, TW, TH, z, x, y, FPS)
    )
    entrees = ['-loop', '1', '-framerate', str(FPS), '-t', '%.4f' % dur,
               '-i', MEDIA + s['src']]
    suivant = 1
    src = 'bg'
    if s.get('overlay'):
        # le texte monte d'autant plus vite que le plan est court
        st, fd = min(0.22, dur * 0.10), min(0.45, dur * 0.20)
        entrees += ['-loop', '1', '-framerate', str(FPS), '-t', '%.4f' % dur,
                    '-i', os.path.join(CARDS, s['overlay'] + '.png')]
        chaine += (";[1:v]format=rgba,fade=t=in:st=%.3f:d=%.3f:alpha=1[ov];"
                   "[bg][ov]overlay=0:0:format=auto[vt]" % (st, fd))
        src, suivant = 'vt', 2
    entrees += entrees_habillage(s, dur)
    chaine, _ = habillage(s, dur, chaine, src, suivant)
    lance([FF, '-y', '-hide_banner', '-loglevel', 'error'] + entrees +
          ['-filter_complex', chaine, '-map', '[v]', '-frames:v', str(n),
           '-c:v', 'libx264', '-preset', 'medium', '-crf', '14',
           '-pix_fmt', 'yuv420p', '-r', str(FPS), '-x264-params', 'keyint=%d:scenecut=0' % FPS,
           sortie], s['id'])


def clip_carton(s, n, dur, sortie):
    nom = s['card']
    p = 'on/%d' % max(n - 1, 1)
    if nom.startswith('mot-'):            # entree sechee, sur le temps
        snap = max(int(0.18 * FPS), 1)
        z = ("if(lt(on,%d),1.10-0.10*on/%d,1.0+0.030*(on-%d)/%d)"
             % (snap, snap, snap, max(n - snap - 1, 1)))
    elif nom == 'intro':
        z = "1.06-0.06*(%s)" % p
    elif nom in ('venez', 'place', 'url', 'rupture', 'zero'):
        z = "1.00+0.055*(1-exp(-2.6*(%s)))" % p
    else:
        z = "1.0+0.045*(1-exp(-2.2*(%s)))" % p
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
    chaine += "[bg]"
    entrees = (['-loop', '1', '-framerate', str(FPS), '-t', '%.4f' % dur,
                '-i', os.path.join(CARDS, nom + '.png')] + entrees_habillage(s, dur))
    chaine, _ = habillage(s, dur, chaine, 'bg', 1)
    lance([FF, '-y', '-hide_banner', '-loglevel', 'error'] + entrees +
          ['-filter_complex', chaine, '-map', '[v]', '-frames:v', str(n),
           '-c:v', 'libx264', '-preset', 'medium', '-crf', '14',
           '-pix_fmt', 'yuv420p', '-r', str(FPS), '-x264-params', 'keyint=%d:scenecut=0' % FPS,
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
