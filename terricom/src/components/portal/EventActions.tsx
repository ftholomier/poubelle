'use client';

import { useStoredValue } from '@/lib/hooks/useStoredValue';
import { useToast } from '@/components/ui/Feedback';

/** « Ajouter à mon agenda » (.ics), itinéraire et partage d'un événement. */
export function EventActions({ id, icsHref, directionsUrl, shareUrl, title }: { id: string; icsHref: string; directionsUrl: string | null; shareUrl: string; title: string }) {
  const toast = useToast();
  const key = `agenda:${id}`;
  const [stored, setStored] = useStoredValue(key);
  const saved = stored === '1';
  const share = async () => {
    try {
      if (navigator.share) return await navigator.share({ title, url: shareUrl });
      await navigator.clipboard.writeText(shareUrl);
      toast('Lien de l’événement copié.');
    } catch {
      /* partage annulé */
    }
  };
  return (
    <>
      <a
        href={icsHref}
        download
        onClick={() => setStored('1')}
        className="btn"
        style={{
          justifyContent: 'center',
          background: saved ? 'var(--leaf)' : 'var(--green)',
          color: saved ? 'var(--ink)' : '#fff',
          padding: 14,
          borderRadius: 12,
          fontWeight: 800,
          fontSize: 15,
        }}
      >
        {saved ? '✓ Dans mon agenda' : 'Ajouter à mon agenda'}
      </a>
      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8 }}>
        {directionsUrl ? (
          <a href={directionsUrl} target="_blank" rel="noopener noreferrer" className="btn btn-outline" style={{ justifyContent: 'center', border: '1.5px solid var(--ink)', padding: 11, borderRadius: 12 }}>
            Itinéraire
          </a>
        ) : (
          <span className="btn btn-outline" aria-disabled="true" style={{ justifyContent: 'center', opacity: 0.45 }}>
            Itinéraire
          </span>
        )}
        <button type="button" className="btn" onClick={share} style={{ justifyContent: 'center', border: '1px solid var(--line)', background: '#fff', padding: 11, borderRadius: 12, fontWeight: 700 }}>
          Partager
        </button>
      </div>
    </>
  );
}
