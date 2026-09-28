'use server';

import { z } from 'zod';
import { requestInfo } from '@/server/request';
import { recordDemoRequest } from '@/server/services/site-requests';

export type DemoState = { status: 'idle' | 'ok' | 'error'; message?: string; name?: string };

const schema = z.object({
  firstName: z.string().trim().min(1, 'Votre prénom').max(80),
  lastName: z.string().trim().min(1, 'Votre nom').max(80),
  role: z.string().trim().max(160).optional(),
  organization: z.string().trim().min(2, 'Le nom de votre collectivité').max(200),
  kind: z.enum(['CC', 'CA', 'CU', 'METROPOLE', 'COMMUNE', 'PETR', 'OFFICE', 'AUTRE']),
  communes: z.coerce
    .number()
    .int()
    .min(1)
    .max(500)
    .optional()
    .or(z.literal('').transform(() => undefined)),
  email: z.string().trim().toLowerCase().email('Adresse email invalide'),
  phone: z.string().trim().max(32).optional(),
  message: z.string().trim().max(3000).optional(),
  consent: z.literal('on', { error: 'Merci d’accepter d’être recontacté·e' }),
  website: z.string().max(0).optional(),
});

/** Demande de démonstration depuis le site : crée une affaire dans le suivi commercial et accuse réception. */
export async function requestDemoAction(_prev: DemoState, form: FormData): Promise<DemoState> {
  if (String(form.get('website') ?? '')) return { status: 'ok', name: '' }; // piège à robots : on ne dit rien
  const parsed = schema.safeParse(Object.fromEntries(form));
  if (!parsed.success) return { status: 'error', message: parsed.error.issues[0]?.message ?? 'Formulaire incomplet.' };
  const d = parsed.data;
  const info = await requestInfo();
  const res = await recordDemoRequest(
    { ...d, role: d.role || undefined, phone: d.phone || undefined, message: d.message || undefined },
    { source: 'Formulaire terricom.fr', ip: info.ip },
  );
  if (!res.ok) return { status: 'error', message: res.message };
  return { status: 'ok', name: d.firstName };
}
