# -*- coding: utf-8 -*-
"""Conducteur du film iOiO.

   La bande-son est composée par musique.py à 120 BPM pile. Un temps dure donc
   exactement 0,5 s, soit 30 images à 60 i/s : la grille n'est pas mesurée,
   elle est posée. Aucune détection, aucune dérive, aucun arrondi — une coupe
   exprimée en temps tombe sur une frontière d'image exacte.

   Chaque plan vaut un nombre entier de temps, et les actes du film coïncident
   avec les sections de l'arrangement (voir SECTIONS dans musique.py) :
   la montée finit quand l'apogée commence, le silence du morceau tombe sur le
   carton nu, les trois frappes isolées sont sous les trois derniers cartons.

   ken   : le mouvement, qui repart vite à la coupe puis ralentit
           push / pull  zoom avant ou arrière, amorti
           kick         démarre zoomé et se détend d'un coup sur le temps
           swipe-l / -r balayage latéral amorti
   biais : cadrage vertical, posé automatiquement selon le rang de reprise
   flash : éclair sur la coupe
   wipe  : volet de couleur qui découvre le plan
"""
import os

RACINE = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
MEDIA = os.path.join(RACINE, 'public', 'media') + os.sep

BPM = 120.0
TEMPS = 60.0 / BPM          # 0,5 s — exactement 30 images à 60 i/s
MESURE = 4 * TEMPS          # 2 s
FPS = 60
DUREE = 108.0               # 52 mesures + 4 s de résonance

JAUNE, VERT, ENCRE = '#FFD100', '#12B39A', '#0E0E0E'

# (durée en TEMPS, type, contenu, mouvement, incrustation, options)
PLAN = [
    # ---- ACTE I — intro du morceau (mesures 0-3), nappe seule
    (8, 'card', 'intro', None, None, {}),
    (8, 'card', 'titre', None, None, {}),

    # ---- ACTE II — l'ouverture, la batterie entre (mesures 4-11)
    (8, 'photo', 'granvelle-bureau-01', 'push', 'l01', {}),
    (8, 'photo', 'granvelle-openspace-04', 'kick', 'l02', dict(flash=True)),
    (8, 'photo', 'granvelle-openspace-03', 'swipe-r', 'l03', {}),
    (8, 'photo', 'carnot-accueil', 'pull', 'l05', {}),

    # ---- ACTE III — Carnot (mesures 12-19), impact sur l'entrée
    (8, 'card', 'chap-carnot', None, None, dict(wipe=JAUNE)),
    (4, 'photo', 'carnot-bureau-02', 'push', 't01', {}),
    (4, 'photo', 'carnot-bureau-01', 'swipe-l', None, {}),
    (4, 'photo', 'carnot-salle-reunion', 'kick', 't02', dict(flash=True)),
    (4, 'photo', 'carnot-cuisine', 'pull', 't03', {}),
    (4, 'photo', 'carnot-couloir', 'swipe-r', 't04', {}),
    (4, 'photo', 'carnot-bureau-vue', 'kick', 't05', dict(flash=True)),

    # ---- ACTE IV — Granvelle (mesures 20-27), la contre-mélodie entre
    (8, 'card', 'chap-granvelle', None, None, dict(wipe=VERT)),
    (4, 'photo', 'granvelle-openspace-01', 'push', 't06', {}),
    (4, 'photo', 'granvelle-bureau-03', 'swipe-l', None, {}),
    (4, 'photo', 'granvelle-bureau-01', 'kick', 't07', dict(flash=True)),
    (4, 'photo', 'granvelle-coin-detente', 'pull', 't08', {}),
    (4, 'photo', 'granvelle-openspace-02', 'swipe-r', None, {}),
    (4, 'photo', 'granvelle-openspace-mezzanine', 'kick', 't09', dict(flash=True)),

    # ---- ACTE V — la montée (mesures 28-31) : le roulement se resserre,
    #      les plans aussi, jusqu'à une coupe par temps
    (4, 'photo', 'granvelle-facade', 'push', None, {}),
    (4, 'photo', 'carnot-bureau-03', 'pull', None, {}),
    (3, 'photo', 'granvelle-openspace-06', 'kick', None, {}),
    (2, 'photo', 'carnot-bureau-04', 'kick', None, dict(flash=True)),
    (1, 'photo', 'granvelle-bureau-02b', 'kick', None, dict(flash=True)),
    (1, 'photo', 'granvelle-openspace-casiers', 'kick', None, dict(flash=True)),
    (1, 'photo', 'granvelle-cuisine', 'kick', None, dict(flash=True)),

    # ---- ACTE VI — l'apogée (mesures 32-41) : un mot, deux images,
    #      une coupe tous les deux temps
    (2, 'card', 'mot-charges', None, None, dict(wipe=ENCRE, flash=True)),
    (2, 'photo', 'granvelle-openspace-05', 'kick', None, {}),
    (2, 'photo', 'carnot-bureau-02', 'push', None, {}),
    (2, 'card', 'mot-internet', None, None, dict(flash=True)),
    (2, 'photo', 'granvelle-openspace-07', 'kick', None, {}),
    (2, 'photo', 'granvelle-cuisine', 'swipe-l', None, {}),
    (2, 'card', 'mot-menage', None, None, dict(flash=True)),
    (2, 'photo', 'granvelle-openspace-casiers', 'kick', None, {}),
    (2, 'photo', 'carnot-cuisine', 'pull', None, {}),
    (2, 'card', 'mot-reunion', None, None, dict(flash=True)),
    (2, 'photo', 'granvelle-bureau-02', 'kick', None, {}),
    (2, 'photo', 'carnot-salle-reunion', 'swipe-r', None, {}),
    (2, 'card', 'mot-mobilier', None, None, dict(flash=True)),
    (2, 'photo', 'granvelle-bureau-02b', 'kick', None, {}),
    (2, 'photo', 'granvelle-openspace-03', 'push', None, {}),
    (2, 'card', 'mot-acces', None, None, dict(flash=True)),
    (2, 'photo', 'granvelle-openspace-06', 'kick', None, {}),
    (2, 'photo', 'carnot-bureau-01', 'pull', None, {}),
    (2, 'card', 'mot-humeur', None, None, dict(wipe=ENCRE, flash=True)),
    (2, 'photo', 'granvelle-bureau-03b', 'kick', None, {}),

    # ---- ACTE VII — la rupture (mesures 42-43) : le morceau se tait
    (8, 'card', 'rupture', None, None, dict(dip=True)),

    # ---- ACTE VIII — les chiffres (mesures 44-47), le groove revient
    (4, 'photo', 'granvelle-facade', 'pull', 'n01', dict(wipe=JAUNE)),
    (4, 'photo', 'granvelle-openspace-04', 'push', 'n02', {}),
    (4, 'photo', 'granvelle-bureau-01', 'kick', 'n03', dict(flash=True)),
    (4, 'card', 'zero', None, None, {}),

    # ---- ACTE IX — les trois frappes isolées (mesures 48, 49, 50)
    (4, 'card', 'venez', None, None, dict(flash=True)),
    (4, 'card', 'place', None, None, dict(flash=True)),
    (4, 'card', 'url', None, None, dict(flash=True)),

    # ---- ACTE X — accord final et résonance (mesure 51 + queue)
    (12, 'card', 'fin', None, None, dict(flash=True)),
]

BIAIS = (0.50, 0.30, 0.70, 0.40)
_prefixes = {'card': 'c', 'photo': 'p'}


def _construit():
    shots, k, vus, num = [], 0, {}, 0
    for duree, genre, contenu, ken, ov, opts in PLAN:
        num += 1
        s = dict(id='%s%02d' % (_prefixes[genre], num), kind=genre,
                 t0=round(k * TEMPS, 6), t1=round((k + duree) * TEMPS, 6),
                 k0=k, ku=duree)
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
    shots[-1]['t1'] = DUREE          # le dernier plan tient jusqu'au bout
    return shots


SHOTS = _construit()
