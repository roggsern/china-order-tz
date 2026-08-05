#!/usr/bin/env bash
# RC1-G4C.5 — static production preflight (no containers started).
# Also asserts production Compose never publishes MySQL (compose config render only).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# shellcheck source=lib/production-preflight.sh
source "${ROOT}/scripts/lib/production-preflight.sh"

production_preflight_static ".env"
# MySQL must never be published in production effective Compose (post-incident hardening).
bash "${ROOT}/scripts/assert-production-mysql-hardening.sh"
