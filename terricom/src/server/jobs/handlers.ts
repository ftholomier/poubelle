import { parisDate } from '@/lib/format';
import { logger } from '../logger';
import { deliverEmail } from '../mail/send';
import type { QueueName } from '../queue';
import { runSireneImport } from '../services/imports';
import { syncCustomDomains } from './domains';
import { dispatchDueNewsletters, dispatchNewsletter, sendNewsletterBatch } from '../services/newsletters';
import {
  billingDaily,
  claimReminders,
  demoReset,
  geocodeEstablishment,
  healthProbe,
  publishDuePosts,
  purgeRetention,
  refreshSearch,
  rollupAnalytics,
  socialSync,
  updateCampaignStatuses,
} from './tasks';

type Payload = Record<string, unknown>;
type Handler = (payload: Payload) => Promise<unknown>;

const str = (p: Payload, key: string): string => {
  const v = p[key];
  if (typeof v !== 'string' || !v) throw new Error(`Paramètre « ${key} » manquant`);
  return v;
};

/** Correspondance file → traitement. Toute file déclarée dans QueueName doit avoir son traitement. */
export const HANDLERS: Record<QueueName, Handler> = {
  'email.send': (p) => deliverEmail(str(p, 'emailId')),
  'newsletter.dispatch': (p) => (typeof p.newsletterId === 'string' ? dispatchNewsletter(p.newsletterId) : dispatchDueNewsletters()),
  'newsletter.send-batch': (p) => sendNewsletterBatch(str(p, 'newsletterId')),
  'posts.publish-due': () => publishDuePosts(),
  'posts.social-sync': (p) => socialSync(str(p, 'postId')),
  'analytics.rollup': () => rollupAnalytics(),
  'maintenance.purge': () => purgeRetention(),
  'claims.reminders': () => claimReminders(),
  'import.geocode': (p) => geocodeEstablishment(str(p, 'establishmentId')),
  'import.sirene': (p) => runSireneImport(str(p, 'batchId')),
  'search.refresh': (p) => refreshSearch(p.full === true),
  'campaigns.status': () => updateCampaignStatuses(),
  'health.probe': () => healthProbe(),
  'billing.overdue': () => billingDaily(),
  'domains.sync': () => syncCustomDomains(),
  'demo.reset': () => demoReset(),
};

export async function runJob(queue: string, payload: Payload): Promise<unknown> {
  const handler = HANDLERS[queue as QueueName];
  if (!handler) throw new Error(`File inconnue : ${queue}`);
  const t0 = performance.now();
  const result = await handler(payload);
  logger.info('job.done', { queue, ms: Math.round(performance.now() - t0), day: parisDate(), result: typeof result === 'object' ? result : undefined });
  return result;
}
