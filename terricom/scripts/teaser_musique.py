"""Musique du teaser terricom : style bande-annonce de film épique, composée par synthèse (libre de droits).

Tempo 128 (calé sur les coupes de l'image), ré mineur, accords ré m – si♭ – fa – do.
Structure en temps : 0-8 grondement et chœurs, 8-16 cordes staccato et tambours qui montent,
16-48 tutti (tambours, cuivres, cordes, chœurs ; thème héroïque dès 32), 48-56 suspension et montée,
56-62 dernier tutti, 62 impact final et résonance.
Usage : python3 scripts/teaser_musique.py docs/teaser/musique.wav   (nécessite numpy)
"""
import sys, wave
import numpy as np

SR = 44100
B = 60 / 128
TOTAL = 64 * B + 2.5
N = int(TOTAL * SR)
rng = np.random.default_rng(11)
dry = np.zeros((N, 2)); wet = np.zeros((N, 2))

def at(beat): return int(round(beat * B * SR))

def put(sig, beat, g=1.0, pan=0.0, rev=0.3):
    i = at(beat); j = min(N, i + len(sig))
    if j <= i: return
    s = sig[: j - i] * g
    lr = np.array([np.sqrt(0.5 - pan / 2), np.sqrt(0.5 + pan / 2)])
    dry[i:j] += s[:, None] * lr; wet[i:j] += s[:, None] * lr * rev

def smooth(x, n):
    # Moyenne glissante de même longueur (filtre passe-bas simple)
    if n <= 1: return x
    c = np.cumsum(np.concatenate([np.zeros(n), x])); return (c[n:] - c[:-n]) / n

def tone(f, dur, harm, amp=lambda k: 1 / k, det=(1.0,), vib=0.0):
    n = int(dur * SR); t = np.arange(n) / SR; out = np.zeros(n)
    for d in det:
        ph = 2 * np.pi * f * d * t + (vib * np.sin(2 * np.pi * 5.2 * t) if vib else 0)
        for k in range(1, harm + 1):
            if f * d * k > 12000: break
            out += np.sin(k * ph + rng.uniform(0, 6.28)) * amp(k)
    return out / len(det)

def adsr(n, a, r, sustain=True):
    t = np.arange(n) / SR; e = np.minimum(1, t / max(a, 1e-4))
    rel = np.minimum(1, (n - np.arange(n)) / (r * SR))
    return e * rel

# ── Instruments
def taiko(pitch=1.0, big=1.0):
    n = int(1.2 * SR); t = np.arange(n) / SR
    f = (48 + 60 * np.exp(-t / 0.05)) * pitch
    body = np.sin(2 * np.pi * np.cumsum(f) / SR) * np.exp(-t / (0.45 * big))
    skin = smooth(rng.normal(0, 1, n), 12) * np.exp(-t / 0.06) * 2.5
    return (body + skin) * 0.9

def snare_ens():
    n = int(0.4 * SR); t = np.arange(n) / SR
    x = rng.normal(0, 1, n); x = x - smooth(x, 6)
    return x * np.exp(-t / 0.09) * 0.5 + np.sin(2 * np.pi * 190 * t) * np.exp(-t / 0.05) * 0.3

def braam(root, dur=3.0):
    n = int(dur * SR); t = np.arange(n) / SR
    s = sum(tone(root * m, dur, 18, det=(0.994, 1.0, 1.007)) * g for m, g in ((1, 1), (2, 0.7), (3, 0.35)))
    growl = 1 + 0.25 * np.sin(2 * np.pi * 28 * t)
    return s * growl * np.minimum(1, t / 0.03) * np.exp(-t / 1.4) * 0.5

def brass(f, dur):
    s = tone(f, dur, 16, det=(0.997, 1.003))
    return s * adsr(len(s), 0.07, 0.15) * 0.45

def string_stac(f):
    s = tone(f, 0.22, 20, det=(0.995, 1.0, 1.005))
    n = len(s); t = np.arange(n) / SR
    return s * np.minimum(1, t / 0.004) * np.exp(-t / 0.08) * 0.35

def choir(f, dur):
    formant = lambda k: np.exp(-((f * k - 750) / 300) ** 2) + 0.6 * np.exp(-((f * k - 1150) / 250) ** 2) + 0.25 / k
    s = tone(f, dur, 24, amp=formant, det=(0.996, 1.0, 1.004), vib=0.012)
    return s * adsr(len(s), 0.35, 0.6) * 0.5

def lead(f, dur):
    s = tone(f, dur, 14, det=(0.998, 1.002), vib=0.01)
    return s * adsr(len(s), 0.06, 0.12) * 0.4

def swell(beats):
    n = at(beats); t = np.arange(n) / SR
    x = rng.normal(0, 1, n); x = x - smooth(x, 3)
    return x * (t / t[-1]) ** 3 * 0.35

def crash():
    n = int(3 * SR); t = np.arange(n) / SR
    x = rng.normal(0, 1, n); x = x - smooth(x, 4)
    return x * np.exp(-t / 1.1) * 0.35

# Harmonie : ré m, si♭, fa, do (une mesure de 4 temps chacun)
D2, Bb1, F2, C2 = 73.42, 58.27, 87.31, 65.41
PROG = [(D2, (293.66, 349.23, 440.00)), (Bb1, (293.66, 349.23, 466.16)), (F2, (261.63, 349.23, 440.00)), (C2, (261.63, 329.63, 392.00))]
def chord(beat): return PROG[int(beat // 4) % 4]
OST = [0, 0, 7, 0, 3, 0, 7, 0, 0, 0, 7, 0, 3, 5, 7, 5]  # motif de cordes (demi-tons au-dessus de la fondamentale)

# 0-8 : grondement, chœurs
put(braam(36.71, 4), 0, 0.9, rev=0.5)
put(braam(36.71, 4), 4, 0.8, rev=0.5)
put(choir(146.83, 8 * B + 0.5), 0, 0.5, -0.2, 0.6); put(choir(220.0, 8 * B + 0.5), 0, 0.4, 0.2, 0.6)
for b in (0, 4, 6, 7): put(taiko(0.8, 1.4), b, 0.8, rev=0.5)

# 8-16 : cordes et tambours qui montent
for i, s16 in enumerate(np.arange(8, 16, 0.25)):
    root = chord(s16)[0] * 4
    put(string_stac(root * 2 ** (OST[i % 16] / 12)), s16, 0.35 + 0.5 * (s16 - 8) / 8, 0.3 if i % 2 else -0.3, 0.25)
for b in np.arange(8, 16, 1): put(taiko(1.0), b, 0.6 + 0.4 * (b - 8) / 8, rev=0.35)
for b in np.arange(14, 16, 0.25): put(snare_ens(), b, 0.2 + 0.5 * (b - 14) / 2, rev=0.3)
put(swell(2), 14, 0.9, rev=0.4)
for bar in (8, 12): put(choir(chord(bar)[1][0] / 2, 4 * B + 0.4), bar, 0.45, 0, 0.6)

# Tutti
def tutti(a, b, theme):
    for bar in np.arange(a, b, 4):
        root, notes = chord(bar)
        dur = min(4, b - bar) * B + 0.25
        put(braam(root / 2 if root > 70 else root, 2.5), bar, 0.55, rev=0.45)
        for k, f in enumerate(notes): put(brass(f / 2, dur), bar, 0.55, (k - 1) * 0.4, 0.35)
        put(tone(root, dur, 10) * adsr(int(dur * SR), 0.02, 0.1) * 0.5, bar, 0.6, rev=0.2)
        for f in notes: put(choir(f, dur), bar, 0.28, 0, 0.6)
    for i, s16 in enumerate(np.arange(a, b, 0.25)):
        root = chord(s16)[0] * 4
        put(string_stac(root * 2 ** (OST[i % 16] / 12)), s16, 0.75, 0.3 if i % 2 else -0.3, 0.25)
        put(string_stac(root * 2 * 2 ** (OST[i % 16] / 12)), s16, 0.3, -0.3 if i % 2 else 0.3, 0.25)
    for b8 in np.arange(a, b, 0.5):
        pos = b8 % 4
        if pos in (0, 2): put(taiko(0.9, 1.3), b8, 1.0, rev=0.4)
        elif pos in (1.5, 3.5): put(taiko(1.25), b8, 0.6, rev=0.3)
        if pos in (1, 3): put(snare_ens(), b8, 0.55, rev=0.35)
    if theme:  # thème héroïque (cor)
        motif = [(0, 587.33, 1.5), (1.5, 440.00, 0.5), (2, 698.46, 2), (4, 659.26, 1.5), (5.5, 587.33, 0.5), (6, 466.16, 2),
                 (8, 523.25, 1.5), (9.5, 587.33, 0.5), (10, 698.46, 2), (12, 783.99, 1.5), (13.5, 698.46, 0.5), (14, 659.26, 2)]
        for start in np.arange(theme, b, 16):
            for off, f, d in motif:
                if start + off < b: put(lead(f, d * B + 0.1), start + off, 0.55, 0.1, 0.5)

put(crash(), 16, 1.0, rev=0.5)
tutti(16, 48, 32)
put(crash(), 32, 0.8, rev=0.5)

# 48-56 : suspension et montée
put(choir(146.83, 8 * B + 0.4), 48, 0.55, -0.2, 0.7); put(choir(220.0, 8 * B + 0.4), 48, 0.45, 0.2, 0.7)
put(tone(36.71, 8 * B, 6) * adsr(at(8), 0.3, 0.3) * 0.6, 48, 0.8, rev=0.3)
for b in np.arange(48, 52, 1): put(taiko(0.75, 1.5), b, 0.55, rev=0.5)
for b in np.arange(52, 56, 0.25): put(snare_ens(), b, 0.15 + 0.6 * (b - 52) / 4, rev=0.35)
for b in np.arange(54, 56, 0.125): put(taiko(1.1), b, 0.25 + 0.6 * (b - 54) / 2, rev=0.3)
n = at(8); t = np.arange(n) / SR
rise = np.sin(2 * np.pi * np.cumsum(110 * 2 ** (3 * (t / t[-1]) ** 1.5)) / SR) * (t / t[-1]) ** 2 * 0.25
put(rise, 48, 1.0, rev=0.5); put(swell(4), 52, 1.1, rev=0.5)

# 56-62 : dernier tutti
put(crash(), 56, 1.1, rev=0.5)
tutti(56, 62, 56)

# 62 : impact final
put(braam(36.71, 4.5), 62, 1.3, rev=0.6)
for f in (73.42, 146.83, 220.0, 293.66, 349.23, 440.0): put(brass(f, 4.2), 62, 0.45, rev=0.5)
put(choir(293.66, 4.2), 62, 0.5, rev=0.7); put(choir(440.0, 4.2), 62, 0.4, rev=0.7)
put(taiko(0.7, 2.0), 62, 1.4, rev=0.6); put(crash(), 62, 1.2, rev=0.6)

# Réverbération de salle (convolution avec une réponse impulsionnelle synthétique)
ir_n = int(2.8 * SR); ti = np.arange(ir_n) / SR
out = dry.copy()
for ch in range(2):
    ir = rng.normal(0, 1, ir_n) * np.exp(-ti / 0.75); ir[: int(0.02 * SR)] = 0
    ir = smooth(ir, 3)
    m = 1 << int(np.ceil(np.log2(N + ir_n)))
    conv = np.fft.irfft(np.fft.rfft(wet[:, ch], m) * np.fft.rfft(ir, m), m)[:N]
    out[:, ch] += conv / np.abs(ir).sum() * 60
# Nuances : intro retenue, montée, tutti, suspension, explosion finale
pts = [(0, 0.35), (7.5, 0.5), (8, 0.45), (16, 0.75), (16.01, 0.85), (48, 0.85), (48.5, 0.55), (56, 0.9), (56.01, 1.0), (70, 1.0)]
curve = np.interp(np.arange(N) / SR / B, [p_[0] for p_ in pts], [p_[1] for p_ in pts])
out *= curve[:, None]
out /= np.abs(out).max() + 1e-9
out = np.tanh(out * 1.3) / np.tanh(1.3) * 0.95
fn = int(1.5 * SR); out[-fn:] *= np.linspace(1, 0, fn)[:, None]
dst = sys.argv[1] if len(sys.argv) > 1 else 'musique.wav'
with wave.open(dst, 'wb') as w:
    w.setnchannels(2); w.setsampwidth(2); w.setframerate(SR)
    w.writeframes((out * 32767).astype('<i2').tobytes())
print(f'{dst} : {TOTAL:.1f} s, style épique')
