# syntax=docker/dockerfile:1.7
# ─────────────────────────────────────────────────────────────────────────────
# terricom — images de production
#   docker build --target web   -t terricom-web .     (Next.js, sortie « standalone »)
#   docker build --target tools -t terricom-tools .   (worker, migrations, graine de démonstration)
# ─────────────────────────────────────────────────────────────────────────────
ARG NODE_VERSION=22

FROM node:${NODE_VERSION}-bookworm-slim AS base
ENV NEXT_TELEMETRY_DISABLED=1 \
    PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1
WORKDIR /app

# Dépendances complètes (compilation)
FROM base AS deps
COPY package.json package-lock.json ./
RUN --mount=type=cache,target=/root/.npm npm ci --no-audit --no-fund

# Dépendances d'exécution seules (worker, migrations)
FROM base AS prod-deps
COPY package.json package-lock.json ./
RUN --mount=type=cache,target=/root/.npm npm ci --omit=dev --no-audit --no-fund

# Compilation Next.js
FROM deps AS build
COPY . .
# Variables factices : la configuration réelle est injectée à l'exécution (le build ne lit aucun secret).
ENV SESSION_SECRET=build-only-secret-not-used-at-runtime \
    DATA_ENCRYPTION_KEY=build-only-key-not-used-at-runtime-000000 \
    ANALYTICS_SALT=build-only-salt \
    NODE_ENV=production
RUN npm run build

# ── Image web ────────────────────────────────────────────────────────────────
FROM base AS web
ENV NODE_ENV=production PORT=3000 HOSTNAME=0.0.0.0
RUN groupadd --gid 10001 terricom && useradd --uid 10001 --gid terricom --no-create-home --shell /usr/sbin/nologin terricom
COPY --from=build --chown=10001:10001 /app/.next/standalone ./
COPY --from=build --chown=10001:10001 /app/.next/static ./.next/static
COPY --from=build --chown=10001:10001 /app/public ./public
# Stockage local des médias (développement, serveur unique) ; en production : stockage objet S3.
RUN mkdir -p /app/storage && chown 10001:10001 /app/storage
USER 10001
EXPOSE 3000
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
  CMD node -e "fetch('http://127.0.0.1:3000/api/health').then(r=>process.exit(r.ok?0:1)).catch(()=>process.exit(1))"
CMD ["node", "server.js"]

# ── Image outils : worker, migrations, graine ────────────────────────────────
FROM base AS tools
ENV NODE_ENV=production
RUN groupadd --gid 10001 terricom && useradd --uid 10001 --gid terricom --no-create-home --shell /usr/sbin/nologin terricom
COPY --from=prod-deps --chown=10001:10001 /app/node_modules ./node_modules
COPY --chown=10001:10001 package.json package-lock.json tsconfig.json drizzle.config.ts ./
COPY --chown=10001:10001 drizzle ./drizzle
COPY --chown=10001:10001 scripts ./scripts
COPY --chown=10001:10001 src ./src
RUN mkdir -p /app/storage && chown 10001:10001 /app/storage
USER 10001
EXPOSE 9090
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
  CMD node -e "fetch('http://127.0.0.1:9090/').then(r=>process.exit(r.ok?0:1)).catch(()=>process.exit(1))"
CMD ["node", "--import", "tsx", "scripts/worker.ts"]
