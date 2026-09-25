'use client';

import { useState } from 'react';

/** Zone de dépôt de fichier aux couleurs de la charte (remplace le bouton natif du navigateur). */
export function FileDrop({
  name,
  accept,
  label,
  multiple,
  required,
  dark,
  form,
}: {
  name: string;
  accept?: string;
  label: string;
  multiple?: boolean;
  required?: boolean;
  /** Variante pour fond sombre (panneaux du back-office). */
  dark?: boolean;
  /** Identifiant du formulaire associé (champ placé hors de la balise form). */
  form?: string;
}) {
  const [files, setFiles] = useState<string[]>([]);
  return (
    <label
      style={{
        border: `2px dashed ${files.length ? (dark ? 'var(--amber)' : 'var(--green)') : dark ? 'var(--dark-4)' : 'var(--sand-3)'}`,
        borderRadius: 10,
        padding: 14,
        textAlign: 'center',
        fontSize: 13,
        color: files.length ? (dark ? 'var(--cream)' : 'var(--green)') : dark ? 'var(--sage)' : 'var(--muted)',
        fontWeight: 600,
        cursor: 'pointer',
        display: 'block',
        background: files.length && !dark ? 'var(--mint-2)' : 'transparent',
      }}
    >
      {files.length ? `✓ ${files.join(', ')}` : label}
      <input
        type="file"
        name={name}
        form={form}
        accept={accept}
        multiple={multiple}
        required={required}
        className="sr-only"
        onChange={(e) => setFiles([...(e.target.files ?? [])].map((f) => f.name))}
      />
    </label>
  );
}
