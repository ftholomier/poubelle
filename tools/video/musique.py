# -*- coding: utf-8 -*-
"""Compose la bande-son du film : corporate, dynamique, 120 BPM.

   Pourquoi la composer plutôt que d'en prendre une : à 120 BPM un temps dure
   exactement 0,5 s, soit 30 images à 60 i/s. La grille n'est plus mesurée,
   elle est posée. Aucune détection, aucune dérive, aucune erreur de phase —
   et l'arrangement peut être écrit POUR le montage : la montée finit quand le
   chapitre commence, le silence tombe sur le carton nu, les trois frappes
   finales sont sous les trois derniers cartons.

   Tout est synthétisé ici : la bande-son est originale, sans licence à gérer.

   Usage : python3 musique.py [sortie.wav]
"""
import os, struct, sys, wave
import numpy as np
from scipy import signal

SR = 48000
BPM = 120.0
TEMPS = 60.0 / BPM          # 0,5 s
MESURE = 4 * TEMPS          # 2 s
MESURES = 52                # 104 s d'arrangement
QUEUE = 4.0                 # résonance finale
DUREE = MESURES * MESURE + QUEUE

rng = np.random.default_rng(20261001)


def n(t):
    return int(round(t * SR))


# ------------------------------------------------------------------ outils
def env(longueur, a, d, s, r, niveau=1.0):
    """Enveloppe ADSR en échantillons."""
    e = np.zeros(longueur)
    na, nd, nr = n(a), n(d), n(r)
    ns = max(longueur - na - nd - nr, 0)
    i = 0
    if na:
        e[:na] = np.linspace(0, 1, na); i = na
    if nd:
        e[i:i + nd] = np.linspace(1, s, nd); i += nd
    if ns:
        e[i:i + ns] = s; i += ns
    if nr and i < longueur:
        fin = min(longueur, i + nr)
        e[i:fin] = np.linspace(s, 0, fin - i)
    return e * niveau


def expdec(longueur, tau):
    return np.exp(-np.arange(longueur) / (tau * SR))


def saw(f, longueur, detune=0.0, phase=0.0):
    t = np.arange(longueur) / SR
    if np.isscalar(f):
        ph = 2 * np.pi * f * t + phase
    else:
        ph = 2 * np.pi * np.cumsum(f) / SR + phase
    s = 2 * (ph / (2 * np.pi) % 1.0) - 1.0
    if detune:
        s = s + (2 * (((1 + detune) * ph) / (2 * np.pi) % 1.0) - 1.0)
        s = s + (2 * (((1 - detune) * ph) / (2 * np.pi) % 1.0) - 1.0)
        s /= 3.0
    return s


def sine(f, longueur, phase=0.0):
    t = np.arange(longueur) / SR
    if np.isscalar(f):
        return np.sin(2 * np.pi * f * t + phase)
    return np.sin(2 * np.pi * np.cumsum(f) / SR + phase)


def bruit(longueur):
    return rng.standard_normal(longueur)


def passebas(x, fc, q=0.707):
    """Lowpass biquad. fc scalaire, ou tableau pour un balayage (par blocs)."""
    if np.isscalar(fc):
        b, a = signal.butter(2, min(fc, SR * 0.45) / (SR / 2), 'low')
        return signal.lfilter(b, a, x)
    bloc = 512
    y = np.zeros(len(x))
    zi = None
    for d in range(0, len(x), bloc):
        f = float(np.clip(fc[min(d, len(fc) - 1)], 30, SR * 0.45))
        b, a = signal.butter(2, f / (SR / 2), 'low')
        if zi is None:
            zi = signal.lfilter_zi(b, a) * x[0]
        seg = x[d:d + bloc]
        y[d:d + len(seg)], zi = signal.lfilter(b, a, seg, zi=zi[:max(len(a), len(b)) - 1])
    return y


def passehaut(x, fc):
    b, a = signal.butter(2, max(fc, 20) / (SR / 2), 'high')
    return signal.lfilter(b, a, x)


def passebande(x, f1, f2):
    b, a = signal.butter(2, [max(f1, 20) / (SR / 2), min(f2, SR * 0.45) / (SR / 2)], 'band')
    return signal.lfilter(b, a, x)


# ------------------------------------------------------------- instruments
def grosse_caisse(force=1.0):
    L = n(0.42)
    f = 52 * np.exp(-np.arange(L) / (0.035 * SR)) + 44
    corps = sine(f, L) * expdec(L, 0.13)
    clic = passehaut(bruit(n(0.012)), 1200) * expdec(n(0.012), 0.004) * 0.5
    s = corps
    s[:len(clic)] += clic
    return np.tanh(s * 1.7) * 0.9 * force


def caisse_claire(force=1.0):
    L = n(0.30)
    corps = (sine(195, L) + sine(278, L)) * expdec(L, 0.045) * 0.35
    souffle = passebande(bruit(L), 1400, 7500) * expdec(L, 0.085)
    return (corps + souffle * 0.75) * 0.8 * force


def clap(force=1.0):
    L = n(0.34)
    s = np.zeros(L)
    for k, dec in enumerate([0.0, 0.011, 0.023]):
        d = n(dec)
        bout = passebande(bruit(L - d), 1100, 6000) * expdec(L - d, 0.012)
        s[d:] += bout * (0.7 ** k)
    queue = passebande(bruit(L), 1000, 5200) * expdec(L, 0.10) * 0.45
    return (s + queue) * 0.7 * force


def charley(ouvert=False, force=1.0):
    L = n(0.16 if ouvert else 0.05)
    s = passehaut(bruit(L), 7000) * expdec(L, 0.075 if ouvert else 0.016)
    return s * (0.30 if ouvert else 0.22) * force


def basse(freq, duree, force=1.0):
    L = n(duree)
    s = saw(freq, L, detune=0.004) * 0.6 + sine(freq, L) * 0.6
    fc = 150 + 500 * np.exp(-np.arange(L) / (0.12 * SR))
    s = passebas(s, fc, q=1.1)
    return s * env(L, 0.004, 0.05, 0.80, 0.06) * 0.55 * force


def pluck(freq, duree, force=1.0, brillance=2600):
    L = n(duree)
    s = saw(freq, L, detune=0.010) * 0.5 + sine(freq * 2, L) * 0.18
    fc = brillance * np.exp(-np.arange(L) / (0.10 * SR)) + 420
    s = passebas(s, fc, q=0.9)
    return s * env(L, 0.003, 0.09, 0.28, duree * 0.55) * 0.34 * force


def nappe(freqs, duree, force=1.0, coupe=2200):
    L = n(duree)
    s = np.zeros(L)
    for f in freqs:
        s += saw(f, L, detune=0.006) + saw(f * 2.0, L, detune=0.004) * 0.28
    s = passebas(s / max(len(freqs), 1), coupe, q=0.7)
    a = min(0.5, duree * 0.25)
    return s * env(L, a, 0.2, 0.85, min(0.9, duree * 0.4)) * 0.18 * force


def cuivre(freqs, duree, force=1.0):
    """Nappe de cuivres pour les frappes de fin : attaque nette, corps riche."""
    L = n(duree)
    s = np.zeros(L)
    for f in freqs:
        s += saw(f, L, detune=0.012) + saw(f * 0.5, L, detune=0.008) * 0.6
    fc = 5200 * np.exp(-np.arange(L) / (0.35 * SR)) + 700
    s = passebas(s / max(len(freqs), 1), fc, q=0.8)
    return s * env(L, 0.012, 0.25, 0.55, duree * 0.5) * 0.30 * force


def montee(duree, force=1.0):
    """Riser : bruit filtré qui monte + sirène."""
    L = n(duree)
    fc = np.geomspace(300, 11000, L)
    s = passebas(bruit(L), fc, q=1.4) * 0.5
    f = np.geomspace(260, 1900, L)
    s += sine(f, L) * 0.12
    return s * np.linspace(0.05, 1.0, L) ** 2 * 0.5 * force


def impact(force=1.0):
    L = n(2.2)
    sub = sine(np.geomspace(90, 38, L), L) * expdec(L, 0.32)
    corps = passebas(bruit(L), 420) * expdec(L, 0.30) * 0.6
    cymb = passehaut(bruit(L), 4500) * expdec(L, 0.85) * 0.22
    return (sub * 1.1 + corps + cymb) * 0.75 * force


def cymbale_inverse(duree, force=1.0):
    L = n(duree)
    s = passehaut(bruit(L), 3000) * np.linspace(0, 1, L) ** 3
    return s * 0.28 * force


def shaker(force=1.0):
    L = n(0.06)
    return passehaut(bruit(L), 9000) * expdec(L, 0.018) * 0.14 * force


# ---------------------------------------------------------------- harmonie
# Enchaînement « uplifting » classique : Fa – Do – Sol – La mineur.
ACCORDS = [
    dict(nom='F',  basse=87.31,  triade=[174.61, 220.00, 261.63], arpege=[349.23, 440.00, 523.25, 440.00]),
    dict(nom='C',  basse=65.41,  triade=[196.00, 261.63, 329.63], arpege=[392.00, 523.25, 659.25, 523.25]),
    dict(nom='G',  basse=98.00,  triade=[196.00, 246.94, 293.66], arpege=[392.00, 493.88, 587.33, 493.88]),
    dict(nom='Am', basse=110.00, triade=[220.00, 261.63, 329.63], arpege=[440.00, 523.25, 659.25, 523.25]),
]


def accord(mesure):
    return ACCORDS[mesure % 4]


# ------------------------------------------------------------- arrangement
# Chaque section dit ce qui joue. Les bornes sont des numéros de mesure, et
# elles correspondent une à une aux actes du film.
# « niveau » est le gain de la section : c'est lui qui fait l'arc du morceau.
# Sans lui, toutes les sections sortent au même volume et le film n'a pas de
# montée — c'est ce qui manquait à la première version.
SECTIONS = [
    dict(nom='intro',     de=0,  a=4,  niveau=0.48, kick='un',    claire=False, charley=0,  basse=False, pluck=0.0, nappe=0.55, contre=False),
    dict(nom='ouverture', de=4,  a=12, niveau=0.66, kick='quatre', claire=False, charley=8,  basse=True,  pluck=0.5, nappe=0.70, contre=False),
    dict(nom='carnot',    de=12, a=20, niveau=0.78, kick='quatre', claire=True,  charley=8,  basse=True,  pluck=0.9, nappe=0.85, contre=False),
    dict(nom='granvelle', de=20, a=28, niveau=0.88, kick='quatre', claire=True,  charley=16, basse=True,  pluck=1.0, nappe=0.95, contre=True),
    dict(nom='montee',    de=28, a=32, niveau=0.94, kick='quatre', claire='roulement', charley=16, basse=True, pluck=0.8, nappe=1.0, contre=False),
    dict(nom='apogee',    de=32, a=42, niveau=1.00, kick='quatre', claire=True,  charley=16, basse=True,  pluck=1.0, nappe=1.0, contre=True),
    dict(nom='rupture',   de=42, a=44, niveau=0.30, kick=None,     claire=False, charley=0,  basse=False, pluck=0.0, nappe=0.4,  contre=False),
    dict(nom='chiffres',  de=44, a=48, niveau=0.84, kick='quatre', claire=True,  charley=8,  basse=True,  pluck=0.8, nappe=0.85, contre=False),
    dict(nom='frappes',   de=48, a=51, niveau=0.97, kick='frappe', claire=False, charley=0,  basse=False, pluck=0.0, nappe=0.5,  contre=False),
    dict(nom='final',     de=51, a=52, niveau=1.00, kick='un',     claire=False, charley=0,  basse=False, pluck=0.0, nappe=1.0,  contre=False),
]


def enveloppe_sections(longueur):
    """Gain des sections, lissé sauf à la rupture où la coupure doit être nette."""
    e = np.zeros(longueur)
    for s in SECTIONS:
        a, b = n(s['de'] * MESURE), min(n(s['a'] * MESURE), longueur)
        e[a:b] = s['niveau']
    e[n(SECTIONS[-1]['a'] * MESURE):] = SECTIONS[-1]['niveau']
    # lissage de 0,6 s, sauf sur l'entrée et la sortie de la rupture
    k = n(0.6)
    lisse = np.convolve(e, np.ones(k) / k, mode='same')
    for t in (42 * MESURE, 44 * MESURE):
        d, f = n(t - 0.35), n(t + 0.35)
        lisse[d:f] = e[d:f]
    return lisse


def section(mesure):
    for s in SECTIONS:
        if s['de'] <= mesure < s['a']:
            return s
    return SECTIONS[-1]


def compose():
    total = n(DUREE)
    pistes = {k: np.zeros(total) for k in
              ('kick', 'claire', 'charley', 'basse', 'pluck', 'nappe', 'cuivre', 'effets')}

    def pose(piste, t, son, gain=1.0):
        d = n(t)
        f = min(d + len(son), total)
        if f > d:
            pistes[piste][d:f] += son[:f - d] * gain

    temps_kick = []

    for m in range(MESURES):
        t0 = m * MESURE
        sec = section(m)
        ac = accord(m)
        prochaine = section(m + 1)['nom'] if m + 1 < MESURES else ''

        # --- grosse caisse
        if sec['kick'] == 'quatre':
            for b in range(4):
                # le dernier temps avant la rupture et avant les frappes saute
                if prochaine in ('rupture',) and m + 1 == sec['a'] and b == 3:
                    continue
                pose('kick', t0 + b * TEMPS, grosse_caisse())
                temps_kick.append(t0 + b * TEMPS)
        elif sec['kick'] == 'un':
            pose('kick', t0, grosse_caisse(0.85))
            temps_kick.append(t0)
        elif sec['kick'] == 'frappe':
            pose('kick', t0, grosse_caisse(1.0))
            temps_kick.append(t0)

        # --- caisse claire et clap
        if sec['claire'] == 'roulement':
            # roulement qui se resserre : croches, puis doubles, puis triples
            pas = [0.5, 0.5, 0.5, 0.5, 0.25, 0.25, 0.25, 0.25, 0.25, 0.25, 0.125, 0.125,
                   0.125, 0.125, 0.125, 0.125, 0.125, 0.125]
            av = (m - 28) / 4.0
            t = 0.0
            i = 0
            while t < 4 * TEMPS and i < len(pas):
                k = int(len(pas) * av) + i
                p = pas[min(k, len(pas) - 1)] * TEMPS
                pose('claire', t0 + t, caisse_claire(0.35 + 0.55 * (t / (4 * TEMPS)) + 0.3 * av))
                t += p
                i += 1
        elif sec['claire']:
            for b in (1, 3):
                pose('claire', t0 + b * TEMPS, clap())
                pose('claire', t0 + b * TEMPS, caisse_claire(0.45))

        # --- charley
        if sec['charley']:
            pas = TEMPS / 2 if sec['charley'] == 8 else TEMPS / 4
            k = 0
            t = 0.0
            while t < 4 * TEMPS:
                ouvert = (k % 8 == 7) if sec['charley'] == 16 else (k % 4 == 3)
                pose('charley', t0 + t, charley(ouvert, 0.9 if k % 2 == 0 else 0.6))
                if sec['charley'] == 16 and k % 2 == 1:
                    pose('charley', t0 + t, shaker())
                t += pas
                k += 1
        elif sec['nom'] == 'intro' and m >= 2:
            for b in range(4):
                pose('charley', t0 + b * TEMPS + TEMPS / 2, charley(False, 0.5))

        # --- basse : croches avec une syncope
        if sec['basse']:
            motif = [(0.0, 0.5), (0.5, 0.25), (0.75, 0.25), (1.5, 0.5), (2.0, 0.5),
                     (2.5, 0.25), (3.0, 0.5), (3.5, 0.5)]
            for pos, dur in motif:
                pose('basse', t0 + pos * TEMPS, basse(ac['basse'], dur * TEMPS))

        # --- arpège
        if sec['pluck'] > 0:
            for k in range(8):
                f = ac['arpege'][k % 4] * (2.0 if k >= 4 and sec['contre'] else 1.0)
                pose('pluck', t0 + k * TEMPS / 2, pluck(f, TEMPS / 2 * 1.4, sec['pluck']))
            if sec['contre']:
                for k in range(4):
                    pose('pluck', t0 + k * TEMPS + TEMPS * 0.25,
                         pluck(ac['arpege'][(k + 2) % 4] * 2, TEMPS * 0.8, 0.45, 4200))

        # --- nappe
        if sec['nappe'] > 0:
            coupe = 900 if sec['nom'] == 'intro' else (3200 if sec['nom'] == 'apogee' else 2200)
            pose('nappe', t0, nappe(ac['triade'], MESURE * 1.05, sec['nappe'], coupe))

        # --- cuivres sur l'apogée et les frappes
        if sec['nom'] == 'apogee' and m % 2 == 0:
            pose('cuivre', t0, cuivre([f * 0.5 for f in ac['triade']], MESURE * 0.95, 0.9))
        if sec['nom'] == 'frappes':
            pose('cuivre', t0, cuivre(ac['triade'] + [ac['basse'] * 2], MESURE * 0.8, 1.15))
            pose('effets', t0, impact(0.8))
        if sec['nom'] == 'final':
            pose('cuivre', t0, cuivre(ACCORDS[3]['triade'] + [110.0, 440.0], 4.0, 1.2))
            pose('effets', t0, impact(1.0))

    # --- effets de structure, posés sur les charnières du film
    pose('effets', 28 * MESURE, montee(4 * MESURE, 1.0))          # montée vers l'apogée
    pose('effets', 32 * MESURE, impact(1.0))                      # l'apogée tombe
    pose('effets', 42 * MESURE - 1.2, cymbale_inverse(1.2, 0.9))  # aspiration avant le silence
    pose('effets', 42 * MESURE, impact(0.9))                      # la rupture
    pose('effets', 44 * MESURE - 0.9, cymbale_inverse(0.9, 0.7))  # retour du groove
    pose('effets', 12 * MESURE, impact(0.45))                     # entrée Carnot
    pose('effets', 20 * MESURE, impact(0.45))                     # entrée Granvelle

    return pistes, sorted(set(temps_kick))


# -------------------------------------------------------------------- mixage
def duck(longueur, temps_kick, profondeur=0.62, duree=0.26):
    """Enveloppe de compression déclenchée par la grosse caisse.
       C'est elle qui donne la respiration « corporate » au morceau."""
    e = np.ones(longueur)
    forme = profondeur + (1 - profondeur) * (1 - np.exp(-np.linspace(0, 4, n(duree))))
    for t in temps_kick:
        d = n(t)
        f = min(d + len(forme), longueur)
        if f > d:
            e[d:f] = np.minimum(e[d:f], forme[:f - d])
    return e


def reverbe(x, duree=1.5, melange=0.3):
    L = n(duree)
    ri = bruit(L) * np.exp(-np.linspace(0, 6, L))
    ri = passebas(ri, 5200)
    ri[:n(0.012)] = 0
    ri /= np.abs(ri).sum() / 12
    humide = signal.fftconvolve(x, ri)[:len(x)]
    return x * (1 - melange) + humide * melange


def large(x, ms=9.0, equilibre=0.35):
    """Mono -> stéréo : léger retard d'un côté, pour les nappes et l'arpège."""
    d = n(ms / 1000.0)
    r = np.concatenate([np.zeros(d), x[:-d]]) if d else x.copy()
    g = np.cos(equilibre), np.sin(equilibre)
    return np.stack([x * g[0] + r * g[1], x * g[1] + r * g[0]])


def centre(x):
    return np.stack([x, x])


def mixe(pistes, temps_kick):
    L = len(pistes['kick'])
    d = duck(L, temps_kick)

    # Dosage revu : la première version était lourde du bas (25 dB d'écart
    # entre le sub et le médium), donc sourde. On recule la grosse caisse et
    # la basse, on avance l'arpège, le charley et les cuivres.
    gains = dict(kick=0.62, claire=0.72, charley=1.15, basse=0.80,
                 pluck=2.70, nappe=1.90, cuivre=2.10, effets=0.50)
    # ce qui plonge sous la grosse caisse
    sous_duck = ('basse', 'nappe', 'pluck', 'cuivre')

    mix = np.zeros((2, L))
    for nom, piste in pistes.items():
        s = piste * gains[nom]
        if nom in sous_duck:
            s = s * d
        if nom in ('nappe', 'pluck'):
            s = reverbe(s, 1.8, 0.34)
            mix += large(s, 11.0 if nom == 'nappe' else 7.0)
        elif nom == 'cuivre':
            mix += large(reverbe(s, 1.4, 0.26), 6.0, 0.30)
        elif nom == 'claire':
            mix += centre(reverbe(s, 1.1, 0.28))
        elif nom == 'charley':
            mix += np.stack([s * 0.82, s * 1.0])
        elif nom == 'effets':
            mix += large(reverbe(s, 2.2, 0.30), 14.0, 0.40)
        else:
            mix += centre(s)

    # l'arc du morceau
    mix *= enveloppe_sections(L)

    # coupe-bas, puis bascule tonale : -3 dB sous 110 Hz, +4 dB au-dessus de 3 kHz
    mix = np.stack([passehaut(c, 30) for c in mix])
    bas = np.stack([passebas(c, 110) for c in mix])
    haut = np.stack([passehaut(c, 3000) for c in mix])
    mix = mix - bas * 0.29 + haut * 0.58
    mix = np.tanh(mix * 1.15) / np.tanh(1.15)
    crete = np.abs(mix).max() or 1.0
    mix *= 10 ** (-1.0 / 20) / crete          # -1 dBFS
    # fondu de sécurité aux deux bouts
    fi, fo = n(0.03), n(2.5)
    mix[:, :fi] *= np.linspace(0, 1, fi)
    mix[:, -fo:] *= np.linspace(1, 0, fo) ** 1.6
    return mix


def ecris(mix, chemin):
    data = np.clip(mix.T, -1, 1)
    pcm = (data * 32767).astype('<i2')
    with wave.open(chemin, 'wb') as w:
        w.setnchannels(2)
        w.setsampwidth(2)
        w.setframerate(SR)
        w.writeframes(pcm.tobytes())


def main():
    dest = sys.argv[1] if len(sys.argv) > 1 else 'build/musique.wav'
    os.makedirs(os.path.dirname(dest) or '.', exist_ok=True)
    pistes, temps_kick = compose()
    mix = mixe(pistes, temps_kick)
    ecris(mix, dest)
    print('%s — %.2f s, %d BPM, %d mesures + %.0f s de queue'
          % (dest, mix.shape[1] / SR, BPM, MESURES, QUEUE))
    print('temps = %.4f s = %d images à 60 i/s (aucun arrondi)'
          % (TEMPS, round(TEMPS * 60)))
    print('crête %.2f dBFS' % (20 * np.log10(np.abs(mix).max())))
    import json
    grille = os.path.join(os.path.dirname(dest) or '.', 'grille.json')
    json.dump({'u': TEMPS, 'phase': 0.0, 'bpm': BPM, 'mesure': MESURE,
               'mesures': MESURES, 'duree': mix.shape[1] / SR}, open(grille, 'w'))
    print('grille écrite dans %s — elle est posée, pas mesurée' % grille)
    for s in SECTIONS:
        print('   %-10s mesures %2d-%2d  →  %6.2f s - %6.2f s'
              % (s['nom'], s['de'], s['a'], s['de'] * MESURE, s['a'] * MESURE))


if __name__ == '__main__':
    main()
