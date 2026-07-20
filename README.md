# CHINA ORDER TZ

Production-ready monorepo foundation for an ecommerce platform connecting China-based suppliers with customers in Tanzania.

## Tech Stack

| Layer      | Technology                          |
| ---------- | ----------------------------------- |
| Frontend   | Next.js 15, React, TypeScript       |
| Styling    | Tailwind CSS                        |
| Backend    | Laravel 12 (API)                    |
| Database   | MySQL 8.4                           |
| Tooling    | Docker, ESLint, Prettier            |
| CI/CD      | GitHub Actions                      |

## Repository Structure

```
china-order-tz/
├── apps/
│   ├── web/                 # Next.js 15 storefront (frontend)
│   └── api/                 # Laravel 12 REST API (backend)
├── docker/
│   ├── nginx/               # Nginx reverse proxy for the API
│   ├── node/                # Next.js Docker image
│   └── php/                 # Laravel PHP-FPM Docker image
├── .github/workflows/       # CI, retail smoke, VPS web deploy
├── docs/RELEASE.md          # Branching, SemVer, deploy & rollback
├── docker-compose.yml       # Local development stack
├── docker-compose.prod.yml  # Production overrides
├── Makefile                 # Common development commands
└── package.json             # Root workspace scripts (Prettier)
```

## Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (recommended)
- [Node.js](https://nodejs.org/) 20+ (optional, for local frontend development)
- [Make](https://www.gnu.org/software/make/) (optional, for convenience commands)

## Quick Start (Docker)

1. **Clone the repository**

   ```bash
   git clone https://github.com/your-org/china-order-tz.git
   cd china-order-tz
   ```

2. **Configure environment**

   ```bash
   cp .env.example .env
   cp apps/web/.env.example apps/web/.env.local
   cp apps/api/.env.example apps/api/.env
   ```

3. **Start the stack**

   ```bash
   docker compose up -d --build
   ```

   On first run, the API container installs Composer dependencies, generates an app key, and runs migrations automatically.

4. **Open the apps**

   | Service        | URL                              |
   | -------------- | -------------------------------- |
   | Frontend       | http://localhost:3000            |
   | API (Nginx)    | http://localhost:8000            |
   | API health     | http://localhost:8000/api/v1/health |
   | Laravel health | http://localhost:8000/up          |

## Local Development (without Docker)

### Frontend

```bash
cd apps/web
cp .env.example .env.local
npm install
npm run dev
```

### Backend

PHP 8.4+, Composer, and MySQL are required locally.

```bash
cd apps/api
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan serve
```

Update `DB_*` variables in `apps/api/.env` to point at your local MySQL instance.

## Available Scripts

### Root (npm)

| Command              | Description                    |
| -------------------- | ------------------------------ |
| `npm run dev:web`    | Start Next.js dev server       |
| `npm run build:web`  | Build Next.js for production   |
| `npm run lint:web`   | Run ESLint on the frontend     |
| `npm run format`     | Format files with Prettier     |
| `npm run format:check` | Check Prettier formatting    |

### Make (Docker)

```bash
make up        # Start services
make down      # Stop services
make logs      # View logs
make migrate   # Run migrations
make test      # Run Laravel tests
```

## Environment Variables

Root `.env` drives Docker Compose. App-specific variables live in:

- `apps/web/.env.local` — `NEXT_PUBLIC_API_URL`
- `apps/api/.env` — Laravel configuration (`DB_*`, `APP_*`, `FRONTEND_URL`)

See `.env.example` files in each location for the full list.

## CI/CD

GitHub Actions workflows under `.github/workflows/` — see [docs/RELEASE.md](docs/RELEASE.md) and [docs/PRODUCTION_OWNERSHIP.md](docs/PRODUCTION_OWNERSHIP.md):

- **CI** (`ci.yml`) — Laravel full test suite + Next.js lint/typecheck/build on PRs and `main`.
- **Retail smoke** (`retail-smoke.yml`) — path-filtered POS/retail Laravel gate.
- **Deploy** (`deploy.yml`) — **manual only** (`workflow_dispatch`, type `deploy` to confirm). Deploys Next.js to the DigitalOcean VPS via SSH + PM2. Does **not** run on push. **API/queue/migrations are not deployed by this workflow.**

**Production frontend authority:** DigitalOcean VPS + PM2. **Vercel** is preview/legacy only.

Release tags: SemVer `vX.Y.Z` / `vX.Y.Z-rcN` on `main`. Rollback: manual dispatch with previous tag/SHA.

## Production (RC1)

Build and run with production overrides (includes API + **queue worker**):

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
```

### Required before go-live

| Setting | Production value |
| ------- | ---------------- |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `LOG_LEVEL` | `warning` or `error` |
| `SESSION_SECURE_COOKIE` | `true` (HTTPS) |
| `PAYMENT_DEFAULT_GATEWAY` | `nmb` (not `mock`) |
| `NMB_WEBHOOK_REQUIRE_SIGNATURE` | `true` + `NMB_WEBHOOK_SECRET` |
| DB / admin passwords | Rotate defaults from `.env.example` |

Mock payment and NMB simulate endpoints are **blocked in production**.

Health: `GET /api/v1/health` reports database readiness (503 when degraded).

Queue: when `NMB_PROCESS_CALLBACKS_ASYNC=true`, the Compose `queue` service must be running (`php artisan queue:work`).

## API Conventions

- Base path: `/api/v1`
- Health check: `GET /api/v1/health`
- Laravel built-in health: `GET /up`

## What's Included

Locked launch closures (do not redesign):

1. Checkout & Payment
2. Order Lifecycle
3. China Workflow
4. Customer Agent Workflow
5. Tracking & Notifications

Plus commerce catalog, warehouse, inventory, POS, CMS, admin/customer APIs, and Dockerized local development.

## License

Proprietary — CHINA ORDER TZ. All rights reserved.
