'use client';

import { useActionState, useEffect, useState, type CSSProperties } from 'react';
import { requestAppointment, sendContactMessage, type FormState } from '@/app/[territory]/actions';
import { Modal, useToast } from '@/components/ui/Feedback';
import { Icon } from '@/components/ui/Icon';
import { Photo } from '@/components/ui/Photo';
import { sendBeacon } from './Beacon';

type GalleryPhoto = { src: string; large: string; alt: string };

/** Galerie de la fiche (1 grande + 4 vignettes) avec visionneuse plein écran. */
export function FicheGallery({ photos, stamp, color, name }: { photos: GalleryPhoto[]; stamp?: string | null; color: string; name: string }) {
  const [index, setIndex] = useState<number | null>(null);
  const shown = photos.slice(0, 5);
  const extra = photos.length - shown.length;

  useEffect(() => {
    if (index === null) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'ArrowRight') setIndex((i) => (i === null ? i : (i + 1) % photos.length));
      if (e.key === 'ArrowLeft') setIndex((i) => (i === null ? i : (i - 1 + photos.length) % photos.length));
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [index, photos.length]);

  if (!photos.length) {
    return (
      <div
        className="img-fallback"
        style={{
          height: 260,
          borderRadius: 20,
          fontSize: 88,
          background: `linear-gradient(135deg, ${color}, color-mix(in srgb, ${color} 65%, #14201B))`,
        }}
        aria-hidden="true"
      >
        {name.charAt(0)}
      </div>
    );
  }

  const layout: CSSProperties =
    shown.length >= 5
      ? {}
      : shown.length === 1
        ? { gridTemplateColumns: '1fr', gridTemplateRows: '428px' }
        : shown.length === 2
          ? { gridTemplateColumns: '2fr 1fr', gridTemplateRows: '428px' }
          : { gridTemplateColumns: '2fr 1fr', gridTemplateRows: '210px 210px' };
  const radius = (i: number): string => {
    if (shown.length >= 5) return ['20px 0 0 20px', '0', '0 20px 0 0', '0', '0 0 20px 0'][i];
    if (shown.length === 1) return '20px';
    if (shown.length === 2) return i === 0 ? '20px 0 0 20px' : '0 20px 20px 0';
    return i === 0 ? '20px 0 0 20px' : i === 1 ? '0 20px 0 0' : '0 0 20px 0';
  };

  return (
    <>
      <div className="gallery" style={layout}>
        {shown.map((p, i) => (
          <button
            key={p.src + i}
            type="button"
            onClick={() => setIndex(i)}
            aria-label={`Agrandir la photo ${i + 1} sur ${photos.length}`}
            style={{
              position: 'relative',
              border: 0,
              padding: 0,
              overflow: 'hidden',
              borderRadius: radius(i),
              background: 'var(--sand)',
              cursor: 'zoom-in',
              gridRow: i === 0 && shown.length !== 1 && shown.length !== 2 ? 'span 2' : undefined,
            }}
          >
            <Photo src={i === 0 ? p.large : p.src} alt={p.alt} color={color} label={name} eager={i === 0} />
            {i === 0 && stamp ? (
              <span
                style={{
                  position: 'absolute',
                  left: 18,
                  bottom: 18,
                  background: 'var(--amber)',
                  color: 'var(--ink)',
                  fontFamily: 'var(--font-display)',
                  fontWeight: 800,
                  fontSize: 14,
                  padding: '8px 12px',
                  borderRadius: 10,
                  transform: 'rotate(-4deg)',
                  boxShadow: '0 6px 16px rgba(0,0,0,.25)',
                }}
              >
                {stamp}
              </span>
            ) : null}
            {i === shown.length - 1 && extra > 0 ? (
              <span
                style={{
                  position: 'absolute',
                  inset: 0,
                  background: 'rgba(20,32,27,.55)',
                  display: 'grid',
                  placeItems: 'center',
                  color: '#fff',
                  fontWeight: 700,
                }}
              >
                + {extra} photo{extra > 1 ? 's' : ''}
              </span>
            ) : null}
          </button>
        ))}
      </div>
      <Modal open={index !== null} onClose={() => setIndex(null)} label={`Photos de ${name}`} width="min(1100px, 100%)" height="min(820px, 100%)">
        {index !== null ? (
          <div style={{ position: 'relative', flex: 1, background: 'var(--ink)', display: 'grid', placeItems: 'center', minHeight: 0 }}>
            <img src={photos[index].large} alt={photos[index].alt} style={{ maxWidth: '100%', maxHeight: '100%', objectFit: 'contain', display: 'block' }} />
            <div style={{ position: 'absolute', top: 14, right: 14, display: 'flex', gap: 8 }}>
              <span className="pill" style={{ background: 'rgba(255,255,255,.9)', color: 'var(--ink)' }}>
                {index + 1} / {photos.length}
              </span>
              <button type="button" className="btn btn-light btn-sm" onClick={() => setIndex(null)} aria-label="Fermer">
                <Icon name="x" size={16} />
              </button>
            </div>
            {photos.length > 1 ? (
              <>
                <button
                  type="button"
                  className="btn btn-light btn-sm"
                  aria-label="Photo précédente"
                  onClick={() => setIndex((index - 1 + photos.length) % photos.length)}
                  style={{ position: 'absolute', left: 14, top: '50%', transform: 'translateY(-50%)' }}
                >
                  <Icon name="arrowLeft" size={16} />
                </button>
                <button
                  type="button"
                  className="btn btn-light btn-sm"
                  aria-label="Photo suivante"
                  onClick={() => setIndex((index + 1) % photos.length)}
                  style={{ position: 'absolute', right: 14, top: '50%', transform: 'translateY(-50%)' }}
                >
                  <Icon name="arrowRight" size={16} />
                </button>
              </>
            ) : null}
          </div>
        ) : null}
      </Modal>
    </>
  );
}

/** Boutons d'action de la fiche, chacun mesuré (appel, itinéraire, site, partage). */
export function FicheActions({
  establishmentId,
  territoryId,
  phone,
  directionsUrl,
  website,
  shareUrl,
  name,
}: {
  establishmentId: string;
  territoryId: string;
  phone: string | null;
  directionsUrl: string | null;
  website: string | null;
  shareUrl: string;
  name: string;
}) {
  const toast = useToast();
  const track = (type: string) => sendBeacon({ type, establishmentId, territoryId });
  const share = async () => {
    track('SHARE_CLICK');
    try {
      if (navigator.share) {
        await navigator.share({ title: name, url: shareUrl });
        return;
      }
      await navigator.clipboard.writeText(shareUrl);
      toast('Lien copié : collez-le où vous voulez.');
    } catch {
      /* partage annulé */
    }
  };
  const primary: CSSProperties = { padding: 13, borderRadius: 12, fontSize: 14, justifyContent: 'center' };
  const secondary: CSSProperties = {
    border: '1px solid var(--line)',
    background: '#fff',
    padding: 11,
    borderRadius: 12,
    fontWeight: 600,
    fontSize: 13,
    color: 'var(--text)',
    justifyContent: 'center',
  };
  return (
    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8 }}>
      {phone ? (
        <a href={`tel:${phone.replace(/[^+\d]/g, '')}`} className="btn btn-brand" style={primary} onClick={() => track('PHONE_CLICK')}>
          Appeler
        </a>
      ) : (
        <span className="btn btn-brand" style={{ ...primary, opacity: 0.45 }} aria-disabled="true">
          Appeler
        </span>
      )}
      {directionsUrl ? (
        <a
          href={directionsUrl}
          target="_blank"
          rel="noopener noreferrer"
          className="btn btn-outline"
          style={{ ...primary, border: '1.5px solid var(--ink)' }}
          onClick={() => track('DIRECTIONS_CLICK')}
        >
          Itinéraire
        </a>
      ) : (
        <span className="btn btn-outline" style={{ ...primary, opacity: 0.45 }} aria-disabled="true">
          Itinéraire
        </span>
      )}
      {website ? (
        <a href={website} target="_blank" rel="noopener noreferrer" className="btn" style={secondary} onClick={() => track('WEBSITE_CLICK')}>
          Site web
        </a>
      ) : (
        <span className="btn" style={{ ...secondary, color: 'var(--faint)' }} aria-disabled="true">
          Site web
        </span>
      )}
      <button type="button" className="btn" style={secondary} onClick={share}>
        Partager
      </button>
    </div>
  );
}

/** « Envoyer un message » : un champ, puis les coordonnées une fois la saisie commencée. */
export function ContactCard({ establishmentId, name }: { establishmentId: string; name: string }) {
  const [state, action, pending] = useActionState<FormState, FormData>(sendContactMessage, { status: 'idle' });
  const [body, setBody] = useState('');
  const expanded = body.trim().length > 0;
  if (state.status === 'ok') {
    return (
      <div className="card" style={{ borderRadius: 18, padding: 20, display: 'flex', flexDirection: 'column', gap: 10 }} role="status">
        <span className="stamp" style={{ alignSelf: 'flex-start' }}>
          Message envoyé !
        </span>
        <div style={{ fontSize: 14, lineHeight: 1.5 }}>{state.message ?? `${name} vous répondra directement.`}</div>
      </div>
    );
  }
  return (
    <form action={action} className="card" style={{ borderRadius: 18, padding: 20, display: 'flex', flexDirection: 'column', gap: 10 }}>
      <div style={{ fontWeight: 700 }}>Envoyer un message</div>
      <input type="hidden" name="establishmentId" value={establishmentId} />
      <input type="text" name="website" tabIndex={-1} autoComplete="off" className="sr-only" aria-hidden="true" />
      <label htmlFor="contact-body" className="sr-only">
        Votre message
      </label>
      {expanded ? (
        <textarea
          id="contact-body"
          name="body"
          rows={3}
          className="textarea"
          value={body}
          onChange={(e) => setBody(e.target.value)}
          maxLength={3000}
          required
          autoFocus
        />
      ) : (
        <input
          id="contact-body"
          name="body"
          className="input"
          placeholder="Votre question, une commande…"
          value={body}
          onChange={(e) => setBody(e.target.value)}
        />
      )}
      {expanded ? (
        <>
          <input name="name" className="input" placeholder="Votre nom" autoComplete="name" required maxLength={120} />
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8 }}>
            <input name="email" type="email" className="input" placeholder="Email" autoComplete="email" maxLength={254} />
            <input name="phone" type="tel" className="input" placeholder="Téléphone" autoComplete="tel" maxLength={32} />
          </div>
          <label className="checkbox" style={{ fontSize: 12, color: 'var(--muted)' }}>
            <input type="checkbox" name="consent" required />
            <span>J&apos;accepte que mes coordonnées soient transmises à {name} pour me répondre.</span>
          </label>
        </>
      ) : null}
      {state.status === 'error' ? (
        <div className="alert alert-error" role="alert">
          {state.message}
        </div>
      ) : null}
      <button type="submit" className="btn btn-brand" disabled={pending || !expanded} style={{ padding: 11, borderRadius: 10, justifyContent: 'center' }}>
        {pending ? 'Envoi…' : 'Envoyer'}
      </button>
    </form>
  );
}

/** Demande de rendez-vous (module Prise de rendez-vous). */
export function AppointmentCard({
  establishmentId,
  info,
  services,
  minDate,
}: {
  establishmentId: string;
  info: string | null;
  services: string[];
  minDate: string;
}) {
  const [state, action, pending] = useActionState<FormState, FormData>(requestAppointment, { status: 'idle' });
  const [open, setOpen] = useState(false);
  if (state.status === 'ok') {
    return (
      <div className="card" style={{ borderRadius: 20, padding: 20 }} role="status">
        <span className="stamp">Demande envoyée !</span>
        <p style={{ fontSize: 14, margin: '10px 0 0' }}>{state.message}</p>
      </div>
    );
  }
  return (
    <div className="card" style={{ borderRadius: 20, padding: 20, display: 'flex', flexDirection: 'column', gap: 10 }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 8 }}>
        <b style={{ fontSize: 16 }}>Prendre rendez-vous</b>
        <Icon name="calendar" size={18} />
      </div>
      {info ? <div style={{ fontSize: 13, color: 'var(--muted)' }}>{info}</div> : null}
      {!open ? (
        <button type="button" className="btn btn-dark" onClick={() => setOpen(true)} style={{ justifyContent: 'center' }}>
          Choisir un créneau
        </button>
      ) : (
        <form action={action} style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
          <input type="hidden" name="establishmentId" value={establishmentId} />
          <input type="text" name="website" tabIndex={-1} autoComplete="off" className="sr-only" aria-hidden="true" />
          {services.length ? (
            <select name="service" className="select" defaultValue="">
              <option value="">Motif du rendez-vous</option>
              {services.map((s) => (
                <option key={s}>{s}</option>
              ))}
            </select>
          ) : (
            <input name="service" className="input" placeholder="Motif (ex. révision, devis…)" maxLength={200} />
          )}
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8 }}>
            <input name="date" type="date" className="input" min={minDate} required aria-label="Date souhaitée" />
            <input name="time" type="time" className="input" min="07:00" max="20:00" step={900} required aria-label="Heure souhaitée" />
          </div>
          <input name="fullName" className="input" placeholder="Prénom et nom" autoComplete="name" required />
          <input name="email" type="email" className="input" placeholder="Email" autoComplete="email" required />
          <input name="phone" type="tel" className="input" placeholder="Téléphone (facultatif)" autoComplete="tel" />
          <label className="checkbox" style={{ fontSize: 12, color: 'var(--muted)' }}>
            <input type="checkbox" name="consent" required />
            <span>J&apos;accepte que ces informations soient transmises au professionnel pour organiser le rendez-vous.</span>
          </label>
          {state.status === 'error' ? (
            <div className="alert alert-error" role="alert">
              {state.message}
            </div>
          ) : null}
          <button type="submit" className="btn btn-brand" disabled={pending} style={{ justifyContent: 'center' }}>
            {pending ? 'Envoi…' : 'Envoyer la demande'}
          </button>
          <div style={{ fontSize: 11, color: 'var(--muted)' }}>Le professionnel confirme le créneau par email.</div>
        </form>
      )}
    </div>
  );
}

/** Onglets d'ancrage de la fiche, avec suivi de la section visible. */
export function FicheTabs({ tabs }: { tabs: { id: string; label: string }[] }) {
  const [active, setActive] = useState(tabs[0]?.id);
  useEffect(() => {
    const els = tabs.map((t) => document.getElementById(t.id)).filter(Boolean) as HTMLElement[];
    const obs = new IntersectionObserver(
      (entries) => {
        const visible = entries.filter((e) => e.isIntersecting).sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);
        if (visible[0]) setActive(visible[0].target.id);
      },
      { rootMargin: '-140px 0px -55% 0px' },
    );
    els.forEach((el) => obs.observe(el));
    return () => obs.disconnect();
  }, [tabs]);
  return (
    <nav className="fiche-tabs" aria-label="Sections de la fiche">
      {tabs.map((t) => (
        <a key={t.id} href={`#${t.id}`} className={active === t.id ? 'is-active' : undefined} aria-current={active === t.id ? 'true' : undefined}>
          {t.label}
        </a>
      ))}
    </nav>
  );
}
