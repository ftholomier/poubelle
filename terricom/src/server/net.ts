import { lookup } from 'node:dns/promises';
import { isIP } from 'node:net';
import { env } from './env';

/**
 * Appels sortants vers des adresses fournies par les utilisateurs (connecteurs des entreprises, agendas
 * externes) : protection contre la falsification de requêtes côté serveur (SSRF). Seules les adresses
 * publiques sont autorisées, les redirections sont vérifiées une à une, la taille et la durée sont bornées.
 */
export class OutboundError extends Error {
  constructor(
    message: string,
    /** Erreur définitive (adresse refusée, requête rejetée) : inutile de réessayer. */
    readonly permanent = false,
  ) {
    super(message);
  }
}

function v4ToInt(ip: string): number {
  return ip.split('.').reduce((acc, p) => (acc << 8) + Number(p), 0) >>> 0;
}

const PRIVATE_V4: [string, number][] = [
  ['0.0.0.0', 8],
  ['10.0.0.0', 8],
  ['100.64.0.0', 10],
  ['127.0.0.0', 8],
  ['169.254.0.0', 16],
  ['172.16.0.0', 12],
  ['192.0.0.0', 24],
  ['192.0.2.0', 24],
  ['192.168.0.0', 16],
  ['198.18.0.0', 15],
  ['198.51.100.0', 24],
  ['203.0.113.0', 24],
  ['224.0.0.0', 4],
  ['240.0.0.0', 4],
];

/** Adresse IP non routable sur Internet (réseau local, boucle locale, métadonnées du cloud, multidiffusion…). */
export function isPrivateIp(ip: string): boolean {
  const family = isIP(ip);
  if (family === 4) {
    const n = v4ToInt(ip);
    return PRIVATE_V4.some(([base, bits]) => (n & (bits === 0 ? 0 : (~0 << (32 - bits)) >>> 0)) >>> 0 === v4ToInt(base));
  }
  if (family === 6) {
    const v = ip.toLowerCase();
    const mapped = /^::ffff:(\d+\.\d+\.\d+\.\d+)$/.exec(v);
    if (mapped) return isPrivateIp(mapped[1]);
    return v === '::' || v === '::1' || /^f[cd]/.test(v) || /^fe[89ab]/.test(v) || /^ff/.test(v) || v.startsWith('64:ff9b:') || v.startsWith('2001:db8');
  }
  return true;
}

const BLOCKED_HOST = /(^localhost$)|(\.localhost$)|(\.local$)|(\.internal$)|(\.svc$)|(\.cluster\.local$)|(^metadata\.google\.internal$)/i;

/** Vérifie qu'une adresse peut être appelée (protocole, hôte, adresses IP résolues). */
export async function assertPublicUrl(raw: string, opts: { allowHttp?: boolean } = {}): Promise<URL> {
  let url: URL;
  try {
    url = new URL(raw);
  } catch {
    throw new OutboundError('Adresse invalide.', true);
  }
  if (url.protocol !== 'https:' && !(opts.allowHttp && url.protocol === 'http:'))
    throw new OutboundError(opts.allowHttp ? 'Adresse en http:// ou https:// attendue.' : 'Adresse sécurisée (https://) attendue.', true);
  if (url.username || url.password) throw new OutboundError('Les identifiants dans l’adresse ne sont pas acceptés.', true);
  if (env.OUTBOUND_ALLOW_PRIVATE) return url;
  const host = url.hostname.replace(/^\[|\]$/g, '');
  if (BLOCKED_HOST.test(host)) throw new OutboundError('Adresse interne refusée.', true);
  let addresses: string[];
  if (isIP(host)) addresses = [host];
  else {
    try {
      addresses = (await lookup(host, { all: true, verbatim: true })).map((a) => a.address);
    } catch {
      throw new OutboundError('Adresse introuvable (DNS).');
    }
  }
  if (!addresses.length || addresses.some(isPrivateIp)) throw new OutboundError('Adresse interne refusée.', true);
  return url;
}

/**
 * Requête sortante sûre : adresse publique vérifiée (y compris après chaque redirection, 3 au plus),
 * délai maximal, taille de réponse bornée.
 */
export async function safeFetch(
  raw: string,
  init: RequestInit & { timeoutMs?: number; maxBytes?: number; allowHttp?: boolean } = {},
): Promise<{ status: number; ok: boolean; text: string; headers: Headers }> {
  const { timeoutMs = 10_000, maxBytes = 2_000_000, allowHttp, ...rest } = init;
  let target = raw;
  for (let hop = 0; hop < 4; hop++) {
    const url = await assertPublicUrl(target, { allowHttp });
    const res = await fetch(url, { ...rest, redirect: 'manual', signal: AbortSignal.timeout(timeoutMs) }).catch((err: Error) => {
      throw new OutboundError(err.name === 'TimeoutError' ? 'Délai dépassé.' : 'Serveur injoignable.');
    });
    if (res.status >= 300 && res.status < 400 && res.headers.get('location')) {
      if (rest.method && rest.method !== 'GET') throw new OutboundError(`Redirection refusée (${res.status}).`, true);
      target = new URL(res.headers.get('location')!, url).toString();
      continue;
    }
    const reader = res.body?.getReader();
    const chunks: Uint8Array[] = [];
    let size = 0;
    if (reader) {
      for (;;) {
        const { done, value } = await reader.read();
        if (done) break;
        size += value.byteLength;
        if (size > maxBytes) {
          await reader.cancel();
          throw new OutboundError('Réponse trop volumineuse.', true);
        }
        chunks.push(value);
      }
    }
    return { status: res.status, ok: res.ok, text: Buffer.concat(chunks).toString('utf8'), headers: res.headers };
  }
  throw new OutboundError('Trop de redirections.', true);
}
