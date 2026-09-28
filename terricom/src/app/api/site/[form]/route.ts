import { NextResponse, type NextRequest } from 'next/server';
import { contactIsLead, demoFromSite, firstIssue, siteContactSchema, siteDemoSchema } from '@/lib/site-forms';
import { env } from '@/server/env';
import { requestInfo } from '@/server/request';
import { recordContactMessage, recordDemoRequest } from '@/server/services/site-requests';

/**
 * Formulaires du site commercial statique terricom.fr (site-terricom/) : POST /api/site/demonstration et
 * POST /api/site/contact, en JSON ou en formulaire classique. Réponse JSON { ok, message }.
 * Appel inter-origines autorisé pour les seules adresses de SITE_ORIGINS (et l'application elle-même).
 */

const SOURCE = 'Site terricom.fr';

function allowedOrigins(): Set<string> {
  const list = env.SITE_ORIGINS.split(',').map((o) => o.trim().replace(/\/$/, ''));
  list.push(new URL(env.APP_URL).origin);
  return new Set(list.filter(Boolean));
}

function cors(req: NextRequest): Record<string, string> {
  const origin = req.headers.get('origin');
  if (!origin || !allowedOrigins().has(origin)) return { Vary: 'Origin' };
  return {
    'Access-Control-Allow-Origin': origin,
    'Access-Control-Allow-Methods': 'POST, OPTIONS',
    'Access-Control-Allow-Headers': 'Content-Type',
    'Access-Control-Max-Age': '86400',
    Vary: 'Origin',
  };
}

const reply = (req: NextRequest, status: number, body: { ok: boolean; message: string }) =>
  NextResponse.json(body, { status, headers: { ...cors(req), 'Cache-Control': 'no-store' } });

export function OPTIONS(req: NextRequest) {
  return new NextResponse(null, { status: 204, headers: cors(req) });
}

async function readBody(req: NextRequest): Promise<Record<string, unknown>> {
  const type = req.headers.get('content-type') ?? '';
  if (type.includes('application/json')) return (await req.json().catch(() => ({}))) as Record<string, unknown>;
  const form = await req.formData().catch(() => null);
  return form ? Object.fromEntries(form) : {};
}

export async function POST(req: NextRequest, { params }: { params: Promise<{ form: string }> }) {
  const origin = req.headers.get('origin');
  if (origin && !allowedOrigins().has(origin)) return reply(req, 403, { ok: false, message: 'Origine non autorisée.' });
  const { form } = await params;
  const body = await readBody(req);
  // Piège à robots : champ invisible rempli, on répond comme si de rien n'était.
  if (typeof body.website === 'string' && body.website) return reply(req, 200, { ok: true, message: 'Merci.' });
  const { ip } = await requestInfo();

  if (form === 'demonstration') {
    const parsed = siteDemoSchema.safeParse(body);
    if (!parsed.success) return reply(req, 422, { ok: false, message: firstIssue(parsed.error) });
    const res = await recordDemoRequest(demoFromSite(parsed.data), { source: SOURCE, ip });
    if (!res.ok) return reply(req, 429, { ok: false, message: res.message });
    return reply(req, 200, {
      ok: true,
      message: 'Merci ! Votre demande est bien enregistrée. Nous vous écrivons sous 48 heures ouvrées pour caler la démonstration de votre territoire.',
    });
  }

  if (form === 'contact') {
    const parsed = siteContactSchema.safeParse(body);
    if (!parsed.success) return reply(req, 422, { ok: false, message: firstIssue(parsed.error) });
    const c = parsed.data;
    const res = await recordContactMessage(
      { name: c.nom, email: c.email, organization: c.collectivite, subject: c.objet, message: c.message, lead: contactIsLead(c) },
      { source: SOURCE, ip },
    );
    if (!res.ok) return reply(req, 429, { ok: false, message: res.message });
    return reply(req, 200, { ok: true, message: 'Merci ! Votre message est bien arrivé. Nous vous répondons sous deux jours ouvrés.' });
  }

  return reply(req, 404, { ok: false, message: 'Formulaire inconnu.' });
}
