'use server';

import { redirect } from 'next/navigation';
import { audit } from '@/server/audit';
import { stopImpersonation } from '@/server/auth/session';
import { requirePlatformStaff } from '@/server/authz';

export async function stopImpersonationAction(): Promise<void> {
  const actor = await requirePlatformStaff();
  if (actor.impersonation) {
    await stopImpersonation();
    await audit({
      actor: { user: actor.user },
      category: 'SECURITE',
      action: 'support.impersonation_end',
      summary: 'Fin de l’accès support temporaire',
      territoryId: actor.impersonation.territoryId,
    });
  }
  redirect('/console/territoires');
}
