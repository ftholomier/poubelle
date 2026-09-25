import { desc, eq, inArray } from 'drizzle-orm';
import { NextResponse } from 'next/server';
import { audit } from '@/server/audit';
import { getSession } from '@/server/auth/session';
import { db } from '@/server/db';
import { auditLog, claims, companies, companyMembers, establishments, privacyRequests, roleAssignments, sessions } from '@/server/db/schema';

/** Export des données personnelles du compte connecté (droit d'accès et de portabilité, RGPD art. 15 et 20). */
export async function GET() {
  const s = await getSession();
  if (!s || (s.user.mfaEnabled && !s.session.mfaVerified)) return new NextResponse('Non autorisé', { status: 401 });
  const u = s.user;
  const [roles, members, sess, myClaims, actions] = await Promise.all([
    db
      .select({
        role: roleAssignments.role,
        territoryId: roleAssignments.territoryId,
        communeId: roleAssignments.communeId,
        createdAt: roleAssignments.createdAt,
      })
      .from(roleAssignments)
      .where(eq(roleAssignments.userId, u.id)),
    db
      .select({ companyId: companyMembers.companyId, role: companyMembers.role, since: companyMembers.createdAt })
      .from(companyMembers)
      .where(eq(companyMembers.userId, u.id)),
    db
      .select({ createdAt: sessions.createdAt, lastSeenAt: sessions.lastSeenAt, ip: sessions.ip, userAgent: sessions.userAgent })
      .from(sessions)
      .where(eq(sessions.userId, u.id)),
    db
      .select({
        establishmentId: claims.establishmentId,
        status: claims.status,
        method: claims.method,
        createdAt: claims.createdAt,
        reviewedAt: claims.reviewedAt,
      })
      .from(claims)
      .where(eq(claims.userId, u.id)),
    db
      .select({ at: auditLog.occurredAt, action: auditLog.action, summary: auditLog.summary })
      .from(auditLog)
      .where(eq(auditLog.actorUserId, u.id))
      .orderBy(desc(auditLog.occurredAt))
      .limit(500),
  ]);
  const companyIds = members.map((m) => m.companyId);
  const [cos, ests] = companyIds.length
    ? await Promise.all([
        db
          .select({ id: companies.id, name: companies.tradeName, legalName: companies.legalName, siren: companies.siren, plan: companies.plan })
          .from(companies)
          .where(inArray(companies.id, companyIds)),
        db
          .select({ id: establishments.id, companyId: establishments.companyId, name: establishments.name })
          .from(establishments)
          .where(inArray(establishments.companyId, companyIds)),
      ])
    : [[], []];
  const payload = {
    exportedAt: new Date().toISOString(),
    notice: 'Données personnelles associées à votre compte terricom. Les contenus publics de vos fiches restent consultables sur le portail.',
    account: {
      email: u.email,
      firstName: u.firstName,
      lastName: u.lastName,
      phone: u.phone,
      jobTitle: u.jobTitle,
      createdAt: u.createdAt,
      lastLoginAt: u.lastLoginAt,
      emailVerifiedAt: u.emailVerifiedAt,
      mfaEnabled: u.mfaEnabled,
    },
    roles,
    companies: cos.map((c) => ({
      ...c,
      membership: members.find((m) => m.companyId === c.id),
      establishments: ests.filter((e) => e.companyId === c.id).map((e) => ({ id: e.id, name: e.name })),
    })),
    claims: myClaims,
    sessions: sess,
    activity: actions,
  };
  await db
    .insert(privacyRequests)
    .values({ email: u.email, kind: 'EXPORT', status: 'DONE', note: 'Export en libre-service depuis le compte', completedAt: new Date() });
  await audit({
    actor: { user: u },
    category: 'RGPD',
    action: 'user.exported',
    summary: 'Export de ses données personnelles',
    targetType: 'user',
    targetId: u.id,
  });
  return new NextResponse(JSON.stringify(payload, null, 2), {
    headers: {
      'content-type': 'application/json; charset=utf-8',
      'content-disposition': `attachment; filename="terricom-mes-donnees-${new Date().toISOString().slice(0, 10)}.json"`,
      'cache-control': 'private, no-store',
    },
  });
}
