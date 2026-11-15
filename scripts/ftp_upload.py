#!/usr/bin/env python3
"""
=================================================================================
 PIN247 — upload deploy bundle lên InfinityFree qua FTP

CÁCH DÙNG:
    # 1. Lưu thông tin FTP vào file (KHÔNG commit — đã nằm trong .gitignore)
    cat > deploy/ftp-credentials.txt <<'EOF'
    ftp_host=ftp.your-domain.com
    ftp_user=if0_xxx
    ftp_pass=your-password
    ftp_port=21
    remote_dir=/htdocs
    EOF

    # 2. Chạy upload
    python3 scripts/ftp_upload.py

    # Hoặc truyền tham số trực tiếp
    python3 scripts/ftp_upload.py --host ftp.x.com --user u --pass p

FILE ĐƯỢC UPLOAD (theo đúng thứ tự AN TOÀN):
    1. .htaccess          -> tên "htaccess" -> RNTO ".htaccess"  (đặt trước)
    2. pin247-deploy.zip -> giải nén ở htdocs (bằng File Manager hoặc
                             deploy-extract.php?token=...)
    3. deploy-extract.php + deploy-import.token.txt
    4. .env               -> tên "env.setup" -> RNTO ".env"
    5. pin247.sql + deploy-import.php  (nếu có)

GHI CHÚ QUAN TRỌNG:
    - File `.env` được upload với tên TẠM "env.setup" rồi đổi tên qua FTP
      "RNTO" => không bao giờ tồn tại trạng thái "đã upload .env mà chưa có
      .htaccess chặn".
    - InfinityFree không cho đổi tên file bắt đầu bằng "." qua File Manager
      web, nhưng FTP RNTO thì được.
"""

import argparse
import getpass
import os
import sys
import ftplib
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
DEPLOY_DIR = ROOT / "deploy"
CRED_FILE = DEPLOY_DIR / "ftp-credentials.txt"

RED = "\033[0;31m"
GREEN = "\033[0;32m"
YELLOW = "\033[0;33m"
BLUE = "\033[0;34m"
NC = "\033[0m"


def info(msg):
    print(f"{BLUE}[ftp ]{NC} {msg}")


def ok(msg):
    print(f"{GREEN}[  ok]{NC} {msg}")


def warn(msg):
    print(f"{YELLOW}[ warn]{NC} {msg}")


def fail(msg):
    print(f"{RED}[ fail]{NC} {msg}", file=sys.stderr)
    sys.exit(1)


def load_credentials(args):
    """Đọc thông tin FTP từ file hoặc từ tham số dòng lệnh."""
    creds = {
        "host": args.host,
        "user": args.user,
        "pass": args.password,
        "port": args.port,
        "remote_dir": args.remote_dir,
    }

    if CRED_FILE.exists():
        for line in CRED_FILE.read_text(encoding="utf-8").splitlines():
            line = line.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            key, value = line.split("=", 1)
            key = key.strip()
            value = value.strip()
            if key in creds and not creds.get(key):
                creds[key] = value

    if not creds["host"]:
        fail("Thiếu FTP host. Tạo deploy/ftp-credentials.txt hoặc dùng --host.")
    if not creds["user"]:
        fail("Thiếu FTP user.")
    if not creds["pass"]:
        creds["pass"] = getpass.getpass(f"FTP password cho {creds['user']}@{creds['host']}: ")

    creds["port"] = int(creds["port"] or 21)
    return creds


def ensure_dir(ftp, path):
    """Tạo thư mục từ xa nếu chưa có (quyền ghi từng cấp)."""
    if path in ("", "/"):
        return
    parts = path.strip("/").split("/")
    current = ""
    for part in parts:
        current += "/" + part
        try:
            ftp.mkd(current)
            info(f"  tạo thư mục {current}")
        except ftplib.error_perm:
            pass  # đã tồn tại


def upload(ftp, local_path, remote_name, remote_dir):
    """Upload 1 file, trả về (True/False, thông báo)."""
    if not Path(local_path).exists():
        warn(f"Bỏ qua (không tồn tại): {local_path}")
        return False, "không tồn tại"

    remote_path = f"{remote_dir.rstrip('/')}/{remote_name}"

    with open(local_path, "rb") as fh:
        ftp.storbinary(f"STOR {remote_path}", fh, blocksize=1024 * 1024)

    size = Path(local_path).stat().st_size
    return True, f"{remote_path} ({size / 1024 / 1024:.2f} MB)"


def main():
    parser = argparse.ArgumentParser(description="Upload PIN247 deploy bundle lên InfinityFree qua FTP")
    parser.add_argument("--host")
    parser.add_argument("--user")
    parser.add_argument("--password")
    parser.add_argument("--port", default=None)
    parser.add_argument("--remote-dir", default=None)
    parser.add_argument("--skip-env", action="store_true", help="Bỏ qua upload file .env")
    args = parser.parse_args()

    creds = load_credentials(args)
    remote_dir = creds["remote_dir"] or "/htdocs"

    print()
    print("==============================================")
    print(" PIN247 — upload lên InfinityFree")
    print("==============================================")
    print(f"  Host       : {creds['host']}:{creds['port']}")
    print(f"  User       : {creds['user']}")
    print(f"  Remote dir : {remote_dir}")
    print()

    try:
        ftp = ftplib.FTP()
        ftp.connect(creds["host"], creds["port"], timeout=120)
        ftp.login(creds["user"], creds["pass"])
        info("Đã đăng nhập FTP.")

        # InfinityFree chỉ cho phép PASV.
        ftp.set_pasv(True)

        ensure_dir(ftp, remote_dir)
        ftp.cwd(remote_dir)

        # ---------------------------------------------------------------
        # 1. .htaccess — ƯU TIÊN CAO NHẤT (phải có trước .env)
        # ---------------------------------------------------------------
        info("Upload .htaccess (tên file: 'htaccess') ...")
        done, msg = upload(ftp, ROOT / ".htaccess", "htaccess", remote_dir)
        if done:
            ok(f"  .htaccess -> {msg}")
            try:
                ftp.rename(f"{remote_dir.rstrip('/')}/htaccess", f"{remote_dir.rstrip('/')}/.htaccess")
                ok("  Đã đổi tên 'htaccess' -> '.htaccess'")
            except ftplib.error_perm as exc:
                warn(f"Không đổi tên được qua FTP ({exc}).")
        else:
            fail("Không upload được .htaccess — DỪNG lại (không an toàn upload .env).")

        # ---------------------------------------------------------------
        # 2. Deploy bundle (zip) — upload 1 file duy nhất, nhanh hơn nhiều
        #    so với upload 7.500 file rời rạc (vendor/).
        # ---------------------------------------------------------------
        zip_path = DEPLOY_DIR / "pin247-deploy.zip"
        if zip_path.exists():
            info("Upload pin247-deploy.zip ...")
            done, msg = upload(ftp, zip_path, "pin247-deploy.zip", remote_dir)
            if done:
                ok(f"  zip -> {msg}")
                warn("  → Giải nén: File Manager (Extract) HOẶC mở deploy-extract.php?token=...")
            else:
                fail("Không upload được file zip.")
        else:
            warn("Chưa có deploy/pin247-deploy.zip — chạy: bash scripts/build-deploy.sh")

        # ---------------------------------------------------------------
        # 2b. Script giải nén tự động (mở bằng trình duyệt, 1 click)
        # ---------------------------------------------------------------
        extractor = DEPLOY_DIR / "deploy-extract.php"
        if extractor.exists():
            info("Upload deploy-extract.php ...")
            done, msg = upload(ftp, extractor, "deploy-extract.php", remote_dir)
            if done:
                ok(f"  extractor -> {msg}")
            else:
                warn("Không upload được deploy-extract.php — giải nén tay qua File Manager.")
        else:
            warn("Chưa có deploy/deploy-extract.php — giải nén tay qua File Manager.")

        # ---------------------------------------------------------------
        # 3. .env — upload tên tạm rồi RNTO
        # ---------------------------------------------------------------
        env_local = DEPLOY_DIR / ".env.production.local"
        if args.skip_env:
            warn("Bỏ qua upload .env (--skip-env).")
        elif env_local.exists():
            info("Upload .env (tên tạm: 'env.setup') ...")
            done, msg = upload(ftp, env_local, "env.setup", remote_dir)
            if done:
                ok(f"  env.setup -> {msg}")
                # Đổi tên thành .env qua FTP RNTO.
                try:
                    ftp.rename(f"{remote_dir.rstrip('/')}/env.setup", f"{remote_dir.rstrip('/')}/.env")
                    ok("  Đã đổi tên 'env.setup' -> '.env'")
                except ftplib.error_perm as exc:
                    warn(f"Không đổi tên được qua FTP ({exc}).")
                    warn("Đổi tên thủ công trong File Manager: env.setup -> .env")
            else:
                fail("Không upload được .env.")
        else:
            warn("Chưa có deploy/.env.production.local — xem DEPLOY_GUIDE.md §6 để tạo .env.")

        # ---------------------------------------------------------------
        # 4. SQL dump
        # ---------------------------------------------------------------
        sql_path = DEPLOY_DIR / "database" / "pin247.sql"
        if sql_path.exists():
            info("Upload pin247.sql ...")
            done, msg = upload(ftp, sql_path, "pin247.sql", remote_dir)
            if done:
                ok(f"  sql -> {msg}")
            else:
                warn("Không upload được file SQL.")
        else:
            warn("Chưa có deploy/database/pin247.sql — chạy: bash scripts/dump-database.sh")

        # ---------------------------------------------------------------
        # 5. Script import DB (tự xoá sau khi chạy)
        # ---------------------------------------------------------------
        importer = DEPLOY_DIR / "deploy-import.php"
        if importer.exists():
            info("Upload deploy-import.php ...")
            done, msg = upload(ftp, importer, "deploy-import.php", remote_dir)
            if done:
                ok(f"  importer -> {msg}")
                warn("  → Mở bằng trình duyệt: https://<domain>/deploy-import.php?token=<token>")
        else:
            warn("Chưa có deploy/deploy-import.php.")

        # ---------------------------------------------------------------
        # 6. Token file (để deploy-import.php / deploy-extract.php lấy token)
        # ---------------------------------------------------------------
        token_file = DEPLOY_DIR / "deploy-import.token.txt"
        if token_file.exists():
            info("Upload deploy-import.token.txt ...")
            done, msg = upload(ftp, token_file, "deploy-import.token.txt", remote_dir)
            if done:
                ok(f"  token -> {msg}")
            else:
                warn("Không upload được file token — nhập token trực tiếp trên URL.")
        else:
            warn("Chưa có deploy/deploy-import.token.txt — nhập token trực tiếp trên URL.")

        ftp.quit()
        info("Đã đóng kết nối FTP.")

        print()
        print("==============================================")
        print(f" {GREEN}✔ Upload xong{NC}")
        print("==============================================")
        print("  Việc cần làm tiếp trên hosting:")
        print("   1. File Manager → htdocs → giải nén 'pin247-deploy.zip'")
        print("      (đè lên file cũ, KHÔNG xoá file .env)")
        print("   2. Xoá file 'pin247-deploy.zip' sau khi giải nén")
        print("   3. chmod 777: storage, storage/framework/*, storage/logs,")
        print("      storage/app, bootstrap/cache")
        print("   4. Mở https://<domain>/deploy-import.php?token=<token> để import DB")
        print("   5. Kiểm tra https://<domain>/")
        print("==============================================")

    except ftplib.error_perm as exc:
        fail(f"FTP permission denied: {exc}")
    except ftplib.all_errors as exc:
        fail(f"Lỗi FTP: {exc}")


if __name__ == "__main__":
    main()
