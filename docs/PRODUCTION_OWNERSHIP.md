# CHINA ORDER TZ — Production ownership

Infrastructure Audit #1. Authoritative answers for operators. VPS host inspection is deferred to Audit #2.

## 1. Authoritative production frontend

**DigitalOcean VPS** path `/root/china-order-tz`, process **PM2** app `china-order-tz` (`ecosystem.config.js` → `apps/web`).

Deploy initiator: **GitHub Actions** workflow `Deploy Next.js to VPS` (`deploy.yml`), **manual `workflow_dispatch` only** (type `deploy` to confirm). Does **not** run on push.

## 2. Vercel role

**Preview / legacy surface only.** Repo homepage may still list `china-order-tz-web.vercel.app`. Vercel is **not** the authoritative production frontend. Do not treat Vercel promotions as production releases unless ownership is explicitly changed in a later audit.

## 3. What is hosted on the DigitalOcean VPS

**Confirmed from repository deploy workflow (not from live VPS inspection):**

- Git working copy at `/root/china-order-tz`
- Next.js production build + PM2 process `china-order-tz`

**Pending Infrastructure Audit #2:** whether Laravel, MySQL, Nginx, Redis, queue, and scheduler also run on the same Droplet, sibling hosts, or Docker Compose on that host.

## 4. How is Laravel API deployed?

**Not deployed by GitHub Actions today.** Documented Compose production path:

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
```

Actual host procedure: **Pending Infrastructure Audit #2.**

## 5. How are migrations applied?

Repository documents Compose/Makefile migrate flows for local/prod Compose. Live production migration command and timing: **Pending Infrastructure Audit #2.**

## 6. How is the queue worker deployed/restarted?

`docker-compose.prod.yml` defines a `queue` service (`php artisan queue:work ...`). Live process manager and restart procedure: **Pending Infrastructure Audit #2.**

## 7. How is the scheduler operated?

No certified scheduler deploy exists in root GitHub Actions. Laravel scheduler (`schedule:run` / cron) on production: **Pending Infrastructure Audit #2.**

## 8. Which parts are automatic?

| Part | Automatic? |
| --- | --- |
| CI on PR / push to `main` | Yes (`ci.yml`) |
| Retail smoke (path-filtered) | Yes (`retail-smoke.yml`) |
| Web production deploy | **No** — manual `workflow_dispatch` + confirm |
| API / migrate / queue / scheduler | **No** (pending Audit #2) |

## 9. Which parts are manual pending Audit #2?

- Laravel API code deploy / image rebuild
- `php artisan migrate` (and rollback policy)
- Queue worker restart
- Scheduler/cron
- Host secrets, TLS, DNS, health checks on the live VPS
- Confirming whether API and web share one Droplet

## 10. Frontend / API version compatibility

**Policy:** ship API-compatible changes in the same SemVer release tag when contracts change. Frontend deploy and API deploy are **separate** until Audit #2 unifies them. Operators must not advance web-only to a tag that requires unmigrated API schema. Compatibility verification on the live stack: **Pending Infrastructure Audit #2.**

## Rollback (web only)

1. Note `PREV_SHA` from the last deploy log or `/tmp/china-order-tz-web.prev_sha` on the VPS (Audit #2 to confirm file presence).
2. GitHub → Actions → **Deploy Next.js to VPS** → `confirm=deploy`, `ref=<previous tag or SHA>`.
3. Do **not** roll back databases via this workflow.

API/database rollback: **Pending Infrastructure Audit #2.**
