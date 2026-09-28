import { describe, expect, it } from 'vitest';
import { contactIsLead, demoFromSite, firstIssue, siteContactSchema, siteDemoSchema, splitName } from '@/lib/site-forms';

const demo = {
  collectivite: 'Communauté de communes du Pays de Test',
  nom: 'Marie-Hélène Dupont',
  fonction: 'Vice-présidente',
  email: ' Marie@Exemple.FR ',
  tel: '',
  format: 'Une visioconférence de trente minutes',
  message: '24 communes, lancement pour Noël',
  consentement: 'on',
};

describe('formulaires du site commercial', () => {
  it('sépare prénom et nom', () => {
    expect(splitName('Marie-Hélène  Dupont')).toEqual({ firstName: 'Marie-Hélène', lastName: 'Dupont' });
    expect(splitName('Jean de La Fontaine')).toEqual({ firstName: 'Jean', lastName: 'de La Fontaine' });
    expect(splitName('Dupont')).toEqual({ firstName: '', lastName: 'Dupont' });
  });

  it('convertit une demande de démonstration', () => {
    const d = siteDemoSchema.parse(demo);
    const r = demoFromSite(d);
    expect(r).toMatchObject({ firstName: 'Marie-Hélène', lastName: 'Dupont', organization: demo.collectivite, kind: 'CC', email: 'marie@exemple.fr' });
    expect(r.phone).toBeUndefined();
    expect(r.message).toBe('24 communes, lancement pour Noël\nFormat souhaité : Une visioconférence de trente minutes.');
  });

  it('exige le consentement et une adresse valide', () => {
    const noConsent = siteDemoSchema.safeParse({ ...demo, consentement: undefined });
    expect(noConsent.success).toBe(false);
    const bad = siteDemoSchema.safeParse({ ...demo, email: 'pas-une-adresse' });
    expect(bad.success).toBe(false);
    if (!bad.success) expect(firstIssue(bad.error)).toBe('Adresse électronique invalide.');
    const empty = siteDemoSchema.safeParse({});
    if (!empty.success) expect(firstIssue(empty.error)).toBe('Indiquez votre collectivité.');
  });

  it('accepte le type de collectivité et le nombre de communes', () => {
    const d = siteDemoSchema.parse({ ...demo, type: 'COMMUNE', communes: '1' });
    expect(demoFromSite(d)).toMatchObject({ kind: 'COMMUNE', communes: 1 });
    expect(siteDemoSchema.parse({ ...demo, communes: '' }).communes).toBeUndefined();
  });

  it('ne compte comme prospect que les messages de collectivités', () => {
    const base = { nom: 'Paul Martin', email: 'paul@exemple.fr', message: 'Pouvez-vous nous envoyer une note ?', consentement: 'on' };
    const cc = siteContactSchema.parse({ ...base, collectivite: 'Mairie de Test', objet: 'Des documents pour notre conseil' });
    expect(contactIsLead(cc)).toBe(true);
    const pro = siteContactSchema.parse({ ...base, collectivite: 'Boulangerie Martin', objet: 'Je suis une entreprise' });
    expect(contactIsLead(pro)).toBe(false);
    const anonyme = siteContactSchema.parse({ ...base, objet: 'Une question sur terricom' });
    expect(contactIsLead(anonyme)).toBe(false);
    expect(siteContactSchema.parse({ ...base, objet: 'inconnu' }).objet).toBe('Autre');
  });
});
