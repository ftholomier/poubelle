# -*- coding: utf-8 -*-
"""Repere les attaques (onsets) d'une bande-son pour y caler les coupes.

   Usage : python3 analyse_musique.py musique.mp3 [analyse.json]
   Aucune dependance : ffmpeg decode en PCM brut, le reste est en Python pur.
"""
import array, json, math, os, subprocess, sys

FF = os.environ.get('FFMPEG', 'ffmpeg')
SR = 22050          # frequence d'echantillonnage de travail
PAS = 256           # pas d'analyse, soit ~11,6 ms
FENETRE = 1024
SEUIL = 1.6         # multiplicateur de la moyenne glissante
GARDE = 0.22        # ecart minimal entre deux attaques, en secondes


def pcm(chemin):
    brut = subprocess.run(
        [FF, '-v', 'error', '-i', chemin, '-ac', '1', '-ar', str(SR),
         '-f', 's16le', '-'], capture_output=True, check=True).stdout
    ech = array.array('h')
    ech.frombytes(brut)
    return ech


def enveloppe(ech):
    """Energie par trame, puis sa derivee positive : la montee d'energie."""
    n = (len(ech) - FENETRE) // PAS
    nrj = []
    for i in range(max(n, 0)):
        d = ech[i * PAS:i * PAS + FENETRE]
        s = 0
        for v in d:
            s += v * v
        nrj.append(math.sqrt(s / FENETRE))
    flux = [max(nrj[i] - nrj[i - 1], 0.0) for i in range(1, len(nrj))]
    return flux


def attaques(flux):
    """Retient les pics qui depassent nettement la moyenne glissante."""
    demi = 20
    pics = []
    dernier = -99.0
    for i in range(1, len(flux) - 1):
        a, b = max(0, i - demi), min(len(flux), i + demi + 1)
        moy = sum(flux[a:b]) / (b - a)
        if flux[i] <= moy * SEUIL or flux[i] < flux[i - 1] or flux[i] < flux[i + 1]:
            continue
        t = (i + 1) * PAS / SR
        if t - dernier < GARDE:
            continue
        pics.append(round(t, 2))
        dernier = t
    return pics


def main():
    src = sys.argv[1] if len(sys.argv) > 1 else 'musique.mp3'
    dest = sys.argv[2] if len(sys.argv) > 2 else 'analyse.json'
    ech = pcm(src)
    duree = len(ech) / SR
    ons = attaques(enveloppe(ech))
    ecarts = [round(ons[i + 1] - ons[i], 3) for i in range(len(ons) - 1)]
    median = sorted(ecarts)[len(ecarts) // 2] if ecarts else 0
    json.dump({'duration': duree, 'onsets': ons}, open(dest, 'w'))
    print('duree %.2f s — %d attaques — ecart median %.3f s (~%.0f BPM)'
          % (duree, len(ons), median, 60 / median if median else 0))


if __name__ == '__main__':
    main()
