import Link from 'next/link';

/** Menu du mini-site d'un établissement : accueil de la fiche, pages supplémentaires, contact. */
export function EstSiteNav({
  base,
  path,
  name,
  pages,
  current,
}: {
  base: string;
  path: string;
  name: string;
  pages: { id: string; slug: string; title: string }[];
  current: string | null;
}) {
  return (
    <nav className="container est-nav" aria-label={`Pages de ${name}`}>
      <Link href={`${base}${path}`} aria-current={current === null ? 'page' : undefined}>
        Accueil
      </Link>
      {pages.map((pg) => (
        <Link key={pg.id} href={`${base}${path}/${pg.slug}`} aria-current={current === pg.slug ? 'page' : undefined}>
          {pg.title}
        </Link>
      ))}
      <Link href={`${base}${path}#message`}>Contact</Link>
    </nav>
  );
}
