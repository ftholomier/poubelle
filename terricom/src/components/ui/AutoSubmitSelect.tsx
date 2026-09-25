'use client';

import type { CSSProperties, ReactNode } from 'react';

/** Liste déroulante qui soumet son formulaire dès qu'on change de valeur (filtres). */
export function AutoSubmitSelect({
  name,
  defaultValue,
  children,
  className,
  style,
  label,
}: {
  name: string;
  defaultValue?: string;
  children: ReactNode;
  className?: string;
  style?: CSSProperties;
  label: string;
}) {
  return (
    <select
      name={name}
      defaultValue={defaultValue}
      className={className}
      style={style}
      aria-label={label}
      onChange={(e) => e.currentTarget.form?.requestSubmit()}
    >
      {children}
    </select>
  );
}
