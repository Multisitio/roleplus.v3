#!/usr/bin/python3
"""InstalaciÃ³n reversible del diagnÃ³stico, sin copiar el pool entre entornos."""
import os
import pathlib
import pwd
import shutil
import subprocess
import sys

SOURCE = pathlib.Path(__file__).resolve().parent
POOL = pathlib.Path("/etc/php/8.4/fpm/pool.d/web6.conf")
FRAGMENT = pathlib.Path("/etc/php/8.4/fpm/roleplus-observability.inc")
UNIT = pathlib.Path("/etc/systemd/system/roleplus-fpm-metrics.service")
ROTATION = pathlib.Path("/etc/logrotate.d/roleplus-observability")
LOGS = pathlib.Path("/var/www/clients/client1/web6/private/var/observability")
BACKUP = SOURCE / "backups" / "pool-before-observability.conf"
INCLUDE = b"include=/etc/php/8.4/fpm/roleplus-observability.inc\n"

OLD_PREPEND = b"php_admin_value[auto_prepend_file] = /usr/share/php/global_waf.php"
NEW_PREPEND = b"php_admin_value[auto_prepend_file] = /var/www/clients/client1/web6/private/ops/php/roleplus-request-prepend.php"

def configured_pool(original):
    return original.replace(OLD_PREPEND, NEW_PREPEND, 1) + b"\n" + INCLUDE

def run(*args):
    subprocess.run(args, check=True)

def write_pool(data):
    staged = POOL.with_suffix(".codex-stage")
    metadata = POOL.stat()
    staged.write_bytes(data)
    os.chown(staged, metadata.st_uid, metadata.st_gid)
    os.chmod(staged, metadata.st_mode & 0o777)
    staged.replace(POOL)

def main():
    if os.geteuid() != 0:
        raise SystemExit("Ejecutar como root en producciÃ³n")
    original = POOL.read_bytes()
    if "--remove" in sys.argv:
        run("systemctl", "disable", "--now", "roleplus-fpm-metrics.service")
        baseline = BACKUP.read_bytes()
        restored = baseline if original == configured_pool(baseline) else original.replace(INCLUDE, b"").replace(NEW_PREPEND, OLD_PREPEND)
        write_pool(restored)
        try:
            run("php-fpm8.4", "-t")
            run("systemctl", "reload", "php8.4-fpm")
        except Exception:
            write_pool(original)
            raise
        for path in (UNIT, ROTATION, FRAGMENT):
            path.unlink(missing_ok=True)
        run("systemctl", "daemon-reload")
        print("DiagnÃ³stico retirado; registros conservados, lÃ­mites intactos")
        return
    if INCLUDE in original:
        raise SystemExit("Ya instalado: revisar antes de actualizar")
    if original.count(b"[web6]") != 1 or original.count(OLD_PREPEND) != 1:
        raise SystemExit("Pool distinto del revisado; no modificar")
    if any(path.exists() for path in (UNIT, ROTATION, FRAGMENT)):
        raise SystemExit("Hay archivos con esos nombres; no sobrescribir")
    BACKUP.parent.mkdir(exist_ok=True)
    if BACKUP.exists():
        raise SystemExit("Existe una copia anterior; revisar antes de instalar")
    BACKUP.write_bytes(original)
    BACKUP.chmod(0o600)
    user = pwd.getpwnam("web6")
    LOGS.mkdir(parents=True, exist_ok=True)
    os.chown(LOGS, user.pw_uid, user.pw_gid)
    LOGS.chmod(0o700)
    for name in ("requests.jsonl", "fpm.jsonl", "slow.log"):
        path = LOGS / name
        path.touch(exist_ok=True)
        os.chown(path, user.pw_uid, user.pw_gid)
        path.chmod(0o600)
    try:
        shutil.copyfile(SOURCE / "roleplus-observability.inc", FRAGMENT)
        FRAGMENT.chmod(0o600)
        shutil.copyfile(SOURCE / "roleplus-fpm-metrics.service", UNIT)
        UNIT.chmod(0o644)
        shutil.copyfile(SOURCE / "roleplus-observability.logrotate", ROTATION)
        ROTATION.chmod(0o644)
        write_pool(configured_pool(original))
        run("php-fpm8.4", "-t")
        run("logrotate", "--debug", str(ROTATION))
        run("systemd-analyze", "verify", str(UNIT))
        run("systemctl", "daemon-reload")
        run("systemctl", "reload", "php8.4-fpm")
        run("systemctl", "enable", "--now", "roleplus-fpm-metrics.service")
        run("systemctl", "is-active", "--quiet", "roleplus-fpm-metrics.service")
    except Exception:
        subprocess.run(["systemctl", "disable", "--now", "roleplus-fpm-metrics.service"], capture_output=True)
        write_pool(original)
        run("php-fpm8.4", "-t")
        run("systemctl", "reload", "php8.4-fpm")
        for path in (UNIT, ROTATION, FRAGMENT):
            path.unlink(missing_ok=True)
        run("systemctl", "daemon-reload")
        raise
    print("RolePlus: diagnÃ³stico instalado; WAF conservado y capacidad sin cambios")

if __name__ == "__main__":
    main()
