'use client';

import { useState } from 'react';

/** Zone de dépôt de fichier aux couleurs de la charte (remplace le bouton natif du navigateur). */
export function FileDrop({
  name,
  accept,
  label,
  multiple,
  required,
}: {
  name: string;
  accept?: string;
  label: string;
  multiple?: boolean;
  required?: boolean;
}) {
  const [files, setFiles] = useState<string[]>([]);
  return (
    <label
      style={{
        border: `2px dashed ${files.length ? 'var(--green)' : 'var(--sand-3)'}`,
        borderRadius: 10,
        padding: 14,
        textAlign: 'center',
        fontSize: 13,
        color: files.length ? 'var(--green)' : 'var(--muted)',
        fontWeight: 600,
        cursor: 'pointer',
        display: 'block',
        background: files.length ? 'var(--mint-2)' : 'transparent',
      }}
    >
      {files.length ? `✓ ${files.join(', ')}` : label}
      <input
        type="file"
        name={name}
        accept={accept}
        multiple={multiple}
        required={required}
        className="sr-only"
        onChange={(e) => setFiles([...(e.target.files ?? [])].map((f) => f.name))}
      />
    </label>
  );
}
