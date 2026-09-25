'use client';

import Link from 'next/link';
import { useEffect, useRef, useState } from 'react';
import { logoutAction } from '@/app/connexion/actions';
import { Photo } from '@/components/ui/Photo';

/** Avatar et menu du compte connecté. */
export function UserMenu({
  name,
  email,
  avatarUrl,
  links,
}: {
  name: string;
  email: string;
  avatarUrl: string | null;
  links: { href: string; label: string }[];
}) {
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);
  useEffect(() => {
    if (!open) return;
    const close = (e: MouseEvent) => ref.current && !ref.current.contains(e.target as Node) && setOpen(false);
    const esc = (e: KeyboardEvent) => e.key === 'Escape' && setOpen(false);
    document.addEventListener('mousedown', close);
    document.addEventListener('keydown', esc);
    return () => {
      document.removeEventListener('mousedown', close);
      document.removeEventListener('keydown', esc);
    };
  }, [open]);
  return (
    <div ref={ref} style={{ position: 'relative' }}>
      <button
        type="button"
        onClick={() => setOpen((o) => !o)}
        aria-haspopup="menu"
        aria-expanded={open}
        aria-label={`Compte de ${name}`}
        style={{ width: 38, height: 38, borderRadius: '50%', overflow: 'hidden', border: 0, padding: 0, display: 'block' }}
      >
        <Photo src={avatarUrl} alt="" label={name} color="#7A5BB5" />
      </button>
      {open ? (
        <div className="menu-pop" role="menu">
          <div style={{ padding: '8px 12px 10px', borderBottom: '1px solid var(--line-2)', marginBottom: 4 }}>
            <div style={{ fontWeight: 700, fontSize: 14 }}>{name}</div>
            <div style={{ fontSize: 12, color: 'var(--muted)' }}>{email}</div>
          </div>
          {links.map((l) => (
            <Link key={l.href} href={l.href} role="menuitem" onClick={() => setOpen(false)}>
              {l.label}
            </Link>
          ))}
          <form action={logoutAction}>
            <button type="submit" role="menuitem" style={{ width: '100%', color: 'var(--danger-fg)' }}>
              Se déconnecter
            </button>
          </form>
        </div>
      ) : null}
    </div>
  );
}
