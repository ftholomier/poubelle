# -*- coding: utf-8 -*-
"""Concatene les plans, pose la musique, encode le master."""
import os, subprocess, sys, json
ICI = os.environ.get('SORTIE') or os.path.join(
    os.path.dirname(os.path.abspath(__file__)), 'build')
FF = os.environ.get('FFMPEG', 'ffmpeg')
MUSIQUE = (sys.argv[1] if len(sys.argv) > 1
           else os.environ.get('MUSIQUE')
           or os.path.join(ICI, 'musique.mp3'))
import sys as _s; _s.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from storyboard import FPS
m = json.load(open(os.path.join(ICI, 'montage.json')))
DUR = m['secondes']
FONDU = 1.70                    # le carton de fin part au noir sur la fin


def lance(args, etape):
    r = subprocess.run(args, capture_output=True, text=True)
    if r.returncode != 0:
        print('ECHEC', etape, file=sys.stderr)
        print(r.stderr[-4000:], file=sys.stderr)
        sys.exit(1)
    return r


muet = os.path.join(ICI, 'montage_muet.mp4')
lance([FF, '-y', '-hide_banner', '-loglevel', 'error', '-f', 'concat', '-safe', '0',
       '-i', os.path.join(ICI, 'liste.txt'), '-c', 'copy', muet], 'concat')

final = os.path.join(ICI, 'ioio-le-film.mp4')
lance([FF, '-y', '-hide_banner', '-loglevel', 'error',
       '-i', muet, '-i', MUSIQUE,
       # grain leger : evite le banding sur les aplats encre et donne du corps
       '-filter_complex',
       "[0:v]noise=alls=2:allf=t+u,format=yuv420p[v];"
       "[1:a]volume=-1.5dB,afade=t=in:st=0:d=0.6,afade=t=out:st=%.3f:d=%.2f,"
       "aresample=48000[a]" % (DUR - FONDU, FONDU),
       '-map', '[v]', '-map', '[a]',
       '-c:v', 'libx264', '-preset', 'slow', '-crf', '19',
       '-profile:v', 'high', '-level', '4.1', '-pix_fmt', 'yuv420p',
       '-x264-params', 'keyint=%d:min-keyint=%d' % (2 * FPS, FPS),
       '-c:a', 'aac', '-b:a', '192k', '-ar', '48000', '-ac', '2',
       '-movflags', '+faststart', '-t', '%.4f' % DUR, final], 'encode final')

r = subprocess.run([FF, '-hide_banner', '-i', final], capture_output=True, text=True)
print(r.stderr.strip())
print('\npoids :', round(os.path.getsize(final) / 1048576, 1), 'Mo')
