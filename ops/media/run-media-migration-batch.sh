#!/usr/bin/env bash

# Ejecuta un único lote histórico con baja prioridad. Conserva los originales,
# actualiza únicamente las referencias seguras y deja un informe por lote.
set -u

BATCH_SIZE="${1:-25}"
OPS_DIR="/root/roleplus-ops/media"
WEB_ROOT="/var/www/clients/client1/web6/web"
PRIVATE_ROOT="/var/www/clients/client1/web6/private"
MEDIA_ROOT="$WEB_ROOT/img/usuarios"
MANIFEST="$OPS_DIR/historical-users-20260902.jsonl"
BACKUP="/root/roleplus-backup-20260902-media/c1_roleplus-before-media-canary.sql.gz"
LOG_DIR="$OPS_DIR/logs"
RUN_ID="$(date -u +%Y%m%dT%H%M%SZ)-$$"
DELTA_MANIFEST="$LOG_DIR/$RUN_ID.jsonl"
MIGRATION_LOG="$LOG_DIR/$RUN_ID-migration.log"
DRY_RUN_REPORT="$LOG_DIR/$RUN_ID-references-dry-run.json"
APPLY_REPORT="$LOG_DIR/$RUN_ID-references-apply.json"
STATUS_FILE="$LOG_DIR/$RUN_ID-status.txt"

case "$BATCH_SIZE" in
    ''|*[!0-9]*) echo "El tamaño del lote debe ser un entero positivo." >&2; exit 2 ;;
esac
if [ "$BATCH_SIZE" -lt 1 ] || [ "$BATCH_SIZE" -gt 100 ]; then
    echo "El tamaño del lote debe estar entre 1 y 100." >&2
    exit 2
fi

mkdir -p "$LOG_DIR"
exec 9>"$OPS_DIR/migration.lock"
if ! flock -n 9; then
    echo "Ya hay un lote de migración en curso." >&2
    exit 3
fi
if [ ! -s "$BACKUP" ] || ! gzip -t "$BACKUP"; then
    echo "No existe un respaldo SQL válido; se cancela el lote." >&2
    exit 4
fi
AVAILABLE_KB="$(df -Pk "$MEDIA_ROOT" | awk 'NR == 2 { print $4 }')"
if [ -z "$AVAILABLE_KB" ] || [ "$AVAILABLE_KB" -lt 31457280 ]; then
    echo "Quedan menos de 30 GiB libres; se cancela el lote." >&2
    exit 5
fi
if [ ! -f "$MANIFEST" ]; then
    cp "$OPS_DIR/canary-users-20260902.jsonl" "$MANIFEST"
fi

BEFORE_LINES="$(wc -l < "$MANIFEST")"
nice -n 15 ionice -c 3 php "$OPS_DIR/migrate-media.php" \
    --root="$MEDIA_ROOT" \
    --processor="$PRIVATE_ROOT/libs/media_processor.php" \
    --apply --limit="$BATCH_SIZE" --manifest="$MANIFEST" \
    >"$MIGRATION_LOG" 2>&1
MIGRATION_STATUS=$?
AFTER_LINES="$(wc -l < "$MANIFEST")"

if [ "$AFTER_LINES" -le "$BEFORE_LINES" ]; then
    printf 'migration_status=%s\nnew_entries=0\n' "$MIGRATION_STATUS" > "$STATUS_FILE"
    cat "$MIGRATION_LOG"
    exit "$MIGRATION_STATUS"
fi

tail -n "+$((BEFORE_LINES + 1))" "$MANIFEST" > "$DELTA_MANIFEST"

DB_PASSWORD="$(php -r 'include "/var/www/clients/client1/web6/private/config/databases.php"; echo $databases["default"]["password"];')"
DB_USER="$(php -r 'include "/var/www/clients/client1/web6/private/config/databases.php"; echo $databases["default"]["username"];')"
export ROLEPLUS_DB_PASSWORD="$DB_PASSWORD"

php "$OPS_DIR/update-media-references.php" \
    --manifest="$DELTA_MANIFEST" --database=c1_roleplus --user="$DB_USER" \
    > "$DRY_RUN_REPORT"
php "$OPS_DIR/update-media-references.php" \
    --manifest="$DELTA_MANIFEST" --database=c1_roleplus --user="$DB_USER" \
    --apply --backup-confirmed="$BACKUP" \
    > "$APPLY_REPORT"
UPDATE_STATUS=$?
unset ROLEPLUS_DB_PASSWORD DB_PASSWORD

printf 'migration_status=%s\nupdate_status=%s\nnew_entries=%s\nmanifest=%s\napply_report=%s\n' \
    "$MIGRATION_STATUS" "$UPDATE_STATUS" "$((AFTER_LINES - BEFORE_LINES))" \
    "$DELTA_MANIFEST" "$APPLY_REPORT" > "$STATUS_FILE"

cat "$MIGRATION_LOG"
cat "$APPLY_REPORT"
exit "$UPDATE_STATUS"
