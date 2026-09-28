# Sécurité

## Identification et sessions

- Mots de passe hachés avec **scrypt** (sel aléatoire), 10 caractères minimum, comparaison en temps constant.
- **Double authentification TOTP** (RFC 6238) avec protection contre le rejeu d’un code et 10 codes de
  secours à usage unique (hachés). Obligatoire pour l’exploitant et les administrateurs ; une collectivité
  peut l’exiger pour toute son équipe. Secret TOTP chiffré en base (AES-256-GCM, `DATA_ENCRYPTION_KEY`).
- Verrouillage du compte 15 minutes après 5 échecs, alerte email au titulaire, message d’erreur identique
  que l’email existe ou non (pas d’énumération de comptes).
- Sessions en base : jeton aléatoire de 256 bits en cookie `HttpOnly`, `Secure`, `SameSite=Lax`, seul son
  empreinte SHA-256 est stockée ; expiration après 14 jours d’inactivité et au plus tard après 30 jours ;
  liste et révocation des sessions depuis « Mon compte ».

## Autorisations (RBAC)

Rôles : super administrateur, support et commercial (exploitant) ; admin territorial, chargé de
communication (territoire) ; admin et agent communal (commune) ; titulaire et collaborateur (entreprise).
Chaque action serveur revérifie le périmètre (`src/server/authz.ts`, `loadBoContext`) : un agent communal ne
voit que les entreprises de sa commune, un professionnel que ses établissements. L’accès support de
l’exploitant à un back-office est temporaire (30 minutes), justifié (ticket ou motif), visible par un bandeau
et journalisé.

## Traçabilité

Journal d’audit **en ajout seul et chaîné par hachage** : chaque entrée contient l’empreinte SHA-256 de la
précédente ; un déclencheur PostgreSQL refuse toute modification ou suppression (hors purge de rétention
explicitement autorisée). L’intégrité se vérifie depuis la console. Sont tracés : connexions et échecs,
validations et refus de revendications, modifications de fiches, modération, envois, imports, configuration,
accès support, consultation de documents privés, opérations RGPD et de facturation.

## Protection de l’application

- Politique de sécurité du contenu stricte avec **nonce par requête** (`script-src 'nonce-…' 'strict-dynamic'`),
  `frame-ancestors 'self'`, `object-src 'none'`, `base-uri 'self'`, `form-action 'self'`.
- En-têtes : HSTS (2 ans, preload), `X-Content-Type-Options: nosniff`, `Referrer-Policy`,
  `Permissions-Policy`, `Cross-Origin-Opener-Policy`, `X-Frame-Options`.
- Actions serveur protégées contre la falsification de requêtes (vérification de l’origine par Next.js),
  validation systématique des entrées (Zod), requêtes SQL paramétrées (Drizzle).
- **Limitation de débit** en base (connexion, double authentification, inscription, codes de revendication,
  invitations, imports, envois, assistants IA, formulaires publics) et à l’Ingress ; pièges à robots sur les
  formulaires publics.
- Liens de clic des newsletters signés (HMAC), désinscription en un clic, jetons d’invitation à usage unique
  et limités dans le temps (7 jours), stockés hachés.
- Exports CSV protégés contre l’injection de formules ; aperçu des emails servi dans un cadre isolé
  (`sandbox`, CSP sans script).

## Fichiers

Contrôle du type réel (signature binaire), taille maximale, réencodage complet des images (suppression
des métadonnées dont la géolocalisation), analyse antivirus ClamAV des documents, stockage privé des
documents sensibles (Kbis, CV, pièces commerciales) servis après contrôle d’habilitation avec
`Content-Security-Policy: sandbox`, consultation journalisée.

## Infrastructure

Conteneurs non-root en système de fichiers en lecture seule, capacités Linux retirées, profil Pod Security
« restricted », NetworkPolicy en refus par défaut, comptes de service Kubernetes à privilèges minimaux,
secrets dans un coffre-fort (External Secrets), sauvegardes chiffrées, hébergement en France. Les images sont
publiées avec SBOM et attestation de provenance. Signalement de vulnérabilités :
`/.well-known/security.txt` (securite@terricom.fr).
