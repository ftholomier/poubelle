import type { Metadata } from 'next';
import Link from 'next/link';
import { autoTranslateTerritoryAction, saveTerritoryTranslationsAction, translateListingsAction } from '../actions';
import { ActionForm } from '@/components/pro/ActionForm';
import { SubmitButton } from '@/components/ui/SubmitButton';
import { fmtInt } from '@/lib/format';
import { aiEnabled } from '@/server/ai/client';
import type { TerritorySettings } from '@/server/db/schema';
import { loadBoContext, requireTerritoryLevel } from '@/server/services/backoffice';
import { getEnabledModules } from '@/server/services/territories';
import { LOCALE_LABELS, listingTranslationStats, TERRITORY_TEXT_FIELDS, territoryFrenchTexts, TRANSLATED_LOCALES } from '@/server/services/translations';
import { portalUrl } from '@/server/urls';

export const metadata: Metadata = { title: 'Langues du portail' };

/** Portail multilingue : traductions des textes du portail et avancement de la traduction des fiches. */
export default async function LanguagesPage() {
  const ctx = await loadBoContext();
  requireTerritoryLevel(ctx);
  const t = ctx.territory;
  const admin = ctx.access === 'ADMIN';
  const modules = await getEnabledModules(t.id);
  const back = (
    <Link href="/collectivite/personnalisation" style={{ fontSize: 13, fontWeight: 600 }}>
      ← Personnalisation
    </Link>
  );
  if (!modules.has('MULTILINGUAL')) {
    return (
      <div className="app-content">
        {back}
        <section className="bo-card" style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
          <b>Portail multilingue</b>
          <p style={{ margin: 0, color: 'var(--muted)', fontSize: 14 }}>
            Le module Multilingue n’est pas activé : le portail est proposé en français uniquement. Avec le module, les visiteurs choisissent l’anglais ou
            l’allemand ; les fiches sont traduites automatiquement. Contactez votre interlocuteur terricom pour l’activer.
          </p>
        </section>
      </div>
    );
  }
  const fr = territoryFrenchTexts(t);
  const tr = ((t.settings ?? {}) as TerritorySettings).translations ?? {};
  const stats = await listingTranslationStats(t.id);
  const ai = aiEnabled();
  const pct = stats.withText ? Math.round((stats.translated / stats.withText) * 100) : 100;
  const fields = TERRITORY_TEXT_FIELDS.filter((f) => fr[f.key]);

  return (
    <div className="app-content">
      {back}
      <div className="split" style={{ ['--cols' as string]: 'minmax(0,1.7fr) minmax(0,1fr)', ['--gap' as string]: '18px', ['--align' as string]: 'start' }}>
        <section className="bo-card" style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
          <div>
            <b>Textes du portail</b>
            <p style={{ margin: '4px 0 0', fontSize: 13, color: 'var(--muted)' }}>
              Traduction des textes rédigés par la collectivité (accroche, accueil, rubriques). Un champ laissé vide affiche le texte français. Les libellés de
              l’interface (menus, boutons, horaires) sont déjà traduits.
            </p>
          </div>
          {admin ? (
            <ActionForm action={autoTranslateTerritoryAction} resetOnSuccess={false}>
              <SubmitButton className="btn btn-outline btn-sm" pendingLabel="Traduction en cours…" disabled={!ai}>
                ✦ Proposer les traductions manquantes
              </SubmitButton>
              {!ai ? (
                <span style={{ fontSize: 12, color: 'var(--muted)', marginLeft: 8 }}>Assistant IA non configuré : saisie manuelle uniquement.</span>
              ) : null}
            </ActionForm>
          ) : null}
          {fields.length ? (
            <ActionForm action={saveTerritoryTranslationsAction} resetOnSuccess={false} style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
              {fields.map((f) => (
                <fieldset key={f.key} className="i18n-row">
                  <legend>{f.label}</legend>
                  <div className="i18n-source" lang="fr">
                    <span className="i18n-lang">FR</span>
                    {fr[f.key]}
                  </div>
                  {f.hint ? <small style={{ color: 'var(--muted)', fontSize: 12 }}>{f.hint}</small> : null}
                  {TRANSLATED_LOCALES.map((l) => {
                    const id = `i18n-${l}-${f.key}`;
                    const value = tr[l]?.[f.key] ?? '';
                    return (
                      <div key={l} className="i18n-input">
                        <label htmlFor={id} className="i18n-lang" title={LOCALE_LABELS[l]}>
                          {l.toUpperCase()}
                          <span className="sr-only">
                            {' '}
                            {f.label} ({LOCALE_LABELS[l]})
                          </span>
                        </label>
                        {f.multiline ? (
                          <textarea
                            id={id}
                            name={`${l}:${f.key}`}
                            lang={l}
                            className="textarea"
                            rows={2}
                            maxLength={600}
                            defaultValue={value}
                            disabled={!admin}
                          />
                        ) : (
                          <input id={id} name={`${l}:${f.key}`} lang={l} className="input" maxLength={600} defaultValue={value} disabled={!admin} />
                        )}
                      </div>
                    );
                  })}
                </fieldset>
              ))}
              {admin ? (
                <SubmitButton className="btn btn-brand btn-sm" pendingLabel="Enregistrement…" style={{ alignSelf: 'flex-start', marginTop: 8 }}>
                  Enregistrer les traductions
                </SubmitButton>
              ) : null}
            </ActionForm>
          ) : (
            <p style={{ margin: 0, color: 'var(--muted)', fontSize: 14 }}>
              Aucun texte personnalisé à traduire : le portail utilise les textes par défaut, déjà traduits.
            </p>
          )}
        </section>

        <div style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
          <section className="bo-card" style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
            <b>Fiches des professionnels</b>
            <div style={{ display: 'flex', alignItems: 'baseline', gap: 8 }}>
              <span className="display" style={{ fontSize: 34 }}>
                {pct} %
              </span>
              <span style={{ fontSize: 13, color: 'var(--muted)' }}>
                {fmtInt(stats.translated)} fiche{stats.translated > 1 ? 's' : ''} traduite{stats.translated > 1 ? 's' : ''} sur {fmtInt(stats.withText)} rédigée
                {stats.withText > 1 ? 's' : ''}
              </span>
            </div>
            <div className="bar" aria-hidden="true">
              <span style={{ width: `${pct}%` }} />
            </div>
            <p style={{ margin: 0, fontSize: 13, color: 'var(--muted)', lineHeight: 1.5 }}>
              L’accroche et la description sont traduites automatiquement après chaque modification par le professionnel. Sans traduction, la fiche affiche le
              texte français avec la mention « Texte original en français ».
            </p>
            {admin ? (
              <ActionForm action={translateListingsAction} resetOnSuccess={false}>
                <SubmitButton className="btn btn-dark btn-sm" pendingLabel="Programmation…" disabled={!ai || stats.translated >= stats.withText}>
                  Traduire les fiches manquantes
                </SubmitButton>
              </ActionForm>
            ) : null}
          </section>
          <section className="bo-card" style={{ display: 'flex', flexDirection: 'column', gap: 8, fontSize: 14 }}>
            <b>Aperçu</b>
            {TRANSLATED_LOCALES.map((l) => (
              <a key={l} href={portalUrl(t, `/?lang=${l}`)} target="_blank" rel="noopener noreferrer">
                Portail en {LOCALE_LABELS[l].toLowerCase()} ↗
              </a>
            ))}
            <span style={{ fontSize: 12, color: 'var(--muted)' }}>
              Les pages d’information légales (mentions légales, données personnelles, accessibilité) restent en français, qui fait foi.
            </span>
          </section>
        </div>
      </div>
    </div>
  );
}
