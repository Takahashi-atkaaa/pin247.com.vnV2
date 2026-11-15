#!/usr/bin/env bash
#
# =============================================================================
#  PIN247 — sinh file .env production cho hosting
#
#  CÁCH DÙNG:
#     bash scripts/make-production-env.sh <domain> <db_host> <db_name> <db_user> <db_pass>
#
#  KẾT QUẢ:
#     deploy/.env.production.local   (đã nằm trong .gitignore — KHÔNG commit)
#
#  APP_KEY được lấy tự động từ .env cục bộ để khớp với SQL dump
#  (bigseller_connections dùng encrypted cast).
# =============================================================================

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DEPLOY_DIR="$ROOT_DIR/deploy"
TEMPLATE="$ROOT_DIR/.env.production.example"
OUTPUT="$DEPLOY_DIR/.env.production.local"

DOMAIN="${1:-}"
DB_HOST="${2:-}"
DB_NAME="${3:-}"
DB_USER="${4:-}"
DB_PASS="${5:-}"

if [ -z "$DOMAIN" ] || [ -z "$DB_HOST" ] || [ -z "$DB_NAME" ] || [ -z "$DB_USER" ]; then
    echo "Cách dùng:" >&2
    echo "  bash scripts/make-production-env.sh <domain> <db_host> <db_name> <db_user> [db_pass]" >&2
    echo "" >&2
    echo "Ví dụ:" >&2
    echo "  bash scripts/make-production-env.sh example.com sql123.infinityfree.com if0_12345678_pin247 if0_12345678 'matkhau'" >&2
    exit 1
fi

# Lấy APP_KEY từ .env cục bộ.
APP_KEY=""
if [ -f "$ROOT_DIR/.env" ]; then
    APP_KEY=$(grep -E '^APP_KEY=' "$ROOT_DIR/.env" | head -1 | cut -d= -f2- | tr -d '"' | tr -d "'")
fi

if [ -z "$APP_KEY" ]; then
    echo "[ fail ] Không tìm thấy APP_KEY trong .env cục bộ." >&2
    exit 1
fi

[ -f "$TEMPLATE" ] || { echo "[ fail ] Không tìm thấy $TEMPLATE" >&2; exit 1; }

# Thay thế các placeholder.
sed \
    -e "s|^APP_KEY=.*|APP_KEY=${APP_KEY}|" \
    -e "s|^APP_URL=.*|APP_URL=https://${DOMAIN}|" \
    -e "s|^DB_HOST=.*|DB_HOST=${DB_HOST}|" \
    -e "s|^DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|" \
    -e "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USER}|" \
    -e "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASS}|" \
    "$TEMPLATE" > "$OUTPUT"

chmod 600 "$OUTPUT"

echo "[  ok ] deploy/.env.production.local"
echo ""
echo "  APP_URL    : https://${DOMAIN}"
echo "  DB_HOST    : ${DB_HOST}"
echo "  DB_DATABASE: ${DB_NAME}"
echo "  DB_USERNAME: ${DB_USER}"
echo "  APP_KEY    : (đã lấy từ .env cục bộ — khớp với SQL dump)"
echo ""
echo "  Kiểm tra lại nội dung trước khi upload:"
echo "    cat deploy/.env.production.local"
