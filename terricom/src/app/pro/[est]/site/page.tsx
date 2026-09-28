import { and, asc, count, eq, inArray } from 'drizzle-orm';
import Link from 'next/link';
import { deleteFormAction, deletePageAction, movePageAction, savePageAction } from './actions';
import { ActionForm } from '@/components/pro/ActionForm';
import { LockedFeature } from '@/components/pro/LockedFeature';
import { FormBuilder, MiniSiteEditor, type EditableForm } from '@/components/pro/SiteEditors';
import { Photo } from '@/components/ui/Photo';
import { variantUrl } from '@/lib/images';
import { MAX_FORMS, MAX_PAGES } from '@/lib/minisite';
import { db } from '@/server/db';
import { establishmentForms, establishmentPages, media, messages, type MiniSite } from '@/server/db/schema';
import { loadProContext } from '@/server/services/pro';
import { portalUrl } from '@/server/urls';

type Props = { params: Promise<{ est: string }>; searchParams: Promise<Record<string, string | undefined>> };

const TABS = [
  ['pages', 'Pages'],
  ['formulaires', 'Formulaires'],
  ['mini-site', 'Mini-site'],
] as const;

export default async function SitePage({ params, searchParams }: Props) {
  const { est: estId } = await params;
  const sp = await searchParams;
  const ctx = await loadProContext(estId);
  const { est, base, limits } = ctx;
  const tab = TABS.some(([k]) => k === sp.onglet) ? (sp.onglet as (typeof TABS)[number][0]) : 'pages';
  const isPublic = ['PRECREATED', 'TO_COMPLETE', 'CLAIMED', 'VALIDATED'].includes(est.status);
  const publicUrl = isPublic ? portalUrl(ctx.territory, est.path) : null;

  const [pages, forms, photos] = await Promise.all([
    db
      .select()
      .from(establishmentPages)
      .where(eq(establishmentPages.establishmentId, est.id))
      .orderBy(asc(establishmentPages.sortOrder), asc(establishmentPages.createdAt)),
    db
      .select()
      .from(establishmentForms)
      .where(eq(establishmentForms.establishmentId, est.id))
      .orderBy(asc(establishmentForms.sortOrder), asc(establishmentForms.createdAt)),
    db
      .select()
      .from(media)
      .where(and(eq(media.establishmentId, est.id), eq(media.kind, 'IMAGE'), eq(media.isPrivate, false)))
      .orderBy(asc(media.sortOrder))
      .limit(24),
  ]);
  const answers = forms.length
    ? await db
        .select({ formId: messages.formId, n: count() })
        .from(messages)
        .where(
          inArray(
            messages.formId,
            forms.map((f) => f.id),
          ),
        )
        .groupBy(messages.formId)
    : [];
  const answersOf = (id: string) => Number(answers.find((a) => a.formId === id)?.n ?? 0);

  const tabs = (
    <nav style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }} aria-label="Rubriques">
      {TABS.map(([k, l]) => (
        <Link key={k} href={`${base}/site?onglet=${k}`} className={`chip${tab === k ? ' is-active' : ''}`} aria-current={tab === k ? 'page' : undefined}>
          {l}
        </Link>
      ))}
    </nav>
  );

  if (tab === 'pages') {
    if (!limits.extraPages)
      return (
        <div className="app-content">
          {tabs}
          <LockedFeature
            base={base}
            plan="Premium"
            title="Racontez-vous sur plusieurs pages"
            text="Votre histoire, votre carte, vos engagements, vos réalisations : chaque page a sa propre adresse, référencée sur les moteurs de recherche et reliée à votre fiche."
            points={[`Jusqu’à ${MAX_PAGES} pages`, 'Intertitres, listes, liens et photo de couverture', 'Brouillons et ordre des pages']}
          />
        </div>
      );
    const editing = sp.page ? pages.find((p) => p.id === sp.page) : undefined;
    return (
      <div className="app-content">
        {tabs}
        <div
          className="split"
          style={{ ['--cols' as string]: 'minmax(260px,360px) minmax(0,1fr)', ['--gap' as string]: '18px', ['--align' as string]: 'start' }}
        >
          <section className="panel" aria-labelledby="pages-list">
            <h2 id="pages-list" className="panel-title">
              Vos pages ({pages.length}/{MAX_PAGES})
            </h2>
            {pages.length ? (
              <ol style={{ listStyle: 'none', margin: 0, padding: 0, display: 'flex', flexDirection: 'column', gap: 8 }}>
                {pages.map((p, i) => (
                  <li
                    key={p.id}
                    className="card"
                    style={{
                      borderRadius: 12,
                      padding: '10px 12px',
                      display: 'flex',
                      flexDirection: 'column',
                      gap: 6,
                      background: editing?.id === p.id ? 'var(--mint-2)' : undefined,
                    }}
                  >
                    <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                      <b style={{ flex: 1, minWidth: 0 }}>{p.title}</b>
                      <span className="tag" style={{ background: p.published ? 'var(--ok-bg)' : 'var(--sand)' }}>
                        {p.published ? 'En ligne' : 'Brouillon'}
                      </span>
                    </div>
                    <div style={{ display: 'flex', gap: 4, flexWrap: 'wrap', alignItems: 'center' }}>
                      <Link href={`${base}/site?onglet=pages&page=${p.id}`} className="btn btn-outline btn-xs">
                        Modifier
                      </Link>
                      {p.published && publicUrl ? (
                        <a href={`${publicUrl}/${p.slug}`} target="_blank" rel="noopener noreferrer" className="btn btn-ghost btn-xs">
                          Voir ↗
                        </a>
                      ) : null}
                      <form action={movePageAction} style={{ marginLeft: 'auto' }}>
                        <input type="hidden" name="estId" value={est.id} />
                        <input type="hidden" name="pageId" value={p.id} />
                        <input type="hidden" name="dir" value="up" />
                        <button type="submit" className="btn btn-ghost btn-xs" disabled={i === 0} aria-label={`Monter « ${p.title} »`}>
                          ↑
                        </button>
                      </form>
                      <form action={movePageAction}>
                        <input type="hidden" name="estId" value={est.id} />
                        <input type="hidden" name="pageId" value={p.id} />
                        <input type="hidden" name="dir" value="down" />
                        <button type="submit" className="btn btn-ghost btn-xs" disabled={i === pages.length - 1} aria-label={`Descendre « ${p.title} »`}>
                          ↓
                        </button>
                      </form>
                      <form action={deletePageAction}>
                        <input type="hidden" name="estId" value={est.id} />
                        <input type="hidden" name="pageId" value={p.id} />
                        <button type="submit" className="btn btn-ghost btn-xs" style={{ color: 'var(--danger-fg)' }} aria-label={`Supprimer « ${p.title} »`}>
                          Supprimer
                        </button>
                      </form>
                    </div>
                  </li>
                ))}
              </ol>
            ) : (
              <p style={{ margin: 0, fontSize: 14, color: 'var(--muted)' }}>
                Aucune page pour l’instant. Idées : notre histoire, la carte, nos réalisations, nos engagements.
              </p>
            )}
            {editing ? (
              <Link href={`${base}/site?onglet=pages`} className="btn btn-outline btn-sm" style={{ alignSelf: 'flex-start' }}>
                + Nouvelle page
              </Link>
            ) : null}
          </section>

          <section className="panel" aria-labelledby="page-editor">
            <h2 id="page-editor" className="panel-title">
              {editing ? `Modifier « ${editing.title} »` : 'Nouvelle page'}
            </h2>
            <ActionForm
              key={editing?.id ?? 'new'}
              action={savePageAction}
              resetOnSuccess={!editing}
              style={{ display: 'flex', flexDirection: 'column', gap: 12 }}
            >
              <input type="hidden" name="estId" value={est.id} />
              <input type="hidden" name="pageId" value={editing?.id ?? ''} />
              <label className="field">
                <span>Titre</span>
                <input name="title" className="input" required maxLength={160} defaultValue={editing?.title ?? ''} placeholder="Notre histoire" />
              </label>
              <div className="field">
                <label htmlFor="page-body">Contenu</label>
                <textarea
                  id="page-body"
                  name="body"
                  className="textarea"
                  rows={14}
                  required
                  maxLength={20000}
                  defaultValue={editing?.body ?? ''}
                  aria-describedby="page-body-help"
                  placeholder={'Tout a commencé en 1987…\n\n## Nos engagements\n- Farines de la région\n- Levain naturel'}
                />
                <small id="page-body-help">Mise en forme : « ## » pour un intertitre, « - » pour une liste, **gras**, [texte du lien](https://…).</small>
              </div>
              <fieldset className="minisite-fieldset">
                <legend>Photo de couverture</legend>
                <div className="cover-picker">
                  <label className="cover-choice">
                    <input type="radio" name="coverUrl" value="" defaultChecked={!editing?.coverUrl} />
                    <span className="cover-none">Aucune</span>
                  </label>
                  {photos.map((m) => {
                    const v = variantUrl(m, 1280);
                    const value = v === m.url || Object.values((m.variants ?? {}) as Record<string, string>).includes(v) ? v : m.url;
                    return (
                      <label key={m.id} className="cover-choice">
                        <input type="radio" name="coverUrl" value={value} defaultChecked={editing?.coverUrl === value || editing?.coverUrl === m.url} />
                        <span style={{ display: 'block', width: 92, height: 64, borderRadius: 8, overflow: 'hidden' }}>
                          <Photo src={variantUrl(m, 320)} alt={m.alt ?? ''} color={est.color} label="" />
                        </span>
                      </label>
                    );
                  })}
                </div>
              </fieldset>
              <label className="checkbox">
                <input type="checkbox" name="published" defaultChecked={editing ? editing.published : true} />
                <span>Publier la page (sinon, brouillon)</span>
              </label>
              <button type="submit" className="btn btn-brand" style={{ alignSelf: 'flex-start' }}>
                Enregistrer la page
              </button>
            </ActionForm>
          </section>
        </div>
      </div>
    );
  }

  if (tab === 'formulaires') {
    if (!limits.customForms)
      return (
        <div className="app-content">
          {tabs}
          <LockedFeature
            base={base}
            plan="Premium"
            title="Des formulaires faits pour votre métier"
            text="Demande de devis, réservation, inscription à un atelier, commande spéciale : créez vos formulaires, ils s’affichent sur votre fiche et les réponses arrivent dans votre messagerie."
            points={[`Jusqu’à ${MAX_FORMS} formulaires`, 'Textes, dates, nombres, listes de choix, cases à cocher', 'Modèles prêts à l’emploi']}
          />
        </div>
      );
    const selected = sp.form === 'nouveau' ? null : sp.form ? forms.find((f) => f.id === sp.form) : undefined;
    const initial: EditableForm | null =
      selected === null
        ? { id: null, title: '', intro: '', submitLabel: 'Envoyer', successText: '', isActive: true, fields: [] }
        : selected
          ? {
              id: selected.id,
              title: selected.title,
              intro: selected.intro ?? '',
              submitLabel: selected.submitLabel,
              successText: selected.successText ?? '',
              isActive: selected.isActive,
              fields: selected.fields,
            }
          : null;
    return (
      <div className="app-content">
        {tabs}
        <div
          className="split"
          style={{ ['--cols' as string]: 'minmax(260px,360px) minmax(0,1fr)', ['--gap' as string]: '18px', ['--align' as string]: 'start' }}
        >
          <section className="panel" aria-labelledby="forms-list">
            <h2 id="forms-list" className="panel-title">
              Vos formulaires ({forms.length}/{MAX_FORMS})
            </h2>
            {forms.length ? (
              <ul style={{ listStyle: 'none', margin: 0, padding: 0, display: 'flex', flexDirection: 'column', gap: 8 }}>
                {forms.map((f) => (
                  <li
                    key={f.id}
                    className="card"
                    style={{
                      borderRadius: 12,
                      padding: '10px 12px',
                      display: 'flex',
                      flexDirection: 'column',
                      gap: 6,
                      background: selected?.id === f.id ? 'var(--mint-2)' : undefined,
                    }}
                  >
                    <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                      <b style={{ flex: 1, minWidth: 0 }}>{f.title}</b>
                      <span className="tag" style={{ background: f.isActive ? 'var(--ok-bg)' : 'var(--sand)' }}>
                        {f.isActive ? 'Affiché' : 'Masqué'}
                      </span>
                    </div>
                    <div style={{ fontSize: 12, color: 'var(--muted)' }}>
                      {f.fields.length} question{f.fields.length > 1 ? 's' : ''} · {answersOf(f.id)} réponse{answersOf(f.id) > 1 ? 's' : ''}
                    </div>
                    <div style={{ display: 'flex', gap: 4 }}>
                      <Link href={`${base}/site?onglet=formulaires&form=${f.id}`} className="btn btn-outline btn-xs">
                        Modifier
                      </Link>
                      <Link href={`${base}/messages`} className="btn btn-ghost btn-xs">
                        Réponses
                      </Link>
                      <form action={deleteFormAction} style={{ marginLeft: 'auto' }}>
                        <input type="hidden" name="estId" value={est.id} />
                        <input type="hidden" name="formId" value={f.id} />
                        <button type="submit" className="btn btn-ghost btn-xs" style={{ color: 'var(--danger-fg)' }} aria-label={`Supprimer « ${f.title} »`}>
                          Supprimer
                        </button>
                      </form>
                    </div>
                  </li>
                ))}
              </ul>
            ) : (
              <p style={{ margin: 0, fontSize: 14, color: 'var(--muted)' }}>Aucun formulaire pour l’instant.</p>
            )}
            {forms.length < MAX_FORMS ? (
              <Link href={`${base}/site?onglet=formulaires&form=nouveau`} className="btn btn-brand btn-sm" style={{ alignSelf: 'flex-start' }}>
                + Nouveau formulaire
              </Link>
            ) : null}
          </section>
          <section className="panel" aria-labelledby="form-editor">
            <h2 id="form-editor" className="panel-title">
              {initial ? (initial.id ? `Modifier « ${initial.title} »` : 'Nouveau formulaire') : 'Formulaires personnalisés'}
            </h2>
            {initial ? (
              <FormBuilder key={initial.id ?? 'new'} estId={est.id} initial={initial} />
            ) : (
              <p style={{ margin: 0, color: 'var(--muted)', fontSize: 14, lineHeight: 1.55 }}>
                Choisissez un formulaire à modifier ou créez-en un : devis, réservation, inscription… Il s’affichera sur votre fiche, et chaque réponse arrivera
                dans vos messages avec un email de notification.
              </p>
            )}
          </section>
        </div>
      </div>
    );
  }

  if (!limits.miniSite)
    return (
      <div className="app-content">
        {tabs}
        <LockedFeature
          base={base}
          plan="Communication"
          title="Votre fiche devient votre site"
          text="Couleur de marque, en-tête personnalisé, accroche, bouton d’action et ordre des sections : votre fiche prend l’allure d’un vrai site, sans rien perdre du référencement du territoire."
          points={['Menu reliant vos pages', 'Bouton principal : commander, réserver, appeler…', 'Sections dans l’ordre de votre choix']}
        />
      </div>
    );
  const bookable = est.appointmentsEnabled && ctx.modules.has('APPOINTMENTS') && limits.appointments;
  const targets = [
    ...(est.phone ? [{ value: 'tel', label: 'Appeler' }] : []),
    ...(est.lat && est.lng ? [{ value: 'itineraire', label: 'Itinéraire' }] : []),
    { value: 'contact', label: 'Formulaire de contact' },
    ...(bookable ? [{ value: 'rdv', label: 'Prise de rendez-vous' }] : []),
    ...(limits.customForms ? forms.filter((f) => f.isActive).map((f) => ({ value: `form:${f.id}`, label: `Formulaire « ${f.title} »` })) : []),
    ...(limits.extraPages ? pages.filter((p) => p.published).map((p) => ({ value: `page:${p.slug}`, label: `Page « ${p.title} »` })) : []),
  ];
  return (
    <div className="app-content">
      {tabs}
      <div className="split" style={{ ['--cols' as string]: 'minmax(0,1.4fr) minmax(0,1fr)', ['--gap' as string]: '18px', ['--align' as string]: 'start' }}>
        <section className="panel" aria-labelledby="minisite-title">
          <h2 id="minisite-title" className="panel-title">
            Mini-site
          </h2>
          <MiniSiteEditor estId={est.id} mini={(est.miniSite ?? {}) as MiniSite} themeColor={est.themeColor} targets={targets} publicUrl={publicUrl} />
        </section>
        <section className="panel" aria-labelledby="minisite-help">
          <h2 id="minisite-help" className="panel-title">
            Ce qui change sur votre fiche
          </h2>
          <ul style={{ margin: 0, paddingLeft: 18, fontSize: 14, lineHeight: 1.6 }}>
            <li>Liens, onglets et étiquettes prennent votre couleur.</li>
            <li>Un menu relie votre fiche à vos pages supplémentaires.</li>
            <li>L’accroche et le bouton principal apparaissent sous votre nom, ou sur le bandeau de couleur.</li>
            <li>Les sections suivent l’ordre choisi ; le formulaire de contact reste toujours accessible.</li>
          </ul>
          <p style={{ margin: 0, fontSize: 13, color: 'var(--muted)' }}>
            Votre fiche garde son adresse sur le portail du territoire : vous profitez de son référencement et de ses visiteurs.
          </p>
        </section>
      </div>
    </div>
  );
}
