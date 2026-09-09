#!/usr/bin/env python3
"""Deck PowerPoint de la formation — 16:9.

Polices volontairement limitées à celles présentes sur Windows et macOS
sans installation (Trebuchet MS, Calibri, Consolas) : le fichier s'ouvre
correctement sur le poste du client. L'identité visuelle passe par la
couleur, la mise en page et les infographies, qui sont des images.
"""
import pathlib
from pptx import Presentation
from pptx.util import Inches, Pt
from pptx.dml.color import RGBColor
from pptx.enum.text import PP_ALIGN, MSO_ANCHOR
from pptx.enum.shapes import MSO_SHAPE

BUILD = pathlib.Path(__file__).parent
IMG = BUILD.parent / "02-infographies"

# ----------------------------------------------------------------- palette
INK   = RGBColor(0x12, 0x18, 0x1D)
INK2  = RGBColor(0x54, 0x5F, 0x69)
INK3  = RGBColor(0x8A, 0x95, 0x9D)
RULE  = RGBColor(0xDD, 0xE2, 0xE6)
WASH  = RGBColor(0xF4, 0xF7, 0xF8)
WHITE = RGBColor(0xFF, 0xFF, 0xFF)
J1    = RGBColor(0x0B, 0x65, 0x5E)
J1W   = RGBColor(0xE4, 0xEF, 0xED)
J2    = RGBColor(0xA3, 0x2B, 0x57)
J2W   = RGBColor(0xF8, 0xE7, 0xEC)
FLAG  = RGBColor(0x8A, 0x5A, 0x00)
FLAGW = RGBColor(0xFA, 0xF1, 0xDE)

DISP, BODY, MONO = "Trebuchet MS", "Calibri", "Consolas"
SW, SH = 13.333, 7.5
M = 0.85                      # marge latérale
CW = SW - 2 * M               # largeur utile

prs = Presentation()
prs.slide_width, prs.slide_height = Inches(SW), Inches(SH)
BLANK = prs.slide_layouts[6]


# ----------------------------------------------------------------- briques
def sl():
    return prs.slides.add_slide(BLANK)


def note(s, text):
    s.notes_slide.notes_text_frame.text = text.strip()


def rect(s, x, y, w, h, fill=None, line=None, lw=1.0):
    sh = s.shapes.add_shape(MSO_SHAPE.RECTANGLE, Inches(x), Inches(y), Inches(w), Inches(h))
    sh.shadow.inherit = False
    if fill is None:
        sh.fill.background()
    else:
        sh.fill.solid(); sh.fill.fore_color.rgb = fill
    if line is None:
        sh.line.fill.background()
    else:
        sh.line.color.rgb = line; sh.line.width = Pt(lw)
    sh.text_frame.text = ""
    return sh


def _runs(p, text, size, color, font, bold=False, italic=False):
    """Découpe *gras* et met en forme les segments."""
    for i, seg in enumerate(text.split("*")):
        if not seg:
            continue
        r = p.add_run(); r.text = seg
        f = r.font
        f.name, f.size = font, Pt(size)
        f.bold = bold or (i % 2 == 1)
        f.italic = italic
        f.color.rgb = INK if (i % 2 == 1 and color in (INK2, INK3)) else color


def txt(s, x, y, w, h, text, size=18, font=BODY, bold=False, color=INK,
        align=PP_ALIGN.LEFT, spacing=1.12, after=0, italic=False,
        anchor=MSO_ANCHOR.TOP, spc=None):
    tb = s.shapes.add_textbox(Inches(x), Inches(y), Inches(w), Inches(h))
    tf = tb.text_frame
    tf.word_wrap = True
    tf.margin_left = tf.margin_right = tf.margin_top = tf.margin_bottom = 0
    tf.vertical_anchor = anchor
    for i, line in enumerate(text.split("\n")):
        p = tf.paragraphs[0] if i == 0 else tf.add_paragraph()
        p.alignment, p.line_spacing, p.space_after = align, spacing, Pt(after)
        _runs(p, line, size, color, font, bold, italic)
        if spc:
            for r in p.runs:
                r.font._rPr.set("spc", str(spc))
    return tb


def kicker(s, text, color=INK3, y=0.55, x=M):
    txt(s, x, y, CW, 0.3, text.upper(), size=10.5, font=MONO, bold=True, color=color, spc=150)


def title(s, text, size=40, y=0.95, color=INK, w=None, x=M):
    txt(s, x, y, w or CW, 1.5, text, size=size, font=DISP, bold=True, color=color, spacing=0.95)


def bullets(s, x, y, w, items, accent=J1, size=17, gap=0.62, color=INK2):
    """Puces dessinées : un tiret coloré, puis le texte."""
    cy = y
    for it in items:
        txt(s, x, cy, 0.3, 0.3, "—", size=size, font=BODY, bold=True, color=accent)
        tb = txt(s, x + 0.34, cy, w - 0.34, 0.4, it, size=size, color=color, spacing=1.14)
        lines = max(1, int(len(it.replace("*", "")) / (w * 9.0)) + 1)
        cy += 0.30 + lines * (size / 72.0) * 1.28 + (gap - 0.62)
    return cy


def card(s, x, y, w, h, label, body, accent=J1, wash=J1W, size=15):
    rect(s, x, y, w, h, fill=wash)
    rect(s, x, y, 0.045, h, fill=accent)
    txt(s, x + 0.28, y + 0.22, w - 0.5, 0.25, label.upper(), size=10, font=MONO,
        bold=True, color=accent, spc=140)
    txt(s, x + 0.28, y + 0.58, w - 0.5, h - 0.8, body, size=size, color=INK2, spacing=1.2)


# ----------------------------------------------------------------- gabarits
def s_cover():
    s = sl()
    rect(s, 0, 0, SW, SH, fill=INK)
    rect(s, M, 1.5, 2.2, 0.06, fill=J1)
    txt(s, M, 0.9, CW, 0.3, "FORMATION · NIVEAU DÉBUTANT", size=11, font=MONO,
        bold=True, color=INK3, spc=180)
    txt(s, M, 1.95, 10.6, 2.4, "Deux jours pour\napprivoiser l'IA", size=58,
        font=DISP, bold=True, color=WHITE, spacing=0.92)
    txt(s, M, 4.55, 8.6, 1.0,
        "Comprendre ce que c'est, savoir lui parler, s'en servir tous les jours\n"
        "— et savoir ce qu'il ne faut jamais lui confier.",
        size=19, color=RGBColor(0xA8, 0xB4, 0xBC), spacing=1.3)
    for i, (k, v) in enumerate([("Durée", "2 jours · 13 h 30"), ("Format", "Intra-entreprise"),
                                ("Effectif", "8 à 12 personnes"), ("Pratique", "Environ 60 %")]):
        x = M + i * 2.95
        txt(s, x, 6.05, 2.7, 0.25, k.upper(), size=9.5, font=MONO, bold=True, color=INK3, spc=140)
        txt(s, x, 6.35, 2.7, 0.4, v, size=17, font=DISP, bold=True, color=WHITE)
    note(s, "Slide d'accueil, projetée pendant que les participants s'installent. "
            "Se présenter en une minute maximum : votre légitimité viendra des ateliers, pas de votre CV.")
    return s


def s_section(num, day, name, goal, accent, wash):
    s = sl()
    rect(s, 0, 0, SW, SH, fill=wash)
    rect(s, 0, 0, 0.28, SH, fill=accent)
    txt(s, M + 0.2, 1.6, 8, 0.3, f"{day} · SÉQUENCE {num}", size=12, font=MONO,
        bold=True, color=accent, spc=180)
    txt(s, M + 0.2, 2.15, 10.5, 2.2, name, size=50, font=DISP, bold=True, color=INK, spacing=0.95)
    rect(s, M + 0.2, 4.5, 1.6, 0.045, fill=accent)
    txt(s, M + 0.2, 4.9, 9.4, 1.2, goal, size=20, color=INK2, spacing=1.32)
    return s


def s_content(kick, ttl, items=None, accent=J1, right=None, big=None, notes="",
              tsize=38, isize=17):
    s = sl()
    rect(s, M, 0.44, CW, 0.035, fill=INK)
    kicker(s, kick, color=accent)
    title(s, ttl, size=tsize)
    body_w = 6.6 if right else 9.9
    if big:
        txt(s, M, 2.45, body_w, 1.0, big, size=24, font=DISP, bold=True, color=accent, spacing=1.1)
        y0 = 3.45
    else:
        y0 = 2.55
    if items:
        bullets(s, M, y0, body_w, items, accent=accent, size=isize)
    if right:
        card(s, M + 7.1, 2.5, CW - 7.1, 3.6, right[0], right[1], accent, WASH)
    if notes:
        note(s, notes)
    return s


def s_image(name, kick, notes=""):
    s = sl()
    s.shapes.add_picture(str(IMG / name), 0, 0, width=Inches(SW))
    if notes:
        note(s, notes)
    return s


def s_statement(text, sub, accent=J1, notes=""):
    s = sl()
    rect(s, 0, 0, SW, SH, fill=WASH)
    rect(s, M, 2.0, 1.4, 0.05, fill=accent)
    txt(s, M, 2.5, 11.2, 2.4, text, size=44, font=DISP, bold=True, color=INK, spacing=1.0)
    txt(s, M, 5.15, 10.0, 1.0, sub, size=19, color=INK2, spacing=1.3)
    if notes:
        note(s, notes)
    return s


def s_atelier(n, minutes, ttl, consigne, debrief, accent=J1, wash=J1W, notes="", livrable=None):
    s = sl()
    rect(s, 0, 0, SW, SH, fill=WHITE)
    rect(s, 0, 0, 0.22, SH, fill=accent)
    rect(s, M, 0.6, 2.9, 0.42, fill=accent)
    txt(s, M + 0.18, 0.68, 2.6, 0.3, f"ATELIER {n} · {minutes} MIN", size=11.5, font=MONO,
        bold=True, color=WHITE, spc=150)
    title(s, ttl, size=36, y=1.25)
    txt(s, M, 2.85, 3.0, 0.3, "CONSIGNE", size=10, font=MONO, bold=True, color=accent, spc=140)
    y = bullets(s, M, 3.22, 6.5, consigne, accent=accent, size=17, color=INK)
    if livrable:
        card(s, M, min(y + 0.2, 6.15), 6.5, 0.9, "Livrable", livrable, accent, wash, size=15)
    card(s, M + 7.1, 2.5, CW - 7.1, 3.7, "Débrief en grand groupe", debrief, accent, WASH, size=16)
    if notes:
        note(s, notes)
    return s


def s_tool(name, tag, what, how, watch, accent=J2, notes=""):
    s = sl()
    rect(s, M, 0.44, CW, 0.035, fill=INK)
    kicker(s, "LABORATOIRE D'OUTILS", color=accent)
    title(s, name, size=40)
    txt(s, M, 2.05, 8, 0.3, tag.upper(), size=11, font=MONO, bold=True, color=accent, spc=150)
    txt(s, M, 2.65, 6.5, 1.4, what, size=18, color=INK2, spacing=1.3)
    txt(s, M, 4.3, 4.0, 0.3, "CE QU'ON FAIT EN SALLE", size=10, font=MONO, bold=True,
        color=INK3, spc=140)
    bullets(s, M, 4.7, 6.5, how, accent=accent, size=16)
    card(s, M + 7.1, 2.5, CW - 7.1, 3.7, "Point de vigilance", watch, FLAG, FLAGW, size=16)
    if notes:
        note(s, notes)
    return s
