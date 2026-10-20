#!/usr/bin/env bash
set -euo pipefail

# Config ajustable
email_to="distrotuz@gmail.com"
email_from="webwatchdog@$(hostname -f 2>/dev/null || hostname)"
fail_threshold=3
tz_madrid="Europe/Madrid"

# Anti-bucle de reinicios: 1 reinicio por "episodio" de caida (hasta que vuelva a UP)
restart_once_per_outage=1

# Timeouts curl (evitar falsos 000)
curl_connect_timeout=4
curl_max_time=8

# Healthcheck local (evita falsos 000 por red externa/DNS)
check_via_local=1
check_ip="127.0.0.1"

# Heartbeat (para saber que corre sin spamear): log 1 vez/hora (minuto 00)
heartbeat_hourly=1
heartbeat_state_dir="/run/webwatchdog"

# Panel (multisitio.es) = estado + json
panel_dir="/var/www/clients/client1/web17/web/webwatchdog"
panel_json="${panel_dir}/status.json"

# Estado de control privado; el panel recibe solo una replica de lectura.
state_dir="/var/lib/vigilante/antipanico"
private_panel_json="$state_dir/status.json"
exec 9>/run/lock/vigilante-watchdog.lock
flock -n 9 || exit 0

# Webs a controlar (orden alfabetico)
hosts_text="$(/usr/local/sbin/vps-service-control list-sites)"
mapfile -t hosts <<< "$hosts_text"

# Si cae UNA web: reiniciar SOLO esto (orden alfabetico)
restart_units_single=(
        "php8.4-fpm"
)

# Si caen TODAS a la vez: reiniciar esto (orden alfabetico)
restart_units_global=(
        "apache2"
        "php8.4-fpm"
)

mkdir -p "$state_dir"
chmod 0700 "$state_dir"
chmod 0755 "$panel_dir" 2>/dev/null || true

log(){
        logger -t webwatchdog -- "$*"
}

now_iso(){
        date -u '+%Y-%m-%dT%H:%M:%SZ'
}

now_madrid(){
        TZ="$tz_madrid" date '+%Y-%m-%d %H:%M:%S'
}

host(){
        hostname -f 2>/dev/null || hostname
}

server_brief(){
        local up load
        up="$(uptime -p 2>/dev/null || true)"
        load="$(cut -d' ' -f1-3 /proc/loadavg 2>/dev/null || true)"
        printf 'Servidor: %s\nFecha (Madrid): %s\nFecha (UTC): %s\nTiempo encendido: %s\nCarga (1m 5m 15m): %s\n' "$(host)" "$(now_madrid)" "$(now_iso)" "$up" "$load"
}

send_mail(){
        local subject="$1"
        local body="$2"

        [ -x /usr/sbin/sendmail ] || {
                log "MAIL_SKIP no_sendmail subject=$subject"
                return 0
        }

        /usr/sbin/sendmail -t <<-EOF
To: ${email_to}
From: ${email_from}
Subject: ${subject}
MIME-Version: 1.0
Content-Type: text/plain; charset=UTF-8

${body}
EOF
}

safe_name(){
        printf '%s' "$1" | tr '/:@?&=%#' '_________'
}

fail_file(){
        printf '%s/%s.fail\n' "$state_dir" "$(safe_name "$1")"
}

status_file(){
        printf '%s/%s.status\n' "$state_dir" "$(safe_name "$1")"
}

restart_done_file(){
        printf '%s/%s.restart_done\n' "$state_dir" "$(safe_name "$1")"
}

last_ok_file(){
        printf '%s/%s.last_ok\n' "$state_dir" "$(safe_name "$1")"
}

last_ok_madrid_file(){
        printf '%s/%s.last_ok_madrid\n' "$state_dir" "$(safe_name "$1")"
}

last_fail_file(){
        printf '%s/%s.last_fail\n' "$state_dir" "$(safe_name "$1")"
}

last_fail_madrid_file(){
        printf '%s/%s.last_fail_madrid\n' "$state_dir" "$(safe_name "$1")"
}

code_file(){
        printf '%s/%s.code\n' "$state_dir" "$(safe_name "$1")"
}

get_fails(){
        local f
        f="$(fail_file "$1")"
        [ -f "$f" ] && cat "$f" || echo 0
}

set_fails(){
        local f
        f="$(fail_file "$1")"
        printf '%s' "$2" > "$f"
        chmod 0644 "$f" 2>/dev/null || true
}

get_status(){
        local f
        f="$(status_file "$1")"
        [ -f "$f" ] && cat "$f" || echo "up"
}

set_status(){
        local f
        f="$(status_file "$1")"
        printf '%s' "$2" > "$f"
        chmod 0644 "$f" 2>/dev/null || true
}

get_restart_done(){
        local f
        f="$(restart_done_file "$1")"
        [ -f "$f" ] && cat "$f" || echo 0
}

set_restart_done(){
        local f
        f="$(restart_done_file "$1")"
        printf '%s' "$2" > "$f"
        chmod 0644 "$f" 2>/dev/null || true
}

get_last_ok(){
        local f
        f="$(last_ok_file "$1")"
        [ -f "$f" ] && cat "$f" || echo ""
}

set_last_ok(){
        local f
        f="$(last_ok_file "$1")"
        printf '%s' "$2" > "$f"
        chmod 0644 "$f" 2>/dev/null || true
}

get_last_ok_madrid(){
        local f
        f="$(last_ok_madrid_file "$1")"
        [ -f "$f" ] && cat "$f" || echo ""
}

set_last_ok_madrid(){
        local f
        f="$(last_ok_madrid_file "$1")"
        printf '%s' "$2" > "$f"
        chmod 0644 "$f" 2>/dev/null || true
}

get_last_fail(){
        local f
        f="$(last_fail_file "$1")"
        [ -f "$f" ] && cat "$f" || echo ""
}

set_last_fail(){
        local f
        f="$(last_fail_file "$1")"
        printf '%s' "$2" > "$f"
        chmod 0644 "$f" 2>/dev/null || true
}

get_last_fail_madrid(){
        local f
        f="$(last_fail_madrid_file "$1")"
        [ -f "$f" ] && cat "$f" || echo ""
}

set_last_fail_madrid(){
        local f
        f="$(last_fail_madrid_file "$1")"
        printf '%s' "$2" > "$f"
        chmod 0644 "$f" 2>/dev/null || true
}

get_code(){
        local f
        f="$(code_file "$1")"
        [ -f "$f" ] && cat "$f" || echo ""
}

set_code(){
        local f
        f="$(code_file "$1")"
        printf '%s' "$2" > "$f"
        chmod 0644 "$f" 2>/dev/null || true
}

# Heartbeat state (anti-duplicados)
heartbeat_hour_file(){
        printf '%s/heartbeat.hour\n' "$heartbeat_state_dir"
}

heartbeat_lock_dir(){
        printf '%s/heartbeat.lock\n' "$heartbeat_state_dir"
}

get_heartbeat_hour(){
        local f
        f="$(heartbeat_hour_file)"
        [ -f "$f" ] && cat "$f" || echo ""
}

set_heartbeat_hour(){
        local f
        f="$(heartbeat_hour_file)"
        printf '%s' "$1" > "$f"
        chmod 0644 "$f" 2>/dev/null || true
}

heartbeat_once_per_hour(){
        [ "$heartbeat_hourly" -eq 1 ] || return 0
        [ "$need_restart" -eq 0 ] || return 0
        [ "$failing_now" -eq 0 ] || return 0

        mkdir -p "$heartbeat_state_dir" 2>/dev/null || true
        chmod 0755 "$heartbeat_state_dir" 2>/dev/null || true

        if ! mkdir "$(heartbeat_lock_dir)" 2>/dev/null; then
                return 0
        fi
        trap 'rmdir "$(heartbeat_lock_dir)" 2>/dev/null || true' EXIT

        local hour_now hour_prev
        hour_now="$(TZ="$tz_madrid" date '+%Y-%m-%d %H')"
        hour_prev="$(get_heartbeat_hour)"

        if [ "$hour_now" != "$hour_prev" ]; then
                set_heartbeat_hour "$hour_now"
                log "OK hosts=$total_hosts failing=$failing_now need_restart=$need_restart"
        fi

        rmdir "$(heartbeat_lock_dir)" 2>/dev/null || true
        trap - EXIT
}

# Extrae host/puerto del URL (puerto por defecto: 80/443)
url_host(){
        printf '%s' "$1" | sed -E 's#^[a-z]+://##; s#/.*$##; s#:[0-9]+$##'
}

url_port(){
        local url="$1"
        printf '%s' "$url" | grep -qE '://[^/]+:[0-9]+' && { printf '%s' "$url" | sed -E 's#^[a-z]+://##; s#/.*$##; s#^.*:([0-9]+)$#\1#'; return 0; }
        printf '%s' "$url" | grep -qE '^https://' && { echo 443; return 0; }
        echo 80
}

# HTTP code real (GET + -L) para evitar 000 falsos y seguir redirecciones
curl_code(){
        local url="$1" code hostn port resolve_opt
        hostn="$(url_host "$url")"
        port="$(url_port "$url")"
        resolve_opt=()

        if [ "$check_via_local" -eq 1 ] && [ -n "$hostn" ] && [ -n "$port" ]; then
                resolve_opt=(--resolve "${hostn}:${port}:${check_ip}")
        fi

        code="$(curl -4 -sS -o /dev/null -w '%{http_code}' --max-time "$curl_max_time" --connect-timeout "$curl_connect_timeout" -L "${resolve_opt[@]}" "$url" 2>/dev/null || true)"
        printf '%s' "$code"
}

maintenance_active(){
        /usr/bin/python3 -I -B /usr/local/lib/vigilante/vigilante-scheduled-job.py maintenance "$(url_host "$1")"
}

http_ok_home(){
        local base="$1" home code
        home="${base%/}/"
        code="$(curl_code "$home")"
        set_code "$base" "$code"

        if [ "$code" = "000" ]; then
                return 1
        fi

        if ! printf '%s' "$code" | grep -qE '^[0-9]{3}$'; then
                return 1
        fi

        if [ "$code" -ge 500 ] && [ "$code" -le 599 ]; then
                return 1
        fi

        return 0
}

normalize_url(){
        local line="$1"
        line="$(printf '%s' "$line" | sed 's/[[:space:]]\+$//')"
        [ -z "$line" ] && return 1
        printf '%s' "$line" | grep -qE '^https?://' && { printf '%s' "$line"; return 0; }
        printf 'https://%s' "$line"
}

restart_unit(){
        local unit="$1"
        /usr/local/sbin/vps-service-control restart "$unit" --origin webwatchdog
}

restart_units(){
        local unit
        for unit in "$@"; do
                log "REINICIO unit=$unit"
                restart_unit "$unit" || log "REINICIO_ERROR unit=$unit"
        done
}

json_escape(){
        printf '%s' "$1" | sed \
                -e 's/\\/\\\\/g' \
                -e 's/"/\\"/g' \
                -e ':a;N;$!ba;s/\r//g' \
                -e ':a;N;$!ba;s/\n/\\n/g'
}

write_panel_json(){
        local tmp uptime_h load hostn ts_utc ts_mad i total
        tmp="$(mktemp "${private_panel_json}.XXXXXX")"
        uptime_h="$(uptime -p 2>/dev/null || true)"
        load="$(cut -d' ' -f1-3 /proc/loadavg 2>/dev/null || true)"
        hostn="$(host)"
        ts_utc="$(now_iso)"
        ts_mad="$(now_madrid)"

        total="${#PANEL_URLS[@]}"

        {
                printf '{'
                printf '"host":"%s",' "$(json_escape "$hostn")"
                printf '"ts":"%s",' "$(json_escape "$ts_utc")"
                printf '"ts_madrid":"%s",' "$(json_escape "$ts_mad")"
                printf '"uptime":"%s",' "$(json_escape "$uptime_h")"
                printf '"load":"%s",' "$(json_escape "$load")"
                printf '"fail_threshold":%s,' "$fail_threshold"
                printf '"sites":['

                for ((i=0;i<total;i++)); do
                        local base st fails lok lokm lfail lfailm code
                        base="${PANEL_URLS[$i]}"
                        st="$(get_status "$base")"
                        fails="$(get_fails "$base")"
                        lok="$(get_last_ok "$base")"
                        lokm="$(get_last_ok_madrid "$base")"
                        lfail="$(get_last_fail "$base")"
                        lfailm="$(get_last_fail_madrid "$base")"
                        code="$(get_code "$base")"

                        [ "$i" -gt 0 ] && printf ','
                        printf '{'
                        printf '"url":"%s",' "$(json_escape "$base")"
                        printf '"status":"%s",' "$(json_escape "$st")"
                        printf '"fails":%s,' "$fails"
                        printf '"maintenance":%s,' "$(cat "$state_dir/$(safe_name "$base").maintenance" 2>/dev/null || echo 0)"
                        printf '"last_ok":"%s",' "$(json_escape "$lok")"
                        printf '"last_ok_madrid":"%s",' "$(json_escape "$lokm")"
                        printf '"last_fail":"%s",' "$(json_escape "$lfail")"
                        printf '"last_fail_madrid":"%s",' "$(json_escape "$lfailm")"
                        printf '"code":"%s"' "$(json_escape "$code")"
                        printf '}'
                done

                printf ']}'
                printf '\n'
        } > "$tmp"

        chmod 0600 "$tmp"
        mv -f "$tmp" "$private_panel_json"
        tmp="$(mktemp "${panel_json}.XXXXXX")"
        cat "$private_panel_json" > "$tmp"
        chmod 0644 "$tmp"
        mv -f "$tmp" "$panel_json"
}

PANEL_URLS=()

total_hosts=0
failing_now=0
need_restart=0

for h in "${hosts[@]}"; do
        base="$(normalize_url "$h" || true)"
        [ -z "${base:-}" ] && continue

        total_hosts=$((total_hosts + 1))
        PANEL_URLS+=("$base")

        st="$(get_status "$base")"
        printf 0 > "$state_dir/$(safe_name "$base").maintenance"

        if maintenance_active "$base"; then
                printf 1 > "$state_dir/$(safe_name "$base").maintenance"
                log "MANTENIMIENTO url=$base sin acumular fallos ni reiniciar"
                continue
        fi

        if http_ok_home "$base"; then
                set_last_ok "$base" "$(now_iso)"
                set_last_ok_madrid "$base" "$(now_madrid)"

                if [ "$st" = "down" ]; then
                        send_mail "Servidor #recuperado [$(now_madrid)]" "$(server_brief)Estado: recuperado
URL: $base
"
                        log "EMAIL recovered url=$base"
                fi

                set_status "$base" "up"
                set_fails "$base" 0
                set_restart_done "$base" 0
                continue
        fi

        failing_now=$((failing_now + 1))

        set_last_fail "$base" "$(now_iso)"
        set_last_fail_madrid "$base" "$(now_madrid)"

        if [ "$st" = "down" ]; then
                log "FALLO url=$base estado=down code=$(get_code "$base")"
                continue
        fi

        fails=$(( $(get_fails "$base") + 1 ))
        set_fails "$base" "$fails"
        log "FALLO url=$base fallos=$fails/$fail_threshold code=$(get_code "$base")"

        if [ "$fails" -lt "$fail_threshold" ]; then
                continue
        fi

        set_status "$base" "down"
        set_fails "$base" 0

        if [ "$restart_once_per_outage" -eq 1 ]; then
                if [ "$(get_restart_done "$base")" -eq 1 ]; then
                        continue
                fi
                set_restart_done "$base" 1
        fi

        action_label="revision requerida; sin reinicio"
        if /usr/local/sbin/vps-service-control site-restart-allowed "$base" >/dev/null; then
                need_restart=1
                action_label="reinicio solicitado al control central"
        else
                log "REVISION_REQUERIDA url=$base reinicio=no"
        fi

        send_mail "Servidor #caido [$(now_madrid)]" "$(server_brief)Estado: caido
URL: $base
Accion: $action_label
"
        log "EMAIL down url=$base"
done

if [ "${#PANEL_URLS[@]}" -eq 0 ]; then
        log "NO_HOSTS"
        exit 0
fi

if [ "$need_restart" -eq 1 ]; then
        if [ "$total_hosts" -gt 0 ] && [ "$failing_now" -ge "$total_hosts" ]; then
                restart_units "${restart_units_global[@]}"
        else
                restart_units "${restart_units_single[@]}"
        fi

        for base in "${PANEL_URLS[@]}"; do
                st="$(get_status "$base")"

                if http_ok_home "$base"; then
                        set_last_ok "$base" "$(now_iso)"
                        set_last_ok_madrid "$base" "$(now_madrid)"
                        set_status "$base" "up"
                        set_fails "$base" 0
                        set_restart_done "$base" 0
                        continue
                fi

                set_last_fail "$base" "$(now_iso)"
                set_last_fail_madrid "$base" "$(now_madrid)"
                set_status "$base" "down"

                if [ "$st" = "up" ] && [ "$restart_once_per_outage" -eq 1 ]; then
                        set_restart_done "$base" 1
                fi
        done
fi

write_panel_json
heartbeat_once_per_hour
