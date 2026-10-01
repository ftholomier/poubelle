# -*- coding: utf-8 -*-
"""Conducteur du film iOiO.

   Tout est pose sur la grille metrique du morceau, mesuree par
   analyse_musique.py : croche U = 0,272736 s (109,996 BPM), phase 0,0432 s.
   Une coupe n'est jamais donnee en secondes mais en nombre de croches : elle
   ne peut donc pas tomber a cote du temps. La densite se resserre acte apres
   acte — 8 croches a l'ouverture (une mesure), 4 a l'apogee, 2 (un temps
   plein) sur la rafale finale.

   ken   : le mouvement, qui repart vite a la coupe puis ralentit
           push / pull  zoom avant ou arriere, amorti
           kick         demarre zoome et se detend d'un coup sur le temps
           swipe-l / -r balayage lateral amorti
   flash : eclair sur la coupe
   wipe  : volet de couleur qui decouvre le plan
   biais : cadrage vertical, pose automatiquement selon le rang de reprise
"""
import os

RACINE = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
MEDIA = os.path.join(RACINE, 'public', 'media') + os.sep

U = 0.272736            # la croche, mesuree par analyse_musique.py
PHASE = 0.043200        # premier temps du morceau
FPS = 60
DUREE = 115.13333       # 6908 images

JAUNE, VERT = '#FFD100', '#12B39A'


def t(k):
    """Instant de la k-ieme croche."""
    return PHASE + k * U


# ---------------------------------------------------------------- conducteur
# (duree en croches, type, contenu, mouvement, incrustation, options)
PLAN = [
    # ---- ACTE I : ouverture. Le film part a 0, la grille le rattrape a k=12.
    (12, 'card', 'intro', None, None, {}),
    (12, 'card', 'titre', None, None, {}),
    (8, 'photo', 'granvelle-bureau-01', 'push', 'l01', {}),
    (8, 'photo', 'granvelle-openspace-04', 'kick', 'l02', dict(flash=True)),
    (8, 'photo', 'granvelle-openspace-03', 'swipe-r', 'l03', {}),
    (8, 'photo', 'granvelle-facade', 'pull', 'l04', {}),
    (8, 'photo', 'carnot-accueil', 'kick', 'l05', dict(flash=True)),
    (8, 'photo', 'granvelle-coin-detente', 'push', None, {}),

    # ---- ACTE II : Carnot, une mesure par plan
    (12, 'card', 'chap-carnot', None, None, dict(wipe=JAUNE)),
    (8, 'photo', 'carnot-bureau-02', 'push', 't01', {}),
    (8, 'photo', 'carnot-bureau-01', 'swipe-l', None, {}),
    (8, 'photo', 'carnot-salle-reunion', 'kick', 't02', dict(flash=True)),
    (8, 'photo', 'carnot-bureau-03', 'pull', None, {}),
    (8, 'photo', 'carnot-cuisine', 'push', 't03', {}),
    (8, 'photo', 'carnot-bureau-04', 'swipe-r', None, {}),
    (8, 'photo', 'carnot-couloir', 'kick', 't04', dict(flash=True)),
    (8, 'photo', 'carnot-accueil', 'pull', None, {}),
    (8, 'photo', 'carnot-bureau-vue', 'push', 't05', {}),
    (8, 'photo', 'carnot-salle-reunion', 'swipe-l', None, {}),

    # ---- ACTE III : Granvelle
    (12, 'card', 'chap-granvelle', None, None, dict(wipe=VERT)),
    (8, 'photo', 'granvelle-openspace-01', 'push', 't06', {}),
    (8, 'photo', 'granvelle-openspace-04', 'swipe-l', None, {}),
    (8, 'photo', 'granvelle-bureau-01', 'kick', 't07', dict(flash=True)),
    (8, 'photo', 'granvelle-bureau-03', 'pull', None, {}),
    (8, 'photo', 'granvelle-coin-detente', 'push', 't08', {}),
    (8, 'photo', 'granvelle-openspace-02', 'swipe-r', None, {}),
    (8, 'photo', 'granvelle-openspace-mezzanine', 'kick', 't09', dict(flash=True)),
    (8, 'photo', 'granvelle-bureau-03b', 'pull', None, {}),
    (8, 'photo', 'granvelle-openspace-06', 'push', None, {}),

    # ---- ACTE IV : apogee, un mot puis deux images, toutes les 4 croches
    (4, 'card', 'mot-charges', None, None, dict(wipe=JAUNE)),
    (4, 'photo', 'granvelle-openspace-05', 'kick', None, dict(flash=True)),
    (4, 'photo', 'carnot-bureau-02', 'push', None, {}),
    (4, 'card', 'mot-internet', None, None, dict(flash=True)),
    (4, 'photo', 'granvelle-openspace-07', 'kick', None, {}),
    (4, 'photo', 'granvelle-cuisine', 'swipe-l', None, {}),
    (4, 'card', 'mot-menage', None, None, dict(flash=True)),
    (4, 'photo', 'granvelle-openspace-casiers', 'kick', None, {}),
    (4, 'photo', 'carnot-cuisine', 'pull', None, {}),
    (4, 'card', 'mot-reunion', None, None, dict(flash=True)),
    (4, 'photo', 'granvelle-bureau-02', 'kick', None, {}),
    (4, 'photo', 'carnot-salle-reunion', 'swipe-r', None, {}),
    (4, 'card', 'mot-mobilier', None, None, dict(flash=True)),
    (4, 'photo', 'granvelle-bureau-02b', 'kick', None, {}),
    (4, 'photo', 'granvelle-openspace-03', 'push', None, {}),
    (4, 'card', 'mot-acces', None, None, dict(flash=True)),
    (4, 'photo', 'granvelle-openspace-06', 'kick', None, {}),
    (4, 'photo', 'carnot-bureau-01', 'pull', None, {}),

    # ---- rafale finale : une coupe par temps (2 croches)
    (2, 'photo', 'granvelle-facade', 'kick', None, dict(flash=True)),
    (2, 'photo', 'carnot-couloir', 'kick', None, dict(flash=True)),
    (2, 'photo', 'granvelle-openspace-02', 'kick', None, dict(flash=True)),
    (2, 'photo', 'granvelle-bureau-03', 'kick', None, dict(flash=True)),
    (2, 'photo', 'granvelle-openspace-mezzanine', 'kick', None, dict(flash=True)),
    (2, 'photo', 'granvelle-openspace-01', 'kick', None, dict(flash=True)),
    (3, 'card', 'mot-humeur', None, None, dict(flash=True, wipe=JAUNE)),

    # ---- ACTE V : la rupture, dans le trou du morceau
    (7, 'card', 'rupture', None, None, dict(dip=True)),

    # ---- ACTE VI : les chiffres
    (8, 'photo', 'granvelle-facade', 'pull', 'n01', dict(wipe=JAUNE)),
    (8, 'photo', 'granvelle-openspace-04', 'push', 'n02', {}),
    (8, 'photo', 'granvelle-bureau-01', 'kick', 'n03', dict(flash=True)),
    (8, 'photo', 'carnot-bureau-vue', 'swipe-l', None, {}),
    (8, 'card', 'zero', None, None, {}),

    # ---- ACTE VII : les trois coups, sur les frappes isolees k=382/390/398
    (8, 'card', 'venez', None, None, dict(flash=True)),
    (8, 'card', 'place', None, None, dict(flash=True)),
    (8, 'card', 'url', None, None, dict(flash=True)),

    # ---- ACTE VIII : carton de fin
    (16, 'card', 'fin', None, None, {}),
]

# ------------------------------------------------- construction des plans
BIAIS = (0.50, 0.30, 0.70, 0.40)       # cadrage selon le rang de reprise
_prefixes = {'card': 'c', 'photo': 'p'}


def _construit():
    shots, k, vus, num = [], 0, {}, 0
    for duree, genre, contenu, ken, ov, opts in PLAN:
        num += 1
        t0 = 0.0 if k == 0 else t(k)
        t1 = t(k + duree)
        s = dict(id='%s%02d' % (_prefixes[genre], num), kind=genre,
                 t0=round(t0, 6), t1=round(t1, 6), k0=k, ku=duree)
        if genre == 'photo':
            rang = vus.get(contenu, 0)
            vus[contenu] = rang + 1
            s.update(src=contenu + '.webp', ken=ken or 'push',
                     biais=BIAIS[rang % len(BIAIS)])
            if ov:
                s['overlay'] = ov
        else:
            s['card'] = contenu
        s.update(opts)
        shots.append(s)
        k += duree
    # le dernier plan va jusqu'au bout du morceau
    shots[-1]['t1'] = DUREE
    return shots


SHOTS = _construit()
