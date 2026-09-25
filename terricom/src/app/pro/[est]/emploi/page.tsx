import { desc, eq } from 'drizzle-orm';
import { notFound } from 'next/navigation';
import { applicationAction, jobStatus, saveJob } from '../actions';
import { ActionForm } from '@/components/pro/ActionForm';
import { CONTRACT_TYPES, type ContractType } from '@/lib/constants';
import { fmtShortDate, fmtStamp } from '@/lib/format';
import { db } from '@/server/db';
import { jobApplications, jobs } from '@/server/db/schema';
import { loadProContext } from '@/server/services/pro';

type Props = { params: Promise<{ est: string }>; searchParams: Promise<Record<string, string | undefined>> };

const JOB_STATUS: Record<string, { label: string; bg: string }> = {
  PUBLISHED: { label: 'En ligne', bg: 'var(--ok-bg)' },
  FILLED: { label: 'Pourvue', bg: 'var(--sky)' },
  EXPIRED: { label: 'Expirée', bg: 'var(--sand)' },
  DRAFT: { label: 'Brouillon', bg: 'var(--sand)' },
};

export default async function JobsProPage({ params, searchParams }: Props) {
  const { est: estId } = await params;
  const sp = await searchParams;
  const ctx = await loadProContext(estId);
  if (!ctx.modules.has('JOBS')) notFound();
  const { est, base } = ctx;
  const [list, apps] = await Promise.all([
    db.select().from(jobs).where(eq(jobs.establishmentId, est.id)).orderBy(desc(jobs.createdAt)),
    db.select().from(jobApplications).where(eq(jobApplications.establishmentId, est.id)).orderBy(desc(jobApplications.createdAt)).limit(100),
  ]);
  const editing = sp.offre === 'nouvelle' ? null : list.find((j) => j.id === sp.offre) ?? null;
  const showForm = sp.offre === 'nouvelle' || Boolean(editing);
  return (
    <div className="app-content">
      <div className="split" style={{ ['--cols' as string]: 'minmax(0,1fr) minmax(0,1fr)', ['--gap' as string]: '18px', ['--align' as string]: 'start' }}>
        <div className="panel">
          <div style={{ display: 'flex', justifyContent: 'space-between', gap: 10 }}>
            <h2 className="panel-title">Vos offres</h2>
            <a href={`${base}/emploi?offre=nouvelle`} className="btn-link" style={{ fontSize: 13 }}>
              + Nouvelle offre
            </a>
          </div>
          {list.length ? (
            list.map((j) => (
              <div key={j.id} style={{ display: 'flex', justifyContent: 'space-between', gap: 10, padding: '10px 0', borderTop: '1px solid var(--line-2)', alignItems: 'center' }}>
                <div>
                  <div style={{ fontWeight: 700 }}>{j.title}</div>
                  <div style={{ fontSize: 12, color: 'var(--muted)' }}>
                    {CONTRACT_TYPES[j.contractType as ContractType].label} · publiée le {j.publishedAt ? fmtShortDate(j.publishedAt) : '—'}
                    {j.expiresAt ? ` · jusqu'au ${fmtShortDate(j.expiresAt)}` : ''}
                  </div>
                </div>
                <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                  <span className="tag" style={{ background: JOB_STATUS[j.status].bg }}>
                    {JOB_STATUS[j.status].label}
                  </span>
                  <a href={`${base}/emploi?offre=${j.id}`} className="btn-link" style={{ fontSize: 12 }}>
                    Modifier
                  </a>
                  <form action={jobStatus}>
                    <input type="hidden" name="estId" value={est.id} />
                    <input type="hidden" name="jobId" value={j.id} />
                    <input type="hidden" name="status" value={j.status === 'PUBLISHED' ? 'FILLED' : 'PUBLISHED'} />
                    <button type="submit" className="btn-link" style={{ fontSize: 12, color: 'var(--muted)' }}>
                      {j.status === 'PUBLISHED' ? 'Marquer pourvue' : 'Republier'}
                    </button>
                  </form>
                </div>
              </div>
            ))
          ) : (
            <p style={{ margin: 0, fontSize: 14, color: 'var(--muted)' }}>Vous n&apos;avez pas encore publié d&apos;offre. Elles apparaissent sur votre fiche et la page Emploi du territoire.</p>
          )}
        </div>
        {showForm ? (
          <ActionForm action={saveJob} className="panel" resetOnSuccess={!editing}>
            <input type="hidden" name="estId" value={est.id} />
            <input type="hidden" name="jobId" value={editing?.id ?? ''} />
            <h2 className="panel-title">{editing ? 'Modifier l’offre' : 'Nouvelle offre'}</h2>
            <input name="title" className="input" placeholder="Intitulé du poste (ex. Apprenti·e boulanger·e)" defaultValue={editing?.title} required maxLength={255} />
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(160px,1fr))', gap: 10 }}>
              <select name="contractType" className="select" defaultValue={editing?.contractType ?? 'CDI'}>
                {(Object.keys(CONTRACT_TYPES) as ContractType[]).map((c) => (
                  <option key={c} value={c}>
                    {CONTRACT_TYPES[c].label}
                  </option>
                ))}
              </select>
              <input name="startText" className="input" placeholder="Début (ex. Dès que possible)" defaultValue={editing?.startText ?? ''} maxLength={160} />
              <input name="salaryText" className="input" placeholder="Rémunération" defaultValue={editing?.salaryText ?? ''} maxLength={160} />
              <input name="workTimeText" className="input" placeholder="Temps de travail" defaultValue={editing?.workTimeText ?? ''} maxLength={160} />
            </div>
            <textarea name="description" className="textarea" rows={4} placeholder="Le poste, l'équipe, l'ambiance…" defaultValue={editing?.description ?? ''} required maxLength={5000} />
            <textarea name="missions" className="textarea" rows={3} placeholder="Missions (une par ligne)" defaultValue={editing?.missions.join('\n') ?? ''} maxLength={3000} />
            <textarea name="profile" className="textarea" rows={3} placeholder="Profil recherché (un point par ligne)" defaultValue={editing?.profile.join('\n') ?? ''} maxLength={3000} />
            <input name="applyEmail" type="email" className="input" placeholder="Email qui reçoit les candidatures (facultatif)" defaultValue={editing?.applyEmail ?? ''} />
            <button type="submit" className="btn btn-brand" style={{ alignSelf: 'flex-start' }}>
              {editing ? 'Enregistrer' : 'Publier l’offre'}
            </button>
          </ActionForm>
        ) : (
          <div className="panel">
            <h2 className="panel-title">Candidatures reçues</h2>
            {apps.length ? (
              apps.map((a) => {
                const job = list.find((j) => j.id === a.jobId);
                return (
                  <div key={a.id} style={{ borderTop: '1px solid var(--line-2)', paddingTop: 10, display: 'flex', flexDirection: 'column', gap: 6 }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', gap: 10 }}>
                      <b style={{ fontWeight: a.status === 'NEW' ? 800 : 600 }}>{a.fullName}</b>
                      <span style={{ fontSize: 12, color: 'var(--muted)' }}>{fmtStamp(a.createdAt)}</span>
                    </div>
                    <div style={{ fontSize: 13, color: 'var(--muted)' }}>
                      {job?.title ?? 'Offre'} · {a.email}
                      {a.phone ? ` · ${a.phone}` : ''}
                    </div>
                    {a.message ? <div style={{ fontSize: 14, background: 'var(--cream)', borderRadius: 10, padding: 10 }}>{a.message}</div> : null}
                    <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap', alignItems: 'center', fontSize: 13 }}>
                      {a.cvMediaId ? (
                        <a href={`/api/documents/${a.cvMediaId}`} target="_blank" rel="noopener">
                          Voir le CV (PDF)
                        </a>
                      ) : null}
                      <a href={`mailto:${a.email}?subject=${encodeURIComponent(`Votre candidature : ${job?.title ?? ''}`)}`}>Répondre par email</a>
                      {a.status !== 'REPLIED' ? (
                        <form action={applicationAction} style={{ display: 'flex', gap: 8 }}>
                          <input type="hidden" name="estId" value={est.id} />
                          <input type="hidden" name="applicationId" value={a.id} />
                          <input type="hidden" name="status" value="REPLIED" />
                          <button name="notify" value="accept" className="btn btn-brand btn-xs">
                            Retenir
                          </button>
                          <button name="notify" value="decline" className="btn btn-outline btn-xs">
                            Ne pas donner suite
                          </button>
                        </form>
                      ) : (
                        <span className="tag" style={{ background: 'var(--ok-bg)' }}>
                          Réponse envoyée
                        </span>
                      )}
                    </div>
                  </div>
                );
              })
            ) : (
              <p style={{ margin: 0, fontSize: 14, color: 'var(--muted)' }}>Aucune candidature pour l&apos;instant.</p>
            )}
          </div>
        )}
      </div>
    </div>
  );
}
