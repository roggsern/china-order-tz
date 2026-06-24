FROM node:22-alpine AS base

WORKDIR /app

FROM base AS development

COPY apps/web/package.json apps/web/package-lock.json* ./
RUN npm ci

COPY apps/web/ ./

EXPOSE 3000

CMD ["npm", "run", "dev", "--", "--hostname", "0.0.0.0", "--port", "3000"]

FROM base AS production

ENV NODE_ENV=production
ENV NEXT_TELEMETRY_DISABLED=1

COPY apps/web/package.json apps/web/package-lock.json* ./
RUN npm ci

COPY apps/web/ ./

RUN npm run build

EXPOSE 3000

CMD ["npm", "run", "start", "--", "--hostname", "0.0.0.0", "--port", "3000"]
