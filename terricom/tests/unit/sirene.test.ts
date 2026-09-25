import { describe, expect, it } from 'vitest';
import {
  diffSirene,
  fromInsee,
  fromRecherche,
  fromStockRow,
  inseeQuery,
  isExcludedActivity,
  parseNafList,
  sireneStreet,
  stockUrls,
  syncDue,
  type InseeEtablissement,
} from '@/lib/sirene';
import type { SireneRecord } from '@/server/db/schema';

const rec = (siret: string, active = true, over: Partial<SireneRecord> = {}): SireneRecord => ({
  siret,
  name: `Établissement ${siret.slice(-3)}`,
  naf: '47.11B',
  street: '1 rue du Pont',
  postalCode: '25290',
  inseeCode: '25434',
  lat: null,
  lng: null,
  active,
  ...over,
});

describe('activités exclues', () => {
  it('exclut par défaut SCI, holdings et administrations', () => {
    expect(isExcludedActivity('68.20B', undefined)).toBe(true);
    expect(isExcludedActivity('70.10Z', undefined)).toBe(true);
    expect(isExcludedActivity('84.11Z', undefined)).toBe(true);
    expect(isExcludedActivity('10.71C', undefined)).toBe(false);
    expect(isExcludedActivity('86.21Z', undefined)).toBe(false);
  });

  it('reconnaît une SCI à sa forme juridique', () => {
    expect(isExcludedActivity('47.11B', undefined, '6540')).toBe(true);
    expect(isExcludedActivity('47.11B', { excludedGroups: [] }, '6540')).toBe(false);
  });

  it('applique les groupes et codes choisis par le territoire', () => {
    const s = { excludedGroups: ['sante'], excludedNaf: parseNafList('56.10A') };
    expect(isExcludedActivity('86.21Z', s)).toBe(true);
    expect(isExcludedActivity('68.20B', s)).toBe(false);
    expect(isExcludedActivity('56.10A', s)).toBe(true);
    expect(isExcludedActivity('56.10C', s)).toBe(false);
    expect(isExcludedActivity(null, s)).toBe(false);
  });

  it('normalise les codes NAF saisis librement', () => {
    expect(parseNafList('68.20B, 7010 ; 94  zz 68.20b')).toEqual(['6820B', '7010', '94']);
  });
});

describe('lecture des sources SIRENE', () => {
  it('développe les types de voie', () => {
    expect(sireneStreet('12', 'B', 'AV', 'DU PRESIDENT WILSON')).toBe('12 bis avenue Du President Wilson');
    expect(sireneStreet(null, null, null, null)).toBeNull();
  });

  it('convertit un établissement de l’API Sirene (INSEE)', () => {
    const e: InseeEtablissement = {
      siret: '98123456000010',
      statutDiffusionEtablissement: 'O',
      dateCreationEtablissement: '2026-08-01',
      uniteLegale: { denominationUniteLegale: 'FOURNIL DE LA LOUE', categorieJuridiqueUniteLegale: '5499' },
      adresseEtablissement: {
        numeroVoieEtablissement: '12',
        typeVoieEtablissement: 'RUE',
        libelleVoieEtablissement: 'SAINT LAURENT',
        codePostalEtablissement: '25290',
        codeCommuneEtablissement: '25434',
        libelleCommuneEtablissement: 'ORNANS',
      },
      periodesEtablissement: [
        {
          dateFin: null,
          dateDebut: '2026-08-01',
          etatAdministratifEtablissement: 'A',
          activitePrincipaleEtablissement: '10.71C',
          enseigne1Etablissement: null,
        },
      ],
    };
    const r = fromInsee(e)!;
    expect(r).toMatchObject({
      name: 'Fournil De La Loue',
      naf: '10.71C',
      inseeCode: '25434',
      city: 'Ornans',
      active: true,
      legalCategory: '5499',
      street: '12 rue Saint Laurent',
    });
    expect(fromInsee({ ...e, statutDiffusionEtablissement: 'P' })).toBeNull();
    const closed = fromInsee({ ...e, periodesEtablissement: [{ dateFin: null, dateDebut: '2026-09-01', etatAdministratifEtablissement: 'F' }] })!;
    expect(closed.active).toBe(false);
    expect(closed.changedOn).toBe('2026-09-01');
  });

  it('construit la requête des changements depuis une date', () => {
    expect(inseeQuery(['25434', '25475'], new Date('2026-08-25T05:10:00Z'))).toBe(
      '(codeCommuneEtablissement:25434 OR codeCommuneEtablissement:25475) AND dateDernierTraitementEtablissement:[2026-08-25T05:10:00 TO *]',
    );
  });

  it('ne garde que les établissements de la commune (API Recherche d’entreprises)', () => {
    const out = fromRecherche(
      {
        nom_complet: 'EPICERIE DU PONT',
        nature_juridique: '1000',
        matching_etablissements: [
          {
            siret: '98123678200018',
            commune: '25475',
            etat_administratif: 'A',
            adresse: '1 PLACE DE LA MAIRIE 25440 QUINGEY',
            latitude: '47.1',
            longitude: '5.88',
            date_creation: '2026-09-02',
          },
          { siret: '98123678200026', commune: '25434', etat_administratif: 'A' },
        ],
      },
      '25475',
    );
    expect(out).toHaveLength(1);
    expect(out[0]).toMatchObject({ name: 'Epicerie Du Pont', street: '1 Place De La Mairie', lat: 47.1, active: true, createdOn: '2026-09-02' });
  });

  it('filtre le fichier stock sur les communes et les établissements actifs', () => {
    const codes = new Set(['25434']);
    const row = {
      siret: '98123567100014',
      codeCommuneEtablissement: '25434',
      etatAdministratifEtablissement: 'A',
      enseigne1Etablissement: 'ATELIER VELO LOUE',
      activitePrincipaleEtablissement: '45.20A',
      latitude: '47.106',
      longitude: '6.144',
    };
    expect(fromStockRow(row, codes)).toMatchObject({ name: 'Atelier Velo Loue', lat: 47.106, active: true });
    expect(fromStockRow({ ...row, etatAdministratifEtablissement: 'F' }, codes)).toBeNull();
    expect(fromStockRow({ ...row, codeCommuneEtablissement: '25475' }, codes)).toBeNull();
  });

  it('liste un fichier stock par département', () => {
    expect(stockUrls(['25', '39', '25', null], 'https://ex.test/geo_siret_{dep}.csv.gz')).toEqual([
      'https://ex.test/geo_siret_25.csv.gz',
      'https://ex.test/geo_siret_39.csv.gz',
    ]);
  });
});

describe('comparaison avec les fiches', () => {
  const known = [
    { id: 'a', siret: '00000000000001', status: 'PRECREATED', communeInsee: '25434' },
    { id: 'b', siret: '00000000000002', status: 'VALIDATED', communeInsee: '25434' },
    { id: 'c', siret: '00000000000003', status: 'ARCHIVED', communeInsee: '25434' },
    { id: 'd', siret: '00000000000004', status: 'PRECREATED', communeInsee: '25475' },
  ];

  it('propose les nouveautés et les fermetures, sans répéter les décisions', () => {
    const { creations, closures } = diffSirene({
      records: [rec('00000000000009'), rec('00000000000008'), rec('00000000000002', false), rec('00000000000003', false), rec('00000000000001')],
      known,
      decided: new Set(['CREATION|00000000000008']),
    });
    expect(creations.map((c) => c.siret)).toEqual(['00000000000009']);
    expect(closures).toEqual([expect.objectContaining({ listingId: 'b', toConfirm: false })]);
  });

  it('signale à confirmer les fiches absentes d’une commune lue intégralement', () => {
    const { closures } = diffSirene({ records: [rec('00000000000001')], known, decided: new Set(), scannedCommunes: new Set(['25434']) });
    expect(closures).toEqual([expect.objectContaining({ listingId: 'b', record: null, toConfirm: true })]);
  });

  it('déclenche la synchronisation mensuelle après 28 jours', () => {
    const now = new Date('2026-09-25T05:10:00Z');
    expect(syncDue(null, now)).toBe(true);
    expect(syncDue(new Date('2026-09-01T05:10:00Z'), now)).toBe(false);
    expect(syncDue(new Date('2026-08-28T05:10:00Z'), now)).toBe(true);
  });
});
