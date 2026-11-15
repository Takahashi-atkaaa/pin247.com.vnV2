#!/usr/bin/env bash
#
# =============================================================================
#  PIN247 — build deploy bundle cho shared hosting (InfinityFree)
#
#  Tạo: deploy/pin247-deploy.zip  (~chứa toàn bộ code + vendor --no-dev)
#
#  CÁCH DÙNG:
#     bash scripts/build-deploy.sh
#
#  KẾT QUẢ:
#     deploy/staging/              — thư mục staging (nội dung sẽ nằm ở htdocs)
#     deploy/pin247-deploy.zip    — file zip để upload lên hosting
#
#  BẢO MẬT:
#     - KHÔNG đưa `.env` vào gói (upload riêng qua FTP sau khi .htaccess có).
#     - KHÔNG đưa node_modules / tests / resources/js+css / .git.
#     - KHÔNG đưa file SQL (dump riêng ở Giai đoạn 4).
# =============================================================================

set -euo pipefail

# --- Đường dẫn ---
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DEPLOY_DIR="$ROOT_DIR/deploy"
STAGING_DIR="$DEPLOY_DIR/staging"
ZIP_FILE="$DEPLOY_DIR/pin247-deploy.zip"

cd "$ROOT_DIR"

# --- Màu ---
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
NC='\033[0m'

info()  { echo -e "${BLUE}[build]${NC} $*"; }
ok()    { echo -e "${GREEN}[  ok ]${NC} $*"; }
warn()  { echo -e "${YELLOW}[ warn]${NC} $*"; }
fail()  { echo -e "${RED}[ fail]${NC} $*"; exit 1; }

echo "=============================================="
echo " PIN247 — build deploy bundle"
echo "=============================================="

# ---------------------------------------------------------------------------
# 0. Kiểm tra môi trường
# ---------------------------------------------------------------------------
command -v composer >/dev/null 2>&1 || fail "Không tìm thấy composer."
command -v zip      >/dev/null 2>&1 || fail "Không tìm thấy zip."

# ---------------------------------------------------------------------------
# 1. Build frontend (nếu chưa build)
# ---------------------------------------------------------------------------
if [ ! -f "$ROOT_DIR/public/build/manifest.json" ]; then
    info "Chưa có public/build/manifest.json → chạy npm run build ..."
    command -v npm >/dev/null 2>&1 || fail "Không tìm thấy npm."
    npm run build
fi
[ -f "$ROOT_DIR/public/build/manifest.json" ] || fail "Build frontend thất bại: thiếu manifest.json."
ok "Frontend đã build (public/build/manifest.json)"

# ---------------------------------------------------------------------------
# 2. Xoá staging cũ
# ---------------------------------------------------------------------------
info "Xoá staging cũ ..."
rm -rf "$STAGING_DIR" "$ZIP_FILE"
mkdir -p "$STAGING_DIR"
mkdir -p "$DEPLOY_DIR/database"

# ---------------------------------------------------------------------------
# 3. Copy mã nguồn runtime
# ---------------------------------------------------------------------------
info "Copy mã nguồn runtime ..."

copy_dir() {
    local src="$1" dst="$2"
    if [ -d "$src" ]; then
        # Copy NỘI DUNG của thư mục nguồn vào dst.
        # Dùng `rsync src/ dst/` — nếu copy `src` (không slash) vào dst đã
        # tồn tại thì rsync sẽ tạo thư mục con lồng nhau (app/app, config/config).
        rsync -a --exclude='.DS_Store' "$src/" "$dst/"
        ok "  + $1"
    else
        warn "  ! bỏ qua (không tồn tại): $1"
    fi
}

copy_dir "$ROOT_DIR/app"           "$STAGING_DIR/app"
copy_dir "$ROOT_DIR/bootstrap"     "$STAGING_DIR/bootstrap"
copy_dir "$ROOT_DIR/config"        "$STAGING_DIR/config"
copy_dir "$ROOT_DIR/database"      "$STAGING_DIR/database"
copy_dir "$ROOT_DIR/routes"        "$STAGING_DIR/routes"
copy_dir "$ROOT_DIR/public"        "$STAGING_DIR/public"

# resources: CHỈ copy `views/` (blade templates cần cho Laravel render).
# resources/css + resources/js đã được compile vào public/build nên không cần.
mkdir -p "$STAGING_DIR/resources"
copy_dir "$ROOT_DIR/resources/views" "$STAGING_DIR/resources/views"

# File rời lẻ
for f in artisan composer.json composer.lock index.php .htaccess; do
    if [ -f "$ROOT_DIR/$f" ]; then
        cp "$ROOT_DIR/$f" "$STAGING_DIR/$f"
        ok "  + $f"
    else
        warn "  ! thiếu file: $f"
    fi
done

# database/database.sqlite là file local, không dùng trên hosting (MySQL).
rm -f "$STAGING_DIR/database/database.sqlite"

# config/view.php là file BẮT BUỘC (không có sẵn trong skeleton Laravel 12):
# định nghĩa đường dẫn blade template + thư mục cache compiled view.
# Nếu thiếu => "View [app] not found".
if [ ! -f "$STAGING_DIR/config/view.php" ]; then
    cp "$ROOT_DIR/vendor/laravel/framework/config/view.php" "$STAGING_DIR/config/view.php"
    ok "  + config/view.php (từ framework skeleton)"
fi

# ---------------------------------------------------------------------------
# 4. Storage — khung thư mục + file upload hiện có
# ---------------------------------------------------------------------------
info "Copy storage (khung thư mục + file bảng giá đã upload) ..."

mkdir -p "$STAGING_DIR/storage/app/public"
mkdir -p "$STAGING_DIR/storage/app/private"
mkdir -p "$STAGING_DIR/storage/framework/cache/data"
mkdir -p "$STAGING_DIR/storage/framework/sessions"
mkdir -p "$STAGING_DIR/storage/framework/testing"
mkdir -p "$STAGING_DIR/storage/framework/views"
mkdir -p "$STAGING_DIR/storage/logs"

# File .gitignore trong storage giữ để không commit nhầm.
for f in storage/app/public/.gitignore \
         storage/app/.gitignore \
         storage/app/private/.gitignore \
         storage/framework/.gitignore \
         storage/logs/.gitignore; do
    [ -f "$ROOT_DIR/$f" ] && cp "$ROOT_DIR/$f" "$STAGING_DIR/$f"
done

# Copy file đã upload trong storage/app/public (ảnh sản phẩm, file bảng giá)
# Bỏ qua .gitignore và file tạm.
if [ -d "$ROOT_DIR/storage/app/public" ]; then
    rsync -a \
        --exclude='.gitignore' \
        --exclude='*.csv' \
        "$ROOT_DIR/storage/app/public/" "$STAGING_DIR/storage/app/public/"
    n=$(find "$STAGING_DIR/storage/app/public" -type f | wc -l | tr -d ' ')
    ok "  + storage/app/public ($n file)"
fi

# ---------------------------------------------------------------------------
# 5. Vendor --no-dev
# ---------------------------------------------------------------------------
info "Cài vendor --no-dev (có thể mất 1-2 phút) ..."
(
    cd "$STAGING_DIR"
    COMPOSER_ALLOW_SUPERUSER=1 composer install \
        --no-dev \
        --optimize-autoloader \
        --no-interaction \
        --no-progress \
        --no-scripts \
        2>&1 | tail -5
)

[ -f "$STAGING_DIR/vendor/autoload.php" ] || fail "composer install thất bại: thiếu vendor/autoload.php."
ok "Vendor đã cài (--no-dev --optimize-autoloader)"

# ---------------------------------------------------------------------------
# 6. Dọn dẹn
# ---------------------------------------------------------------------------
info "Dọn dẹn ..."

# Bỏ symlink public/storage (InfinityFree cấm symlink) — dùng rewrite .htaccess.
rm -f "$STAGING_DIR/public/storage"

# Bỏ file rác.
find "$STAGING_DIR" -name '.DS_Store' -delete 2>/dev/null || true
find "$STAGING_DIR/storage/framework" -type f ! -name '.gitignore' -delete 2>/dev/null || true
find "$STAGING_DIR/storage/logs" -type f ! -name '.gitignore' -delete 2>/dev/null || true
rm -f "$STAGING_DIR/.phpunit.result.cache"

# KHÔNG có .env trong gói — kiểm tra chắc chắn.
if [ -f "$STAGING_DIR/.env" ]; then
    rm -f "$STAGING_DIR/.env"
    warn "  ! đã xoá .env lọt vào gói (KHÔNG BAO GIỜ đưa .env lên hosting qua zip)"
fi

# bootstrap/cache: giữ .gitignore, xoá cache cũ.
rm -f "$STAGING_DIR/bootstrap/cache"/*.php 2>/dev/null || true
mkdir -p "$STAGING_DIR/bootstrap/cache"
touch "$STAGING_DIR/bootstrap/cache/.gitignore"

# ---------------------------------------------------------------------------
# 7. Zip
# ---------------------------------------------------------------------------
info "Đóng gói ..."
cd "$DEPLOY_DIR"
rm -f "$ZIP_FILE"

# Đặt tên file để giải nén ra gốc htdocs (không có thư mục con "staging").
info "Tạo zip phẳng (giải nén ra đúng thư mục gốc htdocs) ..."
TMP_ZIP="$DEPLOY_DIR/.flat.zip"
rm -f "$TMP_ZIP"
(cd "$STAGING_DIR" && zip -rq "$TMP_ZIP" . -x '*.DS_Store')
mv "$TMP_ZIP" "$ZIP_FILE"

SIZE=$(du -h "$ZIP_FILE" | cut -f1)
COUNT=$(unzip -l "$ZIP_FILE" | tail -1 | awk '{print $2}')

echo ""
echo "=============================================="
echo -e " ${GREEN}✔ Deploy bundle đã sẵn sàng${NC}"
echo "=============================================="
echo "  File    : deploy/pin247-deploy.zip"
echo "  Dung lượng: $SIZE"
echo "  Số file : $COUNT"
echo ""
echo "  Bước tiếp theo:"
echo "    1. Upload .htaccess (tên 'htaccess') lên htdocs TRƯỚC"
echo "    2. Upload pin247-deploy.zip lên htdocs, giải nén tại htdocs"
echo "    3. Upload .env (tên tạm 'env.setup') rồi đổi tên thành '.env'"
echo "    4. Import SQL: bash scripts/dump-database.sh"
echo "=============================================="
