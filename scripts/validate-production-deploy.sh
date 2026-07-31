#!/usr/bin/env bash
# RC1-G4C.5 — static production preflight only (no containers).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# shellcheck source=lib/production-preflight.sh
source "${ROOT}/scripts/lib/production-preflight.sh"

production_preflight_static ".env"
