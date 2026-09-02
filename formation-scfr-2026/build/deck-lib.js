/**
 * Bibliothèque de composants de slides — charte Agence SCFR.
 * Palette extraite de la plaquette commerciale SCFR (Plaquette_SCFR.pptx).
 *
 * Le moteur mesure le texte pour dimensionner les conteneurs et éviter
 * les chevauchements : aucune hauteur n'est codée en dur pour du contenu variable.
 */

const C = {
  orange:     "E07B22",
  orangeDeep: "B45E10",
  orangeSoft: "F9ECE0",
  orangePale: "FBF3EA",
  violet:     "8A79B0",
  violetSoft: "EDE9F3",
  violetDeep: "6B5B90",
  ink:        "322F2E",
  inkSoft:    "4A4644",
  gray:       "6B6663",
  grayLight:  "9A948F",
  rule:       "E3DED8",
  cream:      "FDFCFB",
  white:      "FFFFFF",
  green:      "3E7A5E",
  red:        "B33A3A",
};

const F = { head: "Arial", body: "Calibri" };

// Canevas 13.333 × 7.5
const G = {
  W: 13.333, H: 7.5,
  M: 0.62,
  get CW() { return this.W - this.M * 2; },
  titleY: 0.52,
  bodyY: 1.62,
  footY: 6.92,
  bottom: 6.98,          // limite basse sans bandeau
  bottomFoot: 6.28,      // limite basse avec bandeau de note
};

const shadow = (o = {}) => ({
  type: "outer", angle: 90, blur: 14, offset: 3, opacity: 0.10, color: "000000", ...o,
});

/* ─────────────────────────────────────────────────── mesure de texte */

// Largeur moyenne d'un glyphe, exprimée en fraction de la taille de police.
// Marge volontairement large : la césure se fait sur les mots, pas sur les glyphes,
// donc une estimation trop juste produit des chevauchements.
const GLYPH = { body: 0.525, head: 0.60 };

/** Nombre de lignes estimé après retour à la ligne automatique. */
function nLines(text, wIn, fs, face = "body") {
  const cpl = Math.max(6, Math.floor((wIn * 72) / (fs * GLYPH[face])));
  return String(text).split("\n").reduce(
    (n, para) => n + Math.max(1, Math.ceil(para.length / cpl)), 0);
}

/** Hauteur estimée d'un bloc de texte, en pouces. */
function textH(text, wIn, fs, spacing, face = "body") {
  return nLines(text, wIn, fs, face) * (spacing || fs * 1.25) / 72;
}

/** Réduit la taille de police jusqu'à ce que le texte tienne dans la hauteur donnée. */
function fitFont(text, wIn, hIn, maxFs, minFs = 9.5, face = "body") {
  for (let fs = maxFs; fs >= minFs; fs -= 0.5) {
    const sp = fs * 1.3;
    if (textH(text, wIn, fs, sp, face) <= hIn) {
      return { fontSize: fs, lineSpacing: Math.round(sp) };
    }
  }
  return { fontSize: minFs, lineSpacing: Math.round(minFs * 1.25) };
}

/** Taille de police COMMUNE à une liste de puces, garantie de tenir dans `availH`.
 *  On réduit d'abord le corps, puis la gouttière, et en dernier recours on borne
 *  les hauteurs : un débordement invisible vaut mieux qu'un chevauchement.
 *  Un ajustement par puce donnerait des corps de texte inégaux dans un même bloc. */
function fitList(items, wIn, availH, gut, maxFs, minFs = 9.5) {
  const gutMin = Math.max(0.1, gut * 0.5);
  const n = items.length;
  for (let fs = maxFs; fs >= minFs; fs -= 0.5) {
    const sp = Math.round(fs * 1.3);
    const hs = items.map((t) => textH(t, wIn, fs, sp));
    const sum = hs.reduce((a, b) => a + b, 0);
    for (let g = gut; g >= gutMin - 1e-9; g -= 0.02) {
      if (sum + g * (n - 1) <= availH) return { fontSize: fs, lineSpacing: sp, hs, gut: g };
    }
  }
  const sp = Math.round(minFs * 1.2);
  let hs = items.map((t) => textH(t, wIn, minFs, sp));
  const sum = hs.reduce((a, b) => a + b, 0);
  const budget = availH - gutMin * (n - 1);
  if (budget > 0 && sum > budget) {
    const k = budget / sum;
    hs = hs.map((h) => h * k);
  }
  return { fontSize: minFs, lineSpacing: sp, hs, gut: gutMin };
}

/** Ordonnée de départ d'un bloc de hauteur `hIn`, centré dans la zone disponible. */
function centerY(hIn, top, bottom) {
  // Biais vers le haut : un bloc strictement centré « flotte » sous le titre.
  return top + Math.max(0, (bottom - top - hIn) * 0.38);
}

/* ---------------------------------------------------------------- primitives */

function bg(slide, color) { slide.background = { color }; }

/** Grand cercle décoratif — rappel du logo SCFR (deux disques superposés). */
function orb(slide, { x, y, d, color, transparency = 88 }) {
  slide.addShape("ellipse", {
    x, y, w: d, h: d,
    fill: { color, transparency }, line: { type: "none", width: 0 },
  });
}

/** Pastille circulaire contenant un numéro ou une lettre. */
function badge(slide, { x, y, d = 0.5, text, fill, color = C.white, size = null }) {
  slide.addShape("ellipse", { x, y, w: d, h: d, fill: { color: fill }, line: { width: 0 } });
  slide.addText(String(text), {
    x, y, w: d, h: d, isTextBox: true, margin: 0,
    align: "center", valign: "middle",
    fontFace: F.head, fontSize: size || Math.round(d * 30), bold: true, color,
  });
}

/** Carte à coins arrondis. */
function card(slide, { x, y, w, h, fill = C.white, radius = 0.04, withShadow = true, line = null }) {
  slide.addShape("roundRect", {
    x, y, w, h, rectRadius: radius,
    fill: { color: fill },
    line: line || { width: 0 },
    ...(withShadow ? { shadow: shadow() } : {}),
  });
}

/* ------------------------------------------------------------------ en-têtes */

function slideTitle(slide, title, { color = C.ink, kicker = null, kickerColor = C.orange } = {}) {
  let y = G.titleY;
  if (kicker) {
    slide.addText(kicker.toUpperCase(), {
      x: G.M, y: y - 0.06, w: G.CW, h: 0.3, isTextBox: true, margin: 0,
      fontFace: F.head, fontSize: 12, bold: true, color: kickerColor, charSpacing: 1.6,
    });
    y += 0.34;
  }
  const two = nLines(title, G.CW, 32, "head") > 1;
  slide.addText(title, {
    x: G.M, y, w: G.CW, h: two ? 1.15 : 0.86, isTextBox: true, margin: 0, valign: "top",
    fontFace: F.head, fontSize: 32, bold: true, color, lineSpacing: 38,
  });
  return y + (two ? 1.2 : 0.92);   // ordonnée du premier contenu
}

function footer(slide, { left, right, color = C.grayLight }) {
  if (left) {
    slide.addText(left, {
      x: G.M, y: G.footY, w: G.CW * 0.62, h: 0.28, isTextBox: true, margin: 0,
      fontFace: F.body, fontSize: 10, color,
    });
  }
  if (right) {
    slide.addText(right, {
      x: G.M + G.CW * 0.62, y: G.footY, w: G.CW * 0.38, h: 0.28, isTextBox: true, margin: 0,
      align: "right", fontFace: F.body, fontSize: 10, color,
    });
  }
}

/** Bandeau de note en bas de slide. */
function footNote(s, text, accent = C.orange) {
  const h = nLines(text, G.CW - 0.9, 12.5) > 1 ? 0.72 : 0.56;
  const y = 7.5 - 0.42 - h;
  s.addShape("roundRect", {
    x: G.M, y, w: G.CW, h, rectRadius: 0.08,
    fill: { color: accent === C.violet ? C.violetSoft : C.orangeSoft }, line: { width: 0 },
  });
  s.addShape("ellipse", { x: G.M + 0.26, y: y + h / 2 - 0.1, w: 0.2, h: 0.2, fill: { color: accent }, line: { width: 0 } });
  s.addText(text, {
    x: G.M + 0.62, y, w: G.CW - 0.9, h, isTextBox: true, margin: 0, valign: "middle",
    fontFace: F.body, fontSize: 12.5, color: C.ink, bold: true, lineSpacing: 16,
  });
  return y;
}

/* -------------------------------------------------------------- types de slides */

/** Slide de couverture — fond anthracite. */
function coverSlide(pres, { eyebrow, titleRuns, subtitle, meta, notes }) {
  const s = pres.addSlide();
  bg(s, C.ink);
  orb(s, { x: 9.5, y: -1.6, d: 6.2, color: C.orange, transparency: 86 });
  orb(s, { x: 11.0, y: 2.2, d: 4.4, color: C.violet, transparency: 84 });

  s.addText(eyebrow.toUpperCase(), {
    x: G.M, y: 1.05, w: 8.6, h: 0.32, isTextBox: true, margin: 0,
    fontFace: F.head, fontSize: 13, bold: true, color: C.orange, charSpacing: 2,
  });
  s.addText(titleRuns, {
    x: G.M, y: 1.6, w: 8.9, h: 2.3, isTextBox: true, margin: 0, valign: "top",
    fontFace: F.head, fontSize: 50, bold: true, lineSpacing: 56,
  });
  s.addText(subtitle, {
    x: G.M, y: 4.05, w: 8.4, h: 1.0, isTextBox: true, margin: 0,
    fontFace: F.body, fontSize: 17, color: "CFC9C4", lineSpacing: 26,
  });
  meta.forEach((m, i) => {
    const x = G.M + i * 2.62;
    s.addText(m.v, {
      x, y: 5.35, w: 2.5, h: 0.5, isTextBox: true, margin: 0,
      fontFace: F.head, fontSize: 25, bold: true, color: C.white,
    });
    s.addText(m.k, {
      x, y: 5.86, w: 2.5, h: 0.3, isTextBox: true, margin: 0,
      fontFace: F.body, fontSize: 11.5, color: C.orange,
    });
  });
  footer(s, { left: "Agence SCFR — partenaire de vos ambitions", right: "Organisme certifié Qualiopi", color: "8A8380" });
  if (notes) s.addNotes(notes);
  return s;
}

/** Ouverture de journée — pleine couleur. */
function daySlide(pres, { day, title, subtitle, blocks, color = C.orange, notes }) {
  const s = pres.addSlide();
  bg(s, color);
  orb(s, { x: -1.8, y: 3.4, d: 5.6, color: C.white, transparency: 90 });
  orb(s, { x: 10.4, y: -1.2, d: 5.0, color: C.white, transparency: 92 });

  s.addText(`JOUR ${day}`, {
    x: G.M, y: 1.35, w: 6, h: 0.4, isTextBox: true, margin: 0,
    fontFace: F.head, fontSize: 14, bold: true, color: C.white, charSpacing: 3,
  });
  s.addText(title, {
    x: G.M, y: 1.85, w: 8.6, h: 1.1, isTextBox: true, margin: 0, valign: "top",
    fontFace: F.head, fontSize: 44, bold: true, color: C.white, lineSpacing: 50,
  });
  s.addText(subtitle, {
    x: G.M, y: 3.1, w: 8.0, h: 1.25, isTextBox: true, margin: 0, valign: "top",
    fontFace: F.body, fontSize: 17, color: "FFF2E6", lineSpacing: 24,
  });

  const gap = 0.23, n = blocks.length;
  const w = (G.CW - gap * (n - 1)) / n;
  const hs = blocks.map((b) => 0.5 + textH(b.t, w - 0.5, 12, 15) + 0.24);
  const h = Math.max(1.3, Math.max(...hs));
  const y = 6.55 - h;
  blocks.forEach((b, i) => {
    const x = G.M + i * (w + gap);
    s.addShape("roundRect", {
      x, y, w, h, rectRadius: 0.05,
      fill: { color: C.white, transparency: 82 }, line: { width: 0 },
    });
    s.addText(b.n, {
      x: x + 0.25, y: y + 0.18, w: w - 0.5, h: 0.34, isTextBox: true, margin: 0,
      fontFace: F.head, fontSize: 18, bold: true, color: C.white,
    });
    s.addText(b.t, {
      x: x + 0.25, y: y + 0.56, w: w - 0.5, h: h - 0.74, isTextBox: true, margin: 0, valign: "top",
      fontFace: F.body, fontSize: 12, color: "FFF6EE", lineSpacing: 15,
    });
  });
  if (notes) s.addNotes(notes);
  return s;
}

/** Ouverture de module. */
function moduleSlide(pres, { num, title, points, duration, color = C.orange, notes }) {
  const s = pres.addSlide();
  bg(s, C.cream);
  orb(s, { x: 10.9, y: -1.1, d: 4.2, color, transparency: 93 });

  badge(s, { x: G.M, y: 1.7, d: 0.98, text: num, fill: color, size: 34 });
  s.addText("MODULE", {
    x: G.M + 1.28, y: 1.76, w: 6, h: 0.28, isTextBox: true, margin: 0,
    fontFace: F.head, fontSize: 12, bold: true, color: C.gray, charSpacing: 2.2,
  });
  s.addText(title, {
    x: G.M + 1.28, y: 2.06, w: 8.4, h: 0.95, isTextBox: true, margin: 0, valign: "top",
    fontFace: F.head, fontSize: 34, bold: true, color: C.ink, lineSpacing: 40,
  });
  if (duration) {
    s.addShape("roundRect", {
      x: 10.55, y: 1.82, w: 2.16, h: 0.74, rectRadius: 0.1,
      fill: { color: C.white }, line: { color: C.rule, width: 1 },
    });
    s.addText(duration, {
      x: 10.55, y: 1.82, w: 2.16, h: 0.74, isTextBox: true, margin: 0,
      align: "center", valign: "middle", fontFace: F.head, fontSize: 17, bold: true, color: C.ink,
    });
  }

  const pw = 11.2, GUT = 0.24;
  const fit = fitList(points, pw, G.bottom - 3.55 - GUT * (points.length - 1), GUT, 16.5, 13);
  const total = fit.hs.reduce((a, b) => a + b, 0) + GUT * (points.length - 1);
  let y = centerY(total, 3.55, G.bottom);
  points.forEach((p, i) => {
    s.addShape("ellipse", {
      x: G.M + 0.16, y: y + 0.15, w: 0.14, h: 0.14,
      fill: { color }, line: { type: "none", width: 0 },
    });
    s.addText(p, {
      x: G.M + 0.55, y: y - 0.03, w: pw, h: fit.hs[i] + 0.1, isTextBox: true, margin: 0, valign: "top",
      fontFace: F.body, color: C.inkSoft, fontSize: fit.fontSize, lineSpacing: fit.lineSpacing,
    });
    y += fit.hs[i] + fit.gut;
  });
  if (notes) s.addNotes(notes);
  return s;
}


/** Cartes en grille : 2, 3 ou 4 colonnes. Hauteur ajustée au contenu. */
function cardsSlide(pres, { kicker, title, cards, cols = 3, accent = C.orange, foot, notes }) {
  const s = pres.addSlide();
  bg(s, C.cream);
  const top = slideTitle(s, title, { kicker });
  const bottom = foot ? G.bottomFoot : G.bottom;

  const rows = Math.ceil(cards.length / cols);
  const gap = 0.3;
  const w = (G.CW - gap * (cols - 1)) / cols;
  const tw = w - 1.2;          // largeur du titre de carte
  const dw = w - 0.6;          // largeur de la description

  // hauteur requise par rangée
  const rowH = [];
  for (let r = 0; r < rows; r++) {
    let mx = 0;
    for (let i = r * cols; i < Math.min((r + 1) * cols, cards.length); i++) {
      const c = cards[i];
      const th = Math.max(0.5, textH(c.t, tw, 15.5, 19, "head"));
      mx = Math.max(mx, 0.3 + th + 0.16 + textH(c.d, dw, 13, 17) + 0.34);
    }
    rowH.push(mx);
  }
  const totalH = rowH.reduce((a, b) => a + b, 0) + gap * (rows - 1);
  const maxH = bottom - top;
  const scale = totalH > maxH ? maxH / totalH : 1;
  let y = centerY(Math.min(totalH, maxH), top, bottom);

  cards.forEach((c, i) => {
    const r = Math.floor(i / cols), col = i % cols;
    const h = rowH[r] * scale;
    const yy = y + rowH.slice(0, r).reduce((a, b) => a + b * scale, 0) + gap * r;
    const x = G.M + col * (w + gap);
    card(s, { x, y: yy, w, h });
    const col2 = c.color || accent;
    const th = Math.max(0.5, textH(c.t, tw, 15.5, 19, "head"));
    badge(s, { x: x + 0.3, y: yy + 0.3, d: 0.46, text: c.n || (i + 1), fill: col2, size: 15 });
    s.addText(c.t, {
      x: x + 0.9, y: yy + 0.3, w: tw, h: th, isTextBox: true, margin: 0, valign: "middle",
      fontFace: F.head, fontSize: 15.5, bold: true, color: C.ink, lineSpacing: 19,
    });
    const dTop = yy + 0.3 + th + 0.16;
    const dFit = fitFont(c.d, dw, yy + h - 0.28 - dTop, 13, 10.5);
    s.addText(c.d, {
      x: x + 0.3, y: dTop, w: dw, h: yy + h - 0.28 - dTop,
      isTextBox: true, margin: 0, valign: "top",
      fontFace: F.body, color: C.inkSoft, ...dFit,
    });
  });
  if (foot) footNote(s, foot, accent);
  if (notes) s.addNotes(notes);
  return s;
}

/** Grands chiffres. */
function statsSlide(pres, { kicker, title, stats, lead, foot, dark = false, notes }) {
  const s = pres.addSlide();
  bg(s, dark ? C.ink : C.cream);
  let top = slideTitle(s, title, { kicker, color: dark ? C.white : C.ink });
  if (dark) orb(s, { x: 10.6, y: 4.4, d: 4.6, color: C.orange, transparency: 90 });
  const bottom = foot ? G.bottomFoot : G.bottom;

  if (lead) {
    const h = textH(lead, G.CW, 16, 22);
    s.addText(lead, {
      x: G.M, y: top, w: G.CW, h, isTextBox: true, margin: 0, valign: "top",
      fontFace: F.body, fontSize: 16, color: dark ? "CFC9C4" : C.gray, lineSpacing: 22,
    });
    top += h + 0.3;
  }

  const n = stats.length, gap = 0.3;
  const w = (G.CW - gap * (n - 1)) / n;
  const iw = w - 0.56;
  const need = Math.max(...stats.map((st) =>
    0.34 + (st.small ? 0.62 : 0.78) + 0.12 + textH(st.k, iw, 14, 18, "head")
    + (st.d ? 0.1 + textH(st.d, iw, 12.5, 16) : 0) + 0.34));
  const h = Math.min(need, bottom - top);
  const y = centerY(h, top, bottom);

  stats.forEach((st, i) => {
    const x = G.M + i * (w + gap);
    card(s, { x, y, w, h, fill: dark ? "3E3A38" : C.white, withShadow: !dark });
    const vH = st.small ? 0.62 : 0.78;
    s.addText(st.v, {
      x: x + 0.28, y: y + 0.34, w: iw, h: vH, isTextBox: true, margin: 0, valign: "middle",
      fontFace: F.head, fontSize: st.small ? 30 : 40, bold: true, color: st.color || C.orange,
    });
    const kH = textH(st.k, iw, 14, 18, "head");
    s.addText(st.k, {
      x: x + 0.28, y: y + 0.34 + vH + 0.12, w: iw, h: kH, isTextBox: true, margin: 0, valign: "top",
      fontFace: F.head, fontSize: 14, bold: true, color: dark ? C.white : C.ink, lineSpacing: 18,
    });
    if (st.d) {
      const dTop = y + 0.34 + vH + 0.12 + kH + 0.1;
      const dh = y + h - 0.28 - dTop;
      s.addText(st.d, {
        x: x + 0.28, y: dTop, w: iw, h: dh, isTextBox: true, margin: 0, valign: "top",
        fontFace: F.body, color: dark ? "B8B2AE" : C.gray, ...fitFont(st.d, iw, dh, 12.5, 10),
      });
    }
  });
  if (foot) footNote(s, foot, dark ? C.orange : C.orange);
  if (notes) s.addNotes(notes);
  return s;
}

/** Deux colonnes comparatives — puces mesurées, corps de texte uniforme. */
function compareSlide(pres, { kicker, title, left, right, foot, notes }) {
  const s = pres.addSlide();
  bg(s, C.cream);
  const top = slideTitle(s, title, { kicker });
  const bottom = foot ? G.bottomFoot : G.bottom;

  const w = (G.CW - 0.36) / 2;
  const iw = w - 1.05;
  const HEAD = 0.78, GUT = 0.22, PAD = 0.32;

  const sizes = [left, right].map((side) => {
    const fs = side.dense ? 13 : 14, sp = side.dense ? 17 : 18;
    const hs = side.items.map((it) => textH(it, iw, fs, sp));
    return { hs, total: HEAD + PAD * 2 + hs.reduce((a, b) => a + b, 0) + GUT * (side.items.length - 1) };
  });
  const h = Math.min(Math.max(sizes[0].total, sizes[1].total), bottom - top);
  const y = centerY(h, top, bottom);

  [left, right].forEach((side, i) => {
    const x = G.M + i * (w + 0.36);
    const col = side.color || (i === 0 ? C.orange : C.violet);
    card(s, { x, y, w, h });
    s.addShape("roundRect", {
      x, y, w, h: HEAD, rectRadius: 0.04, fill: { color: col }, line: { width: 0 },
    });
    s.addText(side.title, {
      x: x + 0.34, y, w: w - 0.68, h: HEAD, isTextBox: true, margin: 0, valign: "middle",
      fontFace: F.head, fontSize: 18, bold: true, color: C.white,
    });
    const avail = h - HEAD - PAD * 2;
    const fit = fitList(side.items, iw, avail, GUT, side.dense ? 13 : 14);
    let yy = y + HEAD + PAD;
    side.items.forEach((it, j) => {
      s.addShape("ellipse", {
        x: x + 0.36, y: yy + 0.14, w: 0.13, h: 0.13,
        fill: { color: col }, line: { type: "none", width: 0 },
      });
      s.addText(it, {
        x: x + 0.68, y: yy - 0.04, w: iw, h: fit.hs[j] + 0.1, isTextBox: true, margin: 0, valign: "top",
        fontFace: F.body, color: C.inkSoft, fontSize: fit.fontSize, lineSpacing: fit.lineSpacing,
      });
      yy += fit.hs[j] + fit.gut;
    });
  });
  if (foot) footNote(s, foot);
  if (notes) s.addNotes(notes);
  return s;
}


/** Tableau. */
function tableSlide(pres, { kicker, title, head, rows, colW, foot, lead, accent = C.orange, notes }) {
  const s = pres.addSlide();
  bg(s, C.cream);
  let top = slideTitle(s, title, { kicker });
  const bottom = foot ? G.bottomFoot : G.bottom;

  if (lead) {
    const h = textH(lead, G.CW, 15, 20);
    s.addText(lead, {
      x: G.M, y: top, w: G.CW, h, isTextBox: true, margin: 0, valign: "top",
      fontFace: F.body, fontSize: 15, color: C.gray, lineSpacing: 20,
    });
    top += h + 0.26;
  }

  const txt = (cell) => (typeof cell === "object" && cell !== null ? cell.text : cell);
  const rowHeights = rows.map((r) =>
    Math.max(...r.map((cell, ci) => textH(txt(cell), colW[ci] - 0.28, 12.5, 16))) + 0.2);
  const headH = Math.max(...head.map((hh, ci) => textH(hh, colW[ci] - 0.28, 12.5, 16, "head"))) + 0.22;
  const tableH = headH + rowHeights.reduce((a, b) => a + b, 0);
  const y = centerY(Math.min(tableH, bottom - top), top, bottom);

  const body = rows.map((r) =>
    r.map((cell, ci) => {
      const isObj = typeof cell === "object" && cell !== null;
      return {
        text: String(txt(cell)),
        options: {
          fontFace: F.body, fontSize: 12.5, color: (isObj && cell.color) || C.inkSoft,
          bold: (isObj && cell.bold) || ci === 0, valign: "middle",
          fill: { color: C.white }, margin: [7, 10, 7, 10],
        },
      };
    })
  );
  const header = head.map((hh) => ({
    text: hh,
    options: {
      fontFace: F.head, fontSize: 12.5, bold: true, color: C.white,
      fill: { color: accent }, valign: "middle", margin: [8, 10, 8, 10],
    },
  }));
  s.addTable([header, ...body], {
    x: G.M, y, w: G.CW, colW,
    border: { type: "solid", color: C.rule, pt: 1 },
    autoPage: false,
  });
  if (foot) footNote(s, foot, accent);
  if (notes) s.addNotes(notes);
  return s;
}

/** Cas pratique — profil / analyse / réponse, tous dimensionnés au contenu. */
function caseSlide(pres, { num, title, profil, situation, analyse, reponse, accent = C.violet, notes }) {
  const s = pres.addSlide();
  bg(s, C.cream);

  s.addText(`CAS PRATIQUE ${num}`, {
    x: G.M, y: G.titleY - 0.06, w: 8, h: 0.3, isTextBox: true, margin: 0,
    fontFace: F.head, fontSize: 12, bold: true, color: accent, charSpacing: 1.6,
  });
  const twoLine = nLines(title, G.CW, 30, "head") > 1;
  s.addText(title, {
    x: G.M, y: G.titleY + 0.28, w: G.CW, h: twoLine ? 1.05 : 0.72,
    isTextBox: true, margin: 0, valign: "top",
    fontFace: F.head, fontSize: 30, bold: true, color: C.ink, lineSpacing: 36,
  });

  const top = G.titleY + 0.28 + (twoLine ? 1.12 : 0.8);
  const H = G.bottom - top;

  /* colonne gauche : profil + situation */
  const LW = 4.55, lw = LW - 0.6;
  card(s, { x: G.M, y: top, w: LW, h: H });
  s.addShape("roundRect", {
    x: G.M, y: top, w: LW, h: 0.66, rectRadius: 0.04, fill: { color: accent }, line: { width: 0 },
  });
  s.addText("LE PROFIL", {
    x: G.M + 0.3, y: top, w: lw, h: 0.66, isTextBox: true, margin: 0, valign: "middle",
    fontFace: F.head, fontSize: 12.5, bold: true, color: C.white, charSpacing: 1.4,
  });
  const pH = textH(profil, lw, 15, 20, "head");
  s.addText(profil, {
    x: G.M + 0.3, y: top + 0.84, w: lw, h: pH, isTextBox: true, margin: 0, valign: "top",
    fontFace: F.head, fontSize: 15, bold: true, color: C.ink, lineSpacing: 20,
  });
  const sTop = top + 0.84 + pH + 0.26;
  const sH = top + H - 0.3 - sTop;
  s.addText(situation, {
    x: G.M + 0.3, y: sTop, w: lw, h: sH, isTextBox: true, margin: 0, valign: "top",
    fontFace: F.body, color: C.inkSoft, ...fitFont(situation, lw, sH, 13, 10),
  });

  /* colonne droite : analyse en haut, réponse en bas */
  const rx = G.M + LW + 0.35, rw = G.W - G.M - rx, aw = rw - 1.05;
  const GUT = 0.19, GAP = 0.22;
  const aNat = analyse.map((a) => textH(a, aw, 13.5, 18));
  const aNeed = 0.62 + aNat.reduce((x2, y2) => x2 + y2, 0) + GUT * (analyse.length - 1) + 0.28;
  const rNeed = 0.62 + textH(reponse, rw - 0.68, 14.5, 20) + 0.3;

  let aCard, rCard;
  if (aNeed + GAP + rNeed <= H) {
    rCard = rNeed;
    aCard = H - GAP - rCard;
  } else {
    // les deux blocs débordent : on partage au prorata de leurs besoins
    const share = aNeed / (aNeed + rNeed);
    aCard = Math.max(1.7, Math.min(H - GAP - 1.35, (H - GAP) * share));
    rCard = H - GAP - aCard;
  }

  card(s, { x: rx, y: top, w: rw, h: aCard });
  s.addText("L'ANALYSE", {
    x: rx + 0.34, y: top + 0.2, w: rw - 0.68, h: 0.28, isTextBox: true, margin: 0,
    fontFace: F.head, fontSize: 12, bold: true, color: C.gray, charSpacing: 1.4,
  });
  const aFit = fitList(analyse, aw, aCard - 0.62 - 0.28, GUT, 13.5);
  let ay = top + 0.62;
  analyse.forEach((a, i) => {
    s.addShape("ellipse", {
      x: rx + 0.36, y: ay + 0.13, w: 0.13, h: 0.13,
      fill: { color: accent }, line: { type: "none", width: 0 },
    });
    s.addText(a, {
      x: rx + 0.68, y: ay - 0.04, w: aw, h: aFit.hs[i] + 0.1, isTextBox: true, margin: 0, valign: "top",
      fontFace: F.body, color: C.inkSoft, fontSize: aFit.fontSize, lineSpacing: aFit.lineSpacing,
    });
    ay += aFit.hs[i] + aFit.gut;
  });

  const ry = top + aCard + GAP;
  card(s, { x: rx, y: ry, w: rw, h: rCard, fill: C.ink, withShadow: false });
  s.addText("LA RÉPONSE", {
    x: rx + 0.34, y: ry + 0.2, w: rw - 0.68, h: 0.28, isTextBox: true, margin: 0,
    fontFace: F.head, fontSize: 12, bold: true, color: C.orange, charSpacing: 1.4,
  });
  const rTextH = rCard - 0.62 - 0.26;
  s.addText(reponse, {
    x: rx + 0.34, y: ry + 0.6, w: rw - 0.68, h: rTextH, isTextBox: true, margin: 0, valign: "top",
    fontFace: F.body, color: C.white, ...fitFont(reponse, rw - 0.68, rTextH, 14.5, 10.5),
  });
  if (notes) s.addNotes(notes);
  return s;
}


/** Slide « point de vigilance » — pleine couleur. */
function alertSlide(pres, { kicker, title, body, points, color = C.orangeDeep, notes }) {
  const s = pres.addSlide();
  bg(s, color);
  orb(s, { x: 9.9, y: 3.9, d: 5.0, color: C.white, transparency: 93 });

  s.addText(kicker.toUpperCase(), {
    x: G.M, y: 0.92, w: 9, h: 0.32, isTextBox: true, margin: 0,
    fontFace: F.head, fontSize: 13, bold: true, color: "FFD9B3", charSpacing: 2.2,
  });
  const tH = textH(title, 11.0, 38, 44, "head");
  s.addText(title, {
    x: G.M, y: 1.38, w: 11.0, h: tH, isTextBox: true, margin: 0, valign: "top",
    fontFace: F.head, fontSize: 38, bold: true, color: C.white, lineSpacing: 44,
  });
  let y = 1.38 + tH + 0.28;
  if (body) {
    const bH = textH(body, 10.6, 17, 24);
    s.addText(body, {
      x: G.M, y, w: 10.6, h: bH, isTextBox: true, margin: 0, valign: "top",
      fontFace: F.body, fontSize: 17, color: "FFEEDC", lineSpacing: 24,
    });
    y += bH + 0.36;
  }
  const pts = points || [];
  if (pts.length) {
    const pw = 11.2, GUT = 0.18;
    const fit = fitList(pts, pw, G.bottom - y - GUT * (pts.length - 1), GUT, 15, 11);
    pts.forEach((p, i) => {
      const ih = Math.max(0.42, fit.hs[i]);
      badge(s, {
        x: G.M, y: y + Math.max(0, (ih - 0.42) / 2), d: 0.42,
        text: i + 1, fill: C.white, color, size: 14,
      });
      s.addText(p, {
        x: G.M + 0.66, y: y - 0.03, w: pw, h: ih + 0.1, isTextBox: true, margin: 0, valign: "middle",
        fontFace: F.body, color: C.white, fontSize: fit.fontSize, lineSpacing: fit.lineSpacing,
      });
      y += ih + fit.gut;
    });
  }
  if (notes) s.addNotes(notes);
  return s;
}


/** Frise chronologique horizontale. */
function timelineSlide(pres, { kicker, title, steps, lead, foot, notes }) {
  const s = pres.addSlide();
  bg(s, C.cream);
  let top = slideTitle(s, title, { kicker });
  const bottom = foot ? G.bottomFoot : G.bottom;

  if (lead) {
    const h = textH(lead, G.CW, 15, 20);
    s.addText(lead, {
      x: G.M, y: top, w: G.CW, h, isTextBox: true, margin: 0, valign: "top",
      fontFace: F.body, fontSize: 15, color: C.gray, lineSpacing: 20,
    });
    top += h + 0.24;
  }
  const n = steps.length, gap = 0.3;
  const w = (G.CW - gap * (n - 1)) / n;
  const cw = w - 0.48;
  const lineY = top + 0.44;
  const cardTop = lineY + 0.5;
  const need = Math.max(...steps.map((st) =>
    0.24 + textH(st.t, cw, 15, 19, "head") + 0.14 + textH(st.d, cw, 12.5, 17) + 0.26));
  const cardH = Math.min(need, bottom - cardTop);

  s.addShape("rect", {
    x: G.M + w / 2, y: lineY, w: G.CW - w, h: 0.035, fill: { color: C.rule }, line: { width: 0 },
  });
  steps.forEach((st, i) => {
    const x = G.M + i * (w + gap);
    const col = st.color || (st.done ? C.gray : C.orange);
    s.addShape("ellipse", {
      x: x + w / 2 - 0.24, y: lineY - 0.205, w: 0.48, h: 0.48,
      fill: { color: st.done ? C.white : col }, line: { color: col, width: 3 },
    });
    s.addText(st.date, {
      x, y: top, w, h: 0.4, isTextBox: true, margin: 0, align: "center",
      fontFace: F.head, fontSize: 15, bold: true, color: col,
    });
    card(s, { x, y: cardTop, w, h: cardH });
    const tH = textH(st.t, cw, 15, 19, "head");
    s.addText(st.t, {
      x: x + 0.24, y: cardTop + 0.24, w: cw, h: tH, isTextBox: true, margin: 0, valign: "top",
      fontFace: F.head, fontSize: 15, bold: true, color: C.ink, lineSpacing: 19,
    });
    const dTop = cardTop + 0.24 + tH + 0.14;
    const dH = cardTop + cardH - 0.24 - dTop;
    s.addText(st.d, {
      x: x + 0.24, y: dTop, w: cw, h: dH, isTextBox: true, margin: 0, valign: "top",
      fontFace: F.body, color: C.inkSoft, ...fitFont(st.d, cw, dH, 12.5, 9.5),
    });
  });
  if (foot) footNote(s, foot);
  if (notes) s.addNotes(notes);
  return s;
}

/** Liste numérotée, sur une ou deux colonnes. Hauteur garantie sans débordement. */
function listSlide(pres, { kicker, title, lead, items, accent = C.orange, foot, twoCol = false, notes }) {
  const s = pres.addSlide();
  bg(s, C.cream);
  let top = slideTitle(s, title, { kicker });
  const bottom = foot ? G.bottomFoot : G.bottom;

  if (lead) {
    const h = textH(lead, G.CW, 16, 22);
    s.addText(lead, {
      x: G.M, y: top, w: G.CW, h, isTextBox: true, margin: 0, valign: "top",
      fontFace: F.body, fontSize: 16, color: C.gray, lineSpacing: 22,
    });
    top += h + 0.3;
  }

  const cols = twoCol ? 2 : 1;
  const gap = 0.4;
  const w = (G.CW - gap * (cols - 1)) / cols;
  const iw = w - 0.72;
  const per = Math.ceil(items.length / cols);
  const norm = items.map((it) => (typeof it === "string" ? { t: it } : it));
  const avail = bottom - top;

  // Recherche du couple (corps de texte, gouttière) qui fait tenir la colonne la plus chargée.
  const tallest = (tFs, dFs, dSp, gut) => {
    let worst = 0;
    for (let c = 0; c < cols; c++) {
      const slice = norm.slice(c * per, (c + 1) * per);
      const tot = slice.reduce((a, it) =>
        a + textH(it.t, iw, tFs, Math.round(tFs * 1.27), "head")
          + (it.d ? 0.07 + textH(it.d, iw, dFs, dSp) : 0), 0)
        + gut * Math.max(0, slice.length - 1);
      worst = Math.max(worst, tot);
    }
    return worst;
  };

  let tFs = 15, dFs = 13, dSp = 17, GUT = 0.26, total = 0, fits = false;
  outer:
  for (let g of [0.26, 0.22, 0.18, 0.15, 0.12]) {
    for (let t = 15; t >= 12.5; t -= 0.5) {
      for (let d = 13; d >= 9.5; d -= 0.5) {
        const sp = Math.round(d * 1.3);
        const h = tallest(t, d, sp, g);
        if (h <= avail) { tFs = t; dFs = d; dSp = sp; GUT = g; total = h; fits = true; break outer; }
      }
    }
  }
  if (!fits) { tFs = 12.5; dFs = 9.5; dSp = 12; GUT = 0.12; total = tallest(tFs, dFs, dSp, GUT); }

  const hOf = (it) =>
    textH(it.t, iw, tFs, Math.round(tFs * 1.27), "head") + (it.d ? 0.07 + textH(it.d, iw, dFs, dSp) : 0);
  const k = total > avail ? avail / total : 1;
  const colY = new Array(cols).fill(centerY(Math.min(total, avail), top, bottom));

  norm.forEach((it, i) => {
    const c = Math.floor(i / per);
    const x = G.M + c * (w + gap);
    const yy = colY[c];
    const tH = textH(it.t, iw, tFs, Math.round(tFs * 1.27), "head");
    badge(s, { x, y: yy + 0.02, d: 0.42, text: it.n || i + 1, fill: accent, size: 14 });
    s.addText(it.t, {
      x: x + 0.62, y: yy - 0.03, w: iw, h: tH + 0.1, isTextBox: true, margin: 0, valign: "top",
      fontFace: F.head, fontSize: tFs, bold: true, color: C.ink, lineSpacing: Math.round(tFs * 1.27),
    });
    if (it.d) {
      s.addText(it.d, {
        x: x + 0.62, y: yy + tH + 0.07, w: iw, h: textH(it.d, iw, dFs, dSp) + 0.08,
        isTextBox: true, margin: 0, valign: "top",
        fontFace: F.body, color: C.inkSoft, fontSize: dFs, lineSpacing: dSp,
      });
    }
    colY[c] += hOf(it) * k + GUT * k;
  });
  if (foot) footNote(s, foot, accent);
  if (notes) s.addNotes(notes);
  return s;
}


/** Slide de question / quiz. */
function quizSlide(pres, { num, question, options, answer, explain, notes }) {
  const s = pres.addSlide();
  bg(s, C.violetSoft);
  orb(s, { x: 10.4, y: -1.4, d: 5.0, color: C.violet, transparency: 90 });

  s.addText(`QUESTION ${num}`, {
    x: G.M, y: 0.62, w: 8, h: 0.32, isTextBox: true, margin: 0,
    fontFace: F.head, fontSize: 12.5, bold: true, color: C.violetDeep, charSpacing: 1.8,
  });
  const qH = textH(question, 11.4, 27, 33, "head");
  s.addText(question, {
    x: G.M, y: 1.05, w: 11.4, h: qH, isTextBox: true, margin: 0, valign: "top",
    fontFace: F.head, fontSize: 27, bold: true, color: C.ink, lineSpacing: 33,
  });

  let y = 1.05 + qH + 0.3;
  const ow = G.CW - 1.1;
  const explainH = explain ? textH(explain, G.CW, 13.5, 18) + 0.2 : 0;
  const hs = options.map((o) => Math.max(0.62, textH(o, ow, 15, 19) + 0.24));
  const sum = hs.reduce((a, b) => a + b, 0) + 0.14 * (options.length - 1);
  const avail = G.bottom - y - explainH;
  const k = sum > avail ? avail / sum : 1;

  options.forEach((o, i) => {
    const ok = i === answer;
    const h = hs[i] * k;
    card(s, { x: G.M, y, w: G.CW, h, fill: ok ? C.ink : C.white, withShadow: false });
    badge(s, {
      x: G.M + 0.22, y: y + h / 2 - 0.21, d: 0.42, text: "ABCD"[i],
      fill: ok ? C.orange : C.violetSoft, color: ok ? C.white : C.violetDeep, size: 14,
    });
    s.addText(o, {
      x: G.M + 0.84, y, w: ow, h, isTextBox: true, margin: 0, valign: "middle",
      fontFace: F.body, color: ok ? C.white : C.inkSoft, bold: ok,
      ...fitFont(o, ow, h - 0.16, 15, 10.5),
    });
    y += h + 0.14 * k;
  });
  if (explain) {
    s.addText(explain, {
      x: G.M, y: y + 0.06, w: G.CW, h: explainH, isTextBox: true, margin: 0, valign: "top",
      fontFace: F.body, fontSize: 13.5, color: C.violetDeep, italic: true, lineSpacing: 18,
    });
  }
  if (notes) s.addNotes(notes);
  return s;
}

/** Slide de clôture. */
function closingSlide(pres, { title, lines: ls, contact, notes }) {
  const s = pres.addSlide();
  bg(s, C.ink);
  orb(s, { x: -1.4, y: 4.2, d: 5.2, color: C.violet, transparency: 86 });
  orb(s, { x: 10.2, y: -1.4, d: 5.4, color: C.orange, transparency: 86 });

  s.addText(title, {
    x: G.M, y: 1.5, w: 9.4, h: 1.3, isTextBox: true, margin: 0, valign: "top",
    fontFace: F.head, fontSize: 44, bold: true, color: C.white, lineSpacing: 50,
  });
  let y = 3.15;
  ls.forEach((l) => {
    const h = Math.max(0.44, textH(l, 8.4, 15.5, 20));
    s.addShape("ellipse", { x: G.M + 0.04, y: y + h / 2 - 0.07, w: 0.14, h: 0.14, fill: { color: C.orange }, line: { width: 0 } });
    s.addText(l, {
      x: G.M + 0.44, y, w: 8.4, h, isTextBox: true, margin: 0, valign: "middle",
      fontFace: F.body, fontSize: 15.5, color: "DCD6D1", lineSpacing: 20,
    });
    y += h + 0.16;
  });
  card(s, { x: 9.35, y: 3.1, w: 3.36, h: 2.7, fill: "3E3A38", withShadow: false });
  s.addText("Agence SCFR", {
    x: 9.65, y: 3.36, w: 2.8, h: 0.4, isTextBox: true, margin: 0,
    fontFace: F.head, fontSize: 18, bold: true, color: C.white,
  });
  s.addText(contact, {
    x: 9.65, y: 3.84, w: 2.8, h: 1.8, isTextBox: true, margin: 0, valign: "top",
    fontFace: F.body, fontSize: 12.5, color: "B8B2AE", lineSpacing: 18,
  });
  if (notes) s.addNotes(notes);
  return s;
}

module.exports = {
  C, F, G, bg, orb, badge, card, slideTitle, footer, footNote,
  nLines, textH, fitFont, centerY,
  coverSlide, daySlide, moduleSlide, cardsSlide, statsSlide, compareSlide,
  tableSlide, caseSlide, alertSlide, timelineSlide, listSlide, quizSlide, closingSlide,
};
