#!/usr/bin/env python3
"""Genere le logo Etang Fourchu en SVG (textes convertis en courbes, fond transparent)."""
import math
from fontTools.ttLib import TTFont
from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen
from fontTools.pens.boundsPen import BoundsPen
from fontTools.misc.transform import Transform

FONTS = "fonts"
PLAYFAIR = f"{FONTS}/fontsource-playfair-display/files/playfair-display-latin-700-normal.woff2"
ALLURA = f"{FONTS}/fontsource-allura/files/allura-latin-400-normal.woff2"

_cache = {}
def load(path):
    if path not in _cache:
        f = TTFont(path)
        _cache[path] = (f, f.getBestCmap(), f.getGlyphSet(), f["head"].unitsPerEm)
    return _cache[path]


def _run(path, text, size, tracking, pen_factory):
    font, cmap, gs, upem = load(path)
    scale = size / upem
    pen = pen_factory(gs)
    x = 0.0
    for ch in text:
        g = cmap.get(ord(ch))
        if g is None:
            continue
        gs[g].draw(TransformPen(pen, Transform(scale, 0, 0, -scale, x * scale, 0)))
        x += gs[g].width + tracking / scale
    return pen


def text_path(path, text, size, tracking=0.0):
    return _run(path, text, size, tracking, SVGPathPen).getCommands()


def text_bbox(path, text, size, tracking=0.0):
    return _run(path, text, size, tracking, BoundsPen).bounds  # xmin ymin xmax ymax


# ------------------------------------------------------------------ courbes
def catmull(points, t=1.0):
    """Spline de Catmull-Rom passant par tous les points -> path SVG en cubiques."""
    p = [points[0]] + list(points) + [points[-1]]
    d = [f"M {p[1][0]:.2f} {p[1][1]:.2f}"]
    for i in range(1, len(p) - 2):
        p0, p1, p2, p3 = p[i - 1], p[i], p[i + 1], p[i + 2]
        c1 = (p1[0] + (p2[0] - p0[0]) / 6 * t, p1[1] + (p2[1] - p0[1]) / 6 * t)
        c2 = (p2[0] - (p3[0] - p1[0]) / 6 * t, p2[1] - (p3[1] - p1[1]) / 6 * t)
        d.append(f"C {c1[0]:.2f} {c1[1]:.2f} {c2[0]:.2f} {c2[1]:.2f} {p2[0]:.2f} {p2[1]:.2f}")
    return " ".join(d)


def pt(cx, cy, r, deg):
    a = math.radians(deg)
    return cx + r * math.cos(a), cy + r * math.sin(a)


# ---------------------------------------------------------------- geometrie
W, H = 900, 760
CX, CY = 450, 330
R_OUT, R_IN = 266, 246
GAP = (62, 118)          # ouverture bas du cercle, 90 deg = bas


def ring():
    out = []
    for r, pad in ((R_OUT, 0), (R_IN, 10)):
        a0, a1 = GAP[1] - pad, GAP[0] + 360 + pad
        x0, y0 = pt(CX, CY, r, a0)
        x1, y1 = pt(CX, CY, r, a1)
        out.append(f"M {x0:.2f} {y0:.2f} A {r:.2f} {r:.2f} 0 1 1 {x1:.2f} {y1:.2f}")
    return out


AMP, PHASE, ENV_MIN, DRIFT, N = 34.0, 0.20, 0.30, -10.0, 15

def wave():
    """Deux filets miroir autour d'un axe : ils se croisent pres des pointes
    et s'ouvrent au centre -> lentille allongee (l'eau, et la fourche)."""
    y0 = CY + 182
    xl, xr = CX - 292, CX + 292
    A, B = [], []
    for i in range(N):
        t = i / (N - 1)
        x = xl + (xr - xl) * t
        env = ENV_MIN + (1 - ENV_MIN) * math.sin(math.pi * t) ** 0.7
        off = AMP * env * math.sin(math.pi * (-PHASE + (1 + 2 * PHASE) * t))
        axis = y0 + DRIFT * t
        A.append((x, axis - off))
        B.append((x, axis + off))
    # pointes legerement decalees pour eviter la symetrie parfaite
    B[0] = (B[0][0] + 12, B[0][1] - 3)
    B[-1] = (B[-1][0] - 10, B[-1][1] + 2)
    return [catmull(A), catmull(B)]


# ------------------------------------------------------------------- rendu
GOLD_STOPS = [(0, "#C9A24B"), (0.22, "#F2DFA4"), (0.45, "#DCB863"),
              (0.68, "#F7EBC4"), (1, "#C79E42")]

WORDS = (("Étang", 156, 300), ("Fourchu", 156, 438))
TAGLINE = ("Nature & Loisirs", 112, 686)


def build(gold="url(#ef-or)", word_fill="#FFFFFF", grad=True, emblem_only=False):
    stops = "".join(f'<stop offset="{o}" stop-color="{c}"/>' for o, c in GOLD_STOPS)
    defs = (f'\n  <defs><linearGradient id="ef-or" x1="0" y1="0" x2="1" y2="1">{stops}'
            '</linearGradient></defs>') if grad else ""
    ring_d = "\n      ".join(f'<path d="{p}"/>' for p in ring())
    wave_d = "\n      ".join(f'<path d="{p}"/>' for p in wave())

    strokes = f'''  <g fill="none" stroke="{gold}" stroke-linecap="round" stroke-width="6.5">
      {ring_d}
    <g stroke-width="7.5">
      {wave_d}
    </g>
  </g>'''

    if emblem_only:
        vb, body = f"120 40 660 545", strokes
    else:
        parts = []
        for word, size, baseline in WORDS:
            d = text_path(PLAYFAIR, word, size, tracking=2)
            b = text_bbox(PLAYFAIR, word, size, tracking=2)
            parts.append(f'<path transform="translate({CX - (b[0] + b[2]) / 2:.2f} '
                         f'{baseline})" d="{d}"/>')
        txt, size, baseline = TAGLINE
        d = text_path(ALLURA, txt, size)
        b = text_bbox(ALLURA, txt, size)
        tag = f'<path transform="translate({CX - (b[0] + b[2]) / 2:.2f} {baseline})" d="{d}"/>'
        vb = f"0 0 {W} {H}"
        body = (strokes + '\n  <g fill="' + word_fill + '">\n    '
                + "\n    ".join(parts) + '\n  </g>\n  <g fill="' + gold + '">\n    '
                + tag + '\n  </g>')

    return f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="{vb}" role="img" aria-label="Étang Fourchu — Nature &amp; Loisirs">
  <title>Étang Fourchu — Nature &amp; Loisirs</title>{defs}
{body}
</svg>
'''


VARIANTS = {
    "logo-etang-fourchu":          dict(),
    "logo-etang-fourchu-sur-clair": dict(word_fill="#111111"),
    "logo-etang-fourchu-or":       dict(gold="#D9B45F", word_fill="#D9B45F", grad=False),
    "logo-etang-fourchu-blanc":    dict(gold="#FFFFFF", word_fill="#FFFFFF", grad=False),
    "logo-etang-fourchu-noir":     dict(gold="#111111", word_fill="#111111", grad=False),
    # Or clair en aplat + texte blanc : le dégradé passe par des tons sombres
    # qui disparaissent sur fond très foncé, et le petit format les accentue.
    "logo-etang-fourchu-clair":    dict(gold="#E6CD8F", word_fill="#FFFFFF", grad=False),
    "embleme-etang-fourchu":       dict(emblem_only=True),
    "embleme-etang-fourchu-or":    dict(emblem_only=True, gold="#D9B45F", grad=False),
    "embleme-etang-fourchu-clair": dict(emblem_only=True, gold="#E6CD8F", grad=False),
}

if __name__ == "__main__":
    import cairosvg, os, sys
    dest = sys.argv[1] if len(sys.argv) > 1 else "."
    os.makedirs(dest, exist_ok=True)
    for name, kw in VARIANTS.items():
        svg = build(**kw)
        open(f"{dest}/{name}.svg", "w").write(svg)
        print(f"{name}.svg  {len(svg):>6} o")
    # exports PNG transparents de la version principale
    main = build()
    for w in (400, 800, 1600):
        cairosvg.svg2png(bytestring=main.encode(), write_to=f"{dest}/logo-etang-fourchu@{w}.png",
                         output_width=w)
    cairosvg.svg2png(bytestring=build(emblem_only=True).encode(),
                     write_to=f"{dest}/favicon-512.png", output_width=512)
    # planches de controle
    for bg, tag in (("#141414", "sombre"), ("#FFFFFF", "clair")):
        v = build() if tag == "sombre" else build(word_fill="#111111")
        cairosvg.svg2png(bytestring=v.encode(), write_to=f"check-{tag}.png",
                         output_width=560, background_color=bg)
    print("ok ->", dest)
