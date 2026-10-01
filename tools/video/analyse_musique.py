# -*- coding: utf-8 -*-
"""Suivi de tempo : trouve la grille metrique d'une bande-son.

   Le film n'est jamais monte sur des « attaques » reperees une par une :
   elles sont irregulieres, et une coupe posee dessus flotte autour du temps
   au lieu de tomber dessus. On cherche ici une grille reguliere — une
   periode et une phase — a laquelle toutes les coupes seront accrochees.

   Usage : python3 analyse_musique.py musique.mp3 [grille.json]
   Demande numpy ; le reste de la chaine n'a aucune dependance.
"""
import json, os, subprocess, sys
import numpy as np

FF = os.environ.get('FFMPEG', 'ffmpeg')
SR, HOP, NFFT = 22050, 128, 1024        # 172,3 trames/s, resolution 5,8 ms


def detection(chemin):
    """Flux spectral a compression logarithmique, debarrasse de sa tendance."""
    brut = subprocess.run(
        [FF, '-v', 'error', '-i', chemin, '-ac', '1', '-ar', str(SR),
         '-f', 'f32le', '-'], capture_output=True, check=True).stdout
    x = np.frombuffer(brut, dtype=np.float32)
    n = 1 + (len(x) - NFFT) // HOP
    idx = np.arange(NFFT)[None, :] + HOP * np.arange(n)[:, None]
    S = np.abs(np.fft.rfft(x[idx] * np.hanning(NFFT).astype(np.float32), axis=1))
    L = np.log1p(1000.0 * S)                      # rend les faibles transitoires visibles
    od = np.concatenate([[0.0], np.maximum(L[1:] - L[:-1], 0).sum(axis=1)])
    fr = SR / HOP
    k = int(0.5 * fr)                             # retire la tendance lente
    od = np.maximum(od - np.convolve(od, np.ones(2 * k + 1) / (2 * k + 1), 'same'), 0)
    return od / (od.max() or 1.0), fr, len(x) / SR


def peigne(od, fr, P, phi, t0, t1, tol=2):
    """Force moyenne de la fonction de detection aux points de la grille."""
    pts = np.arange(phi, min(t1, (len(od) - 4) / fr), P) * fr
    pts = pts[pts >= t0 * fr]
    if len(pts) < 8:
        return 0.0, 0
    i = np.round(pts).astype(int)
    v = np.max(np.stack([od[np.clip(i + d, 0, len(od) - 1)]
                         for d in range(-tol, tol + 1)]), axis=0)
    return float(v.mean()), len(pts)


def periodes(od, fr):
    """Candidats par autocorrelation, corrigee du biais de recouvrement."""
    s = od - od.mean()
    ac = np.correlate(s, s, 'full')[len(s) - 1:] / np.arange(len(s), 0, -1)
    a, b = int(0.25 * fr), int(1.40 * fr)
    out, vus = [], []
    for j in np.argsort(ac[a:b])[::-1]:
        lag = a + int(j)
        if any(abs(lag - v) < 0.04 * fr for v in vus):
            continue
        vus.append(lag)
        out.append(lag / fr)
        if len(out) >= 8:
            break
    return out


def grille(chemin):
    od, fr, duree = detection(chemin)
    t0, t1 = 0.10 * duree, 0.90 * duree           # on ignore intro et chute
    # 1. la periode la plus contrastee contre son anti-phase
    best = None
    for P in periodes(od, fr):
        phi, v = max(((p / fr, peigne(od, fr, P, p / fr, t0, t1)[0])
                      for p in range(int(P * fr))), key=lambda z: z[1])
        anti = peigne(od, fr, P, (phi + P / 2) % P, t0, t1)[0]
        c = v / max(anti, 1e-9)
        if best is None or c > best[0]:
            best = (c, P, phi)
    P = best[1]
    # 2. redescendre a la pulsation fondamentale. L'autocorrelation prefere les
    #    periodes longues (grille clairsemee, plus facile a faire coincider) ;
    #    on garde le plus petit diviseur dont le peigne tient encore.
    ref = peigne(od, fr, P, best[2], t0, t1)[0]
    for div in (6, 5, 4, 3, 2):
        Pd = P / div
        if Pd < 0.20:
            continue
        phid, vd = max(((p / fr, peigne(od, fr, Pd, p / fr, t0, t1)[0])
                        for p in range(int(Pd * fr))), key=lambda z: z[1])
        if vd >= 0.78 * ref:
            P = Pd
            break
    # 3. affinage : une erreur de 1 ms sur la periode derive d'une demi-seconde
    #    au bout de deux minutes, donc on balaie finement
    fin = None
    for Pt in np.arange(P * 0.985, P * 1.015, P * 0.00008):
        for ph in np.arange(0.0, Pt, 0.0012):
            v, _ = peigne(od, fr, float(Pt), float(ph), t0, t1)
            if fin is None or v > fin[0]:
                fin = (v, float(Pt), float(ph))
    v, P, phi = fin
    phi -= (phi // P) * P
    return od, fr, duree, P, phi, v


def main():
    src = sys.argv[1] if len(sys.argv) > 1 else 'musique.mp3'
    dest = sys.argv[2] if len(sys.argv) > 2 else 'grille.json'
    od, fr, duree, P, phi, v = grille(src)
    anti = peigne(od, fr, P, (phi + P / 2) % P, 0.1 * duree, 0.9 * duree)[0]
    print('duree      %.3f s' % duree)
    print('grille     U = %.6f s  (%.3f BPM a la croche, %.3f BPM au temps)'
          % (P, 60 / P, 30 / P))
    print('phase      %.5f s' % phi)
    print('contraste  x%.2f contre l anti-phase' % (v / max(anti, 1e-9)))
    print('\nderive de phase par tranche (doit rester sous 10 ms) :')
    pas = duree / 5
    for i in range(5):
        a, b = i * pas, (i + 1) * pas
        loc = max(((peigne(od, fr, P, ph, a, b)[0], ph)
                   for ph in np.arange(0, P, 0.0010)))
        d = loc[1] - phi
        d -= round(d / P) * P
        print('   %5.1f-%5.1f s : %+6.1f ms' % (a, b, 1000 * d))
    json.dump({'duree': duree, 'u': P, 'phase': phi}, open(dest, 'w'))
    print('\n-> %s' % dest)
    print('Reportez U et PHASE dans storyboard.py, puis exprimez chaque plan')
    print('en nombre de croches : aucune coupe ne pourra tomber a cote.')


if __name__ == '__main__':
    main()
