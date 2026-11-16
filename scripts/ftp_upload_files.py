#!/usr/bin/env python3
"""
=================================================================================
 PIN247 — upload riêng các file deploy lên InfinityFree qua FTP

CÁCH DÙNG:
    python3 scripts/ftp_upload_files.py deploy-import.php deploy/database/pin247.sql

FILE ĐƯỢC UPLOAD (giữ đúng tên trên remote):
    - deploy-import.php
    - deploy/database/pin247.sql  -> remote: pin247.sql

Script KHÔNG tự xoá file remote (để bạn chạy lại được).
"""

import ftplib
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
DEPLOY = ROOT / "deploy"
CRED = DEPLOY / "ftp-credentials.txt"


def creds():
    data = {}

    for line in CRED.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, value = line.split("=", 1)
        data[key.strip()] = value.strip()

    return data


def main():
    if len(sys.argv) < 2:
        print(__doc__)
        sys.exit(1)

    c = creds()
    remote_dir = c["remote_dir"].rstrip("/")

    ftp = ftplib.FTP()
    ftp.connect(c["ftp_host"], int(c.get("ftp_port", 21)), timeout=120)
    ftp.login(c["ftp_user"], c["ftp_pass"])
    ftp.set_pasv(True)
    ftp.cwd(remote_dir)

    print(f"✅ FTP: {c['ftp_user']}@{c['ftp_host']}{remote_dir}\n")

    for arg in sys.argv[1:]:
        local = ROOT / arg
        remote = Path(arg).name

        if not local.exists():
            print(f"  ✗ Không tồn tại: {local}")
            continue

        size = local.stat().st_size

        with open(local, "rb") as fh:
            ftp.storbinary(f"STOR {remote_dir}/{remote}", fh, blocksize=1024 * 1024)

        print(f"  ⬆️  {remote}  ({size / 1024:.1f} KB)")

    ftp.quit()
    print("\n✅ Xong")


if __name__ == "__main__":
    main()
