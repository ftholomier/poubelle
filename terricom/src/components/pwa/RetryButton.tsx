'use client';

/** Recharge la page (retour de la connexion). */
export function RetryButton({ className = 'btn btn-dark' }: { className?: string }) {
  return (
    <button type="button" className={className} onClick={() => window.location.reload()}>
      Réessayer
    </button>
  );
}
