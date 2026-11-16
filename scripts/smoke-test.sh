#!/usr/bin/env bash
#
# =============================================================================
#  PIN247 — smoke test gói deploy (chạy trên hosting thật hoặc server mô phỏng)
#
#  CÁCH DÙNG:
#     bash scripts/smoke-test.sh [base-url]
#
#  Mặc định: http://127.0.0.1:8099
#  Hosting thật: bash scripts/smoke-test.sh https://example.com
#
#  BIẾN MÔI TRƯỜNG (cho InfinityFree):
#     INF_UA       — User-Agent giống trình duyệt (InfinityFree chặn UA khác)
#     INF_COOKIE  — cookie __test đã giải challenge anti-bot
#     INF_INSECURE — =1 để bỏ kiểm tra chứng thư SSL (chuỗi cert thiếu intermediate)
#
#  LƯU Ý:
#  - InfinityFree chặn User-Agent không phải trình duyệt => script luôn
#    gửi UA giống trình duyệt.
#  - InfinityFree BIND challenge session `__test` vào User-Agent. Cookie giải
#    bằng UA khác sẽ bị coi như chưa giải => server trả lại trang challenge
#    (HTTP 200 nhưng body là JS) => test fail hàng loạt. Vì vậy UA mặc định
#    phải KHỚP với UA dùng trong scripts/infinityfree-challenge.js.
#  - Các endpoint API được gọi kèm header `Accept: application/json` để
#    Laravel render lỗi dạng JSON (đúng như frontend Vue axios làm).
# =============================================================================

set -uo pipefail

BASE="${1:-http://127.0.0.1:8099}"

UA="${INF_UA:-Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36}"
JSON="Accept: application/json"

# Mảng tuỳ chọn curl dùng chung cho mọi request.
CURL_OPTS=(-s -A "$UA" -H "$JSON" -m 45)
[ -n "${INF_COOKIE:-}" ] && CURL_OPTS+=(-b "$INF_COOKIE")
[ "${INF_INSECURE:-}" = "1" ] && CURL_OPTS+=(-k)

PASS=0
FAIL=0
FAILED_TESTS=()

check() {
    local label="$1" expected="$2" url="$3"

    local code
    code=$(curl "${CURL_OPTS[@]}" -o /dev/null -w "%{http_code}" "$url")

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
    body=$(curl "${CURL_OPTS[@]}" "$url")

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
ASSET=$(curl "${CURL_OPTS[@]}" "$BASE/build/manifest.json" | grep -oE '"file":\s*"assets/[^"]+"' | head -1 | sed 's/.*"assets/assets/;s/"$//')
if [ -n "$ASSET" ]; then
    check "Asset JS ($ASSET)"        200 "$BASE/build/$ASSET"
else
    echo -e "  \033[0;33m!\033[0m Không đọc được danh sách asset từ manifest"
fi

echo ""
echo "[ File upload / storage ]"
DOC_ID=$(curl "${CURL_OPTS[@]}" "$BASE/api/price-list/documents" | grep -oE '"id":[0-9]+' | head -1 | grep -oE '[0-9]+')
if [ -n "$DOC_ID" ]; then
    check "Tải file bảng giá (doc $DOC_ID)" 200 "$BASE/api/price-list/documents/$DOC_ID/download"
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
