# CHINA ORDER TZ — Release & Deployment

Infrastructure Audit #1. Scope: GitHub, versioning, CI/CD, and how production receives code.

**Authoritative production ownership:** [PRODUCTION_OWNERSHIP.md](./PRODUCTION_OWNERSHIP.md).

## Branch strategy

| Branch / ref | Purpose |
| --- | --- |
| `main` | Only production-ready code. Default branch. Source of truth for releases. |
| `feature/*` | Short-lived work. Merge via PR into `main`. |
| `hotfix/*` | Urgent production fixes. PR into `main`, then tag. |
| `vX.Y.Z` / `vX.Y.Z-rcN` tags | Immutable release markers (SemVer). |

Do not commit directly to `main` when collaborators are active. Prefer PR + green CI.

### Branch protection (`main`) — required admin action

Repository → Settings → Rules → Rulesets (or Branches → Branch protection):

1. Create a rule targeting `main`
2. Require a pull request before merging
3. Require status checks to pass:
   - `API tests (Laravel)`
   - `Web build (Next.js)`
4. Require branches to be up to date before merge
5. Block force pushes
6. Block deletions
7. Restrict admin bypass as tightly as the org allows

Do not claim protection is enabled until verified in the GitHub UI.

## Versioning (SemVer)

Format: `vMAJOR.MINOR.PATCH` (release candidates: `vMAJOR.MINOR.PATCH-rcN`)

- **MAJOR** — breaking API/contract or incompatible data changes
- **MINOR** — new features, backward compatible
- **PATCH** — bugfixes / hotfixes

### Cut a release

```bash
git checkout main
git pull --ff-only origin main
# Ensure CI is green on the target SHA
git tag -a v1.0.0-rc1 -m "RC1 commerce engine"
git push origin v1.0.0-rc1
```

## Deployment pipeline

```
Developer
  → PR / push to GitHub (main)
  → GitHub Actions CI (required gate)
  → Retail Smoke (path-filtered)
  → [manual] Deploy Next.js to VPS (workflow_dispatch, confirm=deploy)
       → SSH (secrets: HOST, USERNAME, SSH_KEY)
       → /root/china-order-tz
       → git checkout ref / pull main
       → npm ci + build apps/web
       → pm2 reload china-order-tz
```

### What this deploys

- **Yes:** Next.js storefront (`apps/web`) via PM2 on the DigitalOcean VPS.
- **No:** Laravel API, Composer install, migrations, Nginx, MySQL, Redis, queue, scheduler.

API / queue / scheduler: Compose path documented in [OPERATIONS.md](./OPERATIONS.md); live host procedure remains **Pending Infrastructure Audit #2**.

**Vercel** = preview/legacy only — not authoritative production. See [PRODUCTION_OWNERSHIP.md](./PRODUCTION_OWNERSHIP.md).

## Staging

No dedicated staging environment yet. Recommended before multi-operator cadence.

## Rollback (web)

1. Read `PREV_SHA` from the deploy log (also written to `/tmp/china-order-tz-web.prev_sha` on the VPS — confirm in Audit #2).
2. Actions → **Deploy Next.js to VPS** → `confirm` = `deploy`, `ref` = previous tag or SHA.
3. Optional: `scripts/deploy-web-remote.sh user@host <ref>` (requires SSH; does not replace Actions as the audited path).

API/database rollback: **Pending Infrastructure Audit #2.** Do not use web deploy for DB rollback.

### Non-destructive rollback validation (Audit #1)

Without contacting the VPS:

1. Confirm `deploy.yml` accepts `workflow_dispatch` inputs `ref` and `confirm`.
2. Confirm job `if:` requires `confirm == 'deploy'` (typos do not deploy).
3. Confirm script checks out `$DEPLOY_REF` when set (tag or SHA).
4. Confirm `PREV_SHA` is echoed and persisted for operators.
5. Do **not** run a production deploy or rollback during Audit #1 gap closure.

## Required GitHub secrets

| Secret | Used by | Purpose |
| --- | --- | --- |
| `HOST` | `deploy.yml` | VPS hostname / IP |
| `USERNAME` | `deploy.yml` | SSH user |
| `SSH_KEY` | `deploy.yml` | Private key (never commit) |

## Dead / inactive workflows

`apps/api/.github/workflows/*` are Laravel skeleton leftovers. Only root `.github/workflows/` runs.

## Manual deploy

```text
GitHub → Actions → Deploy Next.js to VPS → Run workflow
  confirm: deploy
  ref: (optional) v1.0.0-rc1 | SHA | leave empty for origin/main
```

Only after CI is green on the target commit.
