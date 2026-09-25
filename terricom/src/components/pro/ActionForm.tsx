'use client';

import { useActionState, useState, type CSSProperties, type ReactNode } from 'react';
import type { ActionState } from '@/app/pro/[est]/actions';
import { useToast } from '@/components/ui/Feedback';

type Fn = (prev: ActionState, form: FormData) => Promise<ActionState>;

/** Formulaire relié à une action serveur : message de succès en toast, erreur affichée sous le formulaire. */
export function ActionForm({
  action,
  children,
  style,
  className,
  resetOnSuccess = true,
}: {
  action: Fn;
  children: ReactNode | ((pending: boolean) => ReactNode);
  style?: CSSProperties;
  className?: string;
  resetOnSuccess?: boolean;
}) {
  const toast = useToast();
  const [resetKey, setResetKey] = useState(0);
  const [state, run, pending] = useActionState<ActionState, FormData>(async (prev, form) => {
    const res = await action(prev, form);
    if (res.status === 'ok') {
      if (res.message) toast(res.message);
      if (resetOnSuccess) setResetKey((k) => k + 1);
    }
    return res;
  }, { status: 'idle' });
  return (
    <form
      action={run}
      style={style}
      className={className}
      key={resetKey}
    >
      {typeof children === 'function' ? children(pending) : children}
      {state.status === 'error' ? (
        <div className="alert alert-error" role="alert" style={{ marginTop: 8 }}>
          {state.message}
        </div>
      ) : null}
    </form>
  );
}
