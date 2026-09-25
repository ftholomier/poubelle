"""Musique du teaser terricom : électro entraînante à 128 BPM, composée par synthèse (libre de droits).

Structure en temps (1 temps = 60/128 s) : 0-8 intro (nappe, charleston), 8-16 montée (grosse caisse, basse),
16-48 drop, 48-56 pause et montée de tension, 56-62 dernier drop, 62-64 impact final et queue.
Usage : python3 scripts/teaser_musique.py docs/teaser/musique.wav   (nécessite numpy)
"""
import sys, wave
import numpy as np

SR = 44100
BPM = 128
B = 60 / BPM
TOTAL = 64 * B + 2.5
N = int(TOTAL * SR)
L = np.zeros(N); R = np.zeros(N)
rng = np.random.default_rng(7)

def at(beat): return int(beat * B * SR)

def add(sig, beat, gain=1.0, pan=0.0, buf=None):
    i = at(beat) if buf is None else buf
    j = min(N, i + len(sig))
    if j <= i: return
    s = sig[: j - i] * gain
    L[i:j] += s * (1 - max(pan, 0)); R[i:j] += s * (1 + min(pan, 0))

def env(n, a=0.002, d=0.2):
    t = np.arange(n) / SR
    return np.minimum(1, t / a) * np.exp(-t / d)

def saw(f, dur, harm=None):
    t = np.arange(int(dur * SR)) / SR
    k = np.arange(1, int(min(40, 9000 / f)) + 1 if harm is None else harm + 1)
    return (np.sin(2 * np.pi * np.outer(k, f * t)) / k[:, None]).sum(0) * 0.6

# ── Instruments
def kick():
    n = int(0.45 * SR); t = np.arange(n) / SR
    f = 45 + 110 * np.exp(-t / 0.035)
    ph = 2 * np.pi * np.cumsum(f) / SR
    return np.sin(ph) * np.exp(-t / 0.28) + rng.normal(0, 1, n) * np.exp(-t / 0.004) * 0.3

def clap():
    n = int(0.25 * SR); t = np.arange(n) / SR
    x = rng.normal(0, 1, n); x = x - np.roll(x, 1) * 0.5
    e = sum(np.exp(-(t - o) / 0.012) * (t >= o) for o in (0, 0.012, 0.024)) + np.exp(-t / 0.09) * 0.6
    return x * e * 0.5

def hat(open_=False):
    n = int((0.18 if open_ else 0.05) * SR); t = np.arange(n) / SR
    x = rng.normal(0, 1, n); x = np.diff(np.diff(x, prepend=0), prepend=0)
    return x * np.exp(-t / (0.06 if open_ else 0.012)) * 0.35

def pluck(f, dur=0.22):
    s = saw(f, dur, harm=8)
    return s * env(len(s), 0.001, 0.07)

KICK, CLAP, HAT, OHAT = kick(), clap(), hat(), hat(True)
PROG = [  # (basse, accord)
    (110.00, (220.00, 261.63, 329.63)),  # la mineur
    (87.31, (174.61, 220.00, 261.63)),   # fa
    (130.81, (261.63, 329.63, 392.00)),  # do
    (98.00, (196.00, 246.94, 293.66)),   # sol
]
def chord_at(beat): return PROG[int(beat // 4) % 4]

side = np.ones(N)  # compression rythmique (la nappe « respire » avec la grosse caisse)
def duck(beat):
    i = at(beat); n = int(0.3 * SR); t = np.arange(n) / SR
    j = min(N, i + n); side[i:j] = np.minimum(side[i:j], (1 - 0.8 * np.exp(-t / 0.09))[: j - i])

pad = np.zeros(N)
def pad_chord(beat, beats, gain):
    _, notes = chord_at(beat)
    for f in notes:
        for det, pan in ((0.996, -0.5), (1.0, 0.0), (1.004, 0.5)):
            s = saw(f * det, beats * B, harm=12)
            e = np.minimum(1, np.arange(len(s)) / (0.05 * SR)) * np.minimum(1, (len(s) - np.arange(len(s))) / (0.08 * SR))
            i = at(beat); j = min(N, i + len(s)); pad[i:j] += (s * e * gain)[: j - i]

# Intro et montée : nappe tenue
for bar in range(0, 16, 4): pad_chord(bar, 4, 0.05 if bar < 8 else 0.06)
# Drops : accords en contretemps
for drop in ((16, 48), (56, 62)):
    for b8 in np.arange(drop[0], drop[1], 0.5):
        if b8 % 1 == 0.5: pad_chord(b8, 0.45, 0.09)
# Pause : nappe large
for bar in range(48, 56, 4): pad_chord(bar, 4, 0.07)
pad_chord(62, 6, 0.08)

# Batterie
for b in range(8, 48):
    add(KICK, b, 0.9); duck(b)
for b in range(56, 62):
    add(KICK, b, 0.95); duck(b)
for b in list(range(16, 48)) + list(range(56, 62)):
    if b % 2 == 1: add(CLAP, b, 0.55, 0.1)
for h in np.arange(0, 48, 0.5):
    if h % 1 == 0.5: add(OHAT if h >= 16 else HAT, h, 0.5 if h >= 16 else 0.4, 0.3)
for h in np.arange(16, 48, 0.25):
    if h % 0.5: add(HAT, h, 0.22, -0.3)
for h in np.arange(56, 62, 0.25): add(HAT, h, 0.28 if h % 0.5 else 0.18, -0.3)

# Basse en contretemps (pompe)
for rng_ in ((8, 48), (56, 62)):
    for b8 in np.arange(rng_[0], rng_[1], 0.5):
        f, _ = chord_at(b8)
        s = saw(f / 2 if b8 % 1 == 0 else f / 2, 0.42 * B * 2, harm=10) * env(int(0.42 * B * 2 * SR), 0.003, 0.18)
        add(s, b8, 0.32 if b8 % 1 else 0.18)

# Arpège pendant la seconde moitié du drop et le dernier drop
for rng_ in ((32, 48), (56, 62)):
    for i16, b16 in enumerate(np.arange(rng_[0], rng_[1], 0.25)):
        _, notes = chord_at(b16)
        f = (notes + tuple(n * 2 for n in notes))[i16 % 6]
        add(pluck(f), b16, 0.11, 0.4 if i16 % 2 else -0.4)

# Montée de tension : roulement accéléré et balayage de bruit
for b in range(48, 56):
    step = 1 if b < 50 else 0.5 if b < 52 else 0.25 if b < 54 else 0.125
    for s_ in np.arange(b, b + 1, step): add(CLAP, s_, 0.15 + 0.5 * (s_ - 48) / 8, 0)
n = at(56) - at(48); t = np.arange(n) / SR
noise = rng.normal(0, 1, n); noise = noise - np.roll(noise, 1) * (0.9 - 0.8 * t / t[-1])
sweep = np.sin(2 * np.pi * np.cumsum(200 + 1800 * (t / t[-1]) ** 2) / SR)
add((noise * 0.12 + sweep * 0.08) * (t / t[-1]) ** 2, 48)

# Impacts
def impact(beat, g):
    n = int(2.2 * SR); t = np.arange(n) / SR
    crash = rng.normal(0, 1, n); crash = crash - np.roll(crash, 1) * 0.3
    sub = np.sin(2 * np.pi * np.cumsum(60 * np.exp(-t / 0.8) + 30) / SR)
    add(crash * np.exp(-t / 0.7) * 0.25 * g + sub * np.exp(-t / 0.9) * 0.6 * g, beat); add(KICK, beat, g)
impact(16, 0.8); impact(56, 1.0); impact(62, 1.1)

L += pad * side; R += pad * side
mix = np.stack([L, R], 1)
mix /= np.abs(mix).max() + 1e-9
mix = np.tanh(mix * 1.6) / np.tanh(1.6) * 0.95
fade = np.ones(N); fn = int(1.2 * SR); fade[-fn:] = np.linspace(1, 0, fn)
mix *= fade[:, None]
out = sys.argv[1] if len(sys.argv) > 1 else 'musique.wav'
with wave.open(out, 'wb') as w:
    w.setnchannels(2); w.setsampwidth(2); w.setframerate(SR)
    w.writeframes((mix * 32767).astype('<i2').tobytes())
print(f'{out} : {TOTAL:.1f} s, {BPM} BPM')
