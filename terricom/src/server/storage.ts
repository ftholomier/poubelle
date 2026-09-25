import { DeleteObjectCommand, GetObjectCommand, PutObjectCommand, S3Client } from '@aws-sdk/client-s3';
import { mkdir, readFile, rm, writeFile } from 'node:fs/promises';
import { dirname, join, normalize, resolve } from 'node:path';
import { env } from './env';

/**
 * Stockage des médias : disque local (développement, volume partagé) ou
 * stockage objet compatible S3 (production : Scaleway, OVHcloud, Outscale, MinIO…).
 */
export interface StorageDriver {
  put(key: string, body: Buffer, contentType: string): Promise<void>;
  get(key: string): Promise<{ body: Buffer; contentType: string } | null>;
  delete(key: string): Promise<void>;
  publicUrl(key: string): string;
}

const MIME_BY_EXT: Record<string, string> = {
  webp: 'image/webp',
  jpg: 'image/jpeg',
  jpeg: 'image/jpeg',
  png: 'image/png',
  svg: 'image/svg+xml',
  pdf: 'application/pdf',
  json: 'application/json',
  csv: 'text/csv; charset=utf-8',
  zip: 'application/zip',
};

export function mimeFromKey(key: string): string {
  const ext = key.split('.').pop()?.toLowerCase() ?? '';
  return MIME_BY_EXT[ext] ?? 'application/octet-stream';
}

function safeKey(key: string): string {
  const clean = normalize(key)
    .replace(/^(\.\.(\/|\\|$))+/, '')
    .replace(/^[/\\]+/, '');
  if (clean.includes('..')) throw new Error('Clé de stockage invalide');
  return clean;
}

class LocalDriver implements StorageDriver {
  private root = resolve(/*turbopackIgnore: true*/ process.cwd(), env.STORAGE_LOCAL_DIR);

  private path(key: string) {
    const p = join(this.root, safeKey(key));
    if (!p.startsWith(this.root)) throw new Error('Chemin hors du stockage');
    return p;
  }

  async put(key: string, body: Buffer): Promise<void> {
    const p = this.path(key);
    await mkdir(dirname(p), { recursive: true });
    await writeFile(p, body);
  }

  async get(key: string) {
    try {
      const body = await readFile(this.path(key));
      return { body, contentType: mimeFromKey(key) };
    } catch {
      return null;
    }
  }

  async delete(key: string): Promise<void> {
    await rm(this.path(key), { force: true });
  }

  publicUrl(key: string): string {
    return `/media/${safeKey(key)}`;
  }
}

class S3Driver implements StorageDriver {
  private client = new S3Client({
    region: env.S3_REGION,
    endpoint: env.S3_ENDPOINT || undefined,
    forcePathStyle: Boolean(env.S3_ENDPOINT),
    credentials:
      env.S3_ACCESS_KEY_ID && env.S3_SECRET_ACCESS_KEY ? { accessKeyId: env.S3_ACCESS_KEY_ID, secretAccessKey: env.S3_SECRET_ACCESS_KEY } : undefined,
  });

  async put(key: string, body: Buffer, contentType: string): Promise<void> {
    const k = safeKey(key);
    await this.client.send(
      new PutObjectCommand({
        Bucket: env.S3_BUCKET,
        Key: k,
        Body: body,
        ContentType: contentType,
        CacheControl: k.startsWith('private/') ? 'private, no-store' : 'public, max-age=31536000, immutable',
        ServerSideEncryption: 'AES256',
      }),
    );
  }

  async get(key: string) {
    try {
      const out = await this.client.send(new GetObjectCommand({ Bucket: env.S3_BUCKET, Key: safeKey(key) }));
      const bytes = await out.Body?.transformToByteArray();
      if (!bytes) return null;
      return { body: Buffer.from(bytes), contentType: out.ContentType ?? mimeFromKey(key) };
    } catch {
      return null;
    }
  }

  async delete(key: string): Promise<void> {
    await this.client.send(new DeleteObjectCommand({ Bucket: env.S3_BUCKET, Key: safeKey(key) }));
  }

  publicUrl(key: string): string {
    const k = safeKey(key);
    if (env.S3_PUBLIC_URL && !k.startsWith('private/')) return `${env.S3_PUBLIC_URL.replace(/\/$/, '')}/${k}`;
    return `/media/${k}`;
  }
}

let driver: StorageDriver | null = null;

export function storage(): StorageDriver {
  if (!driver) driver = env.STORAGE_DRIVER === 's3' ? new S3Driver() : new LocalDriver();
  return driver;
}
