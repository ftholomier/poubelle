'use client';

import Link from 'next/link';
import { useT } from '@/components/portal/I18n';

/** Page introuvable du portail (dans la langue du visiteur : le fournisseur de langue est posé par la mise en page). */
export default function PortalNotFound() {
  const t = useT();
  return (
    <div className="container" style={{ paddingTop: 80, paddingBottom: 100, textAlign: 'center' }}>
      <div className="stamp" style={{ marginBottom: 18 }}>
        {t('common.notFound')}
      </div>
      <h1 className="h-page" style={{ margin: '0 auto 12px', maxWidth: 640 }}>
        {t('nf.title')}
      </h1>
      <p style={{ color: 'var(--muted)', fontSize: 17, margin: '0 0 24px' }}>{t('nf.text')}</p>
      <Link href="./" className="btn btn-dark">
        {t('nf.home')}
      </Link>
    </div>
  );
}
