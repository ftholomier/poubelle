import Link from 'next/link';

/** Menu du mini-site d'un établissement : accueil de la fiche, pages supplémentaires, contact. */
export function EstSiteNav({
  base,
  path,
  pages,
  current,
  labels,
}: {
  base: string;
  path: string;
  pages: { id: string; slug: string; title: string }[];
  current: string | null;
  /** Libellés dans la langue du visiteur : nom du menu, « Accueil », « Contact ». */
  labels: { aria: string; home: string; contact: string };
}) {
  return (
    <nav className="container est-nav" aria-label={labels.aria}>
      <Link href={`${base}${path}`} aria-current={current === null ? 'page' : undefined}>
        {labels.home}
      </Link>
      {pages.map((pg) => (
        <Link key={pg.id} href={`${base}${path}/${pg.slug}`} aria-current={current === pg.slug ? 'page' : undefined}>
          {pg.title}
        </Link>
      ))}
      <Link href={`${base}${path}#message`}>{labels.contact}</Link>
    </nav>
  );
}
