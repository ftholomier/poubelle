import { saveListingTranslation } from '@/app/pro/[est]/actions';
import { ActionForm } from '@/components/pro/ActionForm';
import { SubmitButton } from '@/components/ui/SubmitButton';
import type { EstablishmentTranslations } from '@/server/db/schema';
import { LOCALE_LABELS, sourceHash, TRANSLATED_LOCALES, type TranslatedLocale } from '@/server/services/translations';

type Props = {
  estId: string;
  tagline: string | null;
  description: string | null;
  translations: EstablishmentTranslations | null;
  aiEnabled: boolean;
};

function statusOf(p: Props, l: TranslatedLocale): { label: string; tone: 'ok' | 'warn' | 'muted' } {
  const tx = p.translations?.[l];
  const current = tx?.hash === sourceHash({ tagline: p.tagline ?? '', description: p.description ?? '' });
  if (tx?.source === 'manual')
    return current ? { label: 'Rédigée par vous', tone: 'ok' } : { label: 'Rédigée par vous · le texte français a changé depuis', tone: 'warn' };
  if (tx && current) return { label: 'Traduction automatique à jour', tone: 'ok' };
  if (!p.aiEnabled) return { label: tx ? 'Traduction à mettre à jour' : 'Texte français affiché', tone: 'muted' };
  return { label: tx ? 'Mise à jour automatique en cours' : 'Traduction automatique en attente', tone: 'muted' };
}

const TONES = { ok: ['var(--mint)', 'var(--green)'], warn: ['var(--warn-bg)', 'var(--warn-fg)'], muted: ['var(--sand)', 'var(--muted)'] } as const;

/**
 * Traductions de la fiche sur un portail multilingue : l'accroche et la description sont traduites par l'IA ;
 * le professionnel peut rédiger sa propre version, prioritaire, ou revenir à la traduction automatique.
 */
export function ListingTranslations(p: Props) {
  if (!p.tagline && !p.description) return null;
  return (
    <details className="card" style={{ borderRadius: 14, padding: '10px 14px', fontSize: 13 }}>
      <summary style={{ cursor: 'pointer', fontWeight: 700 }}>Traductions de la fiche (anglais, allemand)</summary>
      <p style={{ margin: '8px 0 4px', color: 'var(--muted)', lineHeight: 1.45 }}>
        Le portail de votre territoire est proposé en plusieurs langues. Votre accroche et votre description sont traduites automatiquement ; vous pouvez écrire
        votre propre version.
      </p>
      {TRANSLATED_LOCALES.map((l) => {
        const tx = p.translations?.[l];
        const st = statusOf(p, l);
        const [bg, fg] = TONES[st.tone];
        return (
          <ActionForm
            key={l}
            action={saveListingTranslation}
            resetOnSuccess={false}
            style={{ display: 'flex', flexDirection: 'column', gap: 6, borderTop: '1px solid var(--line-2)', paddingTop: 10, marginTop: 8 }}
          >
            <input type="hidden" name="estId" value={p.estId} />
            <input type="hidden" name="locale" value={l} />
            <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8, alignItems: 'center', flexWrap: 'wrap' }}>
              <b>{LOCALE_LABELS[l]}</b>
              <span style={{ fontSize: 11, fontWeight: 700, padding: '3px 8px', borderRadius: 6, background: bg, color: fg }}>{st.label}</span>
            </div>
            <label className="field">
              <span>Accroche</span>
              <input name="tagline" className="input" lang={l} maxLength={255} defaultValue={tx?.tagline ?? ''} />
            </label>
            <label className="field">
              <span>Description</span>
              <textarea name="description" className="textarea" lang={l} rows={4} maxLength={5000} defaultValue={tx?.description ?? ''} />
            </label>
            <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
              <SubmitButton className="btn btn-dark btn-sm" name="mode" value="manual" pendingLabel="Enregistrement…">
                Enregistrer ma traduction
              </SubmitButton>
              {tx?.source === 'manual' ? (
                <SubmitButton className="btn btn-outline btn-sm" name="mode" value="auto">
                  Revenir à la traduction automatique
                </SubmitButton>
              ) : null}
            </div>
          </ActionForm>
        );
      })}
    </details>
  );
}
