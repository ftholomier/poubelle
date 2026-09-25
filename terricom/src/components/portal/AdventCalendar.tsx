'use client';

import Link from 'next/link';
import { useMemo } from 'react';
import { useStoredValue } from '@/lib/hooks/useStoredValue';

type Door = { day: number; open: boolean; title: string | null; path: string | null };

/** Calendrier de l'Avent : les cases du jour et des jours passés se révèlent au clic. */
export function AdventCalendar({ doors, base, storageKey }: { doors: Door[]; base: string; storageKey: string }) {
  const [raw, setRaw] = useStoredValue(storageKey);
  const revealed = useMemo<number[]>(() => {
    try {
      const v = JSON.parse(raw ?? '[]');
      return Array.isArray(v) ? v.filter((d) => typeof d === 'number') : [];
    } catch {
      return [];
    }
  }, [raw]);
  const toggle = (day: number) => {
    const next = revealed.includes(day) ? revealed.filter((d) => d !== day) : [...revealed, day];
    setRaw(JSON.stringify(next));
  };
  return (
    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill,minmax(130px,1fr))', gap: 10 }}>
      {doors.map((d) => {
        const shown = d.open && revealed.includes(d.day);
        const bg = shown ? 'var(--amber)' : d.open ? 'rgba(255,243,230,.12)' : 'rgba(255,243,230,.04)';
        const fg = shown ? 'var(--ink)' : d.open ? '#FFF3E6' : '#A0706A';
        const bd = shown || d.open ? 'var(--amber)' : '#7E3A33';
        return (
          <button
            key={d.day}
            type="button"
            className="advent-door"
            disabled={!d.open}
            onClick={() => toggle(d.day)}
            aria-pressed={shown}
            aria-label={d.open ? `Case du ${d.day}${shown && d.title ? ` : ${d.title}` : ''}` : `Case du ${d.day}, pas encore ouverte`}
            style={{ background: bg, color: fg, borderColor: bd, cursor: d.open ? 'pointer' : 'default' }}
          >
            <span className="display" style={{ fontSize: 30, lineHeight: 1 }}>
              {d.day}
            </span>
            <span style={{ fontSize: 12, fontWeight: 600, lineHeight: 1.3 }}>
              {shown ? d.title : d.open ? 'Cliquez !' : 'Patience…'}
              {shown && d.path ? (
                <Link
                  href={`${base}${d.path}`}
                  onClick={(e) => e.stopPropagation()}
                  style={{ display: 'block', marginTop: 4, color: 'var(--ink)', fontWeight: 800 }}
                >
                  Voir →
                </Link>
              ) : null}
            </span>
          </button>
        );
      })}
    </div>
  );
}
