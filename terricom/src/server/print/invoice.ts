import fontkit from '@pdf-lib/fontkit';
import { readFile } from 'node:fs/promises';
import { join } from 'node:path';
import { PDFDocument, rgb, type PDFFont, type PDFPage } from 'pdf-lib';
import { env } from '../env';
import type { invoices } from '../db/schema';
import { woffToSfnt } from './woff';

type Invoice = typeof invoices.$inferSelect;

const INK = rgb(0.078, 0.125, 0.106);
const MUTED = rgb(0.37, 0.4, 0.37);
const GREEN = rgb(0.122, 0.42, 0.322);
const LINE = rgb(0.894, 0.875, 0.827);

const euros = (cents: number) =>
  `${(cents / 100).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).replace(/ | /g, ' ')} €`;
const frDate = (iso: string) => {
  const [y, m, d] = iso.split('-');
  return `${d}/${m}/${y}`;
};

async function font(pdf: PDFDocument, file: string): Promise<PDFFont> {
  const buf = woffToSfnt(await readFile(join(process.cwd(), 'node_modules', file)));
  return pdf.embedFont(buf, { subset: true });
}

function right(page: PDFPage, text: string, f: PDFFont, size: number, x: number, y: number, color = INK) {
  page.drawText(text, { x: x - f.widthOfTextAtSize(text, size), y, size, font: f, color });
}

/** Facture PDF conforme (mentions obligatoires, numérotation continue, pénalités de retard). */
export async function invoicePdf(inv: Invoice): Promise<Uint8Array> {
  const pdf = await PDFDocument.create();
  pdf.registerFontkit(fontkit);
  const display = await font(pdf, '@fontsource/bricolage-grotesque/files/bricolage-grotesque-latin-800-normal.woff');
  const bold = await font(pdf, '@fontsource/instrument-sans/files/instrument-sans-latin-700-normal.woff');
  const body = await font(pdf, '@fontsource/instrument-sans/files/instrument-sans-latin-400-normal.woff');
  pdf.setTitle(`Facture ${inv.number}`);
  pdf.setProducer('terricom');
  const W = 595.28;
  const H = 841.89;
  const page = pdf.addPage([W, H]);
  const m = 48;
  // En-tête : marque et émetteur
  page.drawText('terricom', { x: m, y: H - m - 22, size: 24, font: display, color: INK });
  page.drawCircle({ x: m + display.widthOfTextAtSize('terricom', 24) + 5, y: H - m - 20, size: 3, color: rgb(0.957, 0.698, 0.4) });
  let y = H - m - 44;
  for (const l of [env.COMPANY_LEGAL_NAME, ...env.COMPANY_ADDRESS.split(/\n|,\s*(?=\d{5})/), env.COMPANY_SIREN ? `SIREN ${env.COMPANY_SIREN}` : null, env.COMPANY_VAT_NUMBER ? `TVA ${env.COMPANY_VAT_NUMBER}` : null].filter(Boolean) as string[]) {
    page.drawText(l, { x: m, y, size: 9, font: body, color: MUTED });
    y -= 12;
  }
  right(page, 'FACTURE', display, 20, W - m, H - m - 22);
  right(page, `N° ${inv.number}`, bold, 11, W - m, H - m - 42);
  right(page, `Émise le ${frDate(inv.issuedAt)}`, body, 9.5, W - m, H - m - 58, MUTED);
  right(page, `Échéance ${frDate(inv.dueAt)}`, body, 9.5, W - m, H - m - 71, MUTED);
  // Client
  const cy = H - m - 150;
  page.drawText('FACTURÉ À', { x: W / 2, y: cy + 18, size: 8, font: bold, color: GREEN });
  page.drawText(inv.customerName, { x: W / 2, y: cy, size: 12, font: bold, color: INK });
  let ay = cy - 15;
  for (const l of (inv.customerAddress ?? '').split('\n').filter(Boolean).slice(0, 4)) {
    page.drawText(l, { x: W / 2, y: ay, size: 9.5, font: body, color: MUTED });
    ay -= 12;
  }
  if (inv.chorusRef) page.drawText(`Référence Chorus Pro : ${inv.chorusRef}`, { x: W / 2, y: ay - 4, size: 9, font: body, color: MUTED });
  // Lignes
  let ty = cy - 110;
  const cols = [m, W - m - 250, W - m - 170, W - m - 90, W - m];
  page.drawRectangle({ x: m, y: ty - 6, width: W - 2 * m, height: 22, color: rgb(0.969, 0.957, 0.925) });
  page.drawText('Désignation', { x: cols[0] + 8, y: ty, size: 9, font: bold, color: INK });
  right(page, 'Qté', bold, 9, cols[1] + 30, ty);
  right(page, 'PU HT', bold, 9, cols[2] + 60, ty);
  right(page, 'TVA', bold, 9, cols[3] + 40, ty);
  right(page, 'Total HT', bold, 9, cols[4] - 8, ty);
  ty -= 26;
  for (const l of inv.lines) {
    page.drawText(l.label.slice(0, 70), { x: cols[0] + 8, y: ty, size: 9.5, font: body, color: INK });
    right(page, String(l.quantity), body, 9.5, cols[1] + 30, ty);
    right(page, euros(l.unitCents), body, 9.5, cols[2] + 60, ty);
    right(page, `${l.vatRate} %`, body, 9.5, cols[3] + 40, ty);
    right(page, euros(Math.round(l.quantity * l.unitCents)), body, 9.5, cols[4] - 8, ty);
    ty -= 8;
    page.drawLine({ start: { x: m, y: ty }, end: { x: W - m, y: ty }, thickness: 0.6, color: LINE });
    ty -= 16;
  }
  // Totaux
  ty -= 6;
  for (const [label, value, strong] of [
    ['Total HT', euros(inv.totalHtCents), false],
    ['TVA', euros(inv.vatCents), false],
    ['Total TTC', euros(inv.totalTtcCents), true],
  ] as const) {
    right(page, label, strong ? bold : body, strong ? 11 : 9.5, W - m - 110, ty, strong ? INK : MUTED);
    right(page, value, strong ? display : bold, strong ? 13 : 9.5, W - m - 8, ty);
    ty -= strong ? 22 : 16;
  }
  if (inv.status === 'PAID') {
    page.drawText(`Acquittée le ${inv.paidAt ? frDate(inv.paidAt) : ''}${inv.paymentMethod ? ` (${inv.paymentMethod.toLowerCase()})` : ''}`, { x: m, y: ty + 40, size: 10, font: bold, color: GREEN });
  }
  // Mentions légales
  const legal = [
    `Paiement à réception, au plus tard le ${frDate(inv.dueAt)}${env.COMPANY_IBAN ? ` — virement : IBAN ${env.COMPANY_IBAN}` : ''}.`,
    inv.customerType === 'TERRITORY'
      ? 'Personne publique : délai global de paiement de 30 jours ; intérêts moratoires au taux de la BCE majoré de 8 points et indemnité forfaitaire de 40 € pour frais de recouvrement en cas de retard.'
      : 'Pénalités de retard : trois fois le taux d’intérêt légal ; indemnité forfaitaire pour frais de recouvrement de 40 € (art. L441-10 du code de commerce). Pas d’escompte pour paiement anticipé.',
    inv.notes ?? '',
  ].filter(Boolean);
  let ly = 110;
  for (const para of legal) {
    const words = para.split(' ');
    let line = '';
    const lines: string[] = [];
    for (const w of words) {
      const next = line ? `${line} ${w}` : w;
      if (body.widthOfTextAtSize(next, 8) > W - 2 * m) {
        lines.push(line);
        line = w;
      } else line = next;
    }
    lines.push(line);
    for (const l of lines) {
      page.drawText(l, { x: m, y: ly, size: 8, font: body, color: MUTED });
      ly -= 11;
    }
    ly -= 4;
  }
  page.drawLine({ start: { x: m, y: 40 }, end: { x: W - m, y: 40 }, thickness: 0.6, color: LINE });
  page.drawText(`${env.COMPANY_LEGAL_NAME} — plateforme d'animation et de valorisation économique du territoire`, { x: m, y: 28, size: 7.5, font: body, color: MUTED });
  return pdf.save();
}
