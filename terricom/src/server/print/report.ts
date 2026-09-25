import { rgb, type PDFPage } from 'pdf-lib';
import { hex, newDoc, wrap } from './kit';

/**
 * Rapport d'activité (PDF A4) destiné au conseil communautaire ou municipal :
 * indicateurs clés, adoption, fréquentation mensuelle, recherches, tableau par commune.
 */

export type ReportData = {
  territoryName: string;
  scopeName: string;
  legalName: string;
  color: string;
  accent: string;
  periodLabel: string;
  kpis: { label: string; value: string; detail?: string }[];
  funnel: { label: string; value: number }[];
  months: { label: string; value: number }[];
  searches: { q: string; n: number }[];
  signal: { q: string; n: number } | null;
  communes: { name: string; total: number; claimed: number; views: number; calls: number; directions: number }[];
};

const MM = 2.8346;
const fr = (n: number) => n.toLocaleString('fr-FR').replace(/ /g, ' ');

export async function activityReportPdf(d: ReportData, now = new Date()): Promise<Uint8Array> {
  const { pdf, fonts } = await newDoc();
  pdf.setTitle(`Rapport d'activité — ${d.scopeName}`);
  const W = 210 * MM;
  const H = 297 * MM;
  const M = 18 * MM;
  const ink = hex('#14201B');
  const muted = hex('#5E655F');
  const line = hex('#E4DFD3');
  const date = new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'Europe/Paris' }).format(now);

  const footer = (page: PDFPage, n: number) => {
    page.drawText(`${d.scopeName} · rapport généré le ${date} avec terricom`, { x: M, y: 10 * MM, size: 8, font: fonts.body, color: muted });
    page.drawText(String(n), { x: W - M - 6, y: 10 * MM, size: 8, font: fonts.body, color: muted });
  };
  const title = (page: PDFPage, text: string, y: number) => {
    page.drawText(text, { x: M, y, size: 14, font: fonts.display, color: ink });
    return y - 8 * MM;
  };

  // Page 1 : couverture et indicateurs
  let page = pdf.addPage([W, H]);
  page.drawRectangle({ x: 0, y: H - 70 * MM, width: W, height: 70 * MM, color: hex(d.color) });
  page.drawText('RAPPORT D’ACTIVITÉ', { x: M, y: H - 22 * MM, size: 10, font: fonts.bold, color: hex(d.accent) });
  let y = H - 36 * MM;
  for (const l of wrap(d.scopeName, fonts.display, 30, W - 2 * M).slice(0, 2)) {
    page.drawText(l, { x: M, y, size: 30, font: fonts.display, color: rgb(1, 1, 1) });
    y -= 32;
  }
  page.drawText(`Animation économique du territoire · ${d.periodLabel}`, { x: M, y: H - 62 * MM, size: 11, font: fonts.body, color: rgb(1, 1, 1) });

  y = H - 86 * MM;
  y = title(page, 'Indicateurs clés', y);
  const boxW = (W - 2 * M - 2 * 5 * MM) / 3;
  d.kpis.forEach((k, i) => {
    const col = i % 3;
    const row = Math.floor(i / 3);
    const x = M + col * (boxW + 5 * MM);
    const by = y - row * 30 * MM - 25 * MM;
    page.drawRectangle({ x, y: by, width: boxW, height: 25 * MM, color: hex('#FFFDF8'), borderColor: line, borderWidth: 1 });
    page.drawRectangle({ x, y: by + 25 * MM - 1.6 * MM, width: boxW, height: 1.6 * MM, color: hex(d.color) });
    page.drawText(k.label, { x: x + 4 * MM, y: by + 17 * MM, size: 9, font: fonts.body, color: muted });
    page.drawText(k.value, { x: x + 4 * MM, y: by + 8 * MM, size: 20, font: fonts.display, color: ink });
    if (k.detail) page.drawText(k.detail.slice(0, 48), { x: x + 4 * MM, y: by + 3.5 * MM, size: 7, font: fonts.bold, color: hex(d.color) });
  });
  y -= 2 * 30 * MM + 8 * MM;

  y = title(page, 'Adoption de la plateforme', y);
  const total = Math.max(1, d.funnel[0]?.value ?? 1);
  const barW = (W - 2 * M - 3 * 6 * MM) / 4;
  const barMax = 38 * MM;
  const colors = ['#14201B', d.color, '#5FA37E', d.accent];
  d.funnel.forEach((f, i) => {
    const x = M + i * (barW + 6 * MM);
    const h = Math.max(2, (f.value / total) * barMax);
    page.drawRectangle({ x, y: y - barMax - 14 * MM, width: barW, height: h, color: hex(colors[i]) });
    page.drawText(fr(f.value), { x, y: y - 6 * MM, size: 16, font: fonts.display, color: ink });
    page.drawText(`${f.label} · ${Math.round((f.value / total) * 100)} %`, { x, y: y - 11 * MM, size: 8, font: fonts.body, color: muted });
  });
  footer(page, 1);

  // Page 2 : fréquentation et recherches
  page = pdf.addPage([W, H]);
  y = H - M - 4 * MM;
  y = title(page, 'Visiteurs du portail par mois', y);
  const maxV = Math.max(1, ...d.months.map((m) => m.value));
  const colW = (W - 2 * M) / d.months.length;
  const chartH = 55 * MM;
  d.months.forEach((m, i) => {
    const x = M + i * colW + 1.5 * MM;
    const h = Math.max(1, (m.value / maxV) * chartH);
    page.drawRectangle({
      x,
      y: y - chartH - 6 * MM,
      width: colW - 3 * MM,
      height: h,
      color: hex(i === d.months.length - 1 ? d.accent : m.value ? d.color : '#E4DFD3'),
    });
    page.drawText(m.label, { x, y: y - chartH - 11 * MM, size: 7, font: fonts.body, color: muted });
    if (m.value)
      page.drawText(m.value >= 1000 ? `${(m.value / 1000).toFixed(1).replace('.', ',')}k` : String(m.value), {
        x,
        y: y - chartH - 5 * MM + h + 1.5 * MM,
        size: 6.5,
        font: fonts.bold,
        color: muted,
      });
  });
  y -= chartH + 24 * MM;
  y = title(page, 'Ce que recherchent les habitants', y);
  d.searches.forEach((s, i) => {
    page.drawText(`${i + 1}.`, { x: M, y, size: 10, font: fonts.bold, color: hex('#C8702A') });
    page.drawText(s.q, { x: M + 8 * MM, y, size: 10, font: fonts.body, color: ink });
    const n = fr(s.n);
    page.drawText(n, { x: W - M - fonts.bold.widthOfTextAtSize(n, 10), y, size: 10, font: fonts.bold, color: ink });
    page.drawLine({ start: { x: M, y: y - 2.5 * MM }, end: { x: W - M, y: y - 2.5 * MM }, thickness: 0.5, color: line });
    y -= 7 * MM;
  });
  if (d.signal) {
    y -= 4 * MM;
    const text = `Signal faible : ${fr(d.signal.n)} recherches « ${d.signal.q} », aucun établissement référencé sur le territoire. Une piste pour l’installation d’une nouvelle activité ?`;
    const lines = wrap(text, fonts.body, 10, W - 2 * M - 10 * MM);
    const boxH = lines.length * 5 * MM + 8 * MM;
    page.drawRectangle({ x: M, y: y - boxH + 4 * MM, width: W - 2 * M, height: boxH, color: hex(d.accent) });
    let ly = y - 1 * MM;
    for (const l of lines) {
      page.drawText(l, { x: M + 5 * MM, y: ly, size: 10, font: fonts.body, color: ink });
      ly -= 5 * MM;
    }
  }
  footer(page, 2);

  // Pages suivantes : tableau par commune
  page = pdf.addPage([W, H]);
  let n = 3;
  y = H - M - 4 * MM;
  y = title(page, 'Par commune', y);
  const cols = [
    { l: 'Commune', x: M, w: 60 * MM },
    { l: 'Fiches', x: M + 64 * MM },
    { l: 'Revendiquées', x: M + 86 * MM },
    { l: 'Vues', x: M + 114 * MM },
    { l: 'Appels', x: M + 136 * MM },
    { l: 'Itinéraires', x: M + 154 * MM },
  ];
  const header = () => {
    for (const c of cols) page.drawText(c.l.toUpperCase(), { x: c.x, y, size: 7, font: fonts.bold, color: muted });
    y -= 6 * MM;
  };
  header();
  for (const c of d.communes) {
    if (y < 22 * MM) {
      footer(page, n++);
      page = pdf.addPage([W, H]);
      y = H - M - 4 * MM;
      header();
    }
    const vals = [c.name, fr(c.total), fr(c.claimed), fr(c.views), fr(c.calls), fr(c.directions)];
    vals.forEach((v, i) =>
      page.drawText(i === 0 ? (wrap(v, fonts.bold, 9, 58 * MM)[0] ?? v) : v, { x: cols[i].x, y, size: 9, font: i === 0 ? fonts.bold : fonts.body, color: ink }),
    );
    page.drawLine({ start: { x: M, y: y - 2.5 * MM }, end: { x: W - M, y: y - 2.5 * MM }, thickness: 0.4, color: line });
    y -= 7 * MM;
  }
  footer(page, n);
  return pdf.save();
}
