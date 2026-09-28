import { customType, timestamp, uuid } from 'drizzle-orm/pg-core';

/** Texte insensible à la casse (extension citext) — utilisé pour les emails. */
export const citext = customType<{ data: string; driverData: string }>({
  dataType() {
    return 'citext';
  },
});

/** Vecteur de recherche plein texte PostgreSQL. */
export const tsvector = customType<{ data: string; driverData: string }>({
  dataType() {
    return 'tsvector';
  },
});

export const pk = () => uuid().primaryKey().defaultRandom();

export const createdAt = () => timestamp({ withTimezone: true }).notNull().defaultNow();

export const updatedAt = () =>
  timestamp({ withTimezone: true })
    .notNull()
    .defaultNow()
    .$onUpdate(() => new Date());

export const tstz = () => timestamp({ withTimezone: true });
