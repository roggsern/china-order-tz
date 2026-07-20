#!/usr/bin/env bash
# Manual web deploy helper for operators with SSH access to the VPS.
# Prefer GitHub Actions "Deploy Next.js to VPS" for audited deploys.
# Usage: ./scripts/deploy-web-remote.sh [user@host] [ref]
set -euo pipefail

TARGET="${1:?usage: $0 user@host [ref]}"
REF="${2:-main}"
REPO_DIR="/root/china-order-tz"

ssh -o BatchMode=yes "$TARGET" bash -s -- "$REF" "$REPO_DIR" <<'EOF'
set -euo pipefail
REF="$1"
REPO_DIR="$2"
cd "$REPO_DIR"
PREV_SHA="$(git rev-parse HEAD)"
echo "PREV_SHA=${PREV_SHA}"
git fetch origin --tags --prune
git checkout --force "$REF"
git reset --hard "$REF"
rm -rf apps/web/.next
export NODE_OPTIONS="--max-old-space-size=512"
npm ci --no-audit --no-fund
npm run build --workspace=apps/web
pm2 reload ecosystem.config.js --only china-order-tz --update-env || pm2 start ecosystem.config.js --only china-order-tz
pm2 save
echo "Deployed $(git rev-parse HEAD) (was ${PREV_SHA})"
EOF
