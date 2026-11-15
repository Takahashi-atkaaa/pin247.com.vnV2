#!/usr/bin/env bash
#
# =============================================================================
#  PIN247 — dump database local ra file SQL cho hosting
#
#  CÁCH DÙNG:
#     bash scripts/dump-database.sh
#
#  KẾT QUẢ:
#     deploy/database/pin247.sql
#
#  AN TOÀN:
#     - KHÔNG dùng --force
#     - KHÔNG DROP DATABASE
#     - Chỉ schema + INSERT
# =============================================================================

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT_DIR="$ROOT_DIR/deploy/database"
OUT_FILE="$OUT_DIR/pin247.sql"

# Đường dẫn mysqldump của XAMPP (fallback về mysqldump trong PATH).
MYSQLDUMP="/Applications/XAMPP/xamppfiles/bin/mysqldump"
[ -x "$MYSQLDUMP" ] || MYSQLDUMP="$(command -v mysqldump || true)"

[ -n "$MYSQLDUMP" ] || { echo "[ fail ] Không tìm thấy mysqldump." >&2; exit 1; }

# Thông tin DB local (mặc định XAMPP: root / không mật khẩu).
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-pin247_shop}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"

mkdir -p "$OUT_DIR"

echo "[build ] Dump database '$DB_NAME' từ $DB_HOST ..."

# --skip-lock-tables: cho phép dump khi bảng đang được ghi.
# --no-tablespaces: cần quyền PROCESS (không có trên shared hosting).
MYSQLDUMP_ARGS=(
    -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER"
    --default-character-set=utf8mb4
    --single-transaction
    --skip-lock-tables
    --no-tablespaces
    --routines --triggers
    --add-drop-table
    --complete-insert
    "$DB_NAME"
)

if [ -n "$DB_PASS" ]; then
    MYSQLDUMP_ARGS=(-p"$DB_PASS" "${MYSQLDUMP_ARGS[@]}")
fi

"$MYSQLDUMP" "${MYSQLDUMP_ARGS[@]}" > "$OUT_FILE"

TABLES=$(grep -c '^CREATE TABLE' "$OUT_FILE" || true)
SIZE=$(du -h "$OUT_FILE" | cut -f1)

echo "[  ok ] $OUT_FILE"
echo "       Số bảng : $TABLES"
echo "       Dung lượng:  $SIZE"
echo ""
echo "  Bước tiếp theo: upload file này lên htdocs và import qua"
echo "  deploy-import.php hoặc phpMyAdmin."
