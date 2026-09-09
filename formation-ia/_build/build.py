#!/usr/bin/env python3
"""Chaîne de production des documents de formation.

Rendu PDF en deux passes : la première sert à relever, via des marqueurs
invisibles semés dans le HTML, la page réelle où tombe chaque chapitre ;
la seconde réinjecte ces numéros dans le sommaire. Les numéros de page du
sommaire sont donc exacts et non estimés.
"""
import re, subprocess, sys, pathlib, pypdf

BUILD = pathlib.Path(__file__).parent
OUT = BUILD.parent


def run(*cmd):
    r = subprocess.run(cmd, capture_output=True, text=True, cwd=BUILD)
    if r.returncode:
        print(r.stdout, r.stderr, file=sys.stderr)
        raise SystemExit(f"échec : {' '.join(cmd)}")
    return r.stdout.strip()


def pdf(src, dest, footer, toc=True):
    """Rend src (HTML) vers dest (PDF), avec sommaire paginé si demandé."""
    src, dest = BUILD / src, OUT / dest
    dest.parent.mkdir(parents=True, exist_ok=True)
    tmp = BUILD / "_tmp.pdf"

    run("node", "render.js", "pdf", str(src), str(tmp), footer)

    if toc:
        reader = pypdf.PdfReader(tmp)
        pages = {}
        for i, p in enumerate(reader.pages):
            for tag in re.findall(r"§([A-Za-z0-9_-]+)§", p.extract_text() or ""):
                pages.setdefault(tag, i + 1)
        if pages:
            html = src.read_text()
            html = re.sub(
                r'<span class="p" data-ref="([A-Za-z0-9_-]+)">[^<]*</span>',
                lambda m: f'<span class="p" data-ref="{m.group(1)}">{pages.get(m.group(1), "—")}</span>',
                html,
            )
            patched = BUILD / f"_paged_{src.name}"
            patched.write_text(html)
            run("node", "render.js", "pdf", str(patched), str(tmp), footer)
            patched.unlink()

    tmp.replace(dest)
    n = len(pypdf.PdfReader(dest).pages)
    print(f"  {dest.relative_to(OUT)}  ({n} pages)")
    return n


def png(src, dest, selector=".canvas"):
    src, dest = BUILD / src, OUT / dest
    dest.parent.mkdir(parents=True, exist_ok=True)
    run("node", "render.js", "png", str(src), str(dest), selector)
    print(f"  {dest.relative_to(OUT)}")


if __name__ == "__main__":
    targets = sys.argv[1:]
    print("Rien à faire : appeler pdf()/png() depuis un script dédié." if not targets else targets)
