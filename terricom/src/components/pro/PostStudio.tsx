'use client';

import { useActionState, useRef, useState, useTransition } from 'react';
import { createPost, generateVariants, type ActionState, type Variants } from '@/app/pro/[est]/actions';
import { useToast } from '@/components/ui/Feedback';
import { FileDrop } from '@/components/ui/FileDrop';
import { Photo } from '@/components/ui/Photo';

const KINDS: [string, string][] = [
  ['NEWS', 'Actualité'],
  ['PROMO', 'Promotion'],
  ['EVENT', 'Événement'],
  ['NOUVEAUTE', 'Nouveauté'],
  ['HOURS', 'Horaires'],
  ['JOB', 'Recrutement'],
];
const TONES = ['Chaleureux', 'Pro', 'Fun'] as const;

type Channel = { key: string; label: string; detail: string; locked: boolean; always?: boolean };

export function PostStudio({
  estId,
  channels,
  cover,
  canSchedule,
  quota,
}: {
  estId: string;
  channels: Channel[];
  cover: string | null;
  canSchedule: boolean;
  quota: { used: number; max: number | null };
}) {
  const toast = useToast();
  const [kind, setKind] = useState('NEWS');
  const [tone, setTone] = useState<(typeof TONES)[number]>('Chaleureux');
  const [draft, setDraft] = useState('');
  const [variants, setVariants] = useState<Variants | null>(null);
  const [generating, startGen] = useTransition();
  const [title, setTitle] = useState('');
  const [body, setBody] = useState('');
  const [selected, setSelected] = useState<Set<string>>(new Set(['FICHE', 'COMMUNE', 'TERRITOIRE']));
  const [schedule, setSchedule] = useState(false);
  const composerRef = useRef<HTMLDivElement>(null);
  const [state, action, pending] = useActionState<ActionState, FormData>(
    async (prev, form) => {
      const res = await createPost(prev, form);
      if (res.status === 'ok') {
        toast(res.message ?? 'Publié');
        setVariants(null);
        setDraft('');
        setTitle('');
        setBody('');
        setSchedule(false);
      }
      return res;
    },
    { status: 'idle' },
  );

  const generate = () =>
    startGen(async () => {
      const res = await generateVariants(estId, { kind, draft, tone });
      if (!res.ok) {
        toast(res.message, 'error');
        return;
      }
      setVariants(res.variants);
      setTitle(draft.replace(/[.!]+$/, '').slice(0, 120));
      setBody(res.variants.fiche);
    });

  const cards = variants
    ? [
        { key: 'fiche', label: 'Fiche & site', bg: 'var(--mint)', img: true, text: variants.fiche },
        { key: 'facebook', label: 'Facebook', bg: 'var(--sky)', img: true, text: variants.facebook },
        { key: 'instagram', label: 'Instagram', bg: 'var(--rose)', img: true, text: variants.instagram },
        { key: 'linkedin', label: 'LinkedIn', bg: 'var(--lilac)', img: false, text: variants.linkedin },
        { key: 'email', label: 'Email clients', bg: 'var(--amber)', img: false, text: `Objet : ${variants.emailSubject}\n\n${variants.emailBody}` },
        { key: 'seo', label: 'Titre SEO', bg: 'var(--sand)', img: false, text: variants.seoTitle },
      ]
    : [];

  const copy = async (text: string) => {
    try {
      await navigator.clipboard.writeText(text);
      toast('Texte copié.');
    } catch {
      toast('Copie impossible : sélectionnez le texte.', 'error');
    }
  };

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 16, minWidth: 0 }}>
      <div className="panel" ref={composerRef} style={{ borderRadius: 20 }}>
        <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }} role="group" aria-label="Type de publication">
          {KINDS.map(([k, l]) => (
            <button
              key={k}
              type="button"
              aria-pressed={kind === k}
              onClick={() => setKind(k)}
              style={{
                border: 0,
                padding: '8px 13px',
                borderRadius: 999,
                fontWeight: 700,
                fontSize: 13,
                background: kind === k ? 'var(--ink)' : 'var(--sand)',
                color: kind === k ? '#fff' : 'var(--text)',
              }}
            >
              {l}
            </button>
          ))}
        </div>
        <label htmlFor="draft" style={{ fontSize: 13, fontWeight: 600, color: 'var(--muted)' }}>
          Dites-le simplement, l&apos;assistant s&apos;occupe du reste :
        </label>
        <div style={{ display: 'flex', gap: 10, alignItems: 'stretch', flexWrap: 'wrap' }}>
          <input
            id="draft"
            value={draft}
            onChange={(e) => setDraft(e.target.value)}
            onKeyDown={(e) => e.key === 'Enter' && (e.preventDefault(), generate())}
            placeholder="ex. Nouvelle collection printemps disponible en magasin"
            maxLength={500}
            style={{ flex: 1, minWidth: 260, border: '1.5px solid var(--ink)', borderRadius: 12, padding: 14, fontSize: 16 }}
          />
          <button
            type="button"
            onClick={generate}
            disabled={generating || draft.trim().length < 3}
            style={{
              border: 0,
              background: 'var(--ink)',
              color: 'var(--amber)',
              padding: '0 20px',
              minHeight: 50,
              borderRadius: 12,
              fontWeight: 800,
              fontSize: 14,
            }}
          >
            {generating ? 'Rédaction…' : '✦ Rédiger pour tous mes canaux'}
          </button>
        </div>
        <div style={{ display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap' }}>
          <span style={{ fontSize: 13, color: 'var(--muted)' }}>Ton :</span>
          {TONES.map((t) => (
            <button
              key={t}
              type="button"
              aria-pressed={tone === t}
              onClick={() => setTone(t)}
              style={{
                border: `1.5px solid ${tone === t ? 'var(--ink)' : 'var(--line)'}`,
                background: tone === t ? 'var(--amber)' : '#fff',
                padding: '6px 12px',
                borderRadius: 999,
                fontSize: 13,
                fontWeight: 600,
              }}
            >
              {t}
            </button>
          ))}
          {quota.max !== null ? (
            <span style={{ marginLeft: 'auto', fontSize: 12, color: 'var(--muted)' }}>
              {quota.used} / {quota.max} publications ce mois-ci
            </span>
          ) : null}
        </div>
      </div>

      {variants ? (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill,minmax(260px,1fr))', gap: 12 }}>
          {cards.map((g) => (
            <div key={g.key} className="card" style={{ borderRadius: 16, overflow: 'hidden', display: 'flex', flexDirection: 'column' }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '10px 14px', background: g.bg }}>
                <b style={{ fontSize: 13 }}>{g.label}</b>
                <span style={{ fontSize: 11, color: 'var(--muted-3)' }}>{g.text.length} car.</span>
              </div>
              {g.img ? (
                <div style={{ height: 130 }}>
                  <Photo src={cover} alt="" label=" " color="#C8892A" />
                </div>
              ) : null}
              <div style={{ padding: '12px 14px', fontSize: 14, lineHeight: 1.5, whiteSpace: 'pre-line', flex: 1 }}>{g.text}</div>
              <div style={{ display: 'flex', gap: 10, padding: '10px 14px', borderTop: '1px solid var(--line-2)', fontSize: 12, fontWeight: 700 }}>
                <button type="button" className="btn-link" onClick={() => copy(g.text)}>
                  Copier
                </button>
                {g.key !== 'seo' && g.key !== 'email' ? (
                  <button type="button" className="btn-link" style={{ color: 'var(--muted)' }} onClick={() => setBody(g.text)}>
                    Utiliser
                  </button>
                ) : null}
                {canSchedule ? (
                  <button
                    type="button"
                    className="btn-link"
                    style={{ color: 'var(--muted)', marginLeft: 'auto' }}
                    onClick={() => {
                      setBody(g.key === 'fiche' ? g.text : body);
                      setSchedule(true);
                      document.getElementById('post-form')?.scrollIntoView({ behavior: 'smooth' });
                    }}
                  >
                    Programmer
                  </button>
                ) : null}
              </div>
            </div>
          ))}
          <div style={{ gridColumn: '1 / -1', fontSize: 12, color: 'var(--muted)' }}>
            {variants.source === 'ai'
              ? '✦ Rédigé par l’assistant IA : relisez avant de publier.'
              : 'Proposition générée automatiquement : personnalisez-la avant de publier.'}
          </div>
        </div>
      ) : null}

      <form id="post-form" action={action} className="panel" style={{ borderRadius: 20 }}>
        <input type="hidden" name="estId" value={estId} />
        <input type="hidden" name="kind" value={kind} />
        <input type="hidden" name="aiGenerated" value={variants?.source === 'ai' ? '1' : '0'} />
        <input
          type="hidden"
          name="variants"
          value={
            variants
              ? JSON.stringify({
                  fiche: variants.fiche,
                  facebook: variants.facebook,
                  instagram: variants.instagram,
                  linkedin: variants.linkedin,
                  email: `${variants.emailSubject}\n\n${variants.emailBody}`,
                  seoTitle: variants.seoTitle,
                })
              : ''
          }
        />
        <b style={{ fontSize: 16 }}>Votre publication</b>
        <input
          name="title"
          className="input"
          placeholder="Titre (ex. Le pain au Comté est de retour)"
          value={title}
          onChange={(e) => setTitle(e.target.value)}
          required
          maxLength={255}
        />
        <textarea
          name="body"
          className="textarea"
          rows={4}
          placeholder="Texte affiché sur votre fiche"
          value={body}
          onChange={(e) => setBody(e.target.value)}
          maxLength={5000}
        />
        {kind === 'PROMO' ? (
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(200px,1fr))', gap: 10 }}>
            <input name="promoLabel" className="input" placeholder="Offre en gros caractères (ex. -10 %)" maxLength={32} />
            <label style={{ display: 'flex', flexDirection: 'column', gap: 4, fontSize: 12, color: 'var(--muted)', fontWeight: 600 }}>
              Valable jusqu&apos;au
              <input name="validTo" type="date" className="input" />
            </label>
          </div>
        ) : null}
        <FileDrop name="image" accept="image/*" label="+ Ajouter une image (facultatif, sinon votre photo principale)" />

        <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
          <b>Où sera diffusée cette publication ?</b>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(200px,1fr))', gap: 8 }}>
            {channels.map((ch) => {
              const on = ch.always || (!ch.locked && selected.has(ch.key));
              return (
                <label
                  key={ch.key}
                  style={{
                    display: 'flex',
                    gap: 10,
                    alignItems: 'center',
                    padding: '10px 12px',
                    borderRadius: 12,
                    border: `1.5px solid ${on ? 'var(--green)' : 'var(--line)'}`,
                    background: on ? 'var(--mint-2)' : '#fff',
                    cursor: ch.locked || ch.always ? 'default' : 'pointer',
                    opacity: ch.locked ? 0.6 : 1,
                  }}
                >
                  <input
                    type="checkbox"
                    name="channels"
                    value={ch.key}
                    checked={on}
                    disabled={ch.locked || ch.always}
                    onChange={(e) =>
                      setSelected((s) => {
                        const n = new Set(s);
                        if (e.target.checked) n.add(ch.key);
                        else n.delete(ch.key);
                        return n;
                      })
                    }
                    style={{ accentColor: 'var(--green)', width: 18, height: 18 }}
                  />
                  <div>
                    <div style={{ fontSize: 14, fontWeight: 700 }}>{ch.label}</div>
                    <div style={{ fontSize: 12, color: 'var(--muted)' }}>{ch.detail}</div>
                  </div>
                </label>
              );
            })}
          </div>
        </div>

        {schedule && canSchedule ? (
          <label style={{ display: 'flex', flexDirection: 'column', gap: 4, fontSize: 13, color: 'var(--muted)', fontWeight: 600, maxWidth: 280 }}>
            Date et heure de publication
            <input name="publishAt" type="datetime-local" className="input" required />
          </label>
        ) : null}
        {state.status === 'error' ? (
          <div className="alert alert-error" role="alert">
            {state.message}
          </div>
        ) : null}
        <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap', alignItems: 'center' }}>
          <button type="submit" className="btn btn-brand" disabled={pending}>
            {pending ? 'Publication…' : schedule ? 'Programmer' : 'Publier maintenant'}
          </button>
          {canSchedule ? (
            <button type="button" className="btn btn-outline" onClick={() => setSchedule((s) => !s)}>
              {schedule ? 'Publier tout de suite plutôt' : 'Programmer plus tard'}
            </button>
          ) : (
            <span style={{ fontSize: 12, color: 'var(--muted)' }}>Programmation : offre Premium</span>
          )}
        </div>
      </form>
    </div>
  );
}
