#!/bin/bash
# =============================================================================
#  E2E test cơ chế Haravan trên production (tunganhahihi.io.vn)
# =============================================================================
set -uo pipefail

BASE="https://tunganhahihi.io.vn"
UA="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36"

COOKIE=$(node scripts/infinityfree-challenge.js "$BASE/" 2>/dev/null | tail -1)

api() {
    local method="$1"; local path="$2"; local body="${3:-}"
    if [ -n "$body" ]; then
        curl -sk -m 40 -A "$UA" -b "$COOKIE" \
            -H "Accept: application/json" -H "Content-Type: application/json" \
            -H "Authorization: Bearer $TOKEN" \
            -X "$method" "$BASE$path" -d "$body"
    else
        curl -sk -m 40 -A "$UA" -b "$COOKIE" \
            -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" \
            -X "$method" "$BASE$path"
    fi
}

echo "🔐 Đăng nhập..."
TOKEN=$(curl -sk -m 30 -A "$UA" -b "$COOKIE" \
    -H "Accept: application/json" -H "Content-Type: application/json" \
    -X POST "$BASE/api/auth/login" \
    -d '{"email":"admin@pin247.vn","password":"admin1234"}' \
    | python3 -c "import sys,json; print(json.load(sys.stdin).get('data',{}).get('token',''))")

if [ -z "$TOKEN" ]; then
    echo "❌ Không lấy được token"
    exit 1
fi
echo "✅ Token OK (${#TOKEN} ký tự)"

# ------------------------------------------------------------------
echo ""
echo "━━━ GĐ1: Thuộc tính động & tự sinh biến thể ━━━"

RESULT=$(api POST "/api/admin/products" '{
    "name":"[E2E-HARAVAN] Pin 21V test",
    "price":749000,
    "cost_price":500000,
    "attributes":[
        {"name":"Kiểu đầu","values":["Makita","Bosch"]},
        {"name":"Dung lượng","values":["5.0Ah","7.0Ah"]}
    ]
}')

PRODUCT_ID=$(echo "$RESULT" | python3 -c "
import sys,json
d=json.load(sys.stdin)
p=d.get('data',{})
print(p.get('id',''))
")

echo "$RESULT" | python3 -c "
import sys,json
d=json.load(sys.stdin)
p=d.get('data')
if not p:
    print('❌', json.dumps(d)[:300]); sys.exit(1)
print('✅ Sản phẩm id =', p['id'])
print('   Biến thể sinh tự động:', len(p['variants']), '(kỳ vọng 4)')
print('   Thuộc tính:', len(p['attributes']))
for v in p['variants']:
    print('     -', v['sku'][:44], '|', v['option_1_value'], '/', v['option_2_value'], '|', v['price'])
"

VARIANT_ID=$(echo "$RESULT" | python3 -c "
import sys,json
d=json.load(sys.stdin)
v=(d.get('data',{}).get('variants') or [{}])[0]
print(v.get('id',''))
")
VARIANT_SKU=$(echo "$RESULT" | python3 -c "
import sys,json
d=json.load(sys.stdin)
v=(d.get('data',{}).get('variants') or [{}])[0]
print(v.get('sku',''))
")

# ------------------------------------------------------------------
echo ""
echo "━━━ GĐ2: Đơn vị tính ━━━"

api PUT "/api/admin/products/$PRODUCT_ID/variants/$VARIANT_ID/units" '{
    "units":[
        {"name":"viên","conversion_rate":1,"is_base":true},
        {"name":"lô 20","conversion_rate":20},
        {"name":"hộp 100","conversion_rate":100}
    ]
}' | python3 -c "
import sys,json
d=json.load(sys.stdin)
units=(d.get('data') or {}).get('units') or []
if not units:
    print('❌', json.dumps(d)[:300]); sys.exit(1)
print('✅ Đơn vị tính:', len(units))
for u in units:
    print('   -', u['name'], '| rate', u['conversion_rate'], '| giá', u['price'], '| base' if u['is_base'] else '')
"

# ------------------------------------------------------------------
echo ""
echo "━━━ GĐ3a: Lịch sử cập nhật biến thể ━━━"

api PUT "/api/admin/products/$PRODUCT_ID/variants/$VARIANT_ID" '{"price":799000,"sale_price":749000}' > /dev/null

api GET "/api/admin/products/$PRODUCT_ID/variants/$VARIANT_ID/history" | python3 -c "
import sys,json
d=json.load(sys.stdin)
h=d.get('data') or []
print('✅ Số dòng lịch sử:', len(h))
for x in h:
    print('   -', x.get('summary'))
"

# ------------------------------------------------------------------
echo ""
echo "━━━ GĐ3b: Thẻ kho biến thể ━━━"

api GET "/api/admin/products/$PRODUCT_ID/variants/$VARIANT_ID" | python3 -c "
import sys,json
d=json.load(sys.stdin)
v=d.get('data') or {}
inv=v.get('inventory') or {}
print('✅ Thẻ kho:', {k:inv.get(k) for k in ('available_stock','reserved_stock','on_hand_stock','safety_stock','sellable_quantity')})
"

# ------------------------------------------------------------------
echo ""
echo "━━━ GĐ3c: Nhân bản sản phẩm ━━━"

api POST "/api/admin/products/$PRODUCT_ID/duplicate" | python3 -c "
import sys,json
d=json.load(sys.stdin)
c=d.get('data')
if not c:
    print('❌', json.dumps(d)[:300]); sys.exit(1)
print('✅ Bản sao id =', c['id'], '| tên:', c['name'])
print('   Biến thể copy:', len(c['variants']), '| Thuộc tính copy:', len(c['attributes']))
print('   Đơn vị tính copy (biến thể đầu):', len((c['variants'] or [{}])[0].get('units') or []))
print('   Trạng thái:', 'Đang bán' if c['is_active'] else 'Ẩn (chờ review)')
"

# ------------------------------------------------------------------
echo ""
echo "━━━ GĐ3d: Thao tác hàng loạt ━━━"

api POST "/api/admin/products-bulk" "{\"ids\":[$PRODUCT_ID],\"action\":\"deactivate\"}" | python3 -c "
import sys,json
d=json.load(sys.stdin)
print('✅ Bulk:', d.get('message'), '| affected =', (d.get('data') or {}).get('affected'))
"

api POST "/api/admin/products-bulk" "{\"ids\":[$PRODUCT_ID],\"action\":\"activate\"}" > /dev/null

# ------------------------------------------------------------------
echo ""
echo "━━━ Danh sách admin: số biến thể ━━━"

api GET "/api/admin/products?per_page=50" | python3 -c "
import sys,json
d=json.load(sys.stdin)
rows=d.get('data') or []
bad=[r for r in rows if r.get('has_variants') and r.get('variants_count',0)==0]
print('✅ Tổng sản phẩm:', len(rows))
print('   Sản phẩm có biến thể nhưng hiện \"0 biến thể\":', len(bad))
for r in rows[:5]:
    print('   -', r['name'][:38], '| variants_count =', r.get('variants_count'), '| attributes =', len(r.get('attributes') or []))
"

# ------------------------------------------------------------------
echo ""
echo "━━━ Storefront: đơn vị tính & option ━━━"

SLUG=$(echo "$RESULT" | python3 -c "
import sys,json
print(json.load(sys.stdin).get('data',{}).get('slug',''))
")

curl -sk -m 30 -A "$UA" -b "$COOKIE" -H "Accept: application/json" "$BASE/api/products/$SLUG" | python3 -c "
import sys,json
d=json.load(sys.stdin)
p=d.get('data')
if not p:
    print('❌', json.dumps(d)[:200]); sys.exit(1)
print('✅ Storefront sản phẩm:', p['name'])
print('   Biến thể hiển thị:', len(p.get('variants') or []))
for v in (p.get('variants') or []):
    print('     -', v.get('option_1_value'), '/', v.get('option_2_value'), '| units:', len(v.get('units') or []))
"

# ------------------------------------------------------------------
echo ""
echo "━━━ Dọn dẹp ━━━"
api DELETE "/api/admin/products/$PRODUCT_ID" > /dev/null && echo "✅ Đã xoá sản phẩm test"
