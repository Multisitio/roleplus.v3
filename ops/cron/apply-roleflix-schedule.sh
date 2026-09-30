#!/bin/sh
set -eu

old='0 7 * * * /usr/bin/php /var/www/roleplus.app/private/bin/kcli.php rolelocal/roleflix'
new='0 10 * * * /usr/bin/php /var/www/roleplus.app/private/bin/kcli.php rolelocal/roleflix'
current="$(mktemp)"
updated="$(mktemp)"
trap 'rm -f "$current" "$updated"' EXIT

crontab -l > "$current"
old_matches="$(grep -Fxc "$old" "$current" || true)"
new_matches="$(grep -Fxc "$new" "$current" || true)"

if [ "$old_matches" -eq 0 ] && [ "$new_matches" -eq 1 ]; then
    echo 'Roleflix schedule already configured.'
    exit 0
fi
if [ "$old_matches" -ne 1 ] || [ "$new_matches" -ne 0 ]; then
    echo "Unexpected Roleflix cron state: old=$old_matches new=$new_matches" >&2
    exit 1
fi

backup="/root/crontab-backup-roleflix-$(date +%Y%m%d-%H%M%S)"
cp -p "$current" "$backup"
awk -v old="$old" -v new="$new" '$0 == old { print new; next } { print }' "$current" > "$updated"
crontab "$updated"

echo "Backup: $backup"
crontab -l | grep -Fx "$new"
