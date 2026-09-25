/**
 * Jeu de données de démonstration : territoire pilote du Val de Loue (conforme aux maquettes),
 * cinq autres territoires clients, pipeline commercial, statistiques d'usage simulées.
 *
 *   npm run db:seed           (base vide)
 *   npm run db:reset          (réinitialise, migre et réensemence)
 */
import { loadEnvFile } from './_env';

loadEnvFile();

const DEMO_PASSWORD = 'Terricom2026!';
const DEMO_TOTP_SECRET = 'JBSWY3DPEHPK3PXP';

async function main() {
  const started = Date.now();
  const { db, pool } = await import('@/server/db');
  const S = await import('@/server/db/schema');
  const { sql, eq, inArray } = await import('drizzle-orm');
  const { hashPassword, encrypt, randomToken, shortCode, sha256 } = await import('@/server/crypto');
  const { slugify } = await import('@/lib/slug');
  const { refreshCompleteness } = await import('@/server/services/establishments');
  const { audit } = await import('@/server/audit');
  const { parisDate } = await import('@/lib/format');
  const D = await import('./seed/data');
  const R = await import('./seed/random');
  const { OTHER_TERRITORIES, DEALS } = await import('./seed/territories');
  const { completeSiret } = await import('@/server/integrations/public-data');
  /** SIRET des maquettes, avec une clé de contrôle valide. */
  const fixSiret = (s: string) => completeSiret(s.slice(0, 13));

  const existing = await db.select({ id: S.territories.id }).from(S.territories).limit(1);
  if (existing.length && !process.argv.includes('--force')) {
    console.log('ℹ La base contient déjà des données. Utilisez « npm run db:reset » pour repartir de zéro.');
    await pool.end();
    return;
  }

  const r = R.rng(20260925);
  const now = new Date();
  const DAY = 86_400_000;
  const daysAgo = (n: number, h = 10) => {
    const d = new Date(now.getTime() - n * DAY - (now.getHours() - h) * 3_600_000);
    // Jamais dans le futur (graine lancée tôt le matin).
    return d > now ? new Date(now.getTime() - (5 + n) * 60_000) : d;
  };
  const hoursAgo = (n: number) => new Date(now.getTime() - n * 3_600_000);
  const isoDay = (d: Date) => parisDate(d);
  const addIso = (iso: string, n: number) => {
    const d = new Date(`${iso}T12:00:00Z`);
    d.setUTCDate(d.getUTCDate() + n);
    return d.toISOString().slice(0, 10);
  };
  /** Date à heure locale de Paris. */
  const parisAt = (iso: string, hhmm: string) => {
    const probe = new Date(`${iso}T12:00:00Z`);
    const off = new Intl.DateTimeFormat('en-US', { timeZone: 'Europe/Paris', timeZoneName: 'shortOffset' })
      .formatToParts(probe)
      .find((p) => p.type === 'timeZoneName')!.value.replace('GMT', '');
    const [sign, hh] = [off.startsWith('-') ? '-' : '+', off.replace(/[+-]/, '').padStart(2, '0')];
    return new Date(`${iso}T${hhmm}:00${sign}${hh}:00`);
  };
  const today = isoDay(now);
  const weekdayOf = (iso: string) => (new Date(`${iso}T12:00:00Z`).getUTCDay() + 6) % 7;
  /** Prochain jour de semaine donné (0 = lundi), au moins minAhead jours plus tard. */
  const nextWeekday = (wd: number, minAhead = 1) => {
    let d = addIso(today, minAhead);
    while (weekdayOf(d) !== wd) d = addIso(d, 1);
    return d;
  };

  async function insertMany<T extends Record<string, unknown>>(table: Parameters<typeof db.insert>[0], rows: T[], chunk = 500) {
    for (let i = 0; i < rows.length; i += chunk) {
      await db.insert(table).values(rows.slice(i, i + chunk) as any);
    }
  }

  console.log('→ Offres et référentiels');
  await db.insert(S.plans).values([
    {
      key: 'ESSENTIEL',
      name: 'Essentiel',
      priceMonthlyCents: 0,
      tagline: 'Offert par votre collectivité',
      sortOrder: 1,
      features: ['Fiche complète et référencée', 'Photos, horaires, carte', '3 publications / mois', 'QR code vitrine', 'Statistiques essentielles'],
      limits: { postsPerMonth: 3, aiPerMonth: 5, scheduling: false, newsletterChannel: false, socialChannel: false, advancedStats: false, jobs: false, appointments: false, miniSite: false, customerNewsletter: false, contactsExport: false, customQr: false },
    },
    {
      key: 'PREMIUM',
      name: 'Premium',
      priceMonthlyCents: 2400,
      tagline: 'Pour transformer les vues en clients',
      sortOrder: 2,
      features: ['Assistant IA de rédaction', 'Publications illimitées et programmées', 'Diffusion newsletter territoriale', 'Statistiques avancées', "Offres d'emploi", 'Prise de rendez-vous'],
      limits: { postsPerMonth: null, aiPerMonth: null, scheduling: true, newsletterChannel: true, socialChannel: false, advancedStats: true, jobs: true, appointments: true, miniSite: false, customerNewsletter: false, contactsExport: false, customQr: true },
    },
    {
      key: 'COMMUNICATION',
      name: 'Communication',
      priceMonthlyCents: 4900,
      tagline: 'Votre mini-agence marketing',
      sortOrder: 3,
      features: ['Tout Premium', 'Newsletter à vos clients', 'Publication réseaux sociaux', 'Mini-site personnalisable', 'Export des contacts consentis'],
      limits: { postsPerMonth: null, aiPerMonth: null, scheduling: true, newsletterChannel: true, socialChannel: true, advancedStats: true, jobs: true, appointments: true, miniSite: true, customerNewsletter: true, contactsExport: true, customQr: true },
    },
  ]);

  const catRows = await db
    .insert(S.categories)
    .values(D.CATEGORIES.map((c, i) => ({ family: c.family, slug: c.slug, name: c.name, nafCodes: c.naf, synonyms: c.synonyms, sortOrder: i })))
    .returning();
  const catBySlug = new Map(catRows.map((c) => [c.slug, c]));
  const catImg = new Map(D.CATEGORIES.map((c) => [c.slug, c.img]));
  const attrRows = await db
    .insert(S.attributes)
    .values(D.ATTRIBUTES.map((a, i) => ({ slug: a.slug, label: a.label, group: a.group, isFilter: a.isFilter ?? false, sortOrder: i })))
    .returning();
  const attrBySlug = new Map(attrRows.map((a) => [a.slug, a]));

  console.log('→ Comptes de la plateforme');
  const demoHash = await hashPassword(DEMO_PASSWORD);
  const mfaSecretEnc = encrypt(DEMO_TOTP_SECRET);
  const mkUser = async (u: { email: string; first: string; last: string; mfa?: boolean; avatar?: string; job?: string; login?: boolean; createdAt?: Date }) => {
    const [row] = await db
      .insert(S.users)
      .values({
        email: u.email,
        firstName: u.first,
        lastName: u.last,
        passwordHash: u.login === false ? null : demoHash,
        emailVerifiedAt: new Date(),
        mfaEnabled: Boolean(u.mfa),
        mfaSecretEnc: u.mfa ? mfaSecretEnc : null,
        avatarUrl: u.avatar ?? null,
        jobTitle: u.job ?? null,
        lastLoginAt: u.login === false ? null : daysAgo(r.int(0, 6)),
        createdAt: u.createdAt ?? daysAgo(200),
      })
      .returning();
    return row;
  };
  const camille = await mkUser({ email: 'camille@terricom.fr', first: 'Camille', last: 'Moreau', mfa: true, avatar: D.U(D.I.p2, 120, 120), job: 'Responsable des partenariats territoriaux' });
  const support = await mkUser({ email: 'support@terricom.fr', first: 'Support', last: 'terricom', mfa: true, job: 'Assistance' });
  const sales = await mkUser({ email: 'alexandre@terricom.fr', first: 'Alexandre', last: 'Perrot', mfa: true, avatar: D.U(D.I.p3, 120, 120), job: 'Développement commercial' });
  await db.insert(S.roleAssignments).values([
    { userId: camille.id, role: 'PLATFORM_ADMIN' },
    { userId: camille.id, role: 'PLATFORM_SALES' },
    { userId: support.id, role: 'PLATFORM_SUPPORT' },
    { userId: sales.id, role: 'PLATFORM_SALES' },
  ]);

  // ─── Territoire pilote : Val de Loue ──────────────────────────────────────
  console.log('→ Territoire pilote du Val de Loue');
  const [vdl] = await db
    .insert(S.territories)
    .values({
      slug: 'valdeloue',
      name: 'Val de Loue',
      legalName: 'Communauté de communes du Val de Loue',
      kind: 'CC',
      status: 'ACTIVE',
      isPilot: true,
      siren: '200071835',
      population: 22400,
      departmentCode: '25',
      initials: 'VL',
      tagline: 'Commerces & savoir-faire',
      colorPrimary: '#1F6B52',
      colorAccent: '#F4B266',
      heroTitle: 'Le Val de Loue,|fait main & fait ici.',
      heroSubtitle: 'Artisans, producteurs, restaurants et commerces de 24 communes. Trouvez, poussez la porte, soutenez.',
      heroImageUrl: D.U(D.I.valley, 2000),
      centerLat: 47.1062,
      centerLng: 6.1446,
      defaultZoom: 11,
      contactEmail: 'economie@cc-valdeloue.fr',
      websiteUrl: 'https://www.cc-valdeloue.fr',
      settings: {
        claimValidation: 'MANUAL',
        postModeration: 'POST',
        newsletterName: 'La lettre du vendredi',
        newsletterSenderName: 'Val de Loue',
        newsletterReplyTo: 'economie@cc-valdeloue.fr',
        jobsTitle: 'Travailler en Val de Loue',
        jobsIntro: 'Un métier, une rivière, une vie ici.',
        footerText: 'Une initiative de la Communauté de communes du Val de Loue et de ses 24 communes.',
        dpoEmail: 'dpo@cc-valdeloue.fr',
        legalPublisher: 'Communauté de communes du Val de Loue, 2 place de l’Hôtel de Ville, 25290 Ornans',
        circuitsTitle: 'Suivez le fil de la Loue',
        livingTitle: 'Une rivière, des forêts, et 20 minutes de Besançon.',
        livingText: 'Logement, écoles, transport : la collectivité vous accompagne pour vous installer.',
        directionsProvider: 'google',
      },
      quotaEstablishments: 1500,
      quotaEmailsMonthly: 40000,
      quotaAiCreditsMonthly: 5000,
      createdAt: daysAgo(210),
    })
    .returning();
  await db.insert(S.territoryDomains).values([
    { territoryId: vdl.id, host: 'valdeloue.terricom.fr', isPrimary: false, verifiedAt: addIso(today, -200) },
    { territoryId: vdl.id, host: 'commerces.valdeloue.fr', isPrimary: false, verifiedAt: addIso(today, -190) },
  ]);
  await db.insert(S.territoryModules).values(
    (['PORTAL', 'MAP', 'NEWSLETTER', 'IMPORT', 'CAMPAIGNS', 'AI', 'CIRCUITS', 'JOBS', 'APPOINTMENTS', 'MULTILINGUAL'] as const).map((m) => ({
      territoryId: vdl.id,
      module: m,
      enabled: m !== 'MULTILINGUAL',
    })),
  );
  const [madeIn] = await db
    .insert(S.attributes)
    .values({ territoryId: vdl.id, group: 'LABEL', slug: 'made-in-val-de-loue', label: 'Made in Val de Loue', isFilter: false, sortOrder: 100 })
    .returning();

  const communeRows: Record<string, typeof S.communes.$inferSelect> = {};
  for (const c of D.VAL_DE_LOUE_COMMUNES) {
    const [row] = await db
      .insert(S.communes)
      .values({
        inseeCode: c.insee,
        name: c.name,
        slug: slugify(c.name),
        postalCodes: [c.postal],
        departmentCode: '25',
        population: c.pop,
        lat: c.lat,
        lng: c.lng,
      })
      .returning();
    communeRows[c.name] = row;
    await db.insert(S.communeMemberships).values({ communeId: row.id, territoryId: vdl.id, validFrom: '2026-01-01' });
  }
  await db
    .update(S.communes)
    .set({
      tagline: 'La « petite Venise comtoise » et ses {pros} professionnels, de la rue Pierre Vernier aux bords de Loue.',
      description:
        "Ville natale de Gustave Courbet, Ornans aligne ses maisons sur pilotis au-dessus de la Loue. Commerces de centre-bourg, artisans et producteurs y font vivre la « petite Venise comtoise ».",
      heroImageUrl: D.U(D.I.mountains, 2000),
      mayorQuote: 'Nos commerçants font vivre le centre-bourg. Cette plateforme leur donne la vitrine numérique qu’ils méritent, gratuitement.',
      mayorName: 'Anne Roussel',
      mayorRole: 'Adjointe au commerce',
      mayorPhotoUrl: D.U(D.I.p4, 120, 120),
    })
    .where(eq(S.communes.id, communeRows['Ornans'].id));
  await db
    .update(S.communes)
    .set({
      tagline: 'Porte d’entrée du Val de Loue côté Besançon, bourg commerçant et marché du samedi.',
      heroImageUrl: D.U(D.I.forest, 2000),
      mayorQuote: 'À Quingey, chaque commerce compte : la plateforme nous aide à les faire connaître au-delà du bourg.',
      mayorName: 'Hugo Lambert',
      mayorRole: 'Conseiller délégué au commerce',
      mayorPhotoUrl: D.U(D.I.p1, 120, 120),
    })
    .where(eq(S.communes.id, communeRows['Quingey'].id));

  await db.insert(S.markets).values([
    { territoryId: vdl.id, communeId: communeRows['Ornans'].id, name: 'Marché du centre', place: 'Place Courbet', weekday: 5, startTime: '08:00', endTime: '13:00', lat: 47.1063, lng: 6.1452 },
    { territoryId: vdl.id, communeId: communeRows['Ornans'].id, name: 'Producteurs bio', place: 'Halle', weekday: 2, startTime: '16:00', endTime: '19:00', lat: 47.1071, lng: 6.1437 },
    { territoryId: vdl.id, communeId: communeRows['Quingey'].id, name: 'Marché de Quingey', place: 'Place de la Mairie', weekday: 5, startTime: '08:00', endTime: '12:30', lat: 47.1029, lng: 5.8838 },
    { territoryId: vdl.id, communeId: communeRows['Amancey'].id, name: 'Marché des producteurs', place: 'Place du village', weekday: 4, startTime: '16:30', endTime: '19:30', lat: 47.0409, lng: 6.0745 },
  ]);

  console.log('→ Agents de la collectivité');
  const claire = await mkUser({ email: 'c.duval@cc-valdeloue.fr', first: 'Claire', last: 'Duval', mfa: true, avatar: D.U(D.I.p4, 120, 120), job: 'Directrice du développement économique' });
  const thomas = await mkUser({ email: 't.girod@cc-valdeloue.fr', first: 'Thomas', last: 'Girod', mfa: true, avatar: D.U(D.I.p3, 120, 120), job: 'Chargé de communication' });
  const anne = await mkUser({ email: 'commerce@ornans.fr', first: 'Anne', last: 'Roussel', mfa: true, avatar: D.U(D.I.p2, 120, 120), job: 'Adjointe au commerce' });
  const hugo = await mkUser({ email: 'mairie@quingey.fr', first: 'Hugo', last: 'Lambert', mfa: false, avatar: D.U(D.I.p1, 120, 120), job: 'Conseiller délégué au commerce' });
  await db.insert(S.roleAssignments).values([
    { userId: claire.id, role: 'TERRITORY_ADMIN', territoryId: vdl.id, createdById: camille.id },
    { userId: thomas.id, role: 'TERRITORY_EDITOR', territoryId: vdl.id, createdById: claire.id },
    { userId: anne.id, role: 'COMMUNE_ADMIN', territoryId: vdl.id, communeId: communeRows['Ornans'].id, createdById: claire.id },
    { userId: hugo.id, role: 'COMMUNE_ADMIN', territoryId: vdl.id, communeId: communeRows['Quingey'].id, createdById: claire.id },
  ]);

  // ─── Établissements détaillés ─────────────────────────────────────────────
  console.log('→ Établissements des maquettes');
  type EstRef = { id: string; companyId: string; name: string; category: string; commune: string; status: string; lat: number; lng: number; ownerId: string | null; plan: string };
  const estByKey: Record<string, EstRef> = {};
  const allEsts: EstRef[] = [];
  const hoursRows: (typeof S.openingHours.$inferInsert)[] = [];
  const attrLinks: (typeof S.establishmentAttributes.$inferInsert)[] = [];
  const mediaRows: (typeof S.media.$inferInsert)[] = [];
  const productRows: (typeof S.products.$inferInsert)[] = [];
  const memberRows: (typeof S.companyMembers.$inferInsert)[] = [];
  const subscriptionRows: (typeof S.companySubscriptions.$inferInsert)[] = [];

  for (const e of D.ESTABLISHMENTS) {
    const commune = communeRows[e.commune];
    const cat = catBySlug.get(e.category)!;
    const [company] = await db
      .insert(S.companies)
      .values({
        siren: e.siret.slice(0, 9),
        legalName: e.legalName,
        tradeName: e.name,
        nafCode: D.CATEGORIES.find((c) => c.slug === e.category)!.naf[0],
        plan: e.plan,
        billingEmail: e.owner?.email ?? null,
        billingName: e.name,
        billingAddress: `${e.street}, ${commune.postalCodes[0]} ${commune.name}`,
        createdAt: daysAgo(200),
      })
      .returning();
    const claimed = ['CLAIMED', 'VALIDATED', 'SUSPENDED', 'TO_COMPLETE'].includes(e.status) && e.owner;
    const [est] = await db
      .insert(S.establishments)
      .values({
        companyId: company.id,
        communeId: commune.id,
        territoryId: vdl.id,
        categoryId: cat.id,
        slug: slugify(e.name),
        name: e.name,
        siret: fixSiret(e.siret),
        status: e.status,
        origin: 'IMPORT',
        activityLabel: e.activity,
        tagline: e.tagline ?? null,
        description: e.description,
        street: e.street,
        postalCode: commune.postalCodes[0],
        lat: e.lat,
        lng: e.lng,
        phone: e.phone,
        email: e.email ?? null,
        website: e.website ?? null,
        socials: e.plan !== 'ESSENTIEL' ? { facebook: `https://facebook.com/${slugify(e.name)}`, instagram: `https://instagram.com/${slugify(e.name).replace(/-/g, '')}` } : {},
        coverUrl: D.U(e.cover, 1200),
        isFeatured: ['b1', 'b9', 'b3', 'b2'].includes(e.key),
        hoursConfirmedAt: e.status === 'VALIDATED' ? daysAgo(r.int(5, 60)) : null,
        lastActivityAt: daysAgo(e.updatedDaysAgo),
        publishedAt: daysAgo(190),
        suspendedReason: e.status === 'SUSPENDED' ? 'Fiche inactive depuis 5 mois, sans réponse aux relances.' : null,
        appointmentsEnabled: e.key === 'b5' || e.key === 'b12',
        qrCode: shortCode(8),
        createdAt: daysAgo(200),
        updatedAt: daysAgo(e.updatedDaysAgo),
      })
      .returning();
    let ownerId: string | null = null;
    if (claimed && e.owner) {
      const owner = await mkUser({ email: e.owner.email, first: e.owner.first, last: e.owner.last, createdAt: daysAgo(e.key === 'b1' ? 40 : 150), avatar: e.key === 'b1' ? D.U(D.I.p2, 100, 100) : undefined });
      ownerId = owner.id;
      memberRows.push({ companyId: company.id, userId: owner.id, role: 'OWNER' });
    }
    if (e.plan !== 'ESSENTIEL') {
      subscriptionRows.push({ companyId: company.id, plan: e.plan, status: 'ACTIVE', provider: 'MANUAL', startedAt: daysAgo(r.int(40, 180)), currentPeriodEnd: new Date(now.getTime() + r.int(3, 28) * DAY) });
    }
    for (const [wd, o, c] of e.hours) hoursRows.push({ establishmentId: est.id, weekday: wd, opensAt: o, closesAt: c });
    for (const slug of e.attrs) attrLinks.push({ establishmentId: est.id, attributeId: attrBySlug.get(slug)!.id });
    if (['b1', 'b9', 'b2', 'b14', 'b8'].includes(e.key)) attrLinks.push({ establishmentId: est.id, attributeId: madeIn.id });
    (e.gallery ?? [[e.cover, 'Principale']]).forEach(([img, tag], i) =>
      mediaRows.push({
        territoryId: vdl.id,
        establishmentId: est.id,
        ownerType: 'ESTABLISHMENT',
        ownerId: est.id,
        url: D.U(img, 1280),
        variants: { w320: D.U(img, 320), w640: D.U(img, 640), w1280: D.U(img, 1280), w1920: D.U(img, 1920) },
        tag,
        sortOrder: i,
        alt: `${e.name} — photo ${i + 1}`,
      }),
    );
    (e.products ?? []).forEach(([n, p, img], i) =>
      productRows.push({ establishmentId: est.id, name: n, priceText: p, imageUrl: D.U(img, 500, 300), sortOrder: i }),
    );
    const ref: EstRef = { id: est.id, companyId: company.id, name: e.name, category: e.category, commune: e.commune, status: e.status, lat: e.lat, lng: e.lng, ownerId, plan: e.plan };
    estByKey[e.key] = ref;
    allEsts.push(ref);
  }
  // Boulangerie Martin : horaires de fin d'année déjà saisis, pas encore ceux de la Toussaint.
  const sophieId = estByKey.b1.ownerId!;

  // ─── Volume : fiches précréées par import SIRENE puis revendiquées ───────
  console.log('→ Génération des fiches du territoire (import SIRENE simulé)');
  const genEsts: EstRef[] = [];
  const usedSlugs = new Set(D.ESTABLISHMENTS.map((e) => `${communeRows[e.commune].id}:${slugify(e.name)}`));
  let siretSeq = 10_000;
  async function generateFor(territoryId: string, communeRow: typeof S.communes.$inferSelect, count: number, claimRate: number, premiumRate: number) {
    for (let i = 0; i < count; i++) {
      const catSlug = r.weighted(R.CATEGORY_WEIGHTS);
      const cat = catBySlug.get(catSlug)!;
      let name = r.pick(R.NAME_PATTERNS[catSlug] ?? [(rr: typeof r) => `${cat.name} ${rr.pick(R.SURNAMES)}`])(r);
      let guard = 0;
      while (usedSlugs.has(`${communeRow.id}:${slugify(name)}`) && guard++ < 10)
        name = guard < 5 ? `${name} ${r.pick(['& Fils', 'Frères', 'et Cie', communeRow.name])}` : `${name} ${guard}`;
      usedSlugs.add(`${communeRow.id}:${slugify(name)}`);
      const claimed = r.chance(claimRate);
      const status = claimed ? (r.chance(0.72) ? 'VALIDATED' : 'CLAIMED') : r.chance(0.8) ? 'PRECREATED' : 'TO_COMPLETE';
      const plan: 'ESSENTIEL' | 'PREMIUM' | 'COMMUNICATION' = claimed && r.chance(premiumRate) ? (r.chance(0.8) ? 'PREMIUM' : 'COMMUNICATION') : 'ESSENTIEL';
      const siret = completeSiret(`${String(400000000 + siretSeq++).padStart(9, '0')}${String(r.int(1000, 9999))}`);
      const [company] = await db
        .insert(S.companies)
        .values({ siren: siret.slice(0, 9), legalName: name.toUpperCase(), tradeName: name, nafCode: cat.nafCodes[0] ?? null, plan, createdAt: daysAgo(r.int(150, 220)) })
        .returning();
      const spread = Math.min(0.012, 0.0025 + (communeRow.population ?? 500) / 900_000);
      const lat = (communeRow.lat ?? 47) + (r.next() - 0.5) * spread;
      const lng = (communeRow.lng ?? 6) + (r.next() - 0.5) * spread * 1.4;
      const img = catImg.get(catSlug)!;
      const activity = cat.name;
      const street = `${r.int(1, 48)} ${r.pick(R.STREETS)}`;
      const recentlyActive = claimed && r.chance(0.45);
      const [est] = await db
        .insert(S.establishments)
        .values({
          companyId: company.id,
          communeId: communeRow.id,
          territoryId,
          categoryId: cat.id,
          slug: slugify(name),
          name,
          siret,
          status,
          origin: 'IMPORT',
          activityLabel: activity,
          description: claimed ? R.descriptionFor(name, activity, communeRow.name, r) : null,
          street,
          postalCode: communeRow.postalCodes[0],
          lat,
          lng,
          phone: claimed || r.chance(0.6) ? R.phoneNumber(r) : null,
          email: claimed ? `contact@${slugify(name)}.exemple.test` : null,
          website: claimed && r.chance(0.3) ? `https://${slugify(name)}.exemple.test` : null,
          coverUrl: status === 'VALIDATED' || (status === 'CLAIMED' && r.chance(0.6)) ? D.U(img, 1200) : null,
          hoursConfirmedAt: claimed ? (r.chance(0.9) ? daysAgo(r.int(5, 150)) : daysAgo(r.int(200, 400))) : null,
          lastActivityAt: recentlyActive ? daysAgo(r.int(0, 29)) : claimed ? daysAgo(r.int(31, 160)) : null,
          publishedAt: daysAgo(r.int(150, 200)),
          qrCode: shortCode(8),
          createdAt: daysAgo(r.int(150, 210)),
          updatedAt: claimed ? daysAgo(r.int(0, 90)) : daysAgo(r.int(150, 200)),
        })
        .returning();
      let ownerId: string | null = null;
      if (claimed) {
        const first = r.pick(R.FIRSTNAMES);
        const last = r.pick(R.SURNAMES);
        const [owner] = await db
          .insert(S.users)
          .values({ email: `${slugify(first)}.${slugify(last)}.${est.id.slice(0, 6)}@exemple.test`, firstName: first, lastName: last, emailVerifiedAt: daysAgo(100), createdAt: daysAgo(r.int(20, 160)) })
          .returning();
        ownerId = owner.id;
        memberRows.push({ companyId: company.id, userId: owner.id, role: 'OWNER' });
        if (plan !== 'ESSENTIEL')
          subscriptionRows.push({ companyId: company.id, plan, status: 'ACTIVE', provider: r.chance(0.5) ? 'STRIPE' : 'MANUAL', startedAt: daysAgo(r.int(10, 200)), currentPeriodEnd: new Date(now.getTime() + r.int(2, 29) * DAY) });
      }
      for (const [wd, o, c] of R.hoursFor(catSlug, r)) if (claimed || r.chance(0.5)) hoursRows.push({ establishmentId: est.id, weekday: wd, opensAt: o, closesAt: c });
      for (const slug of R.attributesFor(catSlug, r)) if (claimed || r.chance(0.3)) attrLinks.push({ establishmentId: est.id, attributeId: attrBySlug.get(slug)!.id });
      if (status === 'VALIDATED' || status === 'CLAIMED') {
        const n = status === 'VALIDATED' ? r.int(3, 6) : r.int(0, 2);
        const pool2 = r.shuffle([img, D.I.store, D.I.team, D.I.crafts, D.I.board2, D.I.workshop]);
        for (let k = 0; k < n; k++)
          mediaRows.push({
            territoryId,
            establishmentId: est.id,
            ownerType: 'ESTABLISHMENT',
            ownerId: est.id,
            url: D.U(pool2[k], 1280),
            variants: { w320: D.U(pool2[k], 320), w640: D.U(pool2[k], 640), w1280: D.U(pool2[k], 1280) },
            tag: k === 0 ? 'Principale' : r.chance(0.3) ? 'Intérieur' : null,
            sortOrder: k,
          });
        if (status === 'VALIDATED' && r.chance(0.6))
          for (let k = 0; k < r.int(2, 4); k++) productRows.push({ establishmentId: est.id, name: `${activity} — prestation ${k + 1}`, priceText: r.chance(0.5) ? 'Sur devis' : `${r.int(5, 60)} €`, sortOrder: k });
      }
      const ref: EstRef = { id: est.id, companyId: company.id, name, category: catSlug, commune: communeRow.name, status, lat, lng, ownerId, plan };
      genEsts.push(ref);
      allEsts.push(ref);
    }
  }
  for (const c of D.VAL_DE_LOUE_COMMUNES) {
    const detailed = D.ESTABLISHMENTS.filter((e) => e.commune === c.name).length;
    await generateFor(vdl.id, communeRows[c.name], c.count - detailed, c.claimRate / 100, 0.22);
  }

  // ─── Autres territoires clients ───────────────────────────────────────────
  console.log('→ Autres territoires clients');
  const territoryBySlug: Record<string, typeof S.territories.$inferSelect> = { valdeloue: vdl };
  for (const t of OTHER_TERRITORIES) {
    const [tr] = await db
      .insert(S.territories)
      .values({
        slug: t.slug,
        name: t.name,
        legalName: t.legalName,
        kind: t.kind,
        status: t.status,
        population: t.population,
        departmentCode: t.dept,
        initials: t.initials,
        colorPrimary: t.colorPrimary,
        colorAccent: t.colorAccent,
        heroTitle: `${t.name},|fait main & fait ici.`,
        heroSubtitle: `Commerces, artisans et producteurs de ${t.name}. Trouvez, poussez la porte, soutenez.`,
        heroImageUrl: D.U(t.hero, 2000),
        centerLat: t.communes[0].lat,
        centerLng: t.communes[0].lng,
        settings: { claimValidation: 'MANUAL', postModeration: 'POST', newsletterName: 'La lettre locale', jobsTitle: `Travailler à ${t.name}` },
        createdAt: new Date(`${t.signedAt}T09:00:00Z`),
      })
      .returning();
    territoryBySlug[t.slug] = tr;
    await db.insert(S.territoryModules).values(
      (['PORTAL', 'MAP', 'NEWSLETTER', 'IMPORT', 'CAMPAIGNS'] as const).map((m) => ({ territoryId: tr.id, module: m, enabled: true })),
    );
    await db.insert(S.territoryDomains).values({ territoryId: tr.id, host: `${t.slug}.terricom.fr`, verifiedAt: t.signedAt });
    for (const c of t.communes) {
      const [cr] = await db
        .insert(S.communes)
        .values({ inseeCode: c.insee, name: c.name, slug: slugify(c.name), postalCodes: [c.postal], departmentCode: t.dept, population: c.pop, lat: c.lat, lng: c.lng })
        .returning();
      await db.insert(S.communeMemberships).values({ communeId: cr.id, territoryId: tr.id, validFrom: t.signedAt });
      await generateFor(tr.id, cr, c.count, t.claimRate, t.premiumRate);
    }
    await db.insert(S.territoryContracts).values([
      { territoryId: tr.id, kind: 'LICENCE', label: `Licence annuelle ${t.name}`, amountCents: t.licenceCents, startsAt: t.signedAt, endsAt: addIso(t.signedAt, 365), status: 'ACTIVE', signedAt: t.signedAt },
      { territoryId: tr.id, kind: 'SETUP', label: 'Mise en service (paramétrage, import, formation)', amountCents: t.setupCents, startsAt: t.signedAt, status: 'ACTIVE', signedAt: t.signedAt },
    ]);
    const admin = await mkUser({ email: `admin@${t.slug}.exemple.test`, first: 'Admin', last: t.name, mfa: true, login: false });
    await db.insert(S.roleAssignments).values({ userId: admin.id, role: 'TERRITORY_ADMIN', territoryId: tr.id, createdById: camille.id });
  }

  await insertMany(S.companyMembers, memberRows);
  await insertMany(S.companySubscriptions, subscriptionRows);
  await insertMany(S.openingHours, hoursRows);
  await insertMany(S.establishmentAttributes, attrLinks);
  await insertMany(S.media, mediaRows);
  await insertMany(S.products, productRows);

  // Horaires exceptionnels (Noël déjà saisis chez certains)
  await db.insert(S.exceptionalHours).values([
    { establishmentId: estByKey.b1.id, date: `${now.getFullYear()}-12-25`, closed: true, label: 'Noël' },
    { establishmentId: estByKey.b1.id, date: `${now.getFullYear()}-12-24`, closed: false, opensAt: '06:30', closesAt: '17:00', label: 'Veille de Noël' },
    { establishmentId: estByKey.b9.id, date: `${now.getFullYear()}-11-01`, closed: true, label: 'Toussaint' },
    { establishmentId: estByKey.b6.id, date: `${now.getFullYear()}-11-01`, closed: true, label: 'Toussaint' },
  ]);

  // ─── Contrat du territoire pilote ─────────────────────────────────────────
  await db.insert(S.territoryContracts).values([
    { territoryId: vdl.id, kind: 'LICENCE', label: 'Licence annuelle — territoire partenaire pilote', amountCents: 900000, startsAt: '2026-03-01', endsAt: '2027-02-28', status: 'ACTIVE', signedAt: '2026-02-10', notes: 'Conditions pilote : retours mensuels, témoignage et étude de cas.' },
    { territoryId: vdl.id, kind: 'SETUP', label: 'Mise en service : paramétrage, import SIRENE, formation des 24 communes', amountCents: 500000, startsAt: '2026-02-10', status: 'ACTIVE', signedAt: '2026-02-10' },
  ]);

  // ─── Publications (fil du territoire) ────────────────────────────────────
  console.log('→ Publications, événements, emplois');
  const postRows: (typeof S.posts.$inferInsert)[] = [
    { est: 'b9', kind: 'PROMO' as const, title: 'Coffret « Loue gourmande » : -15 % jusqu’à la fin du mois', body: 'Ganaches aux herbes, galets de la Loue et pralinés à l’ancienne : le coffret star passe à 23,80 € jusqu’au 31. Emballage cadeau offert.', at: hoursAgo(2), img: D.I.choco, promo: '-15 %', validTo: addIso(today, 20), views: 186 },
    { est: 'b1', kind: 'NOUVEAUTE' as const, title: 'Le pain au Comté est de retour le vendredi', body: 'Tous les vendredis et samedis, notre pain au levain garni de Comté 18 mois du Plateau. Pensez à le réserver !', at: daysAgo(1), img: D.I.bread, views: 412 },
    { est: 'b3', kind: 'EVENT' as const, title: 'Soirée truite & vin jaune, samedi 20h', body: 'Menu unique à 38 € : truite de la Loue au vin jaune, morilles et dessert au kirsch de Mouthier. Réservation conseillée.', at: daysAgo(1, 16), img: D.I.terrace, views: 233 },
    { est: 'b8', kind: 'EVENT' as const, title: "Portes ouvertes de l'atelier, dimanche", body: 'Démonstrations de tournage toutes les heures et cuisson raku à 15h.', at: daysAgo(2), img: D.I.pottery, views: 158 },
    { est: 'b6', kind: 'NOUVEAUTE' as const, title: 'Arrivage : 12 nouveaux vins du Jura', body: 'Savagnins ouillés, crémants et deux vins jaunes de petits domaines : venez les goûter samedi.', at: daysAgo(3), img: D.I.vine, views: 301 },
    { est: 'b13', kind: 'HOURS' as const, title: 'Ouvert le dimanche matin en octobre', body: 'De 9h30 à 12h30, tous les dimanches du mois.', at: daysAgo(4), img: D.I.grocery, views: 97 },
    { est: 'b1', kind: 'PROMO' as const, title: 'Brioches du dimanche -10 % pour la rentrée', body: 'Commandez en boutique ou en click & collect.', at: daysAgo(6), img: D.I.bakery, promo: '-10 %', validTo: addIso(today, 12), views: 288 },
    { est: 'b1', kind: 'HOURS' as const, title: 'Fermeture exceptionnelle le 25 décembre', body: 'Réouverture le 26 à 6h30. Les commandes de bûches sont ouvertes dès novembre.', at: daysAgo(38), img: D.I.store, views: 121 },
    { est: 'b14', kind: 'NEWS' as const, title: 'La Bleue de la Loue médaillée au concours de Pontarlier', body: 'Notre absinthe blanche décroche l’or : merci à toute l’équipe de la distillerie.', at: daysAgo(5), img: D.I.toast, views: 340 },
    { est: 'b2', kind: 'EVENT' as const, title: 'Dégustation Comté 24 mois vendredi', body: 'Trois affinages, trois caractères : venez goûter la différence.', at: daysAgo(2, 9), img: D.I.cheese, views: 176 },
    { est: 'b15', kind: 'NEWS' as const, title: 'Concert folk vendredi soir en terrasse', body: 'Le trio « Les Gorges » joue dès 19h, entrée libre.', at: daysAgo(7), img: D.I.food, views: 132 },
    { est: 'b3', kind: 'JOB' as const, title: 'On recrute pour la saison prochaine', body: 'Serveur·se saison été, logement possible. Candidatez depuis notre fiche.', at: daysAgo(5, 11), img: D.I.terrace, views: 88 },
  ].map((p) => ({
    territoryId: vdl.id,
    communeId: communeRows[D.ESTABLISHMENTS.find((e) => e.key === p.est)!.commune].id,
    establishmentId: estByKey[p.est].id,
    authorType: 'ESTABLISHMENT' as const,
    kind: p.kind,
    status: 'PUBLISHED' as const,
    title: p.title,
    body: p.body,
    imageUrl: D.U(p.img, 800),
    promoLabel: 'promo' in p ? (p.promo as string) : null,
    validTo: 'validTo' in p ? (p.validTo as string) : null,
    channels: ['FICHE', 'COMMUNE', 'TERRITOIRE'] as ('FICHE' | 'COMMUNE' | 'TERRITOIRE')[],
    publishedAt: p.at,
    createdAt: p.at,
    viewCount: p.views,
    createdById: estByKey[p.est].ownerId,
  }));
  // Publications programmées de Sophie + publications territoriales + publications à modérer
  postRows.push(
    ...[
      ['Galettes -10 % : précommandes de Noël', 'PROMO', 8, D.I.bakery, ['FICHE', 'SOCIAL']],
      ['Atelier pain au levain pour enfants', 'EVENT', 12, D.I.cook, ['FICHE', 'NEWSLETTER']],
      ['Horaires de la Toussaint', 'HOURS', 30, D.I.store, ['FICHE', 'COMMUNE']],
    ].map(([title, kind, inDays, img, ch]) => ({
      territoryId: vdl.id,
      communeId: communeRows['Ornans'].id,
      establishmentId: estByKey.b1.id,
      authorType: 'ESTABLISHMENT' as const,
      kind: kind as 'PROMO',
      status: 'SCHEDULED' as const,
      title: title as string,
      body: '',
      imageUrl: D.U(img as string, 800),
      channels: ch as ('FICHE' | 'SOCIAL')[],
      publishAt: parisAt(addIso(today, inDays as number), (['08:00', '10:00', '07:00'] as const)[Math.min(2, Math.round((inDays as number) / 12))]),
      createdById: sophieId,
    })),
    {
      territoryId: vdl.id,
      authorType: 'TERRITORY',
      kind: 'NEWS',
      status: 'PUBLISHED',
      title: '812 professionnels désormais en vitrine sur le portail',
      body: 'Six mois après le lancement, plus de 370 entreprises ont pris la main sur leur fiche. Merci à toutes et à tous !',
      imageUrl: D.U(D.I.market, 800),
      channels: ['TERRITOIRE'],
      publishedAt: daysAgo(8),
      createdById: thomas.id,
      viewCount: 540,
    },
    {
      territoryId: vdl.id,
      communeId: communeRows['Ornans'].id,
      authorType: 'COMMUNE',
      kind: 'NEWS',
      status: 'PUBLISHED',
      title: 'Travaux rue Pierre Vernier : les commerces restent ouverts',
      body: 'Stationnement gratuit place Courbet pendant toute la durée du chantier.',
      imageUrl: D.U(D.I.store, 800),
      channels: ['COMMUNE'],
      publishedAt: daysAgo(3, 14),
      createdById: anne.id,
      viewCount: 210,
    },
  );
  const genClaimedVdl = genEsts.filter((e) => allEsts.includes(e) && ['CLAIMED', 'VALIDATED'].includes(e.status) && communeRows[e.commune]);
  for (const [i, e] of r.sample(genClaimedVdl, 4).entries()) {
    postRows.push({
      territoryId: vdl.id,
      communeId: communeRows[e.commune].id,
      establishmentId: e.id,
      authorType: 'ESTABLISHMENT',
      kind: i % 2 ? 'PROMO' : 'NEWS',
      status: 'PENDING',
      title: ['Grande braderie de fin de saison', '-50 % sur tout le magasin ce week-end', 'Nouveau : nous livrons à domicile', 'Soirée dégustation vendredi'][i],
      body: [
        'Du vendredi au dimanche, on fait de la place avant la nouvelle saison : fins de séries, articles d’exposition et petits prix sur tout le stock. Venez tôt, les quantités sont limitées !',
        'Ce week-end seulement, profitez de -50 % sur toute la boutique (hors nouveautés). Offre valable samedi et dimanche, dans la limite des stocks disponibles.',
        'Bonne nouvelle : nous livrons désormais à domicile dans un rayon de 15 km, du mardi au samedi. Commande par téléphone la veille avant 18 h, livraison offerte dès 30 €.',
        'Vendredi à partir de 18 h 30, soirée dégustation en présence de producteurs du Val de Loue : comté, vins du Jura et douceurs locales. Entrée libre, réservation conseillée.',
      ][i],
      channels: ['FICHE', 'TERRITOIRE'],
      createdAt: hoursAgo(3 + i * 5),
      createdById: e.ownerId,
    });
  }
  // Activité récente des pros générés
  for (const e of r.sample(genClaimedVdl, 60)) {
    const at = daysAgo(r.int(0, 60), r.int(8, 18));
    postRows.push({
      territoryId: vdl.id,
      communeId: communeRows[e.commune].id,
      establishmentId: e.id,
      authorType: 'ESTABLISHMENT',
      kind: r.pick(['NEWS', 'PROMO', 'NOUVEAUTE', 'HOURS'] as const),
      status: 'PUBLISHED',
      title: r.pick(['Nouveaux horaires pour la rentrée', 'Arrivage de la semaine', 'Offre spéciale ce mois-ci', 'Merci pour votre fidélité !', 'Nous recrutons un apprenti', 'Fermeture pour congés du 20 au 27']),
      body: 'Retrouvez tous les détails en boutique.',
      channels: ['FICHE', 'COMMUNE'],
      publishedAt: at,
      createdAt: at,
      viewCount: r.int(20, 260),
      createdById: e.ownerId,
    });
  }
  await insertMany(S.posts, postRows);

  // ─── Événements (agenda) ─────────────────────────────────────────────────
  const sat1 = nextWeekday(5, 2);
  const fri1 = nextWeekday(4, 1);
  const sun1 = nextWeekday(6, 2);
  const wed1 = nextWeekday(2, 4);
  const EVT: (Omit<typeof S.events.$inferInsert, 'slug' | 'territoryId'> & { est?: string })[] = [
    { est: 'b2', title: 'Dégustation Comté 24 mois', kind: 'DEGUSTATION', startsAt: parisAt(fri1, '17:00'), endsAt: parisAt(fri1, '19:00'), locationName: 'Fromagerie du Plateau', address: 'Route de Salins, Amancey', priceText: 'Gratuit', imageUrl: D.U(D.I.cheese, 1400), description: "Trois affinages, trois caractères : l'équipe de la Fromagerie du Plateau vous fait goûter ses Comté de 12, 18 et 24 mois, accompagnés d'un vin jaune du Jura." },
    { title: "Fête de la pomme & marché d'automne", kind: 'MARCHE', startsAt: parisAt(addIso(sat1, 7), '09:00'), endsAt: parisAt(addIso(sat1, 7), '18:00'), organizerName: "Mairie d'Ornans", locationName: 'Place Courbet', address: 'Place Gustave Courbet, Ornans', priceText: 'Entrée libre', isFeatured: true, imageUrl: D.U(D.I.market, 1400), description: "60 exposants, pressoir à l'ancienne, jus de pomme des vergers de la vallée, animations pour les enfants et concert de la fanfare à 16h. Organisé par la Mairie d'Ornans et l'union des commerçants.", lat: 47.1063, lng: 6.1455 },
    { est: 'b8', title: "Portes ouvertes de l'atelier", kind: 'PORTES_OUVERTES', startsAt: parisAt(sun1, '10:00'), endsAt: parisAt(sun1, '17:00'), locationName: 'Céramiques Lison', address: 'Rue de la Source, Nans-sous-Sainte-Anne', priceText: 'Gratuit', imageUrl: D.U(D.I.pottery, 1400), description: "Poussez la porte de l'atelier : démonstrations de tournage toutes les heures, pièces uniques à prix doux et cuisson raku en extérieur à 15h." },
    { est: 'b1', title: 'Atelier pain au levain pour enfants', kind: 'ATELIER', startsAt: parisAt(wed1, '14:00'), endsAt: parisAt(wed1, '16:00'), locationName: 'Boulangerie Martin', address: '12 rue Pierre Vernier, Ornans', priceText: '8 € · sur inscription', capacity: 10, imageUrl: D.U(D.I.cook, 1400), description: 'Les petits boulangers de 6 à 12 ans façonnent leur pain au levain avec Sophie. 10 places, goûter offert.' },
    { title: 'Marché de nuit', kind: 'MARCHE', startsAt: parisAt(addIso(fri1, 7), '17:00'), endsAt: parisAt(addIso(fri1, 7), '22:00'), organizerName: 'Mairie de Quingey', locationName: 'Halle couverte', address: 'Halle couverte, Quingey', priceText: 'Entrée libre', imageUrl: D.U(D.I.crowd, 1400), description: 'Producteurs, artisans et food-trucks sous la halle illuminée, avec un concert de fanfare à 19h.', lat: 47.1031, lng: 5.8829 },
    { est: 'b14', title: 'Visite & dégustation de la distillerie', kind: 'DEGUSTATION', startsAt: parisAt(addIso(sat1, 14), '15:00'), endsAt: parisAt(addIso(sat1, 14), '17:00'), locationName: 'Distillerie du Val', address: 'Mouthier-Haute-Pierre', priceText: '12 € · dès 18 ans', imageUrl: D.U(D.I.toast, 1400), description: "Des alambics en cuivre à la dégustation : l'histoire de l'absinthe et des eaux-de-vie de la vallée, racontée par ceux qui les distillent." },
    { est: 'b11', title: "Atelier bougies à la cire d'abeille", kind: 'ATELIER', startsAt: parisAt(addIso(sat1, 21), '14:00'), endsAt: parisAt(addIso(sat1, 21), '16:00'), locationName: 'Miellerie des Côtes', address: 'Chemin des ruchers, Lods', priceText: '15 € · sur inscription', imageUrl: D.U(D.I.board2, 1400), description: 'Roulez vos bougies à la cire des ruches du plateau et repartez avec un pot de miel de sapin.' },
    { est: 'b16', title: "Portes ouvertes de l'ébénisterie", kind: 'PORTES_OUVERTES', startsAt: parisAt(addIso(sun1, 14), '10:00'), endsAt: parisAt(addIso(sun1, 14), '16:00'), locationName: 'Ébénisterie Rolland', address: '15 Grande rue, Amancey', priceText: 'Gratuit', imageUrl: D.U(D.I.vases, 1400), description: "Mobilier sur mesure, marqueterie et restauration : l'atelier ouvre ses portes, avec une vente de planches à découper en chutes de bois local." },
    { title: "Marché de Noël d'Ornans", kind: 'MARCHE', startsAt: parisAt(`${now.getFullYear()}-12-12`, '10:00'), endsAt: parisAt(`${now.getFullYear()}-12-12`, '20:00'), organizerName: "Mairie d'Ornans", locationName: 'Place Courbet', address: 'Place Gustave Courbet, Ornans', priceText: 'Entrée libre', isFeatured: true, imageUrl: D.U(D.I.market, 1400), description: "60 exposants, vin chaud des producteurs, atelier bougies pour les enfants et arrivée du Père Noël en barque sur la Loue à 17h. Organisé par la Mairie d'Ornans et l'union des commerçants.", lat: 47.1063, lng: 6.1455 },
  ];
  const { EVENT_PROGRAM_TEMPLATES } = await import('@/lib/constants');
  await db.insert(S.events).values(
    EVT.map(({ est, ...e }) => {
      const ref = est ? estByKey[est] : null;
      const communeName = ref?.commune ?? (e.address?.includes('Quingey') ? 'Quingey' : 'Ornans');
      return {
        ...e,
        territoryId: vdl.id,
        communeId: communeRows[communeName].id,
        establishmentId: ref?.id ?? null,
        authorType: ref ? ('ESTABLISHMENT' as const) : ('COMMUNE' as const),
        slug: slugify(`${e.title}-${isoDay(e.startsAt as Date)}`),
        lat: e.lat ?? ref?.lat ?? null,
        lng: e.lng ?? ref?.lng ?? null,
        accessibilityText: 'Accès PMR',
        program: EVENT_PROGRAM_TEMPLATES[e.kind as 'MARCHE'] ?? [],
        createdById: ref?.ownerId ?? anne.id,
        status: 'PUBLISHED' as const,
      };
    }),
  );

  // ─── Emplois ──────────────────────────────────────────────────────────────
  await db.insert(S.jobs).values(
    D.JOBS.map((j) => {
      const ref = estByKey[j.est];
      return {
        territoryId: vdl.id,
        communeId: communeRows[ref.commune].id,
        establishmentId: ref.id,
        slug: slugify(`${j.title}-${ref.name}`),
        title: j.title,
        contractType: j.contract,
        status: 'PUBLISHED' as const,
        startText: j.start,
        salaryText: j.salary,
        workTimeText: j.time,
        description: j.desc,
        missions: j.missions,
        profile: j.profile,
        publishedAt: daysAgo(j.daysAgo),
        createdAt: daysAgo(j.daysAgo),
        expiresAt: new Date(now.getTime() + 60 * DAY),
      };
    }),
  );
  // Offres complémentaires des entreprises générées (37 offres au total sur le territoire)
  // Intitulés cohérents avec le métier de chaque entreprise.
  type Ct = 'CDI' | 'CDD' | 'ALTERNANCE' | 'SAISONNIER' | 'STAGE';
  const jobTitles: Record<string, [string, Ct][]> = {
    coiffure: [['Coiffeur·se', 'CDI'], ['Apprenti·e coiffeur·se', 'ALTERNANCE']],
    'institut-beaute': [['Esthéticien·ne', 'CDI']],
    boucherie: [['Apprenti·e boucher·e', 'ALTERNANCE'], ['Boucher·e qualifié·e', 'CDI']],
    boulangerie: [['Vendeur·se en boulangerie', 'CDD']],
    maconnerie: [['Maçon·ne qualifié·e', 'CDI']],
    electricien: [['Électricien·ne', 'CDI'], ['Apprenti·e électricien·ne', 'ALTERNANCE']],
    couvreur: [['Couvreur·se', 'CDI']],
    peintre: [['Peintre en bâtiment', 'CDI']],
    menuiserie: [['Menuisier·ère poseur·se', 'CDI']],
    'plombier-chauffagiste': [['Plombier·e chauffagiste', 'CDI']],
    garage: [['Mécanicien·ne automobile', 'CDI']],
    superette: [['Employé·e polyvalent·e', 'CDD']],
    epicerie: [['Employé·e polyvalent·e', 'CDD']],
    restaurant: [['Commis de cuisine', 'SAISONNIER'], ['Serveur·se', 'SAISONNIER']],
    pizzeria: [['Pizzaïolo', 'CDI']],
    bistrot: [['Serveur·se', 'SAISONNIER']],
    auberge: [['Commis de cuisine', 'SAISONNIER']],
    'pret-a-porter': [['Vendeur·se', 'CDD']],
    librairie: [['Libraire', 'CDD']],
    fleuriste: [['Fleuriste', 'CDI']],
    pharmacie: [['Préparateur·rice en pharmacie', 'CDI']],
    conseil: [['Assistant·e administratif·ve', 'CDI'], ['Stagiaire communication', 'STAGE']],
    informatique: [['Technicien·ne informatique', 'CDI']],
    hebergement: [['Réceptionniste saison', 'SAISONNIER']],
    paysagiste: [['Ouvrier·ère paysagiste', 'SAISONNIER']],
    ferme: [['Ouvrier·ère agricole', 'SAISONNIER']],
    fromagerie: [['Fromager·ère', 'CDI']],
  };
  const extraJobs = r.sample(genClaimedVdl.filter((e) => jobTitles[e.category]), 29).map((e, i) => {
    const [title, contract] = r.pick(jobTitles[e.category]);
    return {
      territoryId: vdl.id,
      communeId: communeRows[e.commune].id,
      establishmentId: e.id,
      slug: slugify(`${title}-${e.name}-${i}`),
      title,
      contractType: contract,
      status: 'PUBLISHED' as const,
      startText: r.pick(['Dès que possible', 'Novembre 2026', 'Janvier 2027', 'Printemps 2027']),
      salaryText: r.pick(['Selon profil', 'SMIC + primes', '1 900 – 2 200 € brut', 'Grille conventionnelle']),
      workTimeText: r.pick(['Temps plein', '35 h', '28 h / semaine', 'Temps partiel possible']),
      description: `${e.name} renforce son équipe à ${e.commune} : rejoignez une entreprise locale où chaque personne compte.`,
      missions: ['Accueillir et conseiller la clientèle', "Participer à la vie de l'entreprise"],
      profile: ['Sérieux et motivation', 'Débutant·e accepté·e'],
      publishedAt: daysAgo(r.int(1, 40)),
      createdAt: daysAgo(r.int(1, 40)),
    };
  });
  await db.insert(S.jobs).values(extraJobs);

  // ─── Circuits ─────────────────────────────────────────────────────────────
  console.log('→ Circuits, campagnes, newsletter');
  for (const [i, c] of D.CIRCUITS.entries()) {
    const [circ] = await db
      .insert(S.circuits)
      .values({
        territoryId: vdl.id,
        slug: c.slug,
        name: c.name,
        meta: c.meta,
        description: c.description,
        distanceKm: c.km,
        durationText: c.dur,
        travelMode: c.mode,
        rewardText: c.reward,
        rewardThreshold: c.threshold,
        imageUrl: D.U(c.img, 1200),
        tagColor: c.tagColor,
        sortOrder: i,
        createdById: claire.id,
      })
      .returning();
    const stops = await db
      .insert(S.circuitStops)
      .values(c.stops.map((k, pos) => ({ circuitId: circ.id, position: pos, establishmentId: estByKey[k].id, stampSecret: randomToken(12) })))
      .returning();
    // Passeports ouverts par les visiteurs (statistiques C6)
    const passportsCount = i === 0 ? 312 : i === 1 ? 64 : 88;
    for (let p = 0; p < passportsCount; p++) {
      const created = daysAgo(r.int(0, 120));
      const nStamps = r.weighted([[0, 2], [1, 3], [2, 3], [3, 2], [4, 1.5], [5, 1.2], [6, 0.5], [7, 0.4]] as [number, number][]);
      const completed = nStamps >= c.threshold;
      const [pp] = await db
        .insert(S.passports)
        .values({ circuitId: circ.id, tokenHash: sha256(randomToken(16)), createdAt: created, lastSeenAt: created, completedAt: completed ? created : null, rewardCode: completed ? shortCode(6) : null, rewardClaimedAt: completed && r.chance(0.5) ? created : null })
        .returning();
      const chosen = r.sample(stops, Math.min(nStamps, stops.length));
      if (chosen.length) await db.insert(S.passportStamps).values(chosen.map((s) => ({ passportId: pp.id, stopId: s.id, stampedAt: created })));
    }
  }

  // ─── Campagnes ────────────────────────────────────────────────────────────
  const year = now.getFullYear();
  const [noel] = await db
    .insert(S.campaigns)
    .values({
      territoryId: vdl.id,
      slug: 'noel-chez-vos-commercants',
      name: 'Noël chez vos commerçants',
      tagline: "Un calendrier de l'Avent avec une surprise par jour",
      description: "Chaque jour, une case s'ouvre : une remise, un atelier, une dégustation. 42 commerces du Val de Loue jouent le jeu, et le marché de Noël d'Ornans vous attend le 12 décembre.",
      startsAt: `${year}-12-01`,
      endsAt: `${year}-12-24`,
      status: 'SCHEDULED',
      mode: 'ADVENT',
      colorBg: '#7A2E26',
      colorBgDark: '#5E1F1A',
      colorText: '#FFF3E6',
      colorTextSoft: '#F3D5C9',
      heroImageUrl: D.U(D.I.xmas2, 2000),
      cardImageUrl: D.U(D.I.xmas, 1200),
      ctaLabel: 'Ouvrir le calendrier',
      criteria: { families: ['COMMERCE', 'ARTISAN', 'PRODUCTEUR'], attributeSlugs: ['idees-cadeaux'] },
      invitationMessage: "Ajoutez une offre : elle apparaîtra dans le calendrier de l'Avent, la newsletter et la page campagne.",
      createdById: claire.id,
      createdAt: daysAgo(12),
    })
    .returning();
  const [rentree] = await db
    .insert(S.campaigns)
    .values({
      territoryId: vdl.id,
      slug: 'rentree-chez-vos-commercants',
      name: 'La rentrée chez vos commerçants',
      tagline: 'Des offres locales pour bien démarrer l’automne',
      description: "Jusqu'à mi-octobre, les commerces du Val de Loue vous réservent leurs offres de rentrée.",
      startsAt: addIso(today, -24),
      endsAt: addIso(today, 20),
      status: 'ACTIVE',
      mode: 'STANDARD',
      colorBg: '#1F6B52',
      colorBgDark: '#14201B',
      colorText: '#F7F4EC',
      colorTextSoft: '#CFE3D6',
      heroImageUrl: D.U(D.I.market, 2000),
      cardImageUrl: D.U(D.I.market, 1200),
      criteria: { families: ['COMMERCE'] },
      createdById: thomas.id,
      createdAt: daysAgo(40),
    })
    .returning();
  const [artisanat] = await db
    .insert(S.campaigns)
    .values({ territoryId: vdl.id, slug: 'semaine-de-l-artisanat', name: "Semaine de l'artisanat", tagline: "Portes ouvertes d'ateliers", description: "Une semaine pour découvrir les ateliers du territoire : démonstrations, visites et rencontres.", startsAt: `${year + 1}-03-22`, endsAt: `${year + 1}-03-29`, status: 'SCHEDULED', cardImageUrl: D.U(D.I.crafts, 1200), heroImageUrl: D.U(D.I.crafts, 2000), criteria: { families: ['ARTISAN'] }, createdById: claire.id })
    .returning();
  const [madeInCamp] = await db
    .insert(S.campaigns)
    .values({ territoryId: vdl.id, slug: 'made-in-val-de-loue', name: 'Made in Val de Loue', tagline: 'Sélection fabrication locale', description: 'La sélection permanente des produits fabriqués sur le territoire.', startsAt: addIso(today, -60), endsAt: `${year + 2}-12-31`, status: 'DRAFT', cardImageUrl: D.U(D.I.pottery, 1200), heroImageUrl: D.U(D.I.pottery, 2000), criteria: { attributeSlugs: ['fabrication-locale'] }, createdById: thomas.id })
    .returning();

  const noelPool = [
    ...['b1', 'b9', 'b8', 'b2', 'b6', 'b14', 'b11', 'b13', 'b15'].map((k) => estByKey[k]),
    ...r.sample(genClaimedVdl.filter((e) => ['boulangerie', 'boucherie', 'fleuriste', 'epicerie', 'caviste', 'pret-a-porter', 'librairie', 'chocolatier', 'fromagerie', 'ferme', 'apiculteur', 'metiers-d-art'].includes(e.category)), 60),
  ].slice(0, 42);
  await db.insert(S.campaignParticipants).values(
    noelPool.map((e) => {
      const key = Object.entries(estByKey).find(([, v]) => v.id === e.id)?.[0];
      const offer = key && D.OFFERS[key] ? D.OFFERS[key] : null;
      return { campaignId: noel.id, establishmentId: e.id, status: key === 'b1' ? ('INVITED' as const) : ('JOINED' as const), offerLabel: key === 'b1' ? null : offer ?? r.pick(['-10 %', 'Surprise en boutique', 'Emballage offert', 'Dégustation']), invitedAt: daysAgo(12), joinedAt: key === 'b1' ? null : daysAgo(r.int(1, 11)) };
    }),
  );
  await db.insert(S.adventDoors).values(
    D.ADVENT.map((title, i) => ({ campaignId: noel.id, day: i + 1, title, establishmentId: D.ADVENT_EST[i + 1] ? estByKey[D.ADVENT_EST[i + 1]].id : null })),
  );
  await db.insert(S.campaignParticipants).values(
    r.sample(genClaimedVdl.filter((e) => ['boulangerie', 'boucherie', 'coiffure', 'pret-a-porter', 'fleuriste', 'librairie', 'epicerie'].includes(e.category)), 18).map((e) => ({
      campaignId: rentree.id,
      establishmentId: e.id,
      status: 'JOINED' as const,
      offerLabel: r.pick(['-10 %', '-15 % sur la 2e pièce', 'Carte fidélité doublée', 'Café offert']),
      joinedAt: daysAgo(r.int(5, 20)),
    })),
  );
  await db.insert(S.campaignParticipants).values(
    r.sample(genClaimedVdl.filter((e) => ['menuiserie', 'maconnerie', 'couvreur', 'peintre', 'metiers-d-art', 'ebeniste', 'boulangerie'].includes(e.category)), 27).map((e) => ({ campaignId: artisanat.id, establishmentId: e.id, status: 'INVITED' as const, invitedAt: daysAgo(3) })),
  );
  const fab = await db
    .select({ id: S.establishmentAttributes.establishmentId })
    .from(S.establishmentAttributes)
    .where(eq(S.establishmentAttributes.attributeId, attrBySlug.get('fabrication-locale')!.id));
  await db.insert(S.campaignParticipants).values(
    r.sample(fab.map((f) => f.id).filter((id) => allEsts.find((e) => e.id === id && communeRows[e.commune])), 64).map((id) => ({ campaignId: madeInCamp.id, establishmentId: id, status: 'JOINED' as const, joinedAt: daysAgo(r.int(1, 50)) })),
  );

  // ─── Newsletter : audiences, abonnés, lettres ─────────────────────────────
  const [audH, audT, audE, audO, audC] = await db
    .insert(S.audiences)
    .values([
      { territoryId: vdl.id, name: 'Habitants', description: 'Toutes communes', kind: 'MANUAL', isDefault: true, sortOrder: 0 },
      { territoryId: vdl.id, name: 'Touristes', description: 'Inscrits via office de tourisme', kind: 'MANUAL', sortOrder: 1 },
      { territoryId: vdl.id, name: 'Entreprises', description: 'Lettre pro mensuelle', kind: 'BUSINESSES', sortOrder: 2 },
      { territoryId: vdl.id, name: 'Ornans', description: 'Zone géographique', kind: 'COMMUNE', communeId: communeRows['Ornans'].id, sortOrder: 3 },
      { territoryId: vdl.id, name: 'Amateurs de circuits', description: 'Passeports ouverts', kind: 'CIRCUIT', sortOrder: 4 },
    ])
    .returning();
  const consentText = "J'accepte de recevoir la lettre d'information du Val de Loue. Mes données ne sont jamais revendues (RGPD).";
  const subRows: (typeof S.subscribers.$inferInsert)[] = [];
  const subAud: { idx: number; aud: string }[] = [];
  const total = 5620;
  for (let i = 0; i < total; i++) {
    const tourist = i >= 4120 && i < 5500;
    const pro = i >= 5500;
    const communeName = tourist || pro ? null : r.weighted(D.VAL_DE_LOUE_COMMUNES.map((c) => [c.name, c.name === 'Ornans' ? 1740 / 4120 : c.pop / 22400] as [string, number]));
    const status = r.chance(0.012) ? 'UNSUBSCRIBED' : r.chance(0.02) ? 'PENDING' : 'CONFIRMED';
    const created = daysAgo(r.int(0, 200));
    subRows.push({
      territoryId: vdl.id,
      email: `abonne${i + 1}@demo.terricom.test`,
      communeId: communeName ? communeRows[communeName].id : null,
      status: status as 'CONFIRMED',
      source: tourist ? 'OFFICE_TOURISME' : pro ? 'IMPORT' : 'PORTAL',
      consentText,
      consentAt: created,
      confirmedAt: status === 'CONFIRMED' ? created : null,
      unsubscribedAt: status === 'UNSUBSCRIBED' ? daysAgo(r.int(0, 30)) : null,
      unsubscribeToken: randomToken(24),
      createdAt: created,
    });
    if (!tourist && !pro) subAud.push({ idx: i, aud: audH.id });
    if (tourist) subAud.push({ idx: i, aud: audT.id });
    if (pro) subAud.push({ idx: i, aud: audE.id });
    if (communeName === 'Ornans') subAud.push({ idx: i, aud: audO.id });
    if (i % 18 === 0) subAud.push({ idx: i, aud: audC.id });
  }
  const insertedSubs: { id: string }[] = [];
  for (let i = 0; i < subRows.length; i += 500) insertedSubs.push(...(await db.insert(S.subscribers).values(subRows.slice(i, i + 500)).returning({ id: S.subscribers.id })));
  await insertMany(S.subscriberAudiences, subAud.map((s) => ({ subscriberId: insertedSubs[s.idx].id, audienceId: s.aud })));

  const nlBase = { territoryId: vdl.id, audienceIds: [audH.id, audT.id], createdById: thomas.id };
  for (const [n, subject, sentDays, opens] of [
    [45, 'La rentrée gourmande du Val de Loue', 28, 0.46],
    [46, 'Trois ateliers à découvrir ce week-end', 21, 0.49],
    [47, 'Le marché de nuit revient à Quingey', 14, 0.47],
  ] as [number, string, number, number][]) {
    const recipients = 5320;
    await db.insert(S.newsletters).values({
      ...nlBase,
      number: n,
      subject,
      title: subject,
      intro: 'Les nouveautés de vos commerçants, artisans et producteurs.',
      heroImageUrl: D.U(D.I.market, 1100, 500),
      status: 'SENT',
      sentAt: daysAgo(sentDays, 8),
      scheduledAt: daysAgo(sentDays, 8),
      statsRecipients: recipients,
      statsSent: recipients - 12,
      statsOpens: Math.round(recipients * opens),
      statsClicks: Math.round(recipients * 0.11),
      statsUnsubscribes: Math.round(recipients * 0.003),
      statsBounces: 12,
    });
  }
  await db.insert(S.newsletters).values({
    ...nlBase,
    number: 48,
    subject: "Ce week-end, la fête de la pomme s'installe à Ornans",
    preheader: '60 exposants place Courbet, et trois adresses à découvrir',
    title: "Ce week-end, la fête de la pomme s'installe à Ornans",
    intro: 'Samedi, 60 exposants place Courbet. Et pour prolonger, trois adresses qui ont des nouveautés cette semaine :',
    heroImageUrl: D.U(D.I.market, 1100, 500),
    blocks: [
      { type: 'establishments', ids: [estByKey.b9.id, estByKey.b2.id, estByKey.b8.id], title: 'Trois adresses à découvrir' },
      { type: 'cta', label: "Voir tout l'agenda", url: '/valdeloue/agenda' },
    ],
    status: 'DRAFT',
    scheduledAt: parisAt(addIso(sat1, 6), '08:00'),
  });

  // ─── Revendications en attente (C3) ───────────────────────────────────────
  console.log('→ Revendications, messages, historique');
  const claimants = [
    { key: 'b5', first: 'Julien', last: 'Mougin', email: 'julien@chauffage-mougin.fr', avatar: D.I.p1, risk: 'LOW' as const, hours: 2, siret: '79012345600018', holder: 'MOUGIN JULIEN', checks: [{ ok: true, label: 'SIRET vérifié', detail: 'Titulaire : MOUGIN JULIEN' }, { ok: true, label: 'Domaine email', detail: 'chauffage-mougin.fr = site de la fiche' }, { ok: false, label: 'Téléphone', detail: 'Numéro différent de celui importé' }] },
    { key: 'b12', first: 'Marc', last: 'Petit', email: 'marc.petit@gmail.com', avatar: D.I.p3, risk: 'MEDIUM' as const, hours: 26, siret: '31234567800017', holder: 'SARL GARAGE DES TILLEULS', checks: [{ ok: false, label: 'SIRET', detail: 'Nom du gérant différent' }, { ok: false, label: 'Email personnel', detail: 'Domaine non professionnel' }, { ok: true, label: 'Kbis déposé', detail: 'Daté de moins de 3 mois' }] },
    { key: 'b11', first: 'Léa', last: 'Bouvier', email: 'contact@miellerie-cotes.fr', avatar: D.I.p2, risk: 'LOW' as const, hours: 30, siret: '90123456700015', holder: 'BOUVIER LEA', checks: [{ ok: true, label: 'SIRET vérifié', detail: 'Titulaire : BOUVIER LEA' }, { ok: true, label: 'Code courrier', detail: 'Saisi il y a 2 jours' }, { ok: true, label: 'Téléphone', detail: 'Identique à la fiche' }] },
    { key: 'b16', first: 'Paul', last: 'Rolland', email: 'paul@rolland-ebeniste.fr', avatar: D.I.p1, risk: 'LOW' as const, hours: 72, siret: '75312468900014', holder: 'ROLLAND PAUL', checks: [{ ok: true, label: 'SIRET vérifié', detail: 'Titulaire : ROLLAND PAUL' }, { ok: true, label: 'Domaine email', detail: 'Cohérent' }] },
  ];
  for (const c of claimants) {
    const u = await mkUser({ email: c.email, first: c.first, last: c.last, avatar: D.U(c.avatar, 100, 100), createdAt: hoursAgo(c.hours) });
    await db.insert(S.claims).values({
      establishmentId: estByKey[c.key].id,
      territoryId: vdl.id,
      userId: u.id,
      status: 'PENDING',
      claimantRole: 'Gérant·e',
      method: c.key === 'b12' ? 'KBIS' : c.key === 'b11' ? 'CODE' : 'SIRET',
      siretProvided: fixSiret(c.siret),
      sireneHolder: c.holder,
      checks: c.checks,
      riskLevel: c.risk,
      createdAt: hoursAgo(c.hours),
      updatedAt: hoursAgo(c.hours),
    });
  }
  // Revendications déjà traitées (historique d'adoption)
  const approvedClaims = genEsts
    .filter((e) => e.ownerId && communeRows[e.commune])
    .slice(0, 180)
    .map((e) => ({ establishmentId: e.id, territoryId: vdl.id, userId: e.ownerId!, status: 'APPROVED' as const, method: 'SIRET', riskLevel: 'LOW' as const, reviewerId: r.chance(0.6) ? claire.id : anne.id, reviewedAt: daysAgo(r.int(1, 150)), createdAt: daysAgo(r.int(2, 160)) }));
  await insertMany(S.claims, approvedClaims);
  await db.insert(S.claims).values({ establishmentId: estByKey.b1.id, territoryId: vdl.id, userId: sophieId, status: 'APPROVED', method: 'SIRET', siretProvided: fixSiret('81234567800019'), sireneHolder: 'MARTIN SOPHIE', riskLevel: 'LOW', reviewerId: anne.id, reviewedAt: daysAgo(39), createdAt: daysAgo(40), checks: [{ ok: true, label: 'SIRET vérifié', detail: 'Titulaire : MARTIN SOPHIE' }] });

  await db.insert(S.establishmentRevisions).values([
    { establishmentId: estByKey.b5.id, source: 'IMPORT', summary: 'Fiche précréée (import SIRENE)', createdAt: daysAgo(200) },
    { establishmentId: estByKey.b5.id, source: 'COLLECTIVITE', userId: thomas.id, summary: 'Photos ajoutées par la collectivité', createdAt: daysAgo(120) },
    { establishmentId: estByKey.b12.id, source: 'IMPORT', summary: 'Fiche précréée (import SIRENE)', createdAt: daysAgo(200) },
    { establishmentId: estByKey.b11.id, source: 'IMPORT', summary: 'Fiche précréée (import SIRENE)', createdAt: daysAgo(200) },
    { establishmentId: estByKey.b11.id, source: 'COLLECTIVITE', userId: hugo.id, summary: 'Horaires complétés par la mairie', createdAt: daysAgo(90) },
    { establishmentId: estByKey.b16.id, source: 'IMPORT', summary: 'Fiche précréée (import SIRENE)', createdAt: daysAgo(200) },
    { establishmentId: estByKey.b1.id, source: 'IMPORT', summary: 'Fiche précréée (import SIRENE)', createdAt: daysAgo(200) },
    { establishmentId: estByKey.b1.id, source: 'PRO', userId: sophieId, summary: 'Revendication validée par la mairie d’Ornans', createdAt: daysAgo(39) },
    { establishmentId: estByKey.b1.id, source: 'PRO', userId: sophieId, summary: 'Modification : Description, Photos', createdAt: daysAgo(2) },
  ]);

  // Messages reçus par Boulangerie Martin (E2)
  await db.insert(S.messages).values([
    { establishmentId: estByKey.b1.id, territoryId: vdl.id, senderName: 'Julie P.', senderEmail: 'julie.p@exemple.test', body: 'Bonjour, peut-on commander 2 galettes pour le 6 janvier ?', createdAt: hoursAgo(2) },
    { establishmentId: estByKey.b1.id, territoryId: vdl.id, senderName: 'Marc D.', senderEmail: 'marc.d@exemple.test', body: 'Faites-vous du pain sans gluten ?', createdAt: daysAgo(1, 15) },
    { establishmentId: estByKey.b1.id, territoryId: vdl.id, source: 'COLLECTIVITE', fromUserId: anne.id, senderName: "Mairie d'Ornans", senderEmail: 'commerce@ornans.fr', body: 'Merci de confirmer vos horaires de la Toussaint pour la page de la commune.', createdAt: daysAgo(3, 9) },
  ]);
  await db.insert(S.jobApplications).values({
    jobId: (await db.select({ id: S.jobs.id }).from(S.jobs).where(eq(S.jobs.establishmentId, estByKey.b1.id)).limit(1))[0].id,
    establishmentId: estByKey.b1.id,
    fullName: 'Lucas Grosjean',
    email: 'lucas.g@exemple.test',
    message: "Bonjour, j'entre en CAP boulanger en septembre et je cherche un maître d'apprentissage passionné.",
    createdAt: daysAgo(1, 11),
  });

  // ─── Pipeline commercial (CRM) ────────────────────────────────────────────
  console.log('→ CRM, facturation, audit');
  const stageLog: [string, string, string][] = [
    ['Premier contact', 'Échange au salon des maires', 'PROSPECT'],
    ['Appel', 'Qualification du besoin, 24 min', 'FIRST_CONTACT'],
    ['Démo', 'Démo en visio avec 4 élus', 'DEMO'],
    ['Proposition', 'Envoi proposition commerciale v1', 'PROPOSAL'],
    ['Négociation', 'Réunion sur le tarif et le périmètre', 'NEGOTIATION'],
    ['Signature', 'Délibération votée en conseil', 'SIGNED'],
    ['Onboarding', 'Kick-off et import des données', 'ONBOARDING'],
    ['Bilan', "Point d'usage à 3 mois", 'ACTIVE'],
  ];
  const stageOrder = ['PROSPECT', 'FIRST_CONTACT', 'DEMO', 'PROPOSAL', 'NEGOTIATION', 'SIGNED', 'ONBOARDING', 'ACTIVE'];
  const NOTE: Record<string, string> = {
    PROSPECT: "Territoire au tissu commercial fragile, forte attente sur la redynamisation des centres-bourgs. Budget à inscrire au prochain exercice : viser une démo avant le débat d'orientation budgétaire.",
    NEGOTIATION: 'Intérêt fort du vice-président. Point d’attention : la DGS souhaite comparer avec la solution régionale. Argument clé : gratuité pour les entreprises et fiches précréées dès J1.',
    ONBOARDING: 'Contrat signé. Référent technique identifié, import des données en cours. Objectif : 30 % de fiches revendiquées à 3 mois.',
    ACTIVE: 'Client satisfait, adoption au-dessus de la moyenne. Potentiel de montée en gamme sur les modules seconde génération (IA, circuits).',
  };
  const ACTIONS: Record<string, [string, string][]> = {
    pro: [['Qualifier le besoin (appel 20 min)', 'cette sem.'], ['Envoyer la plaquette terricom.fr', 'J+2'], ['Proposer une démo en ligne', 'J+7']],
    neg: [['Envoyer la proposition chiffrée', 'fait ?'], ['Préparer la note pour le conseil communautaire', 'J+3'], ['Répondre aux questions RGPD / hébergement', 'J+5']],
    onb: [['Import SIRENE et contrôle des doublons', 'en cours'], ['Former les administrateurs communaux', 'J+4'], ['Planifier le lancement presse', 'J+20']],
    act: [["Bilan d'usage trimestriel", 'janv.'], ['Proposer le module Assistant IA', 'févr.'], ["Recueillir un témoignage d'élu", 'mars']],
  };
  for (const [i, d] of DEALS.entries()) {
    const stageIdx = stageOrder.indexOf(d.stage);
    const [deal] = await db
      .insert(S.deals)
      .values({
        name: d.name,
        kind: d.kind,
        communesCount: d.communes,
        population: d.pop,
        stage: d.stage,
        probability: d.prob,
        licenceCents: d.licence,
        setupCents: d.communes > 30 ? 800000 : d.communes > 10 ? 500000 : 200000,
        ownerId: i % 3 === 2 ? sales.id : camille.id,
        territoryId: d.territory ? territoryBySlug[d.territory].id : null,
        nextAction: d.next,
        notes: NOTE[d.stage] ?? NOTE[stageIdx >= 6 ? 'ACTIVE' : stageIdx >= 3 ? 'NEGOTIATION' : 'PROSPECT'],
        source: r.pick(['Salon des maires', 'Recommandation', 'Démo en ligne', 'Appel entrant']),
        lat: d.lat,
        lng: d.lng,
        lastInteractionAt: daysAgo(2 + i * 2),
        createdAt: daysAgo(220 - i * 8),
      })
      .returning();
    await db.insert(S.dealContacts).values([
      { dealId: deal.id, name: d.contact, role: 'Vice-président·e développement économique', tag: 'Décideur', sortOrder: 0 },
      { dealId: deal.id, name: r.pick(['Martine Rolland', 'Sylvie Carrez', 'Jean-Marc Boillot']), role: 'Directrice générale des services', tag: 'Influence', sortOrder: 1 },
      { dealId: deal.id, name: r.pick(['Kevin Morel', 'Laura Pichon', 'Nathan Vidal']), role: 'Chargé·e de mission commerce', tag: 'Utilisateur', sortOrder: 2 },
    ]);
    const logs = stageLog.slice(0, stageIdx + 1).map(([kind, text], k) => ({ dealId: deal.id, kind, text, occurredAt: daysAgo(210 - i * 8 - k * 18), userId: camille.id }));
    await db.insert(S.dealActivities).values(logs);
    const group = stageIdx >= 7 ? 'act' : stageIdx >= 5 ? 'onb' : stageIdx >= 3 ? 'neg' : 'pro';
    await db.insert(S.dealTasks).values(ACTIONS[group].map(([text, due], k) => ({ dealId: deal.id, text, dueText: due, sortOrder: k })));
    const docs: [string, number][] = [['Plaquette terricom.pdf', 0], ['Support de démo.pdf', 2], ['Proposition commerciale v2.pdf', 3], ['Contrat signé.pdf', 5], ['Convention RGPD.pdf', 5]];
    await db.insert(S.dealDocuments).values(docs.filter(([, s]) => stageIdx >= s).map(([name]) => ({ dealId: deal.id, name })));
  }

  // ─── Factures ─────────────────────────────────────────────────────────────
  const { createInvoice } = await import('@/server/services/billing');
  await createInvoice({ customerType: 'TERRITORY', territoryId: vdl.id, customerName: 'Communauté de communes du Val de Loue', customerAddress: '2 place de l’Hôtel de Ville, 25290 Ornans', issuedAt: '2026-02-10', lines: [{ label: 'Mise en service : paramétrage, import SIRENE, formation', quantity: 1, unitCents: 500000, vatRate: 20 }], status: 'PAID', paidAt: '2026-03-18', paymentMethod: 'MANDAT_ADMINISTRATIF', chorusRef: 'CPP-2026-004512' });
  await createInvoice({ customerType: 'TERRITORY', territoryId: vdl.id, customerName: 'Communauté de communes du Val de Loue', customerAddress: '2 place de l’Hôtel de Ville, 25290 Ornans', issuedAt: '2026-03-01', lines: [{ label: 'Licence annuelle terricom — territoire pilote (mars 2026 – février 2027)', quantity: 1, unitCents: 900000, vatRate: 20 }], status: 'PAID', paidAt: '2026-04-06', paymentMethod: 'MANDAT_ADMINISTRATIF', chorusRef: 'CPP-2026-006021' });
  for (const t of OTHER_TERRITORIES) {
    await createInvoice({ customerType: 'TERRITORY', territoryId: territoryBySlug[t.slug].id, customerName: t.legalName, issuedAt: t.signedAt, lines: [{ label: 'Mise en service', quantity: 1, unitCents: t.setupCents, vatRate: 20 }, { label: 'Licence annuelle terricom', quantity: 1, unitCents: t.licenceCents, vatRate: 20 }], status: t.status === 'ACTIVE' ? 'PAID' : 'ISSUED', paidAt: t.status === 'ACTIVE' ? addIso(t.signedAt, 35) : undefined, paymentMethod: 'MANDAT_ADMINISTRATIF' });
  }

  // ─── Utilisation IA, tickets, RGPD ────────────────────────────────────────
  const aiRows = Array.from({ length: 420 }, () => ({
    territoryId: vdl.id,
    feature: r.weighted([['WRITER', 5], ['IMPROVE', 2], ['SEARCH', 6], ['TERRITORIAL', 1]] as [('WRITER' | 'IMPROVE' | 'SEARCH' | 'TERRITORIAL'), number][]),
    model: 'claude-opus-5',
    inputTokens: r.int(900, 4000),
    outputTokens: r.int(300, 1500),
    credits: r.int(2, 8),
    createdAt: new Date(now.getFullYear(), now.getMonth(), r.int(1, Math.max(1, now.getDate())), r.int(8, 20)),
  }));
  await insertMany(S.aiUsage, aiRows);
  await db.insert(S.supportTickets).values(
    [
      ['Import CSV : colonnes non reconnues', 'haut-jura'],
      ['Ajouter un administrateur communal', 'pays-de-lure'],
      ['Logo du portail flou sur mobile', 'valdeloue'],
      ['Question sur la facturation Chorus Pro', 'grand-figeac'],
      ['Newsletter : domaine d’envoi personnalisé', 'quimperle'],
      ['Doublons après import SIRENE', 'haut-jura'],
      ['Formation des agents de la mairie', 'dole'],
    ].map(([subject, t], i) => ({ subject, territoryId: territoryBySlug[t].id, status: 'OPEN' as const, body: '', createdAt: daysAgo(i + 1), createdById: camille.id })),
  );
  await db.insert(S.privacyRequests).values([
    ...Array.from({ length: 17 }, (_, i) => ({ email: `habitant${i + 1}@demo.terricom.test`, kind: 'EXPORT' as const, status: 'DONE' as const, territoryId: vdl.id, createdAt: daysAgo(150 - i * 8), completedAt: daysAgo(149 - i * 8) })),
    ...Array.from({ length: 9 }, (_, i) => ({ email: `ancien${i + 1}@demo.terricom.test`, kind: 'DELETE' as const, status: 'DONE' as const, territoryId: vdl.id, createdAt: daysAgo(140 - i * 12), completedAt: daysAgo(139 - i * 12) })),
  ]);

  // ─── Statistiques d'audience simulées ────────────────────────────────────
  console.log("→ Statistiques d'audience (simulation depuis le lancement pilote)");
  const { seedAnalytics } = await import('./seed/analytics');
  await seedAnalytics({ db, sql, territoryId: vdl.id, b1: estByKey.b1.id, launch: '2026-03-02', now });

  // ─── Scores de complétude et index de recherche ──────────────────────────
  console.log('→ Index de recherche et complétude des fiches');
  await db.execute(sql`
    UPDATE establishments e SET search_keywords = trim(concat_ws(' ',
      (SELECT k.name || ' ' || array_to_string(k.synonyms, ' ') FROM categories k WHERE k.id = e.category_id),
      (SELECT string_agg(a.label, ' ') FROM establishment_attributes ea JOIN attributes a ON a.id = ea.attribute_id WHERE ea.establishment_id = e.id),
      (SELECT string_agg(p.name, ' ') FROM products p WHERE p.establishment_id = e.id),
      (SELECT c.name FROM communes c WHERE c.id = e.commune_id)))`);
  const ids = allEsts.map((e) => e.id);
  for (let i = 0; i < ids.length; i += 50) await Promise.all(ids.slice(i, i + 50).map((id) => refreshCompleteness(id)));

  // ─── Journal d'audit ──────────────────────────────────────────────────────
  const auditEntries: Parameters<typeof audit>[0][] = [
    { actor: 'Système', category: 'IMPORT', action: 'import.sirene', summary: `Import SIRENE Val de Loue : ${allEsts.filter((e) => communeRows[e.commune]).length} fiches créées`, territoryId: vdl.id },
    { actor: { user: camille }, category: 'CONFIGURATION', action: 'module.enable', summary: 'Module « Assistant IA » activé pour Val de Loue', territoryId: vdl.id },
    { actor: 'Système', category: 'IMPORT', action: 'import.sirene', summary: 'Import SIRENE Haut-Jura : 90 fiches créées', territoryId: territoryBySlug['haut-jura'].id },
    { actor: { user: claire }, category: 'MODERATION', action: 'establishment.suspend', summary: 'A suspendu « Céramiques Lison » (inactive 5 mois)', territoryId: vdl.id, targetType: 'establishment', targetId: estByKey.b8.id },
    { actor: { user: thomas }, category: 'ENVOI', action: 'newsletter.schedule', summary: 'Newsletter n°47 programmée (5 320 destinataires)', territoryId: vdl.id },
    { actor: { user: hugo }, category: 'SECURITE', action: 'auth.locked', summary: 'Connexion refusée : 5 tentatives, compte verrouillé 15 min', territoryId: vdl.id },
    { actor: 'Système', category: 'RGPD', action: 'privacy.export', summary: 'Export RGPD généré pour un habitant (demande n°17)', territoryId: vdl.id },
    { actor: { user: anne }, category: 'MODIFICATION', action: 'establishment.update', summary: 'A modifié les horaires de « Boulangerie Martin »', territoryId: vdl.id, targetType: 'establishment', targetId: estByKey.b1.id },
    { actor: { id: support.id, label: 'Support terricom' }, category: 'SUPPORT', action: 'support.impersonate', summary: 'Accès support ouvert sur Haut-Jura (ticket #1, 30 min)', territoryId: territoryBySlug['haut-jura'].id },
    { actor: { user: claire }, category: 'VALIDATION', action: 'claim.approve', summary: 'A validé la revendication « Ferme des Granges »', territoryId: vdl.id },
  ];
  for (const a of auditEntries) await audit(a);

  const counts = await db.execute<{ t: string; n: number }>(sql`SELECT 'establishments' t, count(*)::int n FROM establishments UNION ALL SELECT 'users', count(*)::int FROM users UNION ALL SELECT 'analytics_events', count(*)::int FROM analytics_events UNION ALL SELECT 'subscribers', count(*)::int FROM subscribers`);
  console.log(`\n✓ Jeu de démonstration créé en ${Math.round((Date.now() - started) / 1000)} s`);
  for (const row of counts.rows) console.log(`   ${row.t.padEnd(18)} ${row.n}`);
  console.log(`
Comptes de démonstration (mot de passe : ${DEMO_PASSWORD})
  Super administrateur ....... camille@terricom.fr        (MFA)
  Admin territoriale (CC) .... c.duval@cc-valdeloue.fr    (MFA)
  Chargé de communication .... t.girod@cc-valdeloue.fr    (MFA)
  Admin communale Ornans ..... commerce@ornans.fr         (MFA)
  Admin communal Quingey ..... mairie@quingey.fr
  Professionnelle (boulangère) sophie@boulangerie-martin.fr
Code MFA : secret TOTP ${DEMO_TOTP_SECRET} (à ajouter dans une application d'authentification)
`);
  void inArray;
  await pool.end();
}

main().catch(async (err) => {
  console.error('✗ Échec du seed', err);
  process.exit(1);
});
