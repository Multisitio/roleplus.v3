#!/usr/bin/env bash

# Completa la migración en lotes espaciados. El ejecutor de cada lote aplica
# bloqueo, respaldo obligatorio, prioridad baja y umbral mínimo de disco.
set -u

OPS_DIR="/root/roleplus-ops/media"
WORKER_LOG="$OPS_DIR/worker.log"

exec 8>"$OPS_DIR/worker.lock"
if ! flock -n 8; then
    echo "Ya hay un trabajador de migración activo." >&2
    exit 3
fi

while true; do
    BEFORE_LINES="$(wc -l < "$OPS_DIR/historical-users-20260902.jsonl")"
    printf '%s inicio del lote; manifiesto=%s\n' "$(date -u +%FT%TZ)" "$BEFORE_LINES" >> "$WORKER_LOG"

    if ! "$OPS_DIR/run-media-migration-batch.sh" 100 >> "$WORKER_LOG" 2>&1; then
        printf '%s lote detenido por un error operativo\n' "$(date -u +%FT%TZ)" >> "$WORKER_LOG"
        exit 1
    fi

    AFTER_LINES="$(wc -l < "$OPS_DIR/historical-users-20260902.jsonl")"
    printf '%s fin del lote; manifiesto=%s\n' "$(date -u +%FT%TZ)" "$AFTER_LINES" >> "$WORKER_LOG"
    if [ "$AFTER_LINES" -le "$BEFORE_LINES" ]; then
        printf '%s migración completa\n' "$(date -u +%FT%TZ)" >> "$WORKER_LOG"
        exit 0
    fi
    sleep 60
done
