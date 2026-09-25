'use client';

import { useRouter } from 'next/navigation';
import { useTransition } from 'react';
import { demoToggleStamp, startPassport } from '@/app/[territory]/actions';
import { useToast } from '@/components/ui/Feedback';

type Stop = { id: string; name: string };

const ROT = [-8, 5, -3, 7, -6, 4, -2, 6, -5];

/** Passeport mobile d'un circuit : progression, tampons et récompense. */
export function PassportPhone({
  circuitId,
  name,
  reward,
  threshold,
  stops,
  stamped,
  hasPassport,
  rewardCode,
  demo,
}: {
  circuitId: string;
  name: string;
  reward: string | null;
  threshold: number;
  stops: Stop[];
  stamped: string[];
  hasPassport: boolean;
  rewardCode: string | null;
  demo: boolean;
}) {
  const router = useRouter();
  const toast = useToast();
  const [pending, start] = useTransition();
  const done = stops.filter((s) => stamped.includes(s.id)).length;
  const pct = stops.length ? Math.round((done / stops.length) * 100) : 0;

  const open = () =>
    start(async () => {
      const res = await startPassport(circuitId);
      if (res.ok) {
        toast('Passeport ouvert : scannez le QR code en vitrine à chaque étape.');
        router.refresh();
      }
    });
  const stamp = (id: string) =>
    start(async () => {
      const res = await demoToggleStamp(id);
      if (res.ok) router.refresh();
    });

  return (
    <div className="phone-mock">
      <div
        style={{
          width: '100%',
          height: '100%',
          borderRadius: 36,
          background: 'var(--cream)',
          overflow: 'hidden',
          display: 'flex',
          flexDirection: 'column',
          position: 'relative',
        }}
      >
        <div
          style={{
            position: 'absolute',
            top: 10,
            left: '50%',
            transform: 'translateX(-50%)',
            width: 96,
            height: 26,
            borderRadius: 20,
            background: 'var(--ink)',
          }}
        />
        <div style={{ background: 'var(--brand)', color: '#fff', padding: '48px 20px 20px' }}>
          <div style={{ fontSize: 11, fontWeight: 800, letterSpacing: '0.08em', color: 'var(--amber)' }}>MON PASSEPORT</div>
          <div className="display" style={{ fontSize: 24, lineHeight: 1.05, marginTop: 4 }}>
            {name}
          </div>
          <div style={{ marginTop: 12, height: 8, background: 'rgba(255,255,255,.2)', borderRadius: 4, overflow: 'hidden' }}>
            <div style={{ height: '100%', width: `${pct}%`, background: 'var(--amber)', borderRadius: 4, transition: 'width .3s' }} />
          </div>
          <div style={{ fontSize: 12, marginTop: 6, color: 'var(--mint-3)' }}>
            {done} / {stops.length} tampons
          </div>
        </div>
        <div style={{ padding: 18, display: 'grid', gridTemplateColumns: 'repeat(3,1fr)', gap: 12 }}>
          {stops.map((s, i) => {
            const on = stamped.includes(s.id);
            const label = on ? s.name.split(' ').slice(-1)[0] : String(i + 1);
            const style = {
              aspectRatio: '1',
              borderRadius: '50%',
              display: 'grid',
              placeItems: 'center',
              textAlign: 'center' as const,
              border: `2.5px ${on ? 'solid var(--green)' : 'dashed #B9B3A5'}`,
              color: on ? 'var(--green)' : 'var(--faint)',
              background: on ? 'var(--mint)' : 'transparent',
              transform: `rotate(${ROT[i % ROT.length]}deg)`,
              fontFamily: 'var(--font-display)',
              fontWeight: 800,
              fontSize: 11,
              lineHeight: 1.05,
              padding: 6,
              overflow: 'hidden',
            };
            return demo ? (
              <button
                key={s.id}
                type="button"
                onClick={() => stamp(s.id)}
                disabled={pending}
                title={`Démo : simuler le scan de l'étape ${i + 1}`}
                aria-label={`${on ? 'Retirer' : 'Tamponner'} l'étape ${i + 1} : ${s.name}`}
                style={{ ...style, cursor: 'pointer', font: 'inherit', fontFamily: 'var(--font-display)', fontWeight: 800, fontSize: 11 }}
              >
                {label}
              </button>
            ) : (
              <div key={s.id} style={style} aria-label={`Étape ${i + 1} : ${on ? 'tamponnée' : 'à visiter'}`}>
                {label}
              </div>
            );
          })}
        </div>
        <div style={{ margin: 'auto 16px 18px', display: 'flex', flexDirection: 'column', gap: 8 }}>
          {!hasPassport ? (
            <button type="button" className="btn btn-dark" onClick={open} disabled={pending} style={{ justifyContent: 'center' }}>
              {pending ? 'Ouverture…' : 'Ouvrir mon passeport'}
            </button>
          ) : null}
          <div style={{ background: 'var(--amber)', borderRadius: 16, padding: 14, display: 'flex', flexDirection: 'column', gap: 4 }}>
            {rewardCode ? (
              <>
                <div style={{ fontWeight: 800, fontSize: 14, color: 'var(--ink)' }}>Bravo, circuit réussi !</div>
                <div style={{ fontSize: 12, color: 'var(--amber-fg-2)' }}>
                  Votre code :{' '}
                  <b className="mono" style={{ fontSize: 14, color: 'var(--ink)' }}>
                    {rewardCode}
                  </b>{' '}
                  — présentez-le à l&apos;office de tourisme.
                </div>
              </>
            ) : (
              <>
                <div style={{ fontWeight: 800, fontSize: 14, color: 'var(--ink)' }}>{reward ?? `${threshold} tampons = une surprise`}</div>
                <div style={{ fontSize: 12, color: 'var(--amber-fg-2)' }}>
                  {demo ? 'Démo : touchez un tampon pour simuler un scan.' : 'Scannez le QR code en vitrine à chaque étape.'}
                </div>
              </>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
