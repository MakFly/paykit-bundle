#!/bin/sh
# Manage only the local example's HTTP servers. Compatible with Linux and macOS.
set -eu

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd -P)
STATE="$ROOT/.dev"
WEB_PORT=${WEB_PORT:-5184}
API_PORT=${API_PORT:-8087}
C_OK=${C_OK:-'\033[32m'}
C_WARN=${C_WARN:-'\033[33m'}
C_ERR=${C_ERR:-'\033[31m'}
C_RESET=${C_RESET:-'\033[0m'}

message() { printf '%b[make] %s%b\n' "$1" "$2" "$C_RESET"; }
fail() { message "$C_ERR" "$1" >&2; exit 1; }
valid_port() {
    case "$1" in ''|*[!0-9]*) return 1 ;; esac
    [ "$1" -ge 1024 ] && [ "$1" -le 65535 ]
}
listeners() { lsof -nP -t -iTCP:"$1" -sTCP:LISTEN 2>/dev/null || true; }
occupied() {
    [ -n "$(listeners "$1")" ] || php -r '
        $socket = @stream_socket_client("tcp://127.0.0.1:".$argv[1], $code, $message, 0.2);
        if ($socket) { fclose($socket); exit(0); } exit(1);
    ' "$1"
}
owned() {
    case "$1" in ''|*[!0-9]*) return 1 ;; esac
    process_cwd=$(lsof -a -p "$1" -d cwd -Fn 2>/dev/null | sed -n 's/^n//p')
    case "$process_cwd" in "$ROOT"|"$ROOT"/*) ;; *) return 1 ;; esac
    process_command=$(ps -p "$1" -o command= 2>/dev/null || true)
    case "$process_command" in
        *php*'-S 127.0.0.1:'*'public/index.php'*|*vite* ) return 0 ;;
        *) return 1 ;;
    esac
}
service_port() { if [ "$1" = api ]; then printf '%s' "$API_PORT"; else printf '%s' "$WEB_PORT"; fi; }
running() {
    [ -f "$STATE/$1.pid" ] || return 1
    running_pid=$(cat "$STATE/$1.pid")
    owned "$running_pid" || return 1
    [ -f "$STATE/$1.port" ] || return 1
    running_port=$(cat "$STATE/$1.port")
    valid_port "$running_port" || return 1
    listeners "$running_port" | awk -v pid="$running_pid" '$0 == pid { found = 1 } END { exit !found }'
}
stop_pid() {
    if ! owned "$1"; then return 0; fi
    kill -TERM "$1" 2>/dev/null || true
    attempts=0
    while owned "$1" && [ "$attempts" -lt 30 ]; do
        sleep 0.1
        attempts=$((attempts + 1))
    done
    if owned "$1"; then
        message "$C_WARN" "Arret force du serveur du projet (PID $1)."
        kill -KILL "$1" 2>/dev/null || true
    fi
}
stop_service() {
    service_name=$1
    if [ -f "$STATE/$service_name.pid" ]; then
        stop_pid "$(cat "$STATE/$service_name.pid")"
    fi
    service_ports=$(service_port "$service_name")
    if [ -f "$STATE/$service_name.port" ]; then
        service_ports="$service_ports $(cat "$STATE/$service_name.port")"
    fi
    for port in $service_ports; do
        valid_port "$port" || continue
        for pid in $(listeners "$port"); do
            if owned "$pid"; then
                stop_pid "$pid"
            else
                message "$C_WARN" "Port $port : PID $pid externe au projet, conserve."
            fi
        done
        for pid in $(listeners "$port"); do
            if owned "$pid"; then
                fail "Le serveur du projet PID $pid ecoute encore sur $port."
            fi
        done
    done
    rm -f "$STATE/$service_name.pid" "$STATE/$service_name.port"
}
show_status() {
    for name in api web; do
        if running "$name"; then
            message "$C_OK" "$name actif : http://127.0.0.1:$(cat "$STATE/$name.port") (PID $(cat "$STATE/$name.pid"))"
        else
            message "$C_WARN" "$name arrete (port configure : $(service_port "$name"))."
        fi
    done
}
check_ports() {
    result=0
    for name in api web; do
        port=$(service_port "$name")
        if occupied "$port"; then
            if running "$name" && [ "$(cat "$STATE/$name.port")" = "$port" ]; then
                message "$C_OK" "$name : port $port utilise par ce projet."
            else
                message "$C_ERR" "$name : port $port occupe. Choisis un autre port (WEB_PORT / API_PORT)."
                result=1
            fi
        else
            message "$C_OK" "$name : port $port libre."
        fi
    done
    return "$result"
}

valid_port "$WEB_PORT" && valid_port "$API_PORT" || fail 'Les ports doivent etre compris entre 1024 et 65535.'
[ "$WEB_PORT" != "$API_PORT" ] || fail 'React et Symfony doivent utiliser des ports differents.'
for tool in lsof php; do command -v "$tool" >/dev/null || fail "Commande requise : $tool."; done

case "${1:-}" in
    status) show_status; exit 0 ;;
    ports) check_ports; exit $? ;;
    start|stop) ;;
    *) fail 'Usage : dev.sh start|stop|status|ports' ;;
esac

mkdir -p "$STATE"
mkdir "$STATE/lock" 2>/dev/null || fail "Une commande dev/stop est deja en cours. Si elle a ete interrompue, retire $STATE/lock."
starting=0
cleanup() {
    exit_code=$?
    if [ "$starting" -eq 1 ]; then
        stop_service web
        stop_service api
    fi
    rmdir "$STATE/lock"
    return "$exit_code"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

if [ "$1" = stop ]; then
    stop_service web
    stop_service api
    message "$C_OK" 'Serveurs du projet arretes. Les autres services sont conserves.'
    exit 0
fi

check_ports || exit 1
if running api || running web; then
    if running api && running web && [ "$(cat "$STATE/api.port")" = "$API_PORT" ] && [ "$(cat "$STATE/web.port")" = "$WEB_PORT" ]; then
        show_status
        exit 0
    fi
    fail 'Un lancement precedent est encore actif. Execute make stop avant de relancer.'
fi
command -v bun >/dev/null || fail 'Bun est requis.'
[ -f "$ROOT/apps/api/vendor/autoload.php" ] && [ -f "$ROOT/apps/api/var/paykit.db" ] || fail 'Execute make install pour preparer Symfony et la base.'
VITE="$ROOT/apps/web/node_modules/vite/bin/vite.js"
if [ ! -f "$VITE" ]; then VITE="$ROOT/node_modules/vite/bin/vite.js"; fi
[ -f "$VITE" ] || fail 'Execute make install pour installer Vite.'

# Mark the ports before launching so interrupted startup remains recoverable.
starting=1
printf '%s\n' "$API_PORT" > "$STATE/api.port"
printf '%s\n' "$WEB_PORT" > "$STATE/web.port"
export WEB_PORT API_PORT
(
    cd "$ROOT/apps/api"
    exec nohup php -S "127.0.0.1:$API_PORT" -t public public/index.php
) > "$STATE/api.log" 2>&1 < /dev/null &
printf '%s\n' "$!" > "$STATE/api.pid"
(
    cd "$ROOT/apps/web"
    exec nohup bun --bun "$VITE" --host 127.0.0.1 --port "$WEB_PORT" --strictPort
) > "$STATE/web.log" 2>&1 < /dev/null &
printf '%s\n' "$!" > "$STATE/web.pid"

attempt=0
while [ "$attempt" -lt 100 ]; do
    if running api && running web; then
        starting=0
        show_status
        message "$C_OK" 'Logs : make logs. Arret : make stop.'
        exit 0
    fi
    sleep 0.1
    attempt=$((attempt + 1))
done
stop_service web
stop_service api
starting=0
fail "Demarrage incomplet, serveurs arretes. Consulte $STATE/api.log et $STATE/web.log."
