#!/bin/sh
set -eu

# Keep MariaDB reachable through an SSH tunnel while removing its public
# listener. This drop-in survives package upgrades and ISPConfig rewrites.
cat > /etc/mysql/mariadb.conf.d/99-roleplus-security.cnf <<'EOF'
[mysqld]
bind-address = 127.0.0.1
EOF

# Keep only encrypted IMAPS. POP3 is intentionally disabled because RolePlus
# does not use it. Authentication mechanisms such as PLAIN remain valid inside
# TLS, but are rejected on unencrypted channels.
rm -f /etc/dovecot/conf.d/99-roleplus-security.conf
cat > /etc/dovecot/conf.d/99-ispconfig-custom-config.conf <<'EOF'
disable_plaintext_auth = yes
protocols = imap

service imap-login {
  inet_listener imap {
    port = 0
  }
}

service pop3-login {
  inet_listener pop3 {
    port = 0
  }
  inet_listener pop3s {
    port = 0
  }
}
EOF

mariadbd --verbose --help >/dev/null
doveconf -n >/dev/null

systemctl restart mariadb
systemctl restart dovecot
service pure-ftpd-mysql stop || true
systemctl disable pure-ftpd-mysql
systemctl mask pure-ftpd-mysql.service

mariadb-admin ping
systemctl is-active --quiet mariadb
systemctl is-active --quiet dovecot
