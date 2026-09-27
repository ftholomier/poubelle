"""Génère le HTML A4 de la fiche SI-JUR-06 à partir de content.py.

Usage : python3 build_html.py <sortie.html> [--inline] [--fillable]
  --inline    polices et logo intégrés (fichier autonome)
  --fillable  seulement l'annexe, champs vides pour le PDF remplissable
"""

import base64
import html
import re
import sys
from pathlib import Path

from content import CONTENT, FIELDS

HERE = Path(__file__).parent

FIELD_MM = {k: v[1] for k, v in FIELDS.items()}

# Instances statiques produites par prep_assets.py (famille, fichier, graisse).
FONTS = [
    ("Inter", "generated/web/inter-400.woff2", "400"),
    ("Inter", "generated/web/inter-500.woff2", "500"),
    ("Inter", "generated/web/inter-600.woff2", "600"),
    ("Bricolage Grotesque", "generated/web/bricolage-420.woff2", "420"),
    ("Bricolage Grotesque", "generated/web/bricolage-700.woff2", "700"),
    ("Bricolage Grotesque", "generated/web/bricolage-800.woff2", "800"),
    ("Bricolage Grotesque Display", "generated/web/bricolage-display-800.woff2", "800"),
    ("Space Grotesk", "generated/web/space-grotesk-500.woff2", "500"),
]


def esc(s: str) -> str:
    return html.escape(s, quote=False)


def inline(s: str, fillable: bool = False) -> str:
    """Balisage léger → HTML."""
    out = esc(s)
    out = re.sub(r"\*\*(.+?)\*\*", r"<strong>\1</strong>", out)

    def field(m):
        name, disp = m.group(1), m.group(2) or "À COMPLÉTER"
        width = FIELD_MM.get(name, 30)
        label = "&#8203;" if fillable else f"[{disp}]"
        return (f'<span class="ph" data-field="{name}" style="width:{width}mm">'
                f'{label}</span>')

    out = re.sub(r"\[\[([a-z_]+)(?:\|([^\]]+))?\]\]([.,;)]?)",
                 lambda m: f'<span class="nw">{field(m)}{m.group(3)}</span>' if m.group(3) else field(m), out)
    out = re.sub(r"\{\{cb:([a-z_]+)\}\}", r'<span class="cb" data-field="\1"></span>', out)
    return out


def logo_svg() -> str:
    svg = (HERE / "img/logo-suisse-immo.svg").read_text(encoding="utf-8")
    svg = re.sub(r"<\?xml[^>]*>", "", svg).strip()
    return svg.replace("<svg ", '<svg class="logo" role="img" aria-label="Suisse Immo" ', 1)


def font_faces(inline_assets: bool) -> str:
    rules = []
    for family, path, weight in FONTS:
        if inline_assets:
            data = base64.b64encode((HERE / path).read_bytes()).decode()
            src = f"url(data:font/woff2;base64,{data}) format('woff2')"
        else:
            src = f"url({path}) format('woff2')"
        rules.append(f"@font-face{{font-family:'{family}';src:{src};"
                     f"font-weight:{weight};font-style:normal;font-display:block}}")
    return "\n".join(rules)


CSS = """
:root{
  --ink:#15161b; --text:#25272e; --muted:#5d626d; --faint:#858a95;
  --line:#e4dfd7; --rule:#373737; --red:#cc0017; --red-dark:#a10012;
  --red-tint:#fbe9eb; --warm:#f6f3ee; --warm-2:#efeae2;
  --display:'Bricolage Grotesque','Space Grotesk',system-ui,sans-serif;
  --body:'Inter',system-ui,-apple-system,'Segoe UI',sans-serif;
  --tag:'Space Grotesk','Inter',system-ui,sans-serif;
}
@page{size:A4;margin:0}
*{box-sizing:border-box}
html{-webkit-print-color-adjust:exact;print-color-adjust:exact}
body{margin:0;background:#8f8c86;font-family:var(--body);color:var(--text);
  font-size:9.3pt;line-height:1.5;font-feature-settings:"kern","liga","calt"}
.page{width:210mm;height:297mm;margin:10mm auto;background:#fff;position:relative;
  overflow:hidden;padding:16mm 18mm 0;box-shadow:0 2px 18px rgba(0,0,0,.25)}
.page-body{height:calc(297mm - 16mm - 19mm);overflow:hidden;display:flex;flex-direction:column}
@media print{
  body{background:#fff}
  .page{margin:0;box-shadow:none;break-after:page}
  .page:last-child{break-after:auto}
}
strong{font-weight:600;color:var(--ink)}
p{margin:0 0 2.4mm}

/* pied de page */
.pf{position:absolute;left:18mm;right:18mm;bottom:10mm;display:flex;justify-content:space-between;
  font-family:var(--tag);font-weight:500;font-size:6.6pt;letter-spacing:.11em;text-transform:uppercase;
  color:var(--faint)}
.pf span:nth-child(2){text-align:center}
.pf span:last-child{text-transform:none;letter-spacing:.04em}

/* couverture */
.cover-top{display:flex;align-items:center;justify-content:space-between;padding-bottom:5mm;
  border-bottom:1px solid var(--rule)}
.logo{height:12.5mm;width:auto;display:block}
.cover-ref{text-align:right;font-family:var(--tag);font-weight:500;font-size:7pt;letter-spacing:.1em;
  text-transform:uppercase;color:var(--muted);line-height:1.55}
.cover-ref b{display:block;font-weight:500;color:var(--ink);font-size:11pt;letter-spacing:.06em}
.kicker{margin:7mm 0 2.5mm;font-family:var(--tag);font-weight:500;font-size:7.4pt;letter-spacing:.14em;
  text-transform:uppercase;color:var(--red)}
h1{margin:0;font-family:'Bricolage Grotesque Display',var(--display);font-weight:800;font-size:34pt;line-height:1.02;
  letter-spacing:-.018em;color:var(--ink)}
.subtitle{margin:3.5mm 0 0;font-family:var(--display);font-weight:420;font-size:12.6pt;line-height:1.38;
  color:#3b3e46;max-width:158mm}
.meta{display:grid;grid-template-columns:1.25fr 1.45fr .8fr .8fr;gap:0;margin:6.5mm 0 0;
  border-top:1px solid var(--line);border-bottom:1px solid var(--line)}
.meta div{padding:3mm 3.5mm 3.2mm 0;font-size:8.2pt;line-height:1.42;color:var(--text)}
.meta div+div{padding-left:3.5mm;border-left:1px solid var(--line)}
.label{display:block;margin-bottom:1mm;font-family:var(--tag);font-weight:500;font-size:6.6pt;
  letter-spacing:.12em;text-transform:uppercase;color:var(--faint)}

/* titres */
h2{margin:0 0 4mm;font-family:var(--display);font-weight:800;font-size:18pt;line-height:1.1;
  letter-spacing:-.01em;color:var(--ink)}
h2 .num{display:inline-block;min-width:5mm;margin-right:1.6mm;color:var(--red)}
h2.spaced{margin-top:4.8mm}
.cover-h2{margin-top:7mm}
h3{margin:4.4mm 0 1.8mm;font-family:var(--display);font-weight:700;font-size:11pt;line-height:1.25;
  color:var(--ink)}
h3.first{margin-top:0}
.lead{font-size:10.3pt;line-height:1.5;color:var(--ink);margin-bottom:3mm}

/* listes */
ul{margin:0 0 2.6mm;padding:0;list-style:none}
ul.bullets li{position:relative;padding-left:5mm;margin-bottom:1.3mm}
ul.bullets li::before{content:"";position:absolute;left:.4mm;top:.64em;width:2.4mm;height:.42mm;
  background:var(--red)}
ul.checklist li{position:relative;padding-left:7mm;margin-bottom:1.25mm}
ul.checklist li::before{content:"";position:absolute;left:0;top:.2em;width:3.3mm;height:3.3mm;
  border:1.2px solid var(--rule);border-radius:.6mm;background:#fff}

/* l'essentiel */
.points{display:grid;grid-template-columns:1fr 1fr;column-gap:8mm;row-gap:3.4mm;margin:0}
.pt{display:grid;grid-template-columns:8.5mm 1fr;font-size:8.8pt;line-height:1.46}
.pt-n{font-family:var(--tag);font-weight:500;font-size:8pt;color:var(--red);letter-spacing:.04em;
  padding-top:.6mm}

/* sommaire */
.toc{margin:6mm 0 0;padding:3mm 0 0;border-top:1px solid var(--rule);list-style:none}
.toc-title{margin-bottom:2mm}
.toc li{display:flex;align-items:baseline;gap:2.5mm;font-size:9pt;line-height:1.78;color:var(--ink)}
.toc .n{font-family:var(--tag);font-weight:500;color:var(--red);width:5mm;font-size:8pt}
.toc .dots{flex:1;border-bottom:1px dotted #b9b3aa;transform:translateY(-1.1mm)}
.toc .pg{font-family:var(--tag);font-weight:500;font-size:8pt;color:var(--muted)}

/* encadré */
.callout{margin:6mm 0 0;padding:1mm 0 1mm 4.5mm;border-left:2.4px solid var(--red);
  font-size:9pt;line-height:1.55;color:#4a4e58}
.callout strong{color:var(--ink)}
.refs{margin-top:auto;padding-top:3mm;border-top:1px solid var(--line);font-size:7.4pt;line-height:1.5;
  color:var(--muted)}
.callout + .refs{margin-top:5mm}

/* tableaux */
table{width:100%;border-collapse:collapse;margin:0 0 3mm;font-size:8.5pt;line-height:1.42}
th{font-family:var(--tag);font-weight:500;font-size:6.6pt;letter-spacing:.11em;text-transform:uppercase;
  color:var(--muted);text-align:left;padding:1.8mm 2.5mm 1.8mm 0;border-bottom:1px solid var(--rule)}
td{padding:1.9mm 2.5mm 1.9mm 0;vertical-align:top;border-bottom:1px solid var(--line)}
th+th,td+td{padding-left:2.5mm}
tr:last-child td{border-bottom:1px solid var(--line)}
.t-cases td{padding-top:1.9mm;padding-bottom:1.9mm}
.t-cases td:first-child{color:var(--ink);font-weight:500}
.chip{display:inline-block;padding:.35mm 1.8mm .45mm;border-radius:10mm;font-family:var(--tag);
  font-weight:500;font-size:7pt;letter-spacing:.02em;white-space:nowrap}
.chip.no{background:var(--red-tint);color:var(--red-dark)}
.chip.yes{background:#e3f1e8;color:#1c6b43}
.chip.cond{background:#fbefd9;color:#7d4a00}
.chip.out{background:#ecebe8;color:#55585f}

/* annexe */
.annex-head{display:flex;align-items:center;gap:3mm;padding-bottom:2mm;border-bottom:1.3px solid var(--rule)}
.tag{display:inline-block;background:var(--red);color:#fff;font-family:var(--tag);font-weight:500;
  font-size:7.6pt;letter-spacing:.13em;text-transform:uppercase;padding:1mm 2.2mm .9mm}
.annex-head h2{margin:0;font-size:16.5pt}
.annex-head h2,.cover-h2{display:block}
.annex-note{margin:2.4mm 0 4mm;font-family:var(--tag);font-weight:500;font-size:7pt;line-height:1.55;
  letter-spacing:.01em;color:var(--muted)}
.form{background:var(--warm);border:1px solid var(--rule);padding:5mm 5.6mm 4.6mm;font-size:8.9pt;
  line-height:1.56;color:var(--ink)}
.form p{margin:0 0 1.95mm}
.form p.gap{margin-top:3.8mm}
.form p.ident{line-height:1.72;margin-bottom:2.6mm;padding-bottom:2.2mm;border-bottom:1px solid #dcd5ca}
.nw{white-space:nowrap}
.choices{margin:.4mm 0 2.4mm}
.choices li{display:flex;gap:2.4mm;align-items:baseline;margin-bottom:1.2mm}
.cb{display:inline-block;width:3.3mm;height:3.3mm;border:1.1px solid var(--rule);background:#fff;
  vertical-align:-.5mm;flex:none;margin-right:.6mm}
.choices .cb{transform:translateY(.4mm)}
.ph{display:inline-block;vertical-align:baseline;background:var(--red-tint);
  border-bottom:1px dotted var(--red);padding:0 1.4mm;line-height:10.5pt;white-space:nowrap;
  font-family:var(--tag);font-weight:500;font-size:6.5pt;letter-spacing:.08em;color:var(--red-dark);
  text-align:left}
.sig{margin-top:3.5mm}
.sig-line{height:8.5mm;border-bottom:1.1px dotted var(--rule)}
.reserved{margin:3.5mm 0 0;font-family:var(--tag);font-weight:500;font-size:7pt;line-height:2;color:var(--muted)}
.reserved strong{color:var(--ink);font-weight:500}
.reserved .ph{font-size:6.2pt}
"""


def render_table(b, fillable):
    cols = "".join(f'<col style="width:{w}%">' for w in b["widths"])
    head = "".join(f"<th>{esc(h)}</th>" for h in b["head"])
    rows = []
    for r in b["rows"]:
        cells = []
        for c in r:
            if isinstance(c, dict):
                cells.append(f'<td><span class="chip {c["tone"]}">{esc(c["text"])}</span></td>')
            else:
                cells.append(f"<td>{inline(c, fillable)}</td>")
        rows.append("<tr>" + "".join(cells) + "</tr>")
    return (f'<table class="t t-{b["kind"]}"><colgroup>{cols}</colgroup>'
            f"<thead><tr>{head}</tr></thead><tbody>{''.join(rows)}</tbody></table>")


def render_block(b, fillable=False) -> str:
    t = b["t"]
    if t == "cover":
        meta = "".join(f'<div><span class="label">{esc(k)}</span>{inline(v)}</div>' for k, v in b["meta"])
        return (
            f'<div class="cover-top">{logo_svg()}'
            f'<div class="cover-ref">Fiche juridique<b>{esc(CONTENT["ref"])}</b>{esc(CONTENT["version"])}</div></div>'
            f'<div class="kicker">{esc(b["kicker"])}</div>'
            f'<h1>{esc(b["title"])}</h1>'
            f'<p class="subtitle">{inline(b["subtitle"])}</p>'
            f'<div class="meta">{meta}</div>'
        )
    if t == "h2":
        num = f'<span class="num">{esc(b["num"])}</span> ' if b.get("num") else ""
        cls = []
        if b.get("spaced"):
            cls.append("spaced")
        if not b.get("num"):
            cls.append("cover-h2")
        return f'<h2 class="{" ".join(cls)}">{num}{esc(b["text"])}</h2>'
    if t == "h3":
        return f'<h3 class="{"first" if b.get("first") else ""}">{esc(b["text"])}</h3>'
    if t == "lead":
        return f'<p class="lead">{inline(b["text"])}</p>'
    if t == "p":
        return f"<p>{inline(b['text'])}</p>"
    if t == "ul":
        return '<ul class="bullets">' + "".join(f"<li>{inline(i)}</li>" for i in b["items"]) + "</ul>"
    if t == "checklist":
        return '<ul class="checklist">' + "".join(f"<li>{inline(i)}</li>" for i in b["items"]) + "</ul>"
    if t == "points":
        items = "".join(
            f'<div class="pt"><div class="pt-n">{i + 1:02d}</div><div><strong>{esc(lead)}</strong> {inline(txt)}</div></div>'
            for i, (lead, txt) in enumerate(b["items"]))
        return f'<div class="points">{items}</div>'
    if t == "toc":
        items = "".join(
            f'<li><span class="n">{esc(n)}</span><span>{esc(title)}</span><span class="dots"></span>'
            f'<span class="pg">p.&nbsp;{esc(pg)}</span></li>' for n, title, pg in b["items"])
        return f'<ol class="toc"><li class="toc-title"><span class="label">Sommaire</span></li>{items}</ol>'
    if t == "callout":
        return f'<div class="callout">{inline(b["text"])}</div>'
    if t == "refs":
        return f'<p class="refs">{inline(b["text"])}</p>'
    if t == "table":
        return render_table(b, fillable)
    if t == "annex_head":
        return (f'<div class="annex-head"><span class="tag">{esc(b["tag"])}</span>'
                f'<h2>{esc(b["title"])}</h2></div><p class="annex-note">{inline(b["note"])}</p>')
    if t == "form":
        parts = []
        for p in b["parts"]:
            if p["t"] == "ident":
                parts.append('<p class="ident">' + "<br>".join(inline(l, fillable) for l in p["lines"]) + "</p>")
            elif p["t"] == "fp":
                cls = ' class="gap"' if p.get("gap") else ""
                parts.append(f"<p{cls}>{inline(p['text'], fillable)}</p>")
            elif p["t"] == "choices":
                lis = "".join(f'<li><span class="cb" data-field="{name}"></span><span>{inline(txt)}</span></li>'
                              for name, txt in p["items"])
                parts.append(f'<ul class="choices">{lis}</ul>')
            elif p["t"] == "signature":
                parts.append(f'<div class="sig">{esc(p["label"])}'
                             f'<div class="sig-line" data-field="signature"></div></div>')
        return f'<div class="form">{"".join(parts)}</div>'
    if t == "reserved":
        return f'<p class="reserved">{inline(b["text"], fillable)}</p>'
    raise ValueError(t)


def render(inline_assets=False, fillable=False) -> str:
    total = CONTENT["total"]
    left, center = CONTENT["footer"]
    pages = []
    for i, blocks in enumerate(CONTENT["pages"], start=1):
        if fillable and i != total:
            continue
        body = "".join(render_block(b, fillable) for b in blocks)
        pages.append(
            f'<section class="page" id="p{i}"><div class="page-body">{body}</div>'
            f'<footer class="pf"><span>{esc(left)}</span><span>{esc(center)}</span>'
            f'<span>{"Annexe" if fillable else f"p.&nbsp;{i}/{total}"}</span></footer></section>')
    title = f'{CONTENT["ref"]} — {CONTENT["title"]} · Suisse Immo'
    return (
        '<!doctype html><html lang="fr"><head><meta charset="utf-8">'
        '<meta name="viewport" content="width=device-width, initial-scale=1">'
        f"<title>{esc(title)}</title>"
        f"<style>{font_faces(inline_assets)}\n{CSS}</style></head>"
        f'<body>{"".join(pages)}</body></html>'
    )


if __name__ == "__main__":
    out = Path(sys.argv[1])
    doc = render(inline_assets="--inline" in sys.argv, fillable="--fillable" in sys.argv)
    out.write_text(doc, encoding="utf-8")
    print("écrit", out, f"{len(doc) // 1024} Ko")
