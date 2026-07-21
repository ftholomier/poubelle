#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Extraction fidèle du .doc -> paras.json (+ reflowed + slices par partie).
Usage: python3 extract.py <source.doc> <build_dir>
Reproductible : antiword DocBook XML -> paragraphes (corps identique au mot près)."""
import sys, os, re, html, json, subprocess

src = sys.argv[1] if len(sys.argv) > 1 else None
build = sys.argv[2] if len(sys.argv) > 2 else "book/build"
os.makedirs(build, exist_ok=True)
if not src or not os.path.exists(src):
    print(f"ERREUR: source introuvable: {src}", file=sys.stderr); sys.exit(2)

# 1) antiword -> DocBook XML
xml = subprocess.run(["antiword", "-x", "db", src], capture_output=True, text=True).stdout
open(f"{build}/livre_docbook.xml", "w", encoding="utf-8").write(xml)

# 2) parse paragraphes (préserve les paragraphes entiers ; gras = titre candidat)
paras = re.findall(r"<para>(.*?)</para>", xml, re.S)
records = []
for p in paras:
    inner = p.strip()
    fully_bold = bool(re.fullmatch(r"\s*<emphasis role='bold'>(.*?)</emphasis>\s*", inner, re.S))
    txt = re.sub(r"<[^>]+>", "", p)
    txt = html.unescape(txt)
    txt = " ".join(txt.split())
    records.append({"t": txt, "b": fully_bold})
json.dump(records, open(f"{build}/paras.json", "w", encoding="utf-8"), ensure_ascii=False)

# 3) reflowed + slices
def line(i, r):
    mark = "  <<<BOLD>>>" if r["b"] else ""
    return f"[{i:04}]{mark} {r['t']}"
with open(f"{build}/livre_reflowed.txt", "w", encoding="utf-8") as f:
    for i, r in enumerate(records):
        if r["t"]:
            f.write(line(i, r) + "\n")
parts = {"part1_comptabilite": (83, 415), "part2_particuliers": (416, 1137),
         "part3_societes": (1138, 2074), "part4_holding": (2075, 2398)}
for name, (a, b) in parts.items():
    with open(f"{build}/{name}.txt", "w", encoding="utf-8") as f:
        for i in range(a, min(b + 1, len(records))):
            if records[i]["t"]:
                f.write(line(i, records[i]) + "\n")

ne = [r for r in records if r["t"]]
print(f"paras total={len(records)} non-empty={len(ne)} words={sum(len(r['t'].split()) for r in ne)}")
print("OK -> paras.json + slices dans", build)
