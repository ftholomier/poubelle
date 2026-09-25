import type { QueueName } from '../queue';

/**
 * Tâches périodiques : le worker les inscrit dans job_schedules au démarrage puis les
 * déclenche à intervalle régulier (une seule exécution par créneau, même avec plusieurs workers).
 */
export type ScheduleDef = { name: string; queue: QueueName; everyMinutes: number; label: string; demoOnly?: boolean };

export const SCHEDULES: ScheduleDef[] = [
  { name: 'health.probe', queue: 'health.probe', everyMinutes: 1, label: 'Sonde de disponibilité (/api/health)' },
  { name: 'posts.publish-due', queue: 'posts.publish-due', everyMinutes: 1, label: 'Publication des contenus programmés' },
  { name: 'newsletter.dispatch', queue: 'newsletter.dispatch', everyMinutes: 1, label: 'Envoi des newsletters programmées' },
  { name: 'campaigns.status', queue: 'campaigns.status', everyMinutes: 15, label: 'Ouverture et clôture des campagnes' },
  { name: 'analytics.rollup', queue: 'analytics.rollup', everyMinutes: 60, label: 'Agrégation des statistiques d’audience' },
  { name: 'search.refresh', queue: 'search.refresh', everyMinutes: 60, label: 'Index de recherche et complétude des fiches' },
  { name: 'claims.reminders', queue: 'claims.reminders', everyMinutes: 1440, label: 'Relances des fiches précréées et revendications' },
  { name: 'billing.overdue', queue: 'billing.overdue', everyMinutes: 1440, label: 'Factures échues' },
  { name: 'maintenance.purge', queue: 'maintenance.purge', everyMinutes: 1440, label: 'Purge RGPD, sessions et journaux (rétention)' },
  { name: 'demo.reset', queue: 'demo.reset', everyMinutes: 1440, label: 'Réinitialisation du jeu de démonstration', demoOnly: true },
];

export const QUEUE_LABELS: Record<QueueName, string> = {
  'email.send': 'Emails transactionnels',
  'newsletter.dispatch': 'Préparation des newsletters',
  'newsletter.send-batch': 'Envoi des newsletters (lots)',
  'posts.publish-due': 'Publications programmées',
  'posts.social-sync': 'Diffusion réseaux sociaux',
  'analytics.rollup': 'Agrégats d’audience',
  'maintenance.purge': 'Purge et rétention',
  'claims.reminders': 'Relances revendication',
  'import.geocode': 'Géocodage des imports',
  'import.sirene': 'Import SIRENE',
  'search.refresh': 'Index de recherche',
  'campaigns.status': 'Statut des campagnes',
  'health.probe': 'Sonde de disponibilité',
  'billing.overdue': 'Factures échues',
  'demo.reset': 'Réinitialisation démo',
};
