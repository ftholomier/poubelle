'use client';

import { useRouter } from 'next/navigation';
import { useState } from 'react';

/** Recherche en langage naturel de l'accueil (« un fromage à offrir », « réparer ma chaudière »…). */
export function HeroSearch({ base, prompts }: { base: string; prompts: { label: string; query: string }[] }) {
  const router = useRouter();
  const [q, setQ] = useState('');
  const go = (query: string) => router.push(`${base}/explorer${query ? `?q=${encodeURIComponent(query)}` : ''}`);
  return (
    <>
      <form
        className="hero-search"
        role="search"
        onSubmit={(e) => {
          e.preventDefault();
          go(q.trim());
        }}
      >
        <div aria-hidden="true" style={{ display: 'grid', placeItems: 'center', padding: '0 6px 0 10px', color: 'var(--brick)', fontSize: 20 }}>
          ✦
        </div>
        <label htmlFor="hero-q" className="sr-only">
          Rechercher un commerce, un artisan, un produit ou un service
        </label>
        <input
          id="hero-q"
          value={q}
          onChange={(e) => setQ(e.target.value)}
          placeholder="Demandez ce que vous voulez : « un fromage à offrir », « réparer ma chaudière »…"
          autoComplete="off"
          enterKeyHint="search"
        />
        <button type="submit" className="btn btn-brand" style={{ padding: '0 26px', borderRadius: 12 }}>
          Rechercher
        </button>
      </form>
      <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginTop: 14 }}>
        {prompts.map((p) => (
          <button key={p.label} type="button" className="hero-prompt" onClick={() => go(p.query)}>
            {p.label}
          </button>
        ))}
      </div>
    </>
  );
}
