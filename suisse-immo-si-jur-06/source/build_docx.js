// Génère le Word (.docx) de la fiche SI-JUR-06 à partir de content.json.
// Usage : node build_docx.js <sortie.docx> [--form]
//   --form  le formulaire seul, sur une page, sans pied de page ni note
//
// Polices de la marque intégrées au fichier (normal + gras), champs à compléter
// en contrôles de contenu Word, cases à cocher cliquables.

const fs = require('fs');
const path = require('path');
const JSZip = require('jszip');
const {
  Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell, WidthType, BorderStyle,
  ShadingType, AlignmentType, TabStopType, LeaderType, Footer, PageNumber, ImageRun, CheckBox,
  LevelFormat, TableLayoutType, VerticalAlign, CharacterSet,
} = require('docx');

const HERE = __dirname;
const C = JSON.parse(fs.readFileSync(path.join(HERE, 'content.json'), 'utf8'));
const OUT = process.argv[2] || 'doc.docx';
const FORM_ONLY = process.argv.includes('--form');

const MM = 56.6929;
const mm = (v) => Math.round(v * MM);
const PAGE_W = 11906;
const MARGIN_X = mm(18);
const TEXT_W = PAGE_W - 2 * MARGIN_X;

const COL = {
  ink: '15161B', text: '25272E', muted: '5D626D', faint: '858A95', line: 'E4DFD7', rule: '373737',
  red: 'CC0017', redDark: 'A10012', redTint: 'FBE9EB', warm: 'F6F3EE', callout: '4A4E58',
};
const F = { body: 'Inter', display: 'Bricolage Grotesque', tag: 'Space Grotesk' };
// Interligne exact, calé sur le line-height CSS (Word multiplierait la hauteur de ligne propre à la police).
const EX = (pt, mult) => ({ line: Math.round(pt * mult * 20), lineRule: 'exact' });
const AUTO = { line: 240, lineRule: 'auto' };

const NONE = { style: BorderStyle.NONE, size: 0, color: 'FFFFFF' };
const NO_BORDERS = { top: NONE, bottom: NONE, left: NONE, right: NONE, insideHorizontal: NONE, insideVertical: NONE };
const CELL_NO_BORDERS = { top: NONE, bottom: NONE, left: NONE, right: NONE };

// --- texte --------------------------------------------------------------------

// Balisage léger → runs Word. Les champs deviennent des marqueurs remplacés
// après coup par des contrôles de contenu (voir finalize()).
function runs(str, style = {}) {
  const { boldColor, ...base } = style;
  const out = [];
  const re = /\*\*(.+?)\*\*|\[\[([a-z_]+)(?:\|([^\]]+))?\]\]|\{\{cb:([a-z_]+)\}\}/g;
  let last = 0;
  let m;
  while ((m = re.exec(str))) {
    if (m.index > last) out.push(new TextRun({ ...base, text: str.slice(last, m.index) }));
    if (m[1] !== undefined) out.push(new TextRun({ ...base, text: m[1], bold: true, color: boldColor || COL.ink }));
    else if (m[2]) out.push(placeholder(m[2], m[3]));
    else if (m[4]) {
      out.push(checkbox());
      if (str[re.lastIndex] === ' ') {
        out.push(new TextRun({ ...base, text: '\u00a0' }));
        re.lastIndex += 1;
      }
    }
    last = re.lastIndex;
  }
  if (last < str.length) out.push(new TextRun({ ...base, text: str.slice(last) }));
  return out;
}

function placeholder(name, disp) {
  const label = `[${(disp || 'À COMPLÉTER').replace(/ /g, '\u00a0')}]`;
  const width = (C.fields[name] || { mm: 30 }).mm;
  // Space Grotesk 6,5 pt en capitales espacées : ~1,7 mm par signe, ~0,9 mm par espace insécable.
  const pad = Math.max(0, Math.round((width - 2.6 - label.length * 1.62) / 0.92));
  return new TextRun({ text: `⟦PH:${name}⟧${label}${' '.repeat(pad)}⟦/PH⟧` });
}

function checkbox() {
  return new CheckBox({
    checked: false,
    checkedState: { value: '2612', font: 'MS Gothic' },
    uncheckedState: { value: '2610', font: 'MS Gothic' },
  });
}

const body = (extra = {}) => ({ font: F.body, size: 18, color: COL.text, ...extra });
const tag = (extra = {}) => ({ font: F.tag, size: 13, color: COL.faint, allCaps: true, characterSpacing: 15, ...extra });

function p(children, opts = {}) {
  return new Paragraph({ children, ...opts });
}

// --- blocs --------------------------------------------------------------------

function cover(b) {
  const logo = new ImageRun({
    type: 'png',
    data: fs.readFileSync(path.join(HERE, 'generated/docx/logo-suisse-immo.png')),
    transformation: { width: 196, height: 47 },
    altText: { title: 'Suisse Immo', description: 'Logo Suisse Immo', name: 'logo' },
  });
  const right = [
    p([new TextRun({ ...tag({ size: 14, color: COL.muted }), text: 'Fiche juridique' })], { alignment: AlignmentType.RIGHT }),
    p([new TextRun({ font: F.tag, size: 22, color: COL.ink, characterSpacing: 12, text: C.ref })],
      { alignment: AlignmentType.RIGHT, spacing: { before: 20, after: 20, ...EX(11, 1.3) } }),
    p([new TextRun({ ...tag({ size: 14, color: COL.muted }), text: C.version })], { alignment: AlignmentType.RIGHT }),
  ];
  const head = new Table({
    width: { size: TEXT_W, type: WidthType.DXA },
    columnWidths: [TEXT_W / 2, TEXT_W / 2],
    layout: TableLayoutType.FIXED,
    borders: { ...NO_BORDERS, bottom: { style: BorderStyle.SINGLE, size: 6, color: COL.rule } },
    rows: [new TableRow({
      children: [
        new TableCell({ width: { size: TEXT_W / 2, type: WidthType.DXA }, borders: CELL_NO_BORDERS,
          verticalAlign: VerticalAlign.CENTER, margins: { bottom: mm(5), left: 0, right: 0 },
          children: [p([logo], { spacing: AUTO })] }),
        new TableCell({ width: { size: TEXT_W / 2, type: WidthType.DXA }, borders: CELL_NO_BORDERS,
          verticalAlign: VerticalAlign.CENTER, margins: { bottom: mm(5), left: 0, right: 0 }, children: right }),
      ],
    })],
  });

  const w = [1.25, 1.45, 0.8, 0.8];
  const tot = w.reduce((a, c) => a + c, 0);
  const widths = w.map((x) => Math.floor((TEXT_W * x) / tot));
  widths[3] += TEXT_W - widths.reduce((a, c) => a + c, 0);
  const light = { style: BorderStyle.SINGLE, size: 4, color: COL.line };
  const meta = new Table({
    width: { size: TEXT_W, type: WidthType.DXA },
    columnWidths: widths,
    layout: TableLayoutType.FIXED,
    borders: { ...NO_BORDERS, top: light, bottom: light, insideVertical: light },
    rows: [new TableRow({
      children: b.meta.map(([k, v], i) => new TableCell({
        width: { size: widths[i], type: WidthType.DXA },
        margins: { top: mm(3), bottom: mm(3), left: i ? mm(3.5) : 0, right: mm(3) },
        borders: { top: light, bottom: light, left: i ? light : NONE, right: NONE },
        children: [
          p([new TextRun({ ...tag(), text: k })], { spacing: { after: 40 } }),
          p(runs(v, body({ size: 16 })), { spacing: { ...EX(8, 1.42), after: 0 } }),
        ],
      })),
    })],
  });

  return [
    head,
    p([new TextRun({ ...tag({ size: 15, color: COL.red, characterSpacing: 22 }), text: b.kicker })],
      { spacing: { before: mm(7), after: mm(2.2), ...EX(7.5, 1.4) } }),
    p([new TextRun({ font: F.display, bold: true, size: 68, color: COL.ink, text: b.title })],
      { spacing: { after: mm(3), ...EX(34, 1.04) } }),
    p(runs(b.subtitle, { font: F.display, size: 25, color: '3B3E46' }),
      { spacing: { after: mm(6.5), ...EX(12.5, 1.36) }, indent: { right: mm(16) } }),
    meta,
  ];
}

function h2(b) {
  const children = [];
  if (b.num) {
    children.push(new TextRun({ font: F.display, bold: true, size: 36, color: COL.red, text: b.num }));
    children.push(new TextRun({ font: F.display, bold: true, size: 36, text: '\t' }));
  }
  children.push(new TextRun({ font: F.display, bold: true, size: 36, color: COL.ink, text: b.text }));
  const before = b.num ? (b.spaced ? mm(5) : 0) : mm(7);
  return [p(children, {
    keepNext: true,
    spacing: { before, after: mm(3.6), ...EX(18, 1.15) },
    tabStops: [{ type: TabStopType.LEFT, position: mm(8) }],
  })];
}

function h3(b) {
  return [p([new TextRun({ font: F.display, bold: true, size: 22, color: COL.ink, text: b.text })],
    { keepNext: true, spacing: { before: b.first ? 0 : mm(4.2), after: mm(1.6), ...EX(11, 1.25) } })];
}

function table(b) {
  const tot = b.widths.reduce((a, c) => a + c, 0);
  const widths = b.widths.map((x) => Math.floor((TEXT_W * x) / tot));
  widths[widths.length - 1] += TEXT_W - widths.reduce((a, c) => a + c, 0);
  const rule = { style: BorderStyle.SINGLE, size: 6, color: COL.rule };
  const light = { style: BorderStyle.SINGLE, size: 4, color: COL.line };
  const cell = (children, i, border) => new TableCell({
    width: { size: widths[i], type: WidthType.DXA },
    borders: { top: NONE, left: NONE, right: NONE, bottom: border },
    margins: { top: mm(1.7), bottom: mm(1.7), left: i ? mm(2.5) : 0, right: mm(2.5) },
    children,
  });
  const head = new TableRow({
    tableHeader: true,
    children: b.head.map((h, i) => cell([p([new TextRun({ ...tag({ color: COL.muted }), text: h })])], i, rule)),
  });
  const chipStyle = {
    no: [COL.redTint, COL.redDark], yes: ['E3F1E8', '1C6B43'], cond: ['FBEFD9', '7D4A00'], out: ['ECEBE8', '55585F'],
  };
  const rows = b.rows.map((r) => new TableRow({
    cantSplit: true,
    children: r.map((c, i) => {
      if (typeof c === 'object') {
        const [fill, color] = chipStyle[c.tone];
        return cell([p([new TextRun({ font: F.tag, size: 14, color, text: ` ${c.text} `,
          shading: { type: ShadingType.CLEAR, color: 'auto', fill } })])], i, light);
      }
      const style = b.kind === 'cases' && i === 0 ? body({ size: 17, color: COL.ink }) : body({ size: 17 });
      return cell([p(runs(c, style), { spacing: { ...EX(8.5, 1.42), after: 0 } })], i, light);
    }),
  }));
  return [new Table({
    width: { size: TEXT_W, type: WidthType.DXA },
    columnWidths: widths,
    layout: TableLayoutType.FIXED,
    borders: NO_BORDERS,
    rows: [head, ...rows],
  }), p([], { spacing: { line: 40, lineRule: 'exact', after: mm(2) } })];
}

function callout(b) {
  return [p(runs(b.text, body({ color: COL.callout })), {
    spacing: { before: mm(5), after: 0, ...EX(9, 1.55) },
    border: { left: { style: BorderStyle.SINGLE, size: 14, color: COL.red, space: 10 } },
    indent: { left: mm(3.6) },
  })];
}

function points(b) {
  const half = TEXT_W / 2;
  const rows = [];
  for (let i = 0; i < b.items.length; i += 2) {
    rows.push(new TableRow({
      children: [0, 1].map((j) => {
        const [lead, txt] = b.items[i + j];
        return new TableCell({
          width: { size: half, type: WidthType.DXA },
          borders: CELL_NO_BORDERS,
          margins: { left: j ? mm(4) : 0, right: j ? 0 : mm(4), bottom: mm(3) },
          children: [p([
            new TextRun({ font: F.tag, size: 16, color: COL.red, text: String(i + j + 1).padStart(2, '0') }),
            new TextRun({ text: '\t' }),
            new TextRun({ ...body({ size: 17, color: COL.ink }), bold: true, text: lead }),
            new TextRun({ ...body({ size: 17 }), text: ' ' }),
            ...runs(txt, body({ size: 17 })),
          ], {
            tabStops: [{ type: TabStopType.LEFT, position: mm(8.5) }],
            indent: { left: mm(8.5), hanging: mm(8.5) },
            spacing: { ...EX(8.5, 1.46), after: 0 },
          })],
        });
      }),
    }));
  }
  return [new Table({
    width: { size: TEXT_W, type: WidthType.DXA },
    columnWidths: [half, half],
    layout: TableLayoutType.FIXED,
    borders: NO_BORDERS,
    rows,
  })];
}

function toc(b) {
  const out = [p([new TextRun({ ...tag(), text: 'Sommaire' })], {
    spacing: { before: mm(3), after: mm(1.5) },
    border: { top: { style: BorderStyle.SINGLE, size: 6, color: COL.rule, space: 8 } },
  })];
  for (const [n, title, pg] of b.items) {
    out.push(p([
      new TextRun({ font: F.tag, size: 16, color: COL.red, text: n }),
      new TextRun({ text: '\t' }),
      new TextRun({ ...body({ color: COL.ink }), text: title }),
      new TextRun({ text: '\t' }),
      new TextRun({ font: F.tag, size: 16, color: COL.muted, text: `p. ${pg}` }),
    ], {
      tabStops: [{ type: TabStopType.LEFT, position: mm(7) },
        { type: TabStopType.RIGHT, position: TEXT_W, leader: LeaderType.DOT }],
      spacing: { after: 0, ...EX(9, 1.78) },
    }));
  }
  return out;
}

function annexHead(b) {
  if (FORM_ONLY) {
    return [p([new TextRun({ font: F.display, bold: true, size: 36, color: COL.ink, text: b.title })], {
      spacing: { after: mm(5.5), ...EX(18, 1.25) },
      border: { bottom: { style: BorderStyle.SINGLE, size: 8, color: COL.rule, space: 5 } },
    })];
  }
  return [
    p([
      new TextRun({ font: F.tag, size: 15, color: 'FFFFFF', allCaps: true, characterSpacing: 24, text: ` ${b.tag} `,
        shading: { type: ShadingType.CLEAR, color: 'auto', fill: COL.red } }),
      new TextRun({ font: F.display, size: 33, text: '  ' }),
      new TextRun({ font: F.display, bold: true, size: 33, color: COL.ink, text: b.title }),
    ], {
      spacing: { after: mm(2.2), ...EX(16.5, 1.3) },
      border: { bottom: { style: BorderStyle.SINGLE, size: 8, color: COL.rule, space: 4 } },
    }),
    p(runs(b.note, { font: F.tag, size: 14, color: COL.muted }), { spacing: { after: mm(3.5), ...EX(7, 1.55) } }),
  ];
}

function form(b) {
  const fs9 = body({ size: 17, color: COL.ink });
  // Page dédiée : plus d'air entre les lignes et pour la signature.
  const L = FORM_ONLY ? { ident: 1.85, fp: 1.6, choice: 1.55, after: 2.3, choiceAfter: 1.8, sig: 12.5 }
    : { ident: 1.72, fp: 1.56, choice: 1.5, after: 1.9, choiceAfter: 1.1, sig: 8 };
  const children = [];
  for (const part of b.parts) {
    if (part.t === 'ident') {
      part.lines.forEach((l, i) => {
        const last = i === part.lines.length - 1;
        children.push(p(runs(l, fs9), {
          spacing: { after: last ? mm(2.4) : 0, ...EX(8.5, L.ident) },
          border: last ? { bottom: { style: BorderStyle.SINGLE, size: 4, color: 'DCD5CA', space: 5 } } : undefined,
        }));
      });
    } else if (part.t === 'fp') {
      children.push(p(runs(part.text, fs9), {
        spacing: { before: part.gap ? mm(FORM_ONLY ? 3 : 2) : 0, after: mm(L.after), ...EX(8.5, L.fp) },
      }));
    } else if (part.t === 'choices') {
      for (const [, txt] of part.items) {
        children.push(p([checkbox(), new TextRun({ text: '\t' }), ...runs(txt, fs9)], {
          tabStops: [{ type: TabStopType.LEFT, position: mm(6) }],
          indent: { left: mm(6), hanging: mm(6) },
          spacing: { after: mm(L.choiceAfter), ...EX(8.5, L.choice) },
        }));
      }
    } else if (part.t === 'signature') {
      children.push(p(runs(part.label, fs9), { spacing: { before: mm(1.5), after: 0, ...EX(8.5, 1.5) } }));
      children.push(p([], {
        spacing: { before: mm(L.sig), after: 0, line: 40, lineRule: 'exact' },
        border: { bottom: { style: BorderStyle.DOTTED, size: 6, color: COL.rule, space: 1 } },
      }));
    }
  }
  const frame = { style: BorderStyle.SINGLE, size: 6, color: COL.rule };
  return [new Table({
    width: { size: TEXT_W, type: WidthType.DXA },
    columnWidths: [TEXT_W],
    layout: TableLayoutType.FIXED,
    borders: { top: frame, bottom: frame, left: frame, right: frame, insideHorizontal: NONE, insideVertical: NONE },
    rows: [new TableRow({
      cantSplit: true,
      children: [new TableCell({
        width: { size: TEXT_W, type: WidthType.DXA },
        shading: { type: ShadingType.CLEAR, color: 'auto', fill: COL.warm },
        margins: { top: mm(4.5), bottom: mm(4.2), left: mm(5.2), right: mm(5.2) },
        borders: { top: frame, bottom: frame, left: frame, right: frame },
        children,
      })],
    })],
  })];
}

function block(b) {
  switch (b.t) {
    case 'cover': return cover(b);
    case 'h2': return h2(b);
    case 'h3': return h3(b);
    case 'lead': return [p(runs(b.text, body({ size: 20, color: COL.ink })), { spacing: { after: mm(2.6), ...EX(10, 1.5) } })];
    case 'p': return [p(runs(b.text, body()), { spacing: { after: mm(2.2), ...EX(9, 1.5) } })];
    case 'ul': return b.items.map((it) => p(runs(it, body()), {
      numbering: { reference: 'tirets', level: 0 }, spacing: { after: mm(1.2), ...EX(9, 1.5) } }));
    case 'checklist': return b.items.map((it) => p([checkbox(), new TextRun({ text: '\t' }), ...runs(it, body())], {
      tabStops: [{ type: TabStopType.LEFT, position: mm(6.5) }],
      indent: { left: mm(6.5), hanging: mm(6.5) }, spacing: { after: mm(1.1), ...EX(9, 1.5) } }));
    case 'points': return points(b);
    case 'toc': return toc(b);
    case 'callout': return callout(b);
    case 'refs': return [p(runs(b.text, body({ size: 14, color: COL.muted })), {
      spacing: { before: mm(8), after: 0, ...EX(7, 1.5) },
      border: { top: { style: BorderStyle.SINGLE, size: 4, color: COL.line, space: 6 } } })];
    case 'table': return table(b);
    case 'annex_head': return annexHead(b);
    case 'form': return form(b);
    case 'reserved': return [p(runs(b.text, { font: F.tag, size: 14, color: COL.muted, boldColor: COL.ink }),
      { spacing: { before: mm(FORM_ONLY ? 4.5 : 3), ...EX(7, 2.0) } })];
    default: throw new Error(b.t);
  }
}

// --- document -------------------------------------------------------------------

function build() {
  const children = [];
  const pages = FORM_ONLY ? C.pages.slice(-1) : C.pages;
  pages.forEach((blocks, i) => {
    const els = blocks.flatMap(block);
    if (i > 0) {
      // saut de page porté par le premier paragraphe de la page
      children.push(new Paragraph({ pageBreakBefore: true, spacing: { after: 0, line: 20, lineRule: 'exact' }, children: [] }));
    }
    children.push(...els);
  });

  const [left, center] = C.footer;
  const footer = new Footer({
    children: [p([
      new TextRun({ ...tag({ size: 13 }), text: left }),
      new TextRun({ text: '\t' }),
      new TextRun({ ...tag({ size: 13 }), text: center }),
      new TextRun({ text: '\t' }),
      new TextRun({ font: F.tag, size: 13, color: COL.faint, characterSpacing: 6,
        children: ['p. ', PageNumber.CURRENT, '/', PageNumber.TOTAL_PAGES] }),
    ], {
      tabStops: [{ type: TabStopType.CENTER, position: Math.round(TEXT_W / 2) },
        { type: TabStopType.RIGHT, position: TEXT_W }],
    })],
  });

  const font = (name, file) => ({ name, data: fs.readFileSync(path.join(HERE, 'generated/docx', file)), characterSet: CharacterSet.ANSI });

  return new Document({
    creator: 'Suisse Immo',
    title: FORM_ONLY ? 'Formulaire de recueil du consentement' : `${C.ref} — ${C.title}`,
    subject: 'Prospection téléphonique : le consentement préalable',
    keywords: 'Suisse Immo, SI-JUR-06, démarchage téléphonique, consentement',
    description: 'Fiche juridique du réseau Suisse Immo et formulaire de recueil du consentement.',
    fonts: [
      font('Inter', 'Inter-Regular.ttf'),
      font('Inter__B', 'Inter-Bold.ttf'),
      font('Bricolage Grotesque', 'BricolageGrotesque-Regular.ttf'),
      font('Bricolage Grotesque__B', 'BricolageGrotesque-Bold.ttf'),
      font('Space Grotesk', 'SpaceGrotesk-Regular.ttf'),
    ],
    styles: {
      default: {
        document: {
          run: { font: F.body, size: 18, color: COL.text },
          paragraph: { spacing: { after: 0, ...EX(9, 1.5) } },
        },
      },
    },
    numbering: {
      config: [{
        reference: 'tirets',
        levels: [{
          level: 0, format: LevelFormat.BULLET, text: '–', alignment: AlignmentType.LEFT,
          style: { paragraph: { indent: { left: mm(5), hanging: mm(5) } }, run: { color: COL.red, font: F.body, bold: true } },
        }],
      }],
    },
    sections: [{
      properties: {
        page: {
          size: { width: PAGE_W, height: 16838 },
          margin: FORM_ONLY
            ? { top: mm(15), bottom: mm(12), left: MARGIN_X, right: MARGIN_X, header: mm(8), footer: mm(6) }
            : { top: mm(16), bottom: mm(19), left: MARGIN_X, right: MARGIN_X, header: mm(8), footer: mm(10) },
        },
      },
      ...(FORM_ONLY ? {} : { footers: { default: footer } }),
      children,
    }],
  });
}

// --- finitions dans le XML --------------------------------------------------------

async function finalize(buf) {
  const zip = await JSZip.loadAsync(buf);

  // 1. Champs → contrôles de contenu « texte brut » avec texte d'espace réservé.
  let doc = await zip.file('word/document.xml').async('string');
  const phRun = /<w:r>(?:<w:rPr>((?:(?!<\/w:rPr>).)*)<\/w:rPr>)?<w:t xml:space="preserve">([^<]*?)⟦PH:([a-z_]+)⟧([^<]*?)⟦\/PH⟧([^<]*)<\/w:t><\/w:r>/g;
  let count = 0;
  let id = 7100;
  doc = doc.replace(phRun, (all, rpr = '', before, name, label, after) => {
    count += 1;
    const typed = `<w:rFonts w:ascii="Inter" w:hAnsi="Inter" w:cs="Inter"/><w:color w:val="${COL.ink}"/><w:sz w:val="17"/><w:szCs w:val="17"/>`;
    const shown = `<w:rFonts w:ascii="Space Grotesk" w:hAnsi="Space Grotesk" w:cs="Space Grotesk"/><w:color w:val="${COL.redDark}"/>`
      + `<w:spacing w:val="12"/><w:sz w:val="13"/><w:szCs w:val="13"/><w:u w:val="dotted" w:color="${COL.red}"/>`
      + `<w:shd w:val="clear" w:color="auto" w:fill="${COL.redTint}"/>`;
    const alias = (C.fields[name] || { label: name }).label.replace(/&/g, '&amp;').replace(/"/g, '&quot;');
    const pre = before ? `<w:r>${rpr ? `<w:rPr>${rpr}</w:rPr>` : ''}<w:t xml:space="preserve">${before}</w:t></w:r>` : '';
    const post = after ? `<w:r>${rpr ? `<w:rPr>${rpr}</w:rPr>` : ''}<w:t xml:space="preserve">${after}</w:t></w:r>` : '';
    id += 1;
    return `${pre}<w:sdt><w:sdtPr><w:rPr>${typed}</w:rPr><w:alias w:val="${alias}"/><w:tag w:val="${name}"/>`
      + `<w:id w:val="${id}"/><w:showingPlcHdr/><w:text/></w:sdtPr><w:sdtContent>`
      + `<w:r><w:rPr>${shown}</w:rPr><w:t xml:space="preserve">${label}</w:t></w:r></w:sdtContent></w:sdt>${post}`;
  });
  if (doc.includes('⟦')) throw new Error('marqueur de champ non remplacé');

  // Cases à cocher : le glyphe en texte (comme Word l'écrit) plutôt qu'en w:sym,
  // que LibreOffice et Google Docs affichent mal sans la police MS Gothic.
  doc = doc.replace(/<w:r><w:sym w:char="2610" w:font="MS Gothic"\/><\/w:r>/g,
    '<w:r><w:rPr><w:rFonts w:ascii="MS Gothic" w:eastAsia="MS Gothic" w:hAnsi="MS Gothic" w:hint="eastAsia"/>'
    + `<w:color w:val="${COL.rule}"/></w:rPr><w:t>\u2610</w:t></w:r>`);
  zip.file('word/document.xml', doc);

  if (!FORM_ONLY) await rewriteFooter(zip);

  return finishFonts(zip, count);
}

// Pied de page : un run par élément de champ, chacun avec la mise en forme du texte.
async function rewriteFooter(zip) {
  let foot = await zip.file('word/footer1.xml').async('string');
  foot = foot.replace(/<w:r><w:rPr>((?:(?!<\/w:rPr>).)*)<\/w:rPr><w:t xml:space="preserve">p\.([^<]*)<\/w:t><w:fldChar[\s\S]*?<\/w:r>/, (all, rpr, sp) => {
    const R = (inner) => `<w:r><w:rPr>${rpr}</w:rPr>${inner}</w:r>`;
    const field = (instr, cached) => R('<w:fldChar w:fldCharType="begin"/>')
      + R(`<w:instrText xml:space="preserve"> ${instr} </w:instrText>`) + R('<w:fldChar w:fldCharType="separate"/>')
      + R(`<w:t>${cached}</w:t>`) + R('<w:fldChar w:fldCharType="end"/>');
    return R(`<w:t xml:space="preserve">p.${sp}</w:t>`) + field('PAGE', '1') + R('<w:t>/</w:t>') + field('NUMPAGES', String(C.total));
  });
  if (!foot.includes('NUMPAGES')) throw new Error('pied de page non réécrit');
  zip.file('word/footer1.xml', foot);
}

async function finishFonts(zip, count) {
  // 2. Styles gras : « Famille__B » devient l'embedBold de « Famille ».
  let ft = await zip.file('word/fontTable.xml').async('string');
  for (const fam of ['Inter', 'Bricolage Grotesque']) {
    const boldRe = new RegExp(`<w:font w:name="${fam}__B">((?:(?!</w:font>).)*)</w:font>`);
    const bm = ft.match(boldRe);
    if (!bm) throw new Error(`police grasse absente : ${fam}`);
    const embed = bm[1].match(/<w:embedRegular [^>]*\/>/)[0].replace('w:embedRegular', 'w:embedBold');
    ft = ft.replace(boldRe, '');
    const regRe = new RegExp(`(<w:font w:name="${fam}">(?:(?!</w:font>).)*?<w:embedRegular [^>]*/>)`);
    if (!regRe.test(ft)) throw new Error(`police normale absente : ${fam}`);
    ft = ft.replace(regRe, `$1${embed}`);
  }
  // Le schéma OOXML n'accepte que des GUID en capitales (sans effet sur le désobscurcissement).
  ft = ft.replace(/w:fontKey="(\{[^"]+\})"/g, (all, k) => `w:fontKey="${k.toUpperCase()}"`);
  zip.file('word/fontTable.xml', ft);

  // 3. Demander à Word de conserver les polices intégrées.
  let st = await zip.file('word/settings.xml').async('string');
  // Ordre imposé par le schéma : après displayBackgroundShape, avant le reste.
  if (!st.includes('w:embedTrueTypeFonts')) {
    st = st.includes('<w:displayBackgroundShape/>')
      ? st.replace('<w:displayBackgroundShape/>', '<w:displayBackgroundShape/><w:embedTrueTypeFonts/>')
      : st.replace(/(<w:settings[^>]*>)/, '$1<w:embedTrueTypeFonts/>');
  }
  zip.file('word/settings.xml', st);

  console.log('champs Word :', count);
  return zip.generateAsync({ type: 'nodebuffer', compression: 'DEFLATE' });
}

(async () => {
  const buf = await Packer.toBuffer(build());
  fs.writeFileSync(OUT, await finalize(buf));
  console.log('écrit', OUT);
})();
