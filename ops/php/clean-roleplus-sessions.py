#!/usr/bin/python3
"""Cleanup for the RolePlus pool only; CLI defaults to dry-run."""
import argparse
import json
import os
import re
import stat
import time
try:
    import fcntl
except ImportError:
    fcntl = None

DIRECTORY = '/var/www/clients/client1/web6/tmp'
RETENTION = 31 * 86400
LIMIT = 500
NAME = re.compile(r'sess_[a-zA-Z0-9,-]+\Z')


def eligible(info, uid, cutoff):
    return (stat.S_ISREG(info.st_mode) and info.st_uid == uid
            and info.st_mtime < cutoff and info.st_ctime < cutoff)


def clean(directory, apply=False, uid=None, now=None):
    if apply and fcntl is None:
        raise RuntimeError('POSIX locks required')
    uid = os.getuid() if uid is None else uid
    cutoff = (time.time() if now is None else now) - RETENTION
    counts = dict(scanned=0, eligible=0, deleted=0, locked=0, changed=0, errors=0)
    dirfd = os.open(directory, os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW)
    try:
        with os.scandir(dirfd) as entries:
            for entry in entries:
                if not NAME.fullmatch(entry.name):
                    continue
                counts['scanned'] += 1
                try:
                    before = os.stat(entry.name, dir_fd=dirfd, follow_symlinks=False)
                    if not eligible(before, uid, cutoff):
                        continue
                    counts['eligible'] += 1
                    if not apply or counts['deleted'] >= LIMIT:
                        continue
                    fd = os.open(entry.name, os.O_RDWR | os.O_NOFOLLOW, dir_fd=dirfd)
                    try:
                        try:
                            fcntl.flock(fd, fcntl.LOCK_EX | fcntl.LOCK_NB)
                        except BlockingIOError:
                            counts['locked'] += 1
                            continue
                        current = os.fstat(fd)
                        linked = os.stat(entry.name, dir_fd=dirfd, follow_symlinks=False)
                        if (current.st_ino != before.st_ino or current.st_dev != before.st_dev
                                or linked.st_ino != current.st_ino or linked.st_dev != current.st_dev
                                or not eligible(current, uid, cutoff)):
                            counts['changed'] += 1
                            continue
                        os.unlink(entry.name, dir_fd=dirfd)
                        counts['deleted'] += 1
                    finally:
                        os.close(fd)
                except FileNotFoundError:
                    counts['changed'] += 1
                except OSError:
                    counts['errors'] += 1
    finally:
        os.close(dirfd)
    return counts


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--apply', action='store_true')
    args = parser.parse_args()
    import pwd
    uid = pwd.getpwnam('web6').pw_uid
    if os.getuid() != uid:
        raise SystemExit('Run as web6')
    started = time.monotonic()
    record = clean(DIRECTORY, args.apply, uid)
    record.update(apply=args.apply, retention_days=31, limit=LIMIT,
                  seconds=round(time.monotonic()-started, 3))
    print(json.dumps(record))
    if record['errors']:
        raise SystemExit(1)


if __name__ == '__main__':
    main()
