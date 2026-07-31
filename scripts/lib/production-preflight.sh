#!/usr/bin/env bash
# RC1-G4C.5 — static production preflight (no containers required).
set -euo pipefail

production_env_get() {
  local key="$1"
  local file="${2:-.env}"
  local line

  line="$(grep -E "^${key}=" "$file" 2>/dev/null | tail -1 || true)"
  if [ -z "$line" ]; then
    return 1
  fi

  printf '%s' "${line#*=}" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//' -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//" | tr -d '\r'
}

production_require_env() {
  local key="$1"
  local value

  if ! value="$(production_env_get "$key")"; then
    echo "Preflight failed: missing ${key} in .env" >&2
    return 1
  fi

  if [ -z "$value" ]; then
    echo "Preflight failed: ${key} is empty in .env" >&2
    return 1
  fi
}

production_require_env_not() {
  local key="$1"
  local forbidden="$2"
  local value

  value="$(production_env_get "$key")"
  if [ "$value" = "$forbidden" ]; then
    echo "Preflight failed: ${key} must not be '${forbidden}' in production." >&2
    return 1
  fi
}

production_require_env_value() {
  local key="$1"
  local expected="$2"
  local value

  value="$(production_env_get "$key")"
  if [ "$value" != "$expected" ]; then
    echo "Preflight failed: ${key} must be '${expected}' (found '${value:-<empty>}')." >&2
    return 1
  fi
}

production_preflight_static() {
  local env_file="${1:-.env}"
  local failed=0

  echo "==> Static production preflight (${env_file})..."

  if [ ! -f "$env_file" ]; then
    echo "Preflight failed: ${env_file} not found. Copy apps/api/.env.production.example to .env" >&2
    return 1
  fi

  run_check() {
    if ! "$@"; then
      failed=1
    fi
  }

  # Application
  run_check production_require_env_value APP_ENV production
  run_check production_require_env_value APP_DEBUG false
  run_check production_require_env APP_KEY
  run_check production_require_env APP_URL

  # Database
  run_check production_require_env DB_HOST
  run_check production_require_env DB_DATABASE
  run_check production_require_env DB_USERNAME
  run_check production_require_env DB_PASSWORD

  # Frontend / auth
  run_check production_require_env FRONTEND_URL
  run_check production_require_env SANCTUM_STATEFUL_DOMAINS

  # Mail
  run_check production_require_env MAIL_MAILER
  run_check production_require_env_not MAIL_MAILER log
  run_check production_require_env_not MAIL_MAILER array
  run_check production_require_env MAIL_HOST
  run_check production_require_env MAIL_FROM_ADDRESS
  run_check production_require_env_value NOTIFICATION_EMAIL_CONFIGURED true

  # Payments — align with ops:production-env-check + nmb:validate-config
  run_check production_require_env_not PAYMENT_DEFAULT_GATEWAY mock
  run_check production_require_env_value NMB_WEBHOOK_REQUIRE_SIGNATURE true
  run_check production_require_env NMB_BASE_URL
  run_check production_require_env NMB_MERCHANT_ID
  run_check production_require_env NMB_USERNAME
  run_check production_require_env NMB_PASSWORD
  run_check production_require_env NMB_RETURN_URL
  run_check production_require_env NMB_CALLBACK_URL
  run_check production_require_env NMB_WEBHOOK_SECRET
  run_check production_require_env NMB_MERCHANT_NAME
  run_check production_require_env NMB_MERCHANT_URL

  local nmb_base_url
  nmb_base_url="$(production_env_get NMB_BASE_URL || true)"
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

  local db_password
  db_password="$(production_env_get DB_PASSWORD || true)"
  if [ "$db_password" = "secret" ]; then
    echo "Preflight failed: DB_PASSWORD must be rotated from the default 'secret'." >&2
    failed=1
  fi

  if [ "$failed" -ne 0 ]; then
    echo "Static preflight failed — fix .env before deployment." >&2
    return 1
  fi

  echo "Static preflight passed."
}
