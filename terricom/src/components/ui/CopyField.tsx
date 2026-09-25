'use client';

import { useState } from 'react';

/** Adresse ou valeur à copier (flux, clés, secrets) : champ en lecture seule et bouton « Copier ». */
export function CopyField({ label, value, secret = false }: { label: string; value: string; secret?: boolean }) {
  const [copied, setCopied] = useState(false);
  const [shown, setShown] = useState(!secret);
  const copy = async () => {
    try {
      await navigator.clipboard.writeText(value);
      setCopied(true);
      setTimeout(() => setCopied(false), 1800);
    } catch {
      /* copie refusée par le navigateur : la valeur reste sélectionnable */
    }
  };
  return (
    <div className="copy-field">
      <span className="copy-field-label">{label}</span>
      <div className="copy-field-row">
        <input
          className="input mono"
          readOnly
          value={shown ? value : '•'.repeat(Math.min(value.length, 32))}
          aria-label={label}
          onFocus={(e) => e.target.select()}
        />
        {secret ? (
          <button type="button" className="btn btn-outline btn-sm" onClick={() => setShown((s) => !s)} aria-pressed={shown}>
            {shown ? 'Masquer' : 'Afficher'}
          </button>
        ) : null}
        <button type="button" className="btn btn-dark btn-sm" onClick={copy}>
          {copied ? 'Copié ✓' : 'Copier'}
        </button>
      </div>
    </div>
  );
}
