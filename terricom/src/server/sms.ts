import { env } from './env';
import { logger } from './logger';

/** Numéro de mobile français (06, 07, ou +33 6/7), seul capable de recevoir un SMS. */
export function isMobileNumber(phone: string | null | undefined): boolean {
  if (!phone) return false;
  const clean = phone.replace(/[\s.()-]/g, '');
  return /^(?:0|\+33|0033)[67]\d{8}$/.test(clean);
}

/** Masque un numéro en ne gardant que les deux premiers et deux derniers chiffres. */
export function maskPhone(phone: string): string {
  const digits = phone.replace(/\D/g, '').replace(/^33/, '0');
  if (digits.length < 6) return '•• •• ••';
  return `${digits.slice(0, 2)} •• •• •• ${digits.slice(-2)}`;
}

export function smsAvailable(): boolean {
  return Boolean(env.SMS_WEBHOOK_URL);
}

/**
 * Envoi d'un SMS via une passerelle HTTP générique (Brevo, OVHcloud, Twilio… derrière un relais).
 * Renvoie false si aucune passerelle n'est configurée ou si l'envoi échoue.
 */
export async function sendSms(to: string, text: string): Promise<boolean> {
  if (!env.SMS_WEBHOOK_URL) return false;
  try {
    const res = await fetch(env.SMS_WEBHOOK_URL, {
      method: 'POST',
      headers: {
        'content-type': 'application/json',
        ...(env.SMS_WEBHOOK_TOKEN ? { authorization: `Bearer ${env.SMS_WEBHOOK_TOKEN}` } : {}),
      },
      body: JSON.stringify({ to: to.replace(/[\s.]/g, ''), text }),
      signal: AbortSignal.timeout(8000),
    });
    if (!res.ok) logger.warn('sms.http_error', { status: res.status });
    return res.ok;
  } catch (err) {
    logger.warn('sms.unreachable', { err: err instanceof Error ? err.message : String(err) });
    return false;
  }
}
