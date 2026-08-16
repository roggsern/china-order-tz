# CHINA ORDER TZ — Operations Guide

Operational runbook for production deployment, health checks, mail, queue/scheduler, storage, and backups.

**Related:** [PRODUCTION_OWNERSHIP.md](./PRODUCTION_OWNERSHIP.md) · [RELEASE.md](./RELEASE.md) · [PRODUCTION_COMMERCE_CLEANUP.md](./PRODUCTION_COMMERCE_CLEANUP.md) · [PRODUCT_DESCRIPTION_ENCODING.md](./PRODUCT_DESCRIPTION_ENCODING.md)

---

## 1. Production stack (Docker Compose)

Production Compose merges `docker-compose.yml` + `docker-compose.prod.yml`. The production overlay:

- Starts **all six services by default** — `queue` and `scheduler` are always included (no `--profile workers`).
- Uses **built images only** — no development bind mounts (`./apps/api`, `./apps/web`).
- Mounts **persistent volumes**: recovered MySQL data (`china-order-tz_mysql_data_recovered`), `api_storage`, and `app_backups`.
- **Never publishes MySQL** — `ports: !reset []` clears the base `${MYSQL_PORT:-3306}:3306` binding. Local/dev Compose may still expose MySQL; production must not.

```bash
cp .env.production.example .env
# Fill APP_KEY, MYSQL_* + mirrored DB_*, NMB credentials, SMTP settings

# Recommended — validates .env, optional backup, builds, starts full stack, validates runtime:
bash scripts/deploy-api-compose.sh

# Static .env validation only (no containers):
bash scripts/validate-production-deploy.sh

# Assert production effective Compose never publishes MySQL:
bash scripts/assert-production-mysql-hardening.sh

# Manual equivalent:
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec api php artisan ops:production-env-check
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec api php artisan nmb:validate-config
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec api php artisan ops:health
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec queue php artisan ops:queue-health
```

### MySQL network isolation & recovered volume

| Concern | Production behavior |
|---------|---------------------|
| Host port 3306 | Not published (`docker-compose.prod.yml` resets ports) |
| Data volume | External named volume `china-order-tz_mysql_data_recovered` |
| Old compromised volume | Keep `china-order-tz_mysql_data` on disk; do not delete or rename |
| Temporary incident override | Do **not** depend on `docker-compose.incident-recovery.yml` — hardening lives in `docker-compose.prod.yml` |
| App DB host | `DB_HOST=mysql` (Docker network DNS only) |

**Transition off the temporary incident override** (once this repo change is on the host):

```bash
# Stop stack that used the incident overlay (adjust -f flags to match how it was started)
docker compose -f docker-compose.yml -f docker-compose.prod.yml -f docker-compose.incident-recovery.yml down

# Confirm recovered volume still exists (do not create empty / do not touch compromised volume)
docker volume inspect china-order-tz_mysql_data_recovered

# Deploy with tracked production files only
bash scripts/assert-production-mysql-hardening.sh
bash scripts/deploy-api-compose.sh

# Verify MySQL is not published on the host
docker compose -f docker-compose.yml -f docker-compose.prod.yml ps
ss -lntp | grep 3306 || echo "OK: nothing listening on 3306"
```

| Service | Role | Healthcheck | Required at launch |
|---------|------|-------------|-------------------|
| `api` | Laravel PHP-FPM | `php artisan ops:health` | Yes |
| `queue` | `queue:work database` | `php artisan ops:queue-health` | **Yes** — async NMB callbacks, notifications |
| `scheduler` | `schedule:work` | `php artisan ops:health` (scheduler probe) | **Yes** — backups, monitoring, reconciliation |
| `nginx` | API reverse proxy (production image) | — | Yes |
| `web` | Next.js storefront (production image) | — | Yes |
| `mysql` | Database | `mysqladmin ping` | Yes |

**Web container** has no Laravel healthcheck — monitor via HTTP to the storefront URL.

After deploy, confirm all services are running:

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml ps
# Expect: api, queue, scheduler, nginx, web, mysql
```

---

## 2. Health checks

### HTTP endpoint (public)

```
GET /api/v1/health
```

| Check | Critical? | Notes |
|-------|-----------|-------|
| `database` | Yes | MySQL connectivity |
| `storage` | Yes | Writable storage + public root |
| `queue` | Soft | Worker heartbeat (null during startup grace) |
| `cache` | Soft | Read/write probe |
| `scheduler` | Soft | Heartbeat cache key |
| `mail` | Soft | Production SMTP + `NOTIFICATION_EMAIL_CONFIGURED` |
| `production_config` | Soft | `APP_DEBUG`, gateway ≠ mock, webhook signature |
| `environment` | Soft | App environment present |

- **503** when critical checks fail (`database` or `storage`)
- **200 degraded** when soft checks fail
- Production responses **never** include secrets, credentials, or debug internals

### CLI commands

```bash
php artisan ops:health              # Exit 0 when critical OK
php artisan ops:health --json
php artisan ops:health-check        # Alias (backward compatible)
php artisan ops:queue-health        # Queue worker + failed/pending thresholds
php artisan ops:production-env-check  # Mail + payment + debug validation
php artisan nmb:validate-config     # NMB credentials + webhook secret
php artisan ops:backup-check        # Backup dependencies + latest artifact
```

---

## 3. Production environment safety

**Template:** `.env.production.example` (project root)

**Database credential contract:** `MYSQL_*` is the source of truth for Docker Compose and the MySQL container. Laravel `DB_*` values in `.env` must mirror `MYSQL_*` (same database, user, and password). Static preflight enforces this before deploy.

| Variable | Production value |
|----------|------------------|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `PAYMENT_DEFAULT_GATEWAY` | `nmb` (never `mock`) |
| `NMB_WEBHOOK_REQUIRE_SIGNATURE` | `true` |
| `MAIL_MAILER` | `smtp` (never `log`) |
| `NOTIFICATION_EMAIL_CONFIGURED` | `true` |

`docker-compose.prod.yml` enforces safe overrides for API/queue/scheduler even if `.env` is incomplete.

Mock payment tooling is blocked at runtime via `ProductionSafety` — returns 403 in production.

Validate before go-live:

```bash
php artisan ops:production-env-check
php artisan ops:health
php artisan nmb:validate-config
```

---

## 4. Mail configuration

Outbound application email uses Laravel Mail via the Notification Platform.
On DigitalOcean, prefer **Resend HTTPS** (`MAIL_MAILER=resend`) because outbound SMTP ports 465/587 are blocked.

### Required variables (Resend — recommended production)

| Variable | Purpose |
|----------|---------|
| `MAIL_MAILER` | `resend` |
| `RESEND_API_KEY` | Resend API key (`config/services.php` → `services.resend.key`) |
| `MAIL_FROM_ADDRESS` | Sender address (e.g. `orders@chinaordertz.com`) — domain must be verified in Resend |
| `MAIL_FROM_NAME` | Sender display name |
| `NOTIFICATION_EMAIL_CONFIGURED` | `true` — enables Notification Platform email channel |
| `NOTIFICATION_EMAIL_DRIVER` | Label stored on notification rows (use `resend`) |

### Rollback / alternate SMTP variables

Keep these set for emergency rollback (`MAIL_MAILER=smtp`). They are unused while `MAIL_MAILER=resend`.

| Variable | Purpose |
|----------|---------|
| `MAIL_SCHEME` | `tls` / `ssl` |
| `MAIL_HOST` | SMTP server |
| `MAIL_PORT` | Usually `587` (TLS) or `465` (SSL) — often blocked on DigitalOcean |
| `MAIL_USERNAME` | SMTP auth user |
| `MAIL_PASSWORD` | SMTP auth password |

Also set `NOTIFICATION_EMAIL_DRIVER=smtp` when rolling back to SMTP.

### Email flows that require mail

| Flow | Channel |
|------|---------|
| Password reset | Notification Platform → email |
| Email verification | Notification Platform → email |
| Email change request / confirmation | Notification Platform → email |
| Password changed notification | Notification Platform → email |

In-app notifications still deliver when email is unconfigured; **email links will not send**.

Admin setting `notifications.email_enabled` must also be `true` or the email channel is filtered out.

### Mail test checklist

- [ ] `php artisan ops:production-env-check` reports `mail_configured: yes`
- [ ] `GET /api/v1/health` shows `checks.mail: true` in production
- [ ] Trigger password reset for a test customer — email received
- [ ] Trigger email verification — email received with valid link
- [ ] Request email change — notification to current address
- [ ] Confirm templates render (no broken placeholders)
- [ ] Confirm Resend dashboard delivery (when using Resend)
- [ ] Check spam folder / SPF/DKIM on sending domain (Resend domain verification)

---

## 5. Queue and scheduler

### Queue worker

Processes async jobs including:

- NMB payment callback processing (`NMB_PROCESS_CALLBACKS_ASYNC=true`)
- Customer/admin notifications (email when configured)
- Analytics aggregation jobs

**Production:** `queue` starts automatically with the production stack (`docker-compose.prod.yml` clears the development `workers` profile). Do **not** deploy API-only without queue — payment callbacks will stall.

**Manual worker (non-Compose):**

```bash
php artisan queue:work database --sleep=1 --tries=3 --timeout=120 --max-time=3600
```

After API deploy, signal workers to restart:

```bash
php artisan queue:restart
```

Compose sets `QUEUE_RESTART_ON_BOOT=true` on the API container entrypoint.

Monitor:

```bash
php artisan ops:queue-health
php artisan ops:queue-health --alert   # emits monitoring alert when unhealthy
```

### Scheduler

**Production:** `scheduler` starts automatically with the production stack and runs `php artisan schedule:work`. Required for daily backups (`ops:backup-run`), monitoring sweeps, and payment reconciliation.

**Host cron alternative** (only if not using Compose scheduler):

```cron
* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1
```

### Scheduled tasks

| Schedule | Command | Purpose |
|----------|---------|---------|
| Every minute | heartbeat cache write | Scheduler liveness |
| Every 5 min | `nmb:reconcile-payments` | Payment reconciliation |
| Every 5 min | `ops:monitoring-sweep` | Ops alerts |
| Every 5 min | `ops:queue-health --alert` | Queue monitoring |
| Daily | `ops:backup-run` | Database + media backup |
| Daily | `queue:prune-failed`, `model:prune`, cache/temp prunes | Housekeeping |

---

## 6. Storage

### Layout

| Path | Purpose |
|------|---------|
| `storage/app/public` | Public uploads (product media, store logos) |
| `storage/app/private` | Private files |
| `public/storage` | Symlink → `storage/app/public` |

### Production setup

Entrypoint (`docker/php/entrypoint.prod.sh`) runs:

```bash
php artisan storage:link --force
```

Shared volume `api_storage` is mounted on `api`, `queue`, `scheduler`, and read-only on `nginx`.

### Permissions

- `www-data` owns `storage/` and `bootstrap/cache`
- Health probe writes/deletes a test file under `storage/app`

### Multi-node / cloud

For multi-container or multi-host deployments, set `FILESYSTEM_DISK=s3` (or compatible) and configure AWS/S3 env vars. Default Compose uses `local` on a shared Docker volume.

---

## 7. Database backups

### Commands

```bash
php artisan ops:backup-run           # Database + media (scheduled daily)
php artisan ops:backup-database      # MySQL dump only
php artisan ops:backup-media           # Uploaded files archive
php artisan ops:backup-check           # Verify dependencies + latest backup
php artisan ops:backup-verify {path}   # Verify specific artifact
php artisan ops:backup-prune           # Apply retention policy
```

### Configuration

| Variable | Default |
|----------|---------|
| `BACKUP_ENABLED` | `true` |
| `BACKUP_ROOT` | `/var/backups/china-order-tz` |
| `BACKUP_RETENTION_DAILY` | 7 |
| `BACKUP_RETENTION_WEEKLY` | 4 |
| `BACKUP_RETENTION_MONTHLY` | 6 |
| `BACKUP_DAILY_AT` | `02:15` |

Compose mounts `app_backups` volume at `BACKUP_ROOT`.

### Restore procedure (operator)

1. Stop queue/scheduler to prevent writes during restore
2. Restore database: `gunzip -c daily/YYYY-MM-DD_HHMMSS-database.sql.gz | mysql -u ... china_order_tz`
3. Restore media: extract `daily/YYYY-MM-DD_HHMMSS-media.tar.gz` into `storage/app/`
4. Run `php artisan storage:link`
5. Restart api, queue, scheduler
6. Verify: `php artisan ops:health`

Document actual host paths and credentials in your secure runbook (not in git).

---

## 8. Deployment checklist

### Pre-deploy

- [ ] CI green on target SHA
- [ ] `.env` populated from `.env.production.example`
- [ ] `bash scripts/validate-production-deploy.sh` passes (static gate — no containers)
- [ ] `bash scripts/assert-production-mysql-hardening.sh` passes (MySQL unpublished + recovered volume)
- [ ] `APP_KEY` generated
- [ ] `MYSQL_*` and mirrored `DB_*` credentials rotated from defaults (`secret`)
- [ ] Recovered volume `china-order-tz_mysql_data_recovered` exists on host (`docker volume inspect`)
- [ ] `php artisan nmb:validate-config` passes (also enforced by deploy script post-start)
- [ ] SMTP tested (see mail checklist)
- [ ] Feature flags reviewed (wishlist, reviews, checkout)
- [ ] Existing hosts: consider `PRE_DEPLOY_BACKUP=true` before deploy

### Deploy API (Compose)

```bash
bash scripts/deploy-api-compose.sh
# Order: static preflight → optional backup → compose up → env/NMB gates → queue/scheduler health
# Fails fast if preflight, ops:production-env-check, nmb:validate-config, or health checks fail.
```

Manual equivalent:

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec api php artisan ops:production-env-check
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec api php artisan ops:health
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec queue php artisan ops:queue-health
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec api php artisan queue:restart
```

### Deploy web

GitHub Actions → **Deploy Next.js to VPS** → `confirm=deploy`, optional `ref`.

See [RELEASE.md](./RELEASE.md).

### Post-deploy validation

- [ ] `docker compose -f docker-compose.yml -f docker-compose.prod.yml ps` — **six services** up: `api`, `queue`, `scheduler`, `nginx`, `web`, `mysql`
- [ ] Host has **no** published MySQL listener (`ss -lntp | grep 3306` empty; or compose config shows no mysql ports)
- [ ] `php artisan ops:production-env-check` → exit 0
- [ ] `php artisan nmb:validate-config` → exit 0
- [ ] `php artisan ops:health` → exit 0 (API container)
- [ ] `php artisan ops:queue-health` → exit 0 (queue container)
- [ ] Scheduler heartbeat visible via `ops:health --json` on scheduler container
- [ ] `php artisan ops:backup-check` → dependencies OK
- [ ] `GET /api/v1/health` → 200, `critical_ok: true`
- [ ] Customer login works
- [ ] Checkout + NMB payment test transaction
- [ ] Admin login works
- [ ] Order appears in admin after payment
- [ ] Queue processing (check `jobs` table empty after payment callback)

### Rollback

| Component | Procedure |
|-----------|-----------|
| Web | Redeploy previous SHA via GitHub Actions |
| API | Redeploy previous image/SHA via Compose; **do not** auto-rollback DB |
| Database | Restore from backup (see §7) — manual, destructive |

---

## 9. Regression commands (post-deploy hardening)

Run after operational changes:

```bash
# API
php artisan test tests/Unit/Production/ProductionComposeDefinitionTest.php
php artisan test tests/Feature/Production/ProductionHardeningTest.php
php artisan test tests/Feature/Ops/OpsMonitoringCommandsTest.php
php artisan test tests/Feature/Auth/CustomerAuthenticationTest.php
php artisan test tests/Feature/Checkout/
php artisan test tests/Feature/Features/FeatureRuntimeTest.php

# Web
cd apps/web && npx tsx --test src/lib/features/feature-availability.test.ts
```

Do **not** re-run full inventory/fulfillment/China workflow suites for deployment-only changes unless those domains were touched.
