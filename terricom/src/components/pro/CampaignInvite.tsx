'use client';

import { useActionState, useState } from 'react';
import { joinCampaign, leaveCampaign, type ActionState } from '@/app/pro/[est]/actions';
import { Photo } from '@/components/ui/Photo';

/** Invitation de la collectivité à rejoindre une campagne (offre affichée dans la campagne). */
export function CampaignInvite({
  estId,
  campaign,
  subscribers,
  image,
}: {
  estId: string;
  campaign: { id: string; name: string; status: 'INVITED' | 'JOINED' | 'DECLINED'; offerLabel: string | null; advent: boolean };
  subscribers: number;
  image: string | null;
}) {
  const [state, action, pending] = useActionState<ActionState, FormData>(joinCampaign, { status: 'idle' });
  const [open, setOpen] = useState(false);
  const joined = campaign.status === 'JOINED' || state.status === 'ok';
  return (
    <div id="campagne" style={{ position: 'relative', borderRadius: 22, overflow: 'hidden', minHeight: 260, color: '#FFF3E6', background: '#5E1F1A' }}>
      <Photo src={image} alt="" color="#5E1F1A" label=" " style={{ position: 'absolute', inset: 0, opacity: 0.4 }} />
      <div style={{ position: 'relative', padding: 24, display: 'flex', flexDirection: 'column', gap: 10, height: '100%' }}>
        <span
          style={{
            alignSelf: 'flex-start',
            background: 'var(--amber)',
            color: 'var(--ink)',
            fontWeight: 800,
            fontSize: 11,
            padding: '4px 9px',
            borderRadius: 6,
            letterSpacing: '0.05em',
          }}
        >
          INVITATION DE LA COLLECTIVITÉ
        </span>
        <div className="display" style={{ fontSize: 28, lineHeight: 1 }}>
          Participez à « {campaign.name} »
        </div>
        <div style={{ fontSize: 14, color: '#F3D5C9' }}>
          Ajoutez une offre : elle apparaîtra {campaign.advent ? "dans le calendrier de l'Avent, " : ''}dans la newsletter (
          {subscribers.toLocaleString('fr-FR')} abonnés) et sur la page campagne.
        </div>
        {open && !joined ? (
          <form action={action} style={{ display: 'flex', flexDirection: 'column', gap: 8, marginTop: 4 }}>
            <input type="hidden" name="estId" value={estId} />
            <input type="hidden" name="campaignId" value={campaign.id} />
            <input name="offerLabel" className="input" placeholder="Votre offre (ex. -10 % sur les galettes)" maxLength={80} required autoFocus />
            <input name="offerDescription" className="input" placeholder="Détail, conditions (facultatif)" maxLength={400} />
            {state.status === 'error' ? <div className="alert alert-error">{state.message}</div> : null}
            <div style={{ display: 'flex', gap: 8 }}>
              <button type="submit" className="btn btn-amber" disabled={pending}>
                {pending ? '…' : 'Publier mon offre'}
              </button>
              <button type="button" className="btn btn-ghost" style={{ color: '#FFF3E6' }} onClick={() => setOpen(false)}>
                Annuler
              </button>
            </div>
          </form>
        ) : (
          <div style={{ marginTop: 'auto', display: 'flex', gap: 10, alignItems: 'center', flexWrap: 'wrap' }}>
            <button
              type="button"
              onClick={() => !joined && setOpen(true)}
              className="btn"
              style={{
                border: 0,
                background: joined ? 'var(--leaf)' : 'var(--amber)',
                color: 'var(--ink)',
                padding: '11px 16px',
                borderRadius: 10,
                fontWeight: 800,
              }}
            >
              {joined ? `✓ Vous participez${campaign.offerLabel ? ` · ${campaign.offerLabel}` : ''}` : 'Participer avec une offre'}
            </button>
            {joined ? (
              <form action={leaveCampaign}>
                <input type="hidden" name="estId" value={estId} />
                <input type="hidden" name="campaignId" value={campaign.id} />
                <button type="submit" className="btn-link" style={{ color: '#F3D5C9', fontSize: 13 }}>
                  Se retirer
                </button>
              </form>
            ) : null}
          </div>
        )}
      </div>
    </div>
  );
}
