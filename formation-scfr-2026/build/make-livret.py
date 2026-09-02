#!/usr/bin/env python3
"""Rendu du livret stagiaire — Agence SCFR.  Usage : python3 build/make-livret.py"""
import pathlib, sys
from weasyprint import HTML

ROOT = pathlib.Path(__file__).resolve().parent.parent
SRC = ROOT / "build" / "livret" / "livret.html"
OUT = ROOT / "dist" / "SCFR-Formation-2026-Livret-Stagiaire.pdf"
OUT.parent.mkdir(parents=True, exist_ok=True)

HTML(filename=str(SRC), base_url=str(SRC.parent)).write_pdf(str(OUT))
print("✓ Livret généré :", OUT.relative_to(ROOT))
