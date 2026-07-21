#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Assemble le livre restructuré : paras.json + struct_part{1..4}.json -> .typ + .docx
Le corps du texte reste identique au mot près ; on n'ajoute que des titres/structure."""
import json, os, re, sys

BOOK = os.environ.get("BOOK_DIR") or os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
BUILD = os.path.join(BOOK, "build")
STRUCT = os.path.join(BOOK, "struct")
WORK = BUILD  # sorties (livre.typ) dans build/
recs = json.load(open(f"{BUILD}/paras.json", encoding="utf-8"))
def T(i): return recs[i]["t"]
N = len(recs)

# ---------------- Métadonnées des parties ----------------
PARTS = [
    dict(num="",    title="Introduction", color="INTRO", body_start=13, end=82, intro=True),
    dict(num="I",   title="Le monde de la comptabilité", color="I", head_idx=83, end=415),
    dict(num="II",  title="Le monde des particuliers et entrepreneurs individuels", color="II", head_idx=416, end=1137),
    dict(num="III", title="Le monde des sociétés", color="III", head_idx=1138, end=2074),
    dict(num="IV",  title="La holding", color="IV", head_idx=2075, end=2398),
]
# Sections que j'ajoute moi-même dans l'introduction (niveau 3)
INTRO_SECTIONS = {
    34: "Les carrés magiques, une inspiration",
    55: "Du carré magique au Triangle Magique",
}
COVER = dict(t1=T(3), s1=T(4), t2=T(7), s2=T(8), auteur="LEMAIRE")
SKIP_BODY = {0, 3, 4, 7, 8}  # consommés par la couverture / sommaire
FIG = {42: "durer", 51: "ordre5"}  # [pic] -> figures reconstruites

# ---------------- Chargement de la structure des sous-agents ----------------
heads = {}   # idx -> dict(level, title, action)
for p in (1, 2, 3, 4):
    fp = f"{STRUCT}/struct_part{p}.json"
    if not os.path.exists(fp):
        print(f"!! MANQUANT: {fp}", file=sys.stderr); continue
    data = json.load(open(fp, encoding="utf-8"))
    for h in data["headings"]:
        heads[int(h["idx"])] = dict(level=int(h["level"]), title=h["title"].strip(), action=h["action"])

# part-title paragraphs (sautés du corps : deviennent l'ouverture de partie)
PART_HEAD_IDX = {p["head_idx"] for p in PARTS if not p.get("intro")}

# ---------------- Construction de la liste ordonnée de blocs ----------------
# kind: cover, toc, part, chapter, section, subsection, body, citation, exemple, figure
blocks = []
chapter_no = 0
chapter_titles = []  # (num, title) pour vérif

def classify_run(i):
    """Regroupe une citation multi-paragraphes commençant par « jusqu'à la fermeture »."""
    if not T(i).startswith("«"):
        return None
    j = i
    buf = []
    while j <= endB:
        t = T(j)
        if t:
            buf.append(j)
            if t.rstrip().endswith("»"):
                return buf
        # stop si on tombe sur un titre
        j += 1
        if j in heads or j in FIG:
            break
    return buf if buf else None

# on parcourt partie par partie
for pi, part in enumerate(PARTS):
    startB = part.get("body_start", None)
    if part.get("intro"):
        startB = part["body_start"]
    else:
        startB = part["head_idx"] + 1
    endB = part["end"]

    # bloc d'ouverture de partie
    blocks.append(dict(kind="part", num=part["num"], title=part["title"],
                       color=part["color"], intro=part.get("intro", False)))

    i = startB
    consumed_until = -1
    while i <= endB:
        if i < consumed_until:
            i += 1; continue
        t = T(i)
        if not t:
            i += 1; continue
        if i in SKIP_BODY or i in PART_HEAD_IDX:
            i += 1; continue

        # figure ?
        if i in FIG:
            blocks.append(dict(kind="figure", which=FIG[i]))
            i += 1; continue

        # titre (des sous-agents) ?
        spec = heads.get(i)
        intro_sec = INTRO_SECTIONS.get(i)
        if spec or intro_sec:
            if intro_sec:
                lvl, title, action = 3, intro_sec, "insert_before"
            else:
                lvl, title, action = spec["level"], spec["title"], spec["action"]
            if lvl == 2:
                chapter_no += 1
                chapter_titles.append((chapter_no, title))
                blocks.append(dict(kind="chapter", num=chapter_no, title=title, color=part["color"]))
            elif lvl == 3:
                blocks.append(dict(kind="section", title=title))
            else:
                blocks.append(dict(kind="subsection", title=title))
            # si promote : le paragraphe courant EST le titre -> on ne l'émet pas en corps
            if action == "promote":
                i += 1
                continue
            # insert_before : le paragraphe reste en corps -> on continue pour l'émettre
        # citation multi-para ?
        run = classify_run(i)
        if run:
            blocks.append(dict(kind="citation", idxs=list(run)))
            consumed_until = run[-1] + 1
            i = run[-1] + 1
            continue
        # corps normal
        blocks.append(dict(kind="body", idx=i))
        i += 1

print(f"Blocs: {len(blocks)} | chapitres: {chapter_no}")
for n, t in chapter_titles:
    print(f"  Ch.{n}: {t}")

# ---------------- Émetteur TYPST ----------------
COLORMAP = {"INTRO": "C-INTRO", "I": "C-I", "II": "C-II", "III": "C-III", "IV": "C-IV"}
ROMAN_DESC = {
    "":   "Comment je suis « tombé dans la marmite » de l'entrepreneuriat, et comment est né le Triangle Magique.",
    "I":  "Comprendre le langage du chiffre : la comptabilité comme véritable outil de gestion de l'entrepreneur.",
    "II": "La fiscalité du particulier et de l'investisseur immobilier : ce qu'il faut savoir avant de passer en société.",
    "III":"Créer, choisir et faire vivre ses sociétés : l'outil central du patrimoine de l'entrepreneur.",
    "IV": "Le sommet du triangle : la holding, poste de vigie et clé de voûte du groupe.",
}

def typ_escape(s):
    s = s.replace("\\", "\\\\")
    for ch in ["#", "$", "*", "_", "`", "[", "]", "<", ">", "@", "="]:
        s = s.replace(ch, "\\" + ch)
    return s

def typ_body(s):
    """Échappe + neutralise un marqueur de liste/enum en début de ligne."""
    e = typ_escape(s)
    if re.match(r"^([-+/.]|\d+[.)])\s", e):
        e = "\\" + e
    return e

def emit_typst():
    out = []
    out.append('#import "theme.typ": *')
    out.append('#show: livre.with(titre-court: "La magie des sociétés")')
    out.append("")
    # couverture
    out.append(f'#couverture([{typ_escape(COVER["t1"])}], [{typ_escape(COVER["s1"])}], '
               f'[{typ_escape(COVER["t2"])}], [{typ_escape(COVER["s2"])}], [{typ_escape(COVER["auteur"])}])')
    out.append("#sommaire()")
    out.append("")
    first_body_after_head = False
    for b in blocks:
        k = b["kind"]
        if k == "part":
            if b["intro"]:
                out.append(f'#partie("", [Introduction], [{typ_escape(ROMAN_DESC[""])}], {COLORMAP["INTRO"]})')
            else:
                out.append(f'#partie("{b["num"]}", [{typ_escape(b["title"])}], '
                           f'[{typ_escape(ROMAN_DESC.get(b["num"],""))}], {COLORMAP[b["color"]]})')
            first_body_after_head = True
        elif k == "chapter":
            out.append(f'== {typ_escape(b["title"])}')
            first_body_after_head = True
        elif k == "section":
            out.append(f'=== {typ_escape(b["title"])}')
            first_body_after_head = True
        elif k == "subsection":
            out.append(f'==== {typ_escape(b["title"])}')
            first_body_after_head = True
        elif k == "figure":
            out.append(f'#carre-{"durer" if b["which"]=="durer" else "ordre5"}()')
        elif k == "citation":
            txt = " ".join(T(x) for x in b["idxs"])
            out.append(f'#citation[{typ_escape(txt)}]')
            first_body_after_head = False
        elif k == "body":
            txt = T(b["idx"])
            if first_body_after_head and re.match(r"^[A-Za-zÀ-ÿ]", txt):
                # ouverture de chapitre : versale + petites capitales
                m = re.match(r"^(\S+)(.*)$", txt, re.S)
                firstword = m.group(1); rest = m.group(2)
                initial = firstword[0]; restword = firstword[1:]
                def q(x): return typ_escape(x).replace('"', '\\"')
                out.append(f'#chapstart("{q(initial)}", "{q(restword)}", [{typ_body(rest.lstrip())}])')
            else:
                out.append(typ_body(txt))
            first_body_after_head = False
            out.append("")
    open(f"{WORK}/livre.typ", "w", encoding="utf-8").write("\n".join(out))
    print("écrit livre.typ")

emit_typst()
print("OK")
