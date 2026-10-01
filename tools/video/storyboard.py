# -*- coding: utf-8 -*-
"""Conducteur du film iOiO. Toutes les bornes sont des attaques reelles du
   morceau (voir analyse_musique.py). Le rythme se resserre acte apres acte :
   2,6 s a l'ouverture, 2,45 s dans les deux lieux, 1,4 s a l'apogee.

   ken   : le mouvement, qui repart vite a chaque coupe puis ralentit
           push / pull  zoom avant ou arriere, amorti
           kick         demarre zoome et se detend d'un coup sur le temps
           swipe-l / -r balayage lateral amorti
   biais : position verticale du cadrage (0,5 = centre), pour varier un plan repris
   flash : eclair de deux images sur la coupe
   wipe  : volet de couleur qui decouvre le plan en quatre images
"""
import os

RACINE = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
MEDIA = os.path.join(RACINE, 'public', 'media') + os.sep

JAUNE, VERT = '#FFD100', '#12B39A'


def ph(i, t0, t1, src, ken, ov=None, **kw):
    d = dict(id=i, t0=t0, t1=t1, kind='photo', src=src + '.webp', ken=ken)
    if ov:
        d['overlay'] = ov
    d.update(kw)
    return d


def ca(i, t0, t1, carton, **kw):
    d = dict(id=i, t0=t0, t1=t1, kind='card', card=carton)
    d.update(kw)
    return d


SHOTS = [
    # ---------- ACTE I — ouverture (une ligne de texte par plan) ----------
    ca('a01', 0.00, 3.12, 'intro'),
    ca('a02', 3.12, 7.00, 'titre'),
    ph('a03', 7.00, 9.60, 'granvelle-bureau-01', 'push', 'l01'),
    ph('a04', 9.60, 12.12, 'granvelle-openspace-04', 'kick', 'l02', flash=True),
    ph('a05', 12.12, 14.82, 'granvelle-openspace-03', 'swipe-r', 'l03'),
    ph('a06', 14.82, 17.40, 'granvelle-facade', 'pull', 'l04'),
    ph('a07', 17.40, 19.84, 'carnot-accueil', 'kick', 'l05', flash=True),

    # ---------- ACTE II — Carnot ----------
    ca('b01', 19.84, 23.50, 'chap-carnot', wipe=JAUNE),
    ph('b02', 23.50, 25.98, 'carnot-bureau-02', 'push', 't01'),
    ph('b03', 25.98, 28.56, 'carnot-bureau-01', 'swipe-l'),
    ph('b04', 28.56, 31.04, 'carnot-salle-reunion', 'kick', 't02', flash=True),
    ph('b05', 31.04, 33.52, 'carnot-bureau-03', 'pull'),
    ph('b06', 33.52, 35.94, 'carnot-cuisine', 'push', 't03'),
    ph('b07', 35.94, 38.50, 'carnot-bureau-04', 'swipe-r'),
    ph('b08', 38.50, 41.24, 'carnot-couloir', 'kick', 't04', flash=True),
    ph('b09', 41.24, 43.52, 'carnot-accueil', 'push', biais=0.28),
    ph('b10', 43.52, 45.44, 'carnot-bureau-vue', 'pull', 't05'),

    # ---------- ACTE III — Granvelle ----------
    ca('c01', 45.44, 49.00, 'chap-granvelle', wipe=VERT),
    ph('c02', 49.00, 51.56, 'granvelle-openspace-01', 'push', 't06'),
    ph('c03', 51.56, 54.06, 'granvelle-openspace-04', 'swipe-l', biais=0.30),
    ph('c04', 54.06, 56.46, 'granvelle-bureau-01', 'kick', 't07', flash=True),
    ph('c05', 56.46, 58.96, 'granvelle-bureau-03', 'pull'),
    ph('c06', 58.96, 61.56, 'granvelle-coin-detente', 'push', 't08'),
    ph('c07', 61.56, 64.20, 'granvelle-openspace-02', 'swipe-r'),
    ph('c08', 64.20, 66.70, 'granvelle-openspace-mezzanine', 'kick', 't09', flash=True),
    ph('c09', 66.70, 69.08, 'granvelle-bureau-03b', 'pull'),

    # ---------- ACTE IV — apogee : une coupe toutes les 1,4 s ----------
    ca('d01', 69.08, 70.34, 'mot-charges', wipe=JAUNE),
    ph('d02', 70.34, 71.82, 'granvelle-openspace-05', 'kick', flash=True),
    ph('d03', 71.82, 73.18, 'carnot-bureau-02', 'push', biais=0.70),
    ca('d04', 73.18, 74.44, 'mot-internet', flash=True),
    ph('d05', 74.44, 75.92, 'granvelle-openspace-06', 'kick'),
    ph('d06', 75.92, 77.22, 'granvelle-cuisine', 'swipe-l'),
    ca('d07', 77.22, 78.82, 'mot-menage', flash=True),
    ph('d08', 78.82, 80.20, 'granvelle-openspace-07', 'kick'),
    ph('d09', 80.20, 81.74, 'carnot-salle-reunion', 'pull', biais=0.30),
    ca('d10', 81.74, 83.28, 'mot-reunion', flash=True),
    ph('d11', 83.28, 84.64, 'granvelle-openspace-casiers', 'kick'),
    ph('d12', 84.64, 86.16, 'granvelle-bureau-02b', 'swipe-r'),
    ca('d13', 86.16, 87.62, 'mot-acces', flash=True),
    ph('d14', 87.62, 88.92, 'granvelle-openspace-03', 'kick', biais=0.70),
    ph('d15', 88.92, 90.36, 'granvelle-bureau-02', 'push', biais=0.38),
    ca('d16', 90.36, 91.38, 'mot-humeur', flash=True),

    # ---------- ACTE V — la rupture, dans le trou du morceau ----------
    ca('e01', 91.38, 93.12, 'rupture', dip=True),

    # ---------- ACTE VI — les chiffres ----------
    ph('f01', 93.12, 95.32, 'granvelle-facade', 'pull', 'n01', wipe=JAUNE, biais=0.34),
    ph('f02', 95.32, 97.60, 'granvelle-openspace-04', 'push', 'n02', biais=0.72),
    ph('f03', 97.60, 99.70, 'granvelle-bureau-01', 'kick', 'n03', flash=True, biais=0.30),
    ph('f04', 99.70, 102.08, 'carnot-salle-reunion', 'swipe-l', biais=0.70),
    ca('f05', 102.08, 104.36, 'zero'),

    # ---------- ACTE VII — les trois coups espaces ----------
    ca('g01', 104.36, 106.22, 'venez', flash=True),
    ca('g02', 106.22, 108.46, 'place', flash=True),
    ca('g03', 108.46, 110.64, 'url', flash=True),

    # ---------- ACTE VIII — carton de fin ----------
    ca('h01', 110.64, 115.12, 'fin'),
]

DUREE = 115.12
FPS = 30
