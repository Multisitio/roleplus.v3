#!/bin/sh
set -eu

legacy_monday='0 2 * * 1 /usr/bin/php /var/www/roleplus.app/private/bin/kcli.php rolelocal/boletin/enviar'
legacy_wednesday='0 10 * * 3 /usr/bin/php /var/www/roleplus.app/private/bin/kcli.php rolelocal/boletin/enviar'
current_wednesday='0 10 * * 3 /usr/bin/flock -n /var/lock/roleplus-newsletter.lock /usr/bin/php /var/www/roleplus.app/private/bin/kcli.php rolelocal/boletin/enviar'
new='0 10 * * 1 /usr/bin/flock -n /var/lock/roleplus-newsletter.lock /usr/bin/php /var/www/roleplus.app/private/bin/kcli.php rolelocal/boletin/enviar'
current="$(mktemp)"
updated="$(mktemp)"
trap 'rm -f "$current" "$updated"' EXIT

crontab -l > "$current"
legacy_monday_matches="$(grep -Fxc "$legacy_monday" "$current" || true)"
legacy_wednesday_matches="$(grep -Fxc "$legacy_wednesday" "$current" || true)"
current_wednesday_matches="$(grep -Fxc "$current_wednesday" "$current" || true)"
new_matches="$(grep -Fxc "$new" "$current" || true)"

if [ "$legacy_monday_matches" -eq 0 ] && [ "$legacy_wednesday_matches" -eq 0 ] && [ "$current_wednesday_matches" -eq 0 ] && [ "$new_matches" -eq 1 ]; then
    echo 'Newsletter schedule already configured.'
    exit 0
fi

legacy_matches=$((legacy_monday_matches + legacy_wednesday_matches + current_wednesday_matches))
if [ "$legacy_matches" -ne 1 ] || [ "$new_matches" -ne 0 ]; then
    echo "Unexpected newsletter cron state: monday=$legacy_monday_matches wednesday=$legacy_wednesday_matches current_wednesday=$current_wednesday_matches new=$new_matches" >&2
    exit 1
fi

backup="/root/crontab-backup-$(date +%Y%m%d-%H%M%S)"
cp -p "$current" "$backup"
awk -v monday="$legacy_monday" -v wednesday="$legacy_wednesday" -v current_wednesday="$current_wednesday" -v new="$new" \
    '$0 == monday || $0 == wednesday || $0 == current_wednesday { print new; next } { print }' "$current" > "$updated"

[ "$(grep -Fxc "$legacy_monday" "$updated" || true)" -eq 0 ]
[ "$(grep -Fxc "$legacy_wednesday" "$updated" || true)" -eq 0 ]
[ "$(grep -Fxc "$current_wednesday" "$updated" || true)" -eq 0 ]
[ "$(grep -Fxc "$new" "$updated" || true)" -eq 1 ]
crontab "$updated"

echo "Backup: $backup"
crontab -l | grep -F "$new"
