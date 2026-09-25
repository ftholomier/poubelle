import QRCode from 'qrcode';
import { SYMBOL_PATH } from '@/lib/symbol';

export type QrOptions = { dark?: string; light?: string; withSymbol?: boolean; symbolColor?: string; symbolAccent?: string };

/** QR code vectoriel (impression). Avec « withSymbol », le repère terricom est incrusté au centre. */
export async function qrSvg(data: string, opts: QrOptions = {}): Promise<string> {
  const dark = opts.dark ?? '#14201B';
  const light = opts.light ?? '#FFFFFF';
  const svg = await QRCode.toString(data, {
    type: 'svg',
    margin: 1,
    errorCorrectionLevel: opts.withSymbol ? 'H' : 'M',
    color: { dark, light },
  });
  if (!opts.withSymbol) return svg;
  const viewBox = svg.match(/viewBox="0 0 (\d+) (\d+)"/);
  const size = viewBox ? Number(viewBox[1]) : 33;
  const s = size * 0.2;
  const c = size / 2;
  const mark = `<g transform="translate(${c} ${c})"><rect x="${-s * 0.8}" y="${-s * 0.8}" width="${s * 1.6}" height="${s * 1.6}" rx="${s * 0.35}" fill="${light}"/><path d="${SYMBOL_PATH}" transform="rotate(-45) scale(${s})" fill="${opts.symbolColor ?? '#1F6B52'}"/></g>`;
  return svg.replace('</svg>', `${mark}</svg>`);
}

export async function qrPng(data: string, opts: QrOptions & { width?: number } = {}): Promise<Buffer> {
  return QRCode.toBuffer(data, {
    type: 'png',
    width: opts.width ?? 1024,
    margin: 1,
    errorCorrectionLevel: 'M',
    color: { dark: opts.dark ?? '#14201B', light: opts.light ?? '#FFFFFF' },
  });
}

export async function qrDataUrl(data: string, opts: QrOptions = {}): Promise<string> {
  const svg = await qrSvg(data, opts);
  return `data:image/svg+xml;base64,${Buffer.from(svg).toString('base64')}`;
}
