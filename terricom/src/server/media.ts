import { randomUUID } from 'node:crypto';
import { connect } from 'node:net';
import sharp from 'sharp';
import { db } from './db';
import { media, type MediaVariants } from './db/schema';
import { logger } from './logger';
import { storage } from './storage';

export const MAX_IMAGE_BYTES = 10 * 1024 * 1024;
export const MAX_DOCUMENT_BYTES = 5 * 1024 * 1024;
const ALLOWED_IMAGE_FORMATS = new Set(['jpeg', 'png', 'webp', 'avif', 'heif', 'gif', 'tiff']);
const WIDTHS = [320, 640, 1280, 1920] as const;

export class MediaError extends Error {
  constructor(message: string) {
    super(message);
    this.name = 'MediaError';
  }
}

export type UploadContext = {
  ownerType: string;
  ownerId?: string | null;
  territoryId?: string | null;
  establishmentId?: string | null;
  alt?: string | null;
  tag?: string | null;
  sortOrder?: number;
  uploadedById?: string | null;
};

async function toBuffer(input: File | Buffer): Promise<Buffer> {
  if (Buffer.isBuffer(input)) return input;
  return Buffer.from(await input.arrayBuffer());
}

/**
 * Analyse antivirus via clamd (protocole INSTREAM) si CLAMAV_HOST est configuré.
 * Sans antivirus, les images restent assainies par leur réencodage complet.
 */
export async function scanForViruses(buf: Buffer): Promise<{ clean: boolean; signature?: string; scanned: boolean }> {
  const host = process.env.CLAMAV_HOST;
  if (!host) return { clean: true, scanned: false };
  const port = Number(process.env.CLAMAV_PORT ?? 3310);
  return new Promise((resolvePromise, reject) => {
    const socket = connect({ host, port });
    let response = '';
    socket.setTimeout(20_000, () => socket.destroy(new Error('Délai antivirus dépassé')));
    socket.on('connect', () => {
      socket.write('zINSTREAM\0');
      for (let i = 0; i < buf.length; i += 64 * 1024) {
        const chunk = buf.subarray(i, i + 64 * 1024);
        const len = Buffer.alloc(4);
        len.writeUInt32BE(chunk.length);
        socket.write(len);
        socket.write(chunk);
      }
      socket.write(Buffer.alloc(4));
    });
    socket.on('data', (d) => (response += d.toString()));
    socket.on('end', () => {
      const found = response.match(/: (.+) FOUND/);
      resolvePromise(found ? { clean: false, signature: found[1], scanned: true } : { clean: /OK/.test(response), scanned: true });
    });
    socket.on('error', reject);
  });
}

/**
 * Traite une image téléversée : contrôle du format réel, rotation EXIF, suppression
 * des métadonnées (géolocalisation…), déclinaisons WebP responsives.
 */
export async function saveImageUpload(input: File | Buffer, ctx: UploadContext) {
  const buf = await toBuffer(input);
  if (buf.length === 0) throw new MediaError('Fichier vide.');
  if (buf.length > MAX_IMAGE_BYTES) throw new MediaError('Image trop lourde (10 Mo maximum).');

  let meta: sharp.Metadata;
  try {
    meta = await sharp(buf, { limitInputPixels: 40_000_000, failOn: 'error' }).metadata();
  } catch {
    throw new MediaError("Ce fichier n'est pas une image valide.");
  }
  if (!meta.format || !ALLOWED_IMAGE_FORMATS.has(meta.format)) {
    throw new MediaError('Format non pris en charge (JPEG, PNG, WebP, AVIF, HEIC).');
  }
  const scan = await scanForViruses(buf).catch((err) => {
    logger.warn('antivirus.unavailable', { err });
    return { clean: true, scanned: false } as const;
  });
  if (!scan.clean) throw new MediaError('Fichier refusé par l’analyse antivirus.');

  const id = randomUUID();
  const now = new Date();
  const prefix = `img/${now.getUTCFullYear()}/${String(now.getUTCMonth() + 1).padStart(2, '0')}/${id}`;
  const base = sharp(buf, { limitInputPixels: 40_000_000 }).rotate();
  const variants: MediaVariants = {};
  let width = meta.width ?? null;
  let height = meta.height ?? null;
  for (const w of WIDTHS) {
    if (meta.width && w > meta.width * 1.2 && w !== 320) continue;
    const { data, info } = await base
      .clone()
      .resize({ width: w, withoutEnlargement: true })
      .webp({ quality: 80 })
      .toBuffer({ resolveWithObject: true });
    const key = `${prefix}-${w}.webp`;
    await storage().put(key, data, 'image/webp');
    variants[`w${w}` as keyof MediaVariants] = storage().publicUrl(key);
    width = info.width;
    height = info.height;
  }
  const url = variants.w1280 ?? variants.w640 ?? variants.w320 ?? variants.w1920!;
  const [row] = await db
    .insert(media)
    .values({
      ownerType: ctx.ownerType,
      ownerId: ctx.ownerId ?? null,
      territoryId: ctx.territoryId ?? null,
      establishmentId: ctx.establishmentId ?? null,
      kind: 'IMAGE',
      url,
      storageKey: prefix,
      mimeType: 'image/webp',
      sizeBytes: buf.length,
      width,
      height,
      variants,
      alt: ctx.alt ?? null,
      tag: ctx.tag ?? null,
      sortOrder: ctx.sortOrder ?? 0,
      uploadedById: ctx.uploadedById ?? null,
    })
    .returning();
  return row;
}

/** Document privé (CV, Kbis) : PDF uniquement, 5 Mo maximum, jamais servi publiquement. */
export async function saveDocumentUpload(input: File | Buffer, ctx: UploadContext) {
  const buf = await toBuffer(input);
  if (buf.length === 0) throw new MediaError('Fichier vide.');
  if (buf.length > MAX_DOCUMENT_BYTES) throw new MediaError('Document trop lourd (5 Mo maximum).');
  if (buf.subarray(0, 5).toString('latin1') !== '%PDF-') throw new MediaError('Seuls les fichiers PDF sont acceptés.');
  const scan = await scanForViruses(buf).catch(() => ({ clean: true, scanned: false }) as const);
  if (!scan.clean) throw new MediaError('Fichier refusé par l’analyse antivirus.');
  const key = `private/docs/${randomUUID()}.pdf`;
  await storage().put(key, buf, 'application/pdf');
  const [row] = await db
    .insert(media)
    .values({
      ownerType: ctx.ownerType,
      ownerId: ctx.ownerId ?? null,
      territoryId: ctx.territoryId ?? null,
      establishmentId: ctx.establishmentId ?? null,
      kind: 'DOCUMENT',
      url: key,
      storageKey: key,
      mimeType: 'application/pdf',
      sizeBytes: buf.length,
      isPrivate: true,
      uploadedById: ctx.uploadedById ?? null,
    })
    .returning();
  return row;
}

export async function deleteMediaFiles(row: { storageKey: string | null; kind: string; variants: MediaVariants }) {
  if (!row.storageKey) return;
  if (row.kind === 'DOCUMENT') {
    await storage().delete(row.storageKey);
    return;
  }
  await Promise.all(WIDTHS.map((w) => storage().delete(`${row.storageKey}-${w}.webp`).catch(() => {})));
}

/** Choisit la déclinaison la plus adaptée à une largeur d'affichage. */
export function pickVariant(m: { url: string; variants?: MediaVariants | null }, width: number): string {
  const v = m.variants ?? {};
  if (width <= 320 && v.w320) return v.w320;
  if (width <= 640 && v.w640) return v.w640;
  if (width <= 1280 && v.w1280) return v.w1280;
  return v.w1920 ?? v.w1280 ?? m.url;
}
