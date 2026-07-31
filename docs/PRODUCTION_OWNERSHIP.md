# CHINA ORDER TZ — Production ownership

Who deploys what, and how to operate production. For runbooks see [OPERATIONS.md](./OPERATIONS.md).

---

## Deployment ownership matrix

| Component | Owner / method | Command / workflow |
|-----------|----------------|----------------------|
| **Web (Next.js)** | DevOps / release operator via GitHub Actions | Actions → **Deploy Next.js to VPS** → `confirm=deploy` |
| **API (Laravel)** | DevOps / backend operator — **manual** | `bash scripts/deploy-api-compose.sh` or Compose up (see below) |
| **Database migrations** | Backend operator — runs on **API container start only** | `php artisan migrate --force` (entrypoint.prod.sh; queue/scheduler skip via `SKIP_MIGRATIONS`) |
| **Queue workers** | Compose `queue` service (auto-restart) | `docker compose ... up -d queue` |
| **Scheduler** | Compose `scheduler` service (auto-restart) | `docker compose ... up -d scheduler` |
| **Worker restart after API deploy** | Automatic when `QUEUE_RESTART_ON_BOOT=true` | `php artisan queue:restart` in API entrypoint |
| **Web rollback** | Release operator | Redeploy previous SHA via GitHub Actions |
| **API rollback** | Backend operator | Redeploy previous image/git ref via Compose — **no automatic DB rollback** |
| **Database restore** | DBA / senior operator | Manual restore from backup — see [OPERATIONS.md §7](./OPERATIONS.md#7-database-backups) |

---

## Authoritative production surfaces

| Surface | Location |
|---------|----------|
| Storefront | DigitalOcean VPS — PM2 `china-order-tz` at `/root/china-order-tz` |
| API | Docker Compose production stack (documented path) — **confirm live host in ops audit** |
| Vercel | Preview/legacy only — **not** production |

---

## Web deployment (automatic path)

1. Merge to `main` with green CI
2. GitHub → Actions → **Deploy Next.js to VPS**
3. Inputs: `confirm=deploy`, optional `ref` (tag/SHA; default `main`)
4. Workflow SSHs to VPS, pulls ref, builds `apps/web`, reloads PM2

**Secrets required:** `HOST`, `USERNAME`, `SSH_KEY`

---

## API deployment (manual path)

```bash
# On deployment host with Docker
cp apps/api/.env.production.example .env   # first time only — then edit secrets
bash scripts/validate-production-deploy.sh  # optional — static .env gate (no containers)
bash scripts/deploy-api-compose.sh
```

Optional pre-deploy backup before migrations on **existing** hosts:

```bash
PRE_DEPLOY_BACKUP=true bash scripts/deploy-api-compose.sh   # always backup first
PRE_DEPLOY_BACKUP=auto bash scripts/deploy-api-compose.sh # backup when mysql volume exists
```

Greenfield (`PRE_DEPLOY_BACKUP=false`, default): no backup step.

Equivalent:

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
docker compose exec api php artisan ops:health
docker compose exec api php artisan ops:production-env-check
docker compose exec api php artisan queue:restart
```

### What the API entrypoint does (`docker/php/entrypoint.prod.sh`)

1. Ensures storage directories exist and are writable
2. `php artisan storage:link`
3. `php artisan migrate --force` (**API only** — queue/scheduler set `SKIP_MIGRATIONS=true`)
4. Config/route/view cache (skipped when `SKIP_OPTIMIZE=true` on workers)
5. Optional `queue:restart` when `QUEUE_RESTART_ON_BOOT=true`

---

## Migrations

| Environment | When | Who |
|-------------|------|-----|
| Compose production | API container boot | Automatic (entrypoint; workers skip migrations) |
| Manual / non-Compose | Before or during deploy window | Backend operator: `php artisan migrate --force` |

**Policy:** Never run `migrate:fresh` or `db:wipe` in production. Set `ALLOW_DESTRUCTIVE_DB=false`.

---

## Queue and scheduler

| Process | Production operation |
|---------|---------------------|
| Queue worker | Compose service `queue` — always running, `restart: always` |
| Scheduler | Compose service `scheduler` — `schedule:work` |
| Health | `php artisan ops:queue-health`, scheduler via `ops:health` |
| After code deploy | `php artisan queue:restart` (automatic on API boot) |

If not using Compose, operators must run equivalent systemd/supervisor units — see [OPERATIONS.md](./OPERATIONS.md).

---

## Health monitoring

| Probe | URL / command |
|-------|---------------|
| Public API health | `GET /api/v1/health` |
| CLI (Docker healthcheck) | `php artisan ops:health` |
| Env validation | `php artisan ops:production-env-check` |
| Queue | `php artisan ops:queue-health` |

Docker Compose healthchecks:

- **api:** `ops:health` (critical: database + storage)
- **queue:** `ops:queue-health`
- **scheduler:** `ops:health` (includes scheduler heartbeat)

---

## Rollback procedures

### Web only

1. Find previous SHA from deploy log or `/tmp/china-order-tz-web.prev_sha` on VPS
2. Re-run deploy workflow with `ref=<previous SHA>`

### API

1. Check out / deploy previous git tag or image digest
2. `docker compose ... up -d --build`
3. **Do not** downgrade database schema without a planned migration rollback

### Database

1. Stop queue + scheduler
2. Restore from `BACKUP_ROOT` daily artifact — see [OPERATIONS.md](./OPERATIONS.md)
3. Restart services and verify `ops:health`

---

## Version compatibility

Web and API deploy independently today. When API contracts change:

1. Deploy API + run migrations first
2. Deploy web that expects the new schema
3. Tag releases together (`vX.Y.Z`) for traceability

---

## Pending confirmation (Infrastructure Audit #2)

Live VPS inspection still needed to confirm:

- Whether API runs on the same Droplet as PM2 web or a separate host
- TLS termination and DNS
- Whether production uses Compose or a custom layout

Until confirmed, treat this document as the **repository-intended** ownership model.
