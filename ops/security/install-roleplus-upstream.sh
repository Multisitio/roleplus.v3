#!/bin/sh
set -eu
# Ejecutar en producción. No sustituye el vhost por una copia del entorno local.
app=/var/www/clients/client1/web6
vhost=/etc/apache2/sites-available/roleplus.app.vhost
include=/etc/apache2/roleplus-upstream.conf
backup=$(mktemp -d /tmp/roleplus-upstream.XXXXXX)
cp -p "$vhost" "$backup/vhost"
if [ -f "$include" ]; then
    cp -p "$include" "$backup/include"
fi
install -o root -g root -m 644 "$app/private/ops/security/roleplus-upstream.conf" "$include"
python3 - "$vhost" <<'PY'
from pathlib import Path
import sys
path = Path(sys.argv[1])
data = path.read_bytes()
line = b'    Include /etc/apache2/roleplus-upstream.conf\n'
if line not in data:
    if data.count(b'</VirtualHost>') != 2:
        raise SystemExit('VirtualHost inesperado: no modificar sin revisar.')
    path.write_bytes(data.replace(b'</VirtualHost>', line + b'</VirtualHost>'))
if path.read_bytes().count(line) != 2:
    raise SystemExit('Falta el ajuste en uno de los VirtualHost.')
PY
if ! apachectl configtest; then
    cp -p "$backup/vhost" "$vhost"
    if [ -f "$backup/include" ]; then
        cp -p "$backup/include" "$include"
    fi
    exit 1
fi
if ! systemctl reload apache2; then
    cp -p "$backup/vhost" "$vhost"
    if [ -f "$backup/include" ]; then
        cp -p "$backup/include" "$include"
    fi
    exit 1
fi
printf 'ProxyTimeout de RolePlus aplicado; copia de recuperación: %s\n' "$backup"
