#!/usr/bin/env bash
# RC1-G4C.5 — static production preflight (no containers required).
set -euo pipefail

PRODUCTION_ENV_FILE="${PRODUCTION_ENV_FILE:-.env}"

production_env_get() {
  local key="$1"
  local file="${2:-${PRODUCTION_ENV_FILE:-.env}}"
  local line

  line="$(grep -E "^${key}=" "$file" 2>/dev/null | tail -1 || true)"
  if [ -z "$line" ]; then
    return 1
  fi

  printf '%s' "${line#*=}" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//' -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//" | tr -d '\r'
}

production_require_env() {
  local key="$1"
  local file="${2:-${PRODUCTION_ENV_FILE:-.env}}"
  local value

  if ! value="$(production_env_get "$key" "$file")"; then
    echo "Preflight failed: missing ${key} in ${file}" >&2
    return 1
  fi

  if [ -z "$value" ]; then
    echo "Preflight failed: ${key} is empty in ${file}" >&2
    return 1
  fi
}

production_require_env_not() {
  local key="$1"
  local forbidden="$2"
  local file="${3:-${PRODUCTION_ENV_FILE:-.env}}"
  local value

  value="$(production_env_get "$key" "$file")"
  if [ "$value" = "$forbidden" ]; then
    echo "Preflight failed: ${key} must not be '${forbidden}' in production." >&2
    return 1
  fi
}

production_require_env_value() {
  local key="$1"
  local expected="$2"
  local file="${3:-${PRODUCTION_ENV_FILE:-.env}}"
  local value

  value="$(production_env_get "$key" "$file")"
  if [ "$value" != "$expected" ]; then
    echo "Preflight failed: ${key} must be '${expected}' (found '${value:-<empty>}')." >&2
    return 1
  fi
}

production_require_env_mirror() {
  local source_key="$1"
  local mirror_key="$2"
  local file="${3:-${PRODUCTION_ENV_FILE:-.env}}"
  local source_value mirror_value

  if ! source_value="$(production_env_get "$source_key" "$file")"; then
    echo "Preflight failed: missing ${source_key} in ${file}" >&2
    return 1
  fi

  if ! mirror_value="$(production_env_get "$mirror_key" "$file")"; then
    echo "Preflight failed: missing ${mirror_key} in ${file}" >&2
    return 1
  fi

  if [ "$source_value" != "$mirror_value" ]; then
    echo "Preflight failed: ${mirror_key} must mirror ${source_key} (MYSQL_* is the Compose source of truth)." >&2
    return 1
  fi
}

production_preflight_static() {
  local env_file="${1:-.env}"
  local failed=0

  PRODUCTION_ENV_FILE="$env_file"

  echo "==> Static production preflight (${env_file})..."

  if [ ! -f "$env_file" ]; then
    echo "Preflight failed: ${env_file} not found. Copy .env.production.example to .env" >&2
    return 1
  fi

  run_check() {
    if ! "$@"; then
      failed=1
    fi
  }

  # Application
  run_check production_require_env_value APP_ENV production "$env_file"
  run_check production_require_env_value APP_DEBUG false "$env_file"
  run_check production_require_env APP_KEY "$env_file"
  run_check production_require_env APP_URL "$env_file"

  # Database — MYSQL_* is source of truth; DB_* must mirror for Laravel compatibility
  run_check production_require_env MYSQL_ROOT_PASSWORD "$env_file"
  run_check production_require_env MYSQL_DATABASE "$env_file"
  run_check production_require_env MYSQL_USER "$env_file"
  run_check production_require_env MYSQL_PASSWORD "$env_file"
  run_check production_require_env DB_HOST "$env_file"
  run_check production_require_env DB_DATABASE "$env_file"
  run_check production_require_env DB_USERNAME "$env_file"
  run_check production_require_env DB_PASSWORD "$env_file"
  run_check production_require_env_mirror MYSQL_DATABASE DB_DATABASE "$env_file"
  run_check production_require_env_mirror MYSQL_USER DB_USERNAME "$env_file"
  run_check production_require_env_mirror MYSQL_PASSWORD DB_PASSWORD "$env_file"
  run_check production_require_env_not MYSQL_ROOT_PASSWORD secret "$env_file"
  run_check production_require_env_not MYSQL_PASSWORD secret "$env_file"
  run_check production_require_env_not DB_PASSWORD secret "$env_file"

  # Frontend / auth
  run_check production_require_env FRONTEND_URL "$env_file"
  run_check production_require_env SANCTUM_STATEFUL_DOMAINS "$env_file"

  # Mail
  run_check production_require_env MAIL_MAILER "$env_file"
  run_check production_require_env_not MAIL_MAILER log "$env_file"
  run_check production_require_env_not MAIL_MAILER array "$env_file"
  run_check production_require_env MAIL_HOST "$env_file"
  run_check production_require_env MAIL_FROM_ADDRESS "$env_file"
  run_check production_require_env_value NOTIFICATION_EMAIL_CONFIGURED true "$env_file"

  # Payments — align with ops:production-env-check + nmb:validate-config
  run_check production_require_env_not PAYMENT_DEFAULT_GATEWAY mock "$env_file"
  run_check production_require_env_value NMB_WEBHOOK_REQUIRE_SIGNATURE true "$env_file"
  run_check production_require_env NMB_BASE_URL "$env_file"
  run_check production_require_env NMB_MERCHANT_ID "$env_file"
  run_check production_require_env NMB_USERNAME "$env_file"
  run_check production_require_env NMB_PASSWORD "$env_file"
  run_check production_require_env NMB_RETURN_URL "$env_file"
  run_check production_require_env NMB_CALLBACK_URL "$env_file"
  run_check production_require_env NMB_WEBHOOK_SECRET "$env_file"
  run_check production_require_env NMB_MERCHANT_NAME "$env_file"
  run_check production_require_env NMB_MERCHANT_URL "$env_file"

  local nmb_base_url
  nmb_base_url="$(production_env_get NMB_BASE_URL "$env_file" || true)"
  if [ -n "$nmb_base_url" ]; then
    local lower_base
    lower_base="$(printf '%s' "$nmb_base_url" | tr '[:upper:]' '[:lower:]')"
    for needle in sandbox test localhost; do
      if [[ "$lower_base" == *"$needle"* ]]; then
        echo "Preflight failed: NMB_BASE_URL must not contain '${needle}' in production." >&2
        failed=1
        break
      fi
    done
  fi

  if [ "$failed" -ne 0 ]; then
    echo "Static preflight failed — fix .env before deployment." >&2
    return 1
  fi

  echo "Static preflight passed."
}
