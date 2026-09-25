import Link from 'next/link';

export default function PortalNotFound() {
  return (
    <div className="container" style={{ paddingTop: 80, paddingBottom: 100, textAlign: 'center' }}>
      <div className="stamp" style={{ marginBottom: 18 }}>
        Page introuvable
      </div>
      <h1 className="h-page" style={{ margin: '0 auto 12px', maxWidth: 640 }}>
        Cette adresse a peut-être fermé ses portes.
      </h1>
      <p style={{ color: 'var(--muted)', fontSize: 17, margin: '0 0 24px' }}>La page demandée n&apos;existe pas ou plus. Explorez la carte pour retrouver vos pros.</p>
      <Link href="./" className="btn btn-dark">
        Retour à l&apos;accueil
      </Link>
    </div>
  );
}
