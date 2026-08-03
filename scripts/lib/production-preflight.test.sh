#!/usr/bin/env bash
# Regression tests for scripts/lib/production-preflight.sh (no containers).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

# shellcheck source=lib/load-env.sh
source "${ROOT}/scripts/lib/load-env.sh"
# shellcheck source=lib/production-preflight.sh
source "${ROOT}/scripts/lib/production-preflight.sh"

TMP_DIR=""
cleanup() {
  if [ -n "$TMP_DIR" ] && [ -d "$TMP_DIR" ]; then
    rm -rf "$TMP_DIR"
  fi
}
trap cleanup EXIT

make_env() {
  TMP_DIR="$(mktemp -d)"
  local file="${TMP_DIR}/.env"
  cat >"$file" <<'EOF'
APP_NAME="CHINA ORDER TZ API"
APP_ENV=production
APP_KEY=base64:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa=
APP_DEBUG=false
APP_URL=https://api.example.com

MYSQL_ROOT_PASSWORD=root-strong-pass
MYSQL_DATABASE=china_order_tz
MYSQL_USER=china_order
MYSQL_PASSWORD=app-strong-pass

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=china_order_tz
DB_USERNAME=china_order
DB_PASSWORD=app-strong-pass

FRONTEND_URL=https://www.example.com
SANCTUM_STATEFUL_DOMAINS=example.com,www.example.com

MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_FROM_ADDRESS=noreply@example.com
NOTIFICATION_EMAIL_CONFIGURED=true

PAYMENT_DEFAULT_GATEWAY=nmb
NMB_WEBHOOK_REQUIRE_SIGNATURE=true
NMB_BASE_URL=https://payments.example.com
NMB_MERCHANT_ID=merchant-1
NMB_USERNAME=nmb-user
NMB_PASSWORD=nmb-pass
NMB_RETURN_URL=https://www.example.com/checkout/payment/return
NMB_CALLBACK_URL=https://api.example.com/api/v1/payments/nmb/callback
NMB_WEBHOOK_SECRET=webhook-secret
NMB_MERCHANT_NAME="CHINA ORDER TZ API"
NMB_MERCHANT_URL=https://www.example.com
EOF
  printf '%s' "$file"
}

assert_passes() {
  local file="$1"
  if ! production_preflight_static "$file" >/dev/null 2>&1; then
    echo "Expected preflight to pass for ${file}" >&2
    exit 1
  fi
}

assert_fails() {
  local file="$1"
  local needle="${2:-}"
  local output

  if output="$(production_preflight_static "$file" 2>&1 >/dev/null)"; then
    echo "Expected preflight to fail for ${file}" >&2
    exit 1
  fi

  if [ -n "$needle" ] && ! grep -Fq "$needle" <<<"$output"; then
    echo "Expected failure message to contain: ${needle}" >&2
    echo "$output" >&2
    exit 1
  fi
}

replace_in_env() {
  local file="$1"
  local key="$2"
  local value="$3"
  sed -i.bak "s|^${key}=.*|${key}=${value}|" "$file"
  rm -f "${file}.bak"
}

echo "==> production-preflight regression tests"

BASE_ENV="$(make_env)"
assert_passes "$BASE_ENV"

LOAD_ENV="${TMP_DIR}/load.env"
printf 'MYSQL_ROOT_PASSWORD=loaded-root-pass\nAPP_NAME="Quoted Name"\n' >"$LOAD_ENV"
unset MYSQL_ROOT_PASSWORD APP_NAME
production_load_dotenv "$LOAD_ENV"
if [ "${MYSQL_ROOT_PASSWORD:-}" != "loaded-root-pass" ]; then
  echo "production_load_dotenv failed to export MYSQL_ROOT_PASSWORD" >&2
  exit 1
fi
if [ "${APP_NAME:-}" != "Quoted Name" ]; then
  echo "production_load_dotenv failed to strip quotes from APP_NAME" >&2
  exit 1
fi

MISMATCH_ENV="${TMP_DIR}/mismatch.env"
cp "$BASE_ENV" "$MISMATCH_ENV"
replace_in_env "$MISMATCH_ENV" "DB_PASSWORD" "different-pass"
assert_fails "$MISMATCH_ENV" "DB_PASSWORD must mirror MYSQL_PASSWORD"

MISSING_MYSQL_ENV="${TMP_DIR}/missing-mysql.env"
cp "$BASE_ENV" "$MISSING_MYSQL_ENV"
sed -i.bak '/^MYSQL_PASSWORD=/d' "$MISSING_MYSQL_ENV"
rm -f "${MISSING_MYSQL_ENV}.bak"
assert_fails "$MISSING_MYSQL_ENV" "missing MYSQL_PASSWORD"

SECRET_ENV="${TMP_DIR}/secret.env"
cp "$BASE_ENV" "$SECRET_ENV"
replace_in_env "$SECRET_ENV" "MYSQL_PASSWORD" "secret"
replace_in_env "$SECRET_ENV" "DB_PASSWORD" "secret"
assert_fails "$SECRET_ENV" "MYSQL_PASSWORD must not be 'secret'"

echo "All production-preflight regression tests passed."
