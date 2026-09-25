import { relations } from 'drizzle-orm';
import { adventDoors, campaignParticipants, campaigns, circuitStops, circuits, passportStamps, passports } from './animation';
import {
  companies,
  companyMembers,
  establishmentAttributes,
  establishmentCategories,
  establishmentPages,
  establishments,
  exceptionalHours,
  media,
  openingHours,
  products,
} from './business';
import { claims, establishmentRevisions } from './claims';
import { appointments, events, jobApplications, jobs, markets, messages, posts } from './content';
import { dealActivities, dealContacts, dealDocuments, dealTasks, deals } from './crm';
import { audiences, newsletterDeliveries, newsletters, subscriberAudiences, subscribers } from './newsletter';
import {
  attributes,
  categories,
  communeMemberships,
  communes,
  territories,
  territoryDomains,
  territoryModules,
} from './tenancy';
import { roleAssignments, sessions, users } from './users';

export * from './enums';
export * from './tenancy';
export * from './users';
export * from './business';
export * from './content';
export * from './claims';
export * from './animation';
export * from './newsletter';
export * from './analytics';
export * from './billing';
export * from './crm';
export * from './platform';

// ─── Relations (API de requêtes relationnelles de Drizzle) ──────────────────

export const territoriesRelations = relations(territories, ({ many }) => ({
  memberships: many(communeMemberships),
  domains: many(territoryDomains),
  modules: many(territoryModules),
  establishments: many(establishments),
  campaigns: many(campaigns),
  circuits: many(circuits),
}));

export const territoryDomainsRelations = relations(territoryDomains, ({ one }) => ({
  territory: one(territories, { fields: [territoryDomains.territoryId], references: [territories.id] }),
}));

export const territoryModulesRelations = relations(territoryModules, ({ one }) => ({
  territory: one(territories, { fields: [territoryModules.territoryId], references: [territories.id] }),
}));

export const communesRelations = relations(communes, ({ many }) => ({
  memberships: many(communeMemberships),
  establishments: many(establishments),
  markets: many(markets),
}));

export const communeMembershipsRelations = relations(communeMemberships, ({ one }) => ({
  commune: one(communes, { fields: [communeMemberships.communeId], references: [communes.id] }),
  territory: one(territories, { fields: [communeMemberships.territoryId], references: [territories.id] }),
}));

export const categoriesRelations = relations(categories, ({ many }) => ({
  establishments: many(establishments),
}));

export const attributesRelations = relations(attributes, ({ many }) => ({
  establishments: many(establishmentAttributes),
}));

export const usersRelations = relations(users, ({ many }) => ({
  roles: many(roleAssignments),
  memberships: many(companyMembers),
  sessions: many(sessions),
}));

export const sessionsRelations = relations(sessions, ({ one }) => ({
  user: one(users, { fields: [sessions.userId], references: [users.id] }),
}));

export const roleAssignmentsRelations = relations(roleAssignments, ({ one }) => ({
  user: one(users, { fields: [roleAssignments.userId], references: [users.id] }),
  territory: one(territories, { fields: [roleAssignments.territoryId], references: [territories.id] }),
  commune: one(communes, { fields: [roleAssignments.communeId], references: [communes.id] }),
}));

export const companiesRelations = relations(companies, ({ many }) => ({
  establishments: many(establishments),
  members: many(companyMembers),
}));

export const companyMembersRelations = relations(companyMembers, ({ one }) => ({
  company: one(companies, { fields: [companyMembers.companyId], references: [companies.id] }),
  user: one(users, { fields: [companyMembers.userId], references: [users.id] }),
}));

export const establishmentsRelations = relations(establishments, ({ one, many }) => ({
  company: one(companies, { fields: [establishments.companyId], references: [companies.id] }),
  commune: one(communes, { fields: [establishments.communeId], references: [communes.id] }),
  territory: one(territories, { fields: [establishments.territoryId], references: [territories.id] }),
  category: one(categories, { fields: [establishments.categoryId], references: [categories.id] }),
  hours: many(openingHours),
  exceptionalHours: many(exceptionalHours),
  media: many(media),
  products: many(products),
  pages: many(establishmentPages),
  attributes: many(establishmentAttributes),
  secondaryCategories: many(establishmentCategories),
  posts: many(posts),
  events: many(events),
  jobs: many(jobs),
  claims: many(claims),
  revisions: many(establishmentRevisions),
}));

export const establishmentAttributesRelations = relations(establishmentAttributes, ({ one }) => ({
  establishment: one(establishments, {
    fields: [establishmentAttributes.establishmentId],
    references: [establishments.id],
  }),
  attribute: one(attributes, { fields: [establishmentAttributes.attributeId], references: [attributes.id] }),
}));

export const establishmentCategoriesRelations = relations(establishmentCategories, ({ one }) => ({
  establishment: one(establishments, {
    fields: [establishmentCategories.establishmentId],
    references: [establishments.id],
  }),
  category: one(categories, { fields: [establishmentCategories.categoryId], references: [categories.id] }),
}));

export const openingHoursRelations = relations(openingHours, ({ one }) => ({
  establishment: one(establishments, { fields: [openingHours.establishmentId], references: [establishments.id] }),
}));

export const exceptionalHoursRelations = relations(exceptionalHours, ({ one }) => ({
  establishment: one(establishments, {
    fields: [exceptionalHours.establishmentId],
    references: [establishments.id],
  }),
}));

export const mediaRelations = relations(media, ({ one }) => ({
  establishment: one(establishments, { fields: [media.establishmentId], references: [establishments.id] }),
}));

export const productsRelations = relations(products, ({ one }) => ({
  establishment: one(establishments, { fields: [products.establishmentId], references: [establishments.id] }),
}));

export const establishmentPagesRelations = relations(establishmentPages, ({ one }) => ({
  establishment: one(establishments, {
    fields: [establishmentPages.establishmentId],
    references: [establishments.id],
  }),
}));

export const postsRelations = relations(posts, ({ one }) => ({
  establishment: one(establishments, { fields: [posts.establishmentId], references: [establishments.id] }),
  territory: one(territories, { fields: [posts.territoryId], references: [territories.id] }),
  commune: one(communes, { fields: [posts.communeId], references: [communes.id] }),
}));

export const eventsRelations = relations(events, ({ one }) => ({
  establishment: one(establishments, { fields: [events.establishmentId], references: [establishments.id] }),
  territory: one(territories, { fields: [events.territoryId], references: [territories.id] }),
  commune: one(communes, { fields: [events.communeId], references: [communes.id] }),
}));

export const marketsRelations = relations(markets, ({ one }) => ({
  commune: one(communes, { fields: [markets.communeId], references: [communes.id] }),
}));

export const jobsRelations = relations(jobs, ({ one, many }) => ({
  establishment: one(establishments, { fields: [jobs.establishmentId], references: [establishments.id] }),
  commune: one(communes, { fields: [jobs.communeId], references: [communes.id] }),
  applications: many(jobApplications),
}));

export const jobApplicationsRelations = relations(jobApplications, ({ one }) => ({
  job: one(jobs, { fields: [jobApplications.jobId], references: [jobs.id] }),
}));

export const messagesRelations = relations(messages, ({ one }) => ({
  establishment: one(establishments, { fields: [messages.establishmentId], references: [establishments.id] }),
}));

export const appointmentsRelations = relations(appointments, ({ one }) => ({
  establishment: one(establishments, { fields: [appointments.establishmentId], references: [establishments.id] }),
}));

export const claimsRelations = relations(claims, ({ one }) => ({
  establishment: one(establishments, { fields: [claims.establishmentId], references: [establishments.id] }),
  user: one(users, { fields: [claims.userId], references: [users.id], relationName: 'claimant' }),
  reviewer: one(users, { fields: [claims.reviewerId], references: [users.id], relationName: 'reviewer' }),
}));

export const establishmentRevisionsRelations = relations(establishmentRevisions, ({ one }) => ({
  establishment: one(establishments, {
    fields: [establishmentRevisions.establishmentId],
    references: [establishments.id],
  }),
  user: one(users, { fields: [establishmentRevisions.userId], references: [users.id] }),
}));

export const campaignsRelations = relations(campaigns, ({ one, many }) => ({
  territory: one(territories, { fields: [campaigns.territoryId], references: [territories.id] }),
  participants: many(campaignParticipants),
  doors: many(adventDoors),
}));

export const campaignParticipantsRelations = relations(campaignParticipants, ({ one }) => ({
  campaign: one(campaigns, { fields: [campaignParticipants.campaignId], references: [campaigns.id] }),
  establishment: one(establishments, {
    fields: [campaignParticipants.establishmentId],
    references: [establishments.id],
  }),
}));

export const adventDoorsRelations = relations(adventDoors, ({ one }) => ({
  campaign: one(campaigns, { fields: [adventDoors.campaignId], references: [campaigns.id] }),
  establishment: one(establishments, { fields: [adventDoors.establishmentId], references: [establishments.id] }),
}));

export const circuitsRelations = relations(circuits, ({ one, many }) => ({
  territory: one(territories, { fields: [circuits.territoryId], references: [territories.id] }),
  stops: many(circuitStops),
  passports: many(passports),
}));

export const circuitStopsRelations = relations(circuitStops, ({ one }) => ({
  circuit: one(circuits, { fields: [circuitStops.circuitId], references: [circuits.id] }),
  establishment: one(establishments, { fields: [circuitStops.establishmentId], references: [establishments.id] }),
}));

export const passportsRelations = relations(passports, ({ one, many }) => ({
  circuit: one(circuits, { fields: [passports.circuitId], references: [circuits.id] }),
  stamps: many(passportStamps),
}));

export const passportStampsRelations = relations(passportStamps, ({ one }) => ({
  passport: one(passports, { fields: [passportStamps.passportId], references: [passports.id] }),
  stop: one(circuitStops, { fields: [passportStamps.stopId], references: [circuitStops.id] }),
}));

export const subscribersRelations = relations(subscribers, ({ many, one }) => ({
  audiences: many(subscriberAudiences),
  commune: one(communes, { fields: [subscribers.communeId], references: [communes.id] }),
}));

export const audiencesRelations = relations(audiences, ({ many }) => ({
  subscribers: many(subscriberAudiences),
}));

export const subscriberAudiencesRelations = relations(subscriberAudiences, ({ one }) => ({
  subscriber: one(subscribers, { fields: [subscriberAudiences.subscriberId], references: [subscribers.id] }),
  audience: one(audiences, { fields: [subscriberAudiences.audienceId], references: [audiences.id] }),
}));

export const newslettersRelations = relations(newsletters, ({ many }) => ({
  deliveries: many(newsletterDeliveries),
}));

export const newsletterDeliveriesRelations = relations(newsletterDeliveries, ({ one }) => ({
  newsletter: one(newsletters, { fields: [newsletterDeliveries.newsletterId], references: [newsletters.id] }),
}));

export const dealsRelations = relations(deals, ({ one, many }) => ({
  owner: one(users, { fields: [deals.ownerId], references: [users.id] }),
  contacts: many(dealContacts),
  activities: many(dealActivities),
  tasks: many(dealTasks),
  documents: many(dealDocuments),
}));

export const dealContactsRelations = relations(dealContacts, ({ one }) => ({
  deal: one(deals, { fields: [dealContacts.dealId], references: [deals.id] }),
}));

export const dealActivitiesRelations = relations(dealActivities, ({ one }) => ({
  deal: one(deals, { fields: [dealActivities.dealId], references: [deals.id] }),
}));

export const dealTasksRelations = relations(dealTasks, ({ one }) => ({
  deal: one(deals, { fields: [dealTasks.dealId], references: [deals.id] }),
}));

export const dealDocumentsRelations = relations(dealDocuments, ({ one }) => ({
  deal: one(deals, { fields: [dealDocuments.dealId], references: [deals.id] }),
}));
