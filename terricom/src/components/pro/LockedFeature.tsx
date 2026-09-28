import Link from 'next/link';

/** Fonction non incluse dans l'offre actuelle : ce qu'elle apporte et le lien vers « Mon offre ». */
export function LockedFeature({ base, plan, title, text, points }: { base: string; plan: string; title: string; text: string; points: string[] }) {
  return (
    <div className="card locked-feature">
      <span className="tag" style={{ background: 'var(--lilac)', color: 'var(--lilac-fg)', alignSelf: 'flex-start' }}>
        Offre {plan}
      </span>
      <h2 className="display" style={{ fontSize: 28, margin: 0, letterSpacing: '-0.02em' }}>
        {title}
      </h2>
      <p style={{ margin: 0, fontSize: 15, color: 'var(--muted-3)', lineHeight: 1.55, maxWidth: 620 }}>{text}</p>
      <ul style={{ margin: 0, paddingLeft: 20, display: 'flex', flexDirection: 'column', gap: 6, fontSize: 14 }}>
        {points.map((p) => (
          <li key={p}>{p}</li>
        ))}
      </ul>
      <Link href={`${base}/offre`} className="btn btn-dark" style={{ alignSelf: 'flex-start' }}>
        Découvrir l’offre {plan}
      </Link>
    </div>
  );
}
