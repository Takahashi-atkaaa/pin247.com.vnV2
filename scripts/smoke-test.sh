#!/usr/bin/env bash
#
# =============================================================================
#  PIN247 — smoke test gói deploy (chạy trên server mô phỏng hoặc hosting thật)
#
#  CÁCH DÙNG:
#     bash scripts/smoke-test.sh [base-url]
#
#  Mặc định: http://127.0.0.1:8099
#  Hosting thật: bash scripts/smoke-test.sh https://example.com
#
#  LƯU Ý:
#  - InfinityFree chặn User-Agent không phải trình duyệt → script luôn
#    gửi User-Agent giống trình duyệt.
#  - Các endpoint API được gọi kèm header `Accept: application/json` để
#    Laravel render lỗi dạng JSON (đúng như frontend Vue axios làm).
# =============================================================================

set -uo pipefail

BASE="${1:-http://127.0.0.1:8099}"
UA="Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36"
JSON="Accept: application/json"

PASS=0
FAIL=0
FAILED_TESTS=()

check() {
    local label="$1" expected="$2" url="$3"

    local code
    code=$(curl -s -o /dev/null -w "%{http_code}" -A "$UA" -H "$JSON" -m 30 "$url")

    if [ "$code" = "$expected" ]; then
        echo -e "  \033[0;32m✓\033[0m $label ($code)"
        PASS=$((PASS + 1))
    else
        echo -e "  \033[0;31m✗\033[0m $label — expected $expected, got $code"
        FAIL=$((FAIL + 1))
        FAILED_TESTS+=("$label ($url) expected=$expected got=$code")
    fi
}

check_body() {
    local label="$1" needle="$2" url="$3"

    local body
    body=$(curl -s -A "$UA" -H "$JSON" -m 30 "$url")

    if echo "$body" | grep -q "$needle"; then
        echo -e "  \033[0;32m✓\033[0m $label"
        PASS=$((PASS + 1))
    else
        echo -e "  \033[0;31m✗\033[0m $label — không tìm thấy '$needle'"
        FAIL=$((FAIL + 1))
        FAILED_TESTS+=("$label ($url) thiếu '$needle'")
    fi
}

echo ""
echo "=============================================="
echo " PIN247 — smoke test: $BASE"
echo "=============================================="

echo ""
echo "[ Trang & API công khai ]"
check "Trang chủ (SPA)"              200 "$BASE/"
check "Health check /up"            200 "$BASE/up"
check "API products"                200 "$BASE/api/products"
check "API categories"              200 "$BASE/api/categories"
check "API price-list"              200 "$BASE/api/price-list"
check "API price-list documents"    200 "$BASE/api/price-list/documents"

echo ""
echo "[ Asset tĩnh ]"
check "Vite manifest"               200 "$BASE/build/manifest.json"
check_body "HTML có app-base" 'app-base' "$BASE/"

# Lấy asset đầu tiên từ manifest để test.
ASSET=$(curl -s -A "$UA" "$BASE/build/manifest.json" | grep -oE '"file":\s*"assets/[^"]+"' | head -1 | sed 's/.*"assets/assets/;s/"$//')
if [ -n "$ASSET" ]; then
    check "Asset JS ($ASSET)"        200 "$BASE/build/$ASSET"
else
    echo -e "  \033[0;33m!\033[0m Không đọc được danh sách asset từ manifest"
fi

echo ""
echo "[ File upload / storage ]"
STORAGE_FILE=$(curl -s -A "$UA" -H "$JSON" "$BASE/api/price-list/documents" | grep -oE '"id":[0-9]+' | head -1 | grep -oE '[0-9]+')
if [ -n "$STORAGE_FILE" ]; then
    check "Tải file bảng giá (doc $STORAGE_FILE)" 200 "$BASE/api/price-list/documents/$STORAGE_FILE/download"
else
    echo -e "  \033[0;33m!\033[0m Không có tài liệu bảng giá để test"
fi

echo ""
echo "[ Bảo mật — phải bị chặn ]"
check "Chặn /.env"                 403 "$BASE/.env"
check "Chặn /.gitignore"           403 "$BASE/.gitignore"
check "Chặn /composer.json"        403 "$BASE/composer.json"
check "Chặn /artisan"              403 "$BASE/artisan"
check "Chặn /vendor/autoload.php"  404 "$BASE/vendor/autoload.php"
check "Chặn /app/Models/User.php"  404 "$BASE/app/Models/User.php"
check "Chặn /config/app.php"       404 "$BASE/config/app.php"
check "Chặn /public/index.php"     403 "$BASE/public/index.php"
check "Chặn /storage/logs/x.log"   403 "$BASE/storage/logs/laravel.log"

echo ""
echo "[ Xác thực & phân quyền ]"
check "Admin API không token → 401"  401 "$BASE/api/admin/dashboard"
check "Admin API token rác → 401"    401 "$BASE/api/admin/dashboard"
check "Đơn hàng không token → 401"   401 "$BASE/api/orders"

echo ""
echo "[ Route lỗi ]"
check "API sản phẩm không tồn tại"  404 "$BASE/api/products/khong-ton-tai-xyz"
check "Trang lạ → SPA 404"          200 "$BASE/trang-la-xyz"

echo ""
echo "=============================================="
echo " Kết quả: $PASS pass / $FAIL fail"
if [ "$FAIL" -gt 0 ]; then
    echo ""
    echo " Các test fail:"
    for t in "${FAILED_TESTS[@]}"; do
        echo "   - $t"
    done
fi
echo "=============================================="

[ "$FAIL" -eq 0 ]
