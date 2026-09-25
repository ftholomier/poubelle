'use client';

import { useActionState, useMemo, useState, useTransition, type CSSProperties, type ReactNode } from 'react';
import { improveDescriptionAction, saveFiche, type ActionState } from '@/app/pro/[est]/actions';
import { Switch, useToast } from '@/components/ui/Feedback';
import { LogoField } from './LogoField';

type Range = { opensAt: string; closesAt: string };
type Day = { open: boolean; ranges: Range[] };
type Exception = { date: string; closed: boolean; opensAt: string | null; closesAt: string | null; label: string | null };
type Attr = { slug: string; label: string; group: string };

export type FicheEditorProps = {
  estId: string;
  logoUrl: string | null;
  values: {
    name: string;
    categoryId: string;
    activityLabel: string;
    tagline: string;
    description: string;
    street: string;
    postalCode: string;
    phone: string;
    email: string;
    website: string;
    facebook: string;
    instagram: string;
    linkedin: string;
    priceInfo: string;
    serviceArea: string;
    accessibilityInfo: string;
    appointmentInfo: string;
    appointmentsEnabled: boolean;
  };
  hours: { weekday: number; opensAt: string; closesAt: string }[];
  exceptions: Exception[];
  attributes: string[];
  categories: { id: string; name: string; family: string }[];
  allAttributes: Attr[];
  canAppointments: boolean;
  upcomingHoliday: { date: string; label: string } | null;
  photosSlot?: ReactNode;
  productsSlot?: ReactNode;
};

const DAYS = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
const GROUPS: [string, string][] = [
  ['SERVICE', 'Services'],
  ['HIGHLIGHT', 'Points forts'],
  ['LABEL', 'Labels'],
  ['ACCESSIBILITY', 'Accessibilité'],
  ['PAYMENT', 'Paiement'],
];
const label: CSSProperties = { display: 'flex', flexDirection: 'column', gap: 6, fontSize: 13, fontWeight: 600, color: 'var(--muted)' };
const fmt = (t: string) => {
  const [h, m] = t.split(':');
  return `${Number(h)}h${m}`;
};

export function FicheEditor(p: FicheEditorProps) {
  const toast = useToast();
  const [state, action, pending] = useActionState<ActionState, FormData>(
    async (prev, form) => {
      const res = await saveFiche(prev, form);
      if (res.status === 'ok') toast(res.message ?? 'Enregistré');
      return res;
    },
    { status: 'idle' },
  );
  const [description, setDescription] = useState(p.values.description);
  const [improving, startImprove] = useTransition();
  const [improved, setImproved] = useState<string | null>(null);
  const [days, setDays] = useState<Day[]>(() =>
    DAYS.map((_, wd) => {
      const ranges = p.hours
        .filter((h) => h.weekday === wd)
        .sort((a, b) => a.opensAt.localeCompare(b.opensAt))
        .map((h) => ({ opensAt: h.opensAt.slice(0, 5), closesAt: h.closesAt.slice(0, 5) }));
      return { open: ranges.length > 0, ranges: ranges.length ? ranges : [{ opensAt: '09:00', closesAt: '18:00' }] };
    }),
  );
  const [editing, setEditing] = useState<number | null>(null);
  const [exceptions, setExceptions] = useState<Exception[]>(p.exceptions);
  const [attrs, setAttrs] = useState<Set<string>>(new Set(p.attributes));

  const hoursJson = useMemo(() => JSON.stringify(days.flatMap((d, wd) => (d.open ? d.ranges.map((r) => ({ weekday: wd, ...r })) : []))), [days]);
  const updateDay = (wd: number, fn: (d: Day) => Day) => setDays((all) => all.map((d, i) => (i === wd ? fn(d) : d)));
  const copyToWeek = (wd: number) => setDays((all) => all.map((d, i) => (i < 5 ? { open: all[wd].open, ranges: all[wd].ranges.map((r) => ({ ...r })) } : d)));

  const improve = () =>
    startImprove(async () => {
      const res = await improveDescriptionAction(p.estId, description);
      if (!res.ok) {
        toast(res.message ?? 'Assistant indisponible', 'error');
        return;
      }
      setDescription(res.description!);
      setImproved(res.source === 'ai' ? "Amélioré par l'IA" : 'Proposition générée');
    });

  const addHolidayException = () => {
    if (!p.upcomingHoliday || exceptions.some((x) => x.date === p.upcomingHoliday!.date)) return;
    setExceptions((xs) => [...xs, { date: p.upcomingHoliday!.date, closed: true, opensAt: null, closesAt: null, label: p.upcomingHoliday!.label }]);
  };

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 16, minWidth: 0 }}>
      <form id="fiche-form" action={action} hidden>
        <input type="hidden" name="estId" value={p.estId} />
        <input type="hidden" name="hours" value={hoursJson} />
        <input type="hidden" name="exceptions" value={JSON.stringify(exceptions)} />
        <input type="hidden" name="attributes" value={JSON.stringify([...attrs])} />
      </form>

      <section id="identite" className="panel" style={{ scrollMarginTop: 90 }}>
        <h2 className="panel-title">Identité</h2>
        <LogoField estId={p.estId} logoUrl={p.logoUrl} name={p.values.name} />
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(220px,1fr))', gap: 12 }}>
          <label style={label}>
            Nom commercial
            <input form="fiche-form" name="name" className="input" defaultValue={p.values.name} required maxLength={255} />
          </label>
          <label style={label}>
            Catégorie principale
            <select form="fiche-form" name="categoryId" className="select" defaultValue={p.values.categoryId}>
              {p.categories.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.name}
                </option>
              ))}
            </select>
          </label>
          <label style={label}>
            Activité affichée
            <input
              form="fiche-form"
              name="activityLabel"
              className="input"
              defaultValue={p.values.activityLabel}
              placeholder="ex. Boulangerie-pâtisserie"
              maxLength={160}
            />
          </label>
          <label style={label}>
            Accroche
            <input
              form="fiche-form"
              name="tagline"
              className="input"
              defaultValue={p.values.tagline}
              placeholder="Une phrase qui vous résume"
              maxLength={255}
            />
          </label>
        </div>
        <label id="description" style={{ ...label, scrollMarginTop: 90 }}>
          <span style={{ display: 'flex', justifyContent: 'space-between', gap: 10 }}>
            Description
            <button type="button" onClick={improve} disabled={improving} className="btn-link" style={{ color: 'var(--brick)', fontWeight: 800 }}>
              ✦ {improving ? 'Rédaction…' : (improved ?? "Améliorer avec l'IA")}
            </button>
          </span>
          <textarea
            form="fiche-form"
            name="description"
            aria-label="Description de l'activité"
            rows={5}
            className="textarea"
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            maxLength={5000}
            style={{ lineHeight: 1.5 }}
          />
          <span style={{ fontWeight: 500, fontSize: 12 }}>
            {description.trim() ? description.trim().split(/\s+/).length : 0} mots · visez 60 et plus pour Google
          </span>
        </label>
      </section>

      <section id="contact" className="panel" style={{ scrollMarginTop: 90 }}>
        <h2 className="panel-title">Coordonnées</h2>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(220px,1fr))', gap: 12 }}>
          <label style={label}>
            Adresse
            <input form="fiche-form" name="street" className="input" defaultValue={p.values.street} autoComplete="street-address" maxLength={255} />
          </label>
          <label style={label}>
            Code postal
            <input form="fiche-form" name="postalCode" className="input" defaultValue={p.values.postalCode} inputMode="numeric" maxLength={10} />
          </label>
          <label style={label}>
            Téléphone
            <input form="fiche-form" name="phone" className="input" type="tel" defaultValue={p.values.phone} maxLength={32} />
          </label>
          <label style={label}>
            Email public
            <input form="fiche-form" name="email" className="input" type="email" defaultValue={p.values.email} maxLength={255} />
          </label>
          <label style={label}>
            Site internet
            <input form="fiche-form" name="website" className="input" defaultValue={p.values.website} placeholder="https://" maxLength={500} />
          </label>
          <label style={label}>
            Facebook
            <input form="fiche-form" name="facebook" className="input" defaultValue={p.values.facebook} placeholder="https://facebook.com/…" maxLength={500} />
          </label>
          <label style={label}>
            Instagram
            <input
              form="fiche-form"
              name="instagram"
              className="input"
              defaultValue={p.values.instagram}
              placeholder="https://instagram.com/…"
              maxLength={500}
            />
          </label>
          <label style={label}>
            LinkedIn
            <input form="fiche-form" name="linkedin" className="input" defaultValue={p.values.linkedin} placeholder="https://linkedin.com/…" maxLength={500} />
          </label>
          <label style={label}>
            Zone d&apos;intervention
            <input
              form="fiche-form"
              name="serviceArea"
              className="input"
              defaultValue={p.values.serviceArea}
              placeholder="ex. 25 km autour d’Ornans"
              maxLength={255}
            />
          </label>
          <label style={label}>
            Tarifs indicatifs
            <input form="fiche-form" name="priceInfo" className="input" defaultValue={p.values.priceInfo} placeholder="ex. Menu du jour 16 €" maxLength={500} />
          </label>
        </div>
        <label style={label}>
          Informations d&apos;accessibilité
          <input
            form="fiche-form"
            name="accessibilityInfo"
            className="input"
            defaultValue={p.values.accessibilityInfo}
            placeholder="ex. Plain-pied, place PMR devant la boutique"
            maxLength={500}
          />
        </label>
      </section>

      {p.photosSlot}

      <section id="horaires" className="panel" style={{ scrollMarginTop: 90, gap: 8 }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', gap: 10, flexWrap: 'wrap', marginBottom: 4 }}>
          <h2 className="panel-title">Horaires</h2>
          {p.upcomingHoliday ? (
            <button type="button" className="btn-link" onClick={addHolidayException} style={{ fontSize: 13 }}>
              + Horaires exceptionnels ({p.upcomingHoliday.label})
            </button>
          ) : (
            <button
              type="button"
              className="btn-link"
              style={{ fontSize: 13 }}
              onClick={() =>
                setExceptions((xs) => [
                  ...xs,
                  { date: new Date(Date.now() + 7 * 86_400_000).toISOString().slice(0, 10), closed: true, opensAt: null, closesAt: null, label: 'Congés' },
                ])
              }
            >
              + Horaires exceptionnels
            </button>
          )}
        </div>
        {days.map((d, wd) => (
          <div key={wd} style={{ display: 'grid', gridTemplateColumns: '110px 46px 1fr', gap: 12, alignItems: 'center', fontSize: 14, minHeight: 34 }}>
            <b>{DAYS[wd]}</b>
            <Switch checked={d.open} label={`${DAYS[wd]} : ouvert`} onChange={(v) => updateDay(wd, (x) => ({ ...x, open: v }))} />
            {!d.open ? (
              <span style={{ color: 'var(--faint)' }}>Fermé</span>
            ) : editing === wd ? (
              <div style={{ display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap' }}>
                {d.ranges.map((r, ri) => (
                  <span key={ri} style={{ display: 'inline-flex', gap: 4, alignItems: 'center' }}>
                    <input
                      type="time"
                      className="input"
                      value={r.opensAt}
                      step={900}
                      aria-label={`${DAYS[wd]} ouverture ${ri + 1}`}
                      onChange={(e) => updateDay(wd, (x) => ({ ...x, ranges: x.ranges.map((y, j) => (j === ri ? { ...y, opensAt: e.target.value } : y)) }))}
                      style={{ width: 108, padding: 7 }}
                    />
                    –
                    <input
                      type="time"
                      className="input"
                      value={r.closesAt}
                      step={900}
                      aria-label={`${DAYS[wd]} fermeture ${ri + 1}`}
                      onChange={(e) => updateDay(wd, (x) => ({ ...x, ranges: x.ranges.map((y, j) => (j === ri ? { ...y, closesAt: e.target.value } : y)) }))}
                      style={{ width: 108, padding: 7 }}
                    />
                    {d.ranges.length > 1 ? (
                      <button
                        type="button"
                        className="btn-link"
                        aria-label="Retirer cette plage"
                        onClick={() => updateDay(wd, (x) => ({ ...x, ranges: x.ranges.filter((_, j) => j !== ri) }))}
                      >
                        ×
                      </button>
                    ) : null}
                  </span>
                ))}
                {d.ranges.length < 3 ? (
                  <button
                    type="button"
                    className="btn-link"
                    style={{ fontSize: 13 }}
                    onClick={() => updateDay(wd, (x) => ({ ...x, ranges: [...x.ranges, { opensAt: '14:00', closesAt: '19:00' }] }))}
                  >
                    + coupure
                  </button>
                ) : null}
                {wd < 5 ? (
                  <button type="button" className="btn-link" style={{ fontSize: 13, color: 'var(--muted)' }} onClick={() => copyToWeek(wd)}>
                    Copier du lundi au vendredi
                  </button>
                ) : null}
                <button type="button" className="btn btn-xs btn-dark" onClick={() => setEditing(null)}>
                  OK
                </button>
              </div>
            ) : (
              <button
                type="button"
                onClick={() => setEditing(wd)}
                className="btn-link"
                style={{ color: 'var(--text)', fontWeight: 500, textAlign: 'left' }}
                title="Modifier les horaires"
              >
                {d.ranges.map((r) => `${fmt(r.opensAt)} – ${fmt(r.closesAt)}`).join(', ')}
              </button>
            )}
          </div>
        ))}
        {exceptions.length ? (
          <div style={{ borderTop: '1px solid var(--line-2)', paddingTop: 10, marginTop: 6, display: 'flex', flexDirection: 'column', gap: 8 }}>
            <b style={{ fontSize: 14 }}>Horaires exceptionnels</b>
            {exceptions.map((x, i) => (
              <div key={i} style={{ display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap', fontSize: 14 }}>
                <input
                  type="date"
                  className="input"
                  value={x.date}
                  aria-label="Date"
                  onChange={(e) => setExceptions((xs) => xs.map((y, j) => (j === i ? { ...y, date: e.target.value } : y)))}
                  style={{ width: 160, padding: 7 }}
                />
                <input
                  className="input"
                  value={x.label ?? ''}
                  placeholder="Motif (Noël, congés…)"
                  aria-label="Motif"
                  onChange={(e) => setExceptions((xs) => xs.map((y, j) => (j === i ? { ...y, label: e.target.value } : y)))}
                  style={{ width: 170, padding: 7 }}
                />
                <label style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                  <input
                    type="checkbox"
                    checked={x.closed}
                    onChange={(e) =>
                      setExceptions((xs) =>
                        xs.map((y, j) =>
                          j === i
                            ? { ...y, closed: e.target.checked, opensAt: e.target.checked ? null : '09:00', closesAt: e.target.checked ? null : '12:00' }
                            : y,
                        ),
                      )
                    }
                  />
                  Fermé
                </label>
                {!x.closed ? (
                  <>
                    <input
                      type="time"
                      className="input"
                      value={x.opensAt ?? '09:00'}
                      aria-label="Ouverture"
                      onChange={(e) => setExceptions((xs) => xs.map((y, j) => (j === i ? { ...y, opensAt: e.target.value } : y)))}
                      style={{ width: 108, padding: 7 }}
                    />
                    <input
                      type="time"
                      className="input"
                      value={x.closesAt ?? '12:00'}
                      aria-label="Fermeture"
                      onChange={(e) => setExceptions((xs) => xs.map((y, j) => (j === i ? { ...y, closesAt: e.target.value } : y)))}
                      style={{ width: 108, padding: 7 }}
                    />
                  </>
                ) : null}
                <button
                  type="button"
                  className="btn-link"
                  style={{ color: 'var(--danger-fg)' }}
                  onClick={() => setExceptions((xs) => xs.filter((_, j) => j !== i))}
                >
                  Retirer
                </button>
              </div>
            ))}
          </div>
        ) : null}
      </section>

      <section id="services" className="panel" style={{ scrollMarginTop: 90 }}>
        <h2 className="panel-title">Services, labels &amp; paiement</h2>
        {GROUPS.map(([g, title]) => {
          const list = p.allAttributes.filter((a) => a.group === g);
          if (!list.length) return null;
          return (
            <div key={g} style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
              <span style={{ fontSize: 12, fontWeight: 700, color: 'var(--muted)', textTransform: 'uppercase', letterSpacing: '0.05em' }}>{title}</span>
              <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                {list.map((a) => {
                  const on = attrs.has(a.slug);
                  return (
                    <button
                      key={a.slug}
                      type="button"
                      aria-pressed={on}
                      onClick={() =>
                        setAttrs((s) => {
                          const n = new Set(s);
                          if (on) n.delete(a.slug);
                          else n.add(a.slug);
                          return n;
                        })
                      }
                      style={{
                        padding: '7px 12px',
                        borderRadius: 999,
                        fontSize: 13,
                        fontWeight: 600,
                        border: `1.5px solid ${on ? 'var(--green)' : 'var(--line)'}`,
                        background: on ? 'var(--mint)' : '#fff',
                        color: on ? 'var(--green)' : 'var(--muted)',
                      }}
                    >
                      {on ? '✓' : '+'} {a.label}
                    </button>
                  );
                })}
              </div>
            </div>
          );
        })}
      </section>

      {p.productsSlot}

      {p.canAppointments ? (
        <section className="panel">
          <h2 className="panel-title">Prise de rendez-vous</h2>
          <label style={{ display: 'flex', gap: 10, alignItems: 'center', fontSize: 14 }}>
            <input
              type="checkbox"
              form="fiche-form"
              name="appointmentsEnabled"
              value="on"
              defaultChecked={p.values.appointmentsEnabled}
              style={{ accentColor: 'var(--green)', width: 18, height: 18 }}
            />
            Proposer la demande de rendez-vous sur ma fiche
          </label>
          <label style={label}>
            Message affiché (délais, prestations…)
            <input form="fiche-form" name="appointmentInfo" className="input" defaultValue={p.values.appointmentInfo} maxLength={500} />
          </label>
        </section>
      ) : (
        <input type="hidden" form="fiche-form" name="appointmentInfo" value={p.values.appointmentInfo} />
      )}

      {state.status === 'error' ? (
        <div className="alert alert-error" role="alert">
          {state.message}
        </div>
      ) : null}
      <button type="submit" form="fiche-form" className="btn btn-brand show-md" disabled={pending} style={{ justifyContent: 'center', padding: 14 }}>
        {pending ? 'Enregistrement…' : 'Enregistrer · publié instantanément'}
      </button>
    </div>
  );
}

/** Bouton d'enregistrement de la colonne latérale (soumet le formulaire principal). */
export function SaveFicheButton() {
  return (
    <button type="submit" form="fiche-form" className="btn btn-brand" style={{ justifyContent: 'center', padding: 14, fontSize: 15, borderRadius: 12 }}>
      Enregistrer · publié instantanément
    </button>
  );
}
