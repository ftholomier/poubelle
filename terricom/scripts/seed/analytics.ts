/**
 * Simulation réaliste des statistiques d'audience depuis le lancement du pilote,
 * générée en SQL (plusieurs centaines de milliers d'événements en quelques secondes).
 */
import type { SQL } from 'drizzle-orm';
import { pool } from '@/server/db';
import { rng } from './random';

type Ctx = { db: unknown; sql: unknown; territoryId: string; b1: string; launch: string; now: Date };

const MONTHLY_VISITORS: Record<string, number> = {
  '2026-03': 3100,
  '2026-04': 5400,
  '2026-05': 6200,
  '2026-06': 7800,
  '2026-07': 9100,
  '2026-08': 8400,
  '2026-09': 8900,
  '2026-10': 10200,
  '2026-11': 11800,
  '2026-12': 16400,
};

export async function seedAnalytics(ctx: Ctx): Promise<void> {
  const r = rng(7);
  const days: string[] = [];
  const counts: number[] = [];
  for (let d = new Date(`${ctx.launch}T12:00:00Z`); d <= ctx.now; d.setUTCDate(d.getUTCDate() + 1)) {
    const iso = d.toISOString().slice(0, 10);
    const month = MONTHLY_VISITORS[iso.slice(0, 7)] ?? 9000;
    const dim = new Date(Date.UTC(d.getUTCFullYear(), d.getUTCMonth() + 1, 0)).getUTCDate();
    const wd = (d.getUTCDay() + 6) % 7;
    const factor = [0.85, 0.9, 0.95, 0.95, 1.05, 1.35, 1.0][wd];
    days.push(iso);
    counts.push(Math.round((month / dim) * factor * (0.9 + r.next() * 0.2)));
  }

  const client = await pool.connect();
  try {
    await client.query('BEGIN');
    // Poids de visite de chaque fiche (hors Boulangerie Martin, simulée précisément plus bas)
    await client.query(
      `CREATE TEMP TABLE w ON COMMIT DROP AS
       WITH base AS (
         SELECT e.id AS est_id, e.commune_id,
           (CASE WHEN e.is_featured THEN 5 ELSE 1 END)
           * (CASE co.plan WHEN 'ESSENTIEL' THEN 1 ELSE 2.2 END)
           * (CASE e.status WHEN 'VALIDATED' THEN 3 WHEN 'CLAIMED' THEN 2 ELSE 0.6 END)
           * (0.5 + random()) AS wt
         FROM establishments e JOIN companies co ON co.id = e.company_id
         WHERE e.territory_id = $1 AND e.id <> $2 AND e.status IN ('PRECREATED','TO_COMPLETE','CLAIMED','VALIDATED')
       )
       SELECT est_id, commune_id, sum(wt) OVER (ORDER BY est_id) / sum(wt) OVER () AS hi FROM base`,
      [ctx.territoryId, ctx.b1],
    );
    await client.query('CREATE INDEX ON w (hi)');
    await client.query(
      `CREATE TEMP TABLE v ON COMMIT DROP AS
       SELECT d.day, gs AS n, left(md5(d.day::text || '-' || gs::text || random()::text), 32) AS vh,
              (ARRAY['mobile','mobile','mobile','desktop','desktop','tablet'])[1 + floor(random()*6)::int] AS dev
       FROM unnest($1::date[], $2::int[]) AS d(day, cnt), generate_series(1, d.cnt) gs`,
      [days, counts],
    );
    // Pages vues du portail
    await client.query(
      `INSERT INTO analytics_events (occurred_at, territory_id, type, source, visitor_hash, device, path)
       SELECT v.day + make_interval(mins => (420 + floor(random() * 900))::int), $1, 'PAGE_VIEW',
         (ARRAY['DIRECT','GOOGLE','GOOGLE','GOOGLE','SOCIAL','NEWSLETTER','OTHER'])[1 + floor(random()*7)::int]::traffic_source,
         v.vh, v.dev,
         (ARRAY['/valdeloue','/valdeloue','/valdeloue/explorer','/valdeloue/agenda','/valdeloue/circuits','/valdeloue/emploi','/valdeloue/ornans'])[1 + floor(random()*7)::int]
       FROM v`,
      [ctx.territoryId],
    );
    // Consultations de fiches (1,7 en moyenne par visiteur)
    await client.query(
      `INSERT INTO analytics_events (occurred_at, territory_id, commune_id, establishment_id, type, source, visitor_hash, device)
       SELECT x.ts, $1, w.commune_id, w.est_id, 'EST_VIEW', x.src, x.vh, x.dev
       FROM (
         SELECT v.day + make_interval(mins => (420 + floor(random() * 900))::int) AS ts, random() AS rr, v.vh, v.dev,
           (ARRAY['PLATFORM_SEARCH','PLATFORM_SEARCH','PLATFORM_SEARCH','GOOGLE','GOOGLE','MAP','MAP','NEWSLETTER','QR','SOCIAL'])[1 + floor(random()*10)::int]::traffic_source AS src
         FROM v, generate_series(1, 3) k WHERE random() < 0.57
       ) x
       CROSS JOIN LATERAL (SELECT est_id, commune_id FROM w WHERE w.hi >= x.rr ORDER BY w.hi LIMIT 1) w`,
      [ctx.territoryId],
    );
    // Clics d'appel, d'itinéraire, de site web, scans QR
    for (const [type, ratio] of [
      ['PHONE_CLICK', 0.075],
      ['DIRECTIONS_CLICK', 0.105],
      ['WEBSITE_CLICK', 0.035],
      ['QR_SCAN', 0.03],
      ['SHARE_CLICK', 0.01],
    ] as const) {
      await client.query(
        `INSERT INTO analytics_events (occurred_at, territory_id, commune_id, establishment_id, type, source, visitor_hash, device)
         SELECT x.ts, $1, w.commune_id, w.est_id, $2::analytics_type, ${type === 'QR_SCAN' ? `'QR'` : 'NULL'}, x.vh, x.dev
         FROM (SELECT v.day + make_interval(mins => (420 + floor(random() * 900))::int) AS ts, random() AS rr, v.vh, v.dev FROM v WHERE random() < $3) x
         CROSS JOIN LATERAL (SELECT est_id, commune_id FROM w WHERE w.hi >= x.rr ORDER BY w.hi LIMIT 1) w`,
        [ctx.territoryId, type, ratio],
      );
    }
    // Recherches des habitants : requêtes fréquentes + « signal faible » (cordonnier, aucun résultat)
    const top: [string, number, number][] = [
      ['boulangerie ouverte dimanche', 1204, 9],
      ['restaurant ce soir', 982, 14],
      ['plombier urgence', 644, 6],
      ['produits locaux noël', 611, 31],
      ['marché ornans', 530, 3],
      ['garage', 402, 12],
      ['cordonnier', 212, 0],
      ['coiffeur ornans', 154, 7],
      ['miel', 138, 3],
      ['comté', 187, 5],
      ['pizza', 176, 4],
      ['électricien', 165, 9],
      ['fleuriste', 142, 3],
      ['cadeau fait main', 131, 18],
      ['chambre d’hôtes', 118, 11],
      ['traiteur mariage', 74, 2],
    ];
    for (const [q, n, results] of top) {
      await client.query(
        `INSERT INTO analytics_events (occurred_at, territory_id, type, source, query, result_count, visitor_hash, device)
         SELECT (SELECT day FROM v ORDER BY random() LIMIT 1) + make_interval(mins => (420 + floor(random() * 900))::int),
           $1, 'SEARCH', 'PLATFORM_SEARCH', $2, $3, left(md5(random()::text), 32), 'mobile'
         FROM generate_series(1, $4)`,
        [ctx.territoryId, q, results, n],
      );
    }
    await client.query(
      `INSERT INTO analytics_events (occurred_at, territory_id, type, source, query, result_count, visitor_hash, device)
       SELECT v.day + make_interval(mins => (420 + floor(random() * 900))::int), $1, 'SEARCH', 'PLATFORM_SEARCH',
         (ARRAY['boulangerie','fromagerie','vin jura','ouvert maintenant','restaurant ornans','artisan','bio','menuisier','chocolat','épicerie vrac','absinthe','garage quingey'])[1 + floor(random()*12)::int],
         1 + floor(random()*25)::int, v.vh, v.dev
       FROM v WHERE random() < 0.03`,
      [ctx.territoryId],
    );

    // ─── Boulangerie Martin : séries précises de la maquette (E5) ──────────
    const V = [22, 25, 21, 30, 28, 34, 33, 29, 36, 41, 38, 35, 44, 52, 47, 43, 40, 48, 51, 46, 62, 88, 70, 58, 54, 60, 57, 63, 66, 71];
    const scale = 1284 / V.reduce((a, b) => a + b, 0);
    const cur = V.map((x) => Math.round(x * scale));
    cur[cur.length - 1] += 1284 - cur.reduce((a, b) => a + b, 0);
    const prev = V.map((x, i) => Math.round(x * scale * 0.82 + (i % 5) * 1.2));
    const hourW: [number, number][] = [
      [7, 6], [8, 9], [9, 12], [10, 14], [11, 12], [12, 9], [13, 5], [14, 5], [15, 6], [16, 7], [17, 9], [18, 10], [19, 5], [20, 3], [21, 2],
    ];
    const srcW: [string, number][] = [['PLATFORM_SEARCH', 38], ['GOOGLE', 27], ['MAP', 16], ['NEWSLETTER', 12], ['QR', 7]];
    const queries: [string, number][] = [
      ['pain levain ornans', 84],
      ['boulangerie ouverte dimanche', 61],
      ['galette comtoise', 47],
      ['croissant ornans', 39],
      ['boulangerie click and collect', 22],
      ['pain sans gluten', 14],
    ];
    const rows: [string, string, string | null, string | null, string, string][] = [];
    const queryPool = queries.flatMap(([q, n]) => Array.from({ length: n }, () => q));
    const dayStart = (offset: number) => {
      const d = new Date(ctx.now.getTime() - offset * 86_400_000);
      return d.toISOString().slice(0, 10);
    };
    const tsFor = (iso: string) => {
      const wd = (new Date(`${iso}T12:00:00Z`).getUTCDay() + 6) % 7;
      let h = r.weighted(hourW);
      if (wd === 5 && r.chance(0.35)) h = 10; // le samedi à 10h, c'est l'heure de gloire
      if (wd === 0 && r.chance(0.7)) h = r.int(15, 18); // lundi : fermé le matin, peu de visites
      return `${iso}T${String(h - 2).padStart(2, '0')}:${String(r.int(0, 59)).padStart(2, '0')}:00Z`;
    };
    const push = (type: string, offset: number, src: string | null, q: string | null) =>
      rows.push([tsFor(dayStart(offset)), type, src, q, r.chance(0.7) ? 'mobile' : 'desktop', `${r.next()}`.slice(2, 34).padEnd(32, '0')]);
    cur.forEach((n, i) => {
      for (let k = 0; k < n; k++) {
        const src = r.weighted(srcW);
        push('EST_VIEW', 29 - i, src, src === 'PLATFORM_SEARCH' && queryPool.length && r.chance(0.6) ? queryPool.splice(r.int(0, queryPool.length - 1), 1)[0] : null);
      }
    });
    prev.forEach((n, i) => {
      for (let k = 0; k < n; k++) push('EST_VIEW', 59 - i, r.weighted(srcW), null);
    });
    for (const [type, c, p] of [
      ['PHONE_CLICK', 96, 86],
      ['DIRECTIONS_CLICK', 214, 196],
      ['QR_SCAN', 142, 101],
      ['WEBSITE_CLICK', 31, 27],
    ] as const) {
      for (let k = 0; k < c; k++) push(type, r.int(0, 29), type === 'QR_SCAN' ? 'QR' : null, null);
      for (let k = 0; k < p; k++) push(type, r.int(30, 59), type === 'QR_SCAN' ? 'QR' : null, null);
    }
    for (let i = 0; i < rows.length; i += 1000) {
      const chunk = rows.slice(i, i + 1000);
      const values: string[] = [];
      const params: unknown[] = [ctx.territoryId, ctx.b1];
      chunk.forEach(([ts, type, src, q, dev, vh], j) => {
        const b = 3 + j * 6;
        values.push(`($${b}::timestamptz, $1, (SELECT commune_id FROM establishments WHERE id = $2), $2, $${b + 1}::analytics_type, $${b + 2}::traffic_source, $${b + 3}, $${b + 4}, $${b + 5})`);
        params.push(ts, type, src, q, dev, vh);
      });
      await client.query(
        `INSERT INTO analytics_events (occurred_at, territory_id, commune_id, establishment_id, type, source, query, device, visitor_hash) VALUES ${values.join(',')}`,
        params,
      );
    }
    // Messages de contact des 60 derniers jours (23 ce mois-ci, 19 le mois précédent)
    const names = ['Claire B.', 'Hugo L.', 'Nadia K.', 'Paul R.', 'Émilie T.', 'Jean V.', 'Sarah M.', 'Louis G.'];
    const bodies = [
      'Bonjour, faites-vous des gâteaux d’anniversaire sur commande ?',
      'Est-il possible de réserver 3 baguettes tradition pour dimanche ?',
      'Merci pour l’accueil samedi, les croissants étaient parfaits !',
      'Livrez-vous les entreprises pour des petits-déjeuners ?',
      'Quels sont vos horaires le jour de l’Ascension ?',
    ];
    for (const [n, from] of [[20, 3], [19, 30]] as const) {
      for (let k = 0; k < n; k++) {
        const created = new Date(ctx.now.getTime() - (from + r.int(0, 26)) * 86_400_000);
        await client.query(
          `INSERT INTO messages (establishment_id, territory_id, sender_name, sender_email, body, status, created_at, read_at)
           VALUES ($1, $2, $3, $4, $5, $6::inbox_status, $7, $7)`,
          [ctx.b1, ctx.territoryId, r.pick(names), `client${r.int(1, 999)}@exemple.test`, r.pick(bodies), r.chance(0.6) ? 'REPLIED' : 'READ', created],
        );
      }
    }
    await client.query(
      `INSERT INTO analytics_events (occurred_at, territory_id, establishment_id, type, visitor_hash)
       SELECT created_at, territory_id, establishment_id, 'CONTACT_SENT', left(md5(id::text), 32) FROM messages WHERE source = 'PORTAL'`,
    );
    await client.query('COMMIT');
  } catch (err) {
    await client.query('ROLLBACK');
    throw err;
  } finally {
    client.release();
  }
}

export type { SQL };
