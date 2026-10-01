# -*- coding: utf-8 -*-
"""Storyboard du film iOiO, calé sur les onsets de la musique."""

import os
RACINE = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
MEDIA = os.path.join(RACINE, 'public', 'media') + os.sep

# (debut, fin) pris sur les onsets reels du morceau (analyse.json)
SHOTS = [
    # ---------- ACTE I — ouverture ----------
    dict(id='a01', t0=0.00,  t1=3.12,  kind='card', card='intro'),
    dict(id='a02', t0=3.12,  t1=8.10,  kind='card', card='titre'),
    dict(id='a03', t0=8.10,  t1=12.76, kind='photo', src='granvelle-bureau-01.webp',
         ken='in', overlay='l01'),
    dict(id='a04', t0=12.76, t1=16.82, kind='photo', src='granvelle-openspace-03.webp',
         ken='out', overlay='l02'),
    dict(id='a05', t0=16.82, t1=19.84, kind='photo', src='carnot-accueil.webp',
         ken='in', overlay='l03'),

    # ---------- ACTE II — Carnot ----------
    dict(id='b01', t0=19.84, t1=24.94, kind='card', card='chap-carnot'),
    dict(id='b02', t0=24.94, t1=29.20, kind='photo', src='carnot-bureau-02.webp',
         ken='in',  overlay='t01'),
    dict(id='b03', t0=29.20, t1=33.26, kind='photo', src='carnot-salle-reunion.webp',
         ken='left', overlay='t02'),
    dict(id='b04', t0=33.26, t1=37.28, kind='photo', src='carnot-cuisine.webp',
         ken='out', overlay='t03'),
    dict(id='b05', t0=37.28, t1=41.24, kind='photo', src='carnot-couloir.webp',
         ken='right', overlay='t04'),
    dict(id='b06', t0=41.24, t1=45.44, kind='photo', src='carnot-bureau-01.webp',
         ken='in',  overlay='t05'),

    # ---------- ACTE III — Granvelle ----------
    dict(id='c01', t0=45.44, t1=50.70, kind='card', card='chap-granvelle'),
    dict(id='c02', t0=50.70, t1=55.82, kind='photo', src='granvelle-openspace-01.webp',
         ken='in',  overlay='t06'),
    dict(id='c03', t0=55.82, t1=60.24, kind='photo', src='granvelle-bureau-03.webp',
         ken='out', overlay='t07'),
    dict(id='c04', t0=60.24, t1=64.20, kind='photo', src='granvelle-coin-detente.webp',
         ken='left', overlay='t08'),
    dict(id='c05', t0=64.20, t1=69.08, kind='photo', src='granvelle-openspace-mezzanine.webp',
         ken='in',  overlay='t09'),

    # ---------- ACTE IV — apogée, montage sur les temps ----------
    dict(id='d01', t0=69.08, t1=71.54, kind='card', card='mot-charges'),
    dict(id='d02', t0=71.54, t1=73.80, kind='photo', src='granvelle-openspace-04.webp', ken='in'),
    dict(id='d03', t0=73.80, t1=76.24, kind='card', card='mot-internet'),
    dict(id='d04', t0=76.24, t1=78.82, kind='photo', src='granvelle-cuisine.webp', ken='out'),
    dict(id='d05', t0=78.82, t1=81.14, kind='card', card='mot-menage'),
    dict(id='d06', t0=81.14, t1=83.68, kind='photo', src='granvelle-openspace-06.webp', ken='left'),
    dict(id='d07', t0=83.68, t1=86.16, kind='card', card='mot-acces'),
    dict(id='d08', t0=86.16, t1=88.52, kind='photo', src='granvelle-bureau-03b.webp', ken='in'),
    dict(id='d09', t0=88.52, t1=91.38, kind='card', card='mot-humeur'),

    # ---------- ACTE V — la rupture ----------
    dict(id='e01', t0=91.38, t1=93.12, kind='card', card='rupture', dip=True),

    # ---------- ACTE VI — les chiffres ----------
    dict(id='f01', t0=93.12, t1=96.66, kind='photo', src='granvelle-facade.webp',
         ken='out', overlay='n01'),
    dict(id='f02', t0=96.66, t1=99.70, kind='photo', src='granvelle-openspace-07.webp',
         ken='in',  overlay='n02'),
    dict(id='f03', t0=99.70, t1=102.66, kind='photo', src='granvelle-openspace-02.webp',
         ken='right', overlay='n03'),
    dict(id='f04', t0=102.66, t1=104.36, kind='card', card='zero'),

    # ---------- ACTE VII — les trois coups ----------
    dict(id='g01', t0=104.36, t1=106.22, kind='card', card='venez'),
    dict(id='g02', t0=106.22, t1=108.46, kind='card', card='place'),
    dict(id='g03', t0=108.46, t1=110.64, kind='card', card='url'),

    # ---------- ACTE VIII — carton de fin ----------
    dict(id='h01', t0=110.64, t1=115.12, kind='card', card='fin'),
]

DUREE = 115.12
FPS = 30
