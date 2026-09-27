"""Prépare les polices statiques et le logo PNG.

Les polices variables ne conviennent ni à Word (qui les ignore) ni au PDF de
Chromium (qui les intègre en Type 3) : on fige chaque graisse utilisée.
  generated/web/   instances woff2 pour le HTML et le PDF
  generated/docx/  normal + gras renommés, intégrés au .docx, et le logo PNG
"""

from pathlib import Path

import pymupdf
from fontTools.ttLib import TTFont
from fontTools.varLib.instancer import instantiateVariableFont

HERE = Path(__file__).parent
OUT = HERE / "generated/docx"
WEB = HERE / "generated/web"

# (source, coordonnées, famille, style, fichier)
FACES = [
    ("fonts/inter-var.woff2", {"wght": 400}, "Inter", "Regular", "Inter-Regular.ttf"),
    ("fonts/inter-var.woff2", {"wght": 600}, "Inter", "Bold", "Inter-Bold.ttf"),
    ("fonts/bricolage-grotesque-var.woff2", {"wght": 420, "opsz": 24}, "Bricolage Grotesque", "Regular",
     "BricolageGrotesque-Regular.ttf"),
    ("fonts/bricolage-grotesque-var.woff2", {"wght": 800, "opsz": 36}, "Bricolage Grotesque", "Bold",
     "BricolageGrotesque-Bold.ttf"),
    ("fonts/space-grotesk-500.woff2", None, "Space Grotesk", "Regular", "SpaceGrotesk-Regular.ttf"),
]


def set_names(font: TTFont, family: str, style: str):
    """Noms propres à chaque instance (sinon toutes s'appellent comme la graisse par défaut)."""
    name = font["name"]
    full = family if style == "Regular" else f"{family} {style}"
    ps = f"{family.replace(' ', '')}-{style}"
    for rec in list(name.names):
        if rec.nameID in (16, 17, 21, 22, 25):
            name.removeNames(nameID=rec.nameID)
    for nid, val in ((1, family), (2, style), (3, f"SuisseImmo;{ps}"), (4, full), (6, ps)):
        name.setName(val, nid, 3, 1, 0x409)
        name.setName(val, nid, 1, 0, 0)


def rename(font: TTFont, family: str, style: str):
    """Nommage Word : deux styles par famille, normal et gras."""
    set_names(font, family, style)
    os2 = font["OS/2"]
    bold = style == "Bold"
    os2.fsSelection = (os2.fsSelection & ~0b1100001) | (0b100000 if bold else 0b1000000)
    font["head"].macStyle = 1 if bold else 0


# (source, coordonnées, famille CSS, graisse CSS, fichier) — voir build_html.FONTS
WEB_FACES = [
    ("fonts/inter-var.woff2", {"wght": 400}, "Inter", "Regular", "inter-400.woff2"),
    ("fonts/inter-var.woff2", {"wght": 500}, "Inter", "Medium", "inter-500.woff2"),
    ("fonts/inter-var.woff2", {"wght": 600}, "Inter", "SemiBold", "inter-600.woff2"),
    ("fonts/bricolage-grotesque-var.woff2", {"wght": 420, "opsz": 17}, "Bricolage Grotesque", "Regular",
     "bricolage-420.woff2"),
    ("fonts/bricolage-grotesque-var.woff2", {"wght": 700, "opsz": 15}, "Bricolage Grotesque", "Bold",
     "bricolage-700.woff2"),
    ("fonts/bricolage-grotesque-var.woff2", {"wght": 800, "opsz": 24}, "Bricolage Grotesque", "ExtraBold",
     "bricolage-800.woff2"),
    ("fonts/bricolage-grotesque-var.woff2", {"wght": 800, "opsz": 45}, "Bricolage Grotesque Display", "ExtraBold",
     "bricolage-display-800.woff2"),
]


def main():
    WEB.mkdir(parents=True, exist_ok=True)
    for src, coords, family, style, fname in WEB_FACES:
        font = instantiateVariableFont(TTFont(HERE / src), coords)
        set_names(font, family, style)
        font.flavor = "woff2"
        font.save(WEB / fname)
    (WEB / "space-grotesk-500.woff2").write_bytes((HERE / "fonts/space-grotesk-500.woff2").read_bytes())
    print("polices web :", len(WEB_FACES) + 1)

    OUT.mkdir(parents=True, exist_ok=True)
    for src, coords, family, style, fname in FACES:
        font = TTFont(HERE / src)
        if coords:
            font = instantiateVariableFont(font, coords)
        font.flavor = None
        rename(font, family, style)
        font.save(OUT / fname)
        print(fname, (OUT / fname).stat().st_size // 1024, "Ko")

    svg = pymupdf.open(HERE / "img/logo-suisse-immo.svg")
    pdf = pymupdf.open("pdf", svg.convert_to_pdf())
    pdf[0].get_pixmap(matrix=pymupdf.Matrix(4, 4), alpha=True).save(OUT / "logo-suisse-immo.png")
    print("logo-suisse-immo.png")


if __name__ == "__main__":
    main()
