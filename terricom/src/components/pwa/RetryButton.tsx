'use client';

/** Recharge la page (retour de la connexion). */
export function RetryButton({ className = 'btn btn-dark', label = 'Réessayer' }: { className?: string; label?: string }) {
  return (
    <button type="button" className={className} onClick={() => window.location.reload()}>
      {label}
    </button>
  );
}
