// =====================================================================
//  LA MAGIE DES SOCIÉTÉS — système de design éditorial (Typst)
//  Encre + or, une couleur par partie.
//  Fraunces (titres) / Spectral (corps) / Inter (labels)
// =====================================================================

// ---------- Palette ----------
#let INK    = rgb("#141a21")
#let PAPER  = rgb("#faf7f1")
#let GOLD   = rgb("#b08d3e")
#let MUTED  = rgb("#5a5750")
#let RULE   = rgb("#d9d2c4")

#let C-INTRO = rgb("#8a6f3a")
#let C-I     = rgb("#2f6d5f")
#let C-II    = rgb("#b05a3c")
#let C-III   = rgb("#3a5488")
#let C-IV    = rgb("#8a6d2a")

#let part-color = state("part-color", GOLD)
#let chapnum = state("chapnum", none)  // numéro de chapitre courant (none = hors numérotation)

// ---------- Helpers ----------
#let eyebrow(txt, color: GOLD) = text(
  font: "Inter", size: 8.2pt, weight: 600, tracking: 0.22em, fill: color,
)[#upper(txt)]

// ---------- Ouverture de paragraphe de chapitre ----------
#let chapstart(initial, restword, tail) = {
  set par(first-line-indent: 0pt)
  context box(baseline: 0.02em)[#text(
      font: "Fraunces", weight: 900, size: 3.0em, fill: part-color.get(),
    )[#initial]]
  h(0.05em)
  [#text(font: "Spectral", size: 0.98em, tracking: 0.02em)[#smallcaps(restword)] #tail]
}

// ---------- Callouts ----------
#let citation(body) = context block(
  width: 100%, inset: (left: 14pt, right: 12pt, top: 10pt, bottom: 10pt),
  spacing: 13pt, stroke: (left: 2pt + part-color.get()), fill: luma(249),
)[
  #set text(font: "Spectral", size: 10pt, style: "italic", fill: rgb("#3c3a34"))
  #set par(justify: true, leading: 0.72em, first-line-indent: 0pt)
  #body
]

#let exemple(body) = block(
  width: 100%, inset: 12pt, spacing: 14pt, radius: 3pt,
  fill: rgb("#f4efe3"), stroke: 0.5pt + rgb("#e3d8bf"),
)[
  #eyebrow("Exemple", color: GOLD)
  #v(4pt)
  #set text(font: "Spectral", size: 10pt)
  #set par(justify: true, leading: 0.72em, first-line-indent: 0pt)
  #body
]

// Ligne de valeurs (reliquat de compte en T / tableau) présentée proprement
#let figline(body) = block(width: 100%, spacing: 9pt, above: 9pt, below: 9pt)[
  #set align(center)
  #text(font: "Spectral", size: 11pt, fill: rgb("#33312c"), tracking: 0.12em, number-width: "tabular")[#body]
]

// ---------- Figures : carrés magiques ----------
#let magic-cell(n, s: 13mm) = box(width: s, height: s, fill: white, stroke: 0.5pt + rgb("#c9bfa8"))[
  #set align(center + horizon)
  #text(font: "Fraunces", size: 13pt, weight: 500, fill: INK)[#n]
]

#let carre-durer(caption: none) = figure(
  caption: if caption != none { caption } else [Le carré magique d'ordre 4 gravé par Albrecht Dürer dans « Melencolia » — constante magique : 34.],
)[
  #let g = (16,3,2,13, 5,10,11,8, 9,6,7,12, 4,15,14,1)
  #block(stroke: 1pt + GOLD, inset: 0pt, clip: true)[
    #grid(columns: 4, ..g.map(n => magic-cell(n)))
  ]
]

#let carre-ordre5(caption: none) = figure(
  caption: if caption != none { caption } else [Un carré d'ordre 5 numéroté selon la formule 5 × p + e + 1 (application aux plans d'expériences).],
)[
  #block(stroke: 1pt + GOLD, inset: 0pt, clip: true)[
    #grid(columns: 5, ..range(1,26).map(n => magic-cell(n, s: 11mm)))
  ]
]

// =====================================================================
//  COUVERTURE
// =====================================================================
#let couverture(t1, s1, t2, s2, auteur) = {
  page(fill: INK, margin: 0pt, header: none, footer: none, numbering: none)[
    #set text(fill: PAPER)
    #set par(justify: false)
    #place(top + left, dx: 13mm, dy: 13mm,
      rect(width: 100% - 26mm, height: 100% - 26mm, stroke: 0.6pt + GOLD.darken(5%)))

    // Motif « Triangle magique » (centre-bas) + libellés aux sommets
    #place(center + horizon, dy: 26mm,
      polygon.regular(vertices: 3, size: 56mm, stroke: 0.9pt + GOLD, fill: none))
    #place(center + horizon, dy: 34mm,
      polygon.regular(vertices: 3, size: 24mm, stroke: 0.5pt + GOLD.lighten(12%), fill: none))
    #place(center + horizon, dy: -12mm)[#align(center)[#eyebrow("Particulier", color: GOLD.lighten(30%))]]
    #place(center + horizon, dx: -37mm, dy: 50mm)[#eyebrow("Société", color: GOLD.lighten(30%))]
    #place(center + horizon, dx: 37mm, dy: 50mm)[#eyebrow("Holding", color: GOLD.lighten(30%))]

    // Haut : sur-titre + titre + sous-titre
    #place(top + center, dy: 29mm)[
      #set align(center)
      #eyebrow("L'art de l'entrepreneur", color: GOLD.lighten(22%))
      #v(9mm)
      #box(width: 134mm)[#text(font: "Fraunces", size: 36pt, weight: 900, fill: PAPER, hyphenate: false)[#t1]]
      #v(4mm)
      #text(font: "Fraunces", size: 14pt, weight: 400, style: "italic", fill: GOLD.lighten(26%))[#s1]
    ]

    // Bas : titre secondaire + auteur
    #place(bottom + center, dy: -30mm)[
      #set align(center)
      #line(length: 22mm, stroke: 0.6pt + GOLD)
      #v(6mm)
      #text(font: "Fraunces", size: 20pt, weight: 700, fill: PAPER)[#t2]
      #v(2mm)
      #text(font: "Inter", size: 9.5pt, fill: GOLD.lighten(28%), tracking: 0.05em)[#s2]
    ]
    #place(bottom + center, dy: -14mm)[
      #text(font: "Inter", size: 9pt, weight: 500, tracking: 0.18em, fill: PAPER.darken(6%))[#upper(auteur)]
    ]
  ]
}

// =====================================================================
//  OUVERTURE DE PARTIE
// =====================================================================
#let partie(numeral, titre, descriptif, color) = {
  part-color.update(color)
  pagebreak(weak: true)
  page(fill: color, margin: (x: 24mm, y: 30mm), header: none, footer: none, numbering: none)[
    #set text(fill: PAPER)
    #if numeral != "" {
      place(top + right, dy: -10mm, dx: 8mm,
        text(font: "Fraunces", size: 210pt, weight: 900, fill: white.transparentize(86%))[#numeral])
    }
    #v(1fr)
    #heading(level: 1, outlined: true)[#titre]
    #v(6mm)
    #block(width: 76%)[
      #set text(font: "Spectral", size: 12pt, style: "italic", fill: PAPER.darken(3%))
      #set par(leading: 0.7em, first-line-indent: 0pt)
      #descriptif
    ]
    #v(1fr)
    #line(length: 42mm, stroke: 0.8pt + white.transparentize(35%))
    #v(3mm)
    #text(font: "Inter", size: 9pt, tracking: 0.24em, fill: white.transparentize(22%))[
      #upper(if numeral != "" [Partie #numeral] else [Ouverture])
    ]
  ]
}

// =====================================================================
//  SOMMAIRE
// =====================================================================
#let sommaire() = {
  pagebreak(weak: true)
  page(header: none, footer: none, numbering: none)[
    #v(8mm)
    #text(font: "Fraunces", size: 30pt, weight: 900, fill: INK)[Sommaire]
    #v(3mm)
    #line(length: 42mm, stroke: 1pt + GOLD)
    #v(9mm)
    #show outline.entry.where(level: 1): it => {
      v(11pt, weak: true)
      set text(font: "Fraunces", size: 12.5pt, weight: 700, fill: INK)
      it
    }
    #show outline.entry.where(level: 2): it => {
      set text(font: "Spectral", size: 10.5pt, fill: rgb("#37342d"))
      it
    }
    #outline(title: none, depth: 2, indent: 14pt)
  ]
}

// =====================================================================
//  CONFIG GLOBALE
// =====================================================================
#let livre(titre-court: "La magie des sociétés", doc) = {
  set document(title: "La magie des sociétés — Le triangle magique", author: "LEMAIRE")
  set text(font: "Spectral", size: 10.8pt, fill: INK, lang: "fr", region: "fr", hyphenate: true)
  set par(justify: true, leading: 0.72em, spacing: 1.55em,
          first-line-indent: (amount: 0.9em, all: false))

  set page(
    width: 160mm, height: 240mm,
    margin: (inside: 22mm, outside: 17mm, top: 24mm, bottom: 22mm),
    binding: left, numbering: "1", number-align: center + bottom,
    header: context {
      let pg = here().page()
      let on-page = query(heading).filter(h => h.location().page() == pg)
      if on-page.any(h => h.level <= 2) { return }
      let chs = query(heading.where(level: 2).before(here()))
      let ch = if chs.len() > 0 { chs.last().body } else { none }
      set text(font: "Inter", size: 8pt, fill: MUTED, tracking: 0.12em)
      if calc.even(pg) { upper(titre-court) }
      else if ch != none { align(right)[#upper(ch)] }
    },
    footer: context {
      let pg = here().page()
      set text(font: "Inter", size: 9pt, fill: MUTED)
      align(center)[#pg]
    },
  )

  // Niveau 1 = PARTIE (mise en page par partie())
  show heading.where(level: 1): it => {
    set text(font: "Fraunces", size: 33pt, weight: 900, fill: PAPER)
    set par(leading: 0.34em, justify: false)
    block(width: 84%, below: 0pt)[#it.body]
  }
  // Niveau 2 = CHAPITRE
  show heading.where(level: 2): it => {
    pagebreak(weak: true)
    context {
      let pc = part-color.get()
      let cn = chapnum.get()
      v(15mm)
      block(spacing: 0pt, breakable: false)[
        #if cn != none {
          eyebrow("Chapitre " + cn, color: pc)
          v(5mm)
        }
        #set text(font: "Fraunces", size: 27pt, weight: 700, fill: INK)
        #set par(leading: 0.36em, justify: false)
        #it.body
        #v(5mm)
        #line(length: 32mm, stroke: 1.2pt + pc)
      ]
      v(9mm)
    }
  }
  // Niveau 3 = SECTION (bien décollée du texte précédent, proche du texte suivant)
  show heading.where(level: 3): it => {
    block(above: 22pt, below: 7pt, breakable: false)[
      #set text(font: "Fraunces", size: 14.5pt, weight: 600, fill: INK)
      #set par(justify: false)
      #it.body
    ]
  }
  // Niveau 4 = SOUS-SECTION
  show heading.where(level: 4): it => context {
    block(above: 17pt, below: 5pt, breakable: false)[
      #text(font: "Inter", size: 9.5pt, weight: 700, tracking: 0.05em, fill: part-color.get())[#it.body]
    ]
  }

  // Figures : bien aérées, texte détaché de la légende
  set figure(gap: 10pt)
  show figure: set block(above: 15pt, below: 18pt)
  show figure.caption: set text(font: "Inter", size: 8.5pt, fill: MUTED)

  doc
}
