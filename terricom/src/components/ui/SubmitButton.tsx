'use client';

import type { CSSProperties, ReactNode } from 'react';
import { useFormStatus } from 'react-dom';

/** Bouton d'envoi qui reflète l'état du formulaire parent (utilisable depuis un composant serveur). */
export function SubmitButton({
  children,
  pendingLabel,
  className = 'btn btn-brand',
  style,
  name,
  value,
  disabled = false,
}: {
  children: ReactNode;
  pendingLabel?: ReactNode;
  className?: string;
  style?: CSSProperties;
  name?: string;
  value?: string;
  disabled?: boolean;
}) {
  const { pending } = useFormStatus();
  return (
    <button type="submit" className={className} style={style} disabled={pending || disabled} aria-busy={pending} name={name} value={value}>
      {pending && pendingLabel ? pendingLabel : children}
    </button>
  );
}
