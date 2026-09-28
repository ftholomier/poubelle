'use client';

import { useState } from 'react';
import { ActionForm } from './ActionForm';
import { saveFormAction, saveMiniSiteAction } from '@/app/pro/[est]/site/actions';
import { DEFAULT_SECTIONS, MAX_FIELDS, SECTION_LABELS, THEME_PRESETS } from '@/lib/minisite';
import type { FormField, FormFieldType, MiniSite, MiniSiteSection } from '@/server/db/schema';

const TYPES: [FormFieldType, string][] = [
  ['text', 'Texte court'],
  ['textarea', 'Texte long'],
  ['email', 'Email'],
  ['tel', 'Téléphone'],
  ['date', 'Date'],
  ['number', 'Nombre'],
  ['select', 'Liste de choix'],
  ['checkbox', 'Case à cocher'],
];

const newId = () => Math.random().toString(36).slice(2, 10).padEnd(6, '0');

/** Modèles pour démarrer vite (devis, réservation, inscription). */
export const FORM_TEMPLATES: { key: string; title: string; intro: string; submitLabel: string; fields: Omit<FormField, 'id'>[] }[] = [
  {
    key: 'devis',
    title: 'Demande de devis',
    intro: 'Décrivez votre projet : nous vous répondons sous 48 heures avec un devis gratuit.',
    submitLabel: 'Demander un devis',
    fields: [
      { label: 'Type de prestation', type: 'select', required: true, options: ['Installation', 'Réparation', 'Entretien', 'Autre'] },
      { label: 'Votre projet', type: 'textarea', required: true, help: 'Dimensions, délais, contraintes…' },
      { label: 'Commune des travaux', type: 'text', required: false },
      { label: 'Date souhaitée', type: 'date', required: false },
    ],
  },
  {
    key: 'reservation',
    title: 'Réservation',
    intro: 'Réservez votre table ou votre commande : nous confirmons par email.',
    submitLabel: 'Réserver',
    fields: [
      { label: 'Date', type: 'date', required: true },
      { label: 'Nombre de personnes', type: 'number', required: true },
      { label: 'Créneau', type: 'select', required: true, options: ['Midi', 'Soir'] },
      { label: 'Précisions', type: 'textarea', required: false, help: 'Allergies, occasion particulière…' },
    ],
  },
  {
    key: 'atelier',
    title: 'Inscription à un atelier',
    intro: 'Places limitées : inscrivez-vous, nous confirmons votre place par email.',
    submitLabel: 'M’inscrire',
    fields: [
      { label: 'Atelier', type: 'select', required: true, options: ['Découverte', 'Perfectionnement'] },
      { label: 'Nombre de participants', type: 'number', required: true },
      { label: 'J’ai lu les conditions d’annulation', type: 'checkbox', required: true },
    ],
  },
];

export type EditableForm = {
  id: string | null;
  title: string;
  intro: string;
  submitLabel: string;
  successText: string;
  isActive: boolean;
  fields: FormField[];
};

/** Constructeur de formulaire : champs, types, obligatoire, options, aide. */
export function FormBuilder({ estId, initial }: { estId: string; initial: EditableForm }) {
  const [fields, setFields] = useState<FormField[]>(initial.fields);
  const [title, setTitle] = useState(initial.title);
  const [intro, setIntro] = useState(initial.intro);
  const [submitLabel, setSubmitLabel] = useState(initial.submitLabel);

  const update = (i: number, patch: Partial<FormField>) => setFields((fs) => fs.map((f, k) => (k === i ? { ...f, ...patch } : f)));
  const move = (i: number, d: number) =>
    setFields((fs) => {
      const j = i + d;
      if (j < 0 || j >= fs.length) return fs;
      const next = [...fs];
      [next[i], next[j]] = [next[j], next[i]];
      return next;
    });
  const applyTemplate = (key: string) => {
    const t = FORM_TEMPLATES.find((x) => x.key === key);
    if (!t) return;
    setTitle(t.title);
    setIntro(t.intro);
    setSubmitLabel(t.submitLabel);
    setFields(t.fields.map((f) => ({ ...f, id: newId() })));
  };

  return (
    <ActionForm action={saveFormAction} resetOnSuccess={false} style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
      {(pending) => (
        <>
          <input type="hidden" name="estId" value={estId} />
          <input type="hidden" name="formId" value={initial.id ?? ''} />
          <input type="hidden" name="fields" value={JSON.stringify(fields)} />
          {!initial.id ? (
            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', alignItems: 'center' }}>
              <span style={{ fontSize: 13, color: 'var(--muted)', fontWeight: 600 }}>Partir d’un modèle :</span>
              {FORM_TEMPLATES.map((t) => (
                <button key={t.key} type="button" className="chip" onClick={() => applyTemplate(t.key)}>
                  {t.title}
                </button>
              ))}
            </div>
          ) : null}
          <label className="field">
            <span>Titre du formulaire</span>
            <input name="title" className="input" required maxLength={160} value={title} onChange={(e) => setTitle(e.target.value)} />
          </label>
          <label className="field">
            <span>Introduction (facultatif)</span>
            <textarea name="intro" className="textarea" rows={2} maxLength={1000} value={intro} onChange={(e) => setIntro(e.target.value)} />
          </label>
          <div className="form-fields">
            <div style={{ fontSize: 13, color: 'var(--muted)' }}>
              Nom, email, téléphone et consentement sont toujours demandés. Ajoutez vos questions ({fields.length}/{MAX_FIELDS}) :
            </div>
            {fields.map((f, i) => (
              <fieldset key={f.id} className="form-field-row">
                <legend className="sr-only">Champ {i + 1}</legend>
                <div className="form-field-main">
                  <label className="field">
                    <span>Libellé</span>
                    <input className="input" value={f.label} maxLength={120} required onChange={(e) => update(i, { label: e.target.value })} />
                  </label>
                  <label className="field">
                    <span>Type</span>
                    <select
                      className="select"
                      value={f.type}
                      onChange={(e) => {
                        const type = e.target.value as FormFieldType;
                        update(i, { type, options: type === 'select' ? (f.options?.length ? f.options : ['Option 1', 'Option 2']) : undefined });
                      }}
                    >
                      {TYPES.map(([v, l]) => (
                        <option key={v} value={v}>
                          {l}
                        </option>
                      ))}
                    </select>
                  </label>
                </div>
                {f.type === 'select' ? (
                  <label className="field">
                    <span>Choix proposés (un par ligne)</span>
                    <textarea
                      className="textarea"
                      rows={3}
                      value={(f.options ?? []).join('\n')}
                      onChange={(e) => update(i, { options: e.target.value.split('\n').map((o) => o.slice(0, 80)) })}
                      onBlur={(e) =>
                        update(i, {
                          options: e.target.value
                            .split('\n')
                            .map((o) => o.trim())
                            .filter(Boolean),
                        })
                      }
                    />
                  </label>
                ) : null}
                <label className="field">
                  <span>Aide (facultatif)</span>
                  <input className="input" value={f.help ?? ''} maxLength={200} onChange={(e) => update(i, { help: e.target.value })} />
                </label>
                <div style={{ display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap' }}>
                  <label className="checkbox">
                    <input type="checkbox" checked={f.required} onChange={(e) => update(i, { required: e.target.checked })} />
                    <span>Obligatoire</span>
                  </label>
                  <span style={{ marginLeft: 'auto', display: 'flex', gap: 4 }}>
                    <button type="button" className="btn btn-ghost btn-xs" onClick={() => move(i, -1)} disabled={i === 0} aria-label={`Monter « ${f.label} »`}>
                      ↑
                    </button>
                    <button
                      type="button"
                      className="btn btn-ghost btn-xs"
                      onClick={() => move(i, 1)}
                      disabled={i === fields.length - 1}
                      aria-label={`Descendre « ${f.label} »`}
                    >
                      ↓
                    </button>
                    <button
                      type="button"
                      className="btn btn-ghost btn-xs"
                      style={{ color: 'var(--danger-fg)' }}
                      onClick={() => setFields((fs) => fs.filter((_, k) => k !== i))}
                    >
                      Retirer
                    </button>
                  </span>
                </div>
              </fieldset>
            ))}
            <button
              type="button"
              className="btn btn-outline btn-sm"
              style={{ alignSelf: 'flex-start' }}
              disabled={fields.length >= MAX_FIELDS}
              onClick={() => setFields((fs) => [...fs, { id: newId(), label: 'Nouvelle question', type: 'text', required: false }])}
            >
              + Ajouter une question
            </button>
          </div>
          <div className="split" style={{ ['--cols' as string]: 'minmax(0,1fr) minmax(0,2fr)', ['--gap' as string]: '10px' }}>
            <label className="field">
              <span>Texte du bouton</span>
              <input name="submitLabel" className="input" required maxLength={60} value={submitLabel} onChange={(e) => setSubmitLabel(e.target.value)} />
            </label>
            <label className="field">
              <span>Message de confirmation (facultatif)</span>
              <input
                name="successText"
                className="input"
                maxLength={500}
                defaultValue={initial.successText}
                placeholder="Merci ! Nous vous répondons sous 48 h."
              />
            </label>
          </div>
          <label className="checkbox">
            <input type="checkbox" name="isActive" defaultChecked={initial.isActive} />
            <span>Afficher ce formulaire sur ma fiche</span>
          </label>
          <button type="submit" className="btn btn-brand" disabled={pending} style={{ alignSelf: 'flex-start' }}>
            {pending ? 'Enregistrement…' : 'Enregistrer le formulaire'}
          </button>
        </>
      )}
    </ActionForm>
  );
}

/** Réglages du mini-site : couleur, en-tête, accroche, bouton principal, ordre des sections. */
export function MiniSiteEditor({
  estId,
  mini,
  themeColor,
  targets,
  publicUrl,
}: {
  estId: string;
  mini: MiniSite;
  themeColor: string | null;
  targets: { value: string; label: string }[];
  publicUrl: string | null;
}) {
  const [color, setColor] = useState(themeColor ?? THEME_PRESETS[0]);
  const [hero, setHero] = useState<'photo' | 'color'>(mini.hero ?? 'photo');
  const initialOrder = mini.sections?.length ? [...mini.sections, ...DEFAULT_SECTIONS.filter((s) => !mini.sections!.includes(s))] : DEFAULT_SECTIONS;
  const [order, setOrder] = useState<MiniSiteSection[]>(initialOrder);
  const [shown, setShown] = useState<Set<MiniSiteSection>>(new Set(mini.sections?.length ? mini.sections : DEFAULT_SECTIONS));
  const ctaHref = mini.cta?.href ?? '';
  const knownTarget = targets.some((t) => t.value === ctaHref);
  const [target, setTarget] = useState(ctaHref ? (knownTarget ? ctaHref : 'url') : '');

  const move = (i: number, d: number) =>
    setOrder((o) => {
      const j = i + d;
      if (j < 0 || j >= o.length) return o;
      const next = [...o];
      [next[i], next[j]] = [next[j], next[i]];
      return next;
    });
  const toggle = (s: MiniSiteSection, on: boolean) =>
    setShown((cur) => {
      const next = new Set(cur);
      if (on) next.add(s);
      else next.delete(s);
      return next;
    });

  return (
    <ActionForm action={saveMiniSiteAction} resetOnSuccess={false} style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
      {(pending) => (
        <>
          <input type="hidden" name="estId" value={estId} />
          <input type="hidden" name="themeColor" value={color} />
          <input type="hidden" name="hero" value={hero} />
          <input type="hidden" name="sections" value={order.filter((s) => shown.has(s) || s === 'contact').join(',')} />
          <label className="checkbox" style={{ fontWeight: 700 }}>
            <input type="checkbox" name="enabled" defaultChecked={mini.enabled} />
            <span>Activer le mini-site sur ma fiche</span>
          </label>

          <fieldset className="minisite-fieldset">
            <legend>Couleur de marque</legend>
            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', alignItems: 'center' }}>
              {THEME_PRESETS.map((c) => (
                <button
                  key={c}
                  type="button"
                  className="swatch"
                  style={{ background: c, outline: color === c ? '3px solid var(--ink)' : undefined }}
                  aria-label={`Couleur ${c}`}
                  aria-pressed={color === c}
                  onClick={() => setColor(c)}
                />
              ))}
              <label className="field" style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
                <span>Autre</span>
                <input type="color" value={color} onChange={(e) => setColor(e.target.value)} aria-label="Choisir une couleur" />
              </label>
            </div>
          </fieldset>

          <fieldset className="minisite-fieldset">
            <legend>En-tête</legend>
            <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
              {(
                [
                  ['photo', 'Photos en grand', 'Votre galerie en ouverture, le nom en dessous.'],
                  ['color', 'Bandeau de couleur', 'Logo, nom et accroche sur votre couleur, puis les photos.'],
                ] as const
              ).map(([v, l, h]) => (
                <label key={v} className={`choice-card${hero === v ? ' is-active' : ''}`}>
                  <input type="radio" name="heroChoice" value={v} checked={hero === v} onChange={() => setHero(v)} className="sr-only" />
                  <b>{l}</b>
                  <span>{h}</span>
                </label>
              ))}
            </div>
            <div
              className="hero-preview"
              style={{ background: hero === 'color' ? color : 'var(--sand)', color: hero === 'color' ? '#fff' : 'var(--muted)' }}
              aria-hidden="true"
            >
              {hero === 'color' ? 'Bandeau à votre couleur' : 'Galerie de photos'}
            </div>
          </fieldset>

          <label className="field">
            <span>Accroche (facultatif)</span>
            <input
              name="headline"
              className="input"
              maxLength={160}
              defaultValue={mini.headline ?? ''}
              placeholder="Pain au levain et viennoiseries pur beurre depuis 1987"
            />
          </label>

          <div className="split" style={{ ['--cols' as string]: 'minmax(0,1fr) minmax(0,1.3fr)', ['--gap' as string]: '10px' }}>
            <label className="field">
              <span>Bouton principal (facultatif)</span>
              <input name="ctaLabel" className="input" maxLength={40} defaultValue={mini.cta?.label ?? ''} placeholder="Commander" />
            </label>
            <label className="field">
              <span>Il mène vers</span>
              <select name="ctaTarget" className="select" value={target} onChange={(e) => setTarget(e.target.value)}>
                <option value="">Aucun bouton</option>
                {targets.map((t) => (
                  <option key={t.value} value={t.value}>
                    {t.label}
                  </option>
                ))}
                <option value="url">Un lien (site de commande, billetterie…)</option>
              </select>
            </label>
          </div>
          {target === 'url' ? (
            <label className="field">
              <span>Lien</span>
              <input name="ctaUrl" type="url" className="input" maxLength={500} defaultValue={knownTarget ? '' : ctaHref} placeholder="https://" required />
            </label>
          ) : null}

          <fieldset className="minisite-fieldset">
            <legend>Sections de la page, dans l’ordre</legend>
            <ol className="section-order">
              {order.map((s, i) => (
                <li key={s}>
                  <label className="checkbox">
                    <input type="checkbox" checked={s === 'contact' || shown.has(s)} disabled={s === 'contact'} onChange={(e) => toggle(s, e.target.checked)} />
                    <span>
                      {SECTION_LABELS[s]}
                      {s === 'contact' ? <small style={{ color: 'var(--muted)' }}> (toujours affiché)</small> : null}
                    </span>
                  </label>
                  <span style={{ marginLeft: 'auto', display: 'flex', gap: 4 }}>
                    <button
                      type="button"
                      className="btn btn-ghost btn-xs"
                      onClick={() => move(i, -1)}
                      disabled={i === 0}
                      aria-label={`Monter « ${SECTION_LABELS[s]} »`}
                    >
                      ↑
                    </button>
                    <button
                      type="button"
                      className="btn btn-ghost btn-xs"
                      onClick={() => move(i, 1)}
                      disabled={i === order.length - 1}
                      aria-label={`Descendre « ${SECTION_LABELS[s]} »`}
                    >
                      ↓
                    </button>
                  </span>
                </li>
              ))}
            </ol>
          </fieldset>

          <div style={{ display: 'flex', gap: 10, alignItems: 'center', flexWrap: 'wrap' }}>
            <button type="submit" className="btn btn-brand" disabled={pending}>
              {pending ? 'Enregistrement…' : 'Enregistrer le mini-site'}
            </button>
            {publicUrl ? (
              <a href={publicUrl} target="_blank" rel="noopener noreferrer" className="btn btn-outline btn-sm">
                Voir le résultat ↗
              </a>
            ) : null}
          </div>
        </>
      )}
    </ActionForm>
  );
}
