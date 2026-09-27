"""Renseigne les métadonnées du PDF de la fiche (Chromium n'écrit que le titre).

Usage : python3 pdf_meta.py <fichier.pdf>
"""

import os
import sys

import pymupdf

from content import CONTENT


def main(path):
    doc = pymupdf.open(path)
    doc.set_metadata({
        "title": f"{CONTENT['ref']} — {CONTENT['title']}",
        "author": "Suisse Immo",
        "subject": "Prospection téléphonique : le consentement préalable (depuis le 11 août 2026)",
        "keywords": "Suisse Immo, SI-JUR-06, démarchage téléphonique, consentement, agents commerciaux",
        "creator": "Suisse Immo",
        "producer": "Suisse Immo",
    })
    tmp = path + ".tmp"
    doc.save(tmp, garbage=3, deflate=True)
    doc.close()
    os.replace(tmp, path)
    print("métadonnées :", path)


if __name__ == "__main__":
    main(sys.argv[1])
