"""Pose les champs de formulaire (AcroForm) sur le PDF de l'annexe.

Usage : python3 build_fillable.py <annexe.pdf> <champs.json> <sortie.pdf>
Les positions viennent de render.cjs (--boxes), en pixels CSS depuis le coin
haut gauche de la page ; 1 px CSS = 0,75 pt.
"""

import json
import sys

import pymupdf

from content import FIELDS

PX = 0.75
INK = (0.08, 0.086, 0.106)

LABELS = {k: v[0] for k, v in FIELDS.items()}


def rect(b, inset=0.0):
    x0, y0 = b["x"] * PX, b["y"] * PX
    x1, y1 = (b["x"] + b["w"]) * PX, (b["y"] + b["h"]) * PX
    return pymupdf.Rect(x0 + inset, y0 + inset, x1 - inset, y1 - inset)


def main(src, boxes_path, out):
    boxes = json.load(open(boxes_path, encoding="utf-8"))
    doc = pymupdf.open(src)
    page = doc[0]
    seen = set()
    for b in boxes:
        name = b["name"]
        if name in seen:
            raise SystemExit(f"champ en double : {name}")
        seen.add(name)
        w = pymupdf.Widget()
        w.field_name = name
        w.field_label = LABELS.get(name, name)
        w.border_width = 0
        w.border_color = None
        w.fill_color = None
        if b["kind"] == "checkbox":
            w.field_type = pymupdf.PDF_WIDGET_TYPE_CHECKBOX
            w.rect = rect(b, 0.4)
            w.text_color = INK
            w.field_value = False
        elif b["kind"] == "signature":
            w.field_type = pymupdf.PDF_WIDGET_TYPE_SIGNATURE
            w.rect = rect(b, 0.5)
        else:
            w.field_type = pymupdf.PDF_WIDGET_TYPE_TEXT
            w.rect = rect(b)
            w.text_font = "Helv"
            w.text_fontsize = 0
            w.text_color = INK
        annot = page.add_widget(w)
        if b["kind"] == "signature":
            # Apparence vide : le lecteur PDF dessine sa propre invite de signature,
            # rien ne s'imprime tant que la personne n'a pas signé.
            r = w.rect
            blank = doc.get_new_xref()
            doc.update_object(blank, f"<< /Type /XObject /Subtype /Form /BBox [0 0 {r.width:.2f} {r.height:.2f}] /Resources << >> >>")
            doc.update_stream(blank, b" ")
            doc.xref_set_key(annot.xref, "AP", f"<< /N {blank} 0 R >>")

    doc.set_metadata({
        "title": "Formulaire de recueil du consentement (à remplir)",
        "author": "Suisse Immo",
        "subject": "Prospection téléphonique : recueil du consentement préalable",
        "keywords": "Suisse Immo, SI-JUR-06, consentement, démarchage téléphonique, formulaire",
        "creator": "Suisse Immo",
        "producer": "Suisse Immo",
    })
    doc.save(out, garbage=3, deflate=True)
    print("écrit", out, "·", len(seen), "champs")


if __name__ == "__main__":
    main(*sys.argv[1:4])
