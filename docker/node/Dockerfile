FROM node:22-alpine AS base

FROM base AS deps
WORKDIR /repo
COPY package.json package-lock.json ./
COPY apps/web/package.json ./apps/web/
RUN npm ci --workspace=apps/web --include-workspace-root=false && \
    mkdir -p apps/web/node_modules

# Flatten hoisted + workspace node_modules for /app layout (docker-compose mounts apps/web at /app).
FROM base AS node_modules_flat
WORKDIR /out
COPY --from=deps /repo/node_modules ./node_modules
COPY --from=deps /repo/apps/web/node_modules ./ws_node_modules
RUN if [ -d ws_node_modules ] && [ "$(ls -A ws_node_modules 2>/dev/null)" ]; then \
      cp -aL ws_node_modules/. ./node_modules/; \
    fi && \
    rm -rf ws_node_modules

FROM base AS development
WORKDIR /app
COPY apps/web/ ./
COPY --from=node_modules_flat /out/node_modules ./node_modules
COPY --from=node_modules_flat /out/node_modules /opt/node_modules
COPY docker/node/docker-entrypoint.dev.sh /usr/local/bin/docker-entrypoint.dev.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.dev.sh
EXPOSE 3000
ENTRYPOINT ["docker-entrypoint.dev.sh"]
CMD ["npm", "run", "dev", "--", "--hostname", "0.0.0.0", "--port", "3000"]

FROM deps AS builder
COPY apps/web/ ./apps/web/
WORKDIR /repo/apps/web
ENV NEXT_TELEMETRY_DISABLED=1
RUN npm run build

FROM base AS production
ENV NODE_ENV=production
ENV NEXT_TELEMETRY_DISABLED=1
WORKDIR /app
COPY apps/web/ ./
COPY --from=node_modules_flat /out/node_modules ./node_modules
COPY --from=builder /repo/apps/web/.next ./.next
EXPOSE 3000
CMD ["npm", "run", "start:ws", "--", "--hostname", "0.0.0.0", "--port", "3000"]
