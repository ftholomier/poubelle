#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Émetteur TYPST : blocs -> build/livre.typ (corps identique au mot près)."""
import re, os
from blocks import build_blocks, T, COVER, BUILD, PARTS

COLORMAP = {"INTRO": "C-INTRO", "I": "C-I", "II": "C-II", "III": "C-III", "IV": "C-IV"}
DESC = {
    "":    "Comment je suis « tombé dans la marmite » de l'entrepreneuriat — et comment est né le Triangle Magique.",
    "I":   "Comprendre le langage du chiffre : la comptabilité comme véritable outil de gestion de l'entrepreneur.",
    "II":  "La fiscalité du particulier et de l'investisseur immobilier : ce qu'il faut savoir avant de passer en société.",
    "III": "Créer, choisir et faire vivre ses sociétés : l'outil central du patrimoine de l'entrepreneur.",
    "IV":  "Le sommet du triangle : la holding, poste de vigie et clé de voûte du groupe.",
}

def esc(s):
    s = s.replace("\\", "\\\\")
    for ch in ["#", "$", "*", "_", "`", "[", "]", "<", ">", "@", "="]:
        s = s.replace(ch, "\\" + ch)
    return s

def body_line(s):
    e = esc(s)
    if re.match(r"^([-+/.]|\d+[.)])\s", e):
        e = "\\" + e
    return e

def main():
    blocks, chapter_titles = build_blocks()
    out = []
    out.append('#import "theme.typ": *')
    out.append('#show: livre.with(titre-court: "La magie des sociétés")')
    out.append("")
    out.append(f'#couverture([{esc(COVER["t1"])}], [{esc(COVER["s1"])}], '
               f'[{esc(COVER["t2"])}], [{esc(COVER["s2"])}], [{esc(COVER["auteur"])}])')
    out.append("#sommaire()")
    out.append("")
    want_dropcap = False
    for b in blocks:
        k = b["kind"]
        if k == "part":
            if b["intro"]:
                out.append(f'#partie("", [Introduction], [{esc(DESC[""])}], {COLORMAP["INTRO"]})')
            else:
                out.append(f'#partie("{b["num"]}", [{esc(b["title"])}], [{esc(DESC.get(b["num"],""))}], {COLORMAP[b["color"]]})')
            want_dropcap = True
        elif k == "chapter":
            numval = f'"{b["num"]}"' if b.get("num") else "none"
            out.append(f'#chapnum.update({numval})')
            out.append(f'== {esc(b["title"])}')
            want_dropcap = True
        elif k == "section":
            out.append(f'=== {esc(b["title"])}')
        elif k == "subsection":
            out.append(f'==== {esc(b["title"])}')
        elif k == "figure":
            fn = 'carre-durer' if b["which"] == "durer" else 'carre-ordre5'
            caparg = f'caption: [{esc(b["caption"])}]' if b.get("caption") else ''
            out.append(f'#{fn}({caparg})')
            want_dropcap = False
        elif k == "figures":
            out.append(f'#figline[{esc(T(b["idx"]))}]'); want_dropcap = False
        elif k == "citation":
            txt = " ".join(T(x) for x in b["idxs"])
            out.append(f'#citation[{esc(txt)}]'); want_dropcap = False
        elif k == "body":
            txt = T(b["idx"])
            if want_dropcap and len(txt) > 60 and re.match(r"^[A-Za-zÀ-ÿ]", txt):
                m = re.match(r"^(\S+)(.*)$", txt, re.S)
                fw, rest = m.group(1), m.group(2)
                q = lambda x: esc(x).replace('"', '\\"')
                out.append(f'#chapstart("{q(fw[0])}", "{q(fw[1:])}", [{body_line(rest.lstrip())}])')
            else:
                out.append(body_line(txt))
            want_dropcap = False
        out.append("")

    # 4e de couverture (composée avec les mots du livre)
    mondes = [p["title"] for p in PARTS if not p.get("intro")]
    hook = T(16).strip("«» ").strip()
    mondes_typ = ", ".join("[" + esc(m) + "]" for m in mondes)
    out.append(f'#quatrieme([{esc(hook)}], [Dictionnaire Larousse], [{esc(COVER["s1"])}], '
               f'({mondes_typ}), [{esc(COVER["t2"])}], [{esc(COVER["s2"])}], [{esc(COVER["auteur"])}])')

    open(f"{BUILD}/livre.typ", "w", encoding="utf-8").write("\n".join(out))
    print(f"écrit {BUILD}/livre.typ | blocs={len(blocks)} chapitres={len(chapter_titles)}")

if __name__ == "__main__":
    main()
