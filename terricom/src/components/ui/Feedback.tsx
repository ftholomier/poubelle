'use client';

import { createContext, useCallback, useContext, useEffect, useRef, useState, type ReactNode } from 'react';

type ToastKind = 'ok' | 'error' | 'info';
type ToastItem = { id: number; kind: ToastKind; text: string };

const ToastCtx = createContext<(text: string, kind?: ToastKind) => void>(() => {});

/** Retours discrets après une action (« Enregistré · publié instantanément »). */
export function ToastProvider({ children }: { children: ReactNode }) {
  const [items, setItems] = useState<ToastItem[]>([]);
  const seq = useRef(0);
  const push = useCallback((text: string, kind: ToastKind = 'ok') => {
    const id = ++seq.current;
    setItems((prev) => [...prev.slice(-2), { id, kind, text }]);
    setTimeout(() => setItems((prev) => prev.filter((t) => t.id !== id)), 4200);
  }, []);
  return (
    <ToastCtx.Provider value={push}>
      {children}
      <div className="toast-zone" role="status" aria-live="polite">
        {items.map((t) => (
          <div key={t.id} className={`toast toast-${t.kind}`}>
            {t.text}
          </div>
        ))}
      </div>
    </ToastCtx.Provider>
  );
}

export function useToast() {
  return useContext(ToastCtx);
}

/** Interrupteur accessible (role="switch"). */
export function Switch({
  checked,
  onChange,
  label,
  disabled,
}: {
  checked: boolean;
  onChange: (v: boolean) => void;
  label: string;
  disabled?: boolean;
}) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={checked}
      aria-label={label}
      className="switch"
      disabled={disabled}
      onClick={() => onChange(!checked)}
    />
  );
}

/** Fenêtre modale plein écran (suivi commercial, confirmations). */
export function Modal({
  open,
  onClose,
  children,
  label,
  width = 'min(1280px, 100%)',
  height = 'min(880px, 100%)',
}: {
  open: boolean;
  onClose: () => void;
  children: ReactNode;
  label: string;
  width?: string;
  height?: string;
}) {
  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => e.key === 'Escape' && onClose();
    window.addEventListener('keydown', onKey);
    const prev = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => {
      window.removeEventListener('keydown', onKey);
      document.body.style.overflow = prev;
    };
  }, [open, onClose]);
  if (!open) return null;
  return (
    <div
      onClick={onClose}
      style={{
        position: 'fixed',
        inset: 0,
        background: 'rgba(20,32,27,.55)',
        backdropFilter: 'blur(3px)',
        zIndex: 100,
        display: 'grid',
        placeItems: 'center',
        padding: 24,
      }}
    >
      <div
        role="dialog"
        aria-modal="true"
        aria-label={label}
        onClick={(e) => e.stopPropagation()}
        style={{
          width,
          maxHeight: height,
          height: height === 'auto' ? undefined : height,
          background: 'var(--cream)',
          borderRadius: 24,
          overflow: 'hidden',
          boxShadow: '0 40px 80px rgba(0,0,0,.35)',
          display: 'flex',
          flexDirection: 'column',
        }}
      >
        {children}
      </div>
    </div>
  );
}

/** Confettis de célébration (« Fiche validée ! »). */
export function Confetti() {
  const colors = ['#F4B266', '#1F6B52', '#D95C4E', '#DCD3F3', '#D6E8B4'];
  return (
    <div aria-hidden="true" style={{ position: 'absolute', inset: '-40px -20px auto', height: 0, pointerEvents: 'none' }}>
      {Array.from({ length: 36 }, (_, i) => (
        <span
          key={i}
          style={{
            position: 'absolute',
            left: `${(i * 37) % 100}%`,
            top: 0,
            width: 8,
            height: 14,
            borderRadius: 2,
            background: colors[i % 5],
            animation: `fall ${1.6 + (i % 5) * 0.35}s ${(i % 7) * 0.12}s ease-in forwards`,
          }}
        />
      ))}
    </div>
  );
}
