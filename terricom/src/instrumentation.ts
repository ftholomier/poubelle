/**
 * Contrôles au démarrage du serveur.
 *
 * Next.js compare les réécritures du proxy à l'adresse d'écoute : avec une adresse IP explicite
 * dans HOSTNAME (ex. 127.0.0.1), la réécriture des portails servis sur leur propre domaine est
 * traitée comme externe et boucle. Il faut écouter sur 0.0.0.0 (image Docker, manifestes) ou un nom.
 */
export function register() {
  if (process.env.NEXT_RUNTIME !== 'nodejs') return;
  const host = process.env.HOSTNAME ?? '';
  const isIpLiteral = /^\d{1,3}(\.\d{1,3}){3}$/.test(host) || host.includes(':');
  if (isIpLiteral && host !== '0.0.0.0' && host !== '::') {
    console.warn(
      JSON.stringify({
        level: 'warn',
        msg: `HOSTNAME=${host} : les portails servis sur leur propre domaine ne fonctionneront pas. Utilisez HOSTNAME=0.0.0.0.`,
      }),
    );
  }
}
