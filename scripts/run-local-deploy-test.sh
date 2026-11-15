#!/usr/bin/env bash
#
# =============================================================================
#  PIN247 — chạy server mô phỏng InfinityFree + smoke test + E2E test
#
#  Dùng PHP built-in server với router mô phỏng hành vi .htaccess
#  (DocumentRoot = htdocs gốc, giống InfinityFree).
#
#  CÁCH DÙNG:
#     bash scripts/run-local-deploy-test.sh
#
#  Script sẽ:
#     1. Giải nén gói deploy vào /tmp/pin-sim/htdocs
#     2. Tạo .env production trỏ về MySQL local
#     3. Khởi động server ở port 8099
#     4. Chạy smoke test (24 kiểm tra)
#     5. Chạy E2E test (10 kiểm tra luồng chính)
#     6. Dừng server
# =============================================================================

set -uo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SIM_DIR="/tmp/pin-sim"
PORT="${PORT:-8099}"
DEPLOY_ZIP="$ROOT_DIR/deploy/pin247-deploy.zip"

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[0;33m'; BLUE='\033[0;34m'; NC='\033[0m'

info() { echo -e "${BLUE}[test ]${NC} $*"; }
ok()   { echo -e "${GREEN}[  ok ]${NC} $*"; }
warn() { echo -e "${YELLOW}[ warn]${NC} $*"; }
fail() { echo -e "${RED}[ fail]${NC} $*"; }

[ -f "$DEPLOY_ZIP" ] || { fail "Chưa có $DEPLOY_ZIP — chạy: bash scripts/build-deploy.sh"; exit 1; }

# --- Dọn thư mục mô phỏng ---
info "Chuẩn bị môi trường mô phỏng tại $SIM_DIR ..."
pkill -f "php -S 127.0.0.1:${PORT}" 2>/dev/null || true
rm -rf "$SIM_DIR/htdocs"
mkdir -p "$SIM_DIR/htdocs"

info "Giải nén gói deploy ..."
(cd "$SIM_DIR/htdocs" && unzip -q "$DEPLOY_ZIP")

# --- Tạo .env production trỏ về MySQL local ---
if [ ! -f "$ROOT_DIR/deploy/.env.production.local" ]; then
    warn "Chưa có deploy/.env.production.local — dùng .env mặc định trỏ local."
    cp "$ROOT_DIR/.env" "$SIM_DIR/htdocs/.env"
else
    cp "$ROOT_DIR/deploy/.env.production.local" "$SIM_DIR/htdocs/.env"
fi

# Ghi đè thông tin DB về MySQL local (để test offline).
sed -i '' \
    -e 's|^DB_HOST=.*|DB_HOST=127.0.0.1|' \
    -e 's|^DB_DATABASE=.*|DB_DATABASE=pin247_shop|' \
    -e 's|^DB_USERNAME=.*|DB_USERNAME=root|' \
    -e 's|^DB_PASSWORD=.*|DB_PASSWORD=|' \
    -e "s|^APP_URL=.*|APP_URL=http://127.0.0.1:${PORT}|" \
    "$SIM_DIR/htdocs/.env"

# --- Quyền ghi (mô phỏng chmod 777 trên hosting) ---
chmod -R 777 "$SIM_DIR/htdocs/storage" "$SIM_DIR/htdocs/bootstrap/cache" 2>/dev/null || true

# --- Khởi động server ---
info "Khởi động PHP server trên port ${PORT} ..."
PIN_SIM_ROOT="$SIM_DIR/htdocs" PHP_CLI_SERVER_WORKERS=4 \
    php -S "127.0.0.1:${PORT}" -t "$SIM_DIR/htdocs" "$ROOT_DIR/scripts/sim-router.php" \
    > "$SIM_DIR/server.log" 2>&1 &
SERVER_PID=$!

# Chờ server sẵn sàng.
for i in $(seq 1 30); do
    if curl -s -o /dev/null -m 2 "http://127.0.0.1:${PORT}/"; then
        break
    fi
    sleep 0.5
done

if ! kill -0 "$SERVER_PID" 2>/dev/null; then
    fail "Server không khởi động được. Log:"
    cat "$SIM_DIR/server.log"
    exit 1
fi
ok "Server đang chạy (PID $SERVER_PID)"

# --- Chạy smoke test ---
bash "$ROOT_DIR/scripts/smoke-test.sh" "http://127.0.0.1:${PORT}"
RESULT=$?

# --- Chạy E2E test (luồng chính: login, checkout mock, admin sync) ---
echo ""
info "Chạy E2E test (auth / checkout mock / admin sync) ..."
bash "$ROOT_DIR/scripts/e2e-test.sh" "http://127.0.0.1:${PORT}"
E2E_RESULT=$?

if [ "$E2E_RESULT" -ne 0 ]; then
    RESULT=1
fi

# --- Dừng server ---
kill "$SERVER_PID" 2>/dev/null || true
wait "$SERVER_PID" 2>/dev/null || true

echo ""
if [ "$RESULT" -eq 0 ]; then
    echo -e " ${GREEN}✔ SMOKE + E2E TEST PASS${NC}"
else
    echo -e " ${RED}✗ SMOKE / E2E TEST FAIL${NC} — xem log: $SIM_DIR/server.log"
    echo "  Laravel log: $SIM_DIR/htdocs/storage/logs/laravel.log"
fi

exit $RESULT
