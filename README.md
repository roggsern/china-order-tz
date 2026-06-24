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
├── .github/workflows/       # CI and Docker build pipelines
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

GitHub Actions workflows:

- **CI** (`.github/workflows/ci.yml`) — lints and builds the frontend, runs Laravel tests against MySQL, and checks Prettier formatting on every push/PR to `main` and `develop`.
- **Docker Build** (`.github/workflows/docker-build.yml`) — validates API and Web Docker images build successfully.

## Production

Build and run with production overrides:

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
```

Set `APP_ENV=production`, `APP_DEBUG=false`, and strong database credentials before deploying.

## API Conventions

- Base path: `/api/v1`
- Health check: `GET /api/v1/health`
- Laravel built-in health: `GET /up`

## What's Included

This repository contains **project scaffolding only** — no ecommerce features (catalog, cart, checkout, auth, etc.) have been implemented yet. The foundation includes:

- Monorepo layout with separate frontend and API apps
- Dockerized local development environment
- ESLint (frontend) and Laravel Pint (backend, via Composer)
- Prettier formatting at the monorepo root
- GitHub Actions CI pipelines
- Minimal placeholder UI and API health endpoint

## License

Proprietary — CHINA ORDER TZ. All rights reserved.
