#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

COMPOSE=(docker compose -f docker-compose.yml -f docker-compose.prod.yml)
# shellcheck source=lib/production-preflight.sh
source "${ROOT}/scripts/lib/production-preflight.sh"

wait_for_mysql_healthy() {
  echo "==> Waiting for MySQL health..."
  for i in $(seq 1 30); do
    if "${COMPOSE[@]}" exec -T mysql mysqladmin ping -h localhost -u root -p"${MYSQL_ROOT_PASSWORD:-secret}" >/dev/null 2>&1; then
      echo "MySQL healthy."
      return 0
    fi
    if [ "$i" -eq 30 ]; then
      echo "MySQL health check did not pass within timeout." >&2
      return 1
    fi
    sleep 2
  done
}

wait_for_service_health() {
  local service="$1"
  local command="$2"
  local attempts="${3:-30}"
  local label="${4:-$service}"

  echo "==> Waiting for ${label} health..."
  for i in $(seq 1 "$attempts"); do
    if "${COMPOSE[@]}" exec -T "$service" $command >/dev/null 2>&1; then
      echo "${label} healthy."
      return 0
    fi
    if [ "$i" -eq "$attempts" ]; then
      echo "${label} health check did not pass within timeout." >&2
      "${COMPOSE[@]}" exec -T "$service" $command || true
      return 1
    fi
    sleep 5
  done
}

wait_for_scheduler_health() {
  echo "==> Waiting for scheduler health..."
  for i in $(seq 1 36); do
    if "${COMPOSE[@]}" exec -T scheduler php artisan ops:health --json 2>/dev/null | grep -Eq '"scheduler"[[:space:]]*:[[:space:]]*true'; then
      echo "Scheduler healthy."
      return 0
    fi
    if [ "$i" -eq 36 ]; then
      echo "Scheduler health check did not pass within timeout." >&2
      "${COMPOSE[@]}" exec -T scheduler php artisan ops:health --json || true
      return 1
    fi
    sleep 5
  done
}

maybe_run_pre_deploy_backup() {
  local mode="${PRE_DEPLOY_BACKUP:-false}"
  local project_name
  project_name="$(basename "$ROOT" | tr '[:upper:]' '[:lower:]' | tr -cd 'a-z0-9_-')"
  local mysql_volume="${COMPOSE_PROJECT_NAME:-${project_name}}_mysql_data"

  case "$mode" in
    true|1|yes|YES)
      ;;
    auto|AUTO)
      if ! docker volume inspect "$mysql_volume" >/dev/null 2>&1; then
        echo "==> Greenfield detected (no ${mysql_volume}) — skipping pre-deploy backup."
        return 0
      fi
      ;;
    false|0|no|NO|"")
      echo "==> Pre-deploy backup skipped (PRE_DEPLOY_BACKUP=${mode})."
      return 0
      ;;
    *)
      echo "Unknown PRE_DEPLOY_BACKUP='${mode}'. Use true, false, or auto." >&2
      return 1
      ;;
  esac

  echo "==> Pre-deploy backup (existing installation)..."
  "${COMPOSE[@]}" up -d mysql
  wait_for_mysql_healthy
  "${COMPOSE[@]}" run --rm --entrypoint php api artisan ops:backup-check
  "${COMPOSE[@]}" run --rm --entrypoint php api artisan ops:backup-run
  echo "Pre-deploy backup complete."
}

# ── 1) Fail fast before any production containers start ─────────────────────
production_preflight_static ".env"

# ── 2) Optional backup before migrations (existing installs only) ───────────
maybe_run_pre_deploy_backup

# ── 3) Build and start full production stack ────────────────────────────────
echo "==> Building and starting production stack (api, queue, scheduler, nginx, web, mysql)..."
"${COMPOSE[@]}" up -d --build

# ── 4) Runtime validation gates ─────────────────────────────────────────────
wait_for_service_health api "php artisan ops:health" 30 "API"
"${COMPOSE[@]}" exec -T api php artisan ops:production-env-check
"${COMPOSE[@]}" exec -T api php artisan nmb:validate-config

# ── 5) Worker + scheduler readiness ─────────────────────────────────────────
wait_for_service_health queue "php artisan ops:queue-health" 24 "Queue"
wait_for_scheduler_health

echo "==> Queue worker restart signal..."
"${COMPOSE[@]}" exec -T api php artisan queue:restart || true

echo "==> Done. Services:"
"${COMPOSE[@]}" ps
