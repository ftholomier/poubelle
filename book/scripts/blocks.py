#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Logique commune : paras.json + struct/*.json -> liste ordonnée de blocs.
Le corps du texte reste identique au mot près ; on n'ajoute que la structure."""
import json, os

BOOK = os.environ.get("BOOK_DIR") or os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
BUILD = os.path.join(BOOK, "build")
STRUCT = os.path.join(BOOK, "struct")

recs = json.load(open(f"{BUILD}/paras.json", encoding="utf-8"))
def T(i): return recs[i]["t"]
N = len(recs)

# Parties (ordre linéaire du texte)
PARTS = [
    dict(num="",    title="Introduction", color="INTRO", body_start=13, end=82, intro=True),
    dict(num="I",   title="Le monde de la comptabilité", color="I", head_idx=83, end=415),
    dict(num="II",  title="Le monde des particuliers et entrepreneurs individuels", color="II", head_idx=416, end=1137),
    dict(num="III", title="Le monde des sociétés", color="III", head_idx=1138, end=2074),
    dict(num="IV",  title="La holding", color="IV", head_idx=2075, end=2398),
]
# Sections ajoutées dans l'introduction (niveau 3)
INTRO_SECTIONS = {
    34: "Les carrés magiques, une inspiration",
    55: "Du carré magique au Triangle Magique",
}
COVER = dict(t1=T(3), s1=T(4), t2=T(7), s2=T(8), auteur="LEMAIRE")
SKIP_BODY = {0, 3, 4, 7, 8}       # consommés par la couverture
# [pic] -> figure reconstruite ; caption_idx = paragraphe source utilisé comme légende
FIG = {42: ("durer", 47), 51: ("ordre5", None)}
FIG_CAP = {cap for (_, cap) in FIG.values() if cap is not None}  # légendes -> retirées du corps

def load_heads():
    heads = {}
    for p in (1, 2, 3, 4):
        fp = f"{STRUCT}/struct_part{p}.json"
        if not os.path.exists(fp):
            continue
        data = json.load(open(fp, encoding="utf-8"))
        for h in data["headings"]:
            heads[int(h["idx"])] = dict(level=int(h["level"]), title=h["title"].strip(), action=h["action"])
    return heads

def build_blocks():
    heads = load_heads()
    PART_HEAD_IDX = {p["head_idx"] for p in PARTS if not p.get("intro")}
    blocks = []
    chapter_no = 0
    chapter_titles = []

    def classify_run(i, endB):
        """Citation multi-paragraphes : de « … jusqu'à la fermeture »."""
        if not T(i).startswith("«"):
            return None
        j = i; buf = []
        while j <= endB:
            t = T(j)
            if t:
                buf.append(j)
                if t.rstrip().endswith("»"):
                    return buf
            j += 1
            if j in heads or j in FIG:
                break
        return buf if buf else None

    for part in PARTS:
        startB = part["body_start"] if part.get("intro") else part["head_idx"] + 1
        endB = part["end"]
        blocks.append(dict(kind="part", num=part["num"], title=part["title"],
                           color=part["color"], intro=part.get("intro", False)))
        i = startB; consumed_until = -1
        while i <= endB:
            if i < consumed_until:
                i += 1; continue
            t = T(i)
            if not t:
                i += 1; continue
            if i in SKIP_BODY or i in PART_HEAD_IDX or i in FIG_CAP:
                i += 1; continue
            if i in FIG:
                which, capidx = FIG[i]
                cap = T(capidx) if capidx is not None else None
                blocks.append(dict(kind="figure", which=which, caption=cap)); i += 1; continue
            spec = heads.get(i); intro_sec = INTRO_SECTIONS.get(i)
            if spec or intro_sec:
                if intro_sec:
                    lvl, title, action = 3, intro_sec, "insert_before"
                else:
                    lvl, title, action = spec["level"], spec["title"], spec["action"]
                if lvl == 2:
                    if title.strip().lower() in ("remerciements", "conclusion"):
                        blocks.append(dict(kind="chapter", num=None, title=title, color=part["color"]))
                    else:
                        chapter_no += 1
                        chapter_titles.append((chapter_no, title))
                        blocks.append(dict(kind="chapter", num=chapter_no, title=title, color=part["color"]))
                elif lvl == 3:
                    blocks.append(dict(kind="section", title=title))
                else:
                    blocks.append(dict(kind="subsection", title=title))
                if action == "promote":
                    i += 1; continue
            run = classify_run(i, endB)
            if run:
                blocks.append(dict(kind="citation", idxs=list(run)))
                consumed_until = run[-1] + 1; i = run[-1] + 1; continue
            blocks.append(dict(kind="body", idx=i)); i += 1

    return blocks, chapter_titles

if __name__ == "__main__":
    b, ct = build_blocks()
    print(f"Blocs: {len(b)} | chapitres: {len(ct)}")
    for n, t in ct:
        print(f"  Ch.{n}: {t}")
