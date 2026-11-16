#!/usr/bin/env bash
#
# =============================================================================
#  PIN247 — E2E test trên server mô phỏng (hoặc hosting thật)
#
#  Kiểm tra các luồng chính:
#    1. Đăng nhập admin (Sanctum Bearer token)
#    2. /api/me, /api/admin/dashboard
#    3. Phân quyền: user thường gọi admin API → 403
#    4. Checkout tạo đơn + push BigSeller MOCK (BS-MOCK-xxxxxx)
#    5. Admin: đồng bộ tồn kho (mock)
#    6. Đăng xuất
#
#  CÁCH DÙNG:
#     bash scripts/e2e-test.sh [base-url]
#
#  Mặc định: http://127.0.0.1:8099
# =============================================================================

set -uo pipefail

BASE="${1:-http://127.0.0.1:8099}"
UA="${INF_UA:-Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36}"

ADMIN_EMAIL="${ADMIN_EMAIL:-admin@pin247.vn}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-admin1234}"
CUSTOMER_EMAIL="${CUSTOMER_EMAIL:-khachdemo@example.com}"
CUSTOMER_PASSWORD="${CUSTOMER_PASSWORD:-secret1234}"

# Tùy chọn cho InfinityFree: cookie anti-bot + bỏ kiểm tra SSL.
CURL_GLOBAL=(-s -A "$UA" -H "Accept: application/json")
[ -n "${INF_COOKIE:-}" ] && CURL_GLOBAL+=(-b "$INF_COOKIE")
[ "${INF_INSECURE:-}" = "1" ] && CURL_GLOBAL+=(-k)

PASS=0
FAIL=0
FAILED_TESTS=()

pass() { echo -e "  \033[0;32m✓\033[0m $1"; PASS=$((PASS + 1)); }
fail() {
    echo -e "  \033[0;31m✗\033[0m $1"
    FAIL=$((FAIL + 1))
    FAILED_TESTS+=("$1")
}

# api <method> <path> [token] [json-body]
api() {
    local method="$1" path="$2" token="${3:-}" body="${4:-}"
    local args=("${CURL_GLOBAL[@]}" -X "$method")

    [ -n "$token" ] && args+=(-H "Authorization: Bearer $token")
    [ -n "$body" ] && args+=(-H "Content-Type: application/json" -d "$body")

    curl "${args[@]}" -m 60 "$BASE$path"
}

api_code() {
    local method="$1" path="$2" token="${3:-}" body="${4:-}"
    local args=("${CURL_GLOBAL[@]}" -o /dev/null -w "%{http_code}" -X "$method")

    [ -n "$token" ] && args+=(-H "Authorization: Bearer $token")
    [ -n "$body" ] && args+=(-H "Content-Type: application/json" -d "$body")

    curl "${args[@]}" -m 60 "$BASE$path"
}

# json_get <json> <python-expression trên biến `d`>
json_get() {
    printf '%s' "$1" | python3 -c "
import json,sys
try:
    d = json.load(sys.stdin)
except Exception:
    print(''); sys.exit(0)
try:
    print($2)
except Exception:
    print('')
"
}

echo ""
echo "=============================================="
echo " PIN247 — E2E test: $BASE"
echo "=============================================="

# ---------------------------------------------------------------------------
echo ""
echo "[ 1. Đăng nhập admin ]"
LOGIN=$(api POST "/api/auth/login" "" "{\"email\":\"$ADMIN_EMAIL\",\"password\":\"$ADMIN_PASSWORD\"}")
ADMIN_TOKEN=$(json_get "$LOGIN" "d.get('token') or d.get('data',{}).get('token') or ''")

if [ -n "$ADMIN_TOKEN" ]; then
    pass "Đăng nhập admin thành công (token: ${ADMIN_TOKEN:0:12}...)"
else
    fail "Đăng nhập admin thất bại: $(printf '%s' "$LOGIN" | head -c 200)"
    echo ""
    echo " Kết quả: $PASS pass / $FAIL fail"
    exit 1
fi

# ---------------------------------------------------------------------------
echo ""
echo "[ 2. API đã xác thực ]"
ME=$(api GET "/api/me" "$ADMIN_TOKEN")
IS_ADMIN=$(json_get "$ME" "d.get('data',{}).get('is_admin')")
if [ "$IS_ADMIN" = "True" ] || [ "$IS_ADMIN" = "true" ]; then
    pass "/api/me trả đúng user admin"
else
    fail "/api/me sai: $(printf '%s' "$ME" | head -c 200)"
fi

CODE=$(api_code GET "/api/admin/dashboard" "$ADMIN_TOKEN")
if [ "$CODE" = "200" ]; then
    pass "Admin dashboard với token admin → 200"
else
    fail "Admin dashboard với token admin → $CODE (expected 200)"
fi

# ---------------------------------------------------------------------------
echo ""
echo "[ 3. Phân quyền ]"
CUST_LOGIN=$(api POST "/api/auth/login" "" "{\"email\":\"$CUSTOMER_EMAIL\",\"password\":\"$CUSTOMER_PASSWORD\"}")
CUST_TOKEN=$(json_get "$CUST_LOGIN" "d.get('token') or d.get('data',{}).get('token') or ''")

if [ -n "$CUST_TOKEN" ]; then
    pass "Đăng nhập khách hàng thành công"
    CODE=$(api_code GET "/api/admin/dashboard" "$CUST_TOKEN")
    if [ "$CODE" = "403" ]; then
        pass "User thường gọi admin API → 403"
    else
        fail "User thường gọi admin API → $CODE (expected 403)"
    fi
else
    fail "Đăng nhập khách hàng thất bại: $(printf '%s' "$CUST_LOGIN" | head -c 200)"
fi

# ---------------------------------------------------------------------------
echo ""
echo "[ 4. Checkout mock (BigSeller MOCK) ]"
PRODUCTS=$(api GET "/api/products")
SKU=$(json_get "$PRODUCTS" "d['data'][0]['default_variant']['sku'] if isinstance(d.get('data'),list) and d['data'] else ''")

if [ -z "$SKU" ] || [ "$SKU" = "None" ]; then
    # Fallback: lấy SKU đầu tiên tìm được trong bất kỳ product nào.
    SKU=$(json_get "$PRODUCTS" "next((p['default_variant']['sku'] for p in d['data'] if p.get('default_variant') and p['default_variant'].get('sku')), '')")
fi

if [ -n "$SKU" ]; then
    pass "Lấy được SKU từ API: $SKU"

    CHECKOUT=$(api POST "/api/checkout" "" "{
        \"items\": [{\"sku\": \"$SKU\", \"quantity\": 1}],
        \"name\": \"Khách Test E2E\",
        \"phone\": \"0912345678\",
        \"email\": \"e2e@example.com\",
        \"address\": \"123 Nguyễn Huệ, P. Bến Nghé\",
        \"payment_method\": \"COD\"
    }")
    CODE_ORDER=$(json_get "$CHECKOUT" "d.get('data',{}).get('order_code','')")
    SYNC_STATUS=$(json_get "$CHECKOUT" "d.get('data',{}).get('sync_status','')")
    BS_CODE=$(json_get "$CHECKOUT" "d.get('data',{}).get('bigseller_order_code','')")

    if [ -n "$CODE_ORDER" ]; then
        pass "Checkout tạo đơn: $CODE_ORDER (sync_status=$SYNC_STATUS)"
    else
        fail "Checkout thất bại: $(printf '%s' "$CHECKOUT" | head -c 300)"
    fi

    if echo "$BS_CODE" | grep -q "BS-MOCK-"; then
        pass "BigSeller MOCK đã push: $BS_CODE"
    else
        fail "Mã đơn BigSeller không phải BS-MOCK-*: '$BS_CODE'"
    fi
else
    fail "Không lấy được SKU từ /api/products"
fi

# ---------------------------------------------------------------------------
echo ""
echo "[ 5. Admin: đồng bộ tồn kho (mock) ]"
SYNC=$(api POST "/api/admin/inventory/sync" "$ADMIN_TOKEN")
CODE_SYNC=$(api_code POST "/api/admin/inventory/sync" "$ADMIN_TOKEN")
if [ "$CODE_SYNC" = "200" ]; then
    pass "Đồng bộ tồn kho mock → 200 ($(json_get "$SYNC" "d.get('message','')"))"
else
    fail "Đồng bộ tồn kho → $CODE_SYNC: $(printf '%s' "$SYNC" | head -c 300)"
fi

# ---------------------------------------------------------------------------
echo ""
echo "[ 6. Đăng xuất ]"
CODE=$(api_code POST "/api/auth/logout" "$ADMIN_TOKEN")
if [ "$CODE" = "200" ] || [ "$CODE" = "204" ]; then
    pass "Đăng xuất → $CODE"
else
    fail "Đăng xuất → $CODE (expected 200/204)"
fi

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
