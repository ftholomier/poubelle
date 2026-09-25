import type { QueueName } from '../queue';

/**
 * Tâches périodiques : le worker les inscrit dans job_schedules au démarrage puis les
 * déclenche à intervalle régulier (une seule exécution par créneau, même avec plusieurs workers).
 */
export type ScheduleDef = {
  name: string;
  queue: QueueName;
  everyMinutes: number;
  label: string;
  /** Tâches quotidiennes : heure d'exécution (heure de Paris), pour envoyer les relances en journée et purger la nuit. */
  at?: string;
  demoOnly?: boolean;
};

export const SCHEDULES: ScheduleDef[] = [
  { name: 'health.probe', queue: 'health.probe', everyMinutes: 1, label: 'Sonde de disponibilité (/api/health)' },
  { name: 'posts.publish-due', queue: 'posts.publish-due', everyMinutes: 1, label: 'Publication des contenus programmés' },
  { name: 'newsletter.dispatch', queue: 'newsletter.dispatch', everyMinutes: 1, label: 'Envoi des newsletters programmées' },
  { name: 'campaigns.status', queue: 'campaigns.status', everyMinutes: 15, label: 'Ouverture et clôture des campagnes' },
  { name: 'analytics.rollup', queue: 'analytics.rollup', everyMinutes: 60, label: 'Agrégation des statistiques d’audience' },
  { name: 'search.refresh', queue: 'search.refresh', everyMinutes: 60, label: 'Index de recherche et complétude des fiches' },
  { name: 'domains.sync', queue: 'domains.sync', everyMinutes: 5, label: 'Domaines personnalisés et certificats HTTPS' },
  { name: 'claims.reminders', queue: 'claims.reminders', everyMinutes: 1440, at: '09:30', label: 'Relances des fiches précréées et revendications' },
  { name: 'billing.overdue', queue: 'billing.overdue', everyMinutes: 1440, at: '07:00', label: 'Factures échues' },
  { name: 'maintenance.purge', queue: 'maintenance.purge', everyMinutes: 1440, at: '03:15', label: 'Purge RGPD, sessions et journaux (rétention)' },
  { name: 'demo.reset', queue: 'demo.reset', everyMinutes: 1440, at: '04:30', label: 'Réinitialisation du jeu de démonstration', demoOnly: true },
];

export const QUEUE_LABELS: Record<QueueName, string> = {
  'email.send': 'Emails transactionnels',
  'push.send': 'Notifications push',
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
  'domains.sync': 'Domaines personnalisés',
  'demo.reset': 'Réinitialisation démo',
  'i18n.translate': 'Traduction des fiches (portail multilingue)',
};
