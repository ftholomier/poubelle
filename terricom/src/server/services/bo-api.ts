import { getActor } from '../authz';
import { loadBoContext, type BoContext } from './backoffice';

/** Contexte du back-office pour les routes de téléchargement (401 sans session). */
export async function boApiContext(): Promise<BoContext | null> {
  const actor = await getActor();
  if (!actor || (actor.user.mfaEnabled && !actor.session.mfaVerified)) return null;
  return loadBoContext();
}
