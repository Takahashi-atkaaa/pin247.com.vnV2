#!/usr/bin/env python3
"""Liệt kê cây thư mục từ xa qua FTP (dùng để kiểm tra trạng thái deploy)."""

import ftplib
import sys
from pathlib import Path

DEPLOY = Path(__file__).resolve().parent.parent / "deploy"
CRED = DEPLOY / "ftp-credentials.txt"


def creds():
    data = {}
    for line in CRED.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        k, v = line.split("=", 1)
        data[k.strip()] = v.strip()
    return data


def walk(ftp, path="", depth=0, max_depth=2):
    if depth > max_depth:
        return
    try:
        items = ftp.mlsd(path, facts=["type", "size"])
    except Exception:
        ftp.dir(path)
        return
    for name, facts in sorted(items, key=lambda x: x[0]):
        if name in (".", ".."):
            continue
        ftype = facts.get("type", "")
        size = facts.get("size", "0")
        if ftype == "dir":
            print(f"{'  ' * depth}{name}/")
            try:
                walk(ftp, f"{path}/{name}", depth + 1, max_depth)
            except Exception:
                pass
        else:
            try:
                size = int(size)
            except (TypeError, ValueError):
                size = 0
            print(f"{'  ' * depth}{name}  ({size / 1024:.1f} KB)")


def main():
    c = creds()
    ftp = ftplib.FTP()
    ftp.connect(c["ftp_host"], int(c.get("ftp_port", 21)), timeout=60)
    ftp.login(c["ftp_user"], c["ftp_pass"])
    ftp.set_pasv(True)

    target = sys.argv[1] if len(sys.argv) > 1 else c["remote_dir"]
    print(f"=== FTP: {c['ftp_user']}@{c['ftp_host']}{target} ===\n")
    walk(ftp, target, 0, int(sys.argv[2]) if len(sys.argv) > 2 else 1)
    ftp.quit()


if __name__ == "__main__":
    main()
