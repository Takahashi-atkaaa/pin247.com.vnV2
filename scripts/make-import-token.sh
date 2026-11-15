#!/usr/bin/env bash
#
# =============================================================================
#  PIN247 — sinh token ngẫu nhiên cho script import / giải nén DB
#
#  CÁCH DÙNG:
#     bash scripts/make-import-token.sh
#
#  KẾT QUẢ:
#     deploy/deploy-import.token.txt   (đã nằm trong .gitignore)
#
#  Token này được upload lên hosting cùng `deploy-import.php` và
#  `deploy-extract.php`. Script chỉ chạy được khi token trên URL khớp với
#  token trong file. Sau khi dùng xong, cả 3 file tự xoá.
# =============================================================================

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TOKEN_FILE="$ROOT_DIR/deploy/deploy-import.token.txt"

mkdir -p "$ROOT_DIR/deploy"

# 32 ký tự hex ngẫu nhiên = 128 bit entropy.
TOKEN=$(python3 -c "import secrets; print(secrets.token_hex(16))")

printf '%s' "$TOKEN" > "$TOKEN_FILE"
chmod 600 "$TOKEN_FILE"

echo "[  ok ] deploy/deploy-import.token.txt"
echo ""
echo "  Token: $TOKEN"
echo ""
echo "  URL giải nén:  https://<domain>/deploy-extract.php?token=$TOKEN"
echo "  URL import DB: https://<domain>/deploy-import.php?token=$TOKEN"
echo ""
echo "  (File này KHÔNG được commit — đã nằm trong .gitignore)"
