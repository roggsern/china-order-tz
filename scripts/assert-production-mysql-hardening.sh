#!/usr/bin/env bash
# Assert production Compose never publishes MySQL and uses the recovered volume.
# No secrets required. Does not create or delete china-order-tz_mysql_data_recovered.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

PROD_FILE="${ROOT}/docker-compose.prod.yml"
BASE_FILE="${ROOT}/docker-compose.yml"
EXPECTED_VOLUME_NAME="${MYSQL_DATA_VOLUME_NAME:-china-order-tz_mysql_data_recovered}"
ASSERT_COMPOSE_CONFIG="${ASSERT_COMPOSE_CONFIG:-1}"
failed=0

fail() {
  echo "ASSERT FAILED: $*" >&2
  failed=1
}

pass() {
  echo "OK: $*"
}

usable_python() {
  local candidate
  for candidate in python3 python; do
    if command -v "$candidate" >/dev/null 2>&1; then
      if "$candidate" -c 'import sys; sys.exit(0)' >/dev/null 2>&1; then
        command -v "$candidate"
        return 0
      fi
    fi
  done
  return 1
}

mysql_service_has_published_ports_yaml() {
  local yaml="$1"
  printf '%s\n' "$yaml" | awk '
    $0 ~ /^  mysql:/ { in_mysql=1; next }
    in_mysql && $0 ~ /^  [^ ]/ { in_mysql=0 }
    in_mysql && /published:/ { found=1 }
    END { exit found ? 0 : 1 }
  '
}

echo "==> Static checks (${PROD_FILE})..."

if ! grep -qE 'ports:[[:space:]]*!reset[[:space:]]*\[\]' "$PROD_FILE"; then
  fail "docker-compose.prod.yml must contain 'ports: !reset []' for mysql"
else
  pass "production overlay resets mysql ports"
fi

if ! grep -A20 '^  mysql:' "$PROD_FILE" | grep -qE 'ports:[[:space:]]*!reset[[:space:]]*\[\]'; then
  fail "ports: !reset [] must be under the mysql service"
else
  pass "ports: !reset [] is under mysql service"
fi

if ! grep -q 'mysql_data_recovered:/var/lib/mysql' "$PROD_FILE"; then
  fail "mysql service must mount mysql_data_recovered:/var/lib/mysql"
else
  pass "mysql mounts mysql_data_recovered"
fi

if ! grep -q "name:[[:space:]]*${EXPECTED_VOLUME_NAME}" "$PROD_FILE"; then
  fail "docker-compose.prod.yml must name volume ${EXPECTED_VOLUME_NAME}"
else
  pass "production volume name is ${EXPECTED_VOLUME_NAME}"
fi

if ! grep -A5 'mysql_data_recovered:' "$PROD_FILE" | grep -qE 'external:[[:space:]]*true'; then
  fail "recovered mysql volume must be external: true"
else
  pass "recovered volume is external"
fi

if grep -qi 'incident-recovery' "$PROD_FILE"; then
  fail "production overlay must not depend on incident-recovery compose file"
else
  pass "no dependency on incident-recovery overlay"
fi

if ! grep -qE '\$\{MYSQL_PORT:-3306\}:3306' "$BASE_FILE"; then
  fail "docker-compose.yml should keep optional local MYSQL_PORT publish for development"
else
  pass "base compose still supports local MYSQL_PORT publish"
fi

if [ "$ASSERT_COMPOSE_CONFIG" = "1" ] || [ "$ASSERT_COMPOSE_CONFIG" = "true" ]; then
  echo "==> Effective compose config checks..."
  if ! command -v docker >/dev/null 2>&1; then
    fail "docker not available; set ASSERT_COMPOSE_CONFIG=0 to skip runtime config check"
  else
    # Temporary non-external volume name so we can render config without touching
    # china-order-tz_mysql_data_recovered (never create/delete that volume here).
    TMP_OVERLAY="$(mktemp "${TMPDIR:-/tmp}/mysql-hardening-XXXXXX.yml")"
    cleanup() { rm -f "$TMP_OVERLAY"; }
    trap cleanup EXIT

    cat >"$TMP_OVERLAY" <<EOF
# Assertion-only overlay: allow compose config without the production external volume.
volumes:
  mysql_data_recovered:
    name: china-order-tz_mysql_data_recovered_assert_tmp
    external: false
EOF

    COMPOSE=(docker compose -f "$BASE_FILE" -f "$PROD_FILE" -f "$TMP_OVERLAY")
    CONFIG_ERR="$("${COMPOSE[@]}" config 2>&1 >/dev/null || true)"
    CONFIG_YAML="$("${COMPOSE[@]}" config 2>/dev/null || true)"

    if [ -z "$CONFIG_YAML" ]; then
      fail "could not render production compose config: ${CONFIG_ERR}"
    else
      if mysql_service_has_published_ports_yaml "$CONFIG_YAML"; then
        fail "effective config still has mysql published ports"
      else
        pass "effective config has no mysql published ports"
      fi

      # Confirm base local publish is cleared (no 0.0.0.0:3306 / host 3306 under mysql).
      if printf '%s\n' "$CONFIG_YAML" | awk '
        $0 ~ /^  mysql:/ { in_mysql=1; next }
        in_mysql && $0 ~ /^  [^ ]/ { in_mysql=0 }
        in_mysql && /3306/ { found=1 }
        END { exit found ? 0 : 1 }
      '; then
        # Internal DB_PORT=3306 env on other services is fine; only mysql service block matters.
        fail "mysql service block unexpectedly references published 3306"
      else
        pass "mysql service block has no published 3306 binding"
      fi

      PY="$(usable_python || true)"
      if [ -n "$PY" ]; then
        CONFIG_JSON="$("${COMPOSE[@]}" config --format json 2>/dev/null || true)"
        if [ -n "$CONFIG_JSON" ]; then
          if "$PY" -c '
import json, sys
cfg = json.loads(sys.argv[1])
mysql = cfg.get("services", {}).get("mysql", {})
ports = mysql.get("ports") or []
if ports:
    print("mysql.ports not empty: %r" % (ports,), file=sys.stderr)
    sys.exit(1)
' "$CONFIG_JSON"; then
            pass "JSON inspect confirms mysql.ports empty"
          else
            fail "JSON inspect found mysql published ports"
          fi
        fi
      else
        echo "NOTE: usable python not found; YAML effective-config checks used"
      fi
    fi
  fi
fi

if [ "$failed" -ne 0 ]; then
  echo "Production MySQL hardening assertions failed." >&2
  exit 1
fi

echo "All production MySQL hardening assertions passed."
exit 0
