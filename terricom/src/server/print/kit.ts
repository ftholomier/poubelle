import fontkit from '@pdf-lib/fontkit';
import { readFile } from 'node:fs/promises';
import { join } from 'node:path';
import { degrees, PDFDocument, type PDFFont, type PDFPage, rgb, type RGB } from 'pdf-lib';
import QRCode from 'qrcode';
import { woffToSfnt } from './woff';

/**
 * Supports imprimables du kit vitrine (affichette A5, autocollant rond, carte de visite),
 * générés en PDF vectoriel avec les polices de la charte et un QR code net à toute taille.
 */

export type KitStyle = 'vert' | 'creme' | 'nuit';

export const KIT_STYLES: Record<KitStyle, { label: string; bg: string; fg: string; accent: string }> = {
  vert: { label: 'Vert Loue', bg: '#1F6B52', fg: '#FFFFFF', accent: '#F4B266' },
  creme: { label: 'Crème', bg: '#FFF3E6', fg: '#14201B', accent: '#F4B266' },
  nuit: { label: 'Nuit', bg: '#14201B', fg: '#F7F4EC', accent: '#D6E8B4' },
};

export type KitInput = {
  name: string;
  activity: string;
  address: string | null;
  phone: string | null;
  territoryName: string;
  url: string; // lien du QR code (lien court mesuré)
  displayUrl: string;
  shortUrl: string;
};

const FONT_FILES = {
  display: '@fontsource/bricolage-grotesque/files/bricolage-grotesque-latin-800-normal.woff',
  bold: '@fontsource/instrument-sans/files/instrument-sans-latin-700-normal.woff',
  body: '@fontsource/instrument-sans/files/instrument-sans-latin-400-normal.woff',
} as const;

let fontCache: Promise<Record<keyof typeof FONT_FILES, Buffer>> | null = null;

function loadFontFiles() {
  fontCache ??= (async () => {
    const entries = await Promise.all(
      Object.entries(FONT_FILES).map(async ([k, f]) => [k, woffToSfnt(await readFile(join(process.cwd(), 'node_modules', f)))] as const),
    );
    return Object.fromEntries(entries) as Record<keyof typeof FONT_FILES, Buffer>;
  })();
  return fontCache;
}

async function newDoc() {
  const pdf = await PDFDocument.create();
  pdf.registerFontkit(fontkit);
  const files = await loadFontFiles();
  const fonts = {
    display: await pdf.embedFont(files.display, { subset: true }),
    bold: await pdf.embedFont(files.bold, { subset: true }),
    body: await pdf.embedFont(files.body, { subset: true }),
  };
  pdf.setProducer('terricom');
  pdf.setCreator('terricom — kit vitrine');
  return { pdf, fonts };
}

function hex(c: string): RGB {
  const v = c.replace('#', '');
  return rgb(parseInt(v.slice(0, 2), 16) / 255, parseInt(v.slice(2, 4), 16) / 255, parseInt(v.slice(4, 6), 16) / 255);
}

/** Découpe un texte en lignes tenant dans une largeur donnée. */
function wrap(text: string, font: PDFFont, size: number, width: number): string[] {
  const words = text.split(/\s+/);
  const lines: string[] = [];
  let line = '';
  for (const w of words) {
    const next = line ? `${line} ${w}` : w;
    if (font.widthOfTextAtSize(next, size) > width && line) {
      lines.push(line);
      line = w;
    } else line = next;
  }
  if (line) lines.push(line);
  return lines;
}

/** Dessine un QR code vectoriel (modules pleins) dans un carré de côté `size`. */
function drawQr(page: PDFPage, data: string, x: number, y: number, size: number, color = '#14201B') {
  const qr = QRCode.create(data, { errorCorrectionLevel: 'M' });
  const n = qr.modules.size;
  const cell = size / n;
  const c = hex(color);
  for (let r = 0; r < n; r++) {
    for (let col = 0; col < n; col++) {
      if (qr.modules.get(r, col)) page.drawRectangle({ x: x + col * cell, y: y + size - (r + 1) * cell, width: cell + 0.05, height: cell + 0.05, color: c });
    }
  }
}

function roundedRect(page: PDFPage, x: number, y: number, w: number, h: number, r: number, color: RGB) {
  const path = `M ${r} 0 H ${w - r} A ${r} ${r} 0 0 1 ${w} ${r} V ${h - r} A ${r} ${r} 0 0 1 ${w - r} ${h} H ${r} A ${r} ${r} 0 0 1 0 ${h - r} V ${r} A ${r} ${r} 0 0 1 ${r} 0 Z`;
  // drawSvgPath utilise un repère orienté vers le bas : on part du coin supérieur gauche.
  page.drawSvgPath(path, { x, y: y + h, color });
}

function centerText(page: PDFPage, text: string, font: PDFFont, size: number, y: number, color: RGB, pageWidth: number) {
  const w = font.widthOfTextAtSize(text, size);
  page.drawText(text, { x: (pageWidth - w) / 2, y, size, font, color });
}

/** Affichette de vitrine A5 (148 × 210 mm), trois styles. */
export async function posterA5(input: KitInput, style: KitStyle = 'vert'): Promise<Uint8Array> {
  const { pdf, fonts } = await newDoc();
  const s = KIT_STYLES[style];
  const W = 419.53;
  const H = 595.28;
  const page = pdf.addPage([W, H]);
  const fg = hex(s.fg);
  page.drawRectangle({ x: 0, y: 0, width: W, height: H, color: hex(s.bg) });
  const m = 34;
  page.drawText(input.territoryName, { x: m, y: H - m - 14, size: 15, font: fonts.display, color: fg });
  // Pastille « Fait ici » inclinée
  const tag = 'Fait ici';
  const tw = fonts.bold.widthOfTextAtSize(tag, 11) + 16;
  page.drawRectangle({ x: W - m - tw, y: H - m - 20, width: tw, height: 20, color: hex(s.accent), rotate: degrees(4) });
  page.drawText(tag, { x: W - m - tw + 8, y: H - m - 14, size: 11, font: fonts.bold, color: hex('#14201B'), rotate: degrees(4) });
  // Titre
  let y = H - m - 80;
  for (const line of wrap('Retrouvez-nous en ligne !', fonts.display, 40, W - 2 * m)) {
    page.drawText(line, { x: m, y, size: 40, font: fonts.display, color: fg });
    y -= 40;
  }
  y -= 6;
  for (const line of wrap('Horaires, nouveautés, bons plans : scannez avec votre téléphone.', fonts.body, 14, W - 2 * m)) {
    page.drawText(line, { x: m, y, size: 14, font: fonts.body, color: fg, opacity: 0.88 });
    y -= 19;
  }
  // QR code sur fond blanc
  const box = 210;
  const bx = (W - box) / 2;
  const by = 92;
  roundedRect(page, bx, by, box, box, 18, rgb(1, 1, 1));
  drawQr(page, input.url, bx + 16, by + 16, box - 32);
  centerText(page, input.name, fonts.bold, 16, 62, fg, W);
  centerText(page, input.displayUrl, fonts.body, 9.5, 44, fg, W);
  centerText(page, 'terricom', fonts.display, 8.5, 22, fg, W);
  return pdf.save();
}

/** Autocollant rond de 10 cm (porte, caisse). */
export async function stickerRound(input: KitInput, style: KitStyle = 'vert'): Promise<Uint8Array> {
  const { pdf, fonts } = await newDoc();
  const s = KIT_STYLES[style];
  const D = 283.46; // 100 mm
  const page = pdf.addPage([D, D]);
  page.drawCircle({ x: D / 2, y: D / 2, size: D / 2, color: hex(s.bg) });
  const fg = hex(s.fg);
  centerText(page, 'Retrouvez-nous', fonts.display, 20, D - 58, fg, D);
  centerText(page, 'en ligne !', fonts.display, 20, D - 80, fg, D);
  const box = 118;
  roundedRect(page, (D - box) / 2, 62, box, box, 12, rgb(1, 1, 1));
  drawQr(page, input.url, (D - box) / 2 + 9, 71, box - 18);
  centerText(page, input.name, fonts.bold, 11, 42, fg, D);
  return pdf.save();
}

/** Carte de visite 85 × 55 mm, recto (coordonnées) et verso (QR code). */
export async function businessCard(input: KitInput, style: KitStyle = 'vert'): Promise<Uint8Array> {
  const { pdf, fonts } = await newDoc();
  const s = KIT_STYLES[style];
  const W = 240.94;
  const H = 155.91;
  const fg = hex(s.fg);
  const recto = pdf.addPage([W, H]);
  recto.drawRectangle({ x: 0, y: 0, width: W, height: H, color: hex(s.bg) });
  recto.drawText(input.activity.toUpperCase(), { x: 16, y: H - 28, size: 7.5, font: fonts.bold, color: hex(s.accent) });
  let y = H - 48;
  for (const line of wrap(input.name, fonts.display, 17, W - 32).slice(0, 2)) {
    recto.drawText(line, { x: 16, y, size: 17, font: fonts.display, color: fg });
    y -= 18;
  }
  const lines = [input.address, input.phone].filter(Boolean) as string[];
  let ly = 34;
  for (const l of lines.reverse()) {
    recto.drawText(l, { x: 16, y: ly, size: 8.5, font: fonts.body, color: fg });
    ly += 12;
  }
  recto.drawText(input.territoryName, { x: W - 16 - fonts.bold.widthOfTextAtSize(input.territoryName, 7.5), y: 14, size: 7.5, font: fonts.bold, color: fg, opacity: 0.8 });
  const verso = pdf.addPage([W, H]);
  verso.drawRectangle({ x: 0, y: 0, width: W, height: H, color: rgb(1, 1, 1) });
  drawQr(verso, input.url, 16, 22, 112);
  const tx = 142;
  let vy = 100;
  for (const line of wrap('Scannez pour nos horaires et nouveautés', fonts.display, 12, W - tx - 12)) {
    verso.drawText(line, { x: tx, y: vy, size: 12, font: fonts.display, color: hex('#14201B') });
    vy -= 14;
  }
  verso.drawText(input.shortUrl, { x: tx, y: 30, size: 7, font: fonts.body, color: hex('#5E655F') });
  return pdf.save();
}
